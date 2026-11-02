<?php

namespace BotPlus\Plugin\Core;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class Installer
{
    public static function activate(): void
    {
        // Create custom cron schedules
        self::addCronSchedules();

        // Flush rewrite rules for REST endpoints
        flush_rewrite_rules();

        // Set default widget settings if not exist
        if (get_option('botplus_widget_settings') === false) {
            update_option('botplus_widget_settings', [
                'position' => 'bottom-right',
                'theme' => 'glass',
                'primary_color' => '#3B82F6',
                'welcome_message' => 'Hi! How can I help you today?',
                'offline_message' => 'We\'re offline. Leave a message!',
            ]);
        }

        // Purge any legacy periodic sync cron jobs
        \BotPlus\Plugin\Sync\Scheduler::purgeLegacySchedules();
    }

    public static function deactivate(): void
    {
        // Clear scheduled events
        \BotPlus\Plugin\Sync\Scheduler::purgeLegacySchedules();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    protected static function addCronSchedules(): void
    {
        add_filter('cron_schedules', function ($schedules) {
            $schedules['five_minutes'] = [
                'interval' => 5 * 60,
                'display' => __('Every 5 minutes', 'botplus-ai-agents'),
            ];
            $schedules['fifteen_minutes'] = [
                'interval' => 15 * 60,
                'display' => __('Every 15 minutes', 'botplus-ai-agents'),
            ];
            return $schedules;
        });
    }

    public static function uninstall(): void
    {
        // 1. Notify BotPlus backend to deactivate site and safely cancel Polar gateway subscription
        try {
            $config = new \BotPlus\Plugin\Core\Config();
            if ($config->isConnected()) {
                $client = new \BotPlus\Plugin\Api\LaravelApiClient($config);
                $client->disconnectSite();
            }
        } catch (\Throwable $e) {
            // Best effort on disconnect; proceed with local cleanup
        }

        // 2. Delete all options
        $optionsToDelete = [
            'botplus_settings',
            'botplus_widget_settings',
            'botplus_chatbot_id',
            'botplus_site_credentials',
            'botplus_sync_status',
            'botplus_last_synced_at',
            'botplus_last_sync_stats',
            'botplus_webhook_last_error',
            'botplus_webhook_telemetry',
            'botplus_webhook_retry_buffer',
            'botplus_environment_mismatch',
            'botplus_support_email',
            'botplus_whatsapp_number',
        ];

        foreach ($optionsToDelete as $optionName) {
            delete_option($optionName);
        }

        // Delete cron jobs
        \BotPlus\Plugin\Sync\Scheduler::purgeLegacySchedules();

        // Delete transients and wildcard botplus options
        global $wpdb;
        if (!empty($wpdb) && !empty($wpdb->options) && method_exists($wpdb, 'prepare') && method_exists($wpdb, 'esc_like')) {
            $likeOption    = $wpdb->esc_like('botplus_') . '%';
            $likeTransient = $wpdb->esc_like('_transient_botplus_') . '%';
            $likeTimeout   = $wpdb->esc_like('_transient_timeout_botplus_') . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Wildcard cleanup during full plugin uninstall.
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
                    $likeOption,
                    $likeTransient,
                    $likeTimeout
                )
            );
        }

        // Flush rewrite rules
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules();
        }
    }
}
