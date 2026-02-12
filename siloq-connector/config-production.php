<?php
/**
 * Siloq Production Configuration
 *
 * Copy this file to your WordPress root and require it in wp-config.php:
 * require_once(ABSPATH . 'config-production.php');
 *
 * @package Siloq_Connector
 * @since 1.1.0
 */

// Security: Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// ENVIRONMENT CONFIGURATION
// =============================================================================

/**
 * Set environment mode
 * Options: 'production', 'staging', 'development'
 */
define('SILOQ_ENV', getenv('SILOQ_ENV') ?: 'production');

// =============================================================================
// API CONFIGURATION
// =============================================================================

/**
 * Siloq API URL
 * Production: https://api.siloq.ai/api/v1
 * Staging: https://staging-api.siloq.ai/api/v1
 * Development: http://localhost:8000/api/v1
 */
define('SILOQ_API_URL', getenv('SILOQ_API_URL') ?: 'https://api.siloq.ai/api/v1');

/**
 * Siloq API Key
 * CRITICAL: Store in environment variable, NOT in this file!
 * Generate via: curl -X POST {API_URL}/api-keys
 */
define('SILOQ_API_KEY', getenv('SILOQ_API_KEY'));

/**
 * API Request Timeout (seconds)
 */
define('SILOQ_API_TIMEOUT', 30);

/**
 * API Connection Retry Attempts
 */
define('SILOQ_API_RETRIES', 3);

// =============================================================================
// LEAD GEN SCANNER CONFIGURATION
// =============================================================================

/**
 * Signup URL for lead conversion
 * Where users go after viewing scan results
 */
define('SILOQ_SIGNUP_URL', getenv('SILOQ_SIGNUP_URL') ?: 'https://app.siloq.ai/signup?plan=blueprint');

/**
 * Enable/disable scanner widget
 */
define('SILOQ_SCANNER_ENABLED', true);

// =============================================================================
// SECURITY SETTINGS
// =============================================================================

/**
 * Rate Limiting
 * Prevent abuse by limiting scans per IP address
 */
define('SILOQ_RATE_LIMIT_ENABLED', true);
define('SILOQ_RATE_LIMIT_MAX', 10);           // Max scans per window
define('SILOQ_RATE_LIMIT_WINDOW', 3600);      // Time window in seconds (1 hour)

/**
 * CAPTCHA Integration
 * Recommended for production to prevent bot abuse
 * Options: 'none', 'recaptcha', 'hcaptcha'
 */
define('SILOQ_CAPTCHA_TYPE', getenv('SILOQ_CAPTCHA_TYPE') ?: 'none');
define('SILOQ_CAPTCHA_SITE_KEY', getenv('SILOQ_CAPTCHA_SITE_KEY') ?: '');
define('SILOQ_CAPTCHA_SECRET_KEY', getenv('SILOQ_CAPTCHA_SECRET_KEY') ?: '');

/**
 * IP Blocking
 * Block specific IPs or ranges from using the scanner
 */
define('SILOQ_IP_BLACKLIST', getenv('SILOQ_IP_BLACKLIST') ?: '');

// =============================================================================
// PERFORMANCE SETTINGS
// =============================================================================

/**
 * Caching
 * Cache API responses to reduce latency
 */
define('SILOQ_CACHE_ENABLED', true);
define('SILOQ_CACHE_TTL', 3600);              // Cache lifetime in seconds

/**
 * Asset Optimization
 * Use minified assets in production
 */
define('SILOQ_USE_MINIFIED_ASSETS', SILOQ_ENV === 'production');

/**
 * CDN Configuration
 * Serve static assets from CDN
 */
define('SILOQ_CDN_ENABLED', false);
define('SILOQ_CDN_URL', getenv('SILOQ_CDN_URL') ?: '');

// =============================================================================
// LOGGING & MONITORING
// =============================================================================

/**
 * Error Logging
 * Disable verbose logging in production for performance
 */
define('SILOQ_ENABLE_LOGGING', SILOQ_ENV !== 'production');
define('SILOQ_LOG_LEVEL', SILOQ_ENV === 'production' ? 'ERROR' : 'DEBUG');

/**
 * Error Tracking Service
 * Send errors to Sentry, Rollbar, etc.
 */
define('SILOQ_ERROR_TRACKING_ENABLED', true);
define('SILOQ_ERROR_TRACKING_DSN', getenv('SILOQ_ERROR_TRACKING_DSN') ?: '');

/**
 * Analytics
 * Track scanner usage and conversions
 */
define('SILOQ_ANALYTICS_ENABLED', true);

// =============================================================================
// DATABASE SETTINGS
// =============================================================================

/**
 * Lead Data Retention
 * Automatically archive old leads (in days)
 * Set to 0 to disable automatic archiving
 */
define('SILOQ_LEAD_RETENTION_DAYS', 365);

/**
 * Database Optimization
 * Run optimization queries periodically
 */
define('SILOQ_DB_OPTIMIZE_ENABLED', true);

// =============================================================================
// SYNC SETTINGS
// =============================================================================

/**
 * Auto-sync pages to Siloq
 * Automatically sync when pages are published/updated
 */
define('SILOQ_AUTO_SYNC', true);

/**
 * Sync Post Types
 * Which post types to sync to Siloq
 */
define('SILOQ_SYNC_POST_TYPES', 'page,post');

// =============================================================================
// FEATURE FLAGS
// =============================================================================

/**
 * Enable/disable specific features
 */
define('SILOQ_FEATURE_SCHEMA_INJECTION', true);
define('SILOQ_FEATURE_CONTENT_IMPORT', true);
define('SILOQ_FEATURE_WEBHOOKS', true);
define('SILOQ_FEATURE_REDIRECTS', true);

// =============================================================================
// VALIDATION
// =============================================================================

/**
 * Validate critical configuration
 */
if (SILOQ_ENV === 'production') {
    // API Key must be set in production
    if (empty(SILOQ_API_KEY)) {
        error_log('[Siloq] CRITICAL: SILOQ_API_KEY not set in production environment');
        if (is_admin()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>Siloq:</strong> API Key not configured. Please set SILOQ_API_KEY environment variable.</p></div>';
            });
        }
    }

    // API URL must be HTTPS in production
    if (strpos(SILOQ_API_URL, 'https://') !== 0) {
        error_log('[Siloq] WARNING: API URL is not HTTPS in production environment');
    }

    // Warn if rate limiting is disabled
    if (!SILOQ_RATE_LIMIT_ENABLED) {
        error_log('[Siloq] WARNING: Rate limiting disabled in production (not recommended)');
    }
}

// =============================================================================
// CONSTANTS INFO
// =============================================================================

/**
 * Log configuration (development only)
 */
if (SILOQ_ENV !== 'production' && SILOQ_ENABLE_LOGGING) {
    error_log('[Siloq] Configuration loaded:');
    error_log('[Siloq] - Environment: ' . SILOQ_ENV);
    error_log('[Siloq] - API URL: ' . SILOQ_API_URL);
    error_log('[Siloq] - API Key: ' . (empty(SILOQ_API_KEY) ? 'NOT SET' : '***SET***'));
    error_log('[Siloq] - Rate Limiting: ' . (SILOQ_RATE_LIMIT_ENABLED ? 'Enabled' : 'Disabled'));
    error_log('[Siloq] - Caching: ' . (SILOQ_CACHE_ENABLED ? 'Enabled' : 'Disabled'));
}
