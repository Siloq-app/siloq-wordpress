# Changelog

All notable changes to the Siloq WordPress Connector plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-02-11

### Added
- **TALI - Theme-Aware Layout Intelligence**
  - Automatically adapts Siloq-generated content to match WordPress theme styling
  - Theme fingerprinting extracts colors, typography, spacing, and layout tokens
  - Component discovery identifies supported Gutenberg blocks
  - Authority block injection with claim anchors (`data-siloq-claim-id`)
  - Support for service_city, blog_post, and project_post templates
  - Access state enforcement (ENABLED/FROZEN) with receipt preservation
  - Confidence-based draft fallback (< 90% confidence = Draft status)
  - Admin UI for theme intelligence status and manual re-fingerprinting
  - REST API endpoints for programmatic access

### Technical Details
- **New Files:**
  - `includes/tali/class-siloq-tali.php` - Main TALI coordinator
  - `includes/tali/class-siloq-tali-fingerprinter.php` - Theme token extraction
  - `includes/tali/class-siloq-tali-component-mapper.php` - Block capability discovery
  - `includes/tali/class-siloq-tali-block-injector.php` - Gutenberg block injection
  - `includes/tali/views/admin-tali-settings.php` - Admin settings page
  - `includes/tali/README.md` - Integration documentation
- **Modified Files:**
  - `siloq-connector.php` - Added TALI initialization and SILOQ_PLUGIN_FILE constant
- **REST API Endpoints:**
  - `POST /siloq/v1/tali/fingerprint` - Run theme fingerprint
  - `GET /siloq/v1/tali/design-profile` - Get design profile
  - `GET /siloq/v1/tali/capability-map` - Get capability map
  - `POST /siloq/v1/tali/inject` - Inject authority content
- **Output Files:**
  - `wp-content/uploads/siloq-tali/design_profile_wp.json`
  - `wp-content/uploads/siloq-tali/wp_component_capability_map.json`

## [1.1.0] - 2026-01-29

### Added
- **Lead Gen Scanner Widget**
  - New shortcode `[siloq_scanner]` for embedding scanner on any page
  - Visitor lead capture with email collection
  - Website scanning via Siloq API (5 categories: Technical SEO, Content, Structure, Performance, SEO Factors)
  - Teaser results display (score + grade + top 3 high-priority issues)
  - CTA to full report with automatic signup redirection
  - New database table `wp_siloq_leads` for lead storage
  - Shortcode customization options (title, button_text, signup_url)
  - Admin setting for default signup URL
  - Responsive design with smooth animations
  - AJAX-powered scanning with real-time progress indicator
  - Polling mechanism for scan results (3-second interval)
  - Security features: nonce verification, input sanitization, SQL injection prevention

### Technical Details
- **New Files:**
  - `includes/class-siloq-lead-gen-scanner.php` - Scanner class
  - `assets/css/lead-gen-scanner.css` - Widget styles
  - `assets/js/lead-gen-scanner.js` - Frontend interactivity
- **Modified Files:**
  - `siloq-connector.php` - Added scanner initialization
  - `includes/class-siloq-admin.php` - Added signup URL setting
- **API Endpoints Used:**
  - `POST /api/v1/scans` - Create website scan
  - `GET /api/v1/scans/{id}` - Get scan results
- **Database Changes:**
  - New table: `wp_siloq_leads` (email, website_url, scan_id, ip_address, user_agent, created_at)

## [1.0.0] - 2026-01-21

### Added
- **Core Sync Engine**
  - Two-way synchronization between WordPress and Siloq platform
  - Sync individual pages on demand
  - Bulk sync all published pages
  - Auto-sync on page publish/update (optional)
  - Track sync status and timestamps
  - Detect pages needing re-sync

- **API Integration**
  - Secure Bearer token authentication
  - RESTful API client for all Siloq endpoints
  - Connection testing functionality
  - Comprehensive error handling
  - Request/response logging (debug mode)

- **Admin Interface**
  - Settings page for API configuration
  - Sync Status dashboard showing all pages
  - Content Import interface for AI-generated content
  - Progress indicators and loading states
  - Success/error notifications
  - Responsive design for mobile devices

- **Content Import**
  - Import AI-generated content from Siloq
  - Create new draft pages with generated content
  - Replace existing content (with backup)
  - Restore content from backup
  - Content generation job management
  - Job status polling and notifications

- **FAQ Features**
  - Automatic FAQ section injection
  - Gutenberg block format support
  - Classic editor HTML support
  - Styled FAQ components (frontend CSS)
  - Schema markup for FAQs

