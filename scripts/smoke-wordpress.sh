#!/usr/bin/env bash
# Install a throwaway WordPress (SQLite), activate this plugin, and load
# wp-admin plus the REST API. Fails the build if anything fatals.
#
# Usage: scripts/smoke-wordpress.sh [plugin-dir]
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="${1:-$(cd "$SCRIPT_DIR/.." && pwd)}"
SLUG="pressnative-apps"
WP_DIR="$(mktemp -d)"
trap 'rm -rf "$WP_DIR"' EXIT

if ! command -v php >/dev/null; then
  echo "::error::php is required"
  exit 1
fi

# Checked without a pipe on purpose: `php -m | grep -q` races with pipefail
# because grep closes the pipe and php exits on SIGPIPE.
if ! php -r 'exit( extension_loaded( "pdo_sqlite" ) ? 0 : 1 );'; then
  echo "::error::php pdo_sqlite extension is required"
  exit 1
fi

WP_CLI="$WP_DIR/wp-cli.phar"
if command -v wp >/dev/null; then
  WP_CLI="$(command -v wp)"
else
  curl -sSL -o "$WP_CLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
fi
wpcli() { php "$WP_CLI" --path="$WP_DIR" --allow-root "$@"; }

# Pin WP_VERSION when testing an older PHP that current WordPress has dropped.
WP_VERSION="${WP_VERSION:-latest}"
echo "Downloading WordPress ${WP_VERSION} into $WP_DIR"
if [ "$WP_VERSION" = "latest" ]; then
  wpcli core download --skip-content --quiet
else
  wpcli core download --version="$WP_VERSION" --skip-content --quiet
fi
mkdir -p "$WP_DIR/wp-content/plugins" "$WP_DIR/wp-content/themes"

curl -sSL -o "$WP_DIR/sqlite.zip" https://downloads.wordpress.org/plugin/sqlite-database-integration.zip
unzip -qo "$WP_DIR/sqlite.zip" -d "$WP_DIR/wp-content/plugins"
cp "$WP_DIR/wp-content/plugins/sqlite-database-integration/db.copy" "$WP_DIR/wp-content/db.php"

curl -sSL -o "$WP_DIR/theme.zip" https://downloads.wordpress.org/theme/twentytwentyfour.zip
unzip -qo "$WP_DIR/theme.zip" -d "$WP_DIR/wp-content/themes"

cat > "$WP_DIR/wp-config.php" <<'PHPEOF'
<?php
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'wordpress' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'AUTH_KEY', 'smoke1' );
define( 'SECURE_AUTH_KEY', 'smoke2' );
define( 'LOGGED_IN_KEY', 'smoke3' );
define( 'NONCE_KEY', 'smoke4' );
define( 'AUTH_SALT', 'smoke5' );
define( 'SECURE_AUTH_SALT', 'smoke6' );
define( 'LOGGED_IN_SALT', 'smoke7' );
define( 'NONCE_SALT', 'smoke8' );
$table_prefix = 'wp_';
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
PHPEOF

wpcli core install \
  --url=http://localhost \
  --title="PressNative Smoke" \
  --admin_user=admin \
  --admin_password=smoke-only \
  --admin_email=smoke@example.com \
  --skip-email --quiet

# Set SMOKE_PLUGIN_ZIP to test the artifact users actually download. Without it
# we test the working tree — never a zip found lying around, which silently
# tests a stale build.
if [ -n "${SMOKE_PLUGIN_ZIP:-}" ]; then
  echo "Installing from zip: $SMOKE_PLUGIN_ZIP"
  unzip -qo "$SMOKE_PLUGIN_ZIP" -d "$WP_DIR/wp-content/plugins"
  if [ ! -f "$WP_DIR/wp-content/plugins/$SLUG/pressnative.php" ]; then
    echo "::error::$SMOKE_PLUGIN_ZIP did not extract to $SLUG/pressnative.php"
    exit 1
  fi
else
  echo "Installing from working tree: $PLUGIN_DIR"
  mkdir -p "$WP_DIR/wp-content/plugins/$SLUG"
  rsync -a --exclude='.git' --exclude='.github' --exclude='node_modules' \
    "$PLUGIN_DIR/" "$WP_DIR/wp-content/plugins/$SLUG/"
fi

wpcli theme activate twentytwentyfour --quiet
wpcli plugin activate "$SLUG"

# Seed a little content so layout endpoints have something real to build.
wpcli post create --post_title="Smoke post" --post_content="<p>Hello</p>" --post_status=publish --quiet

php "$SCRIPT_DIR/smoke-harness.php" "$WP_DIR"
