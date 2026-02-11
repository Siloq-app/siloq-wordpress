<?php
/**
 * Siloq TALI - Component Mapper
 * 
 * Discovers what Gutenberg blocks and patterns the theme supports.
 * Generates a capability map with confidence scores.
 * 
 * @package Siloq
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_TALI_Component_Mapper {
    
    /**
     * Core Gutenberg blocks we look for
     */
    private $core_blocks = array(
        'core/paragraph',
        'core/heading',
        'core/list',
        'core/image',
        'core/gallery',
        'core/quote',
        'core/table',
        'core/buttons',
        'core/button',
        'core/columns',
        'core/column',
        'core/group',
        'core/separator',
        'core/spacer',
        'core/cover',
        'core/media-text',
    );
    
    /**
     * Discover theme's component capabilities
     * 
     * @return array Capability map
     */
    public function discover_components() {
        $map = array(
            'tali_version' => Siloq_TALI::VERSION,
            'platform' => 'wordpress',
            'generated_at' => current_time('c'),
            'supports' => array(),
            'confidence' => array(),
            'block_styles' => array(),
            'patterns' => array(),
        );
        
        // Check core component support
        $map = $this->check_core_components($map);
        
        // Check for common patterns
        $map = $this->check_patterns($map);
        
        // Check for block styles
        $map = $this->check_block_styles($map);
        
        // Check for FAQ/accordion support
        $map = $this->check_faq_support($map);
        
        // Check for testimonial support
        $map = $this->check_testimonial_support($map);
        
        // Check for CTA support
        $map = $this->check_cta_support($map);
        
        // Check for grid layouts
        $map = $this->check_grid_support($map);
        
        return $map;
    }
    
    /**
     * Check core component support
     */
    private function check_core_components($map) {
        $registry = WP_Block_Type_Registry::get_instance();
        
        $component_mapping = array(
            'paragraphs' => 'core/paragraph',
            'headings' => 'core/heading',
            'lists' => 'core/list',
            'images' => 'core/image',
            'gallery' => 'core/gallery',
            'quotes' => 'core/quote',
            'tables' => 'core/table',
            'buttons' => 'core/buttons',
            'columns' => 'core/columns',
            'groups' => 'core/group',
            'separators' => 'core/separator',
            'spacers' => 'core/spacer',
            'covers' => 'core/cover',
            'media_text' => 'core/media-text',
        );
        
        foreach ($component_mapping as $capability => $block_name) {
            $block = $registry->get_registered($block_name);
            $map['supports'][$capability] = ($block !== null);
            // Core blocks always high confidence
            $map['confidence'][$capability] = ($block !== null) ? 0.99 : 0.0;
        }
        
        return $map;
    }
    
    /**
     * Check for registered block patterns
     */
    private function check_patterns($map) {
        if (!class_exists('WP_Block_Patterns_Registry')) {
            $map['patterns'] = array();
            return $map;
        }
        
        $registry = WP_Block_Patterns_Registry::get_instance();
        $patterns = $registry->get_all_registered();
        
        $pattern_info = array();
        foreach ($patterns as $pattern) {
            $pattern_info[] = array(
                'name' => $pattern['name'],
                'title' => $pattern['title'] ?? '',
                'categories' => $pattern['categories'] ?? array(),
            );
        }
        
        $map['patterns'] = $pattern_info;
        
        // Check for specific pattern types
        $has_cta_pattern = false;
        $has_faq_pattern = false;
        $has_testimonial_pattern = false;
        $has_gallery_pattern = false;
        
        foreach ($patterns as $pattern) {
            $name = strtolower($pattern['name'] ?? '');
            $title = strtolower($pattern['title'] ?? '');
            $categories = $pattern['categories'] ?? array();
            
            if (strpos($name, 'cta') !== false || strpos($title, 'call to action') !== false) {
                $has_cta_pattern = true;
            }
            if (strpos($name, 'faq') !== false || strpos($title, 'faq') !== false || strpos($title, 'accordion') !== false) {
                $has_faq_pattern = true;
            }
            if (strpos($name, 'testimonial') !== false || strpos($title, 'testimonial') !== false) {
                $has_testimonial_pattern = true;
            }
            if (in_array('gallery', $categories) || strpos($name, 'gallery') !== false) {
                $has_gallery_pattern = true;
            }
        }
        
        // Update supports based on patterns
        if ($has_cta_pattern) {
            $map['supports']['cta_patterns'] = true;
            $map['confidence']['cta_patterns'] = 0.95;
        }
        if ($has_faq_pattern) {
            $map['supports']['faq_patterns'] = true;
            $map['confidence']['faq_patterns'] = 0.90;
        }
        if ($has_testimonial_pattern) {
            $map['supports']['testimonial_patterns'] = true;
            $map['confidence']['testimonial_patterns'] = 0.90;
        }
        if ($has_gallery_pattern) {
            $map['supports']['gallery_patterns'] = true;
            $map['confidence']['gallery_patterns'] = 0.95;
        }
        
        return $map;
    }
    
    /**
     * Check for registered block styles
     */
    private function check_block_styles($map) {
        global $wp_styles;
        
        // Get registered block styles
        $block_styles = array();
        
        // Check for common block style classes in theme
        $theme_css_path = get_stylesheet_directory() . '/style.css';
        if (file_exists($theme_css_path)) {
            $css_content = file_get_contents($theme_css_path);
            
            // Look for block style patterns
            $style_patterns = array(
                'wp-block-button' => array(),
                'wp-block-quote' => array(),
                'wp-block-table' => array(),
                'wp-block-separator' => array(),
            );
            
            foreach ($style_patterns as $block_class => &$styles) {
                // Look for .is-style-* classes
                if (preg_match_all('/\.is-style-([a-z0-9-]+)/', $css_content, $matches)) {
                    $styles = array_unique($matches[1]);
                }
            }
            
            $block_styles = $style_patterns;
        }
        
        $map['block_styles'] = $block_styles;
        
        return $map;
    }
    
    /**
     * Check for FAQ/Accordion support
     */
    private function check_faq_support($map) {
        $registry = WP_Block_Type_Registry::get_instance();
        
        // Check for common FAQ/accordion plugins
        $faq_blocks = array(
            'yoast-seo/faq-block',
            'rank-math/faq-block',
            'generateblocks/accordion',
            'kadence/accordion',
            'stackable/accordion',
            'core/details', // WordPress 6.3+ details block
        );
        
        $has_faq = false;
        $faq_block = null;
        
        foreach ($faq_blocks as $block_name) {
            $block = $registry->get_registered($block_name);
            if ($block !== null) {
                $has_faq = true;
                $faq_block = $block_name;
                break;
            }
        }
        
        $map['supports']['accordion_faq'] = $has_faq;
        $map['confidence']['accordion_faq'] = $has_faq ? 0.95 : 0.30;
        
        if ($faq_block) {
            $map['supports']['faq_block_type'] = $faq_block;
        }
        
        // Can always fall back to details/summary for FAQ
        $map['supports']['details_summary'] = true;
        $map['confidence']['details_summary'] = 0.99;
        
        return $map;
    }
    
    /**
     * Check for testimonial support
     */
    private function check_testimonial_support($map) {
        $registry = WP_Block_Type_Registry::get_instance();
        
        // Check for testimonial blocks
        $testimonial_blocks = array(
            'kadence/testimonials',
            'stackable/testimonial',
            'generateblocks/testimonial',
            'jetpack/testimonial',
        );
        
        $has_testimonial = false;
        
        foreach ($testimonial_blocks as $block_name) {
            $block = $registry->get_registered($block_name);
            if ($block !== null) {
                $has_testimonial = true;
                $map['supports']['testimonial_block_type'] = $block_name;
                break;
            }
        }
        
        $map['supports']['testimonials'] = $has_testimonial;
        // Can always create testimonials with quote block
        $map['confidence']['testimonials'] = $has_testimonial ? 0.95 : 0.70;
        
        return $map;
    }
    
    /**
     * Check for CTA/Button support
     */
    private function check_cta_support($map) {
        $registry = WP_Block_Type_Registry::get_instance();
        
        // Core buttons are always available
        $has_buttons = $registry->get_registered('core/buttons') !== null;
        $has_button = $registry->get_registered('core/button') !== null;
        
        $map['supports']['cta_buttons'] = ($has_buttons && $has_button);
        $map['confidence']['cta_buttons'] = ($has_buttons && $has_button) ? 0.99 : 0.50;
        
        // Check for enhanced CTA blocks
        $cta_blocks = array(
            'kadence/advancedbtn',
            'generateblocks/button',
            'stackable/button',
        );
        
        foreach ($cta_blocks as $block_name) {
            $block = $registry->get_registered($block_name);
            if ($block !== null) {
                $map['supports']['enhanced_cta'] = true;
                $map['supports']['enhanced_cta_block'] = $block_name;
                $map['confidence']['enhanced_cta'] = 0.95;
                break;
            }
        }
        
        return $map;
    }
    
    /**
     * Check for grid layout support
     */
    private function check_grid_support($map) {
        $registry = WP_Block_Type_Registry::get_instance();
        
        // Core columns
        $has_columns = $registry->get_registered('core/columns') !== null;
        
        $map['supports']['grid_layout'] = $has_columns;
        $map['confidence']['grid_layout'] = $has_columns ? 0.95 : 0.0;
        
        // Check for enhanced grid blocks
        $grid_blocks = array(
            'generateblocks/grid',
            'kadence/rowlayout',
            'stackable/columns',
        );
        
        foreach ($grid_blocks as $block_name) {
            $block = $registry->get_registered($block_name);
            if ($block !== null) {
                $map['supports']['enhanced_grid'] = true;
                $map['supports']['enhanced_grid_block'] = $block_name;
                $map['confidence']['enhanced_grid'] = 0.95;
                break;
            }
        }
        
        return $map;
    }
    
    /**
     * Get recommended block for a content type
     * 
     * @param string $content_type Type of content (heading, paragraph, list, cta, faq, testimonial, image, gallery, columns)
     * @param array  $capability_map The capability map
     * @return array Block recommendation with name and confidence
     */
    public function get_recommended_block($content_type, $capability_map = null) {
        if ($capability_map === null) {
            $capability_map = $this->discover_components();
        }
        
        $recommendations = array(
            'heading' => array(
                'block' => 'core/heading',
                'confidence' => $capability_map['confidence']['headings'] ?? 0.99,
            ),
            'paragraph' => array(
                'block' => 'core/paragraph',
                'confidence' => $capability_map['confidence']['paragraphs'] ?? 0.99,
            ),
            'list' => array(
                'block' => 'core/list',
                'confidence' => $capability_map['confidence']['lists'] ?? 0.99,
            ),
            'cta' => array(
                'block' => $capability_map['supports']['enhanced_cta_block'] ?? 'core/buttons',
                'confidence' => $capability_map['confidence']['cta_buttons'] ?? 0.99,
            ),
            'faq' => array(
                'block' => $capability_map['supports']['faq_block_type'] ?? 'core/details',
                'confidence' => $capability_map['confidence']['accordion_faq'] ?? 0.30,
            ),
            'testimonial' => array(
                'block' => $capability_map['supports']['testimonial_block_type'] ?? 'core/quote',
                'confidence' => $capability_map['confidence']['testimonials'] ?? 0.70,
            ),
            'image' => array(
                'block' => 'core/image',
                'confidence' => $capability_map['confidence']['images'] ?? 0.99,
            ),
            'gallery' => array(
                'block' => 'core/gallery',
                'confidence' => $capability_map['confidence']['gallery'] ?? 0.99,
            ),
            'columns' => array(
                'block' => $capability_map['supports']['enhanced_grid_block'] ?? 'core/columns',
                'confidence' => $capability_map['confidence']['grid_layout'] ?? 0.95,
            ),
            'table' => array(
                'block' => 'core/table',
                'confidence' => $capability_map['confidence']['tables'] ?? 0.99,
            ),
            'quote' => array(
                'block' => 'core/quote',
                'confidence' => $capability_map['confidence']['quotes'] ?? 0.99,
            ),
            'group' => array(
                'block' => 'core/group',
                'confidence' => $capability_map['confidence']['groups'] ?? 0.99,
            ),
            'separator' => array(
                'block' => 'core/separator',
                'confidence' => $capability_map['confidence']['separators'] ?? 0.99,
            ),
        );
        
        return $recommendations[$content_type] ?? array(
            'block' => 'core/paragraph',
            'confidence' => 0.50,
        );
    }
}
