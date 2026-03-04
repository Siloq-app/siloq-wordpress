<?php
/**
 * Siloq Divi Intelligence
 *
 * Enqueues the Divi builder Widget Intelligence assets inside
 * the Divi front-end builder editor context.
 *
 * @package Siloq
 * @since   1.5.58
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Divi_Intelligence {

    public static function init() {
        $instance = new self();
        // et_fb_enqueue_assets fires in Divi front-end builder
        add_action( 'et_fb_enqueue_assets', [ $instance, 'enqueue_assets' ] );
        // Fallback: admin_enqueue_scripts for Divi theme builder / backend
        add_action( 'admin_enqueue_scripts', [ $instance, 'enqueue_admin_assets' ] );
    }

    public function enqueue_assets() {
        $this->do_enqueue();
    }

    public function enqueue_admin_assets() {
        // Only enqueue in Divi theme-builder context
        if ( ! ( defined( 'ET_BUILDER_PLUGIN_DIR' ) || class_exists( 'ET_Builder_Plugin' ) ) ) {
            return;
        }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( $screen && ( strpos( $screen->id, 'et_' ) !== false || strpos( $screen->id, 'divi' ) !== false ) ) {
            $this->do_enqueue();
        }
    }

    private function do_enqueue() {
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
            'siloq-divi-intelligence',
            $url . 'assets/js/siloq-divi-intelligence.js',
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
            'siloq-divi-intelligence',
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
