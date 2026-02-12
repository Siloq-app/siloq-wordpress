#!/bin/bash

# =============================================================================
# Siloq WordPress Plugin - Production Deployment Script
# =============================================================================
#
# This script automates the deployment of Siloq Connector to production
#
# Usage:
#   ./deploy-production.sh [environment]
#
# Environments: production, staging
#
# =============================================================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_NAME="siloq-connector"
VERSION="1.1.0"

# Default environment
ENVIRONMENT="${1:-production}"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# =============================================================================
# PRE-DEPLOYMENT CHECKS
# =============================================================================

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║   Siloq WordPress Plugin - Production Deployment            ║"
echo "║   Version: $VERSION                                          ║"
echo "║   Environment: $ENVIRONMENT                                  ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

log_info "Running pre-deployment checks..."

# Check if WP-CLI is available
if ! command -v wp &> /dev/null; then
    log_warning "WP-CLI not found. Some automated tasks will be skipped."
    WP_CLI_AVAILABLE=false
else
    WP_CLI_AVAILABLE=true
    log_success "WP-CLI found"
fi

# Check if required files exist
if [ ! -f "$SCRIPT_DIR/siloq-connector-v${VERSION}-clean.zip" ]; then
    log_error "Plugin ZIP file not found: siloq-connector-v${VERSION}-clean.zip"
    log_info "Run this first: cd $SCRIPT_DIR && zip -r siloq-connector-v${VERSION}-clean.zip siloq-connector/"
    exit 1
fi

log_success "Plugin package found"

# =============================================================================
# BACKUP
# =============================================================================

log_info "Creating backup..."

# Prompt for WordPress directory
read -p "Enter WordPress installation path [/var/www/html]: " WP_PATH
WP_PATH=${WP_PATH:-/var/www/html}

if [ ! -d "$WP_PATH" ]; then
    log_error "WordPress directory not found: $WP_PATH"
    exit 1
fi

# Create backup directory
BACKUP_DIR="$SCRIPT_DIR/backups"
mkdir -p "$BACKUP_DIR"

BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/${PLUGIN_NAME}_backup_${BACKUP_DATE}.tar.gz"

# Backup existing plugin (if exists)
if [ -d "$WP_PATH/wp-content/plugins/$PLUGIN_NAME" ]; then
    log_info "Backing up existing plugin..."
    tar -czf "$BACKUP_FILE" -C "$WP_PATH/wp-content/plugins" "$PLUGIN_NAME" 2>/dev/null || true
    log_success "Backup created: $BACKUP_FILE"
else
    log_info "No existing plugin to backup (fresh install)"
fi

# Backup database (if WP-CLI available)
if [ "$WP_CLI_AVAILABLE" = true ]; then
    log_info "Backing up database..."
    DB_BACKUP_FILE="$BACKUP_DIR/database_backup_${BACKUP_DATE}.sql"
    cd "$WP_PATH"
    wp db export "$DB_BACKUP_FILE" --path="$WP_PATH" 2>/dev/null || log_warning "Database backup failed"

    if [ -f "$DB_BACKUP_FILE" ]; then
        log_success "Database backup created: $DB_BACKUP_FILE"
    fi
fi

# =============================================================================
# DEPLOYMENT
# =============================================================================

log_info "Deploying plugin..."

# Extract plugin
log_info "Extracting plugin files..."
cd "$WP_PATH/wp-content/plugins"

# Remove old version if exists
if [ -d "$PLUGIN_NAME" ]; then
    log_warning "Removing old version..."
    rm -rf "$PLUGIN_NAME"
fi

# Extract new version
unzip -q "$SCRIPT_DIR/siloq-connector-v${VERSION}-clean.zip"
log_success "Plugin files extracted"

# Set correct permissions
log_info "Setting file permissions..."
find "$PLUGIN_NAME" -type d -exec chmod 755 {} \;
find "$PLUGIN_NAME" -type f -exec chmod 644 {} \;
log_success "Permissions set"

# =============================================================================
# CONFIGURATION
# =============================================================================

log_info "Checking configuration..."

# Check for environment variables
if [ -z "$SILOQ_API_KEY" ]; then
    log_warning "SILOQ_API_KEY environment variable not set"
    log_info "Please set it in your server configuration"
fi

# Check if config file exists
if [ -f "$WP_PATH/config-production.php" ]; then
    log_success "Production config found"
else
    log_warning "Production config not found"
    log_info "Copy siloq-connector/config-production.php to WordPress root"
fi

# =============================================================================
# ACTIVATION
# =============================================================================

