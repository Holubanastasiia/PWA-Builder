# WP PWA Builder - Project Notes

## Goal

Build a WordPress plugin that works as a PWA builder for marketing funnels.

The plugin should let managers create many PWA apps on one WordPress domain. Each PWA app has its own slug, manifest data, icon, screenshots, target/offer URL, niche, and selected template.

The current first phase focuses only on the PWA Builder itself:

- create and manage PWA apps in WP admin;
- serve each PWA under its own URL;
- generate a valid manifest;
- register a service worker;
- support install flow on Android/Chrome-like browsers;
- redirect through a technical start URL after install/launch;
- prepare frontend events for future analytics integration.

Out of scope for this phase:

- push notifications;
- cloaking;
- full analytics implementation;
- custom HTML uploads by managers.

These should be separate plugins/integrations later.

## Main Product Flow

Basic flow:

```text
User opens PWA landing
-> sees template UI
-> clicks install/continue CTA
-> browser install prompt appears when available
-> browser fires appinstalled if install is confirmed
-> user is sent to /apps/{slug}/start/
-> start page redirects to offer URL
```

For iOS, install behavior is limited by browser/platform. The current expectation is that iOS can use a redirect flow or a separately split funnel through the existing split plugin.

## Key Architecture Decisions

### One PWA App = One Custom Post Type Entry

Each PWA is represented by the custom post type:

```text
pwa_app
```

Reason:

- WordPress already gives title, slug, status, draft/publish, revisions, preview, permissions, and admin list UI.
- A PWA app naturally has per-entry settings: short name, target URL, colors, template, niche, icon, screenshots.
- Yoast Duplicate Post can be used for clone/duplicate flows.

### Templates Live In The Plugin

Templates are stored under:

```text
templates/{template-key}/
```

Each template has a `template.json` file. The registry scans these folders automatically, so new templates do not need manual PHP registration.

Current templates:

- `default`
- `redirect`

Theme templates are intentionally not used. The PWA output should be isolated in the plugin, not dependent on the active WordPress theme.

### Niche Registry

Current niches:

```text
igaming
dating
```

Niches are used to filter available templates in the PWA settings metabox.

This is separate from the analytics plugin site category system. Analytics categories such as `MOB`, `CASUAL`, `COMICS`, and future `IGAMING` belong to the analytics plugin, not this PWA Builder.

### Images

Managers upload icon and screenshots through the `PWA Settings` metabox, not ACF.

Supported upload types:

- PNG
- JPG/JPEG
- WebP

SVG is intentionally not supported for manifest icons because the plugin crops and exports PNG sizes.

Generated image files are stored in:

```text
wp-content/uploads/wp-pwa-builder/{pwa_app_id}/
```

Generated files:

```text
icon-192.png
icon-512.png
screenshot-wide.png
screenshot-narrow.png
```

If uploaded images are missing or cannot be processed, the image endpoint generates fallback PNGs so the manifest does not break.

### Service Worker Strategy

This project is not an offline-first app.

The installed PWA is mostly a launch/attribution wrapper that opens `/start/` and redirects to the offer. Because of that, caching is intentionally conservative.

Current policy:

- no HTML page precache;
- no `/start/` caching;
- no manifest caching;
- no `sw.js` caching;
- no `wp-admin` caching;
- no `wp-json` caching;
- no URL with query string caching;
- only static assets can be cached: style, script, image, font.

Reason:

For marketing funnels, stale redirects or stale tracking URLs are dangerous. Correct analytics and fresh redirect behavior are more important than offline content.

### Analytics Boundary

Expected events:

```text
visit
click
app_install
app_open
```

Current understanding:

- `visit` / `click` for iGaming flow should go to `https://ig-core-cdn.com/v1/v`.
- PWA Builder dispatches frontend CTA `click`; PWA analytics decides how to deliver it to `https://ig-core-cdn.com/v1/v`.
- `app_install` should go to `https://ig-core-cdn.com/v1/action`.
- `app_install` must be triggered from the browser `appinstalled` event.
- `app_open` should be emitted by `/start/` before redirect and delivered by PWA analytics.

PWA Builder should dispatch frontend events. Analytics plugin should listen and send data to the correct backend endpoint.

Reason:

PWA Builder should not know all analytics endpoint/category/click_id logic. The analytics plugin owns that.

## Important Files

### `wp-pwa-builder.php`

Main plugin file.

Responsibilities:

- plugin header;
- constants;
- Composer/autoload fallback;
- activation/deactivation hooks;
- boot plugin on `plugins_loaded`.

