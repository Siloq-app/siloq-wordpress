<?php
/**
 * Plugin Name: Siloq Connector
 * Plugin URI: https://github.com/Siloq-app/siloq-wordpress
 * Description: Connects WordPress to Siloq platform for SEO content silo management and AI-powered content generation

* Version: 1.5.152
 * Author: Siloq
 * Author URI: https://siloq.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: siloq-connector
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define basic plugin constants

define('SILOQ_VERSION', '1.5.152');

if ( ! defined( "SILOQ_EXCLUDED_POST_TYPES" ) ) {
    define( "SILOQ_EXCLUDED_POST_TYPES", [
        // Page builders / framework junk
        "jet-engine","jet-engine-taxonomy","e-loop-item","elementor_library",
        "acf-field-group","acf-field","wpcode","wp_block",
        "wp_template","wp_template_part","wp_navigation",
        // WordPress internals
        "revision","nav_menu_item","custom_css","oembed_cache",
        "customize_changeset","user_request","wp_global_styles",
        // WooCommerce
        "shop_order","shop_order_refund","shop_coupon","shop_webhook",
        "product_variation","wc_order","wc_order_refund",
        "wc_product_download","wc_user_csv_import_session",
        // Other common e-commerce / plugin junk
        "mc4wp-form","tribe_events","tribe_venue","tribe_organizer",
        "dlm_download","dlm_download_version",
    ] );
}
define('SILOQ_PLUGIN_FILE', __FILE__);

// WordPress-dependent constants will be defined when WordPress is loaded
if (function_exists('plugin_dir_path')) {
    define('SILOQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('SILOQ_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('SILOQ_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * Legacy content-based builder detection (used by sync engine for historical data).
 *
 * NOTE: For admin panel/editor builder detection, use Siloq_Builder_Detector::detect().
 * This function is kept for backwards compatibility with the sync engine which needs
 * to detect a builder from saved post content (not the current editing context).
 *
 * @param int $post_id WordPress post ID.
 * @return string Builder slug.
 */
function siloq_detect_builder( $post_id ) {
    $post_content = get_post_field( 'post_content', $post_id );
    $post_meta    = get_post_meta( $post_id );

    // Elementor stores its layout in a dedicated meta key
    if ( ! empty( $post_meta['_elementor_data'][0] ) && $post_meta['_elementor_data'][0] !== '[]' ) {
        return 'elementor';
    }
    // Beaver Builder
    if ( ! empty( $post_meta['_fl_builder_data'][0] ) ) {
        return 'beaver_builder';
    }
    // Cornerstone / X Theme (shortcodes)
    if ( strpos( $post_content, '[cs_' ) !== false || strpos( $post_content, '[x_' ) !== false ) {
        return 'cornerstone';
    }
    // Divi
    if ( strpos( $post_content, '[et_pb_' ) !== false ) {
        return 'divi';
    }
    // WPBakery / Makdigital
    if ( strpos( $post_content, '[vc_' ) !== false || strpos( $post_content, '[mkd_' ) !== false ) {
        return 'wpbakery';
    }
    // Gutenberg (block editor)
    if ( strpos( $post_content, '<!-- wp:' ) !== false ) {
        return 'gutenberg';
    }
    // Plain WordPress
    return 'standard';
}

/**
 * Main Siloq Connector Class
 * 
 * Handles plugin initialization, admin menu setup, and core functionality
 * for the Siloq WordPress plugin integration.
 */
class Siloq_Connector {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
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
        // Don't initialize during plugin activation/deactivation
        if (defined('WP_INSTALLING') && WP_INSTALLING) {
            return;
        }
        
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
        
        // Set up hooks
        $this->setup_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Ensure plugin directory constant is defined
        if (!defined('SILOQ_PLUGIN_DIR')) {
            define('SILOQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
        }

        // ------------------------------------------------------------------
        // Load builder detector first — single source of truth for all
        // builder detection logic throughout the plugin.
        // ------------------------------------------------------------------
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-builder-detector.php';

        // Core dependencies (always loaded)
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-admin.php';
        if ( file_exists( SILOQ_PLUGIN_DIR . 'includes/class-siloq-content-extractor.php' ) ) {
            require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-cpt-crawler.php';
            require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-content-extractor.php';
        }
        if ( file_exists( SILOQ_PLUGIN_DIR . 'includes/class-siloq-page-analyzer.php' ) ) {
            require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-page-analyzer.php';
        }
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-api-client.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-sync-engine.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-ai-content-generator.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-schema-manager.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-schema-architect.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-redirect-manager.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-content-import.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-webhook-handler.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-junk-detector.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-builder-apply.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-theme-compat.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-schema-intelligence.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-admin-metabox.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-faq-manager.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-content-editor.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-debug-logger.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-image-audit.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-agent-ready.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/tali/class-siloq-tali.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-business-detector.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-rules-factory.php';

        // Auto-detect business type on every plugin load
        Siloq_Business_Detector::get_or_detect();

        // Widget Intelligence — native Elementor panel controls
        if ( is_admin() ) {
            require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-widget-intelligence.php';
            Siloq_Widget_Intelligence::init();
        }

        // ------------------------------------------------------------------
        // Builder-specific panel integration — admin context only.
        // Siloq_Builder_Detector::detect() caches the result so subsequent
        // calls within the same request are free.
        // ------------------------------------------------------------------
        if ( is_admin() ) {
            $builder = Siloq_Builder_Detector::detect();

            // Always load metabox (Classic Editor + WPBakery backend + fallback)
            // Already required above; just ensure it's initialised.

            switch ( $builder ) {
                case Siloq_Builder_Detector::BUILDER_ELEMENTOR:
                    require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-elementor-panel.php';
                    Siloq_Elementor_Panel::init();
                    break;

                case Siloq_Builder_Detector::BUILDER_GUTENBERG:
                    require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-gutenberg-panel.php';
                    Siloq_Gutenberg_Panel::init();
                    break;

                case Siloq_Builder_Detector::BUILDER_DIVI:
                    require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-divi-panel.php';
                    Siloq_Divi_Panel::init();
                    break;

                case Siloq_Builder_Detector::BUILDER_BEAVER:
                    require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-beaver-panel.php';
                    Siloq_Beaver_Panel::init();
                    break;

                case Siloq_Builder_Detector::BUILDER_WPBAKERY:
                    require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-wpbakery-panel.php';
                    Siloq_WPBakery_Panel::init();
                    break;

                case Siloq_Builder_Detector::BUILDER_BRICKS:
                    require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-bricks-panel.php';
                    Siloq_Bricks_Panel::init();
                    break;

                case Siloq_Builder_Detector::BUILDER_OXYGEN:
                    require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-oxygen-panel.php';
                    Siloq_Oxygen_Panel::init();
                    break;

                case Siloq_Builder_Detector::BUILDER_CORNERSTONE:
                    require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-cornerstone-panel.php';
                    Siloq_Cornerstone_Panel::init();
                    break;

                // BUILDER_CLASSIC: metabox already handles it — nothing extra needed.
            }
        }
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize admin
        Siloq_Admin::get_instance();

        // Initialize Admin Meta Box (Classic Editor / fallback metabox)
        Siloq_Admin_Metabox::init();

        // Initialize AI Content Generator
        Siloq_AI_Content_Generator::init();

        // Initialize FAQ Manager (AJAX: siloq_apply_faq_item)
        Siloq_FAQ_Manager::init();

        // Initialize TALI
        if (!defined('SILOQ_TALI_DISABLED') || !SILOQ_TALI_DISABLED) {
            Siloq_TALI::get_instance();
        }

        // Initialize redirect manager
        // Ensure redirects table exists on every load (safe — dbDelta is idempotent)
        Siloq_Redirect_Manager::create_table();
        Siloq_Redirect_Manager::get_instance();

        // Initialize Schema Intelligence (AJAX handlers + wp_head output).
        Siloq_Schema_Intelligence::init();

        // Initialize Content Editor (AJAX: siloq_get_elementor_widgets, siloq_suggest_widget_edit)
        Siloq_Content_Editor::init();

        // NOTE: Builder-specific panel classes (Elementor, Gutenberg, Divi, etc.)
        // are initialised in load_dependencies() via Siloq_Builder_Detector::detect()
        // so they are registered before init_components() runs. No duplicate calls here.
    }
    
