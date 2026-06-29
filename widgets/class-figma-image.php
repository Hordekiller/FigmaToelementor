<?php

declare(strict_types=1);

namespace HelloFigma\Widgets;

defined('ABSPATH') || exit;

class Figma_Image extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'figma_image';
    }

    public function get_title(): string
    {
        return esc_html__('Figma Image', 'hello-figma');
    }

    public function get_icon(): string
    {
        return 'eicon-image';
    }

    public function get_categories(): array
    {
        return ['figma-category'];
    }

    public function get_keywords(): array
    {
        return ['figma', 'image', 'photo'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section(
            'section_image',
            [
                'label' => esc_html__('Image', 'hello-figma'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'image',
            [
                'label' => esc_html__('Choose Image', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::MEDIA,
                'dynamic' => ['active' => true],
                'default' => [
                    'url' => \Elementor\Utils::get_placeholder_image_src(),
                ],
            ]
        );

        $this->add_control(
            'caption',
            [
                'label' => esc_html__('Caption', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'dynamic' => ['active' => true],
            ]
        );

        $this->add_control(
            'link_to',
            [
                'label' => esc_html__('Link To', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'none',
                'options' => [
                    'none' => esc_html__('None', 'hello-figma'),
                    'file' => esc_html__('Media File', 'hello-figma'),
                    'custom' => esc_html__('Custom URL', 'hello-figma'),
                ],
            ]
        );

        $this->add_control(
            'link',
            [
                'label' => esc_html__('Link', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::URL,
                'dynamic' => ['active' => true],
                'condition' => ['link_to' => 'custom'],
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

        $this->add_control(
            'width',
            [
                'label' => esc_html__('Width', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 2000],
                    '%' => ['min' => 0, 'max' => 100],
                ],
                'selectors' => [
                    '{{WRAPPER}} .figma-image img' => 'width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'height',
            [
                'label' => esc_html__('Height', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => ['min' => 0, 'max' => 2000],
                ],
                'selectors' => [
                    '{{WRAPPER}} .figma-image img' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'object_fit',
            [
                'label' => esc_html__('Object Fit', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'cover',
                'options' => [
                    'cover' => esc_html__('Cover', 'hello-figma'),
                    'contain' => esc_html__('Contain', 'hello-figma'),
                    'fill' => esc_html__('Fill', 'hello-figma'),
                    'none' => esc_html__('None', 'hello-figma'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .figma-image img' => 'object-fit: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'border',
                'selector' => '{{WRAPPER}} .figma-image img',
            ]
        );

        $this->add_control(
            'border_radius',
            [
                'label' => esc_html__('Border Radius', 'hello-figma'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .figma-image img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        if (empty($settings['image']['url'])) {
            return;
        }

        $this->add_render_attribute('wrapper', 'class', 'figma-image');
        $this->add_render_attribute('image', 'src', esc_url($settings['image']['url']));
        $this->add_render_attribute('image', 'alt', $settings['caption'] ?? '');

        if (!empty($settings['image']['id'])) {
            $this->add_render_attribute('image', 'srcset', wp_get_attachment_image_srcset($settings['image']['id']));
        }

        $link_open = '';
        $link_close = '';

        if ($settings['link_to'] === 'file') {
            $link_open = '<a href="' . esc_url($settings['image']['url']) . '">';
            $link_close = '</a>';
        } elseif ($settings['link_to'] === 'custom' && !empty($settings['link']['url'])) {
            $this->add_link_attributes('custom_link', $settings['link']);
            $link_open = '<a ' . $this->get_render_attribute_string('custom_link') . '>';
            $link_close = '</a>';
        }
        ?>
        <div <?php $this->print_render_attribute_string('wrapper'); ?>>
            <?php echo $link_open; ?>
            <img <?php $this->print_render_attribute_string('image'); ?>>
            <?php echo $link_close; ?>
        </div>
        <?php
    }

    protected function content_template(): void
    {
        ?>
        <div class="figma-image">
            <# if ( settings.image.url ) { #>
                <# if ( 'file' === settings.link_to ) { #>
                    <a href="{{ settings.image.url }}">
                <# } else if ( 'custom' === settings.link_to && settings.link.url ) { #>
                    <a href="{{ settings.link.url }}">
                <# } #>
                <img src="{{ settings.image.url }}" alt="{{ settings.caption }}">
                <# if ( 'file' === settings.link_to || ( 'custom' === settings.link_to && settings.link.url ) ) { #>
                    </a>
                <# } #>
            <# } #>
        </div>
        <?php
    }
}
