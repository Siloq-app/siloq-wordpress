<?php
/**
 * Plugin Name: Siloq Connector
 * Plugin URI: https://github.com/Siloq-seo/siloq-wordpress-plugin
 * Description: Connects WordPress to Siloq platform for SEO content silo management and AI-powered content generation
 * Version: 1.5.7
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
define('SILOQ_VERSION', '1.5.7');
define('SILOQ_PLUGIN_FILE', __FILE__);

// WordPress-dependent constants will be defined when WordPress is loaded
if (function_exists('plugin_dir_path')) {
    define('SILOQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('SILOQ_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('SILOQ_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

/**
 * Main Siloq Connector Class
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
        
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-admin.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-api-client.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-sync-engine.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-ai-content-generator.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-schema-manager.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-redirect-manager.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/tali/class-siloq-tali.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize admin
        Siloq_Admin::get_instance();
        
        // Initialize AI Content Generator
        Siloq_AI_Content_Generator::init();
        
        // Initialize TALI
        if (!defined('SILOQ_TALI_DISABLED') || !SILOQ_TALI_DISABLED) {
            Siloq_TALI::get_instance();
        }
        
        // Initialize redirect manager
        Siloq_Redirect_Manager::get_instance();
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
        
        // Page editor assets
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_page_editor_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_siloq_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_siloq_sync_page', array($this, 'ajax_sync_page'));
        add_action('wp_ajax_siloq_sync_all_pages', array($this, 'ajax_sync_all_pages'));
        add_action('wp_ajax_siloq_get_sync_status', array($this, 'ajax_get_sync_status'));
        add_action('wp_ajax_siloq_import_content', array($this, 'ajax_import_content'));
        add_action('wp_ajax_siloq_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_siloq_check_job_status', array($this, 'ajax_check_job_status'));
        add_action('wp_ajax_siloq_restore_backup', array($this, 'ajax_restore_backup'));
        add_action('wp_ajax_siloq_sync_outdated', array($this, 'ajax_sync_outdated'));
        add_action('wp_ajax_siloq_get_business_profile', array($this, 'ajax_get_business_profile'));
        add_action('wp_ajax_siloq_save_business_profile', array($this, 'ajax_save_business_profile'));
        
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
            SILOQ_PLUGIN_URL . 'assets/siloq-ai-generator.js',
            array(),
            SILOQ_VERSION,
            true
        );
        
        wp_localize_script('siloq-ai-generator', 'siloqAI', array(
            'postId' => $post->ID,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('siloq_ai_nonce'),
            'preferences' => Siloq_AI_Content_Generator::get_default_preferences()
        ));
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
        
        // Get current screen (might not be available in all contexts)
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        
        // Enqueue sync script on sync pages
        if ($screen && ($screen->id === 'toplevel_page_siloq-settings' || $screen->id === 'siloq_page_siloq-sync' || $screen->id === 'siloq_page_siloq-dashboard')) {
            wp_enqueue_script(
                'siloq-sync',
                SILOQ_PLUGIN_URL . 'assets/siloq-sync.js',
                array('jquery'),
                SILOQ_VERSION,
                true
            );
            
            wp_localize_script('siloq-sync', 'siloqAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('siloq_ajax_nonce')
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
        
        if ($result['success']) {
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
        
        $api_client = new Siloq_API_Client();
        $result = $api_client->get_business_profile();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
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
        
        $profile_data = isset($_POST['profile']) ? $_POST['profile'] : array();
        
        if (empty($profile_data)) {
            wp_send_json_error(array('message' => 'Missing profile data'));
            return;
        }
        
        $api_client = new Siloq_API_Client();
        $result = $api_client->save_business_profile($profile_data);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
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
    
    return Siloq_Connector::get_instance();
}

// Start the plugin only if WordPress functions are available
if (function_exists('add_action')) {
    add_action('plugins_loaded', 'siloq_init');
}
