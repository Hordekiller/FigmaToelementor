<?php

define('ABSPATH', '/tmp');
require '/home/solo/development/Figma/includes/class-logger.php';
require '/home/solo/development/Figma/includes/class-styleextractor.php';
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

echo "\n--- RESULTS: $passed/$total ---\n";
