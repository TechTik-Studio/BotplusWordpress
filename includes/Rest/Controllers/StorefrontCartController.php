<?php

namespace BotPlus\Plugin\Rest\Controllers;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class StorefrontCartController
{
    /**
     * Handles retrieving the active customer cart state for storefront context bridging.
     */
    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $cartData = $this->getCartData();

        // Dynamic security token to replace stale nonces on cached pages
        $cartData['nonce'] = function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : null;

        // Dynamic authenticated customer context (bypasses full-page caching)
        $user = null;
        if (function_exists('wp_get_current_user')) {
            $currentUser = wp_get_current_user();
            if ($currentUser && $currentUser->exists()) {
                $user = [
                    'id'         => (int) $currentUser->ID,
                    'email'      => (string) $currentUser->user_email,
                    'name'       => trim($currentUser->first_name . ' ' . $currentUser->last_name) ?: $currentUser->display_name,
                    'first_name' => $currentUser->first_name ?: $currentUser->display_name,
                    'logged_in'  => true,
                ];
            }
        }
        $cartData['user'] = $user;

        return $this->buildResponse($cartData);
    }

    /**
     * Handles modifying the active customer cart state (add, update, remove) from embed chat.
     */
    public function handleAction(\WP_REST_Request $request): \WP_REST_Response
    {
        $isWcActive = function_exists('botplus_is_woocommerce_active')
            ? botplus_is_woocommerce_active()
            : (class_exists('WooCommerce') || function_exists('WC'));

        if (!$isWcActive) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'WooCommerce is not active.',
            ], 400);
        }

        $isLoggedIn = function_exists('is_user_logged_in') && is_user_logged_in();
        $nonce = method_exists($request, 'get_header') ? $request->get_header('x_wp_nonce') : null;

        if ($isLoggedIn) {
            if (empty($nonce) || !function_exists('wp_verify_nonce') || !wp_verify_nonce((string) $nonce, 'wp_rest')) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Invalid or missing security token for authenticated user.',
                ], 403);
            }
        } elseif (!empty($nonce) && function_exists('wp_verify_nonce') && !wp_verify_nonce((string) $nonce, 'wp_rest')) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid security token.',
            ], 403);
        }

        $this->ensureWooCommerceCartLoaded();

        if (!function_exists('WC') || !WC()->cart) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'WooCommerce cart not initialized.',
            ], 500);
        }

        $wcCart = WC()->cart;
        $action = sanitize_text_field((string) ($request->get_param('action') ?: ''));
        $productId = (int) ($request->get_param('productId') ?: $request->get_param('product_id') ?: 0);
        $variantId = (int) ($request->get_param('variantId') ?: $request->get_param('variant_id') ?: 0);
        $lineKey = sanitize_text_field((string) ($request->get_param('lineKey') ?: $request->get_param('line_key') ?: ''));
        $rawQty = $request->get_param('quantity');
        $quantity = $rawQty !== null ? (int) $rawQty : 1;

        try {
            if ($action === 'add') {
                if ($variantId > 0 && $variantId !== $productId) {
                    $parentProductId = $productId > 0
                        ? $productId
                        : (function_exists('wp_get_post_parent_id') ? (int) wp_get_post_parent_id($variantId) : $variantId);

                    $wcCart->add_to_cart($parentProductId, max(1, $quantity), $variantId);
                } else {
                    $targetId = $productId > 0 ? $productId : $variantId;
                    if ($targetId <= 0) {
                        return new \WP_REST_Response([
                            'success' => false,
                            'message' => 'Invalid product or variant ID.',
                        ], 400);
                    }
                    $wcCart->add_to_cart($targetId, max(1, $quantity));
                }
            } elseif ($action === 'update') {
                $cartItems = $wcCart->get_cart();
                if (!empty($lineKey) && isset($cartItems[$lineKey])) {
                    if ($quantity <= 0) {
                        $wcCart->remove_cart_item($lineKey);
                    } else {
                        $wcCart->set_quantity($lineKey, $quantity);
                    }
                } elseif ($variantId > 0 || $productId > 0) {
                    $targetId = $variantId > 0 ? $variantId : $productId;
                    foreach ($cartItems as $key => $item) {
                        if (($item['variation_id'] ?? 0) === $targetId || ($item['product_id'] ?? 0) === $targetId) {
                            if ($quantity <= 0) {
                                $wcCart->remove_cart_item($key);
                            } else {
                                $wcCart->set_quantity($key, $quantity);
                            }
                            break;
                        }
                    }
                }
            } elseif ($action === 'remove') {
                $cartItems = $wcCart->get_cart();
                if (!empty($lineKey) && isset($cartItems[$lineKey])) {
                    $wcCart->remove_cart_item($lineKey);
                } elseif ($variantId > 0 || $productId > 0) {
                    $targetId = $variantId > 0 ? $variantId : $productId;
                    foreach ($cartItems as $key => $item) {
                        if (($item['variation_id'] ?? 0) === $targetId || ($item['product_id'] ?? 0) === $targetId) {
                            $wcCart->remove_cart_item($key);
                            break;
                        }
                    }
                }
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Unsupported cart action.',
                ], 400);
            }

            // Recalculate totals
            $wcCart->calculate_totals();

            // Persist into session if active
            if (WC()->session && method_exists(WC()->session, 'set')) {
                WC()->session->set('cart', $wcCart->get_cart_for_session());
            }

            $updatedCart = $this->getCartData();

            return $this->buildResponse([
                'success' => true,
                'cart'    => $updatedCart,
            ]);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extracts and normalizes the current WooCommerce cart state.
     */
    public function getCartData(): array
    {
        $isWcActive = function_exists('botplus_is_woocommerce_active')
            ? botplus_is_woocommerce_active()
            : (class_exists('WooCommerce') || function_exists('WC'));

        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';

        $emptyResponse = [
            'cart_hash'       => '',
            'currency'        => $currency,
            'item_count'      => 0,
            'subtotal_amount' => 0.0,
            'items'           => [],
        ];

        if (!$isWcActive) {
            return $emptyResponse;
        }

        $this->ensureWooCommerceCartLoaded();

        if (!function_exists('WC') || !WC()->cart) {
            return $emptyResponse;
        }

        $wcCart = WC()->cart;
        $rawCart = $wcCart->get_cart();
        if (empty($rawCart) && !empty($wcCart->session) && method_exists($wcCart->session, 'get_cart_from_session')) {
            $wcCart->session->get_cart_from_session();
            $rawCart = $wcCart->get_cart();
        }
        $wcCart->calculate_totals();

        $cartHash = (string) $wcCart->get_cart_hash();
        $itemCount = (int) $wcCart->get_cart_contents_count();
        $subtotal = (float) $wcCart->get_subtotal();

        $items = [];

        if (is_array($rawCart) && !empty($rawCart)) {
            foreach ($rawCart as $key => $cartItem) {
                $product = $cartItem['data'] ?? null;
                if (!is_object($product)) {
                    continue;
                }

                $productId = (int) ($cartItem['product_id'] ?? 0);
                $variationId = (int) ($cartItem['variation_id'] ?? 0);
                $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));

                $productTitle = method_exists($product, 'get_title') ? $product->get_title() : '';
                $title = method_exists($product, 'get_name') ? $product->get_name() : $productTitle;
                $variantTitle = '';

                if ($variationId > 0 && !empty($cartItem['variation'])) {
                    $attributes = [];
                    foreach ($cartItem['variation'] as $attrKey => $attrVal) {
                        $taxonomy = str_replace('attribute_', '', $attrKey);
                        $term = function_exists('get_term_by') ? get_term_by('slug', $attrVal, $taxonomy) : null;
                        $label = ($term && !is_wp_error($term)) ? $term->name : $attrVal;
                        $attributes[] = $label;
                    }
                    $variantTitle = implode(' / ', $attributes);
                }

                $imageUrl = '';
                $imageId = method_exists($product, 'get_image_id') ? $product->get_image_id() : 0;
                if ($imageId && function_exists('wp_get_attachment_image_url')) {
                    $imageUrl = (string) wp_get_attachment_image_url($imageId, 'medium');
                }
                if (empty($imageUrl) && $productId > 0 && function_exists('get_post_thumbnail_id')) {
                    $parentImageId = get_post_thumbnail_id($productId);
                    if ($parentImageId && function_exists('wp_get_attachment_image_url')) {
                        $imageUrl = (string) wp_get_attachment_image_url($parentImageId, 'medium');
                    }
                }

                $displayTax = function_exists('wc_prices_include_tax') && wc_prices_include_tax();
                if ($displayTax && function_exists('wc_get_price_including_tax')) {
                    $price = (float) wc_get_price_including_tax($product);
                    $lineTotal = isset($cartItem['line_subtotal']) && isset($cartItem['line_subtotal_tax'])
                        ? (float) ($cartItem['line_subtotal'] + $cartItem['line_subtotal_tax'])
                        : ($price * $quantity);
                } else {
                    $price = method_exists($product, 'get_price') ? (float) $product->get_price() : 0.0;
                    $lineTotal = isset($cartItem['line_total']) ? (float) $cartItem['line_total'] : ($price * $quantity);
                }
                $sku = method_exists($product, 'get_sku') ? (string) $product->get_sku() : '';
                $permalink = method_exists($product, 'get_permalink') ? (string) $product->get_permalink() : '';

                $items[] = [
                    'id'            => $variationId > 0 ? $variationId : $productId,
                    'key'           => (string) $key,
                    'product_id'    => $productId,
                    'variant_id'    => $variationId > 0 ? $variationId : $productId,
                    'title'         => $title,
                    'product_title' => $productTitle ?: $title,
                    'variant_title' => $variantTitle,
                    'sku'           => $sku,
                    'quantity'      => $quantity,
                    'price'         => $price,
                    'line_total'    => $lineTotal,
                    'image'         => $imageUrl,
                    'url'           => $permalink,
                ];
            }
        }

        return [
            'cart_hash'       => $cartHash,
            'currency'        => $currency,
            'item_count'      => $itemCount,
            'subtotal_amount' => $subtotal,
            'items'           => $items,
        ];
    }

    /**
     * Ensures the WooCommerce session handler and cart are active for REST requests.
     */
    protected function ensureWooCommerceCartLoaded(): void
    {
        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }

        if (function_exists('WC')) {
            if (null === WC()->session && class_exists('WC_Session_Handler')) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Invoking official WooCommerce filter hook.
                $sessionClass = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');
                WC()->session = new $sessionClass();
                WC()->session->init();
            }

            if (null === WC()->customer && class_exists('WC_Customer') && function_exists('get_current_user_id')) {
                WC()->customer = new \WC_Customer(get_current_user_id(), true);
            }

            if (null === WC()->cart && class_exists('WC_Cart')) {
                WC()->cart = new \WC_Cart();
            }

            if (WC()->cart) {
                if (empty(WC()->cart->get_cart()) && !empty(WC()->cart->session) && method_exists(WC()->cart->session, 'get_cart_from_session')) {
                    WC()->cart->session->get_cart_from_session();
                }
                WC()->cart->calculate_totals();
            }
        }
    }

    /**
     * Builds standard REST response with anti-caching headers.
     */
    protected function buildResponse(array $data): \WP_REST_Response
    {
        $response = new \WP_REST_Response($data, 200);
        $response->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');

        return $response;
    }

    /**
     * Parses items payload from array, JSON string, or id:qty:vid compact notation.
     */
    public function parseItemsPayload(mixed $items): array
    {
        if (empty($items)) {
            return [];
        }

        if (is_string($items)) {
            $trimmed = trim($items);
            if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return isset($decoded[0]) ? $decoded : [$decoded];
                }
            }

            // Compact format: "101:2,102:1" or "101:2:55" (pid:qty:vid)
            $parsed = [];
            $segments = explode(',', $trimmed);
            foreach ($segments as $seg) {
                $parts = explode(':', trim($seg));
                if (!empty($parts[0]) && is_numeric($parts[0])) {
                    $pid = (int) $parts[0];
                    $qty = isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : 1;
                    $vid = isset($parts[2]) && is_numeric($parts[2]) ? (int) $parts[2] : 0;
                    $parsed[] = [
                        'product_id'   => $pid,
                        'quantity'     => max(1, $qty),
                        'variation_id' => $vid,
                    ];
                }
            }
            return $parsed;
        }

        if (is_array($items)) {
            return $items;
        }

        return [];
    }

    /**
     * Restores normalized item definitions into the active WooCommerce cart session.
     */
    public function restoreItemsIntoCart(mixed $items): bool
    {
        $isWcActive = function_exists('botplus_is_woocommerce_active')
            ? botplus_is_woocommerce_active()
            : (class_exists('WooCommerce') || function_exists('WC'));

        if (!$isWcActive) {
            return false;
        }

        $this->ensureWooCommerceCartLoaded();

        $wcCart = function_exists('WC') && is_object(WC()) ? WC()->cart : null;
        if (!$wcCart) {
            return false;
        }

        $parsedItems = $this->parseItemsPayload($items);
        if (empty($parsedItems)) {
            return false;
        }

        foreach ($parsedItems as $item) {
            $pid = (int) ($item['product_id'] ?? ($item['id'] ?? 0));
            $vid = (int) ($item['variation_id'] ?? ($item['variant_id'] ?? 0));
            $qty = max(1, (int) ($item['quantity'] ?? ($item['qty'] ?? 1)));

            if ($vid > 0 && $vid !== $pid) {
                $wcCart->add_to_cart($pid, $qty, $vid);
            } elseif ($pid > 0) {
                $wcCart->add_to_cart($pid, $qty);
            }
        }

        if (method_exists($wcCart, 'calculate_totals')) {
            $wcCart->calculate_totals();
        }

        if (function_exists('WC') && WC()->session && method_exists(WC()->session, 'set')) {
            WC()->session->set('cart', $wcCart->get_cart_for_session());
        }

        return true;
    }

    /**
     * Handles frontend template_redirect to automatically restore abandoned cart items and redirect to checkout.
     */
    public function handleTemplateRedirectRestoration(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public cart recovery link clicked from external email/SMS notification.
        $hasItems = !empty($_GET['botplus_cart_items']);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $hasCartHash = !empty($_GET['botplus_restore_cart']);

        if (!$hasItems && !$hasCartHash) {
            return;
        }

        if ($hasItems) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $rawItems = sanitize_text_field(wp_unslash($_GET['botplus_cart_items']));
            $this->restoreItemsIntoCart($rawItems);
        }

        $checkoutUrl = function_exists('wc_get_checkout_url')
            ? wc_get_checkout_url()
            : (function_exists('home_url') ? home_url('/checkout/') : '/checkout/');

        if (function_exists('wp_safe_redirect')) {
            wp_safe_redirect($checkoutUrl);
            if (!defined('BOTPLUS_PHPUNIT_TESTING')) {
                exit;
            }
        }
    }

    /**
     * Handles restoring a cart payload and resolving the WooCommerce checkout redirect URL.
     */
    public function handleRestore(\WP_REST_Request $request): \WP_REST_Response
    {
        $isWcActive = function_exists('botplus_is_woocommerce_active')
            ? botplus_is_woocommerce_active()
            : (class_exists('WooCommerce') || function_exists('WC'));

        if (!$isWcActive) {
            return new \WP_REST_Response(['success' => false, 'message' => 'WooCommerce is not active.'], 400);
        }

        $items = $request->get_param('items') ?: $request->get_param('botplus_cart_items');
        if (!empty($items)) {
            $this->restoreItemsIntoCart($items);
        }

        $checkoutUrl = function_exists('wc_get_checkout_url')
            ? wc_get_checkout_url()
            : (function_exists('home_url') ? home_url('/checkout/') : '/checkout/');

        $acceptHeader = method_exists($request, 'get_header') ? strtolower((string) $request->get_header('accept')) : '';
        $shouldRedirect = (bool) $request->get_param('redirect')
            || (str_contains($acceptHeader, 'text/html') && !str_contains($acceptHeader, 'application/json'));

        if ($shouldRedirect && function_exists('wp_safe_redirect')) {
            wp_safe_redirect($checkoutUrl);
            if (!defined('BOTPLUS_PHPUNIT_TESTING')) {
                exit;
            }
            return new \WP_REST_Response([
                'success'      => true,
                'redirected'   => true,
                'checkout_url' => $checkoutUrl,
            ], 302);
        }

        return new \WP_REST_Response([
            'success'      => true,
            'checkout_url' => $checkoutUrl,
            'cart'         => $this->getCartData(),
        ]);
    }
}

