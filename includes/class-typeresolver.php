<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class TypeResolver
{
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

    private StyleExtractor $style_extractor;

    public function __construct(StyleExtractor $style_extractor)
    {
        $this->style_extractor = $style_extractor;
    }

    public function resolve_type(array $node): array
    {
        $figma_type = $node['type'] ?? '';

        if (in_array($figma_type, ['RECTANGLE', 'ELLIPSE', 'BOOLEAN_OPERATION', 'STAR', 'POLYGON'], true)) {
            $fill = $this->style_extractor->get_visible_fill($node);
            if ($fill !== null && ($fill['type'] ?? '') === 'IMAGE') {
                return ['widget', 'image'];
            }
        }

        if (in_array($figma_type, ['BOOLEAN_OPERATION', 'STAR', 'POLYGON'], true)) {
            return ['widget', 'image'];
        }

        return self::NODE_MAP[$figma_type] ?? ['container', null];
    }
}
