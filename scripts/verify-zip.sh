#!/usr/bin/env bash
# Verify a WordPress plugin zip cannot take a site down on extract.
# Usage: scripts/verify-zip.sh /path/to/pressnative-apps.zip
set -euo pipefail

ZIP_PATH="${1:-}"
if [[ -z "$ZIP_PATH" || ! -f "$ZIP_PATH" ]]; then
  echo "::error::Usage: $0 /path/to/pressnative-apps.zip"
  exit 1
fi

SLUG="pressnative-apps"
MAIN="${SLUG}/pressnative.php"
STAGE="$(mktemp -d)"
REQUIRED_COUNT=0
trap 'rm -rf "$STAGE"' EXIT

echo "Verifying $ZIP_PATH ($(wc -c < "$ZIP_PATH") bytes)"

if ! unzip -t "$ZIP_PATH" >/dev/null; then
  echo "::error::Zip failed integrity test"
  exit 1
fi

ROOT_ENTRIES="$(unzip -Z1 "$ZIP_PATH" | awk -F/ 'NF{print $1}' | sort -u)"
if [[ "$ROOT_ENTRIES" != "$SLUG" ]]; then
  echo "::error::Zip must contain exactly one top-level folder named ${SLUG}"
  echo "$ROOT_ENTRIES"
  exit 1
fi

if unzip -Z1 "$ZIP_PATH" | grep -E '(^|/)__MACOSX(/|$)' >/dev/null; then
  echo "::error::Zip contains __MACOSX junk that can make WordPress dump files into wp-content/plugins/"
  exit 1
fi

if unzip -Z1 "$ZIP_PATH" | grep -F "${SLUG}/${SLUG}/" >/dev/null; then
  echo "::error::Zip contains nested ${SLUG}/${SLUG}/ (rsync copied the staging folder into itself)"
  exit 1
fi

unzip -q "$ZIP_PATH" -d "$STAGE"

if [[ ! -f "$STAGE/$MAIN" ]]; then
  echo "::error::Missing $MAIN"
  exit 1
fi

while IFS= read -r rel; do
  if [[ ! -f "$STAGE/$SLUG/$rel" ]]; then
    echo "::error::pressnative.php requires $rel but it is missing from the zip"
    exit 1
  fi
  REQUIRED_COUNT=$((REQUIRED_COUNT + 1))
done < <(php -r '
$src = file_get_contents($argv[1]);
if (!preg_match("/function pressnative_required_files\(\) \{\s*return array\((.*?)\);\s*\}/s", $src, $m)) {
  fwrite(STDERR, "could not parse pressnative_required_files()\n");
  exit(1);
}
preg_match_all("/'\''([^'\'']+)'\''/", $m[1], $files);
if (!$files[1]) {
  fwrite(STDERR, "pressnative_required_files() is empty\n");
  exit(1);
}
foreach ($files[1] as $f) {
  echo $f, PHP_EOL;
}
' "$STAGE/$MAIN")

errors=0
while IFS= read -r file; do
  if ! php -l "$file" >/dev/null 2>&1; then
    php -l "$file"
    errors=$((errors + 1))
  fi
done < <(find "$STAGE/$SLUG" -name '*.php')
if [[ "$errors" -gt 0 ]]; then
  echo "::error::Found $errors PHP file(s) with syntax errors in the zip"
  exit 1
fi

echo "Zip verification passed ($REQUIRED_COUNT required files)"
