# WP PWA Builder: Working Technical Specification

## Goal

Build an internal WordPress-based PWA builder that replaces the current third-party PWA service for marketing campaigns.

The service must let managers create many PWA landing/apps on many domains, across different niches, with reusable templates and predictable technical behavior.

This specification covers only the core PWA Builder plugin.

Push notifications, analytics/tracking, and cloaking must stay separate systems/plugins with clear integration points.

## Scope

### In Scope

- WordPress plugin for managing PWA apps.
- Custom post type for PWA records.
- Template builder architecture.
- Optional custom HTML template mode.
- Reusable interactive component system.
- Redirect-only starter template/flow.
- Niche support.
- OS-specific rendering for Android, iOS, and fallback/desktop.
- Dynamic PWA manifest generation.
- Dynamic service worker generation.
- PWA app icons and screenshots.
- Clean frontend shell without the active WordPress theme header/footer.
- Traffic parameter preservation for later analytics handling.
- Frontend hooks/events for analytics plugin integration.
- Extensible template discovery through folders and `template.json`.

### Out Of Scope

- Push notification management.
- Push campaigns.
- Firebase setup.
- Analytics event delivery to external analytics tools.
- Advertiser postbacks.
- Meta Pixel management.
- Cloaking and anti-fraud filtering.
- Role/permission management.
- Full change history/audit log.

These features should be handled by separate plugins/modules.

## Plugin Boundaries

### PWA Builder Plugin

Responsible for:

- Creating and rendering PWA app pages.
- Managing app settings.
- Selecting template and OS variant.
- Generating `manifest.webmanifest`.
- Serving `sw.js`.
- Registering PWA assets.
- Providing frontend DOM hooks for tracking.
- Preserving incoming traffic parameters.

Not responsible for:

- Sending analytics to external services.
- Running cloaking decisions.
- Sending push notifications.
- Managing user roles.

### Analytics / Tracking Plugin

Responsible for:

- Reading query parameters.
- Creating and storing click/user identifiers.
- Sending visits, clicks, installs, opens, and redirects to analytics.
- Updating offer URLs with required parameters.
- Managing Meta Pixel and advertiser tracking logic.
- Listening to PWA Builder frontend events.

Expected PWA Builder hooks:

- CSS class: `.analytic-url`
- Attribute: `data-pwa-track`
- JS event: `wp-pwa-builder:track`
- JS event: `wp-pwa-builder:install-prompt`
- JS event: `wp-pwa-builder:appinstalled`
- JS event: `wp-pwa-builder:launched`

### Push Plugin

Responsible for:

- Firebase/Web Push setup.
- VAPID/public keys.
- Subscription flow.
- Push campaign delivery.
- Push open/close events.
- Resubscribe handling.

Expected PWA Builder integration:

- Ability to inject push logic into service worker.
- Ability to enqueue push frontend script only when enabled.
- Ability to disable push per app/domain.

### Cloaking Plugin

Responsible for:

- Filtering traffic by rules.
- White page / black page decisions.
- Bot, proxy, VPN, geo, device, referrer, or campaign checks.
- Logging cloak decisions.

Expected PWA Builder integration:

- A filter before rendering a PWA app page.
- A filter before redirecting to offer.
- Ability to override rendered page or redirect target.

## Main Entity: PWA App

PWA App should be a WordPress custom post type.

Recommended CPT:

```text
pwa_app
```

Recommended public URL:

```text
/apps/{slug}/
```

Each PWA App represents one marketing PWA/campaign entry.

One PWA App may render different frontend variants depending on user OS/device, but managers should fill the app content only once.

## PWA App Fields

### Core Fields

- App name.
- App slug.
- Short name.
- Status.
- Niche.
- Template.
- Flow behavior.
- Target / offer URL.
- Theme color.
- Background color.
- App icon.
- Wide screenshot.
- Mobile screenshot.

### Template Content Fields

These fields depend on the selected template family.

Example for App Store / Play Market template:

- Developer name.
- Rating.
- Reviews count.
- Installs count.
- Age label.
- Category.
- Description.
- Screenshots gallery.
- Reviews/comments repeater.
- CTA button text.
- Privacy/data safety blocks.

