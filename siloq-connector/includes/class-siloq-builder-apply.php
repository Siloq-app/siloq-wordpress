<?php
/**
 * Siloq Builder Apply — Official API approach for top 10 page builders
 * Section 06 of the Siloq V1 Development Specification
 *
 * Uses each builder's official PHP API to apply heading changes safely.
 * Falls back to manual_action for WPBakery and Oxygen (no stable API).
 */

if (!defined('ABSPATH')) exit;

class Siloq_Builder_Apply {

    /**
     * Central apply router — routes heading changes to the correct builder API.
     */
    public static function apply_heading_change($post_id, $old_text, $new_text, $level = null) {
        $builder = siloq_detect_builder($post_id);

        $result = match($builder) {
            'gutenberg'   => self::update_gutenberg_heading($post_id, $old_text, $new_text, $level),
            'elementor'   => self::update_elementor_heading($post_id, $old_text, $new_text),
            'divi'        => self::update_divi_heading($post_id, $old_text, $new_text),
            'beaver'      => self::update_beaver_heading($post_id, $old_text, $new_text),
            'cornerstone' => self::update_cornerstone_heading($post_id, $old_text, $new_text),
            'bricks'      => self::update_bricks_heading($post_id, $old_text, $new_text),
            'siteorigin'  => self::update_siteorigin_heading($post_id, $old_text, $new_text),
            'avada'       => self::update_avada_heading($post_id, $old_text, $new_text),
            'wpbakery'    => self::manual_action($post_id, $old_text, $new_text, 'WPBakery'),
            'oxygen'      => self::manual_action($post_id, $old_text, $new_text, 'Oxygen'),
            'standard'    => self::update_gutenberg_heading($post_id, $old_text, $new_text, $level),
            default       => array('status' => 'error', 'message' => 'Unknown builder: ' . $builder),
        };

        $result['builder'] = $builder;
        $result['post_id'] = $post_id;

        // Log result
        if (function_exists('siloq_log')) {
            siloq_log('builder_apply', $result);
        }

        return $result;
    }

    /**
     * Apply content block insertion for any builder.
     * Used for adding new sections (CTAs, testimonials, FAQ blocks).
     */
    public static function apply_content_block($post_id, $content_html, $widget_type = 'text', $position = 'append') {
        $builder = siloq_detect_builder($post_id);

        return match($builder) {
            'gutenberg'   => self::append_gutenberg_block($post_id, $content_html, $widget_type),
            'elementor'   => self::append_elementor_widget($post_id, $content_html, $widget_type),
            'divi'        => self::append_divi_module($post_id, $content_html),
            'beaver'      => self::append_beaver_module($post_id, $content_html, $widget_type),
            'siteorigin'  => self::append_siteorigin_widget($post_id, $content_html),
            'standard'    => self::append_gutenberg_block($post_id, $content_html, $widget_type),
            'wpbakery', 'oxygen' => self::manual_action($post_id, '', $content_html, ucfirst($builder)),
            default       => self::manual_action($post_id, '', $content_html, ucfirst($builder)),
        };
    }

    // ─── 1. GUTENBERG ────────────────────────────────────────────────────────

    private static function update_gutenberg_heading($post_id, $old_text, $new_text, $level = null) {
        $content = get_post_field('post_content', $post_id);
        $blocks = parse_blocks($content);

        $found = false;
        $blocks = self::traverse_gutenberg_blocks($blocks, function($block) use ($old_text, $new_text, $level, &$found) {
            if ($block['blockName'] === 'core/heading') {
                $text = strip_tags($block['innerHTML']);
                if (trim($text) === trim($old_text)) {
                    $new_level = $level ?? ($block['attrs']['level'] ?? 2);
                    $block['attrs']['content'] = $new_text;
                    $block['attrs']['level'] = $new_level;
                    $tag = 'h' . $new_level;
                    $block['innerHTML'] = "<{$tag}>" . esc_html($new_text) . "</{$tag}>";
                    $block['innerContent'] = array($block['innerHTML']);
                    $found = true;
                }
            }
            return $block;
        });

        if (!$found) return array('status' => 'not_found', 'message' => 'Heading not found in Gutenberg blocks');

        $result = wp_update_post(array('ID' => $post_id, 'post_content' => serialize_blocks($blocks)), true);
        if (is_wp_error($result)) return array('status' => 'error', 'message' => $result->get_error_message());
        return array('status' => 'applied');
    }