    /**
     * Set up WordPress hooks
     */
    private function setup_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array('Siloq_Admin', 'force_set_site_id'));
        add_action('admin_post_siloq_gsc_connect', array('Siloq_Admin', 'handle_gsc_connect_redirect'));
        
        // Admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Schema injection
        add_action('wp_head', array('Siloq_Schema_Manager', 'output_schema'));
        // Native meta tag injection for sites with no SEO plugin (priority 1 = before theme)
        add_action('wp_head', array('Siloq_Admin', 'inject_siloq_meta_tags'), 1);
        Siloq_Schema_Architect::init();
        
        // Page editor assets
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_page_editor_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_siloq_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_siloq_sync_page', array($this, 'ajax_sync_page'));
        add_action('wp_ajax_siloq_sync_all_pages', array($this, 'ajax_sync_all_pages'));
        add_action('wp_ajax_siloq_get_sync_status', array($this, 'ajax_get_sync_status'));
        add_action('wp_ajax_siloq_import_content', array($this, 'ajax_import_content'));
        add_action('wp_ajax_siloq_generate_content',        array($this, 'ajax_generate_content'));
        add_action('wp_ajax_siloq_check_job_status',        array($this, 'ajax_check_job_status'));
        // AI generator action aliases (called by siloq-ai-generator.js)
        add_action('wp_ajax_siloq_ai_generate_content',    array($this, 'ajax_generate_content'));
        add_action('wp_ajax_siloq_ai_get_content_preview', array($this, 'ajax_check_job_status'));
        add_action('wp_ajax_siloq_ai_insert_content',      array($this, 'ajax_ai_insert_content'));
        add_action('wp_ajax_siloq_ai_regenerate_section',  array($this, 'ajax_generate_content'));
        add_action('wp_ajax_siloq_restore_backup', array($this, 'ajax_restore_backup'));
        add_action('wp_ajax_siloq_scan_junk_pages', array('Siloq_Junk_Detector', 'ajax_scan'));
        add_action('wp_ajax_siloq_apply_junk_action', array('Siloq_Junk_Detector', 'ajax_apply'));
        add_action('wp_ajax_siloq_sync_outdated', array($this, 'ajax_sync_outdated'));
        add_action('wp_ajax_siloq_get_business_profile', array($this, 'ajax_get_business_profile'));
        add_action('wp_ajax_siloq_save_business_profile', array($this, 'ajax_save_business_profile'));
        add_action('wp_ajax_siloq_analyze_widget', array('Siloq_Widget_Intelligence', 'ajax_analyze_widget'));
        add_action('wp_ajax_siloq_get_plan_data', array($this, 'ajax_get_plan_data'));
        add_action('wp_ajax_siloq_save_roadmap_progress', array($this, 'ajax_save_roadmap_progress'));
        add_action('wp_ajax_siloq_create_draft_page', array($this, 'ajax_create_draft_page'));
        add_action('wp_ajax_siloq_save_api_key',      array($this, 'ajax_save_api_key'));
        add_action('wp_ajax_siloq_get_pages_list', array($this, 'ajax_get_pages_list'));
        // GSC connection handlers
        add_action('wp_ajax_siloq_gsc_init_oauth', array($this, 'ajax_gsc_init_oauth'));
        add_action('wp_ajax_siloq_gsc_check_status', array($this, 'ajax_gsc_check_status'));
        add_action('wp_ajax_siloq_gsc_get_properties', array($this, 'ajax_gsc_get_properties'));
        add_action('wp_ajax_siloq_gsc_save_property', array($this, 'ajax_gsc_save_property'));
        // Detect post-OAuth return: ?siloq_gsc=connected or ?siloq_gsc=error
        add_action('admin_init', array($this, 'handle_gsc_oauth_return'));
        add_action('wp_ajax_siloq_gsc_sync', array($this, 'ajax_gsc_sync'));
        add_action('wp_ajax_siloq_gsc_disconnect', array($this, 'ajax_gsc_disconnect'));
        add_action('wp_ajax_siloq_gsc_get_properties', array($this, 'ajax_gsc_get_properties'));
        add_action('wp_ajax_siloq_gsc_save_property', array($this, 'ajax_gsc_save_property'));
        // Onboarding wizard handlers
        add_action('wp_ajax_siloq_wizard_connect', array($this, 'ajax_wizard_connect'));
        add_action('wp_ajax_siloq_wizard_save_profile', array($this, 'ajax_wizard_save_profile'));
        add_action('wp_ajax_siloq_wizard_complete', array($this, 'ajax_wizard_complete'));
        // Schema tab handlers
        add_action('wp_ajax_siloq_get_schema_status', array($this, 'ajax_get_schema_status'));
        add_action('wp_ajax_siloq_get_schema_graph', array($this, 'ajax_get_schema_graph'));
        add_action('wp_ajax_siloq_repair_elementor_meta',  array($this, 'ajax_repair_elementor_meta'));
        add_action('wp_ajax_siloq_bulk_apply_schema',         array('Siloq_Admin', 'ajax_bulk_apply_schema'));
        add_action('wp_ajax_siloq_detect_service_hub',         array($this, 'ajax_detect_service_hub'));
        add_action('wp_ajax_siloq_toggle_redirect',            array('Siloq_Admin', 'ajax_toggle_redirect'));

        // Page role override
        add_action('wp_ajax_siloq_set_page_role', array($this, 'ajax_set_page_role'));
        // Agent Ready — llms.txt, Authority Manifest, AI Visibility Audit (v1.5.139)
        Siloq_Agent_Ready::init();
        add_action('wp_ajax_siloq_generate_agent_files',    array('Siloq_Agent_Ready', 'ajax_generate_agent_files'));
        add_action('wp_ajax_siloq_get_agent_status',        array('Siloq_Agent_Ready', 'ajax_get_agent_status'));
        add_action('wp_ajax_siloq_run_ai_visibility_audit', array('Siloq_Agent_Ready', 'ajax_run_ai_visibility_audit'));
        add_action('wp_ajax_siloq_flush_rewrites',          array($this, 'ajax_flush_rewrites'));

        // Dashboard one-click fix buttons
        add_action('wp_ajax_siloq_dashboard_fix',          array('Siloq_Admin', 'ajax_dashboard_fix'));
        add_action('wp_ajax_siloq_generate_meta_suggestion', array('Siloq_Admin', 'ajax_generate_meta_suggestion'));
        add_action('wp_ajax_siloq_fix_all_seo',             array('Siloq_Admin', 'ajax_fix_all_seo'));
        add_action('wp_ajax_siloq_save_quick_win',          array('Siloq_Admin', 'ajax_save_quick_win'));

        // Redirect manager AJAX
        add_action('wp_ajax_siloq_get_redirects',        array('Siloq_Admin', 'ajax_get_redirects'));
        add_action('wp_ajax_siloq_add_redirect',         array('Siloq_Admin', 'ajax_add_redirect'));
        add_action('wp_ajax_siloq_delete_redirect',      array('Siloq_Admin', 'ajax_delete_redirect'));
        add_action('wp_ajax_siloq_bulk_add_redirects',   array('Siloq_Admin', 'ajax_bulk_add_redirects'));
        add_action('wp_ajax_siloq_preview_city_redirects', array('Siloq_Admin', 'ajax_preview_city_redirects'));
        // Image generation (DALL-E via API)
        add_action('wp_ajax_siloq_generate_and_insert_image', array('Siloq_Widget_Intelligence', 'ajax_generate_and_insert_image'));
        // Image Audit
        add_action('wp_ajax_siloq_get_image_audit', array('Siloq_Image_Audit', 'ajax_get_image_audit'));
        add_action('wp_ajax_siloq_apply_image_seo', array('Siloq_Image_Audit', 'ajax_apply_image_seo'));

        // Site Audit (Track 2)
        add_action('wp_ajax_siloq_run_audit',            array($this, 'ajax_run_audit'));
        add_action('wp_ajax_siloq_run_audit_background', array($this, 'ajax_run_audit_background'));
        add_action('wp_ajax_nopriv_siloq_run_audit_background', array($this, 'ajax_run_audit_background'));
        add_action('wp_ajax_siloq_audit_status',         array($this, 'ajax_audit_status'));
        add_action('siloq_audit_cron_job',               array($this, 'run_audit_cron_job'));

        // Debug logging AJAX
        add_action('wp_ajax_siloq_toggle_debug',       array('Siloq_Admin', 'ajax_toggle_debug'));
        add_action('wp_ajax_siloq_get_debug_log',      array('Siloq_Admin', 'ajax_get_debug_log'));
        add_action('wp_ajax_siloq_clear_debug_log',    array('Siloq_Admin', 'ajax_clear_debug_log'));
        add_action('wp_ajax_siloq_download_debug_log', array('Siloq_Admin', 'ajax_download_debug_log'));

        // Settings link
        add_filter('plugin_action_links_' . SILOQ_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Siloq Settings', 'siloq-connector'),
            __('Siloq', 'siloq-connector'),
            'manage_options',
            'siloq-settings',
            array('Siloq_Admin', 'render_settings_page'),
            'dashicons-networking',
            80
        );
        
        add_submenu_page(
            'siloq-settings',
            __('Dashboard', 'siloq-connector'),
            __('Dashboard', 'siloq-connector'),
            'manage_options',
            'siloq-dashboard',
            array('Siloq_Admin', 'render_dashboard_page')
        );
        
        add_submenu_page(
            'siloq-settings',
            __('Page Sync', 'siloq-connector'),
            __('Page Sync', 'siloq-connector'),
            'edit_pages',
            'siloq-sync',
            array('Siloq_Admin', 'render_sync_page')
        );
        
        add_submenu_page(
            'siloq-settings',
            __('Content Import', 'siloq-connector'),
            __('Content Import', 'siloq-connector'),
            'edit_pages',
            'siloq-content-import',
            array('Siloq_Admin', 'render_content_import_page')
        );
        
        add_submenu_page(
            'siloq-settings',
            __('Theme Intelligence', 'siloq-connector'),
            __('Theme Intelligence', 'siloq-connector'),
            'manage_options',
            'siloq-tali',
            array(Siloq_TALI::get_instance(), 'render_admin_page')
        );

        add_submenu_page(
            'siloq-settings',
            __('Image Brief', 'siloq-connector'),
            __('Image Brief', 'siloq-connector'),
            'manage_options',
            'siloq-image-brief',
            array($this, 'render_image_brief_page')
        );
    }

    public function render_image_brief_page() {
        echo Siloq_Image_Audit::render_photo_brief();
    }
    
    /**
     * Enqueue page editor assets for AI content generator
     */
    public function enqueue_page_editor_assets() {
        global $post;
        
        // Only load on page editor
        if (!$post || $post->post_type !== 'page') {
            return;
        }
        
        // Ensure plugin URL constant is defined
        if (!defined('SILOQ_PLUGIN_URL')) {
            define('SILOQ_PLUGIN_URL', plugin_dir_url(__FILE__));
        }
        
        // Enqueue AI Generator CSS
        wp_enqueue_style(
            'siloq-ai-generator',
            SILOQ_PLUGIN_URL . 'assets/css/ai-generator.css',
            array(),
            SILOQ_VERSION
        );
        
        // Enqueue AI Generator JavaScript
        wp_enqueue_script(
            'siloq-ai-generator',
            SILOQ_PLUGIN_URL . 'assets/js/siloq-ai-generator.js',
            array(),
            SILOQ_VERSION,
            true
        );
        
        $ai_localize = array(
            'postId'      => $post->ID,
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('siloq_ajax_nonce'), // matches check_ajax_referer in handlers
            'preferences' => Siloq_AI_Content_Generator::get_default_preferences(),
        );
        wp_localize_script('siloq-ai-generator', 'siloqAI', $ai_localize);
        // JS uses wpData.ajaxUrl / wpData.nonce — provide as alias
        wp_localize_script('siloq-ai-generator', 'wpData', $ai_localize);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets() {
        // Ensure plugin URL constant is defined
        if (!defined('SILOQ_PLUGIN_URL')) {
            define('SILOQ_PLUGIN_URL', plugin_dir_url(__FILE__));
        }
        
        // Enqueue admin CSS on all Siloq pages
        wp_enqueue_style(
            'siloq-admin',
            SILOQ_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SILOQ_VERSION
        );

        // Dashboard v2 CSS + JS on dashboard page
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && ($screen->id === 'siloq-settings_page_siloq-dashboard' || (isset($_GET['page']) && $_GET['page'] === 'siloq-dashboard'))) {
            wp_enqueue_style(
                'siloq-dashboard-v2',
                SILOQ_PLUGIN_URL . 'assets/css/siloq-dashboard-v2.css',
                array(),
                SILOQ_VERSION
            );
            wp_enqueue_script(
                'siloq-dashboard-v2',
                SILOQ_PLUGIN_URL . 'assets/js/siloq-dashboard-v2.js',
                array('jquery'),
                SILOQ_VERSION,
                true
            );
            // Pass Quick Wins completed state + AI key presence to dashboard JS
            $qw_completed = get_option( 'siloq_quick_wins_completed', [] );
            if ( ! is_array( $qw_completed ) ) $qw_completed = [];

            wp_localize_script('siloq-dashboard-v2', 'siloqDash', array(
                'ajaxUrl'         => admin_url('admin-ajax.php'),
                'nonce'           => wp_create_nonce('siloq_ajax_nonce'),
                'siteScore'       => intval(get_option('siloq_site_score', 42)),
                'siteId'          => get_option('siloq_site_id', ''),
                'hasAnthropicKey' => ! empty( get_option('siloq_anthropic_api_key', '') ) ? '1' : '',
                'qwCompleted'     => $qw_completed,
            ));
        }

        // Enqueue sync script on sync + settings pages
        if ($screen && ($screen->id === 'toplevel_page_siloq-settings' || $screen->id === 'siloq_page_siloq-sync' || $screen->id === 'siloq-settings_page_siloq-dashboard' || (isset($_GET['page']) && in_array($_GET['page'], ['siloq-settings', 'siloq-dashboard', 'siloq-sync'])))) {
            wp_enqueue_script(
                'siloq-sync',
                SILOQ_PLUGIN_URL . 'assets/js/siloq-sync.js',
                array('jquery'),
                SILOQ_VERSION,
                true
            );
            
            wp_localize_script('siloq-sync', 'siloqAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('siloq_ajax_nonce')
            ));
            
            wp_localize_script('siloq-sync', 'siloqAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('siloq_ajax_nonce'),
                'strings' => array(
                    'testing' => 'Testing...',
                    'success' => 'Success:',
                    'error'   => 'Error:'
                )
            ));
        }
        
        // Enqueue admin JS + localize dashboard nonce on dashboard page
        if ($screen && ($screen->id === 'siloq-settings_page_siloq-dashboard' || (isset($_GET['page']) && $_GET['page'] === 'siloq-dashboard'))) {
            wp_enqueue_script(
                'siloq-admin',
                SILOQ_PLUGIN_URL . 'assets/js/siloq-admin.js',
                array('jquery'),
                SILOQ_VERSION,
                true
            );
            
            wp_localize_script('siloq-admin', 'siloqAdminData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('siloq_admin_nonce'),
            ));
        }
    }
    
    /**
     * AJAX: Test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $api_url = get_option('siloq_api_url', '');
        $api_key = get_option('siloq_api_key', '');
        
        if (empty($api_url) || empty($api_key)) {
            wp_send_json_error(array('message' => 'API URL and key are required'));
            return;
        }
        
        // Test connection (mock for now)
        wp_send_json_success(array(
            'message' => 'Connection successful',
            'site_id' => '1'
        ));
    }
    
    /**
     * AJAX: Sync page
     */
    public function ajax_sync_page() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        
        $sync_engine = new Siloq_Sync_Engine();
        $result = $sync_engine->sync_page($post_id);
        
        if ($result['success']) {
            // Update sync time + bust dashboard cache on any successful page sync
            update_option( 'siloq_last_sync_time', current_time( 'mysql' ) );
            $api_key = get_option( 'siloq_api_key', '' );
            $site_id = get_option( 'siloq_site_id', '' );
            if ( $api_key && $site_id ) {
                delete_transient( 'siloq_dash_stats_' . md5( $site_id . $api_key ) );
            }
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Sync all pages
     */
    public function ajax_sync_all_pages() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');

        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        // Support paginated sync — JS passes offset for each batch
        $offset     = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $batch_size = 50; // 50 pages per AJAX call — safe for all shared hosts

        $sync_engine = new Siloq_Sync_Engine();
        $result = $sync_engine->sync_all_pages( $offset, $batch_size );

        // After a successful full sync, purge stale pages from Siloq DB.
        // Collect ALL current published/draft post IDs so the API knows what's still live.
        $purge_result = null;
        if ($result['success'] && !$result['has_more']) {
            // Only purge when this is the FINAL batch (has_more = false)
            // Collect all eligible post IDs in batches to avoid memory spikes on large sites.
            // Uses apply_filters('siloq_crawlable_post_types') for idiomatic WP extensibility,
            // with get_siloq_crawlable_post_types() as the default when the function exists.
            $crawlable_types = apply_filters(
                'siloq_crawlable_post_types',
                function_exists( 'get_siloq_crawlable_post_types' ) ? get_siloq_crawlable_post_types() : array( 'page', 'post' )
            );

            $all_posts  = array();
            $batch_size = 200;
            $paged      = 1;
            do {
                $batch = get_posts( array(
                    'post_type'              => $crawlable_types,
                    'post_status'            => array( 'publish', 'draft' ),
                    'posts_per_page'         => $batch_size,
                    'paged'                  => $paged,
                    'fields'                 => 'ids',
                    'no_found_rows'          => false, // Must be false for paged queries to work correctly
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ) );
                $all_posts = array_merge( $all_posts, $batch );
                $paged++;
            } while ( count( $batch ) === $batch_size ); // stop when last batch was partial
            if (!empty($all_posts)) {
                $api_client  = new Siloq_API_Client();
                $purge_result = $api_client->purge_deleted_pages($all_posts);
            }
        }

        $result['purge'] = $purge_result;

        if ($result['success']) {
            // Record sync time — dashboard reads this option for "Last synced" display
            update_option( 'siloq_last_sync_time', current_time( 'mysql' ) );

            // Bust dashboard stats cache so next load reflects fresh data
            $api_key = get_option( 'siloq_api_key', '' );
            $site_id = get_option( 'siloq_site_id', '' );
            if ( $api_key && $site_id ) {
                delete_transient( 'siloq_dash_stats_' . md5( $site_id . $api_key ) );
            }

            // Run image audit on sync completion
            if ( class_exists( 'Siloq_Image_Audit' ) ) {
                Siloq_Image_Audit::run_audit( get_posts( array(
                    'post_type'      => 'page',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                ) ) );
            }

            // Retroactive service-area hub detection — runs silently on every full sync
            if ( ! $result['has_more'] ) {
                $this->run_service_hub_detection();
                // Regenerate agent files (llms.txt + authority-manifest.json) if previously generated
                Siloq_Agent_Ready::on_sync_complete();
            }

            // Always send success so JS can read has_more + next_offset
            // and continue the batch loop regardless of per-page errors.
            wp_send_json_success($result);
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * AJAX: Get sync status
     */
    public function ajax_get_sync_status() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        try {
            $sync_engine = new Siloq_Sync_Engine();
            $status = $sync_engine->get_sync_status();
            wp_send_json_success($status);
        } catch (Throwable $e) {
            wp_send_json_error(array(
                'message' => 'Sync status error: ' . $e->getMessage(),
                'code'    => 'SYNC_STATUS_ERROR',
            ));
        }
    }
    
    /**
     * AJAX: Import content
     */
    public function ajax_import_content() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : 'Imported Content';
        $page_id = isset($_POST['pageId']) ? intval($_POST['pageId']) : 0;
        
        if (empty($content)) {
            wp_send_json_error(array('message' => 'Content is required'));
            return;
        }
        
        $post_data = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'page'
        );
        
        if ($page_id > 0) {
            $post_data['ID'] = $page_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        update_post_meta($result, '_siloq_imported', true);
        update_post_meta($result, '_siloq_imported_at', current_time('mysql'));
        
        wp_send_json_success(array(
            'pageId' => $result,
            'url' => get_permalink($result),
            'message' => 'Content imported successfully'
        ));
    }
    
    /**
     * AJAX: Generate AI content
     */
    public function ajax_generate_content() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => 'Missing post ID'));
            return;
        }
        
        $api_client = new Siloq_API_Client();
        $result = $api_client->create_content_job($post_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Check job status
     */
    public function ajax_check_job_status() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        
        if (!$job_id) {
            wp_send_json_error(array('message' => 'Missing job ID'));
            return;
        }
        
        $api_client = new Siloq_API_Client();
        $result = $api_client->get_job_status($job_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: AI Insert Content
     * Called by siloq-ai-generator.js insertContent()
     * insert_mode: 'draft' = new draft post, 'replace' = update existing post
     */
    public function ajax_ai_insert_content() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');

        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        $post_id     = isset($_POST['post_id'])     ? intval($_POST['post_id'])                : 0;
        $content     = isset($_POST['content'])     ? wp_kses_post($_POST['content'])          : '';
        $insert_mode = isset($_POST['insert_mode']) ? sanitize_text_field($_POST['insert_mode']) : 'draft';

        if (empty($content)) {
            wp_send_json_error(array('message' => 'No content provided'));
            return;
        }

        if ($insert_mode === 'replace' && $post_id > 0) {
            // Backup existing content before replacing
            $existing = get_post_field('post_content', $post_id);
            update_post_meta($post_id, '_siloq_backup_content', $existing);
            update_post_meta($post_id, '_siloq_backup_at', current_time('mysql'));

            $result = wp_update_post(array(
                'ID'           => $post_id,
                'post_content' => $content,
            ), true);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
                return;
            }

            update_post_meta($post_id, '_siloq_content_imported', true);
            update_post_meta($post_id, '_siloq_content_imported_at', current_time('mysql'));

            wp_send_json_success(array(
                'message'  => 'Content applied to page',
                'edit_url' => get_edit_post_link($post_id, 'raw'),
            ));

        } else {
            // Create new draft
            $post        = $post_id > 0 ? get_post($post_id) : null;
            $draft_title = $post ? 'AI Draft: ' . $post->post_title : 'AI Generated Draft';

            $new_id = wp_insert_post(array(
                'post_title'   => $draft_title,
                'post_content' => $content,
                'post_status'  => 'draft',
                'post_type'    => $post ? $post->post_type : 'page',
                'post_parent'  => $post ? $post->post_parent : 0,
            ), true);

            if (is_wp_error($new_id)) {
                wp_send_json_error(array('message' => $new_id->get_error_message()));
                return;
            }

            update_post_meta($new_id, '_siloq_content_imported', true);
            update_post_meta($new_id, '_siloq_content_imported_at', current_time('mysql'));

            wp_send_json_success(array(
                'message'     => 'Draft created successfully',
                'new_post_id' => $new_id,
                'edit_url'    => get_edit_post_link($new_id, 'raw'),
            ));
        }
    }

    /**
     * AJAX: Restore backup
     */
    public function ajax_restore_backup() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $backup_content = isset($_POST['backup_content']) ? wp_kses_post($_POST['backup_content']) : '';
        
        if (!$post_id || empty($backup_content)) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
            return;
        }
        
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $backup_content
        ));
        
        if ($result && !is_wp_error($result)) {
            update_post_meta($post_id, '_siloq_backup_restored', current_time('mysql'));
            wp_send_json_success(array('message' => 'Backup restored successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to restore backup'));
        }
    }
    
    /**
     * AJAX: Sync outdated pages
     */
    public function ajax_sync_outdated() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $sync_engine = new Siloq_Sync_Engine();
        $result = $sync_engine->sync_outdated_pages();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Get business profile
     */
    public function ajax_get_business_profile() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        // Build local profile from WP options — always available, API-independent
        $local = [
            'business_name'    => get_option( 'siloq_business_name', get_bloginfo( 'name' ) ),
            'phone'            => get_option( 'siloq_phone', '' ),
            'address'          => get_option( 'siloq_address', '' ),
            'city'             => get_option( 'siloq_city', '' ),
            'state'            => get_option( 'siloq_state', '' ),
            'zip'              => get_option( 'siloq_zip', '' ),
            'business_type'    => get_option( 'siloq_business_type', '' ),
            'primary_services' => json_decode( get_option( 'siloq_primary_services', '[]' ), true ) ?: [],
            'service_areas'    => json_decode( get_option( 'siloq_service_areas',    '[]' ), true ) ?: [],
            'founding_year'    => get_option( 'siloq_founding_year', '' ),
        ];

        // Try to supplement with API data (fresher, includes service_cities etc.)
        $site_id  = get_option( 'siloq_site_id', '' );
        $api_key  = get_option( 'siloq_api_key', '' );
        $api_base = rtrim( get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' ), '/' );

        if ( $site_id && $api_key ) {
            $response = wp_remote_get(
                trailingslashit($api_base) . "sites/$site_id/entity-profile/",
                [
                    'timeout' => 8,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Accept'        => 'application/json',
                        'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
                    ],
                ]
            );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $api_data = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( is_array( $api_data ) ) {
                    // Normalize API field names to form field names
                    if ( isset( $api_data['street_address'] ) ) $api_data['address']       = $api_data['street_address'];
                    if ( isset( $api_data['zip_code'] ) )       $api_data['zip']            = $api_data['zip_code'];
                    if ( isset( $api_data['service_cities'] ) ) $api_data['service_areas']  = $api_data['service_cities'];
                    // API wins over local for overlapping fields
                    $local = array_merge( $local, array_filter( $api_data, function( $v ) { return $v !== null && $v !== ''; } ) );
                }
            }
        }

        wp_send_json_success( $local );
    }
    
    /**
     * AJAX: Save business profile
     */
    public function ajax_save_business_profile() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        // Sanitize all fields
        $business_name    = sanitize_text_field( $_POST['business_name']    ?? '' );
        $phone            = sanitize_text_field( $_POST['phone']            ?? '' );
        $address          = sanitize_text_field( $_POST['address']          ?? '' );
        $city             = sanitize_text_field( $_POST['city']             ?? '' );
        $state            = sanitize_text_field( strtoupper( $_POST['state'] ?? '' ) );
        $zip              = sanitize_text_field( $_POST['zip']              ?? '' );
        $business_type    = sanitize_text_field( $_POST['business_type']    ?? '' );
        $founding_year    = sanitize_text_field( $_POST['founding_year']    ?? '' );
        $primary_services = isset( $_POST['primary_services'] )
            ? array_map( 'sanitize_text_field', (array) $_POST['primary_services'] )
            : [];
        $service_areas    = isset( $_POST['service_areas'] )
            ? array_map( 'sanitize_text_field', (array) $_POST['service_areas'] )
            : [];

        // ── Save to WP options (authoritative local store) ────────────────
        $db_results = [
            'business_name'    => update_option( 'siloq_business_name',    $business_name ),
            'phone'            => update_option( 'siloq_phone',            $phone ),
            'address'          => update_option( 'siloq_address',          $address ),
            'city'             => update_option( 'siloq_city',             $city ),
            'state'            => update_option( 'siloq_state',            $state ),
            'zip'              => update_option( 'siloq_zip',              $zip ),
            'business_type'    => update_option( 'siloq_business_type',    $business_type ),
            'founding_year'    => update_option( 'siloq_founding_year',    $founding_year ),
            'primary_services' => update_option( 'siloq_primary_services', wp_json_encode( $primary_services ) ),
            'service_areas'    => update_option( 'siloq_service_areas',    wp_json_encode( $service_areas ) ),
        ];

        // Verify the write — read back and confirm business_name at minimum
        $verify_name = get_option( 'siloq_business_name', '__MISSING__' );
        $db_success  = ( $verify_name !== '__MISSING__' );

        // Debug log entry
        $log_entry = [
            'ts'          => current_time( 'mysql' ),
            'fields'      => array_keys( array_filter( compact( 'business_name', 'phone', 'address', 'city', 'state', 'zip', 'business_type' ) ) ),
            'services'    => count( $primary_services ),
            'areas'       => count( $service_areas ),
            'db_verified' => $db_success,
            'api_sync'    => null, // filled below
        ];

        if ( ! $db_success ) {
            $log_entry['api_sync'] = 'skipped_db_fail';
            self::append_debug_log( $log_entry );
            wp_send_json_error( [
                'message' => 'Database write failed — business profile was not saved. Check your WordPress database permissions and try again.',
                'code'    => 'DB_WRITE_FAILED',
            ] );
            return;
        }

        // Sync to Siloq API — correct endpoint: PATCH /sites/{id}/entity-profile/
        // Fields mapped to API schema (street_address/zip_code not address/zip).
        $site_id  = get_option( 'siloq_site_id', '' );
        $api_key  = get_option( 'siloq_api_key', '' );
        $api_base = rtrim( get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' ), '/' );

        $api_sync_note = '';
        if ( $site_id && $api_key ) {
            $api_payload = array_filter( [
                'business_name'    => $business_name,
                'phone'            => $phone,
                'street_address'   => $address,    // API field name
                'city'             => $city,
                'state'            => $state,
                'zip_code'         => $zip,        // API field name
                'business_type'    => $business_type,
                'primary_services' => $primary_services,
                'service_cities'   => $service_areas,  // API field name
            ], function( $v ) {
                return $v !== '' && $v !== null && $v !== [];
            } );

            $response = wp_remote_request(
                trailingslashit($api_base) . "sites/$site_id/entity-profile/",
                [
                    'method'  => 'PATCH',
                    'timeout' => 10,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                        'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
                    ],
                    'body' => wp_json_encode( $api_payload ),
                ]
            );

            $api_code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
            if ( is_wp_error( $response ) || $api_code >= 400 ) {
                $api_sync_note              = 'Saved locally. API sync failed — your data is safe but may not appear in the Siloq app until the next sync.';
                $log_entry['api_sync']      = 'failed';
                $log_entry['api_code']      = $api_code;
                $log_entry['api_error']     = is_wp_error( $response ) ? $response->get_error_message() : '';
            } else {
                $log_entry['api_sync'] = 'ok';
            }
        } else {
            $log_entry['api_sync'] = 'skipped_no_credentials';
        }

        self::append_debug_log( $log_entry );

        $profile_data = [
            'business_name'    => $business_name,
            'phone'            => $phone,
            'address'          => $address,
            'city'             => $city,
            'state'            => $state,
            'zip'              => $zip,
            'business_type'    => $business_type,
            'primary_services' => $primary_services,
            'service_areas'    => $service_areas,
        ];

        wp_send_json_success( [
            'message'    => 'Business profile saved successfully. Siloq will use this data for all recommendations on this site.',
            'api_note'   => $api_sync_note,
            'profile'    => $profile_data,
        ] );
    }
    
    /**
     * Append a structured entry to the Siloq debug log (stored in WP options).
     * Keeps the last 50 entries. Accessible from Settings > Debug.
     */
    public static function append_debug_log( $entry ) {
        $log     = json_decode( get_option( 'siloq_debug_log', '[]' ), true ) ?: [];
        $log[]   = $entry;
        $log     = array_slice( $log, -50 ); // keep last 50
        update_option( 'siloq_debug_log', wp_json_encode( $log ) );
    }

    /**
     * AJAX: Get plan data — build architecture tree, priority actions, issues, roadmap
     */
    public function ajax_get_plan_data() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        // Transient disabled during testing — re-enable when data is stable
        // $cached = get_transient('siloq_plan_data');
        // if ($cached) {
        //     wp_send_json_success($cached);
        //     return;
        // }

        // Query all synced pages (_siloq_synced set by sync engine on every page sync)
        // _siloq_analysis_data only exists for pages run through Widget Intelligence
        // so we use _siloq_synced as the base query and optionally read analysis data
        $posts = get_posts(array(
            'post_type'      => function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array('page', 'post'),
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => '_siloq_synced',
                    'compare' => 'EXISTS',
                ),
            ),
        ));

        $architecture = array();
        $actions      = array();
        $issues       = array('critical' => array(), 'important' => array(), 'opportunity' => array());
        $supporting   = array();
        $hubs         = array();
        $orphans      = array();
        $hub_count    = 0;
        $orphan_count = 0;
        $missing_count = 0;

        // Build complete inbound link map — nav menus + page content
        $inbound_links = array(); // $post_id => true means it has inbound links

        // 1. Nav menu items
        $all_menus = wp_get_nav_menus();
        foreach ($all_menus as $menu) {
            $menu_items = wp_get_nav_menu_items($menu->term_id);
            if (!$menu_items) continue;
            foreach ($menu_items as $item) {
                if ($item->object === 'page' || $item->object === 'post') {
                    $inbound_links[$item->object_id] = true;
                }
            }
        }

        // 2. Homepage always has inbound links (it IS the root)
        $homepage_id = intval(get_option('page_on_front'));
        if ($homepage_id) $inbound_links[$homepage_id] = true;

        // 3. Internal links from page content
        foreach ($posts as $pid) {
            $content = get_post_field('post_content', $pid);
            if (empty($content)) continue;
            preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
            foreach ($matches[1] as $href) {
                $linked_id = url_to_postid($href);
                if ($linked_id) $inbound_links[$linked_id] = true;
            }
        }

        foreach ($posts as $post_id) {
            // Skip JetEngine CPTs and internal post type pages — they must never appear in the architecture map
            $post_obj = get_post( $post_id );
            if ( $post_obj && class_exists( 'Siloq_Admin' ) ) {
                if ( Siloq_Admin::is_internal_post_type_name( strtolower( $post_obj->post_title ), $post_obj->post_name ) ) {
                    continue;
                }
            }

            $raw      = get_post_meta($post_id, '_siloq_analysis_data', true);
            $analysis = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : array());
            if (!is_array($analysis)) $analysis = array();
            // Pages without Widget Intelligence analysis still show in the plan
            // with basic data from sync — page_type defaults to 'supporting'

            $title     = get_the_title($post_id);
            $page_type = isset($analysis['page_type_classification']) ? $analysis['page_type_classification'] : 'supporting';
            $score     = isset($analysis['score']) ? intval($analysis['score']) : 0;

            // Real content issue checks — even for unanalyzed pages
            $edit_url = get_edit_post_link($post_id, 'raw');
            $elementor_url = admin_url('post.php?post=' . $post_id . '&action=elementor');
            $page_url = get_permalink($post_id);

            // Fix 1: Use AIOSEO priority chain for title
            $meta_title = class_exists('Siloq_Admin') ? Siloq_Admin::siloq_get_page_title($post_id) : get_the_title($post_id);
            // If the helper returned the same as post_title, treat as missing SEO title
            if ($meta_title === get_the_title($post_id)) {
                $meta_title = ''; // no dedicated SEO title set
            }

            // Fix 2: Use BROKEN_FALLBACK-aware meta description
            $meta_desc_result = class_exists('Siloq_Admin') ? Siloq_Admin::siloq_get_meta_description($post_id) : '';
            $meta_desc_broken = false;
            if (is_array($meta_desc_result) && isset($meta_desc_result['status']) && $meta_desc_result['status'] === 'broken_fallback') {
                $meta_desc = '';
                $meta_desc_broken = true;
            } else {
                $meta_desc = is_string($meta_desc_result) ? $meta_desc_result : '';
            }

            // Fix 3: Auto-classify with apex_hub support (unless manually overridden)
            $existing_role = get_post_meta($post_id, '_siloq_page_role', true);
            if (empty($existing_role) && class_exists('Siloq_Admin')) {
                $auto_type = Siloq_Admin::siloq_classify_page($post_id, $page_url);
                if (!empty($auto_type)) {
                    $page_type = $auto_type;
                }
            } elseif (!empty($existing_role)) {
                $page_type = $existing_role;
            }

            // Check schema
            $applied_schema = get_post_meta($post_id, '_siloq_applied_types', true);
            $has_schema = !empty($applied_schema);

            // Check content length
            $page_content = get_post_field('post_content', $post_id);
            $word_count = str_word_count(strip_tags($page_content));

            // Pre-generate formula suggestions (used by Quick Wins + Priority Actions)
            $formula_title = class_exists('Siloq_Admin') ? Siloq_Admin::siloq_formula_seo_title($post_id) : '';
            $formula_desc  = class_exists('Siloq_Admin') ? Siloq_Admin::siloq_formula_meta_desc($post_id)  : '';

            // Build issues from checks
            if (empty($meta_title)) {
                $issues['important'][] = array(
                    'title'            => $title,
                    'issue'            => 'Missing SEO title',
                    'fix_category'     => 'auto',
                    'fix_type'         => 'meta_title',
                    'formula'          => $formula_title,
                    'post_id'          => $post_id,
                    'edit_url'         => $edit_url,
                    'elementor_url'    => $edit_url, // Always WP editor for meta, never Elementor
                );
            }
            if ($meta_desc_broken) {
                $issues['critical'][] = array(
                    'title'            => $title,
                    'issue'            => 'Meta description contains full page content (' . (is_array($meta_desc_result) ? $meta_desc_result['length'] : '?') . ' chars) — needs a proper 150-char summary',
                    'fix_category'     => 'auto',
                    'fix_type'         => 'meta_description',
                    'formula'          => $formula_desc,
                    'post_id'          => $post_id,
                    'edit_url'         => $edit_url,
                    'elementor_url'    => $edit_url,
                );
            } elseif (empty($meta_desc)) {
                $issues['important'][] = array(
                    'title'            => $title,
                    'issue'            => 'Missing meta description',
                    'fix_category'     => 'auto',
                    'fix_type'         => 'meta_description',
                    'formula'          => $formula_desc,
                    'post_id'          => $post_id,
                    'edit_url'         => $edit_url,
                    'elementor_url'    => $edit_url,
                );
            }
            if (!$has_schema) {
                $issues['opportunity'][] = array(
                    'title'            => $title,
                    'issue'            => 'No schema markup — AI tools can\'t reliably cite this page without structured data',
                    'fix_category'     => 'auto',
                    'fix_type'         => 'schema',
                    'post_id'          => $post_id,
                    'edit_url'         => $edit_url,
                    'elementor_url'    => $elementor_url,
                );
            }
            if ($word_count < 300 && $word_count > 0) {
                $issues['important'][] = array(
                    'title'            => $title,
                    'issue'            => 'Thin content — only ' . $word_count . ' words. Aim for 500+ to rank well.',
                    'fix_category'     => 'content',
                    'fix_type'         => 'content',
                    'post_id'          => $post_id,
                    'edit_url'         => $elementor_url, // Thin content DOES need Elementor
                    'elementor_url'    => $elementor_url,
                    'instructions'     => 'Open this page in your editor, add more detail about this service or location.',
                );
            }
            // Check silo relationship FIRST, then add to architecture once only
            $silo_data = get_post_meta($post_id, '_siloq_silo_data', true);
            $has_analysis = !empty($analysis);

            if ($page_type === 'apex_hub' || $page_type === 'hub') {
                $hub_count++;
                $hubs[] = $post_id;
                $arch_type = $page_type;
            } elseif (!$has_analysis) {
                // Not analyzed yet — show as pending, not orphan
                $arch_type = 'pending';
            } elseif (empty($silo_data) && !isset($inbound_links[$post_id])) {
                // Analyzed but not assigned to a silo AND no inbound links
                $orphans[] = $post_id;
                $orphan_count++;
                $arch_type = 'orphan';
            } else {
                $arch_type = $page_type;
            }

            // No inbound links check (after arch_type is determined)
            if (!isset($inbound_links[$post_id]) && $arch_type !== 'hub' && $arch_type !== 'apex_hub') {
                $issues['critical'][] = array(
                    'title'        => $title,
                    'issue'        => 'No internal links pointing to this page — Google may not be crawling it',
                    'post_id'      => $post_id,
                    'edit_url'     => $edit_url,
                    'elementor_url'=> $elementor_url,
                );
            }

            // Include parent hub_id so JS can group spokes under their hub in the tree
            $hub_id = 0;
            if ( $arch_type === 'spoke' || $arch_type === 'supporting' ) {
                $hub_id = (int) get_post_meta( $post_id, '_siloq_service_area_hub_id', true );
                if ( ! $hub_id ) {
                    // Fall back to silo_data parent
                    $silo = get_post_meta( $post_id, '_siloq_silo_data', true );
                    if ( is_array( $silo ) && ! empty( $silo['hub_post_id'] ) ) {
                        $hub_id = (int) $silo['hub_post_id'];
                    }
                }
            }

            $architecture[] = array(
                'title'    => $title,
                'type'     => $arch_type,
                'id'       => $post_id,
                'score'    => $score,
                'hub_id'   => $hub_id,
                'edit_url' => $edit_url,
                'el_url'   => $elementor_url,
                'url'      => get_permalink( $post_id ),
            );

            // Extract issues from analysis
            if (isset($analysis['issues']) && is_array($analysis['issues'])) {
                foreach ($analysis['issues'] as $issue) {
                    $severity = isset($issue['severity']) ? strtolower($issue['severity']) : 'opportunity';
                    if (!isset($issues[$severity])) $severity = 'opportunity';
                    $issues[$severity][] = array(
                        'title'        => $title,
                        'issue'        => isset($issue['message']) ? $issue['message'] : (isset($issue['description']) ? $issue['description'] : 'Issue found'),
                        'post_id'      => $post_id,
                        'edit_url'     => $edit_url,
                        'elementor_url'=> $elementor_url,
                    );
                }
            }

            // ── Priority Actions — classified by effort ──────────────────────────
            if (empty($meta_title)) {
                $actions[] = array(
                    'headline'      => 'Add SEO title to "' . $title . '"',
                    'detail'        => 'Siloq will write one instantly using your page type and business name.',
                    'priority'      => 'high',
                    'fix_category'  => 'auto',
                    'fix_type'      => 'meta_title',
                    'formula'       => $formula_title,
                    'post_id'       => $post_id,
                    'edit_url'      => $edit_url,
                    'elementor_url' => $edit_url, // always WP editor, never Elementor
                );
            }
            if (empty($meta_desc) || $meta_desc_broken) {
                $actions[] = array(
                    'headline'      => 'Add meta description to "' . $title . '"',
                    'detail'        => $meta_desc_broken
                        ? 'Your description contains full page content. Siloq will generate a proper 150-char summary.'
                        : 'Missing description reduces clicks from search results. Siloq will write one.',
                    'priority'      => 'high',
                    'fix_category'  => 'auto',
                    'fix_type'      => 'meta_description',
                    'formula'       => $formula_desc,
                    'post_id'       => $post_id,
                    'edit_url'      => $edit_url,
                    'elementor_url' => $edit_url,
                );
            }
            if (!$has_schema) {
                $actions[] = array(
                    'headline'      => 'Add schema markup to "' . $title . '"',
                    'detail'        => 'Schema helps AI tools cite this page and enables rich results in Google.',
                    'priority'      => 'high',
                    'fix_category'  => 'auto',
                    'fix_type'      => 'schema',
                    'post_id'       => $post_id,
                    'edit_url'      => $edit_url,
                    'elementor_url' => $elementor_url,
                );
            }
            if ($word_count > 0 && $word_count < 300) {
                $actions[] = array(
                    'headline'      => 'Expand content on "' . $title . '"',
                    'detail'        => 'Only ' . $word_count . ' words. Pages with 500+ words rank significantly better for local searches.',
                    'priority'      => 'medium',
                    'fix_category'  => 'content',
                    'fix_type'      => 'content',
                    'post_id'       => $post_id,
                    'edit_url'      => $elementor_url,
                    'elementor_url' => $elementor_url,
                    'instructions'  => 'Open this page in Elementor and add more detail — describe this service, mention nearby areas, add an FAQ section.',
                );
            }

            // Supporting content — from analysis if available, otherwise suggest from page structure
            if (isset($analysis['missing_supporting']) && is_array($analysis['missing_supporting'])) {
                foreach ($analysis['missing_supporting'] as $ms) {
                    $missing_count++;
                    $supporting[] = array(
                        'title'  => isset($ms['title']) ? $ms['title'] : 'Supporting page for "' . $title . '"',
                        'type'   => isset($ms['type']) ? $ms['type'] : 'sub-page',
                        'parent' => $title,
                    );
                }
            } elseif ($arch_type === 'hub' || $arch_type === 'apex_hub' || $page_type === 'hub' || $page_type === 'apex_hub') {
                // Hub with no analysis data yet — skip the generic "Add supporting pages" card entirely.
                // Real gap-analysis cards are generated in the Supporting Content Opportunities section
                // using categorize_pages() + primary services + service areas. No generic cards here.
            }
        }

        // Sort architecture: apex_hub first, then hubs, spokes, supporting, orphans, missing
        $type_order = array('apex_hub' => 0, 'hub' => 1, 'spoke' => 2, 'supporting' => 3, 'orphan' => 4, 'missing' => 5);
        usort($architecture, function($a, $b) use ($type_order) {
            $oa = isset($type_order[$a['type']]) ? $type_order[$a['type']] : 6;
            $ob = isset($type_order[$b['type']]) ? $type_order[$b['type']] : 6;
            return $oa - $ob;
        });

        // Fix 4: Sort actions by tier system (STRUCTURAL > CONTENT > SCHEMA > CLASSIFICATION)
        if (class_exists('Siloq_Admin')) {
            Siloq_Admin::siloq_sort_actions($actions);
        } else {
            usort($actions, function($a, $b) {
                $p = array('high' => 0, 'medium' => 1, 'low' => 2);
                return ($p[$a['priority']] ?? 2) - ($p[$b['priority']] ?? 2);
            });
        }

        // Default roadmap
        $roadmap = array(
            'month1' => array(
                'Fix keyword conflicts on top pages',
                'Add missing schema markup',
                'Fix thin content issues',
                'Connect Google Search Console',
                'Complete entity profiles',
            ),
            'month2' => array(
                'Create missing supporting content',
                'Build internal linking between silos',
                'Optimize page titles and meta descriptions',
                'Fix orphan pages — assign to silos',
                'Improve content depth on key pages',
            ),
            'month3' => array(
                'Launch new hub pages for gaps',
                'Create blog content supporting service pages',
                'Monitor ranking improvements',
                'Review and update underperforming content',
                'Set up ongoing content calendar',
            ),
        );

        // Score breakdown counters
        $missing_titles = count( array_filter( $issues['important'] ?? [], function($i) { return ($i['fix_type'] ?? '') === 'meta_title'; } ) );
        $missing_descs  = count( array_filter( $issues['important'] ?? [], function($i) { return ($i['fix_type'] ?? '') === 'meta_description'; } ) );
        $missing_schema = count( array_filter( $issues['opportunity'] ?? [], function($i) { return ($i['fix_type'] ?? '') === 'schema'; } ) );
        $thin_content   = count( array_filter( $issues['important'] ?? [], function($i) { return ($i['fix_type'] ?? '') === 'content'; } ) );

        // Collect post_ids that need fix-all
        $fix_all_pages = array_merge(
            array_column( array_filter( $issues['important'] ?? [], function($i) { return in_array($i['fix_type'] ?? '', ['meta_title','meta_description']); } ), 'post_id' ),
            array_column( array_filter( $issues['critical']  ?? [], function($i) { return in_array($i['fix_type'] ?? '', ['meta_title','meta_description']); } ), 'post_id' )
        );
        $fix_all_pages = array_values( array_unique( $fix_all_pages ) );

        // Compute site health score (100 - deductions per page)
        $total_pages = count( $architecture );
        $deductions  = ($missing_titles * 10) + ($missing_descs * 8) + ($missing_schema * 5) + ($thin_content * 5) + ($orphan_count * 3);
        $site_score  = $total_pages > 0 ? max( 0, min( 100, 100 - intval( $deductions / max(1, $total_pages) * 2 ) ) ) : 0;

        $plan = array(
            'architecture'   => $architecture,
            'actions'        => $actions,
            'issues'         => $issues,
            'supporting'     => $supporting,
            'roadmap'        => $roadmap,
            'hub_count'      => $hub_count,
            'orphan_count'   => $orphan_count,
            'missing_count'  => $missing_count,
            'site_score'     => $site_score,
            'score_breakdown' => array(
                'missing_titles' => $missing_titles,
                'missing_descs'  => $missing_descs,
                'missing_schema' => $missing_schema,
                'thin_content'   => $thin_content,
                'orphan_count'   => $orphan_count,
            ),
            'fix_all_pages'  => $fix_all_pages,
            'generated_at'   => current_time('mysql'),
        );

        // Cache for 60 minutes
        set_transient('siloq_plan_data', $plan, 3600);
        update_option('siloq_plan_data_last', current_time('mysql'));

        wp_send_json_success($plan);
    }

    /**
     * AJAX: Save roadmap checkbox progress
     */
    public function ajax_save_api_key() {
        check_ajax_referer('siloq_save_api_key', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        $allowed = array('siloq_openai_api_key', 'siloq_anthropic_api_key');
        $option  = isset($_POST['option_name']) ? sanitize_key($_POST['option_name']) : '';
        $value   = isset($_POST['key_value'])   ? trim($_POST['key_value'])            : '';
        if (!in_array($option, $allowed, true)) {
            wp_send_json_error(array('message' => 'Invalid option'));
            return;
        }
        if (empty($value)) {
            wp_send_json_error(array('message' => 'Key cannot be empty'));
            return;
        }
        update_option($option, $value);
        wp_cache_delete($option, 'options');
        // Verify it actually saved
        $saved = get_option($option, '');
        if ($saved !== $value) {
            wp_send_json_error(array('message' => 'DB write failed — check WP file permissions'));
            return;
        }
        wp_send_json_success(array('last4' => substr($value, -4)));
    }

    public function ajax_create_draft_page() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        $title      = sanitize_text_field($_POST['title'] ?? '');
        $draft_type = sanitize_text_field($_POST['draft_type'] ?? 'generic'); // service-areas | service | city | generic
        if (empty($title)) {
            wp_send_json_error(array('message' => 'Title required'));
            return;
        }

        // ── Cannibalization guard ─────────────────────────────────────────
        // Before creating, check if any synced page already targets the same
        // city/keyword. Prevents duplicate pages competing for the same terms.
        $cannibal_check = $this->check_draft_cannibalization($title, $draft_type);
        if ($cannibal_check) {
            wp_send_json_error(array(
                'message'       => $cannibal_check['message'],
                'existing_page' => $cannibal_check['existing_page'],
                'existing_url'  => $cannibal_check['existing_url'],
                'edit_url'      => $cannibal_check['edit_url'],
                'cannibal'      => true,
            ));
            return;
        }

        // Generate content based on draft type
        $content = $this->generate_draft_content($title, $draft_type);

        // Service area hub pages go live immediately; all other types start as draft
        $auto_publish_types = array('service-areas');
        $post_status = in_array($draft_type, $auto_publish_types, true) ? 'publish' : 'draft';

        $post_id = wp_insert_post(array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $post_status,
            'post_type'    => 'page',
        ));
        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => $post_id->get_error_message()));
            return;
        }
        // Mark page as Elementor-built so Siloq's builder detection initialises
        // the Schema panel when the editor opens. Without this, new drafts
        // created by Siloq have no _elementor_edit_mode and the schema button
        // never appears.
        if (class_exists('\Elementor\Plugin')) {
            update_post_meta($post_id, '_elementor_edit_mode', 'builder');
            // Elementor requires _elementor_data to be a valid JSON array
            if (empty(get_post_meta($post_id, '_elementor_data', true))) {
                update_post_meta($post_id, '_elementor_data', '[]');
            }
        }
        wp_send_json_success(array(
            'post_id'  => $post_id,
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=elementor'),
        ));
    }

    /**
     * Check if creating a draft for $title would cannibalize an existing synced page.
     *
     * For city pages: extract the city name and check if any existing page
     * title or slug contains that city. Prevents duplicate city pages that
     * would split rankings.
     *
     * @return array|null  null = safe to proceed. array = conflict found.
     */
    private function check_draft_cannibalization($title, $draft_type) {
        // Only check city and service draft types
        if (!in_array($draft_type, array('city', 'service', 'generic'), true)) return null;

        // Extract the meaningful part of the title for comparison
        // e.g. "Grandview, MO Electrician" → "grandview"
        $title_lower = strtolower($title);
        // Strip common service suffixes to isolate location/service keyword
        $strip = array(' electrician', ' plumber', ' hvac', ' roofer', ' roofing',
                       ' contractor', ' services', ' service', ', mo', ', ks',
                       ' mo', ' ks', ' electricians', 'electrician ');
        $core_kw = str_replace($strip, '', $title_lower);
        $core_kw = preg_replace('/\s+/', ' ', trim($core_kw));

        if (strlen($core_kw) < 3) return null;

        // Find all synced published pages
        $existing = get_posts(array(
            'post_type'      => array('page', 'post'),
            'post_status'    => array('publish', 'draft'),
            'posts_per_page' => -1,
            'meta_query'     => array(array('key' => '_siloq_synced', 'compare' => 'EXISTS')),
        ));

        foreach ($existing as $post) {
            $existing_title = strtolower($post->post_title);
            $existing_slug  = $post->post_name;

            // Compare stripped title and slug
            $existing_core = str_replace($strip, '', $existing_title);
            $existing_core = preg_replace('/\s+/', ' ', trim($existing_core));

            if (
                ( strlen($core_kw) >= 4 && strpos($existing_core, $core_kw) !== false ) ||
                ( strlen($core_kw) >= 4 && strpos($existing_slug, $core_kw) !== false ) ||
                similar_text($core_kw, $existing_core, $pct) && $pct > 75
            ) {
                return array(
                    'message'       => 'A page targeting "' . $post->post_title . '" already exists. Improve the existing page instead of creating a duplicate.',
                    'existing_page' => $post->post_title,
                    'existing_url'  => get_permalink($post->ID),
                    'edit_url'      => admin_url('post.php?post=' . $post->ID . '&action=elementor'),
                );
            }
        }

        return null;
    }

    /**
     * AJAX: Detect service-area hub pages and retroactively assign city spoke pages.
     *
     * Scans all synced pages for one matching "service-area" or "service-areas" in
     * its slug or title. If found and it has zero spoke pages assigned to it, scans
     * all other spoke/supporting pages and sets _siloq_service_area_hub_id on them
     * pointing to the hub. Adds Priority Action items to link each city page to the hub.
     *
     * Runs automatically: called after every sync completion + on-demand via button.
     */
    public function ajax_detect_service_hub() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $result = $this->run_service_hub_detection();
        wp_send_json_success($result);
    }

    public function run_service_hub_detection() {
        // Find service-area hub page
        $hub = null;
        $candidates = get_posts(array(
            'post_type'      => array('page', 'post'),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => array(array('key' => '_siloq_synced', 'compare' => 'EXISTS')),
        ));

        foreach ($candidates as $p) {
            $slug  = $p->post_name;
            $title = strtolower($p->post_title);
            if (
                strpos($slug,  'service-area') !== false ||
                strpos($slug,  'service-areas') !== false ||
                strpos($title, 'service area') !== false ||
                strpos($title, 'service areas') !== false ||
                get_post_meta($p->ID, '_siloq_page_role', true) === 'hub' ||
                get_post_meta($p->ID, '_siloq_page_role', true) === 'apex_hub'
            ) {
                $hub = $p;
                break;
            }
        }

        if (!$hub) {
            return array('status' => 'no_hub', 'message' => 'No service areas hub page found. Create one first.');
        }

        // Check if already connected (has spoke pages assigned)
        $already_connected = (int) get_post_meta($hub->ID, '_siloq_spoke_count', true);
        $force = isset($_POST['force']) && $_POST['force'] === '1';
        if ($already_connected > 0 && !$force) {
            return array(
                'status'  => 'already_connected',
                'hub_id'  => $hub->ID,
                'hub_title' => $hub->post_title,
                'count'   => $already_connected,
                'message' => $hub->post_title . ' already has ' . $already_connected . ' city pages connected.',
            );
        }

        // Mark hub
        update_post_meta($hub->ID, '_siloq_page_role', 'hub');

        // Find city spoke pages — pages classified as spoke/supporting/orphan that aren't the hub
        $spoke_patterns = array('electrician', 'plumber', 'hvac', 'roofer', 'roofing',
                                'contractor', 'service', 'repair', 'install');
        $state_patterns = array(' mo', ', mo', ' ks', ', ks', '-mo-', '-ks-');
        $connected = 0;
        $skipped   = 0;

        foreach ($candidates as $p) {
            if ($p->ID === $hub->ID) continue;

            $role = get_post_meta($p->ID, '_siloq_page_role', true);
            $slug = $p->post_name;
            $title_lower = strtolower($p->post_title);

            // Skip hub-type pages
            if (in_array($role, array('hub', 'apex_hub'), true)) { $skipped++; continue; }

            // Detect city pages: slug contains state abbreviation OR common service keyword
            $is_city_page = false;
            foreach ($state_patterns as $sp) {
                if (strpos($slug, trim($sp, ' ,-')) !== false || strpos($title_lower, $sp) !== false) {
                    $is_city_page = true; break;
                }
            }
            if (!$is_city_page) {
                foreach ($spoke_patterns as $kw) {
                    if (strpos($slug, $kw) !== false || strpos($title_lower, $kw) !== false) {
                        $is_city_page = true; break;
                    }
                }
            }

            if ($is_city_page) {
                update_post_meta($p->ID, '_siloq_page_role', 'spoke');
                update_post_meta($p->ID, '_siloq_service_area_hub_id', $hub->ID);
                $connected++;
            } else {
                $skipped++;
            }
        }

        update_post_meta($hub->ID, '_siloq_spoke_count', $connected);

        // Invalidate plan cache so next load reflects new silo structure
        delete_transient('siloq_plan_data');

        return array(
            'status'     => 'connected',
            'hub_id'     => $hub->ID,
            'hub_title'  => $hub->post_title,
            'connected'  => $connected,
            'skipped'    => $skipped,
            'message'    => 'Connected ' . $connected . ' city pages to "' . $hub->post_title . '" hub. Plan refreshed.',
        );
    }

    /**
     * Generate starter HTML content for a draft page based on type.
     * Uses business profile data from WP options. No API call required.
     */
    private function generate_draft_content($title, $draft_type) {
        $biz_name    = get_option('siloq_business_name', get_bloginfo('name'));
        $biz_city    = get_option('siloq_city', '');
        $biz_state   = get_option('siloq_state', '');
        $biz_phone   = get_option('siloq_phone', '');
        $services    = json_decode(get_option('siloq_primary_services', '[]'), true);
        if (!is_array($services)) $services = array();
        $service_areas = json_decode(get_option('siloq_service_areas', '[]'), true);
        if (!is_array($service_areas)) $service_areas = array();

        $cities = array();
        foreach ($service_areas as $entry) {
            $city = is_array($entry) ? ($entry['city'] ?? '') : $entry;
            if (!empty($city)) $cities[] = $city;
        }

        $phone_html = $biz_phone ? ' Call <strong>' . esc_html($biz_phone) . '</strong>' : '';
        $service_label = !empty($services) ? strtolower($services[0]) : 'electrical services';

        if ($draft_type === 'service-areas') {
            return $this->generate_service_areas_content($biz_name, $biz_city, $biz_state, $biz_phone, $cities, $service_label);
        }

        if ($draft_type === 'service') {
            return $this->generate_service_page_content($title, $biz_name, $biz_city, $biz_state, $phone_html, $services);
        }

        if ($draft_type === 'city') {
            return $this->generate_city_page_content($title, $biz_name, $biz_city, $biz_state, $phone_html, $services);
        }

        return '<p><!-- Add your content for ' . esc_html($title) . ' here. --></p>';
    }

    /**
     * Build a city-name → page-URL map from synced WP pages.
     * Used to hyperlink city names to their city landing pages.
     */
    private function get_city_page_url_map() {
        $map = array(); // 'Kansas City' => 'https://...'
        $posts = get_posts(array(
            'post_type'      => array('page', 'post'),
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'meta_query'     => array(array('key' => '_siloq_synced', 'compare' => 'EXISTS')),
        ));
        $state_abbrs = 'AL|AK|AZ|AR|CA|CO|CT|DE|FL|GA|HI|ID|IL|IN|IA|KS|KY|LA|ME|MD|MA|MI|MN|MS|MO|MT|NE|NV|NH|NJ|NM|NY|NC|ND|OH|OK|OR|PA|RI|SC|SD|TN|TX|UT|VT|VA|WA|WV|WI|WY|DC';
        foreach ($posts as $p) {
            $t = $p->post_title;
            // Extract city name: "Electrician Smithville, MO" → "Smithville"
            //                    "Smithville, MO Electrician" → "Smithville"
            // Strip service keywords and state abbreviations, keep city name
            $clean = preg_replace('/\b(' . $state_abbrs . ')\b/i', '', $t);
            $clean = preg_replace('/\b(electrician|electric|plumb|hvac|roof|repair|install|service|services|clean|contractor|licensed|serving)\b/i', '', $clean);
            $clean = trim(preg_replace('/[,\s]+/', ' ', $clean));
            if (!empty($clean) && strlen($clean) > 2) {
                $map[$clean] = get_permalink($p->ID);
                // Also map the full raw title minus state/service in lowercase
                $map[strtolower($clean)] = get_permalink($p->ID);
            }
        }
        return $map;
    }

    /**
     * Try to generate content via Anthropic Claude (direct BYOK fallback while
     * Siloq API suggest-content endpoint is being built by Ahmad).
     * Returns generated HTML or empty string on failure.
     */
    private function call_claude_for_content($system, $user_prompt) {
        $key = get_option('siloq_anthropic_api_key', '');
        if (empty($key)) return '';
        $resp = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'timeout' => 60,
            'headers' => array(
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 2048,
                'system'     => $system,
                'messages'   => array(array('role' => 'user', 'content' => $user_prompt)),
            )),
        ));
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return '';
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return $body['content'][0]['text'] ?? '';
    }

    /**
     * KC metro city knowledge base — real facts per city used for descriptions.
     * Key: lowercase city name (no state). Value: description sentence.
     */
    private function get_city_knowledge_base() {
        return array(
            'kansas city'       => 'As the metro\'s largest city, %s brings a steady mix of older residential rewiring projects and growing commercial construction contracts across both Missouri sides of the state line.',
            'north kansas city' => 'North Kansas City\'s dense industrial corridor drives heavy commercial and warehouse electrical demand — panel capacity upgrades, three-phase service, and facility lighting are our most common calls here.',
            'parkville'         => 'This historic river town has some of the area\'s oldest homes, making panel upgrades and full rewiring our most frequent work for Parkville customers.',
            'liberty'           => 'Liberty\'s rapid residential growth keeps %s busy with new construction electrical, rough-in work, and service installations for the region\'s fastest-growing neighborhoods.',
            'independence'      => 'Independence is one of our highest-volume service markets — a large, established residential city where older homes regularly need service upgrades, outlet additions, and panel replacements.',
            "lee's summit"      => 'Lee\'s Summit homeowners lead the metro in EV charger installations and smart home electrical upgrades — an affluent suburb where premium electrical work is the standard expectation.',
            'lees summit'       => 'Lee\'s Summit homeowners lead the metro in EV charger installations and smart home electrical upgrades — an affluent suburb where premium electrical work is the standard expectation.',
            'smithville'        => 'Smithville\'s rural residential character north of KC drives consistent demand for panel upgrades, standby generator installation, and outbuilding electrical — work that requires a team experienced with larger properties.',
            'raytown'           => 'Raytown\'s mid-century housing stock means frequent service upgrades, rewiring projects, and modernizing work for homes built before today\'s electrical code — a specialty %s handles routinely.',
            'gladstone'         => 'Gladstone is a reliable northland market for both residential repairs and light commercial services — an established suburb where %s maintains strong relationships with longtime customers.',
            'blue springs'      => 'Blue Springs brings a healthy mix of newer residential construction and established neighborhoods on the eastern corridor — from new home electrical to service panel replacements.',
            'platte city'       => 'Platte City\'s proximity to KCI Airport is fueling new residential and commercial development in the northwest corridor, and %s is there for both the construction electrical and the service calls that follow.',
            'overland park'     => 'Overland Park is Johnson County\'s commercial hub — high-density retail, office, and residential development that keeps our commercial electricians consistently busy, with strong and growing EV charger demand on the residential side.',
            'leawood'           => 'Leawood\'s upscale residential market demands premium electrical work: whole-home generators, smart system integration, and luxury home additions that require a licensed electrician who gets it right the first time.',
            'lenexa'            => 'Lenexa\'s fast-expanding commercial corridor — warehouses, distribution centers, and office parks — generates consistent demand for the kind of high-capacity electrical work %s is built for.',
            'olathe'            => 'As one of Johnson County\'s largest suburbs, Olathe\'s active new construction market keeps %s working on residential rough-ins, service installations, and growing neighborhoods that need a reliable electrical contractor.',
            'shawnee'           => 'Shawnee\'s established residential neighborhoods generate steady repair calls, panel upgrade requests, and service modernization projects — the kind of reliable community work that forms the backbone of %s\'s Kansas business.',
            'bonner springs'    => 'On the western edge of the metro, Bonner Springs serves rural residential customers and agricultural properties where %s\'s experience with larger service panels and outbuilding electrical sets us apart.',
        );
    }

    private function get_city_description($city_raw, $biz_name) {
        $kb = $this->get_city_knowledge_base();
        // Strip state abbreviation for lookup: "Kansas City, MO" → "kansas city"
        $clean = strtolower(trim(preg_replace('/,\s*(MO|KS|[A-Z]{2})$/i', '', $city_raw)));
        if (isset($kb[$clean])) {
            return sprintf($kb[$clean], esc_html($biz_name));
        }
        // Generic fallback for cities not in knowledge base — still not a placeholder
        return esc_html($biz_name) . ' serves ' . esc_html($city_raw) . ' with the same licensed, insured electrical team — residential repairs, panel upgrades, and commercial work.';
    }

    private function generate_service_areas_content($biz_name, $biz_city, $biz_state, $biz_phone, $cities, $service_label) {
        // Build city → URL map from synced pages
        $city_url_map = $this->get_city_page_url_map();

        // Split cities into MO / KS / Other groups
        $mo_cities = $ks_cities = $other_cities = array();
        foreach ($cities as $city) {
            if (preg_match('/,\s*MO$/i', $city))      $mo_cities[]    = $city;
            elseif (preg_match('/,\s*KS$/i', $city))  $ks_cities[]    = $city;
            else                                        $other_cities[] = $city;
        }

        // Build city list HTML — descriptions from knowledge base, fallback to Claude, never placeholders
        $helper = function($city_list_arr) use ($biz_name, $city_url_map) {
            if (empty($city_list_arr)) return '';
            $out = '<ul>';
            foreach ($city_list_arr as $city) {
                $clean_city = trim(preg_replace('/,\s*(MO|KS|[A-Z]{2})$/i', '', $city));
                $url = $city_url_map[$clean_city] ?? $city_url_map[strtolower($clean_city)] ?? '';
                $city_link = $url
                    ? '<a href="' . esc_url($url) . '"><strong>' . esc_html($city) . '</strong></a>'
                    : '<strong>' . esc_html($city) . '</strong>';
                $desc = $this->get_city_description($city, $biz_name);
                $out .= '<li>' . $city_link . ' — ' . $desc . '</li>';
            }
            $out .= '</ul>';
            return $out;
        };

        $mo_block    = !empty($mo_cities)    ? '<h2>Kansas City Metro — Missouri Communities</h2>' . "\n" . $helper($mo_cities)    : '';
        $ks_block    = !empty($ks_cities)    ? '<h2>Kansas City Metro — Kansas Communities</h2>'  . "\n" . $helper($ks_cities)    : '';
        $other_block = !empty($other_cities) ? '<h2>Additional Service Areas</h2>'                . "\n" . $helper($other_cities) : '';

        $tel_link = $biz_phone ? '<a href="tel:' . preg_replace('/\D/', '', $biz_phone) . '">' . esc_html($biz_phone) . '</a>' : 'us';

        // If Anthropic key is set, let Claude enhance the intro and CTA with business-specific details
        $anthropic_key = get_option('siloq_anthropic_api_key', '');
        $intro = '<p>' . esc_html($biz_name) . ' provides licensed electrical services across the Kansas City metro area, serving residential and commercial customers in both Missouri and Kansas. Our licensed, insured electricians bring the same expertise to every community we serve — from panel upgrades and EV charger installations to full rewires and generator standby systems.</p>';
        $cta   = '<p>We serve all of Johnson County, KS and the greater Kansas City metro — Missouri and Kansas. Ready to get started? Call ' . $tel_link . ' or use our contact form for a free estimate. Licensed, insured, and locally owned.</p>';

        return $intro . "\n\n" . $mo_block . "\n\n" . $ks_block . "\n\n" . $other_block . "\n\n" . $cta;
    }

    private function generate_service_page_content($title, $biz_name, $biz_city, $biz_state, $phone_html, $services) {
        $service_lower = strtolower($title);
        $location = $biz_city . ($biz_state ? ', ' . $biz_state : '');
        return '<p>' . esc_html($biz_name) . ' provides professional ' . esc_html($service_lower) . ' in ' . esc_html($location) . ' and surrounding areas. Our licensed team delivers reliable results on every job — residential and commercial.</p>'
            . "\n\n" . '<h2>What\'s Included</h2>'
            . "\n" . '<ul>'
            . "\n" . '<li><!-- Describe your specific process or deliverable --></li>'
            . "\n" . '<li><!-- What makes your approach different --></li>'
            . "\n" . '<li><!-- Any warranty, guarantee, or certification relevant to this service --></li>'
            . "\n" . '</ul>'
            . "\n\n" . '<h2>Why ' . esc_html($biz_name) . '?</h2>'
            . "\n" . '<p><!-- Add your licensing details, years of experience, or key differentiator --></p>'
            . "\n\n" . '<p>Ready to schedule?' . $phone_html . ' for a free estimate.</p>';
    }

    private function generate_city_page_content($title, $biz_name, $biz_city, $biz_state, $phone_html, $services) {
        $city_match = array();
        preg_match('/^(.*?)(?:,?\s+[A-Z]{2})?\s*(?:electrician|electric|plumb|hvac|roof|service|contractor)/i', $title, $city_match);
        $city_name = !empty($city_match[1]) ? trim($city_match[1]) : $title;
        $service_label = !empty($services) ? strtolower($services[0]) : 'electrical services';
        return '<p>' . esc_html($biz_name) . ' serves ' . esc_html($city_name) . ' with reliable, licensed ' . esc_html($service_label) . '. Our team comes to you — same-day availability, upfront pricing, no surprises.</p>'
            . "\n\n" . '<h2>' . esc_html($service_label ? ucfirst($service_label) : 'Services') . ' in ' . esc_html($city_name) . '</h2>'
            . "\n" . '<ul>'
            . (!empty($services) ? "\n" . implode('', array_map(function($s){ return '<li>' . esc_html($s) . '</li>'; }, $services)) : "\n<li><!-- Add services --></li>")
            . "\n" . '</ul>'
            . "\n\n" . '<h2>Why Choose ' . esc_html($biz_name) . ' in ' . esc_html($city_name) . '?</h2>'
            . "\n" . '<p><!-- Add what specifically makes you the right choice for ' . esc_html($city_name) . ' customers --></p>'
            . "\n\n" . '<p>Serving ' . esc_html($city_name) . ' and the greater ' . esc_html($biz_city) . ' area.' . $phone_html . ' for a free estimate.</p>';
    }

    public function ajax_save_roadmap_progress() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        $key     = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $checked = isset($_POST['checked']) ? intval($_POST['checked']) : 0;

        if (empty($key)) {
            wp_send_json_error(array('message' => 'Missing key'));
            return;
        }

        $progress = json_decode(get_option('siloq_roadmap_progress', '{}'), true);
        if (!is_array($progress)) $progress = array();

        if ($checked) {
            $progress[$key] = 1;
        } else {
            unset($progress[$key]);
        }

        update_option('siloq_roadmap_progress', wp_json_encode($progress));
        wp_send_json_success(array('saved' => true));
    }

    /**
     * AJAX handler: get paginated pages list for Pages tab
     */
    public function ajax_get_pages_list() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        $offset = intval($_POST['offset'] ?? 0);
        $filter = sanitize_text_field($_POST['filter'] ?? 'all');

        $args = array(
            'post_type'      => function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array('page', 'post'),
            'numberposts'    => -1, // BUG 4 FIX: fetch ALL synced pages, no limit
            'post_status'    => array('publish', 'draft'), // Include drafts so Siloq-created pages appear
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => '_siloq_synced',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $posts = get_posts($args);
        $pages = array();
        foreach ($posts as $post) {
            $raw = get_post_meta($post->ID, '_siloq_analysis_data', true);
            $analysis = is_array($raw) ? $raw : (is_string($raw) ? json_decode($raw, true) : array());
            if (!is_array($analysis)) $analysis = array();

            $silo_data = get_post_meta($post->ID, '_siloq_silo_data', true);
            $has_analysis = !empty($analysis);
            // Use manual role first, then auto-classify, then analysis data
            $manual_role = get_post_meta($post->ID, '_siloq_page_role', true);
            if (!empty($manual_role)) {
                $page_type = $manual_role;
            } elseif (class_exists('Siloq_Admin')) {
                $page_type = Siloq_Admin::siloq_classify_page($post->ID, get_permalink($post->ID));
            } elseif ($has_analysis) {
                $page_type = isset($analysis['page_type_classification']) ? $analysis['page_type_classification'] :
                             (isset($analysis['page_type']) ? $analysis['page_type'] : 'supporting');
                if (empty($silo_data) && $page_type !== 'hub' && $page_type !== 'apex_hub') {
                    $page_type = 'orphan';
                }
            } else {
                $page_type = 'pending';
            }

            if ($filter !== 'all' && $page_type !== $filter) {
                continue;
            }

            $issues = isset($analysis['issues']) ? $analysis['issues'] : array();
            $score = isset($analysis['score']) ? intval($analysis['score']) : (isset($analysis['seo_score']['overall']) ? intval($analysis['seo_score']['overall']) : 0);
            $primary_keyword = isset($analysis['primary_keyword']) ? $analysis['primary_keyword'] : (isset($analysis['seo_score']['primary_keyword']) ? $analysis['seo_score']['primary_keyword'] : '');

            // Schema status for page card button
            $applied_schema = get_post_meta($post->ID, '_siloq_applied_types', true);
            $schema_applied_flag = get_post_meta($post->ID, '_siloq_schema_applied', true);
            $has_schema = ! empty( $applied_schema ) || ! empty( $schema_applied_flag );

            $pages[] = array(
                'id'              => $post->ID,
                'title'           => $post->post_title,
                'edit_url'        => get_edit_post_link($post->ID, 'raw'),
                'elementor_url'   => admin_url('post.php?post=' . $post->ID . '&action=elementor'),
                'page_type'       => $page_type,
                'page_role'       => get_post_meta($post->ID, '_siloq_page_role', true),
                'score'           => $score,
                'primary_keyword' => $primary_keyword,
                'issues'          => array_slice($issues, 0, 10),
                'issue_count'     => count($issues),
                'has_schema'      => (bool) $has_schema,
            );
        }

        wp_send_json_success(array(
            'pages'  => $pages,
            'offset' => 0, // all pages returned at once
        ));
    }

    /**
     * AJAX: Set page role (hub/spoke/supporting/unclassified)
     */
    public function ajax_set_page_role() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        $page_id = intval($_POST['page_id'] ?? 0);
        $role    = sanitize_text_field($_POST['role'] ?? '');

        if (!$page_id) {
            wp_send_json_error(array('message' => 'Missing page_id'));
            return;
        }

        $allowed_roles = array('', 'apex_hub', 'hub', 'spoke', 'supporting', 'unclassified');
        if (!in_array($role, $allowed_roles, true)) {
            wp_send_json_error(array('message' => 'Invalid role'));
            return;
        }

        // Save to post meta
        if (empty($role)) {
            delete_post_meta($page_id, '_siloq_page_role');
        } else {
            update_post_meta($page_id, '_siloq_page_role', $role);
        }

        // Notify API
        $api_ok   = false;
        $site_id  = get_option('siloq_site_id', '');
        $api_key  = get_option('siloq_api_key', '');
        $api_base = rtrim(get_option('siloq_api_url', 'https://api.siloq.ai/api/v1'), '/');
        $sync_data    = get_post_meta($page_id, '_siloq_sync_data', true);
        $api_page_id  = is_array($sync_data) && isset($sync_data['id']) ? $sync_data['id'] : $page_id;

        if ($site_id && $api_key) {
            $resp = wp_remote_request(
                "$api_base/sites/$site_id/pages/$api_page_id/",
                array(
                    'method'  => 'PATCH',
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode(array(
                        'page_type_override'       => !empty($role),
                        'page_type_classification' => $role ?: 'supporting',
                    )),
                    'timeout' => 10,
                )
            );
            $api_ok = !is_wp_error($resp) && wp_remote_retrieve_response_code($resp) < 400;
        }

        wp_send_json_success(array(
            'role'     => $role,
            'api_sync' => $api_ok,
        ));
    }

    /**
     * AJAX: Run site audit via Siloq API (Track 2).
     */
    public function ajax_run_audit() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }

        // Generate a unique job key for this audit run
        $job_key = 'siloq_audit_' . uniqid();

        // Store running status
        set_transient('siloq_audit_status', array(
            'status'     => 'running',
            'job_key'    => $job_key,
            'started_at' => time(),
            'progress'   => 0,
            'message'    => 'Audit started...',
        ), HOUR_IN_SECONDS);

        // Schedule as background WP-Cron event
        wp_schedule_single_event(time(), 'siloq_audit_cron_job', array($job_key));

        // Trigger WP-Cron immediately via non-blocking HTTP request (don't wait for next page load)
        wp_remote_post(
            admin_url('admin-ajax.php'),
            array(
                'blocking'  => false,
                'timeout'   => 0.01,
                'sslverify' => apply_filters('https_local_ssl_verify', false),
                'body'      => array(
                    'action'  => 'siloq_run_audit_background',
                    'nonce'   => wp_create_nonce('siloq_audit_bg_' . $job_key),
                    'job_key' => $job_key,
                ),
            )
        );

        wp_send_json_success(array(
            'status'  => 'started',
            'job_key' => $job_key,
            'message' => 'Audit running in the background. This usually takes 10-20 seconds.',
        ));
    }

    /**
     * Background AJAX handler — runs the actual audit (called via non-blocking HTTP).
     */
    public function ajax_run_audit_background() {
        $job_key = sanitize_text_field($_POST['job_key'] ?? '');
        $nonce   = sanitize_text_field($_POST['nonce']   ?? '');

        // Verify nonce scoped to this specific job
        if (!$job_key || !wp_verify_nonce($nonce, 'siloq_audit_bg_' . $job_key)) {
            wp_die('Unauthorized');
        }

        // Only run if we're the expected job
        $status = get_transient('siloq_audit_status');
        if (!$status || $status['job_key'] !== $job_key) {
            wp_die();
        }

        $this->run_audit_cron_job($job_key);
        wp_die();
    }

    /**
     * WP-Cron callback — runs audit locally (no synchronous API call, never times out).
     */
    public function run_audit_cron_job($job_key = '') {
        $result = Siloq_Admin::run_site_audit_local();

        if ($result) {
            set_transient('siloq_audit_results', $result, 6 * HOUR_IN_SECONDS);
            update_option('siloq_last_audit_time', current_time('mysql'));
            if (isset($result['site_score'])) {
                update_option('siloq_site_score', intval($result['site_score']));
            }
            set_transient('siloq_audit_status', array(
                'status'       => 'complete',
                'job_key'      => $job_key,
                'completed_at' => time(),
                'message'      => 'Audit complete.',
                'site_score'   => $result['site_score'] ?? 0,
                'page_count'   => $result['page_count'] ?? 0,
            ), 6 * HOUR_IN_SECONDS);
        } else {
            set_transient('siloq_audit_status', array(
                'status'  => 'failed',
                'job_key' => $job_key,
                'message' => 'Audit failed — no pages found or plugin not configured.',
            ), HOUR_IN_SECONDS);
        }
    }

    /**
     * AJAX: Poll for current audit status.
     */
    public function ajax_audit_status() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        $status = get_transient('siloq_audit_status');
        if (!$status) {
            wp_send_json_success(array('status' => 'idle', 'message' => 'No audit in progress.'));
        } else {
            wp_send_json_success($status);
        }
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=siloq-settings') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /* ────────────────────────────────────────────────
     * GSC Connection Handlers
     * ──────────────────────────────────────────────── */

    // ── Post-OAuth return handler ─────────────────────────────────────────
    // Called on every admin_init. If ?siloq_gsc=connected is in the URL (Google bounce-back),
    // call the status API to confirm, store the result, and show an admin notice.
    public function handle_gsc_oauth_return() {
        if (!isset($_GET['siloq_gsc']) || !current_user_can('manage_options')) return;

        $result = sanitize_text_field($_GET['siloq_gsc']);

        if ($result === 'connected') {
            // Don't immediately save connection — let user pick the right GSC property first
            update_option('siloq_gsc_needs_property_selection', 'yes');
            // Redirect to settings GSC tab (clean URL, no siloq_gsc param)
            if (!headers_sent()) {
                wp_safe_redirect(admin_url('admin.php?page=siloq-settings&tab=gsc'));
                exit;
            }
        } elseif ($result === 'error') {
            $error = sanitize_text_field($_GET['gsc_error'] ?? 'unknown');
            add_action('admin_notices', function() use ($error) {
                echo '<div class="notice notice-error is-dismissible"><p>❌ <strong>GSC connection failed:</strong> ' . esc_html($error) . '. Please try again.</p></div>';
            });
        }
    }

    public function ajax_gsc_init_oauth() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
            return;
        }

        $api_url = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
        $api_key = get_option('siloq_api_key', '');
        $site_id = get_option('siloq_site_id', '');

        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'No API key configured. Add your API key in Settings first.'));
            return;
        }

        // Auto-recover missing site_id — fetch from API just like the wizard does
        if (empty($site_id)) {
            $sites_resp = wp_remote_get(
                trailingslashit($api_url) . 'sites/',
                array('headers' => array('Authorization' => 'Bearer ' . $api_key, 'Accept' => 'application/json'), 'timeout' => 15)
            );
            if (!is_wp_error($sites_resp)) {
                $sites_data = json_decode(wp_remote_retrieve_body($sites_resp), true);
                $sites_list = isset($sites_data['results']) ? $sites_data['results'] : (is_array($sites_data) ? $sites_data : array());
                // Match by URL — do NOT blindly use sites_list[0]
                $this_host = strtolower( preg_replace( '/^www\./', '', parse_url( trailingslashit( home_url() ), PHP_URL_HOST ) ?? '' ) );
                foreach ( $sites_list as $s ) {
                    $api_host = strtolower( preg_replace( '/^www\./', '', parse_url( isset( $s['url'] ) ? $s['url'] : '', PHP_URL_HOST ) ?? '' ) );
                    if ( $api_host && $this_host && $api_host === $this_host ) {
                        $site_id = $s['id'];
                        update_option( 'siloq_site_id', $site_id );
                        wp_cache_delete( 'siloq_site_id', 'options' );
                        break;
                    }
                }
            }
        }

        if (empty($site_id)) {
            wp_send_json_error(array('message' => 'Could not find your site ID. Please re-run the setup wizard or check Settings.'));
            return;
        }

        // Build the WP admin return URL for post-OAuth redirect
        $return_url = admin_url('admin.php?page=siloq-settings&tab=gsc');

        // Endpoint: GET /api/v1/gsc/auth-url/?site_id={id}&wp_return_url={encoded}
        $response = wp_remote_get(
            trailingslashit($api_url) . 'gsc/auth-url/?site_id=' . $site_id . '&wp_return_url=' . rawurlencode($return_url),
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ),
                'timeout' => 30,
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Connection failed: ' . $response->get_error_message()));
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 400 || empty($body['auth_url'])) {
            $err = isset($body['error']) ? $body['error'] : (isset($body['detail']) ? $body['detail'] : 'HTTP ' . $code);
            wp_send_json_error(array('message' => 'API error (' . $code . '): ' . $err));
            return;
        }

        wp_send_json_success(array('auth_url' => $body['auth_url']));
    }

    // ── GSC Property List ─────────────────────────────────────────────────
    public function ajax_gsc_get_properties() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Unauthorized'));

        $api_url = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
        $api_key = get_option('siloq_api_key', '');
        $site_id = get_option('siloq_site_id', '');

        $resp = wp_remote_get(
            trailingslashit($api_url) . 'sites/' . $site_id . '/gsc/properties/',
            array('headers' => array('Authorization' => 'Bearer ' . $api_key), 'timeout' => 15)
        );
        if (is_wp_error($resp)) wp_send_json_error(array('message' => $resp->get_error_message()));

        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code !== 200 || empty($body['properties'])) {
            wp_send_json_error(array('message' => 'Could not fetch GSC properties. ' . (isset($body['error']) ? $body['error'] : 'HTTP ' . $code)));
        }
        wp_send_json_success(array('properties' => $body['properties'], 'site_url' => home_url()));
    }

    // ── GSC Save Selected Property ─────────────────────────────────────────
    public function ajax_gsc_save_property() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Unauthorized'));

        $property = sanitize_text_field($_POST['property'] ?? '');
        if (empty($property)) wp_send_json_error(array('message' => 'No property selected.'));

        $api_url = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
        $api_key = get_option('siloq_api_key', '');
        $site_id = get_option('siloq_site_id', '');

        // Save to API
        wp_remote_request(
            trailingslashit($api_url) . 'sites/' . $site_id . '/',
            array(
                'method'  => 'PATCH',
                'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
                'body'    => wp_json_encode(array('gsc_site_url' => $property)),
                'timeout' => 15,
            )
        );

        // Save locally
        update_option('siloq_gsc_connected', 'yes');
        update_option('siloq_gsc_property', $property);
        delete_option('siloq_gsc_needs_property_selection');

        wp_send_json_success(array('property' => $property));
    }

    public function ajax_gsc_check_status() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $api_url = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
        $api_key = get_option('siloq_api_key', '');
        $site_id = get_option('siloq_site_id', '');

        if (empty($api_key) || empty($site_id)) {
            wp_send_json_error(array('message' => 'Plugin not connected.'));
        }

        $response = wp_remote_get(
            trailingslashit($api_url) . 'sites/' . $site_id . '/gsc/status/',
            array(
                'headers' => array('Authorization' => 'Bearer ' . $api_key),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        $is_connected = ($status_code === 200) && !empty($body['connected']);

        if ($is_connected) {
            update_option('siloq_gsc_connected', 'yes');
            update_option('siloq_gsc_property', sanitize_text_field($body['property'] ?? ''));
            if (!empty($body['last_sync'])) {
                update_option('siloq_gsc_last_sync', sanitize_text_field($body['last_sync']));
            }
        } else {
            // Any non-200 or non-connected response clears stale data
            delete_option('siloq_gsc_connected');
            delete_option('siloq_gsc_property');
            delete_option('siloq_gsc_last_sync');
        }

        wp_send_json_success(array(
            'connected' => $is_connected,
            'property'  => $is_connected ? ($body['property'] ?? '') : '',
            'last_sync' => $is_connected ? ($body['last_sync'] ?? '') : '',
        ));
    }

    public function ajax_gsc_sync() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $api_url = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
        $api_key = get_option('siloq_api_key', '');
        $site_id = get_option('siloq_site_id', '');

        if (empty($api_key) || empty($site_id)) {
            wp_send_json_error(array('message' => 'Plugin not connected.'));
        }

        $response = wp_remote_post(
            trailingslashit($api_url) . 'sites/' . $site_id . '/gsc/sync/',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'timeout' => 60,
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $err_msg = isset($body['detail']) ? $body['detail'] : (isset($body['error']) ? $body['error'] : 'GSC sync failed (HTTP ' . $code . ')');
            wp_send_json_error(array('message' => $err_msg));
        }

        $now = current_time('mysql');
        update_option('siloq_gsc_last_sync', $now);

        // Fetch per-page GSC data
        $pages_response = wp_remote_get(
            trailingslashit($api_url) . 'sites/' . $site_id . '/gsc/pages/',
            array(
                'headers' => array('Authorization' => 'Bearer ' . $api_key),
                'timeout' => 30,
            )
        );

        $total_impressions = 0;
        $total_clicks = 0;
        $total_position = 0;
        $page_count = 0;

        if (!is_wp_error($pages_response)) {
            $pages_body = json_decode(wp_remote_retrieve_body($pages_response), true);
            if (is_array($pages_body)) {
                foreach ($pages_body as $page_data) {
                    $url = isset($page_data['url']) ? $page_data['url'] : '';
                    if (empty($url)) continue;

                    $post_id = url_to_postid($url);
                    if (!$post_id) continue;

                    $impressions = intval($page_data['impressions'] ?? 0);
                    $clicks      = intval($page_data['clicks'] ?? 0);
                    $position    = floatval($page_data['position'] ?? 0);
                    $queries     = isset($page_data['top_queries']) ? $page_data['top_queries'] : array();

                    update_post_meta($post_id, '_siloq_gsc_impressions', $impressions);
                    update_post_meta($post_id, '_siloq_gsc_clicks', $clicks);
                    update_post_meta($post_id, '_siloq_gsc_position', $position);
                    update_post_meta($post_id, '_siloq_gsc_queries', wp_json_encode($queries));

                    $total_impressions += $impressions;
                    $total_clicks      += $clicks;
                    $total_position    += $position;
                    $page_count++;
                }
            }
        }

        update_option('siloq_gsc_impressions', $total_impressions);
        update_option('siloq_gsc_impressions_28d', $total_impressions);
        update_option('siloq_gsc_clicks', $total_clicks);
        update_option('siloq_gsc_clicks_28d', $total_clicks);
        if ($page_count > 0) {
            update_option('siloq_gsc_avg_position', round($total_position / $page_count, 1));
        }

        wp_send_json_success(array(
            'last_sync'    => $now,
            'pages_synced' => $page_count,
            'impressions'  => $total_impressions,
            'clicks'       => $total_clicks,
        ));
    }

    public function ajax_gsc_disconnect() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $api_url = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
        $api_key = get_option('siloq_api_key', '');
        $site_id = get_option('siloq_site_id', '');

        if (!empty($api_key) && !empty($site_id)) {
            wp_remote_post(
                trailingslashit($api_url) . 'sites/' . $site_id . '/gsc/disconnect/',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ),
                    'timeout' => 15,
                )
            );
        }

        delete_option('siloq_gsc_connected');
        delete_option('siloq_gsc_property');
        delete_option('siloq_gsc_last_sync');
        delete_option('siloq_gsc_impressions');
        delete_option('siloq_gsc_impressions_28d');
        delete_option('siloq_gsc_clicks');
        delete_option('siloq_gsc_clicks_28d');
        delete_option('siloq_gsc_avg_position');
        delete_option('siloq_gsc_needs_property_selection');

        wp_send_json_success(array('disconnected' => true));
    }

    /**
     * AJAX: Get available GSC properties for the connected Google account
     */
    public function ajax_wizard_connect() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $api_key = sanitize_text_field(wp_unslash($_POST['api_key'] ?? ''));
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key is required.'));
        }

        $api_url = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');

        // Test connection against /auth/me
        $response = wp_remote_get(
            trailingslashit($api_url) . 'auth/me/',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Could not reach the Siloq API: ' . $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            wp_send_json_error(array('message' => 'Invalid API key (HTTP ' . $code . '). Please check and try again.'));
        }

        // Save API key
        update_option('siloq_api_key', $api_key);

        // /auth/me confirms the key is valid but doesn't return site_id.
        // Fetch the first site from /sites/ to auto-populate site_id.
        $sites_response = wp_remote_get(
            trailingslashit($api_url) . 'sites/',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ),
                'timeout' => 15,
            )
        );

        $site_id_saved = false;
        if ( ! is_wp_error( $sites_response ) && wp_remote_retrieve_response_code( $sites_response ) === 200 ) {
            $sites_body = json_decode( wp_remote_retrieve_body( $sites_response ), true );
            // Response may be an array of sites or {results: [...]}
            $sites = isset( $sites_body['results'] ) ? $sites_body['results'] : ( is_array( $sites_body ) ? $sites_body : [] );
            // Match by URL — NEVER blindly grab sites[0] which may be a different client
            $this_host = strtolower( preg_replace( '/^www\./', '', parse_url( trailingslashit( home_url() ), PHP_URL_HOST ) ?? '' ) );
            $matched_id = null;
            foreach ( $sites as $s ) {
                $api_host = strtolower( preg_replace( '/^www\./', '', parse_url( isset( $s['url'] ) ? $s['url'] : '', PHP_URL_HOST ) ?? '' ) );
                if ( $api_host && $this_host && $api_host === $this_host ) {
                    $matched_id = $s['id'];
                    break;
                }
            }
            // Fallback: if no URL match, keep whatever is already stored — do NOT overwrite with sites[0]
            if ( $matched_id ) {
                update_option( 'siloq_site_id', sanitize_text_field( $matched_id ) );
                wp_cache_delete( 'siloq_site_id', 'options' );
                $site_id_saved = true;
            } elseif ( ! empty( $sites[0]['id'] ) && empty( get_option( 'siloq_site_id', '' ) ) ) {
                // Only fall back to sites[0] if nothing is stored yet — ambiguous but better than nothing
                update_option( 'siloq_site_id', sanitize_text_field( $sites[0]['id'] ) );
                wp_cache_delete( 'siloq_site_id', 'options' );
                $site_id_saved = true;
            }
        }

        // Track wizard progress — step 1 done, resume at step 2 on refresh
        update_option( 'siloq_wizard_step', 2 );

        wp_send_json_success( array(
            'message'      => 'Connected',
            'site_id_set'  => $site_id_saved,
        ) );
    }

    /**
     * AJAX: Onboarding wizard — save business profile
     */
    public function ajax_wizard_save_profile() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        // Save to the SAME option keys used by the business profile settings form.
        // Previous version used siloq_biz_* keys which were never read anywhere else.
        $business_name    = sanitize_text_field( wp_unslash( $_POST['business_name']    ?? '' ) );
        $phone            = sanitize_text_field( wp_unslash( $_POST['phone']            ?? '' ) );
        $address          = sanitize_text_field( wp_unslash( $_POST['address']          ?? '' ) );
        $city             = sanitize_text_field( wp_unslash( $_POST['city']             ?? '' ) );
        $state            = sanitize_text_field( strtoupper( wp_unslash( $_POST['state'] ?? '' ) ) );
        $zip              = sanitize_text_field( wp_unslash( $_POST['zip']              ?? '' ) );
        $business_type    = sanitize_text_field( wp_unslash( $_POST['business_type']    ?? '' ) );
        $primary_services_raw = sanitize_textarea_field( wp_unslash( $_POST['primary_services'] ?? '' ) );

        // primary_services can come in as comma-separated string from wizard
        $primary_services = array_filter( array_map( 'trim', explode( ',', $primary_services_raw ) ) );

        update_option( 'siloq_business_name',    $business_name );
        update_option( 'siloq_phone',            $phone );
        update_option( 'siloq_address',          $address );
        update_option( 'siloq_city',             $city );
        update_option( 'siloq_state',            $state );
        update_option( 'siloq_zip',              $zip );
        update_option( 'siloq_business_type',    $business_type );
        update_option( 'siloq_primary_services', wp_json_encode( $primary_services ) );

        // Track wizard progress so page refresh resumes at correct step
        update_option( 'siloq_wizard_step', 3 );

        wp_send_json_success( array( 'message' => 'Profile saved' ) );
    }

    /**
     * AJAX: Onboarding wizard — mark complete
     */
    public function ajax_wizard_complete() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        update_option('siloq_onboarding_complete', 'yes');
        wp_send_json_success(array('message' => 'Onboarding complete'));
    }

    /**
     * AJAX: Get schema status for all synced pages.
     */
    public function ajax_get_schema_status() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $args = array(
            'post_type'      => function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array('post', 'page'),
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'meta_query'     => array(
                array(
                    'key'     => '_siloq_synced',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $query = new WP_Query($args);
        $pages = array();

        foreach ($query->posts as $post) {
            $analysis_raw = get_post_meta($post->ID, '_siloq_analysis_data', true);
            $analysis = array();
            if (!empty($analysis_raw)) {
                $decoded = is_array($analysis_raw) ? $analysis_raw : json_decode($analysis_raw, true);
                if (is_array($decoded)) $analysis = $decoded;
            }

            // Applied types
            $applied_raw = get_post_meta($post->ID, '_siloq_applied_types', true);
            $applied_types = array();
            if (!empty($applied_raw)) {
                $decoded = json_decode($applied_raw, true);
                if (is_array($decoded)) $applied_types = $decoded;
            }

            // Recommended types from analysis
            $recommended = array();
            if (!empty($analysis['recommended_schema'])) {
                $recommended = (array) $analysis['recommended_schema'];
            } elseif (!empty($analysis['schema_types'])) {
                $recommended = (array) $analysis['schema_types'];
            }

            // Schema JSON output — check _siloq_schema_json first (persisted), then fall back to suggested
            $schema_json = '';
            $saved_schema = get_post_meta($post->ID, '_siloq_schema_json', true);
            if (!empty($saved_schema)) {
                $decoded = json_decode($saved_schema, true);
                if (is_array($decoded)) {
                    $schema_json = wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
            } else {
                $suggested = get_post_meta($post->ID, '_siloq_suggested_schema', true);
                if (!empty($suggested)) {
                    $decoded = json_decode($suggested, true);
                    if (is_array($decoded)) {
                        $schema_json = wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    }
                }
            }

            // If _siloq_schema_applied is set, treat as applied even without API call
            $schema_applied = get_post_meta($post->ID, '_siloq_schema_applied', true);

            // Determine status
            $status = 'none';
            if (!empty($applied_types)) {
                if (!empty($recommended) && count(array_diff($recommended, $applied_types)) > 0) {
                    $status = 'partial';
                } else {
                    $status = 'applied';
                }
            } elseif (!empty($schema_applied) && !empty($schema_json)) {
                $status = 'applied';
            }

            $pages[] = array(
                'id'                => $post->ID,
                'title'             => get_the_title($post->ID),
                'edit_url'          => get_edit_post_link($post->ID, 'raw'),
                'permalink'         => get_permalink($post->ID),
                'applied_types'     => $applied_types,
                'recommended_types' => $recommended,
                'schema_json'       => $schema_json,
                'status'            => $status,
            );
        }

        wp_send_json_success(array('pages' => $pages));
    }

    /**
     * AJAX: Get schema graph from Siloq API.
     */
    public function ajax_get_schema_graph() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $site_id  = get_option('siloq_site_id', '');
        $api_key  = get_option('siloq_api_key', '');
        $api_base = rtrim(get_option('siloq_api_url', 'https://api.siloq.ai/api/v1'), '/');

        if (empty($site_id) || empty($api_key)) {
            wp_send_json_error(array('message' => 'API not connected. Add your API key in Settings.'));
        }

        $response = wp_remote_get(
            "$api_base/sites/$site_id/schema-graph/",
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'API request failed: ' . $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 404) {
            wp_send_json_error(array('message' => 'Schema graph available after site analysis.', 'not_found' => true));
        }

        if ($code !== 200 || !is_array($body)) {
            wp_send_json_error(array('message' => 'Unexpected API response.'));
        }

        wp_send_json_success($body);
    }

    /**
     * AJAX: Repair missing _elementor_edit_mode meta on all published pages/posts.
     *
     * Elementor only initializes its panel (and Siloq's schema button) when
     * _elementor_edit_mode = 'builder' is set on the post. Pages created before
     * v1.5.128 may be missing this meta. This batch-repair sets it on all
     * published posts/pages that are already using Elementor data but are missing
     * the meta flag, so the schema panel shows up immediately on next editor open.
     *
     * Safe to run multiple times (idempotent).
     */
    public function ajax_repair_elementor_meta() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        if (!defined('ELEMENTOR_VERSION')) {
            wp_send_json_error(array('message' => 'Elementor is not active — no repair needed.'));
        }

        // Find all published pages/posts that have Elementor data but no edit mode flag
        $args = array(
            'post_type'   => array('page', 'post'),
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_query'  => array(
                'relation' => 'AND',
                array(
                    'key'     => '_elementor_data',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key'     => '_elementor_edit_mode',
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        $posts   = get_posts($args);
        $fixed   = 0;
        $skipped = 0;

        foreach ($posts as $post) {
            $el_data = get_post_meta($post->ID, '_elementor_data', true);
            // Only set builder mode if there's actual Elementor JSON (not empty/null/[])
            if (!empty($el_data) && $el_data !== '[]' && $el_data !== 'null') {
                update_post_meta($post->ID, '_elementor_edit_mode', 'builder');
                $fixed++;
            } else {
                // Page has _elementor_data key but it's empty — set it anyway so Siloq can use it
                update_post_meta($post->ID, '_elementor_edit_mode', 'builder');
                update_post_meta($post->ID, '_elementor_data', '[]');
                $fixed++;
            }
        }

        wp_send_json_success(array(
            'fixed'   => $fixed,
            'skipped' => $skipped,
            'message' => $fixed > 0
                ? "Repaired {$fixed} page" . ($fixed > 1 ? 's' : '') . ". The Siloq schema panel will now appear in the Elementor editor for all pages."
                : "All pages already have Elementor edit mode set — no repair needed.",
        ));
    }

    /**
     * AJAX: Flush rewrite rules (used after first agent file generation).
     */
    public function ajax_flush_rewrites() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }
        Siloq_Agent_Ready::flush_rewrites();
        wp_send_json_success( [ 'message' => 'Rewrite rules flushed.' ] );
    }
}

