# Backend API Endpoints Checklist

This document lists ALL API endpoints the WordPress plugin uses. Check this against `siloq-app` backend to ensure everything is implemented.

---

## ✅ Scanner Endpoints (VERIFIED - Plugin uses these correctly)

### 1. Create Scan
```http
POST /api/v1/scans
Content-Type: application/json
Authorization: Bearer {api_key}

{
  "url": "https://example.com",
  "scan_type": "full"
}
```

**Response (201 Created):**
```json
{
  "id": "scan_abc123",
  "url": "https://example.com",
  "status": "pending",
  "created_at": "2026-02-14T18:00:00Z"
}
```

**Plugin Usage:** `class-siloq-lead-gen-scanner.php` line 307

---

### 2. Get Scan Status
```http
GET /api/v1/scans/{scan_id}
Authorization: Bearer {api_key}
```

**Response (200 OK) - Processing:**
```json
{
  "id": "scan_abc123",
  "url": "https://example.com",
  "status": "processing",
  "progress": 45
}
```

**Response (200 OK) - Completed:**
```json
{
  "id": "scan_abc123",
  "url": "https://example.com",
  "status": "completed",
  "overall_score": 72,
  "grade": "C",
  "pages_crawled": 15,
  "scan_duration_seconds": 28,
  "recommendations": [
    {
      "category": "Technical SEO",
      "issue": "Missing meta descriptions",
      "action": "Add unique meta descriptions",
      "priority": "high"
    },
    {
      "category": "Content",
      "issue": "Thin content on 3 pages",
      "action": "Expand content to 800+ words",
      "priority": "medium"
    }
  ],
  "technical_score": 68,
  "content_score": 75,
  "structure_score": 70,
  "performance_score": 80,
  "seo_score": 72
}
```

**Response (200 OK) - Failed:**
```json
{
  "id": "scan_abc123",
  "status": "failed",
  "error_message": "Unable to crawl site: DNS resolution failed"
}
```

**Plugin Usage:** `class-siloq-lead-gen-scanner.php` line 357

---

### 3. Get Full Report (Lead Gen)
```http
GET /api/v1/scans/{scan_id}/report
Authorization: Bearer {api_key}
```

**Response (200 OK):**
```json
{
  "scan_id": "scan_abc123",
  "scan_summary": {
    "website_url": "https://example.com",
    "total_pages_analyzed": 15,
    "total_issues": 12,
    "overall_risk_level": "Medium"
  },
  "detailed_issues": [
    {
      "category": "Technical SEO",
      "issue": "Missing meta descriptions",
      "affected_pages": ["/page1", "/page2"],
      "severity": "High",
      "fix": "Add unique meta descriptions under 160 characters"
    }
  ],
  "upgrade_cta": {
    "label": "Get Full Analysis + Fixes",
    "plan": "blueprint"
  }
}
```

**Plugin Usage:** `class-siloq-lead-gen-scanner.php` line 415

---

## ✅ Core Plugin Endpoints (VERIFIED)

### 4. Test Connection / Verify API Key
```http
POST /api/v1/auth/verify
Authorization: Bearer {api_key}
```

**Response (200 OK):**
```json
{
  "valid": true,
  "site": {
    "id": 123,
    "url": "https://example.com",
    "name": "Example Site"
  }
}
```

**Alternative Response Format (Legacy - also supported):**
```json
{
  "valid": true,
  "site_id": 123,
  "site_url": "https://example.com"
}
```

**Plugin Usage:** `class-siloq-api-client.php` line 77  
**Notes:** Plugin checks BOTH `data.site.id` and `data.site_id` (handles both formats)

---

### 5. Sync Page
```http
POST /api/v1/pages/sync/
Content-Type: application/json
Authorization: Bearer {api_key}

{
  "wp_post_id": 42,
  "url": "https://example.com/services",
  "title": "Our Services",
  "content": "<p>Full HTML content...</p>",
  "excerpt": "Brief excerpt",
  "status": "publish",
  "post_type": "page",
  "published_at": "2026-01-01T12:00:00Z",
  "modified_at": "2026-02-01T10:30:00Z",
  "slug": "services",
  "parent_id": 0,
  "menu_order": 0,
  "is_homepage": false,
  "is_noindex": false,
  "meta": {
    "yoast_title": "Our Services | Example",
    "yoast_description": "Description",
    "featured_image": "https://example.com/image.jpg"
  }
}
```

**Response (200/201):**
```json
{
  "page_id": 456,
  "status": "synced",
  "message": "Page synced successfully"
}
```

**Plugin Usage:** `class-siloq-api-client.php` line 164

---

### 6. Get Schema Markup
```http
GET /api/v1/pages/{page_id}/schema/
Authorization: Bearer {api_key}
```

**Response (200 OK):**
```json
{
  "schema_markup": "{\"@context\":\"https://schema.org\",\"@type\":\"LocalBusiness\",\"name\":\"Example\"}"
}
```

**Plugin Usage:** `class-siloq-api-client.php` line 218

---

### 7. Create Content Job
```http
POST /api/v1/content-jobs/
Content-Type: application/json
Authorization: Bearer {api_key}

{
  "page_id": 456,
  "wp_post_id": 42,
  "job_type": "content_generation"
}
```

**Response (201 Created):**
```json
{
  "job_id": "job_xyz789",
  "status": "pending",
  "page_id": 456
}
```

**Plugin Usage:** `class-siloq-api-client.php` line 266

---

### 8. Get Content Job Status
```http
GET /api/v1/content-jobs/{job_id}/
Authorization: Bearer {api_key}
```

