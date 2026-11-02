<?php

/**
 * Fired when the plugin is uninstalled via WordPress Admin.
 *
 * @package BotPlus
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$botplus_autoload = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($botplus_autoload)) {
    require_once $botplus_autoload;
} else {
    // Native PSR-4 fallback autoloader for standalone distribution package
    spl_autoload_register(function ($class) {
        $prefix = 'BotPlus\\Plugin\\';
        $baseDir = plugin_dir_path(__FILE__) . 'includes/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
}

$botplus_helpers = plugin_dir_path(__FILE__) . 'includes/Utilities/Helpers.php';
if (file_exists($botplus_helpers)) {
    require_once $botplus_helpers;
}

\BotPlus\Plugin\Core\Installer::uninstall();

