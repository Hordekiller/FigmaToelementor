<?php

declare(strict_types=1);

namespace HelloFigma\Widgets;

defined('ABSPATH') || exit;

class Figma_Heading extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'figma_heading';
    }

    public function get_title(): string
    {
        return esc_html__('Figma Heading', 'hello-figma');
    }

    public function get_icon(): string
    {
        return 'eicon-heading';
    }

    public function get_categories(): array
    {
        return ['figma-category'];
    }

    public function get_keywords(): array
    {
        return ['figma', 'heading', 'title', 'text'];
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
            'title',
            [
                'label' => esc_html__('Title', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => esc_html__('Heading Text', 'hello-figma'),
                'dynamic' => ['active' => true],
            ]
        );

        $this->add_control(
            'header_size',
            [
                'label' => esc_html__('HTML Tag', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'h2',
                'options' => [
                    'h1' => 'H1',
                    'h2' => 'H2',
                    'h3' => 'H3',
                    'h4' => 'H4',
                    'h5' => 'H5',
                    'h6' => 'H6',
                    'div' => 'div',
                    'span' => 'span',
                    'p' => 'p',
                ],
            ]
        );

        $this->add_control(
            'align',
            [
                'label' => esc_html__('Alignment', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__('Left', 'hello-figma'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => esc_html__('Center', 'hello-figma'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => esc_html__('Right', 'hello-figma'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .figma-heading' => 'text-align: {{VALUE}};',
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
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'typography',
                'selector' => '{{WRAPPER}} .figma-heading',
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label' => esc_html__('Text Color', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .figma-heading' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Text_Shadow::get_type(),
            [
                'name' => 'text_shadow',
                'selector' => '{{WRAPPER}} .figma-heading',
            ]
        );

        $this->add_control(
            'blend_mode',
            [
                'label' => esc_html__('Blend Mode', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    '' => esc_html__('Normal', 'hello-figma'),
                    'multiply' => 'Multiply',
                    'screen' => 'Screen',
                    'overlay' => 'Overlay',
                    'darken' => 'Darken',
                    'lighten' => 'Lighten',
                ],
                'selectors' => [
                    '{{WRAPPER}} .figma-heading' => 'mix-blend-mode: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        if (empty($settings['title'])) {
            return;
        }

        $this->add_render_attribute('heading', 'class', 'figma-heading');
        ?>
        <<?php echo esc_attr($settings['header_size']); ?> <?php $this->print_render_attribute_string('heading'); ?>>
            <?php echo wp_kses_post($settings['title']); ?>
        </<?php echo esc_attr($settings['header_size']); ?>>
        <?php
    }

    protected function content_template(): void
    {
        ?>
        <#
        if ('' === settings.title) {
            return;
        }
        #>
        <{{ settings.header_size }} class="figma-heading">
            {{{ settings.title }}}
        </{{ settings.header_size }}>
        <?php
    }
}
