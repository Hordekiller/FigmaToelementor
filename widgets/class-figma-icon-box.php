<?php

declare(strict_types=1);

namespace HelloFigma\Widgets;

defined('ABSPATH') || exit;

class Figma_Icon_Box extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'figma_icon_box';
    }

    public function get_title(): string
    {
        return esc_html__('Figma Icon Box', 'hello-figma');
    }

    public function get_icon(): string
    {
        return 'eicon-icon-box';
    }

    public function get_categories(): array
    {
        return ['figma-category'];
    }

    public function get_keywords(): array
    {
        return ['figma', 'icon', 'box', 'feature'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__('Content', 'hello-figma'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'icon',
            [
                'label' => esc_html__('Icon', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-star',
                    'library' => 'fa-solid',
                ],
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => esc_html__('Title', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Feature Title', 'hello-figma'),
                'dynamic' => ['active' => true],
            ]
        );

        $this->add_control(
            'description',
            [
                'label' => esc_html__('Description', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => esc_html__('Feature description goes here.', 'hello-figma'),
                'dynamic' => ['active' => true],
            ]
        );

        $this->add_control(
            'position',
            [
                'label' => esc_html__('Icon Position', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'top',
                'options' => [
                    'left' => esc_html__('Left', 'hello-figma'),
                    'top' => esc_html__('Top', 'hello-figma'),
                    'right' => esc_html__('Right', 'hello-figma'),
                ],
                'prefix_class' => 'figma-icon-box-',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_icon_style',
            [
                'label' => esc_html__('Icon', 'hello-figma'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'icon_color',
            [
                'label' => esc_html__('Color', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .figma-icon-box-icon' => 'color: {{VALUE}}; fill: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'icon_size',
            [
                'label' => esc_html__('Size', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => ['min' => 10, 'max' => 200],
                ],
                'selectors' => [
                    '{{WRAPPER}} .figma-icon-box-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .figma-icon-box-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'icon_padding',
            [
                'label' => esc_html__('Padding', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .figma-icon-box-icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_content_style',
            [
                'label' => esc_html__('Content', 'hello-figma'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => esc_html__('Title Color', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .figma-icon-box-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .figma-icon-box-title',
            ]
        );

        $this->add_control(
            'description_color',
            [
                'label' => esc_html__('Description Color', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .figma-icon-box-description' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'description_typography',
                'selector' => '{{WRAPPER}} .figma-icon-box-description',
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        ?>
        <div class="figma-icon-box-wrapper">
            <div class="figma-icon-box-icon">
                <?php \Elementor\Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']); ?>
            </div>
            <div class="figma-icon-box-content">
                <?php if (!empty($settings['title'])) : ?>
                    <h3 class="figma-icon-box-title"><?php echo esc_html($settings['title']); ?></h3>
                <?php endif; ?>
                <?php if (!empty($settings['description'])) : ?>
                    <p class="figma-icon-box-description"><?php echo esc_html($settings['description']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    protected function content_template(): void
    {
        ?>
        <#
        var iconHTML = elementor.helpers.renderIcon( view, settings.icon, {}, 'i' , 'object' );
        #>
        <div class="figma-icon-box-wrapper">
            <div class="figma-icon-box-icon">
                <# if ( iconHTML && iconHTML.rendered ) { #>
                    {{{ iconHTML.value }}}
                <# } #>
            </div>
            <div class="figma-icon-box-content">
                <# if ( settings.title ) { #>
                    <h3 class="figma-icon-box-title">{{{ settings.title }}}</h3>
                <# } #>
                <# if ( settings.description ) { #>
                    <p class="figma-icon-box-description">{{{ settings.description }}}</p>
                <# } #>
            </div>
        </div>
        <?php
    }
}
