<?php
/**
 * Siloq API Client
 * Handles communication with Siloq API
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_API_Client {
    
    /**
     * API URL
     */
    private $api_url;
    
    /**
     * API Key
     */
    private $api_key;
    
    /**
     * Site ID
     */
    private $site_id;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_url = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
        $this->api_key = get_option('siloq_api_key', '');
        $this->site_id = get_option('siloq_site_id', '');
    }
    
    /**
     * Test connection with credentials
     */
    public function test_connection_with_credentials($api_url, $api_key) {
        $response = wp_remote_get($api_url . '/test', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            return array(
                'success' => true,
                'message' => 'Connection successful',
                'data' => json_decode($body, true)
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $body,
                'status_code' => $status_code
            );
        }
    }
    
    /**
     * Create content job
     */
    public function create_content_job($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array('success' => false, 'message' => 'Post not found');
        }
        
        $data = array(
            'post_id' => $post_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'url' => get_permalink($post_id),
            'type' => $post->post_type
        );
        
        return $this->make_request('/content/jobs', 'POST', $data);
    }
    
    /**
     * Get job status
     */
    public function get_job_status($job_id) {
        return $this->make_request('/content/jobs/' . $job_id, 'GET');
    }
    
    /**
     * Get business profile
     */
    public function get_business_profile() {
        return $this->make_request('/business/profile', 'GET');
    }
    
    /**
     * Save business profile
     */
    public function save_business_profile($profile_data) {
        return $this->make_request('/business/profile', 'POST', $profile_data);
    }
    
    /**
     * Get sites
     */
    public function get_sites() {
        return $this->make_request('/sites', 'GET');
    }
    
    /**
     * Sync page
     */
    public function sync_page($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array('success' => false, 'message' => 'Post not found');
        }
        
        $data = array(
            'wp_post_id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'url' => get_permalink($post->ID),
            'type' => $post->post_type,
            'status' => $post->post_status,
            'author' => get_the_author_meta('display_name', $post->post_author),
            'modified' => $post->post_modified,
            'site_id' => $this->site_id
        );
        
        return $this->make_request('/pages/sync', 'POST', $data);
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'GET', $data = array()) {
        $url = $this->api_url . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'X-Site-ID' => $this->site_id
            ),
            'timeout' => 30
        );
        
        if (!empty($data) && $method !== 'GET') {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $data = json_decode($body, true);
        
        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'data' => $data
            );
        } else {
            return array(
                'success' => false,
                'message' => isset($data['message']) ? $data['message'] : 'API request failed',
                'status_code' => $status_code,
                'data' => $data
            );
        }
    }
}
