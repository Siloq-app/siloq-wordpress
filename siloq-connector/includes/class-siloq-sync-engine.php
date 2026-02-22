<?php
/**
 * Siloq Sync Engine
 * Handles synchronization of WordPress pages with Siloq platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Sync_Engine {
    
    /**
     * API client instance
     */
    private $api_client;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new Siloq_API_Client();
    }
    
    /**
     * Sync a single page
     * 
     * @param int $post_id WordPress post ID
     * @param bool $force_schema Whether to force schema fetch even if sync fails
     * @return array Result with success status and message
     */
    public function sync_page($post_id, $force_schema = false) {
        // Validate post
        $post = get_post($post_id);
        
        // Accept pages, posts, and WooCommerce products
        $allowed_types = array('page', 'post', 'product');
        
        if (!$post || !in_array($post->post_type, $allowed_types)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Unsupported content type: %s', 'siloq-connector'), $post ? $post->post_type : 'unknown')
            );
        }
        
        // Check if published
        if ($post->post_status !== 'publish') {
            return array(
                'success' => false,
                'message' => __('Only published content can be synced', 'siloq-connector')
            );
        }
        
        // Check if API is configured
        $api_url = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
        $api_key = get_option('siloq_api_key');
        
        if (empty($api_url) || empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('Siloq API is not configured. Please configure your API settings.', 'siloq-connector')
            );
        }
        
        // Sync via API
        $result = $this->api_client->sync_page($post_id);
        
        // If successful, also fetch schema markup
        if ($result['success']) {
            $schema_result = $this->api_client->get_schema_markup($post_id);
            // Note: Schema fetch failure doesn't fail the sync
            if (!$schema_result['success'] && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Siloq] Schema fetch failed for post ' . $post_id . ': ' . $schema_result['message']);
            }
        } elseif ($force_schema) {
            // Optionally try to fetch schema even if sync failed
            $this->api_client->get_schema_markup($post_id);
        }
        
        return $result;
    }
    
    /**
     * Sync a taxonomy term (e.g., product category) to Siloq
     * 
     * @param int $term_id Term ID
     * @param string $taxonomy Taxonomy name (e.g., 'product_cat')
     * @return array Result with success status
     */
    public function sync_taxonomy_term($term_id, $taxonomy = 'product_cat') {
        $term = get_term($term_id, $taxonomy);
        
        if (!$term || is_wp_error($term)) {
            return array(
                'success' => false,
                'message' => __('Invalid term', 'siloq-connector')
            );
        }
        
        // Check if API is configured
        $api_url = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
        $api_key = get_option('siloq_api_key');
        
        if (empty($api_url) || empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('Siloq API is not configured', 'siloq-connector')
            );
        }
        
        // Build term data to sync (as a "page" with special type)
        $term_link = get_term_link($term);
        $description = term_description($term_id, $taxonomy);
        
        // Get Yoast SEO term meta if available
        $yoast_title = get_term_meta($term_id, '_yoast_wpseo_title', true);
        $yoast_desc = get_term_meta($term_id, '_yoast_wpseo_metadesc', true);
        
        $term_data = array(
            'wp_post_id' => 'term_' . $term_id,
            'url' => is_wp_error($term_link) ? '' : $term_link,
            'title' => $term->name,
            'content' => $description ?: '',
            'excerpt' => wp_trim_words(strip_tags($description), 30),
            'status' => 'publish',
            'post_type' => $taxonomy,
            'slug' => $term->slug,
            'parent_id' => $term->parent,
            'is_homepage' => false,
            'is_noindex' => false,
            'meta' => array(
                'yoast_title' => $yoast_title ?: '',
                'yoast_description' => $yoast_desc ?: '',
            )
        );
        
        // Add category thumbnail if WooCommerce
        if ($taxonomy === 'product_cat' && function_exists('get_term_meta')) {
            $thumbnail_id = get_term_meta($term_id, 'thumbnail_id', true);
            if ($thumbnail_id) {
                $term_data['meta']['featured_image'] = wp_get_attachment_url($thumbnail_id);
            }
        }
        
        // Send to API
        $response = $this->api_client->request('POST', '/pages/sync/', $term_data);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 || $code === 201) {
            return array(
                'success' => true,
                'message' => __('Category synced successfully', 'siloq-connector'),
                'data' => $body
            );
        }
        
        $error_msg = isset($body['error']) ? $body['error'] : (isset($body['detail']) ? $body['detail'] : __('Sync failed', 'siloq-connector'));
        return array(
            'success' => false,
            'message' => $error_msg
        );
    }
    
    /**
     * Sync all published pages
     * 
     * @param int $offset Starting offset for batch processing
     * @param int $limit Maximum number of pages to sync (0 = all)
     * @return array Results with counts and details
     */
    public function sync_all_pages($offset = 0, $limit = 0) {
        // Sync ALL content types: pages, posts, and WooCommerce products
        $post_types = array('page', 'post');
        
        // Add WooCommerce product if available
        if (post_type_exists('product')) {
            $post_types[] = 'product';
        }
        
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC'
        );
        
        $pages = get_posts($args);
        
        $results = array(
            'total' => count($pages),
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => array(),
            'offset' => $offset,
            'has_more' => false,
            'content_types_synced' => $post_types
        );
        
        // Check if there are more items
        if ($limit > 0) {
            $total_count = 0;
            foreach ($post_types as $pt) {
                $counts = wp_count_posts($pt);
                $total_count += isset($counts->publish) ? $counts->publish : 0;
            }
            $results['has_more'] = ($offset + count($pages)) < $total_count;
            $results['total_available'] = $total_count;
        }
        
        foreach ($pages as $page) {
            // Skip if API is not configured
            $api_url = get_option('siloq_api_url', 'https://api.siloq.ai/api/v1');
            $api_key = get_option('siloq_api_key');
            
            if (empty($api_url) || empty($api_key)) {
                $results['skipped']++;
                $results['details'][] = array(
                    'id' => $page->ID,
                    'title' => $page->post_title,
                    'status' => 'skipped',
                    'message' => __('API not configured', 'siloq-connector')
                );
                continue;
            }
            
            $result = $this->sync_page($page->ID);
            
            if (isset($result['skipped']) && $result['skipped']) {
                $results['skipped']++;
                $status = 'skipped';
            } elseif ($result['success']) {
                $results['synced']++;
                $status = 'success';
            } else {
                $results['failed']++;
                $status = 'error';
            }
            
            $results['details'][] = array(
                'id' => $page->ID,
                'title' => $page->post_title,
                'post_type' => $page->post_type,
                'status' => $status,
                'message' => $result['message']
            );
            
            // Small delay to avoid overwhelming the API
            usleep(100000); // 0.1 second
        }
        
        // Also sync WooCommerce product categories if available
        if (taxonomy_exists('product_cat')) {
            $categories = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            ));
            
            if (!is_wp_error($categories) && !empty($categories)) {
                $results['categories_synced'] = 0;
                $results['categories_failed'] = 0;
                
                foreach ($categories as $category) {
                    $cat_result = $this->sync_taxonomy_term($category->term_id, 'product_cat');
                    
                    if ($cat_result['success']) {
                        $results['categories_synced']++;
                        $results['synced']++;
                    } else {
                        $results['categories_failed']++;
                        $results['failed']++;
                    }
                    
                    $results['details'][] = array(
                        'id' => 'cat_' . $category->term_id,
                        'title' => $category->name,
                        'post_type' => 'product_cat',
                        'status' => $cat_result['success'] ? 'success' : 'error',
                        'message' => $cat_result['message']
                    );
                    
                    usleep(100000);
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Import content from Siloq for a specific page
     * 
     * @param int $post_id WordPress post ID
     * @param string $job_id Content generation job ID
     * @return array Result with success status
     */
    public function import_content($post_id, $job_id) {
        // Get job status
        $job_result = $this->api_client->get_content_job_status($job_id);
        
        if (!$job_result['success']) {
            return $job_result;
        }
        
        $job_data = $job_result['data'];
        
        // Check if job is completed
        if ($job_data['status'] !== 'completed') {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Job is not completed yet. Current status: %s', 'siloq-connector'),
                    $job_data['status']
                )
            );
        }
        
        // Check if we have content
        if (empty($job_data['content'])) {
            return array(
                'success' => false,
                'message' => __('No content available', 'siloq-connector')
            );
        }
        
        // Create a draft post with the new content
        $new_post_id = wp_insert_post(array(
            'post_title' => $job_data['title'] ?? get_the_title($post_id) . ' (Generated)',
            'post_content' => $job_data['content'],
            'post_status' => 'draft', // Always create as draft for review
            'post_type' => 'page',
            'post_parent' => get_post_field('post_parent', $post_id)
        ));
        
        if (is_wp_error($new_post_id)) {
            return array(
                'success' => false,
                'message' => $new_post_id->get_error_message()
            );
        }
        
        // Store metadata
        update_post_meta($new_post_id, '_siloq_generated_from', $post_id);
        update_post_meta($new_post_id, '_siloq_content_job_id', $job_id);
        update_post_meta($new_post_id, '_siloq_imported_at', current_time('mysql'));
        
        // Store schema if available
        if (!empty($job_data['schema_markup'])) {
            update_post_meta($new_post_id, '_siloq_schema_markup', $job_data['schema_markup']);
        }
        
        // Store FAQs if available
        if (!empty($job_data['faq_items'])) {
            update_post_meta($new_post_id, '_siloq_faq_items', $job_data['faq_items']);
        }
        
        return array(
            'success' => true,
            'message' => __('Content imported successfully as draft', 'siloq-connector'),
            'data' => array(
                'post_id' => $new_post_id,
                'edit_url' => get_edit_post_link($new_post_id, 'raw')
            )
        );
    }
    
    /**
     * Get sync status for all content (pages, posts, products)
     * 
     * @return array Array of content sync statuses
     */
    public function get_all_sync_status() {
        // Query ALL content types: pages, posts, and WooCommerce products
        $post_types = array('page', 'post');
        if (post_type_exists('product')) {
            $post_types[] = 'product';
        }
        
        $posts = get_posts(array(
            'post_type' => $post_types,
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft')
        ));
        
        $status_data = array();
        
        foreach ($posts as $post) {
            $last_synced = get_post_meta($post->ID, '_siloq_last_synced', true);
            $sync_status = get_post_meta($post->ID, '_siloq_sync_status', true);
            $has_schema = !empty(get_post_meta($post->ID, '_siloq_schema_markup', true));
            $siloq_page_id = get_post_meta($post->ID, '_siloq_page_id', true);
            
            $status_data[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'edit_url' => get_edit_post_link($post->ID, 'raw'),
                'status' => $post->post_status,
                'post_type' => $post->post_type,
                'last_synced' => $last_synced ? $last_synced : __('Never', 'siloq-connector'),
                'sync_status' => $sync_status ? $sync_status : 'not_synced',
                'has_schema' => $has_schema,
                'siloq_page_id' => $siloq_page_id,
                'modified' => $post->post_modified
            );
        }
        
        // Also include WooCommerce product categories
        if (taxonomy_exists('product_cat')) {
            $categories = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            ));
            
            if (!is_wp_error($categories)) {
                foreach ($categories as $cat) {
                    $status_data[] = array(
                        'id' => 'term_' . $cat->term_id,
                        'title' => $cat->name,
                        'url' => get_term_link($cat),
                        'edit_url' => get_edit_term_link($cat->term_id, 'product_cat'),
                        'status' => 'publish',
                        'post_type' => 'product_cat',
                        'last_synced' => __('N/A', 'siloq-connector'),
                        'sync_status' => 'not_synced',
                        'has_schema' => false,
                        'siloq_page_id' => null,
                        'modified' => null
                    );
                }
            }
        }
        
        return $status_data;
    }
    
    /**
     * Clear sync metadata for a page
     * 
     * @param int $post_id WordPress post ID
     * @return bool Success status
     */
    public function clear_sync_data($post_id) {
        delete_post_meta($post_id, '_siloq_last_synced');
        delete_post_meta($post_id, '_siloq_sync_status');
        delete_post_meta($post_id, '_siloq_page_id');
        delete_post_meta($post_id, '_siloq_schema_markup');
        delete_post_meta($post_id, '_siloq_faq_items');
        delete_post_meta($post_id, '_siloq_content_job_id');
        delete_post_meta($post_id, '_siloq_content_job_status');
        
        return true;
    }
    
    /**
     * Check if a page needs re-sync
     * (if modified after last sync)
     * 
     * @param int $post_id WordPress post ID
     * @return bool True if needs re-sync
     */
    public function needs_resync($post_id) {
        $last_synced = get_post_meta($post_id, '_siloq_last_synced', true);
        
        if (empty($last_synced)) {
            return true; // Never synced
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        $last_synced_timestamp = strtotime($last_synced);
        $last_modified_timestamp = strtotime($post->post_modified_gmt);
        
        return $last_modified_timestamp > $last_synced_timestamp;
    }
    
    /**
     * Get content that needs re-sync (pages, posts, products)
     * 
     * @return array Array of post IDs
     */
    public function get_pages_needing_resync() {
        // Query ALL content types
        $post_types = array('page', 'post');
        if (post_type_exists('product')) {
            $post_types[] = 'product';
        }
        
        $posts = get_posts(array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1
        ));
        
        $needs_resync = array();
        
        foreach ($posts as $post) {
            if ($this->needs_resync($post->ID)) {
                $needs_resync[] = $post->ID;
            }
        }
        
        return $needs_resync;
    }
    
    /**
     * Sync pages that need re-sync
     * 
     * @param int $limit Maximum number of pages to sync
     * @return array Results
     */
    public function sync_outdated_pages($limit = 10) {
        $page_ids = $this->get_pages_needing_resync();
        
        if (empty($page_ids)) {
            return array(
                'success' => true,
                'message' => __('All pages are up to date', 'siloq-connector'),
                'synced' => 0
            );
        }
        
        // Limit the number of pages to sync
        $page_ids = array_slice($page_ids, 0, $limit);
        
        $results = array(
            'total' => count($page_ids),
            'synced' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        foreach ($page_ids as $page_id) {
            $result = $this->sync_page($page_id);
            
            if ($result['success']) {
                $results['synced']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][] = array(
                'id' => $page_id,
                'title' => get_the_title($page_id),
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message']
            );
            
            usleep(100000); // 0.1 second delay
        }
        
        return $results;
    }
}
