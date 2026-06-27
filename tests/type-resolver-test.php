<?php
define('ABSPATH', '/tmp');
require '/home/solo/development/Figma/includes/class-logger.php';
require '/home/solo/development/Figma/includes/class-styleextractor.php';
require '/home/solo/development/Figma/includes/class-typeresolver.php';

use HelloFigma\StyleExtractor;
use HelloFigma\TypeResolver;

$style = new StyleExtractor();
$resolver = new TypeResolver($style);
$fixtures = require '/home/solo/development/Figma/tests/fixtures/type-resolver-inputs.php';
$passed = 0;
$total = 0;

function t(string $label, bool $cond, int &$p, int &$t): void {
    $t++;
    if ($cond) { $p++; echo "  PASS: $label\n"; }
    else { echo "  FAIL: $label\n"; }
}

echo "--- TypeResolver::resolve_type ---\n";
foreach ($fixtures as $label => [$node, $expected_elType, $expected_widgetType]) {
    [$elType, $widgetType] = $resolver->resolve_type($node);
    t("$label → elType=$expected_elType", $elType === $expected_elType, $passed, $total);
    // Skip widgetType check for containers (may be empty string vs null — both acceptable)
    if ($expected_widgetType !== null) {
        t("$label → widgetType=$expected_widgetType", $widgetType === $expected_widgetType, $passed, $total);
    }
}

echo "\n--- RESULTS: $passed/$total ---\n";
