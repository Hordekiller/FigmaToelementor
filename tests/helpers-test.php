<?php

define('ABSPATH', '/tmp');
require '/home/solo/development/Figma/includes/class-logger.php';
require '/home/solo/development/Figma/includes/class-positioning.php';
require '/home/solo/development/Figma/includes/class-styleextractor.php';
require '/home/solo/development/Figma/includes/class-layoutextractor.php';

use HelloFigma\Positioning;
use HelloFigma\StyleExtractor;

$style = new StyleExtractor();
$pos = new Positioning();
$passed = 0;
$total = 0;

function t(string $label, bool $cond, int &$p, int &$t): void
{
    $t++;
    if ($cond) {
        $p++;
        echo "  PASS: $label\n";
    } else {
        echo "  FAIL: $label\n";
    }
}

echo "--- rgba_to_hex ---\n";
t('black', $style->rgba_to_hex(['r' => 0,'g' => 0,'b' => 0,'a' => 1])        === '#000000', $passed, $total);
t('white', $style->rgba_to_hex(['r' => 1,'g' => 1,'b' => 1,'a' => 1])        === '#ffffff', $passed, $total);
t('red', $style->rgba_to_hex(['r' => 1,'g' => 0,'b' => 0,'a' => 1])        === '#ff0000', $passed, $total);
t('half green', $style->rgba_to_hex(['r' => 0,'g' => 0.5,'b' => 0,'a' => 1])       === '#008000', $passed, $total); // 0.5*255=127.5→128 round
t('with alpha', $style->rgba_to_hex(['r' => 1,'g' => 0,'b' => 0,'a' => 0.5])      === '#ff000080', $passed, $total);
t('full alpha', $style->rgba_to_hex(['r' => 1,'g' => 1,'b' => 1,'a' => 0.0039])   === '#ffffff01', $passed, $total);
t('missing r', $style->rgba_to_hex(['g' => 1,'b' => 0,'a' => 1])                === '#00ff00', $passed, $total);
t('empty array', $style->rgba_to_hex([])                                     === '#000000', $passed, $total);

echo "--- detect_heading_level ---\n";
t('fontSize 48 → h1', $style->detect_heading_level(['fontSize' => 48])  === 'h1', $passed, $total);
t('fontSize 72 → h1', $style->detect_heading_level(['fontSize' => 72])  === 'h1', $passed, $total);
t('fontSize 36 → h2', $style->detect_heading_level(['fontSize' => 36])  === 'h2', $passed, $total);
t('fontSize 28 → h3', $style->detect_heading_level(['fontSize' => 28])  === 'h3', $passed, $total);
t('fontSize 20 → h4', $style->detect_heading_level(['fontSize' => 20])  === 'h4', $passed, $total);
t('fontSize 16 → h5', $style->detect_heading_level(['fontSize' => 16])  === 'h5', $passed, $total);
t('fontSize 10 → h6', $style->detect_heading_level(['fontSize' => 10])  === 'h6', $passed, $total);
t('no fontSize → h5 def (default 16)', $style->detect_heading_level([]) === 'h5', $passed, $total);

echo "--- map_align ---\n";
t('MIN → flex-start', $pos->map_align('MIN')           === 'flex-start', $passed, $total);
t('CENTER → center', $pos->map_align('CENTER')        === 'center', $passed, $total);
t('MAX → flex-end', $pos->map_align('MAX')           === 'flex-end', $passed, $total);
t('STRETCH → stretch', $pos->map_align('STRETCH')       === 'stretch', $passed, $total);
t('SPACE_BETWEEN → …', $pos->map_align('SPACE_BETWEEN') === 'space-between', $passed, $total);
t('unknown → flex-start', $pos->map_align('INVALID')       === 'flex-start', $passed, $total);
t('empty → flex-start', $pos->map_align('')              === 'flex-start', $passed, $total);

echo "--- RTL compatibility (map_align) ---\n";
// flex-start/flex-end are CSS logical properties that respect dir="rtl"
// MIN (min extent) = flex-start (logical start) = right in RTL → correct
// MAX (max extent) = flex-end (logical end) = left in RTL → correct
t('MIN is logical start (RTL-safe)', $pos->map_align('MIN') === 'flex-start', $passed, $total);
t('MAX is logical end (RTL-safe)', $pos->map_align('MAX') === 'flex-end', $passed, $total);
t('CENTER is dir-agnostic (RTL-safe)', $pos->map_align('CENTER') === 'center', $passed, $total);

