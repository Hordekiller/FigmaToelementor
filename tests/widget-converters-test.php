<?php

define('ABSPATH', '/tmp');
require '/home/solo/development/Figma/includes/class-logger.php';
require '/home/solo/development/Figma/includes/class-styleextractor.php';
require '/home/solo/development/Figma/includes/class-positioning.php';
require '/home/solo/development/Figma/includes/class-layoutextractor.php';
require '/home/solo/development/Figma/includes/class-widgetconverters.php';

use HelloFigma\StyleExtractor;
use HelloFigma\WidgetConverters;

$style = new StyleExtractor();
$id_gen = fn(): string => 'test0001';
$conv = new WidgetConverters($style, $id_gen);
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

echo "--- Carousel detection ---\n";
// Zero children → null
t('empty nodes → null', $conv->try_build_carousel(['children' => []], 'carousel') === null, $passed, $total);
// One child → null (needs ≥2)
t('1 child → null', $conv->try_build_carousel(['children' => [['type' => 'RECTANGLE']]], 'carousel') === null, $passed, $total);
// 2 children with images → carousel
$result = $conv->try_build_carousel([
    'id' => 'car1', 'name' => 'Slider',
    'type' => 'COMPONENT',
    'children' => [
        ['id' => 'ch1', 'type' => 'RECTANGLE', 'fills' => [['type' => 'IMAGE', 'imageRef' => 'img1', 'visible' => true]]],
        ['id' => 'ch2', 'type' => 'RECTANGLE', 'fills' => [['type' => 'IMAGE', 'imageRef' => 'img2', 'visible' => true]]],
    ],
], 'carousel');
t('2 image children → carousel', $result !== null, $passed, $total);
if ($result !== null) {
    t('carousel elType=widget', $result['elType'] === 'widget', $passed, $total);
    t('carousel widgetType', $result['widgetType'] === 'image-carousel', $passed, $total);
    t('carousel has slides', isset($result['settings']->carousel) && count($result['settings']->carousel) === 2, $passed, $total);
}

echo "--- Accordion detection ---\n";
// Zero children → null
t('empty → null', $conv->try_build_accordion(['children' => []]) === null, $passed, $total);
// 1 child → null (needs ≥1 but text check filters)
t('1 child without text → null', $conv->try_build_accordion([
    'children' => [['type' => 'RECTANGLE', 'children' => []]]
]) === null, $passed, $total);

echo "--- Gallery detection ---\n";
// Zero children → null
t('empty → null', $conv->try_build_gallery(['children' => []]) === null, $passed, $total);
// 1 child → null (needs ≥2)
t('1 child → null', $conv->try_build_gallery(['children' => [['type' => 'RECTANGLE']]]) === null, $passed, $total);
// 2 children without images → null (ratio < 0.7)
$result2 = $conv->try_build_gallery([
    'name' => 'Gallery Test',
    'children' => [
        ['type' => 'RECTANGLE'],
        ['type' => 'RECTANGLE'],
    ],
]);
t('2 children no images → null (ratio 0/2=0 < 0.7)', $result2 === null, $passed, $total);

echo "--- Social icons detection ---\n";
$social = $conv->try_build_social_icons([
    'name' => 'Social',
    'children' => [
        ['id' => 's1', 'name' => 'Instagram', 'type' => 'VECTOR'],
        ['id' => 's2', 'name' => 'Facebook', 'type' => 'VECTOR'],
    ],
], 'social');
t('2 icon-like children → social icons', $social !== null, $passed, $total);
if ($social !== null) {
    t('social widgetType', $social['widgetType'] === 'social-icons', $passed, $total);
    t('social repeater key', isset($social['settings']->social_icon_list) && count($social['settings']->social_icon_list) === 2, $passed, $total);
    t('social icon field present', isset($social['settings']->social_icon_list[0]['social_icon']['value']), $passed, $total);
    t('social link field present', isset($social['settings']->social_icon_list[0]['link']['url']), $passed, $total);
}

