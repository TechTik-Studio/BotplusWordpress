<?php

namespace {
    if (!class_exists('WP_REST_Request')) {
        class WP_REST_Request {
            public array $params = [];
            public array $headers = [];
            public function __construct(public string $method = 'GET', public string $route = '') {}
            public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
            public function set_param(string $key, mixed $val): void { $this->params[$key] = $val; }
            public function get_json_params(): array { return $this->params; }
            public function get_header(string $key): ?string { return $this->headers[strtolower($key)] ?? null; }
            public function set_header(string $key, string $val): void { $this->headers[strtolower($key)] = $val; }
        }
    }
    if (!class_exists('WP_REST_Response')) {
        class WP_REST_Response {
            protected int $status = 200;
            protected array $headers = [];
            protected mixed $data = null;
            public function __construct(mixed $data = null, int $status = 200) {
                $this->data = $data;
                $this->status = $status;
            }
            public function header(string $key, string $value): void { $this->headers[$key] = $value; }
            public function get_headers(): array { return $this->headers; }
            public function get_status(): int { return $this->status; }
            public function get_data(): mixed { return $this->data; }
        }
    }
    if (!class_exists('WC_Order')) {
        class WC_Order {
            public int $id = 1623;
            public string $number = '1623';
            public string $status = 'processing';
            public string $currency = 'PKR';
            public float $total = 1500.0;
            public float $subtotal = 1500.0;
            public int $customer_id = 0;
            public string $billing_email = 'shopper@example.com';
            public array $items = [];

            public function get_id(): int { return $this->id; }
            public function get_order_number(): string { return $this->number; }
            public function get_status(): string { return $this->status; }
            public function get_currency(): string { return $this->currency; }
            public function get_total(): float { return $this->total; }
            public function get_subtotal(): float { return $this->subtotal; }
            public function get_customer_id(): int { return $this->customer_id; }
            public function get_billing_email(): string { return $this->billing_email; }
            public function is_paid(): bool { return true; }
            public function get_date_created(): ?object {
                return new class {
                    public function date(string $format): string { return '2026-10-19 14:30:00'; }
                };
            }
            public function get_items(): array { return $this->items; }
        }
    }
}

