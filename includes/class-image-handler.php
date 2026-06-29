<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Image_Handler
{
    private Figma_API $figma_api;

    // Download max 5 images before yielding to avoid resource spikes
    private const DOWNLOAD_CHUNK_SIZE = 5;

    // Minimum interval between progress callbacks (seconds)
    private const PROGRESS_THROTTLE_SEC = 1.0;

    public function __construct(Figma_API $figma_api)
    {
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
    /**
     * @param callable|null $progress_callback Optional. Called with (int $current, int $total) per image.
     */
    public function batch_download_images(
        string $file_key,
        array $node_ids,
        string $format = 'png',
        ?callable $progress_callback = null
    ): array {
        $ids = array_keys($node_ids);
        $images = $this->figma_api->get_images($file_key, $ids, $format);
        if ($images === null) {
            Logger::log('WARNING', 'ImageHandler', 'get_images returned null for batch', [
                'node_ids' => $ids,
                'file_key' => $file_key,
            ]);
            return [];
        }

        $results = [];
        $image_idx = 0;
        $total = count($images);
        $chunk_idx = 0;
        $chunks = array_chunk($images, self::DOWNLOAD_CHUNK_SIZE, true);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $node_id => $image_url) {
                $image_idx++;

                Logger::log('INFO', 'ImageHandler', 'Resolving image', [
                    'ref_or_node_id' => $node_id,
                    'file_key' => $file_key,
                    'chunk' => $chunk_idx + 1,
                    'total_chunks' => count($chunks),
                ]);

                if (empty($image_url)) {
                    Logger::log('WARNING', 'ImageHandler', 'Figma returned null URL for ref', [
                        'ref' => $node_id,
                    ]);
                    $results[$node_id] = new \WP_Error(
                        'figma_image_empty',
                        sprintf('Empty URL for node %s', $node_id)
                    );
                    continue;
                }

                Logger::log('INFO', 'ImageHandler', 'Downloading image binary', [
                    'resolved_url' => $image_url,
                    'node_id' => $node_id,
                ]);

                $name = $node_ids[$node_id] ?? '';
                $attachment_id = $this->sideload_image($image_url, $name, $node_id);

                if (is_wp_error($attachment_id)) {
                    Logger::log('ERROR', 'ImageHandler', 'Image sideload failed', [
                        'node_id' => $node_id,
                        'error_message' => $attachment_id->get_error_message(),
                    ]);
                } else {
                    $final_url = wp_get_attachment_url($attachment_id);
                    Logger::log('INFO', 'ImageHandler', 'Image sideload succeeded', [
                        'node_id' => $node_id,
                        'attachment_id' => $attachment_id,
                        'final_url' => $final_url,
                    ]);
                }

                $results[$node_id] = $attachment_id;
            }

            $chunk_idx++;

            // Throttled progress update: report after each chunk
            if ($progress_callback !== null) {
                $progress_callback($image_idx, $total);
            }
        }

        return $results;
    }

    /**
     * Walk converted Elementor data and resolve every "figma-image://{nodeId}"
     * placeholder into a real WordPress attachment URL+ID.
     *
     * Images are downloaded from Figma via the images API and sideloaded into
     * the WordPress media library. Duplicate nodeId references share one download.
     *
     * @return array The data with all placeholders replaced.
     */
    /**
     * @param callable|null $progress_callback Optional. Called with (int $current, int $total) per image.
     */
    public function resolve_image_placeholders(string $file_key, array $data, ?callable $progress_callback = null): array
    {
        $node_ids = $this->collect_placeholder_ids($data);
        if (empty($node_ids)) {
            Logger::log('INFO', 'ImageHandler', 'No image placeholders found in converted data');
            return $data;
        }

        Logger::log('INFO', 'ImageHandler', 'Image placeholders discovered', [
            'count' => count($node_ids),
            'node_ids' => $node_ids,
        ]);

        $name_map = [];
        foreach ($node_ids as $id) {
            $name_map[$id] = $id;
        }

        // Throttle progress callback to at most once per PROGRESS_THROTTLE_SEC
        $last_callback_time = 0.0;
        $throttled_callback = null;
        if ($progress_callback !== null) {
            $throttled_callback = function (int $current, int $total) use ($progress_callback, &$last_callback_time): void {
                $now = microtime(true);
                if ($now - $last_callback_time < self::PROGRESS_THROTTLE_SEC && $current < $total) {
                    return; // Skip — too soon
                }
                $last_callback_time = $now;
                $progress_callback($current, $total);
            };
        }

        $results = $this->batch_download_images($file_key, $name_map, 'png', $throttled_callback);

        $resolved = [];
        $failures = 0;
        foreach ($results as $node_id => $attachment_id) {
            if (is_wp_error($attachment_id)) {
                $failures++;
                continue;
            }
            $url = wp_get_attachment_url($attachment_id);
            if ($url) {
                $resolved[$node_id] = [
                    'url' => $url,
                    'id' => $attachment_id,
                ];
            }
        }

        Logger::log('INFO', 'ImageHandler', 'Image download batch complete', [
            'requested' => count($node_ids),
            'succeeded' => count($resolved),
            'failed' => $failures,
        ]);

        if (empty($resolved)) {
            return $data;
        }

        $result = $this->walk_and_replace($data, $resolved);

        Logger::log('INFO', 'ImageHandler', 'Placeholder replacement complete', [
            'placeholders_found' => count($node_ids),
            'replaced' => count($resolved),
            'unresolved' => count($node_ids) - count($resolved),
        ]);

        return $result;
    }

    /**
     * Recursively collect unique node IDs found in figma-image:// placeholders.
     */
    private function collect_placeholder_ids(array $data): array
    {
        $ids = [];
        array_walk_recursive($data, function ($value) use (&$ids): void {
            if (is_string($value) && str_starts_with($value, 'figma-image://')) {
                $node_id = substr($value, strlen('figma-image://'));
                if ($node_id !== '') {
                    $ids[$node_id] = true;
                }
            }
        });
        return array_keys($ids);
    }

    /**
     * Recursively walk the Elementor data array and replace placeholders.
     *
     * When an array with a "url" key contains a figma-image:// placeholder,
     * both its "url" and "id" fields are replaced with the real attachment data.
     */
    private function walk_and_replace(array $data, array $resolved): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                if (isset($value['url']) && is_string($value['url']) && str_starts_with($value['url'], 'figma-image://')) {
                    $node_id = substr($value['url'], strlen('figma-image://'));
                    if (isset($resolved[$node_id])) {
                        $value['url'] = $resolved[$node_id]['url'];
                        $value['id'] = $resolved[$node_id]['id'];
                    }
                } else {
                    $value = $this->walk_and_replace($value, $resolved);
                }
            }
        }
        unset($value);
        return $data;
    }

    /**
     * Sideload an image from URL into WordPress media library.
     *
     * @param string $url Image URL
     * @param string $name Image name
     * @param string $node_id Figma node ID (for meta)
     * @return int|\WP_Error
     */
    private function sideload_image(string $url, string $name = '', string $node_id = '')
    {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Validate URL: only https and known Figma/S3 domains.
        $allowed_hosts = [
            'figma.com',
            'figma-beta.com',
            's3.amazonaws.com',
            's3.us-west-2.amazonaws.com',
            's3.us-east-1.amazonaws.com',
        ];
        $parsed = wp_parse_url($url);
        $host = $parsed['host'] ?? '';
        if (empty($host) || wp_parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return new \WP_Error('figma_ssrf_blocked', __('Image URL must be HTTPS.', 'hello-figma'));
        }
        $allowed = false;
        foreach ($allowed_hosts as $ah) {
            if ($host === $ah || str_ends_with($host, '.' . $ah)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            Logger::log('WARNING', 'ImageHandler', 'Blocked download from untrusted host', ['host' => $host, 'url' => $url]);
            /* translators: %s: hostname of blocked URL */
            return new \WP_Error('figma_ssrf_blocked', sprintf(__('Download from %s is not allowed.', 'hello-figma'), $host));
        }

        $temp_file = download_url($url);
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        $file_name = !empty($name)
            ? sanitize_file_name($name) . '.' . pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)
            : basename(parse_url($url, PHP_URL_PATH));

        // Only allow image MIME types — block SVG, XML, etc.
        $mime_type = wp_check_filetype($file_name);
        if (!in_array($mime_type['type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'], true)) {
            @unlink($temp_file);
            return new \WP_Error('figma_invalid_mime', __('Downloaded file is not a supported image type.', 'hello-figma'));
        }

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
