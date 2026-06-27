<?php

define('ABSPATH', '/tmp');
require '/home/solo/development/Figma/includes/class-logger.php';
require '/home/solo/development/Figma/includes/class-jsonnormalizer.php';

use HelloFigma\JsonNormalizer;

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

echo "--- validate_template (ok/errors return) ---\n";
$v1 = JsonNormalizer::validate_template(['title' => 'T','type' => 'page','version' => '0.4','content' => [],'page_settings' => []]);
t('valid → ok=true', $v1['ok'] === true, $passed, $total);
t('valid → 0 errors', count($v1['errors']) === 0, $passed, $total);

$v2 = JsonNormalizer::validate_template([]);
t('empty → ok=false', $v2['ok'] === false, $passed, $total);
t('empty → has errors', count($v2['errors']) > 0, $passed, $total);

$v3 = JsonNormalizer::validate_template(['type' => 'page','version' => '0.4','content' => [],'page_settings' => []]);
t('missing title → critical (ok=false)', $v3['ok'] === false, $passed, $total);

echo "--- Widget type validation ---\n";
$v4 = JsonNormalizer::validate_template([
    'title' => 'T','type' => 'page','version' => '0.4',
    'content' => [['id' => 'abc','elType' => 'widget','settings' => new stdClass(),'elements' => [],'isInner' => false]],
    'page_settings' => []
]);
$has_wt = count(array_filter($v4['errors'], fn($e) => str_contains($e, 'widgetType'))) > 0;
t('widget missing widgetType → warning', $has_wt, $passed, $total);
t('widget issue → still ok=true', $v4['ok'] === true, $passed, $total);

$v5 = JsonNormalizer::validate_template([
    'title' => 'T','type' => 'page','version' => '0.4',
    'content' => [['id' => 'abc','elType' => 'widget','widgetType' => 'heading','settings' => new stdClass(),'elements' => [],'isInner' => false]],
    'page_settings' => []
]);
$no_wt = count(array_filter($v5['errors'], fn($e) => str_contains($e, 'widgetType'))) === 0;
t('widget with widgetType → no warning', $no_wt, $passed, $total);

echo "--- normalize_template ---\n";
$n1 = JsonNormalizer::normalize_template(['title' => 'T','type' => 'page','version' => '0.4','content' => [],'page_settings' => []]);
t('title preserved', $n1['title'] === 'T', $passed, $total);
t('content is array', is_array($n1['content']), $passed, $total);

$n2 = JsonNormalizer::normalize_template([
    'title' => 'T','type' => 'page','version' => '0.4',
    'content' => [['id' => 'x','elType' => 'container','isInner' => false,'settings' => ['foo' => 'bar'],'elements' => []]],
    'page_settings' => []
]);
t('settings array→object', $n2['content'][0]['settings'] instanceof stdClass, $passed, $total);
t('settings value preserved', $n2['content'][0]['settings']->foo === 'bar', $passed, $total);

echo "--- normalize_settings_object ---\n";
// Test via normalize_template (private method)
$n3 = JsonNormalizer::normalize_template([
    'title' => 'T','type' => 'page','version' => '0.4',
    'content' => [['id' => 'x','elType' => 'container','isInner' => false,'settings' => ['padding' => ['unit' => 'px','top' => '10']],'elements' => []]],
    'page_settings' => []
]);
t('nested padding→object', $n3['content'][0]['settings']->padding instanceof stdClass, $passed, $total);
t('nested value preserved', $n3['content'][0]['settings']->padding->top === '10', $passed, $total);

echo "--- Missing keys ---\n";
$n4 = JsonNormalizer::normalize_template([
    'title' => 'T','type' => 'page','version' => '0.4',
    'content' => [['id' => 'x','elType' => 'container','settings' => new stdClass(),'elements' => []]],
    'page_settings' => []
]);
t('isInner filled', is_bool($n4['content'][0]['isInner']), $passed, $total);
// widgetType not filled on non-widget (correct behavior — only validated for widgets)
t('widgetType absent on container', !isset($n4['content'][0]['widgetType']), $passed, $total);

echo "\n--- RESULTS: $passed/$total ---\n";
