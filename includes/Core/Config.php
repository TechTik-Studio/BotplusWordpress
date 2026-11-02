<?php

namespace BotPlus\Plugin\Core;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class Config
{
    protected $settings = [];

    public function __construct()
    {
        $this->loadSettings();
    }

    protected function loadSettings(): void
    {
        $defaults = [
            'jwt_token' => '',
            'webhook_secret' => '',
            'site_id' => null,
            'chatbot_id' => null,
            'chatbot_name' => '',
            'connected' => false,
            'botplus_user_id' => null,
            'botplus_user_email' => null,
            'connected_at' => null,
            'manage_url' => '',
        ];

        $this->settings = function_exists('get_option') ? get_option('botplus_settings', $defaults) : $defaults;
        if (!is_array($this->settings)) {
            $this->settings = $defaults;
        }
    }

    public function get(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->settings[$key] = $value;
        if (function_exists('update_option')) {
            update_option('botplus_settings', $this->settings);
        }
    }

    public function getJwtToken(): ?string
    {
        $raw = $this->get('jwt_token');
        if (empty($raw)) {
            return null;
        }

        if (str_starts_with($raw, 'enc:v1:')) {
            try {
                $decrypted = \BotPlus\Plugin\Utilities\Encryption::decrypt(substr($raw, 7));
                return $decrypted !== '' ? $decrypted : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return $raw;
    }

    public function getWebhookSecret(): ?string
    {
        $raw = $this->get('webhook_secret');
        if (empty($raw)) {
            return null;
        }

        if (str_starts_with($raw, 'enc:v1:')) {
            try {
                $decrypted = \BotPlus\Plugin\Utilities\Encryption::decrypt(substr($raw, 7));
                return $decrypted !== '' ? $decrypted : null;
            } catch (\Throwable) {
                return null;
            }
        }

        return $raw;
    }

    public function setWebhookSecret(string $secret): void
    {
        $stored = empty($secret) ? '' : 'enc:v1:' . \BotPlus\Plugin\Utilities\Encryption::encrypt($secret);
        $this->set('webhook_secret', $stored);
    }

    public function getSiteId(): ?int
    {
        return $this->get('site_id');
    }

    public function getChatbotId(): ?int
    {
        return $this->get('chatbot_id');
    }

    public function getChatbotName(): ?string
    {
        return $this->get('chatbot_name');
    }

    public function isConnected(): bool
    {
        return (bool) $this->get('connected', false);
    }

    public function getBotPlusUserId(): ?int
    {
        return $this->get('botplus_user_id');
    }

    public function getBotPlusUserEmail(): ?string
    {
        return $this->get('botplus_user_email');
    }

    public function getConnectedAt(): ?string
    {
        return $this->get('connected_at');
    }

    public function getManageUrl(): ?string
    {
        return $this->get('manage_url');
    }

    public function getWidgetSettings(): array
    {
        return get_option('botplus_widget_settings', [
            'position' => 'bottom-right',
            'theme' => 'glass',
            'primary_color' => '#3B82F6',
            'welcome_message' => 'Hi! How can I help you today?',
            'offline_message' => 'We\'re offline. Leave a message!',
        ]);
    }

    public function setWidgetSettings(array $settings): void
    {
        update_option('botplus_widget_settings', $settings);
    }

    public function getWebhookTelemetry(): array
    {
        return function_exists('get_option') ? get_option('botplus_webhook_telemetry', [
            'last_dispatched_at' => null,
            'last_topic'         => null,
            'last_resource_id'   => null,
            'total_dispatched'   => 0,
        ]) : [
            'last_dispatched_at' => null,
            'last_topic'         => null,
            'last_resource_id'   => null,
            'total_dispatched'   => 0,
        ];
    }

    public function setConnectionData(array $data): void
    {
        $jwt = $data['jwt_token'] ?? '';
        $storedJwt = empty($jwt) ? '' : 'enc:v1:' . \BotPlus\Plugin\Utilities\Encryption::encrypt($jwt);

        $secret = $data['webhook_secret'] ?? '';
        $storedSecret = empty($secret) ? '' : 'enc:v1:' . \BotPlus\Plugin\Utilities\Encryption::encrypt($secret);

        $this->set('jwt_token', $storedJwt);
        $this->set('webhook_secret', $storedSecret);
        $this->set('site_id', $data['site_id'] ?? null);
        $this->set('chatbot_id', $data['chatbot_id'] ?? null);
        $this->set('chatbot_name', $data['chatbot_name'] ?? '');
        $this->set('botplus_user_id', $data['botplus_user_id'] ?? null);
        $this->set('botplus_user_email', $data['botplus_user_email'] ?? null);
        $this->set('connected_at', $data['connected_at'] ?? (function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s')));
        $this->set('manage_url', $data['manage_url'] ?? '');
        $this->set('connected', true);
    }

    public function disconnect(): void
    {
        $this->set('jwt_token', '');
        $this->set('webhook_secret', '');
        $this->set('site_id', null);
        $this->set('chatbot_id', null);
        $this->set('chatbot_name', '');
        $this->set('botplus_user_id', null);
        $this->set('botplus_user_email', null);
        $this->set('connected_at', null);
        $this->set('manage_url', '');
        $this->set('connected', false);
    }

    // BotPlus API base URL (constant takes top precedence to allow dev/local bypass, then stored setting, then default)
    public function getBotPlusApiUrl(): string
    {
        // 1. Highest Priority: Constant defined in botplus.php or wp-config.php (bypasses DB for dev/local/testing)
        if (defined('BOTPLUS_API_URL') && !empty(BOTPLUS_API_URL)) {
            return rtrim((string) BOTPLUS_API_URL, '/');
        }

        // 2. Check stored setting from database option
        $url = $this->get('botplus_api_url');
        if (!empty($url)) {
            return rtrim((string) $url, '/');
        }

        // 3. Default production URL
        return 'https://botplus.app';
    }

    public function setBotPlusApiUrl(string $url): void
    {
        $this->set('botplus_api_url', rtrim($url, '/'));
    }

    public function getConnectUrl(): string
    {
        return $this->getBotPlusApiUrl() . '/connect/wordpress';
    }
}
