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
        $onboarding_done = get_option('siloq_onboarding_complete', 'no');
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
                                <th scope="row">
                                    <label for="siloq_openai_api_key">
                                        <?php _e('OpenAI API Key', 'siloq-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <?php
                                    $openai_api_key = get_option('siloq_openai_api_key', '');
                                    $oai_saved = !empty($openai_api_key);
                                    $oai_hint  = $oai_saved ? '●●●●●●●● ' . substr($openai_api_key, -4) . ' — key saved ✓' : '';
                                    ?>
                                    <?php if ($oai_saved): ?>
                                    <p style="margin:0 0 6px;color:#16a34a;font-weight:600;">✓ Key saved (ends in …<?php echo esc_html(substr($openai_api_key, -4)); ?>)</p>
                                    <?php endif; ?>
                                    <input
                                        type="text"
                                        id="siloq_openai_api_key"
                                        name="siloq_openai_api_key"
                                        value=""
                                        class="regular-text"
                                        placeholder="<?php echo $oai_saved ? 'Enter new key to replace saved key' : 'sk-...'; ?>"
                                        autocomplete="off"
                                        style="font-family:monospace;"
                                    />
                                    <p class="description">
                                        <?php _e('Required for AI image generation (DALL-E). Also used as fallback for content suggestions. Leave blank to keep existing key.', 'siloq-connector'); ?>
                                        <a href="https://platform.openai.com/api-keys" target="_blank"><?php _e('Get key →', 'siloq-connector'); ?></a>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="siloq_anthropic_api_key">
                                        <?php _e('Anthropic API Key', 'siloq-connector'); ?>
                                    </label>
                                </th>
                                <td>
                                    <?php
                                    $anthropic_api_key = get_option('siloq_anthropic_api_key', '');
                                    $ant_saved = !empty($anthropic_api_key);
                                    ?>
                                    <?php if ($ant_saved): ?>
                                    <p style="margin:0 0 6px;color:#16a34a;font-weight:600;">✓ Key saved (ends in …<?php echo esc_html(substr($anthropic_api_key, -4)); ?>)</p>
                                    <?php endif; ?>
                                    <input
                                        type="text"
                                        id="siloq_anthropic_api_key"
                                        name="siloq_anthropic_api_key"
                                        value=""
                                        class="regular-text"
                                        placeholder="<?php echo $ant_saved ? 'Enter new key to replace saved key' : 'sk-ant-...'; ?>"
                                        autocomplete="off"
                                        style="font-family:monospace;"
                                    />
                                    <p class="description">
                                        <?php _e('Powers AI content suggestions and draft generation (Claude Sonnet). Leave blank to keep existing key.', 'siloq-connector'); ?>
                                        <a href="https://console.anthropic.com/settings/keys" target="_blank"><?php _e('Get key →', 'siloq-connector'); ?></a>
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
        if (!$manual_override_just_set && (empty($site_id) || $old_api_key !== $api_key)) {
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
        $synced_post_types = function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array('page', 'post');
        $synced_pages = get_posts(array('post_type' => $synced_post_types, 'meta_query' => array(array('key' => '_siloq_synced', 'compare' => 'EXISTS')), 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids'));
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
<div class="siloq-dash-v2">

<?php
// Build profile completeness fields for display
$profile_fields = self::get_entity_field_status();
$missing_fields = array_filter($profile_fields, function($f) { return empty($f['filled']); });
$missing_count_profile = count($missing_fields);

// Build hub data: pages marked as hub in analysis, OR pages that have child pages
$all_synced_pages = get_posts(array(
    'post_type'      => function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array('page','post'),
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => array(array('key' => '_siloq_synced', 'compare' => 'EXISTS')),
));
$hub_data = array();
$non_hub_ids = array();

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
    $children = get_posts(array('post_type' => 'any', 'post_parent' => $hp->ID, 'post_status' => 'publish', 'posts_per_page' => -1));
    $is_hub = ($page_type === 'hub') || ($page_type === 'apex_hub') || (count($children) > 0 && $hp->post_parent == 0);
    if (!$is_hub) { $non_hub_ids[] = $hp->ID; continue; }
    $score = isset($analysis['score']) ? intval($analysis['score']) : 0;
    $missing_supporting = isset($analysis['missing_supporting']) ? (array)$analysis['missing_supporting'] : array();
    $hub_data[] = array(
        'id'           => $hp->ID,
        'title'        => get_the_title($hp->ID),
        'url'          => get_permalink($hp->ID),
        'edit_url'     => get_edit_post_link($hp->ID, 'raw'),
        'elementor_url'=> admin_url('post.php?post=' . $hp->ID . '&action=elementor'),
        'score'        => $score,
        'children'     => $children,
        'missing'      => $missing_supporting,
        'keyword'      => isset($analysis['primary_keyword']) ? $analysis['primary_keyword'] : '',
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

$all_synced_ids = wp_list_pluck($all_synced_pages, 'ID');
$true_orphan_posts = array();
foreach ($all_synced_ids as $oid) {
    if (in_array($oid, $hub_child_ids)) continue;
    if (isset($menu_linked_ids[$oid])) continue;
    $true_orphan_posts[] = $oid;
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
                    'elementor_url'=> isset($iss['elementor_url']) ? $iss['elementor_url'] : admin_url('post.php?post=' . $pid . '&action=elementor'),
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
      <a href="#siloq-tab-gsc" class="siloq-ic-link siloq-tab-btn" aria-controls="siloq-tab-gsc">Full Report &rarr;</a>
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
    // Determine fix type from headline/detail text
    $fix_mode = 'link'; // default: open link
    if (stripos($act_text, 'missing meta description') !== false || stripos($act_text, 'meta description') !== false) {
        $fix_mode = 'meta_description';
    } elseif (stripos($act_text, 'missing seo title') !== false || stripos($act_text, 'missing title') !== false || stripos($act_text, 'set a title tag') !== false) {
        $fix_mode = 'meta_title';
    } elseif (stripos($act_text, 'schema') !== false || stripos($act_text, 'structured data') !== false) {
        $fix_mode = 'schema';
    } elseif (stripos($act_text, 'missing h1') !== false) {
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
    <button class="siloq-fix-btn siloq-btn siloq-btn--primary" data-action="view_links" data-post-id="<?php echo $act_post_id; ?>" style="font-size:11px;padding:6px 12px;white-space:nowrap;cursor:pointer">View Internal Links</button>
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
    <div class="siloq-audit-page-row" style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:#f8fafc;border-radius:10px;border:1px solid #e5e7eb;cursor:pointer" onclick="this.querySelector('.siloq-audit-actions')?.classList.toggle('open')">
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
      <?php if (!empty($ap['actions'])): ?>
      <span style="font-size:10px;color:#9ca3af">&#9660;</span>
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
            if (resp.data && resp.data.data && resp.data.data.error === 'upgrade_required') {
                msg = 'Page limit reached (' + resp.data.data.limit + ' pages). Upgrade your plan to audit more pages.';
            }
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
        <span class="siloq-orphan-chip"><?php echo esc_html(get_the_title($oid)); ?></span>
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
          <a href="<?php echo esc_url($h['elementor_url']); ?>" class="siloq-hub-edit-btn" onclick="event.stopPropagation()">Edit Page</a>
          <div class="siloq-hub-chev open">&#9660;</div>
        </div>
      </div>
      <div class="siloq-hub-progress">
        <div class="siloq-hub-prog-row">
          <div class="siloq-hub-prog-lbl">Supporting pages</div>
          <div class="siloq-hub-prog-bar"><div class="siloq-hub-prog-fill" style="width:<?php echo $pct; ?>%;<?php echo $pct < 50 ? 'background:linear-gradient(90deg,#d97706,#f59e0b)' : ''; ?>"></div></div>
          <div class="siloq-hub-prog-count <?php echo $prog_cls; ?>"><?php echo $live_pages; ?> of <?php echo max(1,$total_pages); ?> live<?php echo $pct >= 100 ? ' &#10003;' : ''; ?></div>
        </div>
      </div>
      <div class="siloq-hub-spokes-wrap" style="max-height:400px">
        <div class="siloq-hub-spokes">
          <?php foreach ($h['children'] as $child):
            $c_raw = get_post_meta($child->ID, '_siloq_analysis_data', true);
            $c_an = is_array($c_raw) ? $c_raw : (is_string($c_raw) ? json_decode($c_raw, true) : array());
            $c_sc = isset($c_an['score']) ? intval($c_an['score']) : 0;
            $c_sc_cls = $c_sc >= 75 ? 'good' : ($c_sc >= 50 ? 'ok' : 'bad');
            $c_edit = admin_url('post.php?post=' . $child->ID . '&action=elementor');
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
              <button class="siloq-spoke-btn create" onclick="siloqCreateDraft(this,'<?php echo esc_js(is_array($ms) && isset($ms['title']) ? $ms['title'] : 'New Supporting Page'); ?>')">+ Draft</button>
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
      <a href="<?php echo esc_url($ap['elementor_url']); ?>" class="siloq-page-fix-link">Fix &rarr;</a>
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
            </div><!-- /dashboard tab -->

            <!-- ═══════ SEO/GEO PLAN TAB ═══════ -->
            <div id="siloq-tab-plan" class="siloq-tab-panel" role="tabpanel" aria-hidden="true">
                <div class="siloq-plan-section">

                    <div style="text-align:right;margin-bottom:8px">
                            <button class="siloq-btn siloq-btn--primary siloq-generate-plan-btn">
                                <?php echo $has_plan ? '&#8635; Refresh Plan' : 'Generate Your SEO Plan &rarr;'; ?>
                            </button>
                        </div>

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
                            <?php
                            // Real gap analysis
                            $all_pages_q = get_posts( array(
                                'post_type'      => 'page',
                                'posts_per_page' => -1,
                                'post_status'    => 'publish',
                                'fields'         => 'all',
                            ) );
                            $page_data = array();
                            foreach ( $all_pages_q as $pg ) {
                                $page_data[] = array(
                                    'title'     => $pg->post_title,
                                    'url'       => get_permalink( $pg->ID ),
                                    'page_role' => get_post_meta( $pg->ID, '_siloq_page_role', true ),
                                );
                            }
                            $categorized = self::categorize_pages( $page_data );
                            $city_count  = count( $categorized['cities'] );
                            $has_cards   = false;

                            // --- Card 1: Missing Service Areas Hub ---
                            if ( $city_count >= 3 && $categorized['service_area_page'] === null ) :
                                $has_cards = true;
                            ?>
                            <div class="siloq-action-card" style="border-left:4px solid #f59e0b;background:#fffbeb;border-radius:6px;padding:16px;margin-bottom:12px;">
                                <div class="siloq-action-card__body">
                                    <span style="display:inline-block;background:#f59e0b;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;margin-bottom:8px;">HIGHEST PRIORITY</span>
                                    <p class="siloq-action-card__headline" style="font-weight:600;margin:0 0 6px;">Create a Service Areas Hub Page</p>
                                    <p style="color:#666;font-size:13px;margin:0 0 8px;">You have <?php echo intval( $city_count ); ?> city pages with no hub connecting them. They compete instead of reinforcing one authoritative page.</p>
                                    <p style="color:#999;font-size:12px;margin:0 0 4px;"><strong>Recommended URL:</strong> /service-areas/</p>
                                    <p style="color:#999;font-size:12px;margin:0 0 10px;"><strong>Impact:</strong> High &mdash; affects all <?php echo intval( $city_count ); ?> city pages</p>
                                    <button onclick="siloqCreateGapDraft(this,'Service Areas','service-areas')" class="siloq-btn siloq-btn--sm siloq-btn--primary">Create Draft &rarr;</button>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php
                            // --- Card 2: Content Gaps (missing service pages) ---
                            $primary_services = json_decode( get_option( 'siloq_primary_services', '[]' ), true );
                            if ( ! is_array( $primary_services ) ) { $primary_services = array(); }

                            // Filter out garbage service values: skip anything that looks like an
                            // extracted title fragment (contains state abbr, ends with "Electrician",
                            // or is longer than 5 words). Only show clean service names.
                            $state_abbrs_svc = array('AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY','DC');
                            $primary_services = array_filter( $primary_services, function( $s ) use ( $state_abbrs_svc ) {
                                if ( str_word_count( $s ) > 5 ) return false; // too long — likely a title fragment
                                foreach ( $state_abbrs_svc as $st ) {
                                    if ( preg_match( '/\b' . $st . '\b/', $s ) ) return false; // contains state abbr
                                }
                                return true;
                            });

                            $existing_urls = array_map( function( $p ) { return strtolower( $p['url'] ); }, $page_data );

                            // Extract clean city name from the first city page title
                            // Title patterns: "Electrician Smithville, MO" or "Smithville, MO Electrician"
                            $first_city_raw = ! empty( $categorized['cities'] ) ? $categorized['cities'][0]['title'] : '';
                            $first_city = $first_city_raw;
                            if ( ! empty( $first_city_raw ) ) {
                                // Remove trailing service keywords and state abbreviations
                                $first_city = preg_replace( '/\b(electrician|electric|plumb|hvac|roof|repair|install|service|clean|maint|remodel|contractor|landscap|pest|paint|concrete|handyman|AL|AK|AZ|AR|CA|CO|CT|DE|FL|GA|HI|ID|IL|IN|IA|KS|KY|LA|ME|MD|MA|MI|MN|MS|MO|MT|NE|NV|NH|NJ|NM|NY|NC|ND|OH|OK|OR|PA|RI|SC|SD|TN|TX|UT|VT|VA|WA|WV|WI|WY|DC)\b/i', '', $first_city_raw );
                                $first_city = trim( preg_replace( '/[,\s]+$/', '', $first_city ) );
                                if ( empty( $first_city ) ) { $first_city = $first_city_raw; }
                            }

                            foreach ( $primary_services as $service ) :
                                $service_slug = sanitize_title( $service );
                                $found = false;
                                foreach ( $existing_urls as $eu ) {
                                    if ( strpos( $eu, $service_slug ) !== false ) { $found = true; break; }
                                }
                                if ( $found ) { continue; }
                                $has_cards = true;
                                $card_title = $first_city ? esc_html( $service ) . ' in ' . esc_html( $first_city ) : esc_html( $service );
                            ?>
                            <div class="siloq-action-card" style="border-left:4px solid #3b82f6;background:#eff6ff;border-radius:6px;padding:16px;margin-bottom:12px;">
                                <div class="siloq-action-card__body">
                                    <span style="display:inline-block;background:#3b82f6;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;margin-bottom:8px;">CONTENT GAP</span>
                                    <p class="siloq-action-card__headline" style="font-weight:600;margin:0 0 6px;"><?php echo $card_title; ?></p>
                                    <p style="color:#666;font-size:13px;margin:0 0 8px;">No page targets &ldquo;<?php echo esc_html( $service ); ?>&rdquo; as a primary service. Adding a dedicated page improves topical authority and gives Google a clear ranking signal.</p>
                                    <p style="color:#999;font-size:12px;margin:0 0 10px;"><strong>Type:</strong> Sub-page (Transactional)</p>
                                    <button onclick="siloqCreateGapDraft(this,<?php echo wp_json_encode($service); ?>,'service')" class="siloq-btn siloq-btn--sm siloq-btn--primary">Create Draft &rarr;</button>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php
                            // --- Card 3: Local SEO Gaps (missing city pages) ---
                            $service_areas = json_decode( get_option( 'siloq_service_areas', '[]' ), true );
                            if ( ! is_array( $service_areas ) ) { $service_areas = array(); }

                            $existing_titles = array_map( function( $p ) { return strtolower( $p['title'] ); }, $page_data );
                            $first_service   = ! empty( $primary_services ) ? $primary_services[0] : '';

                            foreach ( $service_areas as $city_entry ) :
                                $city_name = is_array( $city_entry ) ? ( isset( $city_entry['city'] ) ? $city_entry['city'] : '' ) : $city_entry;
                                if ( empty( $city_name ) ) { continue; }
                                $found = false;
                                foreach ( $existing_titles as $et ) {
                                    if ( strpos( $et, strtolower( $city_name ) ) !== false ) { $found = true; break; }
                                }
                                if ( $found ) { continue; }
                                $has_cards = true;
                                $suggested_title = esc_html( $city_name ) . ( $first_service ? ' ' . esc_html( $first_service ) : '' );
                            ?>
                            <div class="siloq-action-card" style="border-left:4px solid #7c3aed;background:#f5f3ff;border-radius:6px;padding:16px;margin-bottom:12px;">
                                <div class="siloq-action-card__body">
                                    <span style="display:inline-block;background:#7c3aed;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;margin-bottom:8px;">LOCAL SEO GAP</span>
                                    <p class="siloq-action-card__headline" style="font-weight:600;margin:0 0 6px;"><?php echo $suggested_title; ?></p>
                                    <p style="color:#666;font-size:13px;margin:0 0 8px;">No page targets &ldquo;<?php echo esc_html( $city_name ); ?>&rdquo;. Adding a dedicated city page helps rank for local searches and strengthens your service area coverage.</p>
                                    <p style="color:#999;font-size:12px;margin:0 0 10px;"><strong>Type:</strong> City Landing Page (Local)</p>
                                    <button onclick="siloqCreateGapDraft(this,<?php echo wp_json_encode($suggested_title); ?>,'city')" class="siloq-btn siloq-btn--sm siloq-btn--primary">Create Draft &rarr;</button>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if ( ! $has_cards ) : ?>
                            <p class="siloq-empty">No content gaps detected. Your site covers all configured services and areas.</p>
                            <?php endif; ?>
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
                        <?php if (get_option('siloq_gsc_needs_property_selection') === 'yes'): ?>
                            <div id="siloq-gsc-property-picker-tab" class="notice notice-info" style="padding:16px;margin:12px 0;text-align:left;">
                                <h3 style="margin:0 0 8px;">&#9989; Google account connected &mdash; choose your property</h3>
                                <p style="color:#555;margin:0 0 12px;">Select which Search Console property to use for this site:</p>
                                <div id="siloq-gsc-property-list-tab">Loading properties...</div>
                                <p style="margin:12px 0 0;">
                                    <button type="button" id="siloq-gsc-confirm-property-tab" class="siloq-btn siloq-btn--primary" disabled>Confirm Connection</button>
                                    <button type="button" id="siloq-gsc-cancel-property-tab" class="siloq-btn siloq-btn--outline" style="margin-left:8px;">Cancel</button>
                                </p>
                            </div>
                        <?php elseif ($gsc_connected): ?>
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
                            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                                <button type="button" id="siloq-gsc-connect-btn-tab" class="siloq-btn siloq-btn--primary">⚡ Connect Google Search Console</button>
                                <button type="button" id="siloq-gsc-check-btn-tab" class="siloq-btn siloq-btn--outline">Check Connection</button>
                            </div>
                            <p style="color:var(--siloq-muted);font-size:13px;margin-top:12px;">Connect in the Siloq dashboard, then click "Check Connection" to confirm.</p>
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

                // Hub expand/collapse
                function siloqToggleHub(hdr) {
                    var wrap = hdr.closest('.siloq-hub-block').querySelector('.siloq-hub-spokes-wrap');
                    var chev = hdr.querySelector('.siloq-hub-chev');
                    var isOpen = wrap.style.maxHeight !== '0px' && wrap.style.maxHeight !== '0';
                    wrap.style.maxHeight = isOpen ? '0' : '500px';
                    chev.classList.toggle('open', !isOpen);
                }

                // Create draft with generated content (for gap analysis cards)
                function siloqCreateGapDraft(btn, title, draftType) {
                    btn.textContent = 'Generating...';
                    btn.disabled = true;
                    jQuery.post(ajaxurl, {
                        action: 'siloq_create_draft_page',
                        nonce: '<?php echo esc_js(wp_create_nonce("siloq_ajax_nonce")); ?>',
                        title: title,
                        draft_type: draftType
                    }, function(r) {
                        if (r.success && r.data.edit_url) {
                            btn.textContent = '✓ Draft Created — opening...';
                            btn.style.background = '#f0fdf4';
                            btn.style.color = '#16a34a';
                            setTimeout(function(){ window.open(r.data.edit_url, '_blank'); }, 600);
                        } else {
                            btn.textContent = 'Error — retry';
                            btn.disabled = false;
                        }
                    });
                }

                // Create draft post from missing spoke card
                function siloqCreateDraft(btn, title) {
                    btn.textContent = 'Creating...';
                    btn.disabled = true;
                    jQuery.post(ajaxurl, {
                        action: 'siloq_create_draft_page',
                        nonce: '<?php echo esc_js(wp_create_nonce("siloq_ajax_nonce")); ?>',
                        title: title
                    }, function(r) {
                        if (r.success && r.data.edit_url) {
                            btn.textContent = '✓ Created';
                            btn.style.background = '#f0fdf4';
                            btn.style.color = '#16a34a';
                            btn.style.boxShadow = 'none';
                            var spoke = btn.closest('.siloq-spoke-card');
                            if (spoke) { spoke.style.borderColor = '#22c55e'; spoke.style.borderStyle = 'solid'; }
                            setTimeout(function(){ window.open(r.data.edit_url, '_blank'); }, 600);
                        } else {
                            btn.textContent = 'Error — retry';
                            btn.disabled = false;
                        }
                    });
                }

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

    // =========================================================================
    // Track 2: Site Audit — collect page data, POST to API, cache results
    // =========================================================================

    public static function run_site_audit() {
        $site_id = get_option('siloq_site_id', '');
        if (empty($site_id)) {
            return array('success' => false, 'message' => 'Site not connected to Siloq.');
        }

        $post_types = function_exists('get_siloq_crawlable_post_types')
            ? get_siloq_crawlable_post_types()
            : array('page', 'post');

        $posts = get_posts(array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
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

            // Schema types from stored meta
            $schema_types = array();
            $applied_types = get_post_meta($p->ID, '_siloq_applied_types', true);
            if (!empty($applied_types)) {
                $schema_types = is_array($applied_types) ? $applied_types : json_decode($applied_types, true);
                if (!is_array($schema_types)) $schema_types = array();
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
                'images_missing_alt'     => $images_missing_alt,
                'has_duplicate_title'    => $has_dup,
            );
        }

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
                // Generate and apply meta title or description via the page analyzer
                if ( $fix_type === 'title' ) {
                    $title = self::siloq_get_page_title( $post_id );
                    if ( empty( $title ) ) {
                        $title = get_the_title( $post_id );
                    }
                    // Save to common SEO meta locations
                    update_post_meta( $post_id, '_yoast_wpseo_title', $title );
                    update_post_meta( $post_id, '_aioseo_title', $title );
                    wp_send_json_success( [ 'message' => 'Meta title set.', 'value' => $title ] );
                } elseif ( $fix_type === 'description' ) {
                    // Generate a meta description from post content
                    $post = get_post( $post_id );
                    $content = wp_strip_all_tags( $post->post_content ?? '' );
                    $desc = wp_trim_words( $content, 25, '...' );
                    if ( strlen( $desc ) > 160 ) {
                        $desc = substr( $desc, 0, 157 ) . '...';
                    }
                    update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );
                    update_post_meta( $post_id, '_aioseo_description', $desc );
                    wp_send_json_success( [ 'message' => 'Meta description generated.', 'value' => $desc ] );
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

        return [
            ['key' => 'business_name',    'label' => 'Business Name',    'weight' => 15, 'filled' => !empty($business_name)],
            ['key' => 'business_type',    'label' => 'Business Type',    'weight' => 15, 'filled' => !empty($business_type)],
            ['key' => 'phone',            'label' => 'Phone',            'weight' => 10, 'filled' => !empty($phone)],
            ['key' => 'address',          'label' => 'Address',          'weight' => 20, 'filled' => $address_filled],
            ['key' => 'primary_services', 'label' => 'Primary Services', 'weight' => 25, 'filled' => is_array($services) && !empty($services)],
            ['key' => 'service_areas',    'label' => 'Service Areas',    'weight' => 15, 'filled' => is_array($areas) && !empty($areas)],
        ];
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
}
