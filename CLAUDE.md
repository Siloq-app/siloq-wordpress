# CLAUDE.md — Siloq WordPress Plugin Development Rules

## Documentation map

- **Operators / site owners:** start with repository [`README.md`](README.md) (install, menus, REST overview, webhooks).  
- **Security triage:** [`SECURITY_AUDIT.md`](SECURITY_AUDIT.md) — verified fixes plus a re-audit checklist; do not treat removed historic bullets as current CVEs without re-verification.  
- **This file:** engineering standards, AJAX/REST conventions, and **release** checklist for maintainers.

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

## ⚠️ MANDATORY: Pre-Release JS Safety Checks
**Every release MUST pass these checks before the zip is built. No exceptions.**

### 1. No bare object references in inline `<script>` blocks
Any `<script>` block rendered directly in PHP (not an enqueued JS file) MUST use safe access:
```js
// ❌ WRONG — crashes if object not yet loaded:
nonce: siloqDash.nonce

// ✅ CORRECT — always safe:
nonce: (typeof siloqDash !== 'undefined' ? siloqDash.nonce : (typeof siloqAdminData !== 'undefined' ? siloqAdminData.ajax_nonce : ''))
```
Run this grep before every release — it must return zero results:
```bash
grep -n "nonce: siloqDash\.nonce\|nonce: siloqAdminData\.nonce" includes/class-siloq-admin.php
```

### 2. All JS must degrade gracefully on page builders
Cornerstone, Elementor, Divi, and WPBakery change script load order.
- Test Save Goals, Run Analysis, and Sync buttons on at least one page-builder site before shipping
- `ERR_CONNECTION_CLOSED` on admin-ajax = JS crash upstream, not a PHP error

### 3. Enqueued scripts need guards before using localized objects
```js
// ❌ WRONG:
function updateDashboardStats() {
    $.ajax({ data: { nonce: siloqAdminData.nonce } })

// ✅ CORRECT:
function updateDashboardStats() {
    if (typeof siloqAdminData === 'undefined') return;
    $.ajax({ data: { nonce: siloqAdminData.nonce } })
```

---

## ⚙️ ENGINEERING STANDARDS — MANDATORY FOR ALL FEATURES

### 1. API Contract First — No Exceptions
Before writing any button, define the contract:
```
REQUEST:  { action, nonce, post_id, job_type }
RESPONSE: { status: "ok|error", data: {...}, message: "human readable" }
```
Every AJAX/REST handler MUST return this exact shape. JS always reads `resp.data`, never `resp.job_id` directly.

### 2. Full-Stack Trace Required
Every feature must be traceable across all 3 layers before code is written:
- **JS Layer:** What data is sent and to what URL?
- **PHP Layer:** How is it verified (nonce/permission) and proxied to Django?
- **Django Layer:** What exact JSON keys are returned?

If you cannot write this trace, do not write the code.

### 3. Event Delegation — No Direct Button Listeners on Dynamic Elements
```js
// ❌ WRONG — breaks when button re-renders:
$('#siloq-some-btn').on('click', handler)

// ✅ CORRECT — delegates from stable parent container:
$(document).on('click', '#siloq-some-btn', handler)
// or better:
$('#siloq-dashboard-container').on('click', '[data-action]', handler)
```

### 4. Every Button Has 3 States — Always
1. **Loading state** — disable + spinner/text change immediately on click (optimistic UI)
2. **Error handler** — explicit `.fail()` + user-visible error message, button re-enables
3. **Verification step** — after success, confirm the action worked (re-fetch or check response)

### 5. Async Job Pattern — Required for All Heavy Operations
All jobs (Fix All, Approve & Write, Full Audit, Schema Apply) MUST use:
1. **Trigger:** POST to start job → receive `job_id` immediately
2. **Watcher:** Poll every 3s with `job_id` → update progress bar
3. **Delivery:** On `status=complete`, render result. On `status=failed`, show retry button.
Never fire-and-forget. Never assume sync completion.

### 6. No Duplicate IDs — Ever
Run before every release:
```bash
grep -oh 'id="siloq-[^"]*"' includes/class-siloq-admin.php | sort | uniq -d
```
Zero output required. Duplicate IDs silently break JS selectors.

### 7. System Health Check Before Jobs Fire
The dashboard MUST check `GET /api/v1/health/` on load.
If `celery: "offline"` → grey out job buttons, show maintenance message.
Never let a user click a button that has no chance of working.
