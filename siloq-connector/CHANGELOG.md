# Siloq Connector — Changelog

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/) conventions.
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.5.117] — 2026-03-06

### Added
- **Image Audit** — new `Siloq_Image_Audit` class scans all published pages for image issues: missing images, stock photos, missing alt text, and unoptimized filenames. Stock detection checks 11 agency watermarks, 6+ digit filenames, and common camera patterns (DSC_, IMG_, photo-).
- **Dashboard Image Audit card** — colored dot summary (red/yellow/green) of image status counts, with link to the Image Brief submenu page.
- **Printable Image Brief** — new submenu page groups pages by status and shows photo type, shot brief, recommended filename, and recommended alt text for each. Print-friendly CSS hides WP admin chrome.
- **Sync hook** — image audit runs automatically after every full sync so results stay current.
- **AJAX endpoints** — `siloq_get_image_audit` (read results) and `siloq_apply_image_seo` (fix alt text + attachment title in one click).

## [1.5.115] — 2026-03-06

### Fixed
- **OpenAI fallback for content suggestions** — when the Siloq API call to `suggest-widget-edit/` fails (non-200, WP error, or missing suggestion), the plugin now calls OpenAI `gpt-4o-mini` directly as a fallback before falling through to local-only suggestion. The "API unavailable" message only appears when both Siloq and OpenAI are unavailable.
- **Image prompt realism** — replaced generic stock-photo prompt with documentary-style template specifying male tradesperson aged 35-55, action verb derived from service type, Canon DSLR photojournalism style, and explicit anti-stock-photo directives.
- **JetEngine / dynamic widget detection** — `ajax_analyze_widget` now detects JetEngine listing grids, ACF fields, Pods, and other dynamic widgets. Returns a structured response with CPT post titles/excerpts instead of running analysis on template markup. JS side shows a dedicated info panel explaining the widget is dynamic and linking to the underlying posts.

## [1.5.114] — 2026-03-06��### Fixed�- **Schema "Staged schema data is invalid." error** — Two fixes: (1) `resolve_service_cities()` now JSON-decodes the stored cities array before falling back to explode — prevents `["Kansas City"]` being used as the city name (double-encoding bug). (2) `ajax_apply_schema()` falls back to `_siloq_schema_json` if `_siloq_suggested_schema` is missing or undecodable, and as a last resort re-generates schema on the fly rather than showing an error. Adds `error_log` on JSON decode failure for debugging.�- **Image prompt service label** — page title extraction for DALL-E prompt (from v1.5.113, carried forward).��
## [1.5.113] — 2026-03-06

### Fixed
- **Image prompt uses actual service label** — was passing raw `siloq_business_type` slug (e.g. `local_service`) to DALL-E, generating generic office workers. Now extracts service keyword from page title first (e.g. "Electrician" from "Excelsior Springs, MO Electrician"), then falls back to first primary service, then business type. Prompt also explicitly describes the trade work environment so DALL-E generates the correct tradesperson on-site.

## [1.5.112] — 2026-03-06

### Fixed
- **Generate Image now calls DALL-E 3 via API** — Replaced Midjourney copy-paste modal with real DALL-E image generation. Routes through Siloq API (`POST /sites/{id}/generate-image/`) so the OpenAI key stays server-side. Falls back to direct OpenAI call using `siloq_openai_api_key` WP option if API endpoint is unavailable. Generated images are sideloaded into WP media library with alt text.
- **One-click Fix It buttons in dashboard** — Priority Actions cards now execute fixes directly via AJAX instead of linking to Elementor. Missing meta title/description: auto-generates and saves to Yoast/AIOSEO meta fields. Missing schema: triggers schema generation inline. Missing H1: relabeled "Edit in Elementor" (requires content edit). Orphan/no internal links: opens Elementor editor. Settings links open in new tab.

---

## [1.5.111] — 2026-03-06

### Fixed
- **Bug 1 — Content suggestion identical to current content** — Three-part fix: (1) API page ID lookup now strips trailing slashes and ignores www prefix, with slug fallback; (2) LLM prompt restructured with explicit TASK/RULES/CURRENT CONTENT format that forbids returning input unchanged; (3) `generate_local_suggestion()` no longer returns original content — returns empty suggestion with `no_suggestion_reason` message instead. JS adds similarity check (>80% word overlap = identical) and shows advisory message instead of Apply button.
- **Bug 2 — Image Intelligence uses business city instead of page city** — `suggest_images()` now accepts `$post_id`, extracts city from page title (regex for "City, ST" pattern) and `_siloq_target_keyword` meta before falling back to business profile city. Excelsior Springs pages now get Excelsior Springs image prompts, not Kansas City.

---

## [1.5.110] — 2026-03-06