Managers should fill these fields once. Android/iOS variants should reuse the same values.

## Slug vs Short Name

### Slug

The slug is part of the URL.

Example:

```text
/apps/lanista-glory/
```

It is technical and should be unique on the domain.

### Short Name

The short name is used by the PWA manifest and may be shown under the installed app icon.

Example:

```text
Lanista
```

It should be short enough for mobile UI.

## Niches

The builder must support multiple niches.

Examples:

- `igaming`
- `insurance`

Requirements:

- Each PWA App has one selected niche.
- Templates may declare which niches they support.
- In admin, the template dropdown must show only templates available for the selected niche.
- New niches should be addable through code/config without rewriting template logic.

## Template Architecture

Templates should be discovered automatically from folders.

Recommended structure:

```text
templates/
  store-app/
    template.json
    android/
      template.php
      style.css
      script.js
    ios/
      template.php
      style.css
      script.js
    fallback/
      template.php
      style.css
      script.js
```

The template should not require manual PHP registration.

Adding a new template should require:

1. Create a folder in `templates/`.
2. Add `template.json`.
3. Add template files/assets.
4. Add ACF JSON field group if needed.

## Template Manifest

Each template folder must include `template.json`.

Example:

```json
{
  "name": "App Store / Play Market",
  "niches": ["igaming", "insurance"],
  "variants": {
    "android": {
      "template": "android/template.php",
      "styles": ["android/style.css"],
      "scripts": ["android/script.js"]
    },
    "ios": {
      "template": "ios/template.php",
      "styles": ["ios/style.css"],
      "scripts": ["ios/script.js"]
    },
    "fallback": {
      "template": "fallback/template.php",
      "styles": ["fallback/style.css"],
      "scripts": ["fallback/script.js"]
    }
  }
}
```

Legacy/simple template support may stay available:

```json
{
  "name": "Default",
  "niches": ["igaming"],
  "template": "template.php",
  "styles": [],
  "scripts": []
}
```

## Custom HTML Template Mode

The builder should support a controlled custom HTML mode for managers who want to upload their own UI instead of using a predefined developer-made template.

This mode should behave as a template family, for example:

```text
templates/custom-html/
  template.json
  template.php
  style.css
  script.js
```

The plugin still owns:

- PWA shell.
- `wp_head()`.
- `wp_footer()`.
- Manifest tags.
- Service worker registration.
- Analytics DOM hooks.
- Flow behavior.
- CTA handling.
- Parameter preservation.

Managers only provide the inner visual HTML/content area.

### Custom HTML Fields

Recommended fields:

- Custom HTML body.
- Optional custom CSS.
- Optional custom JS if allowed.
- Uploaded assets gallery.
- CTA selector or required CTA placeholder.
- OS variant selection if needed.

### Recommended Placeholder API

Managers should use approved placeholders instead of hardcoding dynamic values.

Examples:

```html
{{app_name}}
{{short_name}}
{{app_icon}}
{{cta_url}}
{{cta_button}}
{{rating}}
{{screenshots}}
```

For CTA, the safest option is a required placeholder:

```html
{{cta_button}}
```

The plugin renders the real CTA element with required classes/attributes:

```html
<a class="analytic-url" data-pwa-track="custom_html_cta">Continue</a>
```

Alternative:

```html
<a href="{{cta_url}}" class="analytic-url" data-pwa-track="custom_html_cta">Continue</a>
```

### Validation Requirements

Custom HTML must be validated before saving or rendering.

Disallowed:

- `<script>` tags by default.
- Inline event handlers such as `onclick`, `onload`, `onerror`.
- External JS URLs.
- External CSS URLs unless explicitly allowed.
- `<iframe>`.
- `<object>`.
- `<embed>`.
- `<form>` unless explicitly allowed.
- Dangerous URL schemes such as `javascript:`, `data:text/html`, `vbscript:`.
- Unknown tracking pixels/scripts.

Allowed:

- Structural HTML.
- Images from uploaded media or approved domains.
- CSS classes.
- Safe inline styles if allowed by policy.
- Approved placeholders.
- Approved CTA placeholder.

