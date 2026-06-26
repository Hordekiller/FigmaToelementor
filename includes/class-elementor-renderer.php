<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

/**
 * Official Elementor JSON Data Structure:
 *
 * Template wrapper:
 *   { "title": "...", "type": "page", "version": "0.4", "page_settings": [], "content": [...] }
 *
 * Container element:
 *   { "id": "12345678", "elType": "container", "isInner": false, "settings": {}, "elements": [] }
 *
 * Widget element:
 *   { "id": "12345678", "elType": "widget", "widgetType": "heading", "isInner": false, "settings": {}, "elements": [] }
 *
 * @see https://developers.elementor.com/docs/data-structure/
 */
class Elementor_Renderer
{
    private Figma_API $figma_api;

    // Mapping: Figma node type → [elType, widgetType?]
    private const NODE_MAP = [
        'TEXT'              => ['widget', 'heading'],
        'FRAME'             => ['container', null],
        'GROUP'             => ['container', null],
        'RECTANGLE'         => ['container', null],
        'ELLIPSE'           => ['container', null],
        'VECTOR'            => ['widget', 'image'],
        'LINE'              => ['widget', 'divider'],
        'COMPONENT'         => ['container', null],
        'INSTANCE'          => ['container', null],
        'BOOLEAN_OPERATION' => ['widget', 'icon'],
        'STAR'              => ['widget', 'icon'],
        'POLYGON'           => ['widget', 'icon'],
    ];

    public function __construct(Figma_API $figma_api)
    {
        $this->figma_api = $figma_api;
    }

    // ── Public API ──

    /**
     * Extract canvases/frames from a Figma file (for the file browser UI).
     *
     * Shows any node that has an absoluteBoundingBox (i.e. a defined position
     * on the canvas), excluding only CANVAS and DOCUMENT themselves.
     */
    public function get_file_structure(string $file_key): ?array
    {
        // depth=2 is sufficient: document → canvases → frames (no nested children)
        $data = $this->figma_api->get_file($file_key, null, 2);
        if ($data === null || !isset($data['document'])) {
            return null;
        }

        $document = $data['document'];
        $file_name = $data['name'] ?? $document['name'] ?? 'Untitled';
        $canvases = [];

        foreach ($document['children'] ?? [] as $canvas) {
            if (($canvas['type'] ?? '') !== 'CANVAS') {
                continue;
            }

            $frames = [];
            foreach ($canvas['children'] ?? [] as $child) {
                $type = $child['type'] ?? '';
                // Skip non-design node types
                if (in_array($type, ['CANVAS', 'DOCUMENT'], true)) {
                    continue;
                }
                $bBox = $child['absoluteBoundingBox'] ?? [];
                $frames[] = [
                    'id' => $child['id'] ?? '',
                    'name' => $child['name'] ?? 'Untitled',
                    'type' => $type,
                    'width' => (int) ($bBox['width'] ?? 0),
                    'height' => (int) ($bBox['height'] ?? 0),
                    'child_count' => count($child['children'] ?? []),
                ];
            }

            // Only include canvases that have at least one importable element
            if (!empty($frames)) {
                $canvases[] = [
                    'id' => $canvas['id'] ?? '',
                    'name' => $canvas['name'] ?? 'Untitled',
                    'frames' => $frames,
                ];
            }
        }

        return [
            'file_name' => $file_name,
            'file_key' => $file_key,
            'canvases' => $canvases,
        ];
    }

