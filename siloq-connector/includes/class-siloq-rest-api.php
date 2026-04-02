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

        // Goals — save and retrieve
        register_rest_route( $ns, '/goals', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'get_goals' ),
                'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'save_goals' ),
                'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
            ),
        ) );

        // Business profile
        register_rest_route( $ns, '/business-profile', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'get_business_profile' ),
                'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'save_business_profile' ),
                'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
            ),
        ) );

        // Schema operations
        register_rest_route( $ns, '/schema/generate', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'generate_schema' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );
        register_rest_route( $ns, '/schema/apply', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'apply_schema' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );
        register_rest_route( $ns, '/schema/status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_schema_status' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );
        register_rest_route( $ns, '/schema/bulk-apply', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'bulk_apply_schema' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );
        register_rest_route( $ns, '/schema/graph', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_schema_graph' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );
        register_rest_route( $ns, '/schema/repair', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'repair_schema' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Page operations
        register_rest_route( $ns, '/pages/list', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_pages_list' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );
        register_rest_route( $ns, '/pages/role', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'set_page_role' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );
        register_rest_route( $ns, '/pages/create-draft', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'create_draft' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // SEO plan data
        register_rest_route( $ns, '/plan', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_plan_data' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Intelligence generation
        register_rest_route( $ns, '/intelligence/generate', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'generate_intelligence' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Quick win save
        register_rest_route( $ns, '/quick-win', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'save_quick_win' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Dashboard fix (inline meta fixes)
        register_rest_route( $ns, '/dashboard-fix', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'dashboard_fix' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Fix all SEO (single page)
        register_rest_route( $ns, '/fix-all-seo', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'fix_all_seo' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Meta suggestion (AI)
        register_rest_route( $ns, '/meta-suggestion', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'generate_meta_suggestion' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Roadmap progress
        register_rest_route( $ns, '/roadmap-progress', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'save_roadmap_progress' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Content pipeline
        register_rest_route( $ns, '/content/approve', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'approve_content_job' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );
        register_rest_route( $ns, '/content/generate-plan', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'generate_content_plan' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );
        register_rest_route( $ns, '/publish-draft', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'publish_draft' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Sync all pages
        register_rest_route( $ns, '/sync/all', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'sync_all_pages' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        // Redirects
        register_rest_route( $ns, '/redirects', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'get_redirects' ),
                'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'add_redirect' ),
                'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
            ),
        ) );
        register_rest_route( $ns, '/redirects/toggle', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'toggle_redirect' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );
        register_rest_route( $ns, '/redirects/delete', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'delete_redirect' ),
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

        // No site connected — return clean empty state, not an error
        if ( empty( $site_id ) ) {
            return new WP_REST_Response( array(
                'jobs'      => array(),
                'total'     => 0,
                'connected' => false,
                'notice'    => 'Connect your site to Siloq to enable the content pipeline.',
            ), 200 );
        }

        $api    = new Siloq_API_Client();
        $raw    = $api->get( '/sites/' . intval( $site_id ) . '/content/jobs/' );

        // Normalize whatever shape the API returns
        $jobs = array();
        if ( is_array( $raw ) ) {
            if ( isset( $raw[0] ) ) {
                $jobs = $raw; // direct array
            } elseif ( isset( $raw['jobs'] ) ) {
                $jobs = $raw['jobs'];
            } elseif ( isset( $raw['data'] ) && is_array( $raw['data'] ) ) {
                $jobs = $raw['data'];
            } elseif ( isset( $raw['results'] ) ) {
                $jobs = $raw['results'];
            }
        }

        return new WP_REST_Response( array(
            'jobs'      => array_values( $jobs ),
            'total'     => count( $jobs ),
            'connected' => true,
        ), 200 );
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

    // ── Goals ────────────────────────────────────────────────────────────────

    public static function get_goals( $request ) {
        $goals    = Siloq_Goals::get_goals();
        $site_id  = get_option( 'siloq_site_id', '' );
        $target_kw = json_decode( get_option( 'siloq_target_keywords_' . $site_id, '[]' ), true );
        return new WP_REST_Response( array_merge( $goals, array( 'target_keywords' => $target_kw ?: array() ) ), 200 );
    }

    public static function save_goals( $request ) {
        $params          = $request->get_json_params() ?: array();
        $primary_goal    = sanitize_text_field( $params['primary_goal'] ?? 'local_leads' );
        $services        = array_map( 'sanitize_text_field', (array) ( $params['priority_services'] ?? array() ) );
        $cities          = array_map( 'sanitize_text_field', (array) ( $params['priority_cities'] ?? array() ) );
        $target_keywords = array_slice( array_map( 'sanitize_text_field', (array) ( $params['target_keywords'] ?? array() ) ), 0, 7 );

        $site_id = get_option( 'siloq_site_id', '' );
        if ( ! empty( $target_keywords ) ) {
            update_option( 'siloq_target_keywords_' . $site_id, wp_json_encode( $target_keywords ) );
        }
        $goals = array(
            'primary_goal'      => $primary_goal,
            'priority_services' => $services,
            'priority_cities'   => $cities,
            'target_keywords'   => $target_keywords,
        );
        Siloq_Goals::save_goals( $goals );

        if ( $site_id ) {
            $api = new Siloq_API_Client();
            if ( method_exists( 'Siloq_Goals', 'sync_to_api' ) ) {
                Siloq_Goals::sync_to_api( $site_id, $api );
            }
        }
        return new WP_REST_Response( array( 'success' => true, 'message' => 'Goals saved.' ), 200 );
    }

    // ── Business Profile ──────────────────────────────────────────────────

    public static function get_business_profile( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );
        if ( ! $site_id ) {
            return new WP_REST_Response( array(), 200 );
        }
        $api    = new Siloq_API_Client();
        $result = $api->get( '/sites/' . intval( $site_id ) . '/entity-profile/' );
        return new WP_REST_Response( $result, 200 );
    }

    public static function save_business_profile( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );
        if ( ! $site_id ) {
            return new WP_Error( 'no_site', 'Site not connected.', array( 'status' => 400 ) );
        }
        $api    = new Siloq_API_Client();
        $result = $api->post( '/sites/' . intval( $site_id ) . '/entity-profile/', $request->get_json_params() );
        return new WP_REST_Response( $result, 200 );
    }

    // ── Schema ────────────────────────────────────────────────────────────

    public static function generate_schema( $request ) {
        $post_id = intval( $request->get_param( 'post_id' ) );
        if ( ! $post_id ) {
            return new WP_Error( 'missing_param', 'post_id required.', array( 'status' => 400 ) );
        }
        $_POST['post_id'] = $post_id;
        $_POST['nonce']   = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Schema_Intelligence::ajax_generate_schema();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    public static function apply_schema( $request ) {
        $post_id = intval( $request->get_param( 'post_id' ) );
        if ( ! $post_id ) {
            return new WP_Error( 'missing_param', 'post_id required.', array( 'status' => 400 ) );
        }
        $_POST['post_id'] = $post_id;
        $_POST['nonce']   = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Schema_Intelligence::ajax_apply_schema();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    public static function get_schema_status( $request ) {
        $_REQUEST['nonce'] = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Schema_Intelligence::ajax_get_all_schema_status();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    public static function bulk_apply_schema( $request ) {
        $post_id = intval( $request->get_param( 'post_id' ) );
        if ( ! $post_id ) {
            return new WP_Error( 'missing_param', 'post_id required.', array( 'status' => 400 ) );
        }
        $_POST['post_id'] = $post_id;
        $_POST['nonce']   = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_bulk_apply_schema();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    public static function get_schema_graph( $request ) {
        $_REQUEST['nonce'] = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        $plugin = Siloq_Connector::get_instance();
        if ( method_exists( $plugin, 'ajax_get_schema_graph' ) ) {
            $plugin->ajax_get_schema_graph();
        }
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    public static function repair_schema( $request ) {
        $_POST['nonce'] = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_repair_elementor_meta();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    // ── Pages ─────────────────────────────────────────────────────────────

    public static function get_pages_list( $request ) {
        $_REQUEST['nonce']  = wp_create_nonce( 'siloq_ajax_nonce' );
        $_REQUEST['offset'] = intval( $request->get_param( 'offset' ) );
        $_REQUEST['filter'] = sanitize_key( $request->get_param( 'filter' ) ?? 'all' );
        ob_start();
        $plugin = Siloq_Connector::get_instance();
        if ( method_exists( $plugin, 'ajax_get_pages_list' ) ) {
            $plugin->ajax_get_pages_list();
        }
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    public static function set_page_role( $request ) {
        $params = $request->get_json_params();
        $_POST['page_id'] = sanitize_text_field( $params['page_id'] ?? '' );
        $_POST['role']    = sanitize_key( $params['role'] ?? '' );
        $_POST['nonce']   = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        $plugin = Siloq_Connector::get_instance();
        if ( method_exists( $plugin, 'ajax_set_page_role' ) ) {
            $plugin->ajax_set_page_role();
        }
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    public static function create_draft( $request ) {
        $params    = $request->get_json_params();
        $title     = sanitize_text_field( $params['title'] ?? '' );
        $post_type = sanitize_key( $params['draft_type'] ?? 'post' );
        $parent_id = intval( $params['parent_id'] ?? 0 );

        if ( ! $title ) {
            return new WP_Error( 'missing_title', 'title required.', array( 'status' => 400 ) );
        }

        $post_id = wp_insert_post( array(
            'post_title'  => $title,
            'post_status' => 'draft',
            'post_type'   => 'post',
            'post_author' => get_current_user_id(),
            'post_parent' => $post_type === 'sub_page' ? $parent_id : 0,
            'meta_input'  => array(
                '_siloq_hub_page_id' => $parent_id,
                '_siloq_generated'   => true,
            ),
        ) );

        if ( is_wp_error( $post_id ) ) {
            return new WP_Error( 'insert_failed', $post_id->get_error_message(), array( 'status' => 500 ) );
        }

        $edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
        return new WP_REST_Response( array( 'post_id' => $post_id, 'edit_url' => $edit_url, 'success' => true ), 201 );
    }

    // ── SEO Plan ──────────────────────────────────────────────────────────

    public static function get_plan_data( $request ) {
        $_REQUEST['nonce'] = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        $plugin = Siloq_Connector::get_instance();
        if ( method_exists( $plugin, 'ajax_get_plan_data' ) ) {
            $plugin->ajax_get_plan_data();
        }
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    // ── Intelligence ──────────────────────────────────────────────────────

    public static function generate_intelligence( $request ) {
        $_POST['nonce'] = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_generate_intelligence();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    // ── Quick Win ─────────────────────────────────────────────────────────

    public static function save_quick_win( $request ) {
        $params = $request->get_json_params();
        $_POST['post_id']    = sanitize_text_field( $params['post_id'] ?? '' );
        $_POST['issue_type'] = sanitize_key( $params['issue_type'] ?? '' );
        $_POST['checked']    = intval( $params['checked'] ?? 0 );
        $_POST['nonce']      = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_save_quick_win();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    // ── Dashboard Fix ─────────────────────────────────────────────────────

    public static function dashboard_fix( $request ) {
        $params = $request->get_json_params();
        $_POST['post_id']      = intval( $params['post_id'] ?? 0 );
        $_POST['fix_action']   = sanitize_key( $params['fix_action'] ?? '' );
        $_POST['fix_type']     = sanitize_key( $params['fix_type'] ?? '' );
        $_POST['custom_value'] = sanitize_text_field( $params['custom_value'] ?? '' );
        $_POST['nonce']        = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_dashboard_fix();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    // ── Fix All SEO ───────────────────────────────────────────────────────

    public static function fix_all_seo( $request ) {
        $params = $request->get_json_params();
        $_POST['post_id'] = intval( $params['post_id'] ?? 0 );
        $_POST['nonce']   = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_fix_all_seo();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    // ── Meta Suggestion ───────────────────────────────────────────────────

    public static function generate_meta_suggestion( $request ) {
        $params = $request->get_json_params();
        $_POST['post_id'] = intval( $params['post_id'] ?? 0 );
        $_POST['field']   = sanitize_key( $params['field'] ?? '' );
        $_POST['use_ai']  = intval( $params['use_ai'] ?? 0 );
        $_POST['nonce']   = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_generate_meta_suggestion();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    // ── Roadmap Progress ──────────────────────────────────────────────────

    public static function save_roadmap_progress( $request ) {
        $params = $request->get_json_params();
        $_POST['key']     = sanitize_key( $params['key'] ?? '' );
        $_POST['checked'] = intval( $params['checked'] ?? 0 );
        $_POST['nonce']   = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        $plugin = Siloq_Connector::get_instance();
        if ( method_exists( $plugin, 'ajax_save_roadmap_progress' ) ) {
            $plugin->ajax_save_roadmap_progress();
        }
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    // ── Sync All Pages ────────────────────────────────────────────────────

    public static function sync_all_pages( $request ) {
        $params = $request->get_json_params() ?: array();
        $_POST['offset'] = intval( $params['offset'] ?? $request->get_param( 'offset' ) ?? 0 );
        $_POST['nonce']  = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        $plugin = Siloq_Connector::get_instance();
        if ( method_exists( $plugin, 'ajax_sync_all_pages' ) ) {
            $plugin->ajax_sync_all_pages();
        }
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? array( 'success' => true ), 200 );
    }

    // ── Content Pipeline ─────────────────────────────────────────────────

    public static function approve_content_job( $request ) {
        $params = $request->get_json_params();
        $_POST['job_id'] = intval( $params['job_id'] ?? 0 );
        $_POST['nonce']  = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_approve_content_job();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    public static function generate_content_plan( $request ) {
        $_POST['nonce'] = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_generate_content_plan();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    public static function publish_draft( $request ) {
        $params = $request->get_json_params();
        $_POST['post_id'] = intval( $params['post_id'] ?? 0 );
        $_POST['nonce']   = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_publish_draft();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    // ── Redirects ─────────────────────────────────────────────────────────

    public static function get_redirects( $request ) {
        $_REQUEST['nonce'] = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_get_redirects();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    public static function add_redirect( $request ) {
        $params = $request->get_json_params();
        $_POST['from']        = sanitize_text_field( $params['from'] ?? '' );
        $_POST['to']          = sanitize_text_field( $params['to'] ?? '' );
        $_POST['status_code'] = intval( $params['status_code'] ?? 301 );
        $_POST['nonce']       = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_add_redirect();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    public static function toggle_redirect( $request ) {
        $params = $request->get_json_params();
        $_POST['redirect_id'] = intval( $params['redirect_id'] ?? 0 );
        $_POST['nonce']       = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_toggle_redirect();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }

    public static function delete_redirect( $request ) {
        $params = $request->get_json_params();
        $_POST['redirect_id'] = intval( $params['redirect_id'] ?? 0 );
        $_POST['nonce']       = wp_create_nonce( 'siloq_ajax_nonce' );
        ob_start();
        Siloq_Admin::ajax_delete_redirect();
        $output = ob_get_clean();
        $data = json_decode( $output, true );
        return new WP_REST_Response( $data['data'] ?? $data, 200 );
    }
}

Siloq_REST_API::init();
