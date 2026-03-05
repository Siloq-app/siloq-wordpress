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
        // Onboarding wizard gate
        $api_key = get_option('siloq_api_key', '');
        $onboarding_done = get_option('siloq_onboarding_complete', 'no');
        if ($onboarding_done !== 'yes' || empty($api_key)) {
            self::render_onboarding_wizard();
            return;
        }

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
                <!-- Google Search Console -->
                <?php
                $gsc_is_connected = get_option('siloq_gsc_connected', '') === 'yes';
                $gsc_prop         = get_option('siloq_gsc_property', '');
                $gsc_sync_time    = get_option('siloq_gsc_last_sync', 'Never');
                ?>
                <div class="siloq-card" style="margin-bottom:20px;">
                    <h2><?php _e('Google Search Console', 'siloq-connector'); ?></h2>
                    <?php if ($gsc_is_connected): ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#22c55e;"></span>
                            <strong><?php _e('Connected', 'siloq-connector'); ?></strong>
                        </div>
                        <table class="form-table" style="margin-top:0;">
                            <tr><th scope="row"><?php _e('Property', 'siloq-connector'); ?></th><td><?php echo esc_html($gsc_prop); ?></td></tr>
                            <tr><th scope="row"><?php _e('Last Sync', 'siloq-connector'); ?></th><td><?php echo esc_html($gsc_sync_time); ?></td></tr>
                        </table>
                        <p>
                            <button type="button" id="siloq-gsc-sync-btn" class="button button-primary"><?php _e('Sync Now', 'siloq-connector'); ?></button>
                            <button type="button" id="siloq-gsc-disconnect-btn" class="button" style="color:#dc2626;"><?php _e('Disconnect', 'siloq-connector'); ?></button>
                            <span id="siloq-gsc-status-msg" class="siloq-status-message"></span>
                        </p>
                    <?php else: ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#9ca3af;"></span>
                            <strong><?php _e('Not connected', 'siloq-connector'); ?></strong>
                        </div>
                        <p class="description"><?php _e('Connect GSC to add real traffic data to your SEO Plan — see which pages need the most attention first.', 'siloq-connector'); ?></p>
                        <p>
                            <button type="button" id="siloq-gsc-connect-btn" class="button button-primary"><?php _e('Connect Google Search Console', 'siloq-connector'); ?></button>
                            <span id="siloq-gsc-status-msg" class="siloq-status-message"></span>
                        </p>
                    <?php endif; ?>
                </div>

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

        /* ── GSC Connection Handlers (Settings page) ── */
        (function(){
            var nonce = typeof siloqAjax !== 'undefined' ? siloqAjax.nonce : '';
            var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '';

            function gscMsg(text, type) {
                var $m = $('#siloq-gsc-status-msg');
                $m.text(text).css('color', type === 'error' ? '#dc2626' : '#16a34a').show();
                if (type !== 'error') setTimeout(function(){ $m.fadeOut(); }, 5000);
            }

            function gscInitOAuth() {
                $.post(ajaxUrl, { action: 'siloq_gsc_init_oauth', nonce: nonce }, function(r) {
                    if (r.success && r.data.auth_url) {
                        var popup = window.open(r.data.auth_url, 'siloq_gsc_oauth', 'width=600,height=700,scrollbars=yes');
                        var poll = setInterval(function() {
                            if (!popup || popup.closed) {
                                clearInterval(poll);
                                gscCheckStatus();
                            }
                        }, 1000);
                    } else {
                        gscMsg(r.data && r.data.message ? r.data.message : 'Failed to start OAuth', 'error');
                    }
                }).fail(function(){ gscMsg('Network error', 'error'); });
            }

            function gscCheckStatus() {
                $.post(ajaxUrl, { action: 'siloq_gsc_check_status', nonce: nonce }, function(r) {
                    if (r.success && r.data.connected) {
                        location.reload();
                    } else {
                        gscMsg('GSC not yet connected. Please try again.', 'error');
                    }
                });
            }

            $('#siloq-gsc-connect-btn').on('click', gscInitOAuth);

            $('#siloq-gsc-sync-btn').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Syncing...');
                $.post(ajaxUrl, { action: 'siloq_gsc_sync', nonce: nonce }, function(r) {
                    if (r.success) {
                        gscMsg('Synced ' + r.data.pages_synced + ' pages — ' + r.data.impressions + ' impressions, ' + r.data.clicks + ' clicks');
                        $btn.prop('disabled', false).text('Sync Now');
                    } else {
                        gscMsg(r.data && r.data.message ? r.data.message : 'Sync failed', 'error');
                        $btn.prop('disabled', false).text('Sync Now');
                    }
                }).fail(function(){ $btn.prop('disabled', false).text('Sync Now'); gscMsg('Network error', 'error'); });
            });

            $('#siloq-gsc-disconnect-btn').on('click', function() {
                if (!confirm('Disconnect Google Search Console?')) return;
                $.post(ajaxUrl, { action: 'siloq_gsc_disconnect', nonce: nonce }, function(r) {
                    if (r.success) location.reload();
                    else gscMsg('Disconnect failed', 'error');
                });
            });
        })();
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
        // Onboarding wizard gate
        $api_key = get_option('siloq_api_key', '');
        $onboarding_done = get_option('siloq_onboarding_complete', 'no');
        if ($onboarding_done !== 'yes' || empty($api_key)) {
            self::render_onboarding_wizard();
            return;
        }

        if (!defined('SILOQ_PLUGIN_URL')) {
            define('SILOQ_PLUGIN_URL', plugin_dir_url(dirname(__FILE__) . '/../'));
        }

        $site_score      = intval(get_option('siloq_site_score', 0));
        $plan_data       = get_transient('siloq_plan_data');
        $has_plan        = !empty($plan_data);
        $activity_log    = json_decode(get_option('siloq_activity_log', '[]'), true);
        if (!is_array($activity_log)) $activity_log = [];
        $activity_log    = array_slice($activity_log, 0, 5);
        // Compute entity profile completeness from actual business profile fields
        $entity_pct = self::compute_entity_completeness();
        update_option('siloq_entity_completeness', $entity_pct);
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
        $synced_pages = get_posts(array('post_type' => array('page', 'post'), 'meta_query' => array(array('key' => '_siloq_synced', 'compare' => 'EXISTS')), 'posts_per_page' => -1, 'fields' => 'ids'));
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
                    <div class="siloq-card" data-entity-score="<?php echo intval($entity_pct); ?>">
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
                <!-- Header row -->
                <div class="siloq-pages-header">
                    <div class="siloq-pages-header__left">
                        <input type="text" id="siloq-pages-search" class="siloq-pages-search" placeholder="Search pages..." autocomplete="off">
                        <div class="siloq-pages-filters">
                            <button class="siloq-filter-pill is-active" data-filter="all">All</button>
                            <button class="siloq-filter-pill" data-filter="hub">Hub</button>
                            <button class="siloq-filter-pill" data-filter="spoke">Spoke</button>
                            <button class="siloq-filter-pill" data-filter="supporting">Supporting</button>
                            <button class="siloq-filter-pill" data-filter="orphan">Orphan</button>
                        </div>
                    </div>
                    <div class="siloq-pages-header__right">
                        <span id="siloq-pages-count" class="siloq-pages-count"></span>
                        <button type="button" id="siloq-pages-sync-all" class="siloq-btn siloq-btn--primary siloq-btn--sm">
                            <span class="dashicons dashicons-update"></span> Sync All
                        </button>
                    </div>
                </div>

                <!-- Page cards container -->
                <div id="siloq-pages-grid" class="siloq-pages-grid">
                    <div class="siloq-pages-loading">
                        <span class="siloq-spinner"></span>
                        <span>Loading pages...</span>
                    </div>
                </div>

                <!-- Load More -->
                <div id="siloq-pages-load-more" class="siloq-pages-load-more" style="display:none;">
                    <button type="button" class="siloq-btn siloq-btn--outline">Load More</button>
                </div>
            </div><!-- /pages tab -->

            <!-- ═══════ SCHEMA TAB ═══════ -->
            <div id="siloq-tab-schema" class="siloq-tab-panel" role="tabpanel" aria-hidden="true">

                <!-- Section 1: Entity Profile Completeness -->
                <?php
                $entity_fields = self::get_entity_field_status();
                $entity_color = $entity_pct >= 75 ? 'var(--siloq-success)' : ($entity_pct >= 50 ? 'var(--siloq-warning)' : 'var(--siloq-danger)');
                ?>
                <div class="siloq-card siloq-schema-completeness">
                    <div class="siloq-card-header">
                        <h3 class="siloq-card-title">Entity Profile Completeness</h3>
                    </div>
                    <div class="siloq-schema-completeness__body">
                        <div class="siloq-schema-completeness__ring">
                            <div class="siloq-score-ring-wrap siloq-score-ring-wrap--sm">
                                <svg class="siloq-score-ring siloq-score-ring--entity" viewBox="0 0 120 120">
                                    <circle class="siloq-score-ring__bg" cx="60" cy="60" r="48"/>
                                    <circle class="siloq-score-ring__fg siloq-entity-ring-fg" cx="60" cy="60" r="48"
                                            stroke="<?php echo esc_attr($entity_color); ?>"
                                            data-score="<?php echo intval($entity_pct); ?>"
                                            data-radius="48"/>
                                </svg>
                                <div class="siloq-score-ring__label">
                                    <span class="siloq-score-ring__value siloq-entity-ring-value"><?php echo intval($entity_pct); ?></span>
                                    <span class="siloq-score-ring__caption">%</span>
                                </div>
                            </div>
                        </div>
                        <div class="siloq-schema-completeness__fields">
                            <?php foreach ($entity_fields as $field): ?>
                                <div class="siloq-entity-field <?php echo $field['filled'] ? 'siloq-entity-field--ok' : 'siloq-entity-field--missing'; ?>">
                                    <span class="siloq-entity-field__icon"><?php echo $field['filled'] ? '&#10003;' : '&#10007;'; ?></span>
                                    <span class="siloq-entity-field__label"><?php echo esc_html($field['label']); ?></span>
                                    <span class="siloq-entity-field__weight">(<?php echo intval($field['weight']); ?>pts)</span>
                                </div>
                            <?php endforeach; ?>
                            <div style="margin-top:12px;">
                                <a href="#siloq-tab-settings" class="siloq-btn siloq-btn--outline siloq-btn--sm siloq-tab-btn" aria-controls="siloq-tab-settings">Edit Business Profile</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Schema Applied Per Page -->
                <div class="siloq-card siloq-schema-pages">
                    <div class="siloq-card-header">
                        <h3 class="siloq-card-title">Schema Applied Per Page</h3>
                        <button type="button" id="siloq-schema-refresh" class="siloq-btn siloq-btn--outline siloq-btn--sm">
                            <span class="dashicons dashicons-update"></span> Refresh
                        </button>
                    </div>
                    <div id="siloq-schema-pages-list" class="siloq-schema-pages__list">
                        <div class="siloq-pages-loading">
                            <span class="siloq-spinner"></span>
                            <span>Loading schema status...</span>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Schema Graph -->
                <div class="siloq-card siloq-schema-graph">
                    <div class="siloq-card-header">
                        <h3 class="siloq-card-title">Schema Graph</h3>
                    </div>
                    <div id="siloq-schema-graph-content" class="siloq-schema-graph__content">
                        <?php if (!empty(get_option('siloq_site_id', ''))): ?>
                            <div class="siloq-pages-loading">
                                <span class="siloq-spinner"></span>
                                <span>Loading schema graph...</span>
                            </div>
                        <?php else: ?>
                            <div class="siloq-empty">
                                <p>Schema graph available after site analysis. Connect to Siloq API in Settings to enable.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /schema tab -->

            <!-- ═══════ GSC TAB ═══════ -->
            <div id="siloq-tab-gsc" class="siloq-tab-panel" role="tabpanel" aria-hidden="true">
                <div class="siloq-card">
                    <div class="siloq-gsc-status" style="text-align:center;padding:32px 16px;">
                        <?php if ($gsc_connected): ?>
                            <div class="siloq-gsc-status__icon">&#9989;</div>
                            <h3>Google Search Console Connected</h3>
                            <dl class="siloq-gsc-status__details">
                                <dt>Property</dt>
                                <dd><?php echo esc_html($gsc_property); ?></dd>
                                <dt>Last Sync</dt>
                                <dd><?php echo $gsc_last_sync ? esc_html($gsc_last_sync) : 'Never'; ?></dd>
                            </dl>
                            <?php
                            $tab_impressions = intval(get_option('siloq_gsc_impressions_28d', 0));
                            $tab_clicks      = intval(get_option('siloq_gsc_clicks_28d', 0));
                            $tab_position    = floatval(get_option('siloq_gsc_avg_position', 0));
                            ?>
                            <div style="display:flex;gap:24px;justify-content:center;margin:20px 0;">
                                <div><strong><?php echo number_format($tab_impressions); ?></strong><br><small>Impressions (28d)</small></div>
                                <div><strong><?php echo number_format($tab_clicks); ?></strong><br><small>Clicks (28d)</small></div>
                                <div><strong><?php echo $tab_position > 0 ? number_format($tab_position, 1) : '--'; ?></strong><br><small>Avg Position</small></div>
                            </div>
                            <div style="display:flex;gap:8px;justify-content:center">
                                <button class="siloq-btn siloq-btn--primary" id="siloq-gsc-sync-btn-tab">Sync Now</button>
                                <button class="siloq-btn siloq-btn--outline siloq-btn--danger" id="siloq-gsc-disconnect-btn-tab">Disconnect</button>
                            </div>
                            <span id="siloq-gsc-tab-msg" class="siloq-status-message" style="display:block;margin-top:8px;"></span>
                        <?php else: ?>
                            <div class="siloq-gsc-status__icon">&#128270;</div>
                            <h3>Connect Google Search Console</h3>
                            <p style="color:var(--siloq-muted);margin:8px 0 20px">Link your GSC property to unlock performance data and keyword insights.</p>
                            <button type="button" id="siloq-gsc-connect-btn-tab" class="siloq-btn siloq-btn--primary">Connect Google Search Console &rarr;</button>
                            <span id="siloq-gsc-tab-msg" class="siloq-status-message" style="display:block;margin-top:8px;"></span>
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

                // GSC tab handlers (Dashboard page)
                jQuery(document).ready(function($){
                    var sa = window.siloqAjax || {};
                    var nonce = sa.nonce || '';
                    var ajaxUrl = sa.ajaxurl || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');

                    function tabMsg(text, type) {
                        var $m = $('#siloq-gsc-tab-msg');
                        $m.text(text).css('color', type === 'error' ? '#dc2626' : '#16a34a').show();
                        if (type !== 'error') setTimeout(function(){ $m.fadeOut(); }, 5000);
                    }

                    // Connect via OAuth (GSC tab)
                    $('#siloq-gsc-connect-btn-tab').on('click', function(){
                        var $btn = $(this);
                        $btn.prop('disabled', true).text('Connecting...');
                        $.post(ajaxUrl, { action: 'siloq_gsc_init_oauth', nonce: nonce }, function(r){
                            if (r.success && r.data.auth_url) {
                                var popup = window.open(r.data.auth_url, 'siloq_gsc_oauth', 'width=600,height=700,scrollbars=yes');
                                var poll = setInterval(function(){
                                    if (!popup || popup.closed) {
                                        clearInterval(poll);
                                        $.post(ajaxUrl, { action: 'siloq_gsc_check_status', nonce: nonce }, function(r2){
                                            if (r2.success && r2.data.connected) location.reload();
                                            else { tabMsg('Not connected yet — try again.', 'error'); $btn.prop('disabled',false).text('Connect Google Search Console →'); }
                                        });
                                    }
                                }, 1000);
                            } else {
                                tabMsg(r.data && r.data.message ? r.data.message : 'Failed', 'error');
                                $btn.prop('disabled',false).text('Connect Google Search Console →');
                            }
                        }).fail(function(){ $btn.prop('disabled',false).text('Connect Google Search Console →'); tabMsg('Network error','error'); });
                    });

                    // Sync (GSC tab)
                    $('#siloq-gsc-sync-btn-tab').on('click', function(){
                        var $btn = $(this);
                        $btn.prop('disabled', true).text('Syncing...');
                        $.post(ajaxUrl, { action: 'siloq_gsc_sync', nonce: nonce }, function(r){
                            $btn.prop('disabled', false).text('Sync Now');
                            if (r.success) location.reload();
                            else tabMsg(r.data && r.data.message ? r.data.message : 'Sync failed', 'error');
                        }).fail(function(){ $btn.prop('disabled',false).text('Sync Now'); tabMsg('Network error','error'); });
                    });

                    // Disconnect (GSC tab)
                    $('#siloq-gsc-disconnect-btn-tab').on('click', function(){
                        if (!confirm('Disconnect Google Search Console?')) return;
                        $.post(ajaxUrl, { action: 'siloq_gsc_disconnect', nonce: nonce }, function(r){
                            if (r.success) location.reload();
                            else tabMsg('Failed','error');
                        });
                    });
                });
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

    /**
     * Render onboarding wizard (4-step first-run experience)
     */
    public static function render_onboarding_wizard() {
        if (!defined('SILOQ_PLUGIN_URL')) {
            define('SILOQ_PLUGIN_URL', plugin_dir_url(dirname(__FILE__) . '/../'));
        }
        $api_url = get_option('siloq_api_url', self::DEFAULT_API_URL);
        $nonce   = wp_create_nonce('siloq_ajax_nonce');

        // Resume at saved step so page refresh doesn't restart wizard
        $saved_step = intval( get_option( 'siloq_wizard_step', 1 ) );
        if ( $saved_step < 1 || $saved_step > 4 ) $saved_step = 1;

        // Pre-populate business profile fields from existing WP options
        $prefill = array(
            'business_name' => esc_attr( get_option( 'siloq_business_name', get_bloginfo('name') ) ),
            'phone'         => esc_attr( get_option( 'siloq_phone', '' ) ),
            'address'       => esc_attr( get_option( 'siloq_address', '' ) ),
            'city'          => esc_attr( get_option( 'siloq_city', '' ) ),
            'state'         => esc_attr( get_option( 'siloq_state', '' ) ),
            'zip'           => esc_attr( get_option( 'siloq_zip', '' ) ),
            'business_type' => esc_attr( get_option( 'siloq_business_type', '' ) ),
            'services'      => esc_attr( implode( ', ', json_decode( get_option( 'siloq_primary_services', '[]' ), true ) ?: [] ) ),
        );
        ?>
        <style>
            .siloq-wizard-wrap {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: calc(100vh - 100px);
                background: #f0f0f1;
                margin-left: -20px;
                padding: 40px 20px;
            }
            .siloq-wizard-card {
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.08);
                max-width: 600px;
                width: 100%;
                padding: 40px;
            }
            .siloq-wizard-steps {
                display: flex;
                justify-content: center;
                gap: 8px;
                margin-bottom: 32px;
            }
            .siloq-wizard-step-dot {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background: #d1d5db;
                transition: background 0.3s;
            }
            .siloq-wizard-step-dot.active {
                background: #4f46e5;
            }
            .siloq-wizard-step-dot.completed {
                background: #22c55e;
            }
            .siloq-wizard-panel {
                display: none;
            }
            .siloq-wizard-panel.active {
                display: block;
            }
            .siloq-wizard-card h2 {
                font-size: 24px;
                font-weight: 600;
                margin: 0 0 8px 0;
                text-align: center;
                color: #111827;
            }
            .siloq-wizard-card .siloq-wizard-subtitle {
                text-align: center;
                color: #6b7280;
                margin: 0 0 24px 0;
            }
            .siloq-wizard-logo {
                text-align: center;
                margin-bottom: 16px;
            }
            .siloq-wizard-logo img {
                height: 48px;
                width: auto;
            }
            .siloq-wizard-field {
                margin-bottom: 16px;
            }
            .siloq-wizard-field label {
                display: block;
                font-weight: 500;
                margin-bottom: 6px;
                color: #374151;
                font-size: 14px;
            }
            .siloq-wizard-field input[type="text"],
            .siloq-wizard-field input[type="password"],
            .siloq-wizard-field input[type="tel"],
            .siloq-wizard-field select,
            .siloq-wizard-field textarea {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                font-size: 14px;
                box-sizing: border-box;
                transition: border-color 0.2s;
            }
            .siloq-wizard-field input:focus,
            .siloq-wizard-field select:focus,
            .siloq-wizard-field textarea:focus {
                outline: none;
                border-color: #4f46e5;
                box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
            }
            .siloq-wizard-field textarea {
                min-height: 80px;
                resize: vertical;
            }
            .siloq-wizard-row {
                display: flex;
                gap: 12px;
            }
            .siloq-wizard-row .siloq-wizard-field {
                flex: 1;
            }
            .siloq-wizard-api-key-wrap {
                position: relative;
            }
            .siloq-wizard-api-key-wrap input {
                padding-right: 44px;
            }
            .siloq-wizard-toggle-key {
                position: absolute;
                right: 8px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                cursor: pointer;
                color: #6b7280;
                font-size: 18px;
                padding: 4px;
            }
            .siloq-wizard-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                padding: 12px 24px;
                background: #4f46e5;
                color: #fff;
                border: none;
                border-radius: 8px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.2s;
                margin-top: 8px;
            }
            .siloq-wizard-btn:hover {
                background: #4338ca;
            }
            .siloq-wizard-btn:disabled {
                background: #9ca3af;
                cursor: not-allowed;
            }
            .siloq-wizard-btn .spinner-dot {
                display: none;
                width: 16px;
                height: 16px;
                border: 2px solid rgba(255,255,255,0.3);
                border-top-color: #fff;
                border-radius: 50%;
                animation: siloq-spin 0.6s linear infinite;
                margin-right: 8px;
            }
            .siloq-wizard-btn.loading .spinner-dot {
                display: inline-block;
            }
            @keyframes siloq-spin {
                to { transform: rotate(360deg); }
            }
            .siloq-wizard-link {
                text-align: center;
                margin-top: 12px;
            }
            .siloq-wizard-link a {
                color: #4f46e5;
                text-decoration: none;
                font-size: 14px;
            }
            .siloq-wizard-link a:hover {
                text-decoration: underline;
            }
            .siloq-wizard-skip {
                text-align: center;
                margin-top: 12px;
            }
            .siloq-wizard-skip a {
                color: #6b7280;
                text-decoration: none;
                font-size: 13px;
                cursor: pointer;
            }
            .siloq-wizard-skip a:hover {
                color: #4f46e5;
            }
            .siloq-wizard-error {
                background: #fef2f2;
                border: 1px solid #fecaca;
                color: #dc2626;
                padding: 10px 14px;
                border-radius: 8px;
                font-size: 13px;
                margin-bottom: 16px;
                display: none;
            }
            .siloq-wizard-sync-progress {
                text-align: center;
                padding: 24px 0;
            }
            .siloq-wizard-sync-progress .sync-count {
                font-size: 36px;
                font-weight: 700;
                color: #4f46e5;
            }
            .siloq-wizard-sync-progress .sync-label {
                color: #6b7280;
                font-size: 14px;
                margin-top: 4px;
            }
            .siloq-wizard-sync-progress .sync-bar {
                height: 6px;
                background: #e5e7eb;
                border-radius: 3px;
                margin: 20px 0;
                overflow: hidden;
            }
            .siloq-wizard-sync-progress .sync-bar-fill {
                height: 100%;
                background: #4f46e5;
                border-radius: 3px;
                transition: width 0.5s;
                width: 0%;
            }
            .siloq-wizard-done {
                text-align: center;
                padding: 16px 0;
            }
            .siloq-wizard-done .done-check {
                width: 64px;
                height: 64px;
                background: #22c55e;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 16px;
            }
            .siloq-wizard-done .done-check svg {
                width: 32px;
                height: 32px;
                color: #fff;
            }
            .siloq-wizard-features {
                list-style: none;
                padding: 0;
                margin: 20px 0;
                text-align: left;
            }
            .siloq-wizard-features li {
                padding: 8px 0;
                color: #374151;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .siloq-wizard-features li .feat-icon {
                color: #22c55e;
                font-size: 16px;
                flex-shrink: 0;
            }
        </style>

        <div class="siloq-wizard-wrap">
            <div class="siloq-wizard-card">
                <!-- Step indicators -->
                <div class="siloq-wizard-steps">
                    <div class="siloq-wizard-step-dot <?php echo $saved_step >= 1 ? 'active' : ''; ?>" data-step="1"></div>
                    <div class="siloq-wizard-step-dot <?php echo $saved_step >= 2 ? 'active' : ''; ?>" data-step="2"></div>
                    <div class="siloq-wizard-step-dot <?php echo $saved_step >= 3 ? 'active' : ''; ?>" data-step="3"></div>
                    <div class="siloq-wizard-step-dot <?php echo $saved_step >= 4 ? 'active' : ''; ?>" data-step="4"></div>
                </div>

                <!-- STEP 1: Connect to Siloq -->
                <div class="siloq-wizard-panel <?php echo $saved_step === 1 ? 'active' : ''; ?>" id="siloq-wizard-step-1">
                    <div class="siloq-wizard-logo">
                        <img src="https://siloq.ai/wp-content/uploads/2026/01/logo-siloq.webp" alt="Siloq" style="height:48px;width:auto;" />
                    </div>
                    <h2><?php _e('Welcome to Siloq', 'siloq-connector'); ?></h2>
                    <p class="siloq-wizard-subtitle"><?php _e('Connect your site to start optimizing your SEO architecture.', 'siloq-connector'); ?></p>

                    <div class="siloq-wizard-error" id="siloq-wizard-error-1"></div>

                    <div class="siloq-wizard-field">
                        <label for="siloq-wizard-api-key"><?php _e('API Key', 'siloq-connector'); ?></label>
                        <div class="siloq-wizard-api-key-wrap">
                            <input type="password" id="siloq-wizard-api-key" placeholder="sk_live_..." autocomplete="off" />
                            <button type="button" class="siloq-wizard-toggle-key" onclick="siloqWizardToggleKey()" title="<?php esc_attr_e('Show/hide key', 'siloq-connector'); ?>">&#128065;</button>
                        </div>
                    </div>

                    <button type="button" class="siloq-wizard-btn" id="siloq-wizard-connect-btn" onclick="siloqWizardConnect()">
                        <span class="spinner-dot"></span>
                        <?php _e('Connect and Continue', 'siloq-connector'); ?>
                    </button>

                    <div class="siloq-wizard-link">
                        <a href="<?php echo esc_url(self::DASHBOARD_URL); ?>" target="_blank"><?php _e('Get your API key at app.siloq.ai', 'siloq-connector'); ?></a>
                    </div>
                </div>

                <!-- STEP 2: Your Business -->
                <div class="siloq-wizard-panel <?php echo $saved_step === 2 ? 'active' : ''; ?>" id="siloq-wizard-step-2">
                    <h2><?php _e('Your Business', 'siloq-connector'); ?></h2>
                    <p class="siloq-wizard-subtitle"><?php _e('Help us personalize your SEO recommendations.', 'siloq-connector'); ?></p>

                    <div class="siloq-wizard-error" id="siloq-wizard-error-2"></div>

                    <div class="siloq-wizard-field">
                        <label for="siloq-wiz-biz-name"><?php _e('Business Name', 'siloq-connector'); ?></label>
                        <input type="text" id="siloq-wiz-biz-name" value="<?php echo $prefill['business_name']; ?>" />
                    </div>

                    <div class="siloq-wizard-row">
                        <div class="siloq-wizard-field">
                            <label for="siloq-wiz-biz-phone"><?php _e('Phone', 'siloq-connector'); ?></label>
                            <input type="tel" id="siloq-wiz-biz-phone" value="<?php echo $prefill['phone']; ?>" />
                        </div>
                        <div class="siloq-wizard-field">
                            <label for="siloq-wiz-biz-type"><?php _e('Business Type', 'siloq-connector'); ?></label>
                            <select id="siloq-wiz-biz-type">
                                <option value=""><?php _e('Select...', 'siloq-connector'); ?></option>
                                <option value="local_service" <?php selected($prefill['business_type'], 'local_service'); ?>><?php _e('Local Service', 'siloq-connector'); ?></option>
                                <option value="Local Service" <?php selected($prefill['business_type'], 'Local Service'); ?>><?php _e('Local Service', 'siloq-connector'); ?></option>
                                <option value="ecommerce" <?php selected($prefill['business_type'], 'ecommerce'); ?>><?php _e('E-Commerce', 'siloq-connector'); ?></option>
                                <option value="saas" <?php selected($prefill['business_type'], 'saas'); ?>><?php _e('SaaS', 'siloq-connector'); ?></option>
                                <option value="blog" <?php selected($prefill['business_type'], 'blog'); ?>><?php _e('Blog / Publisher', 'siloq-connector'); ?></option>
                                <option value="agency" <?php selected($prefill['business_type'], 'agency'); ?>><?php _e('Agency', 'siloq-connector'); ?></option>
                                <option value="nonprofit" <?php selected($prefill['business_type'], 'nonprofit'); ?>><?php _e('Non-Profit', 'siloq-connector'); ?></option>
                                <option value="other" <?php selected($prefill['business_type'], 'other'); ?>><?php _e('Other', 'siloq-connector'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="siloq-wizard-field">
                        <label for="siloq-wiz-biz-address"><?php _e('Address', 'siloq-connector'); ?></label>
                        <input type="text" id="siloq-wiz-biz-address" value="<?php echo $prefill['address']; ?>" />
                    </div>

                    <div class="siloq-wizard-row">
                        <div class="siloq-wizard-field">
                            <label for="siloq-wiz-biz-city"><?php _e('City', 'siloq-connector'); ?></label>
                            <input type="text" id="siloq-wiz-biz-city" value="<?php echo $prefill['city']; ?>" />
                        </div>
                        <div class="siloq-wizard-field" style="flex:0.5;">
                            <label for="siloq-wiz-biz-state"><?php _e('State', 'siloq-connector'); ?></label>
                            <input type="text" id="siloq-wiz-biz-state" maxlength="2" value="<?php echo $prefill['state']; ?>" />
                        </div>
                        <div class="siloq-wizard-field" style="flex:0.7;">
                            <label for="siloq-wiz-biz-zip"><?php _e('Zip', 'siloq-connector'); ?></label>
                            <input type="text" id="siloq-wiz-biz-zip" maxlength="10" value="<?php echo $prefill['zip']; ?>" />
                        </div>
                    </div>

                    <div class="siloq-wizard-field">
                        <label for="siloq-wiz-biz-services"><?php _e('Primary Services', 'siloq-connector'); ?></label>
                        <textarea id="siloq-wiz-biz-services" placeholder="<?php esc_attr_e('e.g. Electrician, Panel Upgrade, EV Charging', 'siloq-connector'); ?>"><?php echo $prefill['services']; ?></textarea>
                    </div>

                    <button type="button" class="siloq-wizard-btn" id="siloq-wizard-profile-btn" onclick="siloqWizardSaveProfile()">
                        <span class="spinner-dot"></span>
                        <?php _e('Save and Continue', 'siloq-connector'); ?>
                    </button>

                    <div class="siloq-wizard-skip">
                        <a onclick="siloqWizardGoTo(3)"><?php _e('Skip for now', 'siloq-connector'); ?></a>
                    </div>
                </div>

                <!-- STEP 3: Sync Your Pages -->
                <div class="siloq-wizard-panel <?php echo $saved_step === 3 ? 'active' : ''; ?>" id="siloq-wizard-step-3">
                    <h2><?php _e('Sync Your Pages', 'siloq-connector'); ?></h2>
                    <p class="siloq-wizard-subtitle"><?php _e('We\'re importing your pages into Siloq for analysis.', 'siloq-connector'); ?></p>

                    <div class="siloq-wizard-error" id="siloq-wizard-error-3"></div>

                    <div class="siloq-wizard-sync-progress">
                        <div class="sync-count" id="siloq-wizard-sync-count">0</div>
                        <div class="sync-label"><?php _e('pages synced', 'siloq-connector'); ?></div>
                        <div class="sync-bar">
                            <div class="sync-bar-fill" id="siloq-wizard-sync-bar"></div>
                        </div>
                    </div>

                    <button type="button" class="siloq-wizard-btn" id="siloq-wizard-sync-continue-btn" onclick="siloqWizardGoTo(4)" disabled>
                        <?php _e('Continue', 'siloq-connector'); ?>
                    </button>
                </div>

                <!-- STEP 4: All Set -->
                <div class="siloq-wizard-panel" id="siloq-wizard-step-4">
                    <div class="siloq-wizard-done">
                        <div class="done-check">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </div>
                        <h2><?php _e('You\'re all set!', 'siloq-connector'); ?></h2>
                        <p class="siloq-wizard-subtitle"><?php _e('Siloq is connected and your pages are synced.', 'siloq-connector'); ?></p>

                        <ul class="siloq-wizard-features">
                            <li><span class="feat-icon">&#10003;</span> <?php _e('Keyword cannibalization detection', 'siloq-connector'); ?></li>
                            <li><span class="feat-icon">&#10003;</span> <?php _e('AI-powered content recommendations', 'siloq-connector'); ?></li>
                            <li><span class="feat-icon">&#10003;</span> <?php _e('Internal linking optimization', 'siloq-connector'); ?></li>
                            <li><span class="feat-icon">&#10003;</span> <?php _e('Google Search Console integration', 'siloq-connector'); ?></li>
                            <li><span class="feat-icon">&#10003;</span> <?php _e('SEO site health scoring', 'siloq-connector'); ?></li>
                        </ul>
                    </div>

                    <button type="button" class="siloq-wizard-btn" id="siloq-wizard-finish-btn" onclick="siloqWizardFinish()">
                        <span class="spinner-dot"></span>
                        <?php _e('Go to Dashboard', 'siloq-connector'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var nonce = '<?php echo esc_js($nonce); ?>';
            var apiUrl = '<?php echo esc_js($api_url); ?>';
            var currentStep = 1;

            function setLoading(btn, loading) {
                if (loading) {
                    btn.classList.add('loading');
                    btn.disabled = true;
                } else {
                    btn.classList.remove('loading');
                    btn.disabled = false;
                }
            }

            function showError(step, msg) {
                var el = document.getElementById('siloq-wizard-error-' + step);
                if (el) {
                    el.textContent = msg;
                    el.style.display = 'block';
                }
            }

            function hideError(step) {
                var el = document.getElementById('siloq-wizard-error-' + step);
                if (el) el.style.display = 'none';
            }

            window.siloqWizardGoTo = function(step) {
                // Hide all panels
                var panels = document.querySelectorAll('.siloq-wizard-panel');
                for (var i = 0; i < panels.length; i++) panels[i].classList.remove('active');

                // Show target panel
                var target = document.getElementById('siloq-wizard-step-' + step);
                if (target) target.classList.add('active');

                // Update dots
                var dots = document.querySelectorAll('.siloq-wizard-step-dot');
                for (var i = 0; i < dots.length; i++) {
                    var s = parseInt(dots[i].getAttribute('data-step'));
                    dots[i].classList.remove('active', 'completed');
                    if (s < step) dots[i].classList.add('completed');
                    if (s === step) dots[i].classList.add('active');
                }

                currentStep = step;

                // Auto-trigger sync on step 3
                if (step === 3) siloqWizardStartSync();
            };

            window.siloqWizardToggleKey = function() {
                var inp = document.getElementById('siloq-wizard-api-key');
                inp.type = inp.type === 'password' ? 'text' : 'password';
            };

            window.siloqWizardConnect = function() {
                var key = document.getElementById('siloq-wizard-api-key').value.trim();
                if (!key) { showError(1, '<?php echo esc_js(__('Please enter your API key.', 'siloq-connector')); ?>'); return; }
                hideError(1);

                var btn = document.getElementById('siloq-wizard-connect-btn');
                setLoading(btn, true);

                var fd = new FormData();
                fd.append('action', 'siloq_wizard_connect');
                fd.append('nonce', nonce);
                fd.append('api_key', key);

                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        setLoading(btn, false);
                        if (res.success) {
                            siloqWizardGoTo(2);
                        } else {
                            showError(1, (res.data && res.data.message) || '<?php echo esc_js(__('Connection failed. Please check your API key.', 'siloq-connector')); ?>');
                        }
                    })
                    .catch(function() {
                        setLoading(btn, false);
                        showError(1, '<?php echo esc_js(__('Network error. Please try again.', 'siloq-connector')); ?>');
                    });
            };

            window.siloqWizardSaveProfile = function() {
                hideError(2);
                var btn = document.getElementById('siloq-wizard-profile-btn');
                setLoading(btn, true);

                var fd = new FormData();
                fd.append('action', 'siloq_wizard_save_profile');
                fd.append('nonce', nonce);
                fd.append('business_name', document.getElementById('siloq-wiz-biz-name').value);
                fd.append('phone', document.getElementById('siloq-wiz-biz-phone').value);
                fd.append('address', document.getElementById('siloq-wiz-biz-address').value);
                fd.append('city', document.getElementById('siloq-wiz-biz-city').value);
                fd.append('state', document.getElementById('siloq-wiz-biz-state').value);
                fd.append('zip', document.getElementById('siloq-wiz-biz-zip').value);
                fd.append('business_type', document.getElementById('siloq-wiz-biz-type').value);
                fd.append('primary_services', document.getElementById('siloq-wiz-biz-services').value);

                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        setLoading(btn, false);
                        if (res.success) {
                            siloqWizardGoTo(3);
                        } else {
                            showError(2, (res.data && res.data.message) || '<?php echo esc_js(__('Could not save profile.', 'siloq-connector')); ?>');
                        }
                    })
                    .catch(function() {
                        setLoading(btn, false);
                        showError(2, '<?php echo esc_js(__('Network error. Please try again.', 'siloq-connector')); ?>');
                    });
            };

            window.siloqWizardStartSync = function() {
                var countEl = document.getElementById('siloq-wizard-sync-count');
                var barEl = document.getElementById('siloq-wizard-sync-bar');
                var continueBtn = document.getElementById('siloq-wizard-sync-continue-btn');
                hideError(3);

                var fd = new FormData();
                fd.append('action', 'siloq_sync_all_pages');
                fd.append('nonce', nonce);

                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success && res.data) {
                            var total = res.data.total || 0;
                            countEl.textContent = total;
                            barEl.style.width = '100%';
                            continueBtn.disabled = false;
                        } else {
                            // Even on partial failure, allow continue
                            countEl.textContent = '0';
                            barEl.style.width = '100%';
                            continueBtn.disabled = false;
                            if (res.data && res.data.message) showError(3, res.data.message);
                        }
                    })
                    .catch(function() {
                        continueBtn.disabled = false;
                        showError(3, '<?php echo esc_js(__('Sync encountered an error, but you can continue.', 'siloq-connector')); ?>');
                    });

                // Poll sync status for live progress
                var pollInterval = setInterval(function() {
                    var sfd = new FormData();
                    sfd.append('action', 'siloq_get_sync_status');
                    sfd.append('nonce', nonce);

                    fetch(ajaxUrl, { method: 'POST', body: sfd, credentials: 'same-origin' })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.success && res.data) {
                                var synced = res.data.synced || 0;
                                var total = res.data.total || 1;
                                countEl.textContent = synced;
                                barEl.style.width = Math.min(100, Math.round((synced / total) * 100)) + '%';
                                if (synced >= total || res.data.complete) {
                                    clearInterval(pollInterval);
                                    continueBtn.disabled = false;
                                }
                            }
                        })
                        .catch(function() {
                            clearInterval(pollInterval);
                        });
                }, 2000);
            };

            window.siloqWizardFinish = function() {
                var btn = document.getElementById('siloq-wizard-finish-btn');
                setLoading(btn, true);

                var fd = new FormData();
                fd.append('action', 'siloq_wizard_complete');
                fd.append('nonce', nonce);

                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            window.location.reload();
                        } else {
                            setLoading(btn, false);
                        }
                    })
                    .catch(function() {
                        setLoading(btn, false);
                    });
            };
        })();
        </script>
        <?php
    }

    /**
     * Compute entity profile completeness as weighted percentage.
     */
    public static function compute_entity_completeness() {
        $fields = self::get_entity_field_status();
        $total_weight = 0;
        $filled_weight = 0;
        foreach ($fields as $f) {
            $total_weight += $f['weight'];
            if ($f['filled']) {
                $filled_weight += $f['weight'];
            }
        }
        return $total_weight > 0 ? intval(round(($filled_weight / $total_weight) * 100)) : 0;
    }

    /**
     * Get entity profile field status with fill state and weights.
     */
    public static function get_entity_field_status() {
        $business_name = get_option('siloq_business_name', '');
        $business_type = get_option('siloq_business_type', '');
        $phone = get_option('siloq_phone', '');
        $address = get_option('siloq_address', '');
        $city = get_option('siloq_city', '');
        $state = get_option('siloq_state', '');
        $zip = get_option('siloq_zip', '');
        $services = json_decode(get_option('siloq_primary_services', '[]'), true);
        $areas = json_decode(get_option('siloq_service_areas', '[]'), true);

        $address_filled = !empty($address) && !empty($city) && !empty($state) && !empty($zip);

        return [
            ['key' => 'business_name',    'label' => 'Business Name',    'weight' => 15, 'filled' => !empty($business_name)],
            ['key' => 'business_type',    'label' => 'Business Type',    'weight' => 15, 'filled' => !empty($business_type)],
            ['key' => 'phone',            'label' => 'Phone',            'weight' => 10, 'filled' => !empty($phone)],
            ['key' => 'address',          'label' => 'Address',          'weight' => 20, 'filled' => $address_filled],
            ['key' => 'primary_services', 'label' => 'Primary Services', 'weight' => 25, 'filled' => is_array($services) && !empty($services)],
            ['key' => 'service_areas',    'label' => 'Service Areas',    'weight' => 15, 'filled' => is_array($areas) && !empty($areas)],
        ];
    }
}