### Fixed
- **Bug 1 — Elementor `doc.get is not a function`** — `buildPageMap()` now guards against Elementor document objects that don't expose Backbone `.get()` (newer Elementor builds). Falls back to `elementor.elements` when `.get()` is unavailable. Added `$(window).on('elementor:init', ...)` hook so init fires reliably in all load orders. Zero console errors on widget click.
- **Bug 2 — Schema "API confirmation failed" error** — Schema generation no longer blocks on API GET confirmation (which always failed because schema was never POSTed). Now POSTs generated schema to API with correct `Authorization: Bearer` header and 30s timeout. Failures are `error_log`'d but never shown to user — schema saves to post_meta and succeeds every time.
- **Bug 3 — Apply destroys bullet list formatting** — Three-part fix: (1) text-editor widget content now preserves HTML on collection (`.html()` not `.text()`); (2) Apply button uses TinyMCE `setContent()` for text-editor widgets so HTML structure is honored by the editor; (3) PHP prompt explicitly instructs LLM to return valid HTML preserving `ul/li`, `strong`, `p` and all structural tags — never strip or merge list items.

---

## [1.5.109] — 2026-03-06

### Added
- **Site Audit Integration (Track 2)** — `run_site_audit()` collects all published pages with SEO metadata (title, meta description, H1, word count, schema types, internal links, alt text, duplicate detection), builds site context from entity profile, POSTs to Siloq API `POST /api/v1/sites/{id}/audit/` endpoint.
- **Audit Results Dashboard** — New "Site Audit" card on Dashboard tab shows site score (green/yellow/red), per-page scores sorted worst-first, page type badges, expandable action lists with severity indicators and recommendations.
- **Run Audit Button** — AJAX-powered "Run Audit" button triggers audit via `siloq_run_audit` handler; results cached in transient for 6 hours.
- **Audit Persistence** — Stores `siloq_last_audit_id` and `siloq_last_audit_time` in options; updates `siloq_site_score` from audit response.

---

## [1.5.108] — 2026-03-06

### Fixed
- **SEO Title Detection** — `siloq_get_page_title()` now reads AIOSEO `wp_aioseo_posts` table first, strips `%%post_title%%`/`%%separator_sa%%` tokens, falls back to Yoast `_yoast_wpseo_title`, then `post_title`. Eliminates false-positive "missing title" warnings.
- **Meta Description BROKEN_FALLBACK** — `siloq_get_meta_description()` detects descriptions over 500 characters as broken fallbacks (full page content dumped into meta field). Flags as CRITICAL instead of treating as present/healthy.
- **Page Auto-Classification** — `siloq_classify_page()` auto-detects page types by URL pattern: homepage → `apex_hub`, `/services/` → `hub`, city/state patterns → `spoke`, `/blog/`/`/about/` → `supporting`, zero inbound links → `orphan`. Integrated into sync and plan data flows.
- **Priority Action Sorting** — Actions now sort by 4-tier system: Tier 1 STRUCTURAL (missing title, broken meta, H1 issues, duplicate titles) → Tier 2 CONTENT (thin content, missing links, alt text) → Tier 3 SCHEMA → Tier 4 CLASSIFICATION. Within each tier, sorted by severity.
- **APEX_HUB Badge** — Added CSS (solid purple `#7c3aed` background, white text) and rendering for `apex_hub` page type across dashboard, floating panel, widget intelligence, and intelligence core JS. Added to role dropdown and architecture type ordering.

---

## [1.5.74] — 2026-03-05

### Added
- **Schema Tab** — full 3-section implementation replacing placeholder:
  - **Entity Profile Completeness**: SVG ring score (0-100%) computed from weighted business profile fields (Business Name 15, Business Type 15, Phone 10, Address 20, Primary Services 25, Service Areas 15). Per-field green/red status indicators. "Edit Business Profile" links to Settings tab.
  - **Schema Applied Per Page**: Table of all synced pages showing applied schema types, recommended types, status (Applied/Partial/None). "Generate Schema" button triggers existing `siloq_generate_schema` AJAX. "View Schema" expands JSON-LD preview.
  - **Schema Graph**: Fetches entity graph from Siloq API (`/sites/{id}/schema-graph/`). Falls back to placeholder if API not connected or endpoint returns 404.
- New AJAX handlers: `siloq_get_schema_status`, `siloq_get_schema_graph`
- `data-entity-score` attribute on dashboard insight card for JS access
- `siteId` added to `siloqDash` localized script data
- `.siloq-btn--xs` button size variant

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

## [1.5.57] — 2026-03-01 — [PR #59](https://github.com/Siloq-app/siloq-wordpress/pull/59)

### Added
- Widget Intelligence System — first production release for Elementor
  (an early prototype was scaffolded in v1.5.16 but never shipped; this is
  the first complete, functional version available to users)
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
- Widget Intelligence prototype scaffolded (Elementor-only, admin-gated);
  not shipped to users. First production release is v1.5.57.

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
