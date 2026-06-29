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

    public function parse_stat_value(string $text): ?string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        // Detect ambiguous EU format: multiple dot-grouped triplets (12.345.678)
        // or a trailing comma-decimal (1.234,56) — return null rather than
        // producing silently wrong values.
        if (preg_match('/\b\d{1,3}\.\d{3}\.\d/', $trimmed) || preg_match('/[.,]\d{2}(?:\s|$)/', $trimmed)) {
            return null;
        }

        // Strip leading negative sign (hyphen or unicode minus).
        $negative = 1;
        $search = $trimmed;
        if (preg_match('/^(?:−|-)\s*(.+)$/', $trimmed, $neg_m)) {
            $negative = -1;
            $search = $neg_m[1];
        }

        if (!preg_match('/(?:^|\\s)([$€£])?\\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\\.[0-9]+)?|[0-9]+(?:\\.[0-9]+)?)([kKmMbB%]?)(?=(?:\\s|$|[^A-Za-z0-9]))/', $search, $matches)) {
            return null;
        }

        $number = str_replace(',', '', $matches[2]);
        $suffix = $matches[3];

        if ($suffix === '') {
            return (string) ((float) $number * $negative);
        }

        return ($negative < 0 ? '-' : '') . $number . $suffix;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>|null
     */
    public function try_build_stats(array $node): ?array
    {
        $children = $node['children'] ?? [];
        if (count($children) < 1) {
            return null;
        }

        $items = [];
        foreach ($children as $child) {
            $texts = $this->style_extractor->find_text_nodes_in_subtree($child, 1);
            if (empty($texts)) {
                continue;
            }

            $stat_text = $texts[0]['characters'] ?? '';
            $parsed = $this->parse_stat_value((string) $stat_text);
            if ($parsed === null) {
                continue;
            }

            $items[] = [
                'number' => $parsed,
                'label' => trim((string) preg_replace('/^[^A-Za-z]+/', '', $stat_text)) ?: ($child['name'] ?? ''),
            ];
        }

        if (empty($items)) {
            return null;
        }

        $settings = new \stdClass();
        $settings->editor = $items;

        return [
            'id' => ($this->id_generator)(),
            'elType' => 'widget',
            'widgetType' => 'counter',
            'isInner' => false,
            'settings' => $settings,
            'elements' => [],
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @param string $component_type
     * @return array<string, mixed>|null
     */
    public function try_build_social_icons(array $node, string $component_type): ?array
    {
        $children = $node['children'] ?? [];
        if (count($children) < 2) {
            return null;
        }

        $platform_map = [
            'instagram' => ['value' => 'fab fa-instagram', 'library' => 'fa-brands'],
            'facebook' => ['value' => 'fab fa-facebook-f', 'library' => 'fa-brands'],
            'twitter' => ['value' => 'fab fa-x-twitter', 'library' => 'fa-brands'],
            'x' => ['value' => 'fab fa-x-twitter', 'library' => 'fa-brands'],
            'linkedin' => ['value' => 'fab fa-linkedin', 'library' => 'fa-brands'],
            'youtube' => ['value' => 'fab fa-youtube', 'library' => 'fa-brands'],
            'tiktok' => ['value' => 'fab fa-tiktok', 'library' => 'fa-brands'],
            'pinterest' => ['value' => 'fab fa-pinterest', 'library' => 'fa-brands'],
            'whatsapp' => ['value' => 'fab fa-whatsapp', 'library' => 'fa-brands'],
            'telegram' => ['value' => 'fab fa-telegram', 'library' => 'fa-brands'],
            'github' => ['value' => 'fab fa-github', 'library' => 'fa-brands'],
        ];

        $items = [];
        $icon_like_count = 0;
        foreach ($children as $child) {
            $resolved = $this->style_extractor->find_image_in_subtree($child, 1);
            if ($resolved !== null) {
                continue;
            }

            $type = $child['type'] ?? '';
            if (!in_array($type, ['VECTOR', 'BOOLEAN_OPERATION', 'STAR', 'POLYGON', 'RECTANGLE', 'ELLIPSE'], true)) {
                continue;
            }

            $icon_like_count++;
            $name = mb_strtolower((string) ($child['name'] ?? ''));
            $icon = ['value' => 'fab fa-link', 'library' => 'fa-solid'];
            $matched = false;
            foreach ($platform_map as $needle => $mapped_icon) {
                $matches = match (true) {
                    $needle === 'x' => $name === 'x' || preg_match('/\bx\b/i', $name),
                    $needle === 'twitter' => str_contains($name, 'twitter'),
                    default => str_contains($name, $needle),
                };
                if ($matches) {
                    $icon = $mapped_icon;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                Logger::log('WARNING', 'WidgetConverters', 'Unmatched social icon platform name', [
                    'component_type' => $component_type,
                    'name' => $child['name'] ?? '',
                    'node_id' => $child['id'] ?? '',
                ]);
            }

            $items[] = [
                'social_icon' => $icon,
                'link' => [
                    'url' => '#',
                    'is_external' => true,
                    'nofollow' => false,
                ],
            ];
        }

        if ($icon_like_count < 2 || empty($items)) {
            return null;
        }

        $settings = new \stdClass();
        $settings->social_icon_list = $items;

        return [
            'id' => ($this->id_generator)(),
            'elType' => 'widget',
            'widgetType' => 'social-icons',
            'isInner' => false,
            'settings' => $settings,
            'elements' => [],
        ];
    }
}
