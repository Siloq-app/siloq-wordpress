<?php
/**
 * Siloq Agent Ready — llms.txt, Authority Manifest, AI Visibility Audit
 *
 * Generates and virtually serves:
 *   /llms.txt
 *   /.well-known/authority-manifest.json
 *
 * All files are served via WP rewrite rules (no disk writes needed).
 * Generated content is cached in WP options; regenerated on every full sync.
 *
 * @since 1.5.139
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Siloq_Agent_Ready {

    const OPTION_GENERATED_AT      = 'siloq_agent_files_generated_at';
    const OPTION_LLMS_CONTENT      = 'siloq_llms_txt_content';
    const OPTION_MANIFEST_CONTENT  = 'siloq_authority_manifest_content';
    const OPTION_AUDIT_CACHE       = 'siloq_ai_visibility_audit_cache';
    const QUERY_VAR                = 'siloq_agent_file';

    // ─────────────────────────────────────────────────────────────────────────
    // Bootstrap
    // ─────────────────────────────────────────────────────────────────────────

    public static function init() {
        add_action( 'init',              [ __CLASS__, 'register_rewrite_rules' ] );
        add_action( 'template_redirect', [ __CLASS__, 'maybe_serve_agent_file' ] );
        add_filter( 'query_vars',        [ __CLASS__, 'add_query_var' ] );
    }

    public static function add_query_var( $vars ) {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Virtual file routing
    // ─────────────────────────────────────────────────────────────────────────

    public static function register_rewrite_rules() {
        add_rewrite_rule( '^llms\.txt$',
            'index.php?' . self::QUERY_VAR . '=llms.txt', 'top' );
        add_rewrite_rule( '^\.well-known/authority-manifest\.json$',
            'index.php?' . self::QUERY_VAR . '=authority-manifest.json', 'top' );
    }

    public static function flush_rewrites() {
        self::register_rewrite_rules();
        flush_rewrite_rules();
    }

    public static function maybe_serve_agent_file() {
        $file = get_query_var( self::QUERY_VAR );
        if ( ! $file ) return;

        if ( $file === 'llms.txt' ) {
            $content = get_option( self::OPTION_LLMS_CONTENT, '' );
            if ( ! $content ) {
                $content = self::generate_llms_txt();
            }
            nocache_headers();
            header( 'Content-Type: text/plain; charset=utf-8' );
            header( 'Cache-Control: public, max-age=3600' );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $content;
            exit;
        }

        if ( $file === 'authority-manifest.json' ) {
            $content = get_option( self::OPTION_MANIFEST_CONTENT, '' );
            if ( ! $content ) {
                $content = self::generate_authority_manifest();
            }
            nocache_headers();
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Cache-Control: public, max-age=3600' );
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $content;
            exit;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // File generation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate both files, cache in WP options, record timestamp.
     */
    public static function generate_files() {
        $llms     = self::generate_llms_txt();
        $manifest = self::generate_authority_manifest();

        update_option( self::OPTION_LLMS_CONTENT,     $llms,     false );
        update_option( self::OPTION_MANIFEST_CONTENT, $manifest, false );
        update_option( self::OPTION_GENERATED_AT,     time(),    false );

        return [ 'llms' => $llms, 'manifest' => $manifest ];
    }

    /**
     * Build llms.txt plain-text content from WP options.
     */
    public static function generate_llms_txt() {
        $name          = get_option( 'siloq_business_name', get_bloginfo( 'name' ) );
        $biz_type      = get_option( 'siloq_business_type', '' );
        $city          = get_option( 'siloq_city', '' );
        $state         = get_option( 'siloq_state', '' );
        $founding_year = get_option( 'siloq_founding_year', '' );
        $services      = json_decode( get_option( 'siloq_primary_services', '[]' ), true ) ?: [];
        $areas         = json_decode( get_option( 'siloq_service_areas',    '[]' ), true ) ?: [];
        $site_url      = trailingslashit( get_site_url() );
        $now           = gmdate( 'Y-m-d\TH:i:s\Z' );

        $type_labels = [
            'local_service' => 'Local Service Business',
            'ecommerce'     => 'E-Commerce Business',
            'content_blog'  => 'Blog / Publisher',
            'saas'          => 'SaaS / Software',
            'other'         => 'Business',
        ];
        $type_label = $type_labels[ $biz_type ] ?? 'Business';
        $location   = trim( $city . ( $city && $state ? ', ' : '' ) . $state );

        $content_pages = self::get_site_content_pages();
        $hub_pages     = $content_pages['hub_pages'];
        $service_pages = $content_pages['service_pages'];
        $city_pages    = $content_pages['city_pages'];

        $L = []; // lines

        $L[] = "# Authority Manifest for {$name}";
        $L[] = "# Generated by Siloq Authority Engine";
        $L[] = "# Last Updated: {$now}";
        $L[] = '';
        $L[] = '## Identity';
        $L[] = "Name: {$name}";
        $L[] = "Type: {$type_label}";
        if ( $location )      $L[] = "Location: {$location}";
        if ( $founding_year ) $L[] = "Founded: {$founding_year}";
        $L[] = "Website: {$site_url}";
        $phone   = get_option( 'siloq_phone', '' );
        $address = get_option( 'siloq_address', '' );
        $zip     = get_option( 'siloq_zip', '' );
        if ( $phone ) {
            $L[] = "Phone: {$phone}";
        }
        if ( $address ) {
            $full_addr = trim( $address . ( $zip ? ", {$zip}" : '' ) . ( $location ? ", {$location}" : '' ) );
            $L[] = "Address: {$full_addr}";
        }
        $L[] = "Authority Manifest: {$site_url}.well-known/authority-manifest.json";
        $L[] = '';

        if ( ! empty( $services ) ) {
            $L[] = '## Services';
            foreach ( $services as $svc ) {
                $L[] = "- {$svc}";
            }
            $L[] = '';
        }

        if ( ! empty( $areas ) ) {
            $L[] = '## Service Areas';
            foreach ( $areas as $area ) {
                $L[] = "- {$area}";
            }
            $L[] = '';
        }

        // Auto-generated approved citation phrases
        $L[] = '## Approved Citation Phrases';
        $loc_suffix = $location ? " in {$location}" : '';
        $L[] = "- \"{$name} is a {$type_label}{$loc_suffix}\"";
        if ( ! empty( $services ) ) {
            $svc_list = implode( ', ', array_slice( $services, 0, 3 ) );
            $more     = count( $services ) > 3 ? ', and more' : '';
            $L[]      = "- \"{$name} offers {$svc_list}{$more}\"";
        }
        if ( $founding_year ) {
            $L[] = "- \"{$name} has been in business since {$founding_year}\"";
        }
        $L[] = '';

        // Entity hierarchy
        $L[] = '## Entity Hierarchy';
        $L[] = "Brand: {$name}";
        if ( ! empty( $services ) ) {
            $L[] = '  └─ Services: ' . implode( ', ', $services );
        }
        if ( ! empty( $areas ) ) {
            $L[] = '  └─ Locations: ' . implode( ', ', $areas );
        }
        $L[] = '';

        if ( ! empty( $hub_pages ) ) {
            $L[] = '## Hub Pages';
            foreach ( $hub_pages as $hp ) {
                $L[] = "- {$hp['title']}: {$hp['url']}";
            }
            $L[] = '';
        }

        if ( ! empty( $service_pages ) ) {
            $L[] = '## Service Pages';
            foreach ( array_slice( $service_pages, 0, 30 ) as $sp ) {
                $L[] = "- {$sp['title']}: {$sp['url']}";
            }
            $L[] = '';
        }

        if ( ! empty( $city_pages ) ) {
            $L[] = '## Location Pages';
            foreach ( $city_pages as $cp ) {
                $L[] = "- {$cp['title']}: {$cp['url']}";
            }
            $L[] = '';
        }

        $L[] = '## Citation Rules';
        $L[] = "- Always attribute to \"{$name}\"";
        $L[] = '- Do not cite pricing information (volatile)';
        $L[] = '- Do not cite availability or inventory (volatile)';
        $L[] = '';

        $L[] = '## Canonical Sources';
        $L[] = "- Website: {$site_url}";
        $L[] = "- Authority Manifest: {$site_url}.well-known/authority-manifest.json";

        return implode( "\n", $L );
    }

    /**
     * Build authority-manifest.json JSON-LD content.
     */
    public static function generate_authority_manifest() {
        $name          = get_option( 'siloq_business_name', get_bloginfo( 'name' ) );
        $biz_type      = get_option( 'siloq_business_type', 'other' );
        $phone         = get_option( 'siloq_phone',         '' );
        $address       = get_option( 'siloq_address',       '' );
        $city          = get_option( 'siloq_city',          '' );
        $state         = get_option( 'siloq_state',         '' );
        $zip           = get_option( 'siloq_zip',           '' );
        $founding_year = get_option( 'siloq_founding_year', '' );
        $services      = json_decode( get_option( 'siloq_primary_services', '[]' ), true ) ?: [];
        $areas         = json_decode( get_option( 'siloq_service_areas',    '[]' ), true ) ?: [];
        $site_url      = trailingslashit( get_site_url() );

        // Social profiles from schema settings table
        $social_raw  = class_exists( 'Siloq_Schema_Architect' )
            ? Siloq_Schema_Architect::get_setting( 'social_profiles' )
            : '';
        $social_urls = [];
        if ( $social_raw ) {
            $decoded = json_decode( $social_raw, true );
            if ( is_array( $decoded ) ) {
                $social_urls = array_values( array_filter( $decoded ) );
            } else {
                // plain newline-separated list
                $social_urls = array_values( array_filter( array_map( 'trim', explode( "\n", $social_raw ) ) ) );
            }
        }

        $type_map = [
            'local_service' => 'LocalBusiness',
            'ecommerce'     => 'Store',
            'content_blog'  => 'Blog',
            'saas'          => 'SoftwareApplication',
            'other'         => 'Organization',
        ];
        $schema_type = $type_map[ $biz_type ] ?? 'LocalBusiness';

        $city_pages = self::get_city_pages();

        $manifest = [
            '@context' => 'https://schema.org',
            '@type'    => $schema_type,
            'name'     => $name,
            'url'      => $site_url,
        ];

        if ( $address || $city || $state ) {
            $manifest['address'] = array_filter( [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $address,
                'addressLocality' => $city,
                'addressRegion'   => $state,
                'postalCode'      => $zip,
                'addressCountry'  => 'US',
            ] );
        }

        if ( $phone )         $manifest['telephone']   = $phone;
        if ( $founding_year ) $manifest['foundingDate'] = $founding_year;

        if ( ! empty( $services ) ) {
            $manifest['hasOfferCatalog'] = [
                '@type'           => 'OfferCatalog',
                'name'            => 'Services',
                'itemListElement' => array_map( function ( $svc ) {
                    return [
                        '@type'        => 'Offer',
                        'itemOffered'  => [ '@type' => 'Service', 'name' => $svc ],
                    ];
                }, $services ),
            ];
        }

        if ( ! empty( $areas ) ) {
            $manifest['serviceArea'] = array_map( function ( $area ) {
                return [ '@type' => 'AdministrativeArea', 'name' => $area ];
            }, $areas );
        }

        if ( ! empty( $city_pages ) ) {
            $manifest['hasPart'] = array_map( function ( $cp ) {
                return [ '@type' => 'WebPage', 'name' => $cp['title'], 'url' => $cp['url'] ];
            }, $city_pages );
        }

        if ( ! empty( $social_urls ) ) {
            $manifest['sameAs'] = $social_urls;
        }

        // Siloq metadata footer
        $manifest['_siloq'] = [
            'generated_by' => 'Siloq Authority Engine',
            'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'plugin_version' => defined( 'SILOQ_VERSION' ) ? SILOQ_VERSION : '1.5.139',
        ];

        return wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Badge status
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns badge status array: status, badge text, color, message, missing fields.
     */
    public static function get_badge_status() {
        $generated_at = (int) get_option( self::OPTION_GENERATED_AT, 0 );
        $llms_content = get_option( self::OPTION_LLMS_CONTENT, '' );
        $name         = get_option( 'siloq_business_name', '' );
        $services     = json_decode( get_option( 'siloq_primary_services', '[]' ), true ) ?: [];
        $city         = get_option( 'siloq_city', '' );
        $state        = get_option( 'siloq_state', '' );
        $areas        = json_decode( get_option( 'siloq_service_areas', '[]' ), true ) ?: [];
        $llms_url     = trailingslashit( get_site_url() ) . 'llms.txt';

        // Build list of missing data
        $missing = [];
        if ( ! $name )                 $missing[] = 'Business Name';
        // Compare hub pages on the site against the business profile services list.
        // Flag hub pages not mentioned in profile so user knows to add them.
        $hub_post_query = get_posts( array(
            'post_type'   => array( 'page', 'post' ),
            'post_status' => 'publish',
            'numberposts' => 50,
            'meta_query'  => array(
                'relation' => 'OR',
                array( 'key' => '_siloq_page_role',         'value' => 'hub',      'compare' => '=' ),
                array( 'key' => '_siloq_page_role',         'value' => 'apex_hub', 'compare' => '=' ),
                array( 'key' => 'page_type_classification', 'value' => 'hub',      'compare' => '=' ),
            ),
        ) );

        $profile_services_lower = array_map( 'strtolower', array_map( 'trim', $services ) );
        $unlisted_hubs = array();
        foreach ( $hub_post_query as $hp ) {
            $title_lower = strtolower( trim( $hp->post_title ) );
            $found = false;
            foreach ( $profile_services_lower as $ps ) {
                if ( $ps && ( strpos( $title_lower, $ps ) !== false || strpos( $ps, $title_lower ) !== false ) ) {
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) {
                $unlisted_hubs[] = $hp->post_title;
            }
        }

        if ( ! empty( $unlisted_hubs ) ) {
            update_option( 'siloq_unlisted_hub_services', wp_json_encode( array_slice( $unlisted_hubs, 0, 10 ) ), false );
            $missing[] = count( $unlisted_hubs ) . ' service page(s) not in profile';
        } elseif ( count( $services ) < 2 ) {
            $missing[] = count( $services ) . '/2+ services in profile';
        }
        if ( ! $city && empty( $areas ) ) $missing[] = 'Location';

        // Red: files never generated
        if ( ! $llms_content || ! $generated_at ) {
            return [
                'status'       => 'not_ready',
                'badge'        => '✗ Not Agent-Ready',
                'color'        => 'red',
                'message'      => 'Agent files have not been generated yet.',
                'missing'      => $missing,
                'generated_at' => null,
                'llms_url'     => $llms_url,
            ];
        }

        // Stale if older than 7 days
        $seven_days = 7 * DAY_IN_SECONDS;
        $is_stale   = ( time() - $generated_at ) > $seven_days;
        $generated_date = wp_date( 'M j, Y', $generated_at );

        // Amber: files exist but incomplete or stale
        if ( ! empty( $missing ) || $is_stale ) {
            $parts = [];
            if ( $is_stale )          $parts[] = 'Files last generated ' . $generated_date;
            if ( ! empty( $missing ) ) $parts[] = 'Missing: ' . implode( ', ', $missing );
            return [
                'status'       => 'incomplete',
                'badge'        => '⚠ Incomplete',
                'color'        => 'amber',
                'message'      => implode( '. ', $parts ),
                'missing'      => $missing,
                'generated_at' => $generated_at,
                'llms_url'     => $llms_url,
            ];
        }

        // Green
        return [
            'status'       => 'ready',
            'badge'        => '✓ Agent-Ready',
            'color'        => 'green',
            'message'      => 'Your site is visible to AI agents. Last updated ' . $generated_date . '.',
            'missing'      => [],
            'generated_at' => $generated_at,
            'llms_url'     => $llms_url,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AI Visibility Audit (5 checks)
    // ─────────────────────────────────────────────────────────────────────────

    public static function run_ai_visibility_audit() {
        $results = [];

        // ── Check 1: AI crawlers allowed ─────────────────────────────────────
        $robots_url      = trailingslashit( get_site_url() ) . 'robots.txt';
        $robots_response = wp_remote_get( $robots_url, [ 'timeout' => 6 ] );

        if ( ! is_wp_error( $robots_response ) && wp_remote_retrieve_response_code( $robots_response ) === 200 ) {
            $body         = wp_remote_retrieve_body( $robots_response );
            $blocked_bots = self::find_blocked_ai_bots( $body );

            if ( ! empty( $blocked_bots ) ) {
                $results['robots'] = [
                    'pass'     => false,
                    'severity' => 'red',
                    'label'    => 'AI Crawlers Allowed',
                    'message'  => 'AI crawlers are blocked from reading your site. Update robots.txt to allow: ' . implode( ', ', $blocked_bots ),
                ];
            } else {
                $results['robots'] = [
                    'pass'     => true,
                    'severity' => 'green',
                    'label'    => 'AI Crawlers Allowed',
                    'message'  => 'AI crawlers can access your site.',
                ];
            }
        } else {
            // No robots.txt or unreachable — give benefit of doubt
            $results['robots'] = [
                'pass'     => true,
                'severity' => 'green',
                'label'    => 'AI Crawlers Allowed',
                'message'  => 'No robots.txt restriction found — AI crawlers have full access.',
            ];
        }

        // ── Check 2: Content visible without JavaScript ───────────────────────
        $results['js_content'] = self::check_js_visibility();

        // ── Check 3: Schema present (>50% of synced pages) ───────────────────
        $results['schema'] = self::check_schema_coverage();

        // ── Check 4: llms.txt exists ──────────────────────────────────────────
        $llms_content = get_option( self::OPTION_LLMS_CONTENT, '' );
        if ( $llms_content ) {
            $results['llms'] = [
                'pass'      => true,
                'severity'  => 'green',
                'label'     => 'llms.txt Exists',
                'message'   => 'Agent files are generated and publicly accessible.',
                'link'      => trailingslashit( get_site_url() ) . 'llms.txt',
                'link_text' => 'View llms.txt →',
            ];
        } else {
            $results['llms'] = [
                'pass'        => false,
                'severity'    => 'red',
                'label'       => 'llms.txt Exists',
                'message'     => 'Agent files have not been generated.',
                'action'      => 'generate',
                'action_text' => 'Generate Agent Files →',
            ];
        }

        // ── Check 5: Pages indexed via GSC ───────────────────────────────────
        $gsc_connected = get_option( 'siloq_gsc_connected', '' );
        if ( $gsc_connected !== 'yes' ) {
            $results['indexed'] = [
                'pass'      => null,
                'severity'  => 'amber',
                'label'     => 'Pages Indexed',
                'message'   => 'Connect Google Search Console to verify indexing.',
                'link'      => '#siloq-tab-gsc',
                'link_text' => 'Connect GSC →',
            ];
        } else {
            // GSC connected — check if we have impression data
            $gsc_data = get_option( 'siloq_gsc_impressions_summary', [] );
            if ( empty( $gsc_data ) ) {
                $results['indexed'] = [
                    'pass'      => null,
                    'severity'  => 'amber',
                    'label'     => 'Pages Indexed',
                    'message'   => 'GSC connected — syncing data.',
                    'link'      => '#siloq-tab-gsc',
                    'link_text' => 'View GSC →',
                ];
            } else {
                $results['indexed'] = [
                    'pass'      => true,
                    'severity'  => 'green',
                    'label'     => 'Pages Indexed',
                    'message'   => 'GSC connected and showing impression data.',
                    'link'      => '#siloq-tab-gsc',
                    'link_text' => 'View GSC →',
                ];
            }
        }

        // Score
        $passing = count( array_filter( $results, function( $r ) { return $r['pass'] === true; } ) );
        $total   = count( $results );

        return [
            'checks'    => $results,
            'score'     => $passing,
            'total'     => $total,
            'cached_at' => time(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Audit helpers
    // ─────────────────────────────────────────────────────────────────────────

    private static function find_blocked_ai_bots( $robots_txt ) {
        $blocked   = [];
        $ai_bots   = [ 'GPTBot', 'ClaudeBot', 'PerplexityBot', 'anthropic-ai', 'Google-Extended' ];
        $lines     = preg_split( '/\r?\n/', $robots_txt );
        $current_ua_matches = false;

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( stripos( $line, 'User-agent:' ) === 0 ) {
                $ua = trim( substr( $line, strlen( 'User-agent:' ) ) );
                // Check if this user-agent is one of our AI bots
                $current_ua_matches = false;
                foreach ( $ai_bots as $bot ) {
                    if ( strcasecmp( $ua, $bot ) === 0 || $ua === '*' ) {
                        $current_ua_matches = ( $ua === '*' ) ? '__wildcard__' : $bot;
                        break;
                    }
                }
            } elseif ( $current_ua_matches && stripos( $line, 'Disallow:' ) === 0 ) {
                $path = trim( substr( $line, strlen( 'Disallow:' ) ) );
                if ( $path === '/' ) {
                    if ( $current_ua_matches === '__wildcard__' ) {
                        // Wildcard block — report all AI bots
                        $blocked = $ai_bots;
                        break;
                    }
                    $blocked[] = $current_ua_matches;
                }
            }
        }

        return array_unique( $blocked );
    }

    private static function check_js_visibility() {
        global $wpdb;

        $elementor_pages = $wpdb->get_results(
            "SELECT p.ID, p.post_title
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 AND pm.meta_key = '_elementor_data'
                 AND LENGTH(pm.meta_value) > 10
                 AND pm.meta_value != '[]'
             WHERE p.post_status = 'publish'
               AND p.post_type IN ('page', 'post')
             ORDER BY p.post_modified DESC
             LIMIT 3"
        );

        if ( empty( $elementor_pages ) ) {
            return [
                'pass'     => true,
                'severity' => 'green',
                'label'    => 'Content Visible Without JavaScript',
                'message'  => 'No Elementor pages detected — content is server-rendered.',
            ];
        }

        $low_visibility = [];
        foreach ( $elementor_pages as $ep ) {
            $post_content = get_post_field( 'post_content', $ep->ID );
            $elem_data    = get_post_meta( $ep->ID, '_elementor_data', true );
            $elem_text    = self::extract_text_from_elementor( $elem_data );

            $post_len = strlen( strip_tags( $post_content ) );
            $elem_len = strlen( $elem_text );

            if ( $elem_len > 150 && $post_len < ( $elem_len * 0.20 ) ) {
                $low_visibility[] = $ep->post_title;
            }
        }

        if ( ! empty( $low_visibility ) ) {
            return [
                'pass'     => false,
                'severity' => 'amber',
                'label'    => 'Content Visible Without JavaScript',
                'message'  => 'Content may be invisible to AI crawlers (JS-only rendering): '
                              . implode( ', ', $low_visibility ),
            ];
        }

        return [
            'pass'     => true,
            'severity' => 'green',
            'label'    => 'Content Visible Without JavaScript',
            'message'  => 'Page content appears accessible to AI crawlers.',
        ];
    }

    private static function check_schema_coverage() {
        global $wpdb;

        $total_synced = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id)
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_siloq_synced' AND meta_value = '1'"
        );

        if ( $total_synced === 0 ) {
            return [
                'pass'      => false,
                'severity'  => 'amber',
                'label'     => 'Schema Present',
                'message'   => 'No synced pages found. Sync your pages to enable this check.',
                'link'      => '#siloq-tab-schema',
                'link_text' => 'Go to Schema Tab →',
            ];
        }

        // Count from post meta flag (_siloq_schema_applied = 1)
        $schema_via_meta = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id)
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_siloq_schema_applied' AND meta_value = '1'"
        );

        // Also count from the Schema Architect DB table (more reliable — this is
        // what actually gets injected into wp_head, post meta can fall out of sync)
        $schema_table  = $wpdb->prefix . 'siloq_schema';
        $schema_via_db = 0;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$schema_table}'" ) === $schema_table ) {
            $schema_via_db = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id) FROM {$schema_table} WHERE is_active = 1"
            );
        }

        // Also count pages with _siloq_applied_types meta set (Schema Architect writes this)
        $schema_via_types = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id)
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_siloq_applied_types' AND meta_value != '' AND meta_value IS NOT NULL"
        );

        $schema_applied = max( $schema_via_meta, $schema_via_db, $schema_via_types );
        $pct = round( ( $schema_applied / $total_synced ) * 100 );

        if ( $pct >= 50 ) {
            return [
                'pass'      => true,
                'severity'  => 'green',
                'label'     => 'Schema Present',
                'message'   => "{$pct}% of synced pages have schema applied.",
                'link'      => '#siloq-tab-schema',
                'link_text' => 'View Schema →',
            ];
        }

        return [
            'pass'      => false,
            'severity'  => 'amber',
            'label'     => 'Schema Present',
            'message'   => "Only {$pct}% of synced pages have schema. Apply schema to improve AI recognition.",
            'link'      => '#siloq-tab-schema',
            'link_text' => 'Go to Schema Tab →',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Elementor text extraction
    // ─────────────────────────────────────────────────────────────────────────

    private static function extract_text_from_elementor( $elementor_data ) {
        if ( ! $elementor_data || $elementor_data === '[]' ) return '';
        $data = json_decode( $elementor_data, true );
        if ( ! is_array( $data ) ) return '';
        $text = '';
        self::extract_text_recursive( $data, $text );
        return $text;
    }

    private static function extract_text_recursive( $elements, &$text ) {
        if ( ! is_array( $elements ) ) return;
        foreach ( $elements as $el ) {
            if ( isset( $el['settings'] ) && is_array( $el['settings'] ) ) {
                foreach ( [ 'editor', 'text', 'title', 'description', 'content', 'html' ] as $key ) {
                    if ( ! empty( $el['settings'][ $key ] ) && is_string( $el['settings'][ $key ] ) ) {
                        $text .= ' ' . strip_tags( $el['settings'][ $key ] );
                    }
                }
            }
            if ( ! empty( $el['elements'] ) ) {
                self::extract_text_recursive( $el['elements'], $text );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // City / service area pages
    // ─────────────────────────────────────────────────────────────────────────

    private static function get_site_content_pages() {
        $hub_pages     = array();
        $service_pages = array();
        $city_pages    = array();
        $seen_urls     = array();

        // URL patterns that indicate plugin internals, templates, or junk — never include in llms.txt
        $exclude_patterns = array(
            '?elementor_library=',
            '?jet-engine=',
            'home-slider',
            'home_slider',
            'coming-soon',
            'coming_soon',
            '?s=',
            '?page_id=',
        );

        // Generic titles that indicate non-content pages
        $skip_titles = array( 'Content area', 'Home Slider', 'Coming Soon', 'Search Results', 'Untitled', '' );

        $posts = get_posts( array(
            'post_type'   => array( 'page', 'post' ),
            'post_status' => 'publish',
            'numberposts' => 200,
            'orderby'     => 'menu_order title',
            'order'       => 'ASC',
            'meta_query'  => array(
                'relation' => 'OR',
                array( 'key' => '_siloq_page_role', 'compare' => 'EXISTS' ),
                array( 'key' => '_siloq_synced',    'compare' => 'EXISTS' ),
            ),
        ) );

        foreach ( $posts as $p ) {
            $url = get_permalink( $p->ID );
            if ( ! $url ) continue;

            // Filter bad URLs
            $bad = false;
            foreach ( $exclude_patterns as $pat ) {
                if ( strpos( $url, $pat ) !== false ) {
                    $bad = true;
                    break;
                }
            }
            if ( $bad ) continue;

            // Deduplicate by URL
            $url_key = rtrim( $url, '/' );
            if ( isset( $seen_urls[ $url_key ] ) ) continue;
            $seen_urls[ $url_key ] = true;

            // Filter junk titles
            $title = trim( $p->post_title );
            if ( in_array( $title, $skip_titles ) || strlen( $title ) <= 3 ) continue;

            $role           = get_post_meta( $p->ID, '_siloq_page_role', true );
            $classification = get_post_meta( $p->ID, 'page_type_classification', true );
            $effective_role = $role ?: $classification;

            if ( in_array( $effective_role, array( 'hub', 'apex_hub' ) ) ) {
                $hub_pages[] = array( 'title' => $title, 'url' => $url );
            } elseif ( $effective_role === 'spoke' ) {
                $city_pages[] = array( 'title' => $title, 'url' => $url );
            } else {
                $service_pages[] = array( 'title' => $title, 'url' => $url );
            }
        }

        return compact( 'hub_pages', 'service_pages', 'city_pages' );
    }

    private static function get_city_pages() {
        global $wpdb;

        // Pages classified as spoke for a service-area hub
        $rows = $wpdb->get_results(
            "SELECT DISTINCT p.ID, p.post_title
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 AND pm.meta_key = '_siloq_page_role'
                 AND pm.meta_value = 'spoke'
             WHERE p.post_status = 'publish'
             LIMIT 50"
        );

        $result = [];
        foreach ( $rows as $row ) {
            $url = get_permalink( $row->ID );
            if ( $url ) {
                $result[] = [ 'title' => $row->post_title, 'url' => $url ];
            }
        }
        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX handlers
    // ─────────────────────────────────────────────────────────────────────────

    public static function ajax_generate_agent_files() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $files  = self::generate_files();
        $status = self::get_badge_status();

        wp_send_json_success( [
            'message'      => 'Agent files generated successfully.',
            'badge'        => $status,
            'llms_preview' => wp_strip_all_tags( substr( $files['llms'], 0, 600 ) ),
        ] );
    }

    public static function ajax_get_agent_status() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }
        wp_send_json_success( self::get_badge_status() );
    }

    public static function ajax_run_ai_visibility_audit() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized' ] );
        }

        $audit = self::run_ai_visibility_audit();
        update_option( self::OPTION_AUDIT_CACHE, $audit, false );
        wp_send_json_success( $audit );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sync hook — regenerate on every full sync
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Called from siloq-connector.php after a successful full sync.
     * Only re-generates if files have been generated at least once before.
     */
    public static function on_sync_complete() {
        if ( get_option( self::OPTION_GENERATED_AT, 0 ) ) {
            self::generate_files();
        }
    }
}
