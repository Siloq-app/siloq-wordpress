# Lead Gen Scanner - v1.1.0 Update

## Overview

Version 1.1.0 adds a powerful Lead Gen Scanner widget to the Siloq WordPress Connector plugin. This feature allows you to capture leads by offering free website scans.

**Release Date:** 2026-01-29
**Previous Version:** 1.0.0
**Current Version:** 1.1.0

---

## What's New

### Lead Gen Scanner Widget
- Embeddable website scanner using shortcode `[siloq_scanner]`
- Captures visitor email + website URL
- Performs real-time SEO analysis via Siloq API
- Displays teaser results (score + grade + top 3 issues)
- Converts visitors with "Get Full Report" CTA
- Stores leads in database for marketing campaigns

---

## Files Added

### Core Functionality
- ✅ `siloq-connector/includes/class-siloq-lead-gen-scanner.php` (15 KB)
  - Scanner shortcode implementation
  - AJAX handlers for scan submission and polling
  - Lead storage functionality
  - API integration with Siloq backend

### Frontend Assets
- ✅ `siloq-connector/assets/css/lead-gen-scanner.css` (5.9 KB)
  - Modern, responsive widget styling
  - Gradient effects and animations
  - Mobile-optimized design

- ✅ `siloq-connector/assets/js/lead-gen-scanner.js` (8.1 KB)
  - Form validation
  - AJAX submission and polling
  - Dynamic UI updates
  - Error handling

### Documentation
- ✅ `siloq-connector/LEAD_GEN_SCANNER_USAGE.md` (5.9 KB)
  - Complete usage guide
  - Shortcode options
  - Customization examples
  - Troubleshooting tips

---

## Files Modified

### Plugin Core
**File:** `siloq-connector/siloq-connector.php`
- Version bumped: 1.0.0 → 1.1.0
- Added scanner class loading
- Initialized scanner with API client

### Admin Settings
**File:** `siloq-connector/includes/class-siloq-admin.php`
- Added "Lead Gen Signup URL" setting field
- Save/load signup URL option
- Form display in Settings page

### Documentation
**File:** `siloq-connector/README.md`
- Updated features list
- Added Lead Gen Scanner usage section
- Added shortcode documentation

**File:** `siloq-connector/CHANGELOG.md`
- Added v1.1.0 release notes
- Documented all changes
- Updated version history

---

## Database Changes

### New Table: `wp_siloq_leads`

```sql
CREATE TABLE wp_siloq_leads (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    website_url VARCHAR(255) NOT NULL,
    scan_id VARCHAR(36) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY email (email(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Auto-created:** Table is created automatically on first scan submission.

---

## Usage

### Basic Shortcode
```
[siloq_scanner]
```

### With Custom Options
```
[siloq_scanner
    title="Free Website Audit"
    button_text="Analyze My Site"
    signup_url="https://app.siloq.io/signup?plan=operator"
]
```

### Admin Configuration
1. Go to **Siloq → Settings**
2. Scroll to **Lead Gen Signup URL**
3. Enter custom URL or leave empty for default
4. Click **Save Settings**

---

## API Integration

### Endpoints Used
- `POST /api/v1/scans` - Create scan
- `GET /api/v1/scans/{id}` - Get results

### Scan Process
1. User submits URL + email
2. Plugin calls Siloq API to start scan
3. Polls every 3 seconds for results
4. Displays score + grade + top 3 issues
5. Redirects to signup on CTA click

---

## Testing Checklist

### Installation Test
- [ ] Copy/upload files to WordPress
- [ ] Verify plugin version shows 1.1.0
- [ ] Check no PHP errors on activation

### Functional Test
- [ ] Add `[siloq_scanner]` to a page
- [ ] Widget displays correctly
- [ ] Form validation works
- [ ] Scan starts successfully
- [ ] Progress indicator animates
- [ ] Results display correctly
- [ ] CTA button redirects properly
- [ ] Lead saved to database

### Mobile Test
- [ ] Responsive on phone
- [ ] Responsive on tablet
- [ ] Touch interactions work

### Settings Test
- [ ] Signup URL field appears in Settings
- [ ] Can save custom URL
- [ ] Default URL works when empty

---

## Deployment Steps

### 1. Backup Current Installation
```bash
# Backup database
mysqldump -u user -p database > backup_$(date +%Y%m%d).sql

