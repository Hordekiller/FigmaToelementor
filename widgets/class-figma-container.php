<?php
declare(strict_types=1);

namespace HelloFigma\Widgets;

defined('ABSPATH') || exit;

class Figma_Container extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'figma_container';
    }

    public function get_title(): string {
        return esc_html__('Figma Container', 'hello-figma');
    }

    public function get_icon(): string {
        return 'eicon-container';
    }

    public function get_categories(): array {
        return ['figma-category'];
    }

    public function get_keywords(): array {
        return ['figma', 'container', 'flex', 'layout'];
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
            'flex_direction',
            [
                'label' => esc_html__('Direction', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'column',
                'options' => [
                    'row' => esc_html__('Row', 'hello-figma'),
                    'column' => esc_html__('Column', 'hello-figma'),
                    'row-reverse' => esc_html__('Row Reverse', 'hello-figma'),
                    'column-reverse' => esc_html__('Column Reverse', 'hello-figma'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .figma-container' => 'flex-direction: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'flex_wrap',
            [
                'label' => esc_html__('Wrap', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'wrap',
                'options' => [
                    'nowrap' => esc_html__('No Wrap', 'hello-figma'),
                    'wrap' => esc_html__('Wrap', 'hello-figma'),
                    'wrap-reverse' => esc_html__('Wrap Reverse', 'hello-figma'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .figma-container' => 'flex-wrap: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'align_items',
            [
                'label' => esc_html__('Align Items', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'flex-start',
                'options' => [
                    'flex-start' => esc_html__('Start', 'hello-figma'),
                    'center' => esc_html__('Center', 'hello-figma'),
                    'flex-end' => esc_html__('End', 'hello-figma'),
                    'stretch' => esc_html__('Stretch', 'hello-figma'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .figma-container' => 'align-items: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'justify_content',
            [
                'label' => esc_html__('Justify Content', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'flex-start',
                'options' => [
                    'flex-start' => esc_html__('Start', 'hello-figma'),
                    'center' => esc_html__('Center', 'hello-figma'),
                    'flex-end' => esc_html__('End', 'hello-figma'),
                    'space-between' => esc_html__('Space Between', 'hello-figma'),
                    'space-around' => esc_html__('Space Around', 'hello-figma'),
                    'space-evenly' => esc_html__('Space Evenly', 'hello-figma'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .figma-container' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'gap',
            [
                'label' => esc_html__('Gap', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 100],
                ],
                'selectors' => [
                    '{{WRAPPER}} .figma-container' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style',
            [
                'label' => esc_html__('Style', 'hello-figma'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'background',
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .figma-container',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'border',
                'selector' => '{{WRAPPER}} .figma-container',
            ]
        );

        $this->add_control(
            'border_radius',
            [
                'label' => esc_html__('Border Radius', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .figma-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'box_shadow',
                'selector' => '{{WRAPPER}} .figma-container',
            ]
        );

        $this->add_control(
            'padding',
            [
                'label' => esc_html__('Padding', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .figma-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        ?>
        <div class="figma-container">
            <?php
            $children = $this->get_children();
            foreach ($children as $child) {
                $child->print_element();
            }
            ?>
        </div>
        <?php
    }

    protected function content_template(): void {
        ?>
        <div class="figma-container">
            <#
            if (settings.elements) {
                _.each(settings.elements, function(element) {
                    print(element);
                });
            }
            #>
        </div>
        <?php
    }
}
