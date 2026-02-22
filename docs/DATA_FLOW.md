# Plugin Data Flow Reference

Quick reference for every data exchange involving the PressNative WordPress plugin.

---

## Inbound Requests (to Plugin)

```mermaid
graph RL
    subgraph "From Mobile Apps"
        A1["GET /layout/home"] --> P[Plugin]
        A2["GET /layout/post/{id}"] --> P
        A3["GET /layout/page/{slug}"] --> P
        A4["GET /layout/category/{id}"] --> P
        A5["GET /search?q=..."] --> P
        A6["POST /register-device"] --> P
        A7["POST /track"] --> P
    end

    subgraph "From Registry"
        R1["POST /branding<br/>(push branding updates)"] --> P
        R2["GET /site-info<br/>(fetch admin email)"] --> P
    end
```

## Outbound Requests (from Plugin)

```mermaid
graph LR
    P[Plugin] --> R1["POST /notify/config-changed<br/>(branding/layout saved)"]
    P --> R2["POST /notify/content-changed<br/>(post/page published)"]
    P --> R3["POST /analytics/event<br/>(forward tracking data)"]
    P --> R4["GET /analytics/*<br/>(fetch stats for admin)"]
    P --> R5["GET /stripe/subscription-status<br/>(check billing)"]
    R1 --> Reg[Registry]
    R2 --> Reg
    R3 --> Reg
    R4 --> Reg
    R5 --> Reg
```

---

## Plugin Database Table

The plugin creates one custom table on activation:

| Table | Columns | Purpose |
|-------|---------|----------|
| `wp_pressnative_devices` | id, fcm_token (unique), device_type, created_at, updated_at | Local FCM token storage |

All other data uses standard `wp_options`:

| Option Key | Purpose |
|------------|----------|
| `pressnative_registry_url` | Registry service URL |
| `pressnative_api_key` | API key for Registry auth |
| `pressnative_app_name` | App display name |
| `pressnative_primary_color` | Brand primary color |
| `pressnative_accent_color` | Brand accent color |
| `pressnative_background_color` | Background color |
| `pressnative_text_color` | Text color |
| `pressnative_font_family` | Typography font family |
| `pressnative_base_font_size` | Typography base size |
| `pressnative_logo_attachment` | Logo media attachment ID |
| `pressnative_hero_category_slug` | Hero carousel category |
| `pressnative_hero_max_items` | Max hero items |
| `pressnative_post_grid_columns` | Post grid column count |
| `pressnative_post_grid_per_page` | Posts per page |
| `pressnative_enabled_categories` | Enabled category IDs |
| `pressnative_enabled_components` | Component order list |
