<?php
/**
 * Siloq WPBakery Panel
 *
 * WPBakery has two editing modes:
 *
 * 1. Backend editor  — This is a standard WordPress admin page, so the
 *    regular Siloq_Admin_Metabox already provides SEO functionality there.
 *    No extra code needed here for that mode.
 *
 * 2. Frontend editor — WPBakery appends ?vc_action=vc_inline to the public
 *    page URL when the user clicks "Frontend Editor". The page is still
 *    served in the browser but with WPBakery's editor overlay loaded. The
 *    standard admin metabox is NOT available. We inject the shared floating
 *    panel so the user retains Siloq SEO access.
 *
 * @package Siloq_Connector
 * @since   1.5.50
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_WPBakery_Panel {

    /**
     * Register WordPress hooks.
     */
    public static function init() {
        // Only hook on frontend editor requests
        add_action( 'wp_footer', array( __CLASS__, 'maybe_inject' ) );
    }

    /**
     * Enqueue assets and inject the init call when the WPBakery frontend
     * editor is detected via the vc_action URL parameter.
     */
    public static function maybe_inject() {
        if ( ! self::_is_vc_frontend() ) {
            return;
        }

        global $post;
        if ( ! $post ) {
            return;
        }

        // Enqueue inline (late) — enqueue_scripts has already fired, so we
        // output style/script tags directly.
        $css_url = esc_url( SILOQ_PLUGIN_URL . 'assets/css/siloq-floating-panel.css' );
        $js_url  = esc_url( SILOQ_PLUGIN_URL . 'assets/js/siloq-floating-panel.js' );

        $post_id  = $post->ID;
        $ajax_url = esc_js( admin_url( 'admin-ajax.php' ) );
        $nonce    = esc_js( wp_create_nonce( 'siloq_ai_nonce' ) );

        ?>
        <link rel="stylesheet" id="siloq-floating-panel-css" href="<?php echo $css_url; ?>?ver=<?php echo esc_attr( SILOQ_VERSION ); ?>">
        <script type="text/javascript" src="<?php echo $js_url; ?>?ver=<?php echo esc_attr( SILOQ_VERSION ); ?>"></script>
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
     * Detect the WPBakery frontend editor (vc_inline) request.
     *
     * WPBakery 6+ adds ?vc_action=vc_inline to the page URL when the user
     * activates the frontend editor from the post list.
     */
    private static function _is_vc_frontend() {
        if ( ! class_exists( 'Vc_Manager' ) ) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset( $_GET['vc_action'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['vc_action'] ) ), 'vc_inline' ) !== false;
    }
}
