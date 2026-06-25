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
class Elementor_Renderer {
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

    public function __construct(Figma_API $figma_api) {
        $this->figma_api = $figma_api;
    }

    // ── Public API ──

    /**
     * Extract canvases/frames from a Figma file (for the file browser UI).
     *
     * Shows any node that has an absoluteBoundingBox (i.e. a defined position
     * on the canvas), excluding only CANVAS and DOCUMENT themselves.
     */
    public function get_file_structure(string $file_key): ?array {
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
    public function convert_node_to_template(string $file_key, string $node_id): ?array {
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
            foreach ($children as $child) {
                $converted = $this->convert_node($child);
                if ($converted !== null) {
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
     * Legacy BC: if node_id given, convert that; otherwise pick first frame.
     */
    public function convert_file(string $file_key, ?string $node_id = null): ?array {
        if ($node_id) {
            return $this->convert_node_to_template($file_key, $node_id);
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
    private function convert_node(array $node): ?array {
        if (!$this->should_render($node)) {
            Logger::log('INFO', 'ElementorRenderer', 'Skipping non-visible node', [
                'figma_type' => $node['type'] ?? null,
                'name' => $node['name'] ?? null,
            ]);
            return null;
        }

        $figma_type = $node['type'] ?? '';
        [$elType, $widgetType] = $this->resolve_type($node);

        Logger::log('INFO', 'ElementorRenderer', 'Converting node', [
            'figma_type' => $figma_type,
            'resolved_elType' => $elType,
            'resolved_widgetType' => $widgetType,
            'name' => $node['name'] ?? null,
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

        // Recursively convert children
        foreach ($node['children'] ?? [] as $child) {
            $converted = $this->convert_node($child);
            if ($converted !== null) {
                $element['elements'][] = $converted;
            }
        }

        return $element;
    }

    /**
     * Resolve Figma type → Elementor elType/widgetType.
     */
    private function resolve_type(array $node): array {
        $figma_type = $node['type'] ?? '';

        if (in_array($figma_type, ['RECTANGLE', 'ELLIPSE'], true)) {
            $fill = $this->get_visible_fill($node);
            if ($fill !== null && ($fill['type'] ?? '') === 'IMAGE') {
                return ['widget', 'image'];
            }
        }

        return self::NODE_MAP[$figma_type] ?? ['container', null];
    }

    // ── Settings Extraction ──

    private function extract_settings(array $node, string $elType, ?string $widgetType): \stdClass {
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
    private function get_visible_fill(array $node): ?array {
        foreach ($node['fills'] ?? [] as $fill) {
            if (($fill['visible'] ?? true) !== false && ($fill['opacity'] ?? 1) > 0) {
                return $fill;
            }
        }
        return null;
    }

    // ── Visual Property Extractors ──

    private function extract_background(array $node, \stdClass $settings): void {
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
                // Skip fully transparent fills
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
                $settings->background_background = 'gradient';
                $settings->background_gradient_type = 'linear';
                $stops = $fill['gradientStops'] ?? [];
                $total = count($stops);
                if ($total > 0) {
                    for ($i = 0; $i < $total; $i++) {
                        $stop = $stops[$i];
                        $pct = $i === 0 ? 0 : ($i === $total - 1 ? 100 : (int) round(($stop['position'] ?? 0) * 100));
                        $settings->{"background_gradient_color_{$i}"} = $this->rgba_to_hex($stop['color'] ?? []);
                        $settings->{"background_gradient_color_before_{$i}"} = "{$pct}%";
                    }
                }
                // Gradient direction from handle positions
                if (!empty($fill['gradientHandlePositions'])) {
                    $settings->background_gradient_angle = 180; // default linear bottom-to-top
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

    private function extract_border(array $node, \stdClass $settings): void {
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

        // Stroke weight: Figma may return a single number or object with 'all'
        $stroke_weight = $node['strokeWeight'] ?? null;
        if (is_array($stroke_weight)) {
            $stroke_weight = $stroke_weight['all'] ?? 1;
        }
        $w = max(1, (int) ($stroke_weight ?? 1));

        $settings->border_width = (object) [
            'unit' => 'px',
            'top' => $w, 'right' => $w, 'bottom' => $w, 'left' => $w,
            'isLinked' => true,
        ];

        // Stroke alignment: inside/outside/center
        // Elementor doesn't directly support this, but we can adjust positioning
    }

    private function extract_border_radius(array $node, \stdClass $settings): void {
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

    private function extract_opacity(array $node, \stdClass $settings): void {
        $o = $node['opacity'] ?? null;
        if ($o !== null && $o < 1) {
            $settings->opacity = $o;
        }
    }

    private function extract_shadow(array $node, \stdClass $settings): void {
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

    private function extract_container_layout(array $node, \stdClass $settings): void {
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

        // Sizing modes — map to Elementor's grow/shrink when applicable
        $primary_sizing = $node['primaryAxisSizingMode'] ?? null;
        $counter_sizing = $node['counterAxisSizingMode'] ?? null;

        if ($primary_sizing === 'FIXED') {
            $settings->flex_grow = '0';
        }
    }

    private function extract_container_dimensions(array $node, \stdClass $settings): void {
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

    private function extract_heading_settings(array $node, \stdClass $settings): void {
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

    private function extract_button_settings(array $node, \stdClass $settings): void {
        $settings->text = $node['name'] ?? __('Button', 'hello-figma');
        $settings->button_size = 'md';
        $settings->align = 'center';

        $fills = $node['fills'] ?? [];
        if (!empty($fills) && ($fills[0]['type'] ?? '') === 'SOLID') {
            $settings->button_background_color = $this->rgba_to_hex($fills[0]['color'] ?? []);
        }
    }

    private function extract_image_settings(array $node, \stdClass $settings): void {
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

    private function extract_icon_settings(array $node, \stdClass $settings): void {
        $settings->icon = 'fas fa-star';
        $fills = $node['fills'] ?? [];
        if (!empty($fills) && ($fills[0]['type'] ?? '') === 'SOLID') {
            $settings->primary_color = $this->rgba_to_hex($fills[0]['color'] ?? []);
        }
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
    private function wrap_in_template(array $content_elements, string $title): array {
        return [
            'title' => $title,
            'type' => 'page',
            'version' => '0.4',
            'page_settings' => [],
            'content' => $content_elements,
        ];
    }

    // ── Helpers ──

    private function should_render(array $node): bool {
        return ($node['visible'] ?? true) !== false && ($node['opacity'] ?? 1) > 0;
    }

    private function detect_heading_level(array $style): string {
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

    private function map_align(string $figma): string {
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
    private function generate_id(): string {
        return substr(bin2hex(random_bytes(4)), 0, 8);
    }

    private function rgba_to_hex(array $rgba): string {
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
