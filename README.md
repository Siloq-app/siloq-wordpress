# Siloq WordPress Plugin

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2+-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

> Official WordPress plugin for seamless integration with the Siloq SEO platform, enabling intelligent content silo management, AI-powered content generation, and automated SEO optimization.

## Overview

The Siloq WordPress Connector provides enterprise-grade integration between WordPress and the Siloq SEO platform. It enables automatic content synchronization, AI-powered content generation, schema markup injection, and real-time webhook notifications—all designed to streamline SEO workflows and improve search engine visibility.

## Key Features

- **🔄 Bidirectional Synchronization** - Automatically sync WordPress pages with Siloq platform
- **🤖 AI Content Generation** - Generate optimized content using advanced AI technology
- **📊 Schema Markup Automation** - Automatic structured data injection for enhanced SEO
- **⚡ Real-time Integration** - Webhook support for instant notifications and updates
- **🛡️ Enterprise-Ready** - Built with security, performance, and scalability in mind

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- Active Siloq backend instance
- Valid API credentials (API URL and API Key)

## Installation

### WordPress Admin (Recommended)

1. Download the latest release from the [Releases](https://github.com/Siloq-app/siloq-wordpress/releases) page
2. Navigate to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin** and select the downloaded ZIP file
4. Click **Install Now**, then **Activate Plugin**

### Manual Installation

1. Clone or download this repository
2. Extract the `siloq-connector` folder to `/wp-content/plugins/`
3. Navigate to **WordPress Admin → Plugins**
4. Locate **Siloq Connector** and click **Activate**

## Configuration

### Step 1: Obtain API Credentials

1. Log in to your Siloq platform dashboard
2. Navigate to **Settings → API Keys**
3. Generate a new API key or use an existing one
4. Copy your **API URL** and **API Key**

### Step 2: Configure Plugin

1. In WordPress admin, navigate to **Siloq → Settings**
2. Enter your **API URL** and **API Key**
3. Enable **Auto-Sync** for automatic synchronization (optional)
4. Click **Save Settings**
5. Click **Test Connection** to verify configuration

### Step 3: Initial Sync

Choose one of the following methods:
- **Bulk Sync**: Navigate to **Siloq → Settings** and click **Sync All Pages**
- **Individual Sync**: Go to **Siloq → Sync Status** and sync specific pages
- **Auto-Sync**: Pages automatically sync when published or updated (if enabled)

## Usage

### Content Synchronization
- **Bulk Sync**: Sync all pages at once with progress tracking
- **Individual Sync**: Sync specific pages as needed
- **Auto-Sync**: Automatic synchronization on publish/update

### AI Content Generation
1. Navigate to **Siloq → Content Import**
2. Select a page and click **Generate Content**
3. Wait for AI generation to complete
4. Choose to **Import as Draft** or **Replace Content**

### Monitoring
- **Dashboard**: Overview of sync status and key metrics
- **Sync Status**: Real-time tracking of all page synchronization
- **Settings**: Manage API configuration and plugin preferences

## Security

The plugin implements comprehensive security measures:
- Bearer token authentication for all API requests
- Secure API key storage using WordPress options API
- WordPress capability checks and nonce verification
- Input sanitization and output escaping
- Prepared statements for database queries

## Support

- **GitHub Issues**: [Report bugs or request features](https://github.com/Siloq-app/siloq-wordpress/issues)
- **Documentation**: [Siloq Platform Documentation](https://siloq.com/docs)
- **Email Support**: support@siloq.com

## License

This plugin is licensed under the **GPL v2 or later**.

---

**Developed by** [Siloq](https://siloq.com) | [Visit siloq.com](https://siloq.com) for more information