echo "--- extract_background (gradient types) ---\n";
$s_grad_linear = new \stdClass();
$fill_linear = [
    'type' => 'GRADIENT_LINEAR', 'visible' => true, 'opacity' => 1,
    'gradientStops' => [
        ['position' => 0, 'color' => ['r' => 1, 'g' => 0, 'b' => 0, 'a' => 1]],
        ['position' => 1, 'color' => ['r' => 0, 'g' => 0, 'b' => 1, 'a' => 1]],
    ],
    'gradientHandlePositions' => [['x' => 0, 'y' => 1], ['x' => 1, 'y' => 0]],
];
$style->extract_background(['id' => 'n1', 'fills' => [$fill_linear], 'name' => 'GradBox'], $s_grad_linear);
t('GRADIENT_LINEAR → background=gradient', ($s_grad_linear->background_background ?? '') === 'gradient', $passed, $total);
t('GRADIENT_LINEAR → gradient_type=linear', ($s_grad_linear->background_gradient_type ?? '') === 'linear', $passed, $total);
t('GRADIENT_LINEAR → color stop 0 set', isset($s_grad_linear->background_gradient_color_0), $passed, $total);

$s_default = new \stdClass();
$style->extract_background(['id' => 'n2', 'fills' => [['type' => 'EMOJI', 'visible' => true, 'opacity' => 1]], 'name' => 'EmojiFill'], $s_default);
t('EMOJI unknown type → no background set', !isset($s_default->background_background), $passed, $total);

echo "--- extract_border (per-side stroke weight) ---\n";
// Figma per-side fields on node directly
$s_stroke = new \stdClass();
$stroke_node = [
    'id' => 'n3',
    'strokes' => [['visible' => true, 'color' => ['r' => 1, 'g' => 0, 'b' => 0, 'a' => 1]]],
    'strokeWeight' => null,
    'strokeTopWeight' => 5,
    'strokeRightWeight' => 3,
    'strokeBottomWeight' => 5,
    'strokeLeftWeight' => 3,
];
$style->extract_border($stroke_node, $s_stroke);
t('per-side stroke top=5', (($s_stroke->border_width ?? null)?->top ?? 0) === 5, $passed, $total);
t('per-side stroke right=3', (($s_stroke->border_width ?? null)?->right ?? 0) === 3, $passed, $total);
t('per-side stroke not linked', (($s_stroke->border_width ?? null)?->isLinked ?? true) === false, $passed, $total);

// Numeric strokeWeight fallback
$s_stroke2 = new \stdClass();
$border_node2 = ['id' => 'n4', 'strokeWeight' => 2,
    'strokes' => [['visible' => true, 'color' => ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 1]]],
];
$style->extract_border($border_node2, $s_stroke2);
t('numeric strokeWeight=2', (($s_stroke2->border_width ?? null)?->top ?? 0) === 2, $passed, $total);
t('numeric strokeWeight isLinked', (($s_stroke2->border_width ?? null)?->isLinked ?? false) === true, $passed, $total);

echo "--- extract_container_layout (partial padding) ---\n";
$s_pad = new \stdClass();
$style_extractor = new StyleExtractor();
$pos = new Positioning();
$layout_extractor = new \HelloFigma\LayoutExtractor($pos);
$layout_extractor->extract_container_layout(['id' => 'n5', 'paddingTop' => 20, 'paddingBottom' => 10], $s_pad);
t('partial padding top=20', (($s_pad->padding ?? null)?->top ?? 0) === 20, $passed, $total);
t('partial padding bottom=10', (($s_pad->padding ?? null)?->bottom ?? 0) === 10, $passed, $total);
t('partial padding right filled from fallback', (($s_pad->padding ?? null)?->right === 0 || ($s_pad->padding ?? null)?->right === 20), $passed, $total);

echo "\n--- RESULTS: $passed/$total ---\n";
