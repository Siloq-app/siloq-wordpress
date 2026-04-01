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

        // Also enqueue content editor JS
        wp_enqueue_script(
            'siloq-content-editor',
            $plugin_url . 'assets/js/siloq-floating-panel.js',
            [ 'jquery', 'siloq-schema' ],
            $version,
            true
        );
        wp_localize_script( 'siloq-content-editor', 'siloqContentEditor', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'siloq_ajax_nonce' ),
            'postId'   => $post_id,
            'apiBase'  => defined('SILOQ_API_BASE') ? SILOQ_API_BASE : 'https://api.siloq.app',
            'siteId'   => get_option('siloq_site_id', ''),
            'apiKey'   => get_option('siloq_api_key', ''),
        ] );

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
            <img src="<?php echo esc_url( SILOQ_PLUGIN_URL . 'assets/images/siloq-logo-icon.webp' ); ?>" alt="Siloq" style="width:18px;height:18px;object-fit:contain;vertical-align:middle;" aria-hidden="true">
            <span class="siloq-schema-el-trigger-label">Schema</span>
        </div>

        <?php /* Slide-out panel — populated by siloq-schema.js */ ?>
        <div id="siloq-schema-el-panel"
             class="siloq-schema-surface siloq-schema-el-panel"
             data-post-id="<?php echo esc_attr( $post_id ); ?>"
             aria-hidden="true">

            <div class="siloq-schema-el-panel-header">
                <div class="siloq-ep-tabs" style="display:flex;border-bottom:1px solid #e5e7eb;margin:-0px;padding:0 12px;">
                    <button class="siloq-ep-tab siloq-ep-tab--active" data-siloq-tab="schema" style="padding:10px 12px 8px;font-size:12px;font-weight:600;border:none;background:none;cursor:pointer;border-bottom:2px solid #D39938;color:#D39938;">⚡ Schema</button>
                    <button class="siloq-ep-tab" data-siloq-tab="edit-content" style="padding:10px 12px 8px;font-size:12px;font-weight:600;border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;color:#6b7280;">✏️ Edit Content</button>
                    <!-- Links tab removed: link map is now in the Siloq Intelligence left panel (⚡ Analyze section) -->
                </div>
                <button type="button"
                        id="siloq-schema-el-close"
                        class="siloq-schema-el-close"
                        aria-label="Close Siloq panel" style="position:absolute;top:8px;right:10px;background:none;border:none;font-size:18px;cursor:pointer;color:#6b7280;">✕</button>
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

                <?php
                $test_url = 'https://search.google.com/test/rich-results?url=' . urlencode(get_permalink($post_id));
                echo '<a href="' . esc_url($test_url) . '" target="_blank" rel="noopener noreferrer" style="display:block;margin-top:8px;font-size:11px;color:#D39938;font-weight:500;">🔍 Test with Google →</a>';
                ?>

            </div><!-- /#siloq-schema-preview-el -->

            <!-- Edit Content Tab -->
            <div id="siloq-edit-content-tab" class="siloq-ep-tab-panel" style="display:none;padding:12px;">
                <p style="font-size:12px;color:#6b7280;margin:0 0 12px;">Load all text widgets on this page. Get AI-suggested improvements, then apply directly in Elementor.</p>

                <button type="button" id="siloq-load-widgets-btn"
                        style="width:100%;padding:8px;background:#D39938;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;margin-bottom:12px;">
                    📋 Load Page Content
                </button>

                <div id="siloq-widget-loading" style="display:none;text-align:center;padding:20px;color:#6b7280;font-size:12px;">
                    <span class="spinner is-active" style="float:none;margin:0 auto 8px;display:block;"></span>
                    Loading widgets...
                </div>

                <div id="siloq-widget-list"></div>

                <div id="siloq-ec-status" style="display:none;padding:8px;border-radius:6px;font-size:12px;margin-top:8px;"></div>
            </div>

            <!-- Internal Links Tab -->
            <div id="siloq-links-tab" class="siloq-ep-tab-panel" style="display:none;padding:12px;">
                <p style="font-size:12px;color:#6b7280;margin:0 0 12px;">View your site's internal linking structure. See which pages should link to and from this page.</p>

                <button type="button" id="siloq-load-links-btn"
                        style="width:100%;padding:8px;background:#D39938;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;margin-bottom:12px;">
                    🔗 Load Link Map
                </button>

                <div id="siloq-links-loading" style="display:none;text-align:center;padding:20px;color:#6b7280;font-size:12px;">
                    <span class="spinner is-active" style="float:none;margin:0 auto 8px;display:block;"></span>
                    Loading link data...
                </div>

                <div id="siloq-links-content"></div>

                <div id="siloq-links-status" style="display:none;padding:8px;border-radius:6px;font-size:12px;margin-top:8px;"></div>
            </div>

        </div><!-- /#siloq-schema-el-panel -->
        <?php
    }
}
