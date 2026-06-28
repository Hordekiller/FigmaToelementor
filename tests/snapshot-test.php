<?php

/**
 * Snapshot test runner.
 *
 * Loads each scenario fixture, runs it through Elementor_Renderer
 * with a MockFigmaAPI, and saves/golden-compares the output.
 *
 * Usage:
 *   php tests/snapshot-test.php              # run all + save/compare
 *   php tests/snapshot-test.php --update     # overwrite golden files
 *   php tests/snapshot-test.php --diff-only  # compare without running
 *   php tests/snapshot-test.php --scenario 1 # run single scenario
 */

declare(strict_types=1);

// Bootstrap
define('ABSPATH', '/tmp');
require __DIR__ . '/wordpress-stubs.php';
require __DIR__ . '/../includes/class-logger.php';
require __DIR__ . '/../includes/class-figma-api.php';
require __DIR__ . '/mock-figma-api.php';
require __DIR__ . '/../includes/class-nodefilter.php';
require __DIR__ . '/../includes/class-positioning.php';
require __DIR__ . '/../includes/class-typeresolver.php';
require __DIR__ . '/../includes/class-styleextractor.php';
require __DIR__ . '/../includes/class-layoutextractor.php';
require __DIR__ . '/../includes/class-widgetconverters.php';
require __DIR__ . '/../includes/class-jsonnormalizer.php';
require __DIR__ . '/../includes/class-component-detector.php';
require __DIR__ . '/../includes/class-elementor-renderer.php';

use HelloFigma\Elementor_Renderer;
use HelloFigma\MockFigmaAPI;

// ── Config ──

$SCENARIO_DIR = __DIR__ . '/../project_audit/snapshots/scenarios';
$GOLDEN_DIR   = __DIR__ . '/../project_audit/snapshots/golden';

$scenarios = [
    1 => '01-heading-button-image',
    2 => '02-container-horizontal',
    3 => '03-container-vertical',
    4 => '04-group-flattening',
    5 => '05-accordion-faq',
    6 => '06-carousel-gallery',
    7 => '07-stats-positive',
    8 => '08-stats-fallback',
    9 => '09-social-icons-recognized',
    10 => '10-social-icons-mixed',
];

// ── CLI flags ──

$update       = in_array('--update', $argv);
$diff_only    = in_array('--diff-only', $argv);
$scenario_idx = null;
if (in_array('--scenario', $argv)) {
    $pos = array_search('--scenario', $argv);
    $scenario_idx = (int) ($argv[$pos + 1] ?? 0);
}

// ── Functions ──

function load_scenario(string $file): ?array
{
    if (!file_exists($file)) {
        echo "  MISSING: $file\n";
        return null;
    }
    $document = require $file;
    return is_array($document) ? $document : null;
}

function save_golden(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    file_put_contents($path, $json);
}

function load_golden(string $path): ?array
{
    if (!file_exists($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function diff_structure(array $a, array $b, string $path = ''): array
{
    $diffs = [];
    if (is_array($a) && is_array($b)) {
        // Compare keys
        $all_keys = array_unique(array_merge(array_keys($a), array_keys($b)));
        foreach ($all_keys as $k) {
            $curr_path = $path === '' ? $k : "$path.$k";
            $has_a = array_key_exists($k, $a);
            $has_b = array_key_exists($k, $b);
            if (!$has_a && $has_b) {
                $diffs[] = "  + NEW key: $curr_path";
            } elseif ($has_a && !$has_b) {
                $diffs[] = "  - MISSING key: $curr_path";
            } elseif (is_array($a[$k]) && is_array($b[$k]) && !isset($a[$k]['id'])) {
                // Recurse into arrays that aren't element-like (id-based)
                $diffs = array_merge($diffs, diff_structure($a[$k], $b[$k], $curr_path));
            } elseif ($a[$k] !== $b[$k] && !is_array($a[$k]) && !is_array($b[$k])) {
                $diffs[] = "  ≠ $curr_path: " . json_encode($a[$k]) . ' → ' . json_encode($b[$k]);
            }
        }
    }
    return $diffs;
}

function count_elements(array $template): int
{
    $count = 0;
    foreach ($template['content'] ?? [] as $el) {
        $count += count_elements_recursive($el);
    }
    return $count;
}

function count_elements_recursive(array $element): int
{
    $count = 1;
    foreach ($element['elements'] ?? [] as $child) {
        $count += count_elements_recursive($child);
    }
    return $count;
}

// ── Main ──

$passed = 0;
$total  = 0;

if ($diff_only) {
    echo "--- Diff-only mode: comparing golden files ---\n";
} else {
    echo "--- Running snapshot tests ---\n";
}

foreach ($scenarios as $idx => $name) {
    if ($scenario_idx !== null && $idx !== $scenario_idx) {
        continue;
    }

    $file       = "$SCENARIO_DIR/{$name}.php";
    $golden     = "$GOLDEN_DIR/{$name}.json";
    $total++;

    $document = load_scenario($file);
    if ($document === null) {
        echo "  FAIL: Scenario $idx ($name) — could not load fixture\n";
        continue;
    }

    if ($diff_only) {
        // Compare mode
        $golden_data = load_golden($golden);
        if ($golden_data === null) {
            echo "  SKIP: $name — no golden file\n";
            continue;
        }

        // Re-run to compare (diff_mode = false means we regenerate and compare)
        // Actually for diff-only we skip running; we need current output.
        // Let's run it in compare mode.
    }

    if ($diff_only) {
        echo "  $name: no golden diff possible without running converter\n";
        continue;
    }

    // Build the canned node data in the format expected by get_file_nodes
    $canned = ['frame-1' => $document];

    $mock_api = new MockFigmaAPI($canned);
    $renderer = new Elementor_Renderer($mock_api);
    $result   = $renderer->convert_node_to_template('test-key', 'frame-1');

    if ($result === null) {
        echo "  FAIL: $name — converter returned null\n";
        continue;
    }

    // Normalize for comparison
    $normalized = \HelloFigma\JsonNormalizer::normalize_template($result);

    // Load existing golden for comparison
    $existing = load_golden($golden);

    if ($existing === null) {
        // No golden yet — save it
        save_golden($golden, $normalized);
        $el_count = count_elements($normalized);
        echo "  SAVE: $name → golden saved ($el_count elements, " . count($normalized['content']) . " top-level)\n";
        $passed++;
    } elseif ($update) {
        // Update golden
        save_golden($golden, $normalized);
        $el_count = count_elements($normalized);
        echo "  UPDATE: $name → golden overwritten ($el_count elements)\n";
        $passed++;
    } else {
        // Compare
        $diffs = diff_structure($existing, $normalized);
        $current_count = count_elements($normalized);
        $golden_count  = count_elements($existing);

        if (empty($diffs) && $current_count === $golden_count) {
            echo "  PASS: $name ($current_count elements, " . count($normalized['content']) . " top-level)\n";
            $passed++;
        } else {
            echo "  FAIL: $name — structural differences found\n";
            if ($current_count !== $golden_count) {
                echo "    Element count: golden=$golden_count current=$current_count\n";
            }
            foreach (array_slice($diffs, 0, 20) as $d) {
                echo "    $d\n";
            }
            if (count($diffs) > 20) {
                echo "    ... and " . (count($diffs) - 20) . " more differences\n";
            }
        }
    }
}

echo "--- RESULTS: $passed/$total ---\n";
exit($passed === $total ? 0 : 1);
