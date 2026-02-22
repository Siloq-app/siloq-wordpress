<?php
/**
 * Plugin Name: Siloq Connector
 * Plugin URI: https://siloq.com
 * Description: Connect WordPress to Siloq platform for AI-powered SEO content management and lead generation
 * Version: 1.5.7
 * Author: Siloq
 * Author URI: https://siloq.com
 * License: GPL v2 or later
 * Text Domain: siloq-connector
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SILOQ_VERSION', '1.5.7');
define('SILOQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SILOQ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SILOQ_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SILOQ_PLUGIN_FILE', __FILE__);

/**
 * Main Siloq Connector Class
 */
class Siloq_Connector {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Load dependencies
        $this->load_dependencies();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_page_editor_assets'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('save_post', array($this, 'sync_on_save'), 10, 3);
        add_action('admin_notices', array($this, 'show_admin_notices'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // AJAX hooks
        add_action('wp_ajax_siloq_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_siloq_sync_page', array($this, 'ajax_sync_page'));
        add_action('wp_ajax_siloq_sync_all', array($this, 'ajax_sync_all_pages'));
        add_action('wp_ajax_siloq_get_sync_status', array($this, 'ajax_get_sync_status'));
        add_action('wp_ajax_siloq_import_content', array($this, 'ajax_import_content'));
        add_action('wp_ajax_siloq_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_siloq_check_job_status', array($this, 'ajax_check_job_status'));
        add_action('wp_ajax_siloq_restore_backup', array($this, 'ajax_restore_backup'));
        add_action('wp_ajax_siloq_sync_outdated', array($this, 'ajax_sync_outdated'));
        add_action('wp_ajax_siloq_get_business_profile', array($this, 'ajax_get_business_profile'));
        add_action('wp_ajax_siloq_save_business_profile', array($this, 'ajax_save_business_profile'));
        
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
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Siloq',
            'Siloq',
            'manage_options',
            'siloq-connector',
            array($this, 'render_admin_page'),
            'dashicons-networking',
            25
        );
        
        add_submenu_page(
            'siloq-connector',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'siloq-connector',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'siloq-connector',
            'Setup Wizard',
            'Setup Wizard',
            'manage_options',
            'siloq-wizard',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'siloq-connector',
            'Page Sync',
            'Page Sync',
            'manage_options',
            'siloq-sync',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'siloq-connector',
            'Lead Gen Scanner',
            'Lead Gen Scanner',
            'manage_options',
            'siloq-scanner',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'siloq-connector',
            'Settings',
            'Settings',
            'manage_options',
            'siloq-settings',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div id="siloq-root">
            <div style="background: #F0F0F1; min-height: 100vh; display: flex; align-items: center; justify-content: center;"></div>
        </div>
        <?php
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
        
        // Enqueue the AI content generator script
        wp_enqueue_script(
            'siloq-page-editor',
            SILOQ_PLUGIN_URL . 'assets/siloq-page-editor.js',
            array(),
            SILOQ_VERSION,
            true
        );
        
        // Pass data to script
        wp_localize_script('siloq-page-editor', 'siloqPageEditor', array(
            'apiUrl' => get_option('siloq_api_url', ''),
            'apiKey' => get_option('siloq_api_key', ''),
            'siteId' => get_option('siloq_site_id', ''),
            'postId' => $post->ID,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('siloq_page_editor_nonce'),
        ));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'siloq') === false) {
            return;
        }
        
        // Enqueue Tailwind
        wp_enqueue_script('tailwindcss', 'https://cdn.tailwindcss.com', array(), null, false);
        
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
        
        // Get current screen for initial view
        $screen = get_current_screen();
        $initial_view = 'dashboard';
        
        if ($screen && isset($screen->id)) {
            if (strpos($screen->id, 'wizard') !== false) {
                $initial_view = 'wizard';
            } elseif (strpos($screen->id, 'sync') !== false) {
                $initial_view = 'sync';
            } elseif (strpos($screen->id, 'scanner') !== false) {
                $initial_view = 'scanner';
            } elseif (strpos($screen->id, 'settings') !== false) {
                $initial_view = 'settings';
            }
        }
        
