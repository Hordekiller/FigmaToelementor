<?php
declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Admin {
    private Plugin $plugin;
    private string $menu_slug = 'hello-figma';

    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
    }

    public function init(): void {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'handle_form_submissions']);
        add_action('admin_init', [$this, 'handle_ajax_actions']);
    }

    public function register_admin_menu(): void {
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

    public function render_dashboard(): void {
        $has_token = $this->plugin->get_figma_api()->has_token();
        $stats = $this->plugin->get_template_manager()->get_statistics();
        include HELLO_FIGMA_PATH . 'admin/views/dashboard.php';
    }

    public function render_templates(): void {
        $templates = $this->plugin->get_template_manager()->get_figma_templates();
        include HELLO_FIGMA_PATH . 'admin/views/templates.php';
    }

    public function render_style_sync(): void {
        $this->plugin->get_style_sync()->handle_form_submission();
        include HELLO_FIGMA_PATH . 'admin/views/style-sync.php';
    }

    public function render_settings(): void {
        include HELLO_FIGMA_PATH . 'admin/views/settings.php';
    }

    public function handle_form_submissions(): void {
        // Handle clear cache
        if (isset($_POST['hello_figma_clear_cache'])) {
            if (!wp_verify_nonce($_POST['_figma_cache_nonce'] ?? '', 'hello_figma_clear_cache')) {
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

        if (!wp_verify_nonce($_POST['_hello_figma_nonce'] ?? '', 'hello_figma_settings')) {
            wp_die(__('Security check failed.', 'hello-figma'));
        }

        $pat = sanitize_text_field($_POST['figma_pat'] ?? '');
        if (!empty($pat) && $pat !== '********') {
            $this->plugin->get_figma_api()->set_token($pat);
        }

        $file_key = $this->parse_file_key(sanitize_text_field($_POST['figma_file_key'] ?? ''));
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

    public function handle_ajax_actions(): void {
        add_action('wp_ajax_hello_figma_convert', [$this, 'ajax_convert']);
        add_action('wp_ajax_hello_figma_delete_template', [$this, 'ajax_delete_template']);
        add_action('wp_ajax_hello_figma_export_template', [$this, 'ajax_export_template']);
        add_action('wp_ajax_hello_figma_fetch_preview', [$this, 'ajax_fetch_preview']);
        add_action('wp_ajax_hello_figma_sync_styles', [$this, 'ajax_sync_styles']);
        add_action('wp_ajax_hello_figma_fetch_structure', [$this, 'ajax_fetch_structure']);
        add_action('wp_ajax_hello_figma_fetch_frame_images', [$this, 'ajax_fetch_frame_images']);
    }

    public function ajax_convert(): void {
        $this->verify_ajax();

        Logger::start_run(uniqid('figma_import_'));

        Logger::log('INFO', 'Admin', 'Import started', [
            'file_key' => $this->get_file_key_from_post('file_key'),
            'node_id' => sanitize_text_field($_POST['node_id'] ?? ''),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'format' => sanitize_text_field($_POST['format'] ?? 'post'),
        ]);

        $file_key = $this->get_file_key_from_post('file_key');
        $node_id = sanitize_text_field($_POST['node_id'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? __('Figma Import', 'hello-figma'));
        $format = sanitize_text_field($_POST['format'] ?? 'post');

        if (empty($file_key)) {
            wp_send_json_error(['message' => __('File key is required.', 'hello-figma')]);
        }

        $elementor_data = $this->plugin->get_renderer()->convert_file($file_key, $node_id ?: null);
        if ($elementor_data === null) {
            wp_send_json_error(['message' => __('Failed to convert Figma file.', 'hello-figma')]);
        }

        // Resolve figma-image:// placeholders to real WordPress attachments
        $elementor_data = $this->plugin->get_image_handler()->resolve_image_placeholders($file_key, $elementor_data);

        // format=json: return raw template data (for JS API / editor.js)
        if ($format === 'json') {
            wp_send_json_success([
                'template' => $elementor_data,
            ]);
        }

        // format=post: save as Elementor template post (default)
        $post_id = $this->plugin->get_template_manager()->save_template(
            $elementor_data,
            $title,
            $file_key
        );

        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => $post_id->get_error_message()]);
        }

        wp_send_json_success([
            'post_id' => $post_id,
            'template' => $elementor_data,
            'edit_url' => add_query_arg(
                ['post' => $post_id, 'action' => 'elementor'],
                admin_url('post.php')
            ),
        ]);
    }

    public function ajax_delete_template(): void {
        $this->verify_ajax();

        $post_id = (int) ($_POST['post_id'] ?? 0);
        $result = $this->plugin->get_template_manager()->delete_template($post_id);

        if (!$result) {
            wp_send_json_error(['message' => __('Failed to delete template.', 'hello-figma')]);
        }

        wp_send_json_success(['message' => __('Template deleted.', 'hello-figma')]);
    }

    public function ajax_export_template(): void {
        $this->verify_ajax();

        $post_id = (int) ($_POST['post_id'] ?? 0);
        $export = $this->plugin->get_template_manager()->export_template($post_id);

        if ($export === null) {
            wp_send_json_error(['message' => __('Failed to export template.', 'hello-figma')]);
        }

        wp_send_json_success($export);
    }

    public function ajax_fetch_preview(): void {
        $this->verify_ajax();

        $file_key = $this->get_file_key_from_post('file_key');
        $node_id = sanitize_text_field($_POST['node_id'] ?? '');

        if (empty($file_key)) {
            wp_send_json_error(['message' => __('File key is required.', 'hello-figma')]);
        }

        $images = $this->plugin->get_figma_api()->get_images(
            $file_key,
            $node_id ? [$node_id] : [],
            'png'
        );

        if ($images === null) {
            wp_send_json_error(['message' => __('Failed to fetch preview.', 'hello-figma')]);
        }

        wp_send_json_success(['images' => $images]);
    }

    public function ajax_sync_styles(): void {
        $this->verify_ajax();

        $file_key = sanitize_text_field($_POST['file_key'] ?? '');
        if (empty($file_key)) {
            $file_key = get_option('hello_figma_file_key', '');
        }

        if (empty($file_key)) {
            wp_send_json_error(['message' => __('No Figma file key configured.', 'hello-figma')]);
        }

        $colors = $this->plugin->get_style_sync()->sync_colors($file_key);
        $typography = $this->plugin->get_style_sync()->sync_typography($file_key);

        wp_send_json_success([
            'colors' => $colors,
            'typography' => $typography,
            'message' => sprintf(
                __('Synced %d colors and %d typography styles.', 'hello-figma'),
                count($colors),
                count($typography)
            ),
        ]);
    }

    public function ajax_fetch_structure(): void {
        $this->verify_ajax();

        $file_key = $this->get_file_key_from_post('file_key');

        if (empty($file_key)) {
            wp_send_json_error(['message' => __('File key is required.', 'hello-figma')]);
        }

        $structure = $this->plugin->get_renderer()->get_file_structure($file_key);

        if ($structure === null) {
            wp_send_json_error([
                'message' => __('Failed to fetch file structure. Please check your file key and token.', 'hello-figma'),
            ]);
        }

        wp_send_json_success($structure);
    }

    public function ajax_fetch_frame_images(): void {
        $this->verify_ajax();

        $file_key = $this->get_file_key_from_post('file_key');
        $node_ids_raw = $_POST['node_ids'] ?? '';

        if (empty($file_key) || empty($node_ids_raw)) {
            wp_send_json_error(['message' => __('File key and node IDs are required.', 'hello-figma')]);
        }

        // node_ids comes as comma-separated string
        $node_ids = array_map('sanitize_text_field', explode(',', $node_ids_raw));
        $node_ids = array_filter($node_ids);

        if (empty($node_ids)) {
            wp_send_json_error(['message' => __('No valid node IDs provided.', 'hello-figma')]);
        }

        $images = $this->plugin->get_figma_api()->get_images($file_key, $node_ids, 'png');
        if ($images === null) {
            wp_send_json_error(['message' => __('Failed to fetch preview images.', 'hello-figma')]);
        }

        wp_send_json_success(['images' => $images]);
    }

    private function verify_ajax(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'hello_figma_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'hello-figma')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'hello-figma')]);
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
    private function parse_file_key(string $input): string {
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
    private function get_file_key_from_post(string $field): string {
        return $this->parse_file_key(sanitize_text_field($_POST[$field] ?? ''));
    }
}
