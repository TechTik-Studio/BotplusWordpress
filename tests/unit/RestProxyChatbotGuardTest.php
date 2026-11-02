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
use BotPlus\Plugin\Rest\Controllers\ChatController;
use BotPlus\Plugin\Rest\Controllers\BootstrapController;
use BotPlus\Plugin\Rest\Controllers\CartController;
use BotPlus\Plugin\Rest\Controllers\LeadController;
use BotPlus\Plugin\Core\Bootstrapper;
use BotPlus\Plugin\Core\Config;

class RestProxyChatbotGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Bootstrapper::resetContainer();

        Functions\when('get_option')->justReturn([]);
        Functions\when('update_option')->justReturn(true);
        Functions\when('current_time')->justReturn('2026-10-26 12:00:00');
        Functions\when('sanitize_textarea_field')->alias(fn($v) => $v);
        Functions\when('sanitize_text_field')->alias(fn($v) => $v);
    }

    protected function tearDown(): void
    {
        Bootstrapper::resetContainer();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testChatControllerRejectsChatbotIdMismatch(): void
    {
        $config = new Config();
        $config->setConnectionData([
            'chatbot_id' => 10,
            'jwt_token'  => 'token',
            'site_id'    => 1,
        ]);
        Bootstrapper::container()->instance(Config::class, $config);

        $controller = new ChatController();
        $request = new \WP_REST_Request('POST', '/botplus/v1/chat/99');
        $request->set_param('chatbot_id', 99);

        $response = $controller->handle($request);

        $this->assertEquals(403, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Chatbot ID mismatch', $data['message']);
    }

    public function testBootstrapControllerRejectsChatbotIdMismatch(): void
    {
        $config = new Config();
        $config->setConnectionData([
            'chatbot_id' => 10,
            'jwt_token'  => 'token',
            'site_id'    => 1,
        ]);
        Bootstrapper::container()->instance(Config::class, $config);

        $controller = new BootstrapController();
        $request = new \WP_REST_Request('POST', '/botplus/v1/chat/99/bootstrap');
        $request->set_param('chatbot_id', 99);

        $response = $controller->handle($request);

        $this->assertEquals(403, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Chatbot ID mismatch', $data['message']);
    }

    public function testCartControllerRejectsChatbotIdMismatch(): void
    {
        $config = new Config();
        $config->setConnectionData([
            'chatbot_id' => 10,
            'jwt_token'  => 'token',
            'site_id'    => 1,
        ]);
        Bootstrapper::container()->instance(Config::class, $config);

        $controller = new CartController();
        $request = new \WP_REST_Request('POST', '/botplus/v1/chat/99/cart-sync');
        $request->set_param('chatbot_id', 99);

        $response = $controller->handle($request);

        $this->assertEquals(403, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Chatbot ID mismatch', $data['message']);
    }

    public function testLeadControllerRejectsChatbotIdMismatch(): void
    {
        $config = new Config();
        $config->setConnectionData([
            'chatbot_id' => 10,
            'jwt_token'  => 'token',
            'site_id'    => 1,
        ]);
        Bootstrapper::container()->instance(Config::class, $config);

        $controller = new LeadController();
        $request = new \WP_REST_Request('POST', '/botplus/v1/chat/99/lead-capture');
        $request->set_param('chatbot_id', 99);

        $response = $controller->handle($request);

        $this->assertEquals(403, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Chatbot ID mismatch', $data['message']);
    }
}

}