    /**
     * Convert a specific Figma node (frame) to a full Elementor template JSON.
     *
     * Each direct child of the selected frame becomes a top-level content element
     * in the template, making every section independently editable in Elementor.
     *
     * @return array|null Template structure matching Elementor's JSON spec
     */
    public function convert_node_to_template(string $file_key, string $node_id, array $overrides = []): ?array
    {
        $data = $this->figma_api->get_file_nodes($file_key, [$node_id]);
        if ($data === null || !isset($data['nodes'][$node_id])) {
            return null;
        }

        $document = $data['nodes'][$node_id]['document'] ?? null;
        if ($document === null) {
            return null;
        }

        $frame_name = $document['name'] ?? 'Figma Frame';

        // Convert each direct child of the frame to a standalone element
        $children = $document['children'] ?? [];
        $content = [];

        // Also create a container element for the frame itself (background/effects)
        // so its own visual properties (background, shadow) are preserved
        $frame_element = $this->convert_node($document);
        if ($frame_element !== null) {
            // Top-level sections are the frame's direct children
            $frame_layout_mode = $document['layoutMode'] ?? null;
            $frame_bbox = $document['absoluteBoundingBox'] ?? null;
            foreach ($children as $child) {
                $child_id = $child['id'] ?? '';
                $override = array_key_exists($child_id, $overrides) ? $overrides[$child_id] : null;
                $converted = $this->convert_node($child, $frame_layout_mode, $override);
                if ($converted !== null) {
                    // Apply absolute positioning for frame-level children when frame has no auto-layout
                    if ($frame_bbox !== null && $this->node_needs_absolute_positioning($child, $frame_layout_mode)) {
                        $this->apply_absolute_positioning($converted, $child, $frame_bbox);
                    }
                    $content[] = $converted;
                }
            }

            // If no usable children found, import the frame as a single container
            if (empty($content)) {
                $content[] = $frame_element;
            }
        }

        if (empty($content)) {
            return null;
        }

        return $this->wrap_in_template($content, $frame_name);
    }

    /**
     * Preview sections (direct children) of a frame, with thumbnail URLs and
     * auto-detected component type suggestions.
     *
     * @param string $file_key Figma file key
     * @param string $node_id  Frame node ID
     * @return array|null Array of section arrays, or null if the frame is not found.
     */
    public function get_sections_preview(string $file_key, string $node_id): ?array
    {
        $data = $this->figma_api->get_file_nodes($file_key, [$node_id]);
        if ($data === null || !isset($data['nodes'][$node_id])) {
            return null;
        }

        $document = $data['nodes'][$node_id]['document'] ?? null;
        if ($document === null) {
            return null;
        }

        $children = $document['children'] ?? [];
        $sections = [];
        $child_ids = [];

        foreach ($children as $child) {
            $child_id = $child['id'] ?? '';
            if ($child_id === '') {
                continue;
            }
            $child_ids[] = $child_id;
            $sections[] = [
                'id' => $child_id,
                'name' => $child['name'] ?? 'Untitled',
                'suggested_type' => Component_Detector::detect($child['name'] ?? '') ?? 'container',
            ];
        }

        if (!empty($child_ids)) {
            $thumbnails = $this->figma_api->get_thumbnail_urls($file_key, $child_ids);
            foreach ($sections as &$section) {
                $section['thumbnail_url'] = $thumbnails[$section['id']] ?? null;
            }
            unset($section);
        }

        return $sections;
    }

    /**
     * Legacy BC: if node_id given, convert that; otherwise pick first frame.
     */
    public function convert_file(string $file_key, ?string $node_id = null, array $overrides = []): ?array
    {
        if ($node_id) {
            return $this->convert_node_to_template($file_key, $node_id, $overrides);
        }
        $structure = $this->get_file_structure($file_key);
        if ($structure === null || empty($structure['canvases'])) {
            return null;
        }
        $frames = $structure['canvases'][0]['frames'] ?? [];
        if (empty($frames)) {
            return null;
        }
        return $this->convert_node_to_template($file_key, $frames[0]['id']);
    }

    // ── Node Conversion Engine ──

