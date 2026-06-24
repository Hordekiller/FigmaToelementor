<?php
declare(strict_types=1);

namespace HelloFigma\DynamicTags;

defined('ABSPATH') || exit;

class Figma_Text extends \Elementor\Core\DynamicTags\Tag {

    public function get_name(): string {
        return 'figma-text';
    }

    public function get_title(): string {
        return esc_html__('Figma Text Style', 'hello-figma');
    }

    public function get_group(): array {
        return ['figma-group'];
    }

    public function get_categories(): array {
        return [
            \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
            \Elementor\Modules\DynamicTags\Module::COLOR_CATEGORY,
        ];
    }

    protected function register_controls(): void {
        $synced = get_option(\HelloFigma\Style_Sync::SYNCED_STYLES_OPTION, []);
        $options = [];

        foreach ($synced as $style) {
            if ($style['type'] === 'typography') {
                $options[$style['var']] = $style['name'];
            }
        }

        $this->add_control(
            'figma_style',
            [
                'label' => esc_html__('Figma Text Style', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => !empty($options) ? $options : [
                    '' => __('No styles synced yet', 'hello-figma'),
                ],
                'default' => '',
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
        $style_var = $settings['figma_style'] ?? '';
        $fallback = $settings['fallback'] ?? '';

        if (empty($style_var)) {
            echo esc_html($fallback);
            return;
        }

        echo esc_html($this->get_style_value($style_var));
    }

    private function get_style_value(string $var): string {
        $synced = get_option(\HelloFigma\Style_Sync::SYNCED_STYLES_OPTION, []);

        foreach ($synced as $style) {
            if (($style['var'] ?? '') === $var) {
                return $style['name'] . ' (' . ($style['value']['fontFamily'] ?? '') . ')';
            }
        }

        return '';
    }
}
