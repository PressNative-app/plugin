# PressNative WordPress Plugin

**Role in PressNative.app:** The WordPress Data Provider.

This plugin exposes REST endpoints that serve page layouts, content, and WooCommerce products to the PressNative Android and iOS apps. All responses **must** conform to the schema defined in `www/contract.json`.

## Responsibilities

- **REST API:** Provides `/wp-json/pressnative/v1/*` endpoints that return screen layouts (e.g., home, category, post detail, product detail)
- **WooCommerce Integration:** Native cart management, product grids, secure checkout via Chrome Custom Tabs
- **Schema alignment:** Response payloads are validated against the canonical contract from the www service
- **WordPress integration:** Maps WordPress posts, categories, WooCommerce products, and media to the PressNative component format

## Installation

1. Download [`pressnative-apps.zip`](https://github.com/PressNative-app/plugin/releases/latest/download/pressnative-apps.zip) from GitHub Releases, or run `scripts/build.sh` locally
2. In WordPress, go to Plugins → Add New → Upload Plugin and upload the ZIP (folder slug must be `pressnative-apps/`)
3. Activate the plugin in WordPress Admin → Plugins
4. Connect your site under Settings → PressNative
5. Customize branding under PressNative → App Settings
6. Configure home screen layout under PressNative → Layout Settings

If an update ever fails, WordPress should keep the previous plugin. If the site shows a critical error or maintenance screen, rename `wp-content/plugins/pressnative-apps` via FTP/hosting file manager (for example to `pressnative-apps.off`) and delete a leftover `.maintenance` file in the WordPress root. The rest of the site will load; then reinstall from GitHub Releases.

Installed sites check `https://pressnative.app/api/v1/plugin/update` and show WordPress core update notices when a newer GitHub Release exists.

## Releases

Merging to `main` publishes a plugin update automatically:

```
merge to main
  → bump-version.yml patch-bumps pressnative.php + readme.txt
  → tags vX.Y.Z
  → release.yml builds pressnative-apps.zip and attaches it to a GitHub Release
  → https://github.com/PressNative-app/plugin/releases/latest/download/pressnative-apps.zip serves the new ZIP
  → pressnative.app /api/v1/plugin/update picks it up within 15 minutes
```

To land a change on `main` without a public release, include `[no release]` in the merge commit message.

Manual release: `git tag vX.Y.Z && git push origin vX.Y.Z`.

## Architecture

The plugin follows the PressNative.app workflow:

1. **Schema first:** The contract in `www/contract.json` defines the canonical structure
2. **This plugin:** Implements the REST endpoints that produce JSON matching that schema
3. **Clients:** The `android/` and `ios/` apps consume the API and render components

## Related Repositories

- **www:** Core Registry Service — hosts `contract.json` and `.well-known` files
- **android:** Native Jetpack Compose shell
- **ios:** Native SwiftUI shell
