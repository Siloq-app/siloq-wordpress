<?php
/**
 * Siloq Divi Panel
 *
 * Injects the shared Siloq floating panel into pages being edited with Divi.
 * Assets are enqueued via Divi's own asset hook so they load inside the
 * Divi builder context.
 *
 * @package Siloq_Connector
 * @since   1.5.50
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Divi_Panel {

    /**
     * Register WordPress hooks.
     */
    public static function init() {
        // et_builder_enqueue_assets fires inside the Divi builder
        add_action( 'et_builder_enqueue_assets', array( __CLASS__, 'enqueue_assets' ) );

        // Inject init call in footer
        add_action( 'wp_footer', array( __CLASS__, 'inject_init' ) );
    }

    /**
     * Enqueue shared floating panel assets when Divi builder is active.
     */
    public static function enqueue_assets() {
        if ( ! self::_is_divi_active() ) {
            return;
        }

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
    }

    /**
     * Output the SiloqFloatingPanel.init() call in the footer.
     */
    public static function inject_init() {
        if ( ! self::_is_divi_active() ) {
            return;
        }

        global $post;
        if ( ! $post ) {
            return;
        }

        $post_id  = $post->ID;
        $ajax_url = esc_js( admin_url( 'admin-ajax.php' ) );
        $nonce    = esc_js( wp_create_nonce( 'siloq_ai_nonce' ) );

        ?>
        <script type="text/javascript">
        (function($){
            $(document).ready(function(){
                if (typeof SiloqFloatingPanel !== 'undefined') {
                    SiloqFloatingPanel.init(<?php echo intval( $post_id ); ?>, '<?php echo $ajax_url; ?>', '<?php echo $nonce; ?>');
                }
            });
        }(jQuery));
        </script>
        <?php
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    /**
     * Return true when the Divi builder is active in the current request.
     * Covers the Divi frontend builder (et_fb_is_enabled) and the standard
     * page builder (et_pb_ shortcodes).
     */
    private static function _is_divi_active() {
        if ( ! class_exists( 'ET_Builder_Plugin' ) && ! defined( 'ET_BUILDER_PLUGIN_DIR' ) ) {
            return false;
        }

        // et_fb_is_enabled() is available when the front-end builder is loading
        if ( function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled() ) {
            return true;
        }

        // Fallback: check for Divi builder URL param
        if ( isset( $_GET['et_fb'] ) ) {
            return true;
        }

        return false;
    }
}
