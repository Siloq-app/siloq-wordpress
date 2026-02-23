<?php
/**
 * Siloq Sync Engine
 * Handles synchronization between WordPress and Siloq platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Sync_Engine {
    
    /**
     * API Client
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
     */
    public function sync_page($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array('success' => false, 'message' => 'Post not found');
        }
        
        // Check if user has permission
        if (!current_user_can('edit_post', $post_id)) {
            return array('success' => false, 'message' => 'Insufficient permissions');
        }
        
        // Prepare page data
        $page_data = array(
            'wp_post_id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'url' => get_permalink($post->ID),
            'type' => $post->post_type,
            'status' => $post->post_status,
            'author' => get_the_author_meta('display_name', $post->post_author),
            'modified' => $post->post_modified,
            'site_id' => get_option('siloq_site_id', ''),
            'categories' => wp_get_post_categories($post->ID),
            'tags' => wp_get_post_tags($post->ID),
            'featured_image' => get_the_post_thumbnail_url($post->ID),
            'excerpt' => get_the_excerpt($post),
            'meta' => array(
                'seo_title' => get_post_meta($post->ID, '_yoast_wpseo_title', true) ?: get_post_meta($post->ID, '_rank_math_title', true),
                'seo_description' => get_post_meta($post->ID, '_yoast_wpseo_metadesc', true) ?: get_post_meta($post->ID, '_rank_math_description', true),
                'focus_keyword' => get_post_meta($post->ID, '_yoast_wpseo_focuskw', true) ?: get_post_meta($post->ID, '_rank_math_focus_keyword', true)
            )
        );
        
        // Send to Siloq API
        $result = $this->api_client->sync_page($post_id);
        
        if ($result['success']) {
            // Mark as synced locally
            update_post_meta($post_id, '_siloq_synced', true);
            update_post_meta($post_id, '_siloq_synced_at', current_time('mysql'));
            update_post_meta($post_id, '_siloq_sync_data', $result['data']);
            
            return array(
                'success' => true,
                'message' => 'Page synced successfully',
                'data' => $result['data']
            );
        } else {
            return array(
                'success' => false,
                'message' => $result['message'],
                'data' => isset($result['data']) ? $result['data'] : null
            );
        }
    }
    
    /**
     * Sync all pages
     */
    public function sync_all_pages($offset = 0, $batch_size = 50) {
        $args = array(
            'post_type' => array('page', 'post'),
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'orderby' => 'modified',
            'order' => 'DESC'
        );
        
        $posts = get_posts($args);
        $synced_count = 0;
        $error_count = 0;
        $results = array();
        
        foreach ($posts as $post) {
            $result = $this->sync_page($post->ID);
            $results[] = array(
                'post_id' => $post->ID,
                'title' => $post->post_title,
                'result' => $result
            );
            
            if ($result['success']) {
                $synced_count++;
            } else {
                $error_count++;
            }
            
            // Small delay to avoid overwhelming the API
            usleep(100000); // 0.1 seconds
        }
        
        return array(
            'success' => $synced_count > 0,
            'synced' => $synced_count,
            'errors' => $error_count,
            'total' => count($posts),
            'results' => $results,
            'has_more' => count($posts) === $batch_size
        );
    }
    
    /**
     * Get sync status
     */
    public function get_sync_status() {
        $args = array(
            'post_type' => array('page', 'post'),
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_siloq_synced',
                    'value' => '1',
                    'compare' => '='
                ),
                array(
                    'key' => '_siloq_synced',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        $posts = get_posts($args);
        $total_pages = count($posts);
        $synced_pages = 0;
        $outdated_pages = 0;
        $last_sync = null;
        
        foreach ($posts as $post) {
            $is_synced = get_post_meta($post->ID, '_siloq_synced', true);
            $synced_at = get_post_meta($post->ID, '_siloq_synced_at', true);
            
            if ($is_synced) {
                $synced_pages++;
                
                // Check if page is outdated (modified after last sync)
                if ($synced_at && strtotime($post->post_modified) > strtotime($synced_at)) {
                    $outdated_pages++;
                }
                
                // Track last sync time
                if ($synced_at && (!$last_sync || strtotime($synced_at) > strtotime($last_sync))) {
                    $last_sync = $synced_at;
                }
            }
        }
        
        return array(
            'total_pages' => $total_pages,
            'synced_pages' => $synced_pages,
            'unsynced_pages' => $total_pages - $synced_pages,
            'outdated_pages' => $outdated_pages,
            'sync_percentage' => $total_pages > 0 ? round(($synced_pages / $total_pages) * 100, 2) : 0,
            'last_sync' => $last_sync,
            'auto_sync_enabled' => get_option('siloq_auto_sync', 'no') === 'yes'
        );
    }
    
    /**
     * Sync outdated pages
     */
    public function sync_outdated_pages() {
        $args = array(
            'post_type' => array('page', 'post'),
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_siloq_synced',
                    'value' => '1',
                    'compare' => '='
                ),
                array(
                    'key' => '_siloq_synced_at',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $posts = get_posts($args);
        $outdated_pages = array();
        
        foreach ($posts as $post) {
            $synced_at = get_post_meta($post->ID, '_siloq_synced_at', true);
            
            if ($synced_at && strtotime($post->post_modified) > strtotime($synced_at)) {
                $outdated_pages[] = $post;
            }
        }
        
        $synced_count = 0;
        $error_count = 0;
        
        foreach ($outdated_pages as $post) {
            $result = $this->sync_page($post->ID);
            
            if ($result['success']) {
                $synced_count++;
            } else {
                $error_count++;
            }
            
            // Small delay to avoid overwhelming the API
            usleep(100000); // 0.1 seconds
        }
        
        return array(
            'success' => $synced_count > 0,
            'synced' => $synced_count,
            'errors' => $error_count,
            'total' => count($outdated_pages),
            'message' => sprintf(
                'Synced %d outdated pages, %d errors',
                $synced_count,
                $error_count
            )
        );
    }
    
    /**
     * Get sync history
     */
    public function get_sync_history($limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'postmeta';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value as sync_data, meta_key
             FROM $table_name 
             WHERE meta_key IN ('_siloq_synced_at', '_siloq_sync_data')
             ORDER BY meta_value DESC
             LIMIT %d",
            $limit * 2 // Get more to account for both meta keys
        ));
        
        $history = array();
        
        foreach ($results as $result) {
            $post_id = $result->post_id;
            
            if (!isset($history[$post_id])) {
                $post = get_post($post_id);
                if ($post) {
                    $history[$post_id] = array(
                        'post_id' => $post_id,
                        'title' => $post->post_title,
                        'url' => get_permalink($post_id),
                        'synced_at' => null,
                        'sync_data' => null
                    );
                }
            }
            
            if ($result->meta_key === '_siloq_synced_at') {
                $history[$post_id]['synced_at'] = $result->sync_data;
            } elseif ($result->meta_key === '_siloq_sync_data') {
                $history[$post_id]['sync_data'] = maybe_unserialize($result->sync_data);
            }
        }
        
        // Sort by synced_at date
        usort($history, function($a, $b) {
            $time_a = $a['synced_at'] ? strtotime($a['synced_at']) : 0;
            $time_b = $b['synced_at'] ? strtotime($b['synced_at']) : 0;
            return $time_b - $time_a;
        });
        
        return array_slice($history, 0, $limit);
    }
}
