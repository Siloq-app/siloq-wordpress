<?php
/**
 * Siloq WPBakery Intelligence
 *
 * Enqueues Widget Intelligence assets for WPBakery Page Builder (both
 * backend and frontend editor contexts).
 *
 * @package Siloq
 * @since   1.5.58
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Wpbakery_Intelligence {

    public static function init() {
        $instance = new self();
        add_action( 'vc_backend_editor_enqueue_js_css',  [ $instance, 'enqueue_assets' ] );
        add_action( 'vc_frontend_editor_enqueue_js_css', [ $instance, 'enqueue_assets' ] );
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
            'siloq-wpbakery-intelligence',
            $url . 'assets/js/siloq-wpbakery-intelligence.js',
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
            'siloq-wpbakery-intelligence',
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
