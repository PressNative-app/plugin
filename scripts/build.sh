#!/usr/bin/env bash
# Build a WordPress-ready plugin zip (pressnative-engine.zip) at project root.
# Excludes development files: .git, node_modules, tests.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
ROOT="$(cd "$PLUGIN_DIR/.." && pwd)"
OUT_ZIP="$ROOT/pressnative-engine.zip"
STAGING="$ROOT/.build/pressnative-engine"

echo "Building plugin zip..."
rm -rf "$STAGING" "$OUT_ZIP"
mkdir -p "$STAGING"

# Copy plugin files, excluding dev-only paths
rsync -a "$PLUGIN_DIR/" "$STAGING/" \
  --exclude '.git' \
  --exclude 'node_modules' \
  --exclude 'tests' \
  --exclude 'scripts' \
  --exclude '.build' \
  --exclude '*.zip'

# Create zip with single top-level folder (required for WordPress upload)
(cd "$ROOT/.build" && zip -r "$OUT_ZIP" pressnative-engine -x "*.DS_Store")

# Cleanup staging
rm -rf "$ROOT/.build"

echo "Created: $OUT_ZIP"