- **Internal Linking**
  - Automatic injection of internal links
  - Smart anchor text replacement
  - Avoid over-linking (first occurrence only)

- **Schema Markup**
  - Automatic schema injection in page `<head>`
  - JSON-LD format
  - SEO-friendly structured data
  - Schema update on re-sync

- **Webhook Support**
  - REST API webhook endpoint
  - HMAC signature verification
  - Real-time notifications from Siloq
  - Event handlers for:
    - `content.generated` - Content ready for import
    - `schema.updated` - Schema markup updated
    - `page.analyzed` - Page analysis complete
    - `sync.completed` - Sync operation complete
  - Email notifications for content generation

- **Security Features**
  - WordPress nonce verification for all AJAX
  - Capability checks for admin actions
  - Input sanitization and validation
  - Output escaping
  - Secure API key storage
  - HMAC webhook signature verification

- **Frontend Styling**
  - CSS for FAQ sections
  - Internal link styling
  - Responsive design
  - Dark mode support
  - Generated content notices (optional)

- **Documentation**
  - Comprehensive README.md
  - Quick installation guide (INSTALL.md)
  - Testing checklist (TESTING.md)
  - Deployment guide (DEPLOYMENT.md)
  - Inline code comments
  - Troubleshooting section

### Technical Details
- **WordPress Compatibility:** 5.0+
- **PHP Requirements:** 7.4+
- **Architecture:** Object-oriented with proper separation of concerns
- **API Endpoints Used:**
  - `POST /api/v1/auth/verify`
  - `POST /api/v1/pages/sync`
  - `GET /api/v1/pages/{id}/schema`
  - `POST /api/v1/content-jobs`
  - `GET /api/v1/content-jobs/{id}`

### Known Limitations
- Only works with "page" post type (not posts or custom post types)
- Requires active Siloq backend with API access
- Webhook requires REST API enabled
- Large bulk syncs (100+ pages) may require increased timeouts

### Security
- All API requests use Bearer token authentication
- AJAX requests protected with WordPress nonces
- User capabilities checked for all admin actions
- Webhook signatures verified with HMAC SHA-256
- No sensitive data exposed in JavaScript

---

## [Unreleased]

### Planned Features
- **v1.2.0** (Next Minor Release)
  - Support for custom post types
  - Scheduled content generation
  - Advanced schema types (Article, Product, Review)
  - Bulk content import
  - Export/import settings
  - WP-CLI commands
  - Admin page to view/export captured leads

- **v1.3.0** (Future Release)
  - Gutenberg block integration
  - Visual silo builder in admin
  - Real-time content preview
  - Multi-site support
  - Analytics dashboard
  - A/B testing integration

- **v2.0.0** (Major Update)
  - Full Agent autonomy (with approval gates)
  - Automated internal link optimization
  - Image optimization sync
  - Redirect management
  - Content performance tracking
  - Multi-language support

### Under Consideration
- WordPress.org directory submission
- Premium/Pro version with advanced features
- Third-party integrations (Elementor, WooCommerce)
- Import from other SEO plugins
- Export to other CMS platforms

---

## Release Notes

### v1.0.0 - Initial Public Release

This is the first stable release of the Siloq WordPress Connector plugin. It provides a solid foundation for syncing WordPress pages with the Siloq SEO platform and importing AI-generated content.

**Highlights:**
- Complete two-way synchronization
- AI-powered content import
- Automatic schema markup
- Real-time webhook support
- Professional admin interface

**Who should use this version:**
- WordPress site owners using Siloq platform
- SEO professionals managing content silos
- Teams needing AI content generation
- Anyone wanting automated schema markup

**Migration from Beta:**
If you were using a beta/development version:
1. Backup your WordPress site
2. Deactivate old plugin
3. Delete old plugin files
4. Install v1.0.0
5. Reconfigure API settings
6. Re-sync all pages

---

## Version History

- **1.1.0** (2026-01-29) - Added Lead Gen Scanner widget
- **1.0.0** (2026-01-21) - Initial stable release
- **0.9.0-beta** (Development) - Feature complete, testing phase
- **0.5.0-alpha** (Development) - Core sync functionality
- **0.1.0-alpha** (Development) - Initial proof of concept

---

## Support

For issues, feature requests, or questions:
- **GitHub Issues:** https://github.com/Siloq-seo/siloq-wordpress-plugin/issues
- **Email:** support@siloq.com
- **Documentation:** https://siloq.com/docs

---

## Contributing

We welcome contributions! See CONTRIBUTING.md for guidelines.

---

## License

GPL v2 or later. See LICENSE for full text.
