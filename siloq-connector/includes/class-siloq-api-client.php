<?php
/**
 * Siloq API Client
 * Handles all communication with the Siloq backend API
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_API_Client {
    
    /**
     * API base URL
     */
    private $api_url;
    
    /**
     * API key for authentication
     */
    private $api_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_url = rtrim(get_option('siloq_api_url'), '/');
        $this->api_key = get_option('siloq_api_key');
    }
    
    /**
     * Test API connection (uses saved options from constructor).
     */
    public function test_connection() {
        if (empty($this->api_url) || empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('API URL and API Key are required', 'siloq-connector')
            );
        }
        return $this->test_connection_with_credentials($this->api_url, $this->api_key);
    }

    /**
     * Test API connection using provided URL and key (e.g. from form before save).
     * Use this so "Test Connection" works with current form values without saving first.
     *
     * @param string $api_url Base API URL (e.g. http://localhost:8000/api/v1).
     * @param string $api_key API key.
     * @return array { success: bool, message: string, data?: array }
     */
    public function test_connection_with_credentials($api_url, $api_key) {
        $api_url = is_string($api_url) ? trim($api_url) : '';
        $api_key = is_string($api_key) ? trim($api_key) : '';
        if (empty($api_url) || empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('API URL and API Key are required', 'siloq-connector')
            );
        }
        $base = rtrim($api_url, '/');
        $url = $base . '/auth/verify';
        $args = array(
            'method'  => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'   => 'application/json',
                'Accept'        => 'application/json',
                'User-Agent'    => 'Siloq-WordPress-Plugin/' . SILOQ_VERSION,
            ),
            'timeout' => 30,
            'sslverify' => true,
        );
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            $msg = $response->get_error_message();
            // Friendlier message for "could not connect" (e.g. WordPress in Docker, API on host)
            if (strpos($msg, 'Failed to connect') !== false || strpos($msg, 'Could not connect') !== false) {
                $hint = '';
                if (strpos($api_url, 'localhost') !== false) {
                    $hint = ' ' . __('If WordPress runs in Docker, use host.docker.internal instead of localhost (e.g. http://host.docker.internal:8000/api/v1). Otherwise ensure the Siloq API is running and reachable.', 'siloq-connector');
                } else {
                    $hint = ' ' . __('Ensure the Siloq API is running and the URL is reachable from this server.', 'siloq-connector');
                }
                $msg = $msg . $hint;
            }
            return array(
                'success' => false,
                'message' => $msg
            );
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200 && isset($body['authenticated']) && $body['authenticated']) {
            return array(
                'success' => true,
                'message' => __('Connection successful!', 'siloq-connector'),
                'data' => $body
            );
        }
        return array(
            'success' => false,
            'message' => isset($body['error']) ? $body['error'] : __('Authentication failed', 'siloq-connector')
        );
    }
    
    /**
     * Sync a page to Siloq
     * 
     * @param int $post_id WordPress post ID
     * @return array Response data
     */
    public function sync_page($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return array(
                'success' => false,
                'message' => __('Page not found', 'siloq-connector')
            );
        }
        
        // Prepare page data
        $page_data = array(
            'wp_post_id' => $post->ID,
            'url' => get_permalink($post->ID),
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'published_at' => $post->post_date_gmt,
            'modified_at' => $post->post_modified_gmt,
            'slug' => $post->post_name,
            'parent_id' => $post->post_parent,
            'menu_order' => $post->menu_order,
            'meta' => array(
                'yoast_title' => get_post_meta($post->ID, '_yoast_wpseo_title', true),
                'yoast_description' => get_post_meta($post->ID, '_yoast_wpseo_metadesc', true),
                'featured_image' => get_the_post_thumbnail_url($post->ID, 'full')
            )
        );
        
        // Send to Siloq API
        $response = $this->make_request('POST', '/pages/sync', $page_data);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200 || $code === 201) {
            // Update sync metadata
            update_post_meta($post->ID, '_siloq_last_synced', current_time('mysql'));
            update_post_meta($post->ID, '_siloq_sync_status', 'synced');
            
            // Store Siloq page ID if returned
            if (isset($body['page_id'])) {
                update_post_meta($post->ID, '_siloq_page_id', $body['page_id']);
            }
            
            return array(
                'success' => true,
                'message' => __('Page synced successfully', 'siloq-connector'),
                'data' => $body
            );
        }
        
        update_post_meta($post->ID, '_siloq_sync_status', 'error');
        
        return array(
            'success' => false,
            'message' => isset($body['error']) ? $body['error'] : __('Sync failed', 'siloq-connector')
        );
    }
    
    /**
     * Get schema markup for a page
     * 
     * @param int $post_id WordPress post ID
     * @return array Response data
     */
    public function get_schema_markup($post_id) {
        $siloq_page_id = get_post_meta($post_id, '_siloq_page_id', true);
        
        if (empty($siloq_page_id)) {
            return array(
                'success' => false,
                'message' => __('Page not synced with Siloq', 'siloq-connector')
            );
        }
        
        $response = $this->make_request('GET', "/pages/{$siloq_page_id}/schema", array());
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200 && isset($body['schema_markup'])) {
            // Validate and sanitize schema markup (should be valid JSON)
            $schema_markup = $body['schema_markup'];
            
            // If it's a string, try to decode it to validate it's valid JSON
            if (is_string($schema_markup)) {
                $decoded = json_decode($schema_markup, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Re-encode to ensure consistent formatting
                    $schema_markup = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                } else {
                    // Invalid JSON, log error but don't fail
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[Siloq] Invalid schema markup JSON: ' . json_last_error_msg());
                    }
                }
            }
            
            // Store schema markup in post meta
            update_post_meta($post_id, '_siloq_schema_markup', $schema_markup);
            update_post_meta($post_id, '_siloq_schema_updated_at', current_time('mysql'));
            
            return array(
                'success' => true,
                'message' => __('Schema markup retrieved', 'siloq-connector'),
                'data' => $body
            );
        }
        
        return array(
            'success' => false,
            'message' => isset($body['error']) ? $body['error'] : __('Failed to retrieve schema', 'siloq-connector')
        );
    }
    
    /**
     * Create a content generation job
     * 
     * @param int $post_id WordPress post ID
     * @param array $options Generation options
     * @return array Response data
     */
    public function create_content_job($post_id, $options = array()) {
        $siloq_page_id = get_post_meta($post_id, '_siloq_page_id', true);
        
        if (empty($siloq_page_id)) {
            return array(
                'success' => false,
                'message' => __('Page not synced with Siloq', 'siloq-connector')
            );
        }
        
        $job_data = array_merge(array(
            'page_id' => $siloq_page_id,
            'wp_post_id' => $post_id,
            'job_type' => 'content_generation'
        ), $options);
        
        $response = $this->make_request('POST', '/content-jobs', $job_data);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 201 && isset($body['job_id'])) {
            update_post_meta($post_id, '_siloq_content_job_id', $body['job_id']);
            update_post_meta($post_id, '_siloq_content_job_status', 'pending');
            
            return array(
                'success' => true,
                'message' => __('Content generation job created', 'siloq-connector'),
                'data' => $body
            );
        }
        
        return array(
            'success' => false,
            'message' => isset($body['error']) ? $body['error'] : __('Failed to create job', 'siloq-connector')
        );
    }
    
    /**
     * Get status of a content generation job
     * 
     * @param string $job_id Job ID
     * @return array Response data
     */
    public function get_content_job_status($job_id) {
        $response = $this->make_request('GET', "/content-jobs/{$job_id}", array());
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200) {
            return array(
                'success' => true,
                'data' => $body
            );
        }
        
        return array(
            'success' => false,
            'message' => isset($body['error']) ? $body['error'] : __('Failed to get job status', 'siloq-connector')
        );
    }
    
    /**
     * Make an HTTP request to the Siloq API
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint (without base URL)
     * @param array $data Request data
     * @return array|WP_Error Response or error
     */
    private function make_request($method, $endpoint, $data = array()) {
        if (empty($this->api_url) || empty($this->api_key)) {
            return new WP_Error(
                'siloq_config_error',
                __('Siloq API is not configured. Please check your settings.', 'siloq-connector')
            );
        }
        
        $url = $this->api_url . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Siloq-WordPress-Plugin/' . SILOQ_VERSION
            ),
            'timeout' => 30,
            'sslverify' => true
        );
        
        if (!empty($data) && ($method === 'POST' || $method === 'PUT')) {
            $args['body'] = json_encode($data);
        } elseif (!empty($data) && $method === 'GET') {
            $url = add_query_arg($data, $url);
        }
        
        $response = wp_remote_request($url, $args);
        
        // Log the request for debugging (can be removed in production)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $status = is_wp_error($response) ? 'Error: ' . $response->get_error_message() : wp_remote_retrieve_response_code($response);
            error_log(sprintf(
                '[Siloq API] %s %s - Status: %s',
                $method,
                $endpoint,
                $status
            ));
        }
        
        // Enhanced error handling for common HTTP errors
        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            $error_message = $response->get_error_message();
            
            // Provide more helpful error messages
            if (strpos($error_message, 'curl') !== false || strpos($error_message, 'resolve') !== false) {
                return new WP_Error(
                    'siloq_connection_error',
                    __('Cannot connect to Siloq API. Please check your API URL and network connection.', 'siloq-connector'),
                    array('original_error' => $error_message)
                );
            }
            
            if (strpos($error_message, 'timeout') !== false) {
                return new WP_Error(
                    'siloq_timeout_error',
                    __('Connection to Siloq API timed out. Please try again.', 'siloq-connector'),
                    array('original_error' => $error_message)
                );
            }
        }
        
        return $response;
    }

    /**
     * Public request method for scanner and other callers
     *
     * @param string $method   HTTP method (GET, POST, etc.)
     * @param string $endpoint Endpoint path (e.g. /scans)
     * @param array  $data     Request body/data
     * @return array|WP_Error
     */
    public function request($method, $endpoint, $data = array()) {
        return $this->make_request($method, $endpoint, $data);
    }
    
    /**
     * Validate API credentials
     * 
     * @param string $api_url API URL
     * @param string $api_key API Key
     * @return bool True if valid
     */
    public static function validate_credentials($api_url, $api_key) {
        if (empty($api_url) || empty($api_key)) {
            return false;
        }
        
        // Basic URL validation
        if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Basic API key format validation (adjust as needed)
        if (strlen($api_key) < 20) {
            return false;
        }
        
        return true;
    }
}
