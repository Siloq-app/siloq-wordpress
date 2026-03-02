<?php
/**
 * Siloq Theme Compatibility — Section 12
 * Handles schema conflict detection and H1 duplicate detection
 * for the top 10 WordPress themes.
 */

if (!defined('ABSPATH')) exit;

class Siloq_Theme_Compat {

    // Schema types Siloq generates for local businesses
    private static $siloq_schema_types = array(
        'LocalBusiness', 'Organization', 'WebPage', 'FAQPage',
        'Service', 'Article', 'BreadcrumbList',
    );

    // Theme → schema filter map (only themes that output conflicting schema)
    private static $suppression_map = array(
        'astra'          => array('filter' => 'astra_schema_output',            'types' => array('LocalBusiness', 'Organization')),
        'kadence'        => array('filter' => 'kadence_blocks_schema_output',   'types' => array('WebPage', 'BreadcrumbList')),
        'oceanwp'        => array('filter' => 'oceanwp_schema_markup',          'types' => array('LocalBusiness')),
        'generatepress'  => array('filter' => 'generate_schema_output',         'types' => array('WebSite')),        // low risk
        'blocksy'        => array('filter' => 'blocksy_schema_output',          'types' => array('WebPage')),
        'avada'          => array('filter' => 'avada_schema_output',            'types' => array('LocalBusiness', 'Organization')),
    );

    // Themes known to render post_title as H1 alongside builder H1
    private static $dual_h1_themes = array(
        'astra', 'oceanwp', 'generatepress', 'kadence', 'blocksy',
    );

    // Page templates that suppress the theme title (safe — no H1 conflict)
    private static $no_title_templates = array(
        'elementor_canvas', 'elementor_header_footer',
        'divi-blank', 'bb-blank-tmpl',
        'page-templates/canvas.php', 'page-templates/blank.php',
    );

    /**
     * Initialize theme compat hooks.
     * Called on WordPress init — after theme and plugins are loaded.
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'suppress_conflicting_schema'), 20);
        add_action('wp_head', array(__CLASS__, 'inject_siloq_schema'), 5);
    }

    /**
     * Detect active theme folder name.
     */
    public static function get_theme() {
        return strtolower(get_template());
    }

    /**
     * Suppress theme schema output when Siloq is generating the same type.
     * Prevents duplicate LocalBusiness or Organization schema on the same page.
     */
    public static function suppress_conflicting_schema() {
        $theme = self::get_theme();
        if (!isset(self::$suppression_map[$theme])) return;

        $map = self::$suppression_map[$theme];
        $siloq_types = self::get_siloq_schema_types_for_page();
        $overlapping = array_intersect($siloq_types, $map['types']);

        if (!empty($overlapping) && !empty($map['filter'])) {
            add_filter($map['filter'], '__return_false', 99);
            // Log suppression for debugging
            update_option('siloq_schema_suppressed_' . $theme, array(
                'theme' => $theme,
                'types' => $overlapping,
                'time'  => current_time('mysql'),
            ));
        }
    }

    /**
     * Return schema types Siloq will output for the current page.
     */
    public static function get_siloq_schema_types_for_page() {
        // For V1: always generate LocalBusiness + Organization for local business sites
        $types = array('LocalBusiness', 'Organization');
        if (is_singular()) $types[] = 'WebPage';
        if (is_home() || is_front_page()) $types[] = 'WebSite';
        return $types;
    }

    /**
     * Inject Siloq-generated schema from post meta into wp_head.
     * Only fires when AIOSEO and Yoast are NOT handling schema output
     * (avoids triple schema output if both Siloq and an SEO plugin are active).
     */
    public static function inject_siloq_schema() {
        // Don't inject if AIOSEO or Yoast are active — they handle schema
        if (class_exists('AIOSEO\Plugin\AIOSEO') || defined('WPSEO_VERSION')) return;

        global $post;
        if (!$post) return;

        $schema = get_post_meta($post->ID, '_siloq_schema', true);
        if (empty($schema) || empty($schema['json_ld'])) return;

        echo "\n<!-- Siloq Schema Markup -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema['json_ld'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "\n" . '</script>' . "\n";
    }

