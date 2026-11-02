<?php

namespace BotPlus\Plugin\Utilities;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class CapabilityDetector
{
    /**
     * Inspect WordPress runtime environment and return site capabilities and content stats.
     */
    public static function detect(): array
    {
        $isWoo = function_exists('botplus_is_woocommerce_active')
            ? botplus_is_woocommerce_active()
            : (function_exists('WC') && class_exists('WooCommerce'));

        $counts = [
            'products' => 0,
            'pages' => 0,
            'posts' => 0,
        ];

        if (function_exists('wp_count_posts')) {
            $pagesCount = wp_count_posts('page');
            $counts['pages'] = isset($pagesCount->publish) ? (int) $pagesCount->publish : 0;

            $postsCount = wp_count_posts('post');
            $counts['posts'] = isset($postsCount->publish) ? (int) $postsCount->publish : 0;

            if ($isWoo) {
                $productsCount = wp_count_posts('product');
                $counts['products'] = isset($productsCount->publish) ? (int) $productsCount->publish : 0;
            }
        }

        $currency = null;
        $currencySymbol = null;
        $wcVersion = null;

        if ($isWoo) {
            $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';
            $currencySymbol = function_exists('get_woocommerce_currency_symbol')
                ? html_entity_decode((string) get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : '$';
            $wcVersion = defined('WC_VERSION') ? WC_VERSION : (function_exists('WC') && isset(WC()->version) ? WC()->version : null);
        }

        return [
            'site_type'             => $isWoo ? 'woocommerce_store' : 'wordpress_content',
            'is_woocommerce'        => $isWoo,
            'wp_version'            => function_exists('get_bloginfo') ? get_bloginfo('version') : null,
            'plugin_version'        => defined('BOTPLUS_VERSION') ? BOTPLUS_VERSION : '1.0.0',
            'wc_version'            => $wcVersion,
            'currency'              => $currency,
            'currency_symbol'       => $currencySymbol,
            'has_cart_tracking'     => $isWoo,
            'has_track_order'       => $isWoo,
            'has_lead_intelligence' => true,
            'counts'                => $counts,
            'detected_plugins'      => [
                'woocommerce' => $isWoo,
                'yoast_seo'   => defined('WPSEO_VERSION'),
                'elementor'   => (function_exists('did_action') && did_action('elementor/loaded')) || defined('ELEMENTOR_VERSION'),
            ],
        ];
    }
}
