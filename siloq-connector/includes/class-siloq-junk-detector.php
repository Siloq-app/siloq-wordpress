<?php
/**
 * Siloq Junk Page Detector
 *
 * Scans a WordPress installation for pages that should not be indexed by
 * Google: default WP pages/posts, page-builder template entries, thin
 * content, and similar low-quality / inadvertently-published pages.
 *
 * REST endpoints (authenticated via WP Application Password or cookie nonce):
 *   GET  /wp-json/siloq/v1/junk-scan    → returns list of junk pages
 *   POST /wp-json/siloq/v1/junk-apply   → {post_id, action} applies fix
 *
 * AJAX handlers (for the WP admin UI):
 *   wp_ajax_siloq_scan_junk_pages
 *   wp_ajax_siloq_apply_junk_action
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Junk_Detector {

    // ─────────────────────────────────────────────────────────────
    // REST ROUTE REGISTRATION
    // ─────────────────────────────────────────────────────────────

    public static function register_rest_routes() {
        register_rest_route( 'siloq/v1', '/junk-scan', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( __CLASS__, 'rest_scan' ),
            'permission_callback' => array( __CLASS__, 'rest_permission' ),
        ) );

        register_rest_route( 'siloq/v1', '/junk-apply', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'rest_apply' ),
            'permission_callback' => array( __CLASS__, 'rest_permission' ),
            'args'                => array(
                'post_id' => array( 'required' => true, 'type' => 'integer' ),
                'action'  => array(
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => array( 'delete', 'noindex', 'review' ),
                ),
            ),
        ) );
    }

    public static function rest_permission() {
        return current_user_can( 'manage_options' );
    }

    public static function rest_scan( WP_REST_Request $request ) {
        return new WP_REST_Response( array(
            'success' => true,
            'pages'   => self::detect_junk_pages(),
        ) );
    }

    public static function rest_apply( WP_REST_Request $request ) {
        $post_id = absint( $request->get_param( 'post_id' ) );
        $action  = sanitize_key( $request->get_param( 'action' ) );
        $result  = self::apply_junk_action( $post_id, $action );
        return new WP_REST_Response( $result );
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────

    public static function ajax_scan() {
        check_ajax_referer( 'siloq_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions', 403 );
        }
        wp_send_json_success( self::detect_junk_pages() );
    }

    public static function ajax_apply() {
        check_ajax_referer( 'siloq_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions', 403 );
        }
        $post_id = absint( $_POST['post_id'] ?? 0 );
        $action  = sanitize_key( $_POST['junk_action'] ?? '' );
        wp_send_json( self::apply_junk_action( $post_id, $action ) );
    }

    // ─────────────────────────────────────────────────────────────
    // CORE DETECTION LOGIC
    // ─────────────────────────────────────────────────────────────

    /**
     * Scan the site and return an array of junk-page findings.
     *
     * @return array[] Each element: {post_id, title, url, post_type, action, reason}
     */
    public static function detect_junk_pages() {
        $findings = array();

        // ── 1. Standard post types (page + post) ────────────────
        $posts = get_posts( array(
            'post_type'      => array( 'page', 'post' ),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'all',
        ) );

        foreach ( $posts as $post ) {
            $slug  = $post->post_name;
            $title = $post->post_title;

            // Default WordPress sample page
            if ( $slug === 'sample-page' || $title === 'Sample Page' ) {
                $findings[] = self::finding( $post, 'delete', 'Default WordPress sample page — should be deleted' );
                continue;
            }

            // Default WordPress hello world post
            if ( $slug === 'hello-world' || $title === 'Hello world!' ) {
                $findings[] = self::finding( $post, 'delete', 'Default WordPress hello world post — should be deleted' );
                continue;
            }

            // Elementor auto-generated library pages in post_name or title
            if ( preg_match( '/^elementor[-\s]#?\d+$/i', $title ) || preg_match( '/^elementor-\d+$/', $slug ) ) {
                $findings[] = self::finding( $post, 'noindex', 'Auto-generated Elementor library page' );
                continue;
            }

            // Thin content: fewer than 50 visible words
            $word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
            if ( $word_count < 50 ) {
                $findings[] = self::finding( $post, 'review', "Thin content — only {$word_count} words of visible text" );
                continue;
            }
        }

        // ── 2. Elementor library post type ──────────────────────
        if ( post_type_exists( 'elementor_library' ) ) {
            $el_posts = get_posts( array(
                'post_type'      => 'elementor_library',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ) );
            foreach ( $el_posts as $post ) {
                $findings[] = self::finding( $post, 'noindex', 'Elementor library template — should not be indexed' );
            }
        }

        // ── 3. WPBakery template post types ─────────────────────
        foreach ( array( 'vc_grid_item', 'vc_template' ) as $pt ) {
            if ( post_type_exists( $pt ) ) {
                $vc_posts = get_posts( array(
                    'post_type'      => $pt,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                ) );
                foreach ( $vc_posts as $post ) {
                    $findings[] = self::finding( $post, 'noindex', 'WPBakery builder template — should not be indexed' );
                }
            }
        }

        return $findings;
    }

    // ─────────────────────────────────────────────────────────────
    // APPLY A JUNK FIX
    // ─────────────────────────────────────────────────────────────

    /**
     * Apply a recommended fix to a junk page.
     *
     * @param int    $post_id WordPress post ID.
     * @param string $action  'delete' | 'noindex' | 'review'
     * @return array {success, message}
     */
    public static function apply_junk_action( $post_id, $action ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return array( 'success' => false, 'message' => 'Post not found' );
        }
        if ( ! current_user_can( 'delete_post', $post_id ) ) {
            return array( 'success' => false, 'message' => 'Insufficient permissions' );
        }

        switch ( $action ) {
            case 'delete':
                $result = wp_trash_post( $post_id );
                if ( $result ) {
                    return array( 'success' => true, 'message' => 'Page moved to trash. Recoverable for 30 days.' );
                }
                return array( 'success' => false, 'message' => 'Failed to trash post' );

            case 'noindex':
                return self::apply_noindex( $post_id );

            case 'review':
                // Mark as acknowledged — no automated change
                update_post_meta( $post_id, '_siloq_junk_reviewed', current_time( 'mysql' ) );
                return array( 'success' => true, 'message' => 'Marked as reviewed. No automated change made.' );

            default:
                return array( 'success' => false, 'message' => 'Unknown action: ' . esc_html( $action ) );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────

    /**
     * Build a standardised finding array.
     */
    private static function finding( WP_Post $post, string $action, string $reason ): array {
        return array(
            'post_id'   => $post->ID,
            'title'     => $post->post_title ?: '(untitled)',
            'url'       => get_permalink( $post->ID ),
            'post_type' => $post->post_type,
            'action'    => $action,   // 'delete' | 'noindex' | 'review'
            'reason'    => $reason,
        );
    }

    /**
     * Apply noindex to a post via AIOSEO (preferred) or meta fallback.
     */
    private static function apply_noindex( int $post_id ): array {
        global $wpdb;

        $aioseo_table = $wpdb->prefix . 'aioseo_posts';
        $aioseo_exists = $wpdb->get_var( "SHOW TABLES LIKE '$aioseo_table'" ) === $aioseo_table;

        if ( $aioseo_exists ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO $aioseo_table (post_id, robots_noindex, created, updated)
                 VALUES (%d, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE robots_noindex = 1, updated = NOW()",
                $post_id
            ) );
            return array( 'success' => true, 'message' => 'Noindex applied via AIOSEO' );
        }

        // Yoast fallback
        update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', '1' );
        return array( 'success' => true, 'message' => 'Noindex applied via post meta (Yoast format)' );
    }
}

// Register REST routes on rest_api_init
add_action( 'rest_api_init', array( 'Siloq_Junk_Detector', 'register_rest_routes' ) );
