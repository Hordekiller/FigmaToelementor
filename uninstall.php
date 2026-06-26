<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// Delete plugin options
$options = [
    'hello_figma_pat',
    'hello_figma_token_created_at',
    'hello_figma_file_key',
    'hello_figma_synced_styles',
];

foreach ($options as $option) {
    delete_option($option);
}

// Delete all figma-related transients
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_hello_figma_') . '%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_timeout_hello_figma_') . '%'
    )
);

// Delete post meta from Elementor templates
$meta_keys = [
    '_hello_figma_source',
    '_hello_figma_file_key',
    '_hello_figma_data',
    '_hello_figma_node_name',
    '_hello_figma_file_name',
    '_hello_figma_template_wrapper',
    'hello_figma_figma_data',
];

foreach ($meta_keys as $meta_key) {
    delete_metadata('post', null, $meta_key, '', true);
}

// Delete attachment meta
delete_metadata('post', null, '_hello_figma_node_id', '', true);