    private static function append_gutenberg_block($post_id, $content_html, $widget_type) {
        $content = get_post_field('post_content', $post_id);

        $block = match($widget_type) {
            'heading'  => "<!-- wp:heading -->\n" . $content_html . "\n<!-- /wp:heading -->",
            'button'   => "<!-- wp:buttons -->\n<!-- wp:button -->\n" . $content_html . "\n<!-- /wp:button -->\n<!-- /wp:buttons -->",
            default    => "<!-- wp:paragraph -->\n" . $content_html . "\n<!-- /wp:paragraph -->",
        };

        $updated = $content . "\n\n" . $block;
        $result = wp_update_post(array('ID' => $post_id, 'post_content' => $updated), true);
        if (is_wp_error($result)) return array('status' => 'error', 'message' => $result->get_error_message());
        return array('status' => 'applied', 'method' => 'gutenberg_append');
    }

    private static function traverse_gutenberg_blocks($blocks, $callback) {
        foreach ($blocks as &$block) {
            $block = $callback($block);
            if (!empty($block['innerBlocks'])) {
                $block['innerBlocks'] = self::traverse_gutenberg_blocks($block['innerBlocks'], $callback);
            }
        }
        return $blocks;
    }

    // ─── 2. ELEMENTOR ────────────────────────────────────────────────────────

    private static function update_elementor_heading($post_id, $old_text, $new_text) {
        if (!class_exists('\\Elementor\\Plugin')) {
            return array('status' => 'error', 'message' => 'Elementor not active');
        }

        $document = \Elementor\Plugin::$instance->documents->get($post_id);
        if (!$document) return array('status' => 'error', 'message' => 'Elementor document not found');

        $elements = $document->get_elements_data();
        $found = false;
        $elements = self::traverse_elementor_elements($elements, $old_text, $new_text, $found);

        if (!$found) return array('status' => 'not_found', 'message' => 'Heading not found in Elementor elements');

        $document->save(array('elements' => $elements));

        // Clear CSS cache — required or live page shows stale content
        \Elementor\Plugin::$instance->files_manager->clear_cache();

        return array('status' => 'applied');
    }

    private static function append_elementor_widget($post_id, $content_html, $widget_type) {
        if (!class_exists('\\Elementor\\Plugin')) {
            return array('status' => 'error', 'message' => 'Elementor not active');
        }

        $document = \Elementor\Plugin::$instance->documents->get($post_id);
        if (!$document) return array('status' => 'error', 'message' => 'Document not found');

        $elements = $document->get_elements_data();

        $el_widget_type = match($widget_type) {
            'heading' => 'heading',
            'button'  => 'button',
            default   => 'text-editor',
        };
        $settings_key = ($el_widget_type === 'heading') ? 'title' : 'editor';

        $new_widget = array(
            'id'         => \Elementor\Utils::generate_random_string(),
            'elType'     => 'widget',
            'widgetType' => $el_widget_type,
            'settings'   => array($settings_key => $content_html),
            'elements'   => array(),
        );

        // Find last section → last column → append widget
        if (!empty($elements)) {
            $last_section_idx = count($elements) - 1;
            $last_section = &$elements[$last_section_idx];
            if (!empty($last_section['elements'])) {
                $last_col_idx = count($last_section['elements']) - 1;
                $last_section['elements'][$last_col_idx]['elements'][] = $new_widget;
            }
        }

        $document->save(array('elements' => $elements));
        \Elementor\Plugin::$instance->files_manager->clear_cache();

        return array('status' => 'applied', 'method' => 'elementor_append');
    }

