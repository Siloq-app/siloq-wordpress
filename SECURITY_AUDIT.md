# Security audit notes

This file records **verified** security-relevant changes and a **living checklist** for future review. It is not a substitute for a full third-party penetration test.

---

## Resolved

### Webhook HMAC validation (fixed in **1.5.291**)

- **Component:** `siloq-connector/includes/class-siloq-webhook-handler.php`  
- **Endpoint:** `POST /wp-json/siloq/v1/webhook`  
- **Issue (historical):** When no `siloq_webhook_secret` option existed yet, the endpoint could accept unsigned POST bodies, allowing unauthenticated callers to manipulate drafts, content, meta, or `siloq_site_id` on a fresh install.  
- **Fix:** `Siloq_Webhook_Handler::ensure_secret()` lazily generates a 64-character secret (via `wp_generate_password`) so the option is not left empty in normal operation. HMAC verification is **mandatory**: missing or mismatched `X-Siloq-Signature` (or an empty secret edge case) yields **401**. The secret is shown in **Siloq → Settings → Webhook Secret** for copying into the Siloq dashboard.

Details and rollout notes also appear under **Security** in `siloq-connector/CHANGELOG.md` for version **1.5.291**.

---

## Re-triage (2026-04-22)

Older copies of this document listed “critical” issues with **stale file/line references** (e.g. pointing at shortcode/AJAX registration or script localization instead of mock APIs). Those entries have been **removed** as unverified against current `main`.

When re-auditing, prioritize:

1. **New REST routes** under `siloq/v1` — confirm `permission_callback` coverage, JSON validation, and that privileged operations never rely on `__return_true` except where intentionally public (e.g. diagnostic ping/webhook contract).  
2. **AJAX handlers** — `check_ajax_referer`, `current_user_can`, and stable error shapes without leaking secrets.  
3. **Webhook secret lifecycle** — secret rotation coordination between WordPress and the Siloq dashboard.  
4. **Hosting controls** — WAF, rate limiting, and TLS termination in front of `/wp-json/`.

---

## Ongoing recommendations

- Keep dependencies and build toolchain patched (`siloq-connector/package.json`).  
- Avoid logging raw API keys, webhook secrets, or full request bodies in production debug plugins.  
- Run periodic reviews after large changes to `includes/class-siloq-rest-api.php` and related `register_rest_route` call sites.
