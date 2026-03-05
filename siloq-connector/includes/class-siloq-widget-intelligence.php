<?php
/**
 * Siloq Widget Intelligence
 *
 * Native Elementor panel integration — injects a ⚡ Siloq Intelligence
 * section directly into the left widget-settings panel for text, heading,
 * icon-box, image-box, accordion and toggle widgets.
 *
 * @package Siloq
 * @since   1.5.57
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Widget_Intelligence {

    /** @var Siloq_Widget_Intelligence|null */
    private static $instance = null;

    // ── Bootstrap ───────────────────────────────────────────────────────

    public static function init() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->register_hooks();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Widget types that get the Siloq Intelligence panel.
     * Adding a type here is all that's needed to enable it.
     */
    private static $supported_widgets = [
        'text-editor', 'heading', 'icon-box', 'image-box',
        'accordion', 'toggle', 'button',
    ];

    /**
     * Tracks which widget types have already received the injected section
     * this request (prevents double-injection on multi-section widgets).
     */
    private $sections_injected = [];

    // ── Hook registration ────────────────────────────────────────────────

    private function register_hooks() {
        // Single generic hook — fires after every section end on every element.
        // We filter to supported widget types and inject after their FIRST section.
        // This is version-proof: it doesn't depend on knowing Elementor section IDs.
        add_action( 'elementor/element/after_section_end', [ $this, 'maybe_add_siloq_section' ], 10, 3 );

        // Editor assets
        add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'enqueue_editor_assets' ] );

        // AJAX
        add_action( 'wp_ajax_siloq_analyze_widget', [ $this, 'ajax_analyze_widget' ] );
    }

    /**
     * Called after every section end — injects Siloq Intelligence after
     * the first Content-tab section of each supported widget type.
     *
     * @param \Elementor\Widget_Base $element
     * @param string                 $section_id
     * @param array                  $args
     */
    public function maybe_add_siloq_section( $element, $section_id, $args ) {
        $widget_name = $element->get_name();

        // Only supported widget types
        if ( ! in_array( $widget_name, self::$supported_widgets, true ) ) {
            return;
        }

        // Only inject once per widget type (after first content section)
        if ( isset( $this->sections_injected[ $widget_name ] ) ) {
            return;
        }
        $this->sections_injected[ $widget_name ] = true;

        $this->add_siloq_section( $element, $args );
    }

    // ── Panel section ────────────────────────────────────────────────────

    /**
     * Add the ⚡ Siloq Intelligence section to a widget's controls panel.
     *
     * @param \Elementor\Widget_Base $element
     * @param array                  $args
     */
    public function add_siloq_section( $element, $args ) {
        $element->start_controls_section(
            'siloq_intelligence',
            [
                'label' => '⚡ Siloq Intelligence',
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $element->add_control(
            'siloq_panel_html',
            [
                'type'            => \Elementor\Controls_Manager::RAW_HTML,
                'raw'             => $this->get_panel_html( $element->get_name() ),
                'content_classes' => 'siloq-wi-panel',
            ]
        );

        $element->end_controls_section();
    }

    /**
     * Return the HTML shell rendered inside the RAW_HTML control.
     *
     * @param string $widget_type
     * @return string
     */
    private function get_panel_html( $widget_type ) {
        return '<div class="siloq-wi-container" data-widget-type="' . esc_attr( $widget_type ) . '">
            <div class="siloq-wi-actions">
                <button type="button" class="siloq-wi-analyze-btn">
                    ⚡ Analyze This Widget
                </button>
            </div>
            <div class="siloq-wi-loading" style="display:none">
                <span class="spinner is-active"></span> Analyzing...
            </div>
            <div class="siloq-wi-results" style="display:none">
                <div class="siloq-wi-layer-badge"></div>
                <div class="siloq-wi-violations"></div>
                <div class="siloq-wi-suggestion-block">
                    <p class="siloq-wi-label">SUGGESTED</p>
                    <div class="siloq-wi-suggestion-text"></div>
                    <div class="siloq-wi-heading-tag" style="display:none">
                        <p class="siloq-wi-label">RECOMMENDED TAG</p>
                        <div class="siloq-wi-tag-display"></div>
                    </div>
                    <div class="siloq-wi-heading-warnings"></div>
                    <div class="siloq-wi-btns">
                        <button class="siloq-wi-apply-btn">✅ Apply</button>
                        <button class="siloq-wi-skip-btn">Skip</button>
                    </div>
                </div>
                <div class="siloq-wi-image-block" style="display:none">
                    <p class="siloq-wi-label">📸 IMAGE INTELLIGENCE</p>
                    <div class="siloq-wi-image-recs"></div>
                </div>
            </div>
        </div>';
    }

    // ── Editor assets ────────────────────────────────────────────────────

    public function enqueue_editor_assets() {
        wp_enqueue_style(
            'siloq-wi',
            SILOQ_PLUGIN_URL . 'assets/css/siloq-widget-intelligence.css',
            [],
            SILOQ_VERSION
        );

        wp_enqueue_script(
            'siloq-wi',
            SILOQ_PLUGIN_URL . 'assets/js/siloq-widget-intelligence.js',
            [ 'jquery', 'elementor-editor' ],  // must load AFTER Elementor's editor JS
            SILOQ_VERSION,
            true
        );

        wp_localize_script(
            'siloq-wi',
            'siloqWI',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'siloq_ajax_nonce' ),
                'postId'  => isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0,
                'siteId'  => get_option( 'siloq_site_id', '' ),
                'apiBase' => defined( 'SILOQ_API_BASE' ) ? SILOQ_API_BASE : 'https://api.siloq.app',
            ]
        );
    }

    // ── AJAX handler ─────────────────────────────────────────────────────

    public function ajax_analyze_widget() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_pages' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
            return;
        }

        $post_id     = intval( $_POST['page_id'] ?? 0 );
        $site_id     = get_option( 'siloq_site_id', '' );
        $api_key     = get_option( 'siloq_api_key', '' );
        $raw_payload = wp_unslash( $_POST['payload'] ?? '' );

        // JS sends JSON.stringify(payload) — decode it. Fall back to array if old format.
        if ( is_string( $raw_payload ) && strlen( $raw_payload ) > 0 ) {
            $payload = json_decode( $raw_payload, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $payload = [];
            }
        } else {
            $payload = is_array( $raw_payload ) ? $raw_payload : [];
        }

        // Attempt live Siloq API call
        $api_base = defined( 'SILOQ_API_BASE' ) ? SILOQ_API_BASE : 'https://api.siloq.app';
        $response = wp_remote_post(
            "{$api_base}/api/v1/sites/{$site_id}/pages/{$post_id}/widget-analysis/",
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
                ],
                'body' => wp_json_encode( $payload ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_success( $this->generate_local_suggestion( $payload ) );
            return;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 || ! $body ) {
            wp_send_json_success( $this->generate_local_suggestion( $payload ) );
            return;
        }

        wp_send_json_success( $body );
    }

    // ── Local fallback ───────────────────────────────────────────────────

    /**
     * Generate a local suggestion when the Siloq API is unavailable.
     * Applies 3-layer SEO rules and returns structured analysis data.
     *
     * @param array $payload
     * @return array
     */
    private function generate_local_suggestion( $payload ) {
        $widget_type = $payload['active_widget']['type']    ?? 'text-editor';
        $content     = wp_strip_all_tags( $payload['active_widget']['content'] ?? '' );
        $heading_map = $payload['page_heading_map']         ?? [];
        $layer       = $this->detect_page_layer( $payload );

        $violations  = $this->validate_heading_hierarchy( $heading_map );
        $suggestion  = $content; // Default: return current content unchanged

        // Layer-specific advisory notes
        $layer_notes = [];
        if ( $layer === 'hub' && $widget_type === 'text-editor' ) {
            $layer_notes[] = 'Hub page: ensure first paragraph contains primary keyword + location modifier within first 100 words.';
            $layer_notes[] = 'Hub page: add internal links to all spoke/supporting pages in this silo.';
        } elseif ( $layer === 'spoke' && $widget_type === 'text-editor' ) {
            $layer_notes[] = 'Spoke page: include a link back to the main hub/service page.';
        } elseif ( $layer === 'supporting' ) {
            $layer_notes[] = 'Supporting page: answer the specific question completely. Link to the relevant spoke or hub page.';
        }

        return [
            'widget_id'             => $payload['active_widget']['widget_id'] ?? '',
            'suggested_content'     => $suggestion,
            'suggested_heading_tag' => $this->suggest_heading_tag( $heading_map, $payload['active_widget']['widget_id'] ?? '' ),
            'heading_violations'    => $violations,
            'layer'                 => $layer,
            'layer_violations'      => $layer_notes,
            'image_recommendations' => $this->suggest_images( $widget_type, $content, $layer ),
            'alt_tag_analysis'      => [],
            'source'                => 'local',
        ];
    }

    /**
     * Detect silo layer from URL depth.
     *
     * @param array $payload
     * @return string  hub|spoke|supporting
     */
    private function detect_page_layer( $payload ) {
        $page_id = intval( $payload['page_id'] ?? 0 );
        if ( ! $page_id ) {
            return 'spoke';
        }

        $url   = get_permalink( $page_id );
        $path  = parse_url( $url, PHP_URL_PATH );
        $depth = substr_count( trim( $path, '/' ), '/' );

        if ( $depth === 0 ) return 'hub';
        if ( $depth === 1 ) return 'spoke';
        return 'supporting';
    }

    /**
     * Validate heading hierarchy and return violation objects.
     *
     * @param array $heading_map
     * @return array
     */
    private function validate_heading_hierarchy( $heading_map ) {
        $violations = [];
        $h1_count   = 0;
        $prev_level = 0;

        foreach ( $heading_map as $h ) {
            $level = intval( ltrim( $h['tag'] ?? 'h2', 'h' ) );
            if ( $level === 1 ) {
                $h1_count++;
            }

            if ( $prev_level > 0 && $level > $prev_level + 1 ) {
                $violations[] = [
                    'widget_id' => $h['widget_id'] ?? '',
                    'tag'       => $h['tag'],
                    'issue'     => "Heading hierarchy gap: jumped from H{$prev_level} to H{$level}",
                    'fix'       => 'Change to H' . ( $prev_level + 1 ),
                ];
            }

            $prev_level = $level;
        }

        if ( $h1_count > 1 ) {
            $violations[] = [
                'widget_id' => '',
                'tag'       => 'h1',
                'issue'     => "Multiple H1 tags found ({$h1_count}). Only one H1 is allowed per page.",
                'fix'       => 'Change additional H1s to H2',
            ];
        }

        return $violations;
    }

    /**
     * Return the current heading tag for a given widget ID.
     *
     * @param array  $heading_map
     * @param string $widget_id
     * @return string
     */
    private function suggest_heading_tag( $heading_map, $widget_id ) {
        foreach ( $heading_map as $h ) {
            if ( ( $h['widget_id'] ?? '' ) === $widget_id ) {
                return $h['tag'] ?? 'h2';
            }
        }
        return 'h2';
    }

    /**
     * Generate contextual image recommendations.
     *
     * @param string $widget_type
     * @param string $content
     * @param string $layer
     * @return array
     */
    private function suggest_images( $widget_type, $content, $layer ) {
        $recs          = [];
        $business_name = get_option( 'siloq_business_name', get_bloginfo( 'name' ) );
        $city          = get_option( 'siloq_city', '' );
        $business_type = get_option( 'siloq_business_type', '' );

        if ( $widget_type === 'text-editor' && strlen( $content ) > 200 ) {
            $recs[] = [
                'position'           => 'after_intro',
                'type'               => 'photo',
                'subject'            => "Professional {$business_type} at work in {$city}",
                'suggested_filename' => sanitize_title( $business_type . '-' . $city . '-service' ) . '.jpg',
                'suggested_alt'      => "{$business_name} {$business_type} service in {$city}",
                'ai_prompt'          => "Professional photo of a {$business_type} technician performing work in a residential setting in {$city}. Clean, well-lit, high quality. No text overlays.",
            ];
        }

        return $recs;
    }
}
