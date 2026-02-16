=== PressNative ===
Contributors: pressnative
Tags: mobile app, rest api, native app, content, news, api
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Data provider for the PressNative mobile app. Serves layout and content via REST API to native Android and iOS apps.

== Description ==

PressNative turns your WordPress site into a data source for native mobile apps. Install this plugin to expose your posts, categories, and content through a REST API that conforms to the PressNative contract schema.

**Features:**

* REST API endpoints for home layout, posts, and categories
* Configurable branding (app name, logo, theme colors, typography)
* Layout settings (hero carousel, post grid, category list, component order)
* Device registration for push notifications (FCM)
* Contract-driven structure compatible with PressNative Android and iOS apps

**How it works:**

The plugin serves JSON at `/wp-json/pressnative/v1/layout/home` with your site's content formatted for the PressNative mobile app shell. Configure the Registry URL and App Settings under Settings → PressNative in your WordPress admin.

**Requirements:**

* A PressNative Registry service (optional for schema verification)
* PressNative mobile app (Android or iOS) to consume the API

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install through WordPress Plugins → Add New → Upload Plugin
2. Activate the plugin through the Plugins menu in WordPress
3. Go to Settings → PressNative to configure the Registry URL (if using the PressNative ecosystem)
4. Go to PressNative → App Settings to customize branding (app name, logo, colors, typography)
5. Go to PressNative → Layout Settings to configure home screen components

== Frequently Asked Questions ==

= Do I need the PressNative mobile app to use this plugin? =

Yes. This plugin is a data provider only. It exposes your WordPress content via REST API. The PressNative Android and iOS apps consume this API to display your content in a native mobile experience.

= Is the Registry URL required? =

The Registry URL is used to verify the schema on activation. If you're not running the PressNative Registry service, the plugin will still work; schema verification will simply be skipped.

= What content does the API expose? =

The home layout includes: a hero carousel (from posts in the "Featured" category), a post grid (latest published posts), a category list, and an ad placement slot. All responses conform to the PressNative contract schema.

== Screenshots ==

1. PressNative settings page (Registry URL configuration)
2. App Settings page (branding: app name, colors, logo)

== Changelog ==

= 1.0.0 =
* Initial release
* REST API: /layout/home and /register-device
* Admin: Registry URL, App Settings (branding)
* Device registration for FCM push notifications

== Upgrade Notice ==

= 1.0.0 =
Initial release of PressNative WordPress plugin.
