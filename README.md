# PressNative WordPress Plugin

**Role in PressNative.app:** The WordPress Data Provider.

This plugin exposes REST endpoints that serve page layouts, content, and WooCommerce products to the PressNative Android and iOS apps. All responses **must** conform to the schema defined in `www/contract.json`.

## Responsibilities

- **REST API:** Provides `/wp-json/pressnative/v1/*` endpoints that return screen layouts (e.g., home, category, post detail, product detail)
- **WooCommerce Integration:** Native cart management, product grids, secure checkout via Chrome Custom Tabs
- **Schema alignment:** Response payloads are validated against the canonical contract from the www service
- **WordPress integration:** Maps WordPress posts, categories, WooCommerce products, and media to the PressNative component format

## Installation

1. Copy the entire plugin folder (containing `pressnative.php`) to `wp-content/plugins/pressnative/`, or install via Plugins → Add New → Upload Plugin
2. Activate the plugin in WordPress Admin → Plugins
3. Configure the Registry URL under PressNative → Settings
4. Customize branding under PressNative → App Settings
5. Configure home screen layout under PressNative → Layout Settings

## Architecture

The plugin follows the PressNative.app workflow:

1. **Schema first:** The contract in `www/contract.json` defines the canonical structure
2. **This plugin:** Implements the REST endpoints that produce JSON matching that schema
3. **Clients:** The `android/` and `ios/` apps consume the API and render components

## Related Repositories

- **www:** Core Registry Service — hosts `contract.json` and `.well-known` files
- **android:** Native Jetpack Compose shell
- **ios:** Native SwiftUI shell
