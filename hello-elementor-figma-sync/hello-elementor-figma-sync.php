<?php
declare(strict_types=1);

/**
 * Plugin Name:     Hello Elementor Figma Sync
 * Plugin URI:      https://github.com/your-repo/hello-elementor-figma-sync
 * Description:     تبدیل مستقیم طرح‌های فیگما به قالب‌های المنتور با پشتیبانی کامل از Hello Elementor و Elementor Pro
 * Version:         1.0.0
 * Author:          Your Name
 * Author URI:      https://your-site.com
 * Text Domain:     hello-figma
 * Domain Path:     /languages
 * Requires at least: 6.6
 * Requires PHP:    8.0
 * Tested up to:    6.9
 * Elementor tested up to: 3.27.0
 * Elementor Pro tested up to: 3.27.0
 * License:         GPLv3
 * License URI:     https://opensource.org/licenses/GPL-3.0
 *
 * Requires Plugins: elementor
 *
 * @package HelloFigma
 */

defined('ABSPATH') || exit;

define('HELLO_FIGMA_VERSION', '1.0.0');
define('HELLO_FIGMA_FILE', __FILE__);
define('HELLO_FIGMA_PATH', plugin_dir_path(__FILE__));
define('HELLO_FIGMA_URL', plugin_dir_url(__FILE__));
define('HELLO_FIGMA_BASENAME', plugin_basename(__FILE__));

/**
 * PSR-4-like autoloader for the HelloFigma namespace.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'HelloFigma\\';
    $base_dir = HELLO_FIGMA_PATH . 'includes/';

    if (str_starts_with($class, $prefix) === false) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . 'class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Autoload widgets.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'HelloFigma\\Widgets\\';
    $base_dir = HELLO_FIGMA_PATH . 'widgets/';

    if (str_starts_with($class, $prefix) === false) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . 'class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Autoload dynamic tags.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'HelloFigma\\DynamicTags\\';
    $base_dir = HELLO_FIGMA_PATH . 'dynamic-tags/';

    if (str_starts_with($class, $prefix) === false) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . 'class-' . str_replace('_', '-', strtolower($relative_class)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Bootstrap the plugin.
 */
add_action('plugins_loaded', function (): void {
    load_plugin_textdomain('hello-figma', false, dirname(HELLO_FIGMA_BASENAME) . '/languages');

    HelloFigma\Plugin::instance();
});
