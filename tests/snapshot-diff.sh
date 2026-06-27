#!/bin/bash
# Compare current converter output against golden snapshots.
#
# Usage:
#   ./tests/snapshot-diff.sh              # show any differences
#   ./tests/snapshot-diff.sh --update     # overwrite golden files with current output
#   ./tests/snapshot-diff.sh --diff-only  # compare golden without running
#   ./tests/snapshot-diff.sh --save-only  # just save new golden files
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"

FLAGS="$@"

echo "=== Snapshot Diff ==="
echo ""

php "$DIR/snapshot-test.php" $FLAGS

echo ""
if [ "$?" -eq 0 ]; then
    if [[ " $FLAGS " =~ " --update " ]]; then
        echo "All golden files updated.  Run without --update to verify."
    else
        echo "All golden files match. ✓"
    fi
fi
