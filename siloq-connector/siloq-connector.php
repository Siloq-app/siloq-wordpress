<?php
/**
 * Plugin Name: Siloq Connector
 * Plugin URI: https://siloq.com
 * Description: Connect WordPress to Siloq platform for AI-powered SEO content management and lead generation
 * Version: 1.0.0
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
define('SILOQ_VERSION', '1.0.0');
define('SILOQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SILOQ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SILOQ_PLUGIN_BASENAME', plugin_basename(__FILE__));

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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
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
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'siloq') === false) {
            return;
        }
        
        // Enqueue Tailwind
        wp_enqueue_script('tailwindcss', 'https://cdn.tailwindcss.com', array(), null, false);
        
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
    
    public function scan_content($request) {
        $params = $request->get_json_params();
        $content = isset($params['content']) ? sanitize_textarea_field($params['content']) : '';
        $page_id = isset($params['pageId']) ? intval($params['pageId']) : 0;
        
        // Mock scan results - in production this would call Siloq AI API
        return rest_ensure_response(array(
            'success' => true,
            'analysis' => array(
                'score' => rand(65, 95),
                'issues' => array(
                    array('type' => 'warning', 'message' => 'Add more keywords to improve SEO'),
                    array('type' => 'info', 'message' => 'Consider adding a stronger CTA'),
                ),
                'recommendations' => array(
                    'Add keyword-rich headings',
                    'Include internal links',
                    'Optimize meta description',
                ),
                'leadGenScore' => rand(70, 90),
            ),
        ));
    }
    
    public function complete_wizard($request) {
        $params = $request->get_json_params();
        
        if (isset($params['apiKey'])) {
            update_option('siloq_api_key', sanitize_text_field($params['apiKey']));
        }
        if (isset($params['siteId'])) {
            update_option('siloq_site_id', sanitize_text_field($params['siteId']));
        }
        
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

// Initialize plugin
Siloq_Connector::get_instance();
