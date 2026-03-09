<?php
/**
 * Siloq Content Extractor
 *
 * Extracts text, links, headings, and structured content from WordPress pages
 * regardless of the page builder in use. Supports Elementor, Divi,
 * Beaver Builder, Gutenberg, and Classic editor.
 *
 * @package Siloq
 * @since 1.5.38
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Content_Extractor {

    // =========================================================================
    // PUBLIC ENTRY POINT
    // =========================================================================

    public static function extract( $post_id ) {
        $result = array(
            'builder'     => 'classic',
            'raw_text'    => '',
            'links'       => array(),
            'headings'    => array(),
            'faq_items'   => array(),
            'schema_tags' => array(),
            'word_count'  => 0,
            'has_faq'     => false,
            'errors'      => array(),
        );

        if ( ! $post_id ) {
            $result['errors'][] = 'Invalid post ID';
            return $result;
        }

        $builder          = self::detect_builder( $post_id );
        $result['builder'] = $builder;

        switch ( $builder ) {
            case 'elementor':
                self::extract_elementor( $post_id, $result );
                break;
            case 'divi':
                self::extract_divi( $post_id, $result );
                break;
            case 'beaver-builder':
                self::extract_beaver( $post_id, $result );
                break;
            case 'gutenberg':
                self::extract_gutenberg( $post_id, $result );
                break;
            default:
                self::extract_classic( $post_id, $result );
                break;
        }

        self::extract_schema( $post_id, $result );

        $result['raw_text']  = trim( $result['raw_text'] );
        $result['word_count'] = str_word_count( $result['raw_text'] );
        $result['has_faq']   = ! empty( $result['faq_items'] );

        return $result;
    }

    // =========================================================================
    // BUILDER DETECTION
    // =========================================================================

    private static function detect_builder( $post_id ) {
        if ( function_exists( 'siloq_detect_builder' ) ) {
            return siloq_detect_builder( $post_id );
        }
        if ( class_exists( 'Elementor\Plugin' ) && get_post_meta( $post_id, '_elementor_edit_mode', true ) === 'builder' ) {
            return 'elementor';
        }
        if ( defined( 'DIVI_VERSION' ) && get_post_meta( $post_id, '_et_pb_use_builder', true ) === 'on' ) {
            return 'divi';
        }
        if ( class_exists( 'FLBuilderLoader' ) && get_post_meta( $post_id, '_fl_builder_enabled', true ) ) {
            return 'beaver-builder';
        }
        $content = get_post_field( 'post_content', $post_id );
        if ( function_exists( 'has_blocks' ) && has_blocks( $content ) ) {
            return 'gutenberg';
        }
        return 'classic';
    }

    // =========================================================================
    // ELEMENTOR EXTRACTOR
    // =========================================================================

    private static function extract_elementor( $post_id, &$result ) {
        $raw = get_post_meta( $post_id, '_elementor_data', true );
        if ( empty( $raw ) ) {
            $result['errors'][] = 'Elementor: _elementor_data meta is empty';
            self::extract_classic( $post_id, $result );
            return;
        }

        // Guard: skip json_decode on excessively large payloads to prevent
        // memory exhaustion and timeouts. 512 KB of raw Elementor JSON is
        // already massive — anything larger than 1 MB is a red flag.
        // Fall back to classic extraction (post_content or wp_get_post_content).
        if ( strlen( $raw ) > 1048576 ) { // 1 MB
            $result['errors'][] = 'Elementor: _elementor_data exceeds 1 MB — falling back to classic extract';
            self::extract_classic( $post_id, $result );
            return;
        }

        $data = json_decode( $raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $result['errors'][] = 'Elementor: JSON decode failed';
            return;
        }
        self::walk_elementor_elements( $data, $result );
        $result['links']    = self::deduplicate( $result['links'], 'href' );
        $result['headings'] = array_values( $result['headings'] );
        $result['faq_items'] = array_values( $result['faq_items'] );
    }

    private static function walk_elementor_elements( $elements, &$result ) {
        if ( empty( $elements ) || ! is_array( $elements ) ) {
            return;
        }
        foreach ( $elements as $element ) {
            $el_type     = $element['elType']     ?? '';
            $widget_type = $element['widgetType'] ?? '';
            $settings    = $element['settings']   ?? array();

            if ( ! empty( $element['elements'] ) ) {
                self::walk_elementor_elements( $element['elements'], $result );
            }

            if ( $el_type !== 'widget' ) {
                continue;
            }

            // Heading
            if ( $widget_type === 'heading' ) {
                $text = wp_strip_all_tags( $settings['title'] ?? '' );
                $tag  = $settings['header_size'] ?? 'h2';
                preg_match( '/\d/', $tag, $m );
                $level = ! empty( $m[0] ) ? (int) $m[0] : 2;
                if ( $text ) {
                    $result['headings'][] = array( 'level' => $level, 'text' => $text );
                    $result['raw_text']  .= ' ' . $text;
                }
                continue;
            }

            // Text / HTML widgets
            if ( in_array( $widget_type, array( 'text-editor', 'html', 'theme-post-content' ), true ) ) {
                $html = $settings['editor'] ?? $settings['html'] ?? $settings['content'] ?? '';
                self::parse_html_block( $html, $result );
                continue;
            }

            // Icon Box / Image Box
            if ( in_array( $widget_type, array( 'icon-box', 'image-box' ), true ) ) {
                $title = wp_strip_all_tags( $settings['title_text'] ?? '' );
                $desc  = wp_strip_all_tags( $settings['description_text'] ?? '' );
                if ( $title ) { $result['headings'][] = array( 'level' => 3, 'text' => $title ); }
                $result['raw_text'] .= ' ' . $title . ' ' . $desc;
                continue;
            }

            // Button
            if ( $widget_type === 'button' ) {
                $text = wp_strip_all_tags( $settings['text'] ?? '' );
                $url  = $settings['link']['url'] ?? '';
                if ( $text && $url ) {
                    $result['links'][]  = self::classify_link( $text, $url );
                    $result['raw_text'] .= ' ' . $text;
                }
                continue;
            }

            // Accordion / Toggle / FAQ — the most commonly missed
            if ( in_array( $widget_type, array( 'accordion', 'toggle', 'faq' ), true ) ) {
                foreach ( array( 'tabs', 'tab_items', 'accordion', 'items' ) as $rk ) {
                    if ( empty( $settings[ $rk ] ) ) continue;
                    foreach ( $settings[ $rk ] as $item ) {
                        $q = wp_strip_all_tags( $item['tab_title'] ?? $item['title'] ?? '' );
                        $a = wp_strip_all_tags( $item['tab_content'] ?? $item['content'] ?? '' );
                        if ( $q ) {
                            $result['faq_items'][] = array( 'question' => $q, 'answer' => $a );
                            $result['raw_text']   .= ' ' . $q . ' ' . $a;
                            $result['headings'][]  = array( 'level' => 3, 'text' => $q );
                        }
                    }
                }
                continue;
            }

            // Icon List
            if ( $widget_type === 'icon-list' ) {
                foreach ( $settings['icon_list'] ?? array() as $item ) {
                    $text = wp_strip_all_tags( $item['text'] ?? '' );
                    $url  = $item['link']['url'] ?? '';
                    $result['raw_text'] .= ' ' . $text;
                    if ( $url ) $result['links'][] = self::classify_link( $text, $url );
                }
                continue;
            }

            // Tabs
            if ( $widget_type === 'tabs' ) {
                foreach ( $settings['tabs'] ?? array() as $tab ) {
                    $result['raw_text'] .= ' ' . wp_strip_all_tags( $tab['tab_title'] ?? '' );
                    $result['raw_text'] .= ' ' . wp_strip_all_tags( $tab['tab_content'] ?? '' );
                }
                continue;
            }

            // Testimonial
            if ( $widget_type === 'testimonial' ) {
                $result['raw_text'] .= ' ' . wp_strip_all_tags( $settings['testimonial_content'] ?? '' );
                $result['raw_text'] .= ' ' . wp_strip_all_tags( $settings['testimonial_name'] ?? '' );
                continue;
            }

            // Catch-all generic fields
            foreach ( array( 'title', 'description', 'content', 'text', 'html', 'editor', 'caption', 'sub_title' ) as $field ) {
                if ( ! empty( $settings[ $field ] ) && is_string( $settings[ $field ] ) ) {
                    $result['raw_text'] .= ' ' . wp_strip_all_tags( $settings[ $field ] );
                }
            }
            foreach ( array( 'editor', 'html', 'content' ) as $html_field ) {
                if ( ! empty( $settings[ $html_field ] ) ) {
                    self::extract_links_from_html( $settings[ $html_field ], $result );
                }
            }
        }
    }

    // =========================================================================
    // DIVI EXTRACTOR
    // =========================================================================

    private static function extract_divi( $post_id, &$result ) {
        $content = get_post_field( 'post_content', $post_id );
        if ( empty( $content ) ) {
            $result['errors'][] = 'Divi: post_content is empty';
            return;
        }
        $text_patterns = array(
            '/\[et_pb_text[^\]]*\](.*?)\[\/et_pb_text\]/s',
            '/\[et_pb_blurb[^\]]*\](.*?)\[\/et_pb_blurb\]/s',
            '/\[et_pb_cta[^\]]*\](.*?)\[\/et_pb_cta\]/s',
            '/\[et_pb_accordion_item[^\]]*\](.*?)\[\/et_pb_accordion_item\]/s',
            '/\[et_pb_testimonial[^\]]*\](.*?)\[\/et_pb_testimonial\]/s',
        );
        foreach ( $text_patterns as $pattern ) {
            if ( preg_match_all( $pattern, $content, $matches ) ) {
                foreach ( $matches[1] as $html ) {
                    self::parse_html_block( $html, $result );
                }
            }
        }
        $faq_pattern = '/\[et_pb_accordion_item[^\]]*title="([^"]+)"[^\]]*\](.*?)\[\/et_pb_accordion_item\]/s';
        if ( preg_match_all( $faq_pattern, $content, $matches ) ) {
            foreach ( $matches[1] as $i => $question ) {
                $result['faq_items'][] = array(
                    'question' => html_entity_decode( $question ),
                    'answer'   => wp_strip_all_tags( $matches[2][ $i ] ),
                );
            }
        }
        $result['links'] = self::deduplicate( $result['links'], 'href' );
    }

    // =========================================================================
    // BEAVER BUILDER EXTRACTOR
    // =========================================================================

    private static function extract_beaver( $post_id, &$result ) {
        $builder_data = get_post_meta( $post_id, '_fl_builder_data', true );
        if ( ! empty( $builder_data ) ) {
            if ( is_string( $builder_data ) ) {
                $builder_data = maybe_unserialize( $builder_data );
            }
            if ( is_array( $builder_data ) || is_object( $builder_data ) ) {
                foreach ( (array) $builder_data as $node ) {
                    self::extract_beaver_node( (array) $node, $result );
                }
                $result['links'] = self::deduplicate( $result['links'], 'href' );
                return;
            }
        }
        $content = get_post_field( 'post_content', $post_id );
        if ( $content ) {
            self::parse_html_block( $content, $result );
        }
    }

    private static function extract_beaver_node( $node, &$result ) {
        $type     = $node['type']     ?? '';
        $settings = (array) ( $node['settings'] ?? array() );
        if ( $type === 'module' ) {
            $module = $node['module'] ?? '';
            if ( in_array( $module, array( 'rich-text', 'editor', 'html' ), true ) ) {
                self::parse_html_block( $settings['text'] ?? $settings['html'] ?? '', $result );
            }
            if ( $module === 'heading' ) {
                $text  = wp_strip_all_tags( $settings['heading'] ?? '' );
                $level = (int) ltrim( $settings['tag'] ?? 'h2', 'h' );
                if ( $text ) {
                    $result['headings'][] = array( 'level' => max( 1, $level ), 'text' => $text );
                    $result['raw_text']  .= ' ' . $text;
                }
            }
            if ( $module === 'accordion' ) {
                foreach ( $settings['items'] ?? array() as $item ) {
                    $q = wp_strip_all_tags( $item['label'] ?? '' );
                    $a = wp_strip_all_tags( $item['content'] ?? '' );
                    if ( $q ) {
                        $result['faq_items'][] = array( 'question' => $q, 'answer' => $a );
                        $result['raw_text']   .= ' ' . $q . ' ' . $a;
                    }
                }
            }
        }
        if ( ! empty( $node['children'] ) ) {
            foreach ( (array) $node['children'] as $child ) {
                self::extract_beaver_node( (array) $child, $result );
            }
        }
    }

    // =========================================================================
    // GUTENBERG EXTRACTOR
    // =========================================================================

    private static function extract_gutenberg( $post_id, &$result ) {
        $content = get_post_field( 'post_content', $post_id );
        if ( empty( $content ) ) {
            $result['errors'][] = 'Gutenberg: post_content is empty';
            return;
        }
        if ( ! function_exists( 'parse_blocks' ) ) {
            self::parse_html_block( $content, $result );
            return;
        }
        $blocks = parse_blocks( $content );
        self::walk_gutenberg_blocks( $blocks, $result );
        $result['links'] = self::deduplicate( $result['links'], 'href' );
    }

    private static function walk_gutenberg_blocks( $blocks, &$result ) {
        foreach ( $blocks as $block ) {
            $name  = $block['blockName'] ?? '';
            $attrs = $block['attrs']     ?? array();
            $html  = $block['innerHTML'] ?? '';
            switch ( $name ) {
                case 'core/heading':
                    $text  = wp_strip_all_tags( $html );
                    $level = (int) ( $attrs['level'] ?? 2 );
                    if ( $text ) {
                        $result['headings'][] = array( 'level' => $level, 'text' => $text );
                        $result['raw_text']  .= ' ' . $text;
                    }
                    break;
                case 'core/paragraph':
                case 'core/html':
                    self::parse_html_block( $html, $result );
                    break;
                case 'yoast/faq-block':
                case 'aioseo/faq':
                    self::parse_html_block( $html, $result );
                    foreach ( $attrs['questions'] ?? array() as $q ) {
                        $question = wp_strip_all_tags( $q['jsonQuestion'] ?? $q['question'] ?? '' );
                        $answer   = wp_strip_all_tags( $q['jsonAnswer']   ?? $q['answer']   ?? '' );
                        if ( $question ) {
                            $result['faq_items'][] = array( 'question' => $question, 'answer' => $answer );
                        }
                    }
                    break;
                default:
                    if ( $html ) self::parse_html_block( $html, $result );
                    break;
            }
            if ( ! empty( $block['innerBlocks'] ) ) {
                self::walk_gutenberg_blocks( $block['innerBlocks'], $result );
            }
        }
    }

    // =========================================================================
    // CLASSIC EDITOR
    // =========================================================================

    private static function extract_classic( $post_id, &$result ) {
        $content = get_post_field( 'post_content', $post_id );
        if ( $content ) {
            self::parse_html_block( $content, $result );
        }
    }

    // =========================================================================
    // SCHEMA EXTRACTOR
    // =========================================================================

    private static function extract_schema( $post_id, &$result ) {
        $meta_schema = get_post_meta( $post_id, 'siloq_schema_markup', true );
        if ( $meta_schema ) {
            $decoded = json_decode( $meta_schema, true );
            if ( $decoded ) $result['schema_tags'][] = $decoded;
        }
        $content = get_post_field( 'post_content', $post_id );
        if ( $content ) {
            preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $content, $matches );
            foreach ( $matches[1] as $json_str ) {
                $decoded = json_decode( trim( $json_str ), true );
                if ( $decoded ) $result['schema_tags'][] = $decoded;
            }
        }
    }

    // =========================================================================
    // HTML PARSER
    // =========================================================================

    private static function parse_html_block( $html, &$result ) {
        if ( empty( $html ) ) return;
        $dom = self::load_dom( $html );
        if ( ! $dom ) {
            $result['raw_text'] .= ' ' . wp_strip_all_tags( $html );
            return;
        }
        $xpath = new DOMXPath( $dom );
        $body  = $dom->getElementsByTagName( 'body' )->item( 0 );
        if ( $body ) {
            $result['raw_text'] .= ' ' . trim( $body->textContent );
        }
        foreach ( $xpath->query( '//a[@href]' ) as $a ) {
            $href = trim( $a->getAttribute( 'href' ) );
            $text = trim( $a->textContent );
            if ( $href && $href !== '#' && strpos( $href, 'javascript:' ) === false ) {
                $result['links'][] = self::classify_link( $text, $href );
            }
        }
        foreach ( $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $heading ) {
            $level = (int) substr( strtolower( $heading->nodeName ), 1 );
            $text  = trim( $heading->textContent );
            if ( $text ) {
                $result['headings'][] = array( 'level' => $level, 'text' => $text );
            }
        }
    }

    private static function extract_links_from_html( $html, &$result ) {
        if ( empty( $html ) ) return;
        $dom = self::load_dom( $html );
        if ( ! $dom ) return;
        $xpath = new DOMXPath( $dom );
        foreach ( $xpath->query( '//a[@href]' ) as $a ) {
            $href = trim( $a->getAttribute( 'href' ) );
            $text = trim( $a->textContent );
            if ( $href && $href !== '#' ) {
                $result['links'][] = self::classify_link( $text, $href );
            }
        }
    }

    // =========================================================================
    // UTILITIES
    // =========================================================================

    private static function load_dom( $html ) {
        if ( empty( $html ) ) return null;
        $dom  = new DOMDocument( '1.0', 'UTF-8' );
        $prev = libxml_use_internal_errors( true );
        $ok   = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );
        return $ok ? $dom : null;
    }

    private static function classify_link( $text, $href ) {
        $site_host = parse_url( get_site_url(), PHP_URL_HOST );
        $link_host = parse_url( $href, PHP_URL_HOST );
        $is_internal = empty( $link_host ) || $link_host === $site_host;
        return array(
            'text'        => trim( $text ),
            'href'        => $href,
            'is_internal' => $is_internal,
        );
    }

    private static function deduplicate( $items, $key ) {
        $seen   = array();
        $unique = array();
        foreach ( $items as $item ) {
            $val = $item[ $key ] ?? '';
            if ( ! isset( $seen[ $val ] ) ) {
                $seen[ $val ] = true;
                $unique[]     = $item;
            }
        }
        return $unique;
    }

} // end class Siloq_Content_Extractor
