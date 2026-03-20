<?php
/**
 * Siloq Page Events — fires outbound webhook to Siloq API on page/post changes.
 *
 * @since 1.5.203
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Page_Events {

    private $api_base_url;
    private $api_key;
    private $site_id;

    public function __construct() {
        $this->api_base_url = get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' );
        $this->api_key      = get_option( 'siloq_api_key', '' );
        $this->site_id      = get_option( 'siloq_site_id', '' );
    }

    public function register_hooks() {
        add_action( 'save_post',     [ $this, 'on_save_post' ],     10, 3 );
        add_action( 'trashed_post',  [ $this, 'on_trashed_post' ],  10, 1 );
    }

    /**
     * Fires on every post/page save.
     */
    public function on_save_post( $post_id, $post, $update ) {
        // Skip autosaves — CRITICAL to avoid flooding
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        // Skip revisions
        if ( wp_is_post_revision( $post_id ) ) return;
        // Only fire for published posts/pages
        if ( $post->post_status !== 'publish' ) return;
        // Only pages and posts
        if ( ! in_array( $post->post_type, [ 'page', 'post' ], true ) ) return;
        // Require site_id and api_key
        if ( empty( $this->site_id ) || empty( $this->api_key ) ) return;

        $event_type = $update ? 'page_updated' : 'page_published';

        $this->fire_event( $post_id, $post, $event_type );
    }

    /**
     * Fires when a post/page is trashed.
     */
    public function on_trashed_post( $post_id ) {
        if ( empty( $this->site_id ) || empty( $this->api_key ) ) return;

        $post = get_post( $post_id );
        if ( ! $post ) return;

        $this->fire_event( $post_id, $post, 'page_trashed' );
    }

    /**
     * Send event payload to Siloq inbound webhook endpoint.
     */
    private function fire_event( $post_id, $post, $event_type ) {
        $content = wp_strip_all_tags( $post->post_content );
        $excerpt = substr( $content, 0, 500 );

        $payload = [
            'event_type'      => $event_type,
            'wp_post_id'      => (int) $post_id,
            'site_id'         => (int) $this->site_id,
            'title'           => $post->post_title,
            'url'             => get_permalink( $post_id ),
            'content_excerpt' => $excerpt,
            'post_type'       => $post->post_type,
            'parent_id'       => $post->post_parent ?: null,
            'timestamp'       => gmdate( 'c' ),
        ];

        $body     = wp_json_encode( $payload );
        $sig      = hash_hmac( 'sha256', $body, $this->api_key );
        $endpoint = trailingslashit( $this->api_base_url ) . 'integrations/webhook/inbound/';

        wp_remote_post( $endpoint, [
            'timeout'  => 10,
            'blocking' => false,   // non-blocking: don't hold up the page save
            'headers'  => [
                'Content-Type'      => 'application/json',
                'X-Siloq-Signature' => $sig,
                'User-Agent'        => 'Siloq/1.0 (WordPress Plugin; +https://siloq.ai)',
            ],
            'body' => $body,
        ] );
    }
}
