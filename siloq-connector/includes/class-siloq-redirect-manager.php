<?php
/**
 * Siloq Redirect Manager
 * Native redirect execution engine for Siloq plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Redirect_Manager {
    
    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'siloq_redirects';
    
    /**
     * Singleton instance
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
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Hook into template_redirect at priority 1 (before everything else)
        add_action('template_redirect', array($this, 'handle_redirects'), 1);
    }
    
    /**
     * Create redirects table
     * Called on plugin activation
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_url VARCHAR(2048) NOT NULL,
            target_url VARCHAR(2048) NOT NULL,
            redirect_type INT(11) DEFAULT 301,
            reason VARCHAR(255) DEFAULT NULL,
            created_by VARCHAR(100) DEFAULT 'siloq_api',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            hit_count INT(11) DEFAULT 0,
            last_hit_at DATETIME DEFAULT NULL,
            INDEX idx_source (source_url(191)),
            INDEX idx_active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Check if table was created successfully
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            error_log('[Siloq Redirect Manager] Failed to create redirects table');
            return false;
        }
        
        return true;
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // POST /wp-json/siloq/v1/redirects - Create a redirect
        register_rest_route('siloq/v1', '/redirects', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_create_redirect'),
            'permission_callback' => array($this, 'verify_api_auth')
        ));
        
        // GET /wp-json/siloq/v1/redirects - List all active redirects
        register_rest_route('siloq/v1', '/redirects', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_list_redirects'),
            'permission_callback' => array($this, 'verify_api_auth')
        ));
        
        // DELETE /wp-json/siloq/v1/redirects/{id} - Deactivate a redirect
        register_rest_route('siloq/v1', '/redirects/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'rest_delete_redirect'),
            'permission_callback' => array($this, 'verify_api_auth')
        ));
    }
    
    /**
     * Verify API authentication
     * Supports both Bearer token and X-Siloq-Signature
     */
    public function verify_api_auth($request) {
        // Method 1: Bearer token (API key)
        $auth_header = $request->get_header('Authorization');
        if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
            $provided_key = trim(substr($auth_header, 7));
            $api_key = get_option('siloq_api_key');
            
            if (!empty($api_key) && hash_equals($api_key, $provided_key)) {
                return true;
            }
        }
        
        // Method 2: Webhook signature (for backward compatibility)
        $signature = $request->get_header('X-Siloq-Signature');
        if ($signature) {
            $api_key = get_option('siloq_api_key');
            if (empty($api_key)) {
                return new WP_Error(
                    'not_configured',
                    __('Siloq API not configured', 'siloq-connector'),
                    array('status' => 500)
                );
            }
            
            $body = $request->get_body();
            $expected_signature = hash_hmac('sha256', $body, $api_key);
            
            if (hash_equals($expected_signature, $signature)) {
                return true;
            }
        }
        
        return new WP_Error(
            'unauthorized',
            __('Invalid or missing authentication', 'siloq-connector'),
            array('status' => 401)
        );
    }
    
    /**
     * REST: Create a redirect
     */
    public function rest_create_redirect($request) {
        global $wpdb;
        
        $data = $request->get_json_params();
        
        // Validate required fields
        if (empty($data['source_url']) || empty($data['target_url'])) {
            return new WP_Error(
                'missing_fields',
                __('Missing required fields: source_url and target_url', 'siloq-connector'),
                array('status' => 400)
            );
        }
        
        $source_url = sanitize_text_field($data['source_url']);
        $target_url = sanitize_text_field($data['target_url']);
        $redirect_type = isset($data['redirect_type']) ? intval($data['redirect_type']) : 301;
        $reason = isset($data['reason']) ? sanitize_text_field($data['reason']) : '';
        
        // Validate redirect type
        if (!in_array($redirect_type, array(301, 302, 307, 308))) {
            $redirect_type = 301;
        }
        
        // Insert into database
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $result = $wpdb->insert(
            $table_name,
            array(
                'source_url' => $source_url,
                'target_url' => $target_url,
                'redirect_type' => $redirect_type,
                'reason' => $reason,
                'created_by' => 'siloq_api',
                'created_at' => current_time('mysql'),
                'is_active' => 1,
                'hit_count' => 0
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d')
        );
        
        if ($result === false) {
            return new WP_Error(
                'db_error',
                sprintf(__('Database error: %s', 'siloq-connector'), $wpdb->last_error),
                array('status' => 500)
            );
        }
        
        $redirect_id = $wpdb->insert_id;
        
        return rest_ensure_response(array(
            'success' => true,
            'redirect_id' => $redirect_id,
            'message' => __('Redirect created successfully', 'siloq-connector')
        ));
    }
    
    /**
     * REST: List all active redirects
     */
    public function rest_list_redirects($request) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $redirects = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE is_active = 1 ORDER BY created_at DESC",
            ARRAY_A
        );
        
        if ($redirects === null) {
            return new WP_Error(
                'db_error',
                sprintf(__('Database error: %s', 'siloq-connector'), $wpdb->last_error),
                array('status' => 500)
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'redirects' => $redirects,
            'count' => count($redirects)
        ));
    }
    
    /**
     * REST: Delete (deactivate) a redirect
     */
    public function rest_delete_redirect($request) {
        global $wpdb;
        
        $redirect_id = intval($request['id']);
        
        if ($redirect_id <= 0) {
            return new WP_Error(
                'invalid_id',
                __('Invalid redirect ID', 'siloq-connector'),
                array('status' => 400)
            );
        }
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        // Check if redirect exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE id = %d",
            $redirect_id
        ));
        
        if (!$exists) {
            return new WP_Error(
                'not_found',
                __('Redirect not found', 'siloq-connector'),
                array('status' => 404)
            );
        }
        
        // Deactivate the redirect (soft delete)
        $result = $wpdb->update(
            $table_name,
            array('is_active' => 0),
            array('id' => $redirect_id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error(
                'db_error',
                sprintf(__('Database error: %s', 'siloq-connector'), $wpdb->last_error),
                array('status' => 500)
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Redirect deactivated successfully', 'siloq-connector')
        ));
    }
    
    /**
     * Handle redirects on template_redirect hook
     * Executes before WordPress loads the template
     */
    public function handle_redirects() {
        global $wpdb;
        
        // Get current URL path
        $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $current_url = home_url($current_path);
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        // Try exact match first
        $redirect = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE source_url = %s AND is_active = 1 LIMIT 1",
            $current_url
        ));
        
        // Try with/without trailing slash
        if (!$redirect) {
            $alt_url = rtrim($current_url, '/') === $current_url 
                ? $current_url . '/' 
                : rtrim($current_url, '/');
            
            $redirect = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE source_url = %s AND is_active = 1 LIMIT 1",
                $alt_url
            ));
        }
        
        // If redirect found, update hit count and execute
        if ($redirect) {
            // Update hit count and last hit time
            $wpdb->update(
                $table_name,
                array(
                    'hit_count' => $redirect->hit_count + 1,
                    'last_hit_at' => current_time('mysql')
                ),
                array('id' => $redirect->id),
                array('%d', '%s'),
                array('%d')
            );
            
            // Execute redirect
            wp_redirect($redirect->target_url, $redirect->redirect_type);
            exit;
        }
    }
}
