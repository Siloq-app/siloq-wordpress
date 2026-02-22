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
        
        if (empty($signature)) {
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
     * Get webhook URL for Siloq backend configuration
     */
    public static function get_webhook_url() {
        return rest_url('siloq/v1/webhook');
    }
}
