# Siloq WordPress Plugin

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2+-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Official WordPress plugin connecting your site to the **Siloq** platform for SEO silo management, AI-assisted content workflows, schema support, and platform webhooks.

## Overview

The **Siloq Connector** syncs WordPress content with Siloq, surfaces jobs and recommendations in wp-admin, and receives inbound events over a signed REST webhook. The admin experience combines classic PHP screens with React-based pages where noted in the menu.

## Requirements

- **WordPress**: 5.0 or higher  
- **PHP**: 7.4 or higher  
- **MySQL**: 5.6 or higher (or MariaDB equivalent)  
- A **Siloq** account with API access  
- **API URL** and **API key** from the Siloq dashboard  

The REST layer defaults the API base to `https://api.siloq.ai/api/v1` when no URL is stored yet (see **Siloq → Settings**).

## Installation

### WordPress admin (recommended)

1. Download the latest release from [GitHub Releases](https://github.com/Siloq-app/siloq-wordpress/releases).  
2. In WordPress go to **Plugins → Add New → Upload Plugin**.  
3. Upload the ZIP, install, and activate **Siloq Connector**.

### Manual install from source

1. Clone or download this repository.  
2. Copy the `siloq-connector` folder to `wp-content/plugins/`.  
3. Activate **Siloq Connector** under **Plugins**.

If you build admin assets from source, in `siloq-connector/` run `npm install` (or `npm ci`) and `npm run build` before packaging a release ZIP (see `package.json` scripts).

## Configuration

### 1. API credentials

1. Sign in to the Siloq dashboard.  
2. Open **Settings → API Keys** (or your tenant’s equivalent).  
3. Create or copy an API key. Keys used by this plugin are validated to start with `sk_siloq_`.  
4. Note the **API base URL** your tenant uses (production commonly matches the default above).

### 2. WordPress: **Siloq → Settings**

1. Enter **API URL** and **API Key**.  
2. Save, then use **Test Connection** to confirm reachability.  
3. Copy **Webhook Secret** into the Siloq dashboard webhook configuration so inbound requests can be **HMAC-signed** (`X-Siloq-Signature`). After upgrades that introduced mandatory signing, unsigned webhooks return **401** until the secret matches on both sides.

Optional and advanced options (auto-sync flags, content types, BYOK keys for certain AI flows, etc.) live in the same settings area or legacy PHP settings screens depending on your build—inspect **Siloq → Settings** on your install for the exact controls.

### 3. First sync

- **Bulk / guided sync:** **Siloq → Page Sync** (REST-backed UI uses `/wp-json/siloq/v1/sync/status` and `/wp-json/siloq/v1/sync/start`).  
- **Per-page actions:** Use controls on **Page Sync** or post-level Siloq UI where available.  
- **Auto-sync on save:** Enable if offered in settings so updates propagate when editors publish or update content.

## WordPress admin areas

Under the top-level **Siloq** menu you will typically see:

| Submenu | Purpose |
|--------|---------|
| **Settings** | API URL/key, connection test, webhook secret, integration toggles |
| **Dashboard** | Overview metrics and job health |
| **Page Sync** | Sync status and bulk / selective sync |
| **Content Import** | AI / content import workflows |
| **Theme Intelligence** | Theme-level analysis (TALI) |
| **Image Brief** | Image audit printable brief |
| **Approvals** | Agent recommendations (submenu label; page title may read “Agent Recommendations”) |
| **Content Plan** | Planned content view |

Capability requirements vary by screen (`manage_options` vs `edit_pages`); if a menu is missing for a user, grant the appropriate WordPress role capabilities.

## REST API (troubleshooting)

Plugin routes are registered under the namespace **`siloq/v1`**:

- Base URL: `/wp-json/siloq/v1/`  
- **Public ping:** `GET /wp-json/siloq/v1/ping` — confirms REST is reachable (useful when security plugins block `/wp-json/`).  
- **Inbound webhook:** `POST /wp-json/siloq/v1/webhook` — authenticated via **HMAC** and the shared secret, not via cookie auth.

Other routes (settings, sync, jobs, schema, snapshots, etc.) are split across PHP classes in `siloq-connector/includes/`; search for `register_rest_route( 'siloq/v1'` when auditing endpoints.

## Security

- **Outbound API:** Bearer token using the stored API key; keys live in the WordPress options table—protect database backups accordingly.  
- **Inbound webhook:** HMAC signature required; secret is generated or shown under **Siloq → Settings**.  
- **WordPress hardening:** Admin actions use nonces and capability checks; REST calls from the browser use the `wp_rest` nonce via `apiFetch`.  
- **Data handling:** Sanitization and prepared SQL patterns are used in the plugin code paths that touch the database; hosting-level WAF/rate limits remain your responsibility.

For a concise audit history and re-triage notes, see [`SECURITY_AUDIT.md`](SECURITY_AUDIT.md).

## Contributing and releases

Contributor workflow, database rules, AJAX batching, and release packaging are documented in [`CLAUDE.md`](CLAUDE.md).

## Support

- **Issues:** [github.com/Siloq-app/siloq-wordpress/issues](https://github.com/Siloq-app/siloq-wordpress/issues)  
- **Documentation:** [siloq.com/docs](https://siloq.com/docs)  
- **Email:** support@siloq.com  

## License

GPL v2 or later.

---

Developed by [Siloq](https://siloq.com).
