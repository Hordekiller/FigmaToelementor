<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class WidgetConverters
{
    private StyleExtractor $style_extractor;
    private \Closure $id_generator;

    public function __construct(StyleExtractor $style_extractor, \Closure $id_generator)
    {
        $this->style_extractor = $style_extractor;
        $this->id_generator = $id_generator;
    }

    public function try_build_carousel(array $node, string $component_type): ?array
    {
        $children = $node['children'] ?? [];
        $total = count($children);

        if ($total < 2) {
            return null;
        }

        $image_node_ids = [];
        foreach ($children as $child) {
            $img_node = $this->style_extractor->find_image_in_subtree($child, 1);
            if ($img_node !== null) {
                $image_node_ids[] = $img_node['id'] ?? '';
            }
        }

        $image_count = count($image_node_ids);
        $ratio = $image_count / $total;

        if ($ratio < 0.7) {
            return null;
        }

        $slides = [];
        foreach ($children as $child) {
            $img_node = $this->style_extractor->find_image_in_subtree($child, 1);
            if ($img_node === null) {
                continue;
            }
            $img_settings = new \stdClass();
            $this->style_extractor->extract_image_settings($img_node, $img_settings);
            if (isset($img_settings->image)) {
                $slides[] = $img_settings->image;
            }
        }

        if (empty($slides)) {
            return null;
        }

        $carousel_settings = new \stdClass();
        $carousel_settings->carousel = $slides;
        $carousel_settings->slides_to_show = '3';
        $carousel_settings->navigation = 'both';
        $carousel_settings->_css_classes = 'figma-detected-' . $component_type;

        return [
            'id' => ($this->id_generator)(),
            'elType' => 'widget',
            'widgetType' => 'image-carousel',
            'isInner' => false,
            'settings' => $carousel_settings,
            'elements' => [],
        ];
    }

    public function try_build_accordion(array $node): ?array
    {
        $children = $node['children'] ?? [];
        $total = count($children);

        if ($total < 1) {
            return null;
        }

        $matched_items = [];
        foreach ($children as $child) {
            $figma_type = $child['type'] ?? '';
            if (!in_array($figma_type, ['FRAME', 'GROUP'], true)) {
                continue;
            }
            $texts = $this->style_extractor->find_text_nodes_in_subtree($child, 2);
            if (count($texts) >= 2) {
                $matched_items[] = $child;
            }
        }

        if (count($matched_items) / $total < 0.7) {
            return null;
        }

        $accordion_settings = new \stdClass();
        $accordion_settings->tabs = [];

        foreach ($matched_items as $item_node) {
            $texts = $this->style_extractor->find_text_nodes_in_subtree($item_node, 2);
            if (count($texts) < 2) {
                continue;
            }
            $accordion_settings->tabs[] = [
                'tab_title' => $texts[0]['characters'] ?? '',
                'tab_content' => '<p>' . ($texts[1]['characters'] ?? '') . '</p>',
            ];
        }

        if (empty($accordion_settings->tabs)) {
            return null;
        }

        $accordion_settings->_css_classes = 'figma-detected-faq';

        return [
            'id' => ($this->id_generator)(),
            'elType' => 'widget',
            'widgetType' => 'accordion',
            'isInner' => false,
            'settings' => $accordion_settings,
            'elements' => [],
        ];
    }

    public function try_build_gallery(array $node): ?array
    {
        $children = $node['children'] ?? [];
        $total = count($children);

        if ($total < 2) {
            return null;
        }

        $matched_images = [];
        foreach ($children as $child) {
            $img_node = $this->style_extractor->find_image_in_subtree($child, 1);
            if ($img_node !== null) {
                $matched_images[] = $child;
            }
        }

        if (count($matched_images) / $total < 0.7) {
            return null;
        }

        $gallery_settings = new \stdClass();
        $gallery_settings->wp_gallery = [];

        foreach ($matched_images as $child) {
            $img_node = $this->style_extractor->find_image_in_subtree($child, 1);
            if ($img_node === null) {
                continue;
            }
            $node_id = $img_node['id'] ?? '';
            if ($node_id) {
                $gallery_settings->wp_gallery[] = [
                    'id' => 0,
                    'url' => "figma-image://{$node_id}",
                ];
            }
        }

        if (empty($gallery_settings->wp_gallery)) {
            return null;
        }

        $gallery_settings->_css_classes = 'figma-detected-gallery';
        $gallery_settings->gallery_columns = 4;
        $gallery_settings->link_to = 'file';
        $gallery_settings->open_lightbox = 'yes';

        return [
            'id' => ($this->id_generator)(),
            'elType' => 'widget',
            'widgetType' => 'image-gallery',
            'isInner' => false,
            'settings' => $gallery_settings,
            'elements' => [],
        ];
    }
}
