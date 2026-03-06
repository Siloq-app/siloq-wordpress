<?php
/**
 * Plugin Name: Siloq Connector
 * Plugin URI: https://github.com/Siloq-app/siloq-wordpress
 * Description: Connects WordPress to Siloq platform for SEO content silo management and AI-powered content generation

* Version: 1.5.99
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

define('SILOQ_VERSION', '1.5.99');
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
        require_once SILOQ_PLUGIN_DIR . 'includes/tali/class-siloq-tali.php';

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
        
        // Admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Schema injection
        add_action('wp_head', array('Siloq_Schema_Manager', 'output_schema'));
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
        // Page role override
        add_action('wp_ajax_siloq_set_page_role', array($this, 'ajax_set_page_role'));

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
            wp_localize_script('siloq-dashboard-v2', 'siloqDash', array(
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('siloq_ajax_nonce'),
                'siteScore' => intval(get_option('siloq_site_score', 42)),
                'siteId'    => get_option('siloq_site_id', ''),
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

        $sync_engine = new Siloq_Sync_Engine();
        $result = $sync_engine->sync_all_pages();

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

            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
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
        
        $sync_engine = new Siloq_Sync_Engine();
        $status = $sync_engine->get_sync_status();
        
        wp_send_json_success($status);
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
                    $local = array_merge( $local, array_filter( $api_data, fn( $v ) => $v !== null && $v !== '' ) );
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

            // Check meta title
            $meta_title = '';
            $meta_desc  = '';
            global $wpdb;
            $aioseo_row = $wpdb->get_row($wpdb->prepare(
                "SELECT title, description FROM {$wpdb->prefix}aioseo_posts WHERE post_id = %d LIMIT 1", $post_id
            ));
            if ($aioseo_row) {
                $meta_title = $aioseo_row->title;
                $meta_desc  = $aioseo_row->description;
            } else {
                $meta_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
                $meta_desc  = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                if (!$meta_title) {
                    $meta_title = get_post_meta($post_id, '_aioseop_title', true);
                    $meta_desc  = get_post_meta($post_id, '_aioseop_description', true);
                }
            }

            // Check schema
            $applied_schema = get_post_meta($post_id, '_siloq_applied_types', true);
            $has_schema = !empty($applied_schema);

            // Check content length
            $page_content = get_post_field('post_content', $post_id);
            $word_count = str_word_count(strip_tags($page_content));

            // Build issues from checks
            if (empty($meta_title)) {
                $issues['important'][] = array(
                    'title'        => $title,
                    'issue'        => 'Missing SEO title — set a title tag with your primary keyword',
                    'post_id'      => $post_id,
                    'edit_url'     => $edit_url,
                    'elementor_url'=> $elementor_url,
                );
            }
            if (empty($meta_desc)) {
                $issues['important'][] = array(
                    'title'        => $title,
                    'issue'        => 'Missing meta description — write a compelling 150-character summary',
                    'post_id'      => $post_id,
                    'edit_url'     => $edit_url,
                    'elementor_url'=> $elementor_url,
                );
            }
            if (!$has_schema) {
                $issues['opportunity'][] = array(
                    'title'        => $title,
                    'issue'        => 'No structured data — AI tools can\'t reliably cite this page without schema',
                    'post_id'      => $post_id,
                    'edit_url'     => $edit_url,
                    'elementor_url'=> $elementor_url,
                );
            }
            if ($word_count < 300 && $word_count > 0) {
                $issues['important'][] = array(
                    'title'        => $title,
                    'issue'        => 'Thin content — only ' . $word_count . ' words. Aim for 500+ for better rankings.',
                    'post_id'      => $post_id,
                    'edit_url'     => $edit_url,
                    'elementor_url'=> $elementor_url,
                );
            }
            // Check silo relationship FIRST, then add to architecture once only
            $silo_data = get_post_meta($post_id, '_siloq_silo_data', true);
            $has_analysis = !empty($analysis);

            if ($page_type === 'hub') {
                $hub_count++;
                $hubs[] = $post_id;
                $arch_type = 'hub';
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
            if (!isset($inbound_links[$post_id]) && $arch_type !== 'hub') {
                $issues['critical'][] = array(
                    'title'        => $title,
                    'issue'        => 'No internal links pointing to this page — Google may not be crawling it',
                    'post_id'      => $post_id,
                    'edit_url'     => $edit_url,
                    'elementor_url'=> $elementor_url,
                );
            }

            $architecture[] = array('title' => $title, 'type' => $arch_type, 'id' => $post_id, 'score' => $score);

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

            // Build actions — include links so Fix It buttons actually work
            if (!$has_analysis) {
                $actions[] = array(
                    'headline'      => 'Analyze "' . $title . '" in Widget Intelligence',
                    'detail'        => 'Open this page in Elementor and click Analyze to get SEO recommendations.',
                    'priority'      => 'high',
                    'post_id'       => $post_id,
                    'edit_url'      => $edit_url,
                    'elementor_url' => $elementor_url,
                );
            } elseif ($score < 50) {
                $actions[] = array(
                    'headline'      => 'Improve content quality on "' . $title . '"',
                    'detail'        => 'Score: ' . $score . '/100. Open in Widget Intelligence to see specific recommendations.',
                    'priority'      => 'high',
                    'post_id'       => $post_id,
                    'edit_url'      => $edit_url,
                    'elementor_url' => $elementor_url,
                );
            } elseif ($score < 75) {
                $actions[] = array(
                    'headline'      => 'Optimize "' . $title . '" for better rankings',
                    'detail'        => 'Score: ' . $score . '/100. Minor improvements available.',
                    'priority'      => 'medium',
                    'post_id'       => $post_id,
                    'edit_url'      => $edit_url,
                    'elementor_url' => $elementor_url,
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
            } elseif ($arch_type === 'hub' || $page_type === 'hub') {
                // Hub pages should have supporting content — flag it
                $missing_count++;
                $supporting[] = array(
                    'title'  => 'Add supporting pages under "' . $title . '"',
                    'type'   => 'sub-page',
                    'parent' => $title,
                    'detail' => 'This hub page needs spoke pages that cover specific subtopics in depth.',
                );
            }
        }

        // Sort architecture: hubs first, then spokes, then supporting, then orphans, then missing
        $type_order = array('hub' => 0, 'spoke' => 1, 'supporting' => 2, 'orphan' => 3, 'missing' => 4);
        usort($architecture, function($a, $b) use ($type_order) {
            $oa = isset($type_order[$a['type']]) ? $type_order[$a['type']] : 5;
            $ob = isset($type_order[$b['type']]) ? $type_order[$b['type']] : 5;
            return $oa - $ob;
        });

        // Sort actions by priority
        usort($actions, function($a, $b) {
            $p = array('high' => 0, 'medium' => 1, 'low' => 2);
            return ($p[$a['priority']] ?? 2) - ($p[$b['priority']] ?? 2);
        });

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

        $plan = array(
            'architecture' => $architecture,
            'actions'      => $actions,
            'issues'       => $issues,
            'supporting'   => $supporting,
            'roadmap'      => $roadmap,
            'hub_count'    => $hub_count,
            'orphan_count' => $orphan_count,
            'missing_count' => $missing_count,
            'generated_at' => current_time('mysql'),
        );

        // Cache for 60 minutes
        set_transient('siloq_plan_data', $plan, 3600);
        update_option('siloq_plan_data_last', current_time('mysql'));

        wp_send_json_success($plan);
    }

    /**
     * AJAX: Save roadmap checkbox progress
     */
    public function ajax_create_draft_page() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        $title = sanitize_text_field($_POST['title'] ?? '');
        if (empty($title)) {
            wp_send_json_error(array('message' => 'Title required'));
            return;
        }
        $post_id = wp_insert_post(array(
            'post_title'  => $title,
            'post_status' => 'draft',
            'post_type'   => 'page',
        ));
        if (is_wp_error($post_id)) {
            wp_send_json_error(array('message' => $post_id->get_error_message()));
            return;
        }
        wp_send_json_success(array(
            'post_id'  => $post_id,
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=elementor'),
        ));
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
            'post_status'    => 'publish',
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
            if ($has_analysis) {
                // Page has been through Widget Intelligence — use its classification
                $page_type = isset($analysis['page_type_classification']) ? $analysis['page_type_classification'] :
                             (isset($analysis['page_type']) ? $analysis['page_type'] : 'supporting');
                if (empty($silo_data) && $page_type !== 'hub') {
                    $page_type = 'orphan';
                }
            } else {
                // Not yet analyzed — don't label as orphan, just "pending"
                $page_type = 'pending';
            }

            if ($filter !== 'all' && $page_type !== $filter) {
                continue;
            }

            $issues = isset($analysis['issues']) ? $analysis['issues'] : array();
            $score = isset($analysis['score']) ? intval($analysis['score']) : (isset($analysis['seo_score']['overall']) ? intval($analysis['seo_score']['overall']) : 0);
            $primary_keyword = isset($analysis['primary_keyword']) ? $analysis['primary_keyword'] : (isset($analysis['seo_score']['primary_keyword']) ? $analysis['seo_score']['primary_keyword'] : '');

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

        $allowed_roles = array('', 'hub', 'spoke', 'supporting', 'unclassified');
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
                if (!empty($sites_list[0]['id'])) {
                    $site_id = $sites_list[0]['id'];
                    update_option('siloq_site_id', $site_id);
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
            wp_send_json_error(array('message' => isset($body['detail']) ? $body['detail'] : 'Sync failed'));
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
    public function ajax_gsc_get_properties() {
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
            trailingslashit($api_url) . 'sites/' . $site_id . '/gsc/properties/',
            array(
                'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Accept' => 'application/json'),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $err = isset($body['error']) ? $body['error'] : (isset($body['detail']) ? $body['detail'] : 'HTTP ' . $code);
            wp_send_json_error(array('message' => 'API error: ' . $err));
        }

        $properties = isset($body['properties']) ? $body['properties'] : (is_array($body) ? $body : array());
        wp_send_json_success(array('properties' => $properties, 'home_url' => home_url()));
    }

    /**
     * AJAX: Save selected GSC property
     */
    public function ajax_gsc_save_property() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $property = isset($_POST['property']) ? sanitize_text_field($_POST['property']) : '';
        if (empty($property)) {
            wp_send_json_error(array('message' => 'No property selected.'));
        }

        $api_url = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
        $api_key = get_option('siloq_api_key', '');
        $site_id = get_option('siloq_site_id', '');

        // Save to WP options
        update_option('siloq_gsc_property', $property);
        update_option('siloq_gsc_connected', 'yes');
        delete_option('siloq_gsc_needs_property_selection');

        // Tell the API which property was selected
        if (!empty($api_key) && !empty($site_id)) {
            wp_remote_request(
                trailingslashit($api_url) . 'sites/' . $site_id . '/',
                array(
                    'method'  => 'PATCH',
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode(array('gsc_site_url' => $property)),
                    'timeout' => 15,
                )
            );
        }

        wp_send_json_success(array('property' => $property));
    }

    /**
     * AJAX: Onboarding wizard — connect (save API key, verify with /auth/me)
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
            if ( ! empty( $sites[0]['id'] ) ) {
                update_option( 'siloq_site_id', sanitize_text_field( $sites[0]['id'] ) );
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
    
    // Add default options
    add_option('siloq_api_url', '');
    add_option('siloq_api_key', '');
    add_option('siloq_auto_sync', 'no');
    add_option('siloq_use_dummy_scan', 'yes');
    
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
            $page_type = 'hub';
        } elseif ( preg_match( '#/(services?|service-areas?)/?$#', $path_lower ) ) {
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