if [ "$WP_CLI_AVAILABLE" = true ]; then
    log_info "Activating plugin via WP-CLI..."

    cd "$WP_PATH"

    # Activate plugin
    if wp plugin activate "$PLUGIN_NAME" --path="$WP_PATH" 2>/dev/null; then
        log_success "Plugin activated"
    else
        log_warning "Plugin activation failed (may already be active)"
    fi

    # Verify plugin status
    if wp plugin status "$PLUGIN_NAME" --path="$WP_PATH" 2>/dev/null | grep -q "Status: Active"; then
        log_success "Plugin is active"
    else
        log_error "Plugin is not active"
    fi
else
    log_warning "Please activate plugin manually in WordPress admin"
fi

# =============================================================================
# POST-DEPLOYMENT CHECKS
# =============================================================================

log_info "Running post-deployment checks..."

# Check plugin files
REQUIRED_FILES=(
    "siloq-connector.php"
    "includes/class-siloq-lead-gen-scanner.php"
    "assets/css/lead-gen-scanner.css"
    "assets/js/lead-gen-scanner.js"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$WP_PATH/wp-content/plugins/$PLUGIN_NAME/$file" ]; then
        log_success "✓ $file"
    else
        log_error "✗ $file (MISSING)"
    fi
done

# Check database tables (if WP-CLI available)
if [ "$WP_CLI_AVAILABLE" = true ]; then
    cd "$WP_PATH"

    log_info "Checking database tables..."

    if wp db query "SHOW TABLES LIKE 'wp_siloq_leads'" --path="$WP_PATH" 2>/dev/null | grep -q "wp_siloq_leads"; then
        log_success "✓ wp_siloq_leads table exists"
    else
        log_warning "✗ wp_siloq_leads table not found (will be created on first scan)"
    fi
fi

# =============================================================================
# CLEANUP
# =============================================================================

log_info "Cleaning up..."

# Remove old backups (keep last 10)
cd "$BACKUP_DIR"
ls -t ${PLUGIN_NAME}_backup_*.tar.gz 2>/dev/null | tail -n +11 | xargs rm -f 2>/dev/null || true
log_success "Old backups cleaned"

# =============================================================================
# SUMMARY
# =============================================================================

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║   DEPLOYMENT COMPLETE                                        ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
log_success "Plugin deployed successfully to: $WP_PATH/wp-content/plugins/$PLUGIN_NAME"
log_success "Backup saved to: $BACKUP_FILE"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  NEXT STEPS:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "1. Configure production settings:"
echo "   → WordPress Admin → Siloq → Settings"
echo ""
echo "2. Set environment variables on your server:"
echo "   → SILOQ_API_KEY (required)"
echo "   → SILOQ_API_URL (default: https://api.siloq.io/v1)"
echo ""
echo "3. Test the connection:"
echo "   → Click 'Test Connection' in settings"
echo ""
echo "4. Add scanner to a page:"
echo "   → Use shortcode: [siloq_scanner]"
echo ""
echo "5. Monitor error logs:"
echo "   → tail -f $WP_PATH/wp-content/debug.log"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Save deployment log
DEPLOY_LOG="$BACKUP_DIR/deployment_${BACKUP_DATE}.log"
echo "Deployment Date: $(date)" > "$DEPLOY_LOG"
echo "Version: $VERSION" >> "$DEPLOY_LOG"
echo "Environment: $ENVIRONMENT" >> "$DEPLOY_LOG"
echo "WordPress Path: $WP_PATH" >> "$DEPLOY_LOG"
echo "Backup File: $BACKUP_FILE" >> "$DEPLOY_LOG"

log_success "Deployment log saved: $DEPLOY_LOG"

# Ask if user wants to run post-deployment tests
echo ""
read -p "Run post-deployment tests? (y/n): " RUN_TESTS

if [ "$RUN_TESTS" = "y" ] || [ "$RUN_TESTS" = "Y" ]; then
    log_info "Running post-deployment tests..."

    # Test scanner page load
    if command -v curl &> /dev/null; then
        read -p "Enter URL of page with scanner widget: " TEST_URL

        if [ -n "$TEST_URL" ]; then
            log_info "Testing page load..."
            HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$TEST_URL")

            if [ "$HTTP_CODE" = "200" ]; then
                log_success "Page loads successfully (HTTP $HTTP_CODE)"
            else
                log_error "Page load failed (HTTP $HTTP_CODE)"
            fi
        fi
    fi
fi

echo ""
log_success "Deployment complete!"
echo ""
