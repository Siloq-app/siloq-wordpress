<?php
/**
 * Siloq Beaver Builder Intelligence
 *
 * Enqueues Widget Intelligence assets for Beaver Builder editor context.
 *
 * @package Siloq
 * @since   1.5.58
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Beaver_Intelligence {

    public static function init() {
        $instance = new self();
        // fl_builder_ui_enqueue_scripts fires inside the Beaver Builder UI
        add_action( 'fl_builder_ui_enqueue_scripts', [ $instance, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        $url = SILOQ_PLUGIN_URL;
        $ver = SILOQ_VERSION;

        wp_enqueue_script(
            'siloq-intelligence-core',
            $url . 'assets/js/siloq-intelligence-core.js',
            [ 'jquery' ],
            $ver,
            true
        );

        wp_enqueue_script(
            'siloq-beaver-intelligence',
            $url . 'assets/js/siloq-beaver-intelligence.js',
            [ 'jquery', 'siloq-intelligence-core' ],
            $ver,
            true
        );

        wp_enqueue_style(
            'siloq-wi-css',
            $url . 'assets/css/siloq-widget-intelligence.css',
            [],
            $ver
        );

        wp_localize_script(
            'siloq-beaver-intelligence',
            'siloqIntelligenceCore',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'siloq_ajax_nonce' ),
                'postId'  => isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0,
                'siteId'  => get_option( 'siloq_site_id', '' ),
            ]
        );
    }
}
