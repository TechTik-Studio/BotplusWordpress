<?php

namespace BotPlus\Plugin\Core;

// Prevent direct script execution
if (!defined('ABSPATH')) {
    exit;
}

class UpdateChecker
{
    protected Config $config;
    protected \BotPlus\Plugin\Api\LaravelApiClient $apiClient;

    public function __construct(Config $config, \BotPlus\Plugin\Api\LaravelApiClient $apiClient)
    {
        $this->config = $config;
        $this->apiClient = $apiClient;
    }

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'filterPluginUpdateTransient']);
        add_filter('plugins_api', [$this, 'filterPluginsApi'], 20, 3);
    }

    /**
     * Injects custom update payload into WordPress update transient if a newer version is available.
     */
    public function filterPluginUpdateTransient($transient)
    {
        if (empty($transient) || !is_object($transient)) {
            return $transient;
        }

        $pluginSlug = 'botplus-ai-agents';
        $pluginFile = 'botplus-ai-agents/botplus.php';
        $currentVersion = defined('BOTPLUS_VERSION') ? BOTPLUS_VERSION : '1.0.0';

        $cached = function_exists('get_site_transient') ? get_site_transient('botplus_update_payload_cache') : false;
        if ($cached && is_array($cached)) {
            $info = $cached;
        } else {
            $info = $this->apiClient->checkPluginUpdate($currentVersion);
            if (!empty($info['version']) && function_exists('set_site_transient')) {
                // Cache for 6 hours
                $cacheTtl = defined('HOUR_IN_SECONDS') ? 6 * HOUR_IN_SECONDS : 21600;
                set_site_transient('botplus_update_payload_cache', $info, $cacheTtl);
            }
        }

        if (empty($info['version'])) {
            return $transient;
        }

        $remoteVersion = (string) $info['version'];
        $hasUpdate = version_compare($currentVersion, $remoteVersion, '<');

        $obj = new \stdClass();
        $obj->id = 'botplus-ai-agents';
        $obj->slug = $pluginSlug;
        $obj->plugin = $pluginFile;
        $obj->new_version = $remoteVersion;
        $obj->url = $info['homepage'] ?? 'https://botplus.app/products/wordpress-plugin';
        $obj->package = $info['package'] ?? ($info['download_url'] ?? '');
        $obj->tested = $info['tested'] ?? '7.1';
        $obj->requires_php = $info['requires_php'] ?? '8.1';
        $obj->requires = $info['requires'] ?? '6.0';
        $obj->icons = $info['icons'] ?? [];
        $obj->banners = $info['banners'] ?? [];

        if ($hasUpdate && !empty($obj->package)) {
            if (!isset($transient->response) || !is_array($transient->response)) {
                $transient->response = [];
            }
            $transient->response[$pluginFile] = $obj;
            if (isset($transient->no_update[$pluginFile])) {
                unset($transient->no_update[$pluginFile]);
            }
        } else {
            if (!isset($transient->no_update) || !is_array($transient->no_update)) {
                $transient->no_update = [];
            }
            $transient->no_update[$pluginFile] = $obj;
            if (isset($transient->response[$pluginFile])) {
                unset($transient->response[$pluginFile]);
            }
        }

        return $transient;
    }

    /**
     * Provides popup modal details when the merchant clicks "View version X.X details" in WP Admin.
     */
    public function filterPluginsApi($result, $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'botplus-ai-agents') {
            return $result;
        }

        $currentVersion = defined('BOTPLUS_VERSION') ? BOTPLUS_VERSION : '1.0.0';
        $info = $this->apiClient->checkPluginUpdate($currentVersion);

        if (empty($info['version'])) {
            return $result;
        }

        $res = new \stdClass();
        $res->name = $info['name'] ?? 'BotPlus AI Agents';
        $res->slug = 'botplus-ai-agents';
        $res->version = $info['version'];
        $res->author = $info['author'] ?? '<a href="https://botplus.app">BotPlus</a>';
        $res->homepage = $info['homepage'] ?? 'https://botplus.app/products/wordpress-plugin';
        $res->requires = $info['requires'] ?? '6.0';
        $res->tested = $info['tested'] ?? '7.1';
        $res->requires_php = $info['requires_php'] ?? '8.1';
        $res->download_link = $info['download_url'] ?? ($info['package'] ?? '');
        $res->sections = $info['sections'] ?? [
            'description' => 'Dedicated AI storefront sales and support agent for WordPress and WooCommerce.',
            'changelog' => 'Early access build.',
        ];
        $res->banners = $info['banners'] ?? [];
        $res->icons = $info['icons'] ?? [];

        return $res;
    }
}