### Custom CSS Requirements

Custom CSS should be optional and sanitized.

Disallowed:

- `@import`.
- Remote font imports unless approved.
- `url(javascript:...)`.
- CSS that hides required compliance/CTA elements if such elements are required.

Open question:

- Whether CSS should be stored as raw CSS or generated through a safer visual editor later.

### Custom JS Requirements

Default recommendation:

- Do not allow custom JS in MVP.

Reason:

- Custom JS can steal traffic, break tracking, override redirects, inject pixels, or bypass plugin logic.

If custom JS becomes required later:

- Allow only for trusted developer role.
- Store separately from manager HTML.
- Validate and review before publish.
- Consider a restricted hook API instead of arbitrary JS.

### Asset Requirements

Managers should upload assets through WordPress Media Library.

Custom HTML should reference assets through placeholders or selected media fields, not random external URLs.

Recommended:

- Store uploaded custom-template assets per PWA App.
- Validate image dimensions/weight.
- Generate optimized sizes where possible.

### Security Notes

Custom HTML must not be rendered directly from raw post meta without sanitization.

Recommended implementation:

- Sanitize on save.
- Sanitize again on render.
- Use a strict allowlist of tags/attributes.
- Keep custom HTML inside the plugin shell.
- Keep install/redirect/tracking logic outside manager-controlled HTML.

This feature is useful, but it should be treated as a controlled sandbox, not arbitrary file upload.

## Custom Template Authoring Guide

Managers may use AI tools to generate custom template UI, but the generated output must follow a strict contract.

This guide should be shared with anyone creating custom templates.

### What Managers Can Provide

- Inner HTML markup.
- CSS for visual styling.
- Images uploaded through WordPress Media Library.
- Approved placeholders.
- Approved interactive component placeholders.

### What Managers Must Not Provide

- Full HTML documents with `<!doctype>`, `<html>`, `<head>`, or `<body>`.
- Any `<script>` tags.
- Inline JS attributes such as `onclick`, `onload`, `onerror`, `onmouseover`.
- External scripts.
- External trackers or pixels.
- Iframes.
- Forms that submit data externally.
- Hardcoded offer links.
- Hardcoded analytics endpoints.
- Code that redirects the user.
- Code that hides or replaces the CTA after render.
- Links with dangerous schemes such as `javascript:`.

### Required Output Format

The custom template should be delivered as:

```text
template.html
template.css
assets/
  image-1.webp
  image-2.webp
```

`template.html` must contain only inner markup.

Good:

```html
<section class="custom-hero">
  <img class="custom-hero__icon" src="{{app_icon}}" alt="{{app_name}}">
  <h1>{{app_name}}</h1>
  <p>{{app_description}}</p>
  {{slot_machine}}
  {{cta_button}}
</section>
```

Bad:

```html
<!doctype html>
<html>
  <head>
    <script src="https://example.com/tracker.js"></script>
  </head>
  <body onclick="window.location='https://offer.example'">
    <a href="https://offer.example">Download</a>
  </body>
</html>
```

### Required CTA Rule

Every custom template must include:

```html
{{cta_button}}
```

Managers must not create CTA links manually.

Reason:

- The plugin must control tracking classes.
- The analytics plugin must be able to update the URL.
- The flow layer must decide install/redirect behavior.
- The service must prevent traffic stealing or broken attribution.

### Available Data Placeholders

Initial placeholder list:

```text
{{app_name}}
{{short_name}}
{{app_description}}
{{app_icon}}
{{developer_name}}
{{rating}}
{{reviews_count}}
{{installs_count}}
{{age_label}}
{{category}}
{{screenshots}}
{{cta_button}}
```

All placeholders should be rendered by the plugin.

Unknown placeholders should be rejected or ignored with a validation warning.

### Interactive Component Placeholders

Custom templates may include approved interactive components.

Initial component candidates:

```text
{{slot_machine}}
{{spin_wheel}}
{{scratch_card}}
{{quiz}}
{{fake_loader}}
{{countdown_timer}}
{{install_progress}}
```

These components should be implemented and maintained by developers inside the PWA Builder plugin.

