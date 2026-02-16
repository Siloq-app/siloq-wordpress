<?php
/**
 * Siloq TALI - Theme-Aware Layout Intelligence
 * 
 * Main TALI class that coordinates theme fingerprinting,
 * component discovery, and authority block injection.
 * 
 * @package Siloq
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_TALI {
    
    /**
     * TALI Version
     */
    const VERSION = '1.0';
    
    /**
     * Minimum confidence threshold for auto-publishing
     */
    const CONFIDENCE_THRESHOLD = 0.90;
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Theme fingerprinter instance
     */
    private $fingerprinter;
    
    /**
     * Component mapper instance
     */
    private $component_mapper;
    
    /**
     * Block injector instance
     */
    private $block_injector;
    
    /**
     * Cached design profile
     */
    private $design_profile = null;
    
    /**
     * Cached capability map
     */
    private $capability_map = null;
    
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
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * Initialize TALI components
     */
    private function init_components() {
        require_once plugin_dir_path(__FILE__) . 'class-siloq-tali-fingerprinter.php';
        require_once plugin_dir_path(__FILE__) . 'class-siloq-tali-component-mapper.php';
        require_once plugin_dir_path(__FILE__) . 'class-siloq-tali-block-injector.php';
        
        $this->fingerprinter = new Siloq_TALI_Fingerprinter();
        $this->component_mapper = new Siloq_TALI_Component_Mapper();
        $this->block_injector = new Siloq_TALI_Block_Injector();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Run fingerprint on plugin activation
        register_activation_hook(SILOQ_PLUGIN_DIR . 'siloq-connector.php', array($this, 'run_fingerprint'));
        
        // Run fingerprint on theme switch
        add_action('switch_theme', array($this, 'run_fingerprint'));
        add_action('customize_save_after', array($this, 'run_fingerprint'));
        
        // Admin menu for manual re-fingerprint
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX handlers
        add_action('wp_ajax_siloq_rerun_fingerprint', array($this, 'ajax_rerun_fingerprint'));
        add_action('wp_ajax_siloq_inject_authority', array($this, 'ajax_inject_authority'));
        
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Run full theme fingerprint
     */
    public function run_fingerprint() {
        // Generate design profile
        $this->design_profile = $this->fingerprinter->fingerprint_theme();
        $this->save_design_profile($this->design_profile);
        
        // Generate capability map
        $this->capability_map = $this->component_mapper->discover_components();
        $this->save_capability_map($this->capability_map);
        
        // Log the fingerprint
        $this->log_fingerprint_event();
        
        return array(
            'design_profile' => $this->design_profile,
            'capability_map' => $this->capability_map,
        );
    }
    
    /**
     * Get current design profile
     */
    public function get_design_profile() {
        if (null === $this->design_profile) {
            $this->design_profile = get_option('siloq_tali_design_profile', null);
        }
        return $this->design_profile;
    }
    
    /**
     * Get current capability map
     */
    public function get_capability_map() {
        if (null === $this->capability_map) {
            $this->capability_map = get_option('siloq_tali_capability_map', null);
        }
        return $this->capability_map;
    }
    
    /**
     * Save design profile to database
     */
    private function save_design_profile($profile) {
        update_option('siloq_tali_design_profile', $profile);
        
        // Also save as JSON file for debugging
        $upload_dir = wp_upload_dir();
        $tali_dir = $upload_dir['basedir'] . '/siloq-tali/';
        
        if (!file_exists($tali_dir)) {
            wp_mkdir_p($tali_dir);
        }
        
        file_put_contents(
            $tali_dir . 'design_profile_wp.json',
            wp_json_encode($profile, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Save capability map to database
     */
    private function save_capability_map($map) {
        update_option('siloq_tali_capability_map', $map);
        
        // Also save as JSON file for debugging
        $upload_dir = wp_upload_dir();
        $tali_dir = $upload_dir['basedir'] . '/siloq-tali/';
        
        if (!file_exists($tali_dir)) {
            wp_mkdir_p($tali_dir);
        }
        
        file_put_contents(
            $tali_dir . 'wp_component_capability_map.json',
            wp_json_encode($map, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Log fingerprint event
     */
    private function log_fingerprint_event() {
        $log = array(
            'timestamp' => current_time('mysql'),
            'theme' => wp_get_theme()->get('Name'),
            'tali_version' => self::VERSION,
        );
        
        $history = get_option('siloq_tali_fingerprint_history', array());
        array_unshift($history, $log);
        $history = array_slice($history, 0, 10); // Keep last 10
        update_option('siloq_tali_fingerprint_history', $history);
    }
    
    /**
     * Inject authority content into a page/post
     * 
     * @param int    $post_id      The post ID to inject into
     * @param string $template     Template type (service_city, blog_post, project_post)
     * @param array  $content_data The content/claims to inject
     * @param array  $options      Additional options
     * @return array Result with success status and any warnings
     */
    public function inject_authority($post_id, $template, $content_data, $options = array()) {
        // Get current profiles
        $design_profile = $this->get_design_profile();
        $capability_map = $this->get_capability_map();
        
        // Check if we have valid profiles
        if (empty($design_profile) || empty($capability_map)) {
            // Run fingerprint first
            $this->run_fingerprint();
            $design_profile = $this->get_design_profile();
            $capability_map = $this->get_capability_map();
        }
        
        // Check access state
        $access_state = isset($options['access_state']) ? $options['access_state'] : 'ENABLED';
        
        // Inject the content
        $result = $this->block_injector->inject(
            $post_id,
            $template,
            $content_data,
            $design_profile,
            $capability_map,
            $access_state
        );
        
        // Check confidence and set post status accordingly
        if ($result['confidence'] < self::CONFIDENCE_THRESHOLD) {
            // Set to draft and add admin notice
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'draft',
            ));
            
            $result['warnings'][] = sprintf(
                'Theme mapping confidence (%.0f%%) below threshold (%.0f%%). Post saved as draft for review.',
                $result['confidence'] * 100,
                self::CONFIDENCE_THRESHOLD * 100
            );
            
            // Store warning as post meta
            update_post_meta($post_id, '_siloq_tali_confidence_warning', true);
            update_post_meta($post_id, '_siloq_tali_confidence', $result['confidence']);
        }
        
        // Store TALI metadata
        update_post_meta($post_id, '_siloq_tali_injected', true);
        update_post_meta($post_id, '_siloq_tali_template', $template);
        update_post_meta($post_id, '_siloq_tali_timestamp', current_time('mysql'));
        
        return $result;
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_submenu_page(
            'siloq-settings',
            'TALI Settings',
            'Theme Intelligence',
            'manage_options',
            'siloq-tali',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $design_profile = $this->get_design_profile();
        $capability_map = $this->get_capability_map();
        $history = get_option('siloq_tali_fingerprint_history', array());
        
        include plugin_dir_path(__FILE__) . 'views/admin-tali-settings.php';
    }
    
    /**
     * AJAX handler for re-running fingerprint
     */
    public function ajax_rerun_fingerprint() {
        check_ajax_referer('siloq_tali_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $result = $this->run_fingerprint();
        wp_send_json_success($result);
    }
    
    /**
     * AJAX handler for injecting authority content
     */
    public function ajax_inject_authority() {
        check_ajax_referer('siloq_tali_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = intval($_POST['post_id']);
        $template = sanitize_text_field($_POST['template']);
        $content_data = json_decode(stripslashes($_POST['content_data']), true);
        $options = isset($_POST['options']) ? json_decode(stripslashes($_POST['options']), true) : array();
        
        $result = $this->inject_authority($post_id, $template, $content_data, $options);
        wp_send_json_success($result);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('siloq/v1', '/tali/fingerprint', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_run_fingerprint'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));
        
        register_rest_route('siloq/v1', '/tali/design-profile', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_design_profile'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));
        
        register_rest_route('siloq/v1', '/tali/capability-map', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_capability_map'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));
        
        register_rest_route('siloq/v1', '/tali/inject', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_inject_authority'),
            'permission_callback' => array($this, 'rest_permission_check'),
        ));
    }
    
    /**
     * REST permission check
     */
    public function rest_permission_check($request) {
        // Check for API key in header
        $api_key = $request->get_header('X-Siloq-API-Key');
        if (!empty($api_key)) {
            return $this->validate_api_key($api_key);
        }
        
        // Fall back to user capability check
        return current_user_can('edit_posts');
    }
    
    /**
     * Validate API key
     */
    private function validate_api_key($api_key) {
        // Delegate to main API client
        if (class_exists('Siloq_API_Client')) {
            $client = new Siloq_API_Client();
            return $client->validate_local_key($api_key);
        }
        return false;
    }
    
    /**
     * REST endpoint: Run fingerprint
     */
    public function rest_run_fingerprint($request) {
        $result = $this->run_fingerprint();
        return new WP_REST_Response($result, 200);
    }
    
    /**
     * REST endpoint: Get design profile
     */
    public function rest_get_design_profile($request) {
        $profile = $this->get_design_profile();
        if (empty($profile)) {
            return new WP_REST_Response(array('error' => 'No design profile found. Run fingerprint first.'), 404);
        }
        return new WP_REST_Response($profile, 200);
    }
    
    /**
     * REST endpoint: Get capability map
     */
    public function rest_get_capability_map($request) {
        $map = $this->get_capability_map();
        if (empty($map)) {
            return new WP_REST_Response(array('error' => 'No capability map found. Run fingerprint first.'), 404);
        }
        return new WP_REST_Response($map, 200);
    }
    
    /**
     * REST endpoint: Inject authority content
     */
    public function rest_inject_authority($request) {
        $post_id = $request->get_param('post_id');
        $template = $request->get_param('template');
        $content_data = $request->get_param('content_data');
        $options = $request->get_param('options') ?: array();
        
        if (empty($post_id) || empty($template) || empty($content_data)) {
            return new WP_REST_Response(array('error' => 'Missing required parameters'), 400);
        }
        
        $result = $this->inject_authority($post_id, $template, $content_data, $options);
        return new WP_REST_Response($result, 200);
    }
    
    /**
     * Get TALI status for display
     */
    public function get_status() {
        $design_profile = $this->get_design_profile();
        $capability_map = $this->get_capability_map();
        
        return array(
            'tali_version' => self::VERSION,
            'adapter' => 'wp_native',
            'extension_ready' => true,
            'has_design_profile' => !empty($design_profile),
            'has_capability_map' => !empty($capability_map),
            'theme' => array(
                'name' => wp_get_theme()->get('Name'),
                'version' => wp_get_theme()->get('Version'),
                'is_block_theme' => wp_is_block_theme(),
            ),
            'last_fingerprint' => get_option('siloq_tali_fingerprint_history', array())[0] ?? null,
        );
    }
}

/**
 * Get TALI instance
 */
function siloq_tali() {
    return Siloq_TALI::get_instance();
}
