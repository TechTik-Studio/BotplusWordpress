<?php

// Mock ABSPATH for PHPUnit test runner
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('BOTPLUS_VERSION')) {
    define('BOTPLUS_VERSION', '1.0.0');
}

if (!defined('BOTPLUS_PLUGIN_DIR')) {
    define('BOTPLUS_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('BOTPLUS_PLUGIN_FILE')) {
    define('BOTPLUS_PLUGIN_FILE', dirname(__DIR__) . '/botplus.php');
}

if (!defined('BOTPLUS_PLUGIN_URL')) {
    define('BOTPLUS_PLUGIN_URL', 'https://example.com/wp-content/plugins/botplus-wordpress/');
}

if (!defined('BOTPLUS_TEXT_DOMAIN')) {
    define('BOTPLUS_TEXT_DOMAIN', 'botplus-ai-agents');
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $string, bool $remove_breaks = false): string {
        $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string);
        $string = strip_tags($string);
        if ($remove_breaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }
        return trim($string);
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