Managers may place components in their HTML, but they must not provide component JS logic.

### AI Prompt For Managers

Managers can give this prompt to an AI tool:

```text
Create a mobile-first PWA landing inner template.

Return only two files:
1. template.html
2. template.css

Rules:
- Do not include <!doctype>, <html>, <head>, or <body>.
- Do not include any <script> tags.
- Do not use inline JavaScript attributes such as onclick, onload, onerror.
- Do not use iframe, object, embed, or external trackers.
- Do not hardcode offer links or redirect logic.
- Do not use external JS or CSS URLs.
- Use only safe semantic HTML and CSS.
- The layout must fit mobile screens first and also look acceptable on desktop.
- Use these placeholders for dynamic data:
  {{app_name}}, {{short_name}}, {{app_description}}, {{app_icon}},
  {{developer_name}}, {{rating}}, {{reviews_count}}, {{installs_count}},
  {{age_label}}, {{category}}, {{screenshots}}, {{cta_button}}.
- The template must include {{cta_button}} exactly once.
- If an interactive block is needed, use one approved placeholder:
  {{slot_machine}}, {{spin_wheel}}, {{scratch_card}}, {{quiz}},
  {{fake_loader}}, {{countdown_timer}}, {{install_progress}}.
- Do not implement JavaScript for interactive blocks.
- Use class names prefixed with custom-template__ or custom-template-.
- Do not use fixed widths wider than 100vw.
- Do not create text that overlaps on 320px wide screens.
```

### Validation Checklist

Before saving a custom template, the plugin should check:

- CTA placeholder exists.
- CTA placeholder is used only once.
- No script tags.
- No inline JS attributes.
- No disallowed tags.
- No dangerous URL schemes.
- No external scripts/styles.
- No unknown placeholders.
- HTML is not a full document.
- CSS does not use `@import`.
- CSS does not include suspicious remote URLs.

If validation fails, the plugin should show clear admin errors and prevent publishing.

## Interactive Component System

Interactive components should be reusable across both developer-made templates and custom HTML templates.

They should not belong only to `custom-html`.

Examples:

- Store/App template may use `{{install_progress}}`.
- Slots template may use `{{slot_machine}}`.
- Wheel template may use `{{spin_wheel}}`.
- Custom HTML template may use the same placeholders.

Recommended structure:

```text
components/
  spin-wheel/
    component.json
    render.php
    style.css
    script.js
  slot-machine/
    component.json
    render.php
    style.css
    script.js
  install-progress/
    component.json
    render.php
    style.css
    script.js
```

Each component should define its public placeholder and assets.

Example:

```json
{
  "name": "Spin Wheel",
  "placeholder": "{{spin_wheel}}",
  "styles": ["style.css"],
  "scripts": ["script.js"],
  "tracks": ["spin_start", "spin_finish"]
}
```

Components should be inserted through placeholders:

```html
{{spin_wheel}}
{{slot_machine}}
{{install_progress}}
```

Internally, the plugin may render them through a shortcode-like renderer, but manager-facing templates should use placeholders.

### Component Requirements

- Components must be registered by the plugin.
- Components must be sanitized and rendered by trusted PHP.
- Component JS must be owned by developers, not uploaded by managers.
- Component assets should be enqueued only when the component is used.
- Components should not own final redirect logic.
- Components may dispatch tracking events.
- Components may expose controlled configuration fields through ACF or template settings.

### Component Tracking

Component JS should use the shared frontend tracking event.

Example:

```js
window.dispatchEvent(new CustomEvent('wp-pwa-builder:track', {
  detail: {
    event: 'spin_finish',
    component: 'spin_wheel',
    result: 'bonus'
  }
}));
```

The analytics plugin decides how to send this event.

The PWA Builder only emits the event.

## Redirect Starter Template / Flow

The first MVP template should be a redirect-focused PWA flow with minimal or no visible UI.

Purpose:

- Let managers test PWA behavior without building a prelanding page.
- Preserve parameters.
- Trigger required frontend events.
- Support install/open behavior.
- Redirect to offer when configured.

This should be treated as a starter template and/or starter flow.

