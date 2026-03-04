<?php
/**
 * Siloq Widget Intelligence Core
 *
 * Shared logic for all builder intelligence integrations.
 * Required before any builder-specific intelligence class is loaded.
 *
 * @package Siloq
 * @since   1.5.58
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Widget_Intelligence_Core {

    // ── Page layer detection ──────────────────────────────────────────────

    /**
     * Determine the silo layer of a page based on URL depth.
     *
     * @param int $page_id
     * @return string  hub|spoke|supporting
     */
    public static function get_page_layer( $page_id ) {
        if ( ! $page_id ) return 'spoke';
        $url  = get_permalink( $page_id );
        $path = trim( parse_url( $url, PHP_URL_PATH ), '/' );
        if ( $path === '' ) return 'hub';
        $depth = substr_count( $path, '/' );
        if ( $depth === 0 ) return 'spoke';
        return 'supporting';
    }

    // ── Layer rules ───────────────────────────────────────────────────────

    /**
     * Return the SEO rules array for a given silo layer.
     *
     * @param string $layer  hub|spoke|supporting
     * @return string[]
     */
    public static function get_layer_rules( $layer ) {
        $rules = [
            'hub' => [
                'H1 must contain the primary keyword.',
                'First paragraph: primary keyword + location modifier within first 100 words.',
                'Must internally link to all spoke/supporting pages in the silo.',
                'Content must establish topical authority — not thin.',
            ],
            'spoke' => [
                'Targets a specific supporting keyword.',
                'Must contain an internal link back to the hub page.',
                'H1 targets the spoke keyword; H2s address supporting questions.',
            ],
            'supporting' => [
                'Informational intent — answer the specific question completely.',
                'Must link to the relevant spoke or hub page.',
                'H1 targets long-tail or question-based keyword.',
            ],
        ];
        return $rules[ $layer ] ?? $rules['spoke'];
    }

    // ── Heading hierarchy ─────────────────────────────────────────────────

    /**
     * Validate a heading map for hierarchy violations.
     *
     * @param array $heading_map  Each entry: ['tag'=>'h2','widget_id'=>'...']
     * @return array  Violation objects with widget_id, tag, issue, fix keys.
     */
    public static function validate_heading_hierarchy( $heading_map ) {
        $violations = [];
        $prev_level = 0;
        $h1_count   = 0;

        foreach ( $heading_map as $h ) {
            $level = intval( ltrim( $h['tag'] ?? 'h2', 'hH' ) );
            if ( $level === 1 ) $h1_count++;
            if ( $prev_level > 0 && $level > $prev_level + 1 ) {
                $violations[] = [
                    'widget_id' => $h['widget_id'] ?? '',
                    'tag'       => $h['tag'] ?? '',
                    'issue'     => "Hierarchy gap: H{$prev_level} → H{$level} (skipped H" . ( $prev_level + 1 ) . ")",
                    'fix'       => 'Change to H' . ( $prev_level + 1 ),
                ];
            }
            $prev_level = $level;
        }

        if ( $h1_count > 1 ) {
            $violations[] = [
                'widget_id' => '',
                'tag'       => 'h1',
                'issue'     => "Multiple H1 tags found ({$h1_count}). Only one H1 allowed per page.",
                'fix'       => 'Change additional H1s to H2.',
            ];
        }

        return $violations;
    }

    // ── Image alt analysis ────────────────────────────────────────────────

    /**
     * Analyse an image alt attribute for common SEO issues.
     *
     * @param string $alt_text
     * @param string $primary_keyword
     * @return array  { issues: string[], current_alt: string }
     */
    public static function analyze_image_alt( $alt_text, $primary_keyword = '' ) {
        $issues = [];

        if ( empty( $alt_text ) ) {
            $issues[] = 'Alt tag is empty — required for accessibility and SEO.';
        } elseif ( strlen( $alt_text ) < 10 ) {
            $issues[] = 'Alt tag is too short (under 10 characters).';
        } elseif ( strlen( $alt_text ) > 125 ) {
            $issues[] = 'Alt tag is too long (over 125 characters).';
        }

        if ( $primary_keyword && ! empty( $alt_text ) && stripos( $alt_text, $primary_keyword ) === false ) {
            $issues[] = "Alt tag does not contain the primary keyword: \"{$primary_keyword}\".";
        }

        return [ 'issues' => $issues, 'current_alt' => $alt_text ];
    }

    // ── Image recommendations ─────────────────────────────────────────────

    /**
     * Return a recommended image type/subject for a given content type.
     *
     * @param string $content_type     service|testimonial|faq|hero|location|default
     * @param string $section_position body|top|bottom
     * @return array  { type: string, subject: string }
     */
    public static function get_image_recommendation( $content_type, $section_position = 'body' ) {
        $map = [
            'service'     => [ 'type' => 'photo', 'subject' => 'Professional technician performing the service' ],
            'testimonial' => [ 'type' => 'photo', 'subject' => 'Team photo or completed job site photo' ],
            'faq'         => [ 'type' => 'none',  'subject' => '' ],
            'hero'        => [ 'type' => 'photo', 'subject' => 'Primary service action photo, above the fold' ],
            'location'    => [ 'type' => 'photo', 'subject' => 'Local landmark or service area photo' ],
            'default'     => [ 'type' => 'photo', 'subject' => 'Relevant professional photo for this content' ],
        ];
        return $map[ $content_type ] ?? $map['default'];
    }

    /**
     * Build an AI image-generation prompt.
     *
     * @param string $subject
     * @param string $type
     * @param string $business_type
     * @param string $location
     * @return string
     */
    public static function build_ai_image_prompt( $subject, $type, $business_type, $location ) {
        return "Professional {$type} of {$subject}. Context: {$business_type} business in {$location}. " .
               'Clean, well-lit, high quality, no text overlays, suitable for a business website hero or section image.';
    }

    // ── Entity context ────────────────────────────────────────────────────

    /**
     * Return the site's entity context from WP options.
     *
     * @return array
     */
    public static function get_entity_context() {
        return [
            'business_name' => get_option( 'siloq_business_name', get_bloginfo( 'name' ) ),
            'business_type' => get_option( 'siloq_business_type', '' ),
            'city'          => get_option( 'siloq_city', '' ),
            'state'         => get_option( 'siloq_state', '' ),
            'phone'         => get_option( 'siloq_phone', '' ),
        ];
    }
}
