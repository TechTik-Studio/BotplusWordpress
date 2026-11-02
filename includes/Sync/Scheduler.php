<?php

namespace BotPlus\Plugin\Sync;


// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class Scheduler
{
    public function addSchedules(array $schedules): array
    {
        $schedules['five_minutes'] = [
            'interval' => 5 * 60,
            'display' => __('Every 5 minutes', 'botplus-ai-agents'),
        ];
        $schedules['fifteen_minutes'] = [
            'interval' => 15 * 60,
            'display' => __('Every 15 minutes', 'botplus-ai-agents'),
        ];
        return $schedules;
    }

    /**
     * Registers the recurring webhook retry buffer flush schedule and action hook.
     */
    public static function registerRetryBufferSchedule(): void
    {
        if (function_exists('add_action')) {
            add_action('botplus_flush_retry_buffer', [self::class, 'handleFlushRetryBuffer']);
        }

        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
            if (!wp_next_scheduled('botplus_flush_retry_buffer')) {
                wp_schedule_event(time(), 'fifteen_minutes', 'botplus_flush_retry_buffer');
            }
        }
    }

    /**
     * Executes the retry buffer flush via WebhookHandler.
     */
    public static function handleFlushRetryBuffer(): int
    {
        if (class_exists('\BotPlus\Plugin\Core\Bootstrapper')) {
            $container = \BotPlus\Plugin\Core\Bootstrapper::container();
            if ($container->has(\BotPlus\Plugin\Webhooks\WebhookHandler::class)) {
                $handler = $container->get(\BotPlus\Plugin\Webhooks\WebhookHandler::class);
                return $handler->flushRetryBuffer();
            }
        }
        return 0;
    }

    /**
     * Purges legacy periodic sync cron hooks if previously registered in WordPress.
     */
    public static function purgeLegacySchedules(): void
    {
        if (function_exists('wp_next_scheduled')) {
            if (wp_next_scheduled('botplus_sync_products')) {
                wp_clear_scheduled_hook('botplus_sync_products');
            }
            if (wp_next_scheduled('botplus_sync_content')) {
                wp_clear_scheduled_hook('botplus_sync_content');
            }
            if (wp_next_scheduled('botplus_do_sync')) {
                wp_clear_scheduled_hook('botplus_do_sync');
            }
            if (wp_next_scheduled('botplus_process_webhooks')) {
                wp_clear_scheduled_hook('botplus_process_webhooks');
            }
        }
    }
}