Suggested template slug:

```text
redirect
```

Suggested flow slug:

```text
redirect_or_install
```

### Redirect Template Fields

Required fields:

- App name.
- Short name.
- App icon.
- Offer URL.
- Theme color.
- Background color.
- Optional loading text.
- Optional redirect delay.
- Flow behavior.

Optional fields:

- Screenshot for richer install UI.
- Fallback message.
- iOS instruction text.
- Android install button text if UI fallback is shown.

### Redirect Flow Behavior

The redirect flow should support OS/browser differences.

Recommended initial behavior:

#### Android With Install Prompt Available

1. Preserve incoming params.
2. Emit visit/landing event.
3. Prepare install prompt.
4. If user action is required by browser, show minimal CTA/install UI.
5. On successful install, emit install event.
6. On installed app launch, redirect to offer with preserved params/click identifiers.

Important note:

- Browsers generally do not allow a PWA to install automatically without user interaction.
- The service can make the flow minimal, but should not rely on silent install.

#### Android Without Install Prompt

1. Preserve incoming params.
2. Emit visit/landing event.
3. Use fallback behavior:
   - redirect to offer;
   - or show minimal CTA;
   - or try external browser escape if enabled.

#### iOS

1. Preserve incoming params.
2. Emit visit/landing event.
3. Since iOS does not support the Android-style install prompt, use configured fallback:
   - redirect to offer immediately;
   - or show instruction;
   - or try external browser escape if enabled.

#### Desktop / Unknown

1. Preserve incoming params.
2. Emit visit/landing event.
3. Use configured fallback.

### Redirect Events

The redirect flow should emit frontend events for analytics plugin:

```text
visit
redirect_template_loaded
install_prompt_available
install_prompt_shown
install_prompt_accepted
install_prompt_dismissed
appinstalled
installed_launch
redirect_started
redirect_completed
redirect_failed
```

The analytics plugin decides which of these events are sent to external analytics.

### Redirect URL Rules

The final offer URL must be produced by the analytics/tracking layer or through a shared URL builder.

The redirect template must not hardcode parameter mapping.

Required:

- Original incoming params must be preserved.
- Analytics-generated `click_id`/`user_id` must be preserved if available.
- Redirect URL must pass through filter/hook before navigation.

### MVP Recommendation

Start with the redirect template/flow before building visual templates.

Reason:

- It validates manifest.
- It validates service worker.
- It validates install behavior.
- It validates parameter preservation.
- It validates analytics integration.
- It gives managers a usable campaign mode before full template builder is ready.

## Better Links Reference Notes

The current third-party service documentation describes `Links` as PWA applications.

Observed documentation sections:

- Create link.
- Links/PWA applications table.
- General settings.
- Domain setup.
- Cloudflare cloak.
- Keitaro cloak.
- Integrations.
- Facebook integration.
- Dynamic pixel.
- Static pixel.
- Pushes.
- Flows.
- Default flow.

Important flow-related notes from their documentation:

- The `Flows` tab is used to configure user routing logic and traffic distribution.
- `Default flow` can apply filters by operating system.
- `Default flow` can configure link splitting.
- Pushes, integrations, cloaking, and flows are separate tabs/settings around the same PWA link.

This supports our planned architecture:

- PWA App as the main record.
- Flow behavior separated from visual templates.
- OS-specific behavior handled by flow/template variant logic.
- Pushes, analytics/integrations, and cloaking kept separate from core rendering.

Open finding:

- The public manual confirms flows and OS filtering, but the exact implementation of their redirect template was not clearly documented in the visible manual content.
- We should still implement our own redirect starter flow based on browser/PWA constraints instead of copying undocumented behavior.

## OS Variants

The same PWA App may render different templates by OS:

- Android: Google Play-style page and install prompt flow.
- iOS: App Store-style page and redirect/instruction fallback.
- Fallback: desktop/unknown browser layout.

Managers should not create separate PWA records for Android and iOS.

The plugin should detect OS on request and choose:

1. Exact OS variant if available.
2. `fallback` variant if available.
3. Legacy `template.php` if available.
4. Default plugin template.

## Frontend Shell