# Backup plugin files
cp -r wp-content/plugins/siloq-connector siloq-connector-backup
```

### 2. Update Plugin Files

**Option A: Upload via WordPress Admin**
1. Deactivate current plugin
2. Delete old plugin files (or rename folder)
3. Upload new v1.1.0 ZIP
4. Activate plugin

**Option B: FTP/File Manager**
1. Upload new files, overwriting existing
2. Deactivate plugin
3. Reactivate plugin to run activation hooks

**Option C: Git Pull**
```bash
cd /path/to/wordpress/wp-content/plugins/siloq-connector
git pull origin main
```

### 3. Verify Installation
```sql
-- Check plugin version in database
SELECT option_value FROM wp_options WHERE option_name = 'siloq_version';

-- Check if leads table exists
SHOW TABLES LIKE 'wp_siloq_leads';
```

### 4. Test Basic Functionality
1. Visit WordPress admin
2. Go to **Siloq → Settings**
3. Verify new "Lead Gen Signup URL" field exists
4. Create test page with `[siloq_scanner]`
5. Test full scan flow

---

## Upgrade Path

### From v1.0.0 to v1.1.0

**Compatibility:** 100% backward compatible
- No breaking changes
- Existing features unchanged
- No database migrations required for existing tables

**Steps:**
1. Backup site (recommended)
2. Update files
3. Deactivate/reactivate plugin
4. Test scanner on a page
5. Configure signup URL if desired

**Data Safety:**
- All existing sync data preserved
- API settings unchanged
- No content modifications

---

## Troubleshooting

### Scanner Not Appearing
**Problem:** Shortcode shows as plain text

**Solution:**
- Verify plugin version is 1.1.0
- Check plugin is activated
- Clear page cache if using caching plugin

### "Unable to Start Scan"
**Problem:** Scan fails to start

**Solution:**
- Test API connection in **Siloq → Settings**
- Verify API URL and key are correct
- Check Siloq backend is running
- Review WordPress error logs

### Leads Not Saving
**Problem:** Database table doesn't exist

**Solution:**
```sql
-- Manually create table
CREATE TABLE IF NOT EXISTS wp_siloq_leads (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    website_url VARCHAR(255) NOT NULL,
    scan_id VARCHAR(36) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY email (email(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Progress Indicator Stuck
**Problem:** Scan never completes

**Solution:**
- Check browser console for errors (F12)
- Verify scan ID was returned
- Check Siloq API is processing scan
- Increase timeout if needed

---

## Performance Notes

### Asset Loading
- CSS/JS only loaded on pages with `[siloq_scanner]` shortcode
- Minimal performance impact on other pages
- Total size: ~20 KB (CSS + JS combined)

### Database Impact
- One INSERT per lead captured
- Indexed on email for fast lookups
- No impact on site performance

### API Usage
- One POST request to start scan
- Polling GET requests every 3 seconds
- Average 5-10 requests per scan

---

## Marketing Use Cases

### Lead Capture
- Add to homepage hero section
- Create dedicated landing page
- Embed in blog post CTAs
- Use in popup modals

### Lead Nurturing
```sql
-- Export leads for email campaigns
SELECT email, website_url, created_at
FROM wp_siloq_leads
WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC;
```

### Analytics Tracking
- Track form submissions
- Monitor scan completion rate
- Measure CTA click-through rate
- Calculate conversion to signup

---

## Support

### Getting Help
- **Documentation:** `LEAD_GEN_SCANNER_USAGE.md`
- **Issues:** https://github.com/Siloq-seo/siloq-wordpress/issues
- **Email:** support@siloq.com

### Reporting Bugs
Include:
- WordPress version
- PHP version
- Plugin version (1.1.0)
- Steps to reproduce
- Error messages
- Browser console logs

---

## Next Steps

### Immediate
1. Deploy to production
2. Create landing page with scanner
3. Drive traffic to page
4. Monitor lead capture

### Short-term
1. Export leads weekly
2. Set up email campaigns
3. Track conversion metrics
4. A/B test different copy

### Future Enhancements
- Admin page to view/export leads (v1.2.0)
- Email notifications for new leads
- Google Analytics integration
- CAPTCHA support
- Custom branding options

---

## Credits

**Version:** 1.1.0
**Release Date:** 2026-01-29
**Developed by:** Siloq Team
**License:** GPL v2 or later

---

**Ready to capture leads!** 🚀

Add `[siloq_scanner]` to any page and start converting visitors into customers.
