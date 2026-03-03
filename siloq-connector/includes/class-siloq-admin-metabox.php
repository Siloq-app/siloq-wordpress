<?php
/**
 * Siloq Admin Meta Box
 *
 * Renders the "⚡ Siloq SEO" sidebar panel in the WordPress post/page editor
 * (classic editor AND Gutenberg). Provides connection status, analysis score,
 * quick-apply buttons, top recommendations, supporting content, and builder badge.
 *
 * @package Siloq_Connector
 * @since   1.5.47
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Admin_Metabox {

    /**
     * Register all hooks.
     */
    public static function init() {
        add_action( 'add_meta_boxes',        array( __CLASS__, 'register_meta_box' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        // AJAX handlers
        add_action( 'wp_ajax_siloq_apply_meta',              array( __CLASS__, 'ajax_apply_meta' ) );
        add_action( 'wp_ajax_siloq_apply_schema',            array( __CLASS__, 'ajax_apply_schema' ) );
        add_action( 'wp_ajax_siloq_create_supporting_draft', array( __CLASS__, 'ajax_create_supporting_draft' ) );
        add_action( 'wp_ajax_siloq_metabox_refresh',         array( __CLASS__, 'ajax_metabox_refresh' ) );
    }

    // -------------------------------------------------------------------------
    // Meta box registration
    // -------------------------------------------------------------------------

    /**
     * Register the meta box for all crawlable post types.
     */
    public static function register_meta_box() {
        $post_types = function_exists( 'get_siloq_crawlable_post_types' )
            ? get_siloq_crawlable_post_types()
            : array( 'post', 'page' );

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'siloq-seo-metabox',
                '⚡ Siloq SEO',
                array( __CLASS__, 'render_meta_box' ),
                $post_type,
                'side',
                'high'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Asset enqueueing
    // -------------------------------------------------------------------------

    /**
     * Enqueue JS and CSS only on post-edit screens.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public static function enqueue_assets( $hook_suffix ) {
        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'post' ) {
            return;
        }

        // ---- Inline CSS ----
        wp_register_style( 'siloq-metabox', false, array(), SILOQ_VERSION );
        wp_enqueue_style( 'siloq-metabox' );
        wp_add_inline_style( 'siloq-metabox', self::get_inline_css() );

        // ---- Inline JS ----
        wp_enqueue_script(
            'siloq-metabox',
            plugins_url( 'assets/js/siloq-metabox.js', dirname( __FILE__ ) ),
            array( 'jquery' ),
            SILOQ_VERSION,
            true
        );

        wp_localize_script( 'siloq-metabox', 'siloqAdminData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'siloq_ai_nonce' ),
            'postId'  => get_the_ID(),
        ) );
    }

    /**
     * Return all inline CSS for the meta box.
     *
     * @return string
     */
    private static function get_inline_css() {
        return '
/* ---- Siloq Meta Box ---- */
#siloq-seo-metabox .inside { padding: 0; }

.siloq-mb-section {
    padding: 10px 12px;
    border-bottom: 1px solid #f0f0f0;
}
.siloq-mb-section:last-child { border-bottom: none; }

.siloq-mb-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    color: #666;
    letter-spacing: .05em;
    margin: 0 0 6px;
}

/* Connection strip */
.siloq-mb-status {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
}
.siloq-mb-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.siloq-mb-dot.connected    { background: #46b450; }
.siloq-mb-dot.disconnected { background: #dc3232; }

/* Score badge */
.siloq-mb-score-wrap { display: flex; align-items: center; gap: 8px; }
.siloq-mb-score-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    line-height: 1.4;
}
.siloq-score-green  { background: #46b450; }
.siloq-score-amber  { background: #f5a623; }
.siloq-score-red    { background: #dc3232; }
.siloq-score-none   { background: #bbb; color: #fff; font-weight: 400; font-size: 12px; }

/* Buttons */
.siloq-mb-buttons {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.siloq-mb-buttons .button {
    width: 100%;
    text-align: center;
    position: relative;
}
.siloq-mb-btn-msg {
    font-size: 11px;
    margin-top: 2px;
    display: none;
}
.siloq-mb-btn-msg.success { color: #46b450; }
.siloq-mb-btn-msg.error   { color: #dc3232; }

/* Spinner */
.siloq-spinner {
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 2px solid rgba(255,255,255,.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: siloq-spin .6s linear infinite;
    vertical-align: middle;
    margin-left: 4px;
}
@keyframes siloq-spin { to { transform: rotate(360deg); } }

/* Recommendations */
.siloq-mb-rec {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    margin-bottom: 7px;
    font-size: 12px;
    line-height: 1.4;
}
.siloq-mb-rec:last-of-type { margin-bottom: 8px; }
.siloq-mb-rec-icon { flex-shrink: 0; font-size: 13px; line-height: 1; margin-top: 1px; }
.siloq-mb-rec-body {}
.siloq-mb-rec-label { font-weight: 600; }
.siloq-mb-rec-desc  { color: #555; }
.siloq-mb-view-all  { font-size: 12px; }

/* Supporting */
.siloq-mb-supporting { font-size: 13px; }
.siloq-mb-supporting .siloq-mb-count { font-weight: 700; }

/* Builder badge */
.siloq-mb-builder {
    font-size: 11px;
    color: #888;
}
';
    }

    // -------------------------------------------------------------------------
    // Meta box render
    // -------------------------------------------------------------------------

    /**
     * Render the meta box HTML.
     *
     * @param WP_Post $post Current post object.
     */
    public static function render_meta_box( $post ) {
        echo '<div id="siloq-metabox-inner">';
        self::render_inner( $post->ID );
        echo '</div>';
    }

    /**
     * Render the inner content (also used by the AJAX refresh handler).
     *
     * @param int $post_id
     */
    private static function render_inner( $post_id ) {
        // ---- 1. Connection status ----
        $connected = ! empty( get_option( 'siloq_site_id' ) ) && ! empty( get_option( 'siloq_api_key' ) );
        ?>
        <div class="siloq-mb-section">
            <div class="siloq-mb-status">
                <span class="siloq-mb-dot <?php echo $connected ? 'connected' : 'disconnected'; ?>"></span>
                <?php if ( $connected ) : ?>
                    <span>Connected</span>
                <?php else : ?>
                    <span>Not connected &mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=siloq-settings' ) ); ?>">check Settings</a></span>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // ---- 2. Analysis score ----
        $score     = get_post_meta( $post_id, '_siloq_analysis_score', true );
        $has_score = $score !== '';
        if ( $has_score ) {
            $score = (int) $score;
            if ( $score >= 70 ) {
                $badge_class = 'siloq-score-green';
            } elseif ( $score >= 40 ) {
                $badge_class = 'siloq-score-amber';
            } else {
                $badge_class = 'siloq-score-red';
            }
        }
        ?>
        <div class="siloq-mb-section">
            <p class="siloq-mb-label">SEO Score</p>
            <div class="siloq-mb-score-wrap">
                <?php if ( $has_score ) : ?>
                    <span class="siloq-mb-score-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $score ); ?>/100</span>
                <?php else : ?>
                    <span class="siloq-mb-score-badge siloq-score-none">Not analyzed yet</span>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // ---- 3. Quick-apply buttons ----
        ?>
        <div class="siloq-mb-section">
            <p class="siloq-mb-label">Quick Actions</p>
            <div class="siloq-mb-buttons">
                <div>
                    <button type="button"
                            class="button button-primary siloq-btn-analyze"
                            data-post-id="<?php echo esc_attr( $post_id ); ?>">
                        🔍 Analyze Page
                    </button>
                    <div class="siloq-mb-btn-msg siloq-msg-analyze"></div>
                </div>
                <div>
                    <button type="button"
                            class="button siloq-btn-apply-meta"
                            data-post-id="<?php echo esc_attr( $post_id ); ?>">
                        ✅ Apply Title &amp; Meta
                    </button>
                    <div class="siloq-mb-btn-msg siloq-msg-apply-meta"></div>
                </div>
                <div>
                    <button type="button"
                            class="button siloq-btn-apply-schema"
                            data-post-id="<?php echo esc_attr( $post_id ); ?>">
                        📋 Apply Schema
                    </button>
                    <div class="siloq-mb-btn-msg siloq-msg-apply-schema"></div>
                </div>
            </div>
        </div>

        <?php
        // ---- 4. Top recommendations ----
        $recs_raw = get_post_meta( $post_id, '_siloq_recommendations', true );
        $recs     = array();
        if ( $recs_raw ) {
            $decoded = json_decode( $recs_raw, true );
            if ( is_array( $decoded ) ) {
                $recs = array_slice( $decoded, 0, 3 );
            }
        }

        $type_icons = array(
            'warning'  => '⚠️',
            'ok'       => '✅',
            'critical' => '🔴',
        );
        ?>
        <div class="siloq-mb-section">
            <p class="siloq-mb-label">Top Recommendations</p>
            <?php if ( ! empty( $recs ) ) : ?>
                <?php foreach ( $recs as $rec ) :
                    $type = isset( $rec['type'] ) ? $rec['type'] : 'warning';
                    $icon = isset( $type_icons[ $type ] ) ? $type_icons[ $type ] : '⚠️';
                    ?>
                    <div class="siloq-mb-rec">
                        <span class="siloq-mb-rec-icon"><?php echo esc_html( $icon ); ?></span>
                        <div class="siloq-mb-rec-body">
                            <?php if ( ! empty( $rec['label'] ) ) : ?>
                                <div class="siloq-mb-rec-label"><?php echo esc_html( $rec['label'] ); ?></div>
                            <?php endif; ?>
                            <?php if ( ! empty( $rec['description'] ) ) : ?>
                                <div class="siloq-mb-rec-desc"><?php echo esc_html( $rec['description'] ); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <a href="https://app.siloq.ai" target="_blank" class="siloq-mb-view-all">View all in Siloq →</a>
            <?php else : ?>
                <p style="font-size:12px;color:#888;margin:0;">No recommendations yet. Run an analysis first.</p>
            <?php endif; ?>
        </div>

        <?php
        // ---- 5. Supporting content ----
        $supporting_needed = (int) get_post_meta( $post_id, '_siloq_supporting_needed', true );
        ?>
        <div class="siloq-mb-section">
            <p class="siloq-mb-label">Supporting Content</p>
            <div class="siloq-mb-supporting">
                <?php if ( $supporting_needed > 0 ) : ?>
                    <span class="siloq-mb-count"><?php echo esc_html( $supporting_needed ); ?> supporting
                        <?php echo $supporting_needed === 1 ? 'page' : 'pages'; ?> needed</span>
                    <div style="margin-top:6px;">
                        <button type="button"
                                class="button siloq-btn-create-draft"
                                data-post-id="<?php echo esc_attr( $post_id ); ?>">
                            Create Draft
                        </button>
                        <div class="siloq-mb-btn-msg siloq-msg-create-draft"></div>
                    </div>
                <?php else : ?>
                    <span>✅ Supporting content complete</span>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // ---- 6. Builder badge ----
        $builder = get_post_meta( $post_id, '_siloq_page_builder', true );
        if ( $builder ) :
            $builder_labels = array(
                'elementor'     => 'Elementor',
                'beaver_builder'=> 'Beaver Builder',
                'divi'          => 'Divi',
                'cornerstone'   => 'Cornerstone',
                'wpbakery'      => 'WPBakery',
                'gutenberg'     => 'Gutenberg',
                'standard'      => 'Standard',
            );
            $builder_label = isset( $builder_labels[ $builder ] ) ? $builder_labels[ $builder ] : ucfirst( $builder );
            ?>
            <div class="siloq-mb-section">
                <span class="siloq-mb-builder">📐 Built with: <?php echo esc_html( $builder_label ); ?></span>
            </div>
        <?php endif;
    }

    // -------------------------------------------------------------------------
    // AJAX: meta box refresh (called after Analyze Page succeeds)
    // -------------------------------------------------------------------------

    /**
     * Return refreshed meta box HTML.
     */
    public static function ajax_metabox_refresh() {
        check_ajax_referer( 'siloq_ai_nonce', 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        ob_start();
        self::render_inner( $post_id );
        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: Apply Title & Meta
    // -------------------------------------------------------------------------

    /**
     * Apply suggested title and meta description from post meta to AIOSEO / native.
     */
    public static function ajax_apply_meta() {
        check_ajax_referer( 'siloq_ai_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $suggested_title       = get_post_meta( $post_id, '_siloq_suggested_title', true );
        $suggested_description = get_post_meta( $post_id, '_siloq_suggested_meta_description', true );

        if ( empty( $suggested_title ) && empty( $suggested_description ) ) {
            wp_send_json_error( array( 'message' => 'No suggested title or meta description found. Run an analysis first.' ) );
        }

        global $wpdb;
        $aioseo_table  = $wpdb->prefix . 'aioseo_posts';
        $aioseo_exists = $wpdb->get_var( "SHOW TABLES LIKE '$aioseo_table'" ) === $aioseo_table;

        if ( ! empty( $suggested_title ) ) {
            if ( $aioseo_exists ) {
                $wpdb->query( $wpdb->prepare(
                    "INSERT INTO $aioseo_table (post_id, title) VALUES (%d, %s)
                     ON DUPLICATE KEY UPDATE title = %s",
                    $post_id,
                    $suggested_title,
                    $suggested_title
                ) );
            }
            // Also update native WP post title as fallback
            wp_update_post( array(
                'ID'         => $post_id,
                'post_title' => sanitize_text_field( $suggested_title ),
            ) );
        }

        if ( ! empty( $suggested_description ) ) {
            if ( $aioseo_exists ) {
                $wpdb->query( $wpdb->prepare(
                    "INSERT INTO $aioseo_table (post_id, description) VALUES (%d, %s)
                     ON DUPLICATE KEY UPDATE description = %s",
                    $post_id,
                    $suggested_description,
                    $suggested_description
                ) );
            } else {
                // Fallback: store in standard post meta
                update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $suggested_description ) );
            }
        }

        // Flush AIOSEO cache if function available
        if ( $aioseo_exists && function_exists( 'aioseo' ) ) {
            do_action( 'aioseo_flush_page_cache', $post_id );
        }
        delete_transient( 'aioseo_post_' . $post_id );

        wp_send_json_success( array( 'message' => 'Title & meta applied' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: Apply Schema
    // -------------------------------------------------------------------------

    /**
     * Copy suggested schema to the active schema meta key.
     */
    public static function ajax_apply_schema() {
        check_ajax_referer( 'siloq_ai_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $suggested_schema = get_post_meta( $post_id, '_siloq_suggested_schema', true );
        if ( empty( $suggested_schema ) ) {
            wp_send_json_error( array( 'message' => 'No suggested schema found. Run an analysis first.' ) );
        }

        // Validate JSON
        $decoded = json_decode( $suggested_schema );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( array( 'message' => 'Schema data is not valid JSON.' ) );
        }

        update_post_meta( $post_id, '_siloq_schema_markup', $suggested_schema );

        wp_send_json_success( array( 'message' => 'Schema applied' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: Create Supporting Draft
    // -------------------------------------------------------------------------

    /**
     * Create a draft post/page from the first uncreated supporting topic.
     */
    public static function ajax_create_supporting_draft() {
        check_ajax_referer( 'siloq_ai_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $topics_raw = get_post_meta( $post_id, '_siloq_supporting_topics', true );
        if ( empty( $topics_raw ) ) {
            wp_send_json_error( array( 'message' => 'No supporting topics found for this post.' ) );
        }

        $topics = json_decode( $topics_raw, true );
        if ( ! is_array( $topics ) || empty( $topics ) ) {
            wp_send_json_error( array( 'message' => 'Supporting topics data is invalid.' ) );
        }

        // Find the first topic that hasn't been drafted yet
        // We track created topics by storing their index in post meta
        $created_indices = get_post_meta( $post_id, '_siloq_supporting_topics_created', true );
        $created_indices = $created_indices ? json_decode( $created_indices, true ) : array();
        if ( ! is_array( $created_indices ) ) {
            $created_indices = array();
        }

        $topic = null;
        $topic_index = null;
        foreach ( $topics as $idx => $t ) {
            if ( ! in_array( $idx, $created_indices, true ) ) {
                $topic       = $t;
                $topic_index = $idx;
                break;
            }
        }

        if ( $topic === null ) {
            wp_send_json_error( array( 'message' => 'All supporting topics have already been drafted.' ) );
        }

        $topic_title   = isset( $topic['title'] ) ? sanitize_text_field( $topic['title'] ) : 'Supporting Content';
        $topic_type    = isset( $topic['type'] )  ? $topic['type'] : 'post';
        $post_type_map = array(
            'page'     => 'page',
            'sub-page' => 'page',
            'subpage'  => 'page',
        );
        $wp_post_type  = isset( $post_type_map[ $topic_type ] ) ? $post_type_map[ $topic_type ] : 'post';

        $new_id = wp_insert_post( array(
            'post_title'  => $topic_title,
            'post_status' => 'draft',
            'post_type'   => $wp_post_type,
        ) );

        if ( is_wp_error( $new_id ) || ! $new_id ) {
            wp_send_json_error( array( 'message' => 'Failed to create draft post.' ) );
        }

        // Store target keyword as post meta on the new draft
        if ( ! empty( $topic['target_keyword'] ) ) {
            update_post_meta( $new_id, '_siloq_target_keyword', sanitize_text_field( $topic['target_keyword'] ) );
        }

        // Link new draft back to hub page
        update_post_meta( $new_id, '_siloq_hub_page_id', $post_id );

        // Record this topic as created
        $created_indices[] = $topic_index;
        update_post_meta( $post_id, '_siloq_supporting_topics_created', wp_json_encode( $created_indices ) );

        // Decrement supporting_needed count
        $remaining = max( 0, (int) get_post_meta( $post_id, '_siloq_supporting_needed', true ) - 1 );
        update_post_meta( $post_id, '_siloq_supporting_needed', $remaining );

        wp_send_json_success( array(
            'post_id'  => $new_id,
            'edit_url' => get_edit_post_link( $new_id, 'raw' ),
            'message'  => 'Draft created: ' . $topic_title,
        ) );
    }
}
