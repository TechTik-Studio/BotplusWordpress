<?php

namespace BotPlus\Plugin\Api;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class LaravelApiClient
{
    protected $config;
    protected $httpClient;
    protected $baseUrl;

    public function __construct(\BotPlus\Plugin\Core\Config $config)
    {
        $this->config = $config;
        $this->baseUrl = rtrim($config->getBotPlusApiUrl(), '/');
        $sslVerify = function_exists('apply_filters')
            ? (bool) apply_filters('botplus_sslverify', true)
            : true;

        if (class_exists(\GuzzleHttp\Client::class)) {
            $this->httpClient = new \GuzzleHttp\Client([
                'base_uri' => $this->baseUrl . '/api/',
                'timeout' => 30,
                'http_errors' => false,
                'verify' => $sslVerify,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'BotPlus-WordPress/' . (defined('BOTPLUS_VERSION') ? BOTPLUS_VERSION : '1.0.0'),
                ],
            ]);
        }
    }

    protected function getAuthHeaders(): array
    {
        $headers = [];
        $jwt = $this->config->getJwtToken();
        if ($jwt) {
            $headers['Authorization'] = 'Bearer ' . $jwt;
        }

        $siteUrl = function_exists('home_url') ? home_url() : '';
        if (!empty($siteUrl)) {
            $headers['X-BotPlus-Origin'] = $siteUrl;
        }

        return $headers;
    }

    public function validateSite(array $credentials): array
    {
        $response = $this->post('wordpress/sites/validate', $credentials);
        return json_decode($response->getBody(), true);
    }

    public function exchangeCode(string $code, array $capabilities = []): array
    {
        try {
            $siteUrl = function_exists('home_url') ? home_url() : '';
            $siteName = function_exists('get_bloginfo') ? get_bloginfo('name') : 'WordPress Site';
            $payload = [
                'code' => $code,
                'site_url' => $siteUrl,
                'site_name' => $siteName,
            ];
            if (!empty($capabilities)) {
                $payload['capabilities'] = $capabilities;
            }
            $response = $this->post('wordpress/exchange', $payload);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? [];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function chat(int $chatbotId, string $message, ?string $conversationId, array $context): array
    {
        $payload = [
            'message' => $message,
            'conversation_id' => $conversationId,
            'channel' => 'embedded',
            'context' => $context,
        ];

        $response = $this->post("chat/{$chatbotId}", $payload);
        return json_decode($response->getBody(), true);
    }

    public function bootstrapChat(int $chatbotId, array $context): array
    {
        $response = $this->post("chat/{$chatbotId}/bootstrap", $context);
        return json_decode($response->getBody(), true);
    }

    public function syncCart(int $chatbotId, string $conversationId, string $cartHash): array
    {
        $response = $this->post("chat/{$chatbotId}/cart-sync", [
            'conversation_id' => $conversationId,
            'cart_hash' => $cartHash,
        ]);
        return json_decode($response->getBody(), true);
    }

    public function captureLead(int $chatbotId, string $conversationId, array $data): array
    {
        $response = $this->post("chat/{$chatbotId}/lead-capture", array_merge([
            'conversation_id' => $conversationId,
        ], $data));
        return json_decode($response->getBody(), true);
    }

    public function triggerSync(int $siteId, array $types = ['products', 'content']): array
    {
        $response = $this->post("wordpress/sites/{$siteId}/sync", [
            'types' => $types,
        ]);
        return json_decode($response->getBody(), true);
    }

    public function sendSyncBatch(int $siteId, string $syncType, array $items, array $batchMeta = []): array
    {
        try {
            $siteUrl = function_exists('home_url') ? home_url() : '';
            $payload = array_merge([
                'sync_type' => $syncType,
                'site_url'  => $siteUrl,
                'items'     => $items,
            ], $batchMeta);

            $response = $this->post("wordpress/sites/{$siteId}/sync", $payload);
            return json_decode((string) $response->getBody(), true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function forwardWebhook(
        int $siteId,
        string $topic,
        int $resourceId,
        array $payload,
        ?string $resourceType = null,
        bool $async = true
    ): array {
        $eventId = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            wp_rand(0, 0xffff), wp_rand(0, 0xffff), wp_rand(0, 0xffff),
            wp_rand(0, 0x0fff) | 0x4000,
            wp_rand(0, 0x3fff) | 0x8000,
            wp_rand(0, 0xffff), wp_rand(0, 0xffff), wp_rand(0, 0xffff)
        );

        $siteUrl = function_exists('home_url') ? home_url() : '';
        $triggeredAt = time();
        $isComingSoon = function_exists('get_option') && get_option('woocommerce_coming_soon') === 'yes';

        $envelope = [
            'site_id'          => $siteId,
            'site_url'         => $siteUrl,
            'topic'            => $topic,
            'resource_id'      => $resourceId,
            'resource_type'    => $resourceType ?? (str_starts_with($topic, 'product') ? 'product' : 'page'),
            'event_id'         => $eventId,
            'triggered_at'     => $triggeredAt,
            'store_visibility' => $isComingSoon ? 'coming_soon' : 'live',
            'payload'          => $payload,
        ];

        $rawBody = $this->jsonEncode($envelope);
        $secret = $this->config->getWebhookSecret() ?: $this->config->getJwtToken();
        $signature = $secret ? hash_hmac('sha256', $rawBody, $secret) : '';

        // If asynchronous mode is requested and wp_remote_post is available, fire HTTP request
        if ($async && function_exists('wp_remote_post')) {
            $apiUrl = rtrim($this->baseUrl, '/') . '/api/wordpress/webhooks';
            $headers = array_merge($this->getAuthHeaders(), [
                'Content-Type'          => 'application/json',
                'X-BotPlus-Signature'   => $signature,
                'X-BotPlus-Webhook-Id'  => $eventId,
                'X-BotPlus-Triggered-At'=> (string) $triggeredAt,
            ]);

            $blocking = function_exists('apply_filters') ? (bool) apply_filters('botplus_webhook_blocking', true) : true;
            $timeout = function_exists('apply_filters') ? (int) apply_filters('botplus_webhook_timeout', 5) : 5;
            $sslverify = function_exists('apply_filters') ? (bool) apply_filters('botplus_webhook_sslverify', true) : true;

            $response = wp_remote_post($apiUrl, [
                'headers'   => $headers,
                'body'      => $rawBody,
                'timeout'   => $timeout,
                'blocking'  => $blocking,
                'sslverify' => $sslverify,
            ]);

            if (function_exists('is_wp_error') && is_wp_error($response)) {
                $errorMsg = $response->get_error_message();
                if (function_exists('botplus_log')) {
                    botplus_log("BotPlus Webhook Dispatch Failed: {$errorMsg} (URL: {$apiUrl})", [], 'error');
                }
                if (function_exists('update_option')) {
                    update_option('botplus_webhook_last_error', [
                        'timestamp'     => time(),
                        'topic'         => $topic,
                        'resource_id'   => $resourceId,
                        'api_url'       => $apiUrl,
                        'error_message' => $errorMsg,
                    ]);
                }
                return ['success' => false, 'message' => $errorMsg, 'event_id' => $eventId];
            }

            return ['success' => true, 'async' => !$blocking, 'event_id' => $eventId];
        }

        // Synchronous HTTP client fallback (e.g., during tests or batch jobs)
        try {
            $headers = [
                'X-BotPlus-Signature'   => $signature,
                'X-BotPlus-Webhook-Id'  => $eventId,
                'X-BotPlus-Triggered-At'=> (string) $triggeredAt,
            ];
            $response = $this->httpClient->post('wordpress/webhooks', [
                'headers' => array_merge($this->getAuthHeaders(), $headers),
                'body'    => $rawBody,
            ]);

            return json_decode((string) $response->getBody(), true) ?? ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function disconnectSite(): array
    {
        try {
            $siteUrl = function_exists('home_url') ? home_url() : '';
            $response = $this->post('wordpress/disconnect', [
                'site_url' => $siteUrl,
            ]);
            return json_decode($response->getBody(), true) ?? ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getDashboardOverview(int $siteId, string $period = '30d'): array
    {
        try {
            $response = $this->get("wordpress/sites/{$siteId}/dashboard/overview", [
                'period' => $period,
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (!is_array($data)) {
                return [
                    'success' => false,
                    'message' => 'Malformed response from BotPlus platform.',
                ];
            }

            return $data;
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getAnalytics(int $siteId, string $period = '30d'): array
    {
        try {
            $response = $this->get("wordpress/sites/{$siteId}/analytics", [
                'period' => $period,
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (!is_array($data)) {
                return [
                    'success' => false,
                    'message' => 'Malformed response from BotPlus platform.',
                ];
            }

            return $data;
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getInboxFirebaseAuth(int $siteId): array
    {
        try {
            $response = $this->get("wordpress/sites/{$siteId}/inbox/firebase-token");
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getInboxConversations(int $siteId, array $params = []): array
    {
        try {
            $response = $this->get("wordpress/sites/{$siteId}/inbox/conversations", $params);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getConversationMessages(int $siteId, string $conversationId): array
    {
        try {
            $response = $this->get("wordpress/sites/{$siteId}/inbox/conversations/{$conversationId}/messages");
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function sendHumanMessage(int $siteId, string $conversationId, string $text, string $operatorName = 'Store Operator'): array
    {
        try {
            $response = $this->post("wordpress/sites/{$siteId}/inbox/conversations/{$conversationId}/messages", [
                'message'       => $text,
                'operator_name' => $operatorName,
            ]);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function claimConversation(int $siteId, string $conversationId, string $operatorName = 'Store Operator'): array
    {
        try {
            $response = $this->post("wordpress/sites/{$siteId}/inbox/conversations/{$conversationId}/claim", [
                'operator_name' => $operatorName,
            ]);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function releaseConversation(int $siteId, string $conversationId): array
    {
        try {
            $response = $this->post("wordpress/sites/{$siteId}/inbox/conversations/{$conversationId}/release", []);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function addCaseNote(int $siteId, int $caseId, string $bodyText, string $noteType = 'internal'): array
    {
        try {
            $response = $this->post("wordpress/sites/{$siteId}/inbox/cases/{$caseId}/notes", [
                'body'      => $bodyText,
                'note_type' => $noteType,
            ]);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateCaseStatus(int $siteId, int $caseId, string $status): array
    {
        try {
            $response = $this->put("wordpress/sites/{$siteId}/inbox/cases/{$caseId}/status", ['status' => $status]);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getLeads(int $siteId, array $params = []): array
    {
        try {
            $response = $this->get("wordpress/sites/{$siteId}/leads", $params);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getCarts(int $siteId, array $params = []): array
    {
        try {
            $response = $this->get("wordpress/sites/{$siteId}/carts", $params);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getAgentSettings(int $siteId): array
    {
        try {
            $response = $this->get("wordpress/sites/{$siteId}/settings");
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function saveAgentSettings(int $siteId, array $data): array
    {
        try {
            $response = $this->post("wordpress/sites/{$siteId}/settings", $data);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getCapabilities(int $siteId): array
    {
        try {
            $response = $this->get("wordpress/sites/{$siteId}/capabilities");
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggleCapability(int $siteId, string $feature, bool $enabled): array
    {
        try {
            $response = $this->post("wordpress/sites/{$siteId}/capabilities/toggle", [
                'feature' => $feature,
                'enabled' => $enabled,
            ]);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getBillingState(int $siteId): array
    {
        try {
            $response = $this->get("wordpress/sites/{$siteId}/billing");
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function createBillingCheckoutSession(int $siteId, string $packageSlug, string $interval = 'monthly', ?string $returnUrl = null): array
    {
        try {
            $response = $this->post("wordpress/sites/{$siteId}/billing/checkout-session", [
                'package_slug' => $packageSlug,
                'interval'     => $interval,
                'return_url'   => $returnUrl,
            ]);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function cancelBillingSubscription(int $siteId): array
    {
        try {
            $response = $this->post("wordpress/sites/{$siteId}/billing/cancel", []);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function createBillingCustomerSession(int $siteId): array
    {
        try {
            $response = $this->post("wordpress/sites/{$siteId}/billing/customer-session", []);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? ['success' => false];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Checks BotPlus cloud API for available plugin updates and release metadata.
     */
    public function checkPluginUpdate(string $currentVersion): array
    {
        try {
            $siteUrl = function_exists('home_url') ? home_url() : '';
            $response = $this->get('wordpress/plugin-update-check', [
                'version'  => $currentVersion,
                'site_url' => $siteUrl,
            ]);
            $body = (string) $response->getBody();
            return json_decode($body, true) ?? [];
        } catch (\Throwable $e) {
            return [
                'has_update' => false,
                'error'      => $e->getMessage(),
            ];
        }
    }

    /**
     * Executes an HTTP request using WordPress native APIs with fallback.
     */
    protected function request(string $method, string $endpoint, array $data = [], array $query = [])
    {
        $url = rtrim($this->baseUrl, '/') . '/api/' . ltrim($endpoint, '/');
        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers = array_merge([
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent'   => 'BotPlus-WordPress/' . (defined('BOTPLUS_VERSION') ? BOTPLUS_VERSION : '1.0.0'),
        ], $this->getAuthHeaders());

        $sslVerify = function_exists('apply_filters')
            ? (bool) apply_filters('botplus_sslverify', true)
            : true;

        if (function_exists('wp_remote_request')) {
            $args = [
                'method'    => strtoupper($method),
                'headers'   => $headers,
                'timeout'   => 30,
                'sslverify' => $sslVerify,
            ];

            if (!empty($data) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
                $args['body'] = $this->jsonEncode($data);
            }

            $wpResponse = wp_remote_request($url, $args);

            if (function_exists('is_wp_error') && is_wp_error($wpResponse)) {
                $status = 500;
                $body = $this->jsonEncode(['success' => false, 'message' => $wpResponse->get_error_message()]);
            } else {
                $status = function_exists('wp_remote_retrieve_response_code') ? (int) wp_remote_retrieve_response_code($wpResponse) : 200;
                $body = function_exists('wp_remote_retrieve_body') ? (string) wp_remote_retrieve_body($wpResponse) : ($wpResponse['body'] ?? '');
            }

            return new class($status, $body) {
                protected int $statusCode;
                protected string $body;
                public function __construct(int $status, string $body) {
                    $this->statusCode = $status;
                    $this->body = $body;
                }
                public function getStatusCode(): int { return $this->statusCode; }
                public function getBody(): string { return $this->body; }
                public function __toString(): string { return $this->body; }
            };
        }

        if ($this->httpClient) {
            $options = ['headers' => $headers];
            if (!empty($data)) {
                $options['json'] = $data;
            }
            if (!empty($query)) {
                $options['query'] = $query;
            }
            return $this->httpClient->request($method, $endpoint, $options);
        }

        return new class(500, $this->jsonEncode(['success' => false, 'message' => 'No HTTP transport available'])) {
            protected int $statusCode;
            protected string $body;
            public function __construct(int $status, string $body) {
                $this->statusCode = $status;
                $this->body = $body;
            }
            public function getStatusCode(): int { return $this->statusCode; }
            public function getBody(): string { return $this->body; }
            public function __toString(): string { return $this->body; }
        };
    }

    protected function jsonEncode(mixed $value): string
    {
        return function_exists('wp_json_encode') ? (string) wp_json_encode($value) : (string) json_encode($value);
    }

    protected function post(string $endpoint, array $data)
    {
        return $this->request('POST', $endpoint, $data);
    }

    protected function get(string $endpoint, array $query = [])
    {
        return $this->request('GET', $endpoint, [], $query);
    }

    protected function put(string $endpoint, array $data)
    {
        return $this->request('PUT', $endpoint, $data);
    }
}
