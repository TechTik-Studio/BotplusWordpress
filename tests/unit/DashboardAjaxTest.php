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

class DashboardAjaxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('wp_unslash')->returnArg(1);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
        $_GET = [];
    }

    public function test_ajax_dashboard_returns_error_when_not_connected(): void
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
        $page->ajaxGetDashboardOverview();
        $this->assertTrue(true);
    }

    public function test_ajax_dashboard_cache_hit_returns_transient_without_api_call(): void
    {
        Functions\expect('check_ajax_referer')->once()->with('botplus_admin_action', 'nonce');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg(1);
        $_GET['period'] = '30d';

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(2);
        Bootstrapper::container()->instance(Config::class, $configMock);

        $cachedOverview = [
            'overview' => [
                'attributed_revenue_net' => 1428500,
                'attributed_orders_count' => 142,
            ],
        ];

        Functions\when('get_transient')
            ->alias(function ($key) use ($cachedOverview) {
                if ($key === 'botplus_dashboard_2_30d') {
                    return $cachedOverview;
                }
                return false;
            });

        Functions\expect('wp_send_json_success')
            ->once()
            ->with($this->callback(function ($response) use ($cachedOverview) {
                return $response['cached'] === true
                    && isset($response['overview']['overview']['attributed_revenue_net'])
                    && $response['overview']['overview']['attributed_revenue_net'] === 1428500;
            }));

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->expects($this->never())->method('getDashboardOverview');
        Bootstrapper::container()->instance(LaravelApiClient::class, $apiMock);

        $page = new SettingsPage();
        $page->ajaxGetDashboardOverview();
        $this->assertTrue(true);
    }

    public function test_ajax_dashboard_cache_miss_calls_api_and_sets_transient(): void
    {
        Functions\expect('check_ajax_referer')->once()->with('botplus_admin_action', 'nonce');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg(1);
        $_GET['period'] = '30d';

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(2);
        Bootstrapper::container()->instance(Config::class, $configMock);

        Functions\when('get_transient')->justReturn(false);

        $liveData = [
            'period' => '30d',
            'overview' => [
                'attributed_revenue_net' => 50000,
                'attributed_orders_count' => 5,
            ],
        ];

        Functions\expect('set_transient')
            ->once()
            ->with('botplus_dashboard_2_30d', $this->callback(function ($payload) {
                return $payload['period'] === '30d' && $payload['overview']['attributed_revenue_net'] === 50000;
            }), 600);

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->expects($this->once())
            ->method('getDashboardOverview')
            ->with(2, '30d')
            ->willReturn([
                'success' => true,
                'data'    => $liveData,
            ]);
        Bootstrapper::container()->instance(LaravelApiClient::class, $apiMock);

        Functions\expect('wp_send_json_success')
            ->once()
            ->with($this->callback(function ($response) {
                return $response['cached'] === false
                    && $response['overview']['overview']['attributed_revenue_net'] === 50000;
            }));

        $page = new SettingsPage();
        $page->ajaxGetDashboardOverview();
        $this->assertTrue(true);
    }

    public function test_ajax_dashboard_refresh_bypasses_transient(): void
    {
        Functions\expect('check_ajax_referer')->once()->with('botplus_admin_action', 'nonce');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg(1);
        $_GET['period'] = '7d';
        $_GET['refresh'] = '1';

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(2);
        Bootstrapper::container()->instance(Config::class, $configMock);

        $freshData = [
            'period' => '7d',
            'overview' => [
                'attributed_revenue_net' => 12000,
            ],
        ];

        Functions\expect('set_transient')
            ->once()
            ->with('botplus_dashboard_2_7d', $this->callback(function ($payload) {
                return $payload['period'] === '7d' && $payload['overview']['attributed_revenue_net'] === 12000;
            }), 600);

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->expects($this->once())
            ->method('getDashboardOverview')
            ->with(2, '7d')
            ->willReturn([
                'success' => true,
                'data'    => $freshData,
            ]);
        Bootstrapper::container()->instance(LaravelApiClient::class, $apiMock);

        Functions\expect('wp_send_json_success')
            ->once()
            ->with($this->callback(function ($response) {
                return $response['cached'] === false
                    && $response['overview']['overview']['attributed_revenue_net'] === 12000;
            }));

        $page = new SettingsPage();
        $page->ajaxGetDashboardOverview();
        $this->assertTrue(true);
    }
}
