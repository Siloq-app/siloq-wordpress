# Siloq WordPress Plugin Repository

Official WordPress plugin for integrating your WordPress site with the [Siloq SEO platform](https://siloq.com) for intelligent content silo management and AI-powered content generation.

## Overview

This repository contains the **Siloq Connector** WordPress plugin, which enables seamless two-way synchronization between WordPress and the Siloq platform. The plugin provides automatic schema markup injection, AI content generation, and real-time webhook integration.

## Quick Start

### Installation

1. Clone or download this repository
2. Upload the `siloq-connector` folder to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the WordPress admin panel
4. Configure your API credentials in **Siloq → Settings**

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Active Siloq backend instance with API credentials

## Plugin Features

✅ **Two-Way Page Synchronization** - Sync WordPress pages with Siloq platform  
✅ **Auto-Sync** - Automatically sync pages when published or updated  
✅ **AI Content Generation** - Generate AI-powered content for your pages  
✅ **Schema Markup Injection** - Automatic SEO schema markup injection  
✅ **Webhook Integration** - Real-time notifications from Siloq platform  
✅ **Admin Dashboard** - Comprehensive admin interface for managing syncs  
✅ **Bulk Operations** - Sync all pages at once with progress tracking  
✅ **Sync Status Monitoring** - Track which pages are synced and when  
✅ **Content Import** - Import AI-generated content as drafts or replace existing content  
✅ **FAQ & Internal Links** - Automatic FAQ section and internal link injection

## Documentation

For detailed documentation, see the plugin's [README.md](siloq-connector/README.md) file.

### Quick Links

- [Installation Guide](siloq-connector/INSTALL.md)
- [Testing Checklist](siloq-connector/TESTING.md)
- [Deployment Guide](siloq-connector/DEPLOYMENT.md)
- [Changelog](siloq-connector/CHANGELOG.md)

## Repository Structure

```
siloq-wordpress/
├── siloq-connector/          # Main plugin directory
│   ├── siloq-connector.php   # Main plugin file
│   ├── includes/             # PHP class files
│   ├── assets/               # CSS and JavaScript files
│   ├── README.md             # Plugin documentation
│   └── [docs]                # Additional documentation
└── README.md                 # This file
```

## Configuration

1. Go to **Siloq → Settings** in WordPress admin
2. Enter your **API URL** (e.g., `http://your-server-ip:3000/api/v1`)
3. Enter your **API Key**
4. Click **Test Connection** to verify
5. Enable **Auto-Sync** if desired
6. Click **Save Settings**

## Usage

### Basic Sync

- **Bulk Sync**: Go to **Siloq → Settings** → Click **Sync All Pages**
- **Individual Sync**: Go to **Siloq → Sync Status** → Click **Sync Now** next to any page
- **Auto-Sync**: Enable in settings to automatically sync on publish/update

### Content Generation

1. Go to **Siloq → Content Import**
2. Select a page and click **Generate Content**
3. Wait for AI generation to complete
4. Import as draft or replace existing content

### Webhook Setup

1. Copy the webhook URL from **Siloq → Content Import**
2. Configure it in your Siloq backend
3. Set the webhook secret to match your API key

## API Integration

The plugin communicates with these Siloq API endpoints:

- `POST /auth/verify` - Verify API credentials
- `POST /pages/sync` - Sync page data to Siloq
- `GET /pages/{id}/schema` - Retrieve schema markup
- `POST /content-jobs` - Create content generation jobs
- `GET /content-jobs/{id}` - Check job status

## Security

- Bearer token authentication for all API requests
- Secure API key storage in WordPress options
- WordPress nonce verification for AJAX requests
- Capability-based permissions for admin actions
- HMAC signature verification for webhooks
- Comprehensive input sanitization and output escaping

## Support

- **GitHub Issues**: [Report issues](https://github.com/Siloq-seo/siloq-wordpress/issues)
- **Documentation**: [Siloq Docs](https://siloq.com/docs)
- **Email**: support@siloq.com

## Contributing

We welcome contributions! Please see the [Contributing Guide](siloq-connector/README.md#contributing) in the plugin's README.

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## Development

### File Structure

```
siloq-connector/
├── siloq-connector.php              # Main plugin file
├── includes/
│   ├── class-siloq-api-client.php   # API communication
│   ├── class-siloq-sync-engine.php  # Sync logic
│   ├── class-siloq-admin.php        # Admin interface
│   ├── class-siloq-content-import.php # Content import
│   └── class-siloq-webhook-handler.php # Webhook handler
├── assets/
│   ├── css/
│   │   ├── admin.css                # Admin styles
│   │   └── frontend.css             # Frontend styles
│   └── js/
│       └── admin.js                 # Admin JavaScript
└── README.md                        # Plugin documentation
```

### Testing

Before submitting a PR:
1. Test on a fresh WordPress installation
2. Test with auto-sync enabled and disabled
3. Test bulk sync with 20+ pages
4. Test error handling scenarios
5. Check for PHP errors and warnings

## Version History

### Version 1.0.0 (2026-01-21)

Initial release with complete feature set:
- Two-way page synchronization
- Auto-sync on publish/update
- AI content generation and import
- Schema markup injection
- Webhook integration
- Admin dashboard
- Sync status monitoring
- Bulk sync operations
- Content backup and restore
- Comprehensive documentation

See [CHANGELOG.md](siloq-connector/CHANGELOG.md) for detailed version history.

## License

GPL v2 or later

## Credits

Developed by **Siloq** - [https://siloq.com](https://siloq.com)

---

**Made with ❤️ for better SEO**
