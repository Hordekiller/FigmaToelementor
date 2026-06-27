<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class Positioning
{
    public function node_needs_absolute_positioning(array $child, ?string $parent_layout_mode): bool
    {
        if (isset($child['rotation']) && abs((float) $child['rotation']) > 0.01) {
            Logger::log('WARNING', 'Positioning', 'Skipping absolute positioning — rotated node', [
                'node_id' => $child['id'] ?? 'unknown',
                'name' => $child['name'] ?? 'unnamed',
                'rotation' => (float) $child['rotation'],
            ]);
            return false;
        }

        if ($parent_layout_mode === null) {
            return true;
        }
        return ($child['layoutPositioning'] ?? '') === 'ABSOLUTE';
    }

    public function compute_relative_position(array $child_node, array $parent_node): ?array
    {
        $child_bbox = $child_node['absoluteBoundingBox'] ?? null;
        $parent_bbox = $parent_node['absoluteBoundingBox'] ?? null;
        if ($child_bbox === null || $parent_bbox === null) {
            return null;
        }

        return [
            'x' => ($child_bbox['x'] ?? 0) - ($parent_bbox['x'] ?? 0),
            'y' => ($child_bbox['y'] ?? 0) - ($parent_bbox['y'] ?? 0),
        ];
    }

    public function apply_absolute_positioning(array &$element, array $child, array $parent): void
    {
        $rel = $this->compute_relative_position($child, $parent);
        if ($rel === null) {
            return;
        }

        $child_bbox = $child['absoluteBoundingBox'] ?? [];
        $settings = (array) $element['settings'];
        $el_type = $element['elType'] ?? '';

        if ($el_type === 'container') {
            $settings['position'] = 'absolute';
        } else {
            $settings['_position'] = 'absolute';
        }

        if ($rel['x'] >= 0) {
            $settings['_offset_orientation_h'] = 'start';
            $settings['_offset_x'] = (object) [
                'unit' => 'px',
                'size' => (int) round($rel['x']),
            ];
        } else {
            $settings['_offset_orientation_h'] = 'end';
            $settings['_offset_x_end'] = (object) [
                'unit' => 'px',
                'size' => (int) round(abs($rel['x'])),
            ];
        }

        if ($rel['y'] >= 0) {
            $settings['_offset_orientation_v'] = 'start';
            $settings['_offset_y'] = (object) [
                'unit' => 'px',
                'size' => (int) round($rel['y']),
            ];
        } else {
            $settings['_offset_orientation_v'] = 'end';
            $settings['_offset_y_end'] = (object) [
                'unit' => 'px',
                'size' => (int) round(abs($rel['y'])),
            ];
        }

        $child_w = (int) ($child_bbox['width'] ?? 0);
        $child_h = (int) ($child_bbox['height'] ?? 0);
        if ($child_w > 0) {
            if ($el_type === 'container') {
                $settings['width'] = (object) ['unit' => 'px', 'size' => $child_w];
            } else {
                $settings['_width'] = (object) ['unit' => 'px', 'size' => $child_w];
            }
        }
        if ($child_h > 0) {
            if ($el_type === 'container') {
                $settings['min_height'] = (object) ['unit' => 'px', 'size' => $child_h];
            } else {
                $settings['_height'] = (object) ['unit' => 'px', 'size' => $child_h];
            }
        }

        $element['settings'] = (object) $settings;
    }

    public function map_align(string $figma): string
    {
        return match ($figma) {
            'MIN' => 'flex-start',
            'CENTER' => 'center',
            'MAX' => 'flex-end',
            'STRETCH' => 'stretch',
            'SPACE_BETWEEN' => 'space-between',
            default => 'flex-start',
        };
    }
}