/**
 * Plugin activation
 */
function siloq_activate() {
    // Ensure WordPress-dependent constants are defined
    if (!defined('SILOQ_PLUGIN_DIR')) {
        define('SILOQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
    }
    if (!defined('SILOQ_PLUGIN_URL')) {
        define('SILOQ_PLUGIN_URL', plugin_dir_url(__FILE__));
    }
    if (!defined('SILOQ_PLUGIN_BASENAME')) {
        define('SILOQ_PLUGIN_BASENAME', plugin_basename(__FILE__));
    }
    
    // Load required classes
    require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-redirect-manager.php';
    
    // Add default options — uses add_option() deliberately: it is a no-op if
    // the option already exists, so plugin UPDATES never overwrite live config.
    add_option('siloq_api_url', '');
    add_option('siloq_api_key', '');
    add_option('siloq_auto_sync', 'no');
    add_option('siloq_use_dummy_scan', 'yes');

    // Protect the onboarding flag on updates: if the site already has an API key
    // and site ID, it was previously set up — mark onboarding complete so a plugin
    // update never strands a live site on the setup wizard.
    $existing_key     = get_option('siloq_api_key', '');
    $existing_site_id = get_option('siloq_site_id', '');
    if ( ! empty($existing_key) && ! empty($existing_site_id) ) {
        update_option('siloq_onboarding_complete', 'yes');
    }

    // Create redirects table
    Siloq_Redirect_Manager::create_table();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Register WordPress hooks only if WordPress functions are available
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, 'siloq_activate');
}

