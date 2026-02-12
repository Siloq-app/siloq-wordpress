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
        // Backend returns 'valid' field (not 'authenticated')
        if ($code === 200 && isset($body['valid']) && $body['valid']) {
            return array(
                'success' => true,
                'message' => __('Connection successful!', 'siloq-connector'),
                'data' => $body
            );
        }
        // Backend may return error in 'error' or 'detail' field
        $error_msg = isset($body['error']) ? $body['error'] : (isset($body['detail']) ? $body['detail'] : __('Authentication failed', 'siloq-connector'));
        return array(
            'success' => false,
            'message' => $error_msg
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
        
        // Check if this is the homepage (front page)
        $front_page_id = (int) get_option('page_on_front');
        $is_homepage = ($front_page_id > 0 && $post->ID === $front_page_id);
        
        // Check noindex status across ALL major SEO plugins
        $is_noindex = $this->check_noindex_status($post->ID);
        
        // Prepare page data
        $page_data = array(
            'wp_post_id' => $post->ID,
            'url' => get_permalink($post->ID),
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'post_type' => $post->post_type, // page, post, product, etc.
            'published_at' => $post->post_date_gmt,
            'modified_at' => $post->post_modified_gmt,
            'slug' => $post->post_name,
            'parent_id' => $post->post_parent,
            'menu_order' => $post->menu_order,
            'is_homepage' => $is_homepage,
            'is_noindex' => $is_noindex,
            'meta' => $this->get_seo_meta($post->ID)
        );
        
        // Add WooCommerce product-specific data
        if ($post->post_type === 'product' && class_exists('WooCommerce')) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $page_data['product_data'] = array(
                    'price' => $product->get_price(),
                    'regular_price' => $product->get_regular_price(),
                    'sale_price' => $product->get_sale_price(),
                    'sku' => $product->get_sku(),
                    'stock_status' => $product->get_stock_status(),
                    'categories' => wp_get_post_terms($post->ID, 'product_cat', array('fields' => 'names')),
                    'tags' => wp_get_post_terms($post->ID, 'product_tag', array('fields' => 'names')),
                );
            }
        }
        
        // Send to Siloq API
        $response = $this->make_request('POST', '/pages/sync/', $page_data);
        
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
        
        // Build detailed error message for debugging
        $error_msg = '';
        if (isset($body['error'])) {
            $error_msg = $body['error'];
        } elseif (isset($body['detail'])) {
            $error_msg = $body['detail'];
        } elseif (isset($body['message'])) {
            $error_msg = $body['message'];
        } elseif (is_array($body) && !empty($body)) {
            // DRF validation errors: {"field": ["error message"]}
            $errors = array();
            foreach ($body as $field => $messages) {
                if (is_array($messages)) {
                    $errors[] = $field . ': ' . implode(', ', $messages);
                } else {
                    $errors[] = $field . ': ' . $messages;
                }
            }
            $error_msg = implode('; ', $errors);
        } else {
            $error_msg = sprintf(__('Sync failed (HTTP %d)', 'siloq-connector'), $code);
        }
        
        return array(
            'success' => false,
            'message' => $error_msg
        );
    }
    
    /**
     * Sync a taxonomy term (e.g., WooCommerce product category) to Siloq
     * 
     * @param int $term_id WordPress term ID
     * @param string $taxonomy Taxonomy name (e.g., 'product_cat')
     * @return array Response data
     */
    public function sync_taxonomy_term($term_id, $taxonomy = 'product_cat') {
        $term = get_term($term_id, $taxonomy);
        
        if (!$term || is_wp_error($term)) {
            return array(
                'success' => false,
                'message' => __('Term not found', 'siloq-connector')
            );
        }
        
        // Get term link (URL)
        $term_link = get_term_link($term);
        if (is_wp_error($term_link)) {
            $term_link = '';
        }
        
        // Get term description
        $description = term_description($term_id, $taxonomy);
        
        // Check for Yoast SEO term meta
        $yoast_title = get_term_meta($term_id, '_yoast_wpseo_title', true);
        $yoast_desc = get_term_meta($term_id, '_yoast_wpseo_metadesc', true);
        
        // Get parent term info
        $parent_id = $term->parent;
        $parent_name = '';
        if ($parent_id > 0) {
            $parent_term = get_term($parent_id, $taxonomy);
            if ($parent_term && !is_wp_error($parent_term)) {
                $parent_name = $parent_term->name;
            }
        }
        
        // Prepare term data (sent as a "page" with special type)
        $term_data = array(
            'wp_post_id' => 'term_' . $term_id, // Use term_ prefix to distinguish
            'wp_term_id' => $term_id,
            'url' => $term_link,
            'title' => $term->name,
            'content' => $description,
            'excerpt' => wp_trim_words(strip_tags($description), 30),
            'status' => 'publish',
            'post_type' => $taxonomy, // product_cat, category, etc.
            'published_at' => null,
            'modified_at' => null,
            'slug' => $term->slug,
            'parent_id' => $parent_id,
            'parent_name' => $parent_name,
            'menu_order' => 0,
            'is_homepage' => false,
            'is_noindex' => false,
            'is_taxonomy' => true,
            'term_count' => $term->count, // Number of posts/products in this term
            'meta' => array(
                'yoast_title' => $yoast_title ?: '',
                'yoast_description' => $yoast_desc ?: '',
                'featured_image' => '' // Could add category thumbnail if needed
            )
        );
        
        // Add category thumbnail if WooCommerce
        if ($taxonomy === 'product_cat' && function_exists('get_term_meta')) {
            $thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
            if ($thumbnail_id) {
                $term_data['meta']['featured_image'] = wp_get_attachment_url($thumbnail_id);
            }
        }
        
        // Send to Siloq API
        $response = $this->make_request('POST', '/pages/sync/', $term_data);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200 || $code === 201) {
            // Update sync metadata on term
            update_term_meta($term_id, '_siloq_last_synced', current_time('mysql'));
            update_term_meta($term_id, '_siloq_sync_status', 'synced');
            
            if (isset($body['page_id'])) {
                update_term_meta($term_id, '_siloq_page_id', $body['page_id']);
            }
            
            return array(
                'success' => true,
                'message' => __('Category synced successfully', 'siloq-connector'),
                'data' => $body
            );
        }
        
        update_term_meta($term_id, '_siloq_sync_status', 'error');
        
        $error_msg = '';
        if (isset($body['error'])) {
            $error_msg = $body['error'];
        } elseif (isset($body['detail'])) {
            $error_msg = $body['detail'];
        } elseif (isset($body['message'])) {
            $error_msg = $body['message'];
        } else {
            $error_msg = sprintf(__('Sync failed (HTTP %d)', 'siloq-connector'), $code);
        }
        
        return array(
            'success' => false,
            'message' => $error_msg
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
        
        $response = $this->make_request('GET', "/pages/{$siloq_page_id}/schema/", array());
        
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
        
        $response = $this->make_request('POST', '/content-jobs/', $job_data);
        
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
        $response = $this->make_request('GET', "/content-jobs/{$job_id}/", array());
        
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
     * Get SEO meta (title, description) from whatever SEO plugin is active.
     * Supports: Yoast, AIOSEO, RankMath, SEOPress, The SEO Framework, and more.
     * 
     * @param int $post_id WordPress post ID
     * @return array SEO meta data
     */
    private function get_seo_meta($post_id) {
        $meta = array(
            'seo_title' => '',
            'seo_description' => '',
            'featured_image' => get_the_post_thumbnail_url($post_id, 'full') ?: '',
            'seo_plugin' => 'none'
        );
        
        // 1. Yoast SEO
        $yoast_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        $yoast_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if ($yoast_title || $yoast_desc) {
            $meta['seo_title'] = $yoast_title ?: '';
            $meta['seo_description'] = $yoast_desc ?: '';
            $meta['seo_plugin'] = 'yoast';
            return $meta;
        }
        
        // 2. All in One SEO (AIOSEO)
        $aioseo_title = get_post_meta($post_id, '_aioseo_title', true);
        $aioseo_desc = get_post_meta($post_id, '_aioseo_description', true);
        if ($aioseo_title || $aioseo_desc) {
            $meta['seo_title'] = $aioseo_title ?: '';
            $meta['seo_description'] = $aioseo_desc ?: '';
            $meta['seo_plugin'] = 'aioseo';
            return $meta;
        }
        
        // 3. Rank Math
        $rm_title = get_post_meta($post_id, 'rank_math_title', true);
        $rm_desc = get_post_meta($post_id, 'rank_math_description', true);
        if ($rm_title || $rm_desc) {
            $meta['seo_title'] = $rm_title ?: '';
            $meta['seo_description'] = $rm_desc ?: '';
            $meta['seo_plugin'] = 'rankmath';
            return $meta;
        }
        
        // 4. SEOPress
        $sp_title = get_post_meta($post_id, '_seopress_titles_title', true);
        $sp_desc = get_post_meta($post_id, '_seopress_titles_desc', true);
        if ($sp_title || $sp_desc) {
            $meta['seo_title'] = $sp_title ?: '';
            $meta['seo_description'] = $sp_desc ?: '';
            $meta['seo_plugin'] = 'seopress';
            return $meta;
        }
        
        // 5. The SEO Framework
        $tsf_title = get_post_meta($post_id, '_genesis_title', true);
        $tsf_desc = get_post_meta($post_id, '_genesis_description', true);
        if ($tsf_title || $tsf_desc) {
            $meta['seo_title'] = $tsf_title ?: '';
            $meta['seo_description'] = $tsf_desc ?: '';
            $meta['seo_plugin'] = 'theseoframework';
            return $meta;
        }
        
        // 6. Slim SEO (uses default WP fields, no custom meta)
        
        // 7. SmartCrawl
        $sc_title = get_post_meta($post_id, '_wds_title', true);
        $sc_desc = get_post_meta($post_id, '_wds_metadesc', true);
        if ($sc_title || $sc_desc) {
            $meta['seo_title'] = $sc_title ?: '';
            $meta['seo_description'] = $sc_desc ?: '';
            $meta['seo_plugin'] = 'smartcrawl';
            return $meta;
        }
        
        return $meta;
    }
    
    /**
     * Check if a post/page is set to noindex across ALL major SEO plugins.
     * Supports: Yoast, AIOSEO, RankMath, SEOPress, The SEO Framework, Slim SEO
     * 
     * @param int $post_id WordPress post ID
     * @return bool True if noindex is set
     */
    private function check_noindex_status($post_id) {
        // 1. Yoast SEO
        $yoast = get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
        if ($yoast === '1' || $yoast === 1) {
            return true;
        }
        
        // 2. All in One SEO (AIOSEO)
        $aioseo = get_post_meta($post_id, '_aioseo_noindex', true);
        if ($aioseo === '1' || $aioseo === 1 || $aioseo === true) {
            return true;
        }
        // AIOSEO also stores in a serialized array
        $aioseo_data = get_post_meta($post_id, '_aioseo_og_article_section', true); // Check if AIOSEO is active
        $aioseo_robots = get_post_meta($post_id, '_aioseo_robots_noindex', true);
        if ($aioseo_robots) {
            return true;
        }
        
        // 3. Rank Math
        $rankmath = get_post_meta($post_id, 'rank_math_robots', true);
        if (is_array($rankmath) && in_array('noindex', $rankmath)) {
            return true;
        }
        if (is_string($rankmath) && strpos($rankmath, 'noindex') !== false) {
            return true;
        }
        
        // 4. SEOPress
        $seopress = get_post_meta($post_id, '_seopress_robots_index', true);
        if ($seopress === 'yes' || $seopress === '1') {
            return true;
        }
        
        // 5. The SEO Framework
        $tsf = get_post_meta($post_id, '_genesis_noindex', true);
        if ($tsf === '1' || $tsf === 1) {
            return true;
        }
        $tsf_exclude = get_post_meta($post_id, 'exclude_from_archive', true);
        if ($tsf_exclude === '1') {
            return true;
        }
        
        // 6. Slim SEO
        $slim = get_post_meta($post_id, 'slim_seo_robots', true);
        if (is_array($slim) && in_array('noindex', $slim)) {
            return true;
        }
        
        // 7. SmartCrawl
        $smartcrawl = get_post_meta($post_id, '_wds_meta-robots-noindex', true);
        if ($smartcrawl === '1') {
            return true;
        }
        
        // 8. Squirrly SEO
        $squirrly = get_post_meta($post_id, '_sq_robots_noindex', true);
        if ($squirrly === '1' || $squirrly === 1) {
            return true;
        }
        
        // 9. Check WordPress core robots filter (WP 5.7+)
        // This catches any plugin using the wp_robots filter
        if (function_exists('wp_robots')) {
            // Note: This would require rendering the page, so we skip for performance
            // The above plugin checks should cover 95%+ of cases
        }
        
        return false;
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
