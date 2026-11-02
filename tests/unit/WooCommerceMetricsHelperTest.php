<?php

namespace BotPlus\Plugin\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use BotPlus\Plugin\Utilities\WooCommerceMetricsHelper;

class WooCommerceMetricsHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_get_baseline_metrics_without_woocommerce(): void
    {
        Functions\when('wc_get_orders')->justReturn([]);

        $metrics = WooCommerceMetricsHelper::getBaselineMetrics('30d');

        $this->assertSame(0, $metrics['store_total_orders']);
        $this->assertSame(0.00, $metrics['store_total_revenue']);
        $this->assertSame(0.00, $metrics['store_baseline_aov']);
    }

    public function test_get_baseline_metrics_with_woocommerce_orders(): void
    {
        Functions\when('wc_get_orders')->justReturn([101, 102]);

        $orderMock1 = $this->createMock(\stdClass::class);
        // Using dynamic methods via stdClass or custom class
        $order1 = new class {
            public function get_total() { return '10000.00'; }
        };
        $order2 = new class {
            public function get_total() { return '5000.00'; }
        };

        Functions\when('wc_get_order')->alias(function ($id) use ($order1, $order2) {
            return $id === 101 ? $order1 : $order2;
        });

        $metrics = WooCommerceMetricsHelper::getBaselineMetrics('30d');

        $this->assertTrue($metrics['has_woocommerce']);
        $this->assertSame(2, $metrics['store_total_orders']);
        $this->assertSame(15000.00, $metrics['store_total_revenue']);
        $this->assertSame(7500.00, $metrics['store_baseline_aov']);
    }

    public function test_enrich_with_store_baseline_calculates_aov_uplift(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);
        Functions\when('wc_get_orders')->justReturn([101, 102]);

        $order1 = new class { public function get_total() { return '7200.00'; } };
        $order2 = new class { public function get_total() { return '7200.00'; } };

        Functions\when('wc_get_order')->alias(function ($id) use ($order1, $order2) {
            return $id === 101 ? $order1 : $order2;
        });

        $cloudData = [
            'overview' => [
                'bot_aov' => 10060.00,
                'attributed_orders_count' => 1,
            ],
        ];

        $enriched = WooCommerceMetricsHelper::enrichWithStoreBaseline($cloudData, '30d');

        $this->assertSame(7200.00, $enriched['overview']['store_baseline_aov']);
        $this->assertSame('+39.7%', $enriched['overview']['aov_uplift_percentage']);
        $this->assertSame('50.0%', $enriched['overview']['assisted_share_percentage']);
    }

    public function test_get_baseline_metrics_uses_direct_sql_when_wpdb_available(): void
    {
        Functions\when('wc_get_orders')->justReturn([]);

        global $wpdb;
        $mockWpdb = new class {
            public string $prefix = 'wp_';
            public string $posts = 'wp_posts';
            public string $postmeta = 'wp_postmeta';
            public function prepare($query, ...$args) { return $query; }
            public function get_var($query) { return 'wp_wc_orders'; }
            public function get_row($query) {
                return (object) [
                    'total_orders' => 45,
                    'total_revenue' => 90000.00,
                ];
            }
        };
        $wpdb = $mockWpdb;

        $metrics = WooCommerceMetricsHelper::getBaselineMetrics('30d');

        $this->assertTrue($metrics['has_woocommerce']);
        $this->assertSame(45, $metrics['store_total_orders']);
        $this->assertSame(90000.00, $metrics['store_total_revenue']);
        $this->assertSame(2000.00, $metrics['store_baseline_aov']);

        $wpdb = null;
    }
}
