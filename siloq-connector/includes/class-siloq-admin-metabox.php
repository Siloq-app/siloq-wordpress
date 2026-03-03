<?php
/**
 * Siloq Admin Metabox — Schema Intelligence Section
 *
 * Adds a "Schema Markup" meta box to the post/page editor with the
 * Generate → Preview → Apply workflow. No schema is ever auto-applied.
 *
 * UI flow:
 *  1. User opens the post/page editor.
 *  2. Meta box shows current schema status (applied types or "None applied").
 *  3. User clicks "⚡ Generate Schema" → AJAX generates candidates.
 *  4. Preview panel shows detected page type, business type, and JSON-LD.
 *  5. User reviews and clicks "✅ Apply Schema" → AJAX validates + applies.
 *
 * Architecture note:
 *  All business logic lives in Siloq_Schema_Intelligence. This class is
 *  UI-only — it renders HTML and enqueues the shared siloq-schema.js asset.
 *
 * @package Siloq
 * @since   1.5.49
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Admin_Metabox {

    /** Post types that get the Schema meta box. */
    const SUPPORTED_POST_TYPES = [ 'page', 'post' ];

    // ── Bootstrap ────────────────────────────────────────────────────────────

    public static function init() {
        add_action( 'add_meta_boxes',          [ __CLASS__, 'register_meta_box' ] );
        add_action( 'admin_enqueue_scripts',   [ __CLASS__, 'enqueue_assets' ]   );
    }

    // ── Meta Box Registration ─────────────────────────────────────────────────

    public static function register_meta_box() {
        foreach ( self::SUPPORTED_POST_TYPES as $post_type ) {
            add_meta_box(
                'siloq-schema-intelligence',
                '⚡ Siloq Schema Intelligence',
                [ __CLASS__, 'render_meta_box' ],
                $post_type,
                'side',
                'default'
            );
        }
    }

    // ── Asset Enqueuing ───────────────────────────────────────────────────────

    public static function enqueue_assets( $hook_suffix ) {
        // Only load on post/page edit screens.
        if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->post_type, self::SUPPORTED_POST_TYPES, true ) ) {
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

        global $post;
        wp_localize_script( 'siloq-schema', 'siloqSchema', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'siloq_ajax_nonce' ),
            'postId'   => $post ? $post->ID : 0,
            'surface'  => 'metabox',
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

    // ── Meta Box Renderer ─────────────────────────────────────────────────────

    public static function render_meta_box( $post ) {
        $post_id       = $post->ID;
        $applied_types = get_post_meta( $post_id, '_siloq_applied_types', true );
        $applied_at    = get_post_meta( $post_id, '_siloq_schema_applied', true );
        $has_staged    = ! empty( get_post_meta( $post_id, '_siloq_suggested_schema', true ) );

        $applied_list  = '';
        if ( ! empty( $applied_types ) ) {
            $types = json_decode( $applied_types, true );
            if ( is_array( $types ) && ! empty( $types ) ) {
                $applied_list = implode( ', ', array_map( 'esc_html', $types ) );
            }
        }
        ?>
        <div id="siloq-schema-metabox" class="siloq-schema-surface" data-post-id="<?php echo esc_attr( $post_id ); ?>">

            <?php /* ── Current status ── */ ?>
            <div class="siloq-schema-status">
                <?php if ( $applied_list ) : ?>
                    <span class="siloq-schema-badge siloq-schema-badge--applied">✅ Applied</span>
                    <div class="siloq-schema-applied-types">
                        <?php echo esc_html( $applied_list ); ?>
                    </div>
                    <?php if ( $applied_at ) : ?>
                        <div class="siloq-schema-applied-time">
                            <?php /* translators: %s = date/time string */ ?>
                            <?php printf( esc_html__( 'Last applied: %s', 'siloq-connector' ), esc_html( $applied_at ) ); ?>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <span class="siloq-schema-badge siloq-schema-badge--none">— No schema applied</span>
                <?php endif; ?>
            </div>

            <?php /* ── Generate button ── */ ?>
            <div class="siloq-schema-actions">
                <button type="button"
                        id="siloq-generate-schema-btn"
                        class="button siloq-schema-btn siloq-schema-btn--generate"
                        data-post-id="<?php echo esc_attr( $post_id ); ?>">
                    ⚡ Generate Schema
                </button>
            </div>

            <?php /* ── Spinner ── */ ?>
            <div id="siloq-schema-spinner" class="siloq-schema-spinner" style="display:none;">
                <span class="spinner is-active"></span>
                <span class="siloq-schema-spinner-label">Generating…</span>
            </div>

            <?php /* ── Errors ── */ ?>
            <div id="siloq-schema-errors" class="siloq-schema-errors" style="display:none;"></div>

            <?php /* ── Preview panel (hidden until generated) ── */ ?>
            <div id="siloq-schema-preview" class="siloq-schema-preview" style="display:none;">

                <div class="siloq-schema-meta-row">
                    <span class="siloq-schema-meta-label">Page type:</span>
                    <span id="siloq-schema-page-type" class="siloq-schema-meta-value"></span>
                </div>
                <div class="siloq-schema-meta-row">
                    <span class="siloq-schema-meta-label">Business type:</span>
                    <span id="siloq-schema-business-type" class="siloq-schema-meta-value"></span>
                </div>

                <div class="siloq-schema-types-header">Schema types:</div>
                <div id="siloq-schema-types-list" class="siloq-schema-types-list"></div>

                <?php /* JSON-LD preview — collapsed by default */ ?>
                <details class="siloq-schema-json-details">
                    <summary>View JSON-LD</summary>
                    <pre id="siloq-schema-json-preview" class="siloq-schema-json-preview"></pre>
                </details>

                <?php /* Validation warnings (non-blocking) */ ?>
                <div id="siloq-schema-validation-warnings" class="siloq-schema-validation-warnings" style="display:none;"></div>

                <button type="button"
                        id="siloq-apply-schema-btn"
                        class="button button-primary siloq-schema-btn siloq-schema-btn--apply"
                        data-post-id="<?php echo esc_attr( $post_id ); ?>">
                    ✅ Apply Schema
                </button>

            </div><!-- /#siloq-schema-preview -->

        </div><!-- /#siloq-schema-metabox -->
        <?php
    }
}
