<?php
/**
 * Siloq Beaver Builder Panel
 *
 * Injects the shared Siloq floating panel when Beaver Builder's frontend
 * editor is active.
 *
 * @package Siloq_Connector
 * @since   1.5.50
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Beaver_Panel {

    /**
     * Register WordPress hooks.
     */
    public static function init() {
        // fl_builder_before_enqueue_scripts fires just before Beaver Builder
        // enqueues its own scripts — a reliable place to add ours.
        add_action( 'fl_builder_before_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        // Inject init call in footer
        add_action( 'wp_footer', array( __CLASS__, 'inject_init' ) );
    }

    /**
     * Enqueue shared floating panel assets inside the Beaver Builder context.
     */
    public static function enqueue_assets() {
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
     * Output SiloqFloatingPanel.init() in the footer.
     *
     * The JavaScript also detects document.body having the class
     * 'fl-builder-active' before initializing, providing an extra
     * guard against running on non-Beaver-builder pages.
     */
    public static function inject_init() {
        if ( ! class_exists( 'FLBuilder' ) ) {
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
                // Only initialise inside the Beaver Builder frontend editor
                if (document.body.classList.contains('fl-builder-active') && typeof SiloqFloatingPanel !== 'undefined') {
                    SiloqFloatingPanel.init(<?php echo intval( $post_id ); ?>, '<?php echo $ajax_url; ?>', '<?php echo $nonce; ?>');
                }
            });
        }(jQuery));
        </script>
        <?php
    }
}
