<?php

namespace BotPlus\Plugin\Admin;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class SettingsPage
{
    public function __construct()
    {
        // Register AJAX handlers
        add_action('wp_ajax_botplus_save_connection', [$this, 'ajaxSaveConnection']);
        add_action('wp_ajax_botplus_disconnect', [$this, 'ajaxDisconnect']);
        add_action('wp_ajax_botplus_exchange_code', [$this, 'ajaxExchangeCode']);
        add_action('wp_ajax_botplus_sync_knowledge', [$this, 'ajaxSyncKnowledge']);
        add_action('wp_ajax_botplus_test_webhook', [$this, 'ajaxTestWebhook']);
        add_action('wp_ajax_botplus_get_dashboard_overview', [$this, 'ajaxGetDashboardOverview']);
        add_action('wp_ajax_botplus_get_analytics', [$this, 'ajaxGetAnalytics']);
        add_action('wp_ajax_botplus_inbox_firebase_auth', [$this, 'ajaxInboxFirebaseAuth']);
        add_action('wp_ajax_botplus_inbox_conversations', [$this, 'ajaxInboxConversations']);
        add_action('wp_ajax_botplus_inbox_messages', [$this, 'ajaxInboxMessages']);
        add_action('wp_ajax_botplus_inbox_send_reply', [$this, 'ajaxInboxSendReply']);
        add_action('wp_ajax_botplus_inbox_claim', [$this, 'ajaxInboxClaim']);
        add_action('wp_ajax_botplus_inbox_release', [$this, 'ajaxInboxRelease']);
        add_action('wp_ajax_botplus_inbox_add_note', [$this, 'ajaxInboxAddNote']);
        add_action('wp_ajax_botplus_inbox_update_status', [$this, 'ajaxInboxUpdateStatus']);
        add_action('wp_ajax_botplus_get_leads', [$this, 'ajaxGetLeads']);
        add_action('wp_ajax_botplus_get_carts', [$this, 'ajaxGetCarts']);
        add_action('wp_ajax_botplus_get_agent_settings', [$this, 'ajaxGetAgentSettings']);
        add_action('wp_ajax_botplus_save_agent_settings', [$this, 'ajaxSaveAgentSettings']);
        add_action('wp_ajax_botplus_upload_avatar', [$this, 'ajaxUploadAvatar']);
        add_action('wp_ajax_botplus_get_capabilities', [$this, 'ajaxGetCapabilities']);
        add_action('wp_ajax_botplus_toggle_capability', [$this, 'ajaxToggleCapability']);
        add_action('wp_ajax_botplus_get_billing_state', [$this, 'ajaxGetBillingState']);
        add_action('wp_ajax_botplus_create_billing_checkout', [$this, 'ajaxCreateBillingCheckout']);
        add_action('wp_ajax_botplus_cancel_billing', [$this, 'ajaxCancelBilling']);
        add_action('wp_ajax_botplus_create_billing_customer_session', [$this, 'ajaxCreateBillingCustomerSession']);
        add_action('wp_ajax_botplus_clear_webhook_error', [$this, 'ajaxClearWebhookError']);
        add_filter('script_loader_tag', [$this, 'filterScriptLoaderTag'], 10, 3);
        add_action('admin_init', [$this, 'handleCallback']);
    }

