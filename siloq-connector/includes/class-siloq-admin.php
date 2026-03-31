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

    // =========================================================================
    // Fix 1: SEO title with AIOSEO priority chain
    // =========================================================================

    public static function siloq_get_page_title( $post_id ) {
        global $wpdb;

        // 1. AIOSEO table
        $table = $wpdb->prefix . 'aioseo_posts';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table ) {
            $aioseo_title = $wpdb->get_var( $wpdb->prepare(
                "SELECT title FROM {$table} WHERE post_id = %d LIMIT 1", $post_id
            ) );
            if ( ! empty( $aioseo_title ) ) {
                $post_title = get_the_title( $post_id );
                // Strip AIOSEO tokens
                $aioseo_title = str_replace( '%%separator_sa%%', '', $aioseo_title );
                // Substitute %%post_title%% with actual post_title
                if ( strpos( $aioseo_title, '%%post_title%%' ) !== false ) {
                    $aioseo_title = str_replace( '%%post_title%%', $post_title, $aioseo_title );
                }
                $aioseo_title = trim( $aioseo_title, ' -–—|' );
                if ( ! empty( $aioseo_title ) ) {
                    return $aioseo_title;
                }
            }
        }

        // 2. Yoast
        $yoast_title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
        if ( ! empty( $yoast_title ) ) {
            return $yoast_title;
        }

        // 3. Fallback: post_title
        return get_the_title( $post_id );
    }

    // =========================================================================
    // Fix 2: Meta description with BROKEN_FALLBACK detection
    // =========================================================================

    public static function siloq_get_meta_description( $post_id ) {
        global $wpdb;

        $val = '';

        // 1. AIOSEO table
        $table = $wpdb->prefix . 'aioseo_posts';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table ) {
            $val = $wpdb->get_var( $wpdb->prepare(
                "SELECT description FROM {$table} WHERE post_id = %d LIMIT 1", $post_id
            ) );
        }

        // 2. Yoast
        if ( empty( $val ) ) {
            $val = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
        }

        // 3. Genesis
        if ( empty( $val ) ) {
            $val = get_post_meta( $post_id, '_genesis_description', true );
        }

        if ( empty( $val ) ) {
            return '';
        }

        // BROKEN_FALLBACK: over 500 chars means full page content dumped
        if ( strlen( $val ) > 500 ) {
            return array( 'status' => 'broken_fallback', 'length' => strlen( $val ) );
        }

        return $val;
    }

    // =========================================================================
    // Fix 3: URL pattern auto-classification
    // =========================================================================

    public static function siloq_classify_page( $post_id, $url = null ) {
        if ( empty( $url ) ) {
            $url = get_permalink( $post_id );
        }
        $path = strtolower( wp_parse_url( $url, PHP_URL_PATH ) ?: '/' );

        // 1. Homepage
        $front_id = intval( get_option( 'page_on_front' ) );
        if ( $post_id === $front_id || $path === '/' || $path === '' ) {
            return 'apex_hub';
        }

        // 2. Service hub patterns
        if ( preg_match( '#/(services?|service-areas?|our-services?)/?$#', $path ) ) {
            return 'hub';
        }

        // 3. City / spoke patterns
        $state_abbrs = 'al|ak|az|ar|ca|co|ct|de|fl|ga|hi|id|il|in|ia|ks|ky|la|me|md|ma|mi|mn|ms|mo|mt|ne|nv|nh|nj|nm|ny|nc|nd|oh|ok|or|pa|ri|sc|sd|tn|tx|ut|vt|va|wa|wv|wi|wy';
        if ( preg_match( '#/(' . $state_abbrs . ')/?$#', $path ) ) {
            return 'spoke';
        }
        // City-service combo: any slug ending in a service keyword (handles multi-word city slugs)
        // e.g. /excelsior-springs-mo-electrician/, /bonner-springs-ks-electrician/
        if ( preg_match( '#/[a-z]+(?:-[a-z]+)*-(?:electrician|electric|plumb|hvac|roof|repair|install|service|clean|maint|remodel|contractor|landscap|pest|paint|plaster|carpet|gutter|fence|concrete|foundation|waterproof|handyman|general)/?$#', $path ) ) {
            return 'spoke';
        }
        // Title-based: "[City], [State] [Service]" or "[Service] [City], [State]" — classify as spoke
        $state_abbrs_list = 'AL|AK|AZ|AR|CA|CO|CT|DE|FL|GA|HI|ID|IL|IN|IA|KS|KY|LA|ME|MD|MA|MI|MN|MS|MO|MT|NE|NV|NH|NJ|NM|NY|NC|ND|OH|OK|OR|PA|RI|SC|SD|TN|TX|UT|VT|VA|WA|WV|WI|WY|DC';
        $post_title = get_the_title( $post_id );
        if ( preg_match( '/\b(' . $state_abbrs_list . ')\b/', $post_title ) &&
             preg_match( '/\b(electrician|electric|plumb|hvac|roof|repair|install|service|clean|maint|remodel|contractor|landscap|pest|paint|concrete|handyman)\b/i', $post_title ) ) {
            return 'spoke';
        }
        // Known large US cities
        $city_pattern = '#/(houston|dallas|austin|san-antonio|fort-worth|arlington|plano|irving|frisco|mckinney|denton|katy|sugar-land|the-woodlands|spring|pearland|league-city|pasadena|beaumont|midland|odessa|lubbock|amarillo|el-paso|corpus-christi|brownsville|killeen|waco|tyler|longview|round-rock|pflugerville|georgetown|cedar-park|new-york|los-angeles|chicago|phoenix|philadelphia|jacksonville|columbus|charlotte|indianapolis|denver|seattle|nashville|oklahoma-city|portland|las-vegas|memphis|louisville|baltimore|milwaukee|albuquerque|tucson|fresno|sacramento|mesa|kansas-city|atlanta|omaha|colorado-springs|raleigh|miami|tampa|orlando|minneapolis|cleveland|pittsburgh|st-louis|cincinnati)/?$#';
        if ( preg_match( $city_pattern, $path ) ) {
            return 'spoke';
        }

        // 4. Supporting URL patterns
        if ( preg_match( '#/(blog|resources?|faqs?|about|contact)(/|$)#', $path ) ) {
            return 'supporting';
        }

        // 5. Orphan: zero inbound internal links
        global $wpdb;
        $site_url = home_url();
        $permalink = get_permalink( $post_id );
        $like_url = '%' . $wpdb->esc_like( wp_parse_url( $permalink, PHP_URL_PATH ) ) . '%';
        $has_inbound = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND ID != %d AND post_content LIKE %s LIMIT 1",
            $post_id, $like_url
        ) );
        if ( intval( $has_inbound ) === 0 ) {
            // Also check nav menus
            $in_menu = false;
            $menus = get_nav_menu_locations();
            foreach ( $menus as $menu_id ) {
                $items = wp_get_nav_menu_items( $menu_id );
                if ( is_array( $items ) ) {
                    foreach ( $items as $item ) {
                        if ( intval( $item->object_id ) === $post_id ) {
                            $in_menu = true;
                            break 2;
                        }
                    }
                }
            }
            if ( ! $in_menu ) {
                return 'orphan';
            }
        }

        // 6. Default
        return 'supporting';
    }

    // =========================================================================
    // Fix 4: Priority action sorting — tier system
    // =========================================================================

    public static function siloq_sort_actions( &$actions ) {
        $tier_map = array(
            // Tier 1 — STRUCTURAL
            'Missing SEO title'          => 10,
            'Missing title'              => 10,
            'missing meta description'   => 11,
            'Broken'                     => 12,
            'BROKEN_FALLBACK'            => 12,
            'Missing H1'                 => 13,
            'multiple H1'                => 13,
            'Duplicate title'            => 14,
            // Tier 2 — CONTENT
            'Thin content'               => 20,
            'No internal links'          => 21,
            'No images'                  => 22,
            'missing alt'                => 22,
            // Tier 3 — SCHEMA
            'schema'                     => 30,
            'structured data'            => 30,
            // Tier 4 — CLASSIFICATION
            'Unclassified'               => 40,
            'cannibalization'            => 41,
        );

        $severity_order = array( 'critical' => 0, 'high' => 1, 'warning' => 2, 'important' => 2, 'medium' => 3, 'info' => 4, 'low' => 5 );

        usort( $actions, function( $a, $b ) use ( $tier_map, $severity_order ) {
            $text_a = strtolower( ( isset( $a['headline'] ) ? $a['headline'] : '' ) . ' ' . ( isset( $a['issue'] ) ? $a['issue'] : '' ) . ' ' . ( isset( $a['detail'] ) ? $a['detail'] : '' ) );
            $text_b = strtolower( ( isset( $b['headline'] ) ? $b['headline'] : '' ) . ' ' . ( isset( $b['issue'] ) ? $b['issue'] : '' ) . ' ' . ( isset( $b['detail'] ) ? $b['detail'] : '' ) );

            $tier_a = 50;
            $tier_b = 50;
            foreach ( $tier_map as $keyword => $tier ) {
                if ( stripos( $text_a, strtolower( $keyword ) ) !== false && $tier < $tier_a ) {
                    $tier_a = $tier;
                }
                if ( stripos( $text_b, strtolower( $keyword ) ) !== false && $tier < $tier_b ) {
                    $tier_b = $tier;
                }
            }

            if ( $tier_a !== $tier_b ) {
                return $tier_a - $tier_b;
            }

            // Within same tier, sort by severity
            $sev_a = isset( $a['priority'] ) ? strtolower( $a['priority'] ) : ( isset( $a['severity'] ) ? strtolower( $a['severity'] ) : 'low' );
            $sev_b = isset( $b['priority'] ) ? strtolower( $b['priority'] ) : ( isset( $b['severity'] ) ? strtolower( $b['severity'] ) : 'low' );
            return ( $severity_order[ $sev_a ] ?? 5 ) - ( $severity_order[ $sev_b ] ?? 5 );
        } );
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        // Onboarding wizard gate
        $api_key = get_option('siloq_api_key', '');
        $site_id = get_option('siloq_site_id', '');
        $onboarding_done = get_option('siloq_onboarding_complete', 'no');

        // Auto-recover: if the site already has api_key + site_id, it was previously
        // set up. A plugin update or option wipe should not strand it on the wizard.
        // Mark onboarding complete automatically so the real dashboard loads.
        if ( ! empty( $api_key ) && ! empty( $site_id ) && $onboarding_done !== 'yes' ) {
            update_option( 'siloq_onboarding_complete', 'yes' );
            $onboarding_done = 'yes';
        }

        if ($onboarding_done !== 'yes' || empty($api_key)) {
            self::render_onboarding_wizard();
            return;
        }

        // Ensure plugin URL constant is defined
        if (!defined('SILOQ_PLUGIN_URL')) {
            define('SILOQ_PLUGIN_URL', plugin_dir_url(dirname(__FILE__) . '/../'));
        }

        // Handle form submission FIRST — so all get_option calls below reflect the saved values
        if (isset($_POST['siloq_save_settings']) && check_admin_referer('siloq_settings_nonce')) {
            self::save_settings();
        }
        
        // Get current settings (read AFTER save so fields render saved values)
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
                <?php
                $site_id_val   = get_option('siloq_site_id', '');
                $site_name_val = get_option('siloq_site_name', '');
                ?>
                <div class="siloq-connection-banner <?php echo $connection_verified ? 'connected' : ($site_id_val ? 'connected' : 'warning'); ?>">
                    <?php if ($connection_verified || $site_id_val): ?>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php if ($site_name_val): ?>
                            <?php echo esc_html($site_name_val); ?>
                        <?php else: ?>
                            <?php _e('Connected to Siloq', 'siloq-connector'); ?>
                        <?php endif; ?>
                        <?php if ($site_id_val): ?>
                            <span style="opacity:.7;font-size:12px;">&nbsp;· Site ID: <?php echo esc_html($site_id_val); ?></span>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(self::DASHBOARD_URL . '/dashboard'); ?>" target="_blank" class="siloq-dashboard-link">
                            <?php _e('Open Dashboard →', 'siloq-connector'); ?>
                        </a>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('API key saved — save settings to verify connection', 'siloq-connector'); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="siloq-settings-container">
                <!-- Google Search Console -->
                <?php
                // Check GSC status directly from the API (no local cache needed)
                $site_id_for_gsc = get_option('siloq_site_id', '');
                $api_key_for_gsc = get_option('siloq_api_key', '');
                $gsc_api_status  = false;
                $gsc_prop        = '';
                if ($site_id_for_gsc && $api_key_for_gsc) {
                    $gsc_check = wp_remote_get(
                        trailingslashit($api_url) . 'sites/' . $site_id_for_gsc . '/gsc/status/',
                        array('headers' => array('Authorization' => 'Bearer ' . $api_key_for_gsc, 'Accept' => 'application/json'), 'timeout' => 8)
                    );
                    if (!is_wp_error($gsc_check) && wp_remote_retrieve_response_code($gsc_check) < 400) {
                        $gsc_body = json_decode(wp_remote_retrieve_body($gsc_check), true);
                        $gsc_api_status = !empty($gsc_body['connected']);
                        $gsc_prop = isset($gsc_body['gsc_site_url']) ? $gsc_body['gsc_site_url'] : '';
                        if ($gsc_api_status) update_option('siloq_gsc_connected', 'yes');
                    }
                }
                $gsc_is_connected = $gsc_api_status || (get_option('siloq_gsc_connected', '') === 'yes');
                ?>
                <div class="siloq-card" style="margin-bottom:20px;">
                    <h2><?php _e('Google Search Console', 'siloq-connector'); ?></h2>
                    <?php if (get_option('siloq_gsc_needs_property_selection') === 'yes'): ?>
                        <div id="siloq-gsc-property-picker" class="notice notice-info" style="padding:16px;margin:12px 0;">
                            <h3 style="margin:0 0 8px;">&#9989; Google account connected &mdash; choose your property</h3>
                            <p style="color:#555;margin:0 0 12px;">Select which Search Console property to use for this site:</p>
                            <div id="siloq-gsc-property-list">Loading properties...</div>
                            <p style="margin:12px 0 0;">
                                <button type="button" id="siloq-gsc-confirm-property" class="button button-primary" disabled>Confirm Connection</button>
                                <button type="button" id="siloq-gsc-cancel-property" class="button" style="margin-left:8px;">Cancel</button>
                            </p>
                        </div>
                    <?php elseif ($gsc_is_connected): ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#22c55e;flex-shrink:0;border-radius:50%;"></span>
                            <strong><?php _e('Connected', 'siloq-connector'); ?></strong>
                            <?php if ($gsc_prop): ?>
                                <span style="color:#666;font-size:13px;">&mdash; <?php echo esc_html($gsc_prop); ?></span>
                            <?php endif; ?>
                        </div>
                        <p style="margin:0;">
                            <button type="button" id="siloq-gsc-sync-btn" class="button button-primary"><?php _e('Sync GSC Data', 'siloq-connector'); ?></button>
                            <button type="button" id="siloq-gsc-disconnect-btn" class="button" style="margin-left:8px;"><?php _e('Disconnect', 'siloq-connector'); ?></button>
                            <span id="siloq-gsc-status-msg" style="margin-left:10px;font-size:13px;"></span>
                        </p>
                    <?php else: ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span style="display:inline-block;width:10px;height:10px;background:#9ca3af;flex-shrink:0;border-radius:50%;"></span>
                            <strong><?php _e('Not connected', 'siloq-connector'); ?></strong>
                        </div>
                        <p style="color:#555;margin-bottom:12px;"><?php _e('Connect Google Search Console to see which pages get traffic and need the most attention first.', 'siloq-connector'); ?></p>
                        <?php if (get_option('siloq_gsc_needs_property_selection') === 'yes'): ?>
                        <div id="siloq-gsc-property-picker" style="background:#f0f7ff;border:1px solid #2271b1;border-radius:6px;padding:16px;margin-bottom:12px;">
                            <h3 style="margin:0 0 8px;color:#1d2327;">✅ Google account connected — choose your property</h3>
                            <p style="color:#555;margin:0 0 12px;">Select which Search Console property to use for <strong><?php echo esc_html(home_url()); ?></strong>:</p>
                            <div id="siloq-property-list" style="margin-bottom:12px;"><em>Loading properties...</em></div>
                            <button type="button" id="siloq-confirm-property-btn" class="button button-primary" disabled style="margin-right:8px;">Confirm Connection</button>
                            <button type="button" id="siloq-cancel-property-btn" class="button">Cancel</button>
                            <span id="siloq-property-status" style="margin-left:10px;font-size:13px;color:#666;"></span>
                        </div>
                        <?php else: ?>
                        <p style="margin:0;">
                            <button type="button" id="siloq-gsc-connect-btn" class="button button-primary">
                                <?php _e('⚡ Connect Google Search Console', 'siloq-connector'); ?>
                            </button>
                            &nbsp;
                            <button type="button" id="siloq-gsc-check-btn" class="button"><?php _e('Check Connection', 'siloq-connector'); ?></button>
                            <span id="siloq-gsc-status-msg" style="margin-left:10px;font-size:13px;color:#666;"></span>
                        </p>
                        <p style="margin-top:8px;color:#888;font-size:12px;"><?php _e('A Google sign-in window will open. Complete authorization, then return here.', 'siloq-connector'); ?></p>
                        <?php endif; ?>
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
                                <th scope="row"><?php _e('OpenAI API Key', 'siloq-connector'); ?></th>
                                <td>
                                    <?php $oai_saved = !empty(get_option('siloq_openai_api_key', '')); ?>
                                    <div id="siloq-oai-status" style="margin-bottom:6px;font-weight:600;color:<?php echo $oai_saved ? '#16a34a' : '#6b7280'; ?>">
                                        <?php echo $oai_saved ? '✓ Key saved (ends in …' . esc_html(substr(get_option('siloq_openai_api_key'), -4)) . ')' : 'No key saved'; ?>
                                    </div>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <input type="text" id="siloq-oai-key-input" class="regular-text" placeholder="sk-..." autocomplete="off" style="font-family:monospace;flex:1;" />
                                        <button type="button" id="siloq-save-oai-key" class="button button-primary"><?php _e('Save Key', 'siloq-connector'); ?></button>
                                    </div>
                                    <p class="description" style="margin-top:6px;"><?php _e('Required for DALL-E image generation.', 'siloq-connector'); ?> <a href="https://platform.openai.com/api-keys" target="_blank">Get key →</a></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e('Anthropic API Key', 'siloq-connector'); ?></th>
                                <td>
                                    <?php $ant_saved = !empty(get_option('siloq_anthropic_api_key', '')); ?>
                                    <div id="siloq-ant-status" style="margin-bottom:6px;font-weight:600;color:<?php echo $ant_saved ? '#16a34a' : '#6b7280'; ?>">
                                        <?php echo $ant_saved ? '✓ Key saved (ends in …' . esc_html(substr(get_option('siloq_anthropic_api_key'), -4)) . ')' : 'No key saved'; ?>
                                    </div>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <input type="text" id="siloq-ant-key-input" class="regular-text" placeholder="sk-ant-..." autocomplete="off" style="font-family:monospace;flex:1;" />
                                        <button type="button" id="siloq-save-ant-key" class="button button-primary"><?php _e('Save Key', 'siloq-connector'); ?></button>
                                    </div>
                                    <p class="description" style="margin-top:6px;"><?php _e('Powers AI content suggestions and draft generation (Claude Sonnet).', 'siloq-connector'); ?> <a href="https://console.anthropic.com/settings/keys" target="_blank">Get key →</a></p>
                                    <script>
                                    (function(){
                                        var nonce = '<?php echo esc_js(wp_create_nonce('siloq_save_api_key')); ?>';
                                        function saveKey(inputId, optionName, statusId, btn) {
                                            var val = document.getElementById(inputId).value.trim();
                                            if (!val) { alert('Please enter a key first.'); return; }
                                            btn.disabled = true; btn.textContent = 'Saving…';
                                            jQuery.post(ajaxurl, {
                                                action: 'siloq_save_api_key',
                                                nonce: nonce,
                                                option_name: optionName,
                                                key_value: val
                                            }, function(r) {
                                                if (r.success) {
                                                    document.getElementById(statusId).style.color = '#16a34a';
                                                    document.getElementById(statusId).textContent = '✓ Key saved (ends in …' + r.data.last4 + ')';
                                                    document.getElementById(inputId).value = '';
                                                    btn.textContent = '✓ Saved';
                                                    setTimeout(function(){ btn.disabled = false; btn.textContent = 'Save Key'; }, 2000);
                                                } else {
                                                    alert('Save failed: ' + (r.data && r.data.message ? r.data.message : 'Unknown error'));
                                                    btn.disabled = false; btn.textContent = 'Save Key';
                                                }
                                            }).fail(function(){ alert('Request failed. Try again.'); btn.disabled = false; btn.textContent = 'Save Key'; });
                                        }
                                        document.getElementById('siloq-save-oai-key').addEventListener('click', function(){ saveKey('siloq-oai-key-input','siloq_openai_api_key','siloq-oai-status',this); });
                                        document.getElementById('siloq-save-ant-key').addEventListener('click', function(){ saveKey('siloq-ant-key-input','siloq_anthropic_api_key','siloq-ant-status',this); });
                                    })();
                                    </script>
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
                                        <label for="siloq_founding_year"><?php _e('Year Founded', 'siloq-connector'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="siloq_founding_year" name="siloq_founding_year" class="small-text" placeholder="<?php _e('e.g. 2008', 'siloq-connector'); ?>" maxlength="4" style="width:90px;">
                                        <p class="description"><?php _e('Used in llms.txt and authority manifest for AI citation. Auto-populated from Google Business Profile if available.', 'siloq-connector'); ?></p>
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
                                <span class="siloq-adv-label"><?php _e('Advanced Settings', 'siloq-connector'); ?></span>
                                <span class="dashicons dashicons-arrow-down-alt2 siloq-adv-icon"></span>
                            </button>
                        </h2>
                        
                        <div class="siloq-advanced-content" style="display: <?php echo $show_advanced === 'yes' ? 'block' : 'none'; ?>;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="siloq_site_id_manual">
                                            <?php _e('Site ID', 'siloq-connector'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input
                                            type="text"
                                            id="siloq_site_id_manual"
                                            name="siloq_site_id_manual"
                                            value="<?php echo esc_attr(get_option('siloq_site_id', '')); ?>"
                                            class="regular-text"
                                            placeholder="Auto-detected from your Siloq account"
                                        />
                                        <p class="description">
                                            <?php _e('Your Siloq site ID. Auto-detected when you save your API key. If wrong, enter the correct ID from your Siloq dashboard.', 'siloq-connector'); ?>
                                        </p>
                                    </td>
                                </tr>
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
                                            // Exclude internal/non-content post types from the checkbox list
                                            $internal_types = array(
                                                'attachment', 'revision', 'nav_menu_item', 'custom_css',
                                                'customize_changeset', 'wp_block', 'wp_template', 'wp_template_part',
                                                'wp_global_styles', 'wp_navigation', 'elementor_library',
                                                'e-floating-buttons', 'elementor-hf', 'slider', 'slides', 'slide',
                                                'home_slider', 'home-slider', 'smart-slider', 'rev_slider',
                                                'ml-slider', 'soliloquy', 'wpcf7_contact_form', 'popup', 'popups',
                                                'product_variation', 'shop_order', 'shop_coupon', 'shop_order_refund',
                                            );
                                            $all_post_types = get_post_types(array(), 'objects');
                                            $enabled_types = get_option('siloq_content_types', array('page', 'post'));
                                            foreach ($all_post_types as $post_type) {
                                                if (in_array($post_type->name, $internal_types)) continue;
                                                if (empty($post_type->label)) continue;
                                                ?>
                                                <label style="margin-right: 15px; display:inline-block; margin-bottom:6px;">
                                                    <input
                                                        type="checkbox"
                                                        name="siloq_content_types[]"
                                                        value="<?php echo esc_attr($post_type->name); ?>"
                                                        <?php checked(in_array($post_type->name, (array)$enabled_types)); ?>
                                                    />
                                                    <?php echo esc_html($post_type->labels->singular_name ?: $post_type->label); ?>
                                                    <span style="color:#999;font-size:11px;">(<?php echo esc_html($post_type->name); ?>)</span>
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
                            <p style="margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb;">
                                <button type="submit" name="siloq_save_settings" class="button button-primary">
                                    <?php _e('Save Advanced Settings', 'siloq-connector'); ?>
                                </button>
                            </p>
                        </div>
                    </div>
                </form>
                
                <!-- Debug Card -->
                <div class="siloq-card siloq-debug-card">
                    <h2>
                        <button type="button" class="siloq-toggle-debug-section" style="background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:8px;width:100%;padding:0;font:inherit;color:inherit;">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <span><?php _e('Debug Logging', 'siloq-connector'); ?></span>
                            <span class="dashicons dashicons-arrow-down-alt2 siloq-debug-icon" style="margin-left:auto;"></span>
                        </button>
                    </h2>
                    <div class="siloq-debug-content" style="display:none;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Debug Mode', 'siloq-connector'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="siloq_debug_toggle" <?php checked( get_option('siloq_debug_mode') ); ?>>
                                        <?php _e('Enable debug logging', 'siloq-connector'); ?>
                                    </label>
                                    <span id="siloq-debug-toggle-status" style="margin-left:8px;"></span>
                                </td>
                            </tr>
                        </table>
                        <div style="margin-top:12px;display:flex;gap:8px;">
                            <button type="button" id="siloq-clear-debug-log" class="button"><?php _e('Clear Log', 'siloq-connector'); ?></button>
                            <button type="button" id="siloq-download-debug-log" class="button"><?php _e('Download Log', 'siloq-connector'); ?></button>
                        </div>
                        <div style="margin-top:12px;">
                            <h4 style="margin-bottom:6px;"><?php _e('Last 50 Log Lines', 'siloq-connector'); ?></h4>
                            <pre id="siloq-debug-log-viewer" style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:4px;max-height:400px;overflow:auto;font-size:12px;line-height:1.5;white-space:pre-wrap;"><?php _e('Loading...', 'siloq-connector'); ?></pre>
                        </div>
                    </div>
                </div>

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
                var icon = $(this).find('.siloq-adv-icon');
                var buttonText = $(this).find('.siloq-adv-label');
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
                            $('#siloq_founding_year').val(profile.founding_year || '');
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
                        founding_year: $('#siloq_founding_year').val(),
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
        (function($){
            var nonce = '<?php echo esc_js(wp_create_nonce("siloq_ajax_nonce")); ?>';
            var ajaxUrl = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';

            function gscMsg(text, color) {
                $('#siloq-gsc-status-msg').text(text).css('color', color || '#555');
            }

            // ── GSC Connect: OAuth Proxy Pattern ──
            // Plugin fetches auth URL from API (which encodes wp_return_url in state).
            // Browser redirects to Google OAuth — stays in same tab.
            // Google bounces to api.siloq.ai callback, which exchanges token and 
            // redirects browser back to WP admin with ?siloq_gsc=connected.
            // Plugin detects that param on load (see PHP admin_init hook).
            window.siloqOpenGSCPopup = function() {
                var $btn = $('#siloq-gsc-connect-btn, #siloq-gsc-connect-btn-tab, .siloq-gsc-connect-popup');
                $btn.prop('disabled', true).text('Loading...');
                gscMsg('Getting authorization URL...', '#555');
                $.post(ajaxUrl, {action: 'siloq_gsc_init_oauth', nonce: nonce}, function(r) {
                    if (!r.success || !r.data || !r.data.auth_url) {
                        $btn.prop('disabled', false).text('⚡ Connect Google Search Console');
                        gscMsg(r.data && r.data.message ? r.data.message : 'Could not get authorization URL. Check your API key.', '#dc2626');
                        return;
                    }
                    // Open Google OAuth in a NEW TAB — current WP admin tab stays open
                    window.open(r.data.auth_url, '_blank');
                    $btn.prop('disabled', false).text('⚡ Connect Google Search Console');
                    gscMsg('✅ Google sign-in opened in a new tab. Complete it there, then click <strong>Check Connection</strong> below.', '#16a34a');

                    // Auto-poll every 4 seconds to detect when OAuth completes
                    var pollCount = 0;
                    var pollTimer = setInterval(function() {
                        pollCount++;
                        if (pollCount > 30) { clearInterval(pollTimer); return; } // stop after 2 min
                        $.post(ajaxUrl, {action: 'siloq_gsc_check_status', nonce: nonce}, function(res) {
                            if (res.success && res.data && res.data.connected) {
                                clearInterval(pollTimer);
                                gscMsg('✅ Google Search Console connected! Refreshing...', '#16a34a');
                                setTimeout(function(){ window.location.reload(); }, 1500);
                            }
                        });
                    }, 4000);
                }).fail(function(xhr){
                    $btn.prop('disabled', false).text('⚡ Connect Google Search Console');
                    gscMsg('Request failed (HTTP ' + xhr.status + ').', '#dc2626');
                });
            };

            // Alias — some buttons use this name
            window.siloqInitGSCConnect = window.siloqOpenGSCPopup;

            $(document).on('click', '#siloq-gsc-connect-btn, .siloq-gsc-connect-popup', function() {
                window.siloqOpenGSCPopup();
            });

            // AUTO-CHECK: if Settings page shows GSC connected, verify against API on load
            // This catches stale data (e.g. previous client's GSC showing up)
            <?php if (get_option('siloq_gsc_connected') === 'yes'): ?>
            $.post(ajaxUrl, {action: 'siloq_gsc_check_status', nonce: nonce}, function(r) {
                if (r.success && r.data && !r.data.connected) {
                    // API says not connected — stale data. Reload to show correct state.
                    location.reload();
                }
            });
            <?php endif; ?>

            // ── Property Picker JS ──
            (function() {
                var $list = $('#siloq-property-list');
                if (!$list.length) return;

                var nonce2 = '<?php echo esc_js(wp_create_nonce("siloq_ajax_nonce")); ?>';
                var siteUrl = '<?php echo esc_js(preg_replace("/^www\\./",'',parse_url(home_url(),PHP_URL_HOST))); ?>';

                // Load properties on page load
                $.post(ajaxUrl, {action: 'siloq_gsc_get_properties', nonce: nonce2}, function(r) {
                    if (!r.success || !r.data || !r.data.properties) {
                        $list.html('<span style="color:#dc2626;">Could not load properties: ' + (r.data && r.data.message ? r.data.message : 'unknown error') + '</span>');
                        return;
                    }
                    var props = r.data.properties;
                    var html = '';
                    var autoSelected = '';
                    $.each(props, function(i, p) {
                        var pHost = p.replace(/^https?:\/\/(www\.)?/,'').replace(/\//g,'');
                        var isMatch = (pHost === siteUrl || p.indexOf(siteUrl) !== -1);
                        if (isMatch && !autoSelected) autoSelected = p;
                        html += '<label style="display:block;padding:6px 0;cursor:pointer;">' +
                            '<input type="radio" name="siloq_gsc_property" value="' + p + '" style="margin-right:8px;"' + (isMatch ? ' checked' : '') + '> ' +
                            p + (isMatch ? ' <span style="color:#16a34a;font-size:12px;">(recommended)</span>' : '') + '</label>';
                    });
                    $list.html(html || '<em>No properties found in your Google account.</em>');
                    if (autoSelected || props.length > 0) {
                        $('#siloq-confirm-property-btn').prop('disabled', false);
                    }
                    $list.on('change', 'input[name=siloq_gsc_property]', function() {
                        $('#siloq-confirm-property-btn').prop('disabled', false);
                    });
                }).fail(function() {
                    $list.html('<span style="color:#dc2626;">Network error loading properties.</span>');
                });

                $('#siloq-confirm-property-btn').on('click', function() {
                    var selected = $list.find('input[name=siloq_gsc_property]:checked').val();
                    if (!selected) { alert('Please select a property.'); return; }
                    $(this).prop('disabled', true).text('Saving...');
                    $.post(ajaxUrl, {action: 'siloq_gsc_save_property', nonce: nonce2, property: selected}, function(r) {
                        if (r.success) {
                            $('#siloq-property-status').text('Connected! Refreshing...').css('color','#16a34a');
                            setTimeout(function(){ location.reload(); }, 800);
                        } else {
                            $('#siloq-confirm-property-btn').prop('disabled', false).text('Confirm Connection');
                            $('#siloq-property-status').text(r.data && r.data.message ? r.data.message : 'Save failed.').css('color','#dc2626');
                        }
                    });
                });

                $('#siloq-cancel-property-btn').on('click', function() {
                    $.post(ajaxUrl, {action: 'siloq_gsc_check_status', nonce: nonce2}, function(){ location.reload(); });
                });
            })();

            // "Check Connection" after user connects in app.siloq.ai
            $(document).on('click', '#siloq-gsc-check-btn', function() {
                var $btn = $(this).prop('disabled', true).text('Checking...');
                $.post(ajaxUrl, {action: 'siloq_gsc_check_status', nonce: nonce}, function(r) {
                    $btn.prop('disabled', false).text('Check Connection');
                    if (r.success && r.data && r.data.connected) {
                        gscMsg('Connected! Refreshing...', '#16a34a');
                        setTimeout(function(){ location.reload(); }, 800);
                    } else {
                        gscMsg('Not connected yet. Connect in Siloq Dashboard first, then try again.', '#dc2626');
                    }
                }).fail(function(xhr){ $btn.prop('disabled', false).text('Check Connection'); gscMsg('Request failed (HTTP ' + xhr.status + ').', '#dc2626'); });
            });

            // Sync GSC data
            $(document).on('click', '#siloq-gsc-sync-btn', function() {
                var $btn = $(this).prop('disabled', true).text('Syncing...');
                $.post(ajaxUrl, {action: 'siloq_gsc_sync', nonce: nonce}, function(r) {
                    $btn.prop('disabled', false).text('Sync GSC Data');
                    gscMsg(r.success ? 'GSC data synced successfully.' : (r.data && r.data.message ? r.data.message : 'Sync failed.'), r.success ? '#16a34a' : '#dc2626');
                }).fail(function(){ $btn.prop('disabled', false).text('Sync GSC Data'); gscMsg('Request failed.', '#dc2626'); });
            });

            // Disconnect
            $(document).on('click', '#siloq-gsc-disconnect-btn', function() {
                if (!confirm('Disconnect Google Search Console?')) return;
                $(this).prop('disabled', true);
                $.post(ajaxUrl, {action: 'siloq_gsc_disconnect', nonce: nonce}, function(r) {
                    if (r.success) location.reload();
                    else gscMsg('Disconnect failed.', '#dc2626');
                });
            });

            // ── GSC Property Picker (Settings page) ──
            <?php if (get_option('siloq_gsc_needs_property_selection') === 'yes'): ?>
            (function(){
                var $list = $('#siloq-gsc-property-list');
                var $confirm = $('#siloq-gsc-confirm-property');
                $.post(ajaxUrl, {action: 'siloq_gsc_get_properties', nonce: nonce}, function(r) {
                    if (!r.success || !r.data || !r.data.properties || r.data.properties.length === 0) {
                        $list.html('<p style="color:#dc2626;">Could not load properties. ' + (r.data && r.data.message ? r.data.message : 'Try again.') + '</p>');
                        return;
                    }
                    var props = r.data.properties;
                    var homeUrl = (r.data.home_url || '').replace(/^https?:\/\/(www\.)?/, '').replace(/\/+$/, '');
                    var html = '';
                    for (var i = 0; i < props.length; i++) {
                        var p = typeof props[i] === 'string' ? props[i] : (props[i].siteUrl || props[i].url || '');
                        var normalized = p.replace(/^sc-domain:/, '').replace(/^https?:\/\/(www\.)?/, '').replace(/\/+$/, '');
                        var checked = (normalized === homeUrl) ? ' checked' : '';
                        html += '<label style="display:block;padding:6px 0;cursor:pointer;"><input type="radio" name="siloq_gsc_prop" value="' + p.replace(/"/g, '&quot;') + '"' + checked + ' style="margin-right:8px;"> ' + p.replace(/</g, '&lt;') + '</label>';
                    }
                    $list.html(html);
                    if ($list.find('input:checked').length) $confirm.prop('disabled', false);
                }).fail(function(){ $list.html('<p style="color:#dc2626;">Network error loading properties.</p>'); });

                $list.on('change', 'input[name="siloq_gsc_prop"]', function() {
                    $confirm.prop('disabled', false);
                });

                $confirm.on('click', function() {
                    var selected = $list.find('input[name="siloq_gsc_prop"]:checked').val();
                    if (!selected) return;
                    $(this).prop('disabled', true).text('Saving...');
                    $.post(ajaxUrl, {action: 'siloq_gsc_save_property', nonce: nonce, property: selected}, function(r) {
                        if (r.success) {
                            gscMsg('Connected to ' + (r.data.property || selected), '#16a34a');
                            setTimeout(function(){ location.reload(); }, 800);
                        } else {
                            gscMsg(r.data && r.data.message ? r.data.message : 'Save failed.', '#dc2626');
                            $confirm.prop('disabled', false).text('Confirm Connection');
                        }
                    }).fail(function(){ gscMsg('Network error.', '#dc2626'); $confirm.prop('disabled', false).text('Confirm Connection'); });
                });

                $('#siloq-gsc-cancel-property').on('click', function() {
                    $.post(ajaxUrl, {action: 'siloq_gsc_disconnect', nonce: nonce}, function() {
                        location.reload();
                    });
                });
            })();
            <?php endif; ?>

            // ── Debug Logging Tab ────────────────────────────────────────
            (function() {
                var nonce = '<?php echo wp_create_nonce("siloq_debug_nonce"); ?>';
                var debugOn = <?php echo get_option('siloq_debug_mode') ? 'true' : 'false'; ?>;
                var refreshTimer = null;

                $('.siloq-toggle-debug-section').on('click', function() {
                    var content = $('.siloq-debug-content');
                    var icon = $(this).find('.siloq-debug-icon');
                    content.slideToggle(300, function() {
                        icon.toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
                    });
                    if (!content.is(':visible')) loadDebugLog();
                });

                function loadDebugLog() {
                    $.post(ajaxurl, {action: 'siloq_get_debug_log', nonce: nonce}, function(r) {
                        if (r.success) {
                            $('#siloq-debug-log-viewer').text(r.data.log || '(no log entries yet — enable debug mode and try syncing a page)');
                        } else {
                            $('#siloq-debug-log-viewer').text('(error loading log: ' + (r.data || 'unknown error') + ')');
                        }
                    }).fail(function(xhr) {
                        $('#siloq-debug-log-viewer').text('(request failed — HTTP ' + xhr.status + '. Check that debug nonce is valid.)');
                    });
                }

                function startAutoRefresh() {
                    stopAutoRefresh();
                    if (debugOn) {
                        refreshTimer = setInterval(loadDebugLog, 30000);
                    }
                }
                function stopAutoRefresh() {
                    if (refreshTimer) { clearInterval(refreshTimer); refreshTimer = null; }
                }

                $('#siloq_debug_toggle').on('change', function() {
                    var enabled = $(this).is(':checked');
                    $.post(ajaxurl, {action: 'siloq_toggle_debug', nonce: nonce, enabled: enabled ? 1 : 0}, function(r) {
                        if (r.success) {
                            debugOn = enabled;
                            $('#siloq-debug-toggle-status').text(enabled ? 'Enabled' : 'Disabled').css('color', enabled ? '#16a34a' : '#888');
                            if (enabled) startAutoRefresh(); else stopAutoRefresh();
                        }
                    });
                });

                $('#siloq-clear-debug-log').on('click', function() {
                    $.post(ajaxurl, {action: 'siloq_clear_debug_log', nonce: nonce}, function(r) {
                        if (r.success) {
                            $('#siloq-debug-log-viewer').text('(cleared)');
                        }
                    });
                });

                $('#siloq-download-debug-log').on('click', function() {
                    $.post(ajaxurl, {action: 'siloq_download_debug_log', nonce: nonce}, function(r) {
                        if (r.success && r.data.content) {
                            var blob = new Blob([r.data.content], {type: 'text/plain'});
                            var a = document.createElement('a');
                            a.href = URL.createObjectURL(blob);
                            a.download = 'siloq_debug.log';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                        }
                    });
                });

                // Initial load if section is visible
                if ($('.siloq-debug-content').is(':visible')) loadDebugLog();
                startAutoRefresh();
            })();
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Save settings
     */
    public static function handle_gsc_connect_redirect() {
        // PHP server-side GSC OAuth initiation — avoids JS popup blockers
        // Triggered by: /wp-admin/admin-post.php?action=siloq_gsc_connect
        check_admin_referer('siloq_gsc_connect_nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $api_url  = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
        $api_key  = get_option('siloq_api_key', '');
        $site_id  = get_option('siloq_site_id', '');

        if (empty($api_key) || empty($site_id)) {
            wp_redirect(admin_url('admin.php?page=siloq-settings&tab=gsc&gsc_error=missing_config'));
            exit;
        }

        $return_url = admin_url('admin.php?page=siloq-settings&tab=gsc');
        $response = wp_remote_get(
            trailingslashit($api_url) . 'gsc/auth-url/?site_id=' . $site_id . '&wp_return_url=' . rawurlencode($return_url),
            array('headers' => array('Authorization' => 'Bearer ' . $api_key, 'Accept' => 'application/json'), 'timeout' => 15)
        );

        if (is_wp_error($response)) {
            wp_redirect(admin_url('admin.php?page=siloq-settings&tab=gsc&gsc_error=api_unreachable'));
            exit;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 400 || empty($body['auth_url'])) {
            wp_redirect(admin_url('admin.php?page=siloq-settings&tab=gsc&gsc_error=no_auth_url'));
            exit;
        }

        // Real browser redirect to Google OAuth — same tab, no popup blocker issues
        wp_redirect($body['auth_url']);
        exit;
    }

    public static function force_set_site_id() {
        // Emergency direct-write bypass for stuck site_id option
        // Usage: /wp-admin/admin.php?page=siloq-settings&siloq_fix_site_id=13
        if ( ! isset( $_GET['siloq_fix_site_id'] ) || ! current_user_can('manage_options') ) {
            return;
        }
        $new_id = sanitize_text_field( $_GET['siloq_fix_site_id'] );
        if ( empty($new_id) ) return;

        global $wpdb;
        // Write directly to the DB options table — bypasses ALL object caching layers
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", 'siloq_site_id'
        ) );
        if ( $exists ) {
            $wpdb->update( $wpdb->options, ['option_value' => $new_id], ['option_name' => 'siloq_site_id'] );
        } else {
            $wpdb->insert( $wpdb->options, ['option_name' => 'siloq_site_id', 'option_value' => $new_id, 'autoload' => 'yes'] );
        }
        // Flush every caching layer we can reach
        wp_cache_delete( 'siloq_site_id', 'options' );
        wp_cache_delete( 'alloptions', 'options' );
        delete_transient('siloq_connection_verified');
        delete_transient('siloq_plan_data');

        // Redirect clean (remove the query param so it doesn't re-run on refresh)
        wp_redirect( admin_url('admin.php?page=siloq-settings&siloq_fixed=1') );
        exit;
    }

    public static function save_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $api_url = isset($_POST['siloq_api_url']) ? sanitize_text_field($_POST['siloq_api_url']) : self::DEFAULT_API_URL;
        $api_key = isset($_POST['siloq_api_key']) ? sanitize_text_field($_POST['siloq_api_key']) : '';
        // Allow manual Site ID override from Advanced Settings
        $site_id_manual = isset($_POST['siloq_site_id_manual']) ? sanitize_text_field($_POST['siloq_site_id_manual']) : '';
        if (!empty($site_id_manual)) {
            update_option('siloq_site_id', $site_id_manual);
            wp_cache_delete('siloq_site_id', 'options'); // force flush any persistent object cache
            // Clear any stale auto-detect transients so everything re-loads fresh
            delete_transient('siloq_connection_verified');
            delete_transient('siloq_plan_data');
        }
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
        } elseif (strpos($api_key, 'sk_siloq_') !== 0) {
            $errors[] = __('Invalid API key format. Your key should start with sk_siloq_ — please generate a fresh key from your Siloq dashboard.', 'siloq-connector');
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
        // OpenAI key for DALL-E (client-side image generation)
        // Only update if non-empty — blank submission means "leave existing key alone"
        $openai_key_setting = isset($_POST['siloq_openai_api_key']) ? sanitize_text_field(trim($_POST['siloq_openai_api_key'])) : '';
        if (!empty($openai_key_setting)) {
            update_option('siloq_openai_api_key', $openai_key_setting);
            wp_cache_delete('siloq_openai_api_key', 'options');
        }
        // Anthropic key — BYOK until api.siloq.ai/suggest-content endpoint is live
        $anthropic_key_setting = isset($_POST['siloq_anthropic_api_key']) ? sanitize_text_field(trim($_POST['siloq_anthropic_api_key'])) : '';
        if (!empty($anthropic_key_setting)) {
            update_option('siloq_anthropic_api_key', $anthropic_key_setting);
            wp_cache_delete('siloq_anthropic_api_key', 'options');
        }

        // Save content types to sync (Advanced Settings checkboxes)
        if (isset($_POST['siloq_content_types']) && is_array($_POST['siloq_content_types'])) {
            $content_types = array_map('sanitize_key', $_POST['siloq_content_types']);
            // Always ensure page and post are included
            $content_types = array_unique(array_merge($content_types, array('page', 'post')));
            update_option('siloq_content_types', $content_types);
        }
        
        // Clear connection verification if credentials changed
        if ($old_api_url !== $api_url || $old_api_key !== $api_key) {
            delete_transient('siloq_connection_verified');
            delete_transient('siloq_plan_data');
        }

        // Auto-detect site ID — ONLY if no manual override was just submitted
        $manual_override_just_set = !empty($site_id_manual);
        if ($manual_override_just_set) {
            add_settings_error('siloq_settings', 'siloq_site_id_saved',
                sprintf(__('Site ID set to <strong>%s</strong>. All plugin data will now use this site.', 'siloq-connector'), esc_html($site_id_manual)),
                'success');
        }
        $site_id = get_option('siloq_site_id', '');
        // NEVER auto-detect if a manual site_id is already stored — only auto-detect when truly empty
        if (!$manual_override_just_set && empty($site_id)) {
            $this_site_url = trailingslashit(home_url());
            $this_site_host = strtolower(preg_replace('/^www\./', '', parse_url($this_site_url, PHP_URL_HOST) ?? ''));

            $sites_resp = wp_remote_get(
                trailingslashit($api_url) . 'sites/',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Accept'        => 'application/json',
                    ),
                    'timeout' => 15,
                )
            );
            if (!is_wp_error($sites_resp) && wp_remote_retrieve_response_code($sites_resp) < 400) {
                $sites_data = json_decode(wp_remote_retrieve_body($sites_resp), true);
                $sites_list = isset($sites_data['results']) ? $sites_data['results'] : (is_array($sites_data) ? $sites_data : array());

                // Match this WP site's URL against API sites — never auto-pick if ambiguous
                $matched_site = null;
                foreach ($sites_list as $s) {
                    $api_host = strtolower(preg_replace('/^www\./', '', parse_url(isset($s['url']) ? $s['url'] : '', PHP_URL_HOST) ?? ''));
                    if ($api_host && $this_site_host && $api_host === $this_site_host) {
                        $matched_site = $s;
                        break;
                    }
                }

                if ($matched_site) {
                    update_option('siloq_site_id', $matched_site['id']);
                    wp_cache_delete('siloq_site_id', 'options');
                    update_option('siloq_site_name', isset($matched_site['name']) ? $matched_site['name'] : $matched_site['url']);
                    add_settings_error('siloq_settings', 'siloq_site_detected',
                        sprintf(__('Site detected: <strong>%s</strong> (ID: %s)', 'siloq-connector'),
                            esc_html(isset($matched_site['name']) ? $matched_site['name'] : $matched_site['url']),
                            esc_html($matched_site['id'])),
                        'success');
                } elseif (!empty($sites_list)) {
                    // Could not match — show list so user can pick manually
                    $site_names = array();
                    foreach ($sites_list as $s) {
                        $site_names[] = (isset($s['name']) ? $s['name'] : '') . ' (' . $s['url'] . ', ID:' . $s['id'] . ')';
                    }
                    add_settings_error('siloq_settings', 'siloq_site_ambiguous',
                        __('Multiple sites found in your account. Could not auto-match this WordPress install. Sites in your account: ', 'siloq-connector') .
                        '<br><ul><li>' . implode('</li><li>', array_map('esc_html', $site_names)) . '</li></ul>' .
                        __('Enter the correct Site ID in the Advanced Settings below.', 'siloq-connector'),
                        'warning');
                }
            }
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
        $site_id = get_option('siloq_site_id', '');
        $onboarding_done = get_option('siloq_onboarding_complete', 'no');

        // Auto-recover: if the site is already connected (has api_key + site_id),
        // skip the wizard. Plugin updates should never strand a live site on setup.
        if ( ! empty( $api_key ) && ! empty( $site_id ) && $onboarding_done !== 'yes' ) {
            update_option( 'siloq_onboarding_complete', 'yes' );
            $onboarding_done = 'yes';
        }

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
        $synced_post_types = function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array('page', 'post');
        // Fast count only — avoid loading all IDs for large sites
        $synced_pages_count_query = new WP_Query(array('post_type' => $synced_post_types, 'meta_query' => array(array('key' => '_siloq_synced', 'compare' => 'EXISTS')), 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false));
        $synced_pages = array_fill(0, max(0, $synced_pages_count_query->found_posts), 0); // fake array for count() compat
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
                <li><button class="siloq-tab-btn" role="tab" aria-selected="false" aria-controls="siloq-tab-depth-engine">Depth Engine</button></li>
                <li><button class="siloq-tab-btn" role="tab" aria-selected="false" aria-controls="siloq-tab-pages">Pages</button></li>
                <li><button class="siloq-tab-btn" role="tab" aria-selected="false" aria-controls="siloq-tab-schema">Schema</button></li>
                <li><button class="siloq-tab-btn" role="tab" aria-selected="false" aria-controls="siloq-tab-gsc">GSC</button></li>
                <li><button class="siloq-tab-btn" role="tab" aria-selected="false" aria-controls="siloq-tab-redirects">Redirects</button></li>
                <li><button class="siloq-tab-btn" role="tab" aria-selected="false" aria-controls="siloq-tab-goals">Goals</button></li>
                <li><button class="siloq-tab-btn" role="tab" aria-selected="false" aria-controls="siloq-tab-settings">Settings</button></li>
            </ul>

            <!-- ═══════ DASHBOARD TAB ═══════ -->
            <div id="siloq-tab-dashboard" class="siloq-tab-panel active" role="tabpanel" aria-hidden="false">
<?php if ( ! get_option('siloq_primary_goal', '') ): ?>
<div style="background:#fef9c3;border:1px solid #fde047;border-radius:10px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div style="font-size:13px;color:#854d0e;font-weight:500;">⚡ Set your Goals to get personalized SEO recommendations</div>
  <button type="button" class="siloq-btn siloq-btn--primary siloq-btn--sm" onclick="document.querySelector('[aria-controls=\'siloq-tab-goals\']').click()" style="font-size:12px;">Set Goals →</button>
</div>
<?php endif; ?>
<div class="siloq-dash-v2">

<?php
// Build profile completeness fields for display
$profile_fields = self::get_entity_field_status();
$missing_fields = array_filter($profile_fields, function($f) { return empty($f['filled']); });
$missing_count_profile = count($missing_fields);

// Large site guard: if > 400 synced items, skip the expensive synchronous PHP processing
// that builds the architecture map, hub detection, orphan analysis etc.
// These features rely on loading all pages and doing N² meta queries — fatal on 700+ item sites.
// Dashboard will still render; the SEO/GEO Plan tab shows a "Generate Plan" CTA instead.
$_total_synced_est = (int) get_option( 'siloq_synced_page_count', count( $synced_pages ) );
define( 'SILOQ_LARGE_SITE_MODE', $_total_synced_est > 400 );

// Build hub data: pages marked as hub in analysis, OR pages that have child pages
// Cap at 200 for dashboard hub/architecture detection — large WC sites have 700+ products
// which makes N² loops too slow for synchronous page render. Full data available via AJAX/API.
$all_synced_pages = SILOQ_LARGE_SITE_MODE ? array() : get_posts(array(
    'post_type'          => array('page', 'post'), // pages/posts only for hub detection; products don't have hub structure
    'post_status'        => 'publish',
    'posts_per_page'     => 200,
    'orderby'            => 'menu_order',
    'order'              => 'ASC',
    'meta_query'         => array(array('key' => '_siloq_synced', 'compare' => 'EXISTS')),
));
// Fetch silo map from API — more accurate than WP post_parent detection
$api_silos = array();
$api_silo_page_ids = array(); // WP post IDs already assigned to API silos
$site_id_opt = get_option( 'siloq_site_id', '' );
$api_key_opt = get_option( 'siloq_api_key', '' );
$api_url_opt = get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' );

if ( ! empty( $site_id_opt ) && ! empty( $api_key_opt ) ) {
    $silo_map_url = trailingslashit( $api_url_opt ) . 'sites/' . $site_id_opt . '/silo-map/';
    $resp = wp_remote_get( $silo_map_url, array(
        'headers' => array( 'Authorization' => 'Bearer ' . $api_key_opt ),
        'timeout' => 10,
    ));
    if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( ! empty( $body['silos'] ) ) {
            foreach ( $body['silos'] as $api_silo ) {
                $hub_wp_id = 0;
                $hub_url = isset( $api_silo['pillar_page']['url'] ) ? $api_silo['pillar_page']['url'] : '';
                // Match hub URL to a WP post ID
                if ( $hub_url ) {
                    $hub_post = get_page_by_path( trim( parse_url( $hub_url, PHP_URL_PATH ), '/' ) );
                    if ( $hub_post ) {
                        $hub_wp_id = $hub_post->ID;
                    } else {
                        // fallback: url_to_postid
                        $hub_wp_id = url_to_postid( $hub_url );
                    }
                }

                // Build children array from supporting_pages
                $children_posts = array();
                foreach ( $api_silo['supporting_pages'] ?? array() as $sp ) {
                    $sp_url = $sp['url'] ?? '';
                    if ( ! $sp_url ) continue;
                    $sp_id = url_to_postid( $sp_url );
                    if ( $sp_id ) {
                        $post_obj = get_post( $sp_id );
                        if ( $post_obj ) {
                            $children_posts[] = $post_obj;
                            $api_silo_page_ids[] = $sp_id;
                        }
                    }
                }

                if ( $hub_wp_id || count( $children_posts ) > 0 ) {
                    $api_silo_page_ids[] = $hub_wp_id;
                    // Stamp hub WP post with role so SEO/GEO Plan local detection picks it up
                    if ( $hub_wp_id ) {
                        update_post_meta( $hub_wp_id, '_siloq_page_role', 'hub' );
                    }
                    $api_silos[] = array(
                        'id'       => $hub_wp_id ?: 0,
                        'title'    => $api_silo['name'] ?? 'Silo',
                        'url'      => $hub_url,
                        'edit_url' => $hub_wp_id ? get_edit_post_link( $hub_wp_id, 'raw' ) : '',
                        'elementor_url' => $hub_wp_id ? admin_url( 'post.php?post=' . $hub_wp_id . '&action=elementor' ) : '',
                        'score'    => intval( $api_silo['coverage_score'] ?? 70 ),
                        'children' => $children_posts,
                        'missing'  => array(),
                        'keyword'  => '',
                        'parent_mismatch_ids' => array(),
                    );
                }
            }
        }
    }
}

$hub_data = array();
$non_hub_ids = array();

// ── WooCommerce category hubs (ecommerce sites) ──────────────────────────────
// WC product categories are taxonomy terms, not wp_posts — they never appear in
// $all_synced_pages. Detect them separately and inject as hub_data entries.
$_plan_biz_type_arch = get_option( 'siloq_business_type', 'general' );
if ( class_exists( 'WooCommerce' ) && in_array( $_plan_biz_type_arch, array( 'ecommerce', 'general' ), true ) ) {
    $wc_top_cats = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
        'exclude'    => array( get_option( 'default_product_cat', 0 ) ), // skip Uncategorized
    ) );
    if ( ! is_wp_error( $wc_top_cats ) && ! empty( $wc_top_cats ) ) {
        foreach ( $wc_top_cats as $_wc_cat ) {
            $cat_link    = get_term_link( $_wc_cat );
            $child_terms = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $_wc_cat->term_id, 'hide_empty' => false ) );
            $child_count = is_wp_error( $child_terms ) ? 0 : count( $child_terms );
            // Also count products in this category
            $prod_count  = (int) $_wc_cat->count;
            if ( $child_count > 0 || $prod_count > 0 ) {
                $hub_data[] = array(
                    'id'                  => 'cat_' . $_wc_cat->term_id,
                    'title'               => $_wc_cat->name,
                    'url'                 => $cat_link,
                    'edit_url'            => admin_url( 'term.php?taxonomy=product_cat&tag_ID=' . $_wc_cat->term_id ),
                    'elementor_url'       => '',
                    'score'               => 0,
                    'children'            => array(), // products listed separately
                    'missing'             => array(),
                    'keyword'             => strtolower( $_wc_cat->name ),
                    'parent_mismatch_ids' => array(),
                    'wc_cat'              => true,
                    'wc_term_id'          => $_wc_cat->term_id,
                    'wc_child_cats'       => $child_count,
                    'wc_product_count'    => $prod_count,
                );
            }
        }
    }
}
// ── /WooCommerce category hubs ────────────────────────────────────────────────

// Filter: exclude internal post type names and JetEngine/CPT slugs from hub detection
$internal_name_patterns = array('koops','grid','template','listing','loop','jet-','acf-','pods-','dynamic-','_post_type');
$non_page_slugs_filter  = array('attachment','revision','nav_menu_item','custom_css','customize_changeset');

foreach ($all_synced_pages as $hp) {
    // Skip pages whose title or slug looks like a JetEngine CPT or internal post type
    $hp_slug  = $hp->post_name;
    $hp_title = strtolower($hp->post_title);
    $is_cpt_name = false;
    if (in_array($hp_slug, $non_page_slugs_filter, true)) { $is_cpt_name = true; }
    if (!$is_cpt_name) {
        foreach ($internal_name_patterns as $pat) {
            if (strpos($hp_title, $pat) !== false || strpos($hp_slug, $pat) !== false) {
                $is_cpt_name = true;
                break;
            }
        }
    }
    if ($is_cpt_name) { $non_hub_ids[] = $hp->ID; continue; }

    $analysis_raw = get_post_meta($hp->ID, '_siloq_analysis_data', true);
    $analysis = is_array($analysis_raw) ? $analysis_raw : (is_string($analysis_raw) ? json_decode($analysis_raw, true) : array());
    $page_type = isset($analysis['page_type_classification']) ? $analysis['page_type_classification'] : '';
    // Primary: WP post_parent children
    $children = get_posts(array(
        'post_type'      => 'any',
        'post_parent'    => $hp->ID,
        'post_status'    => 'publish',
        'posts_per_page' => 100,
    ));

    // Secondary: URL-path children (handles /our-services/ vs /services/ mismatch)
    $hub_path      = trim( parse_url( get_permalink( $hp->ID ), PHP_URL_PATH ), '/' );
    $url_children  = array();
    $parent_mismatch_ids = array();
    if ( $hub_path ) {
        foreach ( $all_synced_pages as $candidate ) {
            if ( $candidate->ID === $hp->ID ) continue;
            $candidate_path = trim( parse_url( get_permalink( $candidate->ID ), PHP_URL_PATH ), '/' );
            $candidate_segments = explode( '/', $candidate_path );
            if ( count( $candidate_segments ) >= 2 ) {
                if ( strpos( $candidate_path, $hub_path . '/' ) === 0 ) {
                    $already_child = false;
                    foreach ( $children as $c ) { if ( $c->ID === $candidate->ID ) { $already_child = true; break; } }
                    if ( ! $already_child ) {
                        $url_children[] = $candidate;
                        $parent_mismatch_ids[] = $candidate->ID;
                    }
                }
            }
        }
    }
    $all_children  = array_merge( $children, $url_children );
    $is_hub = ($page_type === 'hub') || ($page_type === 'apex_hub') || (count($all_children) > 0 && $hp->post_parent == 0);
    if (!$is_hub) { $non_hub_ids[] = $hp->ID; continue; }
    $score = isset($analysis['score']) ? intval($analysis['score']) : 0;
    $missing_supporting = isset($analysis['missing_supporting']) ? (array)$analysis['missing_supporting'] : array();
    $hub_data[] = array(
        'id'                   => $hp->ID,
        'title'                => get_the_title($hp->ID),
        'url'                  => get_permalink($hp->ID),
        'edit_url'             => get_edit_post_link($hp->ID, 'raw'),
        'elementor_url'        => admin_url('post.php?post=' . $hp->ID . '&action=elementor'),
        'score'                => $score,
        'children'             => $all_children,
        'missing'              => $missing_supporting,
        'keyword'              => isset($analysis['primary_keyword']) ? $analysis['primary_keyword'] : '',
        'parent_mismatch_ids'  => $parent_mismatch_ids,
    );
}
// If no hubs found (flat site), use top-level pages as hubs
if (empty($hub_data)) {
    foreach ($all_synced_pages as $hp) {
        if ($hp->post_parent != 0) continue;
        // Apply the same CPT/internal post type filter as the main loop above
        $fb_slug  = $hp->post_name;
        $fb_title = strtolower($hp->post_title);
        $fb_skip  = false;
        foreach ($internal_name_patterns as $pat) {
            if (strpos($fb_title, $pat) !== false || strpos($fb_slug, $pat) !== false) {
                $fb_skip = true; break;
            }
        }
        if ($fb_skip) continue;
        $analysis_raw = get_post_meta($hp->ID, '_siloq_analysis_data', true);
        $analysis = is_array($analysis_raw) ? $analysis_raw : (is_string($analysis_raw) ? json_decode($analysis_raw, true) : array());
        $score = isset($analysis['score']) ? intval($analysis['score']) : 0;
        $missing_supporting = isset($analysis['missing_supporting']) ? (array)$analysis['missing_supporting'] : array();
        $hub_data[] = array(
            'id'           => $hp->ID,
            'title'        => get_the_title($hp->ID),
            'url'          => get_permalink($hp->ID),
            'edit_url'     => get_edit_post_link($hp->ID, 'raw'),
            'elementor_url'=> admin_url('post.php?post=' . $hp->ID . '&action=elementor'),
            'score'        => $score,
            'children'     => array(),
            'missing'      => $missing_supporting,
            'keyword'      => isset($analysis['primary_keyword']) ? $analysis['primary_keyword'] : '',
        );
    }
}

// If API returned silos, use those instead of WP-detected hubs
if ( ! empty( $api_silos ) ) {
    $hub_data = $api_silos;
}

// ── Reposition check: location pages filed under services hub ──
$service_cities = json_decode( get_option('siloq_service_cities', '[]'), true );
if (!is_array($service_cities)) $service_cities = array();
$service_areas = json_decode( get_option('siloq_service_areas', '[]'), true );
if (!is_array($service_areas)) $service_areas = array();
$all_cities = array_unique(array_merge($service_cities, $service_areas));

// Clear stale reposition flags before re-evaluating — flags are only valid for current page load
$_stale_flagged = get_posts(array(
    'post_type'      => array('page', 'post'),
    'post_status'    => 'publish',
    'posts_per_page' => 200,
    'meta_query'     => array(array('key' => '_siloq_reposition_flag', 'compare' => 'EXISTS')),
    'fields'         => 'ids',
));
foreach ($_stale_flagged as $_sfid) {
    delete_post_meta($_sfid, '_siloq_reposition_flag');
}
unset($_stale_flagged, $_sfid);

foreach ($hub_data as $hub) {
    $hub_url = strtolower($hub['url']);
    if (strpos($hub_url, 'service-area') !== false || strpos($hub_url, 'locations') !== false) continue;

    // Find the service-areas hub (for reposition target)
    $sa_hub_id = null;
    foreach ($hub_data as $h2) {
        if (strpos(strtolower($h2['url']), 'service-area') !== false || strpos(strtolower($h2['url']), 'locations') !== false) {
            $sa_hub_id = $h2['id'];
            break;
        }
    }

    foreach ($hub['children'] as $child) {
        $child_title = $child->post_title;
        foreach ($all_cities as $city) {
            $city = trim($city);
            if (empty($city)) continue;
            if (stripos($child_title, $city) !== false) {
                update_post_meta($child->ID, '_siloq_reposition_flag', array(
                    'reason'        => 'location_under_services',
                    'city'          => $city,
                    'current_hub'   => $hub['id'],
                    'target_hub_id' => $sa_hub_id,
                    'flagged_at'    => current_time('mysql'),
                ));
                break; // one flag per page
            }
        }
    }
}

// ── Rename recommendation: service page title contains city = cannibalization risk ──
$primary_services = json_decode( get_option('siloq_primary_services', '[]'), true );
if (!is_array($primary_services)) $primary_services = array();

foreach ($hub_data as $hub) {
    $hub_url = strtolower($hub['url']);
    if (strpos($hub_url, 'service-area') !== false) continue;

    foreach ($hub['children'] as $child) {
        $child_title = $child->post_title;

        // Check if title has a city name
        $city_in_title = null;
        foreach ($all_cities as $city) {
            $city = trim($city);
            if (empty($city)) continue;
            if (stripos($child_title, $city) !== false) {
                $city_in_title = $city;
                break;
            }
        }
        if (!$city_in_title) continue;

        // Check if a dedicated city page exists for this city
        $city_page_exists = false;
        foreach ($all_synced_pages as $p) {
            $p_meta = get_post_meta($p->ID, '_siloq_page_type', true);
            if ($p_meta === 'city' || $p_meta === 'service_area') {
                if (stripos($p->post_title, $city_in_title) !== false) {
                    $city_page_exists = true;
                    break;
                }
            }
        }

        // Build suggested rename: strip city name + MO/state suffix
        $suggested = preg_replace('/\s+(serving|in|near)?\s+' . preg_quote($city_in_title, '/') . '(\s*,?\s*(MO|KS|Kansas City|Missouri))?/i', '', $child_title);
        $suggested = trim($suggested);

        update_post_meta($child->ID, '_siloq_rename_suggestion', array(
            'reason'          => 'city_in_service_title',
            'city'            => $city_in_title,
            'current_title'   => $child_title,
            'suggested_title' => $suggested ?: $child_title,
            'city_page_exists'=> $city_page_exists,
            'flagged_at'      => current_time('mysql'),
        ));
    }
}

// Build menu link map once (not per-page)
$menu_linked_ids = array();
$all_menus = wp_get_nav_menus();
foreach ($all_menus as $menu_obj) {
    $menu_items = wp_get_nav_menu_items($menu_obj->term_id) ?: array();
    foreach ($menu_items as $mi) { $menu_linked_ids[intval($mi->object_id)] = true; }
}
// Homepage never an orphan
$hp_id = intval(get_option('page_on_front'));
if ($hp_id) $menu_linked_ids[$hp_id] = true;

// Collect all hub + child ids
$hub_child_ids = array();
foreach ($hub_data as $h) {
    $hub_child_ids[] = $h['id'];
    foreach ($h['children'] as $c) $hub_child_ids[] = $c->ID;
}

// Build a set of permalink paths that appear as href targets in any published page content.
// This prevents city pages linked inside hub body content from being falsely flagged as orphans.
$content_linked_paths = array();
$all_published = get_posts(array('post_type' => array('page', 'post'), 'post_status' => 'publish', 'posts_per_page' => 200, 'fields' => 'ids'));
foreach ($all_published as $_pid) {
    $_p = get_post($_pid);
    if (!$_p || empty($_p->post_content)) continue;
    if (preg_match_all('/href=["\']([^"\']+)["\']/i', $_p->post_content, $_m)) {
        foreach ($_m[1] as $_href) {
            $_parsed = parse_url($_href);
            if (!empty($_parsed['path'])) {
                $content_linked_paths[rtrim($_parsed['path'], '/')] = true;
            }
        }
    }
}

$all_synced_ids = wp_list_pluck($all_synced_pages, 'ID');
$true_orphan_posts = array();
foreach ($all_synced_ids as $oid) {
    if (in_array($oid, $hub_child_ids)) continue;
    if (isset($menu_linked_ids[$oid])) continue;
    // Check if this page's permalink path appears in any published content
    $_permalink_path = rtrim(parse_url(get_permalink($oid), PHP_URL_PATH), '/');
    if (!empty($_permalink_path) && isset($content_linked_paths[$_permalink_path])) continue;
    $true_orphan_posts[] = $oid;
}

// Pre-compute expected linker (hub) for each orphan page
foreach ($true_orphan_posts as $oid) {
    $expected = 0;
    // 1. Direct parent
    $parent_id = wp_get_post_parent_id($oid);
    if ($parent_id && get_post_status($parent_id) === 'publish') {
        $expected = $parent_id;
    }
    // 2. Find most relevant hub page by title similarity
    if (!$expected && !empty($hub_data)) {
        $spoke_title = strtolower(get_the_title($oid));
        $best_score = 0;
        foreach ($hub_data as $h) {
            $role = get_post_meta($h['id'], '_siloq_page_role', true);
            if (!in_array($role, array('hub', 'apex_hub', 'pillar', ''), true) && $role !== false) continue;
            $hub_title = strtolower($h['title']);
            $hub_kw    = strtolower($h['keyword']);
            $score = 0;
            // Check keyword match
            if ($hub_kw && strpos($spoke_title, $hub_kw) !== false) {
                $score += 10;
            }
            // Check title word overlap
            $hub_words = array_filter(explode(' ', preg_replace('/[^a-z0-9 ]/', '', $hub_title)));
            foreach ($hub_words as $w) {
                if (strlen($w) > 2 && strpos($spoke_title, $w) !== false) $score += 2;
            }
            if ($score > $best_score) {
                $best_score = $score;
                $expected = $h['id'];
            }
        }
        // If no keyword/title match, use first hub as fallback
        if (!$expected) {
            $expected = $hub_data[0]['id'];
        }
    }
    if ($expected) {
        update_post_meta($oid, '_siloq_expected_linker_id', $expected);
    }
}

// Score label
if ($site_score >= 90) { $score_grade = 'Excellent!'; $score_color_cls = 'teal'; }
elseif ($site_score >= 75) { $score_grade = 'Good · Room to Grow'; $score_color_cls = 'teal'; }
elseif ($site_score >= 50) { $score_grade = 'Needs Improvement'; $score_color_cls = 'amber'; }
else { $score_grade = 'Needs Attention'; $score_color_cls = 'red'; }

$score_dashoffset = round(314 * (1 - ($site_score / 100)));

// GSC data
$gsc_impr_28d = intval(get_option('siloq_gsc_impressions_28d', get_option('siloq_gsc_impressions', 0)));
$gsc_clicks_28d = intval(get_option('siloq_gsc_clicks_28d', get_option('siloq_gsc_clicks', 0)));
$gsc_avg_pos = floatval(get_option('siloq_gsc_avg_position', 0));
$gsc_keywords = json_decode(get_option('siloq_gsc_top_keywords', '[]'), true) ?: array();

// Pages needing attention — top 7 from plan issues
$attention_pages = array();
if ($has_plan && isset($plan_data['issues'])) {
    foreach (array('critical','important','opportunity') as $sev) {
        if (!isset($plan_data['issues'][$sev])) continue;
        foreach ($plan_data['issues'][$sev] as $iss) {
            if (!isset($attention_pages[$iss['post_id']])) {
                $pid = $iss['post_id'];
                $an_raw = get_post_meta($pid, '_siloq_analysis_data', true);
                $an = is_array($an_raw) ? $an_raw : (is_string($an_raw) ? json_decode($an_raw, true) : array());
                $sc = isset($an['score']) ? intval($an['score']) : 0;
                $attention_pages[$pid] = array(
                    'title'        => get_the_title($pid),
                    'issue'        => $iss['issue'],
                    'score'        => $sc,
                    'severity'     => $sev,
                    'post_id'      => $pid,
                    'fix_type'     => $iss['fix_type'] ?? 'link',
                    'fix_category' => $iss['fix_category'] ?? 'content',
                    // Only use elementor_url for content issues; meta issues always use WP editor
                    'elementor_url'=> (isset($iss['fix_type']) && in_array($iss['fix_type'], ['meta_title','meta_description']))
                        ? get_edit_post_link($pid, 'raw')
                        : (isset($iss['elementor_url']) ? $iss['elementor_url'] : admin_url('post.php?post=' . $pid . '&action=elementor')),
                );
            }
        }
        if (count($attention_pages) >= 7) break;
    }
}
?>

<?php if ($missing_count_profile > 0): ?>
<div class="siloq-alert-profile">
  <span style="font-size:16px;flex-shrink:0">&#9888;&#65039;</span>
  <div class="siloq-alert-profile-t">Business profile missing <?php echo $missing_count_profile; ?> field<?php echo $missing_count_profile > 1 ? 's' : ''; ?> — reduces AI citation readiness and schema accuracy across all pages.</div>
  <a href="<?php echo esc_url(admin_url('admin.php?page=siloq-settings')); ?>" class="siloq-alert-profile-cta">Complete Profile &rarr;</a>
</div>
<?php endif; ?>

<!-- Hero: Score + Plan -->
<div class="siloq-hero-grid">
  <div class="siloq-score-card">
    <div class="siloq-score-label">Overall SEO/GEO Health</div>
    <div class="siloq-score-ring-wrap">
      <svg width="110" height="110" viewBox="0 0 110 110">
        <circle fill="none" stroke="#e5e7eb" stroke-width="10" cx="55" cy="55" r="45"/>
        <circle fill="none" stroke="#0d9488" stroke-width="10" stroke-linecap="round"
          cx="55" cy="55" r="45"
          stroke-dasharray="283"
          stroke-dashoffset="<?php echo esc_attr(round(283 * (1 - $site_score / 100))); ?>"
          style="filter:drop-shadow(0 0 5px rgba(13,148,136,.35));transition:stroke-dashoffset 1.5s ease"/>
      </svg>
      <div class="siloq-score-num"><?php echo $site_score ?: '--'; ?></div>
    </div>
    <div class="siloq-score-grade"><?php echo esc_html($score_grade); ?></div>
    <div class="siloq-score-desc"><?php echo esc_html($score_sentence); ?></div>
    <?php if ($has_plan && !empty($plan_data['actions'])): $top_action = $plan_data['actions'][0]; ?>
    <button class="siloq-score-cta" onclick="document.querySelector('[aria-controls=\'siloq-tab-plan\']').click()">
      <div class="siloq-score-cta-s">&#128293; Highest impact action right now</div>
      <div class="siloq-score-cta-m"><?php echo esc_html(isset($top_action['headline']) ? $top_action['headline'] : 'View your action plan'); ?> &rarr;</div>
    </button>
    <?php else: ?>
    <button class="siloq-score-cta" onclick="document.querySelector('[aria-controls=\'siloq-tab-plan\']').click()">
      <div class="siloq-score-cta-s">&#128203; Get started</div>
      <div class="siloq-score-cta-m">Generate your SEO/GEO Plan &rarr;</div>
    </button>
    <?php endif; ?>
  </div>

  <div class="siloq-plan-card">
    <div class="siloq-plan-card-hdr">
      <div>
        <div class="siloq-plan-card-title">&#128203; Your 90-Day SEO/GEO Plan</div>
        <div class="siloq-plan-card-sub">Personalized to <?php echo esc_html(get_option('siloq_business_name', get_bloginfo('name'))); ?></div>
      </div>
      <button class="siloq-btn siloq-btn--outline" style="font-size:11px;padding:5px 10px" onclick="document.querySelector('[aria-controls=\'siloq-tab-plan\']').click()">View Full Plan</button>
    </div>
    <?php
    $completed_count = 0; $urgent_count = 0; $inprog_count = 0;
    if ($has_plan && isset($plan_data['actions'])) {
        foreach ($plan_data['actions'] as $a) {
            if ($a['priority'] === 'high') $urgent_count++;
            else $inprog_count++;
        }
    }
    if (isset($plan_data['issues']['critical'])) $urgent_count += count($plan_data['issues']['critical']);
    ?>
    <div class="siloq-plan-stats3">
      <div class="siloq-plan-stat"><div class="siloq-plan-stat-n teal"><?php echo intval(count($roadmap_progress)); ?></div><div class="siloq-plan-stat-l">Completed</div></div>
      <div class="siloq-plan-stat"><div class="siloq-plan-stat-n amber"><?php echo $inprog_count; ?></div><div class="siloq-plan-stat-l">In Progress</div></div>
      <div class="siloq-plan-stat"><div class="siloq-plan-stat-n red"><?php echo $urgent_count; ?></div><div class="siloq-plan-stat-l">Urgent</div></div>
    </div>
    <div class="siloq-prog-row">
      <div class="siloq-prog-labels"><span class="siloq-prog-label">Month 1 — Quick Wins</span><span class="siloq-prog-pct"><?php echo min(100, intval(count($roadmap_progress) * 10)); ?>%</span></div>
      <div class="siloq-prog-bar"><div class="siloq-prog-fill" style="width:<?php echo min(100, intval(count($roadmap_progress) * 10)); ?>%"></div></div>
    </div>
    <div class="siloq-prog-row">
      <div class="siloq-prog-labels"><span class="siloq-prog-label">Month 2 — Content Build</span><span class="siloq-prog-pct" style="color:#d97706">0%</span></div>
      <div class="siloq-prog-bar"><div class="siloq-prog-fill" style="width:0%;background:linear-gradient(90deg,#d97706,#f59e0b)"></div></div>
    </div>
    <div class="siloq-prog-row" style="margin-bottom:10px">
      <div class="siloq-prog-labels"><span class="siloq-prog-label">Month 3 — Authority Growth</span><span class="siloq-prog-pct" style="color:#9ca3af">0%</span></div>
      <div class="siloq-prog-bar"><div class="siloq-prog-fill" style="width:0%"></div></div>
    </div>
    <div style="display:flex;gap:5px;flex-wrap:wrap">
      <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#f0fdf4;color:#16a34a">&#10003; Setup Complete</span>
      <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff">&#9679; Month 1 — Active</span>
      <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#f0f2f7;color:#6b7280">Month 2</span>
    </div>
  </div>
</div>

<!-- 3 Insight Cards -->
<div class="siloq-cards3">

  <!-- Business Profile -->
  <div class="siloq-insight-card">
    <div class="siloq-ic-hdr">
      <div class="siloq-ic-title-group"><div class="siloq-ic-icon teal">&#127970;</div><div class="siloq-ic-title">Business Profile</div></div>
      <a href="<?php echo esc_url(admin_url('admin.php?page=siloq-settings')); ?>" class="siloq-ic-link">Edit &rarr;</a>
    </div>
    <div class="siloq-entity-ring-row">
      <div class="siloq-entity-ring-wrap">
        <svg width="52" height="52" viewBox="0 0 52 52">
          <circle cx="26" cy="26" r="20" fill="none" stroke="#e5e7eb" stroke-width="7"/>
          <circle cx="26" cy="26" r="20" fill="none" stroke="#0d9488" stroke-width="7" stroke-linecap="round"
            stroke-dasharray="125.7"
            stroke-dashoffset="<?php echo round(125.7 * (1 - $entity_pct / 100)); ?>"
            transform="rotate(-90 26 26)"/>
        </svg>
        <div class="siloq-entity-ring-num"><?php echo $entity_pct; ?>%</div>
      </div>
      <div>
        <div class="siloq-entity-ring-label">AI Citation Readiness</div>
        <div class="siloq-entity-ring-sub">Complete your profile so AI tools can find and cite your business</div>
      </div>
    </div>
    <div class="siloq-entity-fields">
      <?php foreach (array_slice($profile_fields, 0, 4) as $field): ?>
      <div class="siloq-entity-field">
        <div class="siloq-field-dot <?php echo !empty($field['filled']) ? 'good' : 'bad'; ?>"></div>
        <div class="siloq-field-name"><?php echo esc_html($field['label']); ?></div>
        <?php if (empty($field['filled'])): ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=siloq-settings')); ?>" class="siloq-field-action">Add &rarr;</a>
        <?php else: ?>
        <span style="font-size:10px;color:#16a34a">&#10003;</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Search Performance -->
  <div class="siloq-insight-card">
    <div class="siloq-ic-hdr">
      <div class="siloq-ic-title-group"><div class="siloq-ic-icon indigo">&#128202;</div><div class="siloq-ic-title">Search Performance</div></div>
      <a href="#" onclick="var btn=document.querySelector('[aria-controls=\'siloq-tab-gsc\']'); if(btn){btn.click();} return false;" class="siloq-ic-link" style="color:#4f46e5;font-size:12px;font-weight:500;">Full Report &rarr;</a>
    </div>
    <?php if ($gsc_impr_28d > 0): ?>
    <div class="siloq-gsc-grid">
      <div class="siloq-gsc-metric"><div class="siloq-gsc-val"><?php echo number_format($gsc_impr_28d); ?></div><div class="siloq-gsc-lbl">Impressions</div></div>
      <div class="siloq-gsc-metric"><div class="siloq-gsc-val"><?php echo number_format($gsc_clicks_28d); ?></div><div class="siloq-gsc-lbl">Clicks (28d)</div></div>
      <div class="siloq-gsc-metric"><div class="siloq-gsc-val"><?php echo $gsc_impr_28d > 0 ? round($gsc_clicks_28d/$gsc_impr_28d*100,1) . '%' : '--'; ?></div><div class="siloq-gsc-lbl">Avg CTR</div></div>
      <div class="siloq-gsc-metric"><div class="siloq-gsc-val"><?php echo $gsc_avg_pos > 0 ? number_format($gsc_avg_pos,1) : '--'; ?></div><div class="siloq-gsc-lbl">Avg Position</div></div>
    </div>
    <div class="siloq-spark">
      <?php for ($i = 0; $i < 14; $i++): $h = rand(30,100); ?>
      <div class="siloq-spark-bar" style="height:<?php echo $h; ?>%"></div>
      <?php endfor; ?>
    </div>
    <?php if (!empty($gsc_keywords)): ?>
      <?php foreach (array_slice($gsc_keywords, 0, 3) as $kw): ?>
      <div class="siloq-kw-row">
        <span class="siloq-kw-t"><?php echo esc_html($kw['keyword'] ?? $kw['query'] ?? ''); ?></span>
        <span class="siloq-kw-pos">#<?php echo intval($kw['position'] ?? $kw['rank'] ?? 0); ?></span>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php else: ?>
    <div style="text-align:center;padding:20px 0;color:#6b7280">
      <div style="font-size:28px;margin-bottom:8px">&#128202;</div>
      <div style="font-size:12px;margin-bottom:10px">Connect Google Search Console to see your ranking data</div>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
        <?php wp_nonce_field('siloq_gsc_connect_nonce'); ?>
        <input type="hidden" name="action" value="siloq_gsc_connect">
        <button type="submit" class="siloq-btn siloq-btn--primary" style="font-size:11px">Connect GSC &rarr;</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <!-- Silo Architecture Summary -->
  <div class="siloq-insight-card">
    <div class="siloq-ic-hdr">
      <div class="siloq-ic-title-group"><div class="siloq-ic-icon amber">&#128450;</div><div class="siloq-ic-title">Silo Architecture</div></div>
      <span class="siloq-ic-link" style="cursor:pointer" onclick="document.getElementById('siloq-arch-map-section').scrollIntoView({behavior:'smooth'})">Full Map &#8595;</span>
    </div>
    <div class="siloq-arch-stats3">
      <div class="siloq-arch-stat"><div class="siloq-arch-stat-n green"><?php echo count($hub_data); ?></div><div class="siloq-arch-stat-l">Hubs</div></div>
      <div class="siloq-arch-stat"><div class="siloq-arch-stat-n amber"><?php echo $missing_count; ?></div><div class="siloq-arch-stat-l">Missing</div></div>
      <div class="siloq-arch-stat"><div class="siloq-arch-stat-n red"><?php echo count($true_orphan_posts); ?></div><div class="siloq-arch-stat-l">Orphans</div></div>
    </div>
    <?php foreach (array_slice($hub_data, 0, 4) as $h):
      $total_pages = count($h['children']) + count($h['missing']);
      $live_pages = count($h['children']);
      $pct = $total_pages > 0 ? round($live_pages / $total_pages * 100) : 0;
      $prog_color = $pct >= 80 ? '#16a34a' : ($pct >= 40 ? '#d97706' : '#dc2626');
      $prog_cls = $pct >= 80 ? 'green' : ($pct >= 40 ? 'amber' : 'red');
    ?>
    <div class="siloq-hub-mini">
      <div class="siloq-hub-mini-row">
        <div class="siloq-hub-mini-name"><?php echo esc_html($h['title']); ?></div>
        <div class="siloq-hub-mini-pct <?php echo $prog_cls; ?>"><?php echo $live_pages; ?>/<?php echo max(1,$total_pages); ?></div>
      </div>
      <div class="siloq-hub-mini-bar"><div class="siloq-hub-mini-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $prog_color; ?>"></div></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Entity Readiness / Blueprint Score Card -->
<?php
$_site_id_opt = get_option('siloq_site_id','');
$_api_key_opt = get_option('siloq_api_key','');
$_bp_data     = array();
if ( $_site_id_opt && $_api_key_opt ) {
    $api_url_opt = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
    $_bp_resp    = wp_remote_get(
        trailingslashit($api_url_opt) . 'sites/' . $_site_id_opt . '/blueprint/',
        array('headers' => array('Authorization' => 'Bearer ' . $_api_key_opt), 'timeout' => 8)
    );
    if (!is_wp_error($_bp_resp) && wp_remote_retrieve_response_code($_bp_resp) === 200) {
        $_bp_data = json_decode(wp_remote_retrieve_body($_bp_resp), true) ?: array();
    }
}
$_er_score = isset($_bp_data['entity_readiness_score']) ? intval($_bp_data['entity_readiness_score']) : null;
$_os_scores = $_bp_data['os_scores'] ?? array();
$_bp_actions = $_bp_data['blueprint_actions'] ?? array();
$_critical_count = count(array_filter($_bp_actions, fn($a) => ($a['severity']??'') === 'CRITICAL'));
?>
<?php if ( $_er_score !== null ): ?>
<div class="siloq-card" style="margin-bottom:16px;padding:20px;border-left:4px solid <?php echo $_er_score >= 70 ? '#0d9488' : ($_er_score >= 50 ? '#d97706' : '#dc2626'); ?>">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
        <div>
            <div style="font-size:14px;font-weight:700;">&#127919; Entity Readiness Score</div>
            <div style="font-size:11px;color:#6b7280;margin-top:2px;">How clearly Google classifies this entity</div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="font-size:32px;font-weight:700;color:<?php echo $_er_score >= 70 ? '#0d9488' : ($_er_score >= 50 ? '#d97706' : '#dc2626'); ?>"><?php echo $_er_score; ?><span style="font-size:14px;color:#9ca3af;">/100</span></div>
        </div>
    </div>
    <?php if (!empty($_os_scores)): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;margin-bottom:12px;">
        <?php
        $os_labels = array(
            'entity_os'=>'EntityOS','diagnostic_os'=>'DiagnosticOS','drift_os'=>'DriftOS',
            'page_os'=>'PageOS','credential_os'=>'CredentialOS','rewrite_os'=>'RewriteOS','vision_os'=>'VisionOS'
        );
        foreach ($os_labels as $key => $label):
            if (!isset($_os_scores[$key])) continue;
            $s = intval($_os_scores[$key]);
            $c = $s >= 70 ? '#0d9488' : ($s >= 50 ? '#d97706' : '#dc2626');
        ?>
        <div style="text-align:center;padding:8px;border-radius:6px;border:1px solid #e5e7eb;">
            <div style="font-size:18px;font-weight:700;color:<?php echo $c; ?>"><?php echo $s; ?></div>
            <div style="font-size:10px;color:#6b7280;margin-top:2px;"><?php echo $label; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($_critical_count > 0): ?>
    <div style="font-size:12px;color:#dc2626;font-weight:600;margin-bottom:8px;">&#9888; <?php echo $_critical_count; ?> CRITICAL gap<?php echo $_critical_count !== 1 ? 's' : ''; ?> blocking classifier confidence</div>
    <?php endif; ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=siloq-content-plan')); ?>" class="siloq-btn siloq-btn--primary siloq-btn--sm">View Recommendations &rarr;</a>
        <button type="button" class="siloq-btn siloq-btn--outline siloq-btn--sm" id="siloq-run-blueprint-btn">Refresh Analysis</button>
    </div>
</div>
<script>
(function($){
    $('#siloq-run-blueprint-btn').on('click', function(){
        var $btn = $(this).prop('disabled',true).text('Running...');
        $.post(ajaxurl, {
            action: 'siloq_run_blueprint_analysis',
            nonce: '<?php echo esc_js(wp_create_nonce("siloq_ajax_nonce")); ?>'
        }, function(r){
            $btn.prop('disabled',false).text('Refresh Analysis');
            if(r.success) location.reload();
            else alert('Analysis failed: ' + (r.data && r.data.message ? r.data.message : 'Unknown error'));
        }).fail(function(){ $btn.prop('disabled',false).text('Refresh Analysis'); });
    });
})(jQuery);
</script>
<?php endif; ?>

<!-- Image Audit -->
<?php
$img_audit_raw = get_option('siloq_image_audit_results', '');
$img_audit_items = $img_audit_raw ? json_decode($img_audit_raw, true) : array();
?>
<div class="siloq-card" style="margin-bottom:16px;padding:20px">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px">
    <div>
      <div style="font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px">&#128247; Image Audit</div>
    </div>
    <?php if (!empty($img_audit_items)): ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=siloq-image-brief')); ?>" class="siloq-btn siloq-btn--outline" style="font-size:11px;padding:5px 10px">View Image Brief &rarr;</a>
    <?php endif; ?>
  </div>
  <?php if (empty($img_audit_items)): ?>
    <div style="font-size:12px;color:#6b7280">Run a full sync to generate your image audit.</div>
  <?php else:
    $img_counts = array('no_images' => 0, 'stock_photo' => 0, 'missing_alt' => 0, 'unoptimized' => 0, 'good' => 0);
    foreach ($img_audit_items as $ia) { $s = $ia['status'] ?? 'good'; if (isset($img_counts[$s])) $img_counts[$s]++; }
  ?>
    <div style="display:flex;flex-wrap:wrap;gap:14px;font-size:12px">
      <?php if ($img_counts['no_images']): ?>
      <span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:50%;background:#dc2626;display:inline-block"></span> <?php echo $img_counts['no_images']; ?> No Images</span>
      <?php endif; ?>
      <?php if ($img_counts['stock_photo']): ?>
      <span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:50%;background:#d97706;display:inline-block"></span> <?php echo $img_counts['stock_photo']; ?> Stock</span>
      <?php endif; ?>
      <?php if ($img_counts['missing_alt']): ?>
      <span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:50%;background:#d97706;display:inline-block"></span> <?php echo $img_counts['missing_alt']; ?> Missing Alt</span>
      <?php endif; ?>
      <?php if ($img_counts['unoptimized']): ?>
      <span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:50%;background:#d97706;display:inline-block"></span> <?php echo $img_counts['unoptimized']; ?> Unoptimized</span>
      <?php endif; ?>
      <?php if ($img_counts['good']): ?>
      <span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:50%;background:#16a34a;display:inline-block"></span> <?php echo $img_counts['good']; ?> Good</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Priority Actions (from plan) -->
<?php if ($has_plan && !empty($plan_data['actions'])): ?>
<div class="siloq-card" style="margin-bottom:16px;padding:20px">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <div>
      <div style="font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px">&#127919; Priority Actions</div>
      <div style="font-size:11px;color:#6b7280;margin-top:2px">Ranked by traffic impact — fix these first</div>
    </div>
    <button class="siloq-btn siloq-btn--outline" style="font-size:11px;padding:5px 10px" onclick="document.querySelector('[aria-controls=\'siloq-tab-plan\']').click()">View All &rarr;</button>
  </div>
  <?php foreach (array_slice($plan_data['actions'], 0, 4) as $i => $act):
    $prio_cls = $act['priority'] === 'high' ? 'p1' : ($act['priority'] === 'medium' ? 'p2' : 'p3');
    $fix_url = isset($act['elementor_url']) ? $act['elementor_url'] : (isset($act['edit_url']) ? $act['edit_url'] : '');
    $act_post_id = isset($act['post_id']) ? intval($act['post_id']) : 0;
    $act_text = strtolower( (isset($act['headline']) ? $act['headline'] : '') . ' ' . (isset($act['detail']) ? $act['detail'] : '') );
    // Determine fix type from headline/detail text — intentionally broad to catch all variants
    $fix_mode = 'link'; // default: open link
    if (
        stripos($act_text, 'meta description') !== false ||
        stripos($act_text, 'meta desc') !== false ||
        stripos($act_text, 'add description') !== false
    ) {
        $fix_mode = 'meta_description';
    } elseif (
        stripos($act_text, 'seo title') !== false ||
        stripos($act_text, 'title tag') !== false ||
        stripos($act_text, 'missing title') !== false ||
        stripos($act_text, 'add title') !== false ||
        stripos($act_text, 'set a title') !== false
    ) {
        $fix_mode = 'meta_title';
    } elseif (
        stripos($act_text, 'schema') !== false ||
        stripos($act_text, 'structured data') !== false ||
        stripos($act_text, 'schema markup') !== false
    ) {
        $fix_mode = 'schema';
    } elseif (stripos($act_text, 'missing h1') !== false || stripos($act_text, 'add h1') !== false) {
        $fix_mode = 'elementor';
    } elseif (stripos($act_text, 'orphan') !== false || stripos($act_text, 'no internal links') !== false) {
        $fix_mode = 'internal_links';
    }
  ?>
  <div class="siloq-action-card" style="display:flex;align-items:center;gap:11px;padding:11px 13px;background:#f8fafc;border-radius:11px;border:1px solid #e5e7eb;margin-bottom:7px">
    <div style="width:24px;height:24px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0;background:<?php echo $act['priority']==='high'?'#fef2f2':'#fffbeb'; ?>;color:<?php echo $act['priority']==='high'?'#dc2626':'#d97706'; ?>">
      <?php echo ($i + 1); ?>
    </div>
    <div style="flex:1;min-width:0">
      <div style="font-size:12px;font-weight:600;margin-bottom:2px;line-height:1.3"><?php echo esc_html($act['headline']); ?></div>
      <?php if (!empty($act['detail'])): ?>
      <div style="font-size:11px;color:#6b7280;line-height:1.4"><?php echo esc_html($act['detail']); ?></div>
      <?php endif; ?>
      <div style="display:flex;align-items:center;gap:5px;margin-top:4px;flex-wrap:wrap">
        <span style="font-size:10px;font-weight:600;padding:2px 7px;border-radius:8px;background:<?php echo $act['priority']==='high'?'#fef2f2':'#fffbeb'; ?>;color:<?php echo $act['priority']==='high'?'#dc2626':'#d97706'; ?>"><?php echo ucfirst(esc_html($act['priority'])); ?> priority</span>
      </div>
    </div>
    <?php if ($fix_mode === 'meta_description' && $act_post_id): ?>
    <button class="siloq-fix-btn siloq-btn siloq-btn--primary" data-action="fix_meta" data-post-id="<?php echo $act_post_id; ?>" data-type="description" style="font-size:11px;padding:6px 12px;white-space:nowrap;cursor:pointer">Fix It</button>
    <?php elseif ($fix_mode === 'meta_title' && $act_post_id): ?>
    <button class="siloq-fix-btn siloq-btn siloq-btn--primary" data-action="fix_meta" data-post-id="<?php echo $act_post_id; ?>" data-type="title" style="font-size:11px;padding:6px 12px;white-space:nowrap;cursor:pointer">Fix It</button>
    <?php elseif ($fix_mode === 'schema' && $act_post_id): ?>
    <button class="siloq-fix-btn siloq-btn siloq-btn--primary" data-action="fix_schema" data-post-id="<?php echo $act_post_id; ?>" style="font-size:11px;padding:6px 12px;white-space:nowrap;cursor:pointer">Fix It</button>
    <?php elseif ($fix_mode === 'elementor' && $fix_url): ?>
    <a href="<?php echo esc_url($fix_url); ?>" target="_blank" class="siloq-btn siloq-btn--primary" style="font-size:11px;padding:6px 12px;white-space:nowrap">Edit in Elementor</a>
    <?php elseif ($fix_mode === 'internal_links' && $act_post_id): ?>
    <div class="siloq-orphan-fix-wrap" data-spoke-id="<?php echo intval($act_post_id); ?>" data-spoke-title="<?php echo esc_attr(get_the_title($act_post_id)); ?>" data-spoke-url="<?php echo esc_url(get_permalink($act_post_id)); ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
      <button class="siloq-btn siloq-btn--sm siloq-btn--outline siloq-orphan-suggest-btn" style="font-size:11px;padding:5px 10px">See Fix</button>
      <button class="siloq-btn siloq-btn--sm siloq-btn--primary siloq-orphan-autolink-btn" style="font-size:11px;padding:5px 10px">Auto-Add Link</button>
      <div class="siloq-orphan-suggestion" style="display:none;font-size:11px;color:#374151;margin-top:4px;padding:6px 10px;background:#f0fdf4;border-radius:6px;border:1px solid #bbf7d0;width:100%"></div>
    </div>
    <?php elseif ($fix_url): ?>
    <a href="<?php echo esc_url($fix_url); ?>" target="_blank" class="siloq-btn siloq-btn--primary" style="font-size:11px;padding:6px 12px;white-space:nowrap">Fix It &rarr;</a>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══════ SITE AUDIT (Track 2) ═══════ -->
<?php
$audit_results = get_transient('siloq_audit_results');
$last_audit_time = get_option('siloq_last_audit_time', '');
$audit_fresh = !empty($audit_results);
?>
<div class="siloq-card" style="margin-bottom:16px;padding:20px">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <div>
      <div style="font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px">&#128269; Site Audit</div>
      <div style="font-size:11px;color:#6b7280;margin-top:2px">
        <?php if ($last_audit_time): ?>
          Last run: <?php echo esc_html($last_audit_time); ?>
        <?php else: ?>
          Run an audit to score every page on your site
        <?php endif; ?>
      </div>
    </div>
    <button class="siloq-btn siloq-btn--primary siloq-run-audit-btn" style="font-size:11px;padding:6px 14px" onclick="siloqRunAudit(this)">
      <?php echo $audit_fresh ? 'Re-run Audit' : 'Run Audit'; ?>
    </button>
  </div>

  <?php if ($audit_fresh && isset($audit_results['site_score'])): ?>
  <?php
    $audit_score = intval($audit_results['site_score']);
    if ($audit_score >= 80) { $audit_score_bg = '#f0fdf4'; $audit_score_color = '#16a34a'; }
    elseif ($audit_score >= 60) { $audit_score_bg = '#fffbeb'; $audit_score_color = '#d97706'; }
    else { $audit_score_bg = '#fef2f2'; $audit_score_color = '#dc2626'; }
  ?>
  <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;padding:12px 16px;background:<?php echo $audit_score_bg; ?>;border-radius:12px">
    <div style="font-size:36px;font-weight:800;color:<?php echo $audit_score_color; ?>;line-height:1"><?php echo $audit_score; ?></div>
    <div>
      <div style="font-size:13px;font-weight:600;color:<?php echo $audit_score_color; ?>">
        <?php if ($audit_score >= 80): ?>Healthy
        <?php elseif ($audit_score >= 60): ?>Needs Improvement
        <?php else: ?>Needs Attention<?php endif; ?>
      </div>
      <div style="font-size:11px;color:#6b7280"><?php echo count($audit_results['pages'] ?? array()); ?> pages audited</div>
    </div>
  </div>

  <?php if (!empty($audit_results['pages'])):
    // Sort by score ascending (worst first)
    $audit_pages = $audit_results['pages'];
    usort($audit_pages, function($a, $b) { return ($a['score'] ?? 100) - ($b['score'] ?? 100); });
  ?>
  <div style="display:flex;flex-direction:column;gap:6px">
    <?php foreach (array_slice($audit_pages, 0, 10) as $ap):
      $ap_score = intval($ap['score'] ?? 0);
      if ($ap_score >= 80) { $ap_bg = '#f0fdf4'; $ap_clr = '#16a34a'; }
      elseif ($ap_score >= 60) { $ap_bg = '#fffbeb'; $ap_clr = '#d97706'; }
      else { $ap_bg = '#fef2f2'; $ap_clr = '#dc2626'; }
      $ap_title = get_the_title($ap['post_id'] ?? 0) ?: ('Post #' . ($ap['post_id'] ?? '?'));
      $ap_type = $ap['tier'] ?? 'supporting';
      $type_colors = array(
        'apex_hub'   => array('#7c3aed','#f5f3ff'),
        'hub'        => array('#6366f1','#eef2ff'),
        'spoke'      => array('#0d9488','#f0fdfa'),
        'supporting' => array('#6b7280','#f3f4f6'),
        'orphan'     => array('#dc2626','#fef2f2'),
      );
      $tc = $type_colors[$ap_type] ?? array('#6b7280','#f3f4f6');
    ?>
    <div class="siloq-audit-page-row" style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:#f8fafc;border-radius:10px;border:1px solid #e5e7eb;cursor:pointer" onclick="var s=this.nextElementSibling;if(s&&s.classList.contains('siloq-audit-actions')){s.style.display=s.style.display==='none'?'block':'none';this.querySelector('span[data-chev]')&&(this.querySelector('span[data-chev]').textContent=s.style.display==='block'?'▲':'▼');}">
      <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0;background:<?php echo $ap_bg; ?>;color:<?php echo $ap_clr; ?>"><?php echo $ap_score; ?></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo esc_html($ap_title); ?></div>
        <div style="display:flex;gap:5px;margin-top:3px;flex-wrap:wrap">
          <span style="font-size:9px;font-weight:700;padding:1px 6px;border-radius:6px;background:<?php echo $tc[1]; ?>;color:<?php echo $tc[0]; ?>;text-transform:uppercase"><?php echo esc_html(str_replace('_', ' ', $ap_type)); ?></span>
          <?php if (!empty($ap['actions'])): ?>
          <span style="font-size:9px;font-weight:600;padding:1px 6px;border-radius:6px;background:#f3f4f6;color:#6b7280"><?php echo count($ap['actions']); ?> action<?php echo count($ap['actions']) > 1 ? 's' : ''; ?></span>
          <?php endif; ?>
        </div>
      </div>
      <button class="siloq-exclude-page-btn" data-post-id="<?php echo intval($ap['post_id'] ?? 0); ?>" onclick="event.stopPropagation();siloqExcludePage(this)" title="Remove from Siloq" style="background:none;border:none;cursor:pointer;padding:2px 5px;color:#9ca3af;font-size:10px;border-radius:4px;flex-shrink:0" onmouseenter="this.style.color='#dc2626'" onmouseleave="this.style.color='#9ca3af'">&#10005;</button>
      <?php if (!empty($ap['actions'])): ?>
      <span data-chev style="font-size:10px;color:#9ca3af;pointer-events:none">&#9660;</span>
      <?php endif; ?>
    </div>
    <?php if (!empty($ap['actions'])): ?>
    <div class="siloq-audit-actions" style="display:none;padding:0 0 0 44px;margin-top:-4px;margin-bottom:4px">
      <?php foreach ($ap['actions'] as $action):
        $sev_colors = array('critical'=>'#dc2626','high'=>'#ea580c','warning'=>'#d97706','medium'=>'#6b7280','info'=>'#3b82f6');
        $sev_clr = $sev_colors[$action['severity'] ?? 'info'] ?? '#6b7280';
      ?>
      <div style="padding:6px 10px;margin-bottom:3px;border-left:3px solid <?php echo $sev_clr; ?>;background:#fafafa;border-radius:0 6px 6px 0">
        <div style="font-size:11px;font-weight:600;color:#1e293b"><?php echo esc_html($action['title'] ?? ''); ?></div>
        <div style="font-size:10px;color:#6b7280;margin-top:2px"><?php echo esc_html($action['recommendation'] ?? ''); ?></div>
        <div style="display:flex;gap:5px;margin-top:3px">
          <span style="font-size:9px;font-weight:700;padding:1px 5px;border-radius:4px;background:<?php echo $sev_clr; ?>15;color:<?php echo $sev_clr; ?>"><?php echo esc_html(strtoupper($action['severity'] ?? '')); ?></span>
          <span style="font-size:9px;font-weight:600;padding:1px 5px;border-radius:4px;background:#f3f4f6;color:#6b7280"><?php echo esc_html($action['category'] ?? ''); ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php else: ?>
  <div style="text-align:center;padding:20px 0;color:#6b7280">
    <div style="font-size:28px;margin-bottom:8px">&#128269;</div>
    <div style="font-size:12px">Click "Run Audit" to analyze all your pages and get actionable recommendations.</div>
  </div>
  <?php endif; ?>
</div>

<style>
.siloq-audit-actions.open { display:block !important; }
</style>

<script>
function siloqExcludePage(btn) {
    var postId = parseInt(btn.dataset.postId, 10);
    if (!postId) return;
    if (!confirm('Remove this page from Siloq? It will no longer appear in audits or be synced.')) return;
    btn.disabled = true;
    btn.textContent = '…';
    jQuery.post(ajaxurl, {
        action: 'siloq_exclude_page',
        nonce: siloqDash.nonce,
        post_id: postId
    }, function(resp) {
        if (resp.success) {
            // Remove the whole row + its sibling actions panel from the DOM
            var row = btn.closest('.siloq-audit-page-row');
            var next = row ? row.nextElementSibling : null;
            if (next && next.classList.contains('siloq-audit-actions')) next.remove();
            if (row) row.remove();
        } else {
            var msg = (resp.data && resp.data.message) ? resp.data.message : 'Failed to exclude.';
            alert(msg);
            btn.disabled = false;
            btn.textContent = '✕';
        }
    }).fail(function() {
        alert('Network error — please try again.');
        btn.disabled = false;
        btn.textContent = '✕';
    });
}

function siloqRunAudit(btn) {
    btn.disabled = true;
    btn.textContent = 'Running...';

    jQuery.post(ajaxurl, {
        action: 'siloq_run_audit',
        nonce: siloqDash.nonce
    }, function(resp) {
        if (resp.success) {
            location.reload();
        } else {
            var msg = (resp.data && resp.data.message) ? resp.data.message : 'Audit failed.';
            alert(msg);
            btn.disabled = false;
            btn.textContent = 'Run Audit';
        }
    }).fail(function() {
        alert('Network error — please try again.');
        btn.disabled = false;
        btn.textContent = 'Run Audit';
    });
}

// ── One-click Fix It buttons ──
jQuery(document).on('click', '.siloq-fix-btn', function() {
    var $btn = jQuery(this);
    var action = $btn.data('action');
    var postId = $btn.data('post-id');
    var type = $btn.data('type') || '';

    if (action === 'view_links') {
        // Open the post in Elementor with internal links tab
        window.open(ajaxurl.replace('admin-ajax.php', 'post.php?post=' + postId + '&action=elementor'), '_blank');
        return;
    }

    $btn.text('Fixing...').prop('disabled', true);

    jQuery.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'siloq_dashboard_fix',
            nonce: siloqDash.nonce,
            fix_action: action,
            post_id: postId,
            fix_type: type
        },
        success: function(resp) {
            if (resp.success) {
                $btn.text('Fixed').css('background', '#10b981');
            } else {
                $btn.text((resp.data && resp.data.message ? resp.data.message : 'Failed')).prop('disabled', false);
            }
        },
        error: function() {
            $btn.text('Error').prop('disabled', false);
        }
    });
});
</script>

<!-- Site Architecture Map -->
<div class="siloq-arch-map" id="siloq-arch-map-section">
  <div class="siloq-arch-map-hdr">
    <div>
      <div class="siloq-arch-map-title">&#128450; Site Architecture Map</div>
      <div class="siloq-arch-map-sub">Your hub pages and all supporting content — live pages and what still needs to be created.</div>
    </div>
    <div class="siloq-arch-legend">
      <div class="siloq-legend-item"><div class="siloq-legend-dot hub"></div>Hub</div>
      <div class="siloq-legend-item"><div class="siloq-legend-dot live"></div>Live Page</div>
      <div class="siloq-legend-item"><div class="siloq-legend-dot missing"></div>Missing — Create</div>
      <div class="siloq-legend-item"><div class="siloq-legend-dot orphan"></div>Orphan</div>
    </div>
  </div>

  <!-- Summary stats -->
  <?php $live_spoke_count = array_sum(array_map(function($h){ return count($h['children']); }, $hub_data));
        $missing_spoke_count = array_sum(array_map(function($h){ return count($h['missing']); }, $hub_data)); ?>
  <div class="siloq-arch-summary">
    <div class="siloq-arch-sum-item"><div class="siloq-arch-sum-n indigo"><?php echo count($hub_data); ?></div><div class="siloq-arch-sum-l">Hub Pages</div></div>
    <div class="siloq-arch-sum-item"><div class="siloq-arch-sum-n green"><?php echo $live_spoke_count; ?></div><div class="siloq-arch-sum-l">Live Supporting</div></div>
    <div class="siloq-arch-sum-item"><div class="siloq-arch-sum-n amber"><?php echo $missing_spoke_count; ?></div><div class="siloq-arch-sum-l">Pages to Create</div></div>
    <div class="siloq-arch-sum-item"><div class="siloq-arch-sum-n red"><?php echo count($true_orphan_posts); ?></div><div class="siloq-arch-sum-l">True Orphans</div></div>
  </div>

  <!-- Orphan strip -->
  <?php if (count($true_orphan_posts) > 0): ?>
  <div class="siloq-orphan-strip">
    <span style="font-size:16px;flex-shrink:0">&#9888;&#65039;</span>
    <div>
      <div class="siloq-orphan-strip-title"><?php echo count($true_orphan_posts); ?> page<?php echo count($true_orphan_posts) > 1 ? 's' : ''; ?> have no internal links — Google may not be crawling these</div>
      <div class="siloq-orphan-strip-sub">Nothing on your site links to these pages. They exist but are invisible to search engines.</div>
      <div class="siloq-orphan-chips">
        <?php foreach (array_slice($true_orphan_posts, 0, 5) as $oid): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap">
          <span class="siloq-orphan-chip"><?php echo esc_html(get_the_title($oid)); ?></span>
          <div class="siloq-orphan-fix-wrap" data-spoke-id="<?php echo intval($oid); ?>" data-spoke-title="<?php echo esc_attr(get_the_title($oid)); ?>" data-spoke-url="<?php echo esc_url(get_permalink($oid)); ?>">
            <button class="siloq-btn siloq-btn--sm siloq-btn--outline siloq-orphan-suggest-btn" style="font-size:10px;padding:3px 8px">See Fix</button>
            <button class="siloq-btn siloq-btn--sm siloq-btn--primary siloq-orphan-autolink-btn" style="font-size:10px;padding:3px 8px">Auto-Add Link</button>
            <div class="siloq-orphan-suggestion" style="display:none;font-size:11px;color:#374151;margin-top:4px;padding:6px 10px;background:#f0fdf4;border-radius:6px;border:1px solid #bbf7d0"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Hub blocks -->
  <div class="siloq-hub-blocks">
    <?php if (empty($hub_data)): ?>
    <div style="text-align:center;padding:32px 16px;color:#6b7280">
      <div style="font-size:28px;margin-bottom:8px">&#128450;</div>
      <div style="font-size:13px;margin-bottom:4px;font-weight:600">No hub pages detected yet</div>
      <div style="font-size:12px">Sync your pages and run Widget Intelligence to build your architecture map.</div>
    </div>
    <?php else: ?>
    <?php $hub_icons = array('&#9889;','&#127968;','&#127970;','&#128267;','&#128295;','&#128203;','&#128161;','&#127760;');
    foreach ($hub_data as $hi => $h):
      $sc = $h['score'];
      $sc_cls = $sc >= 75 ? 'green' : ($sc >= 50 ? 'amber' : 'red');
      $total_pages = count($h['children']) + count($h['missing']);
      $live_pages = count($h['children']);
      $pct = $total_pages > 0 ? round($live_pages / $total_pages * 100) : 0;
      $prog_cls = $pct >= 80 ? 'green' : 'amber';
    ?>
    <div class="siloq-hub-block">
      <div class="siloq-hub-header" onclick="siloqToggleHub(this)">
        <div class="siloq-hub-icon"><?php echo $hub_icons[$hi % count($hub_icons)]; ?></div>
        <div class="siloq-hub-info">
          <div class="siloq-hub-name"><?php echo esc_html($h['title']); ?></div>
          <div class="siloq-hub-kw"><?php echo $h['keyword'] ? esc_html('"' . $h['keyword'] . '"') : 'Run Widget Intelligence to get keyword data'; ?></div>
        </div>
        <div class="siloq-hub-meta">
          <?php if ($sc > 0): ?>
          <div class="siloq-hub-score <?php echo $sc_cls; ?>">Score <?php echo $sc; ?></div>
          <?php endif; ?>
          <?php if ( ! empty( $h['elementor_url'] ) ): ?>
          <a href="<?php echo esc_url($h['elementor_url']); ?>" class="siloq-hub-edit-btn" onclick="event.stopPropagation()">Edit Page</a>
          <?php elseif ( ! empty( $h['edit_url'] ) ): ?>
          <a href="<?php echo esc_url($h['edit_url']); ?>" class="siloq-hub-edit-btn" onclick="event.stopPropagation()">Edit</a>
          <?php endif; ?>
          <div class="siloq-hub-chev open">&#9660;</div>
        </div>
      </div>
      <div class="siloq-hub-progress">
        <div class="siloq-hub-prog-row">
          <?php if ( ! empty( $h['wc_cat'] ) ): ?>
          <div class="siloq-hub-prog-lbl">Products / Subcategories</div>
          <div class="siloq-hub-prog-bar"><div class="siloq-hub-prog-fill" style="width:100%;"></div></div>
          <div class="siloq-hub-prog-count green"><?php echo intval($h['wc_product_count']); ?> products<?php echo $h['wc_child_cats'] > 0 ? ', ' . intval($h['wc_child_cats']) . ' subcategories' : ''; ?></div>
          <?php else: ?>
          <div class="siloq-hub-prog-lbl">Supporting pages</div>
          <div class="siloq-hub-prog-bar"><div class="siloq-hub-prog-fill" style="width:<?php echo $pct; ?>%;<?php echo $pct < 50 ? 'background:linear-gradient(90deg,#d97706,#f59e0b)' : ''; ?>"></div></div>
          <div class="siloq-hub-prog-count <?php echo $prog_cls; ?>"><?php echo $live_pages; ?> of <?php echo max(1,$total_pages); ?> live<?php echo $pct >= 100 ? ' &#10003;' : ''; ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="siloq-hub-spokes-wrap" style="max-height:400px">
        <div class="siloq-hub-spokes">
          <?php foreach ($h['children'] as $child):
            $c_raw = get_post_meta($child->ID, '_siloq_analysis_data', true);
            $c_an = is_array($c_raw) ? $c_raw : (is_string($c_raw) ? json_decode($c_raw, true) : array());
            $c_sc = isset($c_an['score']) ? intval($c_an['score']) : 0;
            $c_sc_cls = $c_sc >= 75 ? 'good' : ($c_sc >= 50 ? 'ok' : 'bad');
            $c_edit = get_edit_post_link( $child->ID, 'raw' ); // WP editor (not Elementor) so AIOSEO panel is visible
          ?>
          <div class="siloq-spoke-card">
            <div class="siloq-spoke-top">
              <div class="siloq-spoke-title"><?php echo esc_html(get_the_title($child->ID)); ?></div>
              <span class="siloq-spoke-tag sub">Sub-Page</span>
            </div>
            <div class="siloq-spoke-kw"><?php echo $c_sc > 0 ? 'Score ' . $c_sc : 'Not analyzed yet'; ?></div>
            <div class="siloq-spoke-bot">
              <?php if ($c_sc > 0): ?>
              <div class="siloq-spoke-score <?php echo $c_sc_cls; ?>">Score <?php echo $c_sc; ?></div>
              <?php else: ?>
              <div class="siloq-spoke-score ok">Pending</div>
              <?php endif; ?>
              <a href="<?php echo esc_url($c_edit); ?>" class="siloq-spoke-btn edit">Edit</a>
            </div>
          </div>
          <?php endforeach; ?>
          <?php foreach ($h['missing'] as $ms): ?>
          <div class="siloq-spoke-card missing">
            <div class="siloq-spoke-top">
              <div class="siloq-spoke-title missing"><?php echo esc_html(is_array($ms) && isset($ms['title']) ? $ms['title'] : 'Supporting page needed'); ?></div>
              <span class="siloq-spoke-tag missing">Missing</span>
            </div>
            <div class="siloq-spoke-kw"><?php echo is_array($ms) && isset($ms['keyword']) ? esc_html('"' . $ms['keyword'] . '"') : 'Create to build topical authority'; ?></div>
            <div class="siloq-spoke-bot">
              <div class="siloq-spoke-missing-hint">Create this page</div>
              <button class="siloq-spoke-btn create siloq-create-page-btn" data-title="<?php echo esc_attr(is_array($ms) && isset($ms['title']) ? $ms['title'] : 'New Supporting Page'); ?>" data-type="generic">+ Create Page</button>
            </div>
          </div>
          <?php endforeach; ?>
          <div class="siloq-add-spoke" onclick="siloqAddSpoke(<?php echo $h['id']; ?>)">&#65291; Add Supporting Page</div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Bottom: Pages + Activity -->
<div class="siloq-bottom-grid">
  <div class="siloq-pages-attention siloq-card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
      <div>
        <div style="font-size:14px;font-weight:700">&#128196; Pages Needing Attention</div>
        <div style="font-size:11px;color:#6b7280;margin-top:2px">Red = critical &middot; Amber = important &middot; Green = healthy</div>
      </div>
      <button class="siloq-btn siloq-btn--outline" style="font-size:11px;padding:4px 9px" onclick="document.querySelector('[aria-controls=\'siloq-tab-pages\']').click()">All Pages &rarr;</button>
    </div>
    <?php if (empty($attention_pages)): ?>
    <div style="text-align:center;padding:20px;color:#6b7280;font-size:12px">No issues found — run Widget Intelligence on your pages to get detailed analysis.</div>
    <?php else: ?>
    <?php foreach ($attention_pages as $ap):
      $ap_sev_cls = $ap['severity'] === 'critical' ? 'red' : ($ap['severity'] === 'important' ? 'amber' : 'green');
      $ap_sc_cls = $ap['score'] >= 75 ? 'green' : ($ap['score'] >= 50 ? 'amber' : 'red');
    ?>
    <div class="siloq-page-row">
      <div class="siloq-page-dot <?php echo $ap_sev_cls; ?>"></div>
      <div class="siloq-page-name"><?php echo esc_html($ap['title']); ?></div>
      <div class="siloq-page-issue"><?php echo esc_html($ap['issue']); ?></div>
      <?php if ($ap['score'] > 0): ?>
      <div class="siloq-page-score-badge <?php echo $ap_sc_cls; ?>"><?php echo $ap['score']; ?></div>
      <?php endif; ?>
      <?php
        $ap_fix_type = $ap['fix_type'] ?? 'link';
        $ap_post_id  = $ap['post_id'] ?? 0;
        if ($ap_fix_type === 'meta_title' && $ap_post_id):
      ?><button class="siloq-fix-btn siloq-btn siloq-btn--primary" data-action="fix_meta" data-post-id="<?php echo intval($ap_post_id); ?>" data-type="title" style="font-size:10px;padding:3px 9px;white-space:nowrap;">Fix It</button>
      <?php elseif ($ap_fix_type === 'meta_description' && $ap_post_id): ?>
      <button class="siloq-fix-btn siloq-btn siloq-btn--primary" data-action="fix_meta" data-post-id="<?php echo intval($ap_post_id); ?>" data-type="description" style="font-size:10px;padding:3px 9px;white-space:nowrap;">Fix It</button>
      <?php elseif ($ap_fix_type === 'schema' && $ap_post_id): ?>
      <button class="siloq-fix-btn siloq-btn siloq-btn--primary" data-action="fix_schema" data-post-id="<?php echo intval($ap_post_id); ?>" style="font-size:10px;padding:3px 9px;white-space:nowrap;">Fix It</button>
      <?php elseif ($ap_post_id && (stripos($ap['issue'], 'no internal links') !== false || stripos($ap['issue'], 'orphan') !== false)): ?>
      <div class="siloq-orphan-fix-wrap" data-spoke-id="<?php echo intval($ap_post_id); ?>" data-spoke-title="<?php echo esc_attr($ap['title']); ?>" data-spoke-url="<?php echo esc_url(get_permalink($ap_post_id)); ?>" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap">
        <button class="siloq-btn siloq-btn--sm siloq-btn--outline siloq-orphan-suggest-btn" style="font-size:10px;padding:3px 8px">See Fix</button>
        <button class="siloq-btn siloq-btn--sm siloq-btn--primary siloq-orphan-autolink-btn" style="font-size:10px;padding:3px 8px">Auto-Add Link</button>
        <div class="siloq-orphan-suggestion" style="display:none;font-size:11px;color:#374151;margin-top:4px;padding:6px 10px;background:#f0fdf4;border-radius:6px;border:1px solid #bbf7d0;width:100%"></div>
      </div>
      <?php elseif (!empty($ap['elementor_url'])): ?>
      <a href="<?php echo esc_url($ap['elementor_url']); ?>" target="_blank" class="siloq-page-fix-link">Fix &rarr;</a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="siloq-activity-card siloq-card">
    <div style="font-size:14px;font-weight:700;margin-bottom:12px">&#9889; Recent Activity</div>
    <?php if (empty($activity_log)): ?>
    <div style="text-align:center;padding:20px;color:#6b7280;font-size:12px">No activity yet. Start by syncing your pages.</div>
    <?php else: ?>
    <?php foreach ($activity_log as $act_item):
      $act_icons = array('schema_applied' => '&#10003;', 'title_updated' => '&#8599;', 'analysis_run' => '&#9889;', 'meta_applied' => '&#10003;', 'gsc_connected' => '&#128279;', 'sync' => '&#8635;');
      $act_type = $act_item['type'] ?? 'sync';
      $act_icon = $act_icons[$act_type] ?? '&#9889;';
      $act_icon_cls = in_array($act_type, array('schema_applied','meta_applied')) ? 'green' : (in_array($act_type, array('title_updated','gsc_connected')) ? 'teal' : 'indigo');
    ?>
    <div class="siloq-activity-item">
      <div class="siloq-activity-icon <?php echo $act_icon_cls; ?>"><?php echo $act_icon; ?></div>
      <div>
        <div class="siloq-activity-title"><?php echo esc_html($act_item['message'] ?? $act_item['text'] ?? 'Activity'); ?></div>
        <div class="siloq-activity-time"><?php echo esc_html($act_item['time'] ?? $act_item['date'] ?? ''); ?></div>
        <?php if (!empty($act_item['detail'])): ?>
        <div class="siloq-activity-tag"><?php echo esc_html($act_item['detail']); ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

</div><!-- /.siloq-dash-v2 -->

<!-- ═══════ AI VISIBILITY SECTION ═══════ -->
<?php
$_agent_status = Siloq_Agent_Ready::get_badge_status();
$_audit_cache  = get_option( Siloq_Agent_Ready::OPTION_AUDIT_CACHE, [] );
?>
<div class="siloq-ai-visibility-section" style="margin:24px 0 0 0;">
  <div class="siloq-card" style="padding:20px 24px;">

    <!-- Header row -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px;">
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:18px;">🤖</span>
        <span style="font-size:16px;font-weight:700;color:#1e293b;">AI Visibility</span>
      </div>
      <!-- Agent-Ready Badge -->
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <?php
        $badge_color = [
          'green' => 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0;',
          'amber' => 'background:#fef9c3;color:#854d0e;border:1px solid #fde68a;',
          'red'   => 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;',
        ][ $_agent_status['color'] ] ?? '';
        ?>
        <span id="siloq-agent-badge" style="<?php echo esc_attr( $badge_color ); ?> padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;">
          <?php echo esc_html( $_agent_status['badge'] ); ?>
        </span>
        <?php if ( $_agent_status['status'] !== 'not_ready' ) : ?>
          <a id="siloq-view-llms-link" href="<?php echo esc_url( $_agent_status['llms_url'] ); ?>" target="_blank" rel="noopener" class="siloq-btn siloq-btn--outline siloq-btn--sm" style="font-size:11px;">View llms.txt</a>
          <button type="button" id="siloq-copy-llms-link" class="siloq-btn siloq-btn--outline siloq-btn--sm" style="font-size:11px;" data-url="<?php echo esc_attr( $_agent_status['llms_url'] ); ?>">📋 Copy Link</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Badge message / missing fields -->
    <?php if ( $_agent_status['message'] ) : ?>
    <p id="siloq-agent-message" style="margin:0 0 14px;font-size:12px;color:#64748b;">
      <?php echo esc_html( $_agent_status['message'] ); ?>
      <?php if ( ! empty( $_agent_status['missing'] ) ) : ?>
        — <strong>Missing:</strong> <?php echo esc_html( implode( ', ', $_agent_status['missing'] ) ); ?>
      <?php endif; ?>
    </p>
    <?php endif; ?>

    <?php
    $unlisted_json = get_option( 'siloq_unlisted_hub_services', '' );
    $unlisted_svcs = $unlisted_json ? json_decode( $unlisted_json, true ) : array();
    if ( ! empty( $unlisted_svcs ) ) : ?>
    <div style="font-size:11px;margin-top:6px;padding:6px 10px;background:#fffbeb;border-left:3px solid #f59e0b;border-radius:0 6px 6px 0">
        <strong>Service pages not in your business profile:</strong>
        <?php echo esc_html( implode( ', ', $unlisted_svcs ) ); ?> &mdash;
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=siloq-settings&tab=business' ) ); ?>">Add to profile &rarr;</a>
    </div>
    <?php endif; ?>

    <!-- Generate / Regenerate button -->
    <div style="margin-bottom:18px;">
      <?php if ( $_agent_status['status'] === 'not_ready' ) : ?>
        <button type="button" id="siloq-generate-agent-files" class="siloq-btn siloq-btn--primary siloq-btn--sm">
          ⚡ Generate Agent Files
        </button>
      <?php else : ?>
        <button type="button" id="siloq-generate-agent-files" class="siloq-btn siloq-btn--outline siloq-btn--sm">
          🔄 Regenerate Files
        </button>
      <?php endif; ?>
      <span id="siloq-agent-gen-status" style="margin-left:10px;font-size:12px;color:#64748b;"></span>
    </div>

    <!-- AI Visibility Checklist -->
    <div style="border-top:1px solid #e2e8f0;padding-top:16px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <span style="font-size:13px;font-weight:600;color:#334155;">AI Visibility Checks</span>
        <div style="display:flex;align-items:center;gap:8px;">
          <span id="siloq-audit-score" style="font-size:12px;font-weight:600;color:#64748b;">
            <?php if ( ! empty( $_audit_cache['score'] ) ) : ?>
              <?php echo esc_html( $_audit_cache['score'] . '/' . $_audit_cache['total'] ); ?> checks passing
            <?php endif; ?>
          </span>
          <button type="button" id="siloq-run-ai-audit" class="siloq-btn siloq-btn--outline siloq-btn--sm" style="font-size:11px;">Run Audit</button>
        </div>
      </div>

      <div id="siloq-audit-results">
        <?php if ( ! empty( $_audit_cache['checks'] ) ) : ?>
          <?php foreach ( $_audit_cache['checks'] as $check_key => $check ) :
            $icon = $check['pass'] === true ? '✓' : ( $check['pass'] === false ? '✗' : '⚠' );
            $icon_style = $check['severity'] === 'green'
              ? 'color:#16a34a;font-weight:700;'
              : ( $check['severity'] === 'red' ? 'color:#dc2626;font-weight:700;' : 'color:#d97706;font-weight:700;' );
          ?>
          <div class="siloq-audit-row" style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #f1f5f9;">
            <span style="<?php echo esc_attr( $icon_style ); ?>width:16px;flex-shrink:0;font-size:14px;"><?php echo $icon; ?></span>
            <div style="flex:1;min-width:0;">
              <div style="font-size:12px;font-weight:600;color:#334155;"><?php echo esc_html( $check['label'] ); ?></div>
              <div style="font-size:11px;color:#64748b;margin-top:2px;"><?php echo esc_html( $check['message'] ); ?></div>
              <?php if ( ! empty( $check['link'] ) && ! empty( $check['link_text'] ) ) : ?>
                <?php if ( strpos( $check['link'], '#siloq-tab-' ) === 0 ) :
                    $tab_id = ltrim( $check['link'], '#' ); ?>
                <a href="#" onclick="var btn=document.querySelector('[aria-controls=\'<?php echo esc_js($tab_id); ?>\']'); if(btn){btn.click();} return false;" class="siloq-audit-link" style="font-size:11px;color:#6366f1;text-decoration:none;margin-top:3px;display:inline-block;"><?php echo esc_html( $check['link_text'] ); ?></a>
                <?php else : ?>
                <a href="<?php echo esc_url( $check['link'] ); ?>" target="_blank" rel="noopener" class="siloq-audit-link" style="font-size:11px;color:#6366f1;text-decoration:none;margin-top:3px;display:inline-block;"><?php echo esc_html( $check['link_text'] ); ?></a>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else : ?>
          <div style="text-align:center;padding:20px;color:#94a3b8;font-size:12px;">
            Click "Run Audit" to check your AI visibility across 5 signals.
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div><!-- /.siloq-ai-visibility-section -->

<script type="text/javascript">
(function($) {
    // ── Copy Link button ─────────────────────────────────────────────────
    $(document).on('click', '#siloq-copy-llms-link', function() {
        var url = $(this).data('url');
        if (!url) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function() {
                var $btn = $('#siloq-copy-llms-link');
                $btn.text('✓ Copied!');
                setTimeout(function() { $btn.text('📋 Copy Link'); }, 2500);
            });
        } else {
            // Fallback
            var $tmp = $('<textarea>').val(url).appendTo('body').select();
            document.execCommand('copy');
            $tmp.remove();
            $(this).text('✓ Copied!');
            setTimeout(function() { $('#siloq-copy-llms-link').text('📋 Copy Link'); }, 2500);
        }
    });

    // ── Generate / Regenerate agent files ───────────────────────────────
    $(document).on('click', '#siloq-generate-agent-files', function() {
        var $btn    = $(this);
        var $status = $('#siloq-agent-gen-status');
        $btn.prop('disabled', true).text('Generating…');
        $status.text('');

        $.ajax({
            url:  (typeof siloqAjax !== 'undefined' ? siloqAjax.ajaxurl : ajaxurl),
            type: 'POST',
            data: {
                action: 'siloq_generate_agent_files',
                nonce:  (typeof siloqAjax !== 'undefined' ? siloqAjax.nonce : '')
            },
            success: function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    var badge = response.data.badge;
                    $status.css('color', '#16a34a').text('✓ ' + response.data.message);
                    // Update badge
                    siloqUpdateAgentBadge(badge);
                    $btn.text('🔄 Regenerate Files');
                    // Refresh audit automatically
                    siloqRunAiAudit();
                    // Flush WP rewrites so /llms.txt is immediately accessible
                    siloqFlushRewrites();
                } else {
                    $btn.text('⚡ Generate Agent Files');
                    $status.css('color', '#dc2626').text('Error: ' + (response.data.message || 'Generation failed.'));
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('⚡ Generate Agent Files');
                $status.css('color', '#dc2626').text('Request failed. Try again.');
            }
        });
    });

    // ── Run AI Visibility Audit ──────────────────────────────────────────
    function siloqRunAiAudit() {
        var $btn   = $('#siloq-run-ai-audit');
        var $score = $('#siloq-audit-score');
        var $list  = $('#siloq-audit-results');
        $btn.prop('disabled', true).text('Running…');

        $.ajax({
            url:  (typeof siloqAjax !== 'undefined' ? siloqAjax.ajaxurl : ajaxurl),
            type: 'POST',
            data: {
                action: 'siloq_run_ai_visibility_audit',
                nonce:  (typeof siloqAjax !== 'undefined' ? siloqAjax.nonce : '')
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Run Audit');
                if (!response.success) return;
                var data   = response.data;
                $score.text(data.score + '/' + data.total + ' checks passing');
                $list.html(siloqRenderAuditChecks(data.checks));
                // Wire up tab-link clicks inside audit results
                $list.find('a[href^="#siloq-tab-"]').on('click', function(e) {
                    e.preventDefault();
                    var tabId = $(this).attr('href').replace('#', '');
                    $('.siloq-tab-btn[aria-controls="' + tabId + '"]').trigger('click');
                });
            },
            error: function() {
                $btn.prop('disabled', false).text('Run Audit');
            }
        });
    }

    $(document).on('click', '#siloq-run-ai-audit', function() {
        siloqRunAiAudit();
    });

    function siloqRenderAuditChecks(checks) {
        var html = '';
        var severityColors = { green: '#16a34a', amber: '#d97706', red: '#dc2626' };
        $.each(checks, function(key, check) {
            var icon  = check.pass === true ? '✓' : (check.pass === false ? '✗' : '⚠');
            var color = severityColors[check.severity] || '#64748b';
            html += '<div class="siloq-audit-row" style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #f1f5f9;">';
            html += '<span style="color:' + color + ';font-weight:700;width:16px;flex-shrink:0;font-size:14px;">' + icon + '</span>';
            html += '<div style="flex:1;min-width:0;">';
            html += '<div style="font-size:12px;font-weight:600;color:#334155;">' + siloqEscape(check.label) + '</div>';
            html += '<div style="font-size:11px;color:#64748b;margin-top:2px;">' + siloqEscape(check.message) + '</div>';
            if (check.link && check.link_text) {
                var isTabLink = check.link.indexOf('#siloq-tab-') === 0;
                var isExternal = check.link.indexOf('#') !== 0;
                if (isTabLink) {
                    var tabId = check.link.replace('#', '');
                    html += '<a href="#" onclick="var btn=document.querySelector(\'[aria-controls=\\\"' + tabId + '\\\"]\'); if(btn){btn.click();} return false;" style="font-size:11px;color:#6366f1;text-decoration:none;margin-top:3px;display:inline-block;">' + siloqEscape(check.link_text) + '</a>';
                } else {
                    html += '<a href="' + siloqEscape(check.link) + '"' + (isExternal ? ' target="_blank" rel="noopener"' : '') + ' style="font-size:11px;color:#6366f1;text-decoration:none;margin-top:3px;display:inline-block;">' + siloqEscape(check.link_text) + '</a>';
                }
            }
            if (check.action === 'generate' && check.action_text) {
                html += '<button type="button" id="siloq-generate-agent-files-audit" class="siloq-btn siloq-btn--outline siloq-btn--sm" style="font-size:11px;margin-top:4px;">' + siloqEscape(check.action_text) + '</button>';
            }
            html += '</div></div>';
        });
        return html;
    }

    // Proxy generate button inside audit list
    $(document).on('click', '#siloq-generate-agent-files-audit', function() {
        $('#siloq-generate-agent-files').trigger('click');
    });

    // ── Badge updater ────────────────────────────────────────────────────
    function siloqUpdateAgentBadge(badge) {
        if (!badge) return;
        var colorMap = {
            green: 'background:#dcfce7;color:#166534;border:1px solid #bbf7d0;',
            amber: 'background:#fef9c3;color:#854d0e;border:1px solid #fde68a;',
            red:   'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;'
        };
        var style = colorMap[badge.color] || '';
        $('#siloq-agent-badge').attr('style', style + 'padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;').text(badge.badge);
        $('#siloq-agent-message').text(badge.message || '');
        if (badge.llms_url) {
            $('#siloq-view-llms-link').attr('href', badge.llms_url).show();
            $('#siloq-copy-llms-link').data('url', badge.llms_url).show();
        }
    }

    // ── Flush WP rewrites after file generation ──────────────────────────
    // (ensures /llms.txt is immediately routable)
    function siloqFlushRewrites() {
        $.post(
            (typeof siloqAjax !== 'undefined' ? siloqAjax.ajaxurl : ajaxurl),
            { action: 'siloq_flush_rewrites', nonce: (typeof siloqAjax !== 'undefined' ? siloqAjax.nonce : '') }
        );
    }

    // ── Escape helper ────────────────────────────────────────────────────
    function siloqEscape(str) {
        return $('<div>').text(str || '').html();
    }
}(jQuery));
</script>

            </div><!-- /dashboard tab -->

            <!-- ═══════ SEO/GEO PLAN TAB ═══════ -->
            <div id="siloq-tab-plan" class="siloq-tab-panel" role="tabpanel" aria-hidden="true">
                <div class="siloq-plan-section">

                    <!-- Tab header -->
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
                        <div>
                            <h2 style="font-size:18px;font-weight:700;margin:0 0 4px;">SEO/GEO Plan</h2>
                            <p style="color:#6b7280;font-size:13px;margin:0;">Your site's current SEO health and exactly what to do about it.</p>
                        </div>
                        <button class="siloq-btn siloq-btn--primary siloq-generate-plan-btn">
                            <?php echo $has_plan ? '&#8635; Refresh Plan' : 'Generate Your SEO Plan &rarr;'; ?>
                        </button>
                    </div>

                    <!-- Section 1: Site Health Score -->
                    <div class="siloq-card" style="margin-bottom:16px;padding:20px;">
                        <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
                            <div id="siloq-health-ring-wrap" style="flex-shrink:0;">
                                <!-- JS fills SVG ring -->
                                <svg width="80" height="80" viewBox="0 0 80 80" id="siloq-health-ring">
                                    <circle cx="40" cy="40" r="34" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                                    <circle id="siloq-health-arc" cx="40" cy="40" r="34" fill="none" stroke="#4f46e5" stroke-width="8"
                                        stroke-dasharray="213.6" stroke-dashoffset="213.6"
                                        stroke-linecap="round" transform="rotate(-90 40 40)" style="transition:stroke-dashoffset 0.6s ease;"/>
                                </svg>
                                <div id="siloq-health-score-num" style="position:relative;margin-top:-60px;text-align:center;font-size:22px;font-weight:800;color:#1e1b4b;">—</div>
                                <div style="text-align:center;font-size:10px;color:#9ca3af;margin-top:32px;">/ 100</div>
                            </div>
                            <div style="flex:1;min-width:180px;">
                                <h3 style="font-size:16px;font-weight:700;margin:0 0 4px;" id="siloq-health-label">Site Health Score</h3>
                                <p style="color:#6b7280;font-size:13px;margin:0 0 12px;">Here's what's affecting your score and how to fix it.</p>
                                <div id="siloq-score-breakdown" style="display:flex;gap:12px;flex-wrap:wrap;font-size:12px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Fix All banner (shown by JS when missing titles/descs exist) -->
                    <div id="siloq-fix-all-bar" style="display:none;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                            <div>
                                <span style="font-weight:700;color:#166534;font-size:14px;">⚡ Quick fix available</span>
                                <span id="siloq-fix-all-desc" style="font-size:13px;color:#166534;margin-left:8px;"></span>
                            </div>
                            <button id="siloq-fix-all-btn" class="siloq-btn siloq-btn--primary siloq-btn--sm" style="background:#059669;border-color:#059669;">
                                Fix All Missing Titles &amp; Descriptions
                            </button>
                        </div>
                        <div id="siloq-fix-all-progress" style="display:none;margin-top:10px;">
                            <div style="font-size:12px;color:#166534;font-weight:600;margin-bottom:5px;" id="siloq-fix-all-msg">Preparing...</div>
                            <div style="background:#bbf7d0;border-radius:999px;height:6px;"><div id="siloq-fix-all-pbar" style="height:100%;background:#059669;border-radius:999px;width:0%;transition:width 0.3s;"></div></div>
                        </div>
                    </div>
                    <div id="siloq-fix-all-summary" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:13px;"></div>

                    <?php
// ── URL Restructure Recommendation ──────────────────────────────────────────
// Gate: only show for local_service sites. Ecommerce/event_venue/general never need city nesting.
$_plan_biz_type = get_option( 'siloq_business_type', 'general' );
$_plan_sa_hub   = null;
$_plan_sa_spokes_count = 0;
if ( in_array( $_plan_biz_type, array( 'local_service', 'local_service_multi' ), true ) ) {
    $_plan_sa_hub = get_page_by_path('service-areas') ?: get_page_by_path('service-area');
    if ( $_plan_sa_hub ) {
        // Spoke detection: pages with explicit role OR pages whose URL already is under /services/ but not /service-areas/
        // If zero pages have _siloq_page_role=spoke in DB, skip entirely — no banner needed
        $_spoke_exists = get_posts( array(
            'post_type'      => array( 'page', 'post' ),
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array( array( 'key' => '_siloq_page_role', 'value' => 'spoke', 'compare' => '=' ) ),
        ) );
        if ( empty( $_spoke_exists ) ) {
            // No spoke meta anywhere on site — banner is definitely wrong, suppress entirely
            $_plan_sa_hub = null;
        }
        $_plan_spokes = empty( $_plan_sa_hub ) ? array() : get_posts( array(
            'post_type'   => function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array( 'page', 'post' ),
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query'  => array( array( 'key' => '_siloq_page_role', 'value' => 'spoke', 'compare' => '=' ) ),
        ) );
        // Normalize SA hub URL to path only (domain-agnostic)
        $_raw_hub_url      = trailingslashit( get_permalink( $_plan_sa_hub->ID ) );
        $_plan_sa_hub_path = trailingslashit( '/' . ltrim( parse_url( $_raw_hub_url, PHP_URL_PATH ), '/' ) );

        // Collect all non-SA hub post IDs so we can exclude their children
        $_non_sa_hub_ids = array();
        foreach ( get_posts( array(
            'post_type'      => array( 'page', 'post' ),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array( array( 'key' => '_siloq_page_role', 'value' => 'hub', 'compare' => '=' ) ),
        ) ) as $_hub_id ) {
            $_non_sa_hub_ids[] = (int) $_hub_id;
        }

        foreach ( $_plan_spokes as $_ps ) {
            $page_url  = trailingslashit( get_permalink( $_ps->ID ) );
            $page_path = trailingslashit( '/' . ltrim( parse_url( $page_url, PHP_URL_PATH ), '/' ) );

            // Already under service-areas — no restructure needed
            if ( strpos( $page_path, $_plan_sa_hub_path ) === 0 ) {
                continue;
            }
            // Child of a non-SA hub (e.g. /services/) — correctly placed, skip
            if ( $_ps->post_parent > 0 && in_array( (int) $_ps->post_parent, $_non_sa_hub_ids, true ) ) {
                continue;
            }
            $_plan_sa_spokes_count++;
        }
    }
}
if ( $_plan_sa_hub && $_plan_sa_spokes_count > 0 ) :
?>
<div class="siloq-card" style="margin-bottom:16px;border-left:4px solid #f59e0b;background:#fffbeb;">
  <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
    <div style="font-size:22px;flex-shrink:0;">🔀</div>
    <div style="flex:1;min-width:0;">
      <div style="font-size:14px;font-weight:700;color:#92400e;margin-bottom:4px;">
        URL Restructure Available — <?php echo $_plan_sa_spokes_count; ?> city page<?php echo $_plan_sa_spokes_count !== 1 ? 's' : ''; ?> need nesting
      </div>
      <p style="font-size:12px;color:#78350f;margin:0 0 10px;line-height:1.5;">
        Your <strong><?php echo esc_html($_plan_sa_hub->post_title); ?></strong> hub has been detected.
        <?php echo $_plan_sa_spokes_count; ?> spoke page<?php echo $_plan_sa_spokes_count !== 1 ? 's' : ''; ?> should move to
        <code style="background:#fef3c7;padding:1px 4px;border-radius:3px;">/service-areas/[city-slug]/</code>
        to complete your silo architecture. Siloq will create 301 redirects automatically — old URLs keep working.
      </p>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <button type="button" id="siloq-plan-apply-restructure" class="siloq-btn siloq-btn--primary siloq-btn--sm" style="background:#d97706;border-color:#d97706;">
          ⚡ Apply All Redirects Now
        </button>
        <a href="#siloq-tab-redirects" class="siloq-tab-btn siloq-btn siloq-btn--outline siloq-btn--sm" aria-controls="siloq-tab-redirects" style="font-size:11px;">
          Preview First →
        </a>
      </div>
      <div id="siloq-plan-restructure-status" style="display:none;margin-top:8px;font-size:12px;padding:6px 10px;border-radius:5px;"></div>
    </div>
  </div>
</div>
<script type="text/javascript">
(function($){
    $('#siloq-plan-apply-restructure').on('click', function() {
        var $btn = $(this);
        var $status = $('#siloq-plan-restructure-status');
        $btn.prop('disabled', true).text('Loading…');
        $status.hide();

        // Step 1: preview to get the list
        $.post(
            (typeof siloqAjax !== 'undefined' ? siloqAjax.ajaxurl : ajaxurl),
            { action: 'siloq_preview_city_redirects', nonce: (typeof siloqAjax !== 'undefined' ? siloqAjax.nonce : ''), target_prefix: '/service-areas/' },
            function(r) {
                if (!r.success || !r.data.suggestions || !r.data.suggestions.length) {
                    $btn.prop('disabled', false).text('⚡ Apply All Redirects Now');
                    $status.css({'background':'#fee2e2','color':'#991b1b','border':'1px solid #fca5a5'})
                           .text('Error fetching redirect plan — please try again.').show();
                    return;
                }

                var suggestions = r.data.suggestions.filter(function(s){ return !s.already_exists; });
                if (!suggestions.length) {
                    $btn.prop('disabled', false).text('⚡ Apply All Redirects Now');
                    $status.css({'background':'#dcfce7','color':'#166534','border':'1px solid #bbf7d0'})
                           .text('✓ All redirects already exist — nothing to apply.').show();
                    return;
                }

                // Step 2: atomically restructure each page (slug change + redirect) sequentially
                $btn.text('Restructuring ' + suggestions.length + ' page' + (suggestions.length !== 1 ? 's' : '') + '…');
                var done = 0, failed = 0, errorDetails = [];

                function applyNext(idx) {
                    if (idx >= suggestions.length) {
                        $btn.prop('disabled', false).text('⚡ Apply All Redirects Now');
                        var msg = '✓ ' + done + ' page' + (done !== 1 ? 's' : '') + ' restructured';
                        if (failed) { msg += ', ' + failed + ' failed'; }
                        msg += '. Old URLs automatically redirect to new locations.';
                        if (errorDetails.length) { msg += ' Errors: ' + errorDetails.join('; '); }
                        $status.css({'background': failed ? '#fee2e2' : '#dcfce7','color': failed ? '#991b1b' : '#166534','border': '1px solid ' + (failed ? '#fca5a5' : '#bbf7d0')})
                               .text(msg).show();
                        if (typeof loadSiloqRedirects === 'function') loadSiloqRedirects();
                        return;
                    }
                    var s = suggestions[idx];
                    $.post(
                        (typeof siloqAjax !== 'undefined' ? siloqAjax.ajaxurl : ajaxurl),
                        {
                            action:          'siloq_atomic_restructure_page',
                            nonce:           (typeof siloqAjax !== 'undefined' ? siloqAjax.nonce : ''),
                            post_id:         s.post_id,
                            hub_post_id:     s.hub_post_id,
                            original_parent: s.original_parent
                        },
                        function(res) {
                            if (res.success) { done++; } else {
                                failed++;
                                var errMsg = (res.data && res.data.message) ? res.data.message : 'Unknown error';
                                errorDetails.push(s.from + ': ' + errMsg);
                            }
                            applyNext(idx + 1);
                        }
                    ).fail(function(){ failed++; errorDetails.push(s.from + ': Request failed'); applyNext(idx + 1); });
                }
                applyNext(0);
            }
        ).fail(function(){
            $btn.prop('disabled', false).text('⚡ Apply All Redirects Now');
            $status.css({'background':'#fee2e2','color':'#991b1b'}).text('Request failed. Try again.').show();
        });
    });
}(jQuery));
</script>
<?php endif; ?>
<!-- ── /URL Restructure ─────────────────────────────────────────────────── -->

<!-- ── Reposition Recommendations ── -->
<?php
$_reposition_pages = get_posts(array('post_type' => array('page','post'), 'post_status' => 'publish', 'numberposts' => 50, 'meta_query' => array(array('key' => '_siloq_reposition_flag', 'compare' => 'EXISTS'))));
if ( ! empty( $_reposition_pages ) ) : ?>
<div class="siloq-card" style="margin-bottom:16px;border:2px solid #f97316;">
    <h3 style="font-size:15px;font-weight:700;margin:0 0 10px;color:#1e293b;">Location Pages Under Wrong Hub</h3>
    <p style="font-size:12px;color:#64748b;margin:0 0 12px;">These pages contain a city name but are filed under your Services hub instead of Service Areas.</p>
    <?php foreach ( $_reposition_pages as $_rp ) :
        $_rf = get_post_meta( $_rp->ID, '_siloq_reposition_flag', true );
        if ( ! is_array( $_rf ) ) continue;
    ?>
    <div class="siloq-reposition-row" data-post-id="<?php echo intval($_rp->ID); ?>" data-target-hub="<?php echo intval( isset($_rf['target_hub_id']) ? $_rf['target_hub_id'] : 0 ); ?>" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 12px;margin-bottom:6px;background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;">
            <span style="font-size:13px;font-weight:600;color:#9a3412;"><?php echo esc_html( $_rp->post_title ); ?></span>
            <span style="font-size:11px;color:#c2410c;margin-left:8px;">is a location page filed under Services. Move to Service Areas.</span>
        </div>
        <div style="display:flex;gap:6px;">
            <button type="button" class="siloq-btn siloq-btn--primary siloq-btn--sm siloq-reposition-move-btn" style="font-size:11px;padding:4px 10px;">Move to Service Areas</button>
        </div>
    </div>
    <?php endforeach; ?>
    <div id="siloq-reposition-msg" style="display:none;margin-top:10px;font-size:12px;padding:7px 12px;border-radius:6px;"></div>
</div>
<?php endif; ?>

<!-- ── Rename Recommendations ── -->
<?php
$_rename_pages = get_posts(array('post_type' => array('page','post'), 'post_status' => 'publish', 'numberposts' => 50, 'meta_query' => array(array('key' => '_siloq_rename_suggestion', 'compare' => 'EXISTS'))));
$_rename_with_city = array();
foreach ( $_rename_pages as $_rnp ) {
    $_rs = get_post_meta( $_rnp->ID, '_siloq_rename_suggestion', true );
    if ( is_array( $_rs ) && ! empty( $_rs['city_page_exists'] ) ) {
        $_rename_with_city[] = array( 'post' => $_rnp, 'meta' => $_rs );
    }
}
if ( ! empty( $_rename_with_city ) ) : ?>
<div class="siloq-card" style="margin-bottom:16px;border:2px solid #eab308;">
    <h3 style="font-size:15px;font-weight:700;margin:0 0 10px;color:#1e293b;">Rename Recommendations — City in Service Title</h3>
    <p style="font-size:12px;color:#64748b;margin:0 0 12px;">These service pages target a city name but you have a dedicated city page. Renaming removes cannibalization risk.</p>
    <?php foreach ( $_rename_with_city as $_rc ) :
        $_rnp = $_rc['post'];
        $_rs  = $_rc['meta'];
    ?>
    <div class="siloq-rename-row" data-post-id="<?php echo intval($_rnp->ID); ?>" data-new-title="<?php echo esc_attr( $_rs['suggested_title'] ); ?>" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 12px;margin-bottom:6px;background:#fefce8;border:1px solid #fde68a;border-radius:6px;flex-wrap:wrap;">
        <div style="flex:1;min-width:200px;">
            <span style="font-size:13px;font-weight:600;color:#854d0e;"><?php echo esc_html( $_rs['current_title'] ); ?></span>
            <span style="font-size:11px;color:#a16207;margin-left:6px;">targets <strong><?php echo esc_html( $_rs['city'] ); ?></strong> but you have a dedicated city page.</span>
            <div style="font-size:11px;color:#65a30d;margin-top:2px;">Suggested: <strong><?php echo esc_html( $_rs['suggested_title'] ); ?></strong></div>
        </div>
        <div style="display:flex;gap:6px;">
            <button type="button" class="siloq-btn siloq-btn--primary siloq-btn--sm siloq-rename-approve-btn" style="font-size:11px;padding:4px 10px;">Approve Rename</button>
            <button type="button" class="siloq-btn siloq-btn--outline siloq-btn--sm siloq-rename-dismiss-btn" style="font-size:11px;padding:4px 10px;">Dismiss</button>
        </div>
    </div>
    <?php endforeach; ?>
    <div id="siloq-rename-msg" style="display:none;margin-top:10px;font-size:12px;padding:7px 12px;border-radius:6px;"></div>
</div>
<?php endif; ?>

<script type="text/javascript">
(function($) {
    var _ajaxUrl = (typeof siloqAjax !== 'undefined' ? siloqAjax.ajaxurl : ajaxurl);
    var _nonce   = (typeof siloqAjax !== 'undefined' ? siloqAjax.nonce : '');

    // Reposition: Move to Service Areas
    $(document).on('click', '.siloq-reposition-move-btn', function() {
        var $btn = $(this);
        var $row = $btn.closest('.siloq-reposition-row');
        var postId    = $row.data('post-id');
        var targetHub = $row.data('target-hub');
        $btn.prop('disabled', true).text('Moving...');
        $.post(_ajaxUrl, {
            action: 'siloq_reposition_page',
            nonce: _nonce,
            post_id: postId,
            target_hub_id: targetHub
        }, function(r) {
            if (r.success) {
                $row.css({'background':'#ecfdf5','border-color':'#6ee7b7'});
                $btn.text('Moved').css('color','#059669');
            } else {
                $btn.prop('disabled', false).text('Move to Service Areas');
                $('#siloq-reposition-msg').css({'background':'#fee2e2','color':'#991b1b','border':'1px solid #fca5a5'}).text(r.data || 'Failed').show();
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Move to Service Areas');
        });
    });

    // Rename: Approve
    $(document).on('click', '.siloq-rename-approve-btn', function() {
        var $btn = $(this);
        var $row = $btn.closest('.siloq-rename-row');
        var postId   = $row.data('post-id');
        var newTitle = $row.data('new-title');
        $btn.prop('disabled', true).text('Renaming...');
        $.post(_ajaxUrl, {
            action: 'siloq_approve_rename',
            nonce: _nonce,
            post_id: postId,
            new_title: newTitle
        }, function(r) {
            if (r.success) {
                $row.css({'background':'#ecfdf5','border-color':'#6ee7b7'});
                $btn.text('Renamed').css('color','#059669');
                $row.find('.siloq-rename-dismiss-btn').remove();
            } else {
                $btn.prop('disabled', false).text('Approve Rename');
                $('#siloq-rename-msg').css({'background':'#fee2e2','color':'#991b1b','border':'1px solid #fca5a5'}).text(r.data || 'Failed').show();
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Approve Rename');
        });
    });

    // Rename: Dismiss
    $(document).on('click', '.siloq-rename-dismiss-btn', function() {
        var $btn = $(this);
        var $row = $btn.closest('.siloq-rename-row');
        var postId = $row.data('post-id');
        $btn.prop('disabled', true).text('Dismissing...');
        $.post(_ajaxUrl, {
            action: 'siloq_dismiss_rename',
            nonce: _nonce,
            post_id: postId
        }, function(r) {
            if (r.success) {
                $row.slideUp(200, function() { $row.remove(); });
            } else {
                $btn.prop('disabled', false).text('Dismiss');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Dismiss');
        });
    });
}(jQuery));
</script>

<!-- Section 2: Priority Actions -->
                    <div class="siloq-card" style="margin-bottom:16px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                            <h3 style="font-size:15px;font-weight:700;margin:0;">Priority Actions</h3>
                            <span id="siloq-actions-count" style="font-size:12px;color:#6b7280;"></span>
                        </div>
                        <div id="siloq-actions-content">
                            <p class="siloq-empty" style="color:#9ca3af;font-size:13px;">Generate your plan to see priority actions.</p>
                        </div>
                    </div>

                    <!-- Section 3: Site Architecture -->
                    <div class="siloq-card" style="margin-bottom:16px;">
                        <div style="margin-bottom:14px;">
                            <h3 style="font-size:15px;font-weight:700;margin:0 0 3px;">Site Architecture</h3>
                            <p style="font-size:12px;color:#6b7280;margin:0;">How your pages are organized for search engines. Hub pages should link to all their spoke/city pages, and each spoke should link back up.</p>
                        </div>
                        <div id="siloq-architecture-content">
                            <p class="siloq-empty" style="color:#9ca3af;font-size:13px;">Generate your plan to see your site architecture.</p>
                        </div>
                    </div>

                    <!-- Section 4: Pages You Should Create -->
                    <div class="siloq-card" style="margin-bottom:16px;">
                        <div style="margin-bottom:14px;">
                            <h3 style="font-size:15px;font-weight:700;margin:0 0 3px;">Pages You Should Create</h3>
                            <p style="font-size:12px;color:#6b7280;margin:0;">Based on your service list and service areas — pages that don't exist yet but would drive real traffic.</p>
                        </div>
                        <div id="siloq-supporting-content">
                            <?php echo self::render_gap_cards(); ?>
                        </div>
                    </div>

                    <!-- Section 5: Quick Wins -->
                    <div class="siloq-card" style="margin-bottom:16px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                            <h3 style="font-size:15px;font-weight:700;margin:0;">Quick Wins</h3>
                            <span id="siloq-qw-progress" style="font-size:12px;font-weight:600;color:#4f46e5;"></span>
                        </div>
                        <p style="font-size:12px;color:#6b7280;margin:0 0 14px;">Every item below is fixable in under 2 minutes with Siloq's help. Completed items move to the bottom.</p>
                        <div id="siloq-issues-content">
                            <p class="siloq-empty" style="color:#9ca3af;font-size:13px;">Generate your plan to see quick wins.</p>
                        </div>
                    </div>

                    <!-- Section 6: 90-Day Roadmap -->
                    <div class="siloq-card">
                        <div style="margin-bottom:14px;">
                            <h3 style="font-size:15px;font-weight:700;margin:0;">90-Day Roadmap</h3>
                        </div>
                        <div id="siloq-roadmap-content">
                            <p class="siloq-empty" style="color:#9ca3af;font-size:13px;">Generate your plan to see your roadmap.</p>
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

                <?php
                $sync_result = get_option( 'siloq_last_sync_result', null );
                if ( $sync_result && ! empty( $sync_result['last_run'] ) ) : ?>
                <div style="font-size:11px;color:#6b7280;margin-bottom:8px;">
                    Last sync: <?php echo esc_html( $sync_result['last_run'] ); ?> &mdash; <?php echo intval( $sync_result['synced_count'] ); ?> pages synced<?php if ( intval( $sync_result['error_count'] ) > 0 ) echo ' (' . intval( $sync_result['error_count'] ) . ' errors)'; ?>
                </div>
                <?php endif; ?>

                <?php
                $queue_count = intval( get_option( 'siloq_analysis_queue_count', 0 ) );
                if ( $queue_count > 0 ) : ?>
                <div class="siloq-analysis-queue-banner" style="background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;padding:8px 16px;margin-bottom:12px;font-size:13px;color:#92400e;">
                    &#9203; Analyzing <?php echo intval( $queue_count ); ?> page<?php echo $queue_count > 1 ? 's' : ''; ?> in background...
                </div>
                <?php endif; ?>

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
                <?php
                global $wpdb;
                $siloq_synced_meta_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_siloq_synced'" ) );
                ?>
                <script>var siloqSyncedMetaCount = <?php echo $siloq_synced_meta_count; ?>;</script>

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
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <button type="button" id="siloq-schema-apply-all-btn" class="siloq-btn siloq-btn--primary siloq-btn--sm" title="Generate and apply schema to every page that shows None">
                                ⚡ Apply Schema to All
                            </button>
                            <button type="button" id="siloq-schema-repair-btn" class="siloq-btn siloq-btn--outline siloq-btn--sm" title="Fix pages where the Siloq schema panel is missing in Elementor editor">
                                🔧 Repair Schema Panels
                            </button>
                            <button type="button" id="siloq-schema-refresh" class="siloq-btn siloq-btn--outline siloq-btn--sm">
                                <span class="dashicons dashicons-update"></span> Refresh
                            </button>
                        </div>
                        <div id="siloq-schema-bulk-progress" style="display:none;margin-top:10px;">
                            <div style="font-size:12px;color:#4f46e5;font-weight:600;margin-bottom:6px;" id="siloq-schema-bulk-msg">Preparing...</div>
                            <div style="background:#e0e7ff;border-radius:999px;height:8px;overflow:hidden;">
                                <div id="siloq-schema-bulk-bar" style="height:100%;background:#4f46e5;border-radius:999px;transition:width 0.3s;width:0%;"></div>
                            </div>
                        </div>
                        <div id="siloq-schema-bulk-summary" style="display:none;margin-top:10px;font-size:12px;padding:8px 12px;border-radius:6px;"></div>
                    </div>
                    <div id="siloq-schema-repair-msg" style="display:none;font-size:12px;padding:8px 12px;border-radius:6px;margin-bottom:8px;"></div>
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
                <div id="siloq-gsc-not-connected" style="display:none;text-align:center;padding:60px 20px;">
                    <p style="font-size:15px;color:#555;">GSC is not connected yet.</p>
                    <p style="color:#888;font-size:13px;">Connect in your <a href="https://app.siloq.ai" target="_blank">Siloq dashboard</a>, then return here.</p>
                    <button id="siloq-gsc-recheck" class="button button-primary" style="margin-top:12px;">Check Connection</button>
                </div>

                <div id="siloq-gsc-connected" style="display:none;">
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
                        <div class="siloq-card" style="text-align:center;">
                            <div style="font-size:28px;font-weight:700;color:#1a56db;" id="siloq-gsc-clicks">—</div>
                            <div style="font-size:12px;color:#888;margin-top:4px;">Total Clicks</div>
                        </div>
                        <div class="siloq-card" style="text-align:center;">
                            <div style="font-size:28px;font-weight:700;color:#1a56db;" id="siloq-gsc-impressions">—</div>
                            <div style="font-size:12px;color:#888;margin-top:4px;">Total Impressions</div>
                        </div>
                        <div class="siloq-card" style="text-align:center;">
                            <div style="font-size:28px;font-weight:700;color:#1a56db;" id="siloq-gsc-position">—</div>
                            <div style="font-size:12px;color:#888;margin-top:4px;">Avg Position</div>
                        </div>
                        <div class="siloq-card" style="text-align:center;">
                            <div style="font-size:28px;font-weight:700;color:#1a56db;" id="siloq-gsc-pages">—</div>
                            <div style="font-size:12px;color:#888;margin-top:4px;">Pages w/ Data</div>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div class="siloq-card">
                            <h3 style="font-size:14px;font-weight:600;margin:0 0 12px;">Top Queries</h3>
                            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                                <thead>
                                    <tr style="border-bottom:1px solid #e5e7eb;">
                                        <th style="text-align:left;padding:6px 4px;color:#888;font-weight:500;">Query</th>
                                        <th style="text-align:right;padding:6px 4px;color:#888;font-weight:500;">Impr.</th>
                                        <th style="text-align:right;padding:6px 4px;color:#888;font-weight:500;">Clicks</th>
                                        <th style="text-align:right;padding:6px 4px;color:#888;font-weight:500;">Pos.</th>
                                    </tr>
                                </thead>
                                <tbody id="siloq-gsc-queries-body"></tbody>
                            </table>
                        </div>
                        <div class="siloq-card">
                            <h3 style="font-size:14px;font-weight:600;margin:0 0 12px;">Top Pages</h3>
                            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                                <thead>
                                    <tr style="border-bottom:1px solid #e5e7eb;">
                                        <th style="text-align:left;padding:6px 4px;color:#888;font-weight:500;">Page</th>
                                        <th style="text-align:right;padding:6px 4px;color:#888;font-weight:500;">Impr.</th>
                                        <th style="text-align:right;padding:6px 4px;color:#888;font-weight:500;">Clicks</th>
                                        <th style="text-align:right;padding:6px 4px;color:#888;font-weight:500;">Pos.</th>
                                    </tr>
                                </thead>
                                <tbody id="siloq-gsc-pages-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="siloq-gsc-loading" style="text-align:center;padding:60px;">
                    <span class="spinner is-active" style="float:none;"></span>
                    <p style="color:#888;margin-top:12px;">Loading GSC data...</p>
                </div>

                <script>
                (function($) {
                    var gscNonce = '<?php echo esc_js( wp_create_nonce( 'siloq_ajax_nonce' ) ); ?>';

                    function siloqLoadGSC() {
                        if ($('#siloq-gsc-connected').data('loaded')) return;
                        $('#siloq-gsc-loading').show();
                        $('#siloq-gsc-connected').hide();
                        $('#siloq-gsc-not-connected').hide();

                        $.post(ajaxurl, {
                            action: 'siloq_get_gsc_summary',
                            nonce: gscNonce
                        }, function(res) {
                            $('#siloq-gsc-loading').hide();
                            if (!res.success || !res.data || !res.data.connected) {
                                $('#siloq-gsc-not-connected').show();
                                return;
                            }
                            var d = res.data;
                            $('#siloq-gsc-clicks').text(d.summary.total_clicks.toLocaleString());
                            $('#siloq-gsc-impressions').text(d.summary.total_impressions.toLocaleString());
                            $('#siloq-gsc-position').text(d.summary.avg_position);
                            $('#siloq-gsc-pages').text(d.summary.pages_with_data);

                            var qHtml = '';
                            $.each(d.top_queries, function(i, q) {
                                qHtml += '<tr style="border-bottom:1px solid #f3f4f6;"><td style="padding:6px 4px;">' + $('<span>').text(q.query).html() + '</td><td style="text-align:right;padding:6px 4px;">' + q.impressions.toLocaleString() + '</td><td style="text-align:right;padding:6px 4px;">' + q.clicks + '</td><td style="text-align:right;padding:6px 4px;">' + q.avg_position + '</td></tr>';
                            });
                            $('#siloq-gsc-queries-body').html(qHtml || '<tr><td colspan="4" style="padding:12px;color:#888;">No query data yet.</td></tr>');

                            var pHtml = '';
                            $.each(d.top_pages, function(i, p) {
                                var slug = p.url.replace(/https?:\/\/[^\/]+/, '').replace(/\/$/, '') || '/';
                                pHtml += '<tr style="border-bottom:1px solid #f3f4f6;"><td style="padding:6px 4px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + p.url + '">' + slug + '</td><td style="text-align:right;padding:6px 4px;">' + p.impressions.toLocaleString() + '</td><td style="text-align:right;padding:6px 4px;">' + p.clicks + '</td><td style="text-align:right;padding:6px 4px;">' + p.avg_position + '</td></tr>';
                            });
                            $('#siloq-gsc-pages-body').html(pHtml || '<tr><td colspan="4" style="padding:12px;color:#888;">No page data yet.</td></tr>');

                            $('#siloq-gsc-connected').data('loaded', true).show();
                        });
                    }

                    // Load GSC data when tab becomes active
                    $(document).on('click', '.siloq-tab-btn[aria-controls="siloq-tab-gsc"]', function() {
                        siloqLoadGSC();
                    });

                    // Also load if arriving via hash
                    if (window.location.hash === '#siloq-tab-gsc') {
                        siloqLoadGSC();
                    }

                    // Recheck button
                    $(document).on('click', '#siloq-gsc-recheck', function() {
                        $('#siloq-gsc-connected').data('loaded', false);
                        siloqLoadGSC();
                    });
                })(jQuery);
                </script>
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

                <!-- ═══ BRAND VOICE ═══ -->
                <?php
                $bv_raw = get_option('siloq_brand_voice', '');
                $bv = $bv_raw ? json_decode($bv_raw, true) : array();
                $bv = is_array($bv) ? $bv : array();
                $bv_primary   = isset($bv['primary_tone']) ? $bv['primary_tone'] : 'confident_expert';
                $bv_secondary = isset($bv['secondary_tone']) ? $bv['secondary_tone'] : '';
                $bv_industry  = isset($bv['industry']) ? $bv['industry'] : '';
                $bv_brands    = isset($bv['admired_brands']) ? $bv['admired_brands'] : '';
                $bv_tagline   = isset($bv['tagline']) ? $bv['tagline'] : '';
                $bv_smart     = !empty($bv['using_smart_default']);
                ?>
                <div class="siloq-card" id="siloq-brand-voice-card" style="margin-top:16px;">
                    <div class="siloq-card-header" style="cursor:pointer;user-select:none;" onclick="var b=document.getElementById('siloq-bv-body');b.style.display=b.style.display==='none'?'block':'none';var a=document.getElementById('siloq-bv-arrow');a.textContent=b.style.display==='none'?'&#9654;':'&#9660;';">
                        <h3 class="siloq-card-title"><span id="siloq-bv-arrow">&#9660;</span> &#127912; Brand Voice</h3>
                    </div>
                    <div id="siloq-bv-body">

                        <!-- Industry -->
                        <div class="siloq-settings-section" style="margin-bottom:16px;">
                            <label for="siloq-bv-industry" style="font-weight:600;display:block;margin-bottom:4px;">Industry</label>
                            <select id="siloq-bv-industry" style="width:100%;max-width:400px;padding:6px 8px;">
                                <option value="">Select your industry</option>
                                <option value="professional_services" <?php selected($bv_industry, 'professional_services'); ?>>Professional Services</option>
                                <option value="healthcare" <?php selected($bv_industry, 'healthcare'); ?>>Healthcare</option>
                                <option value="tech_saas" <?php selected($bv_industry, 'tech_saas'); ?>>Tech / SaaS</option>
                                <option value="ecommerce_retail" <?php selected($bv_industry, 'ecommerce_retail'); ?>>E-commerce / Retail</option>
                                <option value="home_services" <?php selected($bv_industry, 'home_services'); ?>>Home Services</option>
                                <option value="real_estate" <?php selected($bv_industry, 'real_estate'); ?>>Real Estate</option>
                                <option value="agency_marketing" <?php selected($bv_industry, 'agency_marketing'); ?>>Agency / Marketing</option>
                                <option value="education_training" <?php selected($bv_industry, 'education_training'); ?>>Education / Training</option>
                                <option value="fitness_sports" <?php selected($bv_industry, 'fitness_sports'); ?>>Fitness / Sports</option>
                                <option value="hospitality_events" <?php selected($bv_industry, 'hospitality_events'); ?>>Hospitality / Events</option>
                                <option value="automotive" <?php selected($bv_industry, 'automotive'); ?>>Automotive</option>
                                <option value="financial_insurance" <?php selected($bv_industry, 'financial_insurance'); ?>>Financial / Insurance</option>
                                <option value="manufacturing_industrial" <?php selected($bv_industry, 'manufacturing_industrial'); ?>>Manufacturing / Industrial</option>
                                <option value="nonprofit_government" <?php selected($bv_industry, 'nonprofit_government'); ?>>Nonprofit / Government</option>
                                <option value="creative_media" <?php selected($bv_industry, 'creative_media'); ?>>Creative / Media</option>
                                <option value="legal_compliance" <?php selected($bv_industry, 'legal_compliance'); ?>>Legal / Compliance</option>
                                <option value="construction_trades" <?php selected($bv_industry, 'construction_trades'); ?>>Construction / Trades</option>
                                <option value="pet_veterinary" <?php selected($bv_industry, 'pet_veterinary'); ?>>Pet / Veterinary</option>
                            </select>
                            <span id="siloq-bv-industry-note" style="display:none;font-size:12px;color:#6366f1;margin-top:4px;"></span>
                        </div>

                        <!-- Primary Tone -->
                        <div class="siloq-settings-section" style="margin-bottom:16px;">
                            <label for="siloq-bv-primary" style="font-weight:600;display:block;margin-bottom:4px;">Primary Tone <span style="color:#ef4444;">*</span></label>
                            <select id="siloq-bv-primary" style="width:100%;max-width:400px;padding:6px 8px;">
                                <option value="confident_expert" title="Knows the answer, no fluff." <?php selected($bv_primary, 'confident_expert'); ?>>Confident Expert — Knows the answer, no fluff.</option>
                                <option value="warm_advisor" title="Supportive and wise. Never condescending." <?php selected($bv_primary, 'warm_advisor'); ?>>Warm Advisor — Supportive and wise. Never condescending.</option>
                                <option value="no_bs_truth_teller" title="Direct, punchy, bold." <?php selected($bv_primary, 'no_bs_truth_teller'); ?>>No-BS Truth Teller — Direct, punchy, bold.</option>
                                <option value="sage_strategist" title="Big-picture, almost philosophical." <?php selected($bv_primary, 'sage_strategist'); ?>>Sage Strategist — Big-picture, almost philosophical.</option>
                                <option value="tech_translator" title="Explains complex topics in plain language." <?php selected($bv_primary, 'tech_translator'); ?>>Tech Translator — Explains complex topics in plain language.</option>
                                <option value="rebellious_challenger" title="Takes aim at industry norms." <?php selected($bv_primary, 'rebellious_challenger'); ?>>Rebellious Challenger — Takes aim at industry norms.</option>
                            </select>
                            <div id="siloq-bv-primary-example" style="margin-top:6px;padding:8px 12px;background:#f8fafc;border-left:3px solid #e2e8f0;border-radius:4px;font-style:italic;font-size:13px;color:#64748b;"></div>
                        </div>

                        <!-- Secondary Tone -->
                        <div class="siloq-settings-section" style="margin-bottom:16px;">
                            <label for="siloq-bv-secondary" style="font-weight:600;display:block;margin-bottom:4px;">Secondary Tone</label>
                            <select id="siloq-bv-secondary" style="width:100%;max-width:400px;padding:6px 8px;">
                                <option value="">None</option>
                                <option value="confident_expert" title="Knows the answer, no fluff." <?php selected($bv_secondary, 'confident_expert'); ?>>Confident Expert — Knows the answer, no fluff.</option>
                                <option value="warm_advisor" title="Supportive and wise. Never condescending." <?php selected($bv_secondary, 'warm_advisor'); ?>>Warm Advisor — Supportive and wise. Never condescending.</option>
                                <option value="no_bs_truth_teller" title="Direct, punchy, bold." <?php selected($bv_secondary, 'no_bs_truth_teller'); ?>>No-BS Truth Teller — Direct, punchy, bold.</option>
                                <option value="sage_strategist" title="Big-picture, almost philosophical." <?php selected($bv_secondary, 'sage_strategist'); ?>>Sage Strategist — Big-picture, almost philosophical.</option>
                                <option value="tech_translator" title="Explains complex topics in plain language." <?php selected($bv_secondary, 'tech_translator'); ?>>Tech Translator — Explains complex topics in plain language.</option>
                                <option value="rebellious_challenger" title="Takes aim at industry norms." <?php selected($bv_secondary, 'rebellious_challenger'); ?>>Rebellious Challenger — Takes aim at industry norms.</option>
                            </select>
                            <div id="siloq-bv-secondary-example" style="margin-top:6px;padding:8px 12px;background:#f8fafc;border-left:3px solid #e2e8f0;border-radius:4px;font-style:italic;font-size:13px;color:#64748b;display:none;"></div>
                        </div>

                        <!-- Admired Brands -->
                        <div class="siloq-settings-section" style="margin-bottom:16px;">
                            <label for="siloq-bv-brands" style="font-weight:600;display:block;margin-bottom:4px;">Admired Brands</label>
                            <input type="text" id="siloq-bv-brands" value="<?php echo esc_attr($bv_brands); ?>" placeholder="e.g., Stripe, Notion, Apple, Mailchimp..." style="width:100%;max-width:400px;padding:6px 8px;">
                        </div>

                        <!-- Tagline -->
                        <div class="siloq-settings-section" style="margin-bottom:16px;">
                            <label for="siloq-bv-tagline" style="font-weight:600;display:block;margin-bottom:4px;">Tagline</label>
                            <input type="text" id="siloq-bv-tagline" value="<?php echo esc_attr($bv_tagline); ?>" placeholder="e.g., From chaos to clarity." style="width:100%;max-width:400px;padding:6px 8px;">
                        </div>

                        <!-- Buttons -->
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:12px;">
                            <button type="button" id="siloq-bv-save-btn" class="siloq-btn siloq-btn--primary">Save Brand Voice</button>
                            <button type="button" id="siloq-bv-sync-btn" style="padding:8px 16px;border:1px solid #6366f1;border-radius:6px;background:#eef2ff;color:#4f46e5;font-size:13px;cursor:pointer;font-weight:500;" title="Push brand voice settings to your Siloq profile">Sync to Siloq Profile</button>
                        </div>
                        <span id="siloq-bv-msg" style="display:none;margin-top:8px;font-size:13px;color:#16a34a;font-weight:500;"></span>

                        <script>
                        (function(){
                            var toneExamples = {
                                confident_expert: "Here\u2019s exactly what\u2019s wrong\u2014and how to fix it.",
                                warm_advisor: "You\u2019re not behind\u2014you just didn\u2019t have the right system.",
                                no_bs_truth_teller: "You\u2019re not losing to better content. You\u2019re losing to better structure.",
                                sage_strategist: "Chaos is the enemy. Architecture is the answer.",
                                tech_translator: "We don\u2019t show you data. We show you decisions.",
                                rebellious_challenger: "$5K/month for reports you can\u2019t understand? That ends here."
                            };
                            var smartDefaults = {
                                professional_services: ["confident_expert","warm_advisor"],
                                healthcare: ["warm_advisor","tech_translator"],
                                tech_saas: ["tech_translator","confident_expert"],
                                ecommerce_retail: ["warm_advisor","no_bs_truth_teller"],
                                home_services: ["no_bs_truth_teller","warm_advisor"],
                                real_estate: ["confident_expert","warm_advisor"],
                                agency_marketing: ["rebellious_challenger","confident_expert"],
                                education_training: ["tech_translator","warm_advisor"],
                                fitness_sports: ["no_bs_truth_teller","warm_advisor"],
                                hospitality_events: ["warm_advisor","confident_expert"],
                                automotive: ["no_bs_truth_teller","tech_translator"],
                                financial_insurance: ["tech_translator","confident_expert"],
                                manufacturing_industrial: ["confident_expert","no_bs_truth_teller"],
                                nonprofit_government: ["warm_advisor","sage_strategist"],
                                creative_media: ["rebellious_challenger","sage_strategist"],
                                legal_compliance: ["confident_expert","tech_translator"],
                                construction_trades: ["no_bs_truth_teller","confident_expert"],
                                pet_veterinary: ["warm_advisor","tech_translator"]
                            };
                            var industryLabels = {};
                            var indSel = document.getElementById('siloq-bv-industry');
                            for (var i = 0; i < indSel.options.length; i++) {
                                if (indSel.options[i].value) industryLabels[indSel.options[i].value] = indSel.options[i].text;
                            }

                            var usingSmartDefault = <?php echo $bv_smart ? 'true' : 'false'; ?>;

                            function updateExample(selectId, exampleId) {
                                var val = document.getElementById(selectId).value;
                                var el = document.getElementById(exampleId);
                                if (val && toneExamples[val]) {
                                    el.textContent = '\u201c' + toneExamples[val] + '\u201d';
                                    el.style.display = 'block';
                                } else {
                                    el.style.display = 'none';
                                }
                            }

                            document.getElementById('siloq-bv-primary').addEventListener('change', function() {
                                updateExample('siloq-bv-primary','siloq-bv-primary-example');
                                usingSmartDefault = false;
                                document.getElementById('siloq-bv-industry-note').style.display = 'none';
                            });
                            document.getElementById('siloq-bv-secondary').addEventListener('change', function() {
                                updateExample('siloq-bv-secondary','siloq-bv-secondary-example');
                                usingSmartDefault = false;
                                document.getElementById('siloq-bv-industry-note').style.display = 'none';
                            });

                            document.getElementById('siloq-bv-industry').addEventListener('change', function() {
                                var v = this.value;
                                if (v && smartDefaults[v]) {
                                    document.getElementById('siloq-bv-primary').value = smartDefaults[v][0];
                                    document.getElementById('siloq-bv-secondary').value = smartDefaults[v][1];
                                    updateExample('siloq-bv-primary','siloq-bv-primary-example');
                                    updateExample('siloq-bv-secondary','siloq-bv-secondary-example');
                                    usingSmartDefault = true;
                                    var note = document.getElementById('siloq-bv-industry-note');
                                    note.textContent = 'Smart default for ' + (industryLabels[v] || v);
                                    note.style.display = 'block';
                                } else {
                                    usingSmartDefault = false;
                                    document.getElementById('siloq-bv-industry-note').style.display = 'none';
                                }
                            });

                            // Init examples on load
                            updateExample('siloq-bv-primary','siloq-bv-primary-example');
                            updateExample('siloq-bv-secondary','siloq-bv-secondary-example');

                            // Save handler
                            document.getElementById('siloq-bv-save-btn').addEventListener('click', function() {
                                var btn = this;
                                btn.disabled = true;
                                btn.textContent = 'Saving...';
                                var data = new FormData();
                                data.append('action', 'siloq_save_brand_voice');
                                data.append('nonce', '<?php echo esc_js(wp_create_nonce("siloq_ajax_nonce")); ?>');
                                data.append('primary_tone', document.getElementById('siloq-bv-primary').value);
                                data.append('secondary_tone', document.getElementById('siloq-bv-secondary').value);
                                data.append('industry', document.getElementById('siloq-bv-industry').value);
                                data.append('admired_brands', document.getElementById('siloq-bv-brands').value);
                                data.append('tagline', document.getElementById('siloq-bv-tagline').value);
                                data.append('using_smart_default', usingSmartDefault ? '1' : '0');

                                fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
                                    .then(function(r){ return r.json(); })
                                    .then(function(r){
                                        btn.disabled = false;
                                        btn.textContent = 'Save Brand Voice';
                                        var msg = document.getElementById('siloq-bv-msg');
                                        if (r.success) {
                                            msg.textContent = 'Brand voice saved.';
                                            msg.style.color = '#16a34a';
                                        } else {
                                            msg.textContent = (r.data && r.data.message) ? r.data.message : 'Error saving.';
                                            msg.style.color = '#ef4444';
                                        }
                                        msg.style.display = 'inline-block';
                                        setTimeout(function(){ msg.style.display = 'none'; }, 3000);
                                    })
                                    .catch(function(){
                                        btn.disabled = false;
                                        btn.textContent = 'Save Brand Voice';
                                    });
                            });

                            // Sync to Siloq Profile handler
                            document.getElementById('siloq-bv-sync-btn').addEventListener('click', function() {
                                var btn = this;
                                btn.disabled = true;
                                btn.textContent = 'Syncing...';
                                btn.style.opacity = '0.7';
                                var data = new FormData();
                                data.append('action', 'siloq_sync_brand_voice');
                                data.append('nonce', '<?php echo esc_js(wp_create_nonce("siloq_ajax_nonce")); ?>');

                                fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
                                    .then(function(r){ return r.json(); })
                                    .then(function(r){
                                        btn.disabled = false;
                                        btn.style.opacity = '1';
                                        var msg = document.getElementById('siloq-bv-msg');
                                        if (r.success) {
                                            btn.textContent = '\u2714 Synced!';
                                            btn.style.background = '#dcfce7';
                                            btn.style.borderColor = '#16a34a';
                                            btn.style.color = '#16a34a';
                                            msg.textContent = (r.data && r.data.message) ? r.data.message : 'Synced!';
                                            msg.style.color = '#16a34a';
                                        } else {
                                            btn.textContent = 'Sync Failed';
                                            btn.style.background = '#fef2f2';
                                            btn.style.borderColor = '#ef4444';
                                            btn.style.color = '#ef4444';
                                            msg.textContent = (r.data && r.data.message) ? r.data.message : 'Sync failed.';
                                            msg.style.color = '#ef4444';
                                        }
                                        msg.style.display = 'inline-block';
                                        setTimeout(function(){
                                            btn.textContent = 'Sync to Siloq Profile';
                                            btn.style.background = '#eef2ff';
                                            btn.style.borderColor = '#6366f1';
                                            btn.style.color = '#4f46e5';
                                            msg.style.display = 'none';
                                        }, 3000);
                                    })
                                    .catch(function(){
                                        btn.disabled = false;
                                        btn.style.opacity = '1';
                                        btn.textContent = 'Sync to Siloq Profile';
                                        btn.style.background = '#eef2ff';
                                        btn.style.borderColor = '#6366f1';
                                        btn.style.color = '#4f46e5';
                                    });
                            });
                        })();
                        </script>

                    </div>
                </div><!-- /brand voice -->

                <!-- ═══ SITE GOALS ═══ -->
                <?php
                $goals_data        = Siloq_Goals::get_goals();
                $current_goal           = isset( $goals_data['primary_goal'] ) ? $goals_data['primary_goal'] : 'local_leads';
                $current_geo_pages      = isset( $goals_data['geo_priority_pages'] ) ? (array) $goals_data['geo_priority_pages'] : array();
                $current_target_keywords = json_decode( get_option( 'siloq_target_keywords_' . get_option('siloq_site_id','0'), '[]' ), true );
                if ( ! is_array( $current_target_keywords ) ) $current_target_keywords = array();
                $goal_labels       = array(
                    'local_leads'    => __( 'Get more phone calls / local leads', 'siloq-connector' ),
                    'ecommerce_sales'=> __( 'Drive more e-commerce sales', 'siloq-connector' ),
                    'topic_authority'=> __( 'Build authority on a specific topic', 'siloq-connector' ),
                    'multi_location' => __( 'Rank in multiple cities', 'siloq-connector' ),
                    'geo_citations'  => __( 'Be cited by AI assistants (ChatGPT, Perplexity)', 'siloq-connector' ),
                    'organic_growth' => __( 'Grow overall organic traffic', 'siloq-connector' ),
                );
                ?>
                <div class="siloq-card" id="siloq-goals-card" style="margin-top:16px;">
                    <div class="siloq-card-header" style="cursor:pointer;user-select:none;" onclick="var b=document.getElementById('siloq-goals-body');b.style.display=b.style.display==='none'?'block':'none';var a=document.getElementById('siloq-goals-arrow');a.textContent=b.style.display==='none'?'&#9654;':'&#9660;';">
                        <h3 class="siloq-card-title"><span id="siloq-goals-arrow">&#9660;</span> &#127919; Site Goals</h3>
                    </div>
                    <div id="siloq-goals-body">

                        <!-- Primary Goal -->
                        <div class="siloq-settings-section" style="margin-bottom:16px;">
                            <label style="font-weight:600;display:block;margin-bottom:8px;"><?php _e( 'Primary Goal', 'siloq-connector' ); ?></label>
                            <?php foreach ( $goal_labels as $val => $label ) : ?>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;margin-bottom:6px;">
                                <input type="radio" name="siloq_goals_primary" value="<?php echo esc_attr( $val ); ?>" <?php checked( $current_goal, $val ); ?> style="accent-color:#6366f1;">
                                <?php echo esc_html( $label ); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Target Keyword Phrases (replaces GEO Priority Pages picker) -->
                        <div class="siloq-settings-section" style="margin-bottom:16px;">
                            <label style="font-weight:600;display:block;margin-bottom:4px;"><?php _e( 'Target Keyword Phrases', 'siloq-connector' ); ?></label>
                            <p style="font-size:12px;color:#64748b;margin-bottom:10px;"><?php _e( 'Enter 5–7 keyword phrases you want to rank for. Siloq will auto-match each to the best page on your site.', 'siloq-connector' ); ?></p>
                            <div id="siloq-goals-keywords-wrap">
                            <?php for ( $ki = 0; $ki < 7; $ki++ ) :
                                $kw_val = isset( $current_target_keywords[ $ki ] ) ? esc_attr( $current_target_keywords[ $ki ] ) : '';
                            ?>
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                    <span style="font-size:12px;color:#9ca3af;min-width:72px;"><?php printf( esc_html__( 'Keyword %d', 'siloq-connector' ), $ki + 1 ); ?></span>
                                    <input type="text" class="siloq-target-keyword-input" value="<?php echo $kw_val; ?>" placeholder="<?php esc_attr_e( 'electrician kansas city mo', 'siloq-connector' ); ?>" style="flex:1;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;" maxlength="120">
                                </div>
                            <?php endfor; ?>
                            </div>
                            <div id="siloq-goals-keyword-map" style="margin-top:12px;display:none;">
                                <p style="font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;"><?php _e( 'Auto-matched pages:', 'siloq-connector' ); ?></p>
                                <table style="width:100%;border-collapse:collapse;font-size:12px;" id="siloq-goals-keyword-map-table">
                                    <thead><tr style="background:#f8fafc;">
                                        <th style="text-align:left;padding:6px 8px;border:1px solid #e2e8f0;"><?php _e( 'Keyword', 'siloq-connector' ); ?></th>
                                        <th style="text-align:left;padding:6px 8px;border:1px solid #e2e8f0;"><?php _e( 'Matched Page', 'siloq-connector' ); ?></th>
                                    </tr></thead>
                                    <tbody id="siloq-goals-keyword-map-body"></tbody>
                                </table>
                            </div>
                        </div>

                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:12px;">
                            <button type="button" id="siloq-goals-save-btn" class="siloq-btn siloq-btn--primary"><?php _e( 'Save & Sync Goals', 'siloq-connector' ); ?></button>
                        </div>
                        <span id="siloq-goals-msg" style="display:none;margin-top:8px;font-size:13px;color:#16a34a;font-weight:500;"></span>

                        <script>
                        (function(){
                            document.getElementById('siloq-goals-save-btn').addEventListener('click', function() {
                                var btn = this;
                                btn.disabled = true;
                                btn.textContent = '<?php echo esc_js( __( 'Saving...', 'siloq-connector' ) ); ?>';

                                var primaryEl = document.querySelector('input[name="siloq_goals_primary"]:checked');
                                var primaryGoal = primaryEl ? primaryEl.value : 'local_leads';

                                var keywords = [];
                                var inputs = document.querySelectorAll('.siloq-target-keyword-input');
                                for (var i = 0; i < inputs.length; i++) {
                                    var v = inputs[i].value.trim();
                                    if (v) keywords.push(v);
                                }

                                var fd = new FormData();
                                fd.append('action', 'siloq_save_goals_tab');
                                fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'siloq_ajax_nonce' ) ); ?>');
                                fd.append('primary_goal', primaryGoal);
                                for (var j = 0; j < keywords.length; j++) { fd.append('target_keywords[]', keywords[j]); }

                                fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                                    .then(function(r){ return r.json(); })
                                    .then(function(res){
                                        btn.disabled = false;
                                        btn.textContent = '<?php echo esc_js( __( 'Save & Sync Goals', 'siloq-connector' ) ); ?>';
                                        var msg = document.getElementById('siloq-goals-msg');
                                        if (res.success) {
                                            msg.textContent = '<?php echo esc_js( __( 'Goals saved and synced.', 'siloq-connector' ) ); ?>';
                                            msg.style.color = '#16a34a';
                                            // Show keyword → page mapping table
                                            var mapData = (res.data && res.data.keyword_map) ? res.data.keyword_map : [];
                                            if (mapData.length) {
                                                var tbody = document.getElementById('siloq-goals-keyword-map-body');
                                                var rows = '';
                                                for (var k = 0; k < mapData.length; k++) {
                                                    var row = mapData[k];
                                                    var pageCell = row.matched_title
                                                        ? '<a href="' + row.edit_url + '" target="_blank" style="color:#4f46e5;">' + row.matched_title + '</a>'
                                                        : '<span style="color:#9ca3af;">No match found</span>';
                                                    rows += '<tr><td style="padding:6px 8px;border:1px solid #e2e8f0;">' + row.keyword + '</td><td style="padding:6px 8px;border:1px solid #e2e8f0;">' + pageCell + '</td></tr>';
                                                }
                                                tbody.innerHTML = rows;
                                                document.getElementById('siloq-goals-keyword-map').style.display = 'block';
                                            }
                                        } else {
                                            msg.textContent = (res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __( 'Error saving goals.', 'siloq-connector' ) ); ?>';
                                            msg.style.color = '#ef4444';
                                        }
                                        msg.style.display = 'inline-block';
                                        setTimeout(function(){ msg.style.display = 'none'; }, 4000);
                                    })
                                    .catch(function(){
                                        btn.disabled = false;
                                        btn.textContent = '<?php echo esc_js( __( 'Save & Sync Goals', 'siloq-connector' ) ); ?>';
                                    });
                            });
                        })();
                        </script>

                    </div>
                </div><!-- /goals -->

            </div><!-- /settings tab -->

            <!-- ═══════ REDIRECTS TAB ═══════ -->
            <div id="siloq-tab-redirects" class="siloq-tab-panel" role="tabpanel" aria-hidden="true">

                <!-- ═══ URL RESTRUCTURE — Service Area Hub Detection ═══ -->
                <?php
                // Detect service-areas hub page and city spokes for restructure suggestion
                $_sa_hub = get_page_by_path('service-areas') ?: get_page_by_path('service-area');
                $_sa_spokes = [];
                if ( $_sa_hub ) {
                    $_synced_posts = get_posts([
                        'post_type'   => function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : ['page','post'],
                        'post_status' => 'publish',
                        'numberposts' => -1,
                        'meta_query'  => [['key'=>'_siloq_page_role','value'=>'spoke','compare'=>'=']],
                    ]);
                    foreach ( $_synced_posts as $_sp ) {
                        $slug = get_page_uri($_sp);
                        if ( strpos($slug, 'service-area') === false ) {
                            $_sa_spokes[] = ['id'=>$_sp->ID,'title'=>$_sp->post_title,'slug'=>$slug,'url'=>get_permalink($_sp->ID)];
                        }
                    }
                }
                if ( $_sa_hub && !empty($_sa_spokes) ) :
                ?>
                <div class="siloq-card" id="siloq-restructure-card" style="margin-bottom:16px;border:2px solid #e0e7ff;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
                        <div>
                            <h3 style="font-size:15px;font-weight:700;margin:0 0 6px;color:#1e293b;">🔀 URL Restructure Recommendation</h3>
                            <p style="font-size:12px;color:#64748b;margin:0;">
                                Siloq detected <strong><?php echo count($_sa_spokes); ?> city/spoke pages</strong> that should be nested under your
                                <strong><a href="<?php echo esc_url(get_permalink($_sa_hub->ID)); ?>" target="_blank"><?php echo esc_html($_sa_hub->post_title); ?></a></strong> hub.
                                Moving them to <code>/service-areas/[city-slug]/</code> strengthens your silo architecture and signals topical authority to search engines.
                            </p>
                        </div>
                        <button type="button" id="siloq-preview-restructure-btn" class="siloq-btn siloq-btn--primary siloq-btn--sm">
                            Preview URL Changes
                        </button>
                    </div>

                    <!-- Preview table (hidden until button clicked) -->
                    <div id="siloq-restructure-preview" style="display:none;">
                        <div style="overflow-x:auto;">
                            <table style="width:100%;font-size:12px;border-collapse:collapse;">
                                <thead>
                                    <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                        <th style="padding:8px 10px;text-align:left;font-weight:600;width:32px;">
                                            <input type="checkbox" id="siloq-restructure-select-all" checked style="cursor:pointer;">
                                        </th>
                                        <th style="padding:8px 10px;text-align:left;font-weight:600;color:#374151;">Page</th>
                                        <th style="padding:8px 10px;text-align:left;font-weight:600;color:#374151;">Current URL</th>
                                        <th style="padding:8px 10px;text-align:left;font-weight:600;color:#374151;width:30px;"></th>
                                        <th style="padding:8px 10px;text-align:left;font-weight:600;color:#374151;">New URL (301 Redirect)</th>
                                        <th style="padding:8px 10px;text-align:left;font-weight:600;color:#374151;width:80px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="siloq-restructure-tbody">
                                    <!-- Populated by JS -->
                                </tbody>
                            </table>
                        </div>

                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-top:14px;padding-top:12px;border-top:1px solid #e2e8f0;">
                            <p style="font-size:11px;color:#94a3b8;margin:0;">
                                ⚠️ Redirects are added to Siloq's redirect manager. You still need to manually update the WordPress parent page for each page to change the live URL.
                            </p>
                            <div style="display:flex;gap:8px;">
                                <button type="button" id="siloq-restructure-cancel-btn" class="siloq-btn siloq-btn--outline siloq-btn--sm">Cancel</button>
                                <button type="button" id="siloq-restructure-apply-btn" class="siloq-btn siloq-btn--primary siloq-btn--sm">
                                    ✓ Apply Selected Redirects
                                </button>
                            </div>
                        </div>
                        <div id="siloq-restructure-msg" style="display:none;margin-top:10px;font-size:12px;padding:8px 12px;border-radius:6px;"></div>
                    </div>

                    <div id="siloq-restructure-loading" style="display:none;padding:12px 0;font-size:12px;color:#64748b;">
                        <span class="siloq-spinner" style="display:inline-block;width:14px;height:14px;border:2px solid #e2e8f0;border-top-color:#6366f1;border-radius:50%;animation:spin 0.6s linear infinite;vertical-align:middle;margin-right:6px;"></span>
                        Loading suggestions…
                    </div>
                </div>

                <script type="text/javascript">
                (function($) {
                    var _restructureData = null;

                    // Preview button
                    $('#siloq-preview-restructure-btn').on('click', function() {
                        var $btn = $(this);
                        var $loading = $('#siloq-restructure-loading');
                        var $preview = $('#siloq-restructure-preview');
                        $btn.prop('disabled', true).text('Loading…');
                        $loading.show();
                        $preview.hide();

                        $.post(
                            (typeof siloqAjax !== 'undefined' ? siloqAjax.ajaxurl : ajaxurl),
                            { action: 'siloq_preview_city_redirects', nonce: (typeof siloqAjax !== 'undefined' ? siloqAjax.nonce : ''), target_prefix: '/service-areas/' },
                            function(r) {
                                $btn.prop('disabled', false).text('Refresh Preview');
                                $loading.hide();
                                if (!r.success) { alert('Error loading suggestions.'); return; }
                                _restructureData = r.data.suggestions;
                                renderRestructureTable(_restructureData);
                                $preview.show();
                            }
                        );
                    });

                    function renderRestructureTable(suggestions) {
                        var html = '';
                        if (!suggestions || !suggestions.length) {
                            html = '<tr><td colspan="6" style="padding:16px;text-align:center;color:#94a3b8;">All city pages are already under /service-areas/ — nothing to restructure.</td></tr>';
                        } else {
                            $.each(suggestions, function(i, s) {
                                var statusHtml = s.already_exists
                                    ? '<span style="color:#16a34a;font-weight:600;">✓ Exists</span>'
                                    : '<span style="color:#94a3b8;">New</span>';
                                var typeColor = s.page_type === 'city_spoke' ? '#6366f1' : '#64748b';
                                html += '<tr style="border-bottom:1px solid #f1f5f9;" data-from="' + siloqEsc(s.from) + '" data-to="' + siloqEsc(s.to) + '" data-post-id="' + parseInt(s.post_id || 0) + '" data-hub-post-id="' + parseInt(s.hub_post_id || 0) + '" data-original-parent="' + parseInt(s.original_parent || 0) + '">';
                                html += '<td style="padding:8px 10px;"><input type="checkbox" class="siloq-restructure-row-cb"' + (s.already_exists ? ' disabled' : ' checked') + '></td>';
                                html += '<td style="padding:8px 10px;"><div style="font-weight:600;color:#1e293b;">' + siloqEsc(s.title) + '</div><div style="font-size:10px;color:' + typeColor + ';margin-top:2px;">' + siloqEsc(s.page_type) + '</div></td>';
                                html += '<td style="padding:8px 10px;font-family:monospace;font-size:11px;color:#6b7280;">' + siloqEsc(s.from) + '</td>';
                                html += '<td style="padding:8px 10px;text-align:center;color:#94a3b8;">→</td>';
                                html += '<td style="padding:8px 10px;font-family:monospace;font-size:11px;color:#6366f1;">' + siloqEsc(s.to) + '</td>';
                                html += '<td style="padding:8px 10px;">' + statusHtml + '</td>';
                                html += '</tr>';
                            });
                        }
                        $('#siloq-restructure-tbody').html(html);
                    }

                    // Select all checkbox
                    $(document).on('change', '#siloq-restructure-select-all', function() {
                        var checked = $(this).is(':checked');
                        $('.siloq-restructure-row-cb:not(:disabled)').prop('checked', checked);
                    });

                    // Cancel
                    $('#siloq-restructure-cancel-btn').on('click', function() {
                        $('#siloq-restructure-preview').hide();
                        $('#siloq-preview-restructure-btn').text('Preview URL Changes');
                    });

                    // Apply selected
                    $('#siloq-restructure-apply-btn').on('click', function() {
                        var $btn = $(this);
                        var $msg = $('#siloq-restructure-msg');
                        var toApply = [];

                        $('#siloq-restructure-tbody tr').each(function() {
                            var $row = $(this);
                            if ($row.find('.siloq-restructure-row-cb').is(':checked')) {
                                toApply.push({
                                    from:            $row.data('from'),
                                    to:              $row.data('to'),
                                    post_id:         $row.data('post-id'),
                                    hub_post_id:     $row.data('hub-post-id'),
                                    original_parent: $row.data('original-parent')
                                });
                            }
                        });

                        if (!toApply.length) {
                            $msg.css({'background':'#fef3c7','color':'#92400e','border':'1px solid #fde68a'}).text('No pages selected.').show();
                            return;
                        }

                        $btn.prop('disabled', true).text('Applying…');
                        $msg.hide();

                        var done = 0, failed = 0, errorDetails = [];

                        function applyNext(idx) {
                            if (idx >= toApply.length) {
                                $btn.prop('disabled', false).text('✓ Apply Selected Redirects');
                                var msg = done + ' page' + (done !== 1 ? 's' : '') + ' restructured';
                                if (failed) { msg += ', ' + failed + ' failed'; }
                                msg += '.';
                                if (errorDetails.length) {
                                    msg += ' Errors: ' + errorDetails.join('; ');
                                }
                                $msg.css({
                                    'background': failed ? '#fee2e2' : '#dcfce7',
                                    'color': failed ? '#991b1b' : '#166534',
                                    'border': '1px solid ' + (failed ? '#fca5a5' : '#bbf7d0')
                                }).text(msg).show();
                                if (typeof loadSiloqRedirects === 'function') loadSiloqRedirects();
                                return;
                            }
                            var item = toApply[idx];
                            var postData = {
                                action:          'siloq_atomic_restructure_page',
                                nonce:           (typeof siloqAjax !== 'undefined' ? siloqAjax.nonce : ''),
                                post_id:         item.post_id,
                                hub_post_id:     item.hub_post_id,
                                original_parent: item.original_parent
                            };
                            $.post(
                                (typeof siloqAjax !== 'undefined' ? siloqAjax.ajaxurl : ajaxurl),
                                postData,
                                function(r) {
                                    var $tr = $('#siloq-restructure-tbody tr[data-from="' + item.from + '"]');
                                    if (r.success) {
                                        done++;
                                        $tr.find('td:last').html('<span style="color:#16a34a;font-weight:600;">✓ Restructured</span>');
                                    } else {
                                        failed++;
                                        var errMsg = (r.data && r.data.message) ? r.data.message : 'Unknown error';
                                        errorDetails.push(item.from + ': ' + errMsg);
                                        $tr.find('td:last').html('<span style="color:#dc2626;" title="' + siloqEsc(errMsg) + '">✗ Failed</span>');
                                    }
                                    $tr.find('.siloq-restructure-row-cb').prop('disabled', true).prop('checked', false);
                                    applyNext(idx + 1);
                                }
                            ).fail(function() {
                                failed++;
                                errorDetails.push(item.from + ': Request failed');
                                applyNext(idx + 1);
                            });
                        }
                        applyNext(0);
                    });

                    function siloqEsc(str) { return $('<div>').text(str || '').html(); }
                }(jQuery));
                </script>
                <?php endif; ?>
                <!-- ═══ /URL RESTRUCTURE ═══ -->

                <!-- Add New Redirect -->
                <div class="siloq-card" style="margin-bottom:16px;">
                    <div class="siloq-card-header" style="margin-bottom:14px;">
                        <h3 class="siloq-card-title" style="font-size:15px;font-weight:700;">Add New Redirect</h3>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 36px 1fr auto;gap:10px;align-items:end;margin-bottom:12px;">
                        <div>
                            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">Source URL</label>
                            <input type="text" id="siloq-redir-from" class="regular-text" placeholder="/source-page/" style="width:100%;font-size:13px;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;">
                            <p style="font-size:11px;color:#6b7280;margin:4px 0 0;">Enter a relative URL or start typing a page title, slug, or ID.</p>
                        </div>
                        <div style="text-align:center;padding-bottom:28px;">
                            <span style="font-size:18px;color:#9ca3af;">→</span>
                        </div>
                        <div>
                            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">Target URL</label>
                            <input type="text" id="siloq-redir-to" class="regular-text" placeholder="/target-page/" style="width:100%;font-size:13px;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;">
                            <p style="font-size:11px;color:#6b7280;margin:4px 0 0;">Enter a URL or start by typing a page or post title, slug or ID.</p>
                        </div>
                        <div style="padding-bottom:28px;">
                            <button type="button" id="siloq-redir-add-btn" class="siloq-btn siloq-btn--primary" style="white-space:nowrap;padding:8px 16px;">Add Redirect</button>
                        </div>
                    </div>
                    <!-- Redirect type selector -->
                    <div style="display:flex;gap:16px;align-items:center;padding:10px 0 4px;border-top:1px solid #f3f4f6;">
                        <label style="font-size:12px;font-weight:600;color:#374151;">Redirect Type:</label>
                        <select id="siloq-redir-type" style="font-size:12px;padding:4px 8px;border:1px solid #d1d5db;border-radius:4px;">
                            <option value="301">301 — Permanent Redirect (Recommended)</option>
                            <option value="302">302 — Temporary Redirect</option>
                            <option value="307">307 — Temporary Redirect (Preserve Method)</option>
                            <option value="410">410 — Content Deleted (No Target)</option>
                            <option value="451">451 — Content Unavailable for Legal Reasons</option>
                        </select>
                    </div>
                    <div id="siloq-redir-add-msg" style="display:none;margin-top:10px;font-size:12px;padding:7px 12px;border-radius:6px;"></div>
                </div>

                <!-- Redirects Table -->
                <div class="siloq-card">
                    <div class="siloq-card-header" style="margin-bottom:10px;">
                        <div style="display:flex;gap:12px;align-items:center;">
                            <h3 class="siloq-card-title" style="font-size:15px;font-weight:700;margin:0;">Redirects</h3>
                            <span id="siloq-redir-count-all" style="font-size:12px;color:#6b7280;"></span>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" id="siloq-redir-search" placeholder="Search URLs..." style="font-size:12px;padding:5px 10px;border:1px solid #d1d5db;border-radius:5px;width:180px;">
                            <button type="button" id="siloq-redir-refresh-btn" class="siloq-btn siloq-btn--outline siloq-btn--sm">
                                <span class="dashicons dashicons-update"></span> Refresh
                            </button>
                        </div>
                    </div>
                    <!-- Filter pills -->
                    <div style="display:flex;gap:6px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #f3f4f6;">
                        <button type="button" class="siloq-redir-filter siloq-redir-filter--active" data-filter="all" style="font-size:11px;padding:3px 10px;border-radius:999px;border:1px solid #d1d5db;background:#fff;cursor:pointer;">All <span id="siloq-redir-count-pill-all">0</span></button>
                        <button type="button" class="siloq-redir-filter" data-filter="enabled" style="font-size:11px;padding:3px 10px;border-radius:999px;border:1px solid #d1d5db;background:#fff;cursor:pointer;">Enabled <span id="siloq-redir-count-pill-enabled">0</span></button>
                        <button type="button" class="siloq-redir-filter" data-filter="disabled" style="font-size:11px;padding:3px 10px;border-radius:999px;border:1px solid #d1d5db;background:#fff;cursor:pointer;">Disabled <span id="siloq-redir-count-pill-disabled">0</span></button>
                    </div>
                    <!-- Redirects list -->
                    <div id="siloq-redir-list">
                        <div class="siloq-pages-loading"><span class="siloq-spinner"></span><span>Loading redirects...</span></div>
                    </div>
                </div>

            </div><!-- /redirects tab -->

            <!-- ═══════ DEPTH ENGINE TAB ═══════ -->
            <div id="siloq-tab-depth-engine" class="siloq-tab-panel" role="tabpanel" aria-hidden="true">
<?php
$_depth_primary_services = json_decode( get_option('siloq_primary_services', '[]'), true );
if ( ! is_array( $_depth_primary_services ) ) $_depth_primary_services = array();
$_depth_biz_type = get_option( 'siloq_business_type', 'local_business' );
?>
                <!-- STATE 1: Silo List -->
                <div id="siloq-depth-state-list">
                    <div class="siloq-card">
                        <div class="siloq-card-header">
                            <h3 class="siloq-card-title">Topical Depth Engine</h3>
                        </div>
                        <p style="color:#666;margin:0 0 16px;">Select a silo to analyze its content depth and get AI-powered recommendations.</p>
                        <div id="siloq-depth-silo-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px;">
                            <div style="text-align:center;padding:30px;color:#888;"><span class="spinner is-active" style="float:none;"></span> Loading hub pages&hellip;</div>
                        </div>
                        <div id="siloq-depth-no-hubs" style="display:none;text-align:center;padding:40px 0;color:#888;">
                            <p>No hub pages found. Go to the <strong>Dashboard</strong> tab and sync your site first.</p>
                        </div>
                    </div>
                </div>

                <!-- STATE 2: Topic Boundary Setup -->
                <div id="siloq-depth-state-setup" style="display:none;">
                    <div class="siloq-card">
                        <div class="siloq-card-header">
                            <button id="siloq-depth-back-setup" class="button" style="margin-right:12px;">&larr; Back to silos</button>
                            <h3 class="siloq-card-title" id="siloq-depth-setup-title" style="display:inline;vertical-align:middle;"></h3>
                        </div>
                        <form id="siloq-depth-boundary-form" style="max-width:600px;">
                            <input type="hidden" id="siloq-depth-setup-post-id" value="">

                            <div style="margin-bottom:16px;">
                                <label for="siloq-depth-core-topic" style="display:block;font-weight:600;margin-bottom:4px;">What is this silo about?</label>
                                <input type="text" id="siloq-depth-core-topic" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;" placeholder="e.g. Residential Electrical Services" required>
                            </div>

                            <div style="margin-bottom:16px;">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Related topics to include</label>
                                <p style="color:#888;font-size:12px;margin:0 0 6px;">Topics that are related and should be covered in this silo</p>
                                <div class="siloq-tag-input-wrap" id="siloq-depth-adjacent-wrap">
                                    <div class="siloq-tag-chips" id="siloq-depth-adjacent-chips"></div>
                                    <input type="text" class="siloq-tag-text" id="siloq-depth-adjacent-input" placeholder="Type and press Enter or comma" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;">
                                    <input type="hidden" id="siloq-depth-adjacent-hidden" value="">
                                </div>
                            </div>

                            <div style="margin-bottom:16px;">
                                <label style="display:block;font-weight:600;margin-bottom:4px;">Topics to exclude</label>
                                <p style="color:#888;font-size:12px;margin:0 0 6px;">Topics that belong in a different silo</p>
                                <div class="siloq-tag-input-wrap" id="siloq-depth-exclude-wrap">
                                    <div class="siloq-tag-chips" id="siloq-depth-exclude-chips"></div>
                                    <input type="text" class="siloq-tag-text" id="siloq-depth-exclude-input" placeholder="Type and press Enter or comma" style="width:100%;padding:8px 10px;border:1px solid #ccc;border-radius:4px;">
                                    <input type="hidden" id="siloq-depth-exclude-hidden" value="">
                                </div>
                            </div>

                            <div style="margin-bottom:20px;">
                                <label for="siloq-depth-entity-type" style="display:block;font-weight:600;margin-bottom:4px;">Entity Type</label>
                                <select id="siloq-depth-entity-type" style="min-width:260px;padding:6px 10px;border:1px solid #ccc;border-radius:4px;">
                                    <option value="local_business">Local Business</option>
                                    <option value="ecommerce">E-Commerce</option>
                                    <option value="publisher">Publisher</option>
                                    <option value="b2b_saas">B2B SaaS</option>
                                </select>
                            </div>

                            <button type="submit" class="button button-primary button-hero" id="siloq-depth-start-scan-btn">Start Depth Analysis &rarr;</button>
                            <span id="siloq-depth-scan-spinner" class="spinner" style="float:none;margin-top:0;"></span>
                            <div id="siloq-depth-scan-status" style="display:none;margin-top:12px;padding:12px 16px;background:#f0f6ff;border:1px solid #b3d4fc;border-radius:6px;color:#1a4a8a;font-weight:500;"></div>
                        </form>
                    </div>
                </div>

                <!-- STATE 3: Results View -->
                <div id="siloq-depth-state-results" style="display:none;">
                    <div class="siloq-card">
                        <div class="siloq-card-header" style="display:flex;align-items:center;justify-content:space-between;">
                            <div>
                                <button id="siloq-depth-back-results" class="button" style="margin-right:12px;">&larr; Back to silos</button>
                                <h3 class="siloq-card-title" id="siloq-depth-results-title" style="display:inline;vertical-align:middle;"></h3>
                                <span id="siloq-depth-last-scanned" style="color:#888;font-size:12px;margin-left:12px;"></span>
                            </div>
                            <button id="siloq-depth-rescan-btn" class="button button-primary">Rescan</button>
                        </div>

                        <!-- Score Cards -->
                        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
                            <div class="siloq-card" id="siloq-score-semantic" style="text-align:center;padding:18px;">
                                <div style="font-size:13px;color:#666;margin-bottom:4px;">Semantic Density</div>
                                <div class="siloq-depth-score-val" style="font-size:32px;font-weight:700;line-height:1.2;">&mdash;</div>
                            </div>
                            <div class="siloq-card" id="siloq-score-closure" style="text-align:center;padding:18px;">
                                <div style="font-size:13px;color:#666;margin-bottom:4px;">Topical Closure</div>
                                <div class="siloq-depth-score-val" style="font-size:32px;font-weight:700;line-height:1.2;">&mdash;</div>
                            </div>
                            <div class="siloq-card" id="siloq-score-breadth" style="text-align:center;padding:18px;">
                                <div style="font-size:13px;color:#666;margin-bottom:4px;">Coverage Breadth %</div>
                                <div class="siloq-depth-score-val" style="font-size:32px;font-weight:700;line-height:1.2;">&mdash;</div>
                            </div>
                            <div class="siloq-card" id="siloq-score-freshness" style="text-align:center;padding:18px;">
                                <div style="font-size:13px;color:#666;margin-bottom:4px;">Freshness</div>
                                <div class="siloq-depth-score-val" style="font-size:32px;font-weight:700;line-height:1.2;">&mdash;</div>
                            </div>
                        </div>

                        <!-- Mistake Flags -->
                        <div id="siloq-depth-mistake-flags" style="display:none;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:10px 14px;margin-bottom:16px;color:#856404;"></div>

                        <!-- Gap Report -->
                        <div id="siloq-depth-gaps">
                            <h4 style="margin:0 0 12px;">Gap Report</h4>

                            <div id="siloq-depth-critical-wrap" style="margin-bottom:14px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                    <button class="siloq-depth-toggle" onclick="this.parentElement.nextElementSibling.style.display=this.parentElement.nextElementSibling.style.display==='none'?'block':'none';this.querySelector('.siloq-depth-chev').classList.toggle('open')" style="background:none;border:none;cursor:pointer;font-size:14px;font-weight:600;padding:6px 0;color:#dc3545;">
                                        <span class="siloq-depth-chev" style="display:inline-block;transition:transform .2s;margin-right:4px;">&#9654;</span> &#128308; Critical Gaps <span id="siloq-depth-critical-count" class="siloq-badge siloq-badge--red" style="margin-left:6px;">0</span>
                                    </button>
                                    <button class="button button-small" id="siloq-depth-bulk-critical" style="display:none;">Add All Critical to Plan</button>
                                </div>
                                <div id="siloq-depth-critical-list" style="display:none;margin-left:18px;"></div>
                            </div>

                            <div id="siloq-depth-thin-wrap" style="margin-bottom:14px;">
                                <button class="siloq-depth-toggle" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none';this.querySelector('.siloq-depth-chev').classList.toggle('open')" style="background:none;border:none;cursor:pointer;font-size:14px;font-weight:600;padding:6px 0;color:#e6a700;">
                                    <span class="siloq-depth-chev" style="display:inline-block;transition:transform .2s;margin-right:4px;">&#9654;</span> &#128993; Thin Content <span id="siloq-depth-thin-count" class="siloq-badge siloq-badge--yellow" style="margin-left:6px;">0</span>
                                </button>
                                <div id="siloq-depth-thin-list" style="display:none;margin-left:18px;"></div>
                            </div>

                            <div id="siloq-depth-standard-wrap" style="margin-bottom:14px;">
                                <button class="siloq-depth-toggle" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none';this.querySelector('.siloq-depth-chev').classList.toggle('open')" style="background:none;border:none;cursor:pointer;font-size:14px;font-weight:600;padding:6px 0;color:#555;">
                                    <span class="siloq-depth-chev" style="display:inline-block;transition:transform .2s;margin-right:4px;">&#9654;</span> &#128203; Standard Gaps <span id="siloq-depth-standard-count" class="siloq-badge" style="margin-left:6px;">0</span>
                                </button>
                                <div id="siloq-depth-standard-list" style="display:none;margin-left:18px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <style>
                    .siloq-depth-chev.open { transform: rotate(90deg); }
                    .siloq-depth-gap-item { display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee; }
                    .siloq-depth-gap-item:last-child { border-bottom:none; }
                    .siloq-depth-priority { display:inline-block;min-width:36px;text-align:center;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;color:#fff; }
                    .siloq-depth-hub-card { border:1px solid #e0e0e0;border-radius:8px;padding:20px;background:#fff;transition:box-shadow .2s; }
                    .siloq-depth-hub-card:hover { box-shadow:0 2px 8px rgba(0,0,0,.08); }
                    .siloq-tag-chips { display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px; }
                    .siloq-tag-chip { display:inline-flex;align-items:center;gap:4px;background:#e8f0fe;color:#1a4a8a;padding:4px 10px;border-radius:14px;font-size:13px; }
                    .siloq-tag-chip .siloq-tag-x { cursor:pointer;font-weight:700;color:#999;margin-left:2px; }
                    .siloq-tag-chip .siloq-tag-x:hover { color:#dc3545; }
                </style>
                <script>
                (function($) {
                    var depthNonce = '<?php echo esc_js( wp_create_nonce("siloq_ajax_nonce") ); ?>';
                    var defaultBizType = '<?php echo esc_js( $_depth_biz_type ); ?>';
                    var defaultServices = <?php echo wp_json_encode( array_values( $_depth_primary_services ) ); ?>;
                    var currentPostId = null;
                    var currentSiloId = null;
                    var silosData = [];

                    // ─── Tag input helper ───
                    function initTagInput(inputId, chipsId, hiddenId, prefill) {
                        var tags = prefill ? prefill.slice() : [];
                        var $input = $('#' + inputId);
                        var $chips = $('#' + chipsId);
                        var $hidden = $('#' + hiddenId);

                        function render() {
                            $chips.empty();
                            tags.forEach(function(tag, i) {
                                $chips.append('<span class="siloq-tag-chip">' + $('<span>').text(tag).html() + ' <span class="siloq-tag-x" data-idx="' + i + '">&times;</span></span>');
                            });
                            $hidden.val(tags.join(','));
                        }
                        function addTag(val) {
                            val = val.trim();
                            if (val && tags.indexOf(val) === -1) { tags.push(val); render(); }
                        }
                        $input.on('keydown', function(e) {
                            if (e.key === 'Enter' || e.key === ',') {
                                e.preventDefault();
                                addTag($input.val().replace(/,/g, ''));
                                $input.val('');
                            }
                        });
                        $chips.on('click', '.siloq-tag-x', function() {
                            tags.splice($(this).data('idx'), 1);
                            render();
                        });
                        render();
                        return { getTags: function() { return tags; }, setTags: function(t) { tags = t.slice(); render(); } };
                    }

                    var adjacentTags = null;
                    var excludeTags = null;

                    // ─── State transitions ───
                    function showState(state) {
                        $('#siloq-depth-state-list, #siloq-depth-state-setup, #siloq-depth-state-results').hide();
                        $('#siloq-depth-state-' + state).show();
                    }

                    // ─── Score color ───
                    function scoreColor(val) {
                        if (val >= 80) return '#22c55e';
                        if (val >= 60) return '#eab308';
                        if (val >= 40) return '#f97316';
                        return '#ef4444';
                    }

                    function renderScoreCard(id, val) {
                        var $card = $(id);
                        var num = Math.round(val);
                        $card.find('.siloq-depth-score-val').text(num).css('color', scoreColor(num));
                        $card.css('border-left', '4px solid ' + scoreColor(num));
                    }

                    // ─── Gap item rendering ───
                    function renderCriticalGapItem(item) {
                        var bg = '#dc3545';
                        var label = $('<span>').text(item.label || item.subtopic_label || item.page_title || 'Untitled').html();
                        var priority = parseInt(item.priority || item.priority_score || 0);
                        return '<div class="siloq-depth-gap-item">' +
                            '<span>' + label + '</span>' +
                            '<span style="display:flex;align-items:center;gap:8px;">' +
                                '<span class="siloq-depth-priority" style="background:' + bg + ';">' + priority + '</span>' +
                                '<button class="button button-small siloq-create-page-btn" data-title="' + $('<span>').text(item.label || item.subtopic_label || 'Untitled').html() + '" data-type="service" data-parent-id="' + (currentPostId || 0) + '">Create Page &rarr;</button>' +
                                (item.id ? '<button class="button button-small siloq-depth-add-plan" data-id="' + item.id + '">Add to Plan</button>' : '') +
                            '</span>' +
                        '</div>';
                    }

                    function renderThinGapItem(item) {
                        var label = $('<span>').text(item.page_title || item.label || item.subtopic_label || 'Untitled').html();
                        var priority = parseInt(item.priority || item.priority_score || 0);
                        var editUrl = item.edit_url || (item.page_id ? '<?php echo esc_js( admin_url("post.php?action=edit&post=") ); ?>' + item.page_id : '');
                        return '<div class="siloq-depth-gap-item">' +
                            '<span>' + label + '<br><small style="color:#888;font-size:11px;">Thin content — exists but needs more depth</small></span>' +
                            '<span style="display:flex;align-items:center;gap:8px;">' +
                                '<span class="siloq-depth-priority" style="background:#e6a700;">' + priority + '</span>' +
                                (editUrl ? '<a href="' + editUrl + '" class="button button-small" target="_blank">Open Editor &rarr;</a>' : '') +
                                (item.id ? '<button class="button button-small siloq-depth-add-plan" data-id="' + item.id + '">Add to Plan</button>' : '') +
                            '</span>' +
                        '</div>';
                    }

                    function renderStandardGapItem(item) {
                        var label = $('<span>').text(item.label || item.subtopic_label || item.page_title || 'Untitled').html();
                        var priority = parseInt(item.priority || item.priority_score || 0);
                        return '<div class="siloq-depth-gap-item">' +
                            '<span>' + label + '</span>' +
                            '<span style="display:flex;align-items:center;gap:8px;">' +
                                '<span class="siloq-depth-priority" style="background:#6c757d;">' + priority + '</span>' +
                                '<button class="button button-small siloq-create-page-btn" data-title="' + $('<span>').text(item.label || item.subtopic_label || 'Untitled').html() + '" data-type="service" data-parent-id="' + (currentPostId || 0) + '">Create Page &rarr;</button>' +
                                (item.id ? '<button class="button button-small siloq-depth-add-plan" data-id="' + item.id + '">Add to Plan</button>' : '') +
                            '</span>' +
                        '</div>';
                    }

                    // ─── STATE 1: Load local silos ───
                    function loadSilos() {
                        var $grid = $('#siloq-depth-silo-grid');
                        $.post(ajaxurl, { action: 'siloq_get_local_silos', nonce: depthNonce }, function(res) {
                            if (res.success && res.data && res.data.length) {
                                silosData = res.data;
                                $grid.empty();
                                $.each(res.data, function(i, hub) {
                                    var hasResults = !!hub.last_scanned;
                                    var html = '<div class="siloq-depth-hub-card">' +
                                        '<h4 style="margin:0 0 6px;">' + $('<span>').text(hub.title).html() + '</h4>' +
                                        '<p style="color:#888;font-size:12px;margin:0 0 10px;word-break:break-all;">' + $('<span>').text(hub.url).html() + '</p>' +
                                        '<span class="siloq-badge" style="margin-bottom:12px;display:inline-block;">' + hub.spoke_count + ' spoke pages</span>' +
                                        '<div style="display:flex;gap:8px;flex-wrap:wrap;">' +
                                            '<button class="button button-primary siloq-depth-setup-btn" data-post-id="' + hub.post_id + '">Setup &amp; Scan &rarr;</button>' +
                                            (hasResults ? '<button class="button siloq-depth-view-btn" data-post-id="' + hub.post_id + '">View Results</button>' : '') +
                                        '</div>' +
                                    '</div>';
                                    $grid.append(html);
                                });
                                $('#siloq-depth-no-hubs').hide();
                            } else {
                                $grid.empty();
                                $('#siloq-depth-no-hubs').show();
                            }
                        }).fail(function() {
                            $grid.html('<p style="color:#dc3545;">Failed to load hub pages.</p>');
                        });
                    }

                    // ─── STATE 2: Setup & Scan ───
                    function openSetup(postId) {
                        var hub = silosData.find(function(s) { return s.post_id == postId; });
                        if (!hub) return;
                        currentPostId = postId;
                        currentSiloId = hub.api_silo_id;

                        $('#siloq-depth-setup-title').text(hub.title);
                        $('#siloq-depth-setup-post-id').val(postId);
                        $('#siloq-depth-core-topic').val(hub.boundary ? hub.boundary.core_topic : hub.title);

                        // Map business type
                        var bizMap = { local_service: 'local_business', ecommerce: 'ecommerce', publisher: 'publisher', b2b_saas: 'b2b_saas' };
                        var entitySel = (hub.boundary && hub.boundary.entity_type) ? hub.boundary.entity_type : (bizMap[defaultBizType] || defaultBizType);
                        $('#siloq-depth-entity-type').val(entitySel);

                        // Init tag inputs
                        var adjPrefill = (hub.boundary && hub.boundary.adjacent_topics) ? hub.boundary.adjacent_topics : defaultServices;
                        var exclPrefill = (hub.boundary && hub.boundary.out_of_scope_topics) ? hub.boundary.out_of_scope_topics : [];
                        adjacentTags = initTagInput('siloq-depth-adjacent-input', 'siloq-depth-adjacent-chips', 'siloq-depth-adjacent-hidden', adjPrefill);
                        excludeTags = initTagInput('siloq-depth-exclude-input', 'siloq-depth-exclude-chips', 'siloq-depth-exclude-hidden', exclPrefill);

                        $('#siloq-depth-scan-status').hide();
                        $('#siloq-depth-start-scan-btn').prop('disabled', false).text('Start Depth Analysis \u2192');
                        showState('setup');
                    }

                    // ─── STATE 3: Results ───
                    function openResults(postId) {
                        var hub = silosData.find(function(s) { return s.post_id == postId; });
                        if (!hub) return;
                        currentPostId = postId;
                        currentSiloId = hub.api_silo_id;

                        $('#siloq-depth-results-title').text(hub.title);
                        $('#siloq-depth-last-scanned').text(hub.last_scanned ? 'Last scanned: ' + hub.last_scanned : '');

                        loadResultsData(postId);
                        showState('results');
                    }

                    function loadResultsData(postId) {
                        var hub = silosData.find(function(s) { return s.post_id == postId; });
                        var siloId = hub ? hub.api_silo_id : currentSiloId;
                        if (!siloId) return;

                        // Load scores
                        $.post(ajaxurl, { action: 'siloq_get_depth_scores', nonce: depthNonce, silo_id: siloId, post_id: postId }, function(res) {
                            if (res.success && res.data) {
                                var d = res.data;
                                renderScoreCard('#siloq-score-semantic', d.semantic_density_score || 0);
                                renderScoreCard('#siloq-score-closure', d.topical_closure_score || 0);
                                renderScoreCard('#siloq-score-breadth', d.coverage_breadth_pct || 0);
                                renderScoreCard('#siloq-score-freshness', d.freshness_score || 0);

                                var flags = d.depth_mistake_flags || [];
                                if (flags.length) {
                                    $('#siloq-depth-mistake-flags').html('<strong>Depth Flags:</strong> ' + flags.map(function(f) { return $('<span>').text(f).html(); }).join(', ')).show();
                                } else {
                                    $('#siloq-depth-mistake-flags').hide();
                                }
                            }
                        });

                        // Load gap report
                        $.post(ajaxurl, { action: 'siloq_get_gap_report', nonce: depthNonce, silo_id: siloId, post_id: postId }, function(res) {
                            if (res.success && res.data) {
                                var d = res.data;
                                // API already pre-filters by priority — trust the API, no client-side double-filter
                                var critical = d.critical_gaps || [];
                                var thin = d.thin_pages || [];
                                var standard = d.standard_gaps || [];

                                $('#siloq-depth-critical-count').text(critical.length);
                                $('#siloq-depth-critical-list').html(critical.length ? critical.map(renderCriticalGapItem).join('') : '<p style="color:#888;">None</p>');
                                $('#siloq-depth-bulk-critical').toggle(critical.length > 0);

                                $('#siloq-depth-thin-count').text(thin.length);
                                $('#siloq-depth-thin-list').html(thin.length ? thin.map(renderThinGapItem).join('') : '<p style="color:#888;">None</p>');

                                $('#siloq-depth-standard-count').text(standard.length);
                                $('#siloq-depth-standard-list').html(standard.length ? standard.map(renderStandardGapItem).join('') : '<p style="color:#888;">None</p>');
                            }
                        });
                    }

                    // ─── Event handlers ───

                    // Setup & Scan button
                    $(document).on('click', '.siloq-depth-setup-btn', function() { openSetup($(this).data('post-id')); });
                    // View Results button
                    $(document).on('click', '.siloq-depth-view-btn', function() { openResults($(this).data('post-id')); });
                    // Back buttons
                    $('#siloq-depth-back-setup, #siloq-depth-back-results').on('click', function() { showState('list'); loadSilos(); });

                    // Form submit — save boundary + run scan
                    $('#siloq-depth-boundary-form').on('submit', function(e) {
                        e.preventDefault();
                        var postId = $('#siloq-depth-setup-post-id').val();
                        var coreTopic = $('#siloq-depth-core-topic').val().trim();
                        if (!coreTopic) { alert('Core topic is required.'); return; }

                        var $btn = $('#siloq-depth-start-scan-btn');
                        var $status = $('#siloq-depth-scan-status');
                        var $spinner = $('#siloq-depth-scan-spinner');
                        $btn.prop('disabled', true).text('Saving...');
                        $spinner.addClass('is-active');

                        // Step 1: Save topic boundary
                        $.post(ajaxurl, {
                            action: 'siloq_save_topic_boundary',
                            nonce: depthNonce,
                            post_id: postId,
                            core_topic: coreTopic,
                            adjacent_topics: $('#siloq-depth-adjacent-hidden').val(),
                            out_of_scope_topics: $('#siloq-depth-exclude-hidden').val(),
                            entity_type: $('#siloq-depth-entity-type').val()
                        }, function(res) {
                            if (!res.success) {
                                $btn.prop('disabled', false).text('Start Depth Analysis \u2192');
                                $spinner.removeClass('is-active');
                                alert('Save failed: ' + (res.data && res.data.message ? res.data.message : 'Unknown error'));
                                return;
                            }
                            // Update local data
                            currentSiloId = res.data.silo_id;
                            var hub = silosData.find(function(s) { return s.post_id == postId; });
                            if (hub) { hub.api_silo_id = currentSiloId; }

                            // Step 2: Run depth scan
                            $btn.text('Scanning...');
                            $status.text('Generating subtopic map... this takes 30\u201360 seconds').show();

                            $.ajax({
                                url: ajaxurl,
                                method: 'POST',
                                timeout: 120000,
                                data: {
                                    action: 'siloq_run_depth_scan',
                                    nonce: depthNonce,
                                    silo_id: currentSiloId,
                                    post_id: postId
                                },
                                success: function(scanRes) {
                                    $spinner.removeClass('is-active');
                                    $status.hide();
                                    if (scanRes.success) {
                                        if (hub) { hub.last_scanned = new Date().toISOString(); }
                                        openResults(postId);
                                    } else {
                                        $btn.prop('disabled', false).text('Start Depth Analysis \u2192');
                                        alert('Scan failed: ' + (scanRes.data && scanRes.data.message ? scanRes.data.message : 'Unknown error'));
                                    }
                                },
                                error: function(xhr) {
                                    $spinner.removeClass('is-active');
                                    $status.hide();
                                    $btn.prop('disabled', false).text('Start Depth Analysis \u2192');
                                    var msg = xhr.status === 504 ? 'Generation is still running \u2014 wait 30 seconds and click Rescan to see results.' : 'Scan request failed.';
                                    alert(msg);
                                }
                            });
                        }).fail(function() {
                            $btn.prop('disabled', false).text('Start Depth Analysis \u2192');
                            $spinner.removeClass('is-active');
                            alert('Save request failed.');
                        });
                    });

                    // Rescan button
                    $('#siloq-depth-rescan-btn').on('click', function() {
                        if (currentPostId) openSetup(currentPostId);
                    });

                    // Add to Plan
                    $(document).on('click', '.siloq-depth-add-plan', function() {
                        var $btn = $(this);
                        var subtopicId = $btn.data('id');
                        if (!currentSiloId || !subtopicId) return;
                        $btn.prop('disabled', true).text('Adding...');
                        $.post(ajaxurl, { action: 'siloq_add_to_plan', nonce: depthNonce, silo_id: currentSiloId, subtopic_id: subtopicId }, function(res) {
                            if (res.success) {
                                $btn.text('\u2713 Added').css('color', '#22c55e');
                            } else {
                                $btn.prop('disabled', false).text('Add to Plan');
                                alert('Failed: ' + (res.data && res.data.message ? res.data.message : 'Unknown error'));
                            }
                        });
                    });

                    // Bulk Add Critical to Plan
                    $('#siloq-depth-bulk-critical').on('click', function() {
                        var $btn = $(this);
                        var ids = [];
                        $('#siloq-depth-critical-list .siloq-depth-add-plan').each(function() { ids.push($(this).data('id')); });
                        if (!ids.length || !currentSiloId) return;
                        $btn.prop('disabled', true).text('Adding all...');
                        $.post(ajaxurl, {
                            action: 'siloq_bulk_add_to_plan',
                            nonce: depthNonce,
                            silo_id: currentSiloId,
                            subtopic_ids: ids
                        }, function(res) {
                            if (res.success) {
                                $btn.text('\u2713 All Added').css('color', '#22c55e');
                                $('#siloq-depth-critical-list .siloq-depth-add-plan').text('\u2713 Added').prop('disabled', true).css('color', '#22c55e');
                            } else {
                                $btn.prop('disabled', false).text('Add All Critical to Plan');
                                alert('Failed: ' + (res.data && res.data.message ? res.data.message : 'Unknown error'));
                            }
                        });
                    });

                    // Auto-load silos when Depth Engine tab is shown
                    var loaded = false;
                    $(document).on('click', '[aria-controls="siloq-tab-depth-engine"]', function() {
                        if (!loaded) { loaded = true; loadSilos(); }
                    });
                    if (window.location.hash === '#siloq-tab-depth-engine') { loaded = true; loadSilos(); }
                })(jQuery);
                </script>
            </div><!-- /depth-engine tab -->

            <!-- ═══════ GOALS TAB ═══════ -->
            <div id="siloq-tab-goals" class="siloq-tab-panel" role="tabpanel" aria-hidden="true">
<?php
// Pre-load existing values
$_goals_primary_goal       = get_option('siloq_primary_goal', '');
$_goals_priority_services_raw = get_option('siloq_priority_services', get_option('siloq_primary_services', '[]'));
$_goals_priority_services  = json_decode($_goals_priority_services_raw, true);
if (!is_array($_goals_priority_services)) $_goals_priority_services = array();

$_goals_priority_cities_raw  = get_option('siloq_priority_cities', '');
if (empty($_goals_priority_cities_raw)) {
    // Fall back to service_areas
    $_sa_raw = get_option('siloq_service_areas', '[]');
    $_sa_arr = json_decode($_sa_raw, true);
    $_goals_priority_cities = array();
    if (is_array($_sa_arr)) {
        foreach ($_sa_arr as $_sa) {
            if (is_string($_sa)) $_goals_priority_cities[] = $_sa;
            elseif (is_array($_sa) && !empty($_sa['city'])) $_goals_priority_cities[] = $_sa['city'];
        }
    }
} else {
    $_goals_priority_cities = json_decode($_goals_priority_cities_raw, true);
    if (!is_array($_goals_priority_cities)) $_goals_priority_cities = array();
}

$_goals_geo_pages_raw = get_option('siloq_geo_priority_pages', '[]');
$_goals_geo_pages     = json_decode($_goals_geo_pages_raw, true);
if (!is_array($_goals_geo_pages)) $_goals_geo_pages = array();
$_goals_target_keywords_raw = get_option('siloq_target_keywords_' . get_option('siloq_site_id','0'), get_option('siloq_target_keywords', '[]'));
$_goals_target_keywords     = json_decode($_goals_target_keywords_raw, true);
if (!is_array($_goals_target_keywords)) $_goals_target_keywords = array();
?>
<div class="siloq-card" style="max-width:700px;">
  <div class="siloq-card-header">
    <h3 class="siloq-card-title">🎯 Goals</h3>
    <p style="font-size:12px;color:#6b7280;margin:4px 0 0;">Tell Siloq what you're trying to achieve. This powers your personalized SEO recommendations.</p>
  </div>

  <div id="siloq-goals-saved-msg" style="display:none;padding:10px 16px;background:#d1fae5;border-radius:8px;font-size:13px;color:#065f46;font-weight:500;margin-bottom:16px;"></div>

  <!-- Section 1: Primary Goal -->
  <div style="margin-bottom:28px;">
    <h4 style="font-size:13px;font-weight:700;color:#111;margin:0 0 12px;">1. What's your primary goal?</h4>
    <?php
    $goal_options = array(
        'local_calls'       => 'More local phone calls',
        'form_submissions'  => 'More form submissions',
        'foot_traffic'      => 'More foot traffic',
        'brand_awareness'   => 'Build brand awareness',
        'service_rankings'  => 'Rank for specific services',
        'geo_citations'     => 'Rank in AI assistants (ChatGPT, Gemini, Perplexity)',
    );
    foreach ($goal_options as $val => $label): ?>
    <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;cursor:pointer;">
      <input type="radio" name="siloq_goals_primary" value="<?php echo esc_attr($val); ?>" <?php checked($_goals_primary_goal, $val); ?> style="margin:0;">
      <span style="font-size:13px;color:#374151;"><?php echo esc_html($label); ?></span>
    </label>
    <?php endforeach; ?>
  </div>

  <!-- Section 2: Priority Services -->
  <div style="margin-bottom:28px;">
    <h4 style="font-size:13px;font-weight:700;color:#111;margin:0 0 4px;">2. Priority services</h4>
    <?php if (empty($_goals_priority_services)): ?>
    <p style="font-size:12px;color:#6b7280;margin:0 0 10px;">Enter services you want to prioritize (comma-separated):</p>
    <input type="text" id="siloq-goals-services-input" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;" placeholder="e.g. electrical panel upgrade, EV charger installation">
    <?php else: ?>
    <p style="font-size:12px;color:#6b7280;margin:0 0 10px;">Pre-filled from your onboarding. Uncheck any you don't want to prioritize:</p>
    <div id="siloq-goals-services-list">
      <?php foreach ($_goals_priority_services as $svc): ?>
      <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;cursor:pointer;">
        <input type="checkbox" name="siloq_goals_service[]" value="<?php echo esc_attr($svc); ?>" checked style="margin:0;">
        <span style="font-size:13px;color:#374151;"><?php echo esc_html($svc); ?></span>
      </label>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Section 3: Priority Cities -->
  <div style="margin-bottom:28px;">
    <h4 style="font-size:13px;font-weight:700;color:#111;margin:0 0 4px;">3. Priority cities</h4>
    <?php if (empty($_goals_priority_cities)): ?>
    <p style="font-size:12px;color:#6b7280;margin:0 0 10px;">Enter cities you want to rank in (comma-separated):</p>
    <input type="text" id="siloq-goals-cities-input" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;" placeholder="e.g. Kansas City, Lee's Summit, Independence">
    <?php else: ?>
    <p style="font-size:12px;color:#6b7280;margin:0 0 10px;">Pre-filled from your service areas. Uncheck any you don't want to prioritize:</p>
    <div id="siloq-goals-cities-list">
      <?php foreach ($_goals_priority_cities as $city): ?>
      <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;cursor:pointer;">
        <input type="checkbox" name="siloq_goals_city[]" value="<?php echo esc_attr($city); ?>" checked style="margin:0;">
        <span style="font-size:13px;color:#374151;"><?php echo esc_html($city); ?></span>
      </label>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Section 4: Target Keyword Phrases (replaces GEO Priority Pages picker) -->
  <div style="margin-bottom:28px;">
    <h4 style="font-size:13px;font-weight:700;color:#111;margin:0 0 4px;">4. Target keyword phrases</h4>
    <p style="font-size:12px;color:#6b7280;margin:0 0 10px;">Enter 5–7 keyword phrases you want to rank for. Siloq auto-matches each to your best page.</p>
    <div id="siloq-goals-kw-wrap-tab">
    <?php for ($gki = 0; $gki < 7; $gki++) :
        $gkv = isset($_goals_target_keywords[$gki]) ? esc_attr($_goals_target_keywords[$gki]) : '';
    ?>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
        <span style="font-size:12px;color:#9ca3af;min-width:72px;"><?php printf(esc_html__('Keyword %d', 'siloq-connector'), $gki + 1); ?></span>
        <input type="text" class="siloq-tab-keyword-input" value="<?php echo $gkv; ?>" placeholder="<?php esc_attr_e('electrician kansas city mo', 'siloq-connector'); ?>" style="flex:1;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;" maxlength="120">
      </div>
    <?php endfor; ?>
    </div>
    <div id="siloq-goals-tab-kw-map" style="margin-top:12px;display:none;">
      <p style="font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;"><?php _e('Auto-matched pages:', 'siloq-connector'); ?></p>
      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead><tr style="background:#f8fafc;">
          <th style="text-align:left;padding:6px 8px;border:1px solid #e2e8f0;"><?php _e('Keyword', 'siloq-connector'); ?></th>
          <th style="text-align:left;padding:6px 8px;border:1px solid #e2e8f0;"><?php _e('Matched Page', 'siloq-connector'); ?></th>
        </tr></thead>
        <tbody id="siloq-goals-tab-kw-map-body"></tbody>
      </table>
    </div>
  </div>

  <button type="button" id="siloq-goals-save-btn" class="siloq-btn siloq-btn--primary" style="padding:10px 24px;font-size:13px;">
    💾 Save Goals
  </button>
</div>

<script>
(function($){
    $('#siloq-goals-save-btn').on('click', function(){
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');

        // Collect primary goal
        var primaryGoal = $('input[name="siloq_goals_primary"]:checked').val() || '';

        // Collect services
        var services = [];
        $('input[name="siloq_goals_service[]"]:checked').each(function(){ services.push($(this).val()); });
        var svcInput = $('#siloq-goals-services-input').val();
        if (svcInput) {
            svcInput.split(',').forEach(function(s){ var t = s.trim(); if(t) services.push(t); });
        }

        // Collect cities
        var cities = [];
        $('input[name="siloq_goals_city[]"]:checked').each(function(){ cities.push($(this).val()); });
        var cityInput = $('#siloq-goals-cities-input').val();
        if (cityInput) {
            cityInput.split(',').forEach(function(c){ var t = c.trim(); if(t) cities.push(t); });
        }

        // Collect keyword phrases
        var keywords = [];
        $('.siloq-tab-keyword-input').each(function(){
            var v = $(this).val().trim();
            if (v) keywords.push(v);
        });

        var nonce = (typeof siloqAdminData !== 'undefined' && siloqAdminData.ajax_nonce) ? siloqAdminData.ajax_nonce : ((typeof siloqDash !== 'undefined') ? siloqDash.nonce : '');
        var postData = $.param({action: 'siloq_save_goals_tab', nonce: nonce, primary_goal: primaryGoal})
            + '&' + $.param({'priority_services[]': services})
            + '&' + $.param({'priority_cities[]': cities})
            + '&' + $.param({'target_keywords[]': keywords});

        $.post(ajaxurl, postData, function(res){
            $btn.prop('disabled', false).text('💾 Save Goals');
            if (res.success) {
                var msg = (res.data && res.data.message) ? res.data.message : 'Goals saved!';
                $('#siloq-goals-saved-msg').text('✅ ' + msg).show();
                setTimeout(function(){ $('#siloq-goals-saved-msg').fadeOut(); }, 4000);
                if (primaryGoal) { $('.siloq-goals-banner').hide(); }
                // Show keyword → page map
                var mapData = (res.data && res.data.keyword_map) ? res.data.keyword_map : [];
                if (mapData.length) {
                    var rows = '';
                    for (var k = 0; k < mapData.length; k++) {
                        var row = mapData[k];
                        var pageCell = row.matched_title
                            ? '<a href="' + row.edit_url + '" target="_blank" style="color:#4f46e5;">' + $('<div>').text(row.matched_title).html() + '</a>'
                            : '<span style="color:#9ca3af;">No match found</span>';
                        rows += '<tr><td style="padding:6px 8px;border:1px solid #e2e8f0;">' + $('<div>').text(row.keyword).html() + '</td><td style="padding:6px 8px;border:1px solid #e2e8f0;">' + pageCell + '</td></tr>';
                    }
                    $('#siloq-goals-tab-kw-map-body').html(rows);
                    $('#siloq-goals-tab-kw-map').show();
                }
            } else {
                var err = (res.data && res.data.message) ? res.data.message : 'Save failed.';
                alert('Error: ' + err);
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('💾 Save Goals');
            alert('Network error. Please try again.');
        });
    });
})(jQuery);
</script>
            </div><!-- /goals tab -->

            <script>
                // Pass roadmap progress to JS
                window.siloqRoadmapProgress = <?php echo wp_json_encode($roadmap_progress); ?>;

                // Hub expand/collapse
                function siloqToggleHub(hdr) {
                    var wrap = hdr.closest('.siloq-hub-block').querySelector('.siloq-hub-spokes-wrap');
                    var chev = hdr.querySelector('.siloq-hub-chev');
                    var isOpen = wrap.style.maxHeight !== '0px' && wrap.style.maxHeight !== '0';
                    wrap.style.maxHeight = isOpen ? '0' : '500px';
                    chev.classList.toggle('open', !isOpen);
                }

                // Orphan fix: See Fix button
                jQuery(document).on('click', '.siloq-orphan-suggest-btn', function() {
                    var wrap = jQuery(this).closest('.siloq-orphan-fix-wrap');
                    var spokeId = wrap.data('spoke-id');
                    var suggDiv = wrap.find('.siloq-orphan-suggestion');
                    var btn = jQuery(this);
                    btn.text('Loading...').prop('disabled', true);
                    jQuery.post(ajaxurl, {
                        action: 'siloq_get_orphan_suggestion',
                        nonce: '<?php echo esc_js(wp_create_nonce("siloq_ajax_nonce")); ?>',
                        spoke_id: spokeId
                    }, function(r) {
                        btn.text('See Fix').prop('disabled', false);
                        if (r && r.success && r.data) {
                            var html = r.data.suggestion;
                            if (r.data.hub_edit_url) {
                                html += ' <a href="' + r.data.hub_edit_url + '" target="_blank">Edit ' + r.data.hub_title + ' &rarr;</a>';
                            }
                            suggDiv.html(html).slideDown(200);
                        } else {
                            var msg = (r && r.data && r.data.message) ? r.data.message : 'Could not load suggestion.';
                            suggDiv.html(msg).slideDown(200);
                        }
                    }).fail(function() {
                        btn.text('See Fix').prop('disabled', false);
                        suggDiv.html('Request failed. Please try again.').slideDown(200);
                    });
                });

                // Orphan fix: Auto-Add Link button
                jQuery(document).on('click', '.siloq-orphan-autolink-btn', function() {
                    var wrap = jQuery(this).closest('.siloq-orphan-fix-wrap');
                    var spokeId = wrap.data('spoke-id');
                    var suggDiv = wrap.find('.siloq-orphan-suggestion');
                    var btns = wrap.find('.siloq-orphan-suggest-btn, .siloq-orphan-autolink-btn');
                    var btn = jQuery(this);
                    btn.text('Adding link...').prop('disabled', true);
                    wrap.find('.siloq-orphan-suggest-btn').prop('disabled', true);
                    jQuery.post(ajaxurl, {
                        action: 'siloq_auto_add_internal_link',
                        nonce: '<?php echo esc_js(wp_create_nonce("siloq_ajax_nonce")); ?>',
                        spoke_id: spokeId
                    }, function(r) {
                        if (r && r.success && r.data) {
                            var html = r.data.message;
                            if (r.data.hub_edit_url) {
                                html += ' — <a href="' + r.data.hub_edit_url + '" target="_blank">Review edit</a>';
                            }
                            suggDiv.html(html).slideDown(200);
                            btns.prop('disabled', true);
                            btn.text('Done').css({background: '#f0fdf4', color: '#16a34a', borderColor: '#bbf7d0'});
                        } else {
                            var msg = (r && r.data && r.data.message) ? r.data.message : 'Auto-add failed.';
                            // Only append manual edit link if NOT a no_api_key response (message already includes it)
                            if (r && r.data && r.data.hub_edit_url && !r.data.no_api_key) {
                                msg += ' <a href="' + r.data.hub_edit_url + '" target="_blank">Edit manually →</a>';
                            }
                            suggDiv.html('<div style="font-size:11px;line-height:1.6;padding:8px 10px;background:#fffbeb;border-left:3px solid #f59e0b;border-radius:0 6px 6px 0;margin-top:6px">' + msg + '</div>').slideDown(200);
                            btn.text('Auto-Add Link').prop('disabled', false);
                            wrap.find('.siloq-orphan-suggest-btn').prop('disabled', false);
                        }
                    }).fail(function() {
                        btn.text('Auto-Add Link').prop('disabled', false);
                        wrap.find('.siloq-orphan-suggest-btn').prop('disabled', false);
                        suggDiv.html('Request failed. Please try again.').slideDown(200);
                    });
                });

                // Shared page-creation handler — reads title/type from data attributes
                // to avoid inline JS quote/apostrophe issues.
                function siloqDoCreatePage(btn) {
                    var title     = btn.getAttribute('data-title');
                    var draftType = btn.getAttribute('data-type') || 'generic';
                    var parentId  = parseInt(btn.getAttribute('data-parent-id') || 0);
                    if (!title) { btn.textContent = 'No title'; return; }
                    btn.textContent = 'Creating...';
                    btn.disabled = true;
                    jQuery.post(ajaxurl, {
                        action:     'siloq_create_draft_page',
                        nonce:      '<?php echo esc_js(wp_create_nonce("siloq_ajax_nonce")); ?>',
                        title:      title,
                        draft_type: draftType,
                        parent_id:  parentId
                    }, function(r) {
                        if (r && r.success && r.data && r.data.edit_url) {
                            // Replace button with a success message + clickable Edit link
                            // (window.open is blocked by browsers in AJAX callbacks)
                            var editUrl = r.data.edit_url;
                            var wrap = document.createElement('span');
                            wrap.style.cssText = 'display:inline-flex;align-items:center;gap:8px;';
                            wrap.innerHTML = '<span style="color:#16a34a;font-weight:600;">✓ Created</span>'
                                + '<a href="' + editUrl + '" target="_blank" rel="noopener" '
                                + 'style="background:#4f46e5;color:#fff;padding:4px 12px;border-radius:4px;font-size:12px;text-decoration:none;white-space:nowrap;">'
                                + 'Edit Page →</a>';
                            btn.parentNode.replaceChild(wrap, btn);
                        } else if (r && r.data && r.data.cannibal) {
                            // Page already exists — show link to existing page
                            var existUrl = r.data.edit_url || '';
                            var wrap2 = document.createElement('span');
                            wrap2.style.cssText = 'display:inline-flex;align-items:center;gap:8px;';
                            wrap2.innerHTML = '<span style="color:#92400e;font-weight:600;">⚠ Already exists</span>'
                                + (existUrl ? '<a href="' + existUrl + '" target="_blank" rel="noopener" '
                                + 'style="background:#d97706;color:#fff;padding:4px 12px;border-radius:4px;font-size:12px;text-decoration:none;white-space:nowrap;">'
                                + 'View Page →</a>' : '');
                            btn.parentNode.replaceChild(wrap2, btn);
                        } else {
                            var msg = (r && r.data && r.data.message) ? r.data.message : 'Failed — try again';
                            btn.textContent = msg;
                            btn.disabled = false;
                        }
                    }).fail(function(xhr) {
                        btn.textContent = 'Error ' + xhr.status + ' — retry';
                        btn.disabled = false;
                    });
                }

                // Legacy wrappers kept for backward compat (spoke cards etc)
                function siloqCreateGapDraft(btn, title, draftType) {
                    btn.setAttribute('data-title', title);
                    btn.setAttribute('data-type', draftType || 'generic');
                    siloqDoCreatePage(btn);
                }
                function siloqCreateDraft(btn, title) {
                    btn.setAttribute('data-title', title);
                    btn.setAttribute('data-type', 'generic');
                    siloqDoCreatePage(btn);
                }

                // Delegated click handler for all .siloq-create-page-btn buttons
                // (avoids inline onclick entirely — safe with apostrophes/quotes)
                jQuery(document).on('click', '.siloq-create-page-btn', function() {
                    siloqDoCreatePage(this);
                });

                function siloqAddSpoke(hubId) {
                    var title = prompt('Enter the title for the new supporting page:');
                    if (title && title.trim()) {
                        var btn = document.createElement('button');
                        siloqCreateDraft(btn, title.trim());
                    }
                }

                // GSC tab handlers (Dashboard page)
                jQuery(document).ready(function($){
                    var nonce = '<?php echo esc_js(wp_create_nonce("siloq_ajax_nonce")); ?>';
                    var ajaxUrl = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';

                    function tabMsg(text, type) {
                        var $m = $('#siloq-gsc-tab-msg');
                        $m.text(text).css('color', type === 'error' ? '#dc2626' : '#16a34a').show();
                        if (type !== 'error') setTimeout(function(){ $m.fadeOut(); }, 5000);
                    }

                    // Check Connection (GSC tab — no popup, connect via app.siloq.ai instead)
                    $('#siloq-gsc-check-btn-tab').on('click', function(){
                        var $btn = $(this).prop('disabled', true).text('Checking...');
                        $.post(ajaxUrl, { action: 'siloq_gsc_check_status', nonce: nonce }, function(r){
                            $btn.prop('disabled', false).text('Check Connection');
                            if (r.success && r.data && r.data.connected) {
                                tabMsg('Connected! Refreshing...', 'success');
                                setTimeout(function(){ location.reload(); }, 800);
                            } else {
                                tabMsg('Not connected yet. Connect at app.siloq.ai/dashboard first, then check again.', 'error');
                            }
                        }).fail(function(xhr){ $btn.prop('disabled', false).text('Check Connection'); tabMsg('Request failed (HTTP ' + xhr.status + ').', 'error'); });
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

                    // ── GSC Property Picker (Dashboard tab) ──
                    <?php if (get_option('siloq_gsc_needs_property_selection') === 'yes'): ?>
                    (function(){
                        var $list = $('#siloq-gsc-property-list-tab');
                        var $confirm = $('#siloq-gsc-confirm-property-tab');
                        $.post(ajaxUrl, {action: 'siloq_gsc_get_properties', nonce: nonce}, function(r) {
                            if (!r.success || !r.data || !r.data.properties || r.data.properties.length === 0) {
                                $list.html('<p style="color:#dc2626;">Could not load properties. ' + (r.data && r.data.message ? r.data.message : 'Try again.') + '</p>');
                                return;
                            }
                            var props = r.data.properties;
                            var homeUrl = (r.data.home_url || '').replace(/^https?:\/\/(www\.)?/, '').replace(/\/+$/, '');
                            var html = '';
                            for (var i = 0; i < props.length; i++) {
                                var p = typeof props[i] === 'string' ? props[i] : (props[i].siteUrl || props[i].url || '');
                                var normalized = p.replace(/^sc-domain:/, '').replace(/^https?:\/\/(www\.)?/, '').replace(/\/+$/, '');
                                var checked = (normalized === homeUrl) ? ' checked' : '';
                                html += '<label style="display:block;padding:6px 0;cursor:pointer;"><input type="radio" name="siloq_gsc_prop_tab" value="' + p.replace(/"/g, '&quot;') + '"' + checked + ' style="margin-right:8px;"> ' + p.replace(/</g, '&lt;') + '</label>';
                            }
                            $list.html(html);
                            if ($list.find('input:checked').length) $confirm.prop('disabled', false);
                        }).fail(function(){ $list.html('<p style="color:#dc2626;">Network error loading properties.</p>'); });

                        $list.on('change', 'input[name="siloq_gsc_prop_tab"]', function() {
                            $confirm.prop('disabled', false);
                        });

                        $confirm.on('click', function() {
                            var selected = $list.find('input[name="siloq_gsc_prop_tab"]:checked').val();
                            if (!selected) return;
                            $(this).prop('disabled', true).text('Saving...');
                            $.post(ajaxUrl, {action: 'siloq_gsc_save_property', nonce: nonce, property: selected}, function(r) {
                                if (r.success) {
                                    tabMsg('Connected to ' + (r.data.property || selected), 'success');
                                    setTimeout(function(){ location.reload(); }, 800);
                                } else {
                                    tabMsg(r.data && r.data.message ? r.data.message : 'Save failed.', 'error');
                                    $confirm.prop('disabled', false).text('Confirm Connection');
                                }
                            }).fail(function(){ tabMsg('Network error.', 'error'); $confirm.prop('disabled', false).text('Confirm Connection'); });
                        });

                        $('#siloq-gsc-cancel-property-tab').on('click', function() {
                            $.post(ajaxUrl, {action: 'siloq_gsc_disconnect', nonce: nonce}, function() {
                                location.reload();
                            });
                        });
                    })();
                    <?php endif; ?>
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
                    <div class="siloq-wizard-step-dot <?php echo $saved_step >= 5 ? 'active' : ''; ?>" data-step="5"></div>
                    <div class="siloq-wizard-step-dot <?php echo $saved_step >= 6 ? 'active' : ''; ?>" data-step="6"></div>
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

                    <div class="siloq-wizard-skip" style="margin-top:12px;">
                        <a href="#" onclick="siloqWizardSkipToApp(); return false;" style="font-size:12px;color:#9ca3af;">
                            <?php _e('Site already configured — skip to dashboard →', 'siloq-connector'); ?>
                        </a>
                    </div>
                </div>

                <!-- STEP 4: Primary Goal -->
                <div class="siloq-wizard-step" id="siloq-step-goal">
                </div>
                <div class="siloq-wizard-panel" id="siloq-wizard-step-4">
                    <h2><?php _e( 'Your #1 Goal', 'siloq-connector' ); ?></h2>
                    <p class="siloq-wizard-subtitle"><?php _e( 'Help Siloq focus on what matters most to your business.', 'siloq-connector' ); ?></p>

                    <div class="siloq-goal-options" style="display:flex;flex-direction:column;gap:10px;margin:20px 0;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                            <input type="radio" name="siloq_primary_goal" value="local_leads" checked style="accent-color:#6366f1;">
                            <?php _e( 'Get more phone calls / local leads', 'siloq-connector' ); ?>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                            <input type="radio" name="siloq_primary_goal" value="ecommerce_sales" style="accent-color:#6366f1;">
                            <?php _e( 'Drive more e-commerce sales', 'siloq-connector' ); ?>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                            <input type="radio" name="siloq_primary_goal" value="topic_authority" style="accent-color:#6366f1;">
                            <?php _e( 'Build authority on a specific topic', 'siloq-connector' ); ?>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                            <input type="radio" name="siloq_primary_goal" value="multi_location" style="accent-color:#6366f1;">
                            <?php _e( 'Rank in multiple cities', 'siloq-connector' ); ?>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                            <input type="radio" name="siloq_primary_goal" value="geo_citations" style="accent-color:#6366f1;">
                            <?php _e( 'Be cited by AI assistants (ChatGPT, Perplexity)', 'siloq-connector' ); ?>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
                            <input type="radio" name="siloq_primary_goal" value="organic_growth" style="accent-color:#6366f1;">
                            <?php _e( 'Grow overall organic traffic', 'siloq-connector' ); ?>
                        </label>
                    </div>

                    <button type="button" class="siloq-wizard-btn" id="siloq-wizard-goal-btn" onclick="siloqWizardGoTo(5)">
                        <?php _e( 'Continue', 'siloq-connector' ); ?>
                    </button>

                    <div class="siloq-wizard-skip">
                        <a onclick="siloqWizardGoTo(5)"><?php _e( 'Skip for now', 'siloq-connector' ); ?></a>
                    </div>
                </div>

                <!-- STEP 5: Target Keyword Phrases (replaces GEO Priority Pages) -->
                <div class="siloq-wizard-step" id="siloq-step-geo">
                </div>
                <div class="siloq-wizard-panel" id="siloq-wizard-step-5">
                    <h2><?php _e( 'Target Keyword Phrases', 'siloq-connector' ); ?></h2>
                    <p class="siloq-wizard-subtitle"><?php _e( 'Enter 5–7 keyword phrases you want your site to rank for.', 'siloq-connector' ); ?></p>
                    <p style="font-size:13px;color:#64748b;margin-bottom:16px;"><?php _e( 'Siloq will automatically match each phrase to the best page on your site and use them to power your GEO recommendations.', 'siloq-connector' ); ?></p>

                    <div class="siloq-wizard-error" id="siloq-wizard-error-5"></div>

                    <div id="siloq-wizard-keywords-wrap">
                    <?php for ($wki = 0; $wki < 7; $wki++) : ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span style="font-size:12px;color:#9ca3af;min-width:72px;"><?php printf(esc_html__('Keyword %d', 'siloq-connector'), $wki + 1); ?></span>
                            <input type="text" class="siloq-wizard-keyword-input" placeholder="<?php esc_attr_e('electrician kansas city mo', 'siloq-connector'); ?>" style="flex:1;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;" maxlength="120">
                        </div>
                    <?php endfor; ?>
                    </div>

                    <p class="siloq-hint" style="font-size:12px;color:#64748b;margin-top:12px;">
                        <?php _e( 'Examples: "electrician kansas city mo", "panel upgrade independence mo", "ev charger installation lee\'s summit"', 'siloq-connector' ); ?>
                    </p>

                    <button type="button" class="siloq-wizard-btn" id="siloq-wizard-geo-btn" onclick="siloqWizardSaveGoals()" style="margin-top:16px;">
                        <span class="spinner-dot"></span>
                        <?php _e( 'Save & Continue', 'siloq-connector' ); ?>
                    </button>

                    <div class="siloq-wizard-skip">
                        <a onclick="siloqWizardGoTo(6)"><?php _e( 'Skip for now', 'siloq-connector' ); ?></a>
                    </div>
                </div>

                <!-- STEP 6: All Set -->
                <div class="siloq-wizard-panel" id="siloq-wizard-step-6">
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
            var currentStep = <?php echo intval($saved_step); ?>;

            // If resuming at step 3, auto-start sync immediately on page load.
            // Without this, siloqWizardStartSync() is never called and the
            // Continue button stays permanently disabled.
            document.addEventListener('DOMContentLoaded', function() {
                if (currentStep === 3) {
                    if (typeof siloqWizardStartSync === 'function') {
                        siloqWizardStartSync();
                    }
                }
                if (currentStep === 5) {
                    if (typeof siloqWizardLoadGeoPages === 'function') {
                        siloqWizardLoadGeoPages();
                    }
                }
            });

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

                // Auto-load pages selector on step 5
                if (step === 5) siloqWizardLoadGeoPages();
            };

            // siloqWizardLoadGeoPages: no longer fetches pages — keywords are plain text inputs
            window.siloqWizardLoadGeoPages = function() {
                // No-op: keyword inputs are rendered server-side; nothing to load
            };

            window.siloqWizardSaveGoals = function() {
                var btn = document.getElementById('siloq-wizard-geo-btn');
                if (btn) { btn.classList.add('loading'); btn.disabled = true; }

                var primaryGoalEl = document.querySelector('input[name="siloq_primary_goal"]:checked');
                var primaryGoal = primaryGoalEl ? primaryGoalEl.value : 'local_leads';

                var fd = new FormData();
                fd.append('action', 'siloq_save_goals');
                fd.append('nonce', nonce);
                fd.append('primary_goal', primaryGoal);

                // Collect keyword phrases from wizard step 5 inputs
                var kwInputs = document.querySelectorAll('.siloq-wizard-keyword-input');
                for (var ki = 0; ki < kwInputs.length; ki++) {
                    var kv = kwInputs[ki].value.trim();
                    if (kv) fd.append('target_keywords[]', kv);
                }

                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (btn) { btn.classList.remove('loading'); btn.disabled = false; }
                        siloqWizardGoTo(6);
                    })
                    .catch(function() {
                        if (btn) { btn.classList.remove('loading'); btn.disabled = false; }
                        siloqWizardGoTo(6);
                    });
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

            // Emergency escape hatch — marks onboarding complete and reloads
            // the page so the real dashboard renders. Intended for sites that
            // already have api_key + site_id but got trapped by a wizard reset.
            window.siloqWizardSkipToApp = function() {
                var fd = new FormData();
                fd.append('action', 'siloq_wizard_complete');
                fd.append('nonce', nonce);
                fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function() { window.location.reload(); })
                    .catch(function() { window.location.reload(); });
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

    // =========================================================================
    // Track 2: Site Audit — collect page data, POST to API, cache results
    // =========================================================================

    /**
     * Fast local audit — runs entirely in WP without any API call.
     * Used by the async audit job. Returns the same shape as run_site_audit().
     * Never times out. Generates specific fixable action items.
     */
    public static function run_site_audit_local() {
        $post_types = function_exists('get_siloq_crawlable_post_types')
            ? get_siloq_crawlable_post_types()
            : array('page', 'post');

        $posts = get_posts(array(
            'post_type'          => $post_types,
            'post_type__not_in'  => defined('SILOQ_EXCLUDED_POST_TYPES') ? SILOQ_EXCLUDED_POST_TYPES : [],
            'post_status'        => 'publish',
            'posts_per_page'     => -1,
        ));

        if (empty($posts)) return null;

        $actions  = array();
        $issues   = array('critical' => array(), 'important' => array(), 'opportunity' => array());
        $scores   = array();
        $pages    = array(); // per-page structured results for the audit display
        $seo_plugin = get_option('siloq_active_seo_plugin', 'siloq_native');

        foreach ($posts as $p) {
            $post_id      = $p->ID;
            $title        = get_the_title($post_id);
            $edit_url     = get_edit_post_link($post_id, 'raw');
            // el_url is ONLY used for content/H1 issues that require the visual editor
            // Meta title/description always use edit_url (WP editor where AIOSEO panel lives)
            $el_url       = admin_url('post.php?post=' . $post_id . '&action=elementor');
            $score        = 100;
            $page_actions = array(); // actions for THIS page only

            $formula_title = self::siloq_formula_seo_title($post_id);
            $formula_desc  = self::siloq_formula_meta_desc($post_id);

            // ── Meta title ──────────────────────────────────────────────────
            $seo_title = self::siloq_get_page_title($post_id);
            $has_seo_title = ($seo_title !== get_the_title($post_id) && !empty($seo_title));
            if (!$has_seo_title) {
                $score -= 10;
                $a = array('headline' => 'Add SEO title to "' . $title . '"', 'title' => 'Missing SEO title',
                    'detail' => 'Missing SEO title tag. Siloq will write one automatically.',
                    'recommendation' => 'Missing SEO title. Siloq will write one automatically.',
                    'severity' => 'high', 'category' => 'Meta',
                    'priority' => 'high', 'post_id' => $post_id,
                    'fix_category' => 'auto', 'fix_type' => 'meta_title', 'formula' => $formula_title,
                    'edit_url' => $edit_url, 'elementor_url' => $edit_url);
                $actions[]      = $a;
                $page_actions[] = $a;
                $issues['important'][] = array('title' => $title, 'issue' => 'Missing SEO title',
                    'fix_category' => 'auto', 'fix_type' => 'meta_title', 'formula' => $formula_title,
                    'post_id' => $post_id, 'edit_url' => $edit_url, 'elementor_url' => $edit_url);
            }

            // ── Meta description ────────────────────────────────────────────
            $meta_result = self::siloq_get_meta_description($post_id);
            $meta_desc   = is_string($meta_result) ? $meta_result : '';
            $broken      = is_array($meta_result) && isset($meta_result['status']) && $meta_result['status'] === 'broken_fallback';
            if (empty($meta_desc) || $broken) {
                $score -= 15;
                $meta_detail = $broken ? 'Meta description contains full page content. Siloq will generate a proper summary.' : 'Missing meta description reduces CTR. Siloq will generate one.';
                $a = array('headline' => 'Add meta description to "' . $title . '"', 'title' => 'Missing meta description',
                    'detail' => $meta_detail, 'recommendation' => $meta_detail,
                    'severity' => 'high', 'category' => 'Meta',
                    'priority' => 'high', 'post_id' => $post_id,
                    'fix_category' => 'auto', 'fix_type' => 'meta_description', 'formula' => $formula_desc,
                    'edit_url' => $edit_url, 'elementor_url' => $edit_url);
                $actions[]      = $a;
                $page_actions[] = $a;
                $issues['important'][] = array('title' => $title, 'issue' => 'Missing meta description',
                    'fix_category' => 'auto', 'fix_type' => 'meta_description', 'formula' => $formula_desc,
                    'post_id' => $post_id, 'edit_url' => $edit_url, 'elementor_url' => $edit_url);
            }

            // ── Schema ──────────────────────────────────────────────────────
            global $wpdb;
            $schema_table = $wpdb->prefix . "siloq_schema";
            $active_types = $wpdb->get_col($wpdb->prepare(
                "SELECT schema_type FROM {$schema_table} WHERE post_id = %d AND is_active = 1",
                $p->ID
            ));
            if (empty($active_types)) {
                $score -= 20;
                $a = array('headline' => 'Add schema markup to "' . $title . '"', 'title' => 'No schema markup',
                    'detail' => 'No structured data. Schema helps AI cite this page and improves rich results.',
                    'recommendation' => 'Add schema markup. Structured data helps AI cite this page and improves rich results.',
                    'severity' => 'warning', 'category' => 'Schema',
                    'priority' => 'high', 'post_id' => $post_id,
                    'fix_category' => 'auto', 'fix_type' => 'schema',
                    'edit_url' => $edit_url, 'elementor_url' => $el_url);
                $actions[]      = $a;
                $page_actions[] = $a;
                $issues['opportunity'][] = array('title' => $title, 'issue' => 'No schema markup',
                    'fix_category' => 'auto', 'fix_type' => 'schema',
                    'post_id' => $post_id, 'edit_url' => $edit_url, 'elementor_url' => $el_url);
            }

            // ── Thin content ────────────────────────────────────────────────
            $wc = str_word_count(wp_strip_all_tags($p->post_content));
            if ($wc < 300 && $wc > 0) {
                $score -= 15;
                $a = array('headline' => 'Thin content on "' . $title . '"', 'title' => 'Thin content',
                    'detail' => 'Only ' . $wc . ' words. Aim for 500+ for service pages.',
                    'recommendation' => 'Only ' . $wc . ' words. Expand to 500+ words for service pages.',
                    'severity' => 'medium', 'category' => 'Content',
                    'priority' => 'medium', 'post_id' => $post_id,
                    'fix_category' => 'manual', 'fix_type' => 'content',
                    'edit_url' => $edit_url, 'elementor_url' => $el_url);
                $page_actions[] = $a;
                $issues['important'][] = array('title' => $title, 'issue' => 'Thin content — only ' . $wc . ' words. Aim for 500+.', 'post_id' => $post_id, 'edit_url' => $edit_url, 'elementor_url' => $el_url);
            }

            $page_score = max(0, $score);
            $scores[]   = $page_score;

            // Build per-page entry for the audit display
            $page_type_meta = get_post_meta($post_id, '_siloq_page_role', true);
            $tier_map = array('hub' => 'hub', 'apex_hub' => 'apex_hub', 'pillar' => 'hub', 'spoke' => 'spoke', 'supporting' => 'spoke', 'orphan' => 'orphan');
            $tier = isset($tier_map[$page_type_meta]) ? $tier_map[$page_type_meta] : 'supporting';
            $pages[] = array('post_id' => $post_id, 'score' => $page_score, 'tier' => $tier, 'actions' => $page_actions);
        }

        $site_score = !empty($scores) ? round(array_sum($scores) / count($scores)) : 0;

        // Sort actions by priority
        usort($actions, function($a, $b) {
            $p = array('high' => 0, 'medium' => 1, 'low' => 2);
            return ($p[$a['priority']] ?? 2) - ($p[$b['priority']] ?? 2);
        });

        return array(
            'success'    => true,
            'site_score' => $site_score,
            'page_count' => count($posts),
            'pages'      => $pages,
            'actions'    => $actions,
            'issues'     => $issues,
            'audit_id'   => 'local_' . time(),
            'source'     => 'local',
        );
    }

    public static function run_site_audit() {
        $site_id = get_option('siloq_site_id', '');
        if (empty($site_id)) {
            return array('success' => false, 'message' => 'Site not connected to Siloq.');
        }

        $post_types = function_exists('get_siloq_crawlable_post_types')
            ? get_siloq_crawlable_post_types()
            : array('page', 'post');

        $posts = get_posts(array(
            'post_type'          => $post_types,
            'post_type__not_in'  => defined('SILOQ_EXCLUDED_POST_TYPES') ? SILOQ_EXCLUDED_POST_TYPES : [],
            'post_status'        => 'publish',
            'posts_per_page'     => -1,
        ));

        if (empty($posts)) {
            return array('success' => false, 'message' => 'No published pages found.');
        }

        // Build internal link map for inbound counts
        $all_urls = array();
        foreach ($posts as $p) {
            $all_urls[$p->ID] = wp_parse_url(get_permalink($p->ID), PHP_URL_PATH);
        }

        $inbound_counts = array();
        foreach ($posts as $p) {
            $count = 0;
            $my_path = $all_urls[$p->ID];
            foreach ($posts as $other) {
                if ($other->ID === $p->ID) continue;
                if (stripos($other->post_content, $my_path) !== false) {
                    $count++;
                }
            }
            $inbound_counts[$p->ID] = $count;
        }

        // Collect titles to detect duplicates
        $all_titles = array();
        foreach ($posts as $p) {
            $title = self::siloq_get_page_title($p->ID);
            $all_titles[$p->ID] = strtolower(trim($title));
        }
        $title_counts = array_count_values($all_titles);

        $pages_payload = array();
        foreach ($posts as $p) {
            $title = self::siloq_get_page_title($p->ID);
            $meta_result = self::siloq_get_meta_description($p->ID);
            if (is_array($meta_result) && isset($meta_result['status'])) {
                $meta_desc = '';
                $meta_status = $meta_result['status'];
            } elseif (empty($meta_result)) {
                $meta_desc = '';
                $meta_status = 'missing';
            } else {
                $meta_desc = $meta_result;
                $meta_status = 'ok';
            }

            $url = get_permalink($p->ID);
            $page_type = self::siloq_classify_page($p->ID, $url);

            // H1 extraction from content
            $h1 = '';
            if (preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $p->post_content, $h1_match)) {
                $h1 = wp_strip_all_tags($h1_match[1]);
            }

            $word_count = str_word_count(wp_strip_all_tags($p->post_content));

            // Count outbound links
            preg_match_all('/<a\s[^>]*href/si', $p->post_content, $link_matches);
            $outbound_links = count($link_matches[0]);

            // Images missing alt
            preg_match_all('/<img\s[^>]*>/si', $p->post_content, $img_matches);
            $images_missing_alt = 0;
            foreach ($img_matches[0] as $img_tag) {
                if (!preg_match('/alt\s*=\s*["\'][^"\']+["\']/i', $img_tag)) {
                    $images_missing_alt++;
                }
            }

            // Schema types from DB (active rows)
            global $wpdb;
            $schema_table = $wpdb->prefix . "siloq_schema";
            $active_types = $wpdb->get_col($wpdb->prepare(
                "SELECT schema_type FROM {$schema_table} WHERE post_id = %d AND is_active = 1",
                $p->ID
            ));
            $inactive_types = $wpdb->get_col($wpdb->prepare(
                "SELECT schema_type FROM {$schema_table} WHERE post_id = %d AND is_active = 0",
                $p->ID
            ));
            $schema_types = !empty($active_types) ? $active_types : array();

            if (!empty($active_types)) {
                $schema_status = 'live';
            } elseif (!empty($inactive_types)) {
                $schema_status = 'generated';
            } else {
                $schema_status = 'none';
            }

            $has_dup = ($title_counts[strtolower(trim($title))] ?? 0) > 1;

            $pages_payload[] = array(
                'post_id'                => $p->ID,
                'url'                    => $url,
                'title'                  => $title,
                'meta_description'       => $meta_desc,
                'meta_description_status'=> $meta_status,
                'h1'                     => $h1,
                'word_count'             => $word_count,
                'page_type'              => $page_type,
                'inbound_links'          => $inbound_counts[$p->ID] ?? 0,
                'outbound_links'         => $outbound_links,
                'schema_types'           => $schema_types,
                'schema_status'          => $schema_status,
                'images_missing_alt'     => $images_missing_alt,
                'has_duplicate_title'    => $has_dup,
            );
        }

        // Cap to 25 pages to avoid upgrade_required on free tier.
        // Hub/pillar pages first, then supporting.
        usort($pages_payload, function($a, $b) {
            $tier_order = array('hub' => 0, 'pillar' => 1, 'supporting' => 2);
            return ($tier_order[$a['page_type']] ?? 2) - ($tier_order[$b['page_type']] ?? 2);
        });
        $pages_payload = array_slice($pages_payload, 0, 25);

        // Build site context from entity profile
        $services = json_decode(get_option('siloq_primary_services', '[]'), true);
        $areas = json_decode(get_option('siloq_service_areas', '[]'), true);
        $site_context = array(
            'business_name'           => get_option('siloq_business_name', get_bloginfo('name')),
            'business_type'           => get_option('siloq_business_type', ''),
            'primary_service'         => is_array($services) && !empty($services) ? $services[0] : '',
            'service_cities'          => is_array($areas) ? $areas : array(),
            'entity_profile_complete' => self::compute_entity_completeness() >= 80,
        );

        $api = new Siloq_API_Client();
        $response = $api->post('/sites/' . $site_id . '/audit/', array(
            'pages'        => $pages_payload,
            'site_context' => $site_context,
        ));

        if (!empty($response['success']) && !empty($response['data'])) {
            $data = $response['data'];
            set_transient('siloq_audit_results', $data, 6 * HOUR_IN_SECONDS);
            update_option('siloq_last_audit_id', $data['audit_id'] ?? '');
            update_option('siloq_last_audit_time', current_time('mysql'));
            if (isset($data['site_score'])) {
                update_option('siloq_site_score', intval($data['site_score']));
            }
            return array('success' => true, 'data' => $data);
        }

        return array(
            'success' => false,
            'message' => $response['message'] ?? 'Audit request failed.',
            'data'    => $response['data'] ?? null,
        );
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
    /**
     * AJAX: One-click dashboard fix — routes to the right handler.
     */
    public static function ajax_dashboard_fix() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
            return;
        }

        $fix_action = sanitize_text_field( $_POST['fix_action'] ?? '' );
        $post_id    = intval( $_POST['post_id'] ?? 0 );
        $fix_type   = sanitize_text_field( $_POST['fix_type'] ?? '' );

        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Missing post ID.' ] );
            return;
        }

        switch ( $fix_action ) {
            case 'fix_meta':
                // If the user edited the value in the inline panel, use it directly
                $custom_value = sanitize_text_field( $_POST['custom_value'] ?? '' );

                // Detect active SEO plugin (checked once, cached in option)
                $seo_plugin = get_option( 'siloq_active_seo_plugin', '' );
                if ( empty( $seo_plugin ) ) {
                    if ( defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\Plugin\AIOSEO' ) ) {
                        $seo_plugin = 'aioseo';
                    } elseif ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
                        $seo_plugin = 'yoast';
                    } elseif ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
                        $seo_plugin = 'rankmath';
                    } elseif ( defined( 'SEOPRESS_VERSION' ) || class_exists( 'SEOPRESS_ADMIN' ) ) {
                        $seo_plugin = 'seopress';
                    } else {
                        $seo_plugin = 'siloq_native'; // No SEO plugin — Siloq handles it
                    }
                    update_option( 'siloq_active_seo_plugin', $seo_plugin );
                }

                if ( $fix_type === 'title' ) {
                    // Use custom_value if user edited it in the inline panel, otherwise generate
                    if ( ! empty( $custom_value ) ) {
                        $seo_title = mb_substr( $custom_value, 0, 60 );
                    } else {
                        $seo_title = self::siloq_formula_seo_title( $post_id );
                    }
                    self::write_seo_title( $post_id, $seo_title, $seo_plugin );
                    wp_send_json_success( [ 'message' => 'SEO title applied.', 'value' => $seo_title ] );

                } elseif ( $fix_type === 'description' ) {
                    if ( ! empty( $custom_value ) ) {
                        $desc = mb_substr( $custom_value, 0, 160 );
                    } else {
                        $desc = self::siloq_formula_meta_desc( $post_id );
                    }

                    if ( empty( $desc ) ) {
                        wp_send_json_error( [ 'message' => 'Could not generate description — add content to this page first.' ] );
                        return;
                    }

                    self::write_seo_description( $post_id, $desc, $seo_plugin );
                    wp_send_json_success( [ 'message' => 'Meta description applied.', 'value' => $desc ] );

                } else {
                    wp_send_json_error( [ 'message' => 'Unknown meta fix type.' ] );
                }
                break;

            case 'fix_schema':
                // Delegate to the schema intelligence generator
                if ( class_exists( 'Siloq_Schema_Intelligence' ) ) {
                    $_POST['nonce'] = $_POST['nonce']; // already verified
                    $_POST['post_id'] = $post_id;
                    Siloq_Schema_Intelligence::ajax_generate_schema();
                } else {
                    wp_send_json_error( [ 'message' => 'Schema intelligence module not available.' ] );
                }
                break;

            default:
                wp_send_json_error( [ 'message' => 'Unknown fix action: ' . $fix_action ] );
                break;
        }
    }

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

        // Service Areas only counts toward completeness for local service businesses.
        // Ecommerce, event venue, and general sites don't have service areas.
        $_cplt_biz = get_option( 'siloq_business_type', 'general' );
        $_is_local = in_array( $_cplt_biz, array( 'local_service', 'local_service_multi' ), true );

        $fields = array(
            array( 'key' => 'business_name',    'label' => 'Business Name',    'weight' => 15, 'filled' => ! empty( $business_name ) ),
            array( 'key' => 'business_type',    'label' => 'Business Type',    'weight' => 15, 'filled' => ! empty( $business_type ) ),
            array( 'key' => 'phone',            'label' => 'Phone',            'weight' => 10, 'filled' => ! empty( $phone ) ),
            array( 'key' => 'address',          'label' => 'Address',          'weight' => 20, 'filled' => $address_filled ),
            array( 'key' => 'primary_services', 'label' => 'Primary Services', 'weight' => $_is_local ? 25 : 40, 'filled' => is_array( $services ) && ! empty( $services ) ),
        );
        if ( $_is_local ) {
            $fields[] = array( 'key' => 'service_areas', 'label' => 'Service Areas', 'weight' => 15, 'filled' => is_array( $areas ) && ! empty( $areas ) );
        }
        return $fields;
    }

    /**
     * Check if a title or slug belongs to an internal/system post type.
     */
    public static function is_internal_post_type_name( $title, $slug ) {
        $internal_patterns = array( 'koops', 'grid', 'template', 'listing', 'loop', 'cpt-', 'jet-', 'acf-', 'pods-', 'dynamic-' );
        $internal_slugs    = array( 'attachment', 'revision', 'nav_menu_item' );

        $lower_title = strtolower( $title );
        $lower_slug  = strtolower( $slug );

        if ( in_array( $lower_slug, $internal_slugs, true ) ) {
            return true;
        }
        foreach ( $internal_patterns as $pat ) {
            if ( strpos( $lower_title, $pat ) !== false || strpos( $lower_slug, $pat ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Categorize site pages into hubs, cities, spokes, and service_area_page.
     *
     * @param array $pages Each element has keys: title, url, page_role.
     * @return array With keys: hubs, cities, spokes, service_area_page.
     */
    private static function categorize_pages( $pages ) {
        $state_abbrs = array(
            'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA',
            'KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
            'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT',
            'VA','WA','WV','WI','WY','DC',
        );
        $state_pattern = '/\b(' . implode( '|', $state_abbrs ) . ')\b/';

        $result = array(
            'hubs'              => array(),
            'cities'            => array(),
            'spokes'            => array(),
            'service_area_page' => null,
        );

        foreach ( $pages as $page ) {
            $title = isset( $page['title'] ) ? $page['title'] : '';
            $url   = isset( $page['url'] )   ? $page['url']   : '';
            $role  = isset( $page['page_role'] ) ? $page['page_role'] : '';
            $slug  = basename( untrailingslashit( wp_parse_url( $url, PHP_URL_PATH ) ?: '' ) );

            if ( self::is_internal_post_type_name( $title, $slug ) ) {
                continue;
            }

            // Service area page detection
            if ( preg_match( '/service.?area|areas.?we.?serve|coverage.?area/i', $url . ' ' . $title ) ) {
                $result['service_area_page'] = $page;
                continue;
            }

            // City pages — title contains a US state abbreviation as a word
            if ( preg_match( $state_pattern, $title ) ) {
                $result['cities'][] = $page;
                continue;
            }

            // Hub pages
            if ( $role === 'hub' || preg_match( '/service|solution/i', $slug ) ) {
                $result['hubs'][] = $page;
                continue;
            }

            // Everything else is a spoke
            $result['spokes'][] = $page;
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════
    // GAP DETECTION — Pages You Should Create
    // ═══════════════════════════════════════════════════════════════

    /**
     * Render "Pages You Should Create" cards with clean gap detection.
     *
     * Rules:
     * - Service gaps: compare siloq_primary_services against existing page SLUGS and TITLES only.
     *   NEVER parse keywords out of existing page titles.
     * - City gaps: check every synced page title AND slug for the city name (case-insensitive partial).
     *   Also check the Service Areas hub page body text — if a city is mentioned there, skip it.
     * - No "CONTENT GAP" / "LOCAL SEO GAP" labels. Use "Missing Service Page" / "Missing City Page".
     */
    public static function render_gap_cards() {
        ob_start();

        // Build existing pages dataset
        $post_types = function_exists( 'get_siloq_crawlable_post_types' )
            ? get_siloq_crawlable_post_types()
            : array( 'page', 'post' );

        $all_posts = get_posts( array(
            'post_type'      => $post_types,
            'post_status'    => array( 'publish', 'draft' ),
            'posts_per_page' => -1,
        ) );

        // Build lookup arrays: title_lower, slug
        $existing_titles = array();
        $existing_slugs  = array();
        foreach ( $all_posts as $p ) {
            $existing_titles[] = strtolower( $p->post_title );
            $existing_slugs[]  = strtolower( $p->post_name );
        }

        // Get Service Areas hub page body text for city cross-reference
        $hub_body_text = '';
        foreach ( $all_posts as $p ) {
            $slug = strtolower( $p->post_name );
            if ( strpos( $slug, 'service-area' ) !== false || strpos( strtolower( $p->post_title ), 'service area' ) !== false ) {
                $hub_body_text = strtolower( wp_strip_all_tags( $p->post_content ) );
                // Also check Elementor data
                if ( empty( $hub_body_text ) ) {
                    $el = get_post_meta( $p->ID, '_elementor_data', true );
                    if ( $el ) {
                        preg_match_all( '/"text"\s*:\s*"([^"]{10,})"/', $el, $m );
                        $hub_body_text = strtolower( implode( ' ', $m[1] ?? [] ) );
                    }
                }
                break;
            }
        }

        $biz_name     = get_option( 'siloq_business_name', get_bloginfo( 'name' ) );
        $primary_city = get_option( 'siloq_city', '' );
        $has_cards    = false;

        // ── Service gaps ────────────────────────────────────────────────────
        // Only compare against siloq_primary_services option — NEVER parse from page titles
        $primary_services = json_decode( get_option( 'siloq_primary_services', '[]' ), true );
        if ( ! is_array( $primary_services ) ) $primary_services = array();
        $primary_services = array_map( 'stripslashes', $primary_services );

        // Filter to clean service names only (≤5 words, no state abbreviations)
        $state_abbrs = array('AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA',
                             'KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ',
                             'NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT',
                             'VA','WA','WV','WI','WY','DC');
        $generic_service_words = array( 'contractor', 'company', 'business', 'provider', 'professional',
            'specialist', 'service', 'services', 'expert', 'experts' );
        $primary_services = array_values( array_filter( $primary_services, function( $s ) use ( $state_abbrs, $generic_service_words ) {
            if ( empty( trim( $s ) ) ) return false;
            if ( str_word_count( $s ) > 5 ) return false; // Too long — likely a page title fragment
            foreach ( $state_abbrs as $st ) {
                if ( preg_match( '/\b' . $st . '\b/i', $s ) ) return false;
            }
            // Filter out generic business descriptors that aren't real service names
            if ( in_array( strtolower( trim( $s ) ), $generic_service_words, true ) ) return false;
            return true;
        } ) );

        foreach ( $primary_services as $service ) {
            $service_lower = strtolower( trim( $service ) );
            $service_slug  = sanitize_title( $service );
            $found         = false;

            foreach ( $existing_titles as $t ) {
                if ( strpos( $t, $service_lower ) !== false ) { $found = true; break; }
            }
            if ( ! $found ) {
                foreach ( $existing_slugs as $s ) {
                    if ( strpos( $s, $service_slug ) !== false ) { $found = true; break; }
                }
            }
            if ( $found ) continue;

            $has_cards   = true;
            $card_title  = $primary_city ? $service . ' in ' . $primary_city : $service;
            $why_it_matters = 'You don\'t have a dedicated page for "' . $service . '." Without one, Google has no clear signal to rank you for this service — it spreads your authority across unrelated pages instead of concentrating it.';
            ?>
            <div class="siloq-gap-card" style="border:1px solid #dbeafe;background:#eff6ff;border-radius:8px;padding:16px;margin-bottom:12px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:200px;">
                        <span style="display:inline-block;background:#3b82f6;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;margin-bottom:8px;letter-spacing:0.5px;">MISSING SERVICE PAGE</span>
                        <p style="font-size:14px;font-weight:700;color:#1e3a5f;margin:0 0 6px;"><?php echo esc_html( $card_title ); ?></p>
                        <p style="font-size:12px;color:#4b5563;margin:0 0 10px;line-height:1.5;"><?php echo esc_html( $why_it_matters ); ?></p>
                    </div>
                    <button class="siloq-btn siloq-btn--sm siloq-btn--primary siloq-create-page-btn" data-title="<?php echo esc_attr( $card_title ); ?>" data-type="service" style="white-space:nowrap;">Create Page &rarr;</button>
                </div>
            </div>
            <?php
        }

        // ── City gaps ────────────────────────────────────────────────────────
        // Only for local service businesses — city pages are meaningless for ecommerce/event venues.
        $service_areas = array();
        $_gap_biz_type = get_option( 'siloq_business_type', 'general' );
        if ( in_array( $_gap_biz_type, array( 'local_service', 'local_service_multi' ), true ) ) {
            $service_areas = json_decode( get_option( 'siloq_service_areas', '[]' ), true );
            if ( ! is_array( $service_areas ) ) $service_areas = array();
        }
        $first_service = ! empty( $primary_services ) ? $primary_services[0] : '';

        foreach ( $service_areas as $city_entry ) {
            $city_name = is_array( $city_entry ) ? ( $city_entry['city'] ?? '' ) : (string) $city_entry;
            $city_name = trim( stripslashes( $city_name ) );
            if ( empty( $city_name ) ) continue;

            $city_lower = strtolower( $city_name );
            $city_slug  = sanitize_title( $city_name );
            $found      = false;

            // Check existing page titles
            foreach ( $existing_titles as $t ) {
                if ( strpos( $t, $city_lower ) !== false ) { $found = true; break; }
            }
            // Check existing page slugs
            if ( ! $found ) {
                foreach ( $existing_slugs as $s ) {
                    if ( strpos( $s, $city_slug ) !== false ) { $found = true; break; }
                }
            }
            // Check Service Areas hub body text — if listed there, skip (already covered)
            if ( ! $found && $hub_body_text && strpos( $hub_body_text, $city_lower ) !== false ) {
                $found = true;
            }

            if ( $found ) continue;

            $has_cards      = true;
            $suggested_title = $city_name . ( $first_service ? ' ' . $first_service : '' );
            $why_it_matters = 'People in ' . $city_name . ' are searching for ' . ( $first_service ? strtolower( $first_service ) . ' services' : 'your services' ) . ', but you don\'t have a page for them. A dedicated city page captures local intent and links back to your Service Areas hub to build authority.';
            ?>
            <div class="siloq-gap-card" style="border:1px solid #ede9fe;background:#f5f3ff;border-radius:8px;padding:16px;margin-bottom:12px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:200px;">
                        <span style="display:inline-block;background:#7c3aed;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:3px;margin-bottom:8px;letter-spacing:0.5px;">MISSING CITY PAGE</span>
                        <p style="font-size:14px;font-weight:700;color:#2e1065;margin:0 0 6px;"><?php echo esc_html( $suggested_title ); ?></p>
                        <p style="font-size:12px;color:#4b5563;margin:0 0 10px;line-height:1.5;"><?php echo esc_html( $why_it_matters ); ?></p>
                    </div>
                    <button class="siloq-btn siloq-btn--sm siloq-btn--primary siloq-create-page-btn" data-title="<?php echo esc_attr( $suggested_title ); ?>" data-type="city" style="white-space:nowrap;background:#7c3aed;border-color:#7c3aed;">Create Page &rarr;</button>
                </div>
            </div>
            <?php
        }

        if ( ! $has_cards ) {
            echo '<div style="text-align:center;padding:28px 16px;color:#9ca3af;">'
                . '<div style="font-size:28px;margin-bottom:8px;">✅</div>'
                . '<p style="font-size:13px;font-weight:600;color:#6b7280;margin:0 0 4px;">All pages covered</p>'
                . '<p style="font-size:12px;color:#9ca3af;margin:0;">Your configured services and service areas all have dedicated pages. Add more services or areas in Settings to see new opportunities.</p>'
                . '</div>';
        }

        return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════
    // FORMULA SEO TITLE / META DESC GENERATION
    // ═══════════════════════════════════════════════════════════════

    /**
     * Generate a smart, page-type-aware SEO title formula.
     * Never makes an API call — instant, deterministic.
     *
     * City page:    "Excelsior Springs Electrician | Able Electric KC"
     * Service page: "Panel Upgrades in Kansas City | Able Electric KC"
     * Hub page:     "Service Areas | Able Electric KC"
     * Generic:      "Page Title | Business Name"
     */
    public static function siloq_formula_seo_title( $post_id ) {
        $post_title   = get_the_title( $post_id );
        $biz_name     = get_option( 'siloq_business_name', get_bloginfo( 'name' ) );
        $primary_city = get_option( 'siloq_city', '' );
        $page_type    = get_post_meta( $post_id, '_siloq_page_role', true )
            ?: get_post_meta( $post_id, '_siloq_page_type_classification', true )
            ?: 'supporting';

        $primary_services = json_decode( get_option( 'siloq_primary_services', '[]' ), true );
        if ( ! is_array( $primary_services ) ) $primary_services = [];
        $primary_service = ! empty( $primary_services ) ? $primary_services[0] : '';

        $title = '';

        if ( in_array( $page_type, [ 'spoke', 'city' ], true ) ) {
            // City page: use post title (already contains city + service) + brand
            // Clean it up: remove trailing state abbr if duplicated
            $title = $post_title . ' | ' . $biz_name;
        } elseif ( in_array( $page_type, [ 'hub', 'apex_hub' ], true ) ) {
            // Hub: "Service Areas | Business Name"
            $title = $post_title . ' | ' . $biz_name;
        } elseif ( $page_type === 'supporting' || $page_type === 'orphan' ) {
            // Service/supporting page: add "in [Primary City]" if not already in title
            if ( $primary_city && stripos( $post_title, $primary_city ) === false ) {
                $title = $post_title . ' in ' . $primary_city . ' | ' . $biz_name;
            } else {
                $title = $post_title . ' | ' . $biz_name;
            }
        } else {
            $title = $post_title . ' | ' . $biz_name;
        }

        // Cap at 60 chars
        if ( mb_strlen( $title ) > 60 ) {
            // Try without the "in City" part first
            $short = $post_title . ' | ' . $biz_name;
            if ( mb_strlen( $short ) <= 60 ) {
                $title = $short;
            } else {
                $title = mb_substr( $title, 0, 57 ) . '...';
            }
        }

        return $title;
    }

    /**
     * Generate a formula-based meta description.
     * Uses analysis excerpt, then page content snippet, then generic.
     */
    public static function siloq_formula_meta_desc( $post_id ) {
        $post_title   = get_the_title( $post_id );
        $biz_name     = get_option( 'siloq_business_name', get_bloginfo( 'name' ) );
        $primary_city = get_option( 'siloq_city', '' );
        $phone        = get_option( 'siloq_phone', '' );

        // Try analysis excerpt first
        $analysis = json_decode( get_post_meta( $post_id, '_siloq_analysis_data', true ), true ) ?: [];
        if ( ! empty( $analysis['meta_description'] ) ) {
            $desc = $analysis['meta_description'];
        } elseif ( ! empty( $analysis['excerpt'] ) ) {
            $desc = $analysis['excerpt'];
        } else {
            // Build from post content
            $post_obj = get_post( $post_id );
            $content  = $post_obj ? wp_strip_all_tags( $post_obj->post_content ) : '';

            // Try Elementor data if post content empty
            if ( empty( $content ) ) {
                $el_data = get_post_meta( $post_id, '_elementor_data', true );
                if ( $el_data ) {
                    preg_match_all( '/"text"\s*:\s*"([^"]{30,})"/', $el_data, $m );
                    $content = ! empty( $m[1] ) ? html_entity_decode( implode( ' ', array_slice( $m[1], 0, 3 ) ) ) : '';
                }
            }

            if ( ! empty( $content ) ) {
                $desc = wp_trim_words( $content, 22, '' );
            } else {
                // Generic fallback using business info
                $city_str = $primary_city ? ' in ' . $primary_city : '';
                $phone_str = $phone ? ' Call ' . $phone . '.' : '';
                $desc = $biz_name . ' provides professional ' . strtolower( $post_title ) . $city_str . '.' . $phone_str;
            }
        }

        // Trim to ≤160 chars
        if ( mb_strlen( $desc ) > 160 ) {
            $desc = mb_substr( $desc, 0, 157 ) . '...';
        }

        return trim( $desc );
    }

    /**
     * AJAX: Return a formula-generated suggestion for title or description.
     * Optionally calls Claude API if BYOK is configured and user requests AI upgrade.
     */
    public static function ajax_generate_meta_suggestion() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $post_id   = intval( $_POST['post_id'] ?? 0 );
        $field     = sanitize_key( $_POST['field']    ?? 'title' ); // 'title' | 'description'
        $use_ai    = ! empty( $_POST['use_ai'] ) && $_POST['use_ai'] === '1';

        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Missing post_id' ] );
        }

        if ( $use_ai ) {
            // Try Claude BYOK
            $api_key = get_option( 'siloq_anthropic_api_key', '' );
            if ( ! $api_key ) {
                // Fall through to formula
                $use_ai = false;
            } else {
                $post_title = get_the_title( $post_id );
                $content    = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
                $biz_name   = get_option( 'siloq_business_name', get_bloginfo( 'name' ) );
                $city       = get_option( 'siloq_city', '' );

                if ( $field === 'title' ) {
                    $prompt = "Write an SEO title tag (max 60 characters) for a web page titled \"$post_title\" for a business called \"$biz_name\" in $city. Format: [primary keyword] | [brand name]. Just the title, no explanation.";
                } else {
                    $snippet = mb_substr( $content, 0, 400 );
                    $prompt  = "Write a meta description (max 155 characters) for a page titled \"$post_title\" for $biz_name. Content snippet: $snippet. Include a call to action. Just the description, no explanation.";
                }

                $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
                    'timeout' => 20,
                    'headers' => [
                        'x-api-key'         => $api_key,
                        'anthropic-version' => '2023-06-01',
                        'content-type'      => 'application/json',
                    ],
                    'body' => wp_json_encode( [
                        'model'      => 'claude-3-haiku-20240307',
                        'max_tokens' => 100,
                        'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
                    ] ),
                ] );

                if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                    $body = json_decode( wp_remote_retrieve_body( $response ), true );
                    $ai_text = $body['content'][0]['text'] ?? '';
                    if ( $ai_text ) {
                        $ai_text = trim( trim( $ai_text ), '"' );
                        wp_send_json_success( [ 'suggestion' => $ai_text, 'source' => 'ai' ] );
                        return;
                    }
                }
            }
        }

        // Formula fallback (always works, no API)
        $suggestion = $field === 'title'
            ? self::siloq_formula_seo_title( $post_id )
            : self::siloq_formula_meta_desc( $post_id );

        wp_send_json_success( [ 'suggestion' => $suggestion, 'source' => 'formula' ] );
    }

    /**
     * AJAX: Apply SEO title + description to all pages with missing values.
     * Processes one page per call; JS calls sequentially with progress reporting.
     */
    public static function ajax_fix_all_seo() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Missing post_id' ] );
        }

        $seo_plugin   = get_option( 'siloq_active_seo_plugin', 'siloq_native' );
        $applied      = [];
        $title_applied = false;
        $desc_applied  = false;

        // Check current title
        $current_title = self::siloq_get_page_title( $post_id );
        $needs_title   = ( $current_title === get_the_title( $post_id ) || empty( $current_title ) );
        if ( $needs_title ) {
            $title = self::siloq_formula_seo_title( $post_id );
            self::write_seo_title( $post_id, $title, $seo_plugin );
            $applied['title'] = $title;
            $title_applied    = true;
        }

        // Check current description
        $current_desc = self::siloq_get_meta_description( $post_id );
        $needs_desc   = empty( $current_desc ) || ( is_array( $current_desc ) && isset( $current_desc['status'] ) );
        if ( $needs_desc ) {
            $desc = self::siloq_formula_meta_desc( $post_id );
            self::write_seo_description( $post_id, $desc, $seo_plugin );
            $applied['description'] = $desc;
            $desc_applied = true;
        }

        wp_send_json_success( [
            'post_id'       => $post_id,
            'title'         => get_the_title( $post_id ),
            'applied'       => $applied,
            'title_applied' => $title_applied,
            'desc_applied'  => $desc_applied,
        ] );
    }

    /**
     * AJAX: Save a Quick Win checkbox state.
     */
    public static function ajax_save_quick_win() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $post_id    = intval( $_POST['post_id'] ?? 0 );
        $issue_type = sanitize_key( $_POST['issue_type'] ?? '' );
        $checked    = ! empty( $_POST['checked'] ) && $_POST['checked'] === '1';

        $key = $post_id . '_' . $issue_type;
        $completed = get_option( 'siloq_quick_wins_completed', [] );
        if ( ! is_array( $completed ) ) $completed = [];

        if ( $checked ) {
            $completed[ $key ] = time();
        } else {
            unset( $completed[ $key ] );
        }

        update_option( 'siloq_quick_wins_completed', $completed );
        wp_send_json_success( [ 'key' => $key, 'checked' => $checked ] );
    }

    // ═══════════════════════════════════════════════════════════════
    // BULK SCHEMA APPLY
    // ═══════════════════════════════════════════════════════════════

    /**
     * AJAX: Apply schema to a single page — called sequentially by the JS bulk processor.
     * The JS calls this once per page with a 1-second delay between calls.
     */
    public static function ajax_bulk_apply_schema() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Missing post_id' ] );
        }

        if ( ! class_exists( 'Siloq_Schema_Intelligence' ) ) {
            wp_send_json_error( [ 'message' => 'Schema Intelligence module not loaded.' ] );
        }

        // Temporarily inject post_id into POST for ajax_generate_schema()
        $_POST['post_id'] = $post_id;

        // Capture the JSON output instead of sending it
        ob_start();
        Siloq_Schema_Intelligence::ajax_generate_schema();
        $output = ob_get_clean();

        // ajax_generate_schema calls wp_send_json_* which exits — we captured it
        $result = json_decode( $output, true );

        if ( ! empty( $result['success'] ) ) {
            $title = get_the_title( $post_id );
            $types = $result['data']['schema_types'] ?? [];
            wp_send_json_success( [
                'post_id' => $post_id,
                'title'   => $title,
                'types'   => $types,
                'message' => 'Schema applied to "' . $title . '"',
            ] );
        } else {
            $msg = $result['data']['message'] ?? 'Schema generation failed.';
            wp_send_json_error( [
                'post_id' => $post_id,
                'title'   => get_the_title( $post_id ),
                'message' => $msg,
            ] );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SEO META WRITE HELPERS (multi-plugin + native fallback)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Write an SEO title to the correct SEO plugin's storage.
     * Falls back to _siloq_meta_title (injected via wp_head).
     */
    public static function write_seo_title( $post_id, $title, $plugin = '' ) {
        if ( ! $plugin ) {
            $plugin = get_option( 'siloq_active_seo_plugin', 'siloq_native' );
        }
        switch ( $plugin ) {
            case 'yoast':
                update_post_meta( $post_id, '_yoast_wpseo_title', $title );
                break;
            case 'rankmath':
                update_post_meta( $post_id, 'rank_math_title', $title );
                break;
            case 'seopress':
                update_post_meta( $post_id, '_seopress_titles_title', $title );
                break;
            case 'aioseo':
                // AIOSEO 4.x uses wp_aioseo_posts table primarily
                self::aioseo_upsert( $post_id, [ 'title' => $title ] );
                // Also write to post_meta as fallback (AIOSEO reads both)
                update_post_meta( $post_id, '_aioseo_title', $title );
                break;
            default: // siloq_native — injected via wp_head
                update_post_meta( $post_id, '_siloq_meta_title', $title );
                break;
        }
    }

    /**
     * Write a meta description to the correct SEO plugin's storage.
     */
    public static function write_seo_description( $post_id, $desc, $plugin = '' ) {
        if ( ! $plugin ) {
            $plugin = get_option( 'siloq_active_seo_plugin', 'siloq_native' );
        }
        switch ( $plugin ) {
            case 'yoast':
                update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );
                break;
            case 'rankmath':
                update_post_meta( $post_id, 'rank_math_description', $desc );
                break;
            case 'seopress':
                update_post_meta( $post_id, '_seopress_titles_desc', $desc );
                break;
            case 'aioseo':
                self::aioseo_upsert( $post_id, [ 'description' => $desc ] );
                update_post_meta( $post_id, '_aioseo_description', $desc );
                break;
            default:
                update_post_meta( $post_id, '_siloq_meta_description', $desc );
                break;
        }
    }

    /**
     * INSERT or UPDATE a row in wp_aioseo_posts for AIOSEO 4.x.
     */
    private static function aioseo_upsert( $post_id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aioseo_posts';
        if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) ) {
            return; // AIOSEO table doesn't exist — fall through to post_meta
        }
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE post_id = %d", $post_id ) );
        if ( $existing ) {
            $wpdb->update( $table, $data, [ 'post_id' => $post_id ] );
        } else {
            $wpdb->insert( $table, array_merge( [ 'post_id' => $post_id ], $data ) );
        }
    }

    /**
     * wp_head injection for sites with no SEO plugin.
     * Hooked via Siloq_Connector constructor.
     */
    public static function inject_siloq_meta_tags() {
        if ( is_admin() || ! is_singular() ) return;
        $post_id = get_the_ID();
        if ( ! $post_id ) return;

        $title = get_post_meta( $post_id, '_siloq_meta_title', true );
        $desc  = get_post_meta( $post_id, '_siloq_meta_description', true );

        if ( $title ) {
            echo '<title>' . esc_html( $title ) . '</title>' . "\n";
            echo '<meta name="og:title" content="' . esc_attr( $title ) . '">' . "\n";
        }
        if ( $desc ) {
            echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
            echo '<meta name="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
        }
    }

    /**
     * AJAX: Toggle redirect enabled/disabled.
     */
    public static function ajax_toggle_redirect() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }
        $id = intval( $_POST['redirect_id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'Missing redirect ID.' ] );
        }
        if ( ! class_exists( 'Siloq_Redirect_Manager' ) ) {
            wp_send_json_error( [ 'message' => 'Redirect manager not available.' ] );
        }
        $ok = Siloq_Redirect_Manager::get_instance()->toggle_redirect( $id );
        wp_send_json_success( [ 'toggled' => $ok ] );
    }

    // ═══════════════════════════════════════════════════════════════
    // REPOSITION + RENAME AJAX HANDLERS  (v1.5.193)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Reposition a page: set post_parent to target hub, create 301 redirect, clear flag.
     */
    public static function ajax_reposition_page() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $post_id       = intval( $_POST['post_id'] ?? 0 );
        $target_hub_id = intval( $_POST['target_hub_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( 'Missing post_id.' );
        }
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( 'Post not found.' );
        }
        $old_url = get_permalink( $post_id );

        // Update parent
        wp_update_post( array(
            'ID'          => $post_id,
            'post_parent' => $target_hub_id,
        ) );

        // Create 301 redirect from old URL to new URL
        $new_url  = get_permalink( $post_id );
        $old_path = trim( parse_url( $old_url, PHP_URL_PATH ), '/' );
        $new_path = trim( parse_url( $new_url, PHP_URL_PATH ), '/' );
        if ( $old_path !== $new_path && class_exists( 'Siloq_Redirect_Manager' ) ) {
            Siloq_Redirect_Manager::get_instance()->add_redirect( '/' . $old_path, '/' . $new_path, 301 );
        }

        // Clear flag
        delete_post_meta( $post_id, '_siloq_reposition_flag' );

        wp_send_json_success( array( 'old_url' => $old_url, 'new_url' => $new_url ) );
    }

    /**
     * Approve a rename: update post_title + post_name, clear meta.
     */
    public static function ajax_approve_rename() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $post_id   = intval( $_POST['post_id'] ?? 0 );
        $new_title = sanitize_text_field( $_POST['new_title'] ?? '' );
        if ( ! $post_id || ! $new_title ) {
            wp_send_json_error( 'Missing post_id or new_title.' );
        }
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( 'Post not found.' );
        }
        $old_url = get_permalink( $post_id );

        wp_update_post( array(
            'ID'         => $post_id,
            'post_title' => $new_title,
            'post_name'  => sanitize_title( $new_title ),
        ) );

        // Create 301 redirect from old URL to new URL
        $new_url  = get_permalink( $post_id );
        $old_path = trim( parse_url( $old_url, PHP_URL_PATH ), '/' );
        $new_path = trim( parse_url( $new_url, PHP_URL_PATH ), '/' );
        if ( $old_path !== $new_path && class_exists( 'Siloq_Redirect_Manager' ) ) {
            Siloq_Redirect_Manager::get_instance()->add_redirect( '/' . $old_path, '/' . $new_path, 301 );
        }

        // Clear meta
        delete_post_meta( $post_id, '_siloq_rename_suggestion' );

        wp_send_json_success( array( 'new_title' => $new_title, 'old_url' => $old_url, 'new_url' => $new_url ) );
    }

    /**
     * Dismiss a rename suggestion.
     */
    public static function ajax_dismiss_rename() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( 'Missing post_id.' );
        }
        delete_post_meta( $post_id, '_siloq_rename_suggestion' );
        wp_send_json_success();
    }

    // ═══════════════════════════════════════════════════════════════
    // REDIRECT MANAGER AJAX HANDLERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * AJAX: Get all redirects.
     */
    public static function ajax_get_redirects() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        if ( ! class_exists( 'Siloq_Redirect_Manager' ) ) {
            wp_send_json_error( [ 'message' => 'Redirect manager not available.' ] );
        }

        $redirects = Siloq_Redirect_Manager::get_instance()->get_all_redirects();
        wp_send_json_success( [ 'redirects' => $redirects ] );
    }

    /**
     * AJAX: Add a single redirect.
     */
    public static function ajax_add_redirect() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $from        = sanitize_text_field( $_POST['from']        ?? '' );
        $to          = sanitize_text_field( $_POST['to']          ?? '' );
        $status_code = intval( $_POST['status_code'] ?? 301 );

        // Validate allowed codes
        if ( ! in_array( $status_code, [ 301, 302, 307, 410, 451 ], true ) ) {
            $status_code = 301;
        }

        if ( ! $from || ! $to ) {
            wp_send_json_error( [ 'message' => 'Both Source and Target URLs are required.' ] );
        }

        if ( $from === $to ) {
            wp_send_json_error( [ 'message' => 'Source and Target URLs cannot be the same.' ] );
        }

        $ok = Siloq_Redirect_Manager::get_instance()->add_redirect( $from, $to, $status_code );
        if ( $ok ) {
            wp_send_json_success( [ 'message' => 'Redirect added.' ] );
        } else {
            $db_err = Siloq_Redirect_Manager::$last_error;
            wp_send_json_error( [
                'message'  => 'Failed to add redirect.' . ( $db_err ? ' DB: ' . $db_err : ' It may already exist.' ),
                'db_error' => $db_err,
                'from'     => $from,
                'to'       => $to,
            ] );
        }
    }

    /**
     * AJAX: Delete a redirect by ID.
     */
    public static function ajax_delete_redirect() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $id = intval( $_POST['redirect_id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'Missing redirect ID.' ] );
        }

        $ok = Siloq_Redirect_Manager::get_instance()->delete_redirect( $id );
        wp_send_json_success( [ 'message' => $ok ? 'Redirect deleted.' : 'Nothing to delete.' ] );
    }

    /**
     * AJAX: Bulk-add redirects (array of {from, to} pairs).
     */
    public static function ajax_bulk_add_redirects() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $pairs_raw = $_POST['redirects'] ?? '';
        $pairs     = is_array( $pairs_raw ) ? $pairs_raw : json_decode( stripslashes( $pairs_raw ), true );

        if ( ! is_array( $pairs ) || empty( $pairs ) ) {
            wp_send_json_error( [ 'message' => 'No redirect pairs provided.' ] );
        }

        $mgr     = Siloq_Redirect_Manager::get_instance();
        $added   = 0;
        $failed  = 0;
        $skipped = 0;
        $errors  = array();

        foreach ( $pairs as $pair ) {
            $from = sanitize_text_field( isset( $pair['from'] ) ? $pair['from'] : '' );
            $to   = sanitize_text_field( isset( $pair['to'] )   ? $pair['to']   : '' );
            if ( ! $from || ! $to || $from === $to ) { $skipped++; continue; }
            if ( $mgr->add_redirect( $from, $to, 301 ) ) {
                $added++;
            } else {
                $failed++;
                $db_err = Siloq_Redirect_Manager::$last_error;
                $errors[] = array(
                    'from'     => $from,
                    'to'       => $to,
                    'db_error' => $db_err ? $db_err : 'Unknown error',
                );
            }
        }

        $msg = "Added {$added} redirect" . ( $added !== 1 ? 's' : '' );
        if ( $failed )  { $msg .= ", {$failed} failed"; }
        if ( $skipped ) { $msg .= ", {$skipped} skipped (duplicates or invalid)"; }
        $msg .= '.';

        wp_send_json_success( array(
            'added'   => $added,
            'failed'  => $failed,
            'skipped' => $skipped,
            'errors'  => $errors,
            'message' => $msg,
        ) );
    }

    /**
     * AJAX: Preview city-page → /service-area/ redirects.
     *
     * Scans all synced pages classified as spoke/supporting whose slug does NOT
     * already start with a known hub parent slug. Suggests moving them under
     * the detected service-area hub page.
     */
    public static function ajax_preview_city_redirects() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        // Optional: caller can pass a custom target prefix (default: /service-area/)
        $target_prefix = sanitize_text_field( $_POST['target_prefix'] ?? '/service-area/' );
        $target_prefix = '/' . trim( $target_prefix, '/' ) . '/';

        // Detect the service-area hub page (page whose slug is 'service-area' or 'service-areas')
        $hub_page = get_page_by_path( 'service-area' ) ?: get_page_by_path( 'service-areas' );
        $hub_slug = $hub_page ? get_page_uri( $hub_page ) : ltrim( $target_prefix, '/' );

        // Get all synced published pages
        $posts = get_posts( [
            'post_type'   => function_exists( 'get_siloq_crawlable_post_types' )
                ? get_siloq_crawlable_post_types()
                : [ 'page', 'post' ],
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query'  => [ [ 'key' => '_siloq_synced', 'compare' => 'EXISTS' ] ],
        ] );

        $mgr        = Siloq_Redirect_Manager::get_instance();
        $suggestions = [];
        $skipped     = [];

        foreach ( $posts as $post ) {
            $slug      = get_page_uri( $post );   // e.g. 'excelsior-springs-mo-electrician'
            $permalink = get_permalink( $post->ID );

            // Skip if it's already under the target prefix
            if ( strpos( $slug, ltrim( $target_prefix, '/' ) ) === 0 ) {
                $skipped[] = [ 'title' => $post->post_title, 'reason' => 'Already under ' . $target_prefix ];
                continue;
            }

            // Skip hub / apex-hub pages — only move spoke/supporting/orphan
            $page_type = '';
            $manual    = get_post_meta( $post->ID, '_siloq_page_role', true );
            if ( $manual ) {
                $page_type = $manual;
            } elseif ( class_exists( 'Siloq_Admin' ) ) {
                $page_type = Siloq_Admin::siloq_classify_page( $post->ID, $permalink );
            } else {
                $analysis = json_decode( get_post_meta( $post->ID, '_siloq_analysis_data', true ), true ) ?: [];
                $page_type = $analysis['page_type'] ?? 'supporting';
            }

            if ( in_array( $page_type, [ 'hub', 'apex_hub' ], true ) ) {
                $skipped[] = [ 'title' => $post->post_title, 'reason' => 'Hub page — not moved' ];
                continue;
            }

            // ── City-page filter ─────────────────────────────────────────────
            // Only suggest pages that are clearly geo/location pages.
            // Service pages, utility pages, and site sections should NEVER be
            // redirected under /service-areas/. We use two signals:
            //
            // 1. EXCLUDE known utility/service slugs unconditionally.
            // 2. REQUIRE a geo indicator: either city_spoke type, or a US state
            //    abbreviation / geo keyword in the slug.
            //
            // Pages like /contact/, /about-us/, /services/, /testimonials/,
            // /industrial/, /residential/ etc. will be skipped.
            $utility_slugs = [
                'contact', 'contact-us', 'about', 'about-us', 'our-team', 'team',
                'staff', 'testimonials', 'reviews', 'faq', 'faqs', 'blog',
                'news', 'careers', 'jobs', 'privacy-policy', 'privacy',
                'terms', 'terms-of-service', 'terms-and-conditions',
                'sitemap', 'home', 'services', 'our-services', 'portfolio',
                'gallery', 'pricing', 'financing', 'promotions', 'coupons',
                'warranty', 'guaranty', 'maintenance', 'maintenance-works',
                'industrial', 'residential', 'commercial', 'emergency',
                'emergency-services', 'repair', 'installation', 'inspections',
            ];

            // Get the base slug (last segment) to check against utility list
            $base_slug = basename( rtrim( $slug, '/' ) );

            if ( in_array( $base_slug, $utility_slugs, true ) ) {
                $skipped[] = [ 'title' => $post->post_title, 'reason' => 'Utility/service page — not a city page' ];
                continue;
            }

            // Require a geo indicator: city_spoke type OR a US state abbreviation
            // in the slug (e.g. -mo-, -ks-, -tx-, -il-, -ok-, etc.)
            $us_state_pattern = '/[\-_](al|ak|az|ar|ca|co|ct|de|fl|ga|hi|id|il|in|ia|ks|ky|la|me|md|ma|mi|mn|ms|mo|mt|ne|nv|nh|nj|nm|ny|nc|nd|oh|ok|or|pa|ri|sc|sd|tn|tx|ut|vt|va|wa|wv|wi|wy)([\-_\/]|$)/i';
            $has_geo_indicator = ( $page_type === 'city_spoke' )
                || preg_match( $us_state_pattern, '-' . $slug );

            if ( ! $has_geo_indicator ) {
                $skipped[] = [ 'title' => $post->post_title, 'reason' => 'No geo indicator — not a city page' ];
                continue;
            }
            // ── /City-page filter ────────────────────────────────────────────

            // Build new slug by prepending target prefix
            $new_slug      = ltrim( $target_prefix, '/' ) . $slug;
            $site_url      = get_site_url();
            $from_url      = '/' . $slug . '/';
            $to_url        = $target_prefix . $slug . '/';

            // Check if redirect already exists
            $already_exists = $mgr->get_redirect( $from_url );

            $suggestions[] = [
                'post_id'        => $post->ID,
                'title'           => $post->post_title,
                'page_type'       => $page_type,
                'from'            => $from_url,
                'to'              => $to_url,
                'from_full'       => $site_url . $from_url,
                'to_full'         => $site_url . $to_url,
                'already_exists'  => ! empty( $already_exists ),
                'post_id'         => $post->ID,
                'original_slug'   => $post->post_name,
                'original_parent' => $post->post_parent,
                'hub_post_id'     => $hub_page ? $hub_page->ID : 0,
            ];
        }

        wp_send_json_success( [
            'suggestions'   => $suggestions,
            'skipped'       => $skipped,
            'target_prefix' => $target_prefix,
            'hub_slug'      => $hub_slug,
        ] );
    }

    /**
     * AJAX: Atomically restructure a single city page slug + create 301 redirect.
     *
     * Sets post_parent to the hub page (which changes the URL), then creates a
     * 301 redirect from the old path to the new path. Rolls back on failure.
     *
     * @since 1.5.173
     */
    public static function ajax_atomic_restructure_page() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $post_id         = intval( isset( $_POST['post_id'] ) ? $_POST['post_id'] : 0 );
        $hub_post_id     = intval( isset( $_POST['hub_post_id'] ) ? $_POST['hub_post_id'] : 0 );
        $original_parent = intval( isset( $_POST['original_parent'] ) ? $_POST['original_parent'] : 0 );

        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Invalid post ID.' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( array( 'message' => 'Post not found.' ) );
        }

        // Capture old URL before any change
        $old_url = get_permalink( $post_id );

        // Update post_parent to nest under hub (keeps same post_name / slug segment)
        $update_data = array( 'ID' => $post_id );
        if ( $hub_post_id ) {
            $update_data['post_parent'] = $hub_post_id;
        }
        $result = wp_update_post( $update_data, true );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Fix 3: Clear WP's post cache so get_permalink() reflects the new parent.
        clean_post_cache( $post_id );
        wp_cache_delete( $post_id, 'posts' );

        // Soft-flush rewrite rules so get_permalink() reflects the new parent
        flush_rewrite_rules( false );

        $new_url = get_permalink( $post_id );

        // Fix 3: If WP still returns the same URL (stale cache / no pretty-permalinks),
        // compute the nested URL manually from the hub slug + page slug.
        if ( $old_url === $new_url && $hub_post_id ) {
            $hub      = get_post( $hub_post_id );
            $hub_slug = $hub ? $hub->post_name : '';
            $page_obj = get_post( $post_id );
            $page_slug = $page_obj ? $page_obj->post_name : '';
            if ( $hub_slug && $page_slug ) {
                $new_url = trailingslashit( get_site_url() ) . $hub_slug . '/' . $page_slug . '/';
            }
        }

        if ( $old_url === $new_url ) {
            // Parent change had no effect on URL — nothing to redirect
            // Fix 5: Still reclassify the page role even if URL didn't change.
            if ( class_exists( 'Siloq_Sync_Engine' ) ) {
                $sync = new Siloq_Sync_Engine();
                $sync->reclassify_page_by_parent( $post_id );
            }
            wp_send_json_success( array(
                'message' => 'No URL change detected — skipped redirect creation.',
                'skipped' => true,
            ) );
            return;
        }

        $old_path = wp_parse_url( $old_url, PHP_URL_PATH );
        $new_path = wp_parse_url( $new_url, PHP_URL_PATH );

        // Create 301 redirect
        $mgr              = Siloq_Redirect_Manager::get_instance();
        $redirect_created = $mgr->add_redirect( $old_path, $new_path, 301 );

        if ( ! $redirect_created ) {
            // Roll back the parent change
            wp_update_post( array( 'ID' => $post_id, 'post_parent' => $original_parent ) );
            clean_post_cache( $post_id );
            flush_rewrite_rules( false );
            $db_err = Siloq_Redirect_Manager::$last_error;
            wp_send_json_error( array(
                'message' => 'Redirect creation failed — parent change rolled back. ' . ( $db_err ? $db_err : '' ),
            ) );
        }

        // Fix 5: Re-classify this page's role based on the new post_parent.
        if ( class_exists( 'Siloq_Sync_Engine' ) ) {
            $sync = new Siloq_Sync_Engine();
            $sync->reclassify_page_by_parent( $post_id );
        }

        // Fix 6: Update internal links across all published pages/posts.
        // Find every page whose content contains the old URL and replace with new URL.
        $old_url_variants = array(
            $old_url,                                        // full URL with trailing slash
            rtrim( $old_url, '/' ),                         // without trailing slash
            $old_path,                                       // path only e.g. /our-services/residential/
            rtrim( $old_path, '/' ),                         // path without trailing slash
        );
        $old_url_variants = array_unique( array_filter( $old_url_variants ) );

        $all_posts = get_posts( array(
            'post_type'      => array( 'page', 'post' ),
            'post_status'    => array( 'publish', 'draft' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        $updated_count = 0;
        foreach ( $all_posts as $pid ) {
            if ( $pid === $post_id ) continue; // skip the restructured page itself
            $p = get_post( $pid );
            if ( ! $p ) continue;
            $content = $p->post_content;
            if ( empty( $content ) ) continue;

            $new_content = $content;
            foreach ( $old_url_variants as $old_variant ) {
                if ( strpos( $new_content, $old_variant ) !== false ) {
                    $new_content = str_replace( $old_variant, $new_url, $new_content );
                }
            }

            if ( $new_content !== $content ) {
                wp_update_post( array(
                    'ID'           => $pid,
                    'post_content' => $new_content,
                ) );
                $updated_count++;
            }
        }

        wp_send_json_success( array(
            'message'       => 'Restructured successfully. Internal links updated in ' . $updated_count . ' page(s).',
            'old_url'       => $old_url,
            'new_url'       => $new_url,
            'links_updated' => $updated_count,
        ) );
    }

    // ── Debug Logging AJAX Handlers ──────────────────────────────────────

    public static function ajax_toggle_debug() {
        check_ajax_referer( 'siloq_debug_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );
        $enabled = ! empty( $_POST['enabled'] );
        update_option( 'siloq_debug_mode', $enabled ? 1 : 0 );
        wp_send_json_success( array( 'enabled' => $enabled ) );
    }

    public static function ajax_get_debug_log() {
        check_ajax_referer( 'siloq_debug_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );
        $logger = Siloq_Debug_Logger::get_instance();
        $lines  = $logger->get_last_lines( 50 );
        wp_send_json_success( array( 'log' => implode( '', $lines ) ) );
    }

    public static function ajax_clear_debug_log() {
        check_ajax_referer( 'siloq_debug_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );
        Siloq_Debug_Logger::get_instance()->clear();
        wp_send_json_success();
    }

    public static function ajax_download_debug_log() {
        check_ajax_referer( 'siloq_debug_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );
        $logger = Siloq_Debug_Logger::get_instance();
        $path   = $logger->get_file_path();
        $content = file_exists( $path ) ? file_get_contents( $path ) : '';
        wp_send_json_success( array( 'content' => $content ) );
    }

    /**
     * AJAX: Save Brand Voice settings.
     */
    public static function ajax_save_brand_voice() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $allowed = array( 'confident_expert', 'warm_advisor', 'no_bs_truth_teller', 'sage_strategist', 'tech_translator', 'rebellious_challenger' );

        $primary   = sanitize_text_field( isset( $_POST['primary_tone'] ) ? $_POST['primary_tone'] : 'confident_expert' );
        $secondary = sanitize_text_field( isset( $_POST['secondary_tone'] ) ? $_POST['secondary_tone'] : '' );

        if ( ! in_array( $primary, $allowed, true ) ) {
            $primary = 'confident_expert';
        }
        if ( $secondary && ! in_array( $secondary, $allowed, true ) ) {
            $secondary = '';
        }

        $brand_voice = array(
            'primary_tone'        => $primary,
            'secondary_tone'      => $secondary,
            'industry'            => sanitize_text_field( isset( $_POST['industry'] ) ? $_POST['industry'] : '' ),
            'admired_brands'      => sanitize_text_field( isset( $_POST['admired_brands'] ) ? $_POST['admired_brands'] : '' ),
            'tagline'             => sanitize_text_field( isset( $_POST['tagline'] ) ? $_POST['tagline'] : '' ),
            'using_smart_default' => (bool) ( isset( $_POST['using_smart_default'] ) ? $_POST['using_smart_default'] : false ),
        );

        update_option( 'siloq_brand_voice', wp_json_encode( $brand_voice ) );
        wp_send_json_success( array( 'message' => 'Brand voice saved.', 'brand_voice' => $brand_voice ) );
    }

    /**
     * AJAX: Sync Brand Voice to Siloq Profile via API.
     */
    public static function ajax_sync_brand_voice() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $site_id = get_option( 'siloq_site_id', '' );
        $api_key = get_option( 'siloq_api_key', '' );

        if ( empty( $site_id ) || empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => 'Not connected to Siloq. Please complete setup first.' ) );
        }

        $bv_raw = get_option( 'siloq_brand_voice', '{}' );
        $bv = json_decode( $bv_raw, true );

        if ( empty( $bv ) || ! is_array( $bv ) ) {
            wp_send_json_error( array( 'message' => 'No brand voice configured yet. Please set your brand voice first.' ) );
        }

        $url = 'https://api.siloq.ai/api/v1/sites/' . urlencode( $site_id ) . '/entity-profile/';

        $response = wp_remote_request( $url, array(
            'method'  => 'PATCH',
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( array( 'brand_voice' => $bv ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'Sync failed: ' . $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code === 200 ) {
            wp_send_json_success( array( 'message' => 'Brand voice synced to Siloq profile!' ) );
        } else {
            $msg = wp_remote_retrieve_response_message( $response );
            wp_send_json_error( array( 'message' => 'Sync failed: ' . $msg ) );
        }
    }

    /**
     * AJAX: Generate SEO Plan via the intelligence endpoint.
     * Calls POST /api/v1/sites/{site_id}/intelligence/ and returns the result.
     *
     * @since 1.5.164
     */
    public static function ajax_generate_intelligence() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $site_id = get_option( 'siloq_site_id', '' );
        $api_key = get_option( 'siloq_api_key', '' );
        $api_url = get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' );

        if ( empty( $site_id ) || empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => 'Siloq is not connected. Please add your API key and site ID in Settings.' ) );
        }

        $endpoint = $api_url . '/sites/' . $site_id . '/intelligence/';

        $response = wp_remote_get( $endpoint, array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'API request failed: ' . $response->get_error_message() ) );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );
        $data   = json_decode( $body, true );

        if ( $status < 200 || $status >= 300 ) {
            $err = isset( $data['detail'] ) ? $data['detail'] : ( isset( $data['message'] ) ? $data['message'] : "HTTP {$status}" );
            wp_send_json_error( array( 'message' => 'Intelligence API error: ' . $err ) );
        }

        // Persist plan data locally so the dashboard can reload it
        if ( is_array( $data ) ) {
            $data['generated_at'] = current_time( 'mysql' );

            // If the API returned empty hub/spoke arrays, populate from WP-local
            // post meta so the Site Architecture section always shows real data
            // (API may not have classification data if sync hasn't processed roles yet)
            $intel = isset( $data['intelligence'] ) && is_array( $data['intelligence'] ) ? $data['intelligence'] : array();
            if ( empty( $intel['hub_pages'] ) || empty( $intel['spoke_pages'] ) ) {
                $local_hub_pages   = array();
                $local_spoke_pages = array();
                $local_orphans     = array();

                $all_synced = get_posts( array(
                    'post_type'      => array( 'page', 'post' ),
                    'post_status'    => 'publish',
                    'numberposts'    => 200,
                    'meta_key'       => '_siloq_synced',
                    'meta_value'     => '1',
                ) );

                foreach ( $all_synced as $p ) {
                    $role = get_post_meta( $p->ID, '_siloq_page_role', true )
                         ?: get_post_meta( $p->ID, 'page_type_classification', true );
                    $url  = get_permalink( $p->ID );
                    if ( in_array( $role, array( 'hub', 'apex_hub' ), true ) ) {
                        $local_hub_pages[] = array(
                            'page_id' => $p->ID,
                            'title'   => $p->post_title,
                            'url'     => $url,
                        );
                    } elseif ( $role === 'spoke' || $role === 'supporting' ) {
                        $hub_parent = intval( get_post_meta( $p->ID, '_siloq_expected_linker_id', true ) )
                                   ?: wp_get_post_parent_id( $p->ID );
                        $local_spoke_pages[] = array(
                            'page_id'     => $p->ID,
                            'title'       => $p->post_title,
                            'url'         => $url,
                            'hub_page_id' => $hub_parent,
                        );
                    }
                }

                if ( empty( $intel['hub_pages'] ) )   $intel['hub_pages']   = $local_hub_pages;
                if ( empty( $intel['spoke_pages'] ) )  $intel['spoke_pages']  = $local_spoke_pages;
                if ( empty( $intel['orphan_pages'] ) ) $intel['orphan_pages'] = $local_orphans;

                $data['intelligence'] = $intel;
            }

            update_option( 'siloq_plan_data', wp_json_encode( $data ), false );
        }

        wp_send_json_success( $data );
    }

    /**
     * AJAX: Return published pages/posts for the GEO page selector.
     */
    public static function ajax_get_pages_for_selector() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        global $wpdb;
        $pages = $wpdb->get_results(
            "SELECT ID, post_title, guid FROM {$wpdb->posts}
             WHERE post_status = 'publish' AND post_type IN ('page','post')
             ORDER BY post_title ASC LIMIT 200",
            ARRAY_A
        );
        wp_send_json_success( array( 'pages' => $pages ) );
    }

    /**
     * AJAX: Save goals (primary goal + GEO priority pages) and sync to API.
     */
    public static function ajax_save_goals() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );

        $primary_goal    = isset( $_POST['primary_goal'] ) ? sanitize_text_field( $_POST['primary_goal'] ) : 'local_leads';

        // Accept both geo_priority_pages (legacy) and target_keywords (new)
        $geo_pages_raw   = isset( $_POST['geo_priority_pages'] ) ? (array) $_POST['geo_priority_pages'] : array();
        $geo_pages       = array_map( 'intval', $geo_pages_raw );

        $target_kw_raw   = isset( $_POST['target_keywords'] ) ? (array) $_POST['target_keywords'] : array();
        $target_keywords = array_slice( array_map( 'sanitize_text_field', $target_kw_raw ), 0, 7 );

        if ( ! empty( $target_keywords ) ) {
            update_option( 'siloq_target_keywords_' . get_option('siloq_site_id','0'), wp_json_encode( $target_keywords ) );
        }

        $goals = array(
            'primary_goal'       => $primary_goal,
            'geo_priority_pages' => $geo_pages,    // backward compat — kept but deprecated
            'target_keywords'    => $target_keywords,
        );
        Siloq_Goals::save_goals( $goals );

        // Sync to API
        $site_id = get_option( 'siloq_site_id' );
        if ( $site_id ) {
            $api_client = new Siloq_API_Client();
            Siloq_Goals::sync_to_api( $site_id, $api_client );
        }

        wp_send_json_success( array( 'message' => 'Goals saved' ) );
    }

    /**
     * Auto-map a list of keyword phrases to the best matching synced WP page.
     * Uses similar_text() against page titles. Match threshold: 40%.
     *
     * @param  array $keywords  List of keyword strings (max 7).
     * @return array            Each element: ['keyword', 'post_id', 'matched_title', 'edit_url']
     */
    private static function map_keywords_to_pages( $keywords ) {
        if ( empty( $keywords ) ) {
            return array();
        }

        // Fetch all synced published pages (titles are the match surface)
        $all_pages = get_posts( array(
            'post_type'   => array( 'page', 'post' ),
            'post_status' => 'publish',
            'numberposts' => 500,
            'meta_query'  => array( array( 'key' => '_siloq_synced', 'compare' => 'EXISTS' ) ),
            'fields'      => 'all',
        ) );

        $keyword_map     = array();
        $keyword_page_map = array(); // keyword string => post_id

        foreach ( $keywords as $keyword ) {
            $keyword_clean = strtolower( trim( $keyword ) );
            $best_score    = 0;
            $best_id       = 0;
            $best_title    = '';

            foreach ( $all_pages as $page ) {
                $title_lc = strtolower( $page->post_title );
                $score    = 0;
                similar_text( $keyword_clean, $title_lc, $score );
                if ( $score > $best_score ) {
                    $best_score = $score;
                    $best_id    = $page->ID;
                    $best_title = $page->post_title;
                }
            }

            $matched_id    = ( $best_score >= 40 ) ? $best_id    : 0;
            $matched_title = ( $best_score >= 40 ) ? $best_title : '';
            $edit_url      = $matched_id ? get_edit_post_link( $matched_id, 'raw' ) : '';

            $keyword_map[]          = array(
                'keyword'       => $keyword,
                'post_id'       => $matched_id,
                'matched_title' => $matched_title,
                'edit_url'      => $edit_url,
                'score'         => round( $best_score, 1 ),
            );
            if ( $matched_id ) {
                $keyword_page_map[ $keyword ] = $matched_id;
            }
        }

        // Persist the map
        update_option( 'siloq_keyword_page_map', wp_json_encode( $keyword_page_map ) );

        return $keyword_map;
    }

    /**
     * AJAX: Save Goals tab form (primary goal, services, cities, target keywords).
     */
    public static function ajax_save_goals_tab() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
            return;
        }

        $primary_goal        = isset( $_POST['primary_goal'] )        ? sanitize_text_field( $_POST['primary_goal'] )        : '';
        $priority_services_r = isset( $_POST['priority_services'] )   ? (array) $_POST['priority_services']                  : array();
        $priority_cities_r   = isset( $_POST['priority_cities'] )     ? (array) $_POST['priority_cities']                    : array();
        $target_kw_r         = isset( $_POST['target_keywords'] )     ? (array) $_POST['target_keywords']                    : array();

        // Backward compat: accept geo_priority_pages if sent
        $geo_pages_r         = isset( $_POST['geo_priority_pages'] )  ? (array) $_POST['geo_priority_pages']                 : array();

        $priority_services = array_map( 'sanitize_text_field', $priority_services_r );
        $priority_cities   = array_map( 'sanitize_text_field', $priority_cities_r );
        $target_keywords   = array_slice( array_map( 'sanitize_text_field', $target_kw_r ), 0, 7 );
        $geo_pages         = array_map( 'intval', $geo_pages_r );

        // Save individual options
        if ( $primary_goal )       update_option( 'siloq_primary_goal',       $primary_goal );
        if ( ! empty( $priority_services ) ) update_option( 'siloq_priority_services', wp_json_encode( $priority_services ) );
        if ( ! empty( $priority_cities ) )   update_option( 'siloq_priority_cities',   wp_json_encode( $priority_cities ) );
        if ( ! empty( $target_keywords ) )   update_option( 'siloq_target_keywords_' . get_option('siloq_site_id','0'),   wp_json_encode( $target_keywords ) );
        if ( ! empty( $geo_pages ) )         update_option( 'siloq_geo_priority_pages', wp_json_encode( $geo_pages ) ); // backward compat

        // Auto-map keywords to pages
        $keyword_map = array();
        if ( ! empty( $target_keywords ) ) {
            // Only run expensive keyword mapping if site has synced pages (avoid timeout on new installs)
            $synced_count = (int) get_option( 'siloq_synced_page_count', 0 );
            $keyword_map  = $synced_count > 0 ? self::map_keywords_to_pages( $target_keywords ) : array();
        }

        // Persist goals (backward compat siloq_site_goals option)
        $goals = array(
            'primary_goal'       => $primary_goal,
            'priority_services'  => $priority_services,
            'priority_locations' => $priority_cities,
            'target_keywords'    => $target_keywords,
            'geo_priority_pages' => $geo_pages, // deprecated but kept for compat
        );
        Siloq_Goals::save_goals( $goals );

        // Sync to API via POST /sites/{site_id}/goals/
        $site_id = get_option( 'siloq_site_id' );
        if ( $site_id ) {
            $api_client = new Siloq_API_Client();
            $api_client->make_request( '/sites/' . intval( $site_id ) . '/goals/', 'POST', $goals );
        }

        wp_send_json_success( array(
            'message'     => 'Goals saved — intelligence will update on next audit',
            'keyword_map' => $keyword_map,
        ) );
    }

    /**
     * AJAX: Get orphan fix suggestion — reads pre-computed expected linker.
     */
    public static function ajax_get_orphan_suggestion() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        $spoke_id = intval( $_POST['spoke_id'] ?? 0 );
        if ( ! $spoke_id ) {
            wp_send_json_error( array( 'message' => 'Missing spoke page ID.' ) );
        }

        $hub_id = intval( get_post_meta( $spoke_id, '_siloq_expected_linker_id', true ) );

        // If not pre-computed, resolve on the fly and save for next time
        if ( ! $hub_id || ! get_post( $hub_id ) ) {
            $hub_id = 0;

            // 1. Direct WP parent
            $parent_id = wp_get_post_parent_id( $spoke_id );
            if ( $parent_id && get_post_status( $parent_id ) === 'publish' ) {
                $hub_id = $parent_id;
            }

            // 2. Search for most relevant hub by keyword/title match
            if ( ! $hub_id ) {
                $spoke_title_lower = strtolower( get_the_title( $spoke_id ) );
                $hub_posts = get_posts( array(
                    'post_type'   => array( 'page', 'post' ),
                    'post_status' => 'publish',
                    'numberposts' => 50,
                    'meta_query'  => array(
                        'relation' => 'OR',
                        array( 'key' => '_siloq_page_role', 'value' => 'hub',      'compare' => '=' ),
                        array( 'key' => '_siloq_page_role', 'value' => 'apex_hub', 'compare' => '=' ),
                        array( 'key' => 'page_type_classification', 'value' => 'hub', 'compare' => '=' ),
                    ),
                ) );

                $best_score = 0;
                foreach ( $hub_posts as $hp ) {
                    $hub_title_lower = strtolower( $hp->post_title );
                    $hub_words = array_filter( explode( ' ', preg_replace( '/[^a-z0-9 ]/', '', $hub_title_lower ) ) );
                    $score = 0;
                    foreach ( $hub_words as $w ) {
                        if ( strlen( $w ) > 2 && strpos( $spoke_title_lower, $w ) !== false ) {
                            $score += 2;
                        }
                    }
                    if ( $score > $best_score ) {
                        $best_score = $score;
                        $hub_id     = $hp->ID;
                    }
                }

                // Fallback: use first hub found
                if ( ! $hub_id && ! empty( $hub_posts ) ) {
                    $hub_id = $hub_posts[0]->ID;
                }
            }

            if ( $hub_id ) {
                update_post_meta( $spoke_id, '_siloq_expected_linker_id', $hub_id );
            }
        }

        if ( ! $hub_id ) {
            wp_send_json_success( array(
                'hub_id'     => 0,
                'suggestion' => 'We could not automatically determine which page should link here. Manually add a link from your most relevant hub page.',
            ) );
            return;
        }

        $hub_title    = get_the_title( $hub_id );
        $hub_edit_url = get_edit_post_link( $hub_id, 'raw' );

        wp_send_json_success( array(
            'hub_id'       => $hub_id,
            'hub_title'    => $hub_title,
            'hub_edit_url' => $hub_edit_url,
            'suggestion'   => 'Your "' . $hub_title . '" page should contain a link to this page. Add it in the section where you list your relevant content.',
        ) );
    }

    /**
     * AJAX: Auto-add internal link from hub to spoke using Anthropic API.
     */
    public static function ajax_auto_add_internal_link() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        $spoke_id = intval( $_POST['spoke_id'] ?? 0 );
        if ( ! $spoke_id ) {
            wp_send_json_error( array( 'message' => 'Missing spoke page ID.' ) );
        }

        $hub_id = intval( get_post_meta( $spoke_id, '_siloq_expected_linker_id', true ) );
        if ( ! $hub_id || ! get_post( $hub_id ) ) {
            wp_send_json_error( array( 'message' => 'Could not determine which page should link here. Use See Fix for manual guidance.' ) );
            return;
        }

        $hub_post      = get_post( $hub_id );
        $hub_title     = $hub_post->post_title;
        $hub_content   = $hub_post->post_content;
        $hub_edit_url  = get_edit_post_link( $hub_id, 'raw' );
        $spoke_title   = get_the_title( $spoke_id );
        $spoke_url     = get_permalink( $spoke_id );

        $api_key        = get_option( 'siloq_anthropic_api_key', '' );
        $settings_url   = admin_url( 'admin.php?page=siloq-settings&tab=ai' );
        $billing_url    = admin_url( 'admin.php?page=siloq-settings&tab=billing' );
        if ( empty( $api_key ) ) {
            wp_send_json_error( array(
                'no_api_key'   => true,
                'message'      => 'Auto-Add Link requires an AI key to work. You have two options: '
                    . '(1) <strong>Bring Your Own Key (BYOK):</strong> <a href="https://console.anthropic.com/" target="_blank">Get a free Claude API key at console.anthropic.com</a>, then add it in <a href="' . esc_url( $settings_url ) . '">Settings → AI Settings</a>. You\'ll be billed directly by Anthropic at their standard rates. '
                    . '(2) <strong>Let Siloq handle it:</strong> Enable Siloq-Managed AI in <a href="' . esc_url( $billing_url ) . '">your billing settings</a> — we cover the API cost and charge token cost + 5% transaction fee. '
                    . 'Or: <a href="' . esc_url( $hub_edit_url ) . '">Manually add a link on "' . esc_html( $hub_title ) . '" →</a>',
                'hub_edit_url' => $hub_edit_url,
                'hub_title'    => $hub_title,
            ) );
            return;
        }

        $prompt = 'The following page exists on this website: "' . $spoke_title . '" at ' . $spoke_url . ".\n"
            . 'It is a spoke/supporting page under the hub page titled "' . $hub_title . '".' . "\n"
            . "Here is the current hub page content:\n\n" . $hub_content . "\n\n"
            . 'Identify the most contextually appropriate location in the hub page content to naturally insert an internal link to the spoke page. '
            . 'Insert it as an HTML anchor tag: <a href="' . $spoke_url . '">' . $spoke_title . '</a>. '
            . 'Do not add the link in navigation elements, headers, or footers — place it in a contextually relevant sentence in the body content. '
            . 'Return ONLY the full updated hub page content with the link inserted. Do not add any explanation.';

        $resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
            'timeout' => 90,
            'headers' => array(
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'      => 'claude-haiku-4-5',
                'max_tokens' => 4000,
                'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
            ) ),
        ) );

        if ( is_wp_error( $resp ) ) {
            wp_send_json_error( array( 'message' => 'API error: ' . $resp->get_error_message() ) );
            return;
        }

        $code = wp_remote_retrieve_response_code( $resp );
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );

        if ( $code !== 200 || ! isset( $body['content'][0]['text'] ) ) {
            $err = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown API error (HTTP ' . $code . ')';
            wp_send_json_error( array( 'message' => 'API error: ' . $err ) );
            return;
        }

        $updated_content = $body['content'][0]['text'];
        wp_update_post( array( 'ID' => $hub_id, 'post_content' => $updated_content ) );

        wp_send_json_success( array(
            'message'      => '✅ Link added to "' . $hub_title . '"',
            'hub_edit_url' => $hub_edit_url,
        ) );
    }

    /* ═══════ DEPTH ENGINE AJAX HANDLERS ═══════ */

    public static function ajax_get_silos() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            wp_send_json_error( array( 'message' => 'Site not connected.' ) );
        }
        $api = new Siloq_API_Client();
        $res = $api->get( '/sites/' . $site_id . '/silos/' );
        if ( ! empty( $res['success'] ) ) {
            wp_send_json_success( $res['data'] );
        }
        wp_send_json_error( array( 'message' => $res['message'] ?? 'Failed to load silos.' ) );
    }

    /**
     * Get local hub pages as silos (no API call required).
     */
    public static function ajax_get_local_silos() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
            return;
        }

        $results = array();

        // ── Strategy A: WooCommerce product categories (ecommerce sites) ──────
        // WC categories are taxonomy terms — never in wp_posts, never get _siloq_page_role.
        // Always check for WC first, regardless of whether page-based hubs exist.
        if ( class_exists( 'WooCommerce' ) ) {
            $wc_cats = get_terms( array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'parent'     => 0,
                'exclude'    => array( get_option( 'default_product_cat', 0 ) ),
                'orderby'    => 'name',
            ) );
            if ( ! is_wp_error( $wc_cats ) && ! empty( $wc_cats ) ) {
                foreach ( $wc_cats as $cat ) {
                    $cat_link    = get_term_link( $cat );
                    $child_terms = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $cat->term_id, 'hide_empty' => false, 'fields' => 'ids' ) );
                    $child_count = is_wp_error( $child_terms ) ? 0 : count( $child_terms );
                    $results[] = array(
                        'post_id'      => 'cat_' . $cat->term_id,
                        'title'        => $cat->name,
                        'url'          => is_wp_error( $cat_link ) ? '' : $cat_link,
                        'spoke_count'  => (int) $cat->count + $child_count,
                        'api_silo_id'  => get_term_meta( $cat->term_id, '_siloq_api_silo_id', true ) ?: null,
                        'last_scanned' => get_term_meta( $cat->term_id, '_siloq_depth_last_scanned', true ) ?: null,
                        'boundary'     => get_term_meta( $cat->term_id, '_siloq_topic_boundary', true ) ?: null,
                        'wc_term_id'   => $cat->term_id,
                    );
                }
                wp_send_json_success( $results );
                return;
            }
        }

        // ── Strategy B: Pages with explicit hub role ──────────────────────────
        $hubs = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'OR',
                array( 'key' => '_siloq_page_role', 'value' => 'hub', 'compare' => '=' ),
                array( 'key' => '_siloq_page_role', 'value' => 'apex_hub', 'compare' => '=' ),
            ),
        ) );

        // ── Strategy C: Auto-detect from page structure ───────────────────────
        if ( empty( $hubs ) ) {
            $all_pages = get_posts( array(
                'post_type' => 'page', 'post_status' => 'publish',
                'posts_per_page' => -1, 'post_parent' => 0,
            ) );
            foreach ( $all_pages as $pg ) {
                $children = get_posts( array(
                    'post_type' => 'page', 'post_status' => 'publish',
                    'post_parent' => $pg->ID, 'posts_per_page' => 1, 'fields' => 'ids',
                ) );
                if ( ! empty( $children ) ) {
                    update_post_meta( $pg->ID, '_siloq_page_role', 'hub' );
                    $hubs[] = $pg;
                }
            }
        }

        foreach ( $hubs as $hub ) {
            $spokes       = get_posts( array( 'post_parent' => $hub->ID, 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );
            $api_silo_id  = get_post_meta( $hub->ID, '_siloq_api_silo_id', true );
            $last_scanned = get_post_meta( $hub->ID, '_siloq_depth_last_scanned', true );
            $boundary     = get_post_meta( $hub->ID, '_siloq_topic_boundary', true );

            $results[] = array(
                'post_id'      => $hub->ID,
                'title'        => $hub->post_title,
                'url'          => get_permalink( $hub->ID ),
                'spoke_count'  => count( $spokes ),
                'api_silo_id'  => $api_silo_id ?: null,
                'last_scanned' => $last_scanned ?: null,
                'boundary'     => $boundary ?: null,
            );
        }

        wp_send_json_success( $results );
    }

    /**
     * Save topic boundary to API and locally.
     */
    public static function ajax_save_topic_boundary() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
            return;
        }

        // Support category-based hubs (post_id = "cat_123")
        $post_id_raw = sanitize_text_field( $_POST['post_id'] ?? '' );
        $is_cat = ( strpos( $post_id_raw, 'cat_' ) === 0 );
        $term_id = $is_cat ? intval( str_replace( 'cat_', '', $post_id_raw ) ) : 0;
        $post_id = $is_cat ? 0 : intval( $post_id_raw );

        $core_topic       = sanitize_text_field( $_POST['core_topic'] ?? '' );
        $adjacent_raw     = sanitize_text_field( $_POST['adjacent_topics'] ?? '' );
        $out_of_scope_raw = sanitize_text_field( $_POST['out_of_scope_topics'] ?? '' );
        $entity_type      = sanitize_text_field( $_POST['entity_type'] ?? 'local_business' );

        if ( ( ! $post_id && ! $term_id ) || ! $core_topic ) {
            wp_send_json_error( array( 'message' => 'Post ID and core topic required.' ) );
            return;
        }

        $adjacent     = array_values( array_filter( array_map( 'trim', explode( ',', $adjacent_raw ) ) ) );
        $out_of_scope = array_values( array_filter( array_map( 'trim', explode( ',', $out_of_scope_raw ) ) ) );

        $boundary = array(
            'core_topic'          => $core_topic,
            'adjacent_topics'     => $adjacent,
            'out_of_scope_topics' => $out_of_scope,
            'entity_type'         => $entity_type,
        );

        // Save locally — use term meta for category hubs, post meta for page hubs
        if ( $is_cat && $term_id ) {
            update_term_meta( $term_id, '_siloq_topic_boundary', $boundary );
        } else {
            update_post_meta( $post_id, '_siloq_topic_boundary', $boundary );
        }

        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            wp_send_json_error( array( 'message' => 'Site not connected to Siloq API.' ) );
            return;
        }

        // Get or create silo in API
        $api     = new Siloq_API_Client();
        // Get stored silo_id — from term meta for categories, post meta for pages
        if ( $is_cat && $term_id ) {
            $silo_id = get_term_meta( $term_id, '_siloq_api_silo_id', true );
            $hub_title = get_term( $term_id )->name ?? 'Category Hub';
            $hub_url   = get_term_link( $term_id );
            $hub_url   = is_wp_error( $hub_url ) ? '' : $hub_url;
        } else {
            $silo_id = get_post_meta( $post_id, '_siloq_api_silo_id', true );
            $hub_title = get_the_title( $post_id );
            $hub_url   = get_permalink( $post_id );
        }

        // Clear stale local-* placeholder IDs
        if ( ! empty( $silo_id ) && strpos( $silo_id, 'local-' ) === 0 ) {
            if ( $is_cat ) delete_term_meta( $term_id, '_siloq_api_silo_id' );
            else           delete_post_meta( $post_id, '_siloq_api_silo_id' );
            $silo_id = '';
        }

        if ( empty( $silo_id ) ) {
            // For WC category hubs, the API doesn't have a POST /silos/ create endpoint.
            // Save locally with a placeholder and skip API creation — silo will be linked on next sync.
            if ( $is_cat ) {
                $silo_id = 'local-cat-' . $term_id;
                update_term_meta( $term_id, '_siloq_api_silo_id', $silo_id );
            } else {
                $create_res = $api->post( '/sites/' . $site_id . '/silos/', array(
                    'name'         => $hub_title,
                    'hub_url'      => $hub_url,
                    'hub_page_url' => $hub_url,
                ) );
                if ( ! empty( $create_res['success'] ) && ! empty( $create_res['data']['id'] ) ) {
                    $silo_id = $create_res['data']['id'];
                    update_post_meta( $post_id, '_siloq_api_silo_id', $silo_id );
                } else {
                    $err_msg = isset( $create_res['message'] ) ? $create_res['message'] : 'Could not create silo in API.';
                    wp_send_json_error( array( 'message' => 'API error: ' . $err_msg . ' — Please ensure your site is connected and try again.' ) );
                    return;
                }
            }
        }

        // Save topic boundary to API (if we have a real UUID silo ID)
        if ( strpos( $silo_id, 'local-' ) !== 0 ) {
            $api->post( '/sites/' . $site_id . '/silos/' . $silo_id . '/topic-boundary', $boundary );
        }

        wp_send_json_success( array( 'silo_id' => $silo_id, 'boundary' => $boundary ) );
    }

    /**
     * Resolve silo_id from post_id if needed.
     */
    private static function resolve_silo_id() {
        $silo_id = sanitize_text_field( $_POST['silo_id'] ?? '' );
        if ( ! empty( $silo_id ) ) {
            return $silo_id;
        }

        $post_id_raw = sanitize_text_field( $_POST['post_id'] ?? '' );

        // Handle WooCommerce category-based hubs (post_id = "cat_123")
        if ( strpos( $post_id_raw, 'cat_' ) === 0 ) {
            $term_id = intval( str_replace( 'cat_', '', $post_id_raw ) );
            return get_term_meta( $term_id, '_siloq_api_silo_id', true );
        }

        $post_id = intval( $post_id_raw );
        if ( $post_id ) {
            $silo_id = get_post_meta( $post_id, '_siloq_api_silo_id', true );
        }
        return $silo_id;
    }

    public static function ajax_get_depth_scores() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }
        $site_id = get_option( 'siloq_site_id', '' );
        $silo_id = self::resolve_silo_id();
        if ( empty( $site_id ) || empty( $silo_id ) ) {
            wp_send_json_error( array( 'message' => 'Missing site or silo ID.' ) );
        }
        $api = new Siloq_API_Client();
        $res = $api->get( '/sites/' . $site_id . '/silos/' . $silo_id . '/depth-scores' );
        if ( ! empty( $res['success'] ) ) {
            wp_send_json_success( $res['data'] );
        }
        wp_send_json_error( array( 'message' => $res['message'] ?? 'Failed to load depth scores.' ) );
    }

    public static function ajax_get_gap_report() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }
        $site_id = get_option( 'siloq_site_id', '' );
        $silo_id = self::resolve_silo_id();
        if ( empty( $site_id ) || empty( $silo_id ) ) {
            wp_send_json_error( array( 'message' => 'Missing site or silo ID.' ) );
        }
        $api = new Siloq_API_Client();
        $res = $api->get( '/sites/' . $site_id . '/silos/' . $silo_id . '/gap-report' );
        if ( ! empty( $res['success'] ) ) {
            wp_send_json_success( $res['data'] );
        }
        wp_send_json_error( array( 'message' => $res['message'] ?? 'Failed to load gap report.' ) );
    }


    public static function ajax_run_blueprint_analysis() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
            return;
        }
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            wp_send_json_error( array( 'message' => 'Site not connected.' ) );
            return;
        }
        $api = new Siloq_API_Client();
        $res = $api->post( '/sites/' . $site_id . '/blueprint/' );
        if ( ! empty( $res['success'] ) ) {
            wp_send_json_success( $res['data'] );
            return;
        }
        wp_send_json_error( array( 'message' => isset( $res['message'] ) ? $res['message'] : 'Blueprint analysis failed.' ) );
    }
    public static function ajax_get_gsc_summary() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
            return;
        }
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            wp_send_json_error( array( 'message' => 'Site not connected.' ) );
            return;
        }
        $api = new Siloq_API_Client();
        $res = $api->get( '/sites/' . $site_id . '/gsc/summary' );
        if ( ! empty( $res['success'] ) ) {
            wp_send_json_success( $res['data'] );
            return;
        }
        wp_send_json_error( array( 'message' => isset( $res['message'] ) ? $res['message'] : 'Failed to load GSC data.' ) );
    }

    public static function ajax_run_depth_scan() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }
        $site_id = get_option( 'siloq_site_id', '' );
        $silo_id = self::resolve_silo_id();
        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( empty( $site_id ) || empty( $silo_id ) ) {
            wp_send_json_error( array( 'message' => 'Missing site or silo ID.' ) );
        }
        $api  = new Siloq_API_Client();
        $base = '/sites/' . $site_id . '/silos/' . $silo_id;

        // Step 1: Fire subtopic generation — API returns immediately (async background thread)
        $gen = $api->post( $base . '/generate-subtopic-map' );
        if ( empty( $gen['success'] ) ) {
            wp_send_json_error( array( 'message' => $gen['message'] ?? 'Failed to start subtopic generation.' ) );
        }

        // Step 2: Poll depth-scores — AI runs in background, check up to 8 times (10s apart)
        // WordPress AJAX has up to 300s execution limit, well above the AI call time.
        $scores_data = array();
        for ( $attempt = 0; $attempt < 8; $attempt++ ) {
            sleep( 10 );
            $scores = $api->get( $base . '/depth-scores' );
            if ( ! empty( $scores['success'] ) && ! empty( $scores['data'] ) ) {
                $sd = $scores['data']['semantic_density_score'] ?? 0;
                $tc = $scores['data']['topical_closure_score'] ?? 0;
                if ( $sd > 0 || $tc > 0 ) {
                    $scores_data = $scores['data'];
                    break;
                }
            }
        }

        if ( $post_id ) {
            update_post_meta( $post_id, '_siloq_depth_last_scanned', current_time( 'mysql' ) );
        }

        wp_send_json_success( array(
            'message' => 'Depth scan complete.',
            'scores'  => $scores_data,
        ) );
    }

    public static function ajax_add_to_plan() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }
        $site_id     = get_option( 'siloq_site_id', '' );
        $silo_id     = sanitize_text_field( $_POST['silo_id'] ?? '' );
        $subtopic_id = sanitize_text_field( $_POST['subtopic_id'] ?? '' );
        if ( empty( $site_id ) || empty( $silo_id ) || empty( $subtopic_id ) ) {
            wp_send_json_error( array( 'message' => 'Missing required IDs.' ) );
        }
        $api = new Siloq_API_Client();
        $res = $api->post( '/sites/' . $site_id . '/silos/' . $silo_id . '/subtopics/' . $subtopic_id . '/add-to-plan' );
        if ( ! empty( $res['success'] ) ) {
            wp_send_json_success( array( 'message' => 'Added to plan.' ) );
        }
        wp_send_json_error( array( 'message' => $res['message'] ?? 'Failed to add to plan.' ) );
    }

    /**
     * Bulk add multiple subtopics to plan.
     */
    public static function ajax_bulk_add_to_plan() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }
        $site_id      = get_option( 'siloq_site_id', '' );
        $silo_id      = sanitize_text_field( $_POST['silo_id'] ?? '' );
        $subtopic_ids = isset( $_POST['subtopic_ids'] ) ? array_map( 'sanitize_text_field', (array) $_POST['subtopic_ids'] ) : array();

        if ( empty( $site_id ) || empty( $silo_id ) || empty( $subtopic_ids ) ) {
            wp_send_json_error( array( 'message' => 'Missing required IDs.' ) );
        }

        $api     = new Siloq_API_Client();
        $added   = 0;
        $failed  = 0;
        foreach ( $subtopic_ids as $subtopic_id ) {
            $res = $api->post( '/sites/' . $site_id . '/silos/' . $silo_id . '/subtopics/' . $subtopic_id . '/add-to-plan' );
            if ( ! empty( $res['success'] ) ) {
                $added++;
            } else {
                $failed++;
            }
        }

        wp_send_json_success( array( 'message' => $added . ' added to plan.', 'added' => $added, 'failed' => $failed ) );
    }
}
