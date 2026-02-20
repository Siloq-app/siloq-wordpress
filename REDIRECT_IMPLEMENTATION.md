# Siloq WordPress Plugin - Redirect Implementation

## Overview
Native redirect execution engine for the Siloq WordPress plugin. This feature allows the Siloq API to create, manage, and execute URL redirects without relying on third-party plugins.

## Features

### 1. Database Table
Custom table `{prefix}siloq_redirects` with:
- `id` - Auto-increment primary key
- `source_url` - The URL to redirect from
- `target_url` - The URL to redirect to
- `redirect_type` - HTTP status code (301, 302, 307, 308)
- `reason` - Optional reason for the redirect
- `created_by` - Source identifier (e.g., 'siloq_api')
- `created_at` - Timestamp of creation
- `is_active` - Boolean flag for soft delete
- `hit_count` - Number of times the redirect was triggered
- `last_hit_at` - Timestamp of last redirect execution

**Indexes:**
- `idx_source` - On `source_url(191)` for fast lookups
- `idx_active` - On `is_active` for filtering

### 2. Redirect Execution
Hooks into WordPress `template_redirect` at **priority 1** (before all other redirects).

**Features:**
- Exact URL matching
- Trailing slash normalization (tries both with and without `/`)
- Hit count tracking
- Performance-optimized with indexed queries

### 3. REST API Endpoints

#### Create Redirect
```
POST /wp-json/siloq/v1/redirects
Authorization: Bearer <api_key>

Body:
{
  "source_url": "https://example.com/old-page",
  "target_url": "https://example.com/new-page",
  "redirect_type": 301,
  "reason": "Page renamed"
}

Response:
{
  "success": true,
  "redirect_id": 123,
  "message": "Redirect created successfully"
}
```

#### List Redirects
```
GET /wp-json/siloq/v1/redirects
Authorization: Bearer <api_key>

Response:
{
  "success": true,
  "redirects": [
    {
      "id": 123,
      "source_url": "https://example.com/old-page",
      "target_url": "https://example.com/new-page",
      "redirect_type": 301,
      "reason": "Page renamed",
      "created_by": "siloq_api",
      "created_at": "2026-02-17 22:30:00",
      "is_active": 1,
      "hit_count": 42,
      "last_hit_at": "2026-02-17 23:15:00"
    }
  ],
  "count": 1
}
```

#### Delete Redirect
```
DELETE /wp-json/siloq/v1/redirects/{id}
Authorization: Bearer <api_key>

Response:
{
  "success": true,
  "message": "Redirect deactivated successfully"
}
```

### 4. Authentication
Supports two authentication methods:

1. **Bearer Token** (Recommended)
   ```
   Authorization: Bearer <api_key>
   ```

2. **HMAC Signature** (Legacy webhook support)
   ```
   X-Siloq-Signature: <hmac_sha256_signature>
   ```

### 5. Webhook Integration
The existing `redirect.create` webhook now prioritizes the native redirect engine:

**Webhook Event:**
```
POST /wp-json/siloq/v1/webhook
X-Siloq-Signature: <signature>

Body:
{
  "event_type": "redirect.create",
  "from_url": "https://example.com/old-page",
  "to_url": "https://example.com/new-page",
  "type": 301,
  "reason": "Page updated"
}
```

**Fallback Order:**
1. Siloq native engine (priority)
2. Redirection plugin (if installed)
3. AIOSEO redirects (if installed)
4. .htaccess modification

## Installation

### New Installations
The table is automatically created when the plugin is activated.

### Existing Installations
To add the redirects table to existing installations:
1. Deactivate the plugin
2. Reactivate the plugin
3. The table will be created on activation

Alternatively, run this SQL manually:
```sql
CREATE TABLE IF NOT EXISTS wp_siloq_redirects (
  id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_url VARCHAR(2048) NOT NULL,
  target_url VARCHAR(2048) NOT NULL,
  redirect_type INT(11) DEFAULT 301,
  reason VARCHAR(255) DEFAULT NULL,
  created_by VARCHAR(100) DEFAULT 'siloq_api',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_active TINYINT(1) DEFAULT 1,
  hit_count INT(11) DEFAULT 0,
  last_hit_at DATETIME DEFAULT NULL,
  INDEX idx_source (source_url(191)),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Files Changed

### New Files
- `siloq-connector/includes/class-siloq-redirect-manager.php` - Core redirect management class

### Modified Files
- `siloq-connector/siloq-connector.php` - Added redirect manager initialization
- `siloq-connector/includes/class-siloq-webhook-handler.php` - Updated to use native redirects

## Performance Considerations

1. **Database Queries**: Single indexed query per page load (only if URL matches)
2. **Early Execution**: Runs at priority 1 on `template_redirect`, before WordPress loads templates
3. **Optimized Indexes**: URL prefix index (191 chars) and active status index
4. **Minimal Overhead**: No database queries if no redirect exists for current URL

## Testing

### Test Redirect Creation
```bash
curl -X POST https://your-site.com/wp-json/siloq/v1/redirects \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "source_url": "https://your-site.com/test-old",
    "target_url": "https://your-site.com/test-new",
    "redirect_type": 301,
    "reason": "Test redirect"
  }'
```

### Test Redirect Execution
1. Create a redirect via API
2. Visit the source URL
3. Should redirect to target URL with correct HTTP status

### Test Redirect Listing
```bash
curl https://your-site.com/wp-json/siloq/v1/redirects \
  -H "Authorization: Bearer YOUR_API_KEY"
```

### Test Redirect Deletion
```bash
curl -X DELETE https://your-site.com/wp-json/siloq/v1/redirects/123 \
  -H "Authorization: Bearer YOUR_API_KEY"
```

## Future Enhancements

Potential improvements:
- Pattern matching (regex support)
- Redirect chains detection
- Import/export functionality
- Analytics dashboard in WordPress admin
- Automatic redirect suggestions based on 404 errors

## Commit Hash
`29ecf62` - feat: add native redirect execution engine
