<?php

namespace Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
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

    public function test_get_returns_default_when_option_not_set(): void
    {
        Functions\expect('get_option')->once()->andReturn(false);

        $config = new \BotPlus\Plugin\Core\Config();
        $this->assertNotEmpty($config->getBotPlusApiUrl());
        $this->assertNull($config->getJwtToken());
        $this->assertFalse($config->isConnected());
    }

    public function test_get_returns_stored_values(): void
    {
        $storedSettings = [
            'botplus_api_url' => 'http://laravel.local',
            'jwt_token' => 'test-jwt-token',
            'site_id' => 1,
            'chatbot_id' => 5,
            'chatbot_name' => 'Acme Shoes Assistant',
            'connected' => true,
            'botplus_user_id' => 99,
            'botplus_user_email' => 'merchant@acme.com',
            'connected_at' => '2026-10-15 14:00:00',
            'manage_url' => 'http://laravel.local/chatbots-page',
        ];

        Functions\expect('get_option')->once()->andReturn($storedSettings);

        $config = new \BotPlus\Plugin\Core\Config();
        $this->assertEquals('test-jwt-token', $config->getJwtToken());
        $this->assertEquals(1, $config->getSiteId());
        $this->assertEquals(5, $config->getChatbotId());
        $this->assertEquals('Acme Shoes Assistant', $config->getChatbotName());
        $this->assertEquals(99, $config->getBotPlusUserId());
        $this->assertEquals('merchant@acme.com', $config->getBotPlusUserEmail());
        $this->assertEquals('2026-10-15 14:00:00', $config->getConnectedAt());
        $this->assertEquals('http://laravel.local/chatbots-page', $config->getManageUrl());
        $this->assertTrue($config->isConnected());
    }

    public function test_set_connection_data_stores_all_attributes(): void
    {
        Functions\expect('get_option')->once()->andReturn([]);
        Functions\expect('update_option')->atLeast()->once();

        $config = new \BotPlus\Plugin\Core\Config();
        $config->setConnectionData([
            'jwt_token' => 'live_jwt_token_xyz',
            'site_id' => 12,
            'chatbot_id' => 45,
            'chatbot_name' => 'Apex Bot',
            'botplus_user_id' => 7,
            'botplus_user_email' => 'admin@apex.com',
            'manage_url' => 'https://botplus.app/chatbots-page',
        ]);

        $this->assertTrue($config->isConnected());
        $this->assertEquals('live_jwt_token_xyz', $config->getJwtToken());
        $this->assertEquals(12, $config->getSiteId());
        $this->assertEquals(45, $config->getChatbotId());
        $this->assertEquals('Apex Bot', $config->getChatbotName());
        $this->assertEquals('admin@apex.com', $config->getBotPlusUserEmail());
        $this->assertEquals('https://botplus.app/chatbots-page', $config->getManageUrl());
        $this->assertNotEmpty($config->getConnectedAt());
    }

    public function test_disconnect_resets_all_connection_attributes(): void
    {
        Functions\expect('get_option')->once()->andReturn([
            'connected' => true,
            'jwt_token' => 'token123',
            'site_id' => 1,
            'chatbot_id' => 2,
            'chatbot_name' => 'Old Bot',
            'botplus_user_email' => 'old@store.com',
        ]);
        Functions\expect('update_option')->atLeast()->once();

        $config = new \BotPlus\Plugin\Core\Config();
        $this->assertTrue($config->isConnected());

        $config->disconnect();
        $this->assertFalse($config->isConnected());
        $this->assertEquals('', $config->getJwtToken());
        $this->assertNull($config->getSiteId());
        $this->assertNull($config->getChatbotId());
        $this->assertEquals('', $config->getChatbotName());
        $this->assertNull($config->getBotPlusUserEmail());
    }

    public function test_webhook_secret_encryption_and_decryption(): void
    {
        Functions\expect('get_option')->once()->andReturn([]);
        Functions\expect('update_option')->atLeast()->once();

        $config = new \BotPlus\Plugin\Core\Config();
        $config->setWebhookSecret('super-secret-hmac-key');

        $this->assertEquals('super-secret-hmac-key', $config->getWebhookSecret());
    }

    public function test_scheduler_purge_preserves_retry_buffer_cron(): void
    {
        Functions\when('wp_next_scheduled')->alias(function ($hook) {
            return $hook === 'botplus_sync_products';
        });

        Functions\expect('wp_clear_scheduled_hook')
            ->with('botplus_sync_products')
            ->once();

        // Crucial: botplus_flush_retry_buffer is NOT cleared
        \BotPlus\Plugin\Sync\Scheduler::purgeLegacySchedules();
        $this->assertTrue(true);
    }
}

class EncryptionTest extends TestCase
{
    public function test_encrypt_decrypt_roundtrip(): void
    {
        $original = 'test-password-123';
        $encrypted = \BotPlus\Plugin\Utilities\Encryption::encrypt($original);
        $decrypted = \BotPlus\Plugin\Utilities\Encryption::decrypt($encrypted);

        $this->assertEquals($original, $decrypted);
    }

    public function test_empty_string_returns_empty(): void
    {
        $this->assertEquals('', \BotPlus\Plugin\Utilities\Encryption::encrypt(''));
        $this->assertEquals('', \BotPlus\Plugin\Utilities\Encryption::decrypt(''));
    }
}

class HelpersTest extends TestCase
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

    public function test_botplus_get_user_context_null_when_not_logged_in(): void
    {
        Functions\expect('wp_get_current_user')->andReturn((object)['exists' => false]);
        $this->assertNull(\botplus_get_user_context());
    }
}
