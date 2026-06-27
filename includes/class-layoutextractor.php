<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class LayoutExtractor
{
    private Positioning $positioning;

    public function __construct(Positioning $positioning)
    {
        $this->positioning = $positioning;
    }

    public function extract_container_layout(array $node, \stdClass $settings): void
    {
        $layout = $node['layoutMode'] ?? null;
        if ($layout) {
            $settings->flex_direction = $layout === 'HORIZONTAL' ? 'row' : 'column';
            $settings->flex_wrap = 'wrap';
        }

        $pL = $node['paddingLeft'] ?? $node['padding'] ?? null;
        $pR = $node['paddingRight'] ?? $node['padding'] ?? null;
        $pT = $node['paddingTop'] ?? $node['padding'] ?? null;
        $pB = $node['paddingBottom'] ?? $node['padding'] ?? null;

        if ($pT !== null && $pR !== null && $pB !== null && $pL !== null) {
            $settings->padding = (object) [
                'unit' => 'px',
                'top' => (int) $pT, 'right' => (int) $pR,
                'bottom' => (int) $pB, 'left' => (int) $pL,
                'isLinked' => ($pT === $pR && $pR === $pB && $pB === $pL),
            ];
        }

        $gap = $node['itemSpacing'] ?? null;
        if ($gap !== null) {
            $is_row = ($layout === 'HORIZONTAL');
            if ($is_row) {
                $settings->column_gap = (object) ['unit' => 'px', 'size' => (int) $gap];
            } else {
                $settings->row_gap = (object) ['unit' => 'px', 'size' => (int) $gap];
            }
        }

        $settings->flex_justify_content = $this->positioning->map_align($node['primaryAxisAlignItems'] ?? 'MIN');
        $settings->flex_align_items = $this->positioning->map_align($node['counterAxisAlignItems'] ?? 'MIN');
    }

    public function extract_container_dimensions(array $node, \stdClass $settings): void
    {
        $bBox = $node['absoluteBoundingBox'] ?? null;
        if ($bBox === null) {
            return;
        }

        $width = (int) ($bBox['width'] ?? 0);
        $height = (int) ($bBox['height'] ?? 0);

        if ($width > 0) {
            $settings->width = (object) ['unit' => 'px', 'size' => $width];
        }
        if ($height > 0) {
            $settings->min_height = (object) ['unit' => 'px', 'size' => $height];
        }
    }

    public function extract_parent_sizing_mode(array $node, \stdClass $settings): void
    {
        $layout_mode = $node['layoutMode'] ?? null;
        if ($layout_mode === null || $layout_mode === 'NONE') {
            return;
        }

        $is_row = ($layout_mode === 'HORIZONTAL');
        $primary_mode = $node['primaryAxisSizingMode'] ?? null;
        $counter_mode = $node['counterAxisSizingMode'] ?? null;

        if ($primary_mode === 'AUTO') {
            if ($is_row) {
                unset($settings->width);
            } else {
                unset($settings->min_height);
            }
        }

        if ($counter_mode === 'AUTO') {
            if ($is_row) {
                unset($settings->min_height);
            } else {
                unset($settings->width);
            }
        }
    }

    public function extract_flex_child_sizing(array $node, \stdClass $settings, string $parent_layout_mode): void
    {
        $is_row = ($parent_layout_mode === 'HORIZONTAL');

        $layout_h = $node['layoutSizingHorizontal'] ?? null;
        $layout_v = $node['layoutSizingVertical'] ?? null;

        if ($layout_h === null && $layout_v === null) {
            $layout_grow = $node['layoutGrow'] ?? 0;
            $layout_align = $node['layoutAlign'] ?? null;

            if ($is_row) {
                $layout_h = ($layout_grow === 1) ? 'FILL' : null;
                $layout_v = ($layout_align === 'STRETCH') ? 'FILL' : null;
            } else {
                $layout_v = ($layout_grow === 1) ? 'FILL' : null;
                $layout_h = ($layout_align === 'STRETCH') ? 'FILL' : null;
            }

            if ($layout_h === null) {
                $layout_h = 'FIXED';
            }
            if ($layout_v === null) {
                $layout_v = 'FIXED';
            }
        }

        $primary_sizing = $is_row ? $layout_h : $layout_v;
        $counter_sizing = $is_row ? $layout_v : $layout_h;

        if ($primary_sizing === 'FILL') {
            $settings->_flex_size = 'grow';
            if ($is_row) {
                unset($settings->width);
            } else {
                unset($settings->min_height);
            }
        } elseif ($primary_sizing === 'FIXED') {
            $settings->_flex_size = 'none';
        } elseif ($primary_sizing === 'HUG') {
            if ($is_row) {
                unset($settings->width);
            } else {
                unset($settings->min_height);
            }
        }

        if ($counter_sizing === 'FILL') {
            $settings->_flex_align_self = 'stretch';
            if ($is_row) {
                unset($settings->min_height);
            } else {
                unset($settings->width);
            }
        } elseif ($counter_sizing === 'FIXED') {
            $settings->_flex_align_self = '';
        } elseif ($counter_sizing === 'HUG') {
            $settings->_flex_align_self = '';
            if ($is_row) {
                unset($settings->min_height);
            } else {
                unset($settings->width);
            }
        }
    }
}