namespace BotPlus\Plugin\Tests\Unit {

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use BotPlus\Plugin\Rest\Controllers\StorefrontOrderController;

class StorefrontOrderControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        \BotPlus\Plugin\Core\Bootstrapper::resetContainer();
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_option')->justReturn([]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('current_time')->justReturn('2026-10-26 12:00:00');
    }

    protected function tearDown(): void
    {
        \BotPlus\Plugin\Core\Bootstrapper::resetContainer();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testHandleTrackReturnsErrorWhenWooCommerceInactive(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(false);

        $controller = new StorefrontOrderController();
        $request = new \WP_REST_Request('GET', '/botplus/v1/storefront/order/track');

        $response = $controller->handleTrack($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('not active', $data['message']);
    }

    public function testHandleTrackReturns400WhenOrderNumberMissing(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);

        $controller = new StorefrontOrderController();
        $request = new \WP_REST_Request('GET', '/botplus/v1/storefront/order/track');

        $response = $controller->handleTrack($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Order number is required', $data['message']);
    }

    public function testHandleTrackReturns404WhenOrderNotFound(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);
        Functions\when('wc_get_order')->justReturn(null);
        Functions\when('wc_get_orders')->justReturn([]);
        Functions\when('get_posts')->justReturn([]);

        $controller = new StorefrontOrderController();
        $request = new \WP_REST_Request('GET', '/botplus/v1/storefront/order/track');
        $request->set_param('order_number', '99999');

        $response = $controller->handleTrack($request);

        $this->assertEquals(404, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('was not found', $data['message']);
    }

    public function testHandleTrackReturns403WhenGuestVerificationFails(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);

        $order = new \WC_Order();
        $order->id = 1623;
        $order->billing_email = 'realbuyer@example.com';

        Functions\when('wc_get_order')->justReturn($order);

        $controller = new StorefrontOrderController();
        $request = new \WP_REST_Request('GET', '/botplus/v1/storefront/order/track');
        $request->set_param('order_number', '1623');
        $request->set_param('email', 'wrong@example.com');

        $response = $controller->handleTrack($request);

        $this->assertEquals(403, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertTrue($data['requires_verification']);
    }

    public function testHandleTrackReturns200WithOrderDetailsAndTrackingWhenVerified(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);
        Functions\when('wc_get_order_status_name')->justReturn('Processing');

        $order = new \WC_Order();
        $order->id = 1623;
        $order->number = '1623';
        $order->status = 'processing';
        $order->currency = 'PKR';
        $order->total = 1500.00;
        $order->billing_email = 'shopper@example.com';

        Functions\when('wc_get_order')->justReturn($order);

        // Mock tracking meta
        Functions\when('get_post_meta')->alias(function ($postId, $key, $single) {
            if ($key === '_wc_shipment_tracking_items') {
                return [
                    [
                        'tracking_provider' => 'TCS Express',
                        'tracking_number' => '78912345',
                        'custom_tracking_link' => 'https://track.tcs.com/78912345',
                        'date_shipped' => '2026-10-19',
                    ],
                ];
            }
            return null;
        });

        $controller = new StorefrontOrderController();
        $request = new \WP_REST_Request('GET', '/botplus/v1/storefront/order/track');
        $request->set_param('order_number', '1623');
        $request->set_param('email', 'shopper@example.com');

        $response = $controller->handleTrack($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);

        $orderData = $data['order'];
        $this->assertEquals(1623, $orderData['id']);
        $this->assertEquals('Order #1623', $orderData['name']);
        $this->assertEquals('processing', $orderData['status']);
        $this->assertEquals('Processing', $orderData['status_label']);
        $this->assertEquals('processing', $orderData['fulfillment_status']);
        $this->assertEquals('paid', $orderData['financial_status']);
        $this->assertEquals(1500.00, $orderData['total']);
        $this->assertEquals(1500.00, $orderData['total_amount']);
        $this->assertEquals(1500.00, $orderData['subtotal']);
        $this->assertEquals(1500.00, $orderData['subtotal_amount']);
        $this->assertEquals('2026-10-19 14:30:00', $orderData['created_at']);
        $this->assertEquals('2026-10-19 14:30:00', $orderData['placed_at']);
        $this->assertArrayHasKey('billing', $orderData);
        $this->assertArrayHasKey('shipping', $orderData);
        $this->assertEquals('PKR', $orderData['currency']);

        $this->assertNotNull($orderData['tracking']);
        $this->assertCount(1, $orderData['tracking_items']);
        $this->assertEquals('TCS Express', $orderData['tracking']['company']);
        $this->assertEquals('78912345', $orderData['tracking']['number']);
        $this->assertEquals('https://track.tcs.com/78912345', $orderData['tracking']['url']);
    }

    public function testHandleTrackReturns200ForLoggedInCustomer(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);
        Functions\when('wc_get_order_status_name')->justReturn('Completed');

        $order = new \WC_Order();
        $order->id = 1700;
        $order->customer_id = 42;
        $order->status = 'completed';

        Functions\when('wc_get_order')->justReturn($order);
        Functions\when('get_post_meta')->justReturn(null);
        Functions\when('wc_get_order_notes')->justReturn([]);

        $controller = new StorefrontOrderController();
        $request = new \WP_REST_Request('GET', '/botplus/v1/storefront/order/track');
        $request->set_param('order_number', '1700');
        $request->set_param('customer_id', '42');

        $response = $controller->handleTrack($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(1700, $data['order']['id']);
        $this->assertEquals('fulfilled', $data['order']['fulfillment_status']);
    }

    public function testHandleTrackRejectsUnauthenticatedCallerWhenTokenConfigured(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(false);

        $config = new \BotPlus\Plugin\Core\Config();
        $config->setConnectionData([
            'jwt_token' => 'jwt_secret_token_abc',
            'site_id'   => 1,
        ]);
        \BotPlus\Plugin\Core\Bootstrapper::container()->instance(\BotPlus\Plugin\Core\Config::class, $config);

        $controller = new StorefrontOrderController();
        $request = new \WP_REST_Request('GET', '/botplus/v1/storefront/order/track');
        $request->set_param('order_number', '1623');
        $request->set_param('email', 'shopper@example.com');

        $response = $controller->handleTrack($request);

        $this->assertEquals(401, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Unauthorized', $data['message']);
    }

    public function testHandleTrackAllowsValidBearerTokenMatchingJwt(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(false);

        $config = new \BotPlus\Plugin\Core\Config();
        $config->setConnectionData([
            'jwt_token' => 'jwt_secret_token_abc',
            'site_id'   => 1,
        ]);
        \BotPlus\Plugin\Core\Bootstrapper::container()->instance(\BotPlus\Plugin\Core\Config::class, $config);

        $order = new \WC_Order();
        $order->id = 1623;
        $order->billing_email = 'shopper@example.com';
        Functions\when('wc_get_order')->justReturn($order);
        Functions\when('wc_get_order_status_name')->justReturn('Processing');
        Functions\when('get_post_meta')->justReturn(null);
        Functions\when('wc_get_order_notes')->justReturn([]);

        $controller = new StorefrontOrderController();
        $request = new \WP_REST_Request('GET', '/botplus/v1/storefront/order/track');
        $request->set_header('authorization', 'Bearer jwt_secret_token_abc');
        $request->set_param('order_number', '1623');
        $request->set_param('email', 'shopper@example.com');

        $response = $controller->handleTrack($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(1623, $data['order']['id']);
    }

    public function testStorefrontOrderControllerIsRegisteredInContainer(): void
    {
        $container = new \BotPlus\Plugin\Core\Container();
        $reflection = new \ReflectionClass(\BotPlus\Plugin\Core\Bootstrapper::class);
        $method = $reflection->getMethod('registerBindings');
        $method->setAccessible(true);
        $method->invoke(null, $container);

        $this->assertTrue($container->has(StorefrontOrderController::class));
        $instance = $container->get(StorefrontOrderController::class);
        $this->assertInstanceOf(StorefrontOrderController::class, $instance);
    }
}

}

