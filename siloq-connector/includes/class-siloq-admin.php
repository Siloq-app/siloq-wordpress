<?php
/**
 * Siloq Admin Interface
 * Handles admin pages and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Admin {
    
    /**
     * Default API URL for production
     */
    const DEFAULT_API_URL = 'https://api.siloq.ai/api/v1';
    const DASHBOARD_URL = 'https://app.siloq.ai';
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        // Handle form submission
        if (isset($_POST['siloq_save_settings']) && check_admin_referer('siloq_settings_nonce')) {
            self::save_settings();
        }
        
        // Get current settings
        $api_url = get_option('siloq_api_url', self::DEFAULT_API_URL);
        $api_key = get_option('siloq_api_key', '');
        $auto_sync = get_option('siloq_auto_sync', 'yes');
        $signup_url = get_option('siloq_signup_url', '');
        $use_dummy_scan = get_option('siloq_use_dummy_scan', 'yes');
        $show_advanced = get_option('siloq_show_advanced', 'no');
        
        // Check if connected
        $is_connected = !empty($api_key) && !empty($api_url);
        $connection_verified = get_transient('siloq_connection_verified');
        
        ?>
        <div class="wrap siloq-admin-wrap">
            <div class="siloq-header">
                <h1>
                    <img src="<?php echo esc_url(SILOQ_PLUGIN_URL . 'assets/siloq-logo.png'); ?>" alt="Siloq" class="siloq-logo" onerror="this.style.display='none'">
                    <?php _e('Siloq Settings', 'siloq-connector'); ?>
                </h1>
                <p class="siloq-tagline"><?php _e('The SEO Architect — Eliminate keyword cannibalization and optimize your site structure.', 'siloq-connector'); ?></p>
            </div>
            
            <?php settings_errors('siloq_settings'); ?>
            
            <?php if (!$is_connected): ?>
                <!-- Setup Wizard for New Users -->
                <div class="siloq-setup-wizard">
                    <div class="siloq-setup-card">
                        <h2><?php _e('🚀 Get Started with Siloq', 'siloq-connector'); ?></h2>
                        <p><?php _e('Connect your WordPress site to Siloq in 3 easy steps:', 'siloq-connector'); ?></p>
                        
                        <div class="siloq-setup-steps">
                            <div class="siloq-step">
                                <div class="siloq-step-number">1</div>
                                <div class="siloq-step-content">
                                    <h3><?php _e('Log In to Siloq', 'siloq-connector'); ?></h3>
                                    <p><?php _e('Sign in to your Siloq account to get your API key.', 'siloq-connector'); ?></p>
                                    <div class="siloq-step-buttons">
                                        <a href="<?php echo esc_url(self::DASHBOARD_URL . '/login'); ?>" target="_blank" class="button button-primary">
                                            <?php _e('Sign In →', 'siloq-connector'); ?>
                                        </a>
                                        <span class="siloq-or"><?php _e('or', 'siloq-connector'); ?></span>
                                        <a href="<?php echo esc_url(self::DASHBOARD_URL . '/signup'); ?>" target="_blank" class="button button-secondary">
                                            <?php _e('Create Account', 'siloq-connector'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="siloq-step">
                                <div class="siloq-step-number">2</div>
                                <div class="siloq-step-content">
                                    <h3><?php _e('Generate an API Key', 'siloq-connector'); ?></h3>
                                    <p><?php _e('In your Siloq dashboard, go to Sites → click your site → Generate Token.', 'siloq-connector'); ?></p>
                                    <a href="<?php echo esc_url(self::DASHBOARD_URL . '/dashboard?tab=sites'); ?>" target="_blank" class="button button-secondary">
                                        <?php _e('Open Dashboard →', 'siloq-connector'); ?>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="siloq-step">
                                <div class="siloq-step-number">3</div>
                                <div class="siloq-step-content">
                                    <h3><?php _e('Paste Your API Key Below', 'siloq-connector'); ?></h3>
                                    <p><?php _e('Copy the API key from Siloq and paste it in the form below.', 'siloq-connector'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Connection Status Banner -->
                <div class="siloq-connection-banner <?php echo $connection_verified ? 'connected' : 'warning'; ?>">
                    <?php if ($connection_verified): ?>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('Connected to Siloq', 'siloq-connector'); ?>
                        <a href="<?php echo esc_url(self::DASHBOARD_URL . '/dashboard'); ?>" target="_blank" class="siloq-dashboard-link">
                            <?php _e('Open Dashboard →', 'siloq-connector'); ?>
                        </a>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('API key configured — click "Test Connection" to verify', 'siloq-connector'); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="siloq-settings-container">
                <form method="post" action="">
                    <?php wp_nonce_field('siloq_settings_nonce'); ?>
                    
                    <!-- Main Settings Card -->
                    <div class="siloq-card">
                        <h2><?php _e('Connection Settings', 'siloq-connector'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="siloq_api_key">
                                        <?php _e('API Key', 'siloq-connector'); ?>
                                        <span class="required">*</span>
                                    </label>
                                </th>
                                <td>
                                    <input 
                                        type="password" 
                                        id="siloq_api_key" 
                                        name="siloq_api_key" 
                                        value="<?php echo esc_attr($api_key); ?>" 
                                        class="regular-text"
                                        placeholder="sk_siloq_..."
                                        required
                                    />
                                    <button type="button" id="siloq-toggle-key" class="button button-small" title="Show/Hide">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                    <p class="description">
                                        <?php _e('Your Siloq API key. ', 'siloq-connector'); ?>
                                        <a href="<?php echo esc_url(self::DASHBOARD_URL . '/dashboard?tab=sites'); ?>" target="_blank">
                                            <?php _e('Get your API key →', 'siloq-connector'); ?>
                                        </a>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <?php _e('Auto-Sync', 'siloq-connector'); ?>
                                </th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="siloq_auto_sync"
                                                value="yes"
                                                <?php checked($auto_sync, 'yes'); ?>
                                            />
                                            <?php _e('Automatically sync pages when published or updated', 'siloq-connector'); ?>
                                        </label>
                                        <p class="description">
                                            <?php _e('Recommended. Keeps your Siloq dashboard up-to-date with your latest content.', 'siloq-connector'); ?>
                                        </p>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" name="siloq_save_settings" class="button button-primary button-large">
                                <?php _e('Save Settings', 'siloq-connector'); ?>
                            </button>
                            
                            <button type="button" id="siloq-test-connection" class="button button-secondary">
                                <?php _e('Test Connection', 'siloq-connector'); ?>
                            </button>
                            
                            <span id="siloq-connection-status" class="siloq-status-message"></span>
                        </p>
                    </div>
                    
                    <?php if ($is_connected): ?>
                    <!-- Sync Actions Card -->
                    <div class="siloq-card">
                        <h2><?php _e('Sync Your Content', 'siloq-connector'); ?></h2>
                        <p class="description"><?php _e('Sync your WordPress pages to Siloq for SEO analysis and optimization recommendations.', 'siloq-connector'); ?></p>
                        
                        <p>
                            <button type="button" id="siloq-sync-all-pages" class="button button-primary button-large">
                                <?php _e('Sync All Pages', 'siloq-connector'); ?>
                            </button>
                            <span class="description" style="margin-left: 10px;">
                                <?php _e('This may take a few minutes for large sites.', 'siloq-connector'); ?>
                            </span>
                        </p>
                        
                        <div id="siloq-sync-progress" class="siloq-sync-progress" style="display:none;">
                            <p><strong><?php _e('Syncing pages...', 'siloq-connector'); ?></strong></p>
                            <div class="siloq-progress-bar">
                                <div class="siloq-progress-fill" style="width: 0%"></div>
                            </div>
                            <p class="siloq-progress-text">0 / 0</p>
                        </div>
                        
                        <div id="siloq-sync-results" class="siloq-sync-results" style="display:none;"></div>
                    </div>
                    
                    <!-- Business Profile Wizard -->
                    <div class="siloq-card siloq-business-profile-card">
                        <h2><?php _e('🏢 Business Profile', 'siloq-connector'); ?></h2>
                        <p class="description"><?php _e('Tell Siloq about your business to get personalized content recommendations and silo suggestions.', 'siloq-connector'); ?></p>
                        
                        <div id="siloq-profile-loading" style="display:none;">
                            <p><span class="spinner is-active" style="float:none;"></span> <?php _e('Loading profile...', 'siloq-connector'); ?></p>
                        </div>
                        
                        <div id="siloq-profile-form" style="display:none;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="siloq_business_type"><?php _e('Business Type', 'siloq-connector'); ?></label>
                                    </th>
                                    <td>
                                        <select id="siloq_business_type" name="siloq_business_type" class="regular-text">
                                            <option value=""><?php _e('Select your business type...', 'siloq-connector'); ?></option>
                                            <option value="local_service"><?php _e('Local/Service Business', 'siloq-connector'); ?></option>
                                            <option value="ecommerce"><?php _e('E-Commerce', 'siloq-connector'); ?></option>
                                            <option value="content_blog"><?php _e('Content/Blog', 'siloq-connector'); ?></option>
                                            <option value="saas"><?php _e('SaaS/Software', 'siloq-connector'); ?></option>
                                            <option value="other"><?php _e('Other', 'siloq-connector'); ?></option>
                                        </select>
                                        <p class="description"><?php _e('Helps Siloq suggest the best content structure.', 'siloq-connector'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="siloq_primary_services"><?php _e('Services/Products', 'siloq-connector'); ?></label>
                                    </th>
                                    <td>
                                        <div id="siloq-services-list" class="siloq-tag-list"></div>
                                        <div style="display:flex; gap:5px; margin-top:5px;">
                                            <input type="text" id="siloq_new_service" placeholder="<?php _e('Add a service or product...', 'siloq-connector'); ?>" class="regular-text">
                                            <button type="button" id="siloq-add-service" class="button"><?php _e('Add', 'siloq-connector'); ?></button>
                                        </div>
                                        <p class="description"><?php _e('Your main services or products. Siloq creates content silos around each one.', 'siloq-connector'); ?></p>
                                    </td>
                                </tr>
                                <tr id="siloq-service-areas-row" style="display:none;">
                                    <th scope="row">
                                        <label for="siloq_service_areas"><?php _e('Service Areas', 'siloq-connector'); ?></label>
                                    </th>
                                    <td>
                                        <div id="siloq-areas-list" class="siloq-tag-list"></div>
                                        <div style="display:flex; gap:5px; margin-top:5px;">
                                            <input type="text" id="siloq_new_area" placeholder="<?php _e('e.g., Kansas City, MO', 'siloq-connector'); ?>" class="regular-text">
                                            <button type="button" id="siloq-add-area" class="button"><?php _e('Add', 'siloq-connector'); ?></button>
                                        </div>
                                        <p class="description"><?php _e('For local businesses: cities or areas you serve.', 'siloq-connector'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="button" id="siloq-save-profile" class="button button-primary">
                                    <?php _e('Save Business Profile', 'siloq-connector'); ?>
                                </button>
                                <span id="siloq-profile-status" class="siloq-status-message"></span>
                            </p>
                        </div>
                        
                        <div id="siloq-profile-error" style="display:none;" class="notice notice-error inline">
                            <p></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Advanced Settings (Collapsible) -->
                    <div class="siloq-card siloq-advanced-card">
                        <h2>
                            <button type="button" class="siloq-toggle-advanced">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <?php _e('Advanced Settings', 'siloq-connector'); ?>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                        </h2>
                        
                        <div class="siloq-advanced-content" style="display: <?php echo $show_advanced === 'yes' ? 'block' : 'none'; ?>;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="siloq_api_url">
                                            <?php _e('API URL', 'siloq-connector'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input 
                                            type="url" 
                                            id="siloq_api_url" 
                                            name="siloq_api_url" 
                                            value="<?php echo esc_attr($api_url); ?>" 
                                            class="regular-text"
                                            placeholder="<?php echo esc_attr(self::DEFAULT_API_URL); ?>"
                                        />
                                        <p class="description">
                                            <?php _e('Only change this if you\'re using a self-hosted Siloq instance. Default: ', 'siloq-connector'); ?>
                                            <code><?php echo esc_html(self::DEFAULT_API_URL); ?></code>
                                        </p>
                                    </td>
                                </tr>

                                <?php // Lead Gen settings hidden for V1 - uncomment for agency features ?>
                                <?php /* 
                                <tr>
                                    <th scope="row">
                                        <label for="siloq_signup_url">
                                            <?php _e('Lead Gen Signup URL', 'siloq-connector'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="url"
                                            id="siloq_signup_url"
                                            name="siloq_signup_url"
                                            value="<?php echo esc_attr($signup_url); ?>"
                                            class="regular-text"
                                            placeholder="https://app.siloq.ai/signup"
                                        />
                                        <p class="description">
                                            <?php _e('For agencies: Customize where users go after using the [siloq_scanner] shortcode.', 'siloq-connector'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <?php _e('Lead Gen Scanner', 'siloq-connector'); ?>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    name="siloq_use_dummy_scan"
                                                    value="yes"
                                                    <?php checked($use_dummy_scan, 'yes'); ?>
                                                />
                                                <?php _e('Use demo mode (returns sample results without calling API)', 'siloq-connector'); ?>
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                                */ ?>
                            </table>
                            
                            <input type="hidden" name="siloq_show_advanced" value="<?php echo esc_attr($show_advanced); ?>" id="siloq_show_advanced">
                        </div>
                    </div>
                </form>
                
                <!-- Help Card -->
                <div class="siloq-card siloq-help-card">
                    <h2><?php _e('Need Help?', 'siloq-connector'); ?></h2>
                    <ul>
                        <li>
                            <a href="https://docs.siloq.ai" target="_blank">
                                <span class="dashicons dashicons-book"></span>
                                <?php _e('Documentation', 'siloq-connector'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo esc_url(self::DASHBOARD_URL); ?>" target="_blank">
                                <span class="dashicons dashicons-dashboard"></span>
                                <?php _e('Siloq Dashboard', 'siloq-connector'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="mailto:support@siloq.ai">
                                <span class="dashicons dashicons-email"></span>
                                <?php _e('Contact Support', 'siloq-connector'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <style>
            .siloq-admin-wrap { max-width: 900px; }
            .siloq-header { margin-bottom: 20px; }
            .siloq-header h1 { display: flex; align-items: center; gap: 10px; }
            .siloq-logo { height: 32px; width: auto; }
            .siloq-tagline { color: #666; font-size: 14px; margin-top: 5px; }
            
            .siloq-setup-wizard { margin-bottom: 30px; }
            .siloq-setup-card { background: linear-gradient(135deg, #fff8e1 0%, #fff 100%); border: 1px solid #f0c14b; border-radius: 8px; padding: 25px; }
            .siloq-setup-card h2 { margin-top: 0; color: #333; }
            .siloq-setup-steps { display: flex; flex-direction: column; gap: 20px; margin-top: 20px; }
            .siloq-step { display: flex; gap: 15px; align-items: flex-start; }
            .siloq-step-number { background: #f0c14b; color: #333; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0; }
            .siloq-step-content h3 { margin: 0 0 5px 0; font-size: 15px; }
            .siloq-step-content p { margin: 0 0 10px 0; color: #666; }
            .siloq-step-buttons { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
            .siloq-or { color: #666; font-size: 13px; }
            
            .siloq-connection-banner { padding: 12px 20px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
            .siloq-connection-banner.connected { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
            .siloq-connection-banner.warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
            .siloq-dashboard-link { margin-left: auto; }
            
            .siloq-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px 25px; margin-bottom: 20px; }
            .siloq-card h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
            
            .siloq-advanced-card h2 { border-bottom: none; padding-bottom: 0; }
            .siloq-toggle-advanced { background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 600; color: #1d2327; padding: 0; }
            .siloq-toggle-advanced:hover { color: #2271b1; }
            
            .siloq-help-card ul { list-style: none; padding: 0; margin: 0; }
            .siloq-help-card li { margin-bottom: 10px; }
            .siloq-help-card a { display: flex; align-items: center; gap: 8px; text-decoration: none; color: #2271b1; }
            .siloq-help-card a:hover { text-decoration: underline; }
            
            .siloq-progress-bar { background: #e0e0e0; border-radius: 4px; height: 20px; overflow: hidden; margin: 10px 0; }
            .siloq-progress-fill { background: #f0c14b; height: 100%; transition: width 0.3s ease; }
            
            .siloq-sync-results { margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 4px; }
            
            #siloq-toggle-key { vertical-align: middle; margin-left: 5px; }
            
            .required { color: #d63638; }
            
            /* Business Profile Styles */
            .siloq-tag-list { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 5px; }
            .siloq-tag { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; background: #f0f0f1; border-radius: 4px; font-size: 13px; }
            .siloq-tag-remove { background: none; border: none; cursor: pointer; color: #666; font-size: 16px; line-height: 1; padding: 0; }
            .siloq-tag-remove:hover { color: #d63638; }
            .siloq-profile-complete { color: #46b450; display: flex; align-items: center; gap: 5px; }
            .siloq-profile-incomplete { color: #dba617; display: flex; align-items: center; gap: 5px; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Toggle API key visibility
            $('#siloq-toggle-key').on('click', function() {
                var input = $('#siloq_api_key');
                var type = input.attr('type') === 'password' ? 'text' : 'password';
                input.attr('type', type);
                $(this).find('.dashicons').toggleClass('dashicons-visibility dashicons-hidden');
            });
            
            // Toggle advanced settings
            $('.siloq-toggle-advanced').on('click', function() {
                var content = $('.siloq-advanced-content');
                var icon = $(this).find('.dashicons-arrow-down-alt2, .dashicons-arrow-up-alt2');
                content.slideToggle(200);
                icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
                $('#siloq_show_advanced').val(content.is(':visible') ? 'yes' : 'no');
            });
            
            // Business Profile functionality
            var siloqServices = [];
            var siloqAreas = [];
            
            // Load business profile on page load
            if ($('.siloq-business-profile-card').length) {
                loadBusinessProfile();
            }
            
            function loadBusinessProfile() {
                $('#siloq-profile-loading').show();
                $('#siloq-profile-form').hide();
                $('#siloq-profile-error').hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'siloq_get_business_profile',
                        nonce: siloqAjax.nonce
                    },
                    success: function(response) {
                        $('#siloq-profile-loading').hide();
                        if (response.success && response.data) {
                            var profile = response.data;
                            $('#siloq_business_type').val(profile.business_type || '');
                            siloqServices = profile.primary_services || [];
                            siloqAreas = profile.service_areas || [];
                            renderServices();
                            renderAreas();
                            toggleServiceAreasRow();
                            $('#siloq-profile-form').show();
                        } else {
                            $('#siloq-profile-error p').text(response.data ? response.data.message : 'Failed to load profile');
                            $('#siloq-profile-error').show();
                            $('#siloq-profile-form').show();
                        }
                    },
                    error: function() {
                        $('#siloq-profile-loading').hide();
                        $('#siloq-profile-error p').text('Connection error. Please try again.');
                        $('#siloq-profile-error').show();
                        $('#siloq-profile-form').show();
                    }
                });
            }
            
            function renderServices() {
                var html = '';
                siloqServices.forEach(function(service, index) {
                    html += '<span class="siloq-tag">' + escapeHtml(service) + 
                            '<button type="button" class="siloq-tag-remove" data-index="' + index + '" data-type="service">&times;</button></span>';
                });
                $('#siloq-services-list').html(html);
            }
            
            function renderAreas() {
                var html = '';
                siloqAreas.forEach(function(area, index) {
                    html += '<span class="siloq-tag">' + escapeHtml(area) + 
                            '<button type="button" class="siloq-tag-remove" data-index="' + index + '" data-type="area">&times;</button></span>';
                });
                $('#siloq-areas-list').html(html);
            }
            
            function escapeHtml(text) {
                return $('<div>').text(text).html();
            }
            
            function toggleServiceAreasRow() {
                if ($('#siloq_business_type').val() === 'local_service') {
                    $('#siloq-service-areas-row').show();
                } else {
                    $('#siloq-service-areas-row').hide();
                }
            }
            
            // Business type change
            $('#siloq_business_type').on('change', toggleServiceAreasRow);
            
            // Add service
            $('#siloq-add-service').on('click', function() {
                var service = $('#siloq_new_service').val().trim();
                if (service && siloqServices.indexOf(service) === -1) {
                    siloqServices.push(service);
                    renderServices();
                    $('#siloq_new_service').val('');
                }
            });
            
            $('#siloq_new_service').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#siloq-add-service').click();
                }
            });
            
            // Add area
            $('#siloq-add-area').on('click', function() {
                var area = $('#siloq_new_area').val().trim();
                if (area && siloqAreas.indexOf(area) === -1) {
                    siloqAreas.push(area);
                    renderAreas();
                    $('#siloq_new_area').val('');
                }
            });
            
            $('#siloq_new_area').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#siloq-add-area').click();
                }
            });
            
            // Remove tag
            $(document).on('click', '.siloq-tag-remove', function() {
                var index = $(this).data('index');
                var type = $(this).data('type');
                if (type === 'service') {
                    siloqServices.splice(index, 1);
                    renderServices();
                } else if (type === 'area') {
                    siloqAreas.splice(index, 1);
                    renderAreas();
                }
            });
            
            // Save profile
            $('#siloq-save-profile').on('click', function() {
                var $btn = $(this);
                var $status = $('#siloq-profile-status');
                
                $btn.prop('disabled', true).text('<?php _e('Saving...', 'siloq-connector'); ?>');
                $status.removeClass('success error').text('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'siloq_save_business_profile',
                        nonce: siloqAjax.nonce,
                        business_type: $('#siloq_business_type').val(),
                        primary_services: siloqServices,
                        service_areas: siloqAreas
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).text('<?php _e('Save Business Profile', 'siloq-connector'); ?>');
                        if (response.success) {
                            $status.addClass('success').html('<span class="dashicons dashicons-yes"></span> ' + response.data.message);
                        } else {
                            $status.addClass('error').text(response.data ? response.data.message : 'Failed to save');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('<?php _e('Save Business Profile', 'siloq-connector'); ?>');
                        $status.addClass('error').text('Connection error. Please try again.');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $api_url = isset($_POST['siloq_api_url']) ? sanitize_text_field($_POST['siloq_api_url']) : self::DEFAULT_API_URL;
        $api_key = isset($_POST['siloq_api_key']) ? sanitize_text_field($_POST['siloq_api_key']) : '';
        $auto_sync = isset($_POST['siloq_auto_sync']) ? 'yes' : 'no';
        $signup_url = isset($_POST['siloq_signup_url']) ? esc_url_raw($_POST['siloq_signup_url']) : '';
        $use_dummy_scan = isset($_POST['siloq_use_dummy_scan']) ? 'yes' : 'no';
        $show_advanced = isset($_POST['siloq_show_advanced']) ? sanitize_text_field($_POST['siloq_show_advanced']) : 'no';
        
        // Use default API URL if empty
        if (empty($api_url)) {
            $api_url = self::DEFAULT_API_URL;
        }
        
        // Validate
        $errors = array();
        if (empty($api_key)) {
            $errors[] = __('API Key is required. Get one from your Siloq dashboard.', 'siloq-connector');
        }
        
        if (!empty($errors)) {
            add_settings_error(
                'siloq_settings',
                'siloq_validation_error',
                implode('<br>', $errors),
                'error'
            );
            return;
        }
        
        // Save
        $old_api_url = get_option('siloq_api_url');
        $old_api_key = get_option('siloq_api_key');
        
        update_option('siloq_api_url', $api_url);
        update_option('siloq_api_key', $api_key);
        update_option('siloq_auto_sync', $auto_sync);
        update_option('siloq_signup_url', $signup_url);
        update_option('siloq_use_dummy_scan', $use_dummy_scan);
        update_option('siloq_show_advanced', $show_advanced);
        
        // Clear connection verification if credentials changed
        if ($old_api_url !== $api_url || $old_api_key !== $api_key) {
            delete_transient('siloq_connection_verified');
        }
        
        add_settings_error(
            'siloq_settings',
            'siloq_settings_saved',
            __('Settings saved successfully!', 'siloq-connector'),
            'success'
        );
    }
    
    /**
     * Render sync status page
     */
    public static function render_sync_status_page() {
        $sync_engine = new Siloq_Sync_Engine();
        $pages_status = $sync_engine->get_all_sync_status();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Sync Status', 'siloq-connector'); ?></h1>
            
            <div class="siloq-sync-status-container">
                <p>
                    <button type="button" id="siloq-refresh-status" class="button button-secondary">
                        <?php _e('Refresh', 'siloq-connector'); ?>
                    </button>
                    
                    <?php
                    $pages_needing_resync = $sync_engine->get_pages_needing_resync();
                    if (!empty($pages_needing_resync)) {
                        ?>
                        <button type="button" id="siloq-sync-outdated" class="button button-primary">
                            <?php printf(__('Sync %d Outdated Pages', 'siloq-connector'), count($pages_needing_resync)); ?>
                        </button>
                        <?php
                    }
                    ?>
                </p>
                
                <?php if (empty($pages_status)): ?>
                    <div class="notice notice-info">
                        <p><?php _e('No pages found. Create some pages first, then sync them to Siloq.', 'siloq-connector'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Page Title', 'siloq-connector'); ?></th>
                                <th><?php _e('Status', 'siloq-connector'); ?></th>
                                <th><?php _e('Last Synced', 'siloq-connector'); ?></th>
                                <th><?php _e('Actions', 'siloq-connector'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pages_status as $page): ?>
                                <?php
                                $needs_resync = $sync_engine->needs_resync($page['id']);
                                $status_class = '';
                                $status_text = '';
                                
                                switch ($page['sync_status']) {
                                    case 'synced':
                                        $status_class = $needs_resync ? 'warning' : 'success';
                                        $status_text = $needs_resync ? __('Needs Re-sync', 'siloq-connector') : __('Synced', 'siloq-connector');
                                        break;
                                    case 'error':
                                        $status_class = 'error';
                                        $status_text = __('Error', 'siloq-connector');
                                        break;
                                    default:
                                        $status_class = 'not-synced';
                                        $status_text = __('Not Synced', 'siloq-connector');
                                }
                                ?>
                                <tr data-page-id="<?php echo esc_attr($page['id']); ?>">
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url($page['edit_url']); ?>">
                                                <?php echo esc_html($page['title']); ?>
                                            </a>
                                        </strong>
                                        <br>
                                        <small>
                                            <a href="<?php echo esc_url($page['url']); ?>" target="_blank">
                                                <?php _e('View', 'siloq-connector'); ?>
                                            </a>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="siloq-status-badge siloq-status-<?php echo esc_attr($status_class); ?>">
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo esc_html($page['last_synced']); ?>
                                    </td>
                                    <td>
                                        <button 
                                            type="button" 
                                            class="button button-small siloq-sync-single" 
                                            data-page-id="<?php echo esc_attr($page['id']); ?>"
                                        >
                                            <?php _e('Sync Now', 'siloq-connector'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <style>
                .siloq-status-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: 500; }
                .siloq-status-success { background: #d4edda; color: #155724; }
                .siloq-status-warning { background: #fff3cd; color: #856404; }
                .siloq-status-error { background: #f8d7da; color: #721c24; }
                .siloq-status-not-synced { background: #e9ecef; color: #495057; }
            </style>
        </div>
        <?php
    }
    
    /**
     * Render content import page
     */
    public static function render_content_import_page() {
        $import_handler = new Siloq_Content_Import();
        
        // Get all pages with available jobs
        $pages = get_posts(array(
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft'),
            'meta_query' => array(
                array(
                    'key' => '_siloq_content_ready',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Content Import', 'siloq-connector'); ?></h1>
            
            <div class="siloq-content-import-container">
                <p class="description">
                    <?php _e('AI-generated content from Siloq is ready to be imported. Review and import content for your pages below.', 'siloq-connector'); ?>
                </p>
                
                <?php if (empty($pages)): ?>
                    <div class="notice notice-info">
                        <p><?php _e('No AI-generated content available yet. Generate content from your Siloq dashboard first.', 'siloq-connector'); ?></p>
                        <p>
                            <a href="<?php echo esc_url(self::DASHBOARD_URL . '/dashboard?tab=content'); ?>" target="_blank" class="button button-primary">
                                <?php _e('Go to Content Hub →', 'siloq-connector'); ?>
                            </a>
                        </p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Page Title', 'siloq-connector'); ?></th>
                                <th><?php _e('Content Ready', 'siloq-connector'); ?></th>
                                <th><?php _e('Actions', 'siloq-connector'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pages as $page): ?>
                                <?php
                                $jobs = $import_handler->get_available_jobs($page->ID);
                                $ready_at = get_post_meta($page->ID, '_siloq_content_ready_at', true);
                                $has_backup = !empty(get_post_meta($page->ID, '_siloq_backup_content', true));
                                ?>
                                <tr data-page-id="<?php echo esc_attr($page->ID); ?>">
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url(get_edit_post_link($page->ID)); ?>">
                                                <?php echo esc_html($page->post_title); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($ready_at) {
                                            echo esc_html(human_time_diff(strtotime($ready_at), current_time('timestamp'))) . ' ' . __('ago', 'siloq-connector');
                                        } else {
                                            _e('Recently', 'siloq-connector');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($jobs)): ?>
                                            <?php foreach ($jobs as $job): ?>
                                                <button 
                                                    type="button" 
                                                    class="button button-primary siloq-import-content" 
                                                    data-page-id="<?php echo esc_attr($page->ID); ?>"
                                                    data-job-id="<?php echo esc_attr($job['job_id']); ?>"
                                                    data-action="create_draft"
                                                >
                                                    <?php _e('Import as Draft', 'siloq-connector'); ?>
                                                </button>
                                                
                                                <button 
                                                    type="button" 
                                                    class="button button-secondary siloq-import-content" 
                                                    data-page-id="<?php echo esc_attr($page->ID); ?>"
                                                    data-job-id="<?php echo esc_attr($job['job_id']); ?>"
                                                    data-action="replace"
                                                >
                                                    <?php _e('Replace Content', 'siloq-connector'); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($has_backup): ?>
                                            <button 
                                                type="button" 
                                                class="button button-link-delete siloq-restore-backup" 
                                                data-page-id="<?php echo esc_attr($page->ID); ?>"
                                            >
                                                <?php _e('Restore Backup', 'siloq-connector'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
