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
}

namespace BotPlus\Plugin\Tests\Unit {

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use BotPlus\Plugin\Rest\Controllers\StorefrontCartController;

class StorefrontCartControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('wp_create_nonce')->justReturn('mocked_nonce_123');
        Functions\when('wp_get_current_user')->justReturn(new class {
            public int $ID = 0;
            public string $user_email = '';
            public string $first_name = '';
            public string $last_name = '';
            public string $display_name = '';
            public function exists(): bool { return false; }
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testHandleReturnsEmptyStructureWhenWooCommerceInactive(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(false);
        Functions\when('get_woocommerce_currency')->justReturn('PKR');

        $controller = new StorefrontCartController();
        $request = new \WP_REST_Request('GET', '/botplus/v1/storefront/cart');

        $response = $controller->handle($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('cart_hash', $data);
        $this->assertArrayHasKey('currency', $data);
        $this->assertArrayHasKey('item_count', $data);
        $this->assertArrayHasKey('subtotal_amount', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('nonce', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals('PKR', $data['currency']);
        $this->assertEquals(0, $data['item_count']);
        $this->assertEquals([], $data['items']);

        // Check anti-caching headers
        $headers = $response->get_headers();
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertStringContainsString('no-cache', $headers['Cache-Control']);
    }

    public function testHandleActionReturnsErrorWhenWooCommerceInactive(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(false);

        $controller = new StorefrontCartController();
        $request = new \WP_REST_Request('POST', '/botplus/v1/storefront/cart/action');

        $response = $controller->handleAction($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('not active', $data['message']);
    }

    public function testHandleRestoreReturnsErrorWhenWooCommerceInactive(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(false);

        $controller = new StorefrontCartController();
        $request = new \WP_REST_Request('POST', '/botplus/v1/storefront/cart/restore');

        $response = $controller->handleRestore($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('not active', $data['message']);
    }

    public function testHandleRestoreSuccessReturnsCheckoutUrl(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);
        Functions\when('wc_get_checkout_url')->justReturn('https://farm.test/checkout/');
        Functions\when('get_woocommerce_currency')->justReturn('USD');

        $controller = new StorefrontCartController();
        $request = new \WP_REST_Request('POST', '/botplus/v1/storefront/cart/restore');

        $response = $controller->handleRestore($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals('https://farm.test/checkout/', $data['checkout_url']);
    }

    public function testHandleActionRejectsInvalidNonce(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);
        Functions\when('wp_verify_nonce')->justReturn(false);

        $controller = new StorefrontCartController();
        $request = new \WP_REST_Request('POST', '/botplus/v1/storefront/cart/action');
        $request->set_header('x_wp_nonce', 'invalid_nonce_abc');

        $response = $controller->handleAction($request);

        $this->assertEquals(403, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid security token', $data['message']);
    }

    public function testHandleRestorePerforms302RedirectWhenRequested(): void
    {
        if (!defined('BOTPLUS_PHPUNIT_TESTING')) {
            define('BOTPLUS_PHPUNIT_TESTING', true);
        }

        Functions\when('botplus_is_woocommerce_active')->justReturn(true);
        Functions\when('wc_get_checkout_url')->justReturn('https://farm.test/checkout/');
        Functions\expect('wp_safe_redirect')->with('https://farm.test/checkout/')->once();

        $controller = new StorefrontCartController();
        $request = new \WP_REST_Request('GET', '/botplus/v1/storefront/cart/restore');
        $request->set_param('redirect', 1);

        $response = $controller->handleRestore($request);

        $this->assertEquals(302, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['redirected']);
        $this->assertEquals('https://farm.test/checkout/', $data['checkout_url']);
    }

    public function testParseItemsPayloadSupportsArrayJsonAndCompactString(): void
    {
        $controller = new StorefrontCartController();

        // 1. Array passthrough
        $arrInput = [['product_id' => 12, 'quantity' => 2]];
        $this->assertEquals($arrInput, $controller->parseItemsPayload($arrInput));

        // 2. JSON string
        $jsonInput = json_encode([['id' => 45, 'quantity' => 3]]);
        $parsedJson = $controller->parseItemsPayload($jsonInput);
        $this->assertCount(1, $parsedJson);
        $this->assertEquals(45, $parsedJson[0]['id']);

        // 3. Compact notation
        $compactInput = '101:2:0,102:1:55';
        $parsedCompact = $controller->parseItemsPayload($compactInput);
        $this->assertCount(2, $parsedCompact);
        $this->assertEquals(101, $parsedCompact[0]['product_id']);
        $this->assertEquals(2, $parsedCompact[0]['quantity']);
        $this->assertEquals(0, $parsedCompact[0]['variation_id']);
        $this->assertEquals(102, $parsedCompact[1]['product_id']);
        $this->assertEquals(1, $parsedCompact[1]['quantity']);
        $this->assertEquals(55, $parsedCompact[1]['variation_id']);
    }

    public function testHandleTemplateRedirectRestorationRedirectsToCheckout(): void
    {
        if (!defined('BOTPLUS_PHPUNIT_TESTING')) {
            define('BOTPLUS_PHPUNIT_TESTING', true);
        }

        $_GET['botplus_cart_items'] = '101:2:0,102:1:55';
        Functions\when('botplus_is_woocommerce_active')->justReturn(false);
        Functions\when('wp_unslash')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wc_get_checkout_url')->justReturn('https://farm.test/checkout/');
        Functions\expect('wp_safe_redirect')->with('https://farm.test/checkout/')->once();

        $controller = new StorefrontCartController();
        $controller->handleTemplateRedirectRestoration();

        $this->assertTrue(true);
        unset($_GET['botplus_cart_items']);
    }

    public function testHandleActionRejectsLoggedInUserWithoutValidNonce(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_verify_nonce')->justReturn(false);

        $controller = new StorefrontCartController();
        $request = new \WP_REST_Request('POST', '/botplus/v1/storefront/cart');
        // No nonce provided
        $response = $controller->handleAction($request);

        $this->assertEquals(403, $response->get_status());
        $this->assertStringContainsString('Invalid or missing security token', $response->get_data()['message']);

        // Invalid nonce provided
        $request->set_header('x_wp_nonce', 'invalid_token');
        $response = $controller->handleAction($request);

        $this->assertEquals(403, $response->get_status());
    }

    public function testHandleActionAllowsLoggedInUserWithValidNonce(): void
    {
        Functions\when('botplus_is_woocommerce_active')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_verify_nonce')->justReturn(true);

        $controller = new StorefrontCartController();
        $request = new \WP_REST_Request('POST', '/botplus/v1/storefront/cart');
        $request->set_header('x_wp_nonce', 'valid_token');

        $response = $controller->handleAction($request);

        // Passed nonce check, reaches WooCommerce cart validation
        $this->assertEquals(500, $response->get_status());
        $this->assertEquals('WooCommerce cart not initialized.', $response->get_data()['message']);
    }
}

}

