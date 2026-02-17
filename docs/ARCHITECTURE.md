# PressNative WordPress Plugin &mdash; Architecture

The PressNative plugin is the **data provider** layer. It transforms WordPress content into a Server-Driven UI (SDUI) contract that native mobile apps consume, and bridges WordPress with the central Registry service for push notifications, analytics, and billing.

---

## Where the Plugin Fits

```mermaid
graph TB
    subgraph "WordPress Site"
        WP[("WordPress<br/>Database")] <--> Plugin["PressNative Plugin"]
        Plugin --> REST["REST API<br/>/wp-json/pressnative/v1/"]
        Plugin --> Admin["WP Admin Panel<br/>Settings · Branding · Analytics"]
    end

    subgraph "PressNative Platform"
        Registry["Registry Service<br/>(www/)"]
        Firebase["Firebase Cloud<br/>Messaging"]
    end

    subgraph "Mobile Apps"
        Android["Android App"]
        iOS["iOS App"]
    end

    Android -- "GET layout, search,<br/>track analytics" --> REST
    iOS -- "GET layout, search,<br/>track analytics" --> REST
    Plugin -- "POST /notify/content-changed<br/>POST /notify/config-changed" --> Registry
    Registry -- "POST /pressnative/v1/branding" --> Plugin
    Registry -- "Send push via FCM" --> Firebase
    Firebase --> Android
    Firebase --> iOS
```

---

## Data Flow: Layout Request (Mobile App &rarr; Plugin)

When a mobile app requests the home screen, the plugin assembles the SDUI contract from WordPress data.

```mermaid
sequenceDiagram
    participant App as Mobile App
    participant Plugin as PressNative Plugin
    participant WP as WordPress Core
    participant DB as WordPress Database

    App->>Plugin: GET /pressnative/v1/layout/home

    Plugin->>WP: get_branding()
    WP->>DB: Query wp_options (branding settings)
    DB-->>WP: Theme, colors, logo, fonts
    WP-->>Plugin: Branding object

    Plugin->>WP: Build components (enabled_components order)

    Note over Plugin,DB: For each enabled component:

    Plugin->>DB: Query posts (HeroCarousel category)
    Plugin->>DB: Query posts (PostGrid)
    Plugin->>DB: Query categories
    Plugin->>DB: Query pages

    Plugin-->>App: SDUI Contract JSON
    Note right of App: {branding, screen, components[]}
```

---

## Data Flow: Content Change Notification

When an author publishes or updates content, the plugin notifies the Registry, which triggers push notifications to subscribed devices.

```mermaid
sequenceDiagram
    participant Author as WordPress Author
    participant WP as WordPress Core
    participant Plugin as PressNative Plugin
    participant Registry as Registry Service
    participant FCM as Firebase
    participant App as Mobile Apps

    Author->>WP: Publish/update post
    WP->>Plugin: transition_post_status hook
    Plugin->>Plugin: Check: status changed to 'publish'?

    alt New or updated published post
        Plugin->>Registry: POST /api/v1/notify/content-changed
        Note right of Plugin: {site_url, post_id, title,<br/>excerpt, thumbnail_url, link}
        Registry->>Registry: Increment content_version
        Registry->>FCM: Send push notification
        FCM->>App: New post notification
    end
```

---

## Data Flow: Branding & Config Sync

Branding changes flow **bidirectionally** between the WordPress admin and the Registry admin panel.

```mermaid
sequenceDiagram
    participant WPAdmin as WordPress Admin
    participant Plugin as PressNative Plugin
    participant WP as wp_options
    participant Registry as Registry Service

    Note over WPAdmin,Registry: Path A: Admin saves branding in WordPress
    WPAdmin->>WP: Save pressnative_app_settings
    WP->>Plugin: updated_option hook
    Plugin->>Registry: POST /api/v1/notify/config-changed
    Registry->>Registry: Invalidate branding cache
    Registry->>Plugin: Fetch updated branding

    Note over WPAdmin,Registry: Path B: Registry pushes branding to WordPress
    Registry->>Plugin: POST /pressnative/v1/branding
    Note right of Registry: {theme_id, primary_color,<br/>accent_color, logo_url, ...}
    Plugin->>WP: Update pressnative options
    Plugin->>Plugin: Download external logo to media library
```

### Tracked Options (trigger Registry notification)

| Category | Options |
|----------|----------|
| **Branding** | app_name, primary_color, accent_color, background_color, text_color, font_family, base_font_size, logo_attachment, theme_id |
| **Layout** | hero_category_slug, hero_max_items, post_grid_columns, post_grid_per_page, enabled_categories, enabled_components |

---

## SDUI Contract Structure

The plugin serves layouts that match the `contract.json` schema defined in the Registry.

```mermaid
graph TD
    Contract["SDUI Contract"]
    Contract --> Branding["branding<br/>{app_name, logo, theme, typography}"]
    Contract --> Screen["screen<br/>{id, title}"]
    Contract --> Components["components[]"]

    Components --> HC["HeroCarousel<br/>Featured posts carousel"]
    Components --> PG["PostGrid<br/>Grid of recent posts"]
    Components --> CL["CategoryList<br/>Browsable categories"]
    Components --> PL["PageList<br/>Static pages"]
    Components --> SB["ShortcodeBlock<br/>Native shortcode rendering"]
    Components --> AD["AdPlacement<br/>Ad slot placeholder"]
```

### Contract JSON Example

