<?php

namespace BotPlus\Plugin\Rest;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class RestRouter
{
    public function register(): void
    {
        // Chat endpoints
        register_rest_route('botplus/v1', '/chat/(?P<chatbot_id>\d+)', [
            'methods' => 'POST',
            'callback' => [\BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Rest\Controllers\ChatController::class), 'handle'],
            'permission_callback' => '__return_true',
            'args' => [
                'chatbot_id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        register_rest_route('botplus/v1', '/chat/(?P<chatbot_id>\d+)/bootstrap', [
            'methods' => 'POST',
            'callback' => [\BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Rest\Controllers\BootstrapController::class), 'handle'],
            'permission_callback' => '__return_true',
            'args' => [
                'chatbot_id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        register_rest_route('botplus/v1', '/chat/(?P<chatbot_id>\d+)/cart-sync', [
            'methods' => 'POST',
            'callback' => [\BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Rest\Controllers\CartController::class), 'handle'],
            'permission_callback' => '__return_true',
            'args' => [
                'chatbot_id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        register_rest_route('botplus/v1', '/chat/(?P<chatbot_id>\d+)/lead-capture', [
            'methods' => 'POST',
            'callback' => [\BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Rest\Controllers\LeadController::class), 'handle'],
            'permission_callback' => '__return_true',
            'args' => [
                'chatbot_id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        // Real-time storefront cart reader for iframe bridging
        register_rest_route('botplus/v1', '/storefront/cart', [
            'methods'             => 'GET',
            'callback'            => [\BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Rest\Controllers\StorefrontCartController::class), 'handle'],
            'permission_callback' => '__return_true',
        ]);

        // Real-time storefront cart action handler for iframe bridging
        register_rest_route('botplus/v1', '/storefront/cart/action', [
            'methods'             => 'POST',
            'callback'            => [\BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Rest\Controllers\StorefrontCartController::class), 'handleAction'],
            'permission_callback' => '__return_true',
            'args'                => [
                'action' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_key',
                    'validate_callback' => function ($param) {
                        return in_array($param, ['add', 'update', 'remove'], true);
                    },
                ],
                'productId' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'product_id' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'variantId' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'variant_id' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'lineKey' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'line_key' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'quantity' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'intval',
                ],
            ],
        ]);

        // One-click storefront cart restore and checkout redirect handler
        register_rest_route('botplus/v1', '/storefront/cart/restore', [
            'methods'             => ['GET', 'POST'],
            'callback'            => [\BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Rest\Controllers\StorefrontCartController::class), 'handleRestore'],
            'permission_callback' => '__return_true',
            'args'                => [
                'items' => [
                    'required'          => false,
                    'type'              => 'array',
                ],
                'clear_cart' => [
                    'required'          => false,
                    'type'              => 'boolean',
                ],
                'redirect' => [
                    'required'          => false,
                    'type'              => 'boolean',
                ],
            ],
        ]);

        // Real-time storefront order tracking handler
        register_rest_route('botplus/v1', '/storefront/order/track', [
            'methods'             => 'GET',
            'callback'            => [\BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Rest\Controllers\StorefrontOrderController::class), 'handleTrack'],
            'permission_callback' => '__return_true',
            'args'                => [
                'order_number' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'orderNumber' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'email' => [
                    'required'          => false,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'customer_id' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'customerId' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route('botplus/v1', '/test-webhook', [
            'methods'             => 'POST',
            'permission_callback' => function () {
                return function_exists('current_user_can') && current_user_can('manage_options');
            },
            'callback'            => function () {
                $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
                if (!$config->isConnected() || !$config->getSiteId()) {
                    return function_exists('rest_ensure_response') 
                        ? rest_ensure_response(['success' => false, 'message' => 'Site not connected']) 
                        : ['success' => false, 'message' => 'Site not connected'];
                }

                $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
                $res = $apiClient->forwardWebhook(
                    (int) $config->getSiteId(),
                    'site.ping',
                    0,
                    ['source' => 'rest_test_endpoint', 'timestamp' => time()],
                    'site',
                    true
                );

                return function_exists('rest_ensure_response') ? rest_ensure_response($res) : $res;
            },
        ]);
    }
}
