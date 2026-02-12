# Siloq Connector - WordPress Plugin

WordPress plugin that connects your WordPress site to the Siloq SEO platform for intelligent content silo management and AI-powered content generation.

## Features

✅ **Two-Way Sync** - Sync WordPress pages with Siloq platform
✅ **Auto-Sync** - Automatically sync pages when published or updated
✅ **Schema Injection** - Automatically inject AI-generated schema markup
✅ **Admin Dashboard** - Easy-to-use interface for managing syncs
✅ **Bulk Operations** - Sync all pages at once
✅ **Sync Status Monitoring** - Track which pages are synced and when
✅ **REST API Integration** - Secure communication with Siloq backend
✅ **Lead Gen Scanner** - Embeddable website scanner widget for lead capture (v1.1.0)

## Requirements

- **WordPress:** 5.0 or higher
- **PHP:** 7.4 or higher
- **Siloq API:** Active Siloq backend instance with API credentials

## Installation

### Method 1: Manual Upload (Recommended)

1. Download the plugin folder as a ZIP file
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Method 2: FTP Upload

1. Extract the plugin folder
2. Upload the `siloq-connector` folder to `/wp-content/plugins/`
3. Go to **WordPress Admin → Plugins**
4. Find **Siloq Connector** and click **Activate**

### Method 3: Git Clone (For Developers)

```bash
cd /path/to/wordpress/wp-content/plugins
git clone https://github.com/Siloq-seo/siloq-wordpress-plugin.git siloq-connector
```

Then activate the plugin in WordPress admin.

## Configuration

### Step 1: Get Your API Credentials

1. Log in to your Siloq platform
2. Go to **Settings → API Keys**
3. Generate a new API key
4. Copy your **API URL** and **API Key**

### Step 2: Configure the Plugin

1. In WordPress, go to **Siloq → Settings**
2. Enter your **API URL** (e.g., `http://your-server-ip:3000/api/v1`)
3. Enter your **API Key**
4. (Optional) Enable **Auto-Sync** to automatically sync pages on publish/update
5. Click **Save Settings**
6. Click **Test Connection** to verify the setup

### Step 3: Sync Your Pages

**Option A: Bulk Sync All Pages**
1. Go to **Siloq → Settings**
2. Click **Sync All Pages**
3. Wait for the sync to complete

**Option B: Sync Individual Pages**
1. Go to **Siloq → Sync Status**
2. Click **Sync Now** next to any page

**Option C: Auto-Sync (if enabled)**
- Pages will automatically sync when you publish or update them

## Usage

### Viewing Sync Status

Go to **Siloq → Sync Status** to see:
- Which pages are synced
- When they were last synced
- Which pages have schema markup
- Which pages need re-syncing

### Auto-Generated Schema Markup

Once a page is synced:
1. Siloq generates appropriate schema markup
2. The plugin automatically injects it into the page's `<head>`
3. Schema is invisible to visitors but readable by search engines

### Re-Syncing Pages

Pages automatically need re-syncing if:
- Content has been modified since last sync
- You manually click **Sync Now**

### Lead Gen Scanner Widget

Embed a website scanner on any page to capture leads and generate scan results:

1. **Add the shortcode** to any page or post: `[siloq_scanner]`
2. Visitors enter their website URL and email
3. The scanner analyzes their site (5 categories: Technical SEO, Content, Structure, Performance, SEO Factors)
4. Shows teaser results: Overall score + grade + top 3 high-priority issues
5. CTA to get full report redirects to Siloq signup (BLUEPRINT plan by default)

**Shortcode Options:**
```
[siloq_scanner title="Free SEO Audit" button_text="Scan My Site" signup_url="https://app.siloq.ai/signup?plan=blueprint"]
```

**Configure Signup URL:**
- Go to **Siloq → Settings**
- Set **Lead Gen Signup URL** (or leave empty for default)
- All captured leads are stored in `wp_siloq_leads` database table

**View Captured Leads:**
```sql
SELECT email, website_url, created_at FROM wp_siloq_leads ORDER BY created_at DESC;
```

## Troubleshooting

### Connection Test Fails

**Problem:** "Connection failed" error when testing API connection

**Solutions:**
1. Verify your API URL is correct (should end with `/api/v1`)
2. Check that your API key is valid
3. Ensure your Siloq backend is running
4. Check firewall settings allow WordPress to reach the API
5. For DigitalOcean setups, verify private networking is configured

### Pages Not Syncing

**Problem:** Pages remain "Not Synced" after clicking Sync

**Solutions:**
1. Check **Siloq → Settings** - ensure API credentials are correct
2. Test the API connection
3. Check WordPress error log for details
4. Verify the page is published (not draft)

### Schema Not Appearing

**Problem:** Schema markup not showing in page source

**Solutions:**
1. Verify the page was successfully synced
2. Check if schema is present in **Siloq → Sync Status**
3. Clear any caching plugins
4. View page source and search for `<script type="application/ld+json">`

### Auto-Sync Not Working

**Problem:** Pages don't sync automatically when published

**Solutions:**
1. Go to **Siloq → Settings**
2. Ensure **Auto-Sync** checkbox is enabled
3. Save settings
4. Try publishing/updating a page

## API Endpoints Used

The plugin communicates with these Siloq API endpoints:

- `POST /auth/verify` - Verify API credentials
- `POST /pages/sync` - Sync page data to Siloq
- `GET /pages/{id}/schema` - Retrieve schema markup
- `POST /content-jobs` - Create content generation jobs
- `GET /content-jobs/{id}` - Check job status

## Security

- All API requests use Bearer token authentication
- API keys are stored securely in WordPress options
- Admin actions require proper WordPress capabilities
- AJAX requests are protected with nonces
- Sensitive data is sanitized and validated

## Support

For issues and feature requests:
- **GitHub Issues:** https://github.com/Siloq-seo/siloq-wordpress-plugin/issues
- **Documentation:** https://siloq.com/docs
- **Email:** support@siloq.com

## Development

### File Structure

```
siloq-connector/
├── siloq-connector.php          # Main plugin file
├── includes/
│   ├── class-siloq-api-client.php    # API communication
│   ├── class-siloq-sync-engine.php   # Sync logic
│   └── class-siloq-admin.php         # Admin pages
├── assets/
│   ├── css/
│   │   └── admin.css            # Admin styles
│   └── js/
│       └── admin.js             # Admin scripts
└── README.md                    # This file
```

### Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Testing

Before submitting a PR:
1. Test on fresh WordPress installation
2. Test with auto-sync enabled and disabled
3. Test bulk sync with 20+ pages
4. Test error handling (wrong API credentials, server down, etc.)
5. Check for PHP errors and warnings

## Changelog

### Version 1.0.0 (2026-01-21)
- Initial release
- Two-way page synchronization
- Auto-sync on publish/update
- Schema markup injection
- Admin dashboard
- Sync status monitoring
- Bulk sync operations

## License

GPL v2 or later

## Credits

Developed by **Siloq** - https://siloq.com

---

**Made with ❤️ for better SEO**
