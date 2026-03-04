# Siloq Connector — Changelog

All notable changes to this plugin will be documented here.

Format: [Semantic Versioning](https://semver.org/)

---

## [1.5.58] — 2026-03-04

### Added
- **Widget Intelligence System — All Page Builders**
  Expanded Widget Intelligence (previously Elementor-only) to all major WordPress page builders:
  - Gutenberg (Block Editor) — InspectorControls panel injection via `wp.plugins`
  - Divi — MutationObserver + `et_fb_enqueue_assets` hook
  - Beaver Builder — `FLBuilder.addHook` + MutationObserver
  - WPBakery — MutationObserver on `.vc_ui-panel-content-area`
  - Bricks — MutationObserver + `bricksData` global
  - Oxygen — MutationObserver + AngularJS scope integration
  - Cornerstone — MutationObserver + Redux `_x_app.store`
  - Classic Editor — DOM-ready metabox injection

- **Shared Core Architecture** (`class-siloq-widget-intelligence-core.php` + `siloq-intelligence-core.js`)
  PHP handles: page layer detection, heading hierarchy validation, image alt analysis, AI prompt builder, entity context.
  JS handles: panel rendering, AJAX API calls, results rendering, image generation modal, event delegation.

- **Per-builder features** (all builders):
  - ⚡ Analyze This Widget button in editor panels
  - Page layer badge (Hub / Spoke / Supporting)
  - Layer violation + heading hierarchy warnings
  - One-click Apply to push suggested content back to widget
  - Image Intelligence with AI prompt generation modal
  - All JS wrapped in top-level try/catch — no unhandled errors if builder is absent

- **Admin-only loading** — entire Intelligence System gated behind `is_admin()` in `load_dependencies()`. Zero frontend overhead.

- **Builder auto-detection** — `Siloq_Builder_Detector::detect()` caches result per-request; only the matching builder's class is loaded.

### Changed
- `siloq-connector.php` — version bumped `1.5.57 → 1.5.58`; `load_dependencies()` updated to require core + detected builder intelligence class

---

## [1.5.57] — 2026-03-01

### Added
- Widget Intelligence System for Elementor (initial release)

---

## [1.5.26] — 2026-02-26

### Fixed
- ModSecurity User-Agent rejection on shared hosting (`python-requests` → `Siloq/1.0`)
- Webhook initialization order (`require_once` before `class_exists`)
- `make_request()` now returns parsed `success/data/message` instead of raw WP_HTTP response
- AIOSEO meta update uses `INSERT...ON DUPLICATE KEY` pattern
- Corrected event field names (`event_type`, `page.update_meta`)
- JS localized object references unified

---

*Older entries not yet backfilled. Start of recorded history: v1.5.26.*
