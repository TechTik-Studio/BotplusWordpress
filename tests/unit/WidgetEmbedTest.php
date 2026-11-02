<?php

namespace BotPlus\Plugin\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use BotPlus\Plugin\Widget\Block;
use BotPlus\Plugin\Widget\Shortcode;
use BotPlus\Plugin\Widget\ChatWidget;
use BotPlus\Plugin\Core\Bootstrapper;
use BotPlus\Plugin\Core\Config;

class WidgetEmbedTest extends TestCase
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

    public function test_block_renders_embed_chat_route(): void
    {
        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getChatbotId')->willReturn(42);
        $configMock->method('getBotPlusApiUrl')->willReturn('https://botplus.app');

        Bootstrapper::container()->instance(Config::class, $configMock);

        Functions\when('esc_url')->returnArg(1);
        Functions\when('esc_attr')->returnArg(1);

        $block = new Block();
        $html = $block->render(['height' => '500px', 'width' => '100%']);

        $this->assertStringContainsString('https://botplus.app/embed/chat?bot=42', $html);
        $this->assertStringNotContainsString('/bot/42', $html);
    }

    public function test_shortcode_renders_embed_chat_route(): void
    {
        $configMock = $this->createMock(Config::class);
        $configMock->method('isConnected')->willReturn(true);
        $configMock->method('getChatbotId')->willReturn(42);
        $configMock->method('getBotPlusApiUrl')->willReturn('https://botplus.app');

        Bootstrapper::container()->instance(Config::class, $configMock);

        Functions\when('esc_url')->returnArg(1);
        Functions\when('esc_attr')->returnArg(1);
        Functions\when('shortcode_atts')->alias(function ($defaults, $atts) {
            return array_merge($defaults, $atts ?: []);
        });

        $shortcode = new Shortcode();
        $html = $shortcode->render(['height' => '650px']);

        $this->assertStringContainsString('https://botplus.app/embed/chat?bot=42', $html);
        $this->assertStringNotContainsString('/bot/42', $html);
    }

    public function test_chat_widget_customer_context_resolves_user_id_as_wc_customer_id(): void
    {
        $user = new class {
            public int $ID = 789;
            public string $user_email = 'shopper@example.com';
            public string $display_name = 'Test Shopper';
            public function exists(): bool { return true; }
        };

        Functions\when('wp_get_current_user')->justReturn($user);

        $widget = new ChatWidget();
        $reflection = new \ReflectionClass($widget);
        $method = $reflection->getMethod('getUserContext');
        $method->setAccessible(true);

        $context = $method->invoke($widget);

        $this->assertIsArray($context);
        $this->assertEquals(789, $context['id']);
        $this->assertEquals(789, $context['wc_customer_id']);
        $this->assertEquals('shopper@example.com', $context['email']);
    }
}