    private static function traverse_elementor_elements(&$elements, $old, $new, &$found) {
        foreach ($elements as &$el) {
            if (($el['elType'] ?? '') === 'widget') {
                // Heading widget
                if (($el['widgetType'] ?? '') === 'heading' && ($el['settings']['title'] ?? '') === $old) {
                    $el['settings']['title'] = $new;
                    $found = true;
                }
                // Text editor widget — check for heading inside HTML
                if (($el['widgetType'] ?? '') === 'text-editor' && isset($el['settings']['editor'])) {
                    if (strpos($el['settings']['editor'], $old) !== false) {
                        $el['settings']['editor'] = str_replace($old, $new, $el['settings']['editor']);
                        $found = true;
                    }
                }
            }
            if (!empty($el['elements'])) {
                $el['elements'] = self::traverse_elementor_elements($el['elements'], $old, $new, $found);
            }
        }
        return $elements;
    }

    // ─── 3. DIVI ─────────────────────────────────────────────────────────────

    private static function update_divi_heading($post_id, $old_text, $new_text) {
        if (!function_exists('et_fb_process_shortcode')) {
            return array('status' => 'error', 'message' => 'Divi not active');
        }

        $content = get_post_field('post_content', $post_id);
        $pattern = '/(<h[1-6][^>]*>)' . preg_quote($old_text, '/') . '(<\/h[1-6]>)/';
        $new_content = preg_replace($pattern, '${1}' . esc_html($new_text) . '${2}', $content);

        if ($new_content === $content) return array('status' => 'not_found', 'message' => 'Heading not found in Divi content');

        wp_update_post(array('ID' => $post_id, 'post_content' => $new_content));

        if (function_exists('et_core_page_resource_delete_static_all')) {
            et_core_page_resource_delete_static_all();
        }
        return array('status' => 'applied');
    }

    private static function append_divi_module($post_id, $content_html) {
        $content = get_post_field('post_content', $post_id);
        $divi_block = '[et_pb_section][et_pb_row][et_pb_column type="4_4"][et_pb_text]' . $content_html . '[/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]';
        wp_update_post(array('ID' => $post_id, 'post_content' => $content . "\n" . $divi_block));
        if (function_exists('et_core_page_resource_delete_static_all')) {
            et_core_page_resource_delete_static_all();
        }
        return array('status' => 'applied', 'method' => 'divi_append');
    }

    // ─── 4. BEAVER BUILDER ───────────────────────────────────────────────────

    private static function update_beaver_heading($post_id, $old_text, $new_text) {
        if (!class_exists('FLBuilderModel')) {
            return array('status' => 'error', 'message' => 'Beaver Builder not active');
        }

        $data = FLBuilderModel::get_layout_data('published', $post_id);
        $found = false;

        if (is_array($data) || is_object($data)) {
            foreach ($data as &$node) {
                if (isset($node->settings) && isset($node->settings->heading)) {
                    if ($node->settings->heading === $old_text) {
                        $node->settings->heading = $new_text;
                        $found = true;
                    }
                }
                // Also check text editor modules
                if (isset($node->settings) && isset($node->settings->text)) {
                    if (strpos($node->settings->text, $old_text) !== false) {
                        $node->settings->text = str_replace($old_text, $new_text, $node->settings->text);
                        $found = true;
                    }
                }
            }
        }

        if (!$found) return array('status' => 'not_found', 'message' => 'Heading not found in Beaver Builder layout');

        FLBuilderModel::save_layout_data($data, 'published', $post_id);
        // fl_builder_after_save_layout hook handles cache automatically
        return array('status' => 'applied');
    }

    private static function append_beaver_module($post_id, $content_html, $widget_type) {
        if (!class_exists('FLBuilderModel')) {
            return self::manual_action($post_id, '', $content_html, 'Beaver Builder');
        }
        // Beaver Builder's API for programmatic module addition is complex
        // For V1, fall back to manual action for content block insertion
        return self::manual_action($post_id, '', $content_html, 'Beaver Builder');
    }