echo "--- Stats parsing ---\n";
t('$2.5M → 2.5M', $conv->parse_stat_value('$2.5M') === '2.5M', $passed, $total);
t('98% → 98%', $conv->parse_stat_value('98%') === '98%', $passed, $total);
t('1,200 → 1200', $conv->parse_stat_value('1,200') === '1200', $passed, $total);
t('plain 42 → 42', $conv->parse_stat_value('plain 42') === '42', $passed, $total);
t('non-numeric → null', $conv->parse_stat_value('hello world') === null, $passed, $total);

echo "--- Stats parsing — regression edge cases ---\n";
// EU format: dotted thousands should return null (ambiguous)
t('EU 12.345.678 → null (ambiguous)', $conv->parse_stat_value('12.345.678') === null, $passed, $total);
// Negative numbers
t('-5 → -5', $conv->parse_stat_value('-5') === '-5', $passed, $total);
t('−42 → -42 (unicode minus)', $conv->parse_stat_value('−42') === '-42', $passed, $total);
// Suffix on word boundary
t('98% off → 98% (% followed by space)', $conv->parse_stat_value('98% off') === '98%', $passed, $total);
t('100k followers → 100k', $conv->parse_stat_value('100k followers') === '100k', $passed, $total);
// Empty string
t('empty string → null', $conv->parse_stat_value('') === null, $passed, $total);

echo "--- Social icons — platform matcher edge cases ---\n";
// 'x' matcher should NOT match "Box" or "Next"
$social_no_x = $conv->try_build_social_icons([
    'name' => 'Social',
    'children' => [
        ['id' => 's1', 'name' => 'Box', 'type' => 'VECTOR'],
        ['id' => 's2', 'name' => 'Next', 'type' => 'VECTOR'],
    ],
], 'social');
$got = $social_no_x['settings']->social_icon_list[0]['social_icon']['value'] ?? '';
t('Box → not matched as twitter', $social_no_x === null || $got !== 'fab fa-x-twitter', $passed, $total);
// Actual exact name 'x' should match twitter
$social_exact_x = $conv->try_build_social_icons([
    'name' => 'Social',
    'children' => [
        ['id' => 's1', 'name' => 'X', 'type' => 'VECTOR'],
        ['id' => 's2', 'name' => 'Instagram', 'type' => 'VECTOR'],
    ],
], 'social');
$got = $social_exact_x['settings']->social_icon_list[0]['social_icon']['value'] ?? '';
t('exact "X" → matches twitter', $social_exact_x !== null && $got === 'fab fa-x-twitter', $passed, $total);
// 'x' as standalone word in name
$social_x_word = $conv->try_build_social_icons([
    'name' => 'Social',
    'children' => [
        ['id' => 's1', 'name' => 'x icon', 'type' => 'VECTOR'],
        ['id' => 's2', 'name' => 'twitter', 'type' => 'VECTOR'],
    ],
], 'social');
$got = $social_x_word['settings']->social_icon_list[0]['social_icon']['value'] ?? '';
t('word "x icon" → matches twitter', $social_x_word !== null && $got === 'fab fa-x-twitter', $passed, $total);

echo "--- LayoutExtractor partial padding (regression) ---\n";
$layout_extractor = new \HelloFigma\LayoutExtractor(new \HelloFigma\Positioning());
$s_partial = new \stdClass();
$layout_extractor->extract_container_layout(['id' => 'n', 'paddingTop' => 20, 'paddingBottom' => 10], $s_partial);
t('partial pad top=20', ($s_partial->padding->top ?? 0) === 20, $passed, $total);
t('partial pad bottom=10', ($s_partial->padding->bottom ?? 0) === 10, $passed, $total);
t('partial pad right filled', ($s_partial->padding->right === 0 || $s_partial->padding->right === 20), $passed, $total);

echo "\n--- RESULTS: $passed/$total ---\n";
