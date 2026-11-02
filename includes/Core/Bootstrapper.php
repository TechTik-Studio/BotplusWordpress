<?php

namespace BotPlus\Plugin\Core;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class Bootstrapper
{
    protected static $container = null;

    public static function init(): void
    {
        // 1. Load configuration
        self::loadConfig();

        // 2. Initialize DI container
        self::initContainer();

        // 3. Register REST API routes
        add_action('rest_api_init', [self::container()->get(\BotPlus\Plugin\Rest\RestRouter::class), 'register']);

        // 5. Initialize widget (frontend)
        add_action('wp_loaded', function () {
            if (!is_admin() || wp_doing_ajax()) {
                self::container()->get(\BotPlus\Plugin\Widget\ChatWidget::class)->init();
            }
        });

        // 6. Initialize admin (backend)
        if (is_admin()) {
            add_action('admin_menu', [self::container()->get(\BotPlus\Plugin\Admin\SettingsPage::class), 'registerMenu']);
            add_action('admin_enqueue_scripts', [self::container()->get(\BotPlus\Plugin\Admin\SettingsPage::class), 'enqueueAssets']);
            add_action('admin_init', function () {
                if (function_exists('wp_add_privacy_policy_content')) {
                    $content = __(
                        'When visitors interact with the BotPlus AI chat widget, their conversation messages and shopping cart items may be transmitted to BotPlus cloud servers (https://botplus.app) to generate real-time AI responses, recommend products, and assist with checkout. No customer data is sold or shared with external public AI training models.',
                        'botplus-ai-agents'
                    );
                    wp_add_privacy_policy_content(
                        __('BotPlus AI Agents', 'botplus-ai-agents'),
                        wp_kses_post(wpautop($content))
                    );
                }
            });
        }

        // 7. Register cron schedules, purge legacy hooks, and schedule retry buffer flush
        add_filter('cron_schedules', [self::container()->get(\BotPlus\Plugin\Sync\Scheduler::class), 'addSchedules']);
        add_action('init', [\BotPlus\Plugin\Sync\Scheduler::class, 'purgeLegacySchedules']);
        add_action('init', [\BotPlus\Plugin\Sync\Scheduler::class, 'registerRetryBufferSchedule']);

        // 8. Register webhooks
        add_action('init', [self::container()->get(\BotPlus\Plugin\Webhooks\WebhookHandler::class), 'register']);

        // 9. Gutenberg block registration
        add_action('init', [self::container()->get(\BotPlus\Plugin\Widget\Block::class), 'register']);

        // 10. Shortcode
        add_shortcode('botplus_chatbot', [self::container()->get(\BotPlus\Plugin\Widget\Shortcode::class), 'render']);

        // 11. Abandoned cart 1-click recovery & restoration listener
        add_action('template_redirect', function () {
            self::container()->get(\BotPlus\Plugin\Rest\Controllers\StorefrontCartController::class)->handleTemplateRedirectRestoration();
        });

        // 12. Register self-hosted plugin update checker for early access builds
        if (is_admin()) {
            self::container()->get(\BotPlus\Plugin\Core\UpdateChecker::class)->register();
        }
    }

    protected static function loadConfig(): void
    {
        // Config is loaded on-demand via Config class
    }

    protected static function initContainer(): void
    {
        if (self::$container === null) {
            self::$container = new \BotPlus\Plugin\Core\Container();
            self::registerBindings(self::$container);
        }
    }

    protected static function registerBindings($container): void
    {
        // Core
        $container->singleton(\BotPlus\Plugin\Core\Config::class);
        $container->singleton(\BotPlus\Plugin\Api\LaravelApiClient::class, function ($c) {
            return new \BotPlus\Plugin\Api\LaravelApiClient($c->get(\BotPlus\Plugin\Core\Config::class));
        });
        $container->singleton(\BotPlus\Plugin\Core\UpdateChecker::class, function ($c) {
            return new \BotPlus\Plugin\Core\UpdateChecker(
                $c->get(\BotPlus\Plugin\Core\Config::class),
                $c->get(\BotPlus\Plugin\Api\LaravelApiClient::class)
            );
        });

        // Admin
        $container->singleton(\BotPlus\Plugin\Admin\SettingsPage::class);

        // Widget
        $container->singleton(\BotPlus\Plugin\Widget\ChatWidget::class);
        $container->singleton(\BotPlus\Plugin\Widget\Shortcode::class);
        $container->singleton(\BotPlus\Plugin\Widget\Block::class);

        // REST
        $container->singleton(\BotPlus\Plugin\Rest\RestRouter::class);
        $container->singleton(\BotPlus\Plugin\Rest\Controllers\ChatController::class, function ($c) {
            return new \BotPlus\Plugin\Rest\Controllers\ChatController();
        });
        $container->singleton(\BotPlus\Plugin\Rest\Controllers\CartController::class, function ($c) {
            return new \BotPlus\Plugin\Rest\Controllers\CartController();
        });
        $container->singleton(\BotPlus\Plugin\Rest\Controllers\LeadController::class, function ($c) {
            return new \BotPlus\Plugin\Rest\Controllers\LeadController();
        });
        $container->singleton(\BotPlus\Plugin\Rest\Controllers\BootstrapController::class, function ($c) {
            return new \BotPlus\Plugin\Rest\Controllers\BootstrapController();
        });
        $container->singleton(\BotPlus\Plugin\Rest\Controllers\StorefrontCartController::class, function ($c) {
            return new \BotPlus\Plugin\Rest\Controllers\StorefrontCartController();
        });
        $container->singleton(\BotPlus\Plugin\Rest\Controllers\StorefrontOrderController::class, function ($c) {
            return new \BotPlus\Plugin\Rest\Controllers\StorefrontOrderController();
        });

        // Sync
        $container->singleton(\BotPlus\Plugin\Sync\Scheduler::class);

        // Webhooks
        $container->singleton(\BotPlus\Plugin\Webhooks\WebhookHandler::class);
    }

    public static function container(): \BotPlus\Plugin\Core\Container
    {
        if (self::$container === null) {
            self::initContainer();
        }
        return self::$container;
    }

    public static function resetContainer(): void
    {
        self::$container = null;
    }
}
