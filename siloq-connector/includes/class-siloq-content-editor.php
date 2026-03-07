<?php
/**
 * Siloq Content Editor — Read Elementor Widgets & Suggest SEO Edits
 *
 * Provides two AJAX endpoints:
 *   • siloq_get_elementor_widgets   — extract all editable text widgets from
 *                                     the current Elementor page's _elementor_data
 *   • siloq_suggest_widget_edit     — request an AI-powered improvement for a
 *                                     single widget's content (with local fallback)
 *
 * Changes are applied client-side via Elementor's $e.run() command API —
 * this class never writes to _elementor_data directly.
 *
 * @package Siloq
 * @since   1.5.51
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Content_Editor {

    // ── Bootstrap ────────────────────────────────────────────────────────────

    public static function init() {
        add_action( 'wp_ajax_siloq_get_elementor_widgets',  [ __CLASS__, 'ajax_get_elementor_widgets'  ] );
        add_action( 'wp_ajax_siloq_suggest_widget_edit',    [ __CLASS__, 'ajax_suggest_widget_edit'    ] );
        add_action( 'wp_ajax_siloq_get_internal_links',     [ __CLASS__, 'ajax_get_internal_links'     ] );
    }

    // ── AJAX: get_elementor_widgets ───────────────────────────────────────────

    /**
     * Extract all editable text widgets from a page's Elementor data.
     *
     * POST params: nonce, post_id
     * Returns: { widgets: [...], count: n }
     */
    public static function ajax_get_elementor_widgets() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
            return;
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Missing post_id' ] );
            return;
        }

        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        if ( ! $elementor_data ) {
            wp_send_json_error( [ 'message' => 'No Elementor data found for this page' ] );
            return;
        }

        $data = json_decode( $elementor_data, true );
        if ( ! $data ) {
            wp_send_json_error( [ 'message' => 'Could not parse Elementor data' ] );
            return;
        }

        $widgets = [];
        self::extract_editable_widgets( $data, $widgets );

        wp_send_json_success( [
            'widgets' => $widgets,
            'count'   => count( $widgets ),
        ] );
    }

    /**
     * Recursively walk Elementor elements and collect editable text widgets.
     *
     * Supported widget types: heading, text-editor, button, icon-box, image-box
     *
     * @param array  $elements   Elementor elements array (recursive).
     * @param array  &$widgets   Accumulator — appended in-place.
     * @param int    $depth      Recursion depth (for future use / limiting).
     */
    private static function extract_editable_widgets( $elements, &$widgets, $depth = 0 ) {
        foreach ( $elements as $element ) {
            $widget_type = $element['widgetType'] ?? '';
            $el_type     = $element['elType']     ?? '';
            $settings    = $element['settings']   ?? [];
            $id          = $element['id']         ?? '';

            // Recurse into sections / columns / containers first
            if ( ! empty( $element['elements'] ) ) {
                self::extract_editable_widgets( $element['elements'], $widgets, $depth + 1 );
            }

            if ( $el_type !== 'widget' ) {
                continue;
            }

            switch ( $widget_type ) {

                case 'heading':
                    $text = wp_strip_all_tags( $settings['title'] ?? '' );
                    if ( $text ) {
                        $widgets[] = [
                            'id'      => $id,
                            'type'    => 'heading',
                            'tag'     => $settings['header_size'] ?? 'h2',
                            'label'   => 'Heading: ' . substr( $text, 0, 40 ) . ( strlen( $text ) > 40 ? '...' : '' ),
                            'content' => $text,
                            'field'   => 'title',
                        ];
                    }
                    break;

                case 'text-editor':
                    $html = $settings['editor'] ?? '';
                    $text = wp_strip_all_tags( $html );
                    if ( strlen( $text ) > 20 ) { // skip tiny/empty widgets
                        $widgets[] = [
                            'id'            => $id,
                            'type'          => 'text-editor',
                            'label'         => 'Text: ' . substr( $text, 0, 40 ) . '...',
                            'content'       => $html,
                            'content_plain' => $text,
                            'field'         => 'editor',
                        ];
                    }
                    break;

                case 'button':
                    $text = wp_strip_all_tags( $settings['text'] ?? '' );
                    if ( $text ) {
                        $widgets[] = [
                            'id'      => $id,
                            'type'    => 'button',
                            'label'   => 'Button: ' . $text,
                            'content' => $text,
                            'field'   => 'text',
                        ];
                    }
                    break;

                case 'icon-box':
                case 'image-box':
                    $title = wp_strip_all_tags( $settings['title_text']       ?? '' );
                    $desc  = wp_strip_all_tags( $settings['description_text'] ?? '' );
                    if ( $title || $desc ) {
                        $widgets[] = [
                            'id'                  => $id,
                            'type'                => $widget_type,
                            'label'               => 'Box: ' . substr( $title ?: $desc, 0, 40 ),
                            'content'             => $title,
                            'content_description' => $desc,
                            'field'               => 'title_text',
                            'field_description'   => 'description_text',
                        ];
                    }
                    break;

                case 'accordion':
                case 'toggle':
                    // Each accordion/toggle item = one FAQ entry.
                    // Added as read-only analysis items (apply not supported for
                    // individual tabs — user must edit manually in Elementor).
                    $tabs = $settings['tabs'] ?? [];
                    foreach ( $tabs as $i => $tab ) {
                        $q = wp_strip_all_tags( $tab['tab_title']   ?? '' );
                        $a = wp_strip_all_tags( $tab['tab_content'] ?? '' );
                        if ( $q ) {
                            $widgets[] = [
                                'id'       => $id . '_tab_' . $i,
                                'type'     => 'faq-item',
                                'label'    => 'FAQ: ' . substr( $q, 0, 50 ) . ( strlen( $q ) > 50 ? '…' : '' ),
                                'content'  => $q . ( $a ? "\n" . $a : '' ),
                                'field'    => 'tabs',
                                'readonly' => true,  // Apply not supported per-tab
                            ];
                        }
                    }
                    break;
            }
        }
    }

    // ── AJAX: get_internal_links ──────────────────────────────────────────────

    /**
     * Fetch related pages (internal link suggestions) from the Siloq API.
     *
     * POST params: nonce, post_id
     * Returns: { should_link_to: [...], should_link_from: [...] }
     */
    public static function ajax_get_internal_links() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
            return;
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Missing post_id' ] );
            return;
        }

        // ── Try API first (URL bug fix: siloq_api_url already contains /api/v1, do NOT re-add it) ──
        $site_id  = get_option( 'siloq_site_id', '' );
        $api_key  = get_option( 'siloq_api_key', '' );
        $api_base = rtrim( get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' ), '/' );

        if ( $site_id && $api_key ) {
            // Get page's API-side ID from sync data if available
            $sync_data  = get_post_meta( $post_id, '_siloq_sync_data', true );
            $api_page_id = ( is_array( $sync_data ) && ! empty( $sync_data['id'] ) ) ? $sync_data['id'] : $post_id;

            $response = wp_remote_get(
                // Fix: $api_base already has /api/v1 — do NOT add it again
                "$api_base/sites/$site_id/pages/$api_page_id/related-pages/",
                [
                    'timeout' => 10,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Accept'        => 'application/json',
                        'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
                    ],
                ]
            );

            if ( ! is_wp_error( $response ) ) {
                $code = wp_remote_retrieve_response_code( $response );
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( $code === 200 && is_array( $body ) ) {
                    wp_send_json_success( $body );
                    return;
                }
            }
        }

        // ── Local WP fallback — works without API, no dependencies ──
        // Derives link recommendations entirely from WP post meta already stored by Siloq.
        $result = self::build_local_link_map( $post_id );
        wp_send_json_success( $result );
    }

    /**
     * Build internal link recommendations from local WordPress data.
     *
     * Uses: _siloq_synced, _siloq_page_type_classification, _siloq_page_role,
     *       _siloq_analysis_data, post permalink, and _elementor_data for existing links.
     *
     * Returns the same shape as the API: { should_link_to: [], should_link_from: [] }
     *
     * @param int $post_id
     * @return array
     */
    private static function build_local_link_map( $post_id ) {
        $current_url  = get_permalink( $post_id );
        $current_type = self::get_page_type( $post_id );

        // Collect all other synced pages
        $all_posts = get_posts( [
            'post_type'      => function_exists( 'get_siloq_crawlable_post_types' )
                ? get_siloq_crawlable_post_types()
                : [ 'page', 'post' ],
            'post_status'    => 'publish',
            'numberposts'    => -1,
            'meta_query'     => [ [ 'key' => '_siloq_synced', 'compare' => 'EXISTS' ] ],
            'exclude'        => [ $post_id ],
        ] );

        // Detect which URLs the current page already links to
        $existing_links = self::get_outbound_links( $post_id );

        // Hierarchy: apex_hub → hub → spoke → supporting → orphan
        $hierarchy = [ 'apex_hub' => 5, 'hub' => 4, 'spoke' => 3, 'supporting' => 2, 'orphan' => 1 ];
        $current_rank = $hierarchy[ $current_type ] ?? 2;

        $should_link_to   = [];  // Pages THIS page should link TO (higher → lower)
        $should_link_from = [];  // Pages that should link TO this page (lower ranks link up)

        foreach ( $all_posts as $page ) {
            $page_type  = self::get_page_type( $page->ID );
            $page_rank  = $hierarchy[ $page_type ] ?? 2;
            $page_url   = get_permalink( $page->ID );
            $already_linked = in_array( $page_url, $existing_links, true );

            $analysis_raw = get_post_meta( $page->ID, '_siloq_analysis_data', true );
            $analysis     = is_array( $analysis_raw ) ? $analysis_raw : ( is_string( $analysis_raw ) ? json_decode( $analysis_raw, true ) : [] );
            if ( ! is_array( $analysis ) ) $analysis = [];

            $anchor = isset( $analysis['primary_keyword'] ) && $analysis['primary_keyword']
                ? $analysis['primary_keyword']
                : $page->post_title;

            $entry = [
                'id'             => $page->ID,
                'title'          => $page->post_title,
                'url'            => $page_url,
                'page_type'      => $page_type,
                'anchor_text'    => $anchor,
                'already_linked' => $already_linked,
            ];

            // This page (hub/apex) should link DOWN to lower-rank pages
            if ( $current_rank > $page_rank && $current_rank >= 3 ) {
                $should_link_to[] = $entry;
            }
            // Higher-rank pages should link TO this page
            if ( $page_rank > $current_rank ) {
                $should_link_from[] = $entry;
            }
        }

        // Sort: unlinked first, then by title
        usort( $should_link_to, function( $a, $b ) {
            if ( $a['already_linked'] !== $b['already_linked'] ) return $a['already_linked'] ? 1 : -1;
            return strcmp( $a['title'], $b['title'] );
        } );
        usort( $should_link_from, function( $a, $b ) {
            if ( $a['already_linked'] !== $b['already_linked'] ) return $a['already_linked'] ? 1 : -1;
            return strcmp( $a['title'], $b['title'] );
        } );

        return [
            'should_link_to'   => array_slice( $should_link_to, 0, 15 ),
            'should_link_from' => array_slice( $should_link_from, 0, 15 ),
            'source'           => 'local',
        ];
    }

    /**
     * Get the page type classification for a post.
     */
    private static function get_page_type( $post_id ) {
        $manual = get_post_meta( $post_id, '_siloq_page_role', true );
        if ( $manual ) return $manual;

        $from_meta = get_post_meta( $post_id, '_siloq_page_type_classification', true );
        if ( $from_meta ) return $from_meta;

        if ( class_exists( 'Siloq_Admin' ) ) {
            return Siloq_Admin::siloq_classify_page( $post_id, get_permalink( $post_id ) );
        }

        $analysis_raw = get_post_meta( $post_id, '_siloq_analysis_data', true );
        $analysis     = is_array( $analysis_raw ) ? $analysis_raw : json_decode( $analysis_raw, true );
        if ( is_array( $analysis ) && ! empty( $analysis['page_type'] ) ) {
            return $analysis['page_type'];
        }

        return 'supporting';
    }

    /**
     * Get URLs this page already links to from _elementor_data.
     */
    private static function get_outbound_links( $post_id ) {
        $urls = [];

        // Scan _elementor_data for href values
        $el_data = get_post_meta( $post_id, '_elementor_data', true );
        if ( $el_data ) {
            preg_match_all( '/"url"\s*:\s*"(https?:[^"]+)"/', $el_data, $matches );
            if ( ! empty( $matches[1] ) ) {
                $urls = array_merge( $urls, $matches[1] );
            }
        }

        // Also scan post_content for classic editor links
        $post = get_post( $post_id );
        if ( $post && $post->post_content ) {
            preg_match_all( '/href=["\']([^"\']+)["\']/', $post->post_content, $matches );
            if ( ! empty( $matches[1] ) ) {
                $urls = array_merge( $urls, $matches[1] );
            }
        }

        return array_unique( array_filter( $urls ) );
    }

    // ── AJAX: suggest_widget_edit ─────────────────────────────────────────────

    /**
     * Request an AI-powered SEO improvement for a single widget's content.
     *
     * POST params: nonce, post_id, widget_id, widget_type, current_content
     * Returns: { suggestion, explanation?, seo_notes?, source }
     */
    public static function ajax_suggest_widget_edit() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
            return;
        }

        $post_id         = intval( $_POST['post_id']         ?? 0 );
        $widget_id       = sanitize_text_field( $_POST['widget_id']       ?? '' );
        $widget_type     = sanitize_text_field( $_POST['widget_type']     ?? '' );
        $current_content = sanitize_textarea_field( $_POST['current_content'] ?? '' );

        if ( ! $post_id || ! $widget_id || ! $current_content ) {
            wp_send_json_error( [ 'message' => 'Missing required parameters' ] );
            return;
        }

        $site_id  = get_option( 'siloq_site_id' );
        $api_key  = get_option( 'siloq_api_key' );
        $api_base = defined( 'SILOQ_API_BASE' ) ? SILOQ_API_BASE : 'https://api.siloq.app';

        if ( ! $site_id || ! $api_key ) {
            wp_send_json_error( [ 'message' => 'Siloq not connected' ] );
            return;
        }

        // Pull page-level context for richer suggestions
        $analysis_data  = json_decode( get_post_meta( $post_id, '_siloq_analysis_data', true ),                   true ) ?: [];
        $entity_profile = json_decode(
            get_post_meta( $post_id, '_siloq_entity_profile', true )
                ?: get_option( 'siloq_entity_profile', '{}' ),
            true
        ) ?: [];

        // Fetch related pages for link context (non-blocking on failure)
        $related_pages  = [];
        $links_response = wp_remote_get(
            $api_base . "/api/v1/sites/{$site_id}/pages/{$post_id}/related-pages/",
            [
                'timeout' => 5,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                    'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
                ],
            ]
        );
        if ( ! is_wp_error( $links_response ) && wp_remote_retrieve_response_code( $links_response ) === 200 ) {
            $links_data    = json_decode( wp_remote_retrieve_body( $links_response ), true );
            $related_pages = array_merge(
                $links_data['should_link_to']   ?? [],
                $links_data['should_link_from'] ?? []
            );
        }

        $response = wp_remote_post(
            $api_base . "/api/v1/sites/{$site_id}/pages/{$post_id}/suggest-widget-edit/",
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
                ],
                'body' => wp_json_encode( [
                    'widget_id'       => $widget_id,
                    'widget_type'     => $widget_type,
                    'current_content' => $current_content,
                    'entity_profile'  => $entity_profile,
                    'page_analysis'   => $analysis_data,
                    'related_pages'   => $related_pages,
                ] ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            // Network error — fall back to local suggestion
            $suggestion = self::generate_local_suggestion( $widget_type, $current_content, $entity_profile );
            wp_send_json_success( [ 'suggestion' => $suggestion, 'source' => 'local' ] );
            return;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 || ! $body ) {
            // API error — fall back to local suggestion
            $suggestion = self::generate_local_suggestion( $widget_type, $current_content, $entity_profile );
            wp_send_json_success( [ 'suggestion' => $suggestion, 'source' => 'local' ] );
            return;
        }

        wp_send_json_success( [
            'suggestion'  => $body['suggestion']  ?? '',
            'explanation' => $body['explanation']  ?? '',
            'seo_notes'   => $body['seo_notes']    ?? [],
            'source'      => 'api',
        ] );
    }

    /**
     * Local fallback suggestion when the API is unavailable.
     *
     * Uses entity profile + basic SEO rules (location inclusion) to suggest
     * lightweight improvements. Returns the original content unchanged when
     * no obvious improvement is possible without AI.
     *
     * @param  string $widget_type  Elementor widget type (heading, button, …)
     * @param  string $content      Current widget text content.
     * @param  array  $entity_profile Entity profile array.
     * @return string Suggested replacement text.
     */
    private static function generate_local_suggestion( $widget_type, $content, $entity_profile ) {
        $city          = $entity_profile['city']          ?? '';
        $business_type = $entity_profile['business_type'] ?? '';

        if ( $widget_type === 'heading' ) {
            // If the heading doesn't mention the service area, suggest appending it
            if ( $city && stripos( $content, $city ) === false ) {
                return $content . ' in ' . $city;
            }
        }

        if ( $widget_type === 'button' && $city && stripos( $content, $city ) === false ) {
            // e.g. "Get a Quote" → "Get a Quote in Lee's Summit"
            return $content . ' in ' . $city;
        }

        // No obvious improvement without API
        return $content;
    }
}
