#!/bin/bash
# Run all unit tests
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(dirname "$DIR")"

echo "========================================"
echo "  Figma Plugin — Unit Test Suite"
echo "========================================"
echo ""

TESTS_PASSED=0
TESTS_FAILED=0
FAILED_NAMES=""

run_test() {
    local test_file="$1"
    local test_name="$2"
    echo "--- $test_name ---"
    if output=$(php -d memory_limit=256M "$test_file" 2>&1); then
        echo "$output" | grep -v "^$"
        if echo "$output" | grep -qi "FAIL"; then
            echo "  ❌ FAILED"
            TESTS_FAILED=$((TESTS_FAILED + 1))
            FAILED_NAMES="$FAILED_NAMES  - $test_name\n"
        else
            echo "  ✅ PASSED"
            TESTS_PASSED=$((TESTS_PASSED + 1))
        fi
    else
        echo "  💥 CRASHED"
        echo "$output" | head -5
        TESTS_FAILED=$((TESTS_FAILED + 1))
        FAILED_NAMES="$FAILED_NAMES  - $test_name (crash)\n"
    fi
    echo ""
}

run_test "$DIR/helpers-test.php" "Helper functions (rgba_to_hex, detect_heading_level, map_align)"
run_test "$DIR/type-resolver-test.php" "TypeResolver (12 Figma → Elementor mappings)"
run_test "$DIR/widget-converters-test.php" "WidgetConverters (carousel/accordion/gallery)"
run_test "$DIR/json-normalizer-test.php" "JsonNormalizer (normalize + validate)"
run_test "$DIR/snapshot-test.php" "Snapshot (6 golden scenarios)"

echo "========================================"
echo "  Results: $TESTS_PASSED passed, $TESTS_FAILED failed"
echo "========================================"

if [ "$TESTS_FAILED" -gt 0 ]; then
    echo ""
    echo "Failed tests:"
    printf "$FAILED_NAMES"
    exit 1
fi
exit 0
