<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Asset_Manager
{
    private const STYLE_HANDLE = 'hello-figma-admin';
    private const SCRIPT_HANDLE = 'hello-figma-admin';

    public function init(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if (!str_contains($hook, 'hello-figma')) {
            return;
        }

        wp_enqueue_style(
            self::STYLE_HANDLE,
            HELLO_FIGMA_URL . 'admin/css/admin.css',
            [],
            HELLO_FIGMA_VERSION
        );

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            HELLO_FIGMA_URL . 'admin/js/admin.js',
            ['jquery', 'wp-i18n'],
            HELLO_FIGMA_VERSION,
            true
        );

        wp_localize_script(self::SCRIPT_HANDLE, 'helloFigmaData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hello_figma_nonce'),
            'i18n' => [
                'syncSuccess' => __('Styles synced successfully!', 'hello-figma'),
                'syncError' => __('Error syncing styles.', 'hello-figma'),
                'confirmDelete' => __('Are you sure you want to delete this template?', 'hello-figma'),
            ],
        ]);
    }

    public function enqueue_elementor_editor_assets(): void
    {
        wp_enqueue_script(
            'hello-figma-editor',
            HELLO_FIGMA_URL . 'admin/js/editor.js',
            ['jquery', 'elementor-editor'],
            HELLO_FIGMA_VERSION,
            true
        );

        wp_localize_script('hello-figma-editor', 'helloFigmaEditorData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hello_figma_nonce'),
        ]);
    }
}
