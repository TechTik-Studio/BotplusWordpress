<?php

/**
 * Plugin Name: BotPlus AI Agents
 * Description: AI Sales & Support Agent for WordPress & WooCommerce. Conversational product discovery, cart assistance, order tracking, lead capture, and human handoff.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 8.1
 * Author: BotPlus
 * Author URI: https://botplus.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: botplus-ai-agents
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('BOTPLUS_VERSION', '1.0.0');
define('BOTPLUS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BOTPLUS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BOTPLUS_PLUGIN_FILE', __FILE__);
define('BOTPLUS_TEXT_DOMAIN', 'botplus-ai-agents');
// if (!defined('BOTPLUS_API_URL')) {
//     // Default production BotPlus cloud endpoint
//     // Can be overridden in wp-config.php for local or staging development:
//     // define('BOTPLUS_API_URL', 'http://127.0.0.1:8000');
//     // define('BOTPLUS_API_URL', 'https://plaything-venomous-mardi.ngrok-free.dev');
// }

// Minimum requirements check
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>' . esc_html__('BotPlus requires PHP 8.1 or higher.', 'botplus-ai-agents') . '</p></div>';
    });
    return;
}

if (version_compare(get_bloginfo('version'), '6.0', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>' . esc_html__('BotPlus requires WordPress 6.0 or higher.', 'botplus-ai-agents') . '</p></div>';
    });
    return;
}

// Autoload dependencies (Composer if present, or native fallback autoloader)
$botplus_autoload_path = BOTPLUS_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($botplus_autoload_path)) {
    require_once $botplus_autoload_path;
} else {
    // Native PSR-4 fallback autoloader for BotPlus namespace
    spl_autoload_register(function ($class) {
        $prefix = 'BotPlus\\Plugin\\';
        $baseDir = BOTPLUS_PLUGIN_DIR . 'includes/';
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

// Ensure procedural helpers are available
$botplus_helpers_path = BOTPLUS_PLUGIN_DIR . 'includes/Utilities/Helpers.php';
if (file_exists($botplus_helpers_path)) {
    require_once $botplus_helpers_path;
} else {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>' . esc_html__('BotPlus installation is incomplete. Please reinstall the plugin.', 'botplus-ai-agents') . '</p></div>';
    });
    return;
}

// Declare WooCommerce HPOS (Custom Order Tables) compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Initialize plugin
\BotPlus\Plugin\Core\Bootstrapper::init();

// Activation / Deactivation hooks
register_activation_hook(__FILE__, ['\BotPlus\Plugin\Core\Installer', 'activate']);
register_deactivation_hook(__FILE__, ['\BotPlus\Plugin\Core\Installer', 'deactivate']);
