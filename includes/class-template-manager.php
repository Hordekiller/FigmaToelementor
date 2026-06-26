<?php
declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Template_Manager {
    private const POST_TYPE = 'elementor_library';
    private const TAXONOMY = 'elementor_library_type';
    private const SOURCE_META = '_hello_figma_source';
    private const FILE_KEY_META = '_hello_figma_file_key';
    private const FIGMA_DATA_META = '_hello_figma_data';

    /**
     * Save a converted Figma design as an Elementor template.
     *
     * @param array $elementor_data Elementor JSON data
     * @param string $title Template title
     * @param string $file_key Figma file key
     * @param string $type Template type (page, section, etc.)
     * @return int|\WP_Error Post ID
     */
    public function save_template(array $elementor_data, string $title, string $file_key, string $type = 'page', string $node_name = '', string $file_name = '') {
        // Extract just the content array for _elementor_data (Elementor stores elements directly)
        $content = $elementor_data['content'] ?? [$elementor_data];

        $elementor_data_json = wp_json_encode($content);
        $elementor_data_size = strlen($elementor_data_json);
        $element_count = count($content);

        Logger::log('INFO', 'TemplateManager', 'Preparing to save template', [
            'title' => $title,
            'type' => $type,
            'file_key' => $file_key,
            'elementor_data_bytes' => $elementor_data_size,
            'top_level_element_count' => $element_count,
        ]);

        $post_data = [
            'post_title' => $title,
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'meta_input' => [
                '_elementor_template_type' => $type,
                '_elementor_edit_mode' => 'builder',
                '_elementor_data' => wp_slash($elementor_data_json),
                self::SOURCE_META => 'figma',
                self::FILE_KEY_META => $file_key,
                self::FIGMA_DATA_META => wp_json_encode([
                    'source' => 'figma',
                    'file_key' => $file_key,
                    'node_name' => $node_name ?: $title,
                    'file_name' => $file_name,
                    'imported_at' => current_time('mysql'),
                ]),
                '_hello_figma_node_name' => $node_name ?: $title,
                '_hello_figma_file_name' => $file_name,
            ],
        ];

        // Store the full template wrapper separately for export
        $post_data['meta_input']['_hello_figma_template_wrapper'] = wp_slash(wp_json_encode($elementor_data));

        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            Logger::log('INFO', 'TemplateManager', 'Template post created', [
                'post_id' => $post_id,
                'title' => $title,
            ]);

            wp_set_object_terms($post_id, $type, self::TAXONOMY);

            update_post_meta(
                $post_id,
                '_elementor_version',
                defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '3.27.0'
            );

            if (class_exists('\Elementor\Core\Files\CSS\Post')) {
                Logger::log('INFO', 'TemplateManager', 'Triggering Elementor CSS generation', [
                    'post_id' => $post_id,
                ]);

                try {
                    $css_file = new \Elementor\Core\Files\CSS\Post($post_id);
                    $css_file->delete();
                    $css_file->update();

                    $css_path = $css_file->get_path();
                    $file_exists = file_exists($css_path);
                    $file_size = $file_exists ? filesize($css_path) : 0;

                    Logger::log('INFO', 'TemplateManager', 'Elementor CSS generation completed', [
                        'post_id' => $post_id,
                        'css_path' => $css_path,
                        'file_exists' => $file_exists,
                        'file_size_bytes' => $file_size,
                    ]);
                } catch (\Throwable $e) {
                    Logger::log('ERROR', 'TemplateManager', 'Elementor CSS generation threw exception', [
                        'post_id' => $post_id,
                        'error_message' => $e->getMessage(),
                    ]);
                }
            } else {
                Logger::log('WARNING', 'TemplateManager', 'Elementor CSS Post class not found — cannot generate CSS', [
                    'post_id' => $post_id,
                ]);
            }
        } else {
            Logger::log('ERROR', 'TemplateManager', 'wp_insert_post failed', [
                'error_message' => $post_id->get_error_message(),
                'title' => $title,
            ]);
        }

        return $post_id;
    }

    /**
     * Get all Figma-imported templates.
     *
     * @param string $type Optional template type filter
     * @return array Array of WP_Post objects
     */
    public function get_figma_templates(string $type = ''): array {
        $args = [
            'post_type' => self::POST_TYPE,
            'posts_per_page' => -1,
            'meta_key' => self::SOURCE_META,
            'meta_value' => 'figma',
            'meta_compare' => '=',
        ];

        if (!empty($type)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => self::TAXONOMY,
                    'field' => 'slug',
                    'terms' => $type,
                ],
            ];
        }

        $query = new \WP_Query($args);
        return $query->posts;
    }

    /**
     * Delete a Figma-imported template.
     *
     * @param int $post_id
     * @return bool
     */
    public function delete_template(int $post_id): bool {
        $source = get_post_meta($post_id, self::SOURCE_META, true);
        if ($source !== 'figma') {
            return false;
        }

        return wp_delete_post($post_id, true) !== false;
    }

    /**
     * Export template as JSON file for download.
     *
     * @param int $post_id
     * @return array|null Array with 'json' and 'filename' keys
     */
    public function export_template(int $post_id): ?array {
        // Prefer the full wrapper we saved, otherwise reconstruct
        $wrapper = get_post_meta($post_id, '_hello_figma_template_wrapper', true);

        if (!empty($wrapper)) {
            $template = json_decode($wrapper, true);
        } else {
            $elementor_data = get_post_meta($post_id, '_elementor_data', true);
            if (empty($elementor_data)) {
                return null;
            }
            $template = [
                'version' => '0.4',
                'title' => get_the_title($post_id),
                'type' => 'page',
                'content' => json_decode($elementor_data, true),
                'page_settings' => [],
            ];
        }

        $filename = sanitize_title(get_the_title($post_id)) . '-figma-template.json';

        return [
            'json' => wp_json_encode($template, JSON_PRETTY_PRINT),
            'filename' => $filename,
        ];
    }

    /**
     * Get the Figma source data for a template.
     *
     * @param int $post_id
     * @return array|null
     */
    public function get_figma_data(int $post_id): ?array {
        $data = get_post_meta($post_id, self::FIGMA_DATA_META, true);
        if (empty($data)) {
            return null;
        }

        $decoded = json_decode($data, true);
        return $decoded ?: null;
    }

    /**
     * Get statistics about Figma imports.
     */
    public function get_statistics(): array {
        $templates = $this->get_figma_templates();

        $stats = [
            'total' => count($templates),
            'by_type' => [],
            'recent' => [],
        ];

        foreach ($templates as $template) {
            $terms = wp_get_object_terms($template->ID, self::TAXONOMY);
            $type = !is_wp_error($terms) && !empty($terms) ? $terms[0]->slug : 'unknown';

            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;
        }

        $recent_query = new \WP_Query([
            'post_type' => self::POST_TYPE,
            'posts_per_page' => 5,
            'meta_key' => self::SOURCE_META,
            'meta_value' => 'figma',
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $stats['recent'] = $recent_query->posts;

        return $stats;
    }

    /**
     * Create necessary database structures on activation.
     */
    public function create_tables(): void {
        // Elementor handles its own post type registration.
        // We just ensure our meta keys are recognized.
    }
}
