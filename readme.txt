=== PressNative Apps ===
Contributors: pressnative
Tags: mobile app, native app, rest api, woocommerce, server-driven ui
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

No-code native app from WordPress. Server-driven UI, no app store resubmissions. SaaS connector for PressNative.

== Description ==

**PressNative Apps** turns your WordPress site into a native Android and iOS app with **no-code native performance**. Content, layout, and branding are driven from your WordPress admin and delivered via a contract-based REST API. The native app renders **server-driven UI** — when you publish or edit posts, change layout, or update products, the app reflects those changes without requiring an app update.

This plugin is a **SaaS connector**: it connects your WordPress site to **PressNative Cloud**. You need a **PressNative account** to use the full platform (connect flow under **Settings → PressNative**, API key, analytics, push notifications, and optional Pro features).

**Why PressNative?**

* **No-code native performance** — Real native screens (Jetpack Compose / SwiftUI), not a WebView wrapper. Your content is rendered as native components.
* **Server-driven UI** — Layout, components, and content are defined by your WordPress site and the PressNative contract. Change your home screen or post layout in WordPress; the app updates automatically.
* **One plugin, one contract** — Install the plugin, connect to PressNative Cloud, and configure branding and layout. The Android and iOS apps consume the same API.

**Core Features:**

* **REST API** — Endpoints for home layout, posts, pages, categories, products, and search
* **Jetpack-style connect** — Connect your site to PressNative Cloud from **Settings → PressNative** (connect button, return with API key, optional initial content cache)
* **WooCommerce Integration** — Native product grids, product details, cart management, and secure checkout via Chrome Custom Tabs
* **App Branding** — App name, logo, theme colors, and typography from **PressNative → App Settings**
* **Layout Builder** — Hero carousel, post grid, category list, page list, product grids; drag-and-drop ordering
* **Push Notifications** — Device registration and ad-hoc push from the admin (requires PressNative account)
* **Analytics Dashboard** — Views, popular posts, search queries (requires PressNative subscription)
* **Live Preview** — Preview branding and layout in a simulated device frame
* **Shortcode-to-native mapping** — Server-driven UI: shortcodes map to native app components

**Requirements:**

* WordPress 6.0 or later
* PHP 7.4 or later
* A **PressNative account** (free or Pro) for connecting your site and using analytics/push
* PressNative Android or iOS app to display the content
* Optional: WooCommerce for ecommerce features

== Installation ==

1. Upload the `pressnative` folder to `/wp-content/plugins/`, or install via **Plugins → Add New → Upload Plugin**
2. Activate the plugin through the **Plugins** menu
3. Go to **Settings → PressNative** and click **Connect to PressNative Cloud** to link your site to your PressNative account (or enter your API key under **PressNative** in the admin menu)
4. Configure branding under **PressNative → App Settings** and layout under **PressNative → Layout Settings**

== Frequently Asked Questions ==

= Do I need a PressNative account? =

Yes. This plugin is the SaaS connector for the PressNative platform. You connect your site from **Settings → PressNative** (or enter an API key). A PressNative account is required for analytics, push notifications, and full functionality.

= Do I need the PressNative mobile app? =

Yes. The plugin serves your content via REST API. The PressNative Android and iOS apps consume that API and render native server-driven UI.

= What is server-driven UI? =

Your WordPress site (and the PressNative contract) define what the app shows: layout, components, and content. When you change a post or the home layout in WordPress, the app fetches the updated data and renders it natively — no app store update needed.

= Does the plugin work with WooCommerce? =

Yes. With WooCommerce installed, you get native product grids, product detail pages, cart management, and secure checkout via Chrome Custom Tabs. Products can be embedded in posts for shoppable content.

= What happens when I deactivate or delete the plugin? =

Deactivating removes the REST API endpoints but keeps your settings. Deleting the plugin removes saved options and the custom devices table. Your posts, pages, and media are not affected.

== Screenshots ==

1. Settings → PressNative — Connect to PressNative Cloud
2. PressNative settings — API key and subscription status
3. App Settings — Branding (app name, colors, logo) with live preview
4. Layout Settings — Component ordering and configuration
5. Analytics dashboard — Views, top posts, device breakdown
6. Push Notifications — Send notifications to app users

== Changelog ==

= 1.1.0 =
* **SaaS Connector MVP:** Settings → PressNative page with Jetpack-style connect flow (connect button, auth catcher, disconnect)
* **AOT on connect:** Initial sweep of top 10 posts after remote auth
* **Premium gating helper:** `pressnative_render_pro_lock( $feature_name )` for Pro-tier feature cards
* **WooCommerce Integration:** Native product grids, product details, shopping cart, secure checkout via Chrome Custom Tabs
* **Shoppable Content:** Embed products in blog posts with native add-to-cart
* **Cart Transfer:** One-time token for app-to-browser cart handoff
* **Demo Data API:** Endpoint for sample WooCommerce products
* **Real-time Cart Updates:** Cart badge updates from embedded products

= 1.0.0 =
* Initial release
* REST API: home layout, post detail, page detail, category archive, search
* Admin: Registry settings, app branding, layout builder, analytics, push notifications
* Theme presets and device registration for FCM
* Live preview and WebView template
* Shortcode-to-native component mapping

== Upgrade Notice ==

= 1.1.0 =
SaaS connector: connect from Settings → PressNative, premium gating helper, and full WooCommerce native experience (cart, checkout, shoppable content).

= 1.0.0 =
Initial release of PressNative Apps.

== Privacy ==

This plugin connects to **PressNative Cloud** when you connect your site (Settings → PressNative) or enter an API key. The following data may be sent:

* **Analytics events** — Content view type, resource ID/title, device type (iOS/Android)
* **Push notification tokens** — FCM device tokens (stored locally); push requests sent to PressNative
* **Configuration notifications** — Site URL and post/settings metadata for cache invalidation when you publish or change settings
* **Schema verification** — Contract schema check on activation

No personal visitor data is collected. Analytics are aggregated by content type.

For more information, visit [pressnative.app/privacy](https://pressnative.app/privacy).
