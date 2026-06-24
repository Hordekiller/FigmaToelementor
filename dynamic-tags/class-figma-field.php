<?php
declare(strict_types=1);

namespace HelloFigma\DynamicTags;

defined('ABSPATH') || exit;

class Figma_Field extends \Elementor\Core\DynamicTags\Tag {

    public function get_name(): string {
        return 'figma-field';
    }

    public function get_title(): string {
        return esc_html__('Figma Field', 'hello-figma');
    }

    public function get_group(): array {
        return ['figma-group'];
    }

    public function get_categories(): array {
        return [
            \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
            \Elementor\Modules\DynamicTags\Module::URL_CATEGORY,
        ];
    }

    protected function register_controls(): void {
        $this->add_control(
            'figma_field_type',
            [
                'label' => esc_html__('Field Type', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'file_key' => __('File Key', 'hello-figma'),
                    'node_name' => __('Node Name', 'hello-figma'),
                    'file_name' => __('File Name', 'hello-figma'),
                ],
                'default' => 'file_key',
            ]
        );

        $this->add_control(
            'fallback',
            [
                'label' => esc_html__('Fallback', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
            ]
        );
    }

    public function render(): void {
        $settings = $this->get_settings_for_display();
        $type = $settings['figma_field_type'] ?? 'file_key';
        $fallback = $settings['fallback'] ?? '';

        $value = $this->get_field_value($type);

        if (empty($value)) {
            echo esc_html($fallback);
            return;
        }

        echo esc_html($value);
    }

    private function get_field_value(string $type): string {
        return match ($type) {
            'file_key' => get_option('hello_figma_file_key', ''),
            'node_name' => get_post_meta(get_the_ID(), '_hello_figma_node_name', true),
            'file_name' => get_post_meta(get_the_ID(), '_hello_figma_file_name', true),
            default => '',
        };
    }
}
