# Siloq Connector — Changelog

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/) conventions.
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.5.60] — 2026-03-04 — [PR #62](https://github.com/Siloq-app/siloq-wordpress/pull/62)

### Added
- Admin dashboard fully redesigned as a purpose-built "what do I do next" interface
  for non-technical business owner clients
- Tabbed navigation: Dashboard / Pages / Business Data / Settings (CSS class-based
  show/hide; full ARIA: `role="tab"`, `aria-controls`, `aria-labelledby`)
- Zone 1 — Site Health Hero: animated SVG score ring (0–100), color-coded by threshold
  (red <50 / amber 50–74 / green 75–89 / teal 90+), plain-English issue sentence, single CTA
- Zone 2 — Three cards: AI Citation Readiness (CSS circular progress, `/schema-graph/completeness/`),
  Pages Needing Attention (colored dots, Fix It → WP post editor), Recent Wins
- Zone 3 — Activity footer: last synced timestamp, Sync Now button with spinner
- `ajax_get_dashboard_stats()` AJAX handler with 10-minute transient caching to prevent
  serial blocking requests; non-blocking pre-fetch warm-up included
- `assets/css/siloq-admin-dashboard.css` — CSS tokens, tab nav, cards, score ring, responsive
- `assets/js/siloq-admin-dashboard.js` — tab switching, ring/meter animation, AJAX loader,
  `safeUrl()` protocol validator, `escAttr()` with backtick escaping

### Changed
- `siloq-admin-dashboard` JS declares `siloq-admin` as dependency (guarantees `siloqAdminData` loads first)
- Tab panels use CSS class-only visibility (removed `hidden` attribute to avoid specificity conflict)

---

## [1.5.59] — 2026-03-04 — [PR #61](https://github.com/Siloq-app/siloq-wordpress/pull/61)

### Fixed
- Analyze button broken in Elementor 3.24+: `getCurrentPageView()` removed from panel API
- Three-strategy model reader added; `panel/open_editor/widget` hook caches model on open
- Payload serialization: `JSON.stringify` + `json_decode` — fixes nested arrays mangled by jQuery
- `applyToWidget()` updated to use `getModelFromPanel()` helper
- Observer disconnect: `stopPanelObserver()` added, wired to `elementor.on('destroy')`
- Debounce timer moved to module scope (was stored as property on `MutationObserver`)

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
- Widget Intelligence System — initial release for Elementor
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

*Versions 1.5.17–1.5.25 not yet backfilled. These were incremental fixes
and stability improvements shipped between Feb 26 and Mar 4, 2026.*
