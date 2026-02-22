# Siloq WordPress Plugin – Developer Guide

This guide explains how **siloq-wordpress** works, how to run it, how it connects to **siloq-app** (FastAPI backend), and which folders to work in as a developer.

---

## 1. What This Plugin Does

- **Connects WordPress to Siloq**  
  Your WordPress site talks to **siloq-app** (FastAPI) via REST API using an **API key** (from the dashboard).
- **Syncs pages**  
  Sends page data to the backend; can sync on publish/update or manually.
- **Content import**  
  Creates content jobs on the backend and can import generated content back into WordPress.
- **Schema**  
  Fetches JSON-LD schema from the backend and injects it in the page `<head>`.
- **Webhooks**  
  Can receive events from the backend (e.g. content ready).
- **Lead Gen Scanner**  
  Optional feature that uses the backend for scanning.

**You develop only in the plugin folder.** The backend (siloq-app) is a separate repo; you call its API, you don’t edit it from this project.

---

## 2. Folder Structure – Where to Work

```
siloq-wordpress/
├── .gitignore
├── docker-compose.yml          ← Run WordPress + DB locally (optional)
├── README.md
├── DEVELOPER_GUIDE.md          ← This file
├── LEAD_GEN_SCANNER_UPDATE.md  ← Docs only; read, don’t need to edit for basic dev
├── UseCasesOfConnection.md     ← Docs only
│
└── siloq-connector/            ← ✅ ALL YOUR PLUGIN CODE LIVES HERE
    ├── siloq-connector.php     ← Main plugin bootstrap (hooks, load classes)
    ├── includes/               ← ✅ PHP classes – main development area
    │   ├── class-siloq-api-client.php      ← All HTTP calls to siloq-app
    │   ├── class-siloq-admin.php           ← Admin UI (settings, sync, content import)
    │   ├── class-siloq-sync-engine.php      ← Sync logic (pages → backend)
    │   ├── class-siloq-content-import.php  ← Content import from backend
    │   ├── class-siloq-webhook-handler.php ← Incoming webhooks from backend
    │   └── class-siloq-lead-gen-scanner.php← Lead gen scanner feature
    ├── assets/
    │   ├── css/                ← ✅ Admin/frontend styles
    │   │   ├── admin.css
    │   │   ├── frontend.css
    │   │   └── lead-gen-scanner.css
    │   └── js/                 ← ✅ Admin/frontend scripts
    │       ├── admin.js
    │       └── lead-gen-scanner.js
    ├── CHANGELOG.md
    ├── DEPLOYMENT.md
    ├── INSTALL.md
    ├── README.md
    ├── TESTING.md
    └── verify-scanner.php      ← Utility; only touch if you work on scanner
```

### What to touch as a developer

| Area | Path | Purpose |
|------|------|--------|
| **Backend communication** | `siloq-connector/includes/class-siloq-api-client.php` | Add new API calls to siloq-app, change request/response handling |
| **Admin UI** | `siloq-connector/includes/class-siloq-admin.php` | New admin pages, settings, copy, buttons |
| **Sync behavior** | `siloq-connector/includes/class-siloq-sync-engine.php` | What gets sent to backend, when |
| **Content import** | `siloq-connector/includes/class-siloq-content-import.php` | How generated content is applied to posts/pages |
| **Webhooks** | `siloq-connector/includes/class-siloq-webhook-handler.php` | Handle events from siloq-app |
| **Plugin bootstrap** | `siloq-connector/siloq-connector.php` | Hooks, loading new classes, new AJAX actions |
| **Admin JS/CSS** | `siloq-connector/assets/js/*.js`, `assets/css/*.css` | UI behavior and styling |

### What you usually don’t need to touch

- **Root of siloq-wordpress** (except `docker-compose.yml` if you run Docker).
- **Docs** (`*.md`) unless you’re updating instructions.
- **`verify-scanner.php`** unless you’re working on the scanner.

So: **develop inside `siloq-connector/`**, and focus on `includes/` and `assets/`.

---

## 3. How to Run the Project

### Option A: Docker (recommended for local dev)

From the **siloq-wordpress** root (where `docker-compose.yml` is):

```bash
cd siloq-wordpress
docker-compose up -d
```

- **WordPress:** http://localhost:8080  
- **phpMyAdmin (optional):** http://localhost:8081  
- The plugin is mounted from `./siloq-connector` into the container, so edits are reflected after refresh (no need to re-copy the plugin).

Then:

1. Open http://localhost:8080, complete WordPress setup (admin user, etc.).
2. Go to **Plugins** → **Siloq Connector** should appear → **Activate**.
3. Go to **Siloq → Settings**, set **API URL** and **API Key** (see “Integrating with siloq-app” below).

### Option B: Existing WordPress install

1. Copy the plugin folder into WordPress:
   - Copy `siloq-connector` to `wp-content/plugins/siloq-connector`.
