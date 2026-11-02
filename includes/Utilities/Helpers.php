<?php

namespace {
    // Prevent direct script execution
    if (!defined('ABSPATH')) {
        exit;
    }

    if (!function_exists('botplus_log')) {
        function botplus_log(string $message, array $context = [], string $level = 'info'): void
        {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $logMessage = sprintf('[%s] %s: %s', gmdate('Y-m-d H:i:s'), strtoupper($level), $message);
                if (!empty($context)) {
                    $logMessage .= ' ' . wp_json_encode($context);
                }
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Guarded by WP_DEBUG check above.
                error_log($logMessage);
            }
        }
    }

    if (!function_exists('botplus_is_woocommerce_active')) {
        function botplus_is_woocommerce_active(): bool
        {
            return function_exists('WC') || class_exists('WooCommerce');
        }
    }

    if (!function_exists('botplus_get_cart_data')) {
        function botplus_get_cart_data(): ?array
        {
            if (!botplus_is_woocommerce_active() || !function_exists('WC') || !WC()->cart) {
                return null;
            }

            $cart = WC()->cart;
            $items = [];

            foreach ($cart->get_cart() as $cartItem) {
                $product = $cartItem['data'];
                $items[] = [
                    'key' => $cartItem['key'],
                    'product_id' => $product->get_id(),
                    'variation_id' => $cartItem['variation_id'],
                    'quantity' => $cartItem['quantity'],
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'image' => $product->get_image_id() ? wp_get_attachment_url($product->get_image_id()) : '',
                    'permalink' => $product->get_permalink(),
                ];
            }

            return [
                'hash' => $cart->get_cart_hash(),
                'item_count' => $cart->get_cart_contents_count(),
                'subtotal' => $cart->get_subtotal(),
                'total' => $cart->get_total(),
                'items' => $items,
            ];
        }
    }

    if (!function_exists('botplus_get_user_context')) {
        function botplus_get_user_context(): ?array
        {
            $user = wp_get_current_user();
            
            if (!$user->exists()) {
                return null;
            }

            $context = [
                'id' => $user->ID,
                'email' => $user->user_email,
                'name' => $user->display_name,
                'roles' => $user->roles,
            ];

            if (botplus_is_woocommerce_active()) {
                $context['wc_customer_id'] = $user->ID;
                $context['billing_email'] = (function_exists('get_user_meta') ? get_user_meta($user->ID, 'billing_email', true) : null) ?: $user->user_email;
            }

            return $context;
        }
    }
}

namespace BotPlus\Plugin\Utilities {
    if (!function_exists('BotPlus\\Plugin\\Utilities\\botplus_is_woocommerce_active')) {
        function botplus_is_woocommerce_active(): bool
        {
            return \botplus_is_woocommerce_active();
        }
    }

    if (!function_exists('BotPlus\\Plugin\\Utilities\\botplus_log')) {
        function botplus_log(string $message, array $context = [], string $level = 'info'): void
        {
            \botplus_log($message, $context, $level);
        }
    }
}