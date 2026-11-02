<?php

namespace BotPlus\Plugin\Rest\Controllers;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class StorefrontOrderController
{
    /**
     * Handles live order tracking requests with customer verification.
     */
    public function handleTrack(\WP_REST_Request $request): \WP_REST_Response
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

        // Verify caller authentication: either server Bearer token or customer session/nonce
        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $expectedToken = $config ? $config->getJwtToken() : null;
        $authHeader = method_exists($request, 'get_header') ? (string) $request->get_header('authorization') : '';
        $bearerToken = '';
        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $bearerToken = $matches[1];
        }

        $isServerAuthorized = !empty($expectedToken) && !empty($bearerToken) && hash_equals($expectedToken, $bearerToken);
        $nonce = method_exists($request, 'get_header') ? $request->get_header('x_wp_nonce') : null;
        $isSessionAuthorized = (function_exists('is_user_logged_in') && is_user_logged_in())
            || (!empty($nonce) && function_exists('wp_verify_nonce') && wp_verify_nonce((string) $nonce, 'wp_rest'));

        if (!empty($expectedToken) && !$isServerAuthorized && !$isSessionAuthorized) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Unauthorized order tracking request.',
            ], 401);
        }

        $orderNumber = trim((string) ($request->get_param('order_number') ?: $request->get_param('orderNumber') ?: ''));
        $email = strtolower(trim((string) ($request->get_param('email') ?: '')));
        $customerId = (int) ($request->get_param('customer_id') ?: $request->get_param('customerId') ?: 0);

        if ($orderNumber === '') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Order number is required.',
            ], 400);
        }

        $order = $this->findOrder($orderNumber);
        if (!$order) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => "Order #{$orderNumber} was not found.",
            ], 404);
        }

        // Verify customer access
        $isVerified = $this->verifyOwnership($order, $email, $customerId);
        if (!$isVerified) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Could not verify order access. Please provide the checkout email used for this order.',
                'requires_verification' => true,
            ], 403);
        }

        $data = $this->buildOrderPayload($order);

        return new \WP_REST_Response([
            'success' => true,
            'order'   => $data,
        ], 200);
    }

    /**
     * Resolves a WooCommerce order by ID or order number.
     */
    public function findOrder(string $orderNumber): ?\WC_Order
    {
        if (!function_exists('wc_get_order')) {
            return null;
        }

        // 1. Direct lookup by numeric ID
        $cleanDigits = preg_replace('/\D+/', '', $orderNumber);
        if (!empty($cleanDigits) && is_numeric($cleanDigits)) {
            $order = wc_get_order((int) $cleanDigits);
            if ($order && is_a($order, 'WC_Order')) {
                return $order;
            }
        }

        // 2. Lookup via wc_get_orders by order_number
        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'limit'        => 1,
                'order_number' => $orderNumber,
            ]);
            if (!empty($orders) && is_a($orders[0], 'WC_Order')) {
                return $orders[0];
            }

            // Also search by clean digits if alphanumeric
            if (!empty($cleanDigits) && $cleanDigits !== $orderNumber) {
                $orders = wc_get_orders([
                    'limit'        => 1,
                    'order_number' => $cleanDigits,
                ]);
                if (!empty($orders) && is_a($orders[0], 'WC_Order')) {
                    return $orders[0];
                }
            }
        }

        // 3. Sequential order number lookup (HPOS compatible via wc_get_orders, fallback to get_posts)
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Needed to resolve custom sequential order number plugins with limit 1.
        if (function_exists('wc_get_orders')) {
            $metaOrders = wc_get_orders([
                'limit'        => 1,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'meta_query'   => [
                    'relation' => 'OR',
                    [
                        'key'     => '_order_number',
                        'value'   => $orderNumber,
                        'compare' => '=',
                    ],
                    [
                        'key'     => '_order_number_formatted',
                        'value'   => $orderNumber,
                        'compare' => '=',
                    ],
                ],
            ]);
            if (!empty($metaOrders) && is_a($metaOrders[0], 'WC_Order')) {
                // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                return $metaOrders[0];
            }
        }

        if (function_exists('get_posts')) {
            $posts = get_posts([
                'post_type'      => ['shop_order', 'shop_order_placehold'],
                'post_status'    => 'any',
                'posts_per_page' => 1,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => '_order_number',
                        'value'   => $orderNumber,
                        'compare' => '=',
                    ],
                    [
                        'key'     => '_order_number_formatted',
                        'value'   => $orderNumber,
                        'compare' => '=',
                    ],
                ],
            ]);

            if (!empty($posts)) {
                $order = wc_get_order($posts[0]->ID);
                if ($order && is_a($order, 'WC_Order')) {
                    // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    return $order;
                }
            }
        }
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

        return null;
    }

    /**
     * Verifies that the requester owns or is authorized to view this order.
     */
    public function verifyOwnership(\WC_Order $order, string $email, int $customerId): bool
    {
        $billingEmail = strtolower(trim((string) $order->get_billing_email()));

        // Logged-in customer ID match
        if ($customerId > 0) {
            $orderCustomerId = (int) $order->get_customer_id();
            if ($orderCustomerId > 0 && $orderCustomerId === $customerId) {
                return true;
            }
        }

        // Billing email match
        if ($email !== '' && $billingEmail !== '') {
            return hash_equals($billingEmail, $email);
        }

        return false;
    }

    /**
     * Extracts canonical order details, line items, and tracking info.
     */
    public function buildOrderPayload(\WC_Order $order): array
    {
        $orderId = $order->get_id();
        $orderNumber = $order->get_order_number();
        $status = $order->get_status();
        $statusLabel = function_exists('wc_get_order_status_name')
            ? wc_get_order_status_name($status)
            : ucfirst($status);

        $dateCreated = $order->get_date_created()
            ? $order->get_date_created()->date('Y-m-d H:i:s')
            : null;

        $currency = $order->get_currency();
        $total = (float) $order->get_total();
        $subtotal = (float) $order->get_subtotal();

        // Fulfillment status mapping
        $fulfillmentStatus = match ($status) {
            'completed' => 'fulfilled',
            'processing' => 'processing',
            'on-hold' => 'on_hold',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'failed',
            default => 'unfulfilled',
        };

        // Financial status mapping
        $financialStatus = $order->is_paid()
            ? 'paid'
            : ($status === 'refunded' ? 'refunded' : 'pending');

        $lineItems = $this->extractLineItems($order);
        $tracking = $this->extractTrackingData($order);

        $billing = method_exists($order, 'get_address') ? $order->get_address('billing') : [
            'first_name' => method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : '',
            'last_name'  => method_exists($order, 'get_billing_last_name') ? $order->get_billing_last_name() : '',
            'city'       => method_exists($order, 'get_billing_city') ? $order->get_billing_city() : '',
            'country'    => method_exists($order, 'get_billing_country') ? $order->get_billing_country() : '',
            'email'      => method_exists($order, 'get_billing_email') ? $order->get_billing_email() : '',
            'phone'      => method_exists($order, 'get_billing_phone') ? $order->get_billing_phone() : '',
        ];

        $shipping = method_exists($order, 'get_address') ? $order->get_address('shipping') : [
            'first_name' => method_exists($order, 'get_shipping_first_name') ? $order->get_shipping_first_name() : '',
            'last_name'  => method_exists($order, 'get_shipping_last_name') ? $order->get_shipping_last_name() : '',
            'city'       => method_exists($order, 'get_shipping_city') ? $order->get_shipping_city() : '',
            'country'    => method_exists($order, 'get_shipping_country') ? $order->get_shipping_country() : '',
        ];

        $paymentMethod = method_exists($order, 'get_payment_method') ? $order->get_payment_method() : null;
        $paymentMethodTitle = method_exists($order, 'get_payment_method_title') ? $order->get_payment_method_title() : null;

        return [
            'id'                   => $orderId,
            'name'                 => "Order #{$orderNumber}",
            'order_number'         => (string) $orderNumber,
            'status'               => $status,
            'status_label'         => $statusLabel,
            'fulfillment_status'   => $fulfillmentStatus,
            'financial_status'     => $financialStatus,
            'currency'             => $currency,
            'total'                => $total,
            'total_amount'         => $total,
            'subtotal'             => $subtotal,
            'subtotal_amount'      => $subtotal,
            'created_at'           => $dateCreated,
            'placed_at'            => $dateCreated,
            'customer_id'          => (int) $order->get_customer_id(),
            'billing_email'        => method_exists($order, 'get_billing_email') ? $order->get_billing_email() : null,
            'payment_method'       => $paymentMethod,
            'payment_method_title' => $paymentMethodTitle,
            'billing'              => $billing,
            'shipping'             => $shipping,
            'tracking'             => $tracking,
            'tracking_items'       => $tracking ? [$tracking] : [],
            'line_items'           => $lineItems,
        ];
    }

    /**
     * Extracts line items with quantities, pricing, and product thumbnail URLs.
     */
    public function extractLineItems(\WC_Order $order): array
    {
        $items = [];

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $imageUrl = null;

            if ($product && function_exists('wp_get_attachment_image_url')) {
                $imageId = $product->get_image_id();
                if ($imageId) {
                    $imageUrl = wp_get_attachment_image_url($imageId, 'thumbnail') ?: null;
                }
            }

            $items[] = [
                'product_id' => $item->get_product_id(),
                'variant_id' => $item->get_variation_id() ?: $item->get_product_id(),
                'name'       => $item->get_name(),
                'quantity'   => (int) $item->get_quantity(),
                'subtotal'   => (float) $item->get_subtotal(),
                'total'      => (float) $item->get_total(),
                'image_url'  => $imageUrl,
            ];
        }

        return $items;
    }

    /**
     * Extracts shipment tracking details from WooCommerce Shipment Tracking, AST, or order meta.
     * Uses HPOS order meta methods ($order->get_meta()) with fallback to get_post_meta().
     */
    public function extractTrackingData(\WC_Order $order): ?array
    {
        $orderId = $order->get_id();

        $getMeta = function (string $key) use ($order, $orderId) {
            if (method_exists($order, 'get_meta')) {
                $val = $order->get_meta($key, true);
                if ($val !== '' && $val !== null && $val !== false) {
                    return $val;
                }
            }
            if (function_exists('get_post_meta')) {
                return get_post_meta($orderId, $key, true);
            }
            return null;
        };

        // 1. Official WooCommerce Shipment Tracking Plugin / AST meta
        $trackingItems = $getMeta('_wc_shipment_tracking_items');
        if (!empty($trackingItems) && is_array($trackingItems)) {
            $latest = end($trackingItems);
            if (!empty($latest) && is_array($latest)) {
                $carrier = $latest['custom_tracking_provider'] ?? ($latest['tracking_provider'] ?? 'Carrier');
                $number = $latest['tracking_number'] ?? '';
                $url = $latest['custom_tracking_link'] ?? ($latest['formatted_tracking_link'] ?? null);

                if (!empty($number)) {
                    return [
                        'company'      => (string) $carrier,
                        'number'       => (string) $number,
                        'url'          => $url ? (string) $url : null,
                        'date_shipped' => $latest['date_shipped'] ?? null,
                    ];
                }
            }
        }

        // 2. Generic carrier meta keys (HPOS and postmeta compatible)
        $genericNumber = $getMeta('_tracking_number') ?: $getMeta('_shipping_tracking_number');

        if (!empty($genericNumber)) {
            $genericCarrier = $getMeta('_tracking_provider') ?: ($getMeta('_shipping_provider') ?: 'Carrier');
            $genericUrl = $getMeta('_tracking_url') ?: null;

            return [
                'company'      => (string) $genericCarrier,
                'number'       => (string) $genericNumber,
                'url'          => $genericUrl ? (string) $genericUrl : null,
                'date_shipped' => null,
            ];
        }

        // 3. Fallback: Check customer notes for tracking links
        if (function_exists('wc_get_order_notes')) {
            $notes = wc_get_order_notes([
                'order_id' => $orderId,
                'type'     => 'customer',
            ]);

            foreach ($notes as $note) {
                $content = $note->content ?? '';
                if (preg_match('/track(?:ing)?\s*(?:#|number|code)?\s*[:\-]?\s*([A-Za-z0-9_-]{5,30})/i', $content, $matches)) {
                    $extractedNumber = $matches[1];
                    preg_match('/(https?:\/\/[^\s]+)/i', $content, $urlMatches);
                    $extractedUrl = $urlMatches[1] ?? null;

                    return [
                        'company'      => 'Carrier',
                        'number'       => $extractedNumber,
                        'url'          => $extractedUrl,
                        'date_shipped' => $note->date_created ? $note->date_created->date('Y-m-d') : null,
                    ];
                }
            }
        }

        return null;
    }
}

