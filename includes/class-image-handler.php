<?php
declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Image_Handler {
    private Figma_API $figma_api;

    public function __construct(Figma_API $figma_api) {
        $this->figma_api = $figma_api;
    }

    /**
     * Download an image from Figma and add to WordPress media library.
     *
     * @param string $file_key Figma file key
     * @param string $node_id Node ID to export
     * @param string $name Image name
     * @param string $format Export format
     * @return int|\WP_Error Attachment ID
     */
    public function download_and_attach_image(
        string $file_key,
        string $node_id,
        string $name = '',
        string $format = 'png'
    ) {
        $images = $this->figma_api->get_images($file_key, [$node_id], $format);
        if ($images === null || !isset($images[$node_id])) {
            return new \WP_Error(
                'figma_image_error',
                __('Failed to get image URL from Figma.', 'hello-figma')
            );
        }

        $image_url = $images[$node_id];
        if (empty($image_url)) {
            return new \WP_Error(
                'figma_image_empty',
                __('Empty image URL returned from Figma.', 'hello-figma')
            );
        }

        return $this->sideload_image($image_url, $name, $node_id);
    }

    /**
     * Batch download multiple images.
     *
     * @param string $file_key Figma file key
     * @param array $node_ids Map of node_id => name
     * @param string $format Export format
     * @return array Map of node_id => attachment_id|\WP_Error
     */
    public function batch_download_images(
        string $file_key,
        array $node_ids,
        string $format = 'png'
    ): array {
        $ids = array_keys($node_ids);
        $images = $this->figma_api->get_images($file_key, $ids, $format);
        if ($images === null) {
            return [];
        }

        $results = [];
        foreach ($images as $node_id => $image_url) {
            if (empty($image_url)) {
                $results[$node_id] = new \WP_Error(
                    'figma_image_empty',
                    sprintf('Empty URL for node %s', $node_id)
                );
                continue;
            }

            $name = $node_ids[$node_id] ?? '';
            $results[$node_id] = $this->sideload_image($image_url, $name, $node_id);
        }

        return $results;
    }

    /**
     * Sideload an image from URL into WordPress media library.
     *
     * @param string $url Image URL
     * @param string $name Image name
     * @param string $node_id Figma node ID (for meta)
     * @return int|\WP_Error
     */
    private function sideload_image(string $url, string $name = '', string $node_id = '') {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $temp_file = download_url($url);
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        $file_name = !empty($name)
            ? sanitize_file_name($name) . '.' . pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)
            : basename(parse_url($url, PHP_URL_PATH));

        $file_array = [
            'name' => $file_name,
            'tmp_name' => $temp_file,
        ];

        $attachment_id = media_handle_sideload($file_array, 0);

        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            return $attachment_id;
        }

        if (!empty($name)) {
            wp_update_post([
                'ID' => $attachment_id,
                'post_title' => $name,
            ]);
        }

        if (!empty($node_id)) {
            update_post_meta($attachment_id, '_hello_figma_node_id', $node_id);
        }

        @unlink($temp_file);
        return $attachment_id;
    }
}
