<?php

namespace BotPlus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use BotPlus\Plugin\Core\Installer;

class InstallerTest extends TestCase
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

    public function test_uninstall_purges_all_options_and_transients(): void
    {
        $deletedOptions = [];
        Functions\when('delete_option')->alias(function ($option) use (&$deletedOptions) {
            $deletedOptions[] = $option;
            return true;
        });

        Functions\when('flush_rewrite_rules')->justReturn(true);
        Functions\when('wp_clear_scheduled_hook')->justReturn(true);
        Functions\when('wp_next_scheduled')->justReturn(false);

        global $wpdb;
        $wpdb = new class {
            public string $options = 'wp_options';
            public array $queries = [];

            public function esc_like(string $text): string
            {
                return addcslashes($text, '_%\\');
            }

            public function prepare(string $query, ...$args): string
            {
                return vsprintf(str_replace('%s', "'%s'", $query), $args);
            }

            public function query(string $query): int
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        Installer::uninstall();

        $expectedOptions = [
            'botplus_settings',
            'botplus_widget_settings',
            'botplus_chatbot_id',
            'botplus_site_credentials',
            'botplus_sync_status',
            'botplus_last_synced_at',
            'botplus_last_sync_stats',
            'botplus_webhook_last_error',
            'botplus_webhook_telemetry',
            'botplus_webhook_retry_buffer',
            'botplus_environment_mismatch',
            'botplus_support_email',
            'botplus_whatsapp_number',
        ];

        foreach ($expectedOptions as $expectedOption) {
            $this->assertContains($expectedOption, $deletedOptions, "Failed asserting that option '{$expectedOption}' is deleted during uninstall.");
        }

        $this->assertNotEmpty($wpdb->queries);
        $this->assertStringContainsString("WHERE option_name LIKE 'botplus\\_%'", $wpdb->queries[0]);
    }
}
