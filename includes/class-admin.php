<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Admin
{
    private const VALID_FORMATS = ['post', 'json'];
    private const MAX_OVERRIDES_BYTES = 10240; // 10 KB limit for overrides JSON
    private const ERROR_CODES = [
        'INVALID_FORMAT' => 'invalid_format',
        'OVERRIDES_TOO_LARGE' => 'overrides_too_large',
        'OVERRIDES_INVALID_JSON' => 'overrides_invalid_json',
        'OVERRIDES_TOO_DEEP' => 'overrides_too_deep',
        'FILE_KEY_REQUIRED' => 'file_key_required',
        'CONVERSION_FAILED' => 'conversion_failed',
        'SAVE_FAILED' => 'save_failed',
        'SECURITY_FAILED' => 'security_failed',
        'PERMISSION_DENIED' => 'permission_denied',
        'NOT_FOUND' => 'not_found',
        'FETCH_FAILED' => 'fetch_failed',
        'MISSING_PARAMS' => 'missing_params',
        'DELETE_FAILED' => 'delete_failed',
        'EXPORT_FAILED' => 'export_failed',
        'SYNC_FAILED' => 'sync_failed',
        'NO_PROGRESS' => 'no_progress',
    ];
    private Plugin $plugin;
    private string $menu_slug = 'hello-figma';

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function init(): void
    {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'handle_form_submissions']);
        add_action('admin_init', [$this, 'handle_style_sync_form']);
        add_action('admin_init', [$this, 'handle_ajax_actions']);
    }

    public function handle_style_sync_form(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'hello-figma-style-sync') {
            return;
        }
        $this->plugin->get_style_sync()->handle_form_submission();
    }

    public function register_admin_menu(): void
    {
        add_menu_page(
            __('Hello Figma Sync', 'hello-figma'),
            __('Figma Sync', 'hello-figma'),
            'manage_options',
            $this->menu_slug,
            [$this, 'render_dashboard'],
            'dashicons-layout',
            30
        );

        add_submenu_page(
            $this->menu_slug,
            __('Dashboard', 'hello-figma'),
            __('Dashboard', 'hello-figma'),
            'manage_options',
            $this->menu_slug,
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            $this->menu_slug,
            __('Templates', 'hello-figma'),
            __('Templates', 'hello-figma'),
            'manage_options',
            'hello-figma-templates',
            [$this, 'render_templates']
        );

        add_submenu_page(
            $this->menu_slug,
            __('Style Sync', 'hello-figma'),
            __('Style Sync', 'hello-figma'),
            'manage_options',
            'hello-figma-style-sync',
            [$this, 'render_style_sync']
        );

        add_submenu_page(
            $this->menu_slug,
            __('Settings', 'hello-figma'),
            __('Settings', 'hello-figma'),
            'manage_options',
            'hello-figma-settings',
            [$this, 'render_settings']
        );
    }

    public function render_dashboard(): void
    {
        $has_token = $this->plugin->get_figma_api()->has_token();
        $stats = $this->plugin->get_template_manager()->get_statistics();
        include HELLO_FIGMA_PATH . 'admin/views/dashboard.php';
    }

    public function render_templates(): void
    {
        $templates = $this->plugin->get_template_manager()->get_figma_templates();
        include HELLO_FIGMA_PATH . 'admin/views/templates.php';
    }

    public function render_style_sync(): void
    {
        include HELLO_FIGMA_PATH . 'admin/views/style-sync.php';
    }

    public function render_settings(): void
    {
        include HELLO_FIGMA_PATH . 'admin/views/settings.php';
    }

    public function handle_form_submissions(): void
    {
        // Handle clear cache
        if (isset($_POST['hello_figma_clear_cache'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce validated by wp_verify_nonce
            if (!wp_verify_nonce(wp_unslash($_POST['_figma_cache_nonce'] ?? ''), 'hello_figma_clear_cache')) {
                wp_die(__('Security check failed.', 'hello-figma'));
            }

            $this->plugin->get_figma_api()->clear_cache();

            add_action('admin_notices', function (): void {
                echo '<div class="notice notice-success is-dismissible"><p>' .
                    esc_html__('Figma cache cleared successfully.', 'hello-figma') .
                    '</p></div>';
            });
            return;
        }

        if (!isset($_POST['hello_figma_save_settings'])) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce validated by wp_verify_nonce
        if (!wp_verify_nonce(wp_unslash($_POST['_hello_figma_nonce'] ?? ''), 'hello_figma_settings')) {
            wp_die(__('Security check failed.', 'hello-figma'));
        }

        $pat = sanitize_text_field(wp_unslash($_POST['figma_pat'] ?? ''));
        if (!empty($pat) && $pat !== '********') {
            $this->plugin->get_figma_api()->set_token($pat);
        }

        $file_key = $this->parse_file_key(sanitize_text_field(wp_unslash($_POST['figma_file_key'] ?? '')));
        if (!empty($file_key)) {
            update_option('hello_figma_file_key', $file_key);
        }

        $messages = [__('Settings saved successfully.', 'hello-figma')];

        // Validate token if set
        if ($this->plugin->get_figma_api()->has_token() && !$this->plugin->get_figma_api()->test_token()) {
            $messages[] = __('Warning: Figma token appears invalid. Please verify it.', 'hello-figma');
        }

        add_action('admin_notices', function () use ($messages): void {
            foreach ($messages as $message) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        });
    }

    public function handle_ajax_actions(): void
    {
        add_action('wp_ajax_hello_figma_convert', [$this, 'ajax_convert']);
        add_action('wp_ajax_hello_figma_delete_template', [$this, 'ajax_delete_template']);
        add_action('wp_ajax_hello_figma_export_template', [$this, 'ajax_export_template']);
        add_action('wp_ajax_hello_figma_fetch_preview', [$this, 'ajax_fetch_preview']);
        add_action('wp_ajax_hello_figma_sync_styles', [$this, 'ajax_sync_styles']);
        add_action('wp_ajax_hello_figma_fetch_structure', [$this, 'ajax_fetch_structure']);
        add_action('wp_ajax_hello_figma_fetch_frame_images', [$this, 'ajax_fetch_frame_images']);
        add_action('wp_ajax_hello_figma_preview_sections', [$this, 'ajax_preview_sections']);
        add_action('wp_ajax_hello_figma_import_progress', [$this, 'ajax_import_progress']);
    }

    public function ajax_convert(): void
    {
        $this->verify_ajax();

        $run_id = uniqid('figma_import_');
        Logger::start_run($run_id);

        Logger::log('INFO', 'Admin', 'Import started', [
            'file_key' => $this->get_file_key_from_post('file_key'),
            'node_id' => sanitize_text_field(wp_unslash($_POST['node_id'] ?? '')),
            'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
            'format' => sanitize_text_field(wp_unslash($_POST['format'] ?? 'post')),
        ]);

        $file_key = $this->get_file_key_from_post('file_key');
        $node_id = sanitize_text_field(wp_unslash($_POST['node_id'] ?? ''));
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? __('Figma Import', 'hello-figma')));
        $format = sanitize_text_field(wp_unslash($_POST['format'] ?? 'post'));
        $file_name = sanitize_text_field(wp_unslash($_POST['file_name'] ?? ''));

        // ── Validate format enum ──
        if (!in_array($format, self::VALID_FORMATS, true)) {
            Logger::log('WARNING', 'Admin', 'Invalid format requested', ['format' => $format]);
            wp_send_json_error(['message' => __('Invalid format. Allowed: post, json.', 'hello-figma'), 'code' => self::ERROR_CODES['INVALID_FORMAT']]);
        }

        // ── Parse and validate overrides ──
        $overrides = [];
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string, validated below
        $overrides_raw = wp_unslash($_POST['overrides'] ?? '');
        if ('' !== $overrides_raw) {
            // Size limit: prevent large payloads
            if (strlen($overrides_raw) > self::MAX_OVERRIDES_BYTES) {
                Logger::log('WARNING', 'Admin', 'Overrides payload too large', [
                    'bytes' => strlen($overrides_raw),
                    'max' => self::MAX_OVERRIDES_BYTES,
                ]);
                wp_send_json_error(['message' => __('Overrides payload too large.', 'hello-figma'), 'code' => self::ERROR_CODES['OVERRIDES_TOO_LARGE']]);
            }

            $decoded = json_decode($overrides_raw, true);
            if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                Logger::log('WARNING', 'Admin', 'Invalid overrides JSON', [
                    'error' => json_last_error_msg(),
                ]);
                wp_send_json_error(['message' => __('Invalid overrides JSON.', 'hello-figma'), 'code' => self::ERROR_CODES['OVERRIDES_INVALID_JSON']]);
            }

            // Limit nested array depth to prevent stack issues
            if (self::array_depth($decoded) > 5) {
                Logger::log('WARNING', 'Admin', 'Overrides nesting too deep', [
                    'depth' => self::array_depth($decoded),
                ]);
                wp_send_json_error(['message' => __('Overrides nesting too deep.', 'hello-figma'), 'code' => self::ERROR_CODES['OVERRIDES_TOO_DEEP']]);
            }

            $overrides = $decoded;
        }

        if (empty($file_key)) {
            wp_send_json_error(['message' => __('File key is required.', 'hello-figma'), 'code' => self::ERROR_CODES['FILE_KEY_REQUIRED']]);
        }

        $this->set_import_progress($run_id, __('Fetching design from Figma...', 'hello-figma'), 10);

        $elementor_data = $this->plugin->get_renderer()->convert_file($file_key, $node_id ?: null, $overrides);
        if ($elementor_data === null) {
            wp_send_json_error(['message' => __('Failed to convert Figma file.', 'hello-figma'), 'code' => self::ERROR_CODES['CONVERSION_FAILED']]);
        }

        $this->set_import_progress($run_id, __('Converting sections...', 'hello-figma'), 30);

        $total_images = $this->count_image_placeholders($elementor_data);

        $this->set_import_progress($run_id, __('Downloading images from Figma...', 'hello-figma'), 40, 0, $total_images);

        $elementor_data = $this->plugin->get_image_handler()->resolve_image_placeholders(
            $file_key,
            $elementor_data,
            function (int $current, int $total) use ($run_id): void {
                $this->set_import_progress($run_id, __('Downloading images from Figma...', 'hello-figma'), 40, $current, $total);
            }
        );

        if ($format === 'json') {
            $this->set_import_progress($run_id, __('Done', 'hello-figma'), 100);
            wp_send_json_success([
                'template' => $elementor_data,
            ]);
        }

        $this->set_import_progress($run_id, __('Saving template...', 'hello-figma'), 80);

        $post_id = $this->plugin->get_template_manager()->save_template(
            $elementor_data,
            $title,
            $file_key,
            'page',
            $title,
            $file_name
        );

        if (is_wp_error($post_id)) {
            $this->clear_import_progress($run_id);
            wp_send_json_error(['message' => $post_id->get_error_message(), 'code' => self::ERROR_CODES['SAVE_FAILED']]);
        }

        $this->set_import_progress($run_id, __('Import complete!', 'hello-figma'), 100);

        wp_send_json_success([
            'post_id' => $post_id,
            'template' => $elementor_data,
            'edit_url' => add_query_arg(
                ['post' => $post_id, 'action' => 'elementor'],
                admin_url('post.php')
            ),
        ]);
    }

    public function ajax_import_progress(): void
    {
        $this->verify_ajax_readonly();

        $run_id = sanitize_text_field(wp_unslash($_POST['run_id'] ?? ''));
        if (empty($run_id)) {
            wp_send_json_error(['message' => 'No run ID provided.', 'code' => self::ERROR_CODES['MISSING_PARAMS']]);
        }

        $progress = get_transient('hello_figma_import_progress_' . $run_id);
        if ($progress === false) {
            wp_send_json_error(['message' => 'No progress data.', 'code' => self::ERROR_CODES['NO_PROGRESS']]);
        }

        wp_send_json_success($progress);
    }

    private function set_import_progress(string $run_id, string $stage, int $percentage, int $current = 0, int $total = 0): void
    {
        set_transient('hello_figma_import_progress_' . $run_id, [
            'stage' => $stage,
            'percentage' => $percentage,
            'current' => $current,
            'total' => $total,
        ], 5 * MINUTE_IN_SECONDS);
    }

    private function clear_import_progress(string $run_id): void
    {
        delete_transient('hello_figma_import_progress_' . $run_id);
    }

    private function count_image_placeholders(array $data): int
    {
        $count = 0;
        array_walk_recursive($data, function ($value) use (&$count): void {
            if (is_string($value) && str_starts_with($value, 'figma-image://')) {
                $count++;
            }
        });
        return $count;
    }

    public function ajax_delete_template(): void
    {
        $this->verify_ajax();

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- (int) cast sanitizes
        $post_id = (int) (wp_unslash($_POST['post_id'] ?? 0));
        $result = $this->plugin->get_template_manager()->delete_template($post_id);

        if (!$result) {
            wp_send_json_error(['message' => __('Failed to delete template.', 'hello-figma'), 'code' => self::ERROR_CODES['DELETE_FAILED']]);
        }

        wp_send_json_success(['message' => __('Template deleted.', 'hello-figma')]);
    }

    public function ajax_export_template(): void
    {
        $this->verify_ajax();

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- (int) cast sanitizes
        $post_id = (int) (wp_unslash($_POST['post_id'] ?? 0));
        $export = $this->plugin->get_template_manager()->export_template($post_id);

        if ($export === null) {
            wp_send_json_error(['message' => __('Failed to export template.', 'hello-figma'), 'code' => self::ERROR_CODES['EXPORT_FAILED']]);
        }

        wp_send_json_success($export);
    }

    public function ajax_fetch_preview(): void
    {
        $this->verify_ajax();

        $file_key = $this->get_file_key_from_post('file_key');
        $node_id = sanitize_text_field(wp_unslash($_POST['node_id'] ?? ''));

        if (empty($file_key)) {
            wp_send_json_error(['message' => __('File key is required.', 'hello-figma'), 'code' => self::ERROR_CODES['FILE_KEY_REQUIRED']]);
        }

        $images = $this->plugin->get_figma_api()->get_images(
            $file_key,
            $node_id ? [$node_id] : [],
            'png'
        );

        if ($images === null) {
            wp_send_json_error(['message' => __('Failed to fetch preview.', 'hello-figma'), 'code' => self::ERROR_CODES['FETCH_FAILED']]);
        }

        wp_send_json_success(['images' => $images]);
    }

    public function ajax_sync_styles(): void
    {
        $this->verify_ajax();

        $file_key = sanitize_text_field(wp_unslash($_POST['file_key'] ?? ''));
        if (empty($file_key)) {
            $file_key = get_option('hello_figma_file_key', '');
        }

        if (empty($file_key)) {
            wp_send_json_error(['message' => __('No Figma file key configured.', 'hello-figma'), 'code' => self::ERROR_CODES['FILE_KEY_REQUIRED']]);
        }

        $type = sanitize_text_field(wp_unslash($_POST['type'] ?? 'all'));
        $colors = [];
        $typography = [];

        if (in_array($type, ['all', 'colors'], true)) {
            $colors = $this->plugin->get_style_sync()->sync_colors($file_key);
        }
        if (in_array($type, ['all', 'typography'], true)) {
            $typography = $this->plugin->get_style_sync()->sync_typography($file_key);
        }

        wp_send_json_success([
            'colors' => $colors,
            'typography' => $typography,
            'message' => sprintf(
                // translators: %1$d is the number of colors, %2$d is the number of typography styles.
                __('Synced %1$d colors and %2$d typography styles.', 'hello-figma'),
                count($colors),
                count($typography)
            ),
        ]);
    }

    public function ajax_fetch_structure(): void
    {
        $this->verify_ajax();

        $file_key = $this->get_file_key_from_post('file_key');

        if (empty($file_key)) {
            wp_send_json_error(['message' => __('File key is required.', 'hello-figma'), 'code' => self::ERROR_CODES['FILE_KEY_REQUIRED']]);
        }

        $structure = $this->plugin->get_renderer()->get_file_structure($file_key);

        if ($structure === null) {
            wp_send_json_error([
                'message' => __('Failed to fetch file structure. Please check your file key and token.', 'hello-figma'),
            ]);
        }

        wp_send_json_success($structure);
    }

    public function ajax_fetch_frame_images(): void
    {
        $this->verify_ajax();

        $file_key = $this->get_file_key_from_post('file_key');
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via array_map below
        $node_ids_raw = wp_unslash($_POST['node_ids'] ?? '');

        if (empty($file_key) || empty($node_ids_raw)) {
            wp_send_json_error(['message' => __('File key and node IDs are required.', 'hello-figma'), 'code' => self::ERROR_CODES['MISSING_PARAMS']]);
        }

        // node_ids comes as comma-separated string
        $node_ids = array_map('sanitize_text_field', explode(',', $node_ids_raw));
        $node_ids = array_filter($node_ids);

        if (empty($node_ids)) {
            wp_send_json_error(['message' => __('No valid node IDs provided.', 'hello-figma'), 'code' => self::ERROR_CODES['MISSING_PARAMS']]);
        }

        $images = $this->plugin->get_figma_api()->get_images($file_key, $node_ids, 'png');
        if ($images === null) {
            wp_send_json_error(['message' => __('Failed to fetch preview images.', 'hello-figma'), 'code' => self::ERROR_CODES['FETCH_FAILED']]);
        }

        wp_send_json_success(['images' => $images]);
    }

    public function ajax_preview_sections(): void
    {
        $this->verify_ajax();

        $file_key = $this->get_file_key_from_post('file_key');
        $node_id = sanitize_text_field(wp_unslash($_POST['node_id'] ?? ''));

        if (empty($file_key) || empty($node_id)) {
            wp_send_json_error(['message' => __('File key and node ID are required.', 'hello-figma'), 'code' => self::ERROR_CODES['MISSING_PARAMS']]);
        }

        $sections = $this->plugin->get_renderer()->get_sections_preview($file_key, $node_id);

        if ($sections === null) {
            wp_send_json_error(['message' => __('Failed to preview sections.', 'hello-figma'), 'code' => self::ERROR_CODES['FETCH_FAILED']]);
        }

        wp_send_json_success(['sections' => $sections]);
    }

    private function verify_ajax(): void
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce validated by wp_verify_nonce
        if (!wp_verify_nonce(wp_unslash($_POST['nonce'] ?? ''), 'hello_figma_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'hello-figma'), 'code' => self::ERROR_CODES['SECURITY_FAILED']]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'hello-figma'), 'code' => self::ERROR_CODES['PERMISSION_DENIED']]);
        }
    }

    private function verify_ajax_readonly(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'hello-figma'), 'code' => self::ERROR_CODES['PERMISSION_DENIED']]);
        }
    }

    /**
     * Parse a Figma file key from a URL or return the raw input.
     *
     * Accepts:
     *   https://www.figma.com/file/abc123DEF/Name
     *   https://www.figma.com/design/abc123DEF/Name
     *   abc123DEF
     */
    private function parse_file_key(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }

        // Extract from full URL
        if (preg_match('#figma\.com/(?:file|design)/([a-zA-Z0-9_-]+)#i', $input, $m)) {
            return $m[1];
        }

        // Accept raw key
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $input)) {
            return $input;
        }

        return '';
    }

    /**
     * Use parse_file_key on a POST field.
     */
    private function get_file_key_from_post(string $field): string
    {
        return $this->parse_file_key(sanitize_text_field(wp_unslash($_POST[$field] ?? '')));
    }

    /**
     * Calculate the maximum nesting depth of a nested array.
     */
    private static function array_depth(array $data): int
    {
        $max_depth = 1;
        foreach ($data as $value) {
            if (is_array($value)) {
                $depth = 1 + self::array_depth($value);
                if ($depth > $max_depth) {
                    $max_depth = $depth;
                }
            }
        }
        return $max_depth;
    }
}
