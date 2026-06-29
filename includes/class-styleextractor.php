<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class StyleExtractor
{
    public function get_visible_fill(array $node): ?array
    {
        foreach ($node['fills'] ?? [] as $fill) {
            if (($fill['visible'] ?? true) !== false && ($fill['opacity'] ?? 1) > 0) {
                return $fill;
            }
        }
        return null;
    }

    public function rgba_to_hex(array $rgba): string
    {
        $r = isset($rgba['r']) ? (int) round($rgba['r'] * 255) : 0;
        $g = isset($rgba['g']) ? (int) round($rgba['g'] * 255) : 0;
        $b = isset($rgba['b']) ? (int) round($rgba['b'] * 255) : 0;
        $a = $rgba['a'] ?? 1;

        if ($a < 1) {
            return sprintf('#%02x%02x%02x%02x', $r, $g, $b, (int) round($a * 255));
        }
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    public function detect_heading_level(array $style): string
    {
        $size = $style['fontSize'] ?? 16;
        return match (true) {
            $size >= 48 => 'h1',
            $size >= 36 => 'h2',
            $size >= 28 => 'h3',
            $size >= 20 => 'h4',
            $size >= 16 => 'h5',
            default => 'h6',
        };
    }

    public function extract_background(array $node, \stdClass $settings): void
    {
        $fill = $this->get_visible_fill($node);
        if ($fill === null) {
            return;
        }

        $type = $fill['type'] ?? 'SOLID';
        $fill_opacity = $fill['opacity'] ?? 1;

        switch ($type) {
            case 'SOLID':
                $color = $fill['color'] ?? [];
                if (empty($color)) {
                    break;
                }
                if (($color['a'] ?? 1) <= 0) {
                    break;
                }
                $settings->background_background = 'classic';
                $settings->background_color = $this->rgba_to_hex($color);
                if ($fill_opacity < 1) {
                    $settings->background_opacity = $fill_opacity;
                }
                break;

            case 'GRADIENT_LINEAR':
            case 'GRADIENT_RADIAL':
            case 'GRADIENT_ANGULAR':
            case 'GRADIENT_DIAMOND':
                $settings->background_background = 'gradient';
                $settings->background_gradient_type = $type === 'GRADIENT_RADIAL' ? 'radial' : 'linear';
                $stops = $fill['gradientStops'] ?? [];
                $total = count($stops);
                if ($total > 0) {
                    for ($i = 0; $i < $total; $i++) {
                        $stop = $stops[$i];
                        $position = $stop['position'] ?? ($total > 1 ? $i / ($total - 1) : 0);
                        $pct = (int) round($position * 100);
                        $settings->{"background_gradient_color_{$i}"} = $this->rgba_to_hex($stop['color'] ?? []);
                        $settings->{"background_gradient_color_before_{$i}"} = "{$pct}%";
                    }
                }
                if (!empty($fill['gradientHandlePositions']) && count($fill['gradientHandlePositions']) >= 2) {
                    $start = $fill['gradientHandlePositions'][0];
                    $end = $fill['gradientHandlePositions'][1];
                    $dx = ($end['x'] ?? 0) - ($start['x'] ?? 0);
                    $dy = ($end['y'] ?? 0) - ($start['y'] ?? 0);
                    $angle = rad2deg(atan2($dx, -$dy));
                    if ($angle < 0) {
                        $angle += 360;
                    }
                    $settings->background_gradient_angle = (int) round($angle);
                }
                break;

            case 'IMAGE':
                $node_id = $node['id'] ?? '';
                if ($node_id) {
                    $settings->background_background = 'classic';
                    $settings->background_image = (object) [
                        'url' => "figma-image://{$node_id}",
                        'id' => 0,
                    ];
                    $settings->background_size = 'cover';
                    $settings->background_position = 'center center';
                    $settings->background_repeat = 'no-repeat';
                }
                break;

            default:
                // Unknown paint type (e.g. a future GRADIENT_* variant, EMOJI, VIDEO).
                // Surface it instead of silently dropping the background.
                Logger::log('WARNING', 'StyleExtractor', 'Unhandled fill type — background skipped', [
                    'type' => $type,
                    'node_id' => $node['id'] ?? '',
                    'name' => $node['name'] ?? '',
                ]);
                break;
        }
    }

    public function extract_border(array $node, \stdClass $settings): void
    {
        $stroke = null;
        foreach ($node['strokes'] ?? [] as $s) {
            if (($s['visible'] ?? true) !== false) {
                $stroke = $s;
                break;
            }
        }
        if ($stroke === null) {
            return;
        }

        $color = $stroke['color'] ?? [];
        if (empty($color) || ($color['a'] ?? 1) <= 0) {
            return;
        }

        $settings->border_border = 'solid';
        $settings->border_color = $this->rgba_to_hex($color);

        $stroke_weight = $node['strokeWeight'] ?? null;
        $w = 1;
        $is_linked = true;
        $top = $w;
        $right = $w;
        $bottom = $w;
        $left = $w;

        if (is_numeric($stroke_weight)) {
            $w = max(1, (int) $stroke_weight);
            $top = $right = $bottom = $left = $w;
        } elseif (is_array($stroke_weight)) {
            // Figma sends per-side weight as individual fields on the node,
            // not as a nested object. This branch handles the (rare) nested form.
            $sw = $stroke_weight;
            $top = max(1, (int) ($sw['strokeTopWeight'] ?? $sw['top'] ?? $sw['all'] ?? $node['strokeTopWeight'] ?? 1));
            $right = max(1, (int) ($sw['strokeRightWeight'] ?? $sw['right'] ?? $sw['all'] ?? $node['strokeRightWeight'] ?? $top));
            $bottom = max(1, (int) ($sw['strokeBottomWeight'] ?? $sw['bottom'] ?? $sw['all'] ?? $node['strokeBottomWeight'] ?? $top));
            $left = max(1, (int) ($sw['strokeLeftWeight'] ?? $sw['left'] ?? $sw['all'] ?? $node['strokeLeftWeight'] ?? $right));
            $is_linked = ($top === $right && $right === $bottom && $bottom === $left);
        } elseif (isset($node['strokeTopWeight']) || isset($node['strokeRightWeight'])) {
            $top = max(1, (int) ($node['strokeTopWeight'] ?? $node['strokeWeight'] ?? $w));
            $right = max(1, (int) ($node['strokeRightWeight'] ?? $node['strokeWeight'] ?? $top));
            $bottom = max(1, (int) ($node['strokeBottomWeight'] ?? $node['strokeWeight'] ?? $top));
            $left = max(1, (int) ($node['strokeLeftWeight'] ?? $node['strokeWeight'] ?? $right));
            $is_linked = ($top === $right && $right === $bottom && $bottom === $left);
        }

        $settings->border_width = (object) [
            'unit' => 'px',
            'top' => $top, 'right' => $right, 'bottom' => $bottom, 'left' => $left,
            'isLinked' => $is_linked,
        ];
    }

    public function extract_border_radius(array $node, \stdClass $settings): void
    {
        $r = $node['cornerRadius'] ?? $node['rectangleCornerRadii'] ?? null;
        if ($r === null) {
            return;
        }

        if (is_numeric($r)) {
            $settings->border_radius = (object) [
                'unit' => 'px', 'top' => (int) $r, 'right' => (int) $r,
                'bottom' => (int) $r, 'left' => (int) $r, 'isLinked' => true,
            ];
        } elseif (is_array($r) && count($r) === 4) {
            $settings->border_radius = (object) [
                'unit' => 'px',
                'top' => (int) ($r[0] ?? 0), 'right' => (int) ($r[1] ?? 0),
                'bottom' => (int) ($r[2] ?? 0), 'left' => (int) ($r[3] ?? 0),
                'isLinked' => false,
            ];
        }
    }

    public function extract_opacity(array $node, \stdClass $settings): void
    {
        $o = $node['opacity'] ?? null;
        if ($o !== null && $o < 1) {
            $settings->opacity = $o;
        }
    }

    public function extract_shadow(array $node, \stdClass $settings): void
    {
        foreach ($node['effects'] ?? [] as $effect) {
            if (in_array($effect['type'] ?? '', ['DROP_SHADOW', 'INNER_SHADOW'], true)) {
                $settings->box_shadow_box_shadow_type = 'yes';
                $settings->box_shadow_box_shadow = (object) [
                    'horizontal' => $effect['offset']['x'] ?? 0,
                    'vertical'   => $effect['offset']['y'] ?? 0,
                    'blur'       => $effect['radius'] ?? 0,
                    'spread'     => $effect['spread'] ?? 0,
                    'color'      => $this->rgba_to_hex($effect['color'] ?? []),
                ];
            }
        }
    }

    public function extract_heading_settings(array $node, \stdClass $settings): void
    {
        $characters = $node['characters'] ?? '';
        if (empty($characters)) {
            $characters = $node['name'] ?? __('Heading', 'hello-figma');
        }
        $settings->title = $characters;

        $style = $node['style'] ?? [];
        $settings->header_size = $this->detect_heading_level($style);

        $has_typography = false;
        $typo = [];

        $font_family = $style['fontFamily'] ?? ($style['fontName']['family'] ?? null);
        if (!empty($font_family)) {
            $typo['font_family'] = $font_family;
            $has_typography = true;
        }
        if (!empty($style['fontSize'])) {
            $typo['font_size'] = (object) [
                'unit' => 'px',
                'size' => (float) $style['fontSize'],
            ];
            $has_typography = true;
        }
        $font_weight = $style['fontWeight'] ?? ($style['fontName']['style'] ?? null);
        if (!empty($font_weight)) {
            $typo['font_weight'] = (string) $font_weight;
            $has_typography = true;
        }
        if (isset($style['lineHeightPx']) && $style['lineHeightPx'] > 0) {
            $typo['line_height'] = (object) [
                'unit' => 'px',
                'size' => (float) $style['lineHeightPx'],
            ];
            $has_typography = true;
        } elseif (!empty($style['lineHeightPercent'])) {
            $typo['line_height'] = (object) [
                'unit' => '%',
                'size' => (float) $style['lineHeightPercent'] * 100,
            ];
            $has_typography = true;
        }
        if (!empty($style['letterSpacing'])) {
            $typo['letter_spacing'] = (object) [
                'unit' => 'px',
                'size' => (float) $style['letterSpacing'],
            ];
            $has_typography = true;
        }
        if (!empty($style['textTransform'])) {
            $map = [
                'UPPER' => 'uppercase',
                'LOWER' => 'lowercase',
                'TITLE' => 'capitalize',
            ];
            $typo['text_transform'] = $map[$style['textTransform']] ?? 'none';
            $has_typography = true;
        }
        if (!empty($style['textDecoration'])) {
            $map = [
                'STRIKETHROUGH' => 'line-through',
                'UNDERLINE' => 'underline',
            ];
            $typo['text_decoration'] = $map[$style['textDecoration']] ?? 'none';
            $has_typography = true;
        }

        if ($has_typography) {
            $typo['typography'] = 'custom';
            foreach ($typo as $key => $value) {
                $settings->{"typography_{$key}"} = $value;
            }
        }

        Logger::log('INFO', 'ElementorRenderer', 'Heading typography extracted', [
            'characters_length' => strlen($characters),
            'has_typography' => $has_typography,
            'typography_keys' => array_keys($typo),
        ]);

        $figma_align = $style['textAlignHorizontal'] ?? 'LEFT';
        $settings->align = strtolower($figma_align);

        $fill = $this->get_visible_fill($node);
        if ($fill !== null && ($fill['type'] ?? '') === 'SOLID') {
            $color = $fill['color'] ?? [];
            if (!empty($color) && ($color['a'] ?? 1) > 0) {
                $settings->text_color = $this->rgba_to_hex($color);
            }
        }
    }

    public function extract_button_settings(array $node, \stdClass $settings): void
    {
        $texts = $this->find_text_nodes_in_subtree($node, 1);
        if (!empty($texts)) {
            $settings->text = $texts[0]['characters'] ?? $node['name'];
        } else {
            $name_labels = [
                'button', 'btn', 'دکمه', 'click', 'submit',
                'ثبت', 'ارسال', 'buy', 'shop', 'خرید',
            ];
            $name_lower = mb_strtolower(trim($node['name'] ?? ''));
            if (in_array($name_lower, $name_labels, true)) {
                $settings->text = __('Button', 'hello-figma');
            } else {
                $settings->text = $node['name'] ?? __('Button', 'hello-figma');
            }
        }

        $settings->button_size = 'md';
        $settings->align = 'center';

        $fills = $node['fills'] ?? [];
        if (!empty($fills) && ($fills[0]['type'] ?? '') === 'SOLID') {
            $settings->button_background_color = $this->rgba_to_hex($fills[0]['color'] ?? []);
        }

        if (!empty($node['componentPropertyDefinitions'])) {
            $settings->hover_background_color = $settings->button_background_color ?? '#000000';
        }
    }

    public function extract_image_settings(array $node, \stdClass $settings): void
    {
        $settings->image_size = 'full';
        $settings->align = 'center';

        $node_id = $node['id'] ?? '';
        if ($node_id) {
            $settings->image = (object) [
                'url' => "figma-image://{$node_id}",
                'id' => 0,
            ];
        }
    }

    public function extract_icon_settings(array $node, \stdClass $settings): void
    {
        $settings->icon = 'fas fa-star';
        $fills = $node['fills'] ?? [];
        if (!empty($fills) && ($fills[0]['type'] ?? '') === 'SOLID') {
            $settings->primary_color = $this->rgba_to_hex($fills[0]['color'] ?? []);
        }
    }

    public function find_text_nodes_in_subtree(array $node, int $max_depth = 2): array
    {
        if ($max_depth < 0) {
            return [];
        }

        $texts = [];
        $figma_type = $node['type'] ?? '';
        if ($figma_type === 'TEXT') {
            $texts[] = $node;
        }

        if ($max_depth > 0) {
            foreach ($node['children'] ?? [] as $child) {
                $texts = array_merge($texts, $this->find_text_nodes_in_subtree($child, $max_depth - 1));
            }
        }

        return $texts;
    }

    public function find_image_in_subtree(array $node, int $max_depth = 1): ?array
    {
        $fill = $this->get_visible_fill($node);
        if ($fill !== null && ($fill['type'] ?? '') === 'IMAGE') {
            $figma_type = $node['type'] ?? '';
            if (in_array($figma_type, ['RECTANGLE', 'ELLIPSE'], true)) {
                return $node;
            }
        }

        if ($max_depth > 0) {
            foreach ($node['children'] ?? [] as $child) {
                $found = $this->find_image_in_subtree($child, $max_depth - 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