        // Check if built assets exist
        $asset_file_path = SILOQ_PLUGIN_DIR . 'react-ui/dist/index.asset.php';
        
        if (file_exists($asset_file_path)) {
            $asset_file = include($asset_file_path);
            
            wp_enqueue_script(
                'siloq-script',
                SILOQ_PLUGIN_URL . 'react-ui/dist/index.js',
                array_merge($asset_file['dependencies'], array('tailwindcss')),
                $asset_file['version'],
                true
            );
        } else {
            // Development mode
            wp_enqueue_script(
                'siloq-script',
                'http://localhost:3000/index.tsx',
                array('tailwindcss', 'react', 'react-dom'),
                SILOQ_VERSION,
                true
            );
            
            add_action('admin_head', function() {
                $screen = get_current_screen();
                if ($screen && $screen->id === 'toplevel_page_siloq-connector') {
                    ?>
                    <style>#wpcontent { padding: 0 !important; }</style>
                    <?php
                }
                ?>
                <script type="importmap">
                {
                  "imports": {
                    "react/": "https://esm.sh/react@^19.2.4/",
                    "react": "https://esm.sh/react@^19.2.4",
                    "lucide-react": "https://esm.sh/lucide-react@^0.563.0",
                    "react-dom/": "https://esm.sh/react-dom@^19.2.4/"
                  }
                }
                </script>
                <?php
            });
        }
        
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
        
