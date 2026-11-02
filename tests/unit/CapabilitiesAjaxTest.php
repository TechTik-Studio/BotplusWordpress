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

class CapabilitiesAjaxTest extends TestCase
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

    public function test_ajax_get_capabilities_returns_error_when_not_connected(): void
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
        $page->ajaxGetCapabilities();
        $this->assertTrue(true);
    }

    public function test_ajax_get_capabilities_returns_data_on_success(): void
    {
        Functions\expect('check_ajax_referer')->once()->with('botplus_admin_action', 'nonce');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('__')->returnArg(1);

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(15);
        Bootstrapper::container()->instance(Config::class, $configMock);

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->method('getCapabilities')->with(15)->willReturn([
            'success' => true,
            'data' => [
                'site' => ['is_woocommerce' => true],
                'chatbot' => ['has_cart_tracking' => true],
                'groups' => [],
            ],
        ]);
        Bootstrapper::container()->instance(LaravelApiClient::class, $apiMock);

        Functions\expect('wp_send_json_success')
            ->once()
            ->with($this->callback(function ($data) {
                return isset($data['site']) && isset($data['chatbot']);
            }));

        $page = new SettingsPage();
        $page->ajaxGetCapabilities();
        $this->assertTrue(true);
    }

    public function test_ajax_toggle_capability_validates_feature(): void
    {
        Functions\expect('check_ajax_referer')->once()->with('botplus_admin_action', 'nonce');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('__')->returnArg(1);
        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('wp_unslash')->returnArg(1);

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(15);
        Bootstrapper::container()->instance(Config::class, $configMock);

        $_POST = ['feature' => ''];

        Functions\expect('wp_send_json_error')
            ->once()
            ->with($this->callback(function ($response) {
                return $response['message'] === 'Feature name is required.';
            }), 400);

        $page = new SettingsPage();
        $page->ajaxToggleCapability();
        $this->assertTrue(true);
    }

    public function test_ajax_toggle_capability_calls_api_and_returns_success(): void
    {
        Functions\expect('check_ajax_referer')->once()->with('botplus_admin_action', 'nonce');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('__')->returnArg(1);
        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('wp_unslash')->returnArg(1);

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(15);
        Bootstrapper::container()->instance(Config::class, $configMock);

        $_POST = [
            'feature' => 'cart_tracking',
            'enabled' => 'true',
        ];

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->method('toggleCapability')->with(15, 'cart_tracking', true)->willReturn([
            'success' => true,
            'data' => [
                'feature' => 'cart_tracking',
                'enabled' => true,
            ],
        ]);
        Bootstrapper::container()->instance(LaravelApiClient::class, $apiMock);

        Functions\expect('wp_send_json_success')
            ->once()
            ->with($this->callback(function ($data) {
                return $data['feature'] === 'cart_tracking' && $data['enabled'] === true;
            }));

        $page = new SettingsPage();
        $page->ajaxToggleCapability();
        $this->assertTrue(true);
    }
}