```json
{
  "branding": {
    "app_name": "My Site",
    "logo_url": "https://example.com/logo.png",
    "theme_id": "editorial",
    "theme": {
      "primary_color": "#1a1a2e",
      "accent_color": "#e94560",
      "background_color": "#ffffff",
      "text_color": "#1a1a2e"
    },
    "typography": {
      "font_family": "sans-serif",
      "base_font_size": 16
    }
  },
  "screen": { "id": "home", "title": "My Site" },
  "components": [
    { "id": "hero", "type": "HeroCarousel", "content": { "items": [...] } },
    { "id": "posts", "type": "PostGrid", "content": { "posts": [...], "columns": 2 } },
    { "id": "categories", "type": "CategoryList", "content": { "categories": [...] } }
  ]
}
```

---

## Device Registration Flow

Mobile apps register their Firebase token with both the WordPress plugin (local storage) and the Registry (push subscriptions).

```mermaid
sequenceDiagram
    participant App as Mobile App
    participant Plugin as WordPress Plugin
    participant DB as wp_pressnative_devices
    participant Registry as Registry Service

    App->>Plugin: POST /pressnative/v1/register-device
    Note right of App: {fcm_token, device_type}
    Plugin->>DB: INSERT/UPDATE device record
    DB-->>Plugin: OK
    Plugin-->>App: 200 OK

    App->>Registry: POST /api/v1/push/subscribe
    Note right of App: {fcm_token, site_url, device_type}
    Registry->>Registry: Store in push_subscriptions
```

---

## Analytics Flow

Analytics events originate from mobile apps, pass through the plugin, and are stored in the Registry.

```mermaid
sequenceDiagram
    participant App as Mobile App
    participant Plugin as WordPress Plugin
    participant Registry as Registry Service
    participant DB as PostgreSQL

    App->>Plugin: POST /pressnative/v1/track
    Note right of App: {event_type, resource_id,<br/>device_type, resource_title}
    Plugin->>Registry: POST /api/v1/analytics/event
    Note right of Plugin: Forwards with API key header
    Registry->>DB: INSERT analytics_events

    Note over Plugin,Registry: Analytics queries (WP Admin panel)
    Plugin->>Registry: GET /api/v1/analytics/summary
    Registry->>DB: Aggregate query
    DB-->>Registry: Summary data
    Registry-->>Plugin: JSON response
```

---

## REST API Reference

### Layout Endpoints

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/pressnative/v1/layout/home` | Home screen SDUI layout |
| `GET` | `/pressnative/v1/layout/post/{id}` | Post detail layout |
| `GET` | `/pressnative/v1/layout/page/{slug}` | Page layout by slug |
| `GET` | `/pressnative/v1/layout/category/{id}` | Category layout |

### Content & Search

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/pressnative/v1/search?q=&per_page=` | Search posts |
| `POST` | `/pressnative/v1/preview` | Preview layout with temporary overrides |

### Device & Notifications

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/pressnative/v1/register-device` | Register FCM token |
| `POST` | `/pressnative/v1/branding` | Registry pushes branding updates |
| `GET` | `/pressnative/v1/site-info` | Site metadata for Registry |

### Analytics

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/pressnative/v1/track` | Forward analytics event to Registry |
| `GET` | `/pressnative/v1/analytics/summary` | Summary stats (admin) |
| `GET` | `/pressnative/v1/analytics/top-posts` | Top posts by views |
| `GET` | `/pressnative/v1/analytics/top-pages` | Top pages by views |
| `GET` | `/pressnative/v1/analytics/top-categories` | Top categories |
| `GET` | `/pressnative/v1/analytics/views-over-time` | Time series views |
| `GET` | `/pressnative/v1/analytics/device-breakdown` | Device type stats |
| `GET` | `/pressnative/v1/analytics/top-searches` | Top search queries |

---

## WordPress Hooks

### Actions

| Hook | Handler | Purpose |
|------|---------|----------|
| `transition_post_status` | `maybe_notify_content_changed()` | Notify Registry on publish |
| `updated_option` | `maybe_notify_registry()` | Notify Registry on config change |
| `rest_api_init` | Register all REST routes | Expose REST API |
| `admin_menu` | Register admin pages | Add WP admin panel |
| `template_redirect` | Handle `?pressnative=1` | WebView content rendering |

### Filters

| Filter | Purpose |
|--------|----------|
| `pressnative_search_results` | Override search results |
| `pressnative_native_shortcodes` | Register native shortcode mappings |
| `pressnative_qr_deep_link_base` | Override QR code deep link URL |

---

## Admin Panel Features

| Page | Purpose |
|------|----------|
| **Settings** | Registry URL, API key, subscription status |
| **App Settings** | Theme presets, colors, logo, typography |
| **Layout Settings** | Component order, hero category, grid config |
| **Analytics** | Charts & stats (proxied from Registry) |
| **Push Notifications** | Send ad-hoc push notifications |

### Theme Presets

| Theme | Primary | Accent | Background | Text |
|-------|---------|--------|------------|------|
| Editorial | #1a1a2e | #e94560 | #ffffff | #1a1a2e |
| Midnight | #0d1b2a | #e0e1dd | #1b263b | #e0e1dd |
| Citrus | #2d6a4f | #f77f00 | #ffffff | #264653 |
| Ocean | #023e8a | #48cae4 | #f8f9fa | #023047 |
| Minimal | #212529 | #212529 | #ffffff | #212529 |
| Custom | User-defined | User-defined | User-defined | User-defined |