PWA pages must not use the active theme header/footer.

The plugin should render a minimal shell:

- `<!doctype html>`
- `<html>`
- `<head>`
- `wp_head()`
- `<body>`
- `wp_body_open()`
- selected template
- `wp_footer()`

The active theme should not inject navigation, blog footer, sidebars, or layout wrappers into PWA pages.

## Manifest

Each PWA App should have a dynamic manifest URL.

Recommended URL:

```text
/apps/{slug}/manifest.webmanifest
```

Manifest should include:

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

### Icons

Required sizes:

- 192x192
- 512x512

Recommended:

- Use separate icon entries for `any` and `maskable` if maskable support is needed.
- Avoid `purpose: "any maskable"` on the same icon unless padding is known to be correct.

### Screenshots

Recommended screenshots:

- Wide screenshot for desktop install UI.
- Narrow/mobile screenshot for mobile install UI.

Screenshots may be optional during development but should be available for production campaigns.

## Start URL

`start_url` is the URL opened when the installed PWA is launched from the home screen or desktop.

Important issue:

- If the original ad URL contains tracking parameters and the installed app opens later without them, attribution may be lost.

Possible strategies:

### Strategy A: Params In Start URL

Generate a per-click manifest where `start_url` contains tracking params.

Pros:

- Parameters are available when app opens.

Cons:

- More complex caching.
- Manifest becomes user/session-specific.

### Strategy B: Store Params Locally

Use a clean `start_url`, but store first-visit params in cookie/localStorage.

Pros:

- Simpler manifest.

Cons:

- Depends on browser storage behavior.
- May be fragile across in-app browsers and installed context.

### Strategy C: Hybrid

Analytics plugin creates `click_id`/`user_id`. PWA Builder includes only stable identifiers in `start_url`.

Pros:

- Cleaner URL.
- Better attribution continuity.

Cons:

- Requires analytics plugin integration.

Recommended direction:

- Use hybrid strategy.
- Preserve all incoming params on first visit.
- Let analytics plugin generate/own click identifiers.
- Keep PWA Builder ready to include stable identifiers in `start_url`.

## Service Worker

Each PWA App should have a dynamic service worker URL.

Recommended URL:

```text
/apps/{slug}/sw.js
```

Service worker should:

- Install successfully.
- Activate and clean old cache versions.
- Only cache safe same-origin HTTP/HTTPS GET requests.
- Ignore unsupported schemes such as `chrome-extension:`.
- Avoid caching consumed responses.
- Provide extension point for Push plugin.

The core PWA Builder service worker should stay minimal.

Push logic should be injected only when Push plugin is enabled.

## Install / Redirect Flow

The plugin should not assume that install is always available.

### Android

Expected behavior:

1. Listen for `beforeinstallprompt`.
2. Store the deferred prompt.
3. On CTA click, call `deferredPrompt.prompt()`.
4. Track prompt result through frontend event.
5. Listen for `appinstalled`.
6. After installed app launch, handle configured flow behavior.

Fallback:

- If install prompt is unavailable, use configured fallback action.

### iOS

iOS does not provide the same install prompt flow.

Expected behavior:

- Detect iOS.
- Detect standalone mode where possible.
- If not installed, use configured fallback:
  - redirect to offer;
  - show install instruction;
  - open external browser attempt;
  - another configured behavior.

### Meta In-App Browser

Most traffic may come from Meta in-app browser.

Expected behavior:

- Detect in-app browser.
- Try configured external browser escape if enabled.
- If escape fails, use fallback action.
- Preserve tracking params regardless of flow.

## Flow Behavior

Flow should be separate from template.

Example flow options:

- `install_then_redirect`
- `redirect_only`
- `show_instruction_then_redirect`
- `open_external_then_install`
- `installed_open_redirect`

Each PWA App should have a selected flow behavior.

Template decides how the page looks.

Flow decides what happens when user clicks CTA.

## Traffic Parameters

PWA Builder should preserve common incoming traffic parameters for analytics plugin.

Examples:

