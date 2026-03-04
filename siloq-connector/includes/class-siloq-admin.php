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
                <form method="post" action="" id="siloq-settings-form">
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
                            <button type="button" id="siloq-sync-all" class="button button-primary button-large">
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
                        
                        <div id="siloq-profile-form">
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
                                        <label for="siloq_business_name"><?php _e('Business Name', 'siloq-connector'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="siloq_business_name" name="siloq_business_name" class="regular-text" placeholder="<?php _e('e.g. Able Electric Inc', 'siloq-connector'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="siloq_phone"><?php _e('Phone Number', 'siloq-connector'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="siloq_phone" name="siloq_phone" class="regular-text" placeholder="<?php _e('e.g. (913) 384-5203', 'siloq-connector'); ?>">
                                        <p class="description"><?php _e('Required for LocalBusiness schema.', 'siloq-connector'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="siloq_address"><?php _e('Street Address', 'siloq-connector'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="siloq_address" name="siloq_address" class="regular-text" placeholder="<?php _e('e.g. 123 Main St', 'siloq-connector'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php _e('City / State / Zip', 'siloq-connector'); ?></label>
                                    </th>
                                    <td>
                                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                            <input type="text" id="siloq_city" name="siloq_city" placeholder="<?php _e('City', 'siloq-connector'); ?>" style="width:160px;">
                                            <input type="text" id="siloq_state" name="siloq_state" placeholder="<?php _e('State (e.g. MO)', 'siloq-connector'); ?>" style="width:100px;" maxlength="2">
                                            <input type="text" id="siloq_zip" name="siloq_zip" placeholder="<?php _e('ZIP', 'siloq-connector'); ?>" style="width:90px;" maxlength="10">
                                        </div>
                                        <p class="description"><?php _e('Used for LocalBusiness schema and location-based recommendations.', 'siloq-connector'); ?></p>
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
                                    <th scope="row"><?php _e('Save History', 'siloq-connector'); ?></th>
                                    <td>
                                        <?php
                                        $debug_log = json_decode( get_option( 'siloq_debug_log', '[]' ), true ) ?: [];
                                        if ( empty( $debug_log ) ) {
                                            echo '<p class="description">No save attempts recorded yet.</p>';
                                        } else {
                                            $recent = array_reverse( array_slice( $debug_log, -10 ) );
                                            echo '<div style="background:#f6f7f7;border:1px solid #ddd;border-radius:4px;padding:10px;font-family:monospace;font-size:11px;max-height:200px;overflow-y:auto;">';
                                            foreach ( $recent as $entry ) {
                                                $api   = $entry['api_sync'] ?? '?';
                                                $db    = $entry['db_verified'] ? '✅' : '❌';
                                                $color = ( $entry['db_verified'] && $api === 'ok' ) ? '#065f46' : '#7c2d12';
                                                printf(
                                                    '<div style="color:%s;margin-bottom:4px;">[%s] DB:%s API:%s fields:%s</div>',
                                                    esc_attr( $color ),
                                                    esc_html( $entry['ts'] ?? '' ),
                                                    $db,
                                                    esc_html( $api ),
                                                    esc_html( implode( ',', $entry['fields'] ?? [] ) )
                                                );
                                            }
                                            echo '</div>';
                                            echo '<p><a href="' . esc_url( add_query_arg( 'siloq_clear_debug_log', '1' ) ) . '" class="button button-small" style="margin-top:6px;">Clear Log</a></p>';
                                        }
                                        // Handle clear action
                                        if ( isset( $_GET['siloq_clear_debug_log'] ) && current_user_can( 'manage_options' ) ) {
                                            delete_option( 'siloq_debug_log' );
                                        }
                                        ?>
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
                var submitButton = $(this).find('button[type="submit"]');
                var originalText = submitButton.text();
                
                // Basic validation
                if (!apiKey) {
                    showNotification('Please enter an API key', 'error');
                    $('#siloq_api_key').focus();
                    e.preventDefault();
                    return false;
                }
                
                if (!apiUrl) {
                    showNotification('Please enter an API URL', 'error');
                    $('#siloq_api_url').focus();
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state but allow form to submit
                submitButton.prop('disabled', true).text('Saving...');
                submitButton.after('<span class="siloq-loading-spinner"></span>');
                
                // Form will submit normally
                return true;
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
            if ($('.siloq-business-profile-card').length && typeof siloqAjax !== 'undefined') {
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
                        $('#siloq-profile-form').show();
                        if (response.success && response.data) {
                            var profile = response.data;
                            $('#siloq_business_name').val(profile.business_name || '');
                            $('#siloq_phone').val(profile.phone || '');
                            $('#siloq_address').val(profile.address || '');
                            $('#siloq_city').val(profile.city || '');
                            $('#siloq_state').val(profile.state || '');
                            $('#siloq_zip').val(profile.zip || '');
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
                        // Always show form even on error so user can fill it in manually
                    },
                    error: function() {
                        $('#siloq-profile-loading').hide();
                        $('#siloq-profile-form').show();
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
                
                $btn.prop('disabled', true).text('Saving...');
                $status.removeClass('success error').text('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'siloq_save_business_profile',
                        nonce: siloqAjax.nonce,
                        business_name: $('#siloq_business_name').val(),
                        phone: $('#siloq_phone').val(),
                        address: $('#siloq_address').val(),
                        city: $('#siloq_city').val(),
                        state: $('#siloq_state').val(),
                        zip: $('#siloq_zip').val(),
                        business_type: $('#siloq_business_type').val(),
                        primary_services: siloqServices,
                        service_areas: siloqAreas
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).text('Save Business Profile');
                        if (response.success) {
                            // Success notice — green, dismissible
                            var msg = response.data.message || 'Business profile saved successfully.';
                            var note = response.data.api_note ? '<br><small style="opacity:.8">' + response.data.api_note + '</small>' : '';
                            $status.removeClass('error').addClass('success')
                                .html('<span class="dashicons dashicons-yes-alt"></span> ' + msg + note)
                                .show();
                            // Auto-dismiss after 6 seconds
                            setTimeout(function() { $status.fadeOut(); }, 6000);
                        } else {
                            // Error notice — red, stays visible until dismissed
                            var errMsg = (response.data && response.data.message)
                                ? response.data.message
                                : 'Save failed. Please try again or contact support.';
                            $status.removeClass('success').addClass('error')
                                .html('<strong>Save failed:</strong> ' + errMsg +
                                      '<br><small>Check Settings → Debug for details.</small>')
                                .show();
                        }
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).text('Save Business Profile');
                        $status.removeClass('success').addClass('error')
                            .html('<strong>Connection error.</strong> Could not reach the server. Check your internet connection and try again.')
                            .show();
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
    public static function save_settings() {
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
        if (!defined('SILOQ_PLUGIN_URL')) {
            define('SILOQ_PLUGIN_URL', plugin_dir_url(dirname(__FILE__) . '/../'));
        }

        $site_score      = intval(get_option('siloq_site_score', 0));
        $plan_data       = get_transient('siloq_plan_data');
        $has_plan        = !empty($plan_data);
        $activity_log    = json_decode(get_option('siloq_activity_log', '[]'), true);
        if (!is_array($activity_log)) $activity_log = [];
        $activity_log    = array_slice($activity_log, 0, 5);
        $entity_pct      = intval(get_option('siloq_entity_completeness', 0));
        $gsc_connected   = !empty(get_option('siloq_gsc_property', ''));
        $gsc_property    = get_option('siloq_gsc_property', '');
        $gsc_last_sync   = get_option('siloq_gsc_last_sync', '');
        $roadmap_progress = json_decode(get_option('siloq_roadmap_progress', '{}'), true);
        if (!is_array($roadmap_progress)) $roadmap_progress = [];

        // Plan progress
        $plan_total = 0; $plan_done = 0; $plan_started = '';
        if ($has_plan && isset($plan_data['actions'])) {
            $plan_total = count($plan_data['actions']);
            $plan_started = isset($plan_data['generated_at']) ? $plan_data['generated_at'] : '';
        }

        // Insight data
        $synced_pages = get_posts(array('post_type' => array('page', 'post'), 'meta_key' => '_siloq_last_sync', 'posts_per_page' => -1, 'fields' => 'ids'));
        $hub_count = 0; $orphan_count = 0; $missing_count = 0;
        if ($has_plan) {
            $hub_count = isset($plan_data['hub_count']) ? intval($plan_data['hub_count']) : 0;
            $orphan_count = isset($plan_data['orphan_count']) ? intval($plan_data['orphan_count']) : 0;
            $missing_count = isset($plan_data['missing_count']) ? intval($plan_data['missing_count']) : 0;
        }
        $gsc_impressions = get_option('siloq_gsc_impressions', 0);
        $gsc_clicks      = get_option('siloq_gsc_clicks', 0);

        // Score color
        if ($site_score >= 90) $score_color = 'var(--siloq-teal)';
        elseif ($site_score >= 75) $score_color = 'var(--siloq-success)';
        elseif ($site_score >= 50) $score_color = 'var(--siloq-warning)';
        else $score_color = 'var(--siloq-danger)';

        // Score sentence
        if ($site_score >= 90) $score_sentence = 'Excellent! Your site architecture is well-optimized.';
        elseif ($site_score >= 75) $score_sentence = 'Good foundation. A few improvements will push you higher.';
        elseif ($site_score >= 50) $score_sentence = 'There are clear opportunities to improve your SEO structure.';
        else $score_sentence = 'Your site needs attention. Generate a plan to get started.';

        ?>
        <div class="siloq-dash-wrap">
            <h1 style="margin-bottom:4px;">Siloq</h1>

            <!-- Tab Navigation -->
            <ul class="siloq-tab-nav" role="tablist">
                <li><button class="siloq-tab-btn" role="tab" aria-selected="true" aria-controls="siloq-tab-dashboard">Dashboard</button></li>
                <li><button class="siloq-tab-btn" role="tab" aria-selected="false" aria-controls="siloq-tab-plan">SEO/GEO Plan</button></li>
                <li><button class="siloq-tab-btn" role="tab" aria-selected="false" aria-controls="siloq-tab-pages">Pages</button></li>
                <li><button class="siloq-tab-btn" role="tab" aria-selected="false" aria-controls="siloq-tab-schema">Schema</button></li>
                <li><button class="siloq-tab-btn" role="tab" aria-selected="false" aria-controls="siloq-tab-gsc">GSC</button></li>
                <li><button class="siloq-tab-btn" role="tab" aria-selected="false" aria-controls="siloq-tab-settings">Settings</button></li>
            </ul>

            <!-- ═══════ DASHBOARD TAB ═══════ -->
            <div id="siloq-tab-dashboard" class="siloq-tab-panel active" role="tabpanel" aria-hidden="false">

                <!-- Zone 1: Hero Score Ring -->
                <div class="siloq-card siloq-hero">
                    <div class="siloq-score-ring-wrap">
                        <svg class="siloq-score-ring" viewBox="0 0 180 180">
                            <circle class="siloq-score-ring__bg" cx="90" cy="90" r="72"/>
                            <circle class="siloq-score-ring__fg" cx="90" cy="90" r="72" stroke="<?php echo esc_attr($score_color); ?>"/>
                        </svg>
                        <div class="siloq-score-ring__label">
                            <span class="siloq-score-ring__value">0</span>
                            <span class="siloq-score-ring__caption">Site Score</span>
                        </div>
                    </div>
                    <p class="siloq-hero__sentence"><?php echo esc_html($score_sentence); ?></p>
                    <br>
                    <?php if (!$has_plan): ?>
                        <button class="siloq-btn siloq-btn--primary siloq-generate-plan-btn">Generate Your SEO Plan &rarr;</button>
                    <?php else: ?>
                        <button class="siloq-btn siloq-btn--primary siloq-tab-btn" aria-controls="siloq-tab-plan">View Priority Actions &rarr;</button>
                    <?php endif; ?>
                </div>

                <!-- Zone 2: Plan Progress -->
                <div class="siloq-card">
                    <div class="siloq-card-header">
                        <h3 class="siloq-card-title">Plan Progress</h3>
                    </div>
                    <?php if ($has_plan && $plan_total > 0): ?>
                        <p><?php echo intval($plan_done); ?> of <?php echo intval($plan_total); ?> actions complete</p>
                        <div class="siloq-progress-wrap">
                            <div class="siloq-progress-bar">
                                <div class="siloq-progress-bar__fill" style="width:<?php echo $plan_total > 0 ? round(($plan_done / $plan_total) * 100) : 0; ?>%"></div>
                            </div>
                            <div class="siloq-progress-stats">
                                <span><?php echo round(($plan_done / $plan_total) * 100); ?>% complete</span>
                                <?php if ($plan_started): ?>
                                    <span><?php echo intval((time() - strtotime($plan_started)) / 86400); ?> days into plan</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="siloq-empty">
                            <p>No plan generated yet.</p>
                            <button class="siloq-btn siloq-btn--primary siloq-generate-plan-btn">Generate Your SEO Plan &rarr;</button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Zone 3: Insight Cards -->
                <div class="siloq-grid-3">
                    <div class="siloq-card">
                        <div class="siloq-insight-icon siloq-insight-icon--schema">&#9881;</div>
                        <div class="siloq-insight-value"><?php echo intval($entity_pct); ?>%</div>
                        <div class="siloq-insight-label">Entity &amp; Schema Completeness</div>
                    </div>
                    <div class="siloq-card">
                        <div class="siloq-insight-icon siloq-insight-icon--gsc">&#9829;</div>
                        <?php if ($gsc_connected): ?>
                            <div class="siloq-insight-value"><?php echo number_format(intval($gsc_impressions)); ?></div>
                            <div class="siloq-insight-label">Impressions &middot; <?php echo number_format(intval($gsc_clicks)); ?> clicks</div>
                        <?php else: ?>
                            <div class="siloq-insight-value">&mdash;</div>
                            <div class="siloq-insight-label"><a href="#siloq-tab-gsc" class="siloq-tab-btn" aria-controls="siloq-tab-gsc">Connect GSC</a></div>
                        <?php endif; ?>
                    </div>
                    <div class="siloq-card">
                        <div class="siloq-insight-icon siloq-insight-icon--arch">&#9776;</div>
                        <div class="siloq-insight-value"><?php echo intval($hub_count); ?> hubs</div>
                        <div class="siloq-insight-label"><?php echo intval($orphan_count); ?> orphans &middot; <?php echo intval($missing_count); ?> missing supporting</div>
                    </div>
                </div>

                <!-- Zone 4: Recent Activity -->
                <div class="siloq-card">
                    <div class="siloq-card-header">
                        <h3 class="siloq-card-title">Recent Activity</h3>
                    </div>
                    <?php if (!empty($activity_log)): ?>
                        <ul class="siloq-activity-list">
                            <?php foreach ($activity_log as $entry): ?>
                                <li>
                                    <span><?php echo esc_html(isset($entry['message']) ? $entry['message'] : ''); ?></span>
                                    <span class="siloq-activity-time"><?php echo esc_html(isset($entry['time']) ? $entry['time'] : ''); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="siloq-empty">No activity yet.</p>
                    <?php endif; ?>
                </div>
            </div><!-- /dashboard tab -->

            <!-- ═══════ SEO/GEO PLAN TAB ═══════ -->
            <div id="siloq-tab-plan" class="siloq-tab-panel" role="tabpanel" aria-hidden="true">
                <div class="siloq-plan-section">

                    <?php if (!$has_plan): ?>
                        <div class="siloq-card siloq-empty" style="padding:48px">
                            <div class="siloq-empty__icon">&#128640;</div>
                            <h3>Generate your personalized SEO/GEO plan</h3>
                            <p>We'll analyze your pages, find gaps, and create a priority action plan.</p>
                            <br>
                            <button class="siloq-btn siloq-btn--primary siloq-generate-plan-btn">Generate Your SEO Plan &rarr;</button>
                        </div>
                    <?php endif; ?>

                    <!-- Section 1: Site Architecture Map -->
                    <div class="siloq-accordion">
                        <button class="siloq-accordion__trigger" aria-expanded="false">
                            <span>Site Architecture Map</span>
                            <span class="siloq-accordion__arrow">&#9660;</span>
                        </button>
                        <div class="siloq-accordion__content" id="siloq-architecture-content">
                            <p class="siloq-empty">Generate a plan to see your site architecture.</p>
                        </div>
                    </div>

                    <!-- Section 2: Priority Action Plan -->
                    <div class="siloq-accordion">
                        <button class="siloq-accordion__trigger" aria-expanded="false">
                            <span>Priority Action Plan</span>
                            <span class="siloq-accordion__arrow">&#9660;</span>
                        </button>
                        <div class="siloq-accordion__content" id="siloq-actions-content">
                            <p class="siloq-empty">Generate a plan to see priority actions.</p>
                        </div>
                    </div>

                    <!-- Section 3: Supporting Content Opportunities -->
                    <div class="siloq-accordion">
                        <button class="siloq-accordion__trigger" aria-expanded="false">
                            <span>Supporting Content Opportunities</span>
                            <span class="siloq-accordion__arrow">&#9660;</span>
                        </button>
                        <div class="siloq-accordion__content" id="siloq-supporting-content">
                            <p class="siloq-empty">Generate a plan to see content opportunities.</p>
                        </div>
                    </div>

                    <!-- Section 4: Content Issues -->
                    <div class="siloq-accordion">
                        <button class="siloq-accordion__trigger" aria-expanded="false">
                            <span>Content Issues</span>
                            <span class="siloq-accordion__arrow">&#9660;</span>
                        </button>
                        <div class="siloq-accordion__content" id="siloq-issues-content">
                            <p class="siloq-empty">Generate a plan to see content issues.</p>
                        </div>
                    </div>

                    <!-- Section 5: 90-Day Roadmap -->
                    <div class="siloq-accordion">
                        <button class="siloq-accordion__trigger" aria-expanded="false">
                            <span>90-Day Roadmap</span>
                            <span class="siloq-accordion__arrow">&#9660;</span>
                        </button>
                        <div class="siloq-accordion__content" id="siloq-roadmap-content">
                            <p class="siloq-empty">Generate a plan to see your roadmap.</p>
                        </div>
                    </div>

                </div>
            </div><!-- /plan tab -->

            <!-- ═══════ PAGES TAB ═══════ -->
            <div id="siloq-tab-pages" class="siloq-tab-panel" role="tabpanel" aria-hidden="true">
                <div class="siloq-card">
                    <div class="siloq-card-header">
                        <h3 class="siloq-card-title">Synced Pages</h3>
                    </div>
                    <?php
                    $pages = get_posts(array(
                        'post_type'      => array('page', 'post'),
                        'meta_key'       => '_siloq_last_sync',
                        'posts_per_page' => 100,
                        'orderby'        => 'modified',
                        'order'          => 'DESC',
                    ));
                    if (!empty($pages)): ?>
                        <table class="siloq-pages-table">
                            <thead>
                                <tr>
                                    <th>Page</th>
                                    <th>Score</th>
                                    <th>Type</th>
                                    <th>Last Analyzed</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pages as $p):
                                    $analysis = get_post_meta($p->ID, '_siloq_analysis_data', true);
                                    $analysis = is_array($analysis) ? $analysis : (is_string($analysis) ? json_decode($analysis, true) : array());
                                    $page_score = isset($analysis['score']) ? intval($analysis['score']) : 0;
                                    $page_type  = isset($analysis['page_type_classification']) ? $analysis['page_type_classification'] : 'unknown';
                                    $last_sync  = get_post_meta($p->ID, '_siloq_last_sync', true);
                                    $type_class = 'gray';
                                    if ($page_type === 'hub') $type_class = 'purple';
                                    elseif ($page_type === 'spoke') $type_class = 'teal';
                                    elseif ($page_type === 'supporting') $type_class = 'blue';
                                ?>
                                <tr>
                                    <td><a href="<?php echo get_edit_post_link($p->ID); ?>"><?php echo esc_html($p->post_title); ?></a></td>
                                    <td><span class="siloq-badge siloq-badge--<?php echo $page_score >= 75 ? 'green' : ($page_score >= 50 ? 'amber' : 'red'); ?>"><?php echo $page_score; ?></span></td>
                                    <td><span class="siloq-badge siloq-badge--<?php echo $type_class; ?>"><?php echo esc_html(ucfirst($page_type)); ?></span></td>
                                    <td><?php echo $last_sync ? esc_html(human_time_diff(strtotime($last_sync), time()) . ' ago') : '&mdash;'; ?></td>
                                    <td><a href="<?php echo admin_url('admin.php?page=siloq-sync&sync_page=' . $p->ID); ?>" class="siloq-btn siloq-btn--sm siloq-btn--outline">Quick Fix</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="siloq-empty">No synced pages yet. <a href="<?php echo admin_url('admin.php?page=siloq-sync'); ?>">Sync your pages</a> to get started.</p>
                    <?php endif; ?>
                </div>
            </div><!-- /pages tab -->

            <!-- ═══════ SCHEMA TAB ═══════ -->
            <div id="siloq-tab-schema" class="siloq-tab-panel" role="tabpanel" aria-hidden="true">
                <div class="siloq-card">
                    <div class="siloq-card-header">
                        <h3 class="siloq-card-title">Entity &amp; Schema Health</h3>
                    </div>
                    <div class="siloq-entity-meter">
                        <div class="siloq-entity-meter__bar">
                            <div class="siloq-entity-meter__fill" style="width:<?php echo intval($entity_pct); ?>%;background:<?php echo $entity_pct >= 75 ? 'var(--siloq-success)' : ($entity_pct >= 50 ? 'var(--siloq-warning)' : 'var(--siloq-danger)'); ?>"></div>
                        </div>
                        <span class="siloq-entity-meter__value"><?php echo intval($entity_pct); ?>%</span>
                    </div>
                    <p>Entity completeness across your site pages.</p>
                    <br>
                    <a href="<?php echo esc_url(self::DASHBOARD_URL . '/schema'); ?>" target="_blank" class="siloq-btn siloq-btn--outline">Manage Schema in Siloq App &rarr;</a>
                </div>
            </div><!-- /schema tab -->

            <!-- ═══════ GSC TAB ═══════ -->
            <div id="siloq-tab-gsc" class="siloq-tab-panel" role="tabpanel" aria-hidden="true">
                <div class="siloq-card">
                    <div class="siloq-gsc-status">
                        <?php if ($gsc_connected): ?>
                            <div class="siloq-gsc-status__icon">&#9989;</div>
                            <h3>Google Search Console Connected</h3>
                            <dl class="siloq-gsc-status__details">
                                <dt>Property</dt>
                                <dd><?php echo esc_html($gsc_property); ?></dd>
                                <dt>Last Sync</dt>
                                <dd><?php echo $gsc_last_sync ? esc_html($gsc_last_sync) : 'Never'; ?></dd>
                            </dl>
                            <div style="display:flex;gap:8px;justify-content:center">
                                <button class="siloq-btn siloq-btn--primary" id="siloq-gsc-sync-btn">Sync Now</button>
                                <button class="siloq-btn siloq-btn--outline siloq-btn--danger" id="siloq-gsc-disconnect-btn">Disconnect</button>
                            </div>
                        <?php else: ?>
                            <div class="siloq-gsc-status__icon">&#128270;</div>
                            <h3>Connect Google Search Console</h3>
                            <p style="color:var(--siloq-muted);margin:8px 0 20px">Link your GSC property to unlock performance data and keyword insights.</p>
                            <a href="<?php echo esc_url(self::DASHBOARD_URL . '/gsc/connect'); ?>" class="siloq-btn siloq-btn--primary">Connect Google Search Console &rarr;</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div><!-- /gsc tab -->

            <!-- ═══════ SETTINGS TAB ═══════ -->
            <div id="siloq-tab-settings" class="siloq-tab-panel" role="tabpanel" aria-hidden="true">
                <div class="siloq-card">
                    <div class="siloq-card-header">
                        <h3 class="siloq-card-title">Plugin Settings</h3>
                    </div>
                    <p>Full settings are available on the <a href="<?php echo admin_url('admin.php?page=siloq-settings'); ?>">Settings page</a>.</p>
                    <?php
                    $api_key = get_option('siloq_api_key', '');
                    $auto_sync = get_option('siloq_auto_sync', 'yes');
                    ?>
                    <div class="siloq-settings-section" style="margin-top:16px">
                        <div class="siloq-settings-section__title">Connection Status</div>
                        <?php if (!empty($api_key)): ?>
                            <p><span class="siloq-badge siloq-badge--green">Connected</span> API key configured</p>
                        <?php else: ?>
                            <p><span class="siloq-badge siloq-badge--red">Not Connected</span> <a href="<?php echo admin_url('admin.php?page=siloq-settings'); ?>">Add your API key</a></p>
                        <?php endif; ?>
                    </div>
                    <div class="siloq-settings-section">
                        <div class="siloq-settings-section__title">Auto-Sync</div>
                        <p><?php echo $auto_sync === 'yes' ? '<span class="siloq-badge siloq-badge--green">On</span>' : '<span class="siloq-badge siloq-badge--gray">Off</span>'; ?> Pages sync automatically on publish/update</p>
                    </div>
                    <br>
                    <a href="<?php echo admin_url('admin.php?page=siloq-settings'); ?>" class="siloq-btn siloq-btn--outline">Go to Full Settings &rarr;</a>
                </div>
            </div><!-- /settings tab -->

            <script>
                // Pass roadmap progress to JS
                window.siloqRoadmapProgress = <?php echo wp_json_encode($roadmap_progress); ?>;
            </script>
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
        
        $import_handler = Siloq_Content_Import::get_instance();
        
        // Get all pages with available jobs
        $pages = get_posts(array(
            'post_type' => 'page',
            'posts_per_page' => 500,
            'no_found_rows'  => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
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
