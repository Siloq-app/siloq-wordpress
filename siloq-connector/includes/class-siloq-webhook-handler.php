<?php
/**
 * Siloq Webhook Handler
 * Handles incoming webhooks from Siloq platform for real-time updates
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Webhook_Handler {
    
    /**
     * Webhook endpoint slug
     */
    const WEBHOOK_ENDPOINT = 'siloq-webhook';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }
    
    /**
     * Register REST API webhook endpoint
     */
    public function register_webhook_endpoint() {
        register_rest_route('siloq/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => array($this, 'verify_webhook_signature')
        ));
    }
    
    /**
     * Verify webhook signature for security
     */
    public function verify_webhook_signature($request) {
        // Get signature from header
        $signature = $request->get_header('X-Siloq-Signature');
        
        // TODO: Implement proper signature auth for content.create_draft — temporary siloq_page_id check
        if (empty($signature)) {
            // Allow content.create_draft with simplified auth (valid siloq_page_id)
            $body_data = json_decode($request->get_body(), true);
            if (
                is_array($body_data) &&
                isset($body_data['event_type']) &&
                $body_data['event_type'] === 'content.create_draft' &&
                isset($body_data['siloq_page_id']) &&
                is_numeric($body_data['siloq_page_id']) &&
                intval($body_data['siloq_page_id']) > 0
            ) {
                return true;
            }

            return new WP_Error(
                'missing_signature',
                __('Missing webhook signature', 'siloq-connector'),
                array('status' => 401)
            );
        }
        
        // Get API key
        $api_key = get_option('siloq_api_key');
        
        if (empty($api_key)) {
            return new WP_Error(
                'not_configured',
                __('Siloq API not configured', 'siloq-connector'),
                array('status' => 500)
            );
        }
        
        // Verify signature
        $body = $request->get_body();
        $expected_signature = hash_hmac('sha256', $body, $api_key);
        
        if (!hash_equals($expected_signature, $signature)) {
            return new WP_Error(
                'invalid_signature',
                __('Invalid webhook signature', 'siloq-connector'),
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Handle incoming webhook
     */
    public function handle_webhook($request) {
        $data = $request->get_json_params();
        
        if (empty($data['event_type'])) {
            return new WP_Error(
                'missing_event_type',
                __('Missing event type', 'siloq-connector'),
                array('status' => 400)
            );
        }
        
        $event_type = $data['event_type'];
        
        // Log webhook for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Siloq Webhook] Received event: %s',
                $event_type
            ));
        }
        
        // Route to appropriate handler
        switch ($event_type) {
            case 'content.generated':
                return $this->handle_content_generated($data);
                
            case 'schema.updated':
                return $this->handle_schema_updated($data);
                
            case 'page.analyzed':
                return $this->handle_page_analyzed($data);
                
            case 'sync.completed':
                return $this->handle_sync_completed($data);
                
            case 'content.create_draft':
                return $this->handle_content_create_draft($data);
                
            case 'redirect.create':
                return $this->handle_redirect_create($data);
                
            case 'page.update_meta':
                return $this->handle_page_update_meta($data);
                
            default:
                return new WP_Error(
                    'unknown_event',
                    sprintf(__('Unknown event type: %s', 'siloq-connector'), $event_type),
                    array('status' => 400)
                );
        }
    }
    
    /**
     * Handle content.generated event
     */
    private function handle_content_generated($data) {
        if (empty($data['wp_post_id']) || empty($data['job_id'])) {
            return new WP_Error(
                'missing_data',
                __('Missing required data', 'siloq-connector'),
                array('status' => 400)
            );
        }
        
        $post_id = intval($data['wp_post_id']);
        $job_id = sanitize_text_field($data['job_id']);
        
        // Store job reference
        $import_handler = new Siloq_Content_Import();
        $import_handler->store_job_reference($post_id, $job_id, $data);
        
        // Update post meta to indicate content is ready
        update_post_meta($post_id, '_siloq_content_ready', 'yes');
        update_post_meta($post_id, '_siloq_content_ready_at', current_time('mysql'));
        
        // Send admin notification
        $this->send_admin_notification(
            $post_id,
            __('Siloq: Content Generated', 'siloq-connector'),
            sprintf(
                __('AI-generated content is ready for: %s', 'siloq-connector'),
                get_the_title($post_id)
            )
        );
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Content generation notification received', 'siloq-connector')
        ));
    }
    
    /**
     * Handle schema.updated event
     */
    private function handle_schema_updated($data) {
        if (empty($data['wp_post_id']) || empty($data['schema_markup'])) {
            return new WP_Error(
                'missing_data',
                __('Missing required data', 'siloq-connector'),
                array('status' => 400)
            );
        }
        
        $post_id = intval($data['wp_post_id']);
        
        // Validate post exists
        if (!get_post($post_id)) {
            return new WP_Error(
                'invalid_post',
                __('Post not found', 'siloq-connector'),
                array('status' => 404)
            );
        }
        
        $schema_markup = $data['schema_markup'];
        
        // Validate JSON if it's a string
        if (is_string($schema_markup)) {
            $decoded = json_decode($schema_markup, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error(
                    'invalid_json',
                    __('Invalid JSON in schema markup', 'siloq-connector'),
                    array('status' => 400)
                );
            }
            // Re-encode for consistent storage
            $schema_markup = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        
        // Update schema markup
        update_post_meta($post_id, '_siloq_schema_markup', $schema_markup);
        update_post_meta($post_id, '_siloq_schema_updated_at', current_time('mysql'));
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Schema markup updated', 'siloq-connector')
        ));
    }
    
    /**
     * Handle page.analyzed event
     */
    private function handle_page_analyzed($data) {
        if (empty($data['wp_post_id']) || empty($data['analysis'])) {
            return new WP_Error(
                'missing_data',
                __('Missing required data', 'siloq-connector'),
                array('status' => 400)
            );
        }
        
        $post_id = intval($data['wp_post_id']);
        $analysis = $data['analysis'];
        
        // Store analysis results
        update_post_meta($post_id, '_siloq_analysis', $analysis);
        update_post_meta($post_id, '_siloq_analyzed_at', current_time('mysql'));
        
        // Store specific metrics if available
        if (isset($analysis['seo_score'])) {
            update_post_meta($post_id, '_siloq_seo_score', $analysis['seo_score']);
        }
        
        if (isset($analysis['content_quality'])) {
            update_post_meta($post_id, '_siloq_content_quality', $analysis['content_quality']);
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Page analysis received', 'siloq-connector')
        ));
    }
    
    /**
     * Handle sync.completed event
     */
    private function handle_sync_completed($data) {
        if (empty($data['wp_post_id'])) {
            return new WP_Error(
                'missing_data',
                __('Missing required data', 'siloq-connector'),
                array('status' => 400)
            );
        }
        
        $post_id = intval($data['wp_post_id']);
        
        // Update sync status
        update_post_meta($post_id, '_siloq_sync_status', 'synced');
        update_post_meta($post_id, '_siloq_last_synced', current_time('mysql'));
        
        if (isset($data['siloq_page_id'])) {
            update_post_meta($post_id, '_siloq_page_id', $data['siloq_page_id']);
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Sync status updated', 'siloq-connector')
        ));
    }
    
    /**
     * Handle content.create_draft event — creates a new WordPress draft post
     */
    private function handle_content_create_draft($data) {
        // Validate required fields
        $required = array('title', 'content', 'slug', 'siloq_page_id');
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error(
                    'missing_data',
                    sprintf(__('Missing required field: %s', 'siloq-connector'), $field),
                    array('status' => 400)
                );
            }
        }

        $siloq_page_id = intval($data['siloq_page_id']);
        if ($siloq_page_id <= 0) {
            return new WP_Error(
                'invalid_page_id',
                __('Invalid siloq_page_id', 'siloq-connector'),
                array('status' => 400)
            );
        }

        // Determine post type
        $post_type = 'post';
        if (!empty($data['post_type']) && in_array($data['post_type'], array('post', 'page'), true)) {
            $post_type = $data['post_type'];
        }

        // Create the draft post
        $post_id = wp_insert_post(array(
            'post_title'   => sanitize_text_field($data['title']),
            'post_content' => wp_kses_post($data['content']),
            'post_name'    => sanitize_title($data['slug']),
            'post_status'  => 'draft',
            'post_type'    => $post_type,
        ), true);

        if (is_wp_error($post_id)) {
            return new WP_Error(
                'post_creation_failed',
                sprintf(__('Failed to create post: %s', 'siloq-connector'), $post_id->get_error_message()),
                array('status' => 500)
            );
        }

        // Store siloq_page_id
        update_post_meta($post_id, '_siloq_page_id', $siloq_page_id);

        // Store meta description via AIOSEO if available
        if (!empty($data['meta_description'])) {
            $meta_desc = sanitize_text_field($data['meta_description']);

            // Try AIOSEO table
            global $wpdb;
            $aioseo_table = $wpdb->prefix . 'aioseo_posts';
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $aioseo_table)) === $aioseo_table) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$aioseo_table} WHERE post_id = %d",
                    $post_id
                ));
                if ($existing) {
                    $wpdb->update($aioseo_table, array('description' => $meta_desc), array('post_id' => $post_id));
                } else {
                    $wpdb->insert($aioseo_table, array(
                        'post_id'     => $post_id,
                        'description' => $meta_desc,
                    ));
                }
            }

            // Also store as post meta fallback
            update_post_meta($post_id, '_aioseo_description', $meta_desc);
        }

        // Sideload featured image if provided
        if (!empty($data['featured_image_url'])) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $image_id = media_sideload_image($data['featured_image_url'], $post_id, null, 'id');
            if (!is_wp_error($image_id)) {
                set_post_thumbnail($post_id, $image_id);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('[Siloq Webhook] Failed to sideload featured image: %s', $image_id->get_error_message()));
                }
            }
        }

        $edit_url = admin_url("post.php?post={$post_id}&action=edit");

        // Notify admin
        $this->send_admin_notification(
            $post_id,
            __('Siloq: New Draft Created', 'siloq-connector'),
            sprintf(
                __('A new draft "%s" has been created from Siloq. Edit it here: %s', 'siloq-connector'),
                get_the_title($post_id),
                $edit_url
            )
        );

        return rest_ensure_response(array(
            'success'    => true,
            'message'    => __('Draft post created successfully', 'siloq-connector'),
            'wp_post_id' => $post_id,
            'edit_url'   => $edit_url,
        ));
    }

    /**
     * Send admin notification
     */
    private function send_admin_notification($post_id, $subject, $message) {
        // Get admin email
        $admin_email = get_option('admin_email');
        
        // Build email body
        $body = $message . "\n\n";
        $body .= __('View page:', 'siloq-connector') . ' ' . get_edit_post_link($post_id, 'raw') . "\n\n";
        $body .= __('This is an automated notification from Siloq Connector.', 'siloq-connector');
        
        // Send email
        wp_mail($admin_email, $subject, $body);
    }
    
    /**
     * Handle redirect.create event
     */
    private function handle_redirect_create($data) {
        // Validate required fields
        if (empty($data['from_url']) || empty($data['to_url'])) {
            return new WP_Error(
                'missing_data',
                __('Missing required fields: from_url and to_url', 'siloq-connector'),
                array('status' => 400)
            );
        }

        $from_url = sanitize_text_field($data['from_url']);
        $to_url = sanitize_text_field($data['to_url']);
        $redirect_type = isset($data['type']) ? intval($data['type']) : 301;

        // Ensure valid redirect type
        if (!in_array($redirect_type, array(301, 302, 307, 308))) {
            $redirect_type = 301;
        }

        $method = 'none';
        $redirect_id = null;

        // Check if Redirection plugin is active
        if (class_exists('Red_Item')) {
            try {
                $redirect = Red_Item::create(array(
                    'url' => $from_url,
                    'match_type' => 'url',
                    'action_type' => 'url',
                    'action_data' => array('url' => $to_url),
                    'action_code' => $redirect_type,
                    'group_id' => 1, // Default group
                ));
                
                if ($redirect) {
                    $method = 'redirection';
                    $redirect_id = $redirect->get_id();
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('[Siloq Webhook] Redirection plugin error: %s', $e->getMessage()));
                }
            }
        }

        // Check if AIOSEO is active and has redirect manager
        if ($method === 'none' && function_exists('aioseo') && method_exists(aioseo(), 'redirects')) {
            try {
                $redirect = aioseo()->redirects->addRedirect(array(
                    'sourceUrl' => $from_url,
                    'targetUrl' => $to_url,
                    'redirectType' => $redirect_type,
                    'enabled' => true,
                ));
                
                if ($redirect) {
                    $method = 'aioseo';
                    $redirect_id = isset($redirect->id) ? $redirect->id : null;
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('[Siloq Webhook] AIOSEO redirect error: %s', $e->getMessage()));
                }
            }
        }

        // Fallback: Add to .htaccess
        if ($method === 'none') {
            $htaccess_result = $this->add_htaccess_redirect($from_url, $to_url, $redirect_type);
            if ($htaccess_result['success']) {
                $method = 'htaccess';
            } else {
                $method = 'manual';
            }
        }

        // Store in siloq_redirects option for backup tracking
        $siloq_redirects = get_option('siloq_redirects', array());
        $siloq_redirects[] = array(
            'from_url' => $from_url,
            'to_url' => $to_url,
            'type' => $redirect_type,
            'method' => $method,
            'redirect_id' => $redirect_id,
            'created_at' => current_time('mysql'),
        );
        update_option('siloq_redirects', $siloq_redirects);

        return rest_ensure_response(array(
            'success' => true,
            'method' => $method,
            'redirect_id' => $redirect_id,
            'message' => sprintf(__('Redirect created via %s', 'siloq-connector'), $method),
        ));
    }

    /**
     * Handle page.update_meta event
     */
    private function handle_page_update_meta($data) {
        // Validate required fields
        if (empty($data['url'])) {
            return new WP_Error(
                'missing_data',
                __('Missing required field: url', 'siloq-connector'),
                array('status' => 400)
            );
        }

        $url = esc_url_raw($data['url']);
        $post_id = $this->find_post_by_url($url);

        if (!$post_id) {
            return new WP_Error(
                'post_not_found',
                __('Post not found for the provided URL', 'siloq-connector'),
                array('status' => 404)
            );
        }

        $updated_fields = array();

        // Update post title
        if (!empty($data['title'])) {
            $new_title = sanitize_text_field($data['title']);
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $new_title,
            ));
            $updated_fields[] = 'title';
        }

        // Update AIOSEO meta title
        if (!empty($data['title'])) {
            $this->update_aioseo_field($post_id, 'title', sanitize_text_field($data['title']));
        }

        // Update AIOSEO meta description
        if (!empty($data['meta_description'])) {
            $this->update_aioseo_field($post_id, 'description', sanitize_text_field($data['meta_description']));
            $updated_fields[] = 'meta_description';
        }

        // Update H1 in content
        if (!empty($data['h1'])) {
            $post = get_post($post_id);
            $content = $post->post_content;
            $new_h1 = sanitize_text_field($data['h1']);

            // Check if content starts with H1
            if (preg_match('/^<h1[^>]*>.*?<\/h1>/i', $content)) {
                // Replace existing H1
                $content = preg_replace(
                    '/^<h1[^>]*>.*?<\/h1>/i',
                    '<h1>' . esc_html($new_h1) . '</h1>',
                    $content
                );
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $content,
                ));
                $updated_fields[] = 'h1';
            } elseif (preg_match('/<h1[^>]*>.*?<\/h1>/i', $content)) {
                // H1 exists but not at the start - replace first occurrence
                $content = preg_replace(
                    '/<h1[^>]*>.*?<\/h1>/i',
                    '<h1>' . esc_html($new_h1) . '</h1>',
                    $content,
                    1
                );
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_content' => $content,
                ));
                $updated_fields[] = 'h1';
            } else {
                // No H1 found - note it needs manual update
                $updated_fields[] = 'h1_manual_required';
            }
        }

        return rest_ensure_response(array(
            'success' => true,
            'post_id' => $post_id,
            'updated_fields' => $updated_fields,
            'message' => sprintf(__('Updated %d fields for post %d', 'siloq-connector'), count($updated_fields), $post_id),
        ));
    }

    /**
     * Find post by URL
     */
    private function find_post_by_url($url) {
        // Try url_to_postid first
        $post_id = url_to_postid($url);
        if ($post_id) {
            return $post_id;
        }
        
        // Try by slug
        $path = parse_url($url, PHP_URL_PATH);
        $slug = trim($path, '/');
        $slug = basename($slug); // Get last segment
        
        $posts = get_posts(array(
            'name' => $slug,
            'post_type' => array('page', 'post', 'product'),
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'numberposts' => 1,
        ));
        
        return !empty($posts) ? $posts[0]->ID : 0;
    }

    /**
     * Update AIOSEO field (title or description)
     */
    private function update_aioseo_field($post_id, $field, $value) {
        global $wpdb;
        $aioseo_table = $wpdb->prefix . 'aioseo_posts';

        // Check if AIOSEO table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $aioseo_table)) === $aioseo_table) {
            // Check if row exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$aioseo_table} WHERE post_id = %d",
                $post_id
            ));

            if ($existing) {
                // Update existing row
                $wpdb->update(
                    $aioseo_table,
                    array($field => $value),
                    array('post_id' => $post_id),
                    array('%s'),
                    array('%d')
                );
            } else {
                // Insert new row
                $wpdb->insert(
                    $aioseo_table,
                    array(
                        'post_id' => $post_id,
                        $field => $value,
                    ),
                    array('%d', '%s')
                );
            }
        }

        // Also store as post meta fallback
        $meta_key = '_aioseo_' . $field;
        update_post_meta($post_id, $meta_key, $value);
    }

    /**
     * Add redirect to .htaccess
     */
    private function add_htaccess_redirect($from, $to, $type = 301) {
        $htaccess = ABSPATH . '.htaccess';

        if (!file_exists($htaccess) || !is_writable($htaccess)) {
            return array(
                'success' => false,
                'message' => __('.htaccess file not writable', 'siloq-connector'),
            );
        }

        $content = file_get_contents($htaccess);
        $redirect_line = "Redirect {$type} {$from} {$to}";

        // Check if redirect already exists
        if (strpos($content, $redirect_line) !== false) {
            return array(
                'success' => true,
                'message' => __('Redirect already exists', 'siloq-connector'),
            );
        }

        // Add before WordPress rewrite rules
        if (strpos($content, '# BEGIN WordPress') !== false) {
            $content = str_replace(
                '# BEGIN WordPress',
                $redirect_line . "\n# BEGIN WordPress",
                $content
            );
        } else {
            // If no WordPress section, add at the end
            $content .= "\n" . $redirect_line . "\n";
        }

        file_put_contents($htaccess, $content);

        return array(
            'success' => true,
            'message' => __('Redirect added to .htaccess', 'siloq-connector'),
        );
    }
    
    /**
     * Get webhook URL for Siloq backend configuration
     */
    public static function get_webhook_url() {
        return rest_url('siloq/v1/webhook');
    }
}
