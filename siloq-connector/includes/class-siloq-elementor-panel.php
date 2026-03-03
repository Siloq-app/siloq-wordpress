<?php
/**
 * Siloq Elementor Panel — Schema Intelligence Tab
 *
 * Appends a "Schema" tab to the Siloq floating panel inside the Elementor
 * editor. Shares the same AJAX actions and JS module (siloq-schema.js) as
 * the Admin Metabox — no duplicated logic.
 *
 * The panel is injected as a floating sidebar button + slide-out panel
 * rendered client-side by siloq-schema.js when surface='elementor'.
 *
 * UI flow (identical to metabox):
 *  1. User opens Elementor editor.
 *  2. Clicks "⚡ Siloq Schema" button in the top bar.
 *  3. Panel slides in; shows current schema status.
 *  4. User clicks "⚡ Generate Schema" → AJAX generates candidates.
 *  5. User reviews JSON-LD preview and clicks "✅ Apply Schema".
 *
 * Output path: wp_head ONLY (via Siloq_Schema_Architect).
 * NEVER writes to _elementor_data or post_content.
 *
 * @package Siloq
 * @since   1.5.49
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Elementor_Panel {

    // ── Bootstrap ────────────────────────────────────────────────────────────

    public static function init() {
        // Elementor editor assets (fires inside the editor iframe).
        add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'enqueue_editor_assets' ] );

        // Inject the panel HTML container into the editor.
        add_action( 'elementor/editor/footer',                [ __CLASS__, 'render_panel_container' ] );
    }

    // ── Asset Enqueuing ───────────────────────────────────────────────────────

    public static function enqueue_editor_assets() {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return;
        }

        $plugin_url = defined( 'SILOQ_PLUGIN_URL' ) ? SILOQ_PLUGIN_URL : plugin_dir_url( dirname( __FILE__ ) );
        $version    = defined( 'SILOQ_VERSION' ) ? SILOQ_VERSION : '1.5.49';

        wp_enqueue_style(
            'siloq-schema',
            $plugin_url . 'assets/css/siloq-schema.css',
            [],
            $version
        );

        wp_enqueue_script(
            'siloq-schema',
            $plugin_url . 'assets/js/siloq-schema.js',
            [ 'jquery' ],
            $version,
            true
        );

        // Resolve post ID from Elementor's document store.
        $post_id = 0;
        if ( isset( $_GET['post'] ) ) {
            $post_id = intval( $_GET['post'] );
        }

        wp_localize_script( 'siloq-schema', 'siloqSchema', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'siloq_ajax_nonce' ),
            'postId'   => $post_id,
            'surface'  => 'elementor',
            'strings'  => [
                'generating'   => 'Generating schema…',
                'applying'     => 'Applying schema…',
                'applySuccess' => 'Schema applied to wp_head.',
                'applyFail'    => 'Apply failed — see errors below.',
                'noSchema'     => 'No schema generated yet.',
                'validating'   => 'Validating…',
            ],
        ] );
    }

    // ── Panel HTML ────────────────────────────────────────────────────────────

    /**
     * Render the empty panel container + trigger button into the Elementor footer.
     * The JS module (siloq-schema.js) mounts UI into #siloq-schema-el-panel.
     */
    public static function render_panel_container() {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return;
        }

        $post_id = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0;
        ?>
        <?php /* Floating trigger button — positioned by CSS */ ?>
        <div id="siloq-schema-el-trigger"
             class="siloq-schema-el-trigger"
             title="Siloq Schema Intelligence"
             role="button"
             tabindex="0"
             aria-controls="siloq-schema-el-panel"
             aria-expanded="false">
            <span aria-hidden="true">⚡</span>
            <span class="siloq-schema-el-trigger-label">Schema</span>
        </div>

        <?php /* Slide-out panel — populated by siloq-schema.js */ ?>
        <div id="siloq-schema-el-panel"
             class="siloq-schema-surface siloq-schema-el-panel"
             data-post-id="<?php echo esc_attr( $post_id ); ?>"
             aria-hidden="true">

            <div class="siloq-schema-el-panel-header">
                <span class="siloq-schema-el-panel-title">⚡ Siloq Schema</span>
                <button type="button"
                        id="siloq-schema-el-close"
                        class="siloq-schema-el-close"
                        aria-label="Close Siloq Schema panel">✕</button>
            </div>

            <?php /* Status, generate, preview, apply — identical structure to metabox */ ?>
            <div id="siloq-schema-el-status" class="siloq-schema-status">
                <span class="siloq-schema-badge siloq-schema-badge--none">— No schema applied</span>
            </div>

            <div class="siloq-schema-actions">
                <button type="button"
                        id="siloq-generate-schema-btn-el"
                        class="button siloq-schema-btn siloq-schema-btn--generate"
                        data-post-id="<?php echo esc_attr( $post_id ); ?>">
                    ⚡ Generate Schema
                </button>
            </div>

            <div id="siloq-schema-spinner-el" class="siloq-schema-spinner" style="display:none;">
                <span class="spinner is-active"></span>
                <span class="siloq-schema-spinner-label">Generating…</span>
            </div>

            <div id="siloq-schema-errors-el" class="siloq-schema-errors" style="display:none;"></div>

            <div id="siloq-schema-preview-el" class="siloq-schema-preview" style="display:none;">

                <div class="siloq-schema-meta-row">
                    <span class="siloq-schema-meta-label">Page type:</span>
                    <span id="siloq-schema-page-type-el" class="siloq-schema-meta-value"></span>
                </div>
                <div class="siloq-schema-meta-row">
                    <span class="siloq-schema-meta-label">Business type:</span>
                    <span id="siloq-schema-business-type-el" class="siloq-schema-meta-value"></span>
                </div>

                <div class="siloq-schema-types-header">Schema types:</div>
                <div id="siloq-schema-types-list-el" class="siloq-schema-types-list"></div>

                <details class="siloq-schema-json-details">
                    <summary>View JSON-LD</summary>
                    <pre id="siloq-schema-json-preview-el" class="siloq-schema-json-preview"></pre>
                </details>

                <div id="siloq-schema-validation-warnings-el"
                     class="siloq-schema-validation-warnings"
                     style="display:none;"></div>

                <button type="button"
                        id="siloq-apply-schema-btn-el"
                        class="button button-primary siloq-schema-btn siloq-schema-btn--apply"
                        data-post-id="<?php echo esc_attr( $post_id ); ?>">
                    ✅ Apply Schema
                </button>

            </div><!-- /#siloq-schema-preview-el -->

        </div><!-- /#siloq-schema-el-panel -->
        <?php
    }
}
