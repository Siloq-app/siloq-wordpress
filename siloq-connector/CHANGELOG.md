# Siloq Connector — Changelog

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/) conventions.
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.5.303] — 2026-04-20

### Fixed
- **scan.siloq.ai scoring algorithm — v1.1 rewrite.** The scan-results shortcode previously averaged pillar scores and applied two flat caps (58 for cannibalization, 65 for "critical"), which let sites with obvious cannibalization still grade B+ when most pillars looked healthy. That produced the crystallizedcouture.com 93/B+ output and similar. The scoring now:
  - **Starts at 100** and applies explicit issue-based deductions (missing H1 -15, heading hierarchy skipped -12, title missing -20, sitemap missing -8, no SSL -15, canonical missing -10, LCP > 4s -12, CLS > 0.25 -10, and ~25 more) mapped via keyword match from API top_issues and per-dimension issue lists.
  - **Applies small bonuses** (+2/+3 per pillar scoring ≥90%/≥95% of its max), capped at +15 total.
  - **Applies a graduated cannibalization cap** based on conflict count: 0→100, 1→84, 2→79, 3→74, 4→69, 5-6→64, 7-9→54, 10+→44. Prior flat 58 cap now reserved for ~5-6-conflict sites.
  - **Applies page-count adjustment**: large sites with few affected pages get the cap relaxed by up to +15. Prevents a 500-page site with 2 duplicate slugs from getting destroyed.
  - **Applies a critical-suppressor ceiling** (score ≤64 if any non-cannibalization critical suppressor surfaces).
  - **Caps only lower** — never raise. Math order is: deductions → bonuses → cap. A site with more conflicts can never score higher than a site with fewer.
- **Cannibalization detection threshold lowered** — `Siloq_Scan_Results_Shortcode::detect_cannibalization()` now returns `detected: true` for 1+ slug-suffix pairs (was 3+), so 1-2-conflict sites stop slipping through uncapped.
- **Grade labels aligned to v1.1** — A+ / A / B+ / B / C+ / C / D+ / D / D- / F with descriptive labels ("Excellent", "Good", "Needs Improvement", "Serious Issues", "Critical Problems", "Failing") and corresponding grade-color hexes.

### Changed
- Display-only pillar caps (Site Architecture → 40% of max when cannibalization detected, Content Depth → 50%, GEO Ready → 30% when schema missing from homepage) now run in `html()` but **do not** recompute the overall score — they only keep the visual pillar bar chart honest. Score is the single source of truth from `calculate_v11_score()` in `render()` upstream.
- New public static methods on `Siloq_Scan_Results_Shortcode` for testability: `calculate_v11_score()`, `v11_cannibal_cap()`, `v11_page_count_adjustment()`, `v11_grade()`, `v11_match_deduction()`, `v11_conflict_count()`. All side-effect-free.

### Not changed
- Crawler, sitemap fetch, and cannibalization URL collection logic unchanged.
- Claude-generated narrative (suppressors, roadmap, entity analysis) unchanged.
- Calendly CTAs and email template unchanged.
- Django API (siloq-api) unchanged — this PR is PHP-only. Django still returns its current response shape; the PHP scorer just interprets it through v1.1 math. A future PR may port the scoring into Django as a proper Python scan engine.

### Follow-ups
- [ ] PHPUnit test harness: no PHP test infra exists in this plugin yet. The v1.1 methods are deliberately public-static so they can be tested directly. Recommend adding `tests/test-v11-scoring.php` in a follow-up PR once Composer/PHPUnit scaffolding is added.
- [ ] Page-count adjustment estimates pages-in-conflict as `pair_count * 2`. When the Django API starts returning the actual list of URLs in conflict, feed that in for a precise percentage.
- [ ] Severity-weighted conflict counts (v1.1 spec §1) deferred to V1.2 — requires API to emit conflict_type classification (exact_match, high_overlap, intent_collision, etc.).

---

## [1.5.302] — 2026-04-20

### Fixed
- **Restored production baseline (v1.5.294 → v1.5.301).** Between early April and mid-April a prior release attempt (`0e29231`, "Bump version to 1.5.291") landed on `main` without the v1.5.294-301 fixes that were already running in production. The missing fixes lived on an unmerged branch (`fix/js-undefined-siloqdash`) and included a critical PHP fatal 500 fix. Deploying the `main` tip to any site would re-introduce that outage. This release merges those 8 commits back into mainline so that `main` is once again a superset of production. No new features. No scoring/behavior changes.

