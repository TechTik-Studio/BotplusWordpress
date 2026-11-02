<?php

namespace {
    if (!function_exists('WC')) {
        function WC() {
            return true;
        }
    }
}

namespace Tests\Unit {

use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use PHPUnit\Framework\TestCase;

class WebhookHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('get_option')->justReturn([]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_hooks_core_actions_universally(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(false);

        $handler = new \BotPlus\Plugin\Webhooks\WebhookHandler();
        $handler->register();

        $this->assertTrue(has_action('wp_after_insert_post'));
        $this->assertTrue(has_action('transition_post_status'));
        $this->assertTrue(has_action('publish_future_post'));
        $this->assertTrue(has_action('trashed_post'));
        $this->assertTrue(has_action('untrashed_post'));
        $this->assertTrue(has_action('before_delete_post'));
        $this->assertTrue(has_action('update_option_blogname'));

        // WooCommerce hooks should NOT be registered when WC is inactive
        $this->assertFalse(has_action('woocommerce_new_product'));
    }

    public function test_register_hooks_woocommerce_actions_when_active(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);

        $handler = new \BotPlus\Plugin\Webhooks\WebhookHandler();
        $handler->register();

        $this->assertTrue(has_action('woocommerce_new_product'));
        $this->assertTrue(has_action('woocommerce_update_product'));
        $this->assertTrue(has_action('woocommerce_delete_product'));
        $this->assertTrue(has_action('woocommerce_product_set_stock'));
        $this->assertTrue(has_action('woocommerce_variation_set_stock'));
        $this->assertTrue(has_action('woocommerce_product_set_stock_status'));
        $this->assertTrue(has_action('woocommerce_variation_set_stock_status'));
        $this->assertTrue(has_action('woocommerce_update_product_variation'));
        $this->assertTrue(has_action('woocommerce_save_product_variation'));
        $this->assertTrue(has_action('woocommerce_product_quick_edit_save'));
        $this->assertTrue(has_action('woocommerce_process_product_meta'));
        $this->assertTrue(has_action('woocommerce_product_import_batch_complete'));
        $this->assertTrue(has_action('woocommerce_order_refunded'));
        $this->assertTrue(has_action('woocommerce_payment_complete'));
        $this->assertTrue(has_action('woocommerce_coupon_options_save'));
        $this->assertTrue(has_action('woocommerce_trash_order'));
        $this->assertTrue(has_action('woocommerce_delete_order'));
        $this->assertTrue(has_action('update_option_woocommerce_coming_soon'));
    }

    public function test_handle_stock_status_changed_resolves_variation_to_parent(): void
    {
        Functions\when('wc_get_order')->justReturn(null);

        $mockHandler = $this->getMockBuilder(\BotPlus\Plugin\Webhooks\WebhookHandler::class)
            ->onlyMethods(['dispatchWebhook'])
            ->getMock();

        $variationProduct = new class {
            public function get_id() { return 5002; }
            public function get_parent_id() { return 5000; }
            public function get_stock_quantity() { return 15; }
        };

        $mockHandler->expects($this->once())
            ->method('dispatchWebhook')
            ->with(
                'product.stock_updated',
                5000,
                $this->callback(function ($payload) {
                    return $payload['id'] === 5000
                        && $payload['variation_id'] === 5002
                        && $payload['stock_status'] === 'outofstock'
                        && $payload['stock_quantity'] === 15;
                }),
                'product'
            )
            ->willReturn(true);

        $mockHandler->handleStockStatusChanged(5002, 'outofstock', $variationProduct);
    }

    public function test_extract_post_payload_formats_data_correctly(): void
    {
        $post = new \stdClass();
        $post->ID = 101;
        $post->post_title = 'Privacy Policy';
        $post->post_name = 'privacy-policy';
        $post->post_content = 'This is our privacy policy.';
        $post->post_excerpt = 'Policy summary';
        $post->post_type = 'page';
        $post->post_status = 'publish';
        $post->post_modified_gmt = '2026-10-16 10:00:00';

        Functions\when('get_permalink')->justReturn('https://example.local/privacy-policy/');
        Functions\when('get_the_category')->justReturn([]);

        $handler = new \BotPlus\Plugin\Webhooks\WebhookHandler();
        $payload = $handler->extractPostPayload($post);

        $this->assertIsArray($payload);
        $this->assertEquals(101, $payload['id']);
        $this->assertEquals('Privacy Policy', $payload['title']);
        $this->assertEquals('page', $payload['post_type']);
        $this->assertEquals('publish', $payload['post_status']);
        $this->assertEquals('https://example.local/privacy-policy/', $payload['permalink']);
    }

