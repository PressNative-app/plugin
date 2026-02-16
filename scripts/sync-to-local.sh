#!/usr/bin/env bash
# Sync PressNative plugin to Local WordPress site for testing.
# Target: ~/Local Sites/ctst-local-demo/app/public/wp-content/plugins/pressnative-engine

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
LOCAL_PLUGINS="${HOME}/Local Sites/ctst-local-demo/app/public/wp-content/plugins"
TARGET="${LOCAL_PLUGINS}/pressnative-engine"

if [[ ! -d "$LOCAL_PLUGINS" ]]; then
  echo "Error: Local WordPress plugins dir not found: $LOCAL_PLUGINS"
  exit 1
fi

echo "Syncing plugin to Local WordPress..."
rsync -av --delete \
  --exclude '.git' \
  --exclude 'node_modules' \
  --exclude 'tests' \
  --exclude '.build' \
  --exclude '*.zip' \
  "$PLUGIN_DIR/" "$TARGET/"

echo "Done. Plugin synced to: $TARGET"
