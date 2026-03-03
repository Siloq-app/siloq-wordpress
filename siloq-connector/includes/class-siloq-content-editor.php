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
        add_action( 'wp_ajax_siloq_get_elementor_widgets', [ __CLASS__, 'ajax_get_elementor_widgets' ] );
        add_action( 'wp_ajax_siloq_suggest_widget_edit',   [ __CLASS__, 'ajax_suggest_widget_edit'   ] );
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
            }
        }
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
