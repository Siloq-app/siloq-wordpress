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
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Default API URL for production
     */
    const DEFAULT_API_URL = 'https://api.siloq.ai/api/v1';
    const DASHBOARD_URL = 'https://app.siloq.ai';
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Initialize admin hooks if needed
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        // Ensure plugin URL constant is defined
        if (!defined('SILOQ_PLUGIN_URL')) {
            define('SILOQ_PLUGIN_URL', plugin_dir_url(dirname(__FILE__) . '/../'));
        }
        
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

                                <tr>
                                    <th scope="row">
                                        <label for="siloq_debug_mode">
                                            <?php _e('Debug Mode', 'siloq-connector'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    name="siloq_debug_mode"
                                                    value="yes"
                                                    <?php checked(get_option('siloq_debug_mode', 'no'), 'yes'); ?>
                                                />
                                                <?php _e('Enable debug logging for troubleshooting', 'siloq-connector'); ?>
                                            </label>
                                            <p class="description">
                                                <?php _e('Logs will be saved to wp-content/debug.log. Only enable when requested by support.', 'siloq-connector'); ?>
                                            </p>
                                        </fieldset>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="siloq_sync_frequency">
                                            <?php _e('Auto-Sync Frequency', 'siloq-connector'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <select name="siloq_sync_frequency" id="siloq_sync_frequency">
                                            <option value="disabled" <?php selected(get_option('siloq_sync_frequency', 'disabled'), 'disabled'); ?>>
                                                <?php _e('Disabled', 'siloq-connector'); ?>
                                            </option>
                                            <option value="hourly" <?php selected(get_option('siloq_sync_frequency', 'disabled'), 'hourly'); ?>>
                                                <?php _e('Every Hour', 'siloq-connector'); ?>
                                            </option>
                                            <option value="twicedaily" <?php selected(get_option('siloq_sync_frequency', 'disabled'), 'twicedaily'); ?>>
                                                <?php _e('Twice Daily', 'siloq-connector'); ?>
                                            </option>
                                            <option value="daily" <?php selected(get_option('siloq_sync_frequency', 'disabled'), 'daily'); ?>>
                                                <?php _e('Daily', 'siloq-connector'); ?>
                                            </option>
                                        </select>
                                        <p class="description">
                                            <?php _e('Automatically sync content with Siloq on schedule.', 'siloq-connector'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="siloq_content_types">
                                            <?php _e('Content Types to Sync', 'siloq-connector'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <fieldset>
                                            <?php
                                            $post_types = get_post_types(['public' => true], 'objects');
                                            $enabled_types = get_option('siloq_content_types', ['page']);
                                            foreach ($post_types as $post_type) {
                                                if ($post_type->name === 'attachment') continue;
                                                ?>
                                                <label style="margin-right: 15px;">
                                                    <input
                                                        type="checkbox"
                                                        name="siloq_content_types[]"
                                                        value="<?php echo esc_attr($post_type->name); ?>"
                                                        <?php checked(in_array($post_type->name, $enabled_types)); ?>
                                                    />
                                                    <?php echo esc_html($post_type->labels->name); ?>
                                                </label>
                                                <?php
                                            }
                                            ?>
                                        </fieldset>
                                        <p class="description">
                                            <?php _e('Choose which content types should be synced with Siloq.', 'siloq-connector'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="siloq_api_timeout">
                                            <?php _e('API Timeout (seconds)', 'siloq-connector'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="number"
                                            name="siloq_api_timeout"
                                            id="siloq_api_timeout"
                                            value="<?php echo esc_attr(get_option('siloq_api_timeout', 30)); ?>"
                                            min="5"
                                            max="120"
                                            class="small-text"
                                        />
                                        <p class="description">
                                            <?php _e('How long to wait for API responses before timing out. Default: 30 seconds.', 'siloq-connector'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="siloq_cache_duration">
                                            <?php _e('Cache Duration (minutes)', 'siloq-connector'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="number"
                                            name="siloq_cache_duration"
                                            id="siloq_cache_duration"
                                            value="<?php echo esc_attr(get_option('siloq_cache_duration', 60)); ?>"
                                            min="0"
                                            max="1440"
                                            class="small-text"
                                        />
                                        <p class="description">
                                            <?php _e('How long to cache API responses. Set to 0 to disable caching. Default: 60 minutes.', 'siloq-connector'); ?>
                                        </p>
                                    </td>
                                </tr>

                                <?php // Lead gen settings hidden for V1 - uncomment for agency features ?>
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
        
        <?php settings_errors('siloq_settings'); ?>
        
        <script>
        jQuery(document).ready(function($) {
            // Enhanced API key visibility toggle with feedback
            $('#siloq-toggle-key').on('click', function() {
                var input = $('#siloq_api_key');
                var button = $(this);
                var icon = button.find('.dashicons');
                var type = input.attr('type') === 'password' ? 'text' : 'password';
                
                // Add visual feedback
                button.addClass('active');
                setTimeout(function() {
                    button.removeClass('active');
                }, 150);
                
                input.attr('type', type);
                icon.toggleClass('dashicons-visibility dashicons-hidden');
                
                // Update tooltip
                var title = type === 'password' ? 'Show API Key' : 'Hide API Key';
                button.attr('title', title);
                
                // Add focus state
                if (type === 'text') {
                    input.focus();
                }
            });
            
            // Enhanced advanced settings toggle with smooth animation
            $('.siloq-toggle-advanced').on('click', function() {
                var content = $('.siloq-advanced-content');
                var icon = $(this).find('.dashicons-arrow-down-alt2, .dashicons-arrow-up-alt2');
                var buttonText = $(this).find('span');
                var isExpanded = content.is(':visible');
                
                // Smooth toggle animation
                content.slideToggle(300, function() {
                    // Update icon and text after animation
                    icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
                    var newText = isExpanded ? 'Show Advanced Settings' : 'Hide Advanced Settings';
                    buttonText.text(newText);
                    $('#siloq_show_advanced').val(!isExpanded ? 'yes' : 'no');
                    
                    // Scroll to advanced section if expanding
                    if (!isExpanded) {
                        $('html, body').animate({
                            scrollTop: content.offset().top - 100
                        }, 300);
                    }
                });
                
                // Add active state
                $(this).toggleClass('active');
            });
            
            // Enhanced form validation and submission
            $('#siloq-settings-form').on('submit', function(e) {
                var apiKey = $('#siloq_api_key').val().trim();
                var apiUrl = $('#siloq_api_url').val().trim();
                var submitButton = $(this).find('input[type="submit"]');
                var originalText = submitButton.val();
                
                // Basic validation
                if (!apiKey) {
                    showNotification('Please enter an API key', 'error');
                    $('#siloq_api_key').focus();
                    return false;
                }
                
                if (!apiUrl) {
                    showNotification('Please enter an API URL', 'error');
                    $('#siloq_api_url').focus();
                    return false;
                }
                
                // Show loading state
                submitButton.prop('disabled', true).val('Saving...');
                submitButton.after('<span class="siloq-loading-spinner"></span>');
                
                // Simulate async save (replace with actual AJAX call)
                setTimeout(function() {
                    submitButton.prop('disabled', false).val(originalText);
                    $('.siloq-loading-spinner').remove();
                    showNotification('Settings saved successfully!', 'success');
                }, 1500);
                
                return false; // Prevent actual submission for demo
            });
            
            // Connection test functionality
            $('#siloq-test-connection').on('click', function() {
                var button = $(this);
                var originalText = button.text();
                var apiKey = $('#siloq_api_key').val().trim();
                var apiUrl = $('#siloq_api_url').val().trim();
                
                if (!apiKey || !apiUrl) {
                    showNotification('Please enter API key and URL first', 'warning');
                    return;
                }
                
                // Show loading state
                button.prop('disabled', true).text('Testing...');
                button.after('<span class="siloq-loading-spinner"></span>');
                
                // Simulate connection test
                setTimeout(function() {
                    button.prop('disabled', false).text(originalText);
                    $('.siloq-loading-spinner').remove();
                    
                    // Simulate success (replace with actual test)
                    if (apiKey.startsWith('sk_')) {
                        showNotification('Connection successful!', 'success');
                        updateConnectionStatus('connected');
                    } else {
                        showNotification('Invalid API key format', 'error');
                        updateConnectionStatus('error');
                    }
                }, 2000);
            });
            
            // Notification system
            function showNotification(message, type) {
                var notification = $('<div class="siloq-notification siloq-notification-' + type + '">' +
                    '<span class="siloq-notification-icon">' + getNotificationIcon(type) + '</span>' +
                    '<span class="siloq-notification-message">' + message + '</span>' +
                    '<button class="siloq-notification-close">&times;</button>' +
                    '</div>');
                
                // Add to page
                $('.siloq-admin-wrap').prepend(notification);
                
                // Auto-hide after 5 seconds
                setTimeout(function() {
                    notification.addClass('fade-out');
                    setTimeout(function() {
                        notification.remove();
                    }, 300);
                }, 5000);
                
                // Manual close
                notification.find('.siloq-notification-close').on('click', function() {
                    notification.addClass('fade-out');
                    setTimeout(function() {
                        notification.remove();
                    }, 300);
                });
            }
            
            function getNotificationIcon(type) {
                var icons = {
                    success: '✓',
                    error: '✕',
                    warning: '⚠',
                    info: 'ℹ'
                };
                return icons[type] || 'ℹ';
            }
            
            function updateConnectionStatus(status) {
                var banner = $('.siloq-connection-banner');
                banner.removeClass('connected warning error').addClass(status);
                
                var messages = {
                    connected: 'Connected to Siloq',
                    warning: 'API key configured — click "Test Connection" to verify',
                    error: 'Connection failed - check your API key and URL'
                };
                
                var icons = {
                    connected: 'dashicons-yes-alt',
                    warning: 'dashicons-warning',
                    error: 'dashicons-no-alt'
                };
                
                banner.find('.dashicons').attr('class', 'dashicons ' + icons[status]);
                banner.find('span:not(.dashicons)').text(messages[status]);
            }
            
            // Enhanced business profile functionality
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
                            updateProfileStatus(profile);
                        } else {
                            showNotification('Failed to load business profile', 'error');
                            $('#siloq-profile-error').show();
                        }
                    },
                    error: function() {
                        $('#siloq-profile-loading').hide();
                        showNotification('Network error loading profile', 'error');
                        $('#siloq-profile-error').show();
                    }
                });
            }
            
            function updateProfileStatus(profile) {
                var statusCard = $('.siloq-profile-status');
                var isComplete = profile.business_type && siloqServices.length > 0;
                
                if (isComplete) {
                    statusCard.html('<span class="siloq-profile-complete">' +
                        '<span class="dashicons dashicons-yes-alt"></span>' +
                        'Profile Complete' +
                        '</span>');
                } else {
                    statusCard.html('<span class="siloq-profile-incomplete">' +
                        '<span class="dashicons dashicons-warning"></span>' +
                        'Profile Incomplete' +
                        '</span>');
                }
            }
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
     * Render dashboard page
     */
    public static function render_dashboard_page() {
        // Ensure plugin URL constant is defined
        if (!defined('SILOQ_PLUGIN_URL')) {
            define('SILOQ_PLUGIN_URL', plugin_dir_url(dirname(__FILE__) . '/../'));
        }
        
        ?>
        <div class="wrap siloq-admin-wrap">
            <div class="siloq-header">
                <h1>
                    <img src="<?php echo esc_url(SILOQ_PLUGIN_URL . 'assets/siloq-logo.png'); ?>" alt="Siloq" class="siloq-logo" onerror="this.style.display='none'">
                    <?php _e('Siloq Dashboard', 'siloq-connector'); ?>
                </h1>
                <p class="siloq-tagline"><?php _e('SEO Content Management Dashboard — Monitor and manage your content optimization.', 'siloq-connector'); ?></p>
            </div>
            
            <div class="siloq-dashboard-container">
                <div class="siloq-card">
                    <h2><?php _e('Overview', 'siloq-connector'); ?></h2>
                    <p><?php _e('Welcome to the Siloq Dashboard. Here you can monitor your SEO performance and manage content optimization.', 'siloq-connector'); ?></p>
                    
                    <div class="siloq-stats-grid">
                        <div class="siloq-stat-card">
                            <h3><?php _e('Pages Synced', 'siloq-connector'); ?></h3>
                            <div class="siloq-stat-number">0</div>
                        </div>
                        <div class="siloq-stat-card">
                            <h3><?php _e('Content Generated', 'siloq-connector'); ?></h3>
                            <div class="siloq-stat-number">0</div>
                        </div>
                        <div class="siloq-stat-card">
                            <h3><?php _e('SEO Score', 'siloq-connector'); ?></h3>
                            <div class="siloq-stat-number">--</div>
                        </div>
                    </div>
                </div>
                
                <div class="siloq-card">
                    <h2><?php _e('Quick Actions', 'siloq-connector'); ?></h2>
                    <div class="siloq-actions-grid">
                        <a href="<?php echo admin_url('admin.php?page=siloq-sync'); ?>" class="siloq-action-card">
                            <span class="dashicons dashicons-update"></span>
                            <h3><?php _e('Sync Pages', 'siloq-connector'); ?></h3>
                            <p><?php _e('Sync your WordPress pages with Siloq platform', 'siloq-connector'); ?></p>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=siloq-content-import'); ?>" class="siloq-action-card">
                            <span class="dashicons dashicons-download"></span>
                            <h3><?php _e('Import Content', 'siloq-connector'); ?></h3>
                            <p><?php _e('Import AI-generated content from Siloq', 'siloq-connector'); ?></p>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=siloq-tali'); ?>" class="siloq-action-card">
                            <span class="dashicons dashicons-palette"></span>
                            <h3><?php _e('Theme Intelligence', 'siloq-connector'); ?></h3>
                            <p><?php _e('Configure theme-aware layout intelligence', 'siloq-connector'); ?></p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render sync page
     */
    public static function render_sync_page() {
        // Ensure plugin URL constant is defined
        if (!defined('SILOQ_PLUGIN_URL')) {
            define('SILOQ_PLUGIN_URL', plugin_dir_url(dirname(__FILE__) . '/../'));
        }
        
        ?>
        <div class="wrap siloq-admin-wrap">
            <div class="siloq-header">
                <h1>
                    <img src="<?php echo esc_url(SILOQ_PLUGIN_URL . 'assets/siloq-logo.png'); ?>" alt="Siloq" class="siloq-logo" onerror="this.style.display='none'">
                    <?php _e('Page Sync', 'siloq-connector'); ?>
                </h1>
                <p class="siloq-tagline"><?php _e('Content Synchronization — Sync your WordPress pages with the Siloq platform.', 'siloq-connector'); ?></p>
            </div>
            
            <div class="siloq-sync-container">
                <div class="siloq-card">
                    <h2><?php _e('Sync Status', 'siloq-connector'); ?></h2>
                    <p><?php _e('Monitor and manage the synchronization status of your pages.', 'siloq-connector'); ?></p>
                    
                    <div class="siloq-sync-actions">
                        <button type="button" id="siloq-sync-all" class="siloq-button siloq-button-primary">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Sync All Pages', 'siloq-connector'); ?>
                        </button>
                        <button type="button" id="siloq-sync-outdated" class="siloq-button siloq-button-secondary">
                            <span class="dashicons dashicons-clock"></span>
                            <?php _e('Sync Outdated Pages', 'siloq-connector'); ?>
                        </button>
                    </div>
                    
                    <div id="siloq-sync-status" class="siloq-sync-status">
                        <p><?php _e('Loading sync status...', 'siloq-connector'); ?></p>
                    </div>
                </div>
                
                <div class="siloq-card">
                    <h2><?php _e('Page List', 'siloq-connector'); ?></h2>
                    <div id="siloq-pages-list">
                        <p><?php _e('Loading pages...', 'siloq-connector'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render sync status page
     */
    public static function render_sync_status_page() {
        // Ensure plugin URL constant is defined
        if (!defined('SILOQ_PLUGIN_URL')) {
            define('SILOQ_PLUGIN_URL', plugin_dir_url(dirname(__FILE__) . '/../'));
        }
        
        $sync_engine = new Siloq_Sync_Engine();
        $pages_status = $sync_engine->get_all_sync_status();
        
        ?>
        <div class="wrap siloq-sync-status-container">
            <div class="siloq-header">
                <h1>
                    <img src="<?php echo esc_url(SILOQ_PLUGIN_URL . 'assets/siloq-logo.png'); ?>" alt="Siloq" class="siloq-logo" onerror="this.style.display='none'">
                    <?php _e('Sync Status', 'siloq-connector'); ?>
                </h1>
                <p class="siloq-tagline"><?php _e('Content Synchronization Monitor — Track and manage your WordPress content sync with Siloq platform.', 'siloq-connector'); ?></p>
            </div>
            
            <div class="siloq-sync-actions">
                <button type="button" id="siloq-refresh-status" class="button button-primary" aria-label="Refresh sync status">
                    <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                    <span><?php _e('Refresh Status', 'siloq-connector'); ?></span>
                </button>
                
                <?php
                $pages_needing_resync = $sync_engine->get_pages_needing_resync();
                if (!empty($pages_needing_resync)) {
                    ?>
                    <button type="button" id="siloq-sync-outdated" class="button button-primary" aria-label="Sync outdated pages">
                        <span class="dashicons dashicons-update" aria-hidden="true"></span>
                        <span><?php printf(__('Sync %d Outdated Pages', 'siloq-connector'), count($pages_needing_resync)); ?></span>
                    </button>
                    <?php
                }
                ?>
                <div class="siloq-loading-indicator" id="siloq-sync-loading" style="display: none;">
                    <span class="dashicons dashicons-spin dashicons-update"></span>
                    <span><?php _e('Updating sync status...', 'siloq-connector'); ?></span>
                </div>
                </div>
            
                <?php if (empty($pages_status)): ?>
                    <div class="siloq-empty-state">
                        <div class="siloq-empty-icon">
                            <span class="dashicons dashicons-database" style="font-size: 48px; color: var(--siloq-gray-400);"></span>
                        </div>
                        <h3><?php _e('No Pages Found', 'siloq-connector'); ?></h3>
                        <p><?php _e('Create some pages first, then sync them to Siloq to see their status here.', 'siloq-connector'); ?></p>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=page')); ?>" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php _e('Create First Page', 'siloq-connector'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="siloq-sync-table-wrapper">
                        <table class="widefat siloq-sync-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Page Title', 'siloq-connector'); ?></th>
                                    <th><?php _e('Sync Status', 'siloq-connector'); ?></th>
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
                                    $status_icon = '';
                                    
                                    switch ($page['sync_status']) {
                                        case 'synced':
                                            $status_class = $needs_resync ? 'warning' : 'success';
                                            $status_text = $needs_resync ? __('Needs Re-sync', 'siloq-connector') : __('Synced', 'siloq-connector');
                                            $status_icon = $needs_resync ? 'update' : 'yes-alt';
                                            break;
                                        case 'error':
                                            $status_class = 'error';
                                            $status_text = __('Error', 'siloq-connector');
                                            $status_icon = 'no-alt';
                                            break;
                                        default:
                                            $status_class = 'not-synced';
                                            $status_text = __('Not Synced', 'siloq-connector');
                                            $status_icon = 'minus';
                                    }
                                    ?>
                                    <tr data-page-id="<?php echo esc_attr($page['id']); ?>">
                                        <td>
                                            <div class="siloq-page-info">
                                                <strong>
                                                    <a href="<?php echo esc_url($page['edit_url']); ?>" class="siloq-page-title">
                                                        <?php echo esc_html($page['title']); ?>
                                                    </a>
                                                </strong>
                                                <div class="siloq-page-meta">
                                                    <a href="<?php echo esc_url($page['url']); ?>" target="_blank" class="siloq-view-link">
                                                        <?php _e('View Page', 'siloq-connector'); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="siloq-status-badge siloq-status-<?php echo esc_attr($status_class); ?>">
                                                <span class="dashicons dashicons-<?php echo esc_attr($status_icon); ?>" aria-hidden="true"></span>
                                                <span><?php echo esc_html($status_text); ?></span>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($page['last_synced']): ?>
                                                <span class="siloq-sync-time">
                                                    <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                                                    <?php echo esc_html(human_time_diff(strtotime($page['last_synced']), current_time('timestamp')) . ' ' . __('ago', 'siloq-connector')); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="siloq-sync-time siloq-never-synced">
                                                    <?php _e('Never', 'siloq-connector'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="siloq-sync-actions-cell">
                                                <button type="button" 
                                                    class="button button-primary siloq-sync-page-btn"
                                                    data-page-id="<?php echo esc_attr($page['id']); ?>"
                                                    aria-label="Sync page: <?php echo esc_attr($page['title']); ?>"
                                                >
                                                    <span><?php _e('Sync Now', 'siloq-connector'); ?></span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render content import page
     */
    public static function render_content_import_page() {
        // Ensure plugin URL constant is defined
        if (!defined('SILOQ_PLUGIN_URL')) {
            define('SILOQ_PLUGIN_URL', plugin_dir_url(dirname(__FILE__) . '/../'));
        }
        
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
        <div class="wrap siloq-content-import-container">
            <div class="siloq-header">
                <h1>
                    <img src="<?php echo esc_url(SILOQ_PLUGIN_URL . 'assets/siloq-logo.png'); ?>" alt="Siloq" class="siloq-logo" onerror="this.style.display='none'">
                    <?php _e('Content Import', 'siloq-connector'); ?>
                </h1>
                <p class="siloq-tagline"><?php _e('AI Content Integration — Import and manage AI-generated content from Siloq platform.', 'siloq-connector'); ?></p>
            </div>
            
            <div class="siloq-content-import-card">
                <h2><?php _e('Available AI Content', 'siloq-connector'); ?></h2>
                
                <div class="siloq-import-description">
                    <p><?php _e('AI-generated content from Siloq is ready to be imported. Review and import content for your pages below.', 'siloq-connector'); ?></p>
                    <a href="<?php echo esc_url(self::DASHBOARD_URL . '/dashboard?tab=content'); ?>" target="_blank" class="button button-secondary">
                        <?php _e('Go to Content Hub →', 'siloq-connector'); ?>
                    </a>
                </div>
                
                <?php if (empty($pages)): ?>
                    <div class="siloq-empty-state">
                        <div class="siloq-empty-icon">
                            <span class="dashicons dashicons-edit-page" style="font-size: 48px; color: var(--siloq-gray-400);"></span>
                        </div>
                        <h3><?php _e('No AI Content Available', 'siloq-connector'); ?></h3>
                        <p><?php _e('Generate content from your Siloq dashboard first, then return here to import it.', 'siloq-connector'); ?></p>
                        <a href="<?php echo esc_url(self::DASHBOARD_URL . '/dashboard?tab=content'); ?>" target="_blank" class="button button-primary">
                            <span class="dashicons dashicons-external"></span>
                            <?php _e('Go to Content Hub →', 'siloq-connector'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="siloq-import-table-wrapper">
                    <table class="widefat siloq-import-table">
                        <thead>
                            <tr>
                                <th><?php _e('Page Title', 'siloq-connector'); ?></th>
                                <th><?php _e('Content Ready', 'siloq-connector'); ?></th>
                                <th><?php _e('Available Actions', 'siloq-connector'); ?></th>
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
                                        <div class="siloq-page-info">
                                            <strong>
                                                <a href="<?php echo esc_url(get_edit_post_link($page->ID)); ?>" class="siloq-page-title">
                                                    <?php echo esc_html($page->post_title); ?>
                                                </a>
                                            </strong>
                                            <div class="siloq-page-meta">
                                                <?php if ($has_backup): ?>
                                                    <span class="siloq-backup-indicator">
                                                        <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                                                        <?php _e('Backup Available', 'siloq-connector'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="siloq-content-time">
                                            <?php 
                                            if ($ready_at) {
                                                echo '<span class="siloq-time-value">' . esc_html(human_time_diff(strtotime($ready_at), current_time('timestamp'))) . ' ' . __('ago', 'siloq-connector') . '</span>';
                                            } else {
                                                echo '<span class="siloq-time-value">' . __('Recently', 'siloq-connector') . '</span>';
                                            }
                                            ?>
                                            <div class="siloq-job-count">
                                                <?php printf(_n('%d job available', '%d jobs available', count($jobs), 'siloq-connector'), count($jobs)); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="siloq-action-buttons">
                                            <?php if (!empty($jobs)): ?>
                                                <?php foreach ($jobs as $job): ?>
                                                    <button 
                                                        type="button" 
                                                        class="button button-primary siloq-import-content" 
                                                        data-page-id="<?php echo esc_attr($page->ID); ?>"
                                                        data-job-id="<?php echo esc_attr($job['job_id']); ?>"
                                                        data-action="create_draft"
                                                        aria-label="Import as draft for: <?php echo esc_attr($page->post_title); ?>"
                                                    >
                                                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                                        <span><?php _e('Import as Draft', 'siloq-connector'); ?></span>
                                                    </button>
                                                    
                                                    <button 
                                                        type="button" 
                                                        class="button button-secondary siloq-import-content" 
                                                        data-page-id="<?php echo esc_attr($page->ID); ?>"
                                                        data-job-id="<?php echo esc_attr($job['job_id']); ?>"
                                                        data-action="preview"
                                                        aria-label="Preview content for: <?php echo esc_attr($page->post_title); ?>"
                                                    >
                                                        <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                                        <span><?php _e('Preview', 'siloq-connector'); ?></span>
                                                    </button>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="siloq-no-jobs">
                                                    <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                                                    <?php _e('Processing...', 'siloq-connector'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
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
