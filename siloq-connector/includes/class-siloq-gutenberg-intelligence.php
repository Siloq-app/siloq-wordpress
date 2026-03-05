<?php
/**
 * Siloq Gutenberg Intelligence
 *
 * Registers a Gutenberg (block editor) sidebar plugin that injects
 * the Siloq Intelligence panel into the block Inspector Controls
 * for supported block types.
 *
 * @package Siloq
 * @since   1.5.58
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Gutenberg_Intelligence {

    public static function init() {
        $instance = new self();
        add_action( 'enqueue_block_editor_assets', [ $instance, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        if ( ! function_exists( 'register_block_type' ) ) {
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
            'siloq-gutenberg-intelligence',
            $url . 'assets/js/siloq-gutenberg-intelligence.js',
            [ 'wp-plugins', 'wp-edit-post', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-element', 'jquery', 'siloq-intelligence-core' ],
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
            'siloq-gutenberg-intelligence',
            'siloqIntelligenceCore',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'siloq_ajax_nonce' ),
                'postId'  => get_the_ID() ?: ( isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0 ),
                'siteId'  => get_option( 'siloq_site_id', '' ),
            ]
        );
    }
}