    public function registerMenu(): void
    {
        $botplusLogoSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="none">' .
            '<defs>' .
            '<linearGradient id="bpLogoGrad" x1="2.5" y1="5.5" x2="29.5" y2="26.5" gradientUnits="userSpaceOnUse">' .
            '<stop offset="0%" stop-color="#4F46E5"/>' .
            '<stop offset="50%" stop-color="#2563EB"/>' .
            '<stop offset="100%" stop-color="#06B6D4"/>' .
            '</linearGradient>' .
            '</defs>' .
            '<path fill-rule="evenodd" clip-rule="evenodd" d="M9.25 21.42L9.42 22.3L12.79 19.67L17.35 19.67L19.38 18.62L19.38 22.48L17.69 23.0L12.96 23.0L8.74 26.32L7.22 26.32L6.55 25.62L6.38 21.42L5.2 20.72L5.2 20.38L4.69 20.2L3.85 18.98L2.84 16.7L2.5 13.9L3.01 11.45L4.53 8.65L6.21 7.08L8.74 5.85L10.94 5.5L16.34 5.5L19.38 6.2L19.38 9.88L18.87 9.88L17.52 9.0L9.93 9.0L8.57 9.52L6.89 11.1L6.04 12.85L6.04 15.65L7.06 17.75L9.25 19.32L9.25 21.25Z" fill="url(#bpLogoGrad)"/>' .
            '<path d="M18.53 12.68L20.89 12.68L20.89 5.5L24.1 5.5L24.1 12.68L29.33 12.68L29.33 16.0L24.1 15.82L24.1 23.0L20.89 23.0L20.89 16.0L14.82 16.0L14.82 12.68L18.36 12.68Z" fill="url(#bpLogoGrad)"/>' .
            '</svg>';

        $iconSvg = 'data:image/svg+xml;base64,' . base64_encode($botplusLogoSvg);

        // Top-Level BotPlus AI Menu (Position 56, right below WooCommerce)
        add_menu_page(
            __('BotPlus AI', 'botplus-ai-agents'),
            __('BotPlus AI', 'botplus-ai-agents'),
            'manage_options',
            'botplus',
            [$this, 'render'],
            $iconSvg,
            56
        );

        $submenus = [
            ['botplus',              __('Dashboard', 'botplus-ai-agents'),         __('Dashboard', 'botplus-ai-agents')],
            ['botplus-inbox',        __('Inbox & Live Chat', 'botplus-ai-agents'), __('Inbox', 'botplus-ai-agents')],
            ['botplus-carts',        __('Carts & Recovery', 'botplus-ai-agents'),  __('Carts', 'botplus-ai-agents')],
            ['botplus-leads',        __('Opportunities', 'botplus-ai-agents'),     __('Opportunities', 'botplus-ai-agents')],
            ['botplus-analytics',    __('Analytics', 'botplus-ai-agents'),         __('Analytics', 'botplus-ai-agents')],
            ['botplus-agent',        __('Agent Settings', 'botplus-ai-agents'),    __('Agent Settings', 'botplus-ai-agents')],
            ['botplus-capabilities', __('Capabilities', 'botplus-ai-agents'),      __('Capabilities', 'botplus-ai-agents')],
            ['botplus-billing',      __('Billing & Plans', 'botplus-ai-agents'),   __('Billing', 'botplus-ai-agents')],
            ['botplus-settings',     __('Settings', 'botplus-ai-agents'),          __('Settings', 'botplus-ai-agents')],
        ];

        foreach ($submenus as [$slug, $pageTitle, $menuTitle]) {
            add_submenu_page(
                'botplus',
                $pageTitle,
                $menuTitle,
                'manage_options',
                $slug,
                [$this, 'render']
            );
        }

        // Backward compatibility: If visitor navigates to deprecated slugs, render seamlessly
        add_submenu_page(
            null,
            'Widget & Display',
            'Widget & Display',
            'manage_options',
            'botplus-widget',
            [$this, 'render']
        );
        add_submenu_page(
            null,
            'Store Knowledge',
            'Store Knowledge',
            'manage_options',
            'botplus-knowledge',
            [$this, 'render']
        );
        add_submenu_page(
            null,
            'Sync & Telemetry',
            'Sync & Telemetry',
            'manage_options',
            'botplus-sync',
            [$this, 'render']
        );

        // Backward compatibility: Also register under options-general.php
        add_options_page(
            __('BotPlus AI Agents', 'botplus-ai-agents'),
            __('BotPlus AI Agents', 'botplus-ai-agents'),
            'manage_options',
            'botplus-settings',
            [$this, 'render']
        );

        // Add callback page under options-general.php
        add_options_page(
            '',
            '',
            'manage_options',
            'botplus-callback',
            [$this, 'handleCallback']
        );

        // Also register under admin.php so admin.php?page=botplus-callback is valid in WordPress core
        add_submenu_page(
            null,
            'BotPlus Callback',
            'BotPlus Callback',
            'manage_options',
            'botplus-callback',
            [$this, 'handleCallback']
        );
    }

