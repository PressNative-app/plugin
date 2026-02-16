#!/usr/bin/env bash
# Watch plugin directory and sync to Local WordPress on changes.
# Requires fswatch (install via: brew install fswatch)

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

if ! command -v fswatch &> /dev/null; then
  echo "fswatch is required. Install with: brew install fswatch"
  exit 1
fi

echo "Watching $PLUGIN_DIR for changes..."
echo "Press Ctrl+C to stop."
echo ""

# Initial sync
"$SCRIPT_DIR/sync-to-local.sh"

# Watch and sync on change
fswatch -o -r "$PLUGIN_DIR" \
  --exclude '\.git' \
  --exclude 'node_modules' \
  --exclude '\.build' \
  | while read -r; do
    "$SCRIPT_DIR/sync-to-local.sh"
  done