    public function test_bulk_import_buffer_and_dispatch(): void
    {
        $handler = new \BotPlus\Plugin\Webhooks\WebhookHandler();

        $product1 = new class { public function get_id() { return 1612; } };
        $product2 = new class { public function get_id() { return 1613; } };

        $handler->bufferImportedProduct($product1, []);
        $handler->bufferImportedProduct($product2, []);

        // Mock dispatchWebhook to return true without calling container
        Functions\when('wc_get_order')->justReturn(null);

        $mockHandler = $this->getMockBuilder(\BotPlus\Plugin\Webhooks\WebhookHandler::class)
            ->onlyMethods(['dispatchWebhook'])
            ->getMock();

        $mockHandler->expects($this->once())
            ->method('dispatchWebhook')
            ->with(
                'catalog.bulk_updated',
                0,
                $this->callback(function ($payload) {
                    return isset($payload['product_ids']) && in_array(1612, $payload['product_ids']);
                }),
                'catalog'
            )
            ->willReturn(true);

        $mockHandler->bufferImportedProduct($product1, []);
        $mockHandler->handleBulkImportComplete(null);
    }

    public function test_handle_order_refunded_dispatches_refund_payload(): void
    {
        Functions\when('wc_get_order')->justReturn(null);

        $mockHandler = $this->getMockBuilder(\BotPlus\Plugin\Webhooks\WebhookHandler::class)
            ->onlyMethods(['dispatchWebhook', 'extractOrderPayload'])
            ->getMock();

        $mockHandler->method('extractOrderPayload')
            ->willReturn([
                'id'           => 7001,
                'order_number' => 'WC-7001',
                'total'        => 200.00,
            ]);

        $mockHandler->expects($this->once())
            ->method('dispatchWebhook')
            ->with(
                'order.refunded',
                7001,
                $this->callback(function ($payload) {
                    return $payload['id'] === 7001 && $payload['refund_id'] === 888;
                }),
                'order'
            )
            ->willReturn(true);

        $mockHandler->handleOrderRefunded(7001, 888);
    }

    public function test_handle_order_deleted_dispatches_order_deleted(): void
    {
        Functions\when('wc_get_order')->justReturn(null);

        $mockHandler = $this->getMockBuilder(\BotPlus\Plugin\Webhooks\WebhookHandler::class)
            ->onlyMethods(['dispatchWebhook'])
            ->getMock();

        $mockHandler->expects($this->once())
            ->method('dispatchWebhook')
            ->with(
                'order.deleted',
                7001,
                $this->callback(function ($payload) {
                    return $payload['id'] === 7001 && $payload['status'] === 'cancelled';
                }),
                'order'
            )
            ->willReturn(true);

        $mockHandler->handleOrderDeleted(7001);
    }

    public function test_handle_post_status_transition_unpublish_dispatches_deleted(): void
    {
        Functions\when('wc_get_order')->justReturn(null);

        $mockHandler = $this->getMockBuilder(\BotPlus\Plugin\Webhooks\WebhookHandler::class)
            ->onlyMethods(['dispatchWebhook'])
            ->getMock();

        $post = (object) [
            'ID'        => 303,
            'post_type' => 'post',
        ];

        $mockHandler->expects($this->once())
            ->method('dispatchWebhook')
            ->with(
                'content.deleted',
                303,
                $this->callback(function ($payload) {
                    return $payload['id'] === 303
                        && $payload['old_status'] === 'publish'
                        && $payload['new_status'] === 'draft';
                }),
                'post'
            )
            ->willReturn(true);

        $mockHandler->handlePostStatusTransition('draft', 'publish', $post);
    }

    public function test_handle_coupon_saved_dispatches_coupon_updated(): void
    {
        Functions\when('wc_get_order')->justReturn(null);

        $mockHandler = $this->getMockBuilder(\BotPlus\Plugin\Webhooks\WebhookHandler::class)
            ->onlyMethods(['dispatchWebhook', 'extractCouponPayload'])
            ->getMock();

        $mockHandler->method('extractCouponPayload')
            ->willReturn([
                'id'            => 101,
                'code'          => 'SAVE20',
                'discount_type' => 'percent',
                'amount'        => 20.0,
            ]);

        $mockHandler->expects($this->once())
            ->method('dispatchWebhook')
            ->with(
                'coupon.updated',
                101,
                $this->callback(function ($payload) {
                    return $payload['code'] === 'SAVE20' && $payload['amount'] === 20.0;
                }),
                'coupon'
            )
            ->willReturn(true);

        $mockHandler->handleCouponSaved(101);
    }

