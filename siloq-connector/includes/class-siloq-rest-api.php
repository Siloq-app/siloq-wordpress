<?php
/**
 * Siloq REST API endpoints
 * Registered under /wp-json/siloq/v1/
 *
 * These replace admin-ajax.php calls that get blocked by security plugins/WAFs.
 * REST API is the modern WP standard and more reliable across hosting environments.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Siloq_REST_API {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        $ns = 'siloq/v1';

        // Diagnostic ping — public, used to detect if REST API is blocked
        register_rest_route( $ns, '/ping', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'ping' ),
            'permission_callback' => '__return_true',
        ) );

        // Authors list
        register_rest_route( $ns, '/authors', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_authors' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Content jobs (blog pipeline)
        register_rest_route( $ns, '/content-jobs', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_content_jobs' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Suggest spoke pages (AI suggestions for a hub page)
        register_rest_route( $ns, '/suggest-spoke', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'suggest_spoke' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Analyze a single page
        register_rest_route( $ns, '/analyze-page', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'analyze_page' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Start a background job
        register_rest_route( $ns, '/jobs/start', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'start_job' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Poll job status
        register_rest_route( $ns, '/jobs/(?P<job_id>\d+)/status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'job_status' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );
    }

    public static function require_edit_posts() {
        return current_user_can( 'edit_posts' );
    }

    // ── Ping ──────────────────────────────────────────────────────────────────

    public static function ping( $request ) {
        return new WP_REST_Response( array(
            'status'  => 'ok',
            'siloq'   => 'connected',
            'version' => defined( 'SILOQ_VERSION' ) ? SILOQ_VERSION : 'unknown',
        ), 200 );
    }

    // ── Authors ───────────────────────────────────────────────────────────────

    public static function get_authors( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            return new WP_Error( 'no_site', 'Site not connected.', array( 'status' => 400 ) );
        }
        $api    = new Siloq_API_Client();
        $result = $api->get( '/sites/' . $site_id . '/authors/' );
        return new WP_REST_Response( $result, 200 );
    }

    // ── Content Jobs ──────────────────────────────────────────────────────────

    public static function get_content_jobs( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            return new WP_Error( 'no_site', 'Site not connected.', array( 'status' => 400 ) );
        }
        $api    = new Siloq_API_Client();
        $result = $api->get( '/sites/' . $site_id . '/content/jobs/' );
        return new WP_REST_Response( $result, 200 );
    }

    // ── Suggest Spoke ─────────────────────────────────────────────────────────

    public static function suggest_spoke( $request ) {
        $site_id    = get_option( 'siloq_site_id', '' );
        $hub_post_id = intval( $request->get_param( 'hub_post_id' ) );
        $hub_title   = sanitize_text_field( $request->get_param( 'hub_title' ) ?? '' );
        $existing    = array_map( 'sanitize_text_field', (array) ( $request->get_param( 'existing_spokes' ) ?? array() ) );

        if ( empty( $site_id ) ) {
            return new WP_Error( 'no_site', 'Site not connected.', array( 'status' => 400 ) );
        }
        if ( empty( $hub_title ) && $hub_post_id ) {
            $post      = get_post( $hub_post_id );
            $hub_title = $post ? $post->post_title : '';
        }

        $api    = new Siloq_API_Client();
        $result = $api->post( '/sites/' . $site_id . '/content/suggest-spoke/', array(
            'hub_id'          => $hub_post_id,
            'hub_title'       => $hub_title,
            'existing_spokes' => $existing,
        ) );

        if ( ! empty( $result['data']['suggestions'] ) ) {
            return new WP_REST_Response( array( 'suggestions' => $result['data']['suggestions'] ), 200 );
        } elseif ( ! empty( $result['suggestions'] ) ) {
            return new WP_REST_Response( array( 'suggestions' => $result['suggestions'] ), 200 );
        }
        return new WP_Error( 'api_error', $result['message'] ?? 'No suggestions returned.', array( 'status' => 502 ) );
    }

    // ── Analyze Page ──────────────────────────────────────────────────────────

    public static function analyze_page( $request ) {
        $post_id = intval( $request->get_param( 'post_id' ) );
        $site_id = get_option( 'siloq_site_id', '' );
        if ( ! $post_id || ! $site_id ) {
            return new WP_Error( 'missing_params', 'Missing post_id or site not connected.', array( 'status' => 400 ) );
        }
        $siloq_page_id = get_post_meta( $post_id, '_siloq_page_id', true );
        if ( ! $siloq_page_id ) {
            return new WP_Error( 'not_synced', 'Page not synced to Siloq yet — sync first.', array( 'status' => 400 ) );
        }
        $api    = new Siloq_API_Client();
        $result = $api->post( '/sites/' . $site_id . '/pages/' . $siloq_page_id . '/analyze/', array() );
        return new WP_REST_Response( $result, 200 );
    }

    // ── Background Jobs ───────────────────────────────────────────────────────

    public static function start_job( $request ) {
        $job_type = sanitize_key( $request->get_param( 'job_type' ) ?? '' );
        $site_id  = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            return new WP_Error( 'no_site', 'Site not connected.', array( 'status' => 400 ) );
        }
        $endpoint_map = array(
            'full_audit'      => '/sites/' . $site_id . '/jobs/full-audit/',
            'meta_generation' => '/sites/' . $site_id . '/jobs/generate-meta/',
            'audit_links'     => '/sites/' . $site_id . '/jobs/audit-links/',
        );
        if ( ! isset( $endpoint_map[ $job_type ] ) ) {
            return new WP_Error( 'invalid_type', 'Unknown job type.', array( 'status' => 400 ) );
        }
        $api    = new Siloq_API_Client();
        $result = $api->post( $endpoint_map[ $job_type ], array() );
        return new WP_REST_Response( $result, isset( $result['job_id'] ) ? 201 : 502 );
    }

    public static function job_status( $request ) {
        $job_id  = intval( $request->get_param( 'job_id' ) );
        $site_id = get_option( 'siloq_site_id', '' );
        if ( ! $job_id || ! $site_id ) {
            return new WP_Error( 'missing_params', 'Missing job_id or site not connected.', array( 'status' => 400 ) );
        }
        $api    = new Siloq_API_Client();
        $result = $api->get( '/jobs/' . $job_id . '/' );
        return new WP_REST_Response( $result, 200 );
    }
}

Siloq_REST_API::init();
