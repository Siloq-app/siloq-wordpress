<?php
/**
 * Siloq Schema Manager
 * Handles schema markup injection and management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Schema_Manager {
    
    /**
     * Output schema markup in wp_head
     */
    public static function output_schema() {
        if (!is_singular('page')) {
            return;
        }
        
        global $post;
        if (!$post || !is_a($post, 'WP_Post')) {
            return;
        }
        
        // Check for new schema format first
        $schema = get_post_meta($post->ID, '_siloq_schema', true);
        if (!empty($schema)) {
            echo "\n<!-- Siloq Schema Markup -->\n";
            echo '<script type="application/ld+json">' . "\n";
            echo json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
            echo '</script>' . "\n";
            return;
        }
        
        // Fallback to legacy format
        $schema_markup = get_post_meta($post->ID, '_siloq_schema_markup', true);
        if (!empty($schema_markup)) {
            echo "\n<!-- Siloq Schema Markup (Legacy) -->\n";
            echo '<script type="application/ld+json">' . "\n";
            echo $schema_markup . "\n";
            echo '</script>' . "\n";
        }
    }
    
    /**
     * Save schema markup for a post
     */
    public static function save_schema($post_id, $schema) {
        if (is_string($schema)) {
            // Try to parse as JSON first
            $decoded = json_decode($schema, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                update_post_meta($post_id, '_siloq_schema', $decoded);
                // Remove legacy format
                delete_post_meta($post_id, '_siloq_schema_markup');
            } else {
                // Store as legacy format
                update_post_meta($post_id, '_siloq_schema_markup', $schema);
                delete_post_meta($post_id, '_siloq_schema');
            }
        } elseif (is_array($schema)) {
            update_post_meta($post_id, '_siloq_schema', $schema);
            delete_post_meta($post_id, '_siloq_schema_markup');
        }
    }
    
    /**
     * Get schema for a post
     */
    public static function get_schema($post_id) {
        // Try new format first
        $schema = get_post_meta($post_id, '_siloq_schema', true);
        if (!empty($schema)) {
            return $schema;
        }
        
        // Fallback to legacy format
        $legacy_schema = get_post_meta($post_id, '_siloq_schema_markup', true);
        if (!empty($legacy_schema)) {
            // Try to parse legacy JSON
            $decoded = json_decode($legacy_schema, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $legacy_schema;
        }
        
        return null;
    }
    
    /**
     * Detect schema conflicts
     */
    public static function detect_conflicts($post_id) {
        $siloq_schema = self::get_schema($post_id);
        if (empty($siloq_schema)) {
            return array('has_conflict' => false);
        }
        
        // Check for other SEO plugins schema
        $other_schemas = array();
        
        // Yoast SEO
        if (function_exists('YoastSEO')) {
            $yoast_schema = get_post_meta($post_id, '_yoast_wpseo_schema', true);
            if (!empty($yoast_schema)) {
                $other_schemas['yoast'] = $yoast_schema;
            }
        }
        
        // Rank Math
        if (function_exists('rank_math')) {
            $rankmath_schema = get_post_meta($post_id, 'rank_math_schema', true);
            if (!empty($rankmath_schema)) {
                $other_schemas['rankmath'] = $rankmath_schema;
            }
        }
        
        // AIOSEO
        if (function_exists('aioseo')) {
            $aioseo_schema = get_post_meta($post_id, '_aioseo_schema', true);
            if (!empty($aioseo_schema)) {
                $other_schemas['aioseo'] = $aioseo_schema;
            }
        }
        
        return array(
            'has_conflict' => !empty($other_schemas),
            'siloq_schema' => $siloq_schema,
            'other_schemas' => $other_schemas,
            'recommendation' => !empty($other_schemas) 
                ? 'Multiple schema plugins detected. Consider disabling other SEO plugin schema features.'
                : 'No conflicts detected.'
        );
    }
}
