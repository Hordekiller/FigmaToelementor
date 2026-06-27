<?php
/**
 * Fixture data for TypeResolver tests.
 *
 * Each entry: [label, figma_node, expected_elType, expected_widgetType]
 */

return [
    'TEXT → heading' => [
        ['type' => 'TEXT', 'name' => 'Hello', 'fillOverride' => []],
        'widget', 'heading',
    ],
    'FRAME → container' => [
        ['type' => 'FRAME', 'name' => 'Section', 'fillOverride' => []],
        'container', null,
    ],
    'GROUP → container' => [
        ['type' => 'GROUP', 'name' => 'Group', 'fillOverride' => []],
        'container', null,
    ],
    'LINE → divider' => [
        ['type' => 'LINE', 'name' => 'Line', 'fillOverride' => []],
        'widget', 'divider',
    ],
    'VECTOR → image' => [
        ['type' => 'VECTOR', 'name' => 'Icon', 'fillOverride' => []],
        'widget', 'image',
    ],
    'RECTANGLE plain → container' => [
        ['type' => 'RECTANGLE', 'name' => 'Box',
         'fillOverride' => [], 'fills' => [['type' => 'SOLID', 'color' => ['r' => 1, 'g' => 0, 'b' => 0, 'a' => 1]]]],
        'container', null,
    ],
    'RECTANGLE with IMAGE fill → widget image' => [
        ['type' => 'RECTANGLE', 'name' => 'ImageBox',
         'fillOverride' => [],
         'fills' => [['type' => 'IMAGE', 'imageRef' => 'img123', 'visible' => true]]],
        'widget', 'image',
    ],
    'ELLIPSE with IMAGE fill → widget image' => [
        ['type' => 'ELLIPSE', 'name' => 'CirclePhoto',
         'fillOverride' => [],
         'fills' => [['type' => 'IMAGE', 'imageRef' => 'img456', 'visible' => true]]],
        'widget', 'image',
    ],
    'BOOLEAN_OPERATION → image' => [
        ['type' => 'BOOLEAN_OPERATION', 'name' => 'Shape', 'fillOverride' => []],
        'widget', 'image',
    ],
    'STAR → image' => [
        ['type' => 'STAR', 'name' => 'Star', 'fillOverride' => []],
        'widget', 'image',
    ],
    'POLYGON → image' => [
        ['type' => 'POLYGON', 'name' => 'Triangle', 'fillOverride' => []],
        'widget', 'image',
    ],
    'BOOLEAN with IMAGE fill → image (overrides default)' => [
        ['type' => 'BOOLEAN_OPERATION', 'name' => 'ImageShape',
         'fillOverride' => [],
         'fills' => [['type' => 'IMAGE', 'imageRef' => 'img789', 'visible' => true]]],
        'widget', 'image',
    ],
    'COMPONENT → container' => [
        ['type' => 'COMPONENT', 'name' => 'ButtonComponent', 'fillOverride' => []],
        'container', null,
    ],
    'INSTANCE → container' => [
        ['type' => 'INSTANCE', 'name' => 'CardInstance', 'fillOverride' => []],
        'container', null,
    ],
    'Unknown type → container fallback' => [
        ['type' => 'WHATEVER', 'name' => 'Unknown', 'fillOverride' => []],
        'container', null,
    ],
];
