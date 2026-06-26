<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', '/');
}
if (!defined('WP_UNINSTALL_PLUGIN')) {
    define('WP_UNINSTALL_PLUGIN', true);
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}
if (!defined('HELLO_FIGMA_PATH')) {
    define('HELLO_FIGMA_PATH', dirname(__DIR__) . '/');
}
if (!defined('HELLO_FIGMA_URL')) {
    define('HELLO_FIGMA_URL', 'https://example.com');
}
if (!defined('HELLO_FIGMA_FILE')) {
    define('HELLO_FIGMA_FILE', __FILE__);
}
if (!defined('HELLO_FIGMA_BASENAME')) {
    define('HELLO_FIGMA_BASENAME', 'hello-elementor-figma-sync/hello-elementor-figma-sync.php');
}

if (!function_exists('__')) { function __(...$args): string { return ''; } }
if (!function_exists('_e')) { function _e(...$args): void {} }
if (!function_exists('esc_html__')) { function esc_html__(...$args): string { return ''; } }
if (!function_exists('esc_html_e')) { function esc_html_e(...$args): void {} }
if (!function_exists('esc_attr__')) { function esc_attr__(...$args): string { return ''; } }
if (!function_exists('esc_attr_e')) { function esc_attr_e(...$args): void {} }
if (!function_exists('esc_html')) { function esc_html(...$args): string { return ''; } }
if (!function_exists('esc_attr')) { function esc_attr(...$args): string { return ''; } }
if (!function_exists('esc_url')) { function esc_url(...$args): string { return ''; } }
if (!function_exists('esc_js')) { function esc_js(...$args): string { return ''; } }
if (!function_exists('wp_kses_post')) { function wp_kses_post(...$args): string { return ''; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field(...$args): string { return ''; } }
if (!function_exists('sanitize_title')) { function sanitize_title(...$args): string { return ''; } }
if (!function_exists('sanitize_file_name')) { function sanitize_file_name(...$args): string { return ''; } }
if (!function_exists('wp_unslash')) { function wp_unslash(...$args): string { return ''; } }
if (!function_exists('wp_slash')) { function wp_slash(...$args): string { return ''; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode(...$args): string { return ''; } }
if (!function_exists('download_url')) { function download_url(...$args): string|object { return ''; } }
if (!function_exists('media_handle_sideload')) { function media_handle_sideload(...$args): int|object { return 0; } }
if (!function_exists('wp_check_filetype')) { function wp_check_filetype(...$args): array { return []; } }
if (!function_exists('wp_read_image_metadata')) { function wp_read_image_metadata(...$args): array { return []; } }
if (!function_exists('is_plugin_active')) { function is_plugin_active(...$args): bool { return true; } }
if (!function_exists('did_action')) { function did_action(...$args): int { return 0; } }
if (!function_exists('add_menu_page')) { function add_menu_page(...$args): string { return ''; } }
if (!function_exists('add_submenu_page')) { function add_submenu_page(...$args): string { return ''; } }
if (!function_exists('wp_remote_get')) {
    /**
     * @return array<string, mixed>|\WP_Error
     */
    function wp_remote_get(...$args): array|object { return []; }
}
if (!function_exists('wp_remote_retrieve_response_code')) { function wp_remote_retrieve_response_code(...$args): int { return 200; } }
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body(...$args): string { return ''; } }
if (!function_exists('wp_remote_retrieve_header')) { function wp_remote_retrieve_header(...$args): string { return ''; } }
if (!function_exists('wp_remote_retrieve_response_message')) { function wp_remote_retrieve_response_message(...$args): string { return ''; } }
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style(...$args): void {} }
if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script(...$args): void {} }
if (!function_exists('wp_localize_script')) { function wp_localize_script(...$args): void {} }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce(...$args): string { return ''; } }
if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce(...$args): bool|int { return true; } }
if (!function_exists('wp_nonce_field')) { function wp_nonce_field(...$args): void {} }
if (!function_exists('wp_salt')) { function wp_salt(...$args): string { return ''; } }
if (!function_exists('get_option')) { function get_option(...$args): mixed { return null; } }
if (!function_exists('update_option')) { function update_option(...$args): bool { return true; } }
if (!function_exists('delete_option')) { function delete_option(...$args): bool { return true; } }
if (!function_exists('delete_metadata')) { function delete_metadata(...$args): bool { return true; } }
if (!function_exists('get_transient')) { function get_transient(...$args): mixed { return false; } }
if (!function_exists('set_transient')) { function set_transient(...$args): bool { return true; } }
if (!function_exists('delete_transient')) { function delete_transient(...$args): bool { return true; } }
if (!function_exists('get_post_meta')) { function get_post_meta(...$args): mixed { return ''; } }
if (!function_exists('update_post_meta')) { function update_post_meta(...$args): bool|int { return true; } }
if (!function_exists('get_the_ID')) { function get_the_ID(...$args): int { return 0; } }
if (!function_exists('get_the_title')) { function get_the_title(...$args): string { return ''; } }
if (!function_exists('get_the_date')) { function get_the_date(...$args): string { return ''; } }
if (!function_exists('wp_insert_post')) { function wp_insert_post(...$args): int|object { return 0; } }
if (!function_exists('wp_delete_post')) { function wp_delete_post(...$args): ?object { return null; } }
if (!function_exists('wp_update_post')) { function wp_update_post(...$args): int|object { return 0; } }
if (!function_exists('wp_get_attachment_url')) { function wp_get_attachment_url(...$args): string { return ''; } }
if (!function_exists('wp_get_attachment_image_srcset')) { function wp_get_attachment_image_srcset(...$args): array|false { return false; } }
if (!function_exists('wp_get_object_terms')) { function wp_get_object_terms(...$args): array|object { return []; } }
if (!function_exists('wp_set_object_terms')) { function wp_set_object_terms(...$args): array|object { return []; } }
if (!function_exists('admin_url')) { function admin_url(...$args): string { return ''; } }
if (!function_exists('add_query_arg')) { function add_query_arg(...$args): string { return ''; } }
if (!function_exists('wp_send_json_success')) { function wp_send_json_success(...$args): void {} }
if (!function_exists('wp_send_json_error')) { function wp_send_json_error(...$args): void {} }
if (!function_exists('wp_die')) { function wp_die(...$args): void {} }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path(...$args): string { return ''; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url(...$args): string { return ''; } }
if (!function_exists('plugin_basename')) { function plugin_basename(...$args): string { return ''; } }
if (!function_exists('wp_upload_dir')) { function wp_upload_dir(...$args): array { return []; } }
if (!function_exists('wp_mkdir_p')) { function wp_mkdir_p(...$args): bool { return true; } }
if (!function_exists('add_action')) { function add_action(...$args): bool { return true; } }
if (!function_exists('add_filter')) { function add_filter(...$args): bool { return true; } }
if (!function_exists('apply_filters')) { function apply_filters(...$args): mixed { return func_get_arg(1); } }
if (!function_exists('do_action')) { function do_action(...$args): void {} }
if (!function_exists('register_activation_hook')) { function register_activation_hook(...$args): void {} }
if (!function_exists('register_deactivation_hook')) { function register_deactivation_hook(...$args): void {} }
if (!function_exists('current_user_can')) { function current_user_can(...$args): bool { return true; } }
if (!function_exists('is_wp_error')) {
    /**
     * @param mixed $thing
     * @phpstan-assert-if-true \WP_Error $thing
     */
    function is_wp_error(...$args): bool { return false; }
}
if (!function_exists('load_plugin_textdomain')) { function load_plugin_textdomain(...$args): bool { return true; } }
if (!function_exists('wp_using_ext_object_cache')) { function wp_using_ext_object_cache(...$args): bool { return false; } }

if (!class_exists('WP_Error')) {
    class WP_Error {
        private array $errors = [];
        public function __construct(...$args) {}
        public function get_error_code(...$args): string { return ''; }
        public function get_error_message(...$args): string { return ''; }
        public function get_error_messages(...$args): array { return []; }
        public function add(...$args): void {}
    }
}

if (!class_exists('WP_Query')) {
    class WP_Query {
        public array $posts = [];
        public function __construct(...$args) {}
        public function have_posts(...$args): bool { return false; }
        public function the_post(...$args): void {}
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post {}
}

if (!class_exists('wpdb')) {
    class wpdb {
        public string $options = 'options';
        public function query(...$args): bool|int { return true; }
        public function prepare(...$args): string { return ''; }
        public function esc_like(...$args): string { return ''; }
    }
}