### `includes/Plugin.php`

Main orchestrator/singleton.

Creates and boots:

- `Post_Types`
- `Media`
- `ACF_JSON`
- `Assets`
- `PWA_Endpoints`
- `Template_Loader`

### `includes/Post_Types.php`

Registers `pwa_app`.

Adds `PWA Settings` metabox with:

- niche;
- template;
- short name;
- manifest theme color;
- manifest background color;
- default CTA/offer URL;
- app icon;
- wide screenshot;
- mobile screenshot.

Saves these values as post meta.

### `includes/Niche_Registry.php`

Central list of PWA niches.

Current:

- `igaming`
- `dating`

### `includes/Template_Registry.php`

Scans template folders and reads `template.json`.

Provides:

- all templates;
- templates filtered by niche;
- selected template for a PWA app;
- template config for asset enqueueing.

### `includes/Template_Loader.php`

Loads the selected plugin template for singular `pwa_app` pages.

Important: theme templates were removed from this flow intentionally.

### `includes/Template_Shell.php`

Minimal HTML shell for PWA pages.

Calls:

- `wp_head()`
- `wp_body_open()`
- `wp_footer()`

This keeps plugin output isolated while still allowing WordPress scripts/styles/hooks to work.

### `includes/Assets.php`

Enqueues frontend/admin assets.

Frontend:

- `pwa-client.js` on normal PWA landing pages;
- `pwa-start.js` on `/start/` launch pages;
- selected template CSS/JS only on landing pages.

Also prints manifest and PWA head tags.

Asset versions are intentionally `null`, so WordPress does not add `?ver=` to plugin asset URLs.

### `includes/Environment.php`

Builds asset URLs.

Supports pretty URLs for production and query-based URLs for local/dev environments.

Important URLs:

```text
/apps/{slug}/manifest.webmanifest
/apps/{slug}/sw.js
/apps/{slug}/start/
/apps/{slug}/icon-192.png
/apps/{slug}/icon-512.png
/apps/{slug}/screenshot-wide.png
/apps/{slug}/screenshot-narrow.png
```

### `includes/PWA_Endpoints.php`

Registers rewrite rules and routes dynamic PWA assets to endpoint classes.

Endpoint classes:

- `Manifest_Endpoint`
- `Service_Worker_Endpoint`
- `Start_Endpoint`
- `Image_Endpoint`

### `includes/Endpoints/Manifest_Endpoint.php`

Outputs the web app manifest JSON.

Contains:

- `id`
- `name`
- `short_name`
- `description`
- `start_url`
- `scope`
- `display`
- `orientation`
- `background_color`
- `theme_color`
- `icons`
- `screenshots`

### `includes/Endpoints/Service_Worker_Endpoint.php`

Outputs JavaScript for the service worker.

Injects app-specific config before reading:

```text
assets/public/service-worker.js
```

Current config:

- app ID;
- cache name;
- scope.

### `assets/public/service-worker.js`

Minimal service worker.

Responsibilities:

- ignore non-GET requests;
- ignore non-http/https schemes;
- stay inside PWA scope;
- skip waiting on install;
- claim clients on activate;
- delete old PWA Builder caches;
- cache only allowed static assets.

### `includes/Endpoints/Start_Endpoint.php`

Serves `/apps/{slug}/start/`.

This is the technical URL opened by `start_url`.

Responsibilities:

- render hidden minimal fallback shell;
- expose fallback offer URL;
- enqueue `pwa-start.js`;
- redirect to offer immediately in the normal flow;
- allow future analytics to catch app launch before redirect.

### `assets/public/pwa-client.js`

Runs on the visible PWA landing/template page.

Responsibilities:

- register service worker;
- catch `beforeinstallprompt`;
- save deferred install prompt;
- handle CTA click;
- show browser install prompt if available;
- listen for `appinstalled`;
- dispatch frontend tracking events;
- redirect to `/start/`.

Important future change:

- event name should align with analytics: `app_install`.

### `assets/public/pwa-start.js`

Runs on `/start/`.

Responsibilities:

- detect standalone mode;
- dispatch launch-related events;
- redirect to offer URL via `window.location.replace()`.
- reveal fallback UI after `fallbackUiDelay` only if the browser stays on `/start/`.

### `includes/Media.php`

Defines manifest icons and screenshots.

Reads media attachment IDs from post meta:

- `pwa_app_icon`
- `pwa_screenshot_wide`
- `pwa_screenshot_narrow`