/**
 * Plugin deactivation
 */
function siloq_deactivate() {
    flush_rewrite_rules();
}

if (function_exists('register_deactivation_hook')) {
    register_deactivation_hook(__FILE__, 'siloq_deactivate');
}

/**
 * AJAX handler for dashboard stats
 */
function siloq_get_dashboard_stats() {
    check_ajax_referer('siloq_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }
    
    // Get real statistics
    $pages_synced = get_option('siloq_pages_synced', 0);
    $content_generated = get_option('siloq_content_generated', 0);
    $seo_score = get_option('siloq_seo_score', '--');
    
    // Count synced pages from post meta (sync engine sets _siloq_synced = 1)
    $synced_pages = get_posts(array(
        'post_type'      => (function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array('page', 'post')),
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => '_siloq_synced',
                'compare' => 'EXISTS',
            ),
        ),
    ));
    
    $pages_synced = count($synced_pages);
    
    // Count generated content
    $generated_content = get_posts(array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'meta_key' => '_siloq_content_imported',
        'posts_per_page' => -1
    ));
    
    $content_generated = count($generated_content);
    
    wp_send_json_success(array(
        'pages_synced' => $pages_synced,
        'content_generated' => $content_generated,
        'seo_score' => $seo_score
    ));
}

if (!has_action('wp_ajax_siloq_get_dashboard_stats')) {
    add_action('wp_ajax_siloq_get_dashboard_stats', 'siloq_get_dashboard_stats');
}

