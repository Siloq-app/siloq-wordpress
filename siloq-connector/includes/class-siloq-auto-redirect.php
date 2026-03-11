<?php
/**
 * Siloq Auto Redirect
 *
 * Automatically creates 301 redirects when a published post/page slug changes.
 * Hooks into post_updated to detect slug changes and register them via
 * Siloq_Redirect_Manager (local DB) and the Siloq API (best-effort sync).
 *
 * PHP 7.3 compatible — no arrow functions, no match().
 *
 * @package Siloq
 * @since   1.5.171
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Auto_Redirect {

    /**
     * Register WordPress hooks.
     */
    public static function init() {
        add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
    }

    /**
     * Fire when a post is saved/updated. Detects slug changes on published posts
     * and creates a 301 redirect from the old path to the new path.
     *
     * @param int     $post_id     Post ID.
     * @param WP_Post $post_after  Post object after the update.
     * @param WP_Post $post_before Post object before the update.
     */
    public static function on_post_updated( $post_id, $post_after, $post_before ) {
        // Only run for published posts/pages — both states must be publish.
        if ( $post_after->post_status !== 'publish' ) return;
        if ( $post_before->post_status !== 'publish' ) return;

        $old_slug = $post_before->post_name;
        $new_slug = $post_after->post_name;

        if ( $old_slug === $new_slug ) return;

        // Build old URL by temporarily cloning post_after with the old slug.
        $post_before_obj            = clone $post_after;
        $post_before_obj->post_name = $old_slug;

        $old_url = get_permalink( $post_before_obj );
        $new_url = get_permalink( $post_after );

        if ( ! $old_url || ! $new_url || $old_url === $new_url ) return;

        $old_path = wp_parse_url( $old_url, PHP_URL_PATH );
        $new_path = wp_parse_url( $new_url, PHP_URL_PATH );

        if ( ! $old_path || ! $new_path ) return;

        // Store redirect via Siloq_Redirect_Manager (local DB).
        if ( class_exists( 'Siloq_Redirect_Manager' ) ) {
            Siloq_Redirect_Manager::add_redirect( $old_path, $new_path, 301 );

            // Sync to API if site is connected (best-effort — non-blocking).
            $site_id = get_option( 'siloq_site_id', '' );
            if ( ! empty( $site_id ) ) {
                $api = new Siloq_API_Client();
                $api->post( '/sites/' . $site_id . '/redirects/', array(
                    'from_url'    => $old_path,
                    'to_url'      => $new_path,
                    'status_code' => 301,
                    'source'      => 'auto_slug_change',
                ) );
            }
        }
    }
}
