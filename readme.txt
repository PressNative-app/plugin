=== PressNative ===
Contributors: pressnative
Tags: mobile app, native app, rest api, push notifications, app
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your WordPress site into a native mobile app. Serves layout, content, and branding via REST API to PressNative Android and iOS apps.

== Description ==

PressNative is the WordPress data provider for native mobile apps built on the PressNative platform. Install this plugin to expose your posts, pages, categories, and site branding through a contract-driven REST API that the PressNative Android and iOS app shells consume.

**Core Features:**

* **REST API** — Endpoints for home layout, posts, pages, categories, and search
* **App Branding** — Configure app name, logo, theme colors, and typography directly from the WordPress admin
* **Theme Presets** — Five built-in themes (Editorial, Midnight, Citrus, Ocean, Minimal) or go fully custom
* **Layout Builder** — Customize the home screen: hero carousel, post grid, category list, page list, and ad placement
* **Component Ordering** — Drag-and-drop component ordering with enable/disable toggles
* **Push Notifications** — Register devices via FCM and send ad-hoc push notifications from the admin dashboard
* **Analytics Dashboard** — Track app views, popular posts, search queries, and device breakdown (requires PressNative subscription)
* **Live Preview** — Preview branding and layout changes on a simulated device frame before saving
* **WebView Support** — Minimal in-app WebView template for pages with complex shortcodes or embeds
* **Shortcode Mapping** — Maps WordPress shortcodes to native app components for server-driven UI

**How It Works:**

1. Install and activate PressNative on your WordPress site
2. Configure branding (app name, logo, colors) under **PressNative → App Settings**
3. Customize the home screen layout under **PressNative → Layout Settings**
4. The PressNative mobile app fetches your content from `/wp-json/pressnative/v1/layout/home`
5. Content updates automatically appear in the app — no app store resubmission needed

**Requirements:**

* WordPress 5.0 or later
* PHP 7.4 or later
* PressNative Android or iOS app to display the content
* Optional: PressNative Registry service for analytics, push notifications, and schema verification

== Installation ==

1. Upload the `pressnative` folder to `/wp-content/plugins/`, or install via **Plugins → Add New → Upload Plugin**
2. Activate the plugin through the **Plugins** menu
3. Navigate to **PressNative → Settings** and enter your Registry URL and API Key (if using the PressNative ecosystem)
4. Go to **PressNative → App Settings** to customize branding (app name, logo, colors, typography)
5. Go to **PressNative → Layout Settings** to configure home screen components

== Frequently Asked Questions ==

= Do I need the PressNative mobile app to use this plugin? =

Yes. This plugin is a data provider only — it serves your WordPress content via REST API. You need the PressNative Android or iOS app to display the content as a native mobile experience.

= Is the PressNative Registry required? =

No. The plugin works without the Registry. The Registry adds optional features: analytics storage, schema verification on activation, and push notification delivery. Without it, the REST API still serves content normally.

= What content does the API expose? =

The home layout endpoint includes a hero carousel (featured posts), a post grid (latest posts), a category list, a page list, and an ad placement slot. Individual post, page, and category endpoints are also available. All responses conform to the PressNative contract schema.

= Can I customize which components appear on the home screen? =

Yes. Under **PressNative → Layout Settings**, you can enable/disable each component and drag-and-drop to reorder them. Changes are reflected immediately in the app.

= Does the plugin support push notifications? =

Yes. The plugin registers FCM device tokens from the mobile app and provides an admin page to send ad-hoc push notifications. Push delivery is handled by the PressNative Registry service (requires API key).

= What happens when I deactivate or delete the plugin? =

Deactivating the plugin removes all REST API endpoints but preserves your settings. Deleting the plugin via the WordPress admin removes all saved options and the custom database table. Your WordPress content (posts, pages, media) is never affected.

= Does the plugin send data to external services? =

Yes. When configured with a Registry URL and API Key, the plugin communicates with the PressNative Registry service for: analytics event forwarding, push notification delivery, schema verification on activation, and cache invalidation notifications when you update settings or publish content. See the Privacy section below for details.

== Screenshots ==

1. PressNative settings page — Registry URL and API Key configuration
2. App Settings page — Branding (app name, colors, logo) with live preview
3. Layout Settings page — Component ordering and configuration
4. Analytics dashboard — Views, top posts, device breakdown
5. Push Notifications page — Send ad-hoc notifications to app users

== Changelog ==

= 1.0.0 =
* Initial release
* REST API: home layout, post detail, page detail, category archive, search
* Admin: Registry settings, app branding, layout builder, analytics dashboard, push notifications
* Theme presets: Editorial, Midnight, Citrus, Ocean, Minimal, Custom
* Device registration for FCM push notifications
* Live preview with simulated device frames
* WebView template for in-app page rendering
* Shortcode-to-native component mapping

== Upgrade Notice ==

= 1.0.0 =
Initial release of PressNative.

== Privacy ==

This plugin optionally communicates with the **PressNative Registry** service when you configure a Registry URL and API Key in the plugin settings. The following data may be transmitted:

* **Analytics events** — Content view type (home/post/page/category/search), resource ID, resource title, and device type (iOS/Android). Sent when the API is called or when the app reports cached views.
* **Push notification tokens** — FCM device tokens are stored locally in your WordPress database. Push delivery requests (title, body, link, image URL) are sent to the Registry.
* **Configuration notifications** — When you save branding or layout settings, or publish/update content, the plugin notifies the Registry so it can invalidate its cache. This includes your site URL and basic post metadata (title, excerpt, permalink, thumbnail URL).
* **Schema verification** — On plugin activation, the plugin fetches the contract schema from the Registry to verify compatibility.

No personal user data from your site visitors is collected or transmitted. Analytics are aggregated by content type, not by individual user.

For more information, visit [pressnative.app/privacy](https://pressnative.app/privacy).
