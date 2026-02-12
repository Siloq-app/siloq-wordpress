<?php
/**
 * Plugin Name: Siloq Connector
 * Plugin URI: https://github.com/Siloq-seo/siloq-wordpress-plugin
 * Description: Connects WordPress to Siloq platform for SEO content silo management and AI-powered content generation
 * Version: 1.1.0
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

// Define plugin constants
define('SILOQ_VERSION', '1.1.0');
define('SILOQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SILOQ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SILOQ_PLUGIN_BASENAME', plugin_basename(__FILE__));

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
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('save_post', array($this, 'sync_on_save'), 10, 3);
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // AJAX hooks - Core
        add_action('wp_ajax_siloq_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_siloq_sync_page', array($this, 'ajax_sync_page'));
        add_action('wp_ajax_siloq_sync_all', array($this, 'ajax_sync_all_pages'));
        add_action('wp_ajax_siloq_get_sync_status', array($this, 'ajax_get_sync_status'));
        add_action('wp_ajax_siloq_import_content', array($this, 'ajax_import_content'));
        add_action('wp_ajax_siloq_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_siloq_check_job_status', array($this, 'ajax_check_job_status'));
        add_action('wp_ajax_siloq_restore_backup', array($this, 'ajax_restore_backup'));
        add_action('wp_ajax_siloq_sync_outdated', array($this, 'ajax_sync_outdated'));

        // AJAX hooks - AI Content Analysis (from wp-nextgen-plugin-demo)
        add_action('wp_ajax_siloq_analyze_content', array($this, 'ajax_analyze_content'));
        add_action('wp_ajax_siloq_optimize_content', array($this, 'ajax_optimize_content'));
        add_action('wp_ajax_siloq_save_optimization', array($this, 'ajax_save_optimization'));
        
        // AJAX hooks - Dashboard & CSV Export (from wp-nextgen-plugin-demo)
        add_action('wp_ajax_siloq_dashboard_stats', array($this, 'ajax_dashboard_stats'));
        add_action('wp_ajax_siloq_export_analysis_csv', array($this, 'ajax_export_analysis_csv'));
        add_action('wp_ajax_siloq_export_all_csv', array($this, 'ajax_export_all_csv'));
        
        // AJAX hooks - Site Configuration (from wp-nextgen-plugin-demo Settings)
        add_action('wp_ajax_siloq_get_config', array($this, 'ajax_get_config'));
        add_action('wp_ajax_siloq_update_config', array($this, 'ajax_update_config'));
        add_action('wp_ajax_siloq_toggle_auto_sync', array($this, 'ajax_toggle_auto_sync'));
        add_action('wp_ajax_siloq_disconnect_site', array($this, 'ajax_disconnect_site'));

        // Setup wizard redirect
        add_action('admin_init', array($this, 'maybe_redirect_to_wizard'));
        
        // Schema injection
        add_action('wp_head', array($this, 'inject_schema_markup'));
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-api-client.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-sync-engine.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-admin.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-content-import.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-webhook-handler.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-lead-gen-scanner.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-setup-wizard.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-ai-service.php'; // AI Content Analysis

        // Initialize webhook handler
        new Siloq_Webhook_Handler();

        // Initialize lead gen scanner
        $api_client = new Siloq_API_Client();
        new Siloq_Lead_Gen_Scanner($api_client);

        // Initialize setup wizard
        new Siloq_Setup_Wizard();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Siloq Settings', 'siloq-connector'),
            __('Siloq', 'siloq-connector'),
            'manage_options',
            'siloq-dashboard',
            array('Siloq_Admin', 'render_dashboard_page'),
            'dashicons-networking',
            80
        );
        
        add_submenu_page(
            'siloq-dashboard',
            __('Dashboard', 'siloq-connector'),
            __('Dashboard', 'siloq-connector'),
            'manage_options',
            'siloq-dashboard',
            array('Siloq_Admin', 'render_dashboard_page')
        );
        
        add_submenu_page(
            'siloq-dashboard',
            __('Settings', 'siloq-connector'),
            __('Settings', 'siloq-connector'),
            'manage_options',
            'siloq-settings',
            array('Siloq_Admin', 'render_settings_page')
        );
        
        add_submenu_page(
            'siloq-dashboard',
            __('Sync Status', 'siloq-connector'),
            __('Sync Status', 'siloq-connector'),
            'manage_options',
            'siloq-sync-status',
            array('Siloq_Admin', 'render_sync_status_page')
        );
        
        add_submenu_page(
            'siloq-dashboard',
            __('Content Import', 'siloq-connector'),
            __('Content Import', 'siloq-connector'),
            'edit_pages',
            'siloq-content-import',
            array('Siloq_Admin', 'render_content_import_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'siloq') === false) {
            return;
        }
        
        // Load dashboard assets on dashboard page
        if ($hook === 'toplevel_page_siloq-dashboard' || $hook === 'siloq_page_siloq-dashboard') {
            wp_enqueue_style(
                'siloq-dashboard-css',
                SILOQ_PLUGIN_URL . 'assets/css/admin-dashboard.css',
                array(),
                SILOQ_VERSION
            );
            
            wp_enqueue_script(
                'siloq-dashboard-js',
                SILOQ_PLUGIN_URL . 'assets/js/admin-dashboard.js',
                array('jquery'),
                SILOQ_VERSION,
                true
            );
            
            // Pass data to JavaScript
            wp_localize_script('siloq-dashboard-js', 'siloqDashboard', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('siloq_ajax_nonce'),
                'siteUrl' => get_site_url(),
                'strings' => array(
                    'testing' => __('Testing connection...', 'siloq-connector'),
                    'syncing' => __('Syncing...', 'siloq-connector'),
                    'success' => __('Success!', 'siloq-connector'),
                    'error' => __('Error:', 'siloq-connector')
                )
            ));
            return;
        }
        
        wp_enqueue_style(
            'siloq-admin-css',
            SILOQ_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SILOQ_VERSION
        );
        
        wp_enqueue_script(
            'siloq-admin-js',
            SILOQ_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SILOQ_VERSION,
            true
        );
        
        // Pass data to JavaScript
        wp_localize_script('siloq-admin-js', 'siloqAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('siloq_ajax_nonce'),
            'strings' => array(
                'testing' => __('Testing connection...', 'siloq-connector'),
                'syncing' => __('Syncing...', 'siloq-connector'),
                'success' => __('Success!', 'siloq-connector'),
                'error' => __('Error:', 'siloq-connector')
            )
        ));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load on pages with Siloq content
        if (!is_singular('page')) {
            return;
        }
        
        global $post;
        
        // Check if page has Siloq-generated content
        $has_siloq_content = get_post_meta($post->ID, '_siloq_generated_from', true);
        $has_faq = get_post_meta($post->ID, '_siloq_faq_items', true);
        
        if ($has_siloq_content || $has_faq) {
            wp_enqueue_style(
                'siloq-frontend-css',
                SILOQ_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                SILOQ_VERSION
            );
        }
    }
    
    /**
     * Auto-sync on page save/update
     */
    public function sync_on_save($post_id, $post, $update) {
        // Check if auto-sync is enabled
        if (get_option('siloq_auto_sync') !== 'yes') {
            return;
        }
        
        // Get configured post types (default to page if not set)
        $post_types = get_option('siloq_post_types', array('page'));
        
        // Only sync configured post types
        if (!in_array($post->post_type, $post_types)) {
            return;
        }
        
        // Don't sync autosaves or revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Don't sync if post is not published
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Sync this page
        $sync_engine = new Siloq_Sync_Engine();
        $sync_engine->sync_page($post_id);
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check if wizard has been completed
        $wizard_completed = get_option('siloq_wizard_completed', false);

        if (!$wizard_completed && !isset($_GET['page']) || (isset($_GET['page']) && $_GET['page'] !== 'siloq-setup-wizard')) {
            $wizard_url = admin_url('admin.php?page=siloq-setup-wizard');
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . __('Siloq Connector:', 'siloq-connector') . '</strong> ';
            echo sprintf(
                __('Welcome! Please complete the <a href="%s">Setup Wizard</a> to configure Siloq for your business.', 'siloq-connector'),
                esc_url($wizard_url)
            );
            echo '</p></div>';
            return;
        }

        // Check if API settings are configured
        $api_url = get_option('siloq_api_url');
        $api_key = get_option('siloq_api_key');

        if (empty($api_url) || empty($api_key)) {
            $settings_url = admin_url('admin.php?page=siloq-settings');
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __('Siloq Connector:', 'siloq-connector') . '</strong> ';
            echo sprintf(
                __('Please configure your <a href="%s">API settings</a> to start syncing.', 'siloq-connector'),
                esc_url($settings_url)
            );
            echo '</p></div>';
        }
    }

    /**
     * Redirect to wizard on first activation
     */
    public function maybe_redirect_to_wizard() {
        // Only redirect once and only on activation
        if (get_transient('siloq_activation_redirect')) {
            delete_transient('siloq_activation_redirect');

            // Check if wizard is already completed
            $wizard_completed = get_option('siloq_wizard_completed', false);

            if (!$wizard_completed && !isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('admin.php?page=siloq-setup-wizard'));
                exit;
            }
        }
    }
    
    /**
     * Inject schema markup in page <head>
     */
    public function inject_schema_markup() {
        if (!is_singular('page')) {
            return;
        }
        
        global $post;
        $schema_markup = get_post_meta($post->ID, '_siloq_schema_markup', true);
        
        if (!empty($schema_markup)) {
            echo "\n<!-- Siloq Schema Markup -->\n";
            echo '<script type="application/ld+json">' . "\n";
            echo $schema_markup . "\n";
            echo '</script>' . "\n";
            echo "<!-- /Siloq Schema Markup -->\n";
        }
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $api_client = new Siloq_API_Client();
        $result = $api_client->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Sync single page
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
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $sync_engine = new Siloq_Sync_Engine();
        $result = $sync_engine->sync_all_pages();
        
        wp_send_json_success($result);
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
        
        $pages = get_posts(array(
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $status_data = array();
        foreach ($pages as $page) {
            $status_data[] = array(
                'id' => $page->ID,
                'title' => $page->post_title,
                'url' => get_permalink($page->ID),
                'last_synced' => get_post_meta($page->ID, '_siloq_last_synced', true),
                'sync_status' => get_post_meta($page->ID, '_siloq_sync_status', true),
                'has_schema' => !empty(get_post_meta($page->ID, '_siloq_schema_markup', true))
            );
        }
        
        wp_send_json_success(array('pages' => $status_data));
    }
    
    /**
     * AJAX: Import content from Siloq
     */
    public function ajax_import_content() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        $action = isset($_POST['import_action']) ? sanitize_text_field($_POST['import_action']) : 'create_draft';
        
        if (!$post_id || !$job_id) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
            return;
        }
        
        $import_handler = new Siloq_Content_Import();
        $result = $import_handler->import_from_job($post_id, $job_id, array('action' => $action));
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Generate content for a page
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
        $result = $api_client->get_content_job_status($job_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Restore backup content
     */
    public function ajax_restore_backup() {
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
        
        $import_handler = new Siloq_Content_Import();
        $result = $import_handler->restore_backup($post_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Sync outdated pages
     */
    public function ajax_sync_outdated() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $limit = min($limit, 50); // Max 50 pages at once
        
        $sync_engine = new Siloq_Sync_Engine();
        $result = $sync_engine->sync_outdated_pages($limit);
        
        wp_send_json_success($result);
    }

    /**
     * AJAX: Analyze content with AI (from wp-nextgen-plugin-demo Scanner)
     */
    public function ajax_analyze_content() {
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
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => 'Post not found'));
            return;
        }
        
        // Get content - use excerpt if available, otherwise strip tags from content
        $content = !empty($post->post_excerpt) ? $post->post_excerpt : wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 500); // Limit content length for AI
        
        $ai_service = new Siloq_AI_Service();
        $result = $ai_service->analyze_content($post->post_title, $content);
        
        if ($result['success']) {
            // Save analysis to post meta
            $ai_service->save_analysis($post_id, $result['data']);
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Optimize content with AI (from wp-nextgen-plugin-demo Scanner)
     */
    public function ajax_optimize_content() {
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
        
        // Get saved analysis
        $ai_service = new Siloq_AI_Service();
        $analysis = $ai_service->get_saved_analysis($post_id);
        
        if (!$analysis) {
            wp_send_json_error(array('message' => 'No analysis found. Please analyze content first.'));
            return;
        }
        
        $post = get_post($post_id);
        $content = !empty($post->post_excerpt) ? $post->post_excerpt : wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 500);
        
        $result = $ai_service->optimize_content($content, $analysis['improvements']);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Save optimized content (from wp-nextgen-plugin-demo Scanner)
     */
    public function ajax_save_optimization() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $optimized_content = isset($_POST['optimized_content']) ? sanitize_textarea_field($_POST['optimized_content']) : '';
        
        if (!$post_id || empty($optimized_content)) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
            return;
        }
        
        // Backup current content
        $post = get_post($post_id);
        update_post_meta($post_id, '_siloq_content_backup', $post->post_content);
        
        // Update post with optimized content
        $update_result = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $optimized_content
        ), true);
        
        if (is_wp_error($update_result)) {
            wp_send_json_error(array('message' => $update_result->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'message' => __('Content updated successfully', 'siloq-connector'),
            'post_id' => $post_id
        ));
    }

    /**
     * AJAX: Get dashboard stats (from wp-nextgen-plugin-demo Dashboard)
     */
    public function ajax_dashboard_stats() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_types = get_option('siloq_post_types', array('page'));
        
        // Get total synced pages
        $synced_count = 0;
        foreach ($post_types as $post_type) {
            $synced_count += count(get_posts(array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_key' => '_siloq_last_synced',
                'meta_compare' => 'EXISTS'
            )));
        }
        
        // Get total pages
        $total_count = 0;
        foreach ($post_types as $post_type) {
            $total_count += wp_count_posts($post_type)->publish;
        }
        
        // Get analyzed pages count
        $ai_service = new Siloq_AI_Service();
        $analyzed_posts = $ai_service->get_analyzed_posts($post_types);
        
        // Get API status
        $api_client = new Siloq_API_Client();
        $connection_test = $api_client->test_connection();
        
        wp_send_json_success(array(
            'total_pages' => $total_count,
            'synced_pages' => $synced_count,
            'analyzed_pages' => count($analyzed_posts),
            'api_connected' => $connection_test['success'],
            'auto_sync' => get_option('siloq_auto_sync') === 'yes',
            'post_types' => $post_types,
            'plugin_version' => SILOQ_VERSION
        ));
    }

    /**
     * AJAX: Export analysis CSV for single page (from wp-nextgen-plugin-demo Scanner)
     */
    public function ajax_export_analysis_csv() {
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
        
        $post = get_post($post_id);
        $ai_service = new Siloq_AI_Service();
        $analysis = $ai_service->get_saved_analysis($post_id);
        
        if (!$analysis) {
            wp_send_json_error(array('message' => 'No analysis found for this page'));
            return;
        }
        
        // Generate CSV
        $filename = 'seo-analysis-' . sanitize_file_name($post->post_title) . '-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Header
        fputcsv($output, array('Page ID', 'Page Title', 'Score', 'Keywords', 'Summary', 'Lead Gen Hook', 'Improvements'));
        
        // CSV Row
        fputcsv($output, array(
            $post_id,
            $post->post_title,
            $analysis['score'],
            implode(', ', $analysis['keywords']),
            $analysis['summary'],
            $analysis['leadGenHook'],
            implode('; ', $analysis['improvements'])
        ));
        
        fclose($output);
        exit;
    }

    /**
     * AJAX: Export all analyses CSV (from wp-nextgen-plugin-demo Sync)
     */
    public function ajax_export_all_csv() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_types = get_option('siloq_post_types', array('page'));
        $ai_service = new Siloq_AI_Service();
        $analyzed_posts = $ai_service->get_analyzed_posts($post_types);
        
        // Generate CSV
        $filename = 'all-pages-analysis-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Header
        fputcsv($output, array('Post ID', 'Title', 'URL', 'Score', 'Keywords', 'Summary', 'Lead Gen Hook', 'Analysis Date'));
        
        // CSV Rows
        foreach ($analyzed_posts as $item) {
            fputcsv($output, array(
                $item['post_id'],
                $item['title'],
                $item['url'],
                $item['analysis']['score'],
                implode(', ', $item['analysis']['keywords']),
                $item['analysis']['summary'],
                $item['analysis']['leadGenHook'],
                $item['analysis_date']
            ));
        }
        
        fclose($output);
        exit;
    }

    /**
     * AJAX: Get site configuration (from wp-nextgen-plugin-demo Settings)
     */
    public function ajax_get_config() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $api_url = get_option('siloq_api_url', '');
        $api_key = get_option('siloq_api_key', '');
        
        // Test connection to get site info
        $site_info = array();
        if (!empty($api_url) && !empty($api_key)) {
            $api_client = new Siloq_API_Client();
            $test = $api_client->test_connection();
            if ($test['success'] && isset($test['data'])) {
                $site_info = $test['data'];
            }
        }
        
        wp_send_json_success(array(
            'api_url' => $api_url,
            'api_key' => !empty($api_key) ? substr($api_key, 0, 8) . '...' : '',
            'gemini_api_key' => !empty(get_option('siloq_gemini_api_key')) ? 'configured' : '',
            'post_types' => get_option('siloq_post_types', array('page')),
            'auto_sync' => get_option('siloq_auto_sync', 'no') === 'yes',
            'is_connected' => !empty($api_url) && !empty($api_key) && !empty($site_info),
            'plugin_version' => SILOQ_VERSION,
            'site_info' => $site_info
        ));
    }

    /**
     * AJAX: Update site configuration (from wp-nextgen-plugin-demo Settings)
     */
    public function ajax_update_config() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $api_url = isset($_POST['api_url']) ? esc_url_raw($_POST['api_url']) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $gemini_api_key = isset($_POST['gemini_api_key']) ? sanitize_text_field($_POST['gemini_api_key']) : '';
        $post_types = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : array('page');
        
        // Validate at least one post type
        if (empty($post_types)) {
            $post_types = array('page');
        }
        
        // Update options
        if (!empty($api_url)) {
            update_option('siloq_api_url', $api_url);
        }
        if (!empty($api_key)) {
            update_option('siloq_api_key', $api_key);
        }
        if (!empty($gemini_api_key)) {
            update_option('siloq_gemini_api_key', $gemini_api_key);
        }
        update_option('siloq_post_types', $post_types);
        
        wp_send_json_success(array(
            'message' => __('Configuration updated successfully', 'siloq-connector'),
            'post_types' => $post_types
        ));
    }

    /**
     * AJAX: Toggle auto-sync (from wp-nextgen-plugin-demo Settings)
     */
    public function ajax_toggle_auto_sync() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $enabled = isset($_POST['enabled']) ? rest_sanitize_boolean($_POST['enabled']) : false;
        
        update_option('siloq_auto_sync', $enabled ? 'yes' : 'no');
        
        wp_send_json_success(array(
            'message' => $enabled ? __('Auto-sync enabled', 'siloq-connector') : __('Auto-sync disabled', 'siloq-connector'),
            'auto_sync' => $enabled
        ));
    }

    /**
     * AJAX: Disconnect site (from wp-nextgen-plugin-demo Settings)
     */
    public function ajax_disconnect_site() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        // Clear API credentials
        delete_option('siloq_api_url');
        delete_option('siloq_api_key');
        delete_option('siloq_gemini_api_key');
        update_option('siloq_auto_sync', 'no');
        update_option('siloq_wizard_completed', false);
        
        wp_send_json_success(array(
            'message' => __('Site disconnected successfully', 'siloq-connector'),
            'redirect_url' => admin_url('admin.php?page=siloq-setup-wizard')
        ));
    }
}

/**
 * Plugin activation
 */
function siloq_activate() {
    // Add default options
    add_option('siloq_api_url', '');
    add_option('siloq_api_key', '');
    add_option('siloq_gemini_api_key', ''); // AI analysis feature
    add_option('siloq_auto_sync', 'no');
    add_option('siloq_post_types', array('page')); // Post types configuration
    add_option('siloq_wizard_completed', false);

    // Set activation redirect transient
    set_transient('siloq_activation_redirect', true, 30);

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'siloq_activate');

/**
 * Plugin deactivation
 */
function siloq_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'siloq_deactivate');

/**
 * Initialize the plugin
 */
function siloq_init() {
    return Siloq_Connector::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'siloq_init');
