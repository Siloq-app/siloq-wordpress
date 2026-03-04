# Siloq Connector — Changelog

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/) conventions.
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.5.63] — 2026-03-04 — [PR #61](https://github.com/Siloq-app/siloq-wordpress/pull/61)

### Fixed
- Siloq Intelligence panel not rendering in Elementor: hooks used hardcoded
  section IDs (`section_editor`, `section_title`, etc.) that differ across
  Elementor versions. Replaced all 6 specific hooks with a single generic
  `elementor/element/after_section_end` hook that injects after the first
  section of each supported widget regardless of section name. Version-proof.
- Widget model caching: `_cachedModel` set via `panel/open_editor/widget` hook
  (Elementor passes model directly). Analyze button now always has a model at
  click time without guessing which panel API to use.
- `readContentFromDOM()` added as final fallback: reads visible inputs from the
  Elementor panel DOM if all model strategies fail.
- Analyze button no longer bails with "Could not read widget content" — runs
  analysis even with empty content (server returns layer advice regardless).

---

## [1.5.62] — 2026-03-04 — [PR #61](https://github.com/Siloq-app/siloq-wordpress/pull/61)

### Fixed
- Intermediate release addressing Analyze button JS model reader (superseded
  by v1.5.63 which also fixed the PHP panel injection root cause).

---

## [1.5.61] — 2026-03-04 — [PR #63](https://github.com/Siloq-app/siloq-wordpress/pull/63)

### Fixed
- Business profile data never saved or loaded correctly (6 root causes):
  1. Wrong save endpoint: `/business/profile` → `/sites/{site_id}/entity-profile/`
  2. Wrong HTTP method: POST → PATCH (added PATCH support to `make_request()`)
  3. Wrong field names on save: `address` → `street_address`, `zip` → `zip_code`
  4. Wrong load endpoint: same `/business/profile` error on GET
  5. Missing field normalization on load: `service_cities ?? service_areas`
     dual-key fallback; `service_cities` array joined to textarea string
  6. WP options not synced after successful GET (local fallback stayed stale)
- `make_request()` in `includes/class-siloq-api-client.php`: PATCH support
  added via `wp_remote_request()` with `$args['method'] = 'PATCH'`

*Note: v1.5.60 (admin dashboard redesign, PR #62) is pending review and merge.
The version sequence intentionally skips 1.5.60 here to avoid a merge conflict
on version number. Merge order: #60 → #63 → #62.*

---

## [1.5.60] — 2026-03-04 — [PR #62](https://github.com/Siloq-app/siloq-wordpress/pull/62)

### Added
- Admin dashboard redesigned as a "what do I do next" interface for non-technical
  business owners: tabbed nav (Dashboard / Pages / Business Data / Settings),
  animated SVG score ring, AI Citation Readiness meter, Pages Needing Attention,
  Recent Wins, Sync Now button with spinner
- `assets/css/siloq-admin-dashboard.css` — design tokens, tab nav, cards,
  score ring, CSS progress meter, responsive breakpoints
- `assets/js/siloq-admin-dashboard.js` — tab switching, animations, AJAX stats
  loader, `safeUrl()` protocol validator, `escAttr()` with backtick escaping
- `ajax_get_dashboard_stats()` AJAX handler with 10-minute transient caching
  (prevents 4 sequential blocking requests; worst-case 8s instead of 40s)

### Changed
- `siloq-admin-dashboard` JS now depends on `siloq-admin` to guarantee
  `siloqAdminData` is available before dashboard JS runs
- Tab panels: use CSS class-only visibility — removed `hidden` attribute to
  avoid specificity conflict between the attribute and `display:none` CSS rule
- All tab buttons now have `aria-controls` + `id`; panels have `aria-labelledby`

---

## [1.5.59] — 2026-03-04 — [PR #61](https://github.com/Siloq-app/siloq-wordpress/pull/61)

### Fixed
- Analyze button broken in Elementor 3.24+: `getCurrentPageView()` removed
  from panel API; three-strategy model reader added as interim fix
- Siloq Intelligence panel moved to top of widget controls via MutationObserver
- Payload serialization: `JSON.stringify` + `json_decode` (fixes nested arrays
  mangled by jQuery form serialization)
- Observer disconnect: `stopPanelObserver()` wired to `elementor.on('destroy')`
- Debounce timer moved to module scope

---

## [1.5.58] — 2026-03-04 — [PR #60](https://github.com/Siloq-app/siloq-wordpress/pull/60)

### Added
- Widget Intelligence System expanded to all major WordPress page builders
  (previously Elementor-only in v1.5.57). New builder integrations:
  - Gutenberg (Block Editor) via `wp.plugins` InspectorControls
  - Divi via `et_fb_enqueue_assets` hook + MutationObserver
  - Beaver Builder via `FLBuilder.addHook` + MutationObserver
  - WPBakery via MutationObserver on `.vc_ui-panel-content-area`
  - Bricks via MutationObserver + `bricksData` global
  - Oxygen via MutationObserver + AngularJS scope integration
  - Cornerstone via MutationObserver + Redux `_x_app.store`
  - Classic Editor via DOM-ready metabox injection
- Shared core architecture extracted into two files:
  - `includes/class-siloq-widget-intelligence-core.php` — PHP layer:
    page layer detection, heading hierarchy validation, image alt analysis,
    AI prompt builder, entity context assembly
  - `assets/js/siloq-intelligence-core.js` — JS layer:
    panel rendering, AJAX dispatch, results rendering,
    image generation modal, event delegation
- Per-builder features (all builders):
  - Analyze This Widget button in editor panels
  - Page layer badge (Hub / Spoke / Supporting)
  - Layer violation and heading hierarchy warnings
  - One-click Apply to push suggested content back to widget
  - Image Intelligence with AI prompt generation modal
  - All JS wrapped in top-level try/catch — silent failure if builder absent
- Admin-only loading: entire Intelligence System gated behind `is_admin()`
- Builder auto-detection via `Siloq_Builder_Detector::detect()` with
  per-request caching — only the matching builder class is loaded

### Changed
- `siloq-connector.php` — version bumped `1.5.57 → 1.5.58`; `load_dependencies()`
  updated to require core module and dispatch to detected builder class

---

## [1.5.57] — 2026-03-04 — [PR #59](https://github.com/Siloq-app/siloq-wordpress/pull/59)

### Added
- Widget Intelligence System — first production release for Elementor
  (an early prototype was scaffolded in v1.5.16 but never shipped to users;
  v1.5.57 is the first complete, functional version)
  - Native panel injection into Elementor widget settings sidebar via
    `elementor/element/*/after_section_end` hooks
  - Supported widgets: text-editor, heading, icon-box, image-box,
    accordion, toggle
  - Page layer detection (Hub / Spoke / Supporting) with advisory notes
  - Heading hierarchy validation — warns on H1 duplicates and tag gaps
  - Image intelligence: placement recommendations and alt tag suggestions
  - Generate Image modal (Midjourney/DALL-E prompt pre-filled)
  - Container awareness: full Elementor layout structure sent per request
  - Local fallback analysis when Siloq API is unavailable
  - `class-siloq-widget-intelligence.php` — PHP host class (RAW_HTML control,
    AJAX handler, local fallback)
  - `assets/js/siloq-widget-intelligence.js` — JS integration
  - `assets/css/siloq-widget-intelligence.css` — panel styles

---

## [1.5.26] — 2026-02-26 — [PR #36](https://github.com/Siloq-app/siloq-wordpress/pull/36)

### Fixed
- ModSecurity rejection on shared hosting: changed outbound User-Agent
  from `python-requests` to `Siloq/1.0`
- Webhook initialization order: `require_once` now precedes `class_exists()`
  check — previously always returned false
- `make_request()` now returns parsed `{success, data, message}` instead
  of raw `WP_HTTP` response object
- AIOSEO 4.x meta update now uses `INSERT ... ON DUPLICATE KEY UPDATE`
  pattern (AIOSEO 4.x stores meta in `wp_aioseo_posts`, not `wp_postmeta`)
- Corrected event field names: `event_type` (not `event`),
  `page.update_meta` (not `meta.update`)
- Unified JS localized object references (was inconsistently `wpData`,
  `siloqAI`, and `siloqAdminData` across files)

---

## [1.5.16] — 2026-02-26

### Added
- Webhook handler with HMAC-SHA256 signature validation
  (replaced `__return_true` permission callback on data-writing endpoint)
  `includes/class-siloq-webhook-handler.php`

### Fixed
- JS SyntaxError: `<?php _e() ?>` inside JavaScript string literal — caused
  entire script block to fail silently; removed inline PHP from JS context

---

## [1.5.15] — 2026-02-26

### Fixed
- `siloqAjax` localization: `wp_localize_script` outputs in footer;
  inline PHP scripts that ran earlier referenced undefined object.
  Added `typeof siloqAjax !== 'undefined'` guard throughout.

---

## [1.5.14] — 2026-02-25

### Fixed
- Schema output in `wp_head`: `schema_type` was being written to
  `wp_postmeta` via standard meta API but AIOSEO 4.x ignores `wp_postmeta`
  for schema — output was silently dropped

---

## [1.5.13] — 2026-02-24

### Added
- Real API integration: content generation wired to Siloq API
  (TALI — Text and Layer Intelligence)
- Replaced all stub/mock responses with live API calls

---

*Versions 1.5.00–1.5.12 not yet backfilled. Development history predates
structured changelog tracking. Key milestone: initial plugin scaffold
established with sync, settings, and admin metabox in v1.5.00.*
