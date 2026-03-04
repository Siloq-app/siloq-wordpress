<?php
/**
 * Siloq Classic Editor Intelligence
 *
 * Enqueues Widget Intelligence assets on Classic Editor post editing screens.
 *
 * @package Siloq
 * @since   1.5.58
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Classic_Intelligence {

    public static function init() {
        $instance = new self();
        add_action( 'admin_enqueue_scripts', [ $instance, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || $screen->base !== 'post' ) {
            return;
        }

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
            'siloq-classic-intelligence',
            $url . 'assets/js/siloq-classic-intelligence.js',
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
            'siloq-classic-intelligence',
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
