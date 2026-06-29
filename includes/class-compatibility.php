<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Compatibility
{
    private const MIN_PHP_VERSION = '8.0.0';
    private const MIN_WP_VERSION = '6.6';
    private const MIN_ELEMENTOR_VERSION = '3.25.0';
    private const REQUIRED_PLUGINS = [
        'elementor' => 'Elementor',
    ];

    /** @var string[] */
    private array $errors = [];

    public function check(): bool
    {
        $this->errors = [];

        $this->check_php_version();
        $this->check_wp_version();
        $this->check_elementor();
        $this->check_dependencies();

        if (!empty($this->errors)) {
            add_action('admin_notices', [$this, 'render_notices']);
            return false;
        }

        return true;
    }

    public function check_or_die(): void
    {
        if (!$this->check()) {
            $message = implode("\n", $this->errors);
            wp_die(esc_html($message));
        }
    }

    private function check_php_version(): void
    {
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            $this->errors[] = sprintf(
                /* translators: 1: Min PHP version, 2: Current PHP version */
                __('Hello Elementor Figma Sync requires PHP %1$s or higher. You are running PHP %2$s.', 'hello-figma'),
                self::MIN_PHP_VERSION,
                PHP_VERSION
            );
        }
    }

    private function check_wp_version(): void
    {
        global $wp_version;
        if (version_compare($wp_version, self::MIN_WP_VERSION, '<')) {
            $this->errors[] = sprintf(
                /* translators: 1: Min WP version, 2: Current WP version */
                __('Hello Elementor Figma Sync requires WordPress %1$s or higher. You are running WordPress %2$s.', 'hello-figma'),
                self::MIN_WP_VERSION,
                $wp_version
            );
        }
    }

    private function check_elementor(): void
    {
        if (!did_action('elementor/loaded')) {
            $this->errors[] = __('Hello Elementor Figma Sync requires Elementor to be installed and activated.', 'hello-figma');
            return;
        }

        if (defined('ELEMENTOR_VERSION') && version_compare(ELEMENTOR_VERSION, self::MIN_ELEMENTOR_VERSION, '<')) {
            $this->errors[] = sprintf(
                /* translators: 1: Min Elementor version, 2: Current Elementor version */
                __('Hello Elementor Figma Sync requires Elementor %1$s or higher. You are running Elementor %2$s.', 'hello-figma'),
                self::MIN_ELEMENTOR_VERSION,
                ELEMENTOR_VERSION
            );
        }
    }

    private function check_dependencies(): void
    {
        // is_plugin_active() lives in wp-admin/includes/plugin.php, which is NOT
        // loaded on front-end requests. check() runs on the front-end-reachable
        // `init` hook, so we must load it ourselves or this fatals on every page.
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach (self::REQUIRED_PLUGINS as $slug => $name) {
            if (!is_plugin_active("{$slug}/{$slug}.php")) {
                $this->errors[] = sprintf(
                    /* translators: %s: Plugin name */
                    __('Hello Elementor Figma Sync requires %s to be installed and activated.', 'hello-figma'),
                    $name
                );
            }
        }
    }

    public function render_notices(): void
    {
        if (empty($this->errors)) {
            return;
        }
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong><?php esc_html_e('Hello Elementor Figma Sync', 'hello-figma'); ?></strong></p>
            <?php foreach ($this->errors as $error) : ?>
                <p><?php echo esc_html($error); ?></p>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /** @return string[] */
    public function get_errors(): array
    {
        return $this->errors;
    }
}