    /**
     * Check if a page has a duplicate H1 (theme rendering post_title + builder H1).
     * Returns false if no conflict, or an array describing the conflict.
     */
    public static function check_h1_conflict($post_id) {
        $builder = function_exists('siloq_detect_builder') ? siloq_detect_builder($post_id) : 'standard';

        // Standard / Gutenberg pages only have one H1 source — no conflict
        if (in_array($builder, array('standard', 'gutenberg'))) return false;

        $theme = self::get_theme();
        if (!in_array($theme, self::$dual_h1_themes)) return false;

        // Check if page is using a blank/canvas template (suppresses theme title)
        $page_template = get_page_template_slug($post_id);
        if (in_array($page_template, self::$no_title_templates)) return false;

        // Also check if the theme's hide-title option is set for this post
        $hide_title = get_post_meta($post_id, '_hide_page_title', true)     // Generic
                   ?: get_post_meta($post_id, '_astra_content_layout_set', true)  // Astra
                   ?: get_post_meta($post_id, '_generate_remove_title', true);    // GeneratePress

        if ($hide_title) return false;

        return array(
            'conflict'   => true,
            'theme'      => $theme,
            'builder'    => $builder,
            'severity'   => 'high',
            'message'    => "Duplicate H1 detected: theme '{$theme}' renders post_title as H1 alongside {$builder}'s H1",
            'fix'        => "Set page template to a blank/canvas template, or enable 'Hide page title' in your theme's page settings for this page.",
            'fix_option' => self::get_theme_title_hide_option($theme),
        );
    }

    /**
     * Returns theme-specific option for hiding the page title.
     */
    private static function get_theme_title_hide_option($theme) {
        return match($theme) {
            'astra'         => "Astra page settings (sidebar panel) → Page Layout → Uncheck 'Display Page Title'",
            'generatepress' => "GeneratePress → Disable Elements → Add rule for this page → Disable 'Page Header'",
            'kadence'       => "Kadence page settings → Uncheck 'Title'",
            'oceanwp'       => "OceanWP page settings → Page Header → Set to 'Disabled'",
            default         => "Theme settings → Page Options → Hide Title",
        };
    }

    /**
     * REST endpoint: detect theme info + H1 conflict + schema conflicts for a post.
     * GET /wp-json/siloq/v1/theme-check/{post_id}
     */
    public static function register_routes() {
        register_rest_route('siloq/v1', '/theme-check/(?P<post_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'theme_check_endpoint'),
            'permission_callback' => '__return_true',
        ));
    }

    public static function theme_check_endpoint($request) {
        $post_id = (int) $request['post_id'];
        $theme   = self::get_theme();

        $h1_conflict = self::check_h1_conflict($post_id);
        $siloq_types = self::get_siloq_schema_types_for_page();

        $schema_conflicts = array();
        if (isset(self::$suppression_map[$theme])) {
            $map = self::$suppression_map[$theme];
            $overlap = array_intersect($siloq_types, $map['types']);
            if (!empty($overlap)) {
                $schema_conflicts[] = array(
                    'theme'           => $theme,
                    'conflicting_types' => array_values($overlap),
                    'resolution'      => 'Theme schema suppressed — Siloq output takes precedence',
                );
            }
        }

        return new WP_REST_Response(array(
            'theme'           => $theme,
            'builder'         => function_exists('siloq_detect_builder') ? siloq_detect_builder($post_id) : 'unknown',
            'h1_conflict'     => $h1_conflict,
            'schema_conflicts'=> $schema_conflicts,
            'siloq_schema_types' => $siloq_types,
        ));
    }
}

// Initialize on REST API init (for route registration)
add_action('rest_api_init', array('Siloq_Theme_Compat', 'register_routes'));
// Initialize schema suppression on init
Siloq_Theme_Compat::init();
