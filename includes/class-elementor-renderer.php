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
    private NodeFilter $node_filter;
    private TypeResolver $type_resolver;
    private StyleExtractor $style_extractor;
    private LayoutExtractor $layout_extractor;
    private Positioning $positioning;
    private WidgetConverters $widget_converters;

    public function __construct(Figma_API $figma_api)
    {
        $this->figma_api = $figma_api;
        $this->node_filter = new NodeFilter();
        $this->style_extractor = new StyleExtractor();
        $this->positioning = new Positioning();
        $this->type_resolver = new TypeResolver($this->style_extractor);
        $this->layout_extractor = new LayoutExtractor($this->positioning);
        $this->widget_converters = new WidgetConverters(
            $this->style_extractor,
            fn(): string => $this->generate_id()
        );
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
                $converted = $this->convert_node($child, $frame_layout_mode, $override, $document);
                if ($converted !== null) {
                    if (isset($converted['elType'])) {
                        // Single element (normal or styled GROUP that falls through)
                        if ($frame_bbox !== null && $this->positioning->node_needs_absolute_positioning($child, $frame_layout_mode)) {
                            $this->positioning->apply_absolute_positioning($converted, $child, $frame_bbox);
                        }
                        $content[] = $converted;
                    } else {
                        // Flattened GROUP result — already positioned relative to the frame
                        foreach ($converted as $c) {
                            $content[] = $c;
                        }
                    }
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

        // ── Collapse empty single-child wrappers within each section ──
        // Top-level elements (sections) are excluded from collapsing so they
        // remain independently editable even if structurally "empty".
        // Collapsing is applied to their descendants instead.
        foreach ($content as &$top_level_element) {
            if (!empty($top_level_element['elements'])) {
                $top_level_element['elements'] = $this->collapse_empty_wrappers(
                    $top_level_element['elements']
                );
            }
            unset($top_level_element['_clips_content']);
        }
        unset($top_level_element);

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
    private function convert_node(array $node, ?string $parent_layout_mode = null, ?string $component_override = null, ?array $grandparent_node = null): ?array
    {
        if (!$this->node_filter->should_render($node)) {
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
                $carousel_element = $this->widget_converters->try_build_carousel($node, $component_type);
                if ($carousel_element !== null) {
                    return $carousel_element;
                }
            }
        }

        // ── FAQ → Accordion: attempt structural conversion ──
        if ($component_type === 'faq') {
            $container_types = ['FRAME', 'GROUP', 'COMPONENT', 'INSTANCE'];
            if (in_array($figma_type, $container_types, true)) {
                $accordion_element = $this->widget_converters->try_build_accordion($node);
                if ($accordion_element !== null) {
                    return $accordion_element;
                }
            }
        }

        // ── Gallery → Basic Gallery: attempt structural conversion ──
        if ($component_type === 'gallery') {
            $container_types = ['FRAME', 'GROUP', 'COMPONENT', 'INSTANCE'];
            if (in_array($figma_type, $container_types, true)) {
                $gallery_element = $this->widget_converters->try_build_gallery($node);
                if ($gallery_element !== null) {
                    return $gallery_element;
                }
            }
        }

        // ── GROUP flattening: skip container, return children directly ──
        if ($figma_type === 'GROUP' && $grandparent_node !== null && $this->group_is_safe_to_flatten($node)) {
            $flattened = [];
            $gp_layout_mode = $grandparent_node['layoutMode'] ?? null;
            foreach ($node['children'] ?? [] as $child) {
                $converted = $this->convert_node($child, $gp_layout_mode, null, $grandparent_node);
                if ($converted !== null) {
                    if ($this->positioning->node_needs_absolute_positioning($child, $gp_layout_mode)) {
                        $this->positioning->apply_absolute_positioning($converted, $child, $grandparent_node);
                    }
                    $flattened[] = $converted;
                }
            }
            return $flattened;
        }

        [$elType, $widgetType] = $this->type_resolver->resolve_type($node);

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
            $this->layout_extractor->extract_flex_child_sizing($node, $element['settings'], $parent_layout_mode);
        }

        // ── Track clipsContent for collapse_empty_wrappers exclusion ──
        if ($elType === 'container' && !empty($node['clipsContent'])) {
            $element['_clips_content'] = true;
        }

        // ── CSS class tagging for all detected component types ──
        if ($component_type !== null) {
            $existing_classes = $element['settings']->_css_classes ?? '';
            $element['settings']->_css_classes = trim($existing_classes . ' figma-detected-' . $component_type);
        }

        // Recursively convert children (pass this node's layoutMode for their sizing)
        $node_layout_mode = $node['layoutMode'] ?? null;
        foreach ($node['children'] ?? [] as $child) {
            $converted = $this->convert_node($child, $node_layout_mode, null, $node);
            if ($converted !== null) {
                if (isset($converted['elType'])) {
                    // Single element
                    if ($this->positioning->node_needs_absolute_positioning($child, $node_layout_mode)) {
                        $this->positioning->apply_absolute_positioning($converted, $child, $node);
                    }
                    $element['elements'][] = $converted;
                } else {
                    // Flattened GROUP result — already positioned relative to $node
                    foreach ($converted as $c) {
                        $element['elements'][] = $c;
                    }
                }
            }
        }

        return $element;
    }

    // ── Settings Extraction (dispatcher) ──

    private function extract_settings(array $node, string $elType, ?string $widgetType): \stdClass
    {
        $settings = new \stdClass();

        // CSS ID from layer name
        $name = $node['name'] ?? '';
        if ($name) {
            $settings->_element_id = sanitize_title($name);
        }

        // Core visual properties (apply to all element types)
        $this->style_extractor->extract_background($node, $settings);
        $this->style_extractor->extract_border($node, $settings);
        $this->style_extractor->extract_border_radius($node, $settings);
        $this->style_extractor->extract_opacity($node, $settings);
        $this->style_extractor->extract_shadow($node, $settings);

        // Type-specific settings
        if ($elType === 'container') {
            $this->layout_extractor->extract_container_layout($node, $settings);
            $this->layout_extractor->extract_container_dimensions($node, $settings);
            $this->layout_extractor->extract_parent_sizing_mode($node, $settings);
        } elseif ($widgetType === 'heading') {
            $this->style_extractor->extract_heading_settings($node, $settings);
        } elseif ($widgetType === 'button') {
            $this->style_extractor->extract_button_settings($node, $settings);
        } elseif ($widgetType === 'image') {
            $this->style_extractor->extract_image_settings($node, $settings);
        } elseif ($widgetType === 'icon') {
            $this->style_extractor->extract_icon_settings($node, $settings);
        }

        return $settings;
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
        return JsonNormalizer::normalize_template([
            'title' => $title,
            'type' => 'page',
            'version' => '0.4',
            'page_settings' => [],
            'content' => $content_elements,
        ]);
    }

    // ── Empty Wrapper Collapsing ──

    /**
     * Post-processing pass that removes meaningless single-child wrapper
     * containers, reducing excessive nesting. Processes bottom-up so that
     * a chain of nested empty wrappers collapses in one pass.
     *
     * A container qualifies if it has exactly one child element and no
     * visual/sizing settings of its own (only _element_id is allowed).
     *
     * Exclusions (never collapse even if structurally empty):
     *   - Top-level elements (these are independent sections) — NOT applied
     *     here, handled at the call site in convert_node_to_template().
     *   - The single child is a structural widget (accordion, image-carousel,
     *     image-gallery) that needs its wrapping container for spacing.
     *   - The original Figma node had clipsContent === true (overflow mask).
     *
     * @param array $elements Array of Elementor element arrays
     * @return array Processed array with qualifying wrappers collapsed
     */
    private function collapse_empty_wrappers(array $elements): array
    {
        $result = [];
        foreach ($elements as $element) {
            // ── Recurse into children first (bottom-up) ──
            if (!empty($element['elements'])) {
                $element['elements'] = $this->collapse_empty_wrappers($element['elements']);
            }

            // ── Strip internal marker used for clipsContent check ──
            $clips_content = !empty($element['_clips_content']);
            unset($element['_clips_content']);

            // ── Determine if this element qualifies for collapsing ──
            $qualifies = (
                !$clips_content
                && ($element['elType'] ?? '') === 'container'
                && count($element['elements']) === 1
                && $this->is_empty_wrapper($element)
            );

            if ($qualifies) {
                $child = $element['elements'][0];

                // Safety exclusion: don't collapse onto structural widgets
                if (!$this->is_structural_widget($child)) {
                    $this->maybe_transfer_element_id($element, $child);
                    $result[] = $child;
                    continue;
                }
            }

            $result[] = $element;
        }
        return $result;
    }

    /**
     * Check whether a container element has no meaningful visual or sizing
     * settings of its own — i.e. its settings object is empty or only
     * contains the safe-to-ignore _element_id property.
     */
    private function is_empty_wrapper(array $element): bool
    {
        $settings = $element['settings'] ?? new \stdClass();

        if ($settings instanceof \stdClass) {
            $vars = get_object_vars($settings);
        } elseif (is_array($settings)) {
            $vars = $settings;
        } else {
            return false;
        }

        foreach ($vars as $key => $value) {
            if ($key !== '_element_id') {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether a widget is one of the structural types (accordion,
     * image-carousel, image-gallery) that should keep their wrapping
     * container for spacing/margin purposes.
     */
    private function is_structural_widget(array $element): bool
    {
        if (($element['elType'] ?? '') !== 'widget') {
            return false;
        }
        $structural = ['accordion', 'image-carousel', 'image-gallery'];
        return in_array(($element['widgetType'] ?? ''), $structural, true);
    }

    /**
     * If the parent container had an _element_id and the surviving child
     * does not already have one, copy it onto the child so the layer name
     * (CSS id) is not lost when the wrapper is removed.
     */
    private function maybe_transfer_element_id(array $parent, array &$child): void
    {
        $parent_settings = $parent['settings'] ?? new \stdClass();
        $parent_id = null;
        if ($parent_settings instanceof \stdClass) {
            $parent_id = $parent_settings->_element_id ?? null;
        } elseif (is_array($parent_settings)) {
            $parent_id = $parent_settings['_element_id'] ?? null;
        }

        if ($parent_id === null) {
            return;
        }

        $child_settings = $child['settings'] ?? null;
        if ($child_settings instanceof \stdClass) {
            if (!isset($child_settings->_element_id)) {
                $child['settings']->_element_id = $parent_id;
            }
        } elseif (is_array($child_settings)) {
            if (!isset($child_settings['_element_id'])) {
                $child['settings']['_element_id'] = $parent_id;
            }
        }
    }

    // ── GROUP Flattening ──

    /**
     * Defensive check: a GROUP is safe to flatten ONLY if it has no visible
     * fills, strokes, or effects.  Figma's UI normally prevents styling a
     * GROUP, but the data model permits it; this check prevents silent data
     * loss in that rare edge case.
     *
     * When a styled GROUP is found, logs a WARNING and returns false so the
     * node falls through to normal container conversion.
     */
    private function group_is_safe_to_flatten(array $node): bool
    {
        // Visible fills
        foreach ($node['fills'] ?? [] as $fill) {
            if (($fill['visible'] ?? true) !== false && ($fill['opacity'] ?? 1) > 0) {
                Logger::log('WARNING', 'ElementorRenderer', 'GROUP with visible fill — skipping flatten', [
                    'name' => $node['name'] ?? 'unnamed',
                    'fill_type' => $fill['type'] ?? 'unknown',
                ]);
                return false;
            }
        }

        // Visible strokes
        foreach ($node['strokes'] ?? [] as $stroke) {
            if (($stroke['visible'] ?? true) !== false) {
                Logger::log('WARNING', 'ElementorRenderer', 'GROUP with visible stroke — skipping flatten', [
                    'name' => $node['name'] ?? 'unnamed',
                ]);
                return false;
            }
        }

        // Visible effects (shadows, blurs, etc.)
        foreach ($node['effects'] ?? [] as $effect) {
            if (($effect['visible'] ?? true) !== false) {
                Logger::log('WARNING', 'ElementorRenderer', 'GROUP with visible effect — skipping flatten', [
                    'name' => $node['name'] ?? 'unnamed',
                    'effect_type' => $effect['type'] ?? 'unknown',
                ]);
                return false;
            }
        }

        return true;
    }

    // ── Helpers ──

    /**
     * Generate an 8-character hex ID (matching Elementor's format).
     */
    private function generate_id(): string
    {
        return substr(bin2hex(random_bytes(4)), 0, 8);
    }
}
