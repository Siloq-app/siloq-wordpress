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

        // Admin Settings endpoints for React UI
        register_rest_route( $ns, '/settings', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_settings' ),
            'permission_callback' => array( __CLASS__, 'require_manage_options' ),
        ) );

        register_rest_route( $ns, '/settings', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'save_settings' ),
            'permission_callback' => array( __CLASS__, 'require_manage_options' ),
        ) );

        register_rest_route( $ns, '/settings/test-connection', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'test_connection' ),
            'permission_callback' => array( __CLASS__, 'require_manage_options' ),
        ) );

        // Dashboard stats
        register_rest_route( $ns, '/dashboard/stats', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_dashboard_stats' ),
            'permission_callback' => array( __CLASS__, 'require_manage_options' ),
        ) );

        // Sync endpoints
        register_rest_route( $ns, '/sync/status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_sync_status' ),
            'permission_callback' => array( __CLASS__, 'require_edit_pages' ),
        ) );

        register_rest_route( $ns, '/sync/start', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'start_sync' ),
            'permission_callback' => array( __CLASS__, 'require_edit_pages' ),
        ) );
    }

    public static function require_edit_posts() {
        return current_user_can( 'edit_posts' );
    }

    public static function require_manage_options() {
        return current_user_can( 'manage_options' );
    }

    public static function require_edit_pages() {
        return current_user_can( 'edit_pages' );
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    public static function get_settings( $request ) {
        $settings = array(
            'api_key'           => get_option( 'siloq_api_key', '' ),
            'api_url'           => get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' ),
            'api_timeout'       => intval( get_option( 'siloq_api_timeout', 30 ) ),
            'site_id'           => get_option( 'siloq_site_id', '' ),
            'webhook_secret'    => get_option( 'siloq_webhook_secret', '' ),
            'anthropic_api_key' => get_option( 'siloq_anthropic_api_key', '' ),
            'anthropic_model'   => get_option( 'siloq_anthropic_model', 'claude-3-5-sonnet-20241022' ),
            'cache_duration'    => intval( get_option( 'siloq_cache_duration', 60 ) ),
            'auto_sync'         => get_option( 'siloq_auto_sync', '1' ) === '1',
            'sync_on_save'      => get_option( 'siloq_sync_on_save', '1' ) === '1',
        );
        return new WP_REST_Response( $settings, 200 );
    }

    public static function save_settings( $request ) {
        $params = $request->get_json_params();

        if ( isset( $params['api_key'] ) ) {
            update_option( 'siloq_api_key', sanitize_text_field( $params['api_key'] ) );
        }
        if ( isset( $params['api_url'] ) ) {
            update_option( 'siloq_api_url', esc_url_raw( $params['api_url'] ) );
        }
        if ( isset( $params['api_timeout'] ) ) {
            update_option( 'siloq_api_timeout', intval( $params['api_timeout'] ) );
        }
        if ( isset( $params['webhook_secret'] ) ) {
            update_option( 'siloq_webhook_secret', sanitize_text_field( $params['webhook_secret'] ) );
        }
        if ( isset( $params['anthropic_api_key'] ) ) {
            update_option( 'siloq_anthropic_api_key', sanitize_text_field( $params['anthropic_api_key'] ) );
        }
        if ( isset( $params['anthropic_model'] ) ) {
            update_option( 'siloq_anthropic_model', sanitize_text_field( $params['anthropic_model'] ) );
        }
        if ( isset( $params['cache_duration'] ) ) {
            update_option( 'siloq_cache_duration', intval( $params['cache_duration'] ) );
        }
        if ( isset( $params['auto_sync'] ) ) {
            update_option( 'siloq_auto_sync', $params['auto_sync'] ? '1' : '0' );
        }
        if ( isset( $params['sync_on_save'] ) ) {
            update_option( 'siloq_sync_on_save', $params['sync_on_save'] ? '1' : '0' );
        }

        return new WP_REST_Response( array( 'success' => true, 'message' => 'Settings saved.' ), 200 );
    }

    public static function test_connection( $request ) {
        $api_key = get_option( 'siloq_api_key', '' );
        $api_url = get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'API key is not configured.', array( 'status' => 400 ) );
        }

        $response = wp_remote_get(
            trailingslashit( $api_url ) . 'ping/',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'connection_failed', $response->get_error_message(), array( 'status' => 502 ) );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code === 200 ) {
            return new WP_REST_Response( array(
                'success' => true,
                'message' => 'Connection successful!',
                'api_version' => $body['version'] ?? 'unknown',
            ), 200 );
        }

        return new WP_Error( 'connection_failed', $body['message'] ?? 'Connection failed.', array( 'status' => 502 ) );
    }

    // ── Dashboard ───────────────────────────────────────────────────────────────

    public static function get_dashboard_stats( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );

        $stats = array(
            'site_score'      => intval( get_option( 'siloq_site_score', 42 ) ),
            'site_id'         => $site_id,
            'total_pages'     => wp_count_posts( 'page' )->publish,
            'synced_pages'    => self::count_synced_pages(),
            'has_api_key'     => ! empty( get_option( 'siloq_api_key', '' ) ),
            'has_anthropic_key' => ! empty( get_option( 'siloq_anthropic_api_key', '' ) ),
        );

        return new WP_REST_Response( $stats, 200 );
    }

    private static function count_synced_pages() {
        global $wpdb;
        return intval( $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_siloq_synced' AND meta_value = '1'"
        ) );
    }

    // ── Sync ──────────────────────────────────────────────────────────────────

    public static function get_sync_status( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );

        if ( empty( $site_id ) ) {
            return new WP_REST_Response( array(
                'connected' => false,
                'message'   => 'Site not connected.',
            ), 200 );
        }

        // Get sync stats
        $total_pages  = wp_count_posts( 'page' )->publish;
        $synced_pages = self::count_synced_pages();

        return new WP_REST_Response( array(
            'connected'     => true,
            'site_id'       => $site_id,
            'total_pages'   => $total_pages,
            'synced_pages'  => $synced_pages,
            'pending_pages' => $total_pages - $synced_pages,
            'sync_percentage' => $total_pages > 0 ? round( ( $synced_pages / $total_pages ) * 100 ) : 0,
        ), 200 );
    }

    public static function start_sync( $request ) {
        $params = $request->get_json_params();
        $mode   = sanitize_text_field( $params['mode'] ?? 'all' );

        // Trigger sync via the existing sync engine
        if ( class_exists( 'Siloq_Sync_Engine' ) ) {
            $engine = Siloq_Sync_Engine::get_instance();
            $result = $engine->sync_all_pages( $mode === 'missing' );
            return new WP_REST_Response( $result, 200 );
        }

        return new WP_Error( 'sync_engine_unavailable', 'Sync engine is not available.', array( 'status' => 500 ) );
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
