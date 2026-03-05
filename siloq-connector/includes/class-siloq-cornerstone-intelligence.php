<?php
/**
 * Siloq Cornerstone Intelligence
 *
 * Enqueues Widget Intelligence assets inside the Cornerstone (X Theme)
 * page builder editor context.
 *
 * @package Siloq
 * @since   1.5.58
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Cornerstone_Intelligence {

    public static function init() {
        $instance = new self();
        add_action( 'admin_enqueue_scripts', [ $instance, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        // Cornerstone check: class or GET param
        if (
            ! class_exists( 'Cornerstone_Plugin' ) &&
            ! defined( 'CS_ROOT_URL' ) &&
            ! isset( $_GET['cornerstone'] )
        ) {
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
            'siloq-cornerstone-intelligence',
            $url . 'assets/js/siloq-cornerstone-intelligence.js',
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
            'siloq-cornerstone-intelligence',
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
