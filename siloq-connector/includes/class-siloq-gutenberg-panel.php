<?php
/**
 * Siloq Gutenberg Panel
 *
 * Registers a PluginSidebar in the WordPress block editor.
 * Also loads the shared floating panel so all three tabs are accessible.
 *
 * Only enqueued when the current post actually uses the block editor.
 *
 * @package Siloq_Connector
 * @since   1.5.50
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Gutenberg_Panel {

    /**
     * Register WordPress hooks.
     */
    public static function init() {
        add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_assets' ) );
    }

    /**
     * Enqueue block editor sidebar script + shared floating panel assets.
     * Runs on enqueue_block_editor_assets — only fires in the block editor context.
     */
    public static function enqueue_assets() {
        global $post;

        // Guard: only proceed when the block editor is actually active for this post.
        if ( ! $post || ! function_exists( 'use_block_editor_for_post' ) ) {
            return;
        }
        if ( ! use_block_editor_for_post( $post ) ) {
            return;
        }

        // --- Shared floating panel (CSS + JS) ---
        wp_enqueue_style(
            'siloq-floating-panel',
            SILOQ_PLUGIN_URL . 'assets/css/siloq-floating-panel.css',
            array(),
            SILOQ_VERSION
        );

        wp_enqueue_script(
            'siloq-floating-panel',
            SILOQ_PLUGIN_URL . 'assets/js/siloq-floating-panel.js',
            array( 'jquery' ),
            SILOQ_VERSION,
            true
        );

        // --- Block editor PluginSidebar ---
        wp_enqueue_script(
            'siloq-gutenberg-panel',
            SILOQ_PLUGIN_URL . 'assets/js/siloq-gutenberg-panel.js',
            array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'jquery', 'siloq-floating-panel' ),
            SILOQ_VERSION,
            true
        );

        wp_localize_script(
            'siloq-gutenberg-panel',
            'siloqGB',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'siloq_ai_nonce' ),
                'postId'  => $post->ID,
            )
        );
    }
}