    // ─── 5. CORNERSTONE ──────────────────────────────────────────────────────

    private static function update_cornerstone_heading($post_id, $old_text, $new_text) {
        $data_raw = get_post_meta($post_id, '_cornerstone_data', true);
        if (!$data_raw) return array('status' => 'error', 'message' => 'No Cornerstone data found');

        $data = json_decode($data_raw, true);
        if (!is_array($data)) return array('status' => 'error', 'message' => 'Invalid Cornerstone data');

        $found = false;
        $data = self::traverse_cornerstone($data, $old_text, $new_text, $found);

        if (!$found) return array('status' => 'not_found', 'message' => 'Heading not found in Cornerstone data');

        // Write via REST endpoint if available, otherwise direct meta
        $rest_url = rest_url('cornerstone/v1/content/' . $post_id);
        $response = wp_remote_post($rest_url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode(array('content' => $data)),
            'cookies' => $_COOKIE, // Pass current auth
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
            // Fallback: direct meta write
            update_post_meta($post_id, '_cornerstone_data', wp_slash(wp_json_encode($data)));
        }

        delete_post_meta($post_id, '_cs_generated_styles');
        return array('status' => 'applied');
    }

    private static function traverse_cornerstone($elements, $old, $new, &$found) {
        foreach ($elements as &$el) {
            $type = $el['type'] ?? $el['_type'] ?? '';
            if (in_array($type, array('cs_headline', 'x_custom_headline', 'headline'))) {
                if (strip_tags($el['content'] ?? '') === $old || ($el['_label'] ?? '') === $old) {
                    $el['content'] = $new;
                    $found = true;
                }
            }
            foreach (array('_modules', '_children', 'elements') as $child_key) {
                if (!empty($el[$child_key])) {
                    $el[$child_key] = self::traverse_cornerstone($el[$child_key], $old, $new, $found);
                }
            }
        }
        return $elements;
    }

    // ─── 6. BRICKS ───────────────────────────────────────────────────────────

    private static function update_bricks_heading($post_id, $old_text, $new_text) {
        $data = get_post_meta($post_id, '_bricks_page_content_2', true);
        if (!$data) return array('status' => 'error', 'message' => 'No Bricks data found');

        if (is_string($data)) $data = json_decode($data, true);
        if (!is_array($data)) return array('status' => 'error', 'message' => 'Invalid Bricks data');

        $found = false;
        $data = self::traverse_bricks($data, $old_text, $new_text, $found);

        if (!$found) return array('status' => 'not_found', 'message' => 'Heading not found in Bricks data');

        update_post_meta($post_id, '_bricks_page_content_2', wp_slash(wp_json_encode($data)));
        delete_post_meta($post_id, '_bricks_css');
        delete_post_meta($post_id, '_bricks_css_hash');
        return array('status' => 'applied');
    }

    private static function traverse_bricks($elements, $old, $new, &$found) {
        foreach ($elements as &$el) {
            $type = $el['elType'] ?? $el['name'] ?? '';
            if (in_array($type, array('text-basic', 'heading', 'block'))) {
                foreach (array('content', 'text', 'tag_content') as $key) {
                    if (isset($el['settings'][$key]) && strip_tags($el['settings'][$key]) === $old) {
                        $el['settings'][$key] = $new;
                        $found = true;
                    }
                }
            }
            if (!empty($el['children'])) {
                $el['children'] = self::traverse_bricks($el['children'], $old, $new, $found);
            }
        }
        return $elements;
    }

    // ─── 7. SITEORIGIN ──────────────────────────────────────────────────────

