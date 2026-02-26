<?php
/**
 * Siloq Webhook Handler
 * Handles incoming webhooks from Siloq platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Webhook_Handler {
    
    /**
     * Initialize webhook handler
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    
    /**
     * Register REST routes
     */
    public static function register_routes() {
        register_rest_route('siloq/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_webhook'),
            'permission_callback' => '__return_true',
            'args' => array(
                'event' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'site_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'data' => array(
                    'required' => true,
                    'type' => 'object'
                )
            )
        ));
    }
    
    /**
     * Handle incoming webhook
     */
    public static function handle_webhook($request) {
        // Security: Validate webhook signature
        $secret = get_option('siloq_webhook_secret', '');
        $signature = $request->get_header('X-Siloq-Signature');
        if (empty($secret) || empty($signature) || !hash_equals('sha256=' . hash_hmac('sha256', $request->get_body(), $secret), $signature)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid signature'), 401);
        }
        
        $params = $request->get_params();
        
        // Validate required parameters
        if (!isset($params['event']) || !isset($params['site_id']) || !isset($params['data'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing required parameters'
            ), 400);
        }
        
        // Validate site_id
        $stored_site_id = get_option('siloq_site_id', '');
        if (empty($stored_site_id) || $params['site_id'] !== $stored_site_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid site_id'
            ), 403);
        }
        
        $event = $params['event'];
        $data = $params['data'];
        
        // Handle different events
        switch ($event) {
            case 'content.apply_content':
                return self::handle_apply_content($data);
                
            case 'meta.update':
                return self::handle_meta_update($data);
                
            case 'page.create_draft':
                return self::handle_create_draft($data);
                
            default:
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Unknown event: ' . $event
                ), 400);
        }
    }
    
    /**
     * Handle content.apply_content event
     */
    private static function handle_apply_content($data) {
        if (!isset($data['url']) || !isset($data['content'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing url or content in data'
            ), 400);
        }
        
        // Find post by URL
        $post_id = url_to_postid($data['url']);
        if (!$post_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Post not found for URL: ' . $data['url']
            ), 404);
        }
        
        // Update post content
        $update_args = array(
            'ID' => $post_id,
            'post_content' => $data['content']
        );
        
        // Update post title if provided
        if (isset($data['title'])) {
            $update_args['post_title'] = $data['title'];
        }
        
        $result = wp_update_post($update_args, true);
        
        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to update post: ' . $result->get_error_message()
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Content applied successfully',
            'post_id' => $post_id
        ));
    }
    
    /**
     * Handle meta.update event
     */
    private static function handle_meta_update($data) {
        if (!isset($data['url']) || !isset($data['meta'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing url or meta in data'
            ), 400);
        }
        
        // Find post by URL
        $post_id = url_to_postid($data['url']);
        if (!$post_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Post not found for URL: ' . $data['url']
            ), 404);
        }
        
        $meta_fields = $data['meta'];
        $updated_fields = array();
        
        // Update meta fields (AIOSEO format)
        foreach ($meta_fields as $key => $value) {
            if (is_string($value)) {
                $value = sanitize_text_field($value);
            }
            
            // Handle AIOSEO specific fields using wp_aioseo_posts table
            if ($key === 'title') {
                global $wpdb;
                $table_name = $wpdb->prefix . 'aioseo_posts';
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET title = %s WHERE post_id = %d",
                    $value, $post_id
                ));
                $updated_fields[] = 'title';
            } elseif ($key === 'description') {
                global $wpdb;
                $table_name = $wpdb->prefix . 'aioseo_posts';
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET description = %s WHERE post_id = %d",
                    $value, $post_id
                ));
                $updated_fields[] = 'description';
            } else {
                // Handle other meta fields
                update_post_meta($post_id, $key, $value);
                $updated_fields[] = $key;
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Meta updated successfully',
            'post_id' => $post_id,
            'updated_fields' => $updated_fields
        ));
    }
    
    /**
     * Handle page.create_draft event
     */
    private static function handle_create_draft($data) {
        if (!isset($data['title']) || !isset($data['content'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing title or content in data'
            ), 400);
        }
        
        // Create draft post
        $post_args = array(
            'post_title' => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['content']),
            'post_status' => 'draft',
            'post_type' => isset($data['post_type']) ? sanitize_text_field($data['post_type']) : 'page',
            'post_author' => isset($data['author_id']) ? intval($data['author_id']) : get_current_user_id()
        );
        
        // Set post parent if provided
        if (isset($data['parent_id'])) {
            $post_args['post_parent'] = intval($data['parent_id']);
        }
        
        $post_id = wp_insert_post($post_args, true);
        
        if (is_wp_error($post_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to create draft: ' . $post_id->get_error_message()
            ), 500);
        }
        
        // Update meta fields if provided
        if (isset($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                if (is_string($value)) {
                    $value = sanitize_text_field($value);
                }
                
                // Handle AIOSEO specific fields using wp_aioseo_posts table
                if ($key === 'title') {
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'aioseo_posts';
                    $wpdb->query($wpdb->prepare(
                        "UPDATE $table_name SET title = %s WHERE post_id = %d",
                        $value, $post_id
                    ));
                } elseif ($key === 'description') {
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'aioseo_posts';
                    $wpdb->query($wpdb->prepare(
                        "UPDATE $table_name SET description = %s WHERE post_id = %d",
                        $value, $post_id
                    ));
                } else {
                    update_post_meta($post_id, $key, $value);
                }
            }
        }
        
        // Return post URL
        $post_url = get_permalink($post_id);
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Draft created successfully',
            'post_id' => $post_id,
            'post_url' => $post_url
        ));
    }
}
