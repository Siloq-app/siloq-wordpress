<?php
/**
 * Siloq Page Analyzer — AJAX Integration
 *
 * Handles AJAX requests for page analysis, applying recommendations,
 * and approving changes. Uses Siloq_Content_Extractor to send accurate
 * content payloads to the API regardless of page builder.
 *
 * Hooks registered: wp_ajax_siloq_analyze_page, wp_ajax_siloq_apply_analysis,
 *                   wp_ajax_siloq_approve_changes
 *
 * @package Siloq
 * @since 1.5.38
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Page_Analyzer {

    public static function init() {
        add_action( 'wp_ajax_siloq_analyze_page',    array( __CLASS__, 'ajax_analyze_page' ) );
        add_action( 'wp_ajax_siloq_apply_analysis',  array( __CLASS__, 'ajax_apply_analysis' ) );
        add_action( 'wp_ajax_siloq_approve_changes', array( __CLASS__, 'ajax_approve_changes' ) );
    }

    // =========================================================================
    // AJAX: Analyze a page
    // =========================================================================

    /**
     * Triggered when the user clicks "Analyze" on a page.
     * Extracts full content using Siloq_Content_Extractor (builder-aware)
     * and sends it to the API analyze endpoint.
     *
     * POST: nonce, post_id
     */
    public static function ajax_analyze_page() {
        check_ajax_referer( 'siloq_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
            return;
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => 'Missing post_id' ) );
            return;
        }

        if ( ! class_exists( 'Siloq_Content_Extractor' ) ) {
            wp_send_json_error( array( 'message' => 'Siloq_Content_Extractor not loaded' ) );
            return;
        }

        // Extract full content (builder-aware)
        $content_payload = Siloq_Content_Extractor::extract( $post_id );

        if ( ! empty( $content_payload['errors'] ) ) {
            error_log( 'Siloq extractor warnings (post ' . $post_id . '): ' . implode( '; ', $content_payload['errors'] ) );
        }

        $site_id  = get_option( 'siloq_site_id' );
        $api_key  = get_option( 'siloq_api_key' );
        $api_base = defined( 'SILOQ_API_BASE' ) ? SILOQ_API_BASE : 'https://api.siloq.app';

        if ( ! $site_id || ! $api_key ) {
            wp_send_json_error( array( 'message' => 'Siloq not connected. Check plugin Settings.' ) );
            return;
        }

        $post      = get_post( $post_id );
        $permalink = get_permalink( $post_id );

        $response = wp_remote_post(
            $api_base . '/api/v1/sites/' . $site_id . '/pages/analyze/',
            array(
                'timeout' => 45,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
                ),
                'body' => wp_json_encode( array(
                    'site_id'         => $site_id,
                    'wp_post_id'      => $post_id,
                    'page_title'      => class_exists( 'Siloq_Admin' ) ? Siloq_Admin::siloq_get_page_title( $post_id ) : ( $post ? $post->post_title : '' ),
                    'page_url'        => $permalink,
                    'content_payload' => $content_payload,
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => 'API request failed: ' . $response->get_error_message() ) );
            return;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status !== 200 && $status !== 201 ) {
            wp_send_json_error( array(
                'message'     => 'API error ' . $status,
                'api_message' => $body['detail'] ?? $body['message'] ?? 'Unknown error',
            ) );
            return;
        }

        wp_send_json_success( array(
            'analysis_id'    => $body['id']     ?? null,
            'status'         => $body['status'] ?? 'pending',
            'builder'        => $content_payload['builder'],
            'word_count'     => $content_payload['word_count'],
            'has_faq'        => $content_payload['has_faq'],
            'links_found'    => count( $content_payload['links'] ),
            'headings_found' => count( $content_payload['headings'] ),
        ) );
    }

    // =========================================================================
    // AJAX: Approve selected recommendations
    // =========================================================================

    /**
     * POST: nonce, analysis_id, approved_ids[]
     */
    public static function ajax_approve_changes() {
        check_ajax_referer( 'siloq_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
            return;
        }

        $site_id     = get_option( 'siloq_site_id' );
        $api_key     = get_option( 'siloq_api_key' );
        $api_base    = defined( 'SILOQ_API_BASE' ) ? SILOQ_API_BASE : 'https://api.siloq.app';
        $analysis_id = isset( $_POST['analysis_id'] ) ? sanitize_text_field( $_POST['analysis_id'] ) : '';
        $approved    = isset( $_POST['approved_ids'] ) ? (array) $_POST['approved_ids'] : array();

        if ( ! $analysis_id ) {
            wp_send_json_error( array( 'message' => 'Missing analysis_id' ) );
            return;
        }

        $response = wp_remote_post(
            $api_base . '/api/v1/sites/' . $site_id . '/pages/analysis/' . $analysis_id . '/approve/',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
                ),
                'body' => wp_json_encode( array(
                    'approved_recommendation_ids' => $approved,
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
            return;
        }

        wp_send_json_success( json_decode( wp_remote_retrieve_body( $response ), true ) );
    }

    // =========================================================================
    // AJAX: Apply approved changes to WordPress
    // =========================================================================

    /**
     * Triggers the API to push approved changes back via webhook.
     * POST: nonce, analysis_id
     */
    public static function ajax_apply_analysis() {
        check_ajax_referer( 'siloq_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
            return;
        }

        $site_id     = get_option( 'siloq_site_id' );
        $api_key     = get_option( 'siloq_api_key' );
        $api_base    = defined( 'SILOQ_API_BASE' ) ? SILOQ_API_BASE : 'https://api.siloq.app';
        $analysis_id = isset( $_POST['analysis_id'] ) ? sanitize_text_field( $_POST['analysis_id'] ) : '';

        if ( ! $analysis_id ) {
            wp_send_json_error( array( 'message' => 'Missing analysis_id' ) );
            return;
        }

        $webhook_secret = get_option( 'siloq_webhook_secret' );
        if ( ! $webhook_secret ) {
            wp_send_json_error( array(
                'message' => 'Webhook secret not configured. Go to Siloq > Settings and generate a webhook secret before applying changes.',
            ) );
            return;
        }

        $response = wp_remote_post(
            $api_base . '/api/v1/sites/' . $site_id . '/pages/analysis/' . $analysis_id . '/apply/',
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
                ),
                'body' => wp_json_encode( array( 'site_id' => $site_id ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
            return;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status >= 400 ) {
            wp_send_json_error( array(
                'message'     => 'Apply failed with status ' . $status,
                'api_message' => $body['detail'] ?? 'Unknown error',
            ) );
            return;
        }

        wp_send_json_success( array(
            'message' => 'Changes applied successfully. Your page will update within a few seconds.',
            'data'    => $body,
        ) );
    }

} // end class Siloq_Page_Analyzer
