<?php

namespace BotPlus\Plugin\Widget;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class ChatWidget
{
    public function init(): void
    {
        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);

        if (!$config->isConnected() || !$config->getChatbotId()) {
            return;
        }

        // Enqueue widget assets
        add_action('wp_enqueue_scripts', function () use ($config) {
            $this->enqueueAssets($config);
        }, 20);

        // Render widget mount point in footer
        add_action('wp_footer', function () use ($config) {
            $this->renderWidget($config);
        }, 99);
    }

    protected function enqueueAssets(\BotPlus\Plugin\Core\Config $config): void
    {
        // Enqueue lightweight vanilla iframe loader (minified build with source fallback)
        $loaderUrl = file_exists(BOTPLUS_PLUGIN_DIR . 'build/botplus-loader.min.js')
            ? BOTPLUS_PLUGIN_URL . 'build/botplus-loader.min.js'
            : BOTPLUS_PLUGIN_URL . 'assets/js/loader.js';

        wp_enqueue_script(
            'botplus-loader',
            $loaderUrl,
            [],
            BOTPLUS_VERSION,
            true
        );

        // Localize config for iframe loader
        wp_localize_script('botplus-loader', 'BotPlusConfig', [
            'chatbotId'     => $config->getChatbotId(),
            'apiUrl'        => $config->getBotPlusApiUrl(),
            'siteUrl'       => function_exists('home_url') ? home_url() : '',
            'restUrl'       => function_exists('rest_url') ? rest_url('botplus/v1/') : '',
            'nonce'         => function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '',
            'isWooCommerce' => function_exists('botplus_is_woocommerce_active') && botplus_is_woocommerce_active(),
            'cartToken'     => function_exists('WC') && WC()->cart ? WC()->cart->get_cart_hash() : null,
            'connected'     => $config->isConnected(),
            'widgetSettings'=> $config->getWidgetSettings(),
            'user'          => $this->getUserContext(),
            'cart'          => $this->getCartContext(),
        ]);
    }

    protected function renderWidget(\BotPlus\Plugin\Core\Config $config): void
    {
        if (!$config->isConnected() || !$config->getChatbotId()) {
            return;
        }

        echo '<div id="botplus-chat-widget" data-chatbot-id="' . esc_attr($config->getChatbotId()) . '"></div>';
    }

    protected function getUserContext(): ?array
    {
        $user = wp_get_current_user();

        if (!$user->exists()) {
            return null;
        }

        return [
            'id' => $user->ID,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'wc_customer_id' => $user->ID,
        ];
    }

    protected function getCartContext(): ?array
    {
        if (!function_exists('WC') || !WC()->cart) {
            return null;
        }

        $cart = WC()->cart;
        $rawCart = $cart->get_cart();
        if (empty($rawCart)) {
            return null;
        }

        $items = [];
        foreach ($rawCart as $key => $cartItem) {
            $product = $cartItem['data'] ?? null;
            if (!is_object($product)) {
                continue;
            }

            $productId = (int) ($cartItem['product_id'] ?? 0);
            $variationId = (int) ($cartItem['variation_id'] ?? 0);
            $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));
            $price = method_exists($product, 'get_price') ? (float) $product->get_price() : 0.0;
            $lineTotal = isset($cartItem['line_total']) ? (float) $cartItem['line_total'] : ($price * $quantity);

            $productTitle = method_exists($product, 'get_title') ? $product->get_title() : '';
            $title = method_exists($product, 'get_name') ? $product->get_name() : $productTitle;

            $items[] = [
                'id'            => $variationId > 0 ? $variationId : $productId,
                'key'           => (string) $key,
                'product_id'    => $productId,
                'variant_id'    => $variationId > 0 ? $variationId : $productId,
                'title'         => $title,
                'product_title' => $productTitle ?: $title,
                'sku'           => method_exists($product, 'get_sku') ? (string) $product->get_sku() : '',
                'quantity'      => $quantity,
                'price'         => $price,
                'line_total'    => $lineTotal,
                'url'           => method_exists($product, 'get_permalink') ? (string) $product->get_permalink() : '',
            ];
        }

        return [
            'cart_hash'       => (string) $cart->get_cart_hash(),
            'currency'        => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            'item_count'      => (int) $cart->get_cart_contents_count(),
            'subtotal_amount' => (float) $cart->get_subtotal(),
            'items'           => $items,
        ];
    }
}
