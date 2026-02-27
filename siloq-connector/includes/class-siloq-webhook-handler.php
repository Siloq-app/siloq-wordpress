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
        // No strict arg validation here — we parse manually in handle_webhook
        // to support both 'event' and 'event_type' field names from the API.
        register_rest_route('siloq/v1', '/webhook', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_webhook'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Handle incoming webhook
     */
    public static function handle_webhook($request) {
        // Security: only enforce HMAC if a webhook secret has been configured.
        // The Siloq API omits the signature header until a secret is set in
        // both the API and the plugin — allow unauthenticated when no secret is set.
        $secret    = get_option('siloq_webhook_secret', '');
        $signature = $request->get_header('X-Siloq-Signature');
        if (!empty($secret)) {
            if (empty($signature) || !hash_equals('sha256=' . hash_hmac('sha256', $request->get_body(), $secret), $signature)) {
                return new WP_REST_Response(array('success' => false, 'message' => 'Invalid signature'), 401);
            }
        }
        
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_params();
        }
        
        // Accept both 'event_type' (API) and 'event' (legacy) field names
        $event = isset($params['event_type']) ? $params['event_type']
               : (isset($params['event'])     ? $params['event'] : null);
        
        $site_id = isset($params['site_id']) ? $params['site_id'] : null;
        $data    = isset($params['data'])    ? $params['data']    : null;
        
        // Validate required parameters
        if (!$event || !$data) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing required parameters: event_type and data are required',
            ), 400);
        }
        
        // Store site_id from API — no validation until HMAC signing is active.
        // Real auth comes from the webhook secret (HMAC); site_id alone is not
        // a security mechanism and should never block legitimate calls.
        if (!empty($site_id)) {
            update_option('siloq_site_id', sanitize_text_field((string) $site_id));
        }
        
        // Handle different events
        // NOTE: 'page.update_meta' is what the Siloq API sends (see page_analysis_views.py).
        // 'meta.update' kept as legacy alias.
        switch ($event) {
            case 'content.apply_content':
                return self::handle_apply_content($data);

            case 'page.update_meta':
            case 'meta.update':
                return self::handle_meta_update($data);

            case 'schema.updated':
                return self::handle_schema_update($data);
                
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
        // API sends 'after' (the new content); legacy callers may send 'content'
        $content = isset($data['after']) ? $data['after']
                 : (isset($data['content']) ? $data['content'] : null);

        if (!isset($data['url']) || $content === null) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing url or content/after in data',
            ), 400);
        }

        $post_id = url_to_postid($data['url']);
        if (!$post_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Post not found for URL: ' . $data['url'],
            ), 404);
        }

        $post_content = get_post_field('post_content', $post_id);

        // ── Page builder safety check ─────────────────────────────────────────
        // Page builders (Elementor, Divi, WPBakery, Beaver Builder, etc.) store
        // their layouts in post meta or shortcodes. Overwriting post_content
        // directly will wipe the visible page. Always return manual_action so
        // the dashboard can guide the user to paste in their builder's editor.
        $elementor_raw = get_post_meta($post_id, '_elementor_data', true);
        $has_elementor = !empty($elementor_raw) && $elementor_raw !== '[]';
        $has_fl        = !empty(get_post_meta($post_id, '_fl_builder_data', true));
        $has_shortcodes = (bool) preg_match('/\[vc_|\[et_pb_|\[fl_/i', $post_content);

        if ($has_elementor || $has_fl || $has_shortcodes) {
            $builder = $has_elementor ? 'Elementor' : ($has_fl ? 'Beaver Builder' : 'WPBakery/Divi');
            return new WP_REST_Response(array(
                'success'       => false,
                'manual_action' => true,
                'builder'       => $builder,
                'content'       => $content,
                'message'       => $builder . ' page detected. Open your page editor and paste the suggested content into the appropriate section.',
            ), 200);
        }

        // ── Standard WordPress page — safe to replace post_content directly ───
        // Backup existing content before replacing
        update_post_meta($post_id, '_siloq_backup_content', $post_content);
        update_post_meta($post_id, '_siloq_backup_at', current_time('mysql'));

        $update_args = array(
            'ID'           => $post_id,
            'post_content' => wp_kses_post($content),
            'post_content' => $new_content,
        );
        if (isset($data['title'])) {
            $update_args['post_title'] = sanitize_text_field($data['title']);
        }

        $result = wp_update_post($update_args, true);
        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to update post: ' . $result->get_error_message(),
            ), 500);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Content applied successfully',
            'post_id' => $post_id,
            'method'  => 'replaced',
        ));
    }
    
    /**
     * Handle meta.update event
     */
    private static function handle_meta_update($data) {
        if (!isset($data['url'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing url in data'
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

        // Build a normalised flat fields map.
        // API sends: { url, title, meta_description, h1 }
        // Legacy sends: { url, meta: { title, description } }
        $fields = array();

        if (!empty($data['meta']) && is_array($data['meta'])) {
            // Legacy nested format — map 'description' → 'meta_description'
            foreach ($data['meta'] as $k => $v) {
                $fields[$k === 'description' ? 'meta_description' : $k] = $v;
            }
        }
        // Flat format — pull recognised keys directly
        foreach (['title', 'meta_description', 'h1'] as $key) {
            if (isset($data[$key])) {
                $fields[$key] = $data[$key];
            }
        }

        if (empty($fields)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'No recognised meta fields provided (title, meta_description, h1)'
            ), 400);
        }
        
        $updated_fields = array();
        global $wpdb;
        $aioseo_table = $wpdb->prefix . 'aioseo_posts';
        $aioseo_exists = $wpdb->get_var("SHOW TABLES LIKE '$aioseo_table'") === $aioseo_table;

        foreach ($fields as $key => $value) {
            $value = sanitize_text_field($value);

            if ($key === 'title') {
                if ($aioseo_exists) {
                    // AIOSEO — upsert (INSERT … ON DUPLICATE KEY UPDATE)
                    $wpdb->query($wpdb->prepare(
                        "INSERT INTO $aioseo_table (post_id, title) VALUES (%d, %s)
                         ON DUPLICATE KEY UPDATE title = %s",
                        $post_id, $value, $value
                    ));
                }
                // Also update the WP post title as fallback
                wp_update_post(array('ID' => $post_id, 'post_title' => $value));
                $updated_fields[] = 'title';

            } elseif ($key === 'meta_description') {
                if ($aioseo_exists) {
                    $wpdb->query($wpdb->prepare(
                        "INSERT INTO $aioseo_table (post_id, description) VALUES (%d, %s)
                         ON DUPLICATE KEY UPDATE description = %s",
                        $post_id, $value, $value
                    ));
                } else {
                    // Fallback: store in standard post meta
                    update_post_meta($post_id, '_yoast_wpseo_metadesc', $value);
                }
                $updated_fields[] = 'meta_description';

            } elseif ($key === 'h1') {
                // H1 lives in the post title in most WP setups
                wp_update_post(array('ID' => $post_id, 'post_title' => $value));
                $updated_fields[] = 'h1';

            } else {
                update_post_meta($post_id, sanitize_key($key), $value);
                $updated_fields[] = $key;
            }
        }
        
        return new WP_REST_Response(array(
            'success'        => true,
            'message'        => 'Meta updated successfully',
            'post_id'        => $post_id,
            'updated_fields' => $updated_fields,
        ));
    }

    /**
     * Handle schema.updated event
     * Stores schema JSON-LD in post meta for output in wp_head.
     */
    private static function handle_schema_update($data) {
        if (!isset($data['url']) || !isset($data['schema_markup'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing url or schema_markup in data'
            ), 400);
        }

        $post_id = url_to_postid($data['url']);
        if (!$post_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Post not found for URL: ' . $data['url']
            ), 404);
        }

        $schema = is_array($data['schema_markup'])
            ? json_encode($data['schema_markup'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $data['schema_markup'];

        update_post_meta($post_id, '_siloq_schema_markup', $schema);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Schema updated successfully',
            'post_id' => $post_id,
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
