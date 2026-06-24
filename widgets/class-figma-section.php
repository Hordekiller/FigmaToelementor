<?php
declare(strict_types=1);

namespace HelloFigma\Widgets;

defined('ABSPATH') || exit;

class Figma_Section extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'figma_section';
    }

    public function get_title(): string {
        return esc_html__('Figma Section', 'hello-figma');
    }

    public function get_icon(): string {
        return 'eicon-section';
    }

    public function get_categories(): array {
        return ['figma-category'];
    }

    public function get_keywords(): array {
        return ['figma', 'section', 'row', 'wrapper'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->start_controls_section(
            'section_layout',
            [
                'label' => esc_html__('Layout', 'hello-figma'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'content_width',
            [
                'label' => esc_html__('Content Width', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'full',
                'options' => [
                    'full' => esc_html__('Full Width', 'hello-figma'),
                    'boxed' => esc_html__('Boxed', 'hello-figma'),
                ],
            ]
        );

        $this->add_control(
            'min_height',
            [
                'label' => esc_html__('Min Height', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'vh'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 1200],
                    'vh' => ['min' => 0, 'max' => 100],
                ],
                'selectors' => [
                    '{{WRAPPER}} .figma-section' => 'min-height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_background',
            [
                'label' => esc_html__('Background', 'hello-figma'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'background',
                'types' => ['classic', 'gradient', 'video'],
                'selector' => '{{WRAPPER}} .figma-section',
            ]
        );

        $this->add_control(
            'overlay_color',
            [
                'label' => esc_html__('Overlay Color', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .figma-section::before' => 'background-color: {{VALUE}};',
                ],
                'condition' => [
                    'background_background' => ['classic', 'gradient'],
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_spacing',
            [
                'label' => esc_html__('Spacing', 'hello-figma'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'padding',
            [
                'label' => esc_html__('Padding', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'default' => [
                    'top' => '80',
                    'right' => '0',
                    'bottom' => '80',
                    'left' => '0',
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .figma-section' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'margin',
            [
                'label' => esc_html__('Margin', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .figma-section' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_border',
            [
                'label' => esc_html__('Border', 'hello-figma'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'border',
                'selector' => '{{WRAPPER}} .figma-section',
            ]
        );

        $this->add_control(
            'border_radius',
            [
                'label' => esc_html__('Border Radius', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .figma-section' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $this->add_render_attribute('section', 'class', 'figma-section');

        if ($settings['content_width'] === 'boxed') {
            $this->add_render_attribute('section', 'class', 'figma-section-boxed');
        }
        ?>
        <div <?php $this->print_render_attribute_string('section'); ?>>
            <div class="figma-section-inner">
                <?php
                $children = $this->get_children();
                foreach ($children as $child) {
                    $child->print_element();
                }
                ?>
            </div>
        </div>
        <?php
    }

    protected function content_template(): void {
        ?>
        <div class="figma-section figma-section-{{ settings.content_width }}">
            <div class="figma-section-inner">
                <#
                if (settings.elements) {
                    _.each(settings.elements, function(element) {
                        print(element);
                    });
                }
                #>
            </div>
        </div>
        <?php
    }
}
