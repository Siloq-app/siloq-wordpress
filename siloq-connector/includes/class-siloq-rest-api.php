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

        // Authors list + create
        register_rest_route( $ns, '/authors', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'get_authors' ),
                'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'create_author' ),
                'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
            ),
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
            // Not an error — site not connected yet
            return new WP_REST_Response( array(), 200 );
        }
        $api    = new Siloq_API_Client();
        $result = $api->get( '/authors/' );

        // Normalize: API may return array directly or wrapped
        if ( is_array( $result ) && isset( $result[0] ) ) {
            return new WP_REST_Response( $result, 200 );
        }
        if ( is_array( $result ) && isset( $result['results'] ) ) {
            return new WP_REST_Response( $result['results'], 200 );
        }
        if ( is_array( $result ) && isset( $result['data'] ) ) {
            return new WP_REST_Response( $result['data'], 200 );
        }
        // Empty — no authors yet (not an error)
        return new WP_REST_Response( array(), 200 );
    }

    public static function create_author( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            return new WP_Error( 'no_site', 'Site not connected.', array( 'status' => 400 ) );
        }
        $api    = new Siloq_API_Client();
        $result = $api->post( '/authors/', $request->get_json_params() );

        // Return whatever the API returns
        $status = isset( $result['id'] ) ? 201 : 200;
        return new WP_REST_Response( $result, $status );
    }

    // ── Content Jobs ──────────────────────────────────────────────────────────

    public static function get_content_jobs( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            // Return empty state — site not connected, not an error
            return new WP_REST_Response( array(
                'jobs'      => array(),
                'message'   => 'Connect your site to Siloq to enable the content pipeline.',
                'connected' => false,
            ), 200 );
        }

        $api    = new Siloq_API_Client();
        $result = $api->get( '/sites/' . $site_id . '/content/jobs/' );

        // Normalize response — API may return array directly or wrapped object
        if ( is_array( $result ) && isset( $result[0] ) ) {
            return new WP_REST_Response( array( 'jobs' => $result, 'connected' => true ), 200 );
        }
        if ( is_array( $result ) && isset( $result['data'] ) ) {
            return new WP_REST_Response( array( 'jobs' => $result['data'], 'connected' => true ), 200 );
        }
        // Empty or no jobs
        return new WP_REST_Response( array( 'jobs' => array(), 'connected' => true ), 200 );
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
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            return new WP_Error( 'no_site', 'Site not connected.', array( 'status' => 400 ) );
        }

        // Accept either siloq_page_id (preferred — passed directly from pages list)
        // OR post_id (WP post ID — used by the Pages tab metabox)
        $siloq_page_id = intval( $request->get_param( 'siloq_page_id' ) );

        if ( ! $siloq_page_id ) {
            // Fallback: look up via WP post ID
            $post_id = intval( $request->get_param( 'post_id' ) );
            if ( $post_id ) {
                $siloq_page_id = intval( get_post_meta( $post_id, '_siloq_page_id', true ) );
            }
        }

        if ( ! $siloq_page_id ) {
            return new WP_Error(
                'not_synced',
                'Page ID missing. Sync this page to Siloq first, then try again.',
                array( 'status' => 400 )
            );
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