        // Localize script with WordPress data
        wp_localize_script('siloq-script', 'siloqData', array(
            'restUrl'     => esc_url_raw(rest_url('siloq/v1')),
            'nonce'       => wp_create_nonce('wp_rest'),
            'initialView' => $initial_view,
            'apiKey'      => get_option('siloq_api_key', ''),
            'siteId'      => get_option('siloq_site_id', ''),
            'connected'   => !empty(get_option('siloq_api_key')),
            'autoSync'    => get_option('siloq_auto_sync', false),
            'wizardCompleted' => get_option('siloq_wizard_completed', false),
        ));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('siloq/v1', '/config', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_config'),
                'permission_callback' => array($this, 'check_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'save_config'),
                'permission_callback' => array($this, 'check_permission'),
            ),
        ));
        
        register_rest_route('siloq/v1', '/disconnect', array(
            'methods' => 'POST',
            'callback' => array($this, 'disconnect'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        register_rest_route('siloq/v1', '/pages', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_pages'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        register_rest_route('siloq/v1', '/sync', array(
            'methods' => 'POST',
            'callback' => array($this, 'sync_pages'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        register_rest_route('siloq/v1', '/scan', array(
            'methods' => 'POST',
            'callback' => array($this, 'scan_content'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        register_rest_route('siloq/v1', '/wizard/complete', array(
            'methods' => 'POST',
            'callback' => array($this, 'complete_wizard'),
            'permission_callback' => array($this, 'check_permission'),
        ));
    }
    
    public function check_permission() {
        return current_user_can('manage_options');
    }
    
    public function get_config() {
        return rest_ensure_response(array(
            'apiKey' => get_option('siloq_api_key', ''),
            'siteId' => get_option('siloq_site_id', ''),
            'connected' => !empty(get_option('siloq_api_key')),
            'autoSync' => get_option('siloq_auto_sync', false),
            'wizardCompleted' => get_option('siloq_wizard_completed', false),
        ));
    }
    
    public function save_config($request) {
        $params = $request->get_json_params();
        
        if (isset($params['apiKey'])) {
            update_option('siloq_api_key', sanitize_text_field($params['apiKey']));
        }
        if (isset($params['autoSync'])) {
            update_option('siloq_auto_sync', (bool) $params['autoSync']);
        }
        if (isset($params['siteId'])) {
            update_option('siloq_site_id', sanitize_text_field($params['siteId']));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    public function disconnect() {
        delete_option('siloq_api_key');
        delete_option('siloq_site_id');
        delete_option('siloq_auto_sync');
        delete_option('siloq_wizard_completed');
        
        return rest_ensure_response(array('success' => true));
    }
    
    public function get_pages() {
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft'),
        ));
        
        $pages = array();
        foreach ($posts as $post) {
            $pages[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'type' => $post->post_type,
                'status' => $post->post_status,
                'author' => get_the_author_meta('display_name', $post->post_author),
                'modified' => $post->post_modified,
                'synced' => (bool) get_post_meta($post->ID, '_siloq_synced', true),
                'syncedAt' => get_post_meta($post->ID, '_siloq_synced_at', true),
            );
        }
        
        return rest_ensure_response($pages);
    }
    
    public function sync_pages($request) {
        $params = $request->get_json_params();
        $page_ids = isset($params['pageIds']) ? array_map('intval', $params['pageIds']) : array();
        
        foreach ($page_ids as $post_id) {
            update_post_meta($post_id, '_siloq_synced', true);
            update_post_meta($post_id, '_siloq_synced_at', current_time('mysql'));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'synced' => count($page_ids),
        ));
    }
    
    /**
     * Auto-sync on page save/update
     */
    public function sync_on_save($post_id, $post, $update) {
        // Check if auto-sync is enabled
        if (get_option('siloq_auto_sync') !== 'yes') {
            return;
        }
        
        // Only sync pages (not posts or other post types)
        if ($post->post_type !== 'page') {
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
        // When using dummy scan only, skip API notice
        if (get_option('siloq_use_dummy_scan', 'yes') === 'yes') {
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
     * Inject schema markup in page <head>
     */
    public function inject_schema_markup() {
        if (!is_singular('page')) {
            return;
        }
        
        global $post;
        if (!$post || !is_a($post, 'WP_Post')) {
            return;
        }
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
     * AJAX: Test API connection.
     * Uses current form values (api_url, api_key) from POST; falls back to saved options if empty.
     */
    public function ajax_test_connection() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        // Prefer POST (form), then REQUEST (some servers), then saved options
        $api_url = '';
        $api_key = '';
        if (!empty($_POST['siloq_api_url']) || !empty($_POST['siloq_api_key'])) {
            $api_url = isset($_POST['siloq_api_url']) ? trim(sanitize_text_field(wp_unslash($_POST['siloq_api_url']))) : '';
            $api_key = isset($_POST['siloq_api_key']) ? trim(sanitize_text_field(wp_unslash($_POST['siloq_api_key']))) : '';
        }
        if (($api_url === '' || $api_key === '') && (isset($_REQUEST['siloq_api_url']) || isset($_REQUEST['siloq_api_key']))) {
            $api_url = isset($_REQUEST['siloq_api_url']) ? trim(sanitize_text_field(wp_unslash($_REQUEST['siloq_api_url']))) : $api_url;
            $api_key = isset($_REQUEST['siloq_api_key']) ? trim(sanitize_text_field(wp_unslash($_REQUEST['siloq_api_key']))) : $api_key;
        }
        if ($api_url === '' || $api_key === '') {
            $api_url = trim((string) get_option('siloq_api_url', 'https://api.siloq.ai/api/v1'));
            $api_key = trim((string) get_option('siloq_api_key', ''));
        }
        
        $api_client = new Siloq_API_Client();
        $result = $api_client->test_connection_with_credentials($api_url, $api_key);
        
        if ($result['success']) {
            // Extract and persist site_id so Business Profile works immediately
            $site_id = null;
            if (!empty($result['data']['site_id'])) {
                $site_id = $result['data']['site_id'];
            } elseif (!empty($result['data']['site']['id'])) {
                $site_id = $result['data']['site']['id'];
            }
            if ($site_id) {
                update_option('siloq_site_id', $site_id);
                set_transient('siloq_site_id', $site_id, HOUR_IN_SECONDS);
            }
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
     * AJAX: Sync all pages (batched to avoid PHP timeout)
     */
    public function ajax_sync_all_pages() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        // Extend execution time for large sites
        @set_time_limit(300);
        
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
        if ($batch_size < 1 || $batch_size > 200) {
            $batch_size = 50;
        }
        
        $sync_engine = new Siloq_Sync_Engine();
        $result = $sync_engine->sync_all_pages($offset, $batch_size);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Get sync status
     */
    public function ajax_get_sync_status() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $sync_engine = new Siloq_Sync_Engine();
        $result = $sync_engine->get_sync_status();
        
        wp_send_json_success($result);
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
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        $replace_content = isset($_POST['replace_content']) ? (bool) $_POST['replace_content'] : false;
        
        if (!$post_id || !$job_id) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
            return;
        }
        
        $import_handler = new Siloq_Content_Import();
        $result = $import_handler->import_content($post_id, $job_id, $replace_content);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Generate content
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
     * AJAX: Get business profile from Siloq
     */
    public function ajax_get_business_profile() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $api_client = new Siloq_API_Client();
        $site_id = $this->get_current_site_id();
        
        if (!$site_id) {
            wp_send_json_error(array('message' => 'No site connected. Please sync your pages first.'));
            return;
        }
        
        $result = $api_client->get_business_profile($site_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success($result);
    }

    /**
     * AJAX: Save business profile to Siloq
     */
    public function ajax_save_business_profile() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $api_client = new Siloq_API_Client();
        $site_id = $this->get_current_site_id();
        
        if (!$site_id) {
            wp_send_json_error(array('message' => 'No site connected. Please sync your pages first.'));
            return;
        }
        
        $profile_data = array(
            'business_type' => isset($_POST['business_type']) ? sanitize_text_field($_POST['business_type']) : '',
            'primary_services' => isset($_POST['primary_services']) ? array_map('sanitize_text_field', (array)$_POST['primary_services']) : array(),
            'service_areas' => isset($_POST['service_areas']) ? array_map('sanitize_text_field', (array)$_POST['service_areas']) : array(),
            'target_audience' => isset($_POST['target_audience']) ? sanitize_textarea_field($_POST['target_audience']) : '',
            'business_description' => isset($_POST['business_description']) ? sanitize_textarea_field($_POST['business_description']) : '',
        );
        
        $result = $api_client->save_business_profile($site_id, $profile_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'Business profile saved successfully!',
            'profile' => $result
        ));
    }

    /**
     * Get the current site ID from Siloq
     * Gets site_id from the auth/verify endpoint which works with API key auth
     */
    private function get_current_site_id() {
        // 1. Check persistent option first (survives restarts, no expiry)
        $site_id = get_option('siloq_site_id');
        if ($site_id) {
            return $site_id;
        }
        
        // 2. Check transient (legacy, kept for backward compat)
        $site_id = get_transient('siloq_site_id');
        if ($site_id) {
            update_option('siloq_site_id', $site_id);
            return $site_id;
        }
        
        // 3. Fetch from auth/verify endpoint
        $api_client = new Siloq_API_Client();
        $result = $api_client->test_connection();
        
        // site_id can be at data.site_id (legacy) or data.site.id (current API)
        $site_id = null;
        if ($result['success'] && !empty($result['data'])) {
            if (!empty($result['data']['site_id'])) {
                $site_id = $result['data']['site_id'];
            } elseif (!empty($result['data']['site']['id'])) {
                $site_id = $result['data']['site']['id'];
            }
        }
        if (!$site_id) {
            return null;
        }
        update_option('siloq_site_id', $site_id);
        set_transient('siloq_site_id', $site_id, HOUR_IN_SECONDS);
        return $site_id;
    }
    
    public function scan_content($request) {
        $params = $request->get_json_params();
        $page_id = isset($params['pageId']) ? intval($params['pageId']) : 0;
        
        // Mock scan results - in production this would call Siloq AI API
        return rest_ensure_response(array(
            'success' => true,
            'analysis' => array(
                'score' => rand(65, 95),
                'issues' => array(
                    array('type' => 'warning', 'message' => 'Add more keywords to improve SEO'),
                    array('type' => 'info', 'message' => 'Consider adding a stronger CTA'),
                )
            )
        ));
    }
    
    public function complete_wizard($request) {
        update_option('siloq_wizard_completed', true);
        update_option('siloq_wizard_completed_at', current_time('mysql'));
        return rest_ensure_response(array('success' => true));
    }
    
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
    // Add default options
    add_option('siloq_api_url', '');
    add_option('siloq_api_key', '');
    add_option('siloq_auto_sync', 'no');
    add_option('siloq_use_dummy_scan', 'yes');
    
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

// Initialize plugin
siloq_init();
