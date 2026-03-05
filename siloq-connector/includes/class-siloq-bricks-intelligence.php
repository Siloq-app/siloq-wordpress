<?php
/**
 * Siloq Bricks Intelligence
 *
 * Enqueues Widget Intelligence assets inside the Bricks builder iframe context.
 *
 * @package Siloq
 * @since   1.5.58
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Bricks_Intelligence {

    public static function init() {
        $instance = new self();
        add_action( 'wp_enqueue_scripts', [ $instance, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        // Only load inside Bricks builder iframe
        if ( ! function_exists( 'bricks_is_builder_iframe' ) || ! bricks_is_builder_iframe() ) {
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
            'siloq-bricks-intelligence',
            $url . 'assets/js/siloq-bricks-intelligence.js',
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
            'siloq-bricks-intelligence',
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
