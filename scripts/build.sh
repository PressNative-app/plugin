#!/usr/bin/env bash
# Build a WordPress-ready plugin zip (pressnative.zip) at project root.
# The zip contains a single top-level folder named "pressnative" matching
# the plugin slug required by WordPress.org.

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
ROOT="$(cd "$PLUGIN_DIR/.." && pwd)"
OUT_ZIP="$ROOT/pressnative.zip"
STAGING="$ROOT/.build/pressnative"

echo "Building plugin zip..."
rm -rf "$STAGING" "$OUT_ZIP"
mkdir -p "$STAGING"

# Copy plugin files, excluding dev-only paths and anything not needed for WordPress.org
rsync -a "$PLUGIN_DIR/" "$STAGING/" \
  --exclude '.git' \
  --exclude '.github' \
  --exclude '.env' \
  --exclude '*.env' \
  --exclude 'node_modules' \
  --exclude 'tests' \
  --exclude 'scripts' \
  --exclude '.build' \
  --exclude '*.zip' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude 'README.md' \
  --exclude 'RELEASE_NOTES*.md' \
  --exclude 'pressnative-app' \
  --exclude '.DS_Store' \
  --exclude '.gitignore' \
  --exclude 'docs' \
  --exclude 'test-*.php'

# Create zip with single top-level folder (required for WordPress upload)
(cd "$ROOT/.build" && zip -r "$OUT_ZIP" pressnative -x "*.DS_Store")

# Cleanup staging
rm -rf "$ROOT/.build"

echo "Created: $OUT_ZIP"
echo ""
echo "Contents:"
unzip -l "$OUT_ZIP" | tail -5
