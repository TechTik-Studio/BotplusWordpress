<?php

namespace BotPlus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use BotPlus\Plugin\Core\Config;
use BotPlus\Plugin\Core\UpdateChecker;
use BotPlus\Plugin\Api\LaravelApiClient;

class UpdateCheckerTest extends TestCase
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

    public function test_filter_plugin_update_transient_injects_update_when_newer_version_available(): void
    {
        Functions\when('get_site_transient')->justReturn(false);
        Functions\when('set_site_transient')->justReturn(true);

        $config = $this->createMock(Config::class);
        $apiClient = $this->createMock(LaravelApiClient::class);

        $apiClient->expects($this->once())
            ->method('checkPluginUpdate')
            ->willReturn([
                'version' => '1.0.5',
                'download_url' => 'https://botplus.app/downloads/wordpress/latest',
                'package' => 'https://botplus.app/downloads/wordpress/latest',
                'tested' => '7.1',
                'requires_php' => '8.1',
            ]);

        $checker = new UpdateChecker($config, $apiClient);

        $transient = new \stdClass();
        $transient->response = [];
        $transient->no_update = [];

        $filtered = $checker->filterPluginUpdateTransient($transient);

        $this->assertArrayHasKey('botplus-ai-agents/botplus.php', $filtered->response);
        $this->assertEquals('1.0.5', $filtered->response['botplus-ai-agents/botplus.php']->new_version);
        $this->assertEquals('https://botplus.app/downloads/wordpress/latest', $filtered->response['botplus-ai-agents/botplus.php']->package);
    }

    public function test_filter_plugin_update_transient_stores_in_no_update_when_already_latest(): void
    {
        Functions\when('get_site_transient')->justReturn(false);
        Functions\when('set_site_transient')->justReturn(true);

        $config = $this->createMock(Config::class);
        $apiClient = $this->createMock(LaravelApiClient::class);

        $apiClient->expects($this->once())
            ->method('checkPluginUpdate')
            ->willReturn([
                'version' => '1.0.0',
                'download_url' => 'https://botplus.app/downloads/wordpress/latest',
                'package' => 'https://botplus.app/downloads/wordpress/latest',
            ]);

        $checker = new UpdateChecker($config, $apiClient);

        $transient = new \stdClass();
        $transient->response = [];
        $transient->no_update = [];

        $filtered = $checker->filterPluginUpdateTransient($transient);

        $this->assertArrayHasKey('botplus-ai-agents/botplus.php', $filtered->no_update);
        $this->assertArrayNotHasKey('botplus-ai-agents/botplus.php', $filtered->response);
    }
}

