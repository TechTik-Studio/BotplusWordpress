<?php

namespace BotPlus\Plugin\Tests\Unit;

if (!defined('BOTPLUS_VERSION')) {
    define('BOTPLUS_VERSION', '1.0.0');
}

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use BotPlus\Plugin\Api\LaravelApiClient;
use BotPlus\Plugin\Core\Config;
use BotPlus\Plugin\Sync\SyncDispatcher;
use BotPlus\Plugin\Sync\CatalogExtractor;
use BotPlus\Plugin\Sync\ContentExtractor;

class LaravelApiClientTest extends TestCase
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

    protected function getTestBaseUrl(): string
    {
        if (defined('BOTPLUS_API_URL')) {
            return BOTPLUS_API_URL;
        }

        return getenv('BOTPLUS_API_URL') ?: 'http://localhost:8000';
    }

    public function test_auth_headers_injects_x_botplus_origin(): void
    {
        Functions\expect('home_url')->atLeast()->once()->andReturn('https://mystore.local');

        $configMock = $this->createMock(Config::class);
        $configMock->method('getBotPlusApiUrl')->willReturn($this->getTestBaseUrl());
        $configMock->method('getJwtToken')->willReturn('jwt_test_token_123');

        $client = new LaravelApiClient($configMock);

        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('getAuthHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($client);

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer jwt_test_token_123', $headers['Authorization']);
        $this->assertArrayHasKey('X-BotPlus-Origin', $headers);
        $this->assertEquals('https://mystore.local', $headers['X-BotPlus-Origin']);
    }

    public function test_sync_dispatcher_records_environment_mismatch_and_halts(): void
    {
        Functions\expect('current_time')->atLeast()->once()->andReturn('2026-10-16 12:00:00');
        Functions\expect('update_option')
            ->once()
            ->with('botplus_environment_mismatch', \Mockery::on(function ($val) {
                return isset($val['message']) && str_contains($val['message'], 'origin does not match');
            }));

        $configMock = $this->createMock(Config::class);
        $configMock->method('getSiteId')->willReturn(14);

        $catalogMock = $this->createMock(CatalogExtractor::class);
        $catalogMock->method('isWooCommerceActive')->willReturn(true);
        $catalogMock->method('extractPolicies')->willReturn([
            ['id' => 1, 'title' => 'Policy 1', 'policy_type' => 'privacy']
        ]);

        $contentMock = $this->createMock(ContentExtractor::class);

        $apiMock = $this->createMock(LaravelApiClient::class);
        $apiMock->expects($this->once())
            ->method('sendSyncBatch')
            ->willReturn([
                'success' => false,
                'error'   => 'host_mismatch',
                'message' => 'The request origin does not match the connected site host. Please reconnect the BotPlus plugin.',
            ]);

        $dispatcher = new SyncDispatcher($configMock, $apiMock, $catalogMock, $contentMock);
        $results = $dispatcher->dispatchFullSync(14);

        $this->assertFalse($results['success']);
        $this->assertEquals('host_mismatch', $results['error_code']);
        $this->assertStringContainsString('origin does not match', $results['message']);
    }

    public function test_forward_webhook_captures_wp_error_and_records_telemetry(): void
    {
        Functions\when('home_url')->justReturn('https://mystore.local');
        Functions\when('get_option')->justReturn('no');
        Functions\when('apply_filters')->returnArg(2);
        Functions\when('wp_generate_uuid4')->justReturn('uuid-test-123');

        $wpError = new class {
            public function get_error_message() {
                return 'cURL error 28: Connection timed out after 5000 milliseconds';
            }
        };

        Functions\when('wp_remote_post')->justReturn($wpError);
        Functions\when('is_wp_error')->justReturn(true);
        Functions\expect('update_option')
            ->once()
            ->with('botplus_webhook_last_error', \Mockery::on(function ($val) {
                return $val['topic'] === 'product.updated'
                    && $val['resource_id'] === 1623
                    && str_contains($val['error_message'], 'cURL error 28');
            }));

        $configMock = $this->createMock(Config::class);
        $configMock->method('getBotPlusApiUrl')->willReturn($this->getTestBaseUrl());
        $configMock->method('getJwtToken')->willReturn('jwt_test_token_123');
        $configMock->method('getWebhookSecret')->willReturn('secret_123');

        $client = new LaravelApiClient($configMock);
        $result = $client->forwardWebhook(14, 'product.updated', 1623, ['name' => 'Test Product']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('cURL error 28', $result['message']);
        $this->assertEquals('uuid-test-123', $result['event_id']);
    }

    public function test_forward_webhook_success(): void
    {
        Functions\when('home_url')->justReturn('https://mystore.local');
        Functions\when('get_option')->justReturn('no');
        Functions\when('apply_filters')->returnArg(2);
        Functions\when('wp_generate_uuid4')->justReturn('uuid-test-456');

        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);

        $configMock = $this->createMock(Config::class);
        $configMock->method('getBotPlusApiUrl')->willReturn($this->getTestBaseUrl());
        $configMock->method('getJwtToken')->willReturn('jwt_test_token_123');
        $configMock->method('getWebhookSecret')->willReturn('secret_123');

        $client = new LaravelApiClient($configMock);
        $result = $client->forwardWebhook(14, 'product.updated', 1623, ['name' => 'Test Product']);

        $this->assertTrue($result['success']);
        $this->assertEquals('uuid-test-456', $result['event_id']);
    }

    public function test_inbox_client_methods_structure(): void
    {
        $configMock = $this->createMock(Config::class);
        $configMock->method('getBotPlusApiUrl')->willReturn($this->getTestBaseUrl());
        $configMock->method('getJwtToken')->willReturn('jwt_test_token_123');

        $client = new LaravelApiClient($configMock);

        $this->assertTrue(method_exists($client, 'getInboxFirebaseAuth'));
        $this->assertTrue(method_exists($client, 'getInboxConversations'));
        $this->assertTrue(method_exists($client, 'getConversationMessages'));
        $this->assertTrue(method_exists($client, 'sendHumanMessage'));
        $this->assertTrue(method_exists($client, 'claimConversation'));
        $this->assertTrue(method_exists($client, 'releaseConversation'));
        $this->assertTrue(method_exists($client, 'addCaseNote'));
        $this->assertTrue(method_exists($client, 'updateCaseStatus'));
        $this->assertTrue(method_exists($client, 'getAgentSettings'));
        $this->assertTrue(method_exists($client, 'saveAgentSettings'));
    }
}
