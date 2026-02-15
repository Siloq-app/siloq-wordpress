<?php
/**
 * Siloq TALI - Theme Fingerprinter
 * 
 * Extracts design tokens from the active WordPress theme.
 * Handles both block themes (theme.json) and classic themes (CSS).
 * 
 * @package Siloq
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_TALI_Fingerprinter {
    
    /**
     * Fingerprint the active theme
     * 
     * @return array Design profile
     */
    public function fingerprint_theme() {
        $theme = wp_get_theme();
        $is_block_theme = wp_is_block_theme();
        
        $profile = array(
            'tali_version' => Siloq_TALI::VERSION,
            'platform' => 'wordpress',
            'generated_at' => current_time('c'),
            'theme' => array(
                'name' => $theme->get('Name'),
                'stylesheet' => $theme->get_stylesheet(),
                'template' => $theme->get_template(),
                'version' => $theme->get('Version'),
                'is_block_theme' => $is_block_theme,
            ),
            'tokens' => array(
                'colors' => $this->extract_colors($is_block_theme),
                'typography' => $this->extract_typography($is_block_theme),
                'spacing' => $this->extract_spacing($is_block_theme),
                'layout' => $this->extract_layout($is_block_theme),
            ),
            'extraction_method' => $is_block_theme ? 'theme_json' : 'css_computed',
            'confidence' => 1.0, // Will be adjusted based on extraction success
        );
        
        // Calculate overall confidence
        $profile['confidence'] = $this->calculate_confidence($profile['tokens']);
        
        return $profile;
    }
    
    /**
     * Extract color tokens
     */
    private function extract_colors($is_block_theme) {
        $colors = array(
            'primary' => null,
            'secondary' => null,
            'text' => null,
            'background' => null,
            'accent' => null,
            'link' => null,
        );
        
        if ($is_block_theme) {
            $colors = $this->extract_colors_from_theme_json($colors);
        }
        
        // Fallback to CSS extraction if theme.json didn't provide values
        $colors = $this->extract_colors_from_css($colors);
        
        return $colors;
    }
    
    /**
     * Extract colors from theme.json
     */
    private function extract_colors_from_theme_json($colors) {
        $theme_json = $this->get_theme_json();
        
        if (!$theme_json) {
            return $colors;
        }
        
        // Get color palette
        $palette = $theme_json['settings']['color']['palette'] ?? array();
        
        foreach ($palette as $color) {
            $slug = $color['slug'] ?? '';
            $value = $color['color'] ?? '';
            
            // Map common color slugs to our tokens
            if (stripos($slug, 'primary') !== false) {
                $colors['primary'] = $value;
            } elseif (stripos($slug, 'secondary') !== false) {
                $colors['secondary'] = $value;
            } elseif (stripos($slug, 'foreground') !== false || stripos($slug, 'text') !== false) {
                $colors['text'] = $value;
            } elseif (stripos($slug, 'background') !== false || stripos($slug, 'base') !== false) {
                $colors['background'] = $value;
            } elseif (stripos($slug, 'accent') !== false) {
                $colors['accent'] = $value;
            }
        }
        
        // Get styles colors
        $styles = $theme_json['styles'] ?? array();
        if (!empty($styles['color']['text'])) {
            $colors['text'] = $colors['text'] ?: $this->resolve_css_var($styles['color']['text']);
        }
        if (!empty($styles['color']['background'])) {
            $colors['background'] = $colors['background'] ?: $this->resolve_css_var($styles['color']['background']);
        }
        if (!empty($styles['elements']['link']['color']['text'])) {
            $colors['link'] = $this->resolve_css_var($styles['elements']['link']['color']['text']);
        }
        
        return $colors;
    }
    
    /**
     * Extract colors from computed CSS
     */
    private function extract_colors_from_css($colors) {
        // These CSS variables are commonly used
        $css_var_mappings = array(
            'primary' => array(
                '--wp--preset--color--primary',
                '--primary-color',
                '--color-primary',
                '--theme-primary',
            ),
            'secondary' => array(
                '--wp--preset--color--secondary',
                '--secondary-color',
                '--color-secondary',
                '--theme-secondary',
            ),
            'text' => array(
                '--wp--preset--color--foreground',
                '--wp--preset--color--contrast',
                '--text-color',
                '--color-text',
                '--body-color',
            ),
            'background' => array(
                '--wp--preset--color--background',
                '--wp--preset--color--base',
                '--background-color',
                '--color-background',
                '--body-background',
            ),
            'link' => array(
                '--wp--preset--color--primary',
                '--link-color',
                '--color-link',
            ),
        );
        
        foreach ($css_var_mappings as $token => $vars) {
            if ($colors[$token] === null) {
                foreach ($vars as $var) {
                    $colors[$token] = "var({$var})";
                    break; // Use first one as fallback reference
                }
            }
        }
        
        // Ultimate fallbacks
        $fallbacks = array(
            'primary' => '#0073aa',
            'secondary' => '#23282d',
            'text' => '#1e1e1e',
            'background' => '#ffffff',
            'accent' => '#0073aa',
            'link' => '#0073aa',
        );
        
        foreach ($fallbacks as $token => $fallback) {
            if ($colors[$token] === null) {
                $colors[$token] = $fallback;
            }
        }
        
        return $colors;
    }
    
    /**
     * Extract typography tokens
     */
    private function extract_typography($is_block_theme) {
        $typography = array(
            'font_family' => null,
            'font_family_headings' => null,
            'h1' => null,
            'h2' => null,
            'h3' => null,
            'body' => null,
            'line_height' => null,
        );
        
        if ($is_block_theme) {
            $typography = $this->extract_typography_from_theme_json($typography);
        }
        
        // Fallbacks
        $typography = $this->apply_typography_fallbacks($typography);
        
        return $typography;
    }
    
    /**
     * Extract typography from theme.json
     */
    private function extract_typography_from_theme_json($typography) {
        $theme_json = $this->get_theme_json();
        
        if (!$theme_json) {
            return $typography;
        }
        
        // Font families
        $font_families = $theme_json['settings']['typography']['fontFamilies'] ?? array();
        foreach ($font_families as $font) {
            $slug = $font['slug'] ?? '';
            if (stripos($slug, 'body') !== false || stripos($slug, 'system') !== false) {
                $typography['font_family'] = $font['fontFamily'] ?? null;
            }
            if (stripos($slug, 'heading') !== false) {
                $typography['font_family_headings'] = $font['fontFamily'] ?? null;
            }
        }
        
        // Font sizes
        $font_sizes = $theme_json['settings']['typography']['fontSizes'] ?? array();
        $size_map = array();
        foreach ($font_sizes as $size) {
            $size_map[$size['slug']] = $size['size'] ?? null;
        }
        
        // Map to our tokens
        $typography['h1'] = $size_map['xx-large'] ?? $size_map['huge'] ?? '2.5rem';
        $typography['h2'] = $size_map['x-large'] ?? $size_map['large'] ?? '2rem';
        $typography['h3'] = $size_map['large'] ?? $size_map['medium'] ?? '1.5rem';
        $typography['body'] = $size_map['medium'] ?? $size_map['normal'] ?? '1rem';
        
        // Styles
        $styles = $theme_json['styles'] ?? array();
        if (!empty($styles['typography']['fontFamily'])) {
            $typography['font_family'] = $typography['font_family'] ?: $this->resolve_css_var($styles['typography']['fontFamily']);
        }
        if (!empty($styles['typography']['lineHeight'])) {
            $typography['line_height'] = $styles['typography']['lineHeight'];
        }
        
        return $typography;
    }
    
    /**
     * Apply typography fallbacks
     */
    private function apply_typography_fallbacks($typography) {
        $fallbacks = array(
            'font_family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
            'font_family_headings' => 'inherit',
            'h1' => 'clamp(2rem, 4vw, 2.5rem)',
            'h2' => 'clamp(1.5rem, 3vw, 2rem)',
            'h3' => 'clamp(1.25rem, 2.5vw, 1.5rem)',
            'body' => '1rem',
            'line_height' => '1.6',
        );
        
        foreach ($fallbacks as $token => $fallback) {
            if ($typography[$token] === null) {
                $typography[$token] = $fallback;
            }
        }
        
        return $typography;
    }
    
    /**
     * Extract spacing tokens
     */
    private function extract_spacing($is_block_theme) {
        $spacing = array(
            'xs' => null,
            'sm' => null,
            'md' => null,
            'lg' => null,
            'xl' => null,
            'block_gap' => null,
        );
        
        if ($is_block_theme) {
            $theme_json = $this->get_theme_json();
            
            if ($theme_json) {
                $spacing_sizes = $theme_json['settings']['spacing']['spacingSizes'] ?? array();
                $spacing_scale = $theme_json['settings']['spacing']['spacingScale'] ?? array();
                
                // Try to map spacing sizes
                foreach ($spacing_sizes as $size) {
                    $slug = $size['slug'] ?? '';
                    $value = $size['size'] ?? null;
                    
                    if (in_array($slug, array('10', '20', 'small', 'xs'))) {
                        $spacing['xs'] = $value;
                    } elseif (in_array($slug, array('30', 'sm'))) {
                        $spacing['sm'] = $value;
                    } elseif (in_array($slug, array('40', '50', 'medium', 'md'))) {
                        $spacing['md'] = $value;
                    } elseif (in_array($slug, array('60', '70', 'large', 'lg'))) {
                        $spacing['lg'] = $value;
                    } elseif (in_array($slug, array('80', 'x-large', 'xl'))) {
                        $spacing['xl'] = $value;
                    }
                }
                
                // Block gap
                $styles = $theme_json['styles'] ?? array();
                if (!empty($styles['spacing']['blockGap'])) {
                    $spacing['block_gap'] = $styles['spacing']['blockGap'];
                }
            }
        }
        
        // Fallbacks
        $fallbacks = array(
            'xs' => '0.5rem',
            'sm' => '1rem',
            'md' => '1.5rem',
            'lg' => '2rem',
            'xl' => '3rem',
            'block_gap' => '1.5rem',
        );
        
        foreach ($fallbacks as $token => $fallback) {
            if ($spacing[$token] === null) {
                $spacing[$token] = $fallback;
            }
        }
        
        return $spacing;
    }
    
    /**
     * Extract layout tokens
     */
    private function extract_layout($is_block_theme) {
        $layout = array(
            'content_width' => null,
            'wide_width' => null,
        );
        
        if ($is_block_theme) {
            $theme_json = $this->get_theme_json();
            
            if ($theme_json) {
                $layout_settings = $theme_json['settings']['layout'] ?? array();
                $layout['content_width'] = $layout_settings['contentSize'] ?? null;
                $layout['wide_width'] = $layout_settings['wideSize'] ?? null;
            }
        }
        
        // Fallbacks
        if ($layout['content_width'] === null) {
            $layout['content_width'] = '650px';
        }
        if ($layout['wide_width'] === null) {
            $layout['wide_width'] = '1200px';
        }
        
        return $layout;
    }
    
    /**
     * Get theme.json data
     */
    private function get_theme_json() {
        static $theme_json = null;
        
        if ($theme_json !== null) {
            return $theme_json;
        }
        
        // Try to get merged theme.json (includes parent theme)
        if (class_exists('WP_Theme_JSON_Resolver')) {
            $resolved = WP_Theme_JSON_Resolver::get_merged_data();
            if ($resolved) {
                $theme_json = $resolved->get_raw_data();
                return $theme_json;
            }
        }
        
        // Fallback: read theme.json directly
        $theme_dir = get_stylesheet_directory();
        $theme_json_path = $theme_dir . '/theme.json';
        
        if (file_exists($theme_json_path)) {
            $theme_json = json_decode(file_get_contents($theme_json_path), true);
            return $theme_json;
        }
        
        // Try parent theme
        $parent_dir = get_template_directory();
        if ($parent_dir !== $theme_dir) {
            $parent_json_path = $parent_dir . '/theme.json';
            if (file_exists($parent_json_path)) {
                $theme_json = json_decode(file_get_contents($parent_json_path), true);
                return $theme_json;
            }
        }
        
        $theme_json = false;
        return $theme_json;
    }
    
    /**
     * Resolve CSS variable references
     */
    private function resolve_css_var($value) {
        if (is_string($value) && strpos($value, 'var:') === 0) {
            // Convert theme.json var: syntax to CSS var()
            $var_path = str_replace('var:', '', $value);
            $var_path = str_replace('|', '--', $var_path);
            return "var(--wp--{$var_path})";
        }
        return $value;
    }
    
    /**
     * Calculate confidence score based on token extraction success
     */
    private function calculate_confidence($tokens) {
        $total = 0;
        $filled = 0;
        
        foreach ($tokens as $category => $values) {
            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    $total++;
                    if ($value !== null && strpos($value, 'var(') === false) {
                        // Has actual value, not just CSS var reference
                        $filled++;
                    } elseif ($value !== null) {
                        // Has CSS var reference (partial credit)
                        $filled += 0.5;
                    }
                }
            }
        }
        
        return $total > 0 ? round($filled / $total, 2) : 0;
    }
}