**Response (200 OK) - Completed:**
```json
{
  "job_id": "job_xyz789",
  "status": "completed",
  "content": "<p>Generated content...</p>",
  "title": "AI Generated Title",
  "faq_items": [
    {
      "question": "What is this?",
      "answer": "This is the answer."
    }
  ],
  "internal_links": [
    {
      "anchor_text": "related page",
      "target_url": "/related"
    }
  ],
  "schema_markup": "{...}",
  "seo_metadata": {
    "title": "SEO Title",
    "description": "SEO Description",
    "focus_keyword": "keyword"
  }
}
```

**Plugin Usage:** `class-siloq-api-client.php` line 296

---

## ⚠️ Business Profile Endpoints (NEEDS VERIFICATION)

### 9. Get Sites List
```http
GET /api/v1/sites/
Authorization: Bearer {api_key}
```

**Response (200 OK) - Paginated:**
```json
{
  "results": [
    {
      "id": 123,
      "url": "https://example.com",
      "name": "Example Site",
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "count": 1,
  "next": null,
  "previous": null
}
```

**Response (200 OK) - Non-paginated (also accepted):**
```json
[
  {
    "id": 123,
    "url": "https://example.com",
    "name": "Example Site"
  }
]
```

**Plugin Usage:** `class-siloq-api-client.php` line 531  
**Note:** Plugin handles both formats

---

### 10. Get Business Profile ⚠️
```http
GET /api/v1/sites/{site_id}/profile/
Authorization: Bearer {api_key}
```

**Expected Response (200 OK):**
```json
{
  "business_type": "local_service",
  "primary_services": ["HVAC", "Plumbing"],
  "service_areas": ["Kansas City, MO", "Overland Park, KS"],
  "target_audience": "Homeowners and small businesses",
  "business_description": "Full-service HVAC and plumbing"
}
```

**Plugin Usage:** `class-siloq-api-client.php` line 560  
**Status:** ⚠️ **UNKNOWN** - Need to verify this endpoint exists

---

### 11. Save Business Profile ⚠️
```http
PATCH /api/v1/sites/{site_id}/profile/
Content-Type: application/json
Authorization: Bearer {api_key}

{
  "business_type": "local_service",
  "primary_services": ["HVAC", "Plumbing"],
  "service_areas": ["Kansas City, MO"],
  "target_audience": "Homeowners",
  "business_description": "Description here"
}
```

**Expected Response (200 OK):**
```json
{
  "business_type": "local_service",
  "primary_services": ["HVAC", "Plumbing"],
  "service_areas": ["Kansas City, MO"],
  "target_audience": "Homeowners",
  "business_description": "Description here",
  "updated_at": "2026-02-14T18:00:00Z"
}
```

**Plugin Usage:** `class-siloq-api-client.php` line 586  
**Status:** ⚠️ **UNKNOWN** - Need to verify this endpoint exists

---

## Webhook Events (Plugin Receives)

The plugin registers a webhook endpoint at:
```
POST https://yoursite.com/wp-json/siloq/v1/webhook
```

**Security:** Requires `X-Siloq-Signature` header (HMAC-SHA256 of body using API key)

### Event: content.generated
```json
{
  "event_type": "content.generated",
  "wp_post_id": 42,
  "job_id": "job_xyz789",
  "status": "completed",
  "title": "Generated Title"
}
```

### Event: schema.updated
```json
{
  "event_type": "schema.updated",
  "wp_post_id": 42,
  "schema_markup": "{...}"
}
```

### Event: page.analyzed
```json
{
  "event_type": "page.analyzed",
  "wp_post_id": 42,
  "analysis": {
    "seo_score": 75,
    "content_quality": 80
  }
}
```

### Event: sync.completed
```json
{
  "event_type": "sync.completed",
  "wp_post_id": 42,
  "siloq_page_id": 456
}
```

---

## Backend Implementation Checklist

| Endpoint | Method | Status | Notes |
|----------|--------|--------|-------|
| `/scans` | POST | ✅ ? | Scanner - Create scan |
| `/scans/{id}` | GET | ✅ ? | Scanner - Get results |
| `/scans/{id}/report` | GET | ✅ ? | Scanner - Full report |
| `/auth/verify` | POST | ✅ ? | Test connection |
| `/pages/sync/` | POST | ✅ ? | Sync page |
| `/pages/{id}/schema/` | GET | ✅ ? | Get schema |
| `/content-jobs/` | POST | ✅ ? | Create content job |
| `/content-jobs/{id}/` | GET | ✅ ? | Get job status |
| `/sites/` | GET | ✅ ? | List sites |
| `/sites/{id}/profile/` | GET | ⚠️ ? | Get business profile |
| `/sites/{id}/profile/` | PATCH | ⚠️ ? | Update profile |

**Legend:**
- ✅ = Should exist (plugin expects it)
- ⚠️ = Uncertain (needs verification)
- ? = Not tested by me (can't access backend)

---

## Action Items for Backend Team

1. **Verify all scanner endpoints exist** (`/scans` routes)
2. **Check Business Profile endpoints** (`/sites/{id}/profile/`)
   - If missing: Add them OR tell Kyle to hide the UI feature
3. **Verify webhook signature generation** (HMAC-SHA256 using API key)
4. **Test response formats** (match expected JSON shapes above)

---

## Questions?

Contact Kyle or check:
- Plugin code: `/tmp/siloq-wordpress/siloq-connector/includes/class-siloq-api-client.php`
- Scanner code: `/tmp/siloq-wordpress/siloq-connector/includes/class-siloq-lead-gen-scanner.php`
- Full audit: `AUDIT_REPORT.md`
