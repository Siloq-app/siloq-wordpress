# Siloq WordPress Plugin

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2+-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

> Official WordPress plugin for seamless integration with the Siloq SEO platform, enabling intelligent content silo management, AI-powered content generation, and automated SEO optimization.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Documentation](#documentation)
- [API Reference](#api-reference)
- [Security](#security)
- [Contributing](#contributing)
- [Support](#support)
- [License](#license)

## Overview

The Siloq WordPress Connector plugin provides enterprise-grade integration between WordPress and the Siloq SEO platform. It enables automatic content synchronization, AI-powered content generation, schema markup injection, and real-time webhook notifications—all designed to streamline SEO workflows and improve search engine visibility.

### Key Capabilities

- **Bidirectional Synchronization**: Automatically sync WordPress pages with the Siloq platform
- **AI Content Generation**: Leverage AI to create optimized content for your pages
- **Schema Markup Automation**: Automatic injection of structured data for enhanced SEO
- **Real-time Integration**: Webhook support for instant notifications and updates
- **Enterprise-Ready**: Built with security, performance, and scalability in mind

## Features

### Core Functionality

- **Two-Way Page Synchronization** - Seamless sync between WordPress and Siloq platform
- **Automatic Sync on Publish** - Pages sync automatically when published or updated
- **Bulk Sync Operations** - Sync all pages at once with progress tracking
- **Sync Status Monitoring** - Real-time tracking of sync status across all pages

### Content Management

- **AI Content Generation** - Generate optimized content using AI technology
- **Content Import System** - Import generated content as drafts or replace existing
- **Content Backup & Restore** - Automatic backup before content replacement
- **FAQ Auto-Injection** - Automatically add FAQ sections to content
- **Internal Link Management** - Intelligent internal linking suggestions

### SEO Optimization

- **Schema Markup Injection** - Automatic structured data injection
- **SEO Metadata Sync** - Sync meta titles, descriptions, and keywords
- **Rich Results Support** - Google Rich Results compatible schema

### Developer Features

- **REST API Integration** - Secure API communication with Bearer token authentication
- **Webhook Support** - Real-time event notifications from Siloq platform
- **Comprehensive Admin UI** - Professional admin interface for management
- **Error Handling & Logging** - Detailed error reporting and debugging support

## Requirements

### Minimum Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher

### Additional Requirements

- Active Siloq backend instance
- Valid API credentials (API URL and API Key)
- Network access to Siloq API endpoint

## Installation

### Method 1: WordPress Admin (Recommended)

1. Download the latest release from the [Releases](https://github.com/Siloq-seo/siloq-wordpress/releases) page
2. Navigate to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin** and select the downloaded ZIP file
4. Click **Install Now**, then **Activate Plugin**

### Method 2: Manual Installation

1. Clone or download this repository
2. Extract the `siloq-connector` folder
3. Upload the folder to `/wp-content/plugins/` via FTP or file manager
4. Navigate to **WordPress Admin → Plugins**
5. Locate **Siloq Connector** and click **Activate**

### Method 3: Git Installation (Developers)

```bash
cd /path/to/wordpress/wp-content/plugins
git clone https://github.com/Siloq-seo/siloq-wordpress.git siloq-connector
cd siloq-connector
git checkout main
```

Then activate the plugin through the WordPress admin panel.

## Configuration

### Step 1: Obtain API Credentials

1. Log in to your Siloq platform dashboard
2. Navigate to **Settings → API Keys**
3. Generate a new API key or use an existing one
4. Copy your **API URL** and **API Key**

### Step 2: Configure Plugin Settings

1. In WordPress admin, navigate to **Siloq → Settings**
2. Enter your **API URL** (e.g., `https://api.siloq.com/api/v1`)
3. Enter your **API Key**
4. Optionally enable **Auto-Sync** for automatic synchronization
5. Click **Save Settings**
6. Click **Test Connection** to verify the configuration

### Step 3: Initial Synchronization

Choose one of the following methods:

- **Bulk Sync**: Navigate to **Siloq → Settings** and click **Sync All Pages**
- **Individual Sync**: Go to **Siloq → Sync Status** and click **Sync Now** next to specific pages
- **Auto-Sync**: Pages will automatically sync when published or updated (if enabled)

## Usage

### Synchronizing Pages

#### Bulk Synchronization

1. Navigate to **Siloq → Settings**
2. Click **Sync All Pages**
3. Monitor progress in the sync status panel
4. Review results for any errors or warnings

#### Individual Page Sync

1. Navigate to **Siloq → Sync Status**
2. Locate the page you want to sync
3. Click **Sync Now** button
4. Verify sync status updates in real-time

#### Automatic Synchronization

Enable Auto-Sync in settings to automatically synchronize pages when:
- A page is published
- A page is updated
- A page status changes

### Content Generation

1. Navigate to **Siloq → Content Import**
2. Select a page from the dropdown
3. Click **Generate Content**
4. Wait for AI generation to complete (you'll receive a notification)
5. Choose to **Import as Draft** or **Replace Content**
6. Review and publish the generated content

### Monitoring Sync Status

The **Sync Status** page provides:
- Current sync status for each page
- Last sync timestamp
- Schema markup presence indicator
- Quick action buttons for re-syncing

### Webhook Configuration

1. Navigate to **Siloq → Content Import**
2. Copy the webhook URL displayed
3. In your Siloq backend, configure the webhook endpoint
4. Set the webhook secret to match your API key
5. Test webhook delivery to verify configuration

## Documentation

The plugin uses a modern React-based architecture:

### Plugin Structure

- **[siloq-connector.php](siloq-connector/siloq-connector.php)** - Main plugin file with REST API endpoints
- **[react-ui/src/](siloq-connector/react-ui/src/)** - React TypeScript frontend
  - `App.tsx` - Main application component
  - `index.tsx` - Application entry point
  - `types.ts` - TypeScript type definitions
  - `components/` - Reusable UI components
  - `services/` - API service modules
  - `views/` - Page view components:
    - `Dashboard.tsx` - Main dashboard view
    - `LeadGenScanner.tsx` - Lead generation scanner
    - `PageSync.tsx` - Page synchronization
    - `Settings.tsx` - Plugin settings
    - `SetupWizard.tsx` - Onboarding wizard
- **[scripts/](siloq-connector/scripts/)** - Build and utility scripts

## API Reference

The plugin integrates with the following Siloq API endpoints:

### Authentication
- `POST /api/v1/auth/verify` - Verify API credentials

### Page Synchronization
- `POST /api/v1/pages/sync` - Synchronize page data to Siloq
- `GET /api/v1/pages/{id}/schema` - Retrieve schema markup for a page

### Content Generation
- `POST /api/v1/content-jobs` - Create content generation job
- `GET /api/v1/content-jobs/{id}` - Retrieve job status and results

### Webhooks

The plugin provides a webhook endpoint for receiving notifications:
- `POST /wp-json/siloq/v1/webhook` - Webhook endpoint for Siloq events

Supported webhook events:
- `content.generated` - Content generation completed
- `schema.updated` - Schema markup updated
- `page.analyzed` - Page analysis completed
- `sync.completed` - Sync operation completed

## Security

The plugin implements multiple layers of security:

### Authentication & Authorization
- Bearer token authentication for all API requests
- Secure API key storage using WordPress options API
- WordPress capability checks for admin actions
- Nonce verification for all AJAX requests

### Data Protection
- Input sanitization on all user inputs
- Output escaping for all displayed data
- Prepared statements for database queries
- HMAC signature verification for webhooks

### Best Practices
- Follows WordPress coding standards
- Implements security best practices
- Regular security audits recommended
- No sensitive data in client-side code

## Contributing

We welcome contributions from the community. Please follow these guidelines:

### Development Setup

1. Fork the repository
2. Clone your fork locally
3. Create a feature branch (`git checkout -b feature/your-feature`)
4. Make your changes following WordPress coding standards
5. Test thoroughly on a development environment
6. Commit with clear, descriptive messages
7. Push to your fork and create a Pull Request

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use meaningful variable and function names
- Add comments for complex logic
- Include inline documentation for functions and classes

### Testing Requirements

Before submitting a PR:
- Test on fresh WordPress installation
- Verify with auto-sync enabled and disabled
- Test bulk sync with multiple pages
- Test error handling scenarios
- Check for PHP errors and warnings
- Verify security measures are maintained

## Support

### Getting Help

- **GitHub Issues**: [Report bugs or request features](https://github.com/Siloq-seo/siloq-wordpress/issues)
- **Documentation**: [Siloq Platform Documentation](https://siloq.com/docs)
- **Email Support**: support@siloq.com

### Reporting Issues

When reporting issues, please include:
- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected vs actual behavior
- Relevant error messages

## License

This plugin is licensed under the **GPL v2 or later**.

```
Copyright (C) 2026 Siloq

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Credits

**Developed by** [Siloq](https://siloq.com)

For more information about Siloq, visit [https://siloq.com](https://siloq.com)

---

**Made with ❤️ for better SEO**
