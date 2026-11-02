<?php

namespace BotPlus\Plugin\Utilities;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceCustomerContextHelper
{
    public static function isWooCommerceActive(): bool
    {
        return WooCommerceMetricsHelper::isWooCommerceActive();
    }

    /**
     * Enriches an array of conversation summaries with local WooCommerce customer stats and past orders.
     */
    public static function enrichConversations(array $conversations): array
    {
        if (!self::isWooCommerceActive() || empty($conversations)) {
            return $conversations;
        }

        return array_map([self::class, 'enrichConversation'], $conversations);
    }

    /**
     * Enriches a single conversation summary.
     */
    public static function enrichConversation(array $conversation): array
    {
        if (!self::isWooCommerceActive()) {
            return $conversation;
        }

        $email = $conversation['guest_email'] ?? $conversation['customerEmail'] ?? null;
        if (empty($email) || !is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $conversation;
        }

        try {
            $customerId = 0;
            if (function_exists('wc_get_customer_id_by_email')) {
                $customerId = (int) wc_get_customer_id_by_email($email);
            } elseif (function_exists('get_user_by')) {
                $user = get_user_by('email', $email);
                if ($user) {
                    $customerId = (int) $user->ID;
                }
            }

            $currency = function_exists('get_woocommerce_currency_symbol')
                ? html_entity_decode((string) get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                : (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '$');

            $totalSpent = 0.00;
            $ordersCount = 0;
            $recentOrders = [];

            if ($customerId > 0) {
                if (function_exists('wc_get_customer_total_spent')) {
                    $totalSpent = (float) wc_get_customer_total_spent($customerId);
                }
                if (function_exists('wc_get_customer_order_count')) {
                    $ordersCount = (int) wc_get_customer_order_count($customerId);
                }

                if (function_exists('wc_get_orders')) {
                    $orders = wc_get_orders([
                        'customer_id' => $customerId,
                        'limit'       => 5,
                        'orderby'     => 'date',
                        'order'       => 'DESC',
                    ]);

                    if (is_array($orders)) {
                        $recentOrders = self::formatOrders($orders, $currency);
                    }
                }
            } else {
                // Search by billing email for guest orders
                if (function_exists('wc_get_orders')) {
                    $orders = wc_get_orders([
                        'billing_email' => $email,
                        'limit'         => 5,
                        'orderby'       => 'date',
                        'order'         => 'DESC',
                    ]);

                    if (is_array($orders)) {
                        $recentOrders = self::formatOrders($orders, $currency);
                        $ordersCount = count($recentOrders);
                        foreach ($recentOrders as $ro) {
                            $totalSpent += (float) ($ro['total_amount'] ?? 0);
                        }
                    }
                }
            }

            if ($ordersCount > 0 || $totalSpent > 0) {
                $conversation['totalCustomerSpent'] = sprintf('%s%0.2f', $currency, $totalSpent);
                $conversation['customerOrdersCount'] = $ordersCount;
            }

            if (!empty($recentOrders)) {
                $conversation['recent_orders'] = $recentOrders;
                $conversation['customerOrders'] = $recentOrders;
            }

        } catch (\Throwable $e) {
            // Silently fall back to existing conversation data if WooCommerce lookup encounters issue
        }

        return $conversation;
    }

    /**
     * Formats WooCommerce order objects into clean UI payloads with admin links.
     */
    protected static function formatOrders(array $orders, string $currency): array
    {
        $formatted = [];
        $isHpos = self::isHposActive();

        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            $orderId = $order->get_id();
            $orderDate = $order->get_date_created();

            $adminEditUrl = $isHpos && function_exists('admin_url')
                ? admin_url("admin.php?page=wc-orders&action=edit&id={$orderId}")
                : (function_exists('admin_url') ? admin_url("post.php?post={$orderId}&action=edit") : '');

            $formatted[] = [
                'id'           => $orderId,
                'order_number' => $order->get_order_number(),
                'status'       => $order->get_status(),
                'total_amount' => (float) $order->get_total(),
                'total'        => sprintf('%s%0.2f', $currency, (float) $order->get_total()),
                'currency'     => $order->get_currency() ?: $currency,
                'date'         => $orderDate ? $orderDate->date('M j, Y') : '',
                'ordered_at'   => $orderDate ? $orderDate->date('Y-m-d H:i:s') : '',
                'edit_url'     => $adminEditUrl,
            ];
        }

        return $formatted;
    }

    /**
     * Detects if WooCommerce High-Performance Order Storage (HPOS) is active.
     */
    protected static function isHposActive(): bool
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            if (method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
                return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            }
        }
        return false;
    }
}

