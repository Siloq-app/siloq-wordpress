<?php
/**
 * Siloq Builder Detector
 *
 * Single source of truth for detecting which page builder (if any) is active
 * on the current admin post/page edit screen.
 *
 * Detection runs once per request and caches the result in a static property.
 * Nothing else in the plugin should perform builder detection independently.
 *
 * @package Siloq_Connector
 * @since   1.5.50
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Builder_Detector {

    // -----------------------------------------------------------------------
    // Builder constants
    // -----------------------------------------------------------------------

    const BUILDER_ELEMENTOR   = 'elementor';
    const BUILDER_GUTENBERG   = 'gutenberg';
    const BUILDER_DIVI        = 'divi';
    const BUILDER_BEAVER      = 'beaver';
    const BUILDER_WPBAKERY    = 'wpbakery';
    const BUILDER_BRICKS      = 'bricks';
    const BUILDER_OXYGEN      = 'oxygen';
    const BUILDER_CORNERSTONE = 'cornerstone';
    const BUILDER_CLASSIC     = 'classic';

    /**
     * Builders that take over the full browser viewport (no standard WP chrome).
     * These receive the floating panel instead of a sidebar metabox.
     */
    const FULLSCREEN_BUILDERS = [ 'elementor', 'cornerstone', 'oxygen', 'bricks' ];

    // -----------------------------------------------------------------------
    // Cache
    // -----------------------------------------------------------------------

    /**
     * Detected builder for this request (null = not yet checked).
     *
     * @var string|null
     */
    private static $_detected = null;

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Detect which builder is active for the current post/page edit screen.
     *
     * @return string One of the BUILDER_* constants.
     */
    public static function detect() {
        if ( null !== self::$_detected ) {
            return self::$_detected;
        }

        self::$_detected = self::_run_detection();
        return self::$_detected;
    }

    /**
     * Reset the cached result (useful for unit tests).
     */
    public static function reset() {
        self::$_detected = null;
    }

    /**
     * Return true if the detected builder renders full-screen (no WP chrome).
     */
    public static function is_fullscreen() {
        return in_array( self::detect(), self::FULLSCREEN_BUILDERS, true );
    }

    // -----------------------------------------------------------------------
    // Internal detection logic
    // -----------------------------------------------------------------------

    /**
     * Run detection in priority order and return the first matching builder.
     *
     * @return string
     */
    private static function _run_detection() {
        $post_id = self::_current_post_id();

        // 1. Elementor -------------------------------------------------------
        //    - Plugin class must exist (Elementor active)
        //    - Post meta _elementor_edit_mode = 'builder' means this post uses Elementor
        if ( class_exists( 'Elementor\Plugin' ) ) {
            if ( $post_id ) {
                $edit_mode = get_post_meta( $post_id, '_elementor_edit_mode', true );
                if ( 'builder' === $edit_mode ) {
                    return self::BUILDER_ELEMENTOR;
                }
            } else {
                // Plugin is active; conservatively return Elementor when we have no post context.
                return self::BUILDER_ELEMENTOR;
            }
        }

        // 2. Gutenberg / Block Editor ----------------------------------------
        //    function_exists check guards against sites that have disabled the block editor.
        if ( function_exists( 'use_block_editor_for_post' ) && $post_id ) {
            $post = get_post( $post_id );
            if ( $post && use_block_editor_for_post( $post ) ) {
                return self::BUILDER_GUTENBERG;
            }
        }

        // 3. Divi ------------------------------------------------------------
        if ( class_exists( 'ET_Builder_Plugin' ) || defined( 'ET_BUILDER_PLUGIN_DIR' ) ) {
            return self::BUILDER_DIVI;
        }

        // 4. Beaver Builder --------------------------------------------------
        if ( class_exists( 'FLBuilder' ) ) {
            return self::BUILDER_BEAVER;
        }

        // 5. WPBakery --------------------------------------------------------
        if ( class_exists( 'Vc_Manager' ) ) {
            return self::BUILDER_WPBAKERY;
        }

        // 6. Bricks ----------------------------------------------------------
        if ( class_exists( 'Bricks\Elements' ) ) {
            return self::BUILDER_BRICKS;
        }

        // 7. Oxygen ----------------------------------------------------------
        if ( class_exists( 'OxyEl' ) || defined( 'CT_VERSION' ) ) {
            return self::BUILDER_OXYGEN;
        }

        // 8. Cornerstone -----------------------------------------------------
        if ( class_exists( 'Cornerstone' ) || defined( 'CS_PLUGIN_URL' ) ) {
            return self::BUILDER_CORNERSTONE;
        }

        // 9. Classic Editor (fallback) ---------------------------------------
        return self::BUILDER_CLASSIC;
    }

    /**
     * Resolve the post ID being edited from the current request context.
     *
     * @return int|null
     */
    private static function _current_post_id() {
        // Standard post edit screen: ?post=123
        if ( isset( $_GET['post'] ) && intval( $_GET['post'] ) > 0 ) {
            return intval( $_GET['post'] );
        }

        // New post: post.php?action=edit (no ID yet) — return null
        // post-new.php sends ?post_type=... so we rely on global $post
        global $post;
        if ( $post instanceof WP_Post && $post->ID > 0 ) {
            return $post->ID;
        }

        return null;
    }
}
