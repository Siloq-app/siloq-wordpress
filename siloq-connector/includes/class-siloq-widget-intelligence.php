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
        add_action( 'elementor/element/before_section_start', [ $this, 'maybe_add_siloq_section' ], 10, 3 );

        // Editor assets
        add_action( 'elementor/editor/before_enqueue_scripts', [ $this, 'enqueue_editor_assets' ] );

        // AJAX
        add_action( 'wp_ajax_siloq_analyze_widget', [ $this, 'ajax_analyze_widget' ] );
        add_action( 'wp_ajax_siloq_generate_and_insert_image', [ __CLASS__, 'ajax_generate_and_insert_image' ] );
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
                'label' => 'Siloq Intelligence',
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

            <!-- ── Widget Analysis ───────────────────────────── -->
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

            <!-- ── Internal Links ─────────────────────────────── -->
            <div class="siloq-wi-links-section" style="margin-top:12px;border-top:1px solid #e5e7eb;padding-top:10px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <p class="siloq-wi-label" style="margin:0;">🔗 INTERNAL LINKS</p>
                    <button type="button" class="siloq-wi-links-load-btn" style="font-size:10px;padding:2px 8px;border:1px solid #d1d5db;border-radius:4px;background:#f9fafb;cursor:pointer;color:#374151;">Load</button>
                </div>
                <div class="siloq-wi-links-loading" style="display:none;font-size:11px;color:#6b7280;padding:4px 0;">
                    <span class="spinner is-active" style="float:none;vertical-align:middle;margin:0 4px 0 0;width:14px;height:14px;"></span> Loading link map...
                </div>
                <div class="siloq-wi-links-content" style="display:none;"></div>
                <div class="siloq-wi-links-status" style="display:none;font-size:11px;padding:4px 6px;border-radius:4px;margin-top:4px;"></div>
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
                'apiBase' => rtrim( get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' ), '/' ),
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

        // Detect dynamic / JetEngine widgets
        $dynamic_widget_types = [
            "jet-listing-grid", "jet-engine-listing-grid", "acf-field",
            "acf", "pods-field", "elementor-theme-post-content",
            "dynamic-field", "jet-engine",
        ];
        $is_dynamic = in_array( $widget_type, $dynamic_widget_types, true );

        // Also detect by CPT source in payload
        $listing_source = $payload['active_widget']['settings']['jet_cpt']
            ?? $payload['active_widget']['settings']['_post_type']
            ?? $payload['active_widget']['settings']['custom_post_type']
            ?? '';
        if ( ! empty( $listing_source ) ) {
            $is_dynamic = true;
        }

        if ( $is_dynamic ) {
            // Try to read the CPT content
            $cpt_posts = [];
            if ( ! empty( $listing_source ) ) {
                $raw_posts = get_posts( [
                    'post_type'      => sanitize_key( $listing_source ),
                    'posts_per_page' => 20,
                    'post_status'    => 'publish',
                ] );
                foreach ( $raw_posts as $p ) {
                    $cpt_posts[] = [
                        'title'   => $p->post_title,
                        'excerpt' => wp_trim_words( wp_strip_all_tags( $p->post_content ), 20 ),
                    ];
                }
            }
            wp_send_json_success( [
                'is_dynamic_widget'    => true,
                'widget_type'          => $widget_type,
                'listing_source'       => $listing_source,
                'cpt_posts'            => $cpt_posts,
                'no_suggestion_reason' => '',
                'suggested_content'    => '',
                'violations'           => [],
                'layer_notes'          => [],
                'image_recs'           => [],
            ] );
            return;
        }

        // Use correct API base from WP option (not hardcoded wrong domain)
        $api_base_raw = get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' );
        $api_base     = rtrim( $api_base_raw, '/' );

        // Get widget content from payload
        $widget_content = $payload['active_widget']['content'] ?? '';
        $widget_type    = $payload['active_widget']['type'] ?? 'text-editor';
        $post_title     = get_the_title( $post_id );
        $layer          = $this->detect_page_layer( $payload );
        $business       = get_option( 'siloq_business_name', get_bloginfo( 'name' ) );
        $city_name      = get_option( 'siloq_city', '' );
        $services_arr   = json_decode( get_option( 'siloq_primary_services', '[]' ), true );
        $service_str    = is_array( $services_arr ) ? implode( ', ', array_slice( $services_arr, 0, 3 ) ) : '';

        // Detect page-level city from title (e.g. "Excelsior Springs, MO")
        $page_city = '';
        if ( $post_id ) {
            if ( preg_match( '/([A-Z][a-zA-Z\s]{2,}),?\s+(MO|KS|AR|OK|NE|IA|KY|TN|IL|TX)/i', $post_title, $cm ) ) {
                $page_city = trim( $cm[0] );
            }
        }
        $location_str = ! empty( $page_city ) ? $page_city : ( $city_name ? "{$city_name}" : '' );

        // Build a specific, actionable edit instruction
        $edit_instruction = 'TASK: Rewrite the following content to improve local SEO performance for the page: "' . $post_title . '".' . "\n\n"
            . "RULES:\n"
            . "- The rewritten content MUST be meaningfully different from the input\n"
            . "- Keep the same HTML structure (ul/li, headings, paragraphs — never strip tags)\n"
            . "- The first sentence must contain the primary service keyword naturally\n"
            . ( $location_str ? "- Include the location: {$location_str} naturally in the text\n" : '' )
            . "- Include at least one specific, concrete detail (not generic marketing language)\n"
            . "- Do NOT return the input unchanged under any circumstances\n"
            . "- City pages must contain facts or details specific to that city, not just the city name swapped in\n\n"
            . "CURRENT CONTENT:\n"
            . $widget_content . "\n\n"
            . "Return only the improved HTML. No explanation. No preamble. No markdown code fences.";

        // Find the API page ID by matching WP post URL
        $post_url    = get_permalink( $post_id );
        $wp_path     = rtrim( parse_url( $post_url, PHP_URL_PATH ) ?: '', '/' );
        $wp_host_raw = parse_url( $post_url, PHP_URL_HOST ) ?: '';
        $wp_host     = preg_replace( '/^www\./i', '', $wp_host_raw );
        $wp_slug     = basename( $wp_path );
        $api_page_id = null;
        $pages_resp  = wp_remote_get( "{$api_base}/sites/{$site_id}/pages/?limit=100",
            [ 'headers' => [ 'Authorization' => 'Bearer ' . $api_key ], 'timeout' => 10 ] );
        if ( ! is_wp_error( $pages_resp ) && wp_remote_retrieve_response_code( $pages_resp ) === 200 ) {
            $pages_data = json_decode( wp_remote_retrieve_body( $pages_resp ), true );
            $pages_list = $pages_data['results'] ?? ( is_array( $pages_data ) ? $pages_data : [] );
            foreach ( $pages_list as $p ) {
                $p_path = rtrim( parse_url( $p['url'] ?? '', PHP_URL_PATH ) ?: '', '/' );
                $p_host = preg_replace( '/^www\./i', '', parse_url( $p['url'] ?? '', PHP_URL_HOST ) ?: '' );

                // Match by path (trailing-slash-insensitive) + host (www-insensitive)
                if ( $p_path === $wp_path && $p_host === $wp_host ) {
                    $api_page_id = $p['id'];
                    break;
                }
                // Fallback: match by slug
                if ( ! empty( $p['slug'] ) && $p['slug'] === $wp_slug ) {
                    $api_page_id = $p['id'];
                    break;
                }
            }
        }

        if ( $api_page_id && ! empty( $widget_content ) ) {
            $api_response = wp_remote_post(
                "{$api_base}/sites/{$site_id}/pages/{$api_page_id}/suggest-widget-edit/",
                [
                    'timeout' => 45,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                        'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
                    ],
                    'body' => wp_json_encode( [
                        'widget_content'   => $widget_content,
                        'edit_instruction' => $edit_instruction,
                        'related_pages'    => [],
                    ] ),
                ]
            );

            if ( ! is_wp_error( $api_response ) && wp_remote_retrieve_response_code( $api_response ) === 200 ) {
                $api_body = json_decode( wp_remote_retrieve_body( $api_response ), true );
                if ( ! empty( $api_body['suggestion'] ) ) {
                    $result = $this->generate_local_suggestion( $payload );
                    $result['suggested_content'] = $api_body['suggestion'];
                    $result['source'] = 'api';
                    wp_send_json_success( $result );
                    return;
                }
            }
        }

        // Fallback 2: Route through Siloq API suggest-content endpoint
        // Siloq API holds the Anthropic key server-side — plugin never needs it.
        // Ahmad: implement POST /api/v1/sites/{site_id}/suggest-content/
        // Body: { "content": "...", "instruction": "...", "page_title": "..." }
        // Response: { "suggestion": "..." }
        // Uses ANTHROPIC_API_KEY env var on the DO app. Respects billing tier.
        if ( $api_page_id && ! empty( $widget_content ) ) {
            $suggest_resp = wp_remote_post(
                "{$api_base}/sites/{$site_id}/suggest-content/",
                [
                    'timeout' => 45,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                        'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
                    ],
                    'body' => wp_json_encode( [
                        'content'     => $widget_content,
                        'instruction' => $edit_instruction,
                        'page_title'  => get_the_title( $post_id ),
                    ] ),
                ]
            );

            if ( ! is_wp_error( $suggest_resp ) && wp_remote_retrieve_response_code( $suggest_resp ) === 200 ) {
                $suggest_body    = json_decode( wp_remote_retrieve_body( $suggest_resp ), true );
                $suggest_content = $suggest_body['suggestion'] ?? '';
                if ( ! empty( $suggest_content ) ) {
                    $result = $this->generate_local_suggestion( $payload );
                    $result['suggested_content'] = $suggest_content;
                    $result['source'] = 'api';
                    unset( $result['no_suggestion_reason'] );
                    wp_send_json_success( $result );
                    return;
                }
            }
        }

        // Fallback 3: Direct Anthropic Claude call (BYOK — key in WP Settings)
        // Temporary until api.siloq.ai/suggest-content/ is built by Ahmad.
        $ai_system_prompt = 'You are an expert local SEO copywriter. Rewrite the provided content to improve local SEO. '
            . 'Rules: (1) Return valid HTML preserving all tags exactly — ul/li in = ul/li out. '
            . '(2) Never strip HTML tags. (3) Content must be meaningfully different from input. '
            . '(4) First sentence must contain the primary service keyword. '
            . '(5) Include city and state naturally. (6) No generic filler — at least one concrete specific detail. '
            . 'Minimum 600 words required. Write comprehensive, detailed content. '
            . 'Return only the improved HTML. No explanation. No markdown fences.';

        $anthropic_key = get_option( 'siloq_anthropic_api_key', '' );
        if ( ! empty( $anthropic_key ) && ! empty( $widget_content ) ) {
            $ant_resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
                'timeout' => 45,
                'headers' => [
                    'x-api-key'         => $anthropic_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'model'      => 'claude-sonnet-4-6',
                    'max_tokens' => 2048,
                    'system'     => $ai_system_prompt,
                    'messages'   => [ [ 'role' => 'user', 'content' => $edit_instruction ] ],
                ] ),
            ] );
            if ( ! is_wp_error( $ant_resp ) && wp_remote_retrieve_response_code( $ant_resp ) === 200 ) {
                $ant_body    = json_decode( wp_remote_retrieve_body( $ant_resp ), true );
                $ant_content = $ant_body['content'][0]['text'] ?? '';
                if ( ! empty( $ant_content ) ) {
                    $result = $this->generate_local_suggestion( $payload );
                    $result['suggested_content'] = $ant_content;
                    $result['source']            = 'anthropic_byok';
                    unset( $result['no_suggestion_reason'] );
                    wp_send_json_success( $result );
                    return;
                }
            }
        }

        // Fallback 4: OpenAI GPT-4o (DALL-E key reused for text when Anthropic not set)
        $openai_key = get_option( 'siloq_openai_api_key', '' );
        if ( ! empty( $openai_key ) && ! empty( $widget_content ) ) {
            $oai_resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $openai_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'model'    => 'gpt-4o',
                    'messages' => [
                        [ 'role' => 'system', 'content' => $ai_system_prompt ],
                        [ 'role' => 'user',   'content' => $edit_instruction ],
                    ],
                ] ),
            ] );
            if ( ! is_wp_error( $oai_resp ) && wp_remote_retrieve_response_code( $oai_resp ) === 200 ) {
                $oai_body    = json_decode( wp_remote_retrieve_body( $oai_resp ), true );
                $oai_content = $oai_body['choices'][0]['message']['content'] ?? '';
                if ( ! empty( $oai_content ) ) {
                    $result = $this->generate_local_suggestion( $payload );
                    $result['suggested_content'] = $oai_content;
                    $result['source']            = 'openai_byok';
                    unset( $result['no_suggestion_reason'] );
                    wp_send_json_success( $result );
                    return;
                }
            }
        }

        // Fallback 5: local rule-based only
        wp_send_json_success( $this->generate_local_suggestion( $payload ) );
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

        // Never return the original content as a suggestion
        $suggestion           = '';
        $no_suggestion_reason = '';
        if ( $widget_type === 'text-editor' && ! empty( $content ) ) {
            $no_suggestion_reason = 'API unavailable — connect Siloq to your API key to get AI suggestions.';
        }

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

        $result = [
            'widget_id'             => $payload['active_widget']['widget_id'] ?? '',
            'suggested_content'     => $suggestion,
            'suggested_heading_tag' => $this->suggest_heading_tag( $heading_map, $payload['active_widget']['widget_id'] ?? '' ),
            'heading_violations'    => $violations,
            'layer'                 => $layer,
            'layer_violations'      => $layer_notes,
            'image_recommendations' => $this->suggest_images( $widget_type, $content, $layer, intval( $payload['page_id'] ?? 0 ) ),
            'alt_tag_analysis'      => [],
            'source'                => 'local',
        ];
        if ( $no_suggestion_reason ) {
            $result['no_suggestion_reason'] = $no_suggestion_reason;
        }
        return $result;
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
    private function suggest_images( $widget_type, $content, $layer, $post_id = 0 ) {
        $recs          = [];
        $business_name = get_option( 'siloq_business_name', get_bloginfo( 'name' ) );
        $business_city = get_option( 'siloq_city', '' );
        $state         = get_option( 'siloq_state', 'MO' );
        $business_type = get_option( 'siloq_business_type', '' );

        // Priority 1: Extract city from page title
        $page_city = '';
        if ( $post_id ) {
            $page_title = get_the_title( $post_id );
            if ( preg_match( '/([A-Z][a-zA-Z\s]{2,}),?\s+(MO|KS|AR|OK|NE|IA|KY|TN|IL|TX)/i', $page_title, $m ) ) {
                $page_city = trim( $m[0] );
            }
            if ( empty( $page_city ) ) {
                $kw = get_post_meta( $post_id, '_siloq_target_keyword', true );
                if ( $kw && preg_match( '/([A-Z][a-zA-Z\s]{2,}),?\s+(MO|KS|AR|OK|NE|IA|KY|TN|IL|TX)/i', $kw, $m2 ) ) {
                    $page_city = trim( $m2[0] );
                }
            }
        }

        // Priority 2: Fall back to business profile city
        $city = ! empty( $page_city ) ? $page_city : $business_city;

        // Build a human-readable service label for the prompt.
        // siloq_business_type stores slugs like "local_service", "electrical", etc.
        // Pull the first primary service as the descriptor — far more specific and
        // produces accurate DALL-E images (e.g. "electrician" not "local_service").
        $services_raw  = json_decode( get_option( 'siloq_primary_services', '[]' ), true );
        $service_label = ( is_array( $services_raw ) && ! empty( $services_raw ) )
            ? strtolower( trim( $services_raw[0] ) )
            : strtolower( trim( $business_type ) );

        // Also try to pull service label from page title (e.g. "Electrician" from "Excelsior Springs, MO Electrician")
        if ( $post_id ) {
            $page_title = get_the_title( $post_id );
            // Strip city/state from title to get the service keyword
            $stripped = preg_replace( '/[A-Z][a-zA-Z\s]+,?\s+(MO|KS|AR|OK|NE|IA|KY|TN|IL|TX)\s*/i', '', $page_title );
            $stripped = trim( $stripped, " \t\n\r\0\x0B|,-" );
            if ( ! empty( $stripped ) && strlen( $stripped ) > 2 && strlen( $stripped ) < 40 ) {
                $service_label = strtolower( $stripped );
            }
        }

        $city_slug    = sanitize_title( $city );
        $service_slug = sanitize_title( $service_label );

        if ( $widget_type === 'text-editor' && strlen( $content ) > 200 ) {
            // Derive action verb from service label for realistic image prompts
            $service_lower = strtolower( $service_label );
            if ( strpos( $service_lower, 'electric' ) !== false ) {
                $action_verb = 'installing electrical wiring or replacing an electrical panel';
            } elseif ( strpos( $service_lower, 'plumb' ) !== false ) {
                $action_verb = 'installing or repairing plumbing under a sink or in a crawlspace';
            } elseif ( strpos( $service_lower, 'hvac' ) !== false || strpos( $service_lower, 'heat' ) !== false || strpos( $service_lower, 'air' ) !== false ) {
                $action_verb = 'servicing an HVAC unit or installing ductwork';
            } elseif ( strpos( $service_lower, 'roof' ) !== false ) {
                $action_verb = 'installing shingles or inspecting a roof';
            } elseif ( strpos( $service_lower, 'paint' ) !== false ) {
                $action_verb = 'painting exterior trim or rolling interior walls';
            } elseif ( strpos( $service_lower, 'landscap' ) !== false || strpos( $service_lower, 'lawn' ) !== false ) {
                $action_verb = 'operating a zero-turn mower or installing landscape edging';
            } else {
                $action_verb = 'performing skilled trade work on-site';
            }

            $ai_prompt = "Authentic documentary-style photo of a male {$service_label} aged 35-55, "
                . "wearing work uniform and tool belt, actively {$action_verb} in {$city}. "
                . "Natural indoor/outdoor lighting, realistic skin texture, genuine candid expression. "
                . "Shot on Canon DSLR, photojournalism style, f/2.8 depth of field. "
                . "Gritty realism, authentic trade work environment. "
                . "No stock photo aesthetic. No posed smiling. No artificial diversity casting. "
                . "No text overlays. No logos.";

            $recs[] = [
                'position'           => 'after_intro',
                'type'               => 'photo',
                'subject'            => "Male {$service_label} performing service in {$city}",
                'suggested_filename' => "{$service_slug}-{$city_slug}-service.jpg",
                'suggested_alt'      => "{$business_name} {$service_label} technician working in {$city}",
                'ai_prompt'          => $ai_prompt,
            ];
        }

        return $recs;
    }

    // ── AJAX: Generate image via DALL-E and sideload into media library ──

    public static function ajax_generate_and_insert_image() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
            return;
        }

        $prompt   = sanitize_text_field( $_POST['prompt'] ?? '' );
        $filename = sanitize_file_name( $_POST['filename'] ?? 'generated-image.png' );
        $alt_text = sanitize_text_field( $_POST['alt_text'] ?? '' );
        $post_id  = intval( $_POST['post_id'] ?? 0 );

        if ( empty( $prompt ) ) {
            wp_send_json_error( [ 'message' => 'Prompt is required.' ] );
            return;
        }

        $image_url = null;

        // Strategy 1: Call Siloq API generate-image endpoint
        $api_url  = get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' );
        $api_key  = get_option( 'siloq_api_key', '' );
        $site_id  = get_option( 'siloq_site_id', '' );

        if ( ! empty( $api_key ) && ! empty( $site_id ) ) {
            $api_response = wp_remote_post(
                rtrim( $api_url, '/' ) . '/sites/' . $site_id . '/generate-image/',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'body'    => wp_json_encode( [
                        'prompt'   => $prompt,
                        'filename' => $filename,
                        'alt_text' => $alt_text,
                    ] ),
                    'timeout' => 60,
                ]
            );

            if ( ! is_wp_error( $api_response ) ) {
                $status = wp_remote_retrieve_response_code( $api_response );
                $body   = json_decode( wp_remote_retrieve_body( $api_response ), true );
                if ( $status >= 200 && $status < 300 && ! empty( $body['image_url'] ) ) {
                    $image_url = $body['image_url'];
                }
            }
        }

        // Strategy 2: Fallback — call OpenAI directly if API returned 404 or failed
        if ( empty( $image_url ) ) {
            $openai_key = get_option( 'siloq_openai_api_key', '' );
            if ( empty( $openai_key ) ) {
                wp_send_json_error( [ 'message' => 'Image generation is not available. The Siloq API endpoint returned no image and no OpenAI API key is configured (Settings > siloq_openai_api_key).' ] );
                return;
            }

            $oai_response = wp_remote_post(
                'https://api.openai.com/v1/images/generations',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $openai_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'body'    => wp_json_encode( [
                        'model'  => 'dall-e-3',
                        'prompt' => $prompt,
                        'n'      => 1,
                        'size'   => '1024x1024',
                    ] ),
                    'timeout' => 60,
                ]
            );

            if ( is_wp_error( $oai_response ) ) {
                wp_send_json_error( [ 'message' => 'OpenAI request failed: ' . $oai_response->get_error_message() ] );
                return;
            }

            $oai_body = json_decode( wp_remote_retrieve_body( $oai_response ), true );
            if ( empty( $oai_body['data'][0]['url'] ) ) {
                $err = isset( $oai_body['error']['message'] ) ? $oai_body['error']['message'] : 'Unknown OpenAI error';
                wp_send_json_error( [ 'message' => 'DALL-E generation failed: ' . $err ] );
                return;
            }
            $image_url = $oai_body['data'][0]['url'];
        }

        // Sideload image into WP media library
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image( $image_url, $post_id, $alt_text, 'id' );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => 'Failed to sideload image: ' . $attachment_id->get_error_message() ] );
            return;
        }

        if ( ! empty( $alt_text ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
        }

        wp_send_json_success( [
            'attachment_id' => $attachment_id,
            'url'           => wp_get_attachment_url( $attachment_id ),
        ] );
    }
}
