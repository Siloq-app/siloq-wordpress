<?php
/**
 * Siloq Sync Engine
 * Handles synchronization of WordPress content with Siloq platform
 * Supports: Pages, Posts, WooCommerce Products, WooCommerce Categories
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
     * Supported post types for sync
     */
    private $supported_post_types = array('page', 'post', 'product');
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new Siloq_API_Client();
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Get post types to sync based on what's available
     */
    private function get_syncable_post_types() {
        $types = array('page', 'post');
        
        if ($this->is_woocommerce_active()) {
            $types[] = 'product';
        }
        
        return $types;
    }
    
    /**
     * Sync a single post/page/product
     * 
     * @param int $post_id WordPress post ID
     * @param bool $force_schema Whether to force schema fetch even if sync fails
     * @return array Result with success status and message
     */
    public function sync_page($post_id, $force_schema = false) {
        // Validate post
        $post = get_post($post_id);
        if (!$post) {
            return array(
                'success' => false,
                'message' => __('Invalid post ID', 'siloq-connector')
            );
        }
        
        // Check if post type is supported
        if (!in_array($post->post_type, $this->get_syncable_post_types())) {
            return array(
                'success' => false,
                'message' => sprintf(__('Post type "%s" is not supported', 'siloq-connector'), $post->post_type)
            );
        }
        
        // Check if post is published
        if ($post->post_status !== 'publish') {
            return array(
                'success' => false,
                'message' => __('Only published content can be synced', 'siloq-connector')
            );
        }
        
        // Check if API is configured
        $api_url = get_option('siloq_api_url');
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
            if (!$schema_result['success'] && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Siloq] Schema fetch failed for post ' . $post_id . ': ' . $schema_result['message']);
            }
        } elseif ($force_schema) {
            $this->api_client->get_schema_markup($post_id);
        }
        
        return $result;
    }
    
    /**
     * Sync a WooCommerce product category (taxonomy term)
     * 
     * @param int $term_id Term ID
     * @return array Result with success status and message
     */
    public function sync_product_category($term_id) {
        if (!$this->is_woocommerce_active()) {
            return array(
                'success' => false,
                'message' => __('WooCommerce is not active', 'siloq-connector')
            );
        }
        
        $term = get_term($term_id, 'product_cat');
        if (!$term || is_wp_error($term)) {
            return array(
                'success' => false,
                'message' => __('Invalid category ID', 'siloq-connector')
            );
        }
        
        // Check if API is configured
        $api_url = get_option('siloq_api_url');
        $api_key = get_option('siloq_api_key');
        
        if (empty($api_url) || empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('Siloq API is not configured', 'siloq-connector')
            );
        }
        
        // Sync via API
        $result = $this->api_client->sync_taxonomy_term($term_id, 'product_cat');
        
        return $result;
    }
    
    /**
     * Sync all published content (pages, posts, products, categories)
     * 
     * @param int $offset Starting offset for batch processing
     * @param int $limit Maximum number of items to sync (0 = all)
     * @return array Results with counts and details
     */
    public function sync_all_pages($offset = 0, $limit = 0) {
        $results = array(
            'total' => 0,
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => array(),
            'offset' => $offset,
            'has_more' => false
        );
        
        // Get all syncable post types
        $post_types = $this->get_syncable_post_types();
        
        // Query all content
        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC'
        );
        
        $posts = get_posts($args);
        $results['total'] = count($posts);
        
        // Check if there are more posts
        if ($limit > 0) {
            $total_count = 0;
            foreach ($post_types as $type) {
                $count_obj = wp_count_posts($type);
                $total_count += isset($count_obj->publish) ? $count_obj->publish : 0;
            }
            $results['has_more'] = ($offset + count($posts)) < $total_count;
            $results['total_available'] = $total_count;
        }
        
        // Sync each post
        foreach ($posts as $post) {
            $api_url = get_option('siloq_api_url');
            $api_key = get_option('siloq_api_key');
            
            if (empty($api_url) || empty($api_key)) {
                $results['skipped']++;
                $results['details'][] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'type' => $post->post_type,
                    'status' => 'skipped',
                    'message' => __('API not configured', 'siloq-connector')
                );
                continue;
            }
            
            $result = $this->sync_page($post->ID);
            
            if ($result['success']) {
                $results['synced']++;
                $status = 'success';
            } else {
                $results['failed']++;
                $status = 'error';
            }
            
            $results['details'][] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'status' => $status,
                'message' => $result['message']
            );
            
            usleep(100000); // 0.1 second delay
        }
        
        // Also sync WooCommerce product categories if WooCommerce is active
        if ($this->is_woocommerce_active() && $offset === 0) {
            $category_results = $this->sync_all_product_categories();
            $results['categories'] = $category_results;
            $results['synced'] += $category_results['synced'];
            $results['failed'] += $category_results['failed'];
            $results['total'] += $category_results['total'];
        }
        
        return $results;
    }
    
    /**
     * Sync all WooCommerce product categories
     * 
     * @return array Results
     */
    public function sync_all_product_categories() {
        $results = array(
            'total' => 0,
            'synced' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        if (!$this->is_woocommerce_active()) {
            return $results;
        }
        
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));
        
        if (is_wp_error($categories)) {
            return $results;
        }
        
        $results['total'] = count($categories);
        
        foreach ($categories as $category) {
            $result = $this->sync_product_category($category->term_id);
            
            if ($result['success']) {
                $results['synced']++;
                $status = 'success';
            } else {
                $results['failed']++;
                $status = 'error';
            }
            
            $results['details'][] = array(
                'id' => $category->term_id,
                'name' => $category->name,
                'type' => 'product_cat',
                'status' => $status,
                'message' => $result['message']
            );
            
            usleep(100000); // 0.1 second delay
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
        $job_result = $this->api_client->get_content_job_status($job_id);
        
        if (!$job_result['success']) {
            return $job_result;
        }
        
        $job_data = $job_result['data'];
        
        if ($job_data['status'] !== 'completed') {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Job is not completed yet. Current status: %s', 'siloq-connector'),
                    $job_data['status']
                )
            );
        }
        
        if (empty($job_data['content'])) {
            return array(
                'success' => false,
                'message' => __('No content available', 'siloq-connector')
            );
        }
        
        $original_post = get_post($post_id);
        $post_type = $original_post ? $original_post->post_type : 'page';
        
        $new_post_id = wp_insert_post(array(
            'post_title' => $job_data['title'] ?? get_the_title($post_id) . ' (Generated)',
            'post_content' => $job_data['content'],
            'post_status' => 'draft',
            'post_type' => $post_type,
            'post_parent' => get_post_field('post_parent', $post_id)
        ));
        
        if (is_wp_error($new_post_id)) {
            return array(
                'success' => false,
                'message' => $new_post_id->get_error_message()
            );
        }
        
        update_post_meta($new_post_id, '_siloq_generated_from', $post_id);
        update_post_meta($new_post_id, '_siloq_content_job_id', $job_id);
        update_post_meta($new_post_id, '_siloq_imported_at', current_time('mysql'));
        
        if (!empty($job_data['schema_markup'])) {
            update_post_meta($new_post_id, '_siloq_schema_markup', $job_data['schema_markup']);
        }
        
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
     * Get sync status for all content
     * 
     * @return array Array of sync statuses
     */
    public function get_all_sync_status() {
        $post_types = $this->get_syncable_post_types();
        
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
                'post_type' => $post->post_type,
                'status' => $post->post_status,
                'last_synced' => $last_synced ? $last_synced : __('Never', 'siloq-connector'),
                'sync_status' => $sync_status ? $sync_status : 'not_synced',
                'has_schema' => $has_schema,
                'siloq_page_id' => $siloq_page_id,
                'modified' => $post->post_modified
            );
        }
        
        // Add WooCommerce categories
        if ($this->is_woocommerce_active()) {
            $categories = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            ));
            
            if (!is_wp_error($categories)) {
                foreach ($categories as $category) {
                    $last_synced = get_term_meta($category->term_id, '_siloq_last_synced', true);
                    $sync_status = get_term_meta($category->term_id, '_siloq_sync_status', true);
                    $siloq_page_id = get_term_meta($category->term_id, '_siloq_page_id', true);
                    
                    $status_data[] = array(
                        'id' => 'cat_' . $category->term_id,
                        'term_id' => $category->term_id,
                        'title' => $category->name,
                        'url' => get_term_link($category),
                        'edit_url' => get_edit_term_link($category->term_id, 'product_cat'),
                        'post_type' => 'product_cat',
                        'status' => 'publish',
                        'last_synced' => $last_synced ? $last_synced : __('Never', 'siloq-connector'),
                        'sync_status' => $sync_status ? $sync_status : 'not_synced',
                        'has_schema' => false,
                        'siloq_page_id' => $siloq_page_id,
                        'modified' => null
                    );
                }
            }
        }
        
        return $status_data;
    }
    
    /**
     * Clear sync metadata for a post
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
     * Check if content needs re-sync
     */
    public function needs_resync($post_id) {
        $last_synced = get_post_meta($post_id, '_siloq_last_synced', true);
        
        if (empty($last_synced)) {
            return true;
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
     * Get content that needs re-sync
     */
    public function get_pages_needing_resync() {
        $post_types = $this->get_syncable_post_types();
        
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
     * Sync content that needs re-sync
     */
    public function sync_outdated_pages($limit = 10) {
        $post_ids = $this->get_pages_needing_resync();
        
        if (empty($post_ids)) {
            return array(
                'success' => true,
                'message' => __('All content is up to date', 'siloq-connector'),
                'synced' => 0
            );
        }
        
        $post_ids = array_slice($post_ids, 0, $limit);
        
        $results = array(
            'total' => count($post_ids),
            'synced' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        foreach ($post_ids as $post_id) {
            $result = $this->sync_page($post_id);
            
            if ($result['success']) {
                $results['synced']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][] = array(
                'id' => $post_id,
                'title' => get_the_title($post_id),
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['message']
            );
            
            usleep(100000);
        }
        
        return $results;
    }
}
