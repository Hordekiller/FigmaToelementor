<?php

declare(strict_types=1);

namespace HelloFigma\Widgets;

defined('ABSPATH') || exit;

class Figma_Button extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'figma_button';
    }

    public function get_title(): string
    {
        return esc_html__('Figma Button', 'hello-figma');
    }

    public function get_icon(): string
    {
        return 'eicon-button';
    }

    public function get_categories(): array
    {
        return ['figma-category'];
    }

    public function get_keywords(): array
    {
        return ['figma', 'button', 'cta'];
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
            'text',
            [
                'label' => esc_html__('Text', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__('Click Here', 'hello-figma'),
                'dynamic' => ['active' => true],
            ]
        );

        $this->add_control(
            'link',
            [
                'label' => esc_html__('Link', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::URL,
                'placeholder' => 'https://example.com',
                'dynamic' => ['active' => true],
            ]
        );

        $this->add_control(
            'icon',
            [
                'label' => esc_html__('Icon', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::ICONS,
            ]
        );

        $this->add_control(
            'icon_position',
            [
                'label' => esc_html__('Icon Position', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'before',
                'options' => [
                    'before' => esc_html__('Before', 'hello-figma'),
                    'after' => esc_html__('After', 'hello-figma'),
                ],
                'condition' => ['icon[value]!' => ''],
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
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'typography',
                'selector' => '{{WRAPPER}} .figma-button',
            ]
        );

        $this->start_controls_tabs('button_tabs');

        $this->start_controls_tab(
            'tab_normal',
            ['label' => esc_html__('Normal', 'hello-figma')]
        );

        $this->add_control(
            'text_color',
            [
                'label' => esc_html__('Text Color', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .figma-button' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'background_color',
            [
                'label' => esc_html__('Background Color', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .figma-button' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'border',
                'selector' => '{{WRAPPER}} .figma-button',
            ]
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'tab_hover',
            ['label' => esc_html__('Hover', 'hello-figma')]
        );

        $this->add_control(
            'hover_text_color',
            [
                'label' => esc_html__('Text Color', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .figma-button:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'hover_background_color',
            [
                'label' => esc_html__('Background Color', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .figma-button:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_control(
            'border_radius',
            [
                'label' => esc_html__('Border Radius', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .figma-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'padding',
            [
                'label' => esc_html__('Padding', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .figma-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $this->add_link_attributes('button_link', $settings['link'] ?? []);
        $this->add_render_attribute('button_link', 'class', 'figma-button');
        ?>
        <a <?php $this->print_render_attribute_string('button_link'); ?>>
            <?php if (!empty($settings['icon']['value']) && $settings['icon_position'] === 'before') : ?>
                <span class="figma-button-icon"><?php \Elementor\Icons_Manager::render_icon($settings['icon']); ?></span>
            <?php endif; ?>
            <span class="figma-button-text"><?php echo esc_html($settings['text']); ?></span>
            <?php if (!empty($settings['icon']['value']) && $settings['icon_position'] === 'after') : ?>
                <span class="figma-button-icon"><?php \Elementor\Icons_Manager::render_icon($settings['icon']); ?></span>
            <?php endif; ?>
        </a>
        <?php
    }

    protected function content_template(): void
    {
        ?>
        <#
        var iconHTML = elementor.helpers.renderIcon( view, settings.icon, {}, 'i' , 'object' );
        #>
        <a class="figma-button" href="{{ settings.link.url }}">
            <# if ( iconHTML && iconHTML.rendered && settings.icon_position === 'before' ) { #>
                <span class="figma-button-icon">{{{ iconHTML.value }}}</span>
            <# } #>
            <span class="figma-button-text">{{{ settings.text }}}</span>
            <# if ( iconHTML && iconHTML.rendered && settings.icon_position === 'after' ) { #>
                <span class="figma-button-icon">{{{ iconHTML.value }}}</span>
            <# } #>
        </a>
        <?php
    }
}
