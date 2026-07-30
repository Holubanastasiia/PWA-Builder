# WP PWA Builder

Starter WordPress plugin for serving many PWA experiences from one domain.

## What this skeleton includes

- `pwa_app` custom post type served at `/apps/{slug}/`.
- Dynamic manifest per app at `/apps/{slug}/manifest.webmanifest`.
- Dynamic service worker per app at `/apps/{slug}/sw.js`.
- Dynamic placeholder PNG icons at `/apps/{slug}/icon-192.png` and `/apps/{slug}/icon-512.png`.
- Public JavaScript that emits PWA lifecycle events for analytics adapters.
- Default template CTA support through the `analytic-url` class used by the existing analytics plugin.
- Theme-overridable templates: add `pwa-apps/{template-key}.php` to the active theme.
- Template auto-discovery from `templates/{template-key}/template.json`.
- ACF JSON load/save path at `acf-json/`.

## Adding Templates

Create a folder in `templates/`:

```text
templates/dating-offer/
  template.json
  template.php
  style.css
  script.js
```

Example `template.json`:

```json
{
  "name": "Dating Offer",
  "niches": ["dating"],
  "template": "template.php",
  "styles": ["style.css"],
  "scripts": ["script.js"]
}
```

The template will appear automatically in the admin dropdown for matching niches.

## ACF JSON

ACF field groups should be managed through the ACF UI and synced through:

```text
acf-json/
```

Use location rules for `Post Type is equal to PWA App`.

Template-specific field groups can use the custom ACF location rule:

```text
PWA Builder -> PWA Template is equal to Default Funnel
```

This rule checks the PWA Builder template selected in the `PWA Settings` metabox.

## Local and production URLs

Asset URLs are resolved by `WP_PWA_Builder\Environment`.

- `local` uses query-string URLs by default, which avoids redirect issues in XAMPP/subfolder installs.
- `production`, `staging`, and `development` use pretty URLs by default.

To force the mode in `wp-config.php`:

```php
define('WP_ENVIRONMENT_TYPE', 'local');
define('WP_PWA_BUILDER_USE_PRETTY_ASSET_URLS', false);
```

For production:

```php
define('WP_ENVIRONMENT_TYPE', 'production');
define('WP_PWA_BUILDER_USE_PRETTY_ASSET_URLS', true);
```

After changing pretty URL mode, resave WordPress permalinks.

## Next decisions

- How push sending will work: native Web Push with VAPID keys or an external provider.
- Whether templates are built with ACF fields, Gutenberg blocks, or both.
- How icons/screenshots are generated per app for stricter installability checks.
- Whether local environments should always use query-string asset URLs while production uses pretty URLs.
