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

        // Detect and cache which page builder created this page
        $builder = siloq_detect_builder($post_id);
        update_post_meta($post_id, '_siloq_page_builder', $builder);

        // Read SEO meta: AIOSEO (custom table) → Yoast → RankMath
        // AIOSEO 4.x stores meta in wp_aioseo_posts, NOT in standard post_meta.
        global $wpdb;
        $aioseo = $wpdb->get_row( $wpdb->prepare(
            "SELECT title, description FROM {$wpdb->prefix}aioseo_posts WHERE post_id = %d",
            $post->ID
        ) );

        $seo_title = ( $aioseo && ! empty( $aioseo->title ) )
            ? $aioseo->title
            : ( get_post_meta( $post->ID, '_yoast_wpseo_title', true )
                ?: get_post_meta( $post->ID, '_rank_math_title', true ) );

        $seo_description = ( $aioseo && ! empty( $aioseo->description ) )
            ? $aioseo->description
            : ( get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true )
                ?: get_post_meta( $post->ID, '_rank_math_description', true ) );

        $data = array(
            'wp_post_id'        => $post->ID,
            'title'             => $post->post_title,
            'content'           => $post->post_content,
            'url'               => get_permalink($post->ID),
            'type'              => $post->post_type,
            'status'            => $post->post_status,
            'author'            => get_the_author_meta('display_name', $post->post_author),
            'modified'          => $post->post_modified,
            'site_id'           => $this->site_id,
            'page_builder'      => $builder,  // 'elementor'|'cornerstone'|'divi'|'wpbakery'|'beaver_builder'|'gutenberg'|'standard'
            'yoast_title'       => $seo_title ?: '',        // Field name matches PageSyncSerializer
            'yoast_description' => $seo_description ?: '',  // Works for AIOSEO, Yoast, and RankMath
            'junk_action'       => get_post_meta( $post->ID, '_siloq_junk_action', true ) ?: null,
            'junk_reason'       => get_post_meta( $post->ID, '_siloq_junk_reason', true ) ?: null,
        );
        
        return $this->make_request('/pages/sync', 'POST', $data);
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $method = 'GET', $data = array()) {
        $url = $this->api_url . $endpoint;
        
        $args = array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            )
        );
        
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data);
            $response = wp_remote_post($url, $args);
        } else {
            $response = wp_remote_get($url, $args);
        }
        
        // Handle WP HTTP errors (network failures, DNS, etc.)
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'data'    => null,
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);
        $parsed      = json_decode($body, true);
        
        // 2xx = success
        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'data'    => $parsed,
                'message' => 'OK',
            );
        }
        
        // API returned an error status
        $error_message = isset($parsed['detail'])
            ? $parsed['detail']
            : ( isset($parsed['message']) ? $parsed['message'] : "HTTP {$status_code}" );
        
        return array(
            'success' => false,
            'message' => $error_message,
            'data'    => $parsed,
        );
    }
    
    /**
     * Generic POST request
     */
    public function post($endpoint, $data = array()) {
        return $this->make_request($endpoint, 'POST', $data);
    }
    
    /**
     * Generic GET request
     */
    public function get($endpoint) {
        return $this->make_request($endpoint, 'GET');
    }

    /**
     * Purge pages from Siloq DB that no longer exist in WordPress.
     * Call this after a full sync completes with the complete list of active WP post IDs.
     *
     * @param  array $active_wp_post_ids  All current published/draft post IDs from WP
     * @return array                      API response with deleted_count
     */
    public function purge_deleted_pages($active_wp_post_ids) {
        return $this->make_request('/pages/purge-deleted/', 'POST', array(
            'active_wp_post_ids' => array_values(array_map('intval', $active_wp_post_ids)),
        ));
    }
}
