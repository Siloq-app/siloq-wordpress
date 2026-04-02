<?php
/**
 * Siloq Case Study — REST endpoints for snapshot tracking and case study generation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Case_Study {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        $ns = 'siloq/v1';

        register_rest_route( $ns, '/snapshots', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_snapshots' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        register_rest_route( $ns, '/snapshots/capture', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'capture_snapshot' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        register_rest_route( $ns, '/snapshots/events', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_events' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        register_rest_route( $ns, '/jobs/case-study', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'start_case_study_job' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );

        register_rest_route( $ns, '/jobs/(?P<job_id>[\w-]+)/status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_job_status' ),
            'permission_callback' => array( __CLASS__, 'require_edit_posts' ),
        ) );
    }

    public static function require_edit_posts() {
        return current_user_can( 'edit_posts' );
    }

    public static function get_snapshots( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            return new WP_REST_Response( array( 'snapshots' => array(), 'has_baseline' => false ), 200 );
        }
        $api    = new Siloq_API_Client();
        $result = $api->get( '/sites/' . intval( $site_id ) . '/snapshots/' );
        return new WP_REST_Response( $result, 200 );
    }

    public static function capture_snapshot( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            return new WP_Error( 'no_site', 'Site not connected.', array( 'status' => 400 ) );
        }
        $api    = new Siloq_API_Client();
        $result = $api->post( '/sites/' . intval( $site_id ) . '/snapshots/capture/', array() );
        return new WP_REST_Response( $result, 201 );
    }

    public static function get_events( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            return new WP_REST_Response( array( 'events' => array() ), 200 );
        }
        $api    = new Siloq_API_Client();
        $result = $api->get( '/sites/' . intval( $site_id ) . '/snapshots/events/' );
        return new WP_REST_Response( $result, 200 );
    }

    public static function start_case_study_job( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            return new WP_Error( 'no_site', 'Site not connected.', array( 'status' => 400 ) );
        }
        $api    = new Siloq_API_Client();
        $result = $api->post( '/sites/' . intval( $site_id ) . '/jobs/case-study/', array() );
        return new WP_REST_Response( $result, 201 );
    }

    public static function get_job_status( $request ) {
        $site_id = get_option( 'siloq_site_id', '' );
        if ( empty( $site_id ) ) {
            return new WP_Error( 'no_site', 'Site not connected.', array( 'status' => 400 ) );
        }
        $job_id = sanitize_text_field( $request['job_id'] );
        $api    = new Siloq_API_Client();
        $result = $api->get( '/sites/' . intval( $site_id ) . '/jobs/' . $job_id . '/status/' );
        return new WP_REST_Response( $result, 200 );
    }
}

Siloq_Case_Study::init();
