<?php

namespace BotPlus\Plugin\Tests\Unit;

if (!defined('BOTPLUS_VERSION')) {
    define('BOTPLUS_VERSION', '1.0.0');
}
if (!defined('BOTPLUS_PLUGIN_URL')) {
    define('BOTPLUS_PLUGIN_URL', 'https://example.com/wp-content/plugins/botplus-wordpress/');
}

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use BotPlus\Plugin\Admin\SettingsPage;
use BotPlus\Plugin\Core\Bootstrapper;
use BotPlus\Plugin\Core\Config;
use BotPlus\Plugin\Api\LaravelApiClient;

class BillingAjaxTest extends TestCase
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
        $_POST = [];
    }

    public function test_ajax_get_billing_state_returns_error_when_not_connected(): void
    {
        Functions\expect('check_ajax_referer')->once()->with('botplus_admin_action', 'nonce');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('__')->returnArg(1);

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(false);
        $configMock->method('getSiteId')->willReturn(null);
        Bootstrapper::container()->instance(Config::class, $configMock);

        Functions\expect('wp_send_json_error')
            ->once()
            ->with($this->callback(function ($response) {
                return $response['message'] === 'BotPlus is not connected.';
            }), 400);

        $page = new SettingsPage();
        $page->ajaxGetBillingState();
        $this->assertTrue(true);
    }

    public function test_ajax_get_billing_state_returns_data_on_success(): void
    {
        Functions\expect('check_ajax_referer')->once()->with('botplus_admin_action', 'nonce');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('__')->returnArg(1);

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(42);
        Bootstrapper::container()->instance(Config::class, $configMock);

        $apiClientMock = $this->createMock(LaravelApiClient::class);
        $apiClientMock->expects($this->once())
            ->method('getBillingState')
            ->with(42)
            ->willReturn([
                'success' => true,
                'data' => [
                    'billing_state' => ['package_slug' => 'wc-growth', 'billing_status' => 'active'],
                    'available_packages' => [],
                ],
            ]);
        Bootstrapper::container()->instance(LaravelApiClient::class, $apiClientMock);

        Functions\expect('wp_send_json_success')
            ->once()
            ->with($this->callback(function ($data) {
                return isset($data['billing_state']) && $data['billing_state']['package_slug'] === 'wc-growth';
            }));

        $page = new SettingsPage();
        $page->ajaxGetBillingState();
        $this->assertTrue(true);
    }

    public function test_ajax_create_billing_checkout_success(): void
    {
        Functions\expect('check_ajax_referer')->once()->with('botplus_admin_action', 'nonce');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('__')->returnArg(1);
        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('wp_unslash')->returnArg(1);
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/admin.php?page=botplus-settings&tab=billing');

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(42);
        Bootstrapper::container()->instance(Config::class, $configMock);

        $_POST['package_slug'] = 'wc-growth';
        $_POST['interval'] = 'annual';

        $apiClientMock = $this->createMock(LaravelApiClient::class);
        $apiClientMock->expects($this->once())
            ->method('createBillingCheckoutSession')
            ->with(42, 'wc-growth', 'annual', 'https://example.com/wp-admin/admin.php?page=botplus-settings&tab=billing')
            ->willReturn([
                'success' => true,
                'data' => [
                    'checkout_url' => 'https://sandbox.polar.sh/checkout/chk_123',
                    'session_id' => 'chk_123',
                ],
            ]);
        Bootstrapper::container()->instance(LaravelApiClient::class, $apiClientMock);

        Functions\expect('wp_send_json_success')
            ->once()
            ->with($this->callback(function ($data) {
                return isset($data['checkout_url']) && str_contains($data['checkout_url'], 'polar.sh');
            }));

        $page = new SettingsPage();
        $page->ajaxCreateBillingCheckout();
        $this->assertTrue(true);
    }

    public function test_ajax_create_billing_customer_session_success(): void
    {
        Functions\expect('check_ajax_referer')->once()->with('botplus_admin_action', 'nonce');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('__')->returnArg(1);

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(42);
        Bootstrapper::container()->instance(Config::class, $configMock);

        $apiClientMock = $this->createMock(LaravelApiClient::class);
        $apiClientMock->expects($this->once())
            ->method('createBillingCustomerSession')
            ->with(42)
            ->willReturn([
                'success' => true,
                'data' => [
                    'customer_portal_url' => 'https://sandbox.polar.sh/customer-portal/sess_test_123',
                ],
            ]);
        Bootstrapper::container()->instance(LaravelApiClient::class, $apiClientMock);

        Functions\expect('wp_send_json_success')
            ->once()
            ->with($this->callback(function ($data) {
                return isset($data['customer_portal_url']) && str_contains($data['customer_portal_url'], 'customer-portal');
            }));

        $page = new SettingsPage();
        $page->ajaxCreateBillingCustomerSession();
        $this->assertTrue(true);
    }
}