Hooks the image generator.

### `includes/Image_Assets/PWA_Image_Generator.php`

Generates exact PNG sizes for manifest assets.

Uses GD cover-crop logic so uploaded images can be any reasonable size and still become exact required sizes.

### `includes/Endpoints/Image_Endpoint.php`

Public endpoint for manifest images.

Order:

1. serve existing generated PNG;
2. try lazy generation;
3. try old uploaded-icon stream fallback for icons;
4. generate fallback PNG.

### `includes/ACF_JSON.php`

Configures ACF JSON load/save path:

```text
acf-json/
```

Currently image fields are not ACF. Template-specific fields can be added later through ACF JSON.

## Current Validation Status

Last known checks:

```text
php lint ok
node --check ok
autoload ok
template json ok
template niches ok
```

Manual test after latest push:

- images still show;
- install prompt still appears;
- no visible behavior regression.

## Next Planned Work

1. Walk through all files from zero as an educational/exam-prep explanation.
2. Then move to analytics integration:
   - keep visit in analytics plugin;
   - do not duplicate click;
   - add `app_install` handling from `appinstalled`.
3. Polish template:
   - ACF fields for template UI;
   - button text/color;
   - loader state;
   - open/continue state;
   - better default funnel UI.

## Fixed Flow Decisions

### PWA landing page

The visible PWA page is the only page that should be treated as the landing page.

Responsibilities:

- show the selected template UI;
- let the analytics plugin send the normal `visit`;
- let the analytics plugin update CTA/tracking URLs;
- let the analytics plugin try Meta/WebView exit before user interaction;
- let PWA Builder handle install/fallback only after CTA click.

For PWA pages, analytics must ignore `response.data.url` remarketing redirects. That logic belongs to ordinary landings and can bypass the PWA flow.

### CTA / install flow

All visible templates should use the same final technical step: `/apps/{slug}/start/`.

`/start/` is not only a fallback. It is the main redirect hub for every PWA scenario.

The visible template flow:

1. user clicks CTA;
2. PWA Builder stores the resolved CTA URL after analytics updated it;
3. CTA enters loading state while the browser prompt is open;
4. PWA Builder tries the browser install prompt if available;
5. if the prompt succeeds, CTA changes to `Open`;
6. user clicks `Open`;
7. PWA Builder sends user to `/apps/{slug}/start/`;
8. if the prompt is unavailable, failed, dismissed, blocked by WebView, or any unknown case happens, the user is sent to `/apps/{slug}/start/` immediately;
9. `/start/` resolves the stored launch URL first, falls back to the app offer URL, and redirects.

If the browser opens the visible landing page in standalone mode after installation, PWA Builder treats it as an installed launch and redirects to `/start/` immediately. It also listens for `display-mode` changes after `appinstalled`, because some browsers can switch/open the installed PWA without a full page reload.

To avoid a visible template flash, landing pages also print an early `<head>` guard: hide the body in standalone display mode and immediately redirect to `/start/`. This guard is not printed on `/start/`.

The rule is:

```text
CTA / installed app / fallback / unclear state -> /start/ -> offer
```

### `/start/` endpoint

`/start/` is not a template and should not reuse visible template UI.

It is a technical launch/redirect endpoint, similar to a controller action:

- no normal landing `visit`;
- no remarketing redirect from analytics;
- no template builder UI;
- no ACF/template design dependency;
- resolve the stored launch URL first, then fallback offer URL;
- optionally send future PWA-specific events through analytics;
- redirect to offer immediately.

Fallback UI may exist only as a backup if automatic redirect fails. In the normal flow, the user should not visibly see `/start/`.

### Analytics ownership

Analytics plugin owns:

- URL/query parameter collection;
- `visit`;
- click/tracking URL preparation;
- `click_id` / `user_id`;
- Meta/WebView external browser attempt;
- future PWA event transport such as `app_install`.

PWA Builder owns:

- manifest;
- service worker;
- PWA template rendering;
- install prompt orchestration;
- `/start/` launch endpoint;
- browser-side install/open signals.

## Presentation Explanation Core

Short version:

> This plugin models each PWA as a WordPress custom post type. For every PWA app it dynamically serves the manifest, service worker, start URL, icons, screenshots, and selected frontend template. The service worker is intentionally minimal because this is not an offline-first content app; it is a marketing PWA funnel that launches and redirects to an offer. Analytics is separated into the existing analytics plugin, while PWA Builder only emits browser-side events such as install and launch.
