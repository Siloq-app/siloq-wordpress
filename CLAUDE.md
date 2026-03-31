# CLAUDE.md — Siloq WordPress Plugin Development Rules

## Plugin Identity
- **Plugin file:** `siloq-connector/siloq-connector.php`
- **Current version:** Check `Version:` header in siloq-connector.php
- **REST namespace:** `siloq/v1`
- **Option name for wizard gate:** `siloq_onboarding_complete` (NOT siloq_setup_complete)

## ⚠️ CRITICAL: Version Header Must Be Bumped Every Release
The `Version:` line in `siloq-connector/siloq-connector.php` MUST be updated on every release.
WordPress reads this header to show version in admin. Git tags alone are not enough.
**Before every release:** bump `Version: X.X.XXX` in the PHP file header.

## Database Rules
- **NEVER use `dbDelta()` with `ON UPDATE CURRENT_TIMESTAMP`** — it silently fails table creation.
  Use `$wpdb->query("CREATE TABLE IF NOT EXISTS ...")` directly, then dbDelta as a secondary pass.
- Always surface `$wpdb->last_error` in AJAX responses — never return "It may already exist."

## AJAX Rules
- Large operations (100+ posts) MUST be paginated: 50/batch with `has_more` + `next_offset`
- Never loop all posts in one AJAX call — 745 WC products × API time = PHP timeout
- Always use `wp_send_json_success()` for batch operations (even on partial errors)
  Put error counts in the data payload. `wp_send_json_error()` breaks JS loops.

## JS Localization
- `wpData` — general WP data (nonce, admin URL)
- `siloqAI` — AI-related JS vars
- `siloqAdminData` — admin page settings
- Never use an object that isn't registered with `wp_localize_script()`

## API Request Rules
- User-Agent MUST be `Siloq/1.0` (not python-requests — ModSecurity blocks it on shared hosting)
- HMAC webhook validation: always verify `X-Siloq-Signature` before processing
- Don't validate site_id in Settings without HMAC secret present — corrupts stored ID

## Page Builder Handling
- Detect page builder via `_siloq_page_builder` post meta (set on every sync)
- All page builders (Elementor, Cornerstone, Divi, WPBakery) return `manual_action` — no direct content injection in V1
- Send `page_builder` field in sync payload to API

## SEO Plugin Integration
- Kyle uses **AIOSEO** (All In One SEO) — NOT Yoast. Don't assume Yoast.
- AIOSEO uses custom tables: `wp_aioseo_posts`, `wp_aioseo_terms`
- AIOSEO updates use `INSERT ... ON DUPLICATE KEY UPDATE` — not plain UPDATE

## File Structure
```
siloq-connector/
  siloq-connector.php    ← Main file, version header here
  includes/
  assets/js/
  assets/css/
```

## Branch Strategy
- All work on `main`
- Feature branches: `feat/description`
- Never commit Flywheel local paths (`Local Sites/...`) — .gitignore blocks it
- Always check `git diff` before committing for accidental path inclusions

## Release Checklist
1. Bump `Version:` in `siloq-connector/siloq-connector.php`
2. `git tag vX.X.XXX`
3. `git push && git push --tags`
4. Create GitHub Release with zip asset named `siloq-connector-vX.X.XXX.zip`
5. Update EDD download on siloq.ai for all 5 tiers
6. Send download link to Kyle via WhatsApp (+16366677247)