    /**
     * Recursively convert a Figma node to an Elementor element.
     *
     * @return array|null { id, elType, widgetType?, isInner, settings, elements }
     */
    private function convert_node(array $node, ?string $parent_layout_mode = null, ?string $component_override = null): ?array
    {
        if (!$this->should_render($node)) {
            Logger::log('INFO', 'ElementorRenderer', 'Skipping non-visible node', [
                'figma_type' => $node['type'] ?? null,
                'name' => $node['name'] ?? null,
            ]);
            return null;
        }

        $figma_type = $node['type'] ?? '';

        // ── Component-type detection from layer name (or override) ──
        if ($component_override !== null) {
            $component_type = ($component_override === 'container') ? null : $component_override;
        } else {
            $component_type = Component_Detector::detect($node['name'] ?? '');
        }

        // ── Slider / Carousel: attempt structural conversion ──
        if ($component_type !== null && in_array($component_type, ['slider', 'carousel'], true)) {
            $container_types = ['FRAME', 'GROUP', 'COMPONENT', 'INSTANCE'];
            if (in_array($figma_type, $container_types, true)) {
                $carousel_element = $this->try_build_carousel($node, $component_type);
                if ($carousel_element !== null) {
                    return $carousel_element;
                }
            }
        }

        // ── FAQ → Accordion: attempt structural conversion ──
        if ($component_type === 'faq') {
            $container_types = ['FRAME', 'GROUP', 'COMPONENT', 'INSTANCE'];
            if (in_array($figma_type, $container_types, true)) {
                $accordion_element = $this->try_build_accordion($node);
                if ($accordion_element !== null) {
                    return $accordion_element;
                }
            }
        }

        // ── Gallery → Basic Gallery: attempt structural conversion ──
        if ($component_type === 'gallery') {
            $container_types = ['FRAME', 'GROUP', 'COMPONENT', 'INSTANCE'];
            if (in_array($figma_type, $container_types, true)) {
                $gallery_element = $this->try_build_gallery($node);
                if ($gallery_element !== null) {
                    return $gallery_element;
                }
            }
        }

        [$elType, $widgetType] = $this->resolve_type($node);

        Logger::log('INFO', 'ElementorRenderer', 'Converting node', [
            'figma_type' => $figma_type,
            'resolved_elType' => $elType,
            'resolved_widgetType' => $widgetType,
            'name' => $node['name'] ?? null,
            'component_type' => $component_type,
        ]);

        $element = [
            'id' => $this->generate_id(),
            'elType' => $elType,
            'isInner' => false,
            'settings' => new \stdClass(), // empty object → encodes as {} in JSON
            'elements' => [],
        ];

        if ($elType === 'widget' && $widgetType !== null) {
            $element['widgetType'] = $widgetType;
        }

        // Populate settings based on element type
        $element['settings'] = $this->extract_settings($node, $elType, $widgetType);

        // ── Flex child sizing for children of auto-layout parents ──
        if ($parent_layout_mode !== null && $elType === 'container') {
            $this->extract_flex_child_sizing($node, $element['settings'], $parent_layout_mode);
        }

        // ── CSS class tagging for all detected component types ──
        if ($component_type !== null) {
            $existing_classes = $element['settings']->_css_classes ?? '';
            $element['settings']->_css_classes = trim($existing_classes . ' figma-detected-' . $component_type);
        }

        // Recursively convert children (pass this node's layoutMode for their sizing)
        $node_layout_mode = $node['layoutMode'] ?? null;
        foreach ($node['children'] ?? [] as $child) {
            $converted = $this->convert_node($child, $node_layout_mode);
            if ($converted !== null) {
                if ($this->node_needs_absolute_positioning($child, $node_layout_mode)) {
                    $this->apply_absolute_positioning($converted, $child, $node);
                }
                $element['elements'][] = $converted;
            }
        }

        return $element;
    }

    /**
     * Determine if a child node needs absolute positioning in Elementor.
     *
     * Two scenarios:
     * 1. Parent has NO auto-layout (layoutMode is null) → all children are absolute.
     * 2. Parent has auto-layout and child.layoutPositioning === 'ABSOLUTE'.
     *
     * Rotated nodes (abs(rotation) > 0.01) are excluded — computing position for
     * rotated absolute nodes requires the full relativeTransform matrix (deferred).
     */
    private function node_needs_absolute_positioning(array $child, ?string $parent_layout_mode): bool
    {
        // Skip rotated nodes — their position requires the full relativeTransform
        // matrix calculation, not just x/y (deferred to a future task).
        if (isset($child['rotation']) && abs((float) $child['rotation']) > 0.01) {
            return false;
        }

        if ($parent_layout_mode === null) {
            return true;
        }
        return ($child['layoutPositioning'] ?? '') === 'ABSOLUTE';
    }