    public function enqueueAssets($hook): void
    {
        if (!str_contains((string) $hook, 'botplus')) {
            return;
        }

        // Enable WordPress core Media Library modal scripts
        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        wp_enqueue_script(
            'botplus-admin',
            BOTPLUS_PLUGIN_URL . 'build/botplus-admin.min.js',
            [],
            BOTPLUS_VERSION,
            true
        );

        wp_enqueue_style(
            'botplus-admin-css',
            BOTPLUS_PLUGIN_URL . 'build/botplus-admin.min.css',
            [],
            BOTPLUS_VERSION
        );

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $userId = get_current_user_id();
        $oauthState = wp_generate_password(32, false);
        set_transient('botplus_oauth_state_' . $userId, $oauthState, 600);

        $capabilities = \BotPlus\Plugin\Utilities\CapabilityDetector::detect();

        wp_localize_script('botplus-admin', 'BotPlusAdminConfig', [
            'apiUrl' => get_rest_url(null, 'botplus/v1'),
            'nonce' => wp_create_nonce('botplus_admin_action'),
            'oauthState' => $oauthState,
            'connected' => $config->isConnected(),
            'chatbotId' => $config->getChatbotId(),
            'chatbotName' => $config->getChatbotName(),
            'siteId' => $config->getSiteId(),
            'botplusUserId' => $config->getBotPlusUserId(),
            'botplusUserEmail' => $config->getBotPlusUserEmail(),
            'connectedAt' => $config->getConnectedAt(),
            'manageUrl' => $config->getManageUrl(),
            'connectUrl' => $config->getConnectUrl(),
            'botplusAppUrl' => $config->getBotPlusApiUrl(),
            'siteUrl' => home_url(),
            'siteName' => get_bloginfo('name'),
            'redirectUri' => admin_url('admin.php?page=botplus-billing'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'siteCapabilities' => $capabilities,
            'pluginUrl' => BOTPLUS_PLUGIN_URL,
            'logoUrl' => BOTPLUS_PLUGIN_URL . 'assets/images/botplus-logo.png',
            'lastSyncedAt' => function_exists('get_option') ? get_option('botplus_last_synced_at', '') : '',
            'lastSyncStats' => function_exists('get_option') ? get_option('botplus_last_sync_stats', []) : [],
            'lastError' => function_exists('get_option') ? get_option('botplus_webhook_last_error', null) : null,
            'webhookTelemetry' => function_exists('get_option') ? get_option('botplus_webhook_telemetry', []) : [],
            'retryBufferCount' => function_exists('get_option') ? count((array) (get_option('botplus_webhook_retry_buffer', []) ?: [])) : 0,
        ]);

        $initialState = [
            'connected'        => $config->isConnected(),
            'chatbotId'        => $config->getChatbotId() ? (int) $config->getChatbotId() : null,
            'chatbotName'      => (string) ($config->getChatbotName() ?? ''),
            'siteId'           => $config->getSiteId() ? (int) $config->getSiteId() : null,
            'botplusUserId'    => $config->getBotPlusUserId() ? (int) $config->getBotPlusUserId() : null,
            'botplusUserEmail' => (string) ($config->getBotPlusUserEmail() ?? ''),
            'connectedAt'      => (string) ($config->getConnectedAt() ?? ''),
            'manageUrl'        => (string) ($config->getManageUrl() ?? ''),
            'connectUrl'       => (string) $config->getConnectUrl(),
            'botplusAppUrl'    => (string) $config->getBotPlusApiUrl(),
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only inspection of active screen query param for asset bootstrap.
            'currentPage'      => isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'botplus',
            'currency'         => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
        ];

        if (function_exists('wp_add_inline_script')) {
            wp_add_inline_script(
                'botplus-admin',
                'window.BotPlusAdminInitialState = ' . (function_exists('wp_json_encode') ? wp_json_encode($initialState) : json_encode($initialState)) . ';',
                'before'
            );
        }
    }

    public function filterScriptLoaderTag(string $tag, string $handle, string $src): string
    {
        if ('botplus-admin' === $handle) {
            if (!str_contains($tag, 'type="module"')) {
                return preg_replace('/<script\b(?![^>]*\btype=)([^>]*\bid=[\'"]botplus-admin-js[\'"][^>]*)>/i', '<script type="module"$1>', $tag)
                    ?? str_replace('<script ', '<script type="module" ', $tag);
            }
        }
        return $tag;
    }

    public function render(): void
    {
        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $connected = $config->isConnected();
?>
        <div class="wrap botplus-admin">
            <?php $mismatch = function_exists('get_option') ? get_option('botplus_environment_mismatch') : null; ?>
            <?php if (!empty($mismatch)): ?>
                <div class="notice notice-warning is-dismissible" style="margin-top: 15px; padding: 12px 16px;">
                    <p><strong><?php esc_html_e('BotPlus Environment Notice:', 'botplus-ai-agents'); ?></strong>
                    <?php echo esc_html($mismatch['message'] ?? __('The current WordPress site URL does not match the connected BotPlus chatbot.', 'botplus-ai-agents')); ?>
                    <?php esc_html_e('If you changed domains or moved to a new environment, please reconnect BotPlus to bind this site.', 'botplus-ai-agents'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=botplus-settings')); ?>" style="margin-left: 8px; font-weight: 600; text-decoration: underline;"><?php esc_html_e('Manage Connection', 'botplus-ai-agents'); ?> &rarr;</a>
                    </p>
                </div>
            <?php endif; ?>

            <?php 
            $lastError = function_exists('get_option') ? get_option('botplus_webhook_last_error') : null;
            $telemetry = function_exists('get_option') ? get_option('botplus_webhook_telemetry') : [];
            $retryBuffer = function_exists('get_option') ? get_option('botplus_webhook_retry_buffer') : [];
            ?>
            <?php if (!empty($lastError)): ?>
                <div class="notice notice-error is-dismissible" style="margin-top: 15px; padding: 12px 16px;">
                    <p><strong><?php esc_html_e('BotPlus Webhook Delivery Alert:', 'botplus-ai-agents'); ?></strong>
                    <?php echo esc_html($lastError['error_message'] ?? 'Unknown network failure'); ?><br>
                    <small>
                        <strong><?php esc_html_e('Topic:', 'botplus-ai-agents'); ?></strong> <?php echo esc_html($lastError['topic'] ?? 'N/A'); ?> |
                        <strong><?php esc_html_e('Target:', 'botplus-ai-agents'); ?></strong> <code><?php echo esc_html($lastError['api_url'] ?? 'N/A'); ?></code> |
                        <strong><?php esc_html_e('Timestamp:', 'botplus-ai-agents'); ?></strong> <?php echo esc_html(isset($lastError['timestamp']) ? date_i18n('Y-m-d H:i:s', $lastError['timestamp']) : 'N/A'); ?>
                    </small>
                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'botplus_clear_webhook_error']), 'botplus_clear_webhook_error')); ?>" class="button button-small" style="margin-left: 12px; vertical-align: middle;"><?php esc_html_e('Dismiss Alert', 'botplus-ai-agents'); ?></a>
                    </p>
                </div>
            <?php endif; ?>

            <div id="botplus-admin-app"></div>
        </div>
