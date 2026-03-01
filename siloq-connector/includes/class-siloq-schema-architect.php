<?php
/**
 * Siloq Schema Architect
 *
 * Direct wp_head JSON-LD injection, independent of AIOSEO/Yoast/Rank Math.
 * Storage: wp_siloq_schema custom table.
 * Detection: auto from Hub & Spoke role + content analysis.
 *
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Siloq_Schema_Architect {

    const TABLE_SCHEMA   = 'siloq_schema';
    const TABLE_SETTINGS = 'siloq_schema_settings';
    const VERSION_KEY    = 'siloq_schema_db_version';
    const VERSION        = '1.0';

    // ── Boot ────────────────────────────────────────────────────────────────

    public static function init() {
        add_action( 'wp_head', [ __CLASS__, 'inject_schema' ], 1 );
        add_action( 'wp_head', [ __CLASS__, 'conflict_check' ], 0 );
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
        add_action( 'after_switch_theme', [ __CLASS__, 'create_tables' ] );

        if ( get_option( self::VERSION_KEY ) !== self::VERSION ) {
            self::create_tables();
        }
    }

    // ── Database ─────────────────────────────────────────────────────────────

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $schema_table   = $wpdb->prefix . self::TABLE_SCHEMA;
        $settings_table = $wpdb->prefix . self::TABLE_SETTINGS;

        $sql = "
        CREATE TABLE IF NOT EXISTS {$schema_table} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            schema_type VARCHAR(100) NOT NULL,
            schema_json LONGTEXT NOT NULL,
            confidence INT UNSIGNED DEFAULT 0,
            detection_reason VARCHAR(500) DEFAULT NULL,
            source VARCHAR(50) DEFAULT 'auto',
            validation_status VARCHAR(20) DEFAULT 'pending',
            validation_errors TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_post_id (post_id),
            INDEX idx_schema_type (schema_type),
            INDEX idx_is_active (is_active),
            UNIQUE KEY unique_post_type (post_id, schema_type)
        ) {$charset};

        CREATE TABLE IF NOT EXISTS {$settings_table} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value LONGTEXT DEFAULT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) {$charset};
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Default settings
        $defaults = [
            'auto_breadcrumbs'   => 'true',
            'auto_faq_detection' => 'true',
            'auto_apply'         => 'false',
            'conflict_mode'      => 'warn',
        ];
        foreach ( $defaults as $key => $value ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO {$settings_table} (setting_key, setting_value) VALUES (%s, %s)",
                $key, $value
            ) );
        }

        update_option( self::VERSION_KEY, self::VERSION );
    }

    // ── Head Injection ────────────────────────────────────────────────────────

    public static function inject_schema() {
        global $wpdb, $post;

        if ( is_front_page() || is_home() ) {
            self::inject_homepage_schema();
        }

        if ( ! is_singular() ) return;

        $post_id = $post->ID;
        $table   = $wpdb->prefix . self::TABLE_SCHEMA;

        $schemas = $wpdb->get_results( $wpdb->prepare(
            "SELECT schema_type, schema_json, validation_status
             FROM {$table}
             WHERE post_id = %d AND is_active = 1
               AND validation_status IN ('valid','warning','pending')
             ORDER BY schema_type ASC",
            $post_id
        ) );

        if ( empty( $schemas ) ) return;

        $external       = self::detect_external_schema( $post_id );
        $conflict_mode  = self::get_setting( 'conflict_mode', 'warn' );

        foreach ( $schemas as $schema ) {
            $json_ld = json_decode( $schema->schema_json, true );
            if ( ! $json_ld ) continue;

            if ( $external && in_array( $schema->schema_type, $external ) && $conflict_mode === 'skip' ) {
                continue;
            }

            if ( ! isset( $json_ld['@context'] ) ) {
                $json_ld['@context'] = 'https://schema.org';
            }

            $encoded = wp_json_encode( $json_ld, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
            echo "\n<!-- Siloq Schema Architect: {$schema->schema_type} -->\n";
            echo '<script type="application/ld+json">' . "\n";
            echo $encoded . "\n";
            echo '</script>' . "\n";
        }
    }

    public static function inject_homepage_schema() {
        $name = self::get_setting( 'business_name' );
        if ( ! $name ) return;

        $org = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $name,
            'url'      => home_url( '/' ),
        ];
        $logo = self::get_setting( 'business_logo' );
        if ( $logo ) $org['logo'] = $logo;

        $social = self::get_setting( 'social_profiles' );
        if ( $social ) {
            $decoded = json_decode( $social, true );
            if ( $decoded ) $org['sameAs'] = $decoded;
        }

        echo "\n<!-- Siloq Schema Architect: Organization -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n";
        echo '</script>' . "\n";

        $website = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => get_bloginfo( 'name' ),
            'url'      => home_url( '/' ),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => home_url( '/?s={search_term_string}' ),
                'query-input' => 'required name=search_term_string',
            ],
        ];
        echo "\n<!-- Siloq Schema Architect: WebSite -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $website, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n";
        echo '</script>' . "\n";
    }

    // ── Conflict Detection ────────────────────────────────────────────────────

    public static function conflict_check() {
        if ( ! is_singular() ) return;
        global $post;
        $mode = self::get_setting( 'conflict_mode', 'warn' );
        if ( $mode !== 'override' ) return;

        $external = self::detect_external_schema( $post->ID );
        if ( ! $external ) return;

        // Suppress other plugin schema output
        remove_action( 'wp_head', [ 'AIOSEO\Plugin\Common\Schema\Schema', 'output' ] );
        add_filter( 'wpseo_json_ld_output', '__return_empty_array' );
        remove_all_actions( 'rank_math/json_ld' );
        remove_action( 'wp_head', 'seopress_social_accounts_jsonld_hook' );
    }

    public static function detect_external_schema( $post_id ) {
        global $wpdb;
        $types = [];

        // AIOSEO
        $aioseo_table = $wpdb->prefix . 'aioseo_posts';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$aioseo_table}'" ) === $aioseo_table ) {
            $val = $wpdb->get_var( $wpdb->prepare(
                "SELECT schema FROM {$aioseo_table} WHERE post_id = %d", $post_id
            ) );
            if ( $val ) {
                $data = json_decode( $val, true );
                if ( ! empty( $data['default']['graphName'] ) ) {
                    $types[] = $data['default']['graphName'];
                }
            }
        }

        // Yoast
        $yoast = get_post_meta( $post_id, '_yoast_wpseo_schema_page_type', true );
        if ( $yoast ) $types[] = $yoast;

        // Rank Math
        $rm = get_post_meta( $post_id, 'rank_math_rich_snippet', true );
        if ( $rm ) $types[] = $rm;

        return ! empty( $types ) ? $types : false;
    }

    // ── Schema Detection from Content ─────────────────────────────────────────

    public static function detect_schema_for_post( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) return [];

        $content  = apply_filters( 'the_content', $post->post_content );
        $url      = get_permalink( $post_id );
        $detected = [];

        // Siloq architecture data
        $siloq_role = get_post_meta( $post_id, '_siloq_page_type', true ) ?: 'unknown';

        // 1. BreadcrumbList — always
        $breadcrumbs = self::build_breadcrumbs( $post );
        $detected[]  = [
            'type'       => 'BreadcrumbList',
            'confidence' => 100,
            'reason'     => 'Auto-generated from Hub & Spoke architecture',
            'json_ld'    => self::generate_breadcrumb_schema( $breadcrumbs ),
        ];

        // 2. Hub → LocalBusiness
        if ( in_array( $siloq_role, [ 'hub', 'pillar' ] ) ) {
            $detected[] = [
                'type'       => 'LocalBusiness',
                'confidence' => 96,
                'reason'     => 'Hub page — primary business entity for this silo',
                'json_ld'    => self::generate_local_business_schema( $post ),
            ];
        }

        // 3. Spoke → Service
        if ( $siloq_role === 'supporting' || $siloq_role === 'spoke' ) {
            if ( self::has_service_signals( $content ) ) {
                $detected[] = [
                    'type'       => 'Service',
                    'confidence' => 94,
                    'reason'     => 'Spoke page targeting specific service offering',
                    'json_ld'    => self::generate_service_schema( $post ),
                ];
            }
        }

        // 4. FAQPage — any page
        $faq_items = self::extract_faq( $content );
        if ( count( $faq_items ) >= 2 ) {
            $detected[] = [
                'type'       => 'FAQPage',
                'confidence' => min( 95, 70 + count( $faq_items ) * 5 ),
                'reason'     => sprintf( 'Detected %d Q&A pairs in page content', count( $faq_items ) ),
                'json_ld'    => self::generate_faq_schema( $faq_items ),
            ];
        }

        // 5. HowTo — step-based content
        if ( self::has_howto_signals( $content ) ) {
            $steps = self::extract_steps( $content );
            if ( count( $steps ) >= 2 ) {
                $detected[] = [
                    'type'       => 'HowTo',
                    'confidence' => 92,
                    'reason'     => sprintf( '%d steps detected in page content', count( $steps ) ),
                    'json_ld'    => self::generate_howto_schema( $post, $steps ),
                ];
            }
        }

        // 6. Article — blog posts
        if ( $post->post_type === 'post' ) {
            $detected[] = [
                'type'       => 'Article',
                'confidence' => 92,
                'reason'     => 'Blog post — author, publish date, headline detected',
                'json_ld'    => self::generate_article_schema( $post ),
            ];
        }

        usort( $detected, fn( $a, $b ) => $b['confidence'] - $a['confidence'] );
        return $detected;
    }

    // ── JSON-LD Generators ────────────────────────────────────────────────────

    private static function generate_breadcrumb_schema( $path ) {
        $items    = [];
        $position = 1;
        $items[]  = [ '@type' => 'ListItem', 'position' => $position++, 'name' => 'Home', 'item' => home_url( '/' ) ];
        foreach ( $path as $node ) {
            $items[] = [ '@type' => 'ListItem', 'position' => $position++, 'name' => $node['title'], 'item' => $node['url'] ];
        }
        return [ '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items ];
    }

    private static function generate_local_business_schema( $post ) {
        $settings = self::get_all_settings();
        $schema   = [
            '@context'    => 'https://schema.org',
            '@type'       => $settings['business_type'] ?? 'LocalBusiness',
            'name'        => $settings['business_name'] ?? get_bloginfo( 'name' ),
            'url'         => home_url( '/' ),
            'description' => get_the_excerpt( $post ),
        ];
        if ( ! empty( $settings['business_phone'] ) ) $schema['telephone'] = $settings['business_phone'];
        if ( ! empty( $settings['business_address'] ) ) {
            $addr = json_decode( $settings['business_address'], true );
            if ( $addr ) $schema['address'] = array_merge( [ '@type' => 'PostalAddress' ], $addr );
        }
        if ( ! empty( $settings['business_hours'] ) )      $schema['openingHours'] = $settings['business_hours'];
        if ( ! empty( $settings['business_price_range'] ) ) $schema['priceRange']  = $settings['business_price_range'];
        return $schema;
    }

    private static function generate_service_schema( $post ) {
        $settings = self::get_all_settings();
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Service',
            'name'        => get_the_title( $post ),
            'description' => get_the_excerpt( $post ),
            'url'         => get_permalink( $post ),
            'provider'    => [
                '@type' => 'LocalBusiness',
                'name'  => $settings['business_name'] ?? get_bloginfo( 'name' ),
                'url'   => home_url( '/' ),
            ],
        ];
    }

    private static function generate_faq_schema( $items ) {
        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map( function( $item ) {
                return [
                    '@type'          => 'Question',
                    'name'           => $item['question'],
                    'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $item['answer'] ],
                ];
            }, $items ),
        ];
    }

    private static function generate_howto_schema( $post, $steps ) {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'HowTo',
            'name'        => get_the_title( $post ),
            'description' => get_the_excerpt( $post ),
            'step'        => array_map( function( $step, $i ) {
                return [
                    '@type'    => 'HowToStep',
                    'position' => $i + 1,
                    'name'     => $step['name'],
                    'text'     => $step['text'],
                ];
            }, $steps, array_keys( $steps ) ),
        ];
    }

    private static function generate_article_schema( $post ) {
        $settings = self::get_all_settings();
        return [
            '@context'     => 'https://schema.org',
            '@type'        => 'Article',
            'headline'     => get_the_title( $post ),
            'description'  => get_the_excerpt( $post ),
            'url'          => get_permalink( $post ),
            'datePublished'=> get_the_date( 'c', $post ),
            'dateModified' => get_the_modified_date( 'c', $post ),
            'author'       => [ '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $post->post_author ) ],
            'publisher'    => [
                '@type' => 'Organization',
                'name'  => $settings['business_name'] ?? get_bloginfo( 'name' ),
                'logo'  => $settings['business_logo'] ?? '',
            ],
        ];
    }

    // ── Content Detectors ─────────────────────────────────────────────────────

    public static function extract_faq( $html ) {
        $items = [];

        // Pattern 1: H2–H4 ending in ? followed by <p>
        preg_match_all(
            '/<h[2-4][^>]*>(.*?\?)<\/h[2-4]>\s*(<p>.*?<\/p>(?:\s*<p>.*?<\/p>)*)/si',
            $html, $matches, PREG_SET_ORDER
        );
        foreach ( $matches as $m ) {
            $q = wp_strip_all_tags( $m[1] );
            $a = wp_strip_all_tags( $m[2] );
            if ( strlen( $q ) > 10 && strlen( $a ) > 20 ) {
                $items[] = [ 'question' => trim( $q ), 'answer' => trim( $a ) ];
            }
        }

        // Pattern 2: FAQ section container
        if ( preg_match( '/<(?:div|section)[^>]*(?:class|id)=["\'][^"\']*faq[^"\']*["\'][^>]*>(.*?)<\/(?:div|section)>/si', $html, $faq ) ) {
            preg_match_all(
                '/<(?:h[2-6]|dt|strong)[^>]*>(.*?)<\/(?:h[2-6]|dt|strong)>\s*<(?:p|dd|div)[^>]*>(.*?)<\/(?:p|dd|div)>/si',
                $faq[1], $qa, PREG_SET_ORDER
            );
            foreach ( $qa as $m ) {
                $q = wp_strip_all_tags( $m[1] );
                $a = wp_strip_all_tags( $m[2] );
                if ( strlen( $q ) > 10 && strlen( $a ) > 20 ) {
                    $items[] = [ 'question' => trim( $q ), 'answer' => trim( $a ) ];
                }
            }
        }

        // Deduplicate
        $seen   = [];
        $unique = [];
        foreach ( $items as $item ) {
            $key = md5( $item['question'] );
            if ( ! isset( $seen[ $key ] ) ) {
                $seen[ $key ] = true;
                $unique[]     = $item;
            }
        }
        return $unique;
    }

    public static function has_service_signals( $html ) {
        $text = strtolower( wp_strip_all_tags( $html ) );
        $signals = [ 'service', 'repair', 'installation', 'replacement', 'maintenance', 'inspection', 'cleaning', 'treatment', 'consultation', 'estimate', 'call us', 'get a quote', 'free estimate' ];
        foreach ( $signals as $s ) {
            if ( strpos( $text, $s ) !== false ) return true;
        }
        return false;
    }

    public static function has_howto_signals( $html ) {
        $text = strtolower( $html );
        return preg_match( '/step\s*\d|how\s+to\s+|<ol[^>]*>.*<li/si', $text ) ? true : false;
    }

    public static function extract_steps( $html ) {
        $steps = [];
        if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/si', $html, $matches ) ) {
            foreach ( $matches[1] as $i => $li ) {
                $text = trim( wp_strip_all_tags( $li ) );
                if ( strlen( $text ) > 15 ) {
                    $steps[] = [ 'name' => 'Step ' . ( $i + 1 ), 'text' => $text ];
                }
            }
        }
        return array_slice( $steps, 0, 10 );
    }

    private static function build_breadcrumbs( $post ) {
        $crumbs   = [];
        $ancestors = get_ancestors( $post->ID, 'page' );
        foreach ( array_reverse( $ancestors ) as $anc_id ) {
            $crumbs[] = [ 'title' => get_the_title( $anc_id ), 'url' => get_permalink( $anc_id ) ];
        }
        $crumbs[] = [ 'title' => get_the_title( $post ), 'url' => get_permalink( $post ) ];
        return $crumbs;
    }

    // ── Storage ───────────────────────────────────────────────────────────────

    public static function save_schema( $post_id, $type, $json_ld, $meta = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SCHEMA;
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE post_id = %d AND schema_type = %s",
            $post_id, $type
        ) );

        $data = [
            'post_id'          => $post_id,
            'schema_type'      => $type,
            'schema_json'      => wp_json_encode( $json_ld, JSON_UNESCAPED_SLASHES ),
            'confidence'       => intval( $meta['confidence'] ?? 0 ),
            'detection_reason' => sanitize_text_field( $meta['reason'] ?? '' ),
            'source'           => $meta['source'] ?? 'auto',
            'validation_status'=> $meta['validation_status'] ?? 'pending',
            'is_active'        => 1,
            'updated_at'       => current_time( 'mysql' ),
        ];

        if ( $existing ) {
            $wpdb->update( $table, $data, [ 'id' => $existing ] );
            $id = $existing;
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $wpdb->insert( $table, $data );
            $id = $wpdb->insert_id;
        }

        self::purge_cache( $post_id );
        return $id;
    }

    public static function get_active_schema( $post_id ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SCHEMA;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE post_id = %d AND is_active = 1 ORDER BY schema_type ASC",
            $post_id
        ), ARRAY_A );
    }

    // ── Cache Purge ────────────────────────────────────────────────────────────

    public static function purge_cache( $post_id ) {
        $url = get_permalink( $post_id );
        if ( function_exists( 'wp_cache_post_change' ) )  wp_cache_post_change( $post_id );
        if ( function_exists( 'w3tc_flush_post' ) )        w3tc_flush_post( $post_id );
        if ( function_exists( 'rocket_clean_post' ) )      rocket_clean_post( $post_id );
        if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache( $url );
        if ( class_exists( 'LiteSpeed_Cache_API' ) )       LiteSpeed_Cache_API::purge_post( $post_id );
        do_action( 'cloudflare_purge_by_url', $url );
        clean_post_cache( $post_id );
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    public static function get_setting( $key, $default = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SETTINGS;
        $val   = $wpdb->get_var( $wpdb->prepare(
            "SELECT setting_value FROM {$table} WHERE setting_key = %s", $key
        ) );
        return $val !== null ? $val : $default;
    }

    public static function get_all_settings() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SETTINGS;
        $rows  = $wpdb->get_results( "SELECT setting_key, setting_value FROM {$table}", ARRAY_A );
        $out   = [];
        foreach ( $rows as $row ) $out[ $row['setting_key'] ] = $row['setting_value'];
        return $out;
    }

    public static function update_setting( $key, $value ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SETTINGS;
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$table} (setting_key, setting_value) VALUES (%s, %s)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
            $key, $value
        ) );
    }

    // ── REST Routes ────────────────────────────────────────────────────────────

    public static function register_routes() {
        $ns = 'siloq/v1';

        register_rest_route( $ns, '/schema/(?P<post_id>\d+)', [
            [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'route_get' ],  'permission_callback' => [ __CLASS__, 'auth' ] ],
            [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'route_save' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
        ] );

        register_rest_route( $ns, '/schema/detect/(?P<post_id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'route_detect' ],
            'permission_callback' => [ __CLASS__, 'auth' ],
        ] );

        register_rest_route( $ns, '/schema/toggle/(?P<id>\d+)', [
            'methods'             => 'PATCH',
            'callback'            => [ __CLASS__, 'route_toggle' ],
            'permission_callback' => [ __CLASS__, 'auth' ],
        ] );

        register_rest_route( $ns, '/schema/settings', [
            [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'route_get_settings' ],    'permission_callback' => [ __CLASS__, 'auth' ] ],
            [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'route_update_settings' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
        ] );

        register_rest_route( $ns, '/schema/detect-bulk', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'route_detect_bulk' ],
            'permission_callback' => [ __CLASS__, 'auth' ],
        ] );
    }

    public static function auth( $request ) {
        $key = $request->get_header( 'X-Siloq-Key' ) ?: $request->get_param( 'api_key' );
        if ( ! $key ) return new WP_Error( 'unauthorized', 'API key required', [ 'status' => 401 ] );
        $stored = get_option( 'siloq_api_key' );
        return $stored && hash_equals( $stored, $key );
    }

    public static function route_detect( $request ) {
        $post_id  = intval( $request['post_id'] );
        $detected = self::detect_schema_for_post( $post_id );
        $existing = self::detect_external_schema( $post_id );
        $active   = self::get_active_schema( $post_id );
        $post     = get_post( $post_id );

        return rest_ensure_response( [
            'post_id'                => $post_id,
            'page_title'             => $post ? $post->post_title : '',
            'page_url'               => $post ? get_permalink( $post_id ) : '',
            'detected_schemas'       => $detected,
            'existing_external_schema' => $existing ?: [],
            'siloq_active_schema'    => $active,
        ] );
    }

    public static function route_save( $request ) {
        $post_id = intval( $request['post_id'] );
        $schemas = $request->get_json_params();
        if ( ! is_array( $schemas ) ) {
            return new WP_Error( 'invalid', 'Expected array of schema objects', [ 'status' => 400 ] );
        }

        $results = [];
        foreach ( $schemas as $schema ) {
            if ( empty( $schema['type'] ) || empty( $schema['json_ld'] ) ) continue;
            $id        = self::save_schema( $post_id, $schema['type'], $schema['json_ld'], $schema );
            $results[] = [ 'type' => $schema['type'], 'id' => $id ];
        }

        return rest_ensure_response( [
            'success'  => true,
            'post_id'  => $post_id,
            'results'  => $results,
            'message'  => sprintf( '%d schema types injected via wp_head.', count( $results ) ),
        ] );
    }

    public static function route_get( $request ) {
        return rest_ensure_response( self::get_active_schema( intval( $request['post_id'] ) ) );
    }

    public static function route_toggle( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_SCHEMA;
        $id    = intval( $request['id'] );
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
        if ( ! $row ) return new WP_Error( 'not_found', 'Schema not found', [ 'status' => 404 ] );
        $new_status = $row->is_active ? 0 : 1;
        $wpdb->update( $table, [ 'is_active' => $new_status ], [ 'id' => $id ] );
        self::purge_cache( $row->post_id );
        return rest_ensure_response( [ 'id' => $id, 'is_active' => (bool) $new_status ] );
    }

    public static function route_get_settings( $request ) {
        return rest_ensure_response( self::get_all_settings() );
    }

    public static function route_update_settings( $request ) {
        $params = $request->get_json_params();
        $allowed = [ 'business_name','business_type','business_phone','business_address','business_hours','business_logo','business_price_range','social_profiles','auto_breadcrumbs','auto_faq_detection','auto_apply','conflict_mode' ];
        foreach ( $params as $key => $value ) {
            if ( in_array( $key, $allowed ) ) self::update_setting( $key, $value );
        }
        return rest_ensure_response( [ 'success' => true, 'settings' => self::get_all_settings() ] );
    }

    public static function route_detect_bulk( $request ) {
        $post_ids = $request->get_json_params();
        if ( ! is_array( $post_ids ) ) return new WP_Error( 'invalid', 'Expected array of post_ids', [ 'status' => 400 ] );

        $results = [];
        foreach ( array_slice( $post_ids, 0, 50 ) as $post_id ) {
            $detected  = self::detect_schema_for_post( intval( $post_id ) );
            $results[] = [ 'post_id' => $post_id, 'detected' => $detected, 'count' => count( $detected ) ];
        }
        return rest_ensure_response( [ 'results' => $results, 'total_pages' => count( $results ) ] );
    }
}
