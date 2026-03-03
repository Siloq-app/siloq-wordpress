<?php
/**
 * Siloq Oxygen Builder Panel
 *
 * Injects the shared Siloq floating panel when the Oxygen visual builder
 * is active (CT_VERSION defined + ?ct_builder in the URL).
 *
 * @package Siloq_Connector
 * @since   1.5.50
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Oxygen_Panel {

    /**
     * Register WordPress hooks.
     */
    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_footer',          array( __CLASS__, 'inject_init' ) );
    }

    /**
     * Enqueue shared floating panel assets inside the Oxygen builder context.
     */
    public static function enqueue_assets() {
        if ( ! self::_is_oxygen_builder() ) {
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
     * Output SiloqFloatingPanel.init() in the page footer.
     */
    public static function inject_init() {
        if ( ! self::_is_oxygen_builder() ) {
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

    private static function _is_oxygen_builder() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return defined( 'CT_VERSION' ) && isset( $_GET['ct_builder'] );
    }
}