<?php
    }

    public function handleCallback(): void
    {
        // Handle direct dismissal of webhook delivery alert
        if (isset($_GET['action']) && $_GET['action'] === 'botplus_clear_webhook_error') {
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if (wp_verify_nonce($nonce, 'botplus_clear_webhook_error')) {
                if (current_user_can('manage_options') && function_exists('delete_option')) {
                    delete_option('botplus_webhook_last_error');
                }
                wp_safe_redirect(remove_query_arg(['action', '_wpnonce']));
                exit;
            }
        }

        // Only process if this is an OAuth return query
        if (!isset($_GET['code']) && !isset($_GET['error'])) {
            return;
        }

        // Avoid infinite loop if already on options-general.php with botplus_code
        if (isset($_GET['botplus_code'])) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page !== 'botplus-callback' && $page !== 'botplus-settings' && $page !== 'botplus-billing') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized user.', 'botplus-ai-agents'), '', ['response' => 403]);
        }

        // Verify state parameter for CSRF protection
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $userId = get_current_user_id();
        $storedState = get_transient('botplus_oauth_state_' . $userId);

        if (!$state || !$storedState || !hash_equals($storedState, $state)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'botplus-billing',
                'botplus_error' => urlencode(__('Invalid or expired state parameter. Please try connecting again.', 'botplus-ai-agents')),
            ], admin_url('admin.php')));
            exit;
        }

        delete_transient('botplus_oauth_state_' . $userId);


        // Check for authorization code
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';

        if ($error) {
            $errorDescription = isset($_GET['error_description']) ? sanitize_text_field(wp_unslash($_GET['error_description'])) : $error;
            wp_safe_redirect(add_query_arg([
                'page' => 'botplus-billing',
                'botplus_error' => $errorDescription,
            ], admin_url('admin.php')));
            exit;
        }

        if (!$code) {
            wp_safe_redirect(add_query_arg([
                'page' => 'botplus-billing',
                'botplus_error' => 'No authorization code received.',
            ], admin_url('admin.php')));
            exit;
        }

        // Exchange authorization code directly with BotPlus platform
        try {
            $capabilities = \BotPlus\Plugin\Utilities\CapabilityDetector::detect();
            $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $apiClient->exchangeCode($code, $capabilities);

            if (!empty($result['jwt_token']) && !empty($result['site_id']) && !empty($result['chatbot_id'])) {
                $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
                $config->setConnectionData($result);
                if (function_exists('delete_option')) {
                    delete_option('botplus_environment_mismatch');
                }

                // Auto-trigger initial knowledge sync
                try {
                    $dispatcher = new \BotPlus\Plugin\Sync\SyncDispatcher($config);
                    $syncStats = $dispatcher->dispatchFullSync((int) $result['site_id']);
                    if (!empty($syncStats['success']) && function_exists('update_option')) {
                        update_option('botplus_last_synced_at', current_time('mysql'));
                        update_option('botplus_last_sync_stats', $syncStats);
                    }
                } catch (\Throwable $syncEx) {
                    if (function_exists('botplus_log')) {
                        botplus_log('BotPlus initial sync notice: ' . $syncEx->getMessage(), [], 'warning');
                    }
                }

                wp_safe_redirect(add_query_arg([
                    'page' => 'botplus-billing',
                    'botplus_connected' => '1',
                ], admin_url('admin.php')));
                exit;
            }

            $errorMessage = $result['message'] ?? __('Failed to exchange authorization code with BotPlus.', 'botplus-ai-agents');
            wp_safe_redirect(add_query_arg([
                'page' => 'botplus-billing',
                'botplus_error' => urlencode($errorMessage),
            ], admin_url('admin.php')));
            exit;
        } catch (\Throwable $e) {
            wp_safe_redirect(add_query_arg([
                'page' => 'botplus-billing',
                'botplus_error' => urlencode($e->getMessage()),
            ], admin_url('admin.php')));
            exit;
        }
    }

    public function ajaxExchangeCode(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $code = sanitize_text_field(wp_unslash($_POST['code'] ?? ''));
        if (empty($code)) {
            wp_send_json_error(['message' => 'Authorization code is required'], 400);
        }

        try {
            $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $capabilities = \BotPlus\Plugin\Utilities\CapabilityDetector::detect();
            $result = $apiClient->exchangeCode($code, $capabilities);

            if (empty($result['jwt_token']) || empty($result['site_id']) || empty($result['chatbot_id'])) {
                wp_send_json_error(['message' => $result['message'] ?? 'Failed to exchange authorization code with BotPlus.'], 400);
            }

            $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
            $config->setConnectionData($result);
            if (function_exists('delete_option')) {
                delete_option('botplus_environment_mismatch');
            }

            // Auto-trigger initial knowledge sync
            try {
                $dispatcher = new \BotPlus\Plugin\Sync\SyncDispatcher($config);
                $syncStats = $dispatcher->dispatchFullSync((int) $result['site_id']);
                if (!empty($syncStats['success']) && function_exists('update_option')) {
                    update_option('botplus_last_synced_at', current_time('mysql'));
                    update_option('botplus_last_sync_stats', $syncStats);
                }
            } catch (\Throwable $syncEx) {
                if (function_exists('botplus_log')) {
                    botplus_log('BotPlus initial sync notice: ' . $syncEx->getMessage(), [], 'warning');
                }
            }

            if (isset($syncStats)) {
                $result['sync_stats'] = $syncStats;
                $result['synced_at'] = current_time('mysql');
            }

            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Connection failed: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxSaveConnection(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $data = [
            'jwt_token' => sanitize_text_field(wp_unslash($_POST['jwt_token'] ?? '')),
            'site_id' => isset($_POST['site_id']) ? intval(wp_unslash($_POST['site_id'])) : null,
            'chatbot_id' => isset($_POST['chatbot_id']) ? intval(wp_unslash($_POST['chatbot_id'])) : null,
            'botplus_user_id' => isset($_POST['botplus_user_id']) ? intval(wp_unslash($_POST['botplus_user_id'])) : null,
            'botplus_user_email' => sanitize_email(wp_unslash($_POST['botplus_user_email'] ?? '')),
        ];

        if (empty($data['jwt_token']) || empty($data['site_id']) || empty($data['chatbot_id'])) {
            wp_send_json_error(['message' => 'Missing required connection data'], 400);
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $config->setConnectionData($data);
        if (function_exists('delete_option')) {
            delete_option('botplus_environment_mismatch');
        }

        wp_send_json_success(['message' => 'Connection saved']);
    }

    public function ajaxDisconnect(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        try {
            $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $apiClient->disconnectSite();
        } catch (\Throwable $e) {
            // Non-blocking: proceed with local disconnect even if network fails
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $config->disconnect();

        if (function_exists('delete_option')) {
            delete_option('botplus_last_synced_at');
            delete_option('botplus_last_sync_stats');
            delete_option('botplus_environment_mismatch');
        }

        wp_send_json_success(['message' => 'Disconnected']);
    }

    public function ajaxSyncKnowledge(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected()) {
            wp_send_json_error(['message' => 'Plugin is not connected to BotPlus.'], 400);
        }

        $mode = sanitize_text_field(wp_unslash($_POST['mode'] ?? 'full'));
        $dispatcher = new \BotPlus\Plugin\Sync\SyncDispatcher($config);

        if ($mode === 'chunk') {
            $type = sanitize_text_field(wp_unslash($_POST['type'] ?? 'products'));
            $offset = isset($_POST['offset']) ? absint(wp_unslash($_POST['offset'])) : 0;
            $limit = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 25;
            $batchIndex = isset($_POST['batch_index']) ? absint(wp_unslash($_POST['batch_index'])) : 1;

            $chunkResult = $dispatcher->dispatchSyncChunk((int) $config->getSiteId(), $type, $offset, $limit, $batchIndex);

            if (!empty($chunkResult['success'])) {
                if (!empty($chunkResult['finished']) && function_exists('update_option')) {
                    update_option('botplus_last_synced_at', current_time('mysql'));
                }
                wp_send_json_success($chunkResult);
            } else {
                wp_send_json_error($chunkResult, 500);
            }
            return;
        }

        try {
            $stats = $dispatcher->dispatchFullSync();

            if (!empty($stats['success'])) {
                if (function_exists('update_option')) {
                    update_option('botplus_last_synced_at', current_time('mysql'));
                    update_option('botplus_last_sync_stats', $stats);
                }
                wp_send_json_success([
                    'message'   => __('Knowledge synced successfully with BotPlus.', 'botplus-ai-agents'),
                    'stats'     => $stats,
                    'synced_at' => current_time('mysql'),
                ]);
            } else {
                $isHostMismatch = (!empty($stats['error_code']) && $stats['error_code'] === 'host_mismatch')
                    || (!empty($stats['message']) && str_contains($stats['message'], 'origin does not match'));
                if ($isHostMismatch && function_exists('update_option')) {
                    update_option('botplus_environment_mismatch', [
                        'detected_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
                        'message'     => $stats['message'] ?? 'Site URL does not match connected BotPlus chatbot.',
                    ]);
                }

                wp_send_json_error([
                    'message'    => $stats['message'] ?? __('Failed to sync items with BotPlus.', 'botplus-ai-agents'),
                    'error_code' => $stats['error_code'] ?? null,
                    'errors'     => $stats['errors'] ?? [],
                ], 500);
            }
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxTestWebhook(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => 'Plugin is not connected to BotPlus.'], 400);
        }

        try {
            $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $apiClient->forwardWebhook(
                (int) $config->getSiteId(),
                'site.ping',
                0,
                [
                    'source'     => 'admin_test_webhook',
                    'tested_by'  => get_current_user_id(),
                    'timestamp'  => time(),
                ],
                'site',
                true
            );

            if (!empty($result['success'])) {
                wp_send_json_success([
                    'message'  => __('Webhook test dispatched successfully! BotPlus received the signal.', 'botplus-ai-agents'),
                    'event_id' => $result['event_id'] ?? null,
                ]);
            } else {
                wp_send_json_error([
                    /* translators: %s: webhook failure error details */
                    'message'  => sprintf(__('Webhook dispatch failed: %s', 'botplus-ai-agents'), $result['message'] ?? 'Unknown network error'),
                    'event_id' => $result['event_id'] ?? null,
                ], 500);
            }
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Test failed: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxGetDashboardOverview(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        $siteId = (int) $config->getSiteId();
        $period = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : '30d';
        if (!in_array($period, ['7d', '30d', '90d'], true)) {
            $period = '30d';
        }

        $forceRefresh = !empty($_GET['refresh']);
        $transientKey = "botplus_dashboard_{$siteId}_{$period}";

        if (!$forceRefresh && function_exists('get_transient')) {
            $cached = get_transient($transientKey);
            if (!empty($cached) && is_array($cached)) {
                $enriched = \BotPlus\Plugin\Utilities\WooCommerceMetricsHelper::enrichWithStoreBaseline($cached, $period);
                wp_send_json_success([
                    'cached'   => true,
                    'overview' => $enriched,
                ]);
                return;
            }
        }

        try {
            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->getDashboardOverview($siteId, $period);

            if (!empty($result['success']) && !empty($result['data'])) {
                $enrichedData = \BotPlus\Plugin\Utilities\WooCommerceMetricsHelper::enrichWithStoreBaseline($result['data'], $period);

                if (function_exists('set_transient')) {
                    set_transient($transientKey, $enrichedData, 600);
                }

                wp_send_json_success([
                    'cached'   => false,
                    'overview' => $enrichedData,
                ]);
                return;
            }

            // Check if stale cached data is available on failure
            if (function_exists('get_transient')) {
                $stale = get_transient($transientKey);
                if (!empty($stale) && is_array($stale)) {
                    wp_send_json_success([
                        'cached'   => true,
                        'stale'    => true,
                        'overview' => $stale,
                        'warning'  => $result['message'] ?? __('Failed to refresh latest data from BotPlus platform.', 'botplus-ai-agents'),
                    ]);
                    return;
                }
            }

            wp_send_json_error([
                'message' => $result['message'] ?? __('Unable to retrieve dashboard metrics from BotPlus platform.', 'botplus-ai-agents'),
            ], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Dashboard request failed: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxGetAnalytics(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        $siteId = (int) $config->getSiteId();
        $period = isset($_GET['period']) ? sanitize_text_field(wp_unslash($_GET['period'])) : '30d';
        if (!in_array($period, ['7d', '30d', '90d', '1y'], true)) {
            $period = '30d';
        }

        try {
            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->getAnalytics($siteId, $period);

            if (!empty($result['success']) && !empty($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error([
                'message' => $result['message'] ?? __('Unable to retrieve analytics data from BotPlus platform.', 'botplus-ai-agents'),
            ], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Analytics request failed: ' . $e->getMessage()], 500);
        }
    }

    public function ajaxInboxFirebaseAuth(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->getInboxFirebaseAuth((int) $config->getSiteId());

            if (!empty($result['success']) && !empty($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to obtain Firebase authorization.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxInboxConversations(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            $params = [
                'status'   => sanitize_text_field(wp_unslash($_GET['status'] ?? 'all')),
                'search'   => sanitize_text_field(wp_unslash($_GET['search'] ?? '')),
                'page'     => isset($_GET['page']) ? max(1, absint(wp_unslash($_GET['page']))) : 1,
                'per_page' => isset($_GET['per_page']) ? min(100, max(1, absint(wp_unslash($_GET['per_page'])))) : 50,
            ];

            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->getInboxConversations((int) $config->getSiteId(), $params);

            if (!empty($result['success']) && isset($result['data'])) {
                $data = $result['data'];
                if (!empty($data['conversations']) && is_array($data['conversations'])) {
                    $data['conversations'] = \BotPlus\Plugin\Utilities\WooCommerceCustomerContextHelper::enrichConversations($data['conversations']);
                }
                wp_send_json_success($data);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to load conversations.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxInboxMessages(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        $conversationId = sanitize_text_field(wp_unslash($_GET['conversation_id'] ?? ''));
        if (empty($conversationId)) {
            wp_send_json_error(['message' => __('Conversation ID is required.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->getConversationMessages((int) $config->getSiteId(), $conversationId);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to load messages.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxInboxSendReply(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        $conversationId = sanitize_text_field(wp_unslash($_POST['conversation_id'] ?? ''));
        $messageText = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

        if (empty($conversationId) || empty($messageText)) {
            wp_send_json_error(['message' => __('Conversation ID and message are required.', 'botplus-ai-agents')], 400);
            return;
        }

        $currentUser = wp_get_current_user();
        $operatorName = !empty($currentUser->display_name) ? $currentUser->display_name : 'Store Operator';

        try {
            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->sendHumanMessage((int) $config->getSiteId(), $conversationId, $messageText, $operatorName);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to send reply.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxInboxClaim(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        $conversationId = sanitize_text_field(wp_unslash($_POST['conversation_id'] ?? ''));
        if (empty($conversationId)) {
            wp_send_json_error(['message' => __('Conversation ID is required.', 'botplus-ai-agents')], 400);
            return;
        }

        $currentUser = wp_get_current_user();
        $operatorName = !empty($currentUser->display_name) ? $currentUser->display_name : 'Store Operator';

        try {
            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->claimConversation((int) $config->getSiteId(), $conversationId, $operatorName);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to claim conversation.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxInboxRelease(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        $conversationId = sanitize_text_field(wp_unslash($_POST['conversation_id'] ?? ''));
        if (empty($conversationId)) {
            wp_send_json_error(['message' => __('Conversation ID is required.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->releaseConversation((int) $config->getSiteId(), $conversationId);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to release conversation.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxInboxAddNote(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        $caseId = isset($_POST['case_id']) ? absint(wp_unslash($_POST['case_id'])) : 0;
        $body = sanitize_textarea_field(wp_unslash($_POST['body'] ?? ''));
        $noteType = sanitize_text_field(wp_unslash($_POST['note_type'] ?? 'internal'));

        if ($caseId <= 0 || empty($body)) {
            wp_send_json_error(['message' => __('Case ID and note body are required.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->addCaseNote((int) $config->getSiteId(), $caseId, $body, $noteType);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to add note.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxInboxUpdateStatus(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        $caseId = isset($_POST['case_id']) ? absint(wp_unslash($_POST['case_id'])) : 0;
        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? ''));

        if ($caseId <= 0 || empty($status)) {
            wp_send_json_error(['message' => __('Case ID and status are required.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->updateCaseStatus((int) $config->getSiteId(), $caseId, $status);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to update status.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxGetLeads(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            $params = [
                'category' => sanitize_text_field(wp_unslash($_GET['category'] ?? 'all')),
                'search'   => sanitize_text_field(wp_unslash($_GET['search'] ?? '')),
                'page'     => isset($_GET['page']) ? max(1, absint(wp_unslash($_GET['page']))) : 1,
                'per_page' => isset($_GET['per_page']) ? min(100, max(1, absint(wp_unslash($_GET['per_page'])))) : 50,
            ];

            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->getLeads((int) $config->getSiteId(), $params);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to load commercial leads.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxGetCarts(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            $params = [
                'status'   => sanitize_text_field(wp_unslash($_GET['status'] ?? 'all')),
                'period'   => sanitize_text_field(wp_unslash($_GET['period'] ?? '30d')),
                'search'   => sanitize_text_field(wp_unslash($_GET['search'] ?? '')),
                'page'     => isset($_GET['page']) ? max(1, absint(wp_unslash($_GET['page']))) : 1,
                'per_page' => isset($_GET['per_page']) ? min(100, max(1, absint(wp_unslash($_GET['per_page'])))) : 50,
            ];

            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->getCarts((int) $config->getSiteId(), $params);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to load carts and recovery data.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxGetAgentSettings(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->getAgentSettings((int) $config->getSiteId());

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to load agent settings.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxSaveAgentSettings(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        if (!$config->isConnected() || !$config->getSiteId()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        $rawJson = file_get_contents('php://input');
        $input = !empty($rawJson) ? json_decode($rawJson, true) : wp_unslash($_POST);

        if (!is_array($input)) {
            $input = [];
        }

        $primaryColor = sanitize_hex_color($input['primary_color'] ?? '') ?: sanitize_text_field($input['primary_color'] ?? '#2563eb');
        $buttonColor = sanitize_hex_color($input['button_color'] ?? '') ?: sanitize_text_field($input['button_color'] ?? $primaryColor);

        $sanitized = [
            'tone_style'          => sanitize_text_field($input['tone_style'] ?? 'friendly'),
            'custom_tone_prompt'  => sanitize_textarea_field($input['custom_tone_prompt'] ?? ''),
            'system_prompt'       => sanitize_textarea_field($input['system_prompt'] ?? ''),
            'first_message'       => sanitize_textarea_field($input['first_message'] ?? ''),
            'widget_text'         => sanitize_text_field($input['widget_text'] ?? ''),
            'display_name'        => sanitize_text_field($input['display_name'] ?? ''),
            'bot_avatar_url'      => esc_url_raw($input['bot_avatar_url'] ?? ''),
            'primary_color'       => $primaryColor,
            'button_color'        => $buttonColor,
            'font_family'         => sanitize_text_field($input['font_family'] ?? 'Inter'),
            'widget_position'     => in_array($input['widget_position'] ?? '', ['left', 'right'], true) ? $input['widget_position'] : 'right',
            'remove_branding'     => !empty($input['remove_branding']),
            'human_agent_enabled' => !empty($input['human_agent_enabled']),
            'support_email'       => sanitize_email($input['support_email'] ?? ''),
            'whatsapp_number'     => sanitize_text_field($input['whatsapp_number'] ?? ''),
        ];

        try {
            /** @var \BotPlus\Plugin\Api\LaravelApiClient $api */
            $api = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $api->saveAgentSettings((int) $config->getSiteId(), $sanitized);

            if (!empty($result['success']) && isset($result['data'])) {
                // Update local WP option for immediate cold-start storefront styling
                update_option('botplus_widget_settings', [
                    'position'        => $sanitized['widget_position'],
                    'primary_color'   => $sanitized['primary_color'],
                    'button_color'    => $sanitized['button_color'],
                    'widget_text'     => $sanitized['widget_text'],
                    'first_message'   => $sanitized['first_message'],
                    'display_name'    => $sanitized['display_name'],
                    'font_family'     => $sanitized['font_family'],
                    'bot_avatar_url'  => $sanitized['bot_avatar_url'],
                    'remove_branding' => $sanitized['remove_branding'],
                ]);

                if (!empty($sanitized['support_email'])) {
                    update_option('botplus_support_email', $sanitized['support_email']);
                }
                if (!empty($sanitized['whatsapp_number'])) {
                    update_option('botplus_whatsapp_number', $sanitized['whatsapp_number']);
                }

                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to save agent settings.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxUploadAvatar(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');
        if (!current_user_can('manage_options') || !current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('You do not have permission to upload files.', 'botplus-ai-agents')], 403);
            return;
        }

        if (empty($_FILES['avatar']) || empty($_FILES['avatar']['name'])) {
            wp_send_json_error(['message' => __('No image file provided.', 'botplus-ai-agents')], 400);
            return;
        }

        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $attachmentId = media_handle_upload('avatar', 0);
        if (is_wp_error($attachmentId)) {
            wp_send_json_error(['message' => $attachmentId->get_error_message()], 500);
            return;
        }

        $url = wp_get_attachment_url($attachmentId);
        if (!$url) {
            wp_send_json_error(['message' => __('Failed to retrieve attachment URL.', 'botplus-ai-agents')], 500);
            return;
        }

        wp_send_json_success([
            'url'           => $url,
            'attachment_id' => $attachmentId,
        ]);
    }

    public function ajaxGetCapabilities(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $siteId = $config->getSiteId();

        if (!$siteId || !$config->isConnected()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $apiClient->getCapabilities((int) $siteId);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to load capabilities.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxToggleCapability(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $siteId = $config->getSiteId();

        if (!$siteId || !$config->isConnected()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        $rawJson = file_get_contents('php://input');
        $input = !empty($rawJson) ? json_decode($rawJson, true) : wp_unslash($_POST);
        if (!is_array($input)) {
            $input = (array) wp_unslash($_POST);
        }

        $feature = sanitize_text_field(wp_unslash($input['feature'] ?? ''));
        $enabled = filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($feature)) {
            wp_send_json_error(['message' => __('Feature name is required.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $apiClient->toggleCapability((int) $siteId, $feature, $enabled);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to update capability.', 'botplus-ai-agents')], 422);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxGetBillingState(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $siteId = $config->getSiteId();

        if (!$siteId || !$config->isConnected()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $apiClient->getBillingState((int) $siteId);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to load billing state.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxCreateBillingCheckout(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $siteId = $config->getSiteId();

        if (!$siteId || !$config->isConnected()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        $rawJson = file_get_contents('php://input');
        $input = !empty($rawJson) ? json_decode($rawJson, true) : wp_unslash($_POST);
        if (!is_array($input)) {
            $input = (array) wp_unslash($_POST);
        }

        $packageSlug = sanitize_text_field(wp_unslash($input['package_slug'] ?? ''));
        $interval = sanitize_text_field(wp_unslash($input['interval'] ?? 'monthly'));
        $returnUrl = admin_url('admin.php?page=botplus-billing');

        if (empty($packageSlug)) {
            wp_send_json_error(['message' => __('Package slug is required.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $apiClient->createBillingCheckoutSession((int) $siteId, $packageSlug, $interval, $returnUrl);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to start checkout session.', 'botplus-ai-agents')], 422);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxCancelBilling(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $siteId = $config->getSiteId();

        if (!$siteId || !$config->isConnected()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $apiClient->cancelBillingSubscription((int) $siteId);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to cancel subscription.', 'botplus-ai-agents')], 500);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxCreateBillingCustomerSession(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'botplus-ai-agents')], 403);
            return;
        }

        $config = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Core\Config::class);
        $siteId = $config->getSiteId();

        if (!$siteId || !$config->isConnected()) {
            wp_send_json_error(['message' => __('BotPlus is not connected.', 'botplus-ai-agents')], 400);
            return;
        }

        try {
            $apiClient = \BotPlus\Plugin\Core\Bootstrapper::container()->get(\BotPlus\Plugin\Api\LaravelApiClient::class);
            $result = $apiClient->createBillingCustomerSession((int) $siteId);

            if (!empty($result['success']) && isset($result['data'])) {
                wp_send_json_success($result['data']);
                return;
            }

            wp_send_json_error(['message' => $result['message'] ?? __('Failed to generate customer portal session.', 'botplus-ai-agents')], 422);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    public function ajaxClearWebhookError(): void
    {
        check_ajax_referer('botplus_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'botplus-ai-agents')], 403);
            return;
        }

        if (function_exists('delete_option')) {
            delete_option('botplus_webhook_last_error');
        }

        wp_send_json_success(['message' => __('Webhook delivery alert dismissed.', 'botplus-ai-agents')]);
    }
}

