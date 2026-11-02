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

class SettingsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('get_woocommerce_currency')->justReturn('USD');
        Functions\when('botplus_is_woocommerce_active')->justReturn(false);
        Functions\when('wp_unslash')->returnArg(1);
        Functions\when('esc_url')->returnArg(1);
        Functions\when('add_query_arg')->alias(function ($args, $url = '') {
            return (is_string($url) ? $url : '') . '?' . http_build_query(is_array($args) ? $args : []);
        });
        Functions\when('wp_nonce_url')->alias(function ($actionurl, $action = -1, $name = '_wpnonce') {
            return $actionurl . '&' . $name . '=mocked_nonce';
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_render_displays_webhook_error_alert_when_present(): void
    {
        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getChatbotId')->willReturn(10);
        $configMock->method('getSiteId')->willReturn(2);
        Bootstrapper::container()->instance(Config::class, $configMock);

        Functions\when('esc_html_e')->echoArg(1);
        Functions\when('esc_html')->returnArg(1);
        Functions\when('esc_js')->returnArg(1);
        Functions\when('date_i18n')->justReturn('2026-10-19 12:00:00');

        Functions\when('get_option')->alias(function ($key) {
            if ($key === 'botplus_webhook_last_error') {
                return [
                    'topic'         => 'product.updated',
                    'resource_id'   => 1623,
                    'api_url'       => 'https://botplus.ngrok.app/api/wordpress/webhooks',
                    'error_message' => 'cURL error 28: Connection timed out',
                    'timestamp'     => 1729339200,
                ];
            }
            return null;
        });

        $page = new SettingsPage();

        ob_start();
        $page->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('BotPlus Webhook Delivery Alert:', $output);
        $this->assertStringContainsString('cURL error 28: Connection timed out', $output);
        $this->assertStringContainsString('product.updated', $output);
        $this->assertStringContainsString('https://botplus.ngrok.app/api/wordpress/webhooks', $output);
    }

    public function test_ajax_test_webhook_success(): void
    {
        Functions\expect('check_ajax_referer')->once()->with('botplus_admin_action', 'nonce');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('__')->returnArg(1);

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(2);
        Bootstrapper::container()->instance(Config::class, $configMock);

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->expects($this->once())
            ->method('forwardWebhook')
            ->with(
                2,
                'site.ping',
                0,
                $this->callback(function ($payload) {
                    return $payload['source'] === 'admin_test_webhook' && $payload['tested_by'] === 1;
                }),
                'site',
                true
            )
            ->willReturn(['success' => true, 'event_id' => 'ping-uuid-123']);
        Bootstrapper::container()->instance(LaravelApiClient::class, $apiMock);

        Functions\expect('wp_send_json_success')
            ->once()
            ->with($this->callback(function ($response) {
                return $response['event_id'] === 'ping-uuid-123'
                    && str_contains($response['message'], 'Webhook test dispatched successfully');
            }));

        $page = new SettingsPage();
        $page->ajaxTestWebhook();
    }

    public function test_register_menu_registers_top_level_and_submenus(): void
    {
        Functions\when('__')->returnArg(1);

        $registeredMenus = [];
        $registeredSubmenus = [];

        Functions\when('add_menu_page')->alias(function (...$args) use (&$registeredMenus) {
            $registeredMenus[] = $args;
            return 'toplevel_page_botplus';
        });

        Functions\when('add_submenu_page')->alias(function (...$args) use (&$registeredSubmenus) {
            $registeredSubmenus[] = $args;
            return $args[0] . '_page_' . $args[4];
        });

        Functions\when('add_options_page')->justReturn(true);

        $page = new SettingsPage();
        $page->registerMenu();

        $this->assertCount(1, $registeredMenus);
        $this->assertSame('BotPlus AI', $registeredMenus[0][0]);
        $this->assertSame('botplus', $registeredMenus[0][3]);
        $this->assertSame(56, $registeredMenus[0][6]);

        $subSlugs = array_map(function ($call) {
            return $call[4];
        }, array_filter($registeredSubmenus, fn($c) => $c[0] === 'botplus'));

        $this->assertContains('botplus', $subSlugs);
        $this->assertContains('botplus-inbox', $subSlugs);
        $this->assertContains('botplus-carts', $subSlugs);
        $this->assertContains('botplus-leads', $subSlugs);
        $this->assertContains('botplus-analytics', $subSlugs);
        $this->assertContains('botplus-agent', $subSlugs);
        $this->assertContains('botplus-capabilities', $subSlugs);
        $this->assertContains('botplus-billing', $subSlugs);
        $this->assertContains('botplus-settings', $subSlugs);
        $this->assertCount(9, $subSlugs);

        $fallbackSlugs = array_map(function ($call) {
            return $call[4];
        }, array_filter($registeredSubmenus, fn($c) => $c[0] === null));
        $this->assertContains('botplus-widget', $fallbackSlugs);
        $this->assertContains('botplus-knowledge', $fallbackSlugs);
        $this->assertContains('botplus-sync', $fallbackSlugs);
    }

    public function test_enqueue_assets_only_on_botplus_pages(): void
    {
        $enqueuedScripts = [];
        Functions\when('wp_enqueue_script')->alias(function ($handle) use (&$enqueuedScripts) {
            $enqueuedScripts[] = $handle;
        });
        Functions\when('wp_enqueue_media')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_localize_script')->justReturn(true);
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('wp_generate_password')->justReturn('secret123');
        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_rest_url')->justReturn('https://example.com/wp-json/botplus/v1');
        Functions\when('wp_create_nonce')->justReturn('test-nonce');
        Functions\when('home_url')->justReturn('https://example.com');
        Functions\when('get_bloginfo')->justReturn('Test Site');
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/');
        Functions\when('get_option')->justReturn(null);

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        Bootstrapper::container()->instance(Config::class, $configMock);

        $page = new SettingsPage();

        // Non-botplus page should not enqueue
        $page->enqueueAssets('index.php');
        $this->assertEmpty($enqueuedScripts);

        // Top level botplus page should enqueue
        $page->enqueueAssets('toplevel_page_botplus');
        $this->assertContains('botplus-admin', $enqueuedScripts);

        // Submenu botplus page should enqueue
        $enqueuedScripts = [];
        $page->enqueueAssets('botplus_page_botplus-inbox');
        $this->assertContains('botplus-admin', $enqueuedScripts);
    }

    public function test_ajax_get_carts_unauthorized_when_user_cannot_manage_options(): void
    {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('__')->returnArg(1);

        $sentError = null;
        $sentStatus = null;
        Functions\when('wp_send_json_error')->alias(function ($data, $status = null) use (&$sentError, &$sentStatus) {
            $sentError = $data;
            $sentStatus = $status;
        });

        $page = new SettingsPage();
        $page->ajaxGetCarts();

        $this->assertEquals(403, $sentStatus);
        $this->assertArrayHasKey('message', $sentError);
        $this->assertStringContainsString('Unauthorized', $sentError['message']);
    }

    public function test_ajax_get_agent_settings_unauthorized_when_user_cannot_manage_options(): void
    {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('__')->returnArg(1);

        $sentError = null;
        $sentStatus = null;
        Functions\when('wp_send_json_error')->alias(function ($data, $status = null) use (&$sentError, &$sentStatus) {
            $sentError = $data;
            $sentStatus = $status;
        });

        $page = new SettingsPage();
        $page->ajaxGetAgentSettings();

        $this->assertEquals(403, $sentStatus);
        $this->assertArrayHasKey('message', $sentError);
        $this->assertStringContainsString('Unauthorized', $sentError['message']);
    }

    public function test_ajax_save_agent_settings_success(): void
    {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('sanitize_textarea_field')->returnArg(1);
        Functions\when('esc_url_raw')->returnArg(1);
        Functions\when('sanitize_hex_color')->returnArg(1);
        Functions\when('sanitize_email')->returnArg(1);

        $savedOptions = [];
        Functions\when('update_option')->alias(function ($key, $val) use (&$savedOptions) {
            $savedOptions[$key] = $val;
            return true;
        });

        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getSiteId')->willReturn(42);
        Bootstrapper::container()->instance(Config::class, $configMock);

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->method('saveAgentSettings')->willReturn([
            'success' => true,
            'data'    => ['chatbot' => ['id' => 10]],
        ]);
        Bootstrapper::container()->instance(LaravelApiClient::class, $apiMock);

        $_POST = [
            'tone_style'      => 'professional',
            'display_name'    => 'Pro Assistant',
            'primary_color'   => '#1e40af',
            'button_color'    => '#1e40af',
            'widget_position' => 'left',
            'support_email'   => 'support@example.com',
        ];

        $sentSuccess = null;
        Functions\when('wp_send_json_success')->alias(function ($data) use (&$sentSuccess) {
            $sentSuccess = $data;
        });

        $page = new SettingsPage();
        $page->ajaxSaveAgentSettings();

        $this->assertNotNull($sentSuccess);
        $this->assertEquals(10, $sentSuccess['chatbot']['id']);
        $this->assertArrayHasKey('botplus_widget_settings', $savedOptions);
        $this->assertEquals('left', $savedOptions['botplus_widget_settings']['position']);
        $this->assertEquals('#1e40af', $savedOptions['botplus_widget_settings']['primary_color']);
        $this->assertEquals('support@example.com', $savedOptions['botplus_support_email']);
    }

    public function test_ajax_upload_avatar_unauthorized(): void
    {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('__')->returnArg(1);

        $sentError = null;
        $sentStatus = null;
        Functions\when('wp_send_json_error')->alias(function ($data, $status = null) use (&$sentError, &$sentStatus) {
            $sentError = $data;
            $sentStatus = $status;
        });

        $page = new SettingsPage();
        $page->ajaxUploadAvatar();

        $this->assertEquals(403, $sentStatus);
        $this->assertStringContainsString('permission', $sentError['message']);
    }

    public function test_ajax_upload_avatar_success(): void
    {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('media_handle_upload')->justReturn(142);
        Functions\when('wp_get_attachment_url')->justReturn('https://example.com/wp-content/uploads/bot-avatar.png');

        $_FILES = [
            'avatar' => [
                'name'     => 'avatar.png',
                'type'     => 'image/png',
                'tmp_name' => '/tmp/phpavatar',
                'error'    => 0,
                'size'     => 12345,
            ],
        ];

        $sentSuccess = null;
        Functions\when('wp_send_json_success')->alias(function ($data) use (&$sentSuccess) {
            $sentSuccess = $data;
        });

        $page = new SettingsPage();
        $page->ajaxUploadAvatar();

        $this->assertNotNull($sentSuccess);
        $this->assertEquals('https://example.com/wp-content/uploads/bot-avatar.png', $sentSuccess['url']);
        $this->assertEquals(142, $sentSuccess['attachment_id']);
    }

    public function test_register_menu_registers_submenus_including_billing(): void
    {
        $registeredMenus = [];
        $registeredSubmenus = [];

        Functions\when('add_menu_page')->alias(function (...$args) use (&$registeredMenus) {
            $registeredMenus[] = $args;
        });

        Functions\when('add_submenu_page')->alias(function (...$args) use (&$registeredSubmenus) {
            $registeredSubmenus[] = $args;
        });

        Functions\when('add_options_page')->justReturn(true);
        Functions\when('__')->returnArg(1);

        $page = new SettingsPage();
        $page->registerMenu();

        $this->assertNotEmpty($registeredMenus);
        $this->assertEquals('botplus', $registeredMenus[0][3]);

        $subSlugs = array_map(fn ($item) => $item[4] ?? null, $registeredSubmenus);
        $this->assertContains('botplus', $subSlugs);
        $this->assertContains('botplus-inbox', $subSlugs);
        $this->assertContains('botplus-carts', $subSlugs);
        $this->assertContains('botplus-billing', $subSlugs);
        $this->assertContains('botplus-settings', $subSlugs);
    }

    public function test_filter_script_loader_tag_preserves_inline_scripts_and_injects_module_type(): void
    {
        $page = new SettingsPage();

        $inputTag = "<script id='botplus-admin-js-extra'>var BotPlusAdminConfig = { connected: true };</script>\n" .
                    "<script id='botplus-admin-js-before'>window.BotPlusAdminInitialState = { connected: true };</script>\n" .
                    "<script src='https://example.com/build/botplus-admin.min.js' id='botplus-admin-js'></script>\n";

        $filtered = $page->filterScriptLoaderTag($inputTag, 'botplus-admin', 'https://example.com/build/botplus-admin.min.js');

        // Preserves both inline scripts
        $this->assertStringContainsString("id='botplus-admin-js-extra'", $filtered);
        $this->assertStringContainsString("id='botplus-admin-js-before'", $filtered);
        $this->assertStringContainsString('BotPlusAdminConfig', $filtered);
        $this->assertStringContainsString('BotPlusAdminInitialState', $filtered);

        // Injects type="module" on the main enqueued script
        $this->assertStringContainsString('<script type="module" src=\'https://example.com/build/botplus-admin.min.js\' id=\'botplus-admin-js\'></script>', $filtered);

        // Non-target handle is unchanged
        $otherTag = "<script src='https://example.com/other.js'></script>";
        $this->assertEquals($otherTag, $page->filterScriptLoaderTag($otherTag, 'other-handle', 'https://example.com/other.js'));
    }

    public function test_ajax_clear_webhook_error_success(): void
    {
        Functions\expect('check_ajax_referer')->once()->with('botplus_admin_action', 'nonce');
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('__')->returnArg(1);

        $deletedOption = null;
        Functions\when('delete_option')->alias(function ($key) use (&$deletedOption) {
            $deletedOption = $key;
            return true;
        });

        $sentSuccess = null;
        Functions\when('wp_send_json_success')->alias(function ($data) use (&$sentSuccess) {
            $sentSuccess = $data;
        });

        $page = new SettingsPage();
        $page->ajaxClearWebhookError();

        $this->assertEquals('botplus_webhook_last_error', $deletedOption);
        $this->assertNotNull($sentSuccess);
    }
}
