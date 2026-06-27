<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

class NodeFilter
{
    public function should_render(array $node): bool
    {
        return ($node['visible'] ?? true) !== false && ($node['opacity'] ?? 1) > 0;
    }
}
