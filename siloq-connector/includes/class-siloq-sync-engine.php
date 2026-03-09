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

        // Auto-publish Siloq-created hub/service-area pages that are still drafts
        if ( $post->post_status === 'draft' ) {
            $page_role = get_post_meta( $post_id, '_siloq_page_role', true );
            if (
                in_array( $page_role, array( 'hub', 'apex_hub', 'service_areas' ) ) ||
                strpos( $post->post_name, 'service-area' ) !== false ||
                strpos( strtolower( $post->post_title ), 'service area' ) !== false
            ) {
                wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
                $post = get_post( $post_id ); // refresh
            }
        }

        // Prepare page data
        $page_data = array(
            'wp_post_id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'url' => get_permalink($post->ID),
            'post_type' => $post->post_type,
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

            // Service Areas hub auto-classification (runs on every sync)
            $this->maybe_classify_service_areas_hub( $post );

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
        // Extend PHP execution time so large sites (80-500 pages) don't hit the
        // shared-hosting 30s limit mid-batch. 300s = 5 min, well above any real need.
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 300 );
        }

        // BUG 4 FIX: Fetch ALL posts with numberposts=-1 to sync every page
        // Include drafts so Siloq-created hub/service-area pages get synced even if not manually published
        $all_posts = get_posts( array(
            'post_type'          => function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array('page', 'post'),
            'post_type__not_in'  => defined('SILOQ_EXCLUDED_POST_TYPES') ? SILOQ_EXCLUDED_POST_TYPES : [],
            'post_status'        => array( 'publish', 'draft' ),
            'numberposts'        => -1,
            'fields'             => 'ids',
        ) );

        // Auto-publish any draft hub or service-areas pages before syncing
        // These were created by Siloq and should be live (user may not have manually published them)
        foreach ( $all_posts as $pid ) {
            $p = get_post( $pid );
            if ( ! $p || $p->post_status !== 'draft' ) continue;
            $slug      = $p->post_name;
            $page_role = get_post_meta( $pid, '_siloq_page_role', true );
            // Publish if it's a service-areas/hub page created by Siloq
            if (
                in_array( $page_role, array( 'hub', 'apex_hub', 'service_areas' ) ) ||
                strpos( $slug, 'service-area' ) !== false ||
                strpos( strtolower( $p->post_title ), 'service area' ) !== false
            ) {
                wp_update_post( array( 'ID' => $pid, 'post_status' => 'publish' ) );
            }
        }

        $synced_count = 0;
        $error_count  = 0;
        $results      = array();

        foreach ($all_posts as $post_id) {
            $post   = get_post($post_id);
            $result = $this->sync_page($post_id);
            $results[] = array(
                'post_id' => $post_id,
                'title'   => $post ? $post->post_title : '',
                'result'  => $result,
            );

            if ($result['success']) {
                $synced_count++;
            } else {
                $error_count++;
            }

            usleep(100000); // 0.1 seconds
        }

        // BUG 4 FIX: Schedule auto-analysis for unanalyzed pages
        $unanalyzed = get_posts( array(
            'post_type'          => function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array('page', 'post'),
            'post_type__not_in'  => defined('SILOQ_EXCLUDED_POST_TYPES') ? SILOQ_EXCLUDED_POST_TYPES : [],
            'post_status'        => 'publish',
            'numberposts'        => -1,
            'fields'             => 'ids',
            'meta_query'         => array(
                'relation' => 'AND',
                array( 'key' => '_siloq_synced', 'compare' => 'EXISTS' ),
                array( 'key' => '_siloq_analysis_score', 'compare' => 'NOT EXISTS' ),
            ),
        ) );
        if ( ! empty( $unanalyzed ) ) {
            update_option( 'siloq_analysis_queue_count', count( $unanalyzed ) );
            if ( ! wp_next_scheduled( 'siloq_analyze_batch' ) ) {
                wp_schedule_single_event( time() + 30, 'siloq_analyze_batch' );
            }
        }

        return array(
            'success'  => $synced_count > 0,
            'synced'   => $synced_count,
            'errors'   => $error_count,
            'total'    => count($all_posts),
            'results'  => $results,
            'has_more' => false, // all pages synced in one pass
        );
    }
    
    /**
     * Get sync status
     */
    public function get_sync_status() {
        $args = array(
            'post_type'          => function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array('page', 'post'),
            'post_type__not_in'  => defined('SILOQ_EXCLUDED_POST_TYPES') ? SILOQ_EXCLUDED_POST_TYPES : [],
            'post_status'        => array('publish', 'draft'),
            'posts_per_page'     => 500,
            'no_found_rows'      => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
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
        $pages = array();
        
        foreach ($posts as $post) {
            $is_synced = get_post_meta($post->ID, '_siloq_synced', true);
            $synced_at = get_post_meta($post->ID, '_siloq_synced_at', true);
            
            // Determine page status
            if ($is_synced) {
                $synced_pages++;
                
                // Check if page is outdated (modified after last sync)
                if ($synced_at && strtotime($post->post_modified) > strtotime($synced_at)) {
                    $outdated_pages++;
                    $status = 'outdated';
                } else {
                    $status = 'synced';
                }
                
                // Track last sync time
                if ($synced_at && (!$last_sync || strtotime($synced_at) > strtotime($last_sync))) {
                    $last_sync = $synced_at;
                }
            } else {
                $status = 'pending';
            }
            
            $pages[] = array(
                'id'       => $post->ID,
                'title'    => $post->post_title ?: '(Untitled)',
                'modified' => get_the_modified_date('M j, Y g:i a', $post->ID),
                'status'   => $status,
                'url'      => get_permalink($post->ID),
            );
        }
        
        return array(
            'total_pages' => $total_pages,
            'synced_pages' => $synced_pages,
            'unsynced_pages' => $total_pages - $synced_pages,
            'outdated_pages' => $outdated_pages,
            'sync_percentage' => $total_pages > 0 ? round(($synced_pages / $total_pages) * 100, 2) : 0,
            'last_sync' => $last_sync,
            'auto_sync_enabled' => get_option('siloq_auto_sync', 'no') === 'yes',
            'pages' => $pages,
        );
    }
    
    /**
     * Sync outdated pages
     */
    public function sync_outdated_pages() {
        $args = array(
            'post_type'          => function_exists('get_siloq_crawlable_post_types') ? get_siloq_crawlable_post_types() : array('page', 'post'),
            'post_type__not_in'  => defined('SILOQ_EXCLUDED_POST_TYPES') ? SILOQ_EXCLUDED_POST_TYPES : [],
            'post_status'        => array('publish', 'draft'),
            'posts_per_page'     => 500,
            'no_found_rows'      => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
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

    /**
     * Detect if a synced page is a Service Areas hub and auto-classify spokes.
     *
     * Runs on every sync (upsert pattern). A page is a Service Areas hub if its
     * title contains "service area" (case-insensitive) OR its slug contains
     * "service-area" or "service-areas".
     *
     * @param WP_Post $post The post that was just synced.
     */
    private function maybe_classify_service_areas_hub( $post ) {
        $title_lower = strtolower( $post->post_title );
        $slug        = $post->post_name;

        $is_hub = ( strpos( $title_lower, 'service area' ) !== false )
               || ( strpos( $slug, 'service-area' ) !== false );

        if ( ! $is_hub ) {
            return;
        }

        if ( function_exists( 'siloq_debug_log' ) ) {
            siloq_debug_log( "Service Areas hub detected: \"{$post->post_title}\" (ID {$post->ID})" );
        }

        // Set this page's role to Hub
        update_post_meta( $post->ID, '_siloq_page_role', 'hub' );

        // Gather service areas list from business profile
        $service_areas_raw = get_option( 'siloq_service_areas', '' );
        $service_areas     = array();
        if ( is_array( $service_areas_raw ) ) {
            $service_areas = $service_areas_raw;
        } elseif ( is_string( $service_areas_raw ) && ! empty( $service_areas_raw ) ) {
            $decoded = json_decode( $service_areas_raw, true );
            $service_areas = is_array( $decoded ) ? $decoded : array_filter( array_map( 'trim', explode( ',', $service_areas_raw ) ) );
        }

        // Extract just city names (strip state portion like ", MO")
        $city_names = array();
        foreach ( $service_areas as $area ) {
            $parts = array_map( 'trim', explode( ',', $area ) );
            if ( ! empty( $parts[0] ) ) {
                $city_names[] = strtolower( $parts[0] );
            }
        }

        // Get all synced published pages (excluding self)
        $all_pages = get_posts( array(
            'post_type'          => function_exists( 'get_siloq_crawlable_post_types' ) ? get_siloq_crawlable_post_types() : array( 'page', 'post' ),
            'post_type__not_in'  => defined( 'SILOQ_EXCLUDED_POST_TYPES' ) ? SILOQ_EXCLUDED_POST_TYPES : [],
            'post_status'        => 'publish',
            'numberposts'        => -1,
            'exclude'            => array( $post->ID ),
        ) );

        $has_service_areas_list = ! empty( $city_names );

        foreach ( $all_pages as $candidate ) {
            $candidate_slug  = $candidate->post_name;
            $candidate_title = strtolower( $candidate->post_title );
            $is_spoke        = false;
            $confidence      = 'high';

            // Check 1: Previously manually classified as Spoke for this hub
            $existing_role   = get_post_meta( $candidate->ID, '_siloq_page_role', true );
            $existing_hub_id = (int) get_post_meta( $candidate->ID, '_siloq_service_area_hub_id', true );
            if ( $existing_role === 'spoke' && $existing_hub_id === $post->ID ) {
                $is_spoke = true;
            }

            // Check 2: Title matches a city from service areas list
            if ( ! $is_spoke && $has_service_areas_list ) {
                foreach ( $city_names as $city ) {
                    if ( strpos( $candidate_title, $city ) !== false ) {
                        $is_spoke = true;
                        break;
                    }
                }
            }

            // Check 3: Slug matches city-state pattern (e.g., "kansas-city-mo")
            if ( ! $is_spoke && preg_match( '/^[a-z]+-(?:[a-z]+-)*[a-z]{2}$/', $candidate_slug ) ) {
                if ( $has_service_areas_list ) {
                    // Verify slug city portion matches a known service area
                    $slug_city = preg_replace( '/-[a-z]{2}$/', '', $candidate_slug );
                    $slug_city_name = str_replace( '-', ' ', $slug_city );
                    if ( in_array( $slug_city_name, $city_names, true ) ) {
                        $is_spoke = true;
                    }
                } else {
                    // No service areas list — flag as likely location page
                    $is_spoke   = true;
                    $confidence = 'low';
                }
            }

            if ( ! $is_spoke ) {
                continue;
            }

            // Check for cannibalization: already assigned to a DIFFERENT hub
            if ( $existing_hub_id && $existing_hub_id !== $post->ID && $existing_role === 'spoke' ) {
                update_post_meta( $candidate->ID, '_siloq_cannibalization_warning',
                    sprintf( 'Page "%s" is spoke of hub #%d but also matches hub #%d ("%s")',
                        $candidate->post_title, $existing_hub_id, $post->ID, $post->post_title
                    )
                );
                if ( function_exists( 'siloq_debug_log' ) ) {
                    siloq_debug_log( "Cannibalization: \"{$candidate->post_title}\" already spoke of hub #{$existing_hub_id}, skipping hub #{$post->ID}" );
                }
                continue;
            }

            // Assign as spoke
            update_post_meta( $candidate->ID, '_siloq_page_role', 'spoke' );
            update_post_meta( $candidate->ID, '_siloq_service_area_hub_id', $post->ID );

            if ( $confidence === 'low' ) {
                update_post_meta( $candidate->ID, '_siloq_location_confidence', 'low' );
            }

            // Add Priority Action: link back to Service Areas hub
            $actions = get_post_meta( $candidate->ID, '_siloq_priority_actions', true );
            if ( ! is_array( $actions ) ) {
                $actions = array();
            }
            $action_text = sprintf( 'Add internal link from "%s" back to Service Areas', $candidate->post_title );
            // Avoid duplicates
            $already_has = false;
            foreach ( $actions as $a ) {
                if ( isset( $a['text'] ) && $a['text'] === $action_text ) {
                    $already_has = true;
                    break;
                }
            }
            if ( ! $already_has ) {
                $actions[] = array(
                    'text'     => $action_text,
                    'hub_id'   => $post->ID,
                    'hub_title'=> $post->post_title,
                    'type'     => 'internal_link',
                    'created'  => current_time( 'mysql' ),
                );
                update_post_meta( $candidate->ID, '_siloq_priority_actions', $actions );
            }

            if ( function_exists( 'siloq_debug_log' ) ) {
                siloq_debug_log( "Spoke assigned: \"{$candidate->post_title}\" → hub \"{$post->post_title}\" (confidence: {$confidence})" );
            }
        }
    }
}