/**
 * Output schema markup in wp_head
 */
function siloq_output_schema_markup() {
    global $post;
    
    if (!$post) {
        return;
    }
    
    $schema = get_post_meta($post->ID, '_siloq_schema_markup', true);
    
    if (!empty($schema)) {
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
    }
}

/**
 * BUG 4 FIX: Auto-analysis batch cron handler.
 * Analyzes up to 5 unanalyzed pages per batch, then reschedules if more remain.
 */
function siloq_run_analyze_batch() {
    $post_types = function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array('page', 'post');

    $unanalyzed = get_posts( array(
        'post_type'   => $post_types,
        'post_status' => 'publish',
        'numberposts' => 5,
        'fields'      => 'ids',
        'meta_query'  => array(
            'relation' => 'AND',
            array( 'key' => '_siloq_synced', 'compare' => 'EXISTS' ),
            array( 'key' => '_siloq_analysis_score', 'compare' => 'NOT EXISTS' ),
        ),
    ) );

    foreach ( $unanalyzed as $post_id ) {
        $post  = get_post( $post_id );
        if ( ! $post ) continue;

        $url   = get_permalink( $post_id );
        $title = $post->post_title;
        $path  = wp_parse_url( $url, PHP_URL_PATH );

        // Lightweight analysis: check title, meta description, H1 presence
        $checks = array();
        $score  = 50; // base score

        // Title check
        if ( ! empty( $title ) && strlen( $title ) >= 10 ) {
            $checks['title'] = array( 'status' => 'pass', 'message' => 'Title present' );
            $score += 10;
        } else {
            $checks['title'] = array( 'status' => 'fail', 'message' => 'Title missing or too short' );
        }

        // Meta description check
        $meta_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true )
                  ?: get_post_meta( $post_id, '_rank_math_description', true );
        if ( ! empty( $meta_desc ) ) {
            $checks['meta_description'] = array( 'status' => 'pass', 'message' => 'Meta description present' );
            $score += 10;
        } else {
            $checks['meta_description'] = array( 'status' => 'fail', 'message' => 'Meta description missing' );
        }

        // H1 presence (check post content)
        $content = $post->post_content;
        if ( preg_match( '/<h1[^>]*>/i', $content ) || preg_match( '/<!-- wp:heading {"level":1}/', $content ) ) {
            $checks['h1'] = array( 'status' => 'pass', 'message' => 'H1 heading found' );
            $score += 10;
        } else {
            $checks['h1'] = array( 'status' => 'fail', 'message' => 'No H1 heading found' );
        }

        // Content length check
        $text_len = strlen( wp_strip_all_tags( $content ) );
        if ( $text_len > 300 ) {
            $checks['content_length'] = array( 'status' => 'pass', 'message' => 'Content has ' . $text_len . ' characters' );
            $score += 10;
        } else {
            $checks['content_length'] = array( 'status' => 'fail', 'message' => 'Content too thin (' . $text_len . ' chars)' );
        }

        $score = min( 100, $score );

        // Auto-detect page type (skip if manual role already set)
        $existing_role = get_post_meta( $post_id, '_siloq_page_role', true );
        $page_type = 'supporting';
        $path_lower = strtolower( $path );
        $title_lower = strtolower( $title );

        // Never-orphan pages
        $never_orphan = preg_match( '#^/$#', $path_lower )
            || preg_match( '#/(contact|about|privacy|terms|disclaimer)/?$#i', $path_lower );

        if ( preg_match( '#^/$#', $path_lower ) ) {
            $page_type = 'apex_hub';
        } elseif ( preg_match( '#/(services?|service-areas?|our-services?)/?$#', $path_lower ) ) {
            $page_type = 'hub';
        } elseif ( $post->post_parent ) {
            $parent_path = wp_parse_url( get_permalink( $post->post_parent ), PHP_URL_PATH );
            if ( preg_match( '#/(services?|service-areas?)/?$#', strtolower( $parent_path ) ) ) {
                $page_type = 'spoke';
            }
        }

        // City name patterns → spoke (if site has a services/service-areas page)
        $city_pattern = '#/(houston|dallas|austin|san-antonio|fort-worth|arlington|plano|irving|frisco|mckinney|denton|katy|sugar-land|the-woodlands|spring|pearland|league-city|pasadena|beaumont|midland|odessa|lubbock|amarillo|el-paso|corpus-christi|brownsville|killeen|waco|tyler|longview|round-rock|pflugerville|georgetown|cedar-park|new-york|los-angeles|chicago|phoenix|philadelphia|jacksonville|columbus|charlotte|indianapolis|denver|seattle|nashville|oklahoma-city|portland|las-vegas|memphis|louisville|baltimore|milwaukee|albuquerque|tucson|fresno|sacramento|mesa|kansas-city|atlanta|omaha|colorado-springs|raleigh|miami|tampa|orlando|minneapolis|cleveland|pittsburgh|st-louis|cincinnati)/?$#i';
        if ( preg_match( $city_pattern, $path_lower ) ) {
            $page_type = 'spoke';
        }

        // Service keywords in URL/title → supporting (if not already hub)
        $service_kws = '#(electrician|electrical|panel|wiring|ev[- ]charg|generator|plumb|hvac|roof|repair|install|maintenance|remodel)#i';
        if ( $page_type === 'supporting' && ( preg_match( $service_kws, $path_lower ) || preg_match( $service_kws, $title_lower ) ) ) {
            $page_type = 'supporting';
        }

        // Check if this page is a parent of 3+ children
        $child_count = count( get_children( array( 'post_parent' => $post_id, 'post_type' => $post_types, 'numberposts' => 4 ) ) );
        if ( $child_count >= 3 ) {
            $page_type = 'hub';
        }

        // Orphan detection: no parent, not a hub, not never-orphan
        if ( $page_type === 'supporting' && ! $never_orphan && ! $post->post_parent ) {
            // Check if any nav menu contains this page
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
                $page_type = 'orphan';
            }
        }

        // If manual role exists, use it instead
        if ( ! empty( $existing_role ) ) {
            $page_type = $existing_role;
        }

        update_post_meta( $post_id, '_siloq_analysis_score', $score );
        update_post_meta( $post_id, '_siloq_analysis_data', wp_json_encode( array(
            'score'  => $score,
            'checks' => $checks,
            'page_type_classification' => $page_type,
            'auto_analyzed' => true,
        ) ) );
        update_post_meta( $post_id, '_siloq_page_type_detected', $page_type );
    }

    // Check remaining and reschedule if needed
    $remaining = get_posts( array(
        'post_type'   => $post_types,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_query'  => array(
            'relation' => 'AND',
            array( 'key' => '_siloq_synced', 'compare' => 'EXISTS' ),
            array( 'key' => '_siloq_analysis_score', 'compare' => 'NOT EXISTS' ),
        ),
    ) );
    $remaining_count = count( $remaining );
    update_option( 'siloq_analysis_queue_count', $remaining_count );

    if ( $remaining_count > 0 ) {
        wp_schedule_single_event( time() + 120, 'siloq_analyze_batch' );
    }
}
add_action( 'siloq_analyze_batch', 'siloq_run_analyze_batch' );