- `utm_source`
- `utm_medium`
- `utm_campaign`
- `utm_content`
- `campaign_id`
- `adset_id`
- `ad_id`
- `campaign_name`
- `adset_name`
- `ad_name`
- `placement`
- `site_source_name`
- `fbclid`
- `pixel_id`
- `pixel`
- `click_id`
- `c`
- `user_id`

PWA Builder should not send these to analytics itself.

It should:

- Make them available to frontend JS.
- Keep them available for `start_url`/launch flow.
- Let analytics plugin decide how to persist and send them.

## Admin UX

Managers should be able to:

- Create a new PWA App.
- Select niche.
- Select template filtered by niche.
- Fill template fields.
- Upload app icon.
- Upload screenshots.
- Configure manifest colors.
- Configure offer URL.
- Configure flow behavior.
- Preview the PWA.
- Publish/unpublish/archive the PWA.

The admin should avoid duplicate work.

Managers should not fill Android and iOS content separately unless a template explicitly needs OS-specific overrides.

## Statuses

Required statuses should be finalized with team lead.

Suggested statuses:

- Draft.
- Active.
- Paused.
- Archived.

WordPress post statuses may be used initially, but custom campaign status may be needed later.

## ACF Strategy

ACF JSON should be used for field groups.

Recommended:

- Store ACF JSON in the plugin folder.
- Keep global/core PWA fields in one group.
- Keep template-specific fields in separate groups.
- Use conditional logic based on selected template where possible.

Open question:

- Whether template-specific ACF JSON should live inside each template folder or in a central `acf-json/` folder.

Recommended initial approach:

- Use central `acf-json/` for now.
- Revisit per-template field groups when template count grows.

## Development / Production Modes

The plugin should support dev/prod differences.

Examples:

- Pretty asset URLs in production.
- Query-string asset URLs on local dev if needed.
- Environment-aware service worker and manifest URLs.
- Avoid hardcoded local paths.

Environment detection should be centralized.

## Extension Points

Needed filters/actions:

- Filter available niches.
- Filter available templates.
- Filter selected OS variant.
- Filter manifest data.
- Filter `start_url`.
- Filter service worker config.
- Action before rendering PWA app.
- Action after rendering PWA app.
- JS event for frontend tracking.
- Optional integration point for push service worker code.
- Optional integration point for cloaking decision.

## MVP Deliverables

### Phase 1: Core Builder

- CPT `pwa_app`.
- Basic settings metabox.
- Niche registry.
- Auto template registry.
- Clean frontend shell.
- Default template.
- Manifest endpoint.
- Service worker endpoint.
- Icon and screenshot fields.
- Basic frontend tracking events.

### Phase 2: OS Variants And Flow

- Template variants in `template.json`.
- OS detection.
- Android/iOS/fallback template loading.
- Flow behavior setting.
- CTA behavior layer.
- Installed launch handling.
- Meta in-app browser detection.

### Phase 3: Template Builder Foundation

- App Store / Play Market template family.
- Shared ACF fields.
- Repeater fields for screenshots/reviews.
- Better preview experience.
- Template-specific assets.

### Phase 4: Integrations

- Analytics plugin event listener.
- Stable click/user identifier handoff.
- Push plugin integration point.
- Cloaking plugin integration point.

## Open Questions For Team Lead

- Which flow should be default for Android?
- Which flow should be default for iOS?
- Should iOS show install instructions or immediately redirect?
- Should installed PWA always redirect to offer on open?
- Should the PWA page ever stay usable after installation, or is it only a redirect shell?
- Which traffic params are mandatory and must never be lost?
- Should `start_url` include full params or only stable identifiers?
- Which statuses are required for managers?
- Is one PWA App equal to one campaign, one offer, or one creative?
- Do we need preview links before publishing?
- Do we need domain-level settings?
- Do we need per-niche default templates/settings?
- Where should template-specific ACF JSON live long term?
- Which parts must be editable by managers and which should stay developer-only?

## Current Architectural Decision

For now, build the PWA Builder as a clean core service:

- One PWA App record.
- One shared content set.
- Template family selected once.
- OS-specific variants rendered automatically.
- Analytics/push/cloak handled outside the core plugin.

This keeps the PWA Builder focused and makes future integrations safer.