    public function test_handle_store_visibility_option_changed(): void
    {
        Functions\when('wc_get_order')->justReturn(null);

        $mockHandler = $this->getMockBuilder(\BotPlus\Plugin\Webhooks\WebhookHandler::class)
            ->onlyMethods(['dispatchWebhook'])
            ->getMock();

        $mockHandler->expects($this->once())
            ->method('dispatchWebhook')
            ->with(
                'site.settings_updated',
                0,
                $this->callback(function ($payload) {
                    return $payload['is_coming_soon'] === true;
                }),
                'site'
            )
            ->willReturn(true);

        $mockHandler->handleStoreVisibilityOptionChanged('no', 'yes');
    }

    public function test_dispatch_webhook_buffers_on_transport_failure(): void
    {
        $configMock = $this->createMock(\BotPlus\Plugin\Core\Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(99);

        $apiMock = $this->createMock(\BotPlus\Plugin\Api\LaravelApiClient::class);
        $apiMock->expects($this->once())
            ->method('forwardWebhook')
            ->willReturn(['success' => false, 'message' => 'cURL timeout']);

        \BotPlus\Plugin\Core\Bootstrapper::container()->instance(\BotPlus\Plugin\Core\Config::class, $configMock);
        \BotPlus\Plugin\Core\Bootstrapper::container()->instance(\BotPlus\Plugin\Api\LaravelApiClient::class, $apiMock);

        Functions\when('get_option')->justReturn([]);
        Functions\expect('update_option')
            ->once()
            ->with('botplus_webhook_retry_buffer', \Mockery::on(function ($buffer) {
                return count($buffer) === 1
                    && $buffer[0]['topic'] === 'product.updated'
                    && $buffer[0]['resource_id'] === 1623
                    && $buffer[0]['error'] === 'cURL timeout';
            }));

        $handler = new \BotPlus\Plugin\Webhooks\WebhookHandler();
        $result = $handler->dispatchWebhook('product.updated', 1623, ['name' => 'Failed Product'], 'product');

        $this->assertFalse($result);
    }

    public function test_handle_variation_saved_dispatches_stock_and_product_updated(): void
    {
        Functions\when('wc_get_order')->justReturn(null);

        $mockHandler = $this->getMockBuilder(\BotPlus\Plugin\Webhooks\WebhookHandler::class)
            ->onlyMethods(['dispatchWebhook', 'extractProductPayload'])
            ->getMock();

        $variation = new class {
            public function get_id() { return 1624; }
            public function get_parent_id() { return 1623; }
            public function get_stock_quantity() { return 42; }
            public function get_stock_status() { return 'instock'; }
            public function get_manage_stock() { return true; }
        };

        Functions\when('wc_get_product')->justReturn($variation);

        $mockHandler->method('extractProductPayload')
            ->willReturn([
                'id'    => 1623,
                'title' => 'Parent Variable Product',
                'price' => 100.0,
            ]);

        $dispatched = [];
        $mockHandler->expects($this->exactly(2))
            ->method('dispatchWebhook')
            ->willReturnCallback(function ($topic, $resourceId, $payload, $resourceType) use (&$dispatched) {
                $dispatched[] = [$topic, $resourceId, $payload, $resourceType];
                return true;
            });

        $mockHandler->handleVariationSaved(1624);

        $this->assertCount(2, $dispatched);
        // 1. Stock updated
        $this->assertEquals('product.stock_updated', $dispatched[0][0]);
        $this->assertEquals(1623, $dispatched[0][1]);
        $this->assertEquals(1624, $dispatched[0][2]['variation_id']);
        $this->assertEquals(42, $dispatched[0][2]['stock_quantity']);
        $this->assertEquals('instock', $dispatched[0][2]['stock_status']);

        // 2. Parent product updated
        $this->assertEquals('product.updated', $dispatched[1][0]);
        $this->assertEquals(1623, $dispatched[1][1]);
        $this->assertEquals('Parent Variable Product', $dispatched[1][2]['title']);
    }

    public function test_handle_product_meta_saved_dispatches_product_updated_and_stock_updated(): void
    {
        Functions\when('wc_get_order')->justReturn(null);

        $mockHandler = $this->getMockBuilder(\BotPlus\Plugin\Webhooks\WebhookHandler::class)
            ->onlyMethods(['dispatchWebhook', 'extractProductPayload'])
            ->getMock();

        $product = new class {
            public function get_id() { return 1623; }
            public function get_stock_quantity() { return 15; }
            public function get_stock_status() { return 'instock'; }
            public function get_manage_stock() { return true; }
        };

        Functions\when('wc_get_product')->justReturn($product);

        $mockHandler->method('extractProductPayload')
            ->willReturn([
                'id'    => 1623,
                'title' => 'Simple Product 1623',
                'price' => 50.0,
            ]);

        $dispatched = [];
        $mockHandler->expects($this->exactly(2))
            ->method('dispatchWebhook')
            ->willReturnCallback(function ($topic, $resourceId, $payload, $resourceType) use (&$dispatched) {
                $dispatched[] = [$topic, $resourceId, $payload, $resourceType];
                return true;
            });

        $mockHandler->handleProductMetaSaved(1623);

        $this->assertCount(2, $dispatched);
        // 1. Product updated (catalog sync)
        $this->assertEquals('product.updated', $dispatched[0][0]);
        $this->assertEquals(1623, $dispatched[0][1]);
        $this->assertEquals('Simple Product 1623', $dispatched[0][2]['title']);

        // 2. Stock updated (real-time inventory sync)
        $this->assertEquals('product.stock_updated', $dispatched[1][0]);
        $this->assertEquals(1623, $dispatched[1][1]);
        $this->assertEquals(15, $dispatched[1][2]['stock_quantity']);
        $this->assertEquals('instock', $dispatched[1][2]['stock_status']);
        $this->assertTrue($dispatched[1][2]['manage_stock']);
    }

    public function test_handle_product_meta_saved_deduplicates_duplicate_stock_in_same_request(): void
    {
        Functions\when('wc_get_order')->justReturn(null);

        $mockHandler = $this->getMockBuilder(\BotPlus\Plugin\Webhooks\WebhookHandler::class)
            ->onlyMethods(['dispatchWebhook', 'extractProductPayload'])
            ->getMock();

        $product = new class {
            public function get_id() { return 9999; }
            public function get_stock_quantity() { return 20; }
            public function get_stock_status() { return 'instock'; }
            public function get_manage_stock() { return true; }
        };

        Functions\when('wc_get_product')->justReturn($product);

        $mockHandler->method('extractProductPayload')
            ->willReturn([
                'id'    => 9999,
                'title' => 'Dedupe Product',
            ]);

        $stockUpdatesCount = 0;
        $mockHandler->method('dispatchWebhook')
            ->willReturnCallback(function ($topic, $resourceId, $payload, $resourceType) use (&$stockUpdatesCount) {
                if ($topic === 'product.stock_updated') {
                    $stockUpdatesCount++;
                }
                return true;
            });

        // First call
        $mockHandler->handleProductMetaSaved(9999);
        // Second call with identical product and stock in same request
        $mockHandler->handleProductMetaSaved(9999);

        $this->assertEquals(1, $stockUpdatesCount, 'Duplicate stock update within the same request must be suppressed');
    }

    public function test_flush_retry_buffer_replays_and_prunes_queue(): void
    {
        $buffer = [
            [
                'topic' => 'product.updated',
                'resource_id' => 101,
                'payload' => ['title' => 'Shirt'],
                'resource_type' => 'product',
            ],
            [
                'topic' => 'product.updated',
                'resource_id' => 102,
                'payload' => ['title' => 'Pants'],
                'resource_type' => 'product',
            ],
        ];

        Functions\when('get_option')->alias(function ($key, $default = []) use ($buffer) {
            if ($key === 'botplus_webhook_retry_buffer') {
                return $buffer;
            }
            return $default;
        });

        $updatedBuffer = null;
        Functions\when('update_option')->alias(function ($key, $val) use (&$updatedBuffer) {
            if ($key === 'botplus_webhook_retry_buffer') {
                $updatedBuffer = $val;
            }
            return true;
        });

        $configMock = $this->createMock(\BotPlus\Plugin\Core\Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(123);

        $apiClientMock = $this->createMock(\BotPlus\Plugin\Api\LaravelApiClient::class);
        // First replay succeeds, second fails
        $apiClientMock->expects($this->exactly(2))
            ->method('forwardWebhook')
            ->willReturnOnConsecutiveCalls(
                ['success' => true],
                ['success' => false]
            );

        $container = \BotPlus\Plugin\Core\Bootstrapper::container();
        $container->instance(\BotPlus\Plugin\Core\Config::class, $configMock);
        $container->instance(\BotPlus\Plugin\Api\LaravelApiClient::class, $apiClientMock);

        $handler = new \BotPlus\Plugin\Webhooks\WebhookHandler();
        $flushed = $handler->flushRetryBuffer();

        $this->assertEquals(1, $flushed);
        $this->assertCount(1, $updatedBuffer);
        $this->assertEquals(102, $updatedBuffer[0]['resource_id']);
    }
}

}