2. In WP Admin → **Plugins**, activate **Siloq Connector**.
3. Configure **Siloq → Settings** (API URL + API Key).

So: **run WordPress** (Docker or existing), **activate the plugin**, **configure API URL + key**. No separate “run” step for the plugin itself.

---

## 4. How It Integrates with siloq-app (FastAPI Backend)

- The plugin does **not** know about the dashboard. It only talks to **siloq-app** over HTTP.
- **siloq-dashboard** is where users create sites and generate API keys. Users copy the **API URL** and **API Key** from the dashboard into **Siloq → Settings** in WordPress.

### Configuration (WordPress)

- **API URL:** Base URL of the FastAPI app, e.g.  
  `https://siloq-app-edwlr.ondigitalocean.app/api/v1`  
  or locally: `http://localhost:8000/api/v1`
- **API Key:** The `sk-...` key generated in the dashboard for this site. Stored in WordPress options and sent as `Authorization: Bearer <api_key>` on every request.

### How the plugin calls the backend

All HTTP calls go through **`Siloq_API_Client`** in `includes/class-siloq-api-client.php`:

1. **Constructor**  
   Reads `siloq_api_url` and `siloq_api_key` from WordPress options.
2. **`make_request($method, $endpoint, $data)`**  
   - Builds URL: `{api_url}{endpoint}` (e.g. `https://.../api/v1/pages/sync`).
   - Sets headers: `Authorization: Bearer <api_key>`, `Content-Type: application/json`.
   - Sends GET/POST/PUT/DELETE and returns `wp_remote_request()` result.

So to add a new feature that uses the backend:

1. Add a new method in `class-siloq-api-client.php` that calls `make_request()` with the right method and endpoint.
2. Use that method from admin code (e.g. `class-siloq-admin.php`) or from another class (sync, content import, etc.).

### Endpoints the plugin uses today

| Purpose | Method | Endpoint (relative to base URL) |
|--------|--------|----------------------------------|
| Test connection | POST | `/auth/verify` |
| Sync a page | POST | `/pages/sync` |
| Get schema for a page | GET | `/pages/{id}/schema` or `/pages/{id}/jsonld` (match backend) |
| Create content job | POST | `/content-jobs` |
| Get job status | GET | `/content-jobs/{id}` |

Backend base URL is the one you set in **API URL** (e.g. `https://siloq-app-xxx.ondigitalocean.app/api/v1`). So the plugin does **not** hardcode the backend; it’s fully driven by settings.

---

## 5. Developing a New Feature (Checklist)

1. **Backend (siloq-app)**  
   If the feature needs new API: add the route and logic in FastAPI (separate repo). Document method, path, body, and response.

2. **API client (plugin)**  
   In `includes/class-siloq-api-client.php`:
   - Add a method, e.g. `do_something($param)`.
   - Call `$this->make_request('POST', '/your/endpoint', $payload)` (or GET, etc.).
   - Parse response and return success/error in the same way as existing methods (e.g. `array('success' => true, 'data' => $body)`).

3. **Use the new method**  
   From `class-siloq-admin.php` (e.g. new menu page or button), or from another class (e.g. sync engine), call `$api_client->do_something($param)`.

4. **AJAX (if needed)**  
   In `siloq-connector.php` add `add_action('wp_ajax_siloq_my_action', array($this, 'ajax_my_action'))` and a handler that:
   - Checks nonce and capabilities.
   - Calls the API client.
   - Returns JSON for the browser.

5. **Frontend (if needed)**  
   In `assets/js/admin.js` (or a new JS file and enqueue it on your admin page), send the AJAX request and update the UI. Add any styles in `assets/css/admin.css`.

6. **Settings (if needed)**  
   If the feature needs new options, add them to the settings form and save in `class-siloq-admin.php` with `update_option()` / `get_option()`, and use them in the API client if needed.

That’s the full loop: **Backend → API client → Admin (or other class) → AJAX + JS/CSS**.

---

## 6. Quick Reference

- **Run WordPress (Docker):** `docker-compose up -d` → http://localhost:8080  
- **Plugin code:** All under `siloq-connector/`; main work in `includes/` and `assets/`.  
- **Backend base URL:** Set in **Siloq → Settings** as **API URL** (e.g. `https://siloq-app-xxx.ondigitalocean.app/api/v1`).  
- **Auth:** **API Key** from dashboard, sent as `Authorization: Bearer <key>` by `Siloq_API_Client`.  
- **New backend feature:** Implement in siloq-app, then in plugin add a method in `class-siloq-api-client.php` and call it from admin or other classes; add AJAX + JS if needed.

If you tell me the exact feature you want (e.g. “sync custom post type” or “new button that calls /my-endpoint”), I can outline the exact changes in `siloq-connector` and, if needed, the FastAPI route in siloq-app.