    private static function update_siteorigin_heading($post_id, $old_text, $new_text) {
        if (!function_exists('siteorigin_panels_get_panels_data')) {
            return array('status' => 'error', 'message' => 'SiteOrigin not active');
        }

        $data = siteorigin_panels_get_panels_data($post_id);
        $found = false;

        if (isset($data['widgets']) && is_array($data['widgets'])) {
            foreach ($data['widgets'] as &$widget) {
                if (isset($widget['title']) && $widget['title'] === $old_text) {
                    $widget['title'] = $new_text;
                    $found = true;
                }
                if (isset($widget['text']) && strip_tags($widget['text']) === $old_text) {
                    $widget['text'] = $new_text;
                    $found = true;
                }
            }
        }

        if (!$found) return array('status' => 'not_found', 'message' => 'Heading not found in SiteOrigin layout');

        siteorigin_panels_save_panels_data($post_id, $data);
        return array('status' => 'applied');
    }

    private static function append_siteorigin_widget($post_id, $content_html) {
        if (!function_exists('siteorigin_panels_get_panels_data')) {
            return self::manual_action($post_id, '', $content_html, 'SiteOrigin');
        }
        $data = siteorigin_panels_get_panels_data($post_id);
        // Add a new text widget
        $data['widgets'][] = array(
            'panels_info' => array(
                'class' => 'SiteOrigin_Widget_Editor_Widget',
                'grid'  => count($data['grids'] ?? array()),
                'cell'  => 0,
            ),
            'text' => $content_html,
        );
        siteorigin_panels_save_panels_data($post_id, $data);
        return array('status' => 'applied', 'method' => 'siteorigin_append');
    }

    // ─── 8. AVADA ────────────────────────────────────────────────────────────

    private static function update_avada_heading($post_id, $old_text, $new_text) {
        $content = get_post_field('post_content', $post_id);
        $pattern = '/(\[fusion_title[^\]]*\])' . preg_quote($old_text, '/') . '(\[\/fusion_title\])/';
        $new_content = preg_replace($pattern, '${1}' . esc_html($new_text) . '${2}', $content);

        // Also try plain heading tags inside fusion modules
        if ($new_content === $content) {
            $pattern2 = '/(<h[1-6][^>]*>)' . preg_quote($old_text, '/') . '(<\/h[1-6]>)/';
            $new_content = preg_replace($pattern2, '${1}' . esc_html($new_text) . '${2}', $content);
        }

        if ($new_content === $content) return array('status' => 'not_found', 'message' => 'Heading not found in Avada content');

        wp_update_post(array('ID' => $post_id, 'post_content' => $new_content));
        if (class_exists('Fusion_Dynamic_CSS')) {
            Fusion_Dynamic_CSS::clear_all_caches();
        }
        return array('status' => 'applied');
    }

    // ─── 9 & 10. MANUAL ACTION (WPBakery, Oxygen) ───────────────────────────

    private static function manual_action($post_id, $old_text, $new_text, $builder_name) {
        $instructions = self::get_manual_instructions($builder_name, $old_text, $new_text);

        return array(
            'status'         => 'manual_action',
            'manual_action'  => true,
            'builder'        => $builder_name,
            'content'        => $new_text,
            'old_content'    => $old_text,
            'instructions'   => $instructions,
            'message'        => "{$builder_name} page detected. Follow the steps below to apply the change manually.",
        );
    }

    private static function get_manual_instructions($builder, $old_text, $new_text) {
        $base = match($builder) {
            'WPBakery' => array(
                "Click 'Backend Editor' button on the page",
                "Find the heading element (shows as 'Heading' widget)",
                "Click the pencil (edit) icon on the heading",
                "Change the text to the recommended text below",
                "Click Save. Then Update page.",
            ),
            'Oxygen' => array(
                "Go to WordPress Admin > Pages > Edit with Oxygen",
                "Click on the heading element you want to change",
                "In the left panel under 'Content', update the heading text",
                "Click Save in the top toolbar",
            ),
            default => array(
                "Open the page in your page editor",
                "Find the heading or text section to update",
                "Replace the text with the recommended content below",
                "Save and publish the page",
            ),
        };

        return array(
            'steps'    => $base,
            'old_text' => $old_text,
            'new_text' => $new_text,
        );
    }
}
