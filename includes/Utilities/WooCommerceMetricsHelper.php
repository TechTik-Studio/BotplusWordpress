<?php

namespace BotPlus\Plugin\Utilities;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceMetricsHelper
{
    public static function isWooCommerceActive(): bool
    {
        if (defined('WC_VERSION') || class_exists('WooCommerce')) {
            return true;
        }

        return function_exists('wc_get_orders');
    }

    /**
     * Retrieves storewide baseline order metrics directly from the local WooCommerce database.
     */
    public static function getBaselineMetrics(string $period = '30d'): array
    {
        if (!self::isWooCommerceActive()) {
            return [
                'has_woocommerce'     => false,
                'store_total_orders'  => 0,
                'store_total_revenue' => 0.00,
                'store_baseline_aov'  => 0.00,
            ];
        }

        $days = match ($period) {
            '7d'    => 7,
            '90d'   => 90,
            default => 30,
        };

        $startDate = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        global $wpdb;

        try {
            // 1. Direct high-performance SQL aggregation if $wpdb is available
            if (!empty($wpdb) && is_object($wpdb) && method_exists($wpdb, 'get_row')) {
                $hposTable = $wpdb->prefix . 'wc_orders';
                $useHpos = false;

                if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
                    $useHpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                } elseif (method_exists($wpdb, 'get_var')) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $hposTable));
                    $useHpos = ($tableExists === $hposTable);
                }

                if ($useHpos) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Store-wide metric aggregations with dynamic date ranges.
                    $row = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT COUNT(id) AS total_orders, COALESCE(SUM(total_amount), 0.00) AS total_revenue 
                             FROM {$wpdb->prefix}wc_orders 
                             WHERE status IN ('wc-processing', 'wc-completed', 'processing', 'completed') 
                             AND date_created_gmt >= %s",
                            $startDate
                        )
                    );
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $row = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT COUNT(p.ID) AS total_orders, COALESCE(SUM(CAST(pm.meta_value AS DECIMAL(10,2))), 0.00) AS total_revenue 
                             FROM {$wpdb->posts} p 
                             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total' 
                             WHERE p.post_type = 'shop_order' 
                             AND p.post_status IN ('wc-processing', 'wc-completed') 
                             AND p.post_date_gmt >= %s",
                            $startDate
                        )
                    );
                }

                if ($row) {
                    $totalOrders = (int) $row->total_orders;
                    $totalRevenue = (float) $row->total_revenue;
                    $baselineAov = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0.00;

                    return [
                        'has_woocommerce'     => true,
                        'store_total_orders'  => $totalOrders,
                        'store_total_revenue' => round($totalRevenue, 2),
                        'store_baseline_aov'  => $baselineAov,
                    ];
                }
            }

            // 2. Fallback to wc_get_orders for mock/test harnesses without $wpdb
            $orders = function_exists('wc_get_orders') ? wc_get_orders([
                'limit'        => 500,
                'status'       => ['wc-processing', 'wc-completed'],
                'date_created' => '>=' . $startDate,
                'return'       => 'ids',
            ]) : [];

            $totalOrders = is_array($orders) ? count($orders) : 0;
            $totalRevenue = 0.00;

            if ($totalOrders > 0) {
                foreach ($orders as $orderId) {
                    if (function_exists('wc_get_order')) {
                        $order = wc_get_order($orderId);
                        if ($order && method_exists($order, 'get_total')) {
                            $totalRevenue += (float) $order->get_total();
                        }
                    }
                }
            }

            $baselineAov = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0.00;

            return [
                'has_woocommerce'     => true,
                'store_total_orders'  => $totalOrders,
                'store_total_revenue' => round($totalRevenue, 2),
                'store_baseline_aov'  => $baselineAov,
            ];
        } catch (\Throwable $e) {
            return [
                'has_woocommerce'     => true,
                'store_total_orders'  => 0,
                'store_total_revenue' => 0.00,
                'store_baseline_aov'  => 0.00,
                'error'               => $e->getMessage(),
            ];
        }
    }

    /**
     * Enriches the Cloud overview payload with local store baseline calculations.
     */
    public static function enrichWithStoreBaseline(array $cloudOverview, string $period = '30d'): array
    {
        $baseline = self::getBaselineMetrics($period);

        if (!isset($cloudOverview['overview']) || !is_array($cloudOverview['overview'])) {
            $cloudOverview['overview'] = [];
        }

        $botAov = (float) ($cloudOverview['overview']['bot_aov'] ?? 0.00);
        $attributedOrders = (int) ($cloudOverview['overview']['attributed_orders_count'] ?? 0);
        $storeOrders = (int) ($baseline['store_total_orders'] ?? 0);
        $storeBaselineAov = (float) ($baseline['store_baseline_aov'] ?? 0.00);

        // AOV Uplift calculation
        $aovUplift = '0.0%';
        if ($storeBaselineAov > 0 && $botAov > 0) {
            $diff = (($botAov - $storeBaselineAov) / $storeBaselineAov) * 100;
            $sign = $diff >= 0 ? '+' : '';
            $aovUplift = $sign . number_format($diff, 1) . '%';
        }

        // Assisted share of total store orders
        $assistedShare = '0.0%';
        if ($storeOrders > 0 && $attributedOrders > 0) {
            $share = ($attributedOrders / $storeOrders) * 100;
            $assistedShare = number_format(min($share, 100.0), 1) . '%';
        }

        $cloudOverview['overview']['store_total_orders'] = $storeOrders;
        $cloudOverview['overview']['store_total_revenue'] = $baseline['store_total_revenue'];
        $cloudOverview['overview']['store_baseline_aov'] = $storeBaselineAov;
        $cloudOverview['overview']['aov_uplift_percentage'] = $aovUplift;
        $cloudOverview['overview']['assisted_share_percentage'] = $assistedShare;

        return $cloudOverview;
    }
}