### Recovered fixes (originally shipped via `fix/js-undefined-siloqdash` but never merged to `main`)
- **v1.5.294** — Safe `siloqDash` / `siloqAdminData` access to prevent `ReferenceError` on Elementor sites where the admin dashboard JS never loads.
- **v1.5.295** — CORS headers on job calls; AIOSEO auto-detect; schema Apply All selector fix; remaining `siloqDash` references cleaned up.
- **v1.5.296** — Restored `class-siloq-rest-api.php` (dropped since v1.5.286) and fixed duplicate `siloq-goals-save-btn` ID that broke the goals form.
- **v1.5.297** — System Status light with health check on load, Celery/Redis aware; job buttons grey out when the backend is offline.
- **v1.5.298** — All missing AJAX handlers added (`get_plan_data`, `pages_list`, `set_role`, `schema_graph`, `repair`, `add_link`), duplicate IDs fixed, Approve & Write response shape normalized.
- **v1.5.299** — **CRITICAL:** Fix unclosed brace in `siloq-connector.php` causing PHP fatal 500 on activation.
- **v1.5.300** — `toUpperCase` crash on undefined values, nonce refresh before Approve & Write, blog signal handling, modal hub name, duplicate IDs.
- **v1.5.301** — Async Approve & Write polling flow (`siloqPollJobStatus`, `siloqCreateDraftPost`, `siloqCheckForActiveJobs`, resume on tab load).

### Notes for operators
- Production installs already running v1.5.299 are now upgrading forward (to v1.5.302), not sideways.
- This PR does not change scan.siloq.ai scoring behavior. The v1.1 scoring rework ships as a separate PR on top of this baseline.

---

## [1.5.291] — 2026-04-09

### Fixed
- **CRITICAL — Webhook content-wipe** — `handle_apply_content` had a duplicated `post_content` array key referencing an undefined `$new_content` variable. PHP silently dropped the sanitized content, so every successful `content.apply_content` webhook on a standard (non-builder) page wiped post bodies to empty while returning `success: true`. Backups captured one line earlier so data is recoverable.
- **Custom post type propagation (BUG 1/2)** — `get_siloq_crawlable_post_types()` was already wired into the sync engine, but 18 downstream queries still hardcoded `array('page','post')` and ignored the indexed CPTs. Fixed: internal-link orphan detector (the BUG 2 root cause), junk detector, schema dashboard, keyword/page mapper, hub search, cannibalization check, service-hub candidate search, city→URL map, Elementor repair, reposition/rename lists, URL rewrite, local hub/spoke fallback, unlisted hub detection, authority manifest, and others.
- **API base URL inconsistency** — Two broken patterns existed alongside the canonical `get_option('siloq_api_url', ...)`: (1) a `SILOQ_API_BASE` constant that was never defined anywhere, falling back to `api.siloq.app` (wrong host); (2) a stale DigitalOcean staging fallback `sea-lion-app-8rkgr.ondigitalocean.app` in `class-siloq-agent-pages.php`. All 14 sites unified on the canonical pattern. The cpt-crawler URL fix is required for the BUG 1/2 work to actually reach the correct sync endpoint.
- **AIOSEO upsert race in `handle_create_draft`** — Naked `UPDATE` queries against `wp_aioseo_posts` for freshly inserted drafts silently affected zero rows because no AIOSEO row existed yet, losing the SEO title/description. Refactored to match the existing-row check pattern in `handle_meta_update`. Also added a `SHOW TABLES` guard so the code is safe on non-AIOSEO sites.

### Security
- **Mandatory webhook HMAC validation** — The `/wp-json/siloq/v1/webhook` endpoint previously accepted any unsigned POST when no webhook secret was configured. On a fresh install this allowed unauthenticated callers to create draft posts, overwrite content, update meta fields, and rewrite `siloq_site_id` to redirect the install to an attacker-controlled tenant. Fix: introduced `Siloq_Webhook_Handler::ensure_secret()` which auto-generates a 64-char `wp_generate_password` secret on first request if `siloq_webhook_secret` is empty. Existing installs receive the secret on the next page load after upgrade. HMAC validation is now unconditional: empty secret, missing signature, or mismatched HMAC all return 401.
- **Webhook secret surfaced in Settings** — Added a new read-only "Webhook Secret" row to the Settings page right after the API URL field, with a description explaining that the value must be copied into the Siloq dashboard's webhook configuration.

### Breaking
- **Webhook secret coordination required.** After upgrading, the API side must be configured with the secret shown in Settings → Webhook Secret. If the API is still sending unsigned webhooks (or webhooks signed with a different secret), they will all 401. Coordinate the rollout: open the Siloq dashboard, copy the new auto-generated secret over, and verify webhooks before announcing.

## [1.5.190] — 2026-03-14

### Added
- **Depth Engine tab** — new WP admin tab with silo selector, score cards (Semantic Density, Topical Closure, Coverage Breadth, Freshness), collapsible gap report (Critical/Thin/Standard), and Add to Plan buttons.
- **AJAX endpoints** — `siloq_get_silos`, `siloq_get_depth_scores`, `siloq_get_gap_report`, `siloq_run_depth_scan`, `siloq_add_to_plan`.

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