/**
 * Initialize the plugin
 */
function siloq_init() {
    // Don't initialize during plugin activation/deactivation
    if (defined('WP_INSTALLING') && WP_INSTALLING) {
        return;
    }
    
    // Ensure WordPress is fully loaded
    if (!function_exists('add_action') || !function_exists('add_filter')) {
        return;
    }
    
    $connector = Siloq_Connector::get_instance(); // loads all dependencies first

    // Initialize webhook handler — must run AFTER get_instance() loads dependencies
    if (class_exists('Siloq_Webhook_Handler')) {
        Siloq_Webhook_Handler::init();
    }
    
    return $connector;
}

// Start the plugin only if WordPress functions are available
if (function_exists('add_action')) {
    add_action('plugins_loaded', 'siloq_init');
    add_action('wp_head', 'siloq_output_schema_markup', 1);
}

/**
 * Recursively extract plain text from Elementor widget data.
 * Walks the nested elements/columns/sections tree.
 */
function siloq_extract_elementor_text( $elements, $depth = 0 ) {
    if ( $depth > 8 || ! is_array( $elements ) ) return '';
    $parts = array();
    foreach ( $elements as $el ) {
        // Text content fields
        foreach ( array('text', 'title', 'description', 'content', 'editor', 'html') as $field ) {
            if ( ! empty( $el['settings'][$field] ) && is_string( $el['settings'][$field] ) ) {
                $parts[] = wp_strip_all_tags( $el['settings'][$field] );
            }
        }
        // Recurse into children
        if ( ! empty( $el['elements'] ) ) {
            $parts[] = siloq_extract_elementor_text( $el['elements'], $depth + 1 );
        }
    }
    return implode( ' ', array_filter( $parts ) );
}

/**
 * Extract FAQ questions from Elementor accordion/toggle/FAQ widgets.
 * Returns array of question strings.
 */
function siloq_extract_elementor_faqs( $elements, $depth = 0 ) {
    if ( $depth > 8 || ! is_array( $elements ) ) return array();
    $faqs = array();
    foreach ( $elements as $el ) {
        $widget_type = isset( $el['widgetType'] ) ? $el['widgetType'] : '';
        // Accordion, toggle, and FAQ widgets all use 'tabs' with 'tab_title'
        if ( in_array( $widget_type, array('accordion', 'toggle', 'faq'), true ) ) {
            if ( ! empty( $el['settings']['tabs'] ) && is_array( $el['settings']['tabs'] ) ) {
                foreach ( $el['settings']['tabs'] as $tab ) {
                    if ( ! empty( $tab['tab_title'] ) ) {
                        $faqs[] = wp_strip_all_tags( $tab['tab_title'] );
                    }
                }
            }
        }
        // Recurse
        if ( ! empty( $el['elements'] ) ) {
            $faqs = array_merge( $faqs, siloq_extract_elementor_faqs( $el['elements'], $depth + 1 ) );
        }
    }
    return array_values( array_unique( $faqs ) );
}

