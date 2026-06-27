<?php

/**
 * Mock Figma_API that returns canned node data for snapshot tests.
 */

namespace HelloFigma;

class MockFigmaAPI extends Figma_API
{
    private array $canned_nodes;

    public function __construct(array $canned_nodes)
    {
        $this->canned_nodes = $canned_nodes;
    }

    public function get_file_nodes(string $file_key, array $node_ids): ?array
    {
        $result = ['nodes' => []];
        foreach ($node_ids as $nid) {
            if (isset($this->canned_nodes[$nid])) {
                $result['nodes'][$nid] = ['document' => $this->canned_nodes[$nid]];
            }
        }
        return $result;
    }
}
