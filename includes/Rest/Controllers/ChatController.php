<?php

namespace BotPlus\Plugin\Rest\Controllers;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class ChatController
{
    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $chatbotId = (int) $request->get_param('chatbot_id');
        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $configuredBotId = (int) $config->getChatbotId();

        if ($configuredBotId > 0 && $chatbotId !== $configuredBotId) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'Forbidden',
                'message' => 'Chatbot ID mismatch with store configuration.',
            ], 403);
        }

        $params = $request->get_json_params();

        $message = sanitize_textarea_field($params['message'] ?? '');
        $conversationId = $params['conversation_id'] ?? null;
        $context = $params['context'] ?? [];

        if (empty($message)) {
            return new \WP_REST_Response(['error' => 'Message is required'], 400);
        }

        try {
            $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $apiClient->chat($chatbotId, $message, $conversationId, $context);

            return new \WP_REST_Response($result, 200);
        } catch (\Exception $e) {
            if (function_exists('botplus_log')) {
                botplus_log('BotPlus Chat Error: ' . $e->getMessage(), [], 'error');
            }
            return new \WP_REST_Response(['error' => 'Chat service unavailable'], 503);
        }
    }
}
