<?php
/**
 * Siloq Agent Executor
 * REST endpoints for the agent execution engine to push changes to WordPress.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Agent_Executor {

    /**
     * Initialize agent executor
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    /**
     * Register REST routes
     */
    public static function register_routes() {
        register_rest_route('siloq/v1', '/update-meta', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_update_meta'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('siloq/v1', '/internal-link', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_internal_link'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Verify request via Bearer token or HMAC signature
     */
    private static function verify_request($request) {
        $api_key = get_option('siloq_api_key', '');
        if (empty($api_key)) {
            return false;
        }

        // Bearer token auth
        $auth = $request->get_header('Authorization');
        if (!empty($auth) && str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);
            return hash_equals($api_key, $token);
        }

        // HMAC signature auth
        $sig = $request->get_header('X-Siloq-Signature');
        if (!empty($sig)) {
            $expected = hash_hmac('sha256', $request->get_body(), $api_key);
            return hash_equals($expected, $sig);
        }

        return false;
    }

    /**
     * POST /wp-json/siloq/v1/update-meta
     * Updates page meta (title, description, canonical URL) for a given post.
     */
    public static function handle_update_meta($request) {
        if (!self::verify_request($request)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Unauthorized'), 401);
        }

        $params = $request->get_json_params();
        if (empty($params['post_id'])) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Missing post_id'), 400);
        }

        $post_id = intval($params['post_id']);
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_REST_Response(array('success' => false, 'message' => 'Post not found or not published'), 404);
        }

        $updated = array();

        // Meta title
        if (!empty($params['meta_title'])) {
            $title = sanitize_text_field($params['meta_title']);
            update_post_meta($post_id, '_aioseo_title', $title);
            update_post_meta($post_id, '_yoast_wpseo_title', $title);
            $updated[] = 'meta_title';
        }

        // Meta description
        if (!empty($params['meta_description'])) {
            $desc = sanitize_text_field($params['meta_description']);
            update_post_meta($post_id, '_aioseo_description', $desc);
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $desc);
            $updated[] = 'meta_description';
        }

        // Canonical URL
        if (!empty($params['canonical_url'])) {
            $canonical = esc_url_raw($params['canonical_url']);
            update_post_meta($post_id, '_aioseo_canonical_url', $canonical);
            update_post_meta($post_id, '_yoast_wpseo_canonical', $canonical);
            $updated[] = 'canonical_url';
        }

        if (empty($updated)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'No meta fields provided'), 400);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'post_id' => $post_id,
            'updated' => $updated,
        ));
    }

    /**
     * POST /wp-json/siloq/v1/internal-link
     * Injects an internal link into a page's content.
     */
    public static function handle_internal_link($request) {
        if (!self::verify_request($request)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Unauthorized'), 401);
        }

        $params = $request->get_json_params();

        if (empty($params['source_post_id']) || empty($params['target_url']) || empty($params['anchor_text'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Missing required fields: source_post_id, target_url, anchor_text',
            ), 400);
        }

        $post_id     = intval($params['source_post_id']);
        $target_url  = esc_url_raw($params['target_url']);
        $anchor_text = sanitize_text_field($params['anchor_text']);
        $context_hint = !empty($params['context_hint']) ? sanitize_text_field($params['context_hint']) : '';

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_REST_Response(array('success' => false, 'message' => 'Post not found or not published'), 404);
        }

        $content = $post->post_content;

        // Check if a link to target_url already exists
        if (stripos($content, $target_url) !== false && preg_match('/<a\s[^>]*href=["\']' . preg_quote($target_url, '/') . '["\'][^>]*>/i', $content)) {
            return new WP_REST_Response(array(
                'success'        => true,
                'source_post_id' => $post_id,
                'method'         => 'already_exists',
            ));
        }

        $method = 'appended';

        // Try to find context_hint in content and wrap anchor_text with link
        if (!empty($context_hint)) {
            // Find a sentence containing the context hint (case-insensitive)
            $pattern = '/([^.!?\n]*' . preg_quote($context_hint, '/') . '[^.!?\n]*[.!?]?)/i';
            if (preg_match($pattern, $content, $matches)) {
                $sentence = $matches[1];
                // Check if anchor_text exists in that sentence
                $anchor_pos = stripos($sentence, $anchor_text);
                if ($anchor_pos !== false) {
                    $actual_text = substr($sentence, $anchor_pos, strlen($anchor_text));
                    $linked = '<a href="' . esc_url($target_url) . '">' . esc_html($actual_text) . '</a>';
                    $new_sentence = substr_replace($sentence, $linked, $anchor_pos, strlen($anchor_text));
                    $content = str_replace($sentence, $new_sentence, $content);
                    $method = 'injected';
                } else {
                    // Anchor text not in that sentence — append after the sentence
                    $link_html = ' <a href="' . esc_url($target_url) . '">' . esc_html($anchor_text) . '</a>';
                    $content = str_replace($sentence, $sentence . $link_html, $content);
                    $method = 'injected';
                }
            }
        }

        // Fallback: append a "Related" paragraph
        if ($method === 'appended') {
            $content .= "\n\n" . '<p>Related: <a href="' . esc_url($target_url) . '">' . esc_html($anchor_text) . '</a></p>';
        }

        $result = wp_update_post(array(
            'ID'           => $post_id,
            'post_content' => wp_kses_post($content),
        ), true);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to update post: ' . $result->get_error_message(),
            ), 500);
        }

        return new WP_REST_Response(array(
            'success'        => true,
            'source_post_id' => $post_id,
            'method'         => $method,
        ));
    }
}