    /**
     * Compute the relative position of a child within its parent.
     *
     * Formula: relative = child.absoluteBoundingBox - parent.absoluteBoundingBox
     * This works correctly even when absoluteBoundingBox values are negative
     * (relative to canvas center), because both values share the same origin.
     *
     * @return array{ x: float, y: float }|null
     */
    private function compute_relative_position(array $child_node, array $parent_node): ?array
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

    /**
     * Apply Elementor absolute positioning controls to a converted child element.
     *
     * Field names confirmed via round-trip test on Elementor 4.1.3:
     *   Widgets: _position = 'absolute'
     *   Containers: position = 'absolute' (no underscore)
     *   Common: _offset_orientation_h (start/end), _offset_x, _offset_x_end,
     *           _offset_orientation_v (start/end), _offset_y, _offset_y_end
     *
     * Also sets explicit width/height from the child's bounding box so that
     * the absolutely-positioned element maintains its Figma dimensions.
     */
    private function apply_absolute_positioning(array &$element, array $child, array $parent): void
    {
        $rel = $this->compute_relative_position($child, $parent);
        if ($rel === null) {
            return;
        }

        $child_bbox = $child['absoluteBoundingBox'] ?? [];
        $settings = (array) $element['settings'];
        $el_type = $element['elType'] ?? '';

        // Position key differs: containers use "position", widgets use "_position"
        if ($el_type === 'container') {
            $settings['position'] = 'absolute';
        } else {
            $settings['_position'] = 'absolute';
        }

        // Horizontal offset + orientation
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

        // Vertical offset + orientation
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

        // Set explicit width/height from bounding box
        // Containers use width/min_height, widgets use _width/_height
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

    /**
     * Resolve Figma type → Elementor elType/widgetType.
     */
    private function resolve_type(array $node): array
    {
        $figma_type = $node['type'] ?? '';

        if (in_array($figma_type, ['RECTANGLE', 'ELLIPSE', 'BOOLEAN_OPERATION', 'STAR', 'POLYGON'], true)) {
            $fill = $this->get_visible_fill($node);
            if ($fill !== null && ($fill['type'] ?? '') === 'IMAGE') {
                return ['widget', 'image'];
            }
        }

        // Vector shapes → render as images via Figma export API
        if (in_array($figma_type, ['BOOLEAN_OPERATION', 'STAR', 'POLYGON'], true)) {
            return ['widget', 'image'];
        }

        return self::NODE_MAP[$figma_type] ?? ['container', null];
    }

    // ── Settings Extraction ──

    private function extract_settings(array $node, string $elType, ?string $widgetType): \stdClass
    {
        $settings = new \stdClass();

        // CSS ID from layer name
        $name = $node['name'] ?? '';
        if ($name) {
            $settings->_element_id = sanitize_title($name);
        }

        // Core visual properties (apply to all element types)
        $this->extract_background($node, $settings);
        $this->extract_border($node, $settings);
        $this->extract_border_radius($node, $settings);
        $this->extract_opacity($node, $settings);
        $this->extract_shadow($node, $settings);

        // Type-specific settings
        if ($elType === 'container') {
            $this->extract_container_layout($node, $settings);
            $this->extract_container_dimensions($node, $settings);
            $this->extract_parent_sizing_mode($node, $settings);
        } elseif ($widgetType === 'heading') {
            $this->extract_heading_settings($node, $settings);
        } elseif ($widgetType === 'button') {
            $this->extract_button_settings($node, $settings);
        } elseif ($widgetType === 'image') {
            $this->extract_image_settings($node, $settings);
        } elseif ($widgetType === 'icon') {
            $this->extract_icon_settings($node, $settings);
        }

        return $settings;
    }

    /**
     * Get the first visible paint (fill) from a node.
     */
    private function get_visible_fill(array $node): ?array
    {
        foreach ($node['fills'] ?? [] as $fill) {
            if (($fill['visible'] ?? true) !== false && ($fill['opacity'] ?? 1) > 0) {
                return $fill;
            }
        }
        return null;
    }

    // ── Visual Property Extractors ──

    private function extract_background(array $node, \stdClass $settings): void
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

            case 'GRADIENT':
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
                // Gradient angle from handle positions
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
        }
    }

    private function extract_border(array $node, \stdClass $settings): void
    {
        // Find first visible stroke
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

        // Stroke weight: Figma may return a single number or an object with per-side values
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
            $top = max(1, (int) ($stroke_weight['top'] ?? $stroke_weight['all'] ?? 1));
            $right = max(1, (int) ($stroke_weight['right'] ?? $stroke_weight['all'] ?? $top));
            $bottom = max(1, (int) ($stroke_weight['bottom'] ?? $stroke_weight['all'] ?? $top));
            $left = max(1, (int) ($stroke_weight['left'] ?? $stroke_weight['all'] ?? $right));
            $is_linked = ($top === $right && $right === $bottom && $bottom === $left);
        }

        $settings->border_width = (object) [
            'unit' => 'px',
            'top' => $top, 'right' => $right, 'bottom' => $bottom, 'left' => $left,
            'isLinked' => $is_linked,
        ];

        // Stroke alignment: inside/outside/center
        // Elementor doesn't directly support this, but we can adjust positioning
    }

    private function extract_border_radius(array $node, \stdClass $settings): void
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

    private function extract_opacity(array $node, \stdClass $settings): void
    {
        $o = $node['opacity'] ?? null;
        if ($o !== null && $o < 1) {
            $settings->opacity = $o;
        }
    }

    private function extract_shadow(array $node, \stdClass $settings): void
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

    private function extract_container_layout(array $node, \stdClass $settings): void
    {
        $layout = $node['layoutMode'] ?? null;
        if ($layout) {
            $settings->flex_direction = $layout === 'HORIZONTAL' ? 'row' : 'column';
            $settings->flex_wrap = 'wrap';
        }

        // Padding — extracted regardless of auto-layout
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

        // Gap — map itemSpacing to column_gap (row) or row_gap (column) depending on direction
        $gap = $node['itemSpacing'] ?? null;
        if ($gap !== null) {
            $is_row = ($layout === 'HORIZONTAL');
            if ($is_row) {
                $settings->column_gap = (object) ['unit' => 'px', 'size' => (int) $gap];
            } else {
                $settings->row_gap = (object) ['unit' => 'px', 'size' => (int) $gap];
            }
        }

        // Alignment
        $settings->flex_justify_content = $this->map_align($node['primaryAxisAlignItems'] ?? 'MIN');
        $settings->flex_align_items = $this->map_align($node['counterAxisAlignItems'] ?? 'MIN');

        // Sizing modes for the PARENT container itself are handled
        // in extract_parent_sizing_mode() called from extract_settings().
    }

    private function extract_container_dimensions(array $node, \stdClass $settings): void
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

    private function extract_heading_settings(array $node, \stdClass $settings): void
    {
        // Text content
        $characters = $node['characters'] ?? '';
        if (empty($characters)) {
            // Try name as fallback, or set a placeholder
            $characters = $node['name'] ?? __('Heading', 'hello-figma');
        }
        $settings->title = $characters;

        $style = $node['style'] ?? [];
        $settings->header_size = $this->detect_heading_level($style);

        // === Full Typography Settings ===
        $has_typography = false;
        $typo = [];

        if (!empty($style['fontFamily'])) {
            $typo['font_family'] = $style['fontFamily'];
            $has_typography = true;
        }
        if (!empty($style['fontSize'])) {
            $typo['font_size'] = (object) [
                'unit' => 'px',
                'size' => (float) $style['fontSize'],
            ];
            $has_typography = true;
        }
        if (!empty($style['fontWeight'])) {
            $typo['font_weight'] = (string) $style['fontWeight'];
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

        // Text alignment
        $figma_align = $style['textAlignHorizontal'] ?? 'LEFT';
        $settings->align = strtolower($figma_align);

        // Text color from visible fill
        $fill = $this->get_visible_fill($node);
        if ($fill !== null && ($fill['type'] ?? '') === 'SOLID') {
            $color = $fill['color'] ?? [];
            if (!empty($color) && ($color['a'] ?? 1) > 0) {
                $settings->text_color = $this->rgba_to_hex($color);
            }
        }
    }

    private function extract_button_settings(array $node, \stdClass $settings): void
    {
        // Try to find text from child TEXT nodes first
        $texts = $this->find_text_nodes_in_subtree($node, 1);
        if (!empty($texts)) {
            $settings->text = $texts[0]['characters'] ?? $node['name'];
        } else {
            // Map common button layer names
            $name_labels = [
                'button', 'btn', 'دکمه', 'click', 'submit',
                'ثبت', 'ارسال', 'buy', 'shop', 'خرید',
            ];
            $name_lower = mb_strtolower(trim($node['name'] ?? ''));
            $settings->text = in_array($name_lower, $name_labels, true)
                ? ($node['name'] ?? __('Button', 'hello-figma'))
                : ($node['name'] ?? __('Button', 'hello-figma'));
        }

        $settings->button_size = 'md';
        $settings->align = 'center';

        $fills = $node['fills'] ?? [];
        if (!empty($fills) && ($fills[0]['type'] ?? '') === 'SOLID') {
            $settings->button_background_color = $this->rgba_to_hex($fills[0]['color'] ?? []);
        }

        // Extract hover background if available from component variants
        if (!empty($node['componentPropertyDefinitions'])) {
            $settings->hover_background_color = $settings->button_background_color ?? '#000000';
        }
    }

    private function extract_image_settings(array $node, \stdClass $settings): void
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

    private function extract_icon_settings(array $node, \stdClass $settings): void
    {
        $settings->icon = 'fas fa-star';
        $fills = $node['fills'] ?? [];
        if (!empty($fills) && ($fills[0]['type'] ?? '') === 'SOLID') {
            $settings->primary_color = $this->rgba_to_hex($fills[0]['color'] ?? []);
        }
    }

    // ── Parent Sizing Mode (for auto-layout containers themselves) ──

    /**
     * Adjust the parent container's own width/min_height based on
     * primaryAxisSizingMode / counterAxisSizingMode.
     *
     * When an auto-layout frame's primary axis is AUTO (= HUG), the
     * container should NOT have an explicit width (row) or height (column)
     * from absoluteBoundingBox — it should shrink-wrap its children instead.
     */
    private function extract_parent_sizing_mode(array $node, \stdClass $settings): void
    {
        $layout_mode = $node['layoutMode'] ?? null;
        if ($layout_mode === null || $layout_mode === 'NONE') {
            return;
        }

        $is_row = ($layout_mode === 'HORIZONTAL');
        $primary_mode  = $node['primaryAxisSizingMode'] ?? null;
        $counter_mode  = $node['counterAxisSizingMode'] ?? null;

        // Primary axis: AUTO = hug → remove explicit dimension
        if ($primary_mode === 'AUTO') {
            if ($is_row) {
                unset($settings->width);
            } else {
                unset($settings->min_height);
            }
        }

        // Counter axis: AUTO = hug → remove explicit dimension
        if ($counter_mode === 'AUTO') {
            if ($is_row) {
                unset($settings->min_height);
            } else {
                unset($settings->width);
            }
        }
    }

    // ── Flex Child Sizing (for children of auto-layout parents) ──

    /**
     * Map Figma child sizing modes (FIXED / HUG / FILL) to Elementor
     * flex-item controls (_flex_size, _flex_align_self).
     *
     * Called during convert_node() for every container that is a direct
     * child of an auto-layout parent.
     *
     * Fallback: if layoutSizingHorizontal/Vertical are absent (older files),
     *          uses layoutGrow: 1 → FILL primary, layoutAlign: 'STRETCH' → FILL counter.
     *
     * @param array    $node               The child Figma node
     * @param \stdClass $settings           Elementor settings (mutated in place)
     * @param string   $parent_layout_mode Parent's layoutMode ('HORIZONTAL' or 'VERTICAL')
     */
    private function extract_flex_child_sizing(array $node, \stdClass $settings, string $parent_layout_mode): void
    {
        $is_row = ($parent_layout_mode === 'HORIZONTAL');

        // ── Read sizing per axis ──
        // Modern API (v5+): use layoutSizingHorizontal / layoutSizingVertical
        $layout_h = $node['layoutSizingHorizontal'] ?? null;
        $layout_v = $node['layoutSizingVertical'] ?? null;

        // Legacy API fallback: derive from layoutGrow / layoutAlign
        if ($layout_h === null && $layout_v === null) {
            $layout_grow  = $node['layoutGrow'] ?? 0;
            $layout_align = $node['layoutAlign'] ?? null;

            if ($is_row) {
                $layout_h = ($layout_grow === 1) ? 'FILL' : null;
                $layout_v = ($layout_align === 'STRETCH') ? 'FILL' : null;
            } else {
                $layout_v = ($layout_grow === 1) ? 'FILL' : null;
                $layout_h = ($layout_align === 'STRETCH') ? 'FILL' : null;
            }

            // For non-FILL cases in legacy mode, keep the explicit
            // bounding-box dimensions already set by extract_container_dimensions
            // (acts as FIXED).
            if ($layout_h === null) {
                $layout_h = 'FIXED';
            }
            if ($layout_v === null) {
                $layout_v = 'FIXED';
            }
        }

        // Primary axis = direction the parent flows (main axis)
        $primary_sizing = $is_row ? $layout_h : $layout_v;
        // Counter axis = cross-axis of parent
        $counter_sizing = $is_row ? $layout_v : $layout_h;

        // ── Primary axis (main axis of parent flex) ──
        if ($primary_sizing === 'FILL') {
            $settings->_flex_size = 'grow';
            // Remove explicit dimension — flex-grow determines size
            if ($is_row) {
                unset($settings->width);
            } else {
                unset($settings->min_height);
            }
        } elseif ($primary_sizing === 'FIXED') {
            $settings->_flex_size = 'none';
            // Keep explicit dimension (already set by extract_container_dimensions)
        } elseif ($primary_sizing === 'HUG') {
            // No explicit dimension — size to content
            if ($is_row) {
                unset($settings->width);
            } else {
                unset($settings->min_height);
            }
        }

        // ── Counter axis (cross axis of parent flex) ──
        if ($counter_sizing === 'FILL') {
            $settings->_flex_align_self = 'stretch';
            if ($is_row) {
                unset($settings->min_height);
            } else {
                unset($settings->width);
            }
        } elseif ($counter_sizing === 'FIXED') {
            $settings->_flex_align_self = '';
            // Keep explicit dimension
        } elseif ($counter_sizing === 'HUG') {
            $settings->_flex_align_self = '';
            if ($is_row) {
                unset($settings->min_height);
            } else {
                unset($settings->width);
            }
        }
    }

    // ── Slider / Carousel Conversion ──

    /**
     * Attempt to build an Elementor Image Carousel widget from a node.
     *
     * Conditions (both must hold):
     *   1. Node has at least 2 direct children.
     *   2. At least 70% of direct children have a visible IMAGE fill
     *      (either on themselves or within one level of descendants).
     *
     * If conditions are not met, returns null (caller should fall through
     * to normal generic conversion).
     *
     * @return array|null A carousel widget element, or null.
     */
    private function try_build_carousel(array $node, string $component_type): ?array
    {
        $children = $node['children'] ?? [];
        $total = count($children);

        if ($total < 2) {
            return null;
        }

        // Find which children have an image fill within 1 sub-level
        $image_node_ids = [];
        foreach ($children as $child) {
            $img_node = $this->find_image_in_subtree($child, 1);
            if ($img_node !== null) {
                $image_node_ids[] = $img_node['id'] ?? '';
            }
        }

        $image_count = count($image_node_ids);
        $ratio = $image_count / $total;

        if ($ratio < 0.7) {
            return null;
        }

        // Build the carousel slides array reusing the same image-settings logic
        // that extract_image_settings() uses, so placeholder format stays consistent
        // with the rest of the pipeline.
        $slides = [];
        foreach ($children as $child) {
            $img_node = $this->find_image_in_subtree($child, 1);
            if ($img_node === null) {
                continue;
            }
            $img_settings = new \stdClass();
            $this->extract_image_settings($img_node, $img_settings);
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
            'id' => $this->generate_id(),
            'elType' => 'widget',
            'widgetType' => 'image-carousel',
            'isInner' => false,
            'settings' => $carousel_settings,
            'elements' => [],
        ];
    }

    /**
     * Find the first node with a visible IMAGE fill within $max_depth levels.
     *
     * Depth 0 = check $node itself.
     * Depth 1 = also check direct children of $node.
     *
     * @return array|null The raw Figma node array, or null if not found.
     */
    private function find_image_in_subtree(array $node, int $max_depth = 1): ?array
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

    // ── FAQ / Accordion Conversion ──

    /**
     * Attempt to build an Elementor Accordion widget from a node.
     *
     * Conditions (both must hold):
     *   1. component_type === 'faq'.
     *   2. Node has at least 1 direct child, AND at least 70% of those children
     *      are FRAME/GROUP nodes containing at least 2 descendant TEXT nodes
     *      within 2 levels of depth.
     *
     * If conditions are not met or no valid items remain, returns null.
     *
     * @return array|null An accordion widget element, or null.
     */
    private function try_build_accordion(array $node): ?array
    {
        $children = $node['children'] ?? [];
        $total = count($children);

        if ($total < 1) {
            return null;
        }

        // Count children that match the text-rich frame pattern
        $matched_items = [];
        foreach ($children as $child) {
            $figma_type = $child['type'] ?? '';
            if (!in_array($figma_type, ['FRAME', 'GROUP'], true)) {
                continue;
            }
            $texts = $this->find_text_nodes_in_subtree($child, 2);
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
            $texts = $this->find_text_nodes_in_subtree($item_node, 2);
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
            'id' => $this->generate_id(),
            'elType' => 'widget',
            'widgetType' => 'accordion',
            'isInner' => false,
            'settings' => $accordion_settings,
            'elements' => [],
        ];
    }

    // ── Gallery / Basic Gallery Conversion ──

    /**
     * Attempt to build an Elementor Image Gallery widget from a node.
     *
     * Conditions (same shape as carousel):
     *   1. component_type === 'gallery'.
     *   2. Node has at least 2 direct children, AND at least 70% have a visible
     *      IMAGE fill within 1 level of descendants.
     *
     * @return array|null A gallery widget element, or null.
     */
    private function try_build_gallery(array $node): ?array
    {
        $children = $node['children'] ?? [];
        $total = count($children);

        if ($total < 2) {
            return null;
        }

        $matched_images = [];
        foreach ($children as $child) {
            $img_node = $this->find_image_in_subtree($child, 1);
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
            $img_node = $this->find_image_in_subtree($child, 1);
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
            'id' => $this->generate_id(),
            'elType' => 'widget',
            'widgetType' => 'image-gallery',
            'isInner' => false,
            'settings' => $gallery_settings,
            'elements' => [],
        ];
    }

    /**
     * Collect all TEXT node references within $max_depth levels of a node.
     *
     * Depth 0 = check $node itself.
     * Depth 2 = also check children and grandchildren.
     *
     * @return array Array of raw Figma TEXT node arrays.
     */
    private function find_text_nodes_in_subtree(array $node, int $max_depth = 2): array
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

    // ── Template Wrapper ──

    /**
     * Wrap converted content in Elementor's template JSON structure.
     *
     * @param array $content_elements Array of top-level element arrays
     * @param string $title Template title
     * @return array
     *
     * @see https://developers.elementor.com/docs/data-structure/page-content/
     */
    private function wrap_in_template(array $content_elements, string $title): array
    {
        return [
            'title' => $title,
            'type' => 'page',
            'version' => '0.4',
            'page_settings' => [],
            'content' => $content_elements,
        ];
    }

    // ── Helpers ──

    private function should_render(array $node): bool
    {
        return ($node['visible'] ?? true) !== false && ($node['opacity'] ?? 1) > 0;
    }

    private function detect_heading_level(array $style): string
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

    private function map_align(string $figma): string
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

    /**
     * Generate an 8-character hex ID (matching Elementor's format).
     */
    private function generate_id(): string
    {
        return substr(bin2hex(random_bytes(4)), 0, 8);
    }

    private function rgba_to_hex(array $rgba): string
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
}
