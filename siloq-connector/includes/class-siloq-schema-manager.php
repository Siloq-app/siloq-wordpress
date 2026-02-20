<?php
/**
 * Siloq Schema Manager
 * Detects existing SEO plugin schema, prevents duplicates, injects Siloq schema.
 */
class Siloq_Schema_Manager {

    /**
     * Detect which SEO plugin is active.
     * Returns: 'aioseo' | 'yoast' | 'rankmath' | 'none'
     */
    public static function detect_active_seo_plugin() {
        if (defined('AIOSEO_VERSION') || class_exists('AIOSEO\Plugin\AIOSEO')) return 'aioseo';
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) return 'yoast';
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) return 'rankmath';
        return 'none';
    }

    /**
     * Get the schema types already being output by the active SEO plugin for a given post.
     * Returns array of schema type strings, e.g. ['LocalBusiness', 'WebSite', 'Organization']
     */
    public static function get_existing_schema_types($post_id) {
        $types = [];
        $plugin = self::detect_active_seo_plugin();

        if ($plugin === 'aioseo') {
            // AIOSEO stores schema in wp_aioseo_posts table
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT schema_type FROM {$wpdb->prefix}aioseo_posts WHERE post_id = %d",
                $post_id
            ));
            if ($row && $row->schema_type) {
                $types[] = $row->schema_type;
            }
            // Check for graph schemas
            $graph = $wpdb->get_var($wpdb->prepare(
                "SELECT schema FROM {$wpdb->prefix}aioseo_posts WHERE post_id = %d",
                $post_id
            ));
            if ($graph) {
                $decoded = json_decode($graph, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $node) {
                        if (isset($node['@type'])) $types[] = $node['@type'];
                    }
                }
            }
        } elseif ($plugin === 'yoast') {
            // Yoast stores schema in _yoast_wpseo_schema post meta
            $schema_meta = get_post_meta($post_id, '_yoast_wpseo_schema', true);
            if ($schema_meta && is_array($schema_meta)) {
                foreach ($schema_meta as $node) {
                    if (isset($node['@type'])) $types[] = $node['@type'];
                }
            }
            // Also check page type setting
            $page_type = get_post_meta($post_id, '_yoast_wpseo_schema_page_type', true);
            if ($page_type) $types[] = $page_type;
        } elseif ($plugin === 'rankmath') {
            // RankMath stores schema in rankmath_schema_* post meta
            $meta_keys = get_post_meta($post_id);
            foreach (array_keys($meta_keys) as $key) {
                if (strpos($key, 'rank_math_schema_') === 0) {
                    $schema_data = get_post_meta($post_id, $key, true);
                    if (is_array($schema_data) && isset($schema_data['@type'])) {
                        $types[] = $schema_data['@type'];
                    }
                }
            }
        }

        // Also check for manually added schema in post content or _siloq_schema meta
        $siloq_schema = get_post_meta($post_id, '_siloq_schema', true);
        if ($siloq_schema) {
            $decoded = json_decode($siloq_schema, true);
            if (is_array($decoded) && isset($decoded['@type'])) {
                $types[] = 'siloq:' . $decoded['@type'];
            }
        }

        return array_unique(array_filter($types));
    }

    /**
     * Check if a schema type would conflict with existing schema.
     * Returns array: ['conflicts' => bool, 'existing_types' => [], 'plugin' => 'aioseo', 'recommendation' => 'enhance'|'replace'|'safe']
     */
    public static function check_conflict($post_id, $new_schema_type) {
        $plugin = self::detect_active_seo_plugin();
        $existing = self::get_existing_schema_types($post_id);

        // Normalize type comparison
        $new_type_clean = strtolower(str_replace(['siloq:', ' '], '', $new_schema_type));
        $existing_clean = array_map(function($t) { return strtolower(str_replace(['siloq:', ' '], '', $t)); }, $existing);

        // These types should never be duplicated
        $singleton_types = ['organization', 'localbusiness', 'website', 'person'];
        $is_singleton = in_array($new_type_clean, $singleton_types);
        $has_conflict = $is_singleton && in_array($new_type_clean, $existing_clean);

        return [
            'conflicts'      => $has_conflict,
            'existing_types' => $existing,
            'plugin'         => $plugin,
            'new_type'       => $new_schema_type,
            'recommendation' => $has_conflict ? 'enhance' : 'safe',
            'message'        => $has_conflict
                ? sprintf('This page already has %s schema from %s. Siloq will enhance it with missing properties.', $new_schema_type, $plugin)
                : 'No conflict — safe to inject.',
        ];
    }

    /**
     * Inject JSON-LD schema into a post.
     * Handles conflict checking and stores in _siloq_schema post meta.
     * The wp_head hook reads this meta and outputs the <script> tag.
     *
     * Returns array: ['success' => bool, 'action' => 'injected'|'enhanced'|'skipped', 'conflict' => [...]]
     */
    public static function inject_schema($post_id, $schema_markup, $mode = 'auto') {
        if (is_string($schema_markup)) {
            $schema_data = json_decode($schema_markup, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'Invalid JSON-LD'];
            }
        } else {
            $schema_data = $schema_markup;
        }

        $schema_type = isset($schema_data['@type']) ? $schema_data['@type'] : 'Unknown';
        $conflict_info = self::check_conflict($post_id, $schema_type);

        if ($conflict_info['conflicts'] && $mode === 'safe') {
            return [
                'success'  => false,
                'action'   => 'skipped',
                'conflict' => $conflict_info,
                'message'  => $conflict_info['message'],
            ];
        }

        // Store the schema in post meta — wp_head hook will output it
        update_post_meta($post_id, '_siloq_schema', wp_slash(json_encode($schema_data)));
        update_post_meta($post_id, '_siloq_schema_type', $schema_type);
        update_post_meta($post_id, '_siloq_schema_updated', current_time('mysql'));

        return [
            'success'  => true,
            'action'   => $conflict_info['conflicts'] ? 'enhanced' : 'injected',
            'conflict' => $conflict_info,
            'post_id'  => $post_id,
        ];
    }

    /**
     * Output Siloq schema via wp_head hook.
     * Called on all frontend pages.
     */
    public static function output_schema() {
        if (!is_singular()) return;

        $post_id = get_the_ID();
        $schema_json = get_post_meta($post_id, '_siloq_schema', true);
        if (!$schema_json) return;

        $schema_data = json_decode($schema_json, true);
        if (!$schema_data) return;

        echo "\n<!-- Siloq Schema Markup -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo json_encode($schema_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "\n" . '</script>' . "\n";
    }

    /**
     * REST API endpoint: check schema conflict before pushing.
     * GET /wp-json/siloq/v1/schema/check?post_id=123&schema_type=FAQPage
     */
    public static function rest_check_conflict($request) {
        $post_id     = intval($request->get_param('post_id'));
        $schema_type = sanitize_text_field($request->get_param('schema_type'));

        if (!$post_id || !$schema_type) {
            return new WP_Error('missing_params', 'post_id and schema_type required', ['status' => 400]);
        }

        return rest_ensure_response(self::check_conflict($post_id, $schema_type));
    }
}
