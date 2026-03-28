<?php
/**
 * [siloq_scan_results] shortcode — 3-layer diagnostic results page
 *
 * @package Siloq_Connector
 * @since   1.5.243
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Siloq_Scan_Results_Shortcode {

    public static function init() {
        add_shortcode( 'siloq_scan_results', array( __CLASS__, 'render' ) );
    }

    public static function render( $atts ) {
        $scan_id = isset( $_GET['scan_id'] ) ? sanitize_text_field( $_GET['scan_id'] ) : '';
        $domain  = isset( $_GET['domain'] )  ? sanitize_text_field( urldecode( $_GET['domain'] ) ) : '';
        $biz     = isset( $_GET['biz'] )     ? sanitize_text_field( rawurldecode( urldecode( $_GET['biz'] ) ) ) : '';
        $email   = isset( $_GET['email'] )   ? sanitize_email( urldecode( $_GET['email'] ) )       : '';
        $name    = isset( $_GET['fname'] )   ? sanitize_text_field( urldecode( $_GET['fname'] ) )  : '';

        if ( empty( $scan_id ) ) {
            return '<div style="text-align:center;padding:80px 20px;font-family:sans-serif;background:#0d0d0d;color:#888;min-height:60vh">No scan found. <a href="/scan/" style="color:#c9a84c">Run a diagnostic →</a></div>';
        }

        $api_key  = get_option( 'siloq_api_key', '' );
        $api_base = rtrim( get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' ), '/' );

        // Cache the API scan response for 24h — scan results never change after completion
        $api_cache_key = 'siloq_scan_api_' . md5( $scan_id );
        $scan_data     = get_transient( $api_cache_key );

        if ( false === $scan_data && ! empty( $api_key ) ) {
            $resp = wp_remote_get( $api_base . '/scans/' . $scan_id . '/', array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ),
            ) );
            if ( ! is_wp_error( $resp ) && 200 === wp_remote_retrieve_response_code( $resp ) ) {
                $scan_data = json_decode( wp_remote_retrieve_body( $resp ), true );
                if ( $scan_data ) {
                    set_transient( $api_cache_key, $scan_data, DAY_IN_SECONDS );
                }
            }
        }
        $scan_data = $scan_data ?: null;

        if ( empty( $scan_data ) ) {
            return '<div style="text-align:center;padding:80px 20px;font-family:sans-serif;background:#0d0d0d;color:#888;min-height:60vh">Could not load results. <a href="/scan/" style="color:#c9a84c">Try again →</a></div>';
        }

        $results       = ( isset( $scan_data['results'] ) && is_array( $scan_data['results'] ) ) ? $scan_data['results'] : $scan_data;
        $total_score   = intval( $results['total_score'] ?? $scan_data['score'] ?? 0 );

        // Detect zero-crawl (site blocked our crawler — Cloudflare, robots.txt, etc.)
        $pages_crawled_raw = intval( $results['pages_crawled'] ?? $scan_data['pages_analyzed'] ?? 1 );
        if ( $pages_crawled_raw === 0 && $total_score === 0 ) {
            return '<div style="max-width:620px;margin:60px auto;padding:32px 24px;background:#141414;border:1px solid rgba(201,168,76,.2);border-radius:8px;font-family:sans-serif;text-align:center">'
                . '<div style="font-size:11px;font-family:monospace;color:#c9a84c;text-transform:uppercase;letter-spacing:2px;margin-bottom:16px">Scan Incomplete</div>'
                . '<div style="font-size:16px;color:#f0f0f0;margin-bottom:12px">Our crawler could not access <strong style="color:#c9a84c">' . esc_html( $scan_data['url'] ?? $domain ) . '</strong></div>'
                . '<div style="font-size:14px;color:#9a9488;line-height:1.65;margin-bottom:20px">This usually happens when the site blocks automated crawlers (Cloudflare Bot Fight Mode, robots.txt, or a WAF rule). The site itself is working fine — we just need crawl access to run the diagnostic.</div>'
                . '<div style="background:#1a1a1a;border-left:3px solid #c9a84c;padding:12px 16px;text-align:left;border-radius:0 4px 4px 0;margin-bottom:24px;font-size:13px;color:#888">'
                . '<strong style="color:#f0f0f0;display:block;margin-bottom:4px">Common fix:</strong>'
                . 'If the site uses Cloudflare, pause Bot Fight Mode temporarily and re-scan. Or whitelist the User-Agent <code style="color:#c9a84c">Siloq/1.0</code> in the firewall rules.'
                . '</div>'
                . '<a href="/scan/" style="display:inline-block;padding:12px 28px;background:#c9a84c;color:#000;text-decoration:none;font-size:14px;font-weight:700;border-radius:4px">Try Another Site →</a>'
                . '</div>';
        }
        $grade         = sanitize_text_field( $results['grade'] ?? 'Needs Attention' );
        $pages         = intval( $results['pages_crawled'] ?? $scan_data['pages_analyzed'] ?? 0 );
        $benchmark     = sanitize_text_field( $results['benchmark'] ?? '' );
        $dimensions    = is_array( $results['dimensions'] ?? null ) ? $results['dimensions'] : array();
        $auto_count    = intval( $results['auto_fixable_count'] ?? 0 );
        $content_count = intval( $results['requires_content_count'] ?? 0 );
        $scan_url      = esc_url( $scan_data['url'] ?? '' );
        $duration      = intval( $scan_data['scan_duration_seconds'] ?? 0 );
        $top_issues    = is_array( $results['top_issues'] ?? null ) ? $results['top_issues'] : array();

        if ( empty( $domain ) && ! empty( $scan_url ) ) {
            $parsed = wp_parse_url( $scan_url );
            $domain = $parsed['host'] ?? $scan_url;
            $domain = preg_replace( '/^www\./', '', $domain );
        }

        // Gather all issues for Claude
        $issues_by_dim = array();
        foreach ( $dimensions as $key => $dim ) {
            if ( ! empty( $dim['issues'] ) && is_array( $dim['issues'] ) ) {
                $issues_by_dim[ $key ] = $dim['issues'];
            }
        }

        // ── Expensive secondary analysis: cache per scan_id for 24h ──────────
        // These calls (sitemap fetch, cannibalization detect, RSEO, entity signals,
        // service extraction) collectively add 20-60s on every uncached page load.
        // Keying on scan_id means the results page serves from cache after the first load.
        $cache_key     = 'siloq_scan_secondary_' . md5( $scan_id );
        $cached        = get_transient( $cache_key );

        if ( false !== $cached && is_array( $cached ) ) {
            // Serve from cache
            $detected_services = $cached['detected_services'] ?? array();
            $site_urls         = $cached['site_urls']         ?? array();
            $cannibal          = $cached['cannibal']          ?? array();
            $rseo_data         = $cached['rseo_data']         ?? null;
            $entity_signals    = $cached['entity_signals']    ?? array();
            $narrative         = $cached['narrative']         ?? '';
        } else {
            // First load for this scan_id — run all secondary analysis
            $target_url        = $scan_url ?: 'https://' . $domain;

            // Auto-detect services from website
            $detected_services = self::extract_services_from_site( $target_url );

            // Fetch URLs for cannibalization analysis
            $site_urls    = self::fetch_urls( $target_url );
            $cannibal     = self::detect_cannibalization( $site_urls, $target_url );

            // Try RSEO for entity analysis
            $rseo_data      = ! empty( $biz ) ? self::call_rseo( $biz, $domain, $scan_url ) : null;
            // GBP auto-lookup removed (unreliable) — manual GBP check shown in UI instead
            // NAP mismatch still detected via schema extraction for suppressor injection
            $entity_signals = ! empty( $biz ) ? self::check_entity_signals( $biz, $domain, $scan_url ) : array();

            $narrative = self::generate_narrative( $results, $dimensions, $domain, $issues_by_dim, $cannibal, $rseo_data, $site_urls );

            // Cache for 24 hours — scan results don't change
            set_transient( $cache_key, array(
                'detected_services' => $detected_services,
                'site_urls'         => $site_urls,
                'cannibal'          => $cannibal,
                'rseo_data'         => $rseo_data,
                'entity_signals'    => $entity_signals,
                'narrative'         => $narrative,
            ), DAY_IN_SECONDS );
        }

        // Inject entity signal findings into narrative suppressors
        if ( ! empty( $entity_signals ) ) {
            $injected = array();
            // NAP address mismatch
            if ( ! empty( $entity_signals['address_mismatch'] ) ) {
                $injected[] = array(
                    'severity'    => 'critical',
                    'title'       => 'NAP inconsistency — website and GBP addresses do not match',
                    'desc'        => 'Your website schema lists "' . esc_html( $entity_signals['schema_address'] ) . '" but your Google Business Profile shows "' . esc_html( explode( ',', $entity_signals['gbp_address'] ?? '' )[0] ) . '". Google cannot confidently resolve your entity\'s location when these conflict — this directly suppresses local pack eligibility.',
                    'siloq_angle' => 'Siloq: entity consistency',
                );
            }
            // Stale reviews
            if ( ! empty( $entity_signals['gbp_found'] ) ) {
                $last_rt = intval( $entity_signals['last_review_time'] ?? 0 );
                if ( $last_rt > 0 && ( time() - $last_rt ) > ( 365 * 86400 ) ) {
                    $injected[] = array(
                        'severity'    => 'high',
                        'title'       => 'GBP review velocity is zero — profile appears abandoned',
                        'desc'        => 'Last Google review was ' . esc_html( $entity_signals['last_review_label'] ) . '. Reviews older than one year are heavily discounted. Google interprets low velocity as a dormant or unmanaged business.',
                        'siloq_angle' => 'Siloq: entity freshness',
                    );
                }
            }
            // Prepend to suppressors
            if ( ! empty( $injected ) ) {
                $existing = $narrative['suppressors'] ?? array();
                $narrative['suppressors'] = array_merge( $injected, $existing );
            }
        }

        // Apply score overrides here (same logic as html()) so email shows correct score
        $cannibal_detected_r = ! empty( $cannibal['detected'] );
        $has_critical_r      = $cannibal_detected_r;
        foreach ( $narrative['suppressors'] ?? array() as $_s ) {
            if ( strtolower( $_s['severity'] ?? '' ) === 'critical' ) { $has_critical_r = true; break; }
        }
        $has_schema_issue_r = false;
        foreach ( $top_issues as $_ti ) {
            if ( stripos( $_ti, 'schema' ) !== false || stripos( $_ti, 'LocalBusiness' ) !== false ) {
                $has_schema_issue_r = true; break;
            }
        }
        if ( $cannibal_detected_r || $has_schema_issue_r ) {
            // Recalculate from capped pillars
            $capped_dims = $dimensions;
            if ( $cannibal_detected_r && isset( $capped_dims['cannibalization'] ) ) {
                $capped_dims['cannibalization']['score'] = min(
                    intval( $capped_dims['cannibalization']['score'] ?? 0 ),
                    (int) round( intval( $capped_dims['cannibalization']['max'] ?? 30 ) * 0.40 )
                );
            }
            $psum = 0; $pmax = 0;
            foreach ( $capped_dims as $_d ) { $psum += intval( $_d['score'] ?? 0 ); $pmax += intval( $_d['max'] ?? 0 ); }
            if ( $pmax > 0 ) $total_score = (int) round( ( $psum / $pmax ) * 100 );
            if ( $has_critical_r )      $total_score = min( $total_score, 65 );
            if ( $cannibal_detected_r ) $total_score = min( $total_score, 58 );
        }
        // Override grade to match capped score
        if ( $total_score < 45 )       $grade = 'Critical Issues Found';
        elseif ( $total_score < 60 )   $grade = 'Significant Issues Found';
        elseif ( $total_score < 70 )   $grade = 'Needs Attention';

        // Send email if provided
        if ( ! empty( $email ) ) {
            $results_url = home_url( '/scan/results/?scan_id=' . rawurlencode( $scan_id ) . '&domain=' . rawurlencode( $domain ) );
            self::send_results_email( $email, $name, $domain, $total_score, $grade, $results_url, $narrative );
        }

        return self::html( $scan_data, $results, $dimensions, $total_score, $grade, $pages, $duration, $domain, $biz, $benchmark, $auto_count, $content_count, $top_issues, $narrative, $scan_id, $cannibal, $entity_signals, $detected_services );
    }


    /* ── Fetch URL list from sitemap ─────────────────────────── */

    private static function fetch_urls( $site_url ) {
        $urls = array();
        $host = rtrim( $site_url, '/' );

        // Try sitemap_index first, then sitemap.xml
        $sitemaps = array(
            $host . '/sitemap_index.xml',
            $host . '/sitemap.xml',
            $host . '/wp-sitemap.xml',
        );

        foreach ( $sitemaps as $sm_url ) {
            $resp = wp_remote_get( $sm_url, array( 'timeout' => 8 ) );
            if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) continue;
            $xml = wp_remote_retrieve_body( $resp );
            // Get <loc> URLs
            preg_match_all( '/<loc>(https?:\/\/[^<]+)<\/loc>/i', $xml, $matches );
            if ( empty( $matches[1] ) ) continue;
            foreach ( $matches[1] as $loc ) {
                $loc = trim( $loc );
                // If this is a sitemap file, recurse once
                if ( preg_match( '/sitemap.*\.xml$/i', $loc ) ) {
                    $sub = wp_remote_get( $loc, array( 'timeout' => 6 ) );
                    if ( ! is_wp_error( $sub ) && 200 === wp_remote_retrieve_response_code( $sub ) ) {
                        preg_match_all( '/<loc>(https?:\/\/[^<]+)<\/loc>/i', wp_remote_retrieve_body( $sub ), $sm );
                        $urls = array_merge( $urls, $sm[1] ?? array() );
                    }
                } else {
                    $urls[] = $loc;
                }
            }
            if ( count( $urls ) > 10 ) break; // got enough
        }
        return array_unique( array_slice( $urls, 0, 200 ) );
    }

    /* ── Detect cannibalization from URL list ─────────────────── */

    private static function detect_cannibalization( $urls, $site_url ) {
        $structural = array( 'blog', 'category', 'tag', 'author', 'page', 'feed', 'wp-content', 'comments', 'attachment', 'embed', 'trackback' );
        $host = rtrim( $site_url, '/' );

        // Extract paths
        $paths = array();
        foreach ( $urls as $url ) {
            $path = str_replace( $host, '', rtrim( $url, '/' ) );
            $parts = array_filter( explode( '/', $path ) );
            if ( count( $parts ) < 2 ) continue;
            $parts = array_values( $parts );
            // Skip if first segment is structural
            if ( in_array( $parts[0], $structural, true ) ) continue;
            $paths[] = $parts;
        }

        // Find duplicate slug suffixes across different parent paths
        $suffix_map = array(); // suffix => list of parent segments
        foreach ( $paths as $parts ) {
            $parent = $parts[0];
            $suffix = implode( '/', array_slice( $parts, 1 ) );
            if ( strlen( $suffix ) < 3 ) continue;
            if ( ! isset( $suffix_map[ $suffix ] ) ) $suffix_map[ $suffix ] = array();
            if ( ! in_array( $parent, $suffix_map[ $suffix ], true ) ) {
                $suffix_map[ $suffix ][] = $parent;
            }
        }

        // Count pairs
        $pairs = array();
        foreach ( $suffix_map as $suffix => $parents ) {
            if ( count( $parents ) >= 2 ) {
                $pairs[] = array( 'suffix' => $suffix, 'parents' => $parents );
            }
        }

        if ( count( $pairs ) >= 3 ) {
            $count   = count( $pairs );
            $example = '/' . $pairs[0]['parents'][0] . '/' . $pairs[0]['suffix'] . '/ vs /' . $pairs[0]['parents'][1] . '/' . $pairs[0]['suffix'] . '/';
            return array(
                'detected'   => true,
                'pair_count' => $count,
                'example'    => $example,
                'parents'    => array_unique( array_merge( ...array_column( array_slice( $pairs, 0, 5 ), 'parents' ) ) ),
            );
        }
        return array( 'detected' => false );
    }

    /* ── RSEO entity analysis ─────────────────────────────────── */

    private static function call_rseo( $biz, $domain, $scan_url ) {
        $rseo_key = get_option( 'siloq_rseo_api_key', '' );
        // Try env var as fallback (for sites where it's set server-side)
        if ( empty( $rseo_key ) ) $rseo_key = getenv( 'RELATIONAL_SEO_API_KEY' ) ?: '';
        if ( empty( $rseo_key ) || empty( $biz ) ) return null;

        // Derive location from domain/URL if not available
        $location = '';
        $parsed   = wp_parse_url( $scan_url );
        $host     = $parsed['host'] ?? $domain;

        $payload = array(
            'entityName' => $biz,
            'location'   => $location,
        );

        $resp = wp_remote_post( 'https://login.relationalseo.com/api/v1/diagnostic', array(
            'timeout' => 45,
            'headers' => array(
                'Authorization' => 'Bearer ' . $rseo_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
            'body' => wp_json_encode( $payload ),
        ) );

        if ( is_wp_error( $resp ) ) return null;
        $code = wp_remote_retrieve_response_code( $resp );
        if ( $code < 200 || $code >= 300 ) return null;

        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        return is_array( $data ) ? $data : null;
    }

    /* ── Claude narrative ─────────────────────────────────────── */

    private static function generate_narrative( $results, $dimensions, $domain, $issues_by_dim, $cannibal = array(), $rseo_data = null, $site_urls = array() ) {
        $anthropic_key = get_option( 'siloq_anthropic_api_key', '' );
        $total_score   = intval( $results['total_score'] ?? 0 );
        $auto_count    = intval( $results['auto_fixable_count'] ?? 0 );
        $content_count = intval( $results['requires_content_count'] ?? 0 );
        $benchmark     = sanitize_text_field( $results['benchmark'] ?? '' );
        $pages         = intval( $results['pages_crawled'] ?? 0 );

        $issues_text = '';
        foreach ( $issues_by_dim as $cat => $issues ) {
            $issues_text .= '- ' . ucwords( str_replace( '_', ' ', $cat ) ) . ': ' . implode( '; ', array_slice( $issues, 0, 3 ) ) . "\n";
        }
        if ( empty( $issues_text ) ) {
            $issues_text = "- No specific issues extracted from scan.\n";
        }

        if ( ! empty( $anthropic_key ) ) {
            // Build cannibalization context
            $cannibal_note = '';
            if ( !empty($cannibal['detected']) ) {
                $cannibal_note = "\n- CANNIBALIZATION DETECTED: " . $cannibal['pair_count'] . " page pairs share identical slug suffixes under different parent paths. Example: " . $cannibal['example'] . ". Parent sections involved: " . implode(', ', $cannibal['parents'] ?? array()) . ". THIS IS CRITICAL — these pages compete for the same keywords. Site Architecture score must be capped at 4/10.";
            }

            // Build URL sample (first 40 non-structural URLs)
            $structural_skip = array('blog','category','tag','author','page','feed','wp-content','comments','attachment');
            $url_sample = array();
            foreach ( $site_urls as $u ) {
                $p = trim(str_replace('https://' . $domain, '', rtrim($u, '/')), '/');
                $parts = explode('/', $p);
                if ( !empty($parts[0]) && !in_array($parts[0], $structural_skip, true) ) {
                    $url_sample[] = '/' . $p;
                }
                if ( count($url_sample) >= 40 ) break;
            }
            $url_list_text = !empty($url_sample) ? "\n- Sample URLs crawled:\n" . implode("\n", array_map(fn($u) => "  $u", $url_sample)) : '';

            $prompt = "You are an SEO diagnostic assistant for Siloq, an SEO governance platform. Analyze this website scan and return ONLY a raw JSON object — no markdown, no code fences, just JSON.\n\nCRITICAL RULES:\n1. NEVER flag /blog/, /category/, /tag/, /author/, /page/, /feed/ URLs as cannibalization or architecture issues — these are standard WordPress structural paths.\n2. IF cannibalization is detected (see data below), this MUST be the #1 critical suppressor with full explanation of the duplicate structure and which keywords it splits.\n3. Site Architecture score CANNOT be 7/10+ when cannibalization is present — cap it at 4/10.\n4. Be direct and specific — name actual page patterns, actual keywords, actual structure. No generic advice.\n5. The entity_analysis section should be detailed and insightful based on what the business appears to be.\n\nRequired JSON:\n{\n  \"grade_label\": \"short phrase e.g. Critical Issues Found\",\n  \"veto\": {\"title\": \"most critical single issue\", \"desc\": \"2 sentences, highly specific to this site\"},\n  \"suppressors\": [\n    {\"severity\": \"critical|high|medium\", \"title\": \"...\", \"desc\": \"2-3 sentences naming specific patterns\", \"siloq_angle\": \"e.g. silo architecture\"}\n  ],\n  \"opportunities\": [\"...\", \"...\", \"...\"],\n  \"roadmap\": [\n    {\"priority\": 1, \"timeframe\": \"Days 0-30\", \"title\": \"...\", \"desc\": \"2-3 sentences on exactly what to change\", \"siloq_chip\": \"Siloq: schema governance\"}\n  ],\n  \"pitch\": \"One sentence Siloq pitch specific to the biggest gap.\",\n  \"feasibility_pct\": 20,\n  \"feasibility_note\": \"2 sentences on recovery path.\",\n  \"entity_analysis\": {\n    \"current_perception\": \"How Google currently classifies this business\",\n    \"target_classification\": \"What it should be classified as\",\n    \"authority_gaps\": [\n      {\"name\": \"gap name\", \"severity\": \"critical|high|medium\", \"desc\": \"specific gap\"}\n    ],\n    \"key_finding\": \"Most important entity finding in 1 sentence\"\n  }\n}\n\nScan data for " . esc_html( $domain ) . ":\n- Score: " . $total_score . "/100\n- Pages crawled: " . $pages . "\n- Benchmark: " . $benchmark . "\n- Issues found:\n" . $issues_text . $cannibal_note . "- Auto-fixable: " . $auto_count . "\n- Content fixes needed: " . $content_count . $url_list_text . "\n\nGenerate 3 suppressors, 3 opportunities, 4-5 roadmap items. If cannibalization is detected, it must be suppressor #1. For entity_analysis, use the domain name, URL patterns, and issue data to infer what this business does and where its entity gaps are.";

            $api_resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
                'timeout' => 30,
                'headers' => array(
                    'x-api-key'         => $anthropic_key,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ),
                'body' => wp_json_encode( array(
                    'model'      => 'claude-3-5-haiku-20241022',
                    'max_tokens' => 1500,
                    'system'     => 'You are an SEO diagnostic assistant. Return only raw JSON with no markdown.',
                    'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
                ) ),
            ) );

            if ( ! is_wp_error( $api_resp ) && 200 === wp_remote_retrieve_response_code( $api_resp ) ) {
                $body = json_decode( wp_remote_retrieve_body( $api_resp ), true );
                $text = $body['content'][0]['text'] ?? '';
                $text = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
                $text = preg_replace( '/\s*```$/', '', $text );
                $narrative = json_decode( $text, true );
                if ( is_array( $narrative ) && isset( $narrative['suppressors'] ) ) {
                    return $narrative;
                }
            }
        }

        return self::fallback_narrative( $results, $dimensions, $domain, $cannibal );
    }

    private static function fallback_narrative( $results, $dimensions, $domain, $cannibal = array() ) {
        $score = intval( $results['total_score'] ?? 0 );
        $auto  = intval( $results['auto_fixable_count'] ?? 0 );
        $cont  = intval( $results['requires_content_count'] ?? 0 );
        $top   = is_array( $results['top_issues'] ?? null ) ? $results['top_issues'] : array();

        $sev_map   = array( 'ai_visibility' => 'critical', 'cannibalization' => 'critical', 'meta_titles' => 'high', 'content_structure' => 'high', 'technical' => 'medium' );
        $angle_map = array( 'ai_visibility' => 'GEO visibility', 'cannibalization' => 'silo architecture', 'meta_titles' => 'meta governance', 'content_structure' => 'content depth scoring', 'technical' => 'technical SEO' );
        $label_map = array( 'ai_visibility' => 'AI & GEO Visibility', 'cannibalization' => 'Site Architecture', 'meta_titles' => 'Meta Titles', 'content_structure' => 'Content Structure', 'technical' => 'Technical SEO' );

        $suppressors = array();
        // Prepend cannibalization if detected
        if ( !empty($cannibal['detected']) ) {
            $suppressors[] = array(
                'severity'    => 'critical',
                'title'       => 'Duplicate service architecture — keyword cannibalization detected',
                'desc'        => $cannibal['pair_count'] . ' page pairs share identical slug suffixes under different parent sections (e.g. ' . ($cannibal['example'] ?? '') . '). These pages directly compete for the same search keywords. Google cannot determine which to rank and may suppress both.',
                'siloq_angle' => 'Siloq: silo architecture governance',
            );
            // Check for commercial/residential pattern specifically
            $parents = array_map('strtolower', $cannibal['parents'] ?? array());
            $has_commercial = array_filter($parents, fn($p) => strpos($p, 'commercial') !== false);
            $has_residential = array_filter($parents, fn($p) => strpos($p, 'residential') !== false || strpos($p, 'homeowner') !== false || strpos($p, 'home') !== false);
            if ( !empty($has_commercial) && !empty($has_residential) ) {
                $suppressors[] = array(
                    'severity'    => 'critical',
                    'title'       => 'Commercial pages not differentiated for B2B buyer intent',
                    'desc'        => 'Commercial service pages share near-identical content with residential counterparts. Commercial buyers search differently — they need minimum downtime guarantees, liability and insurance documentation, compliance certificates, and ROI framing (cost of business interruption vs. remediation cost). Without B2B differentiation, commercial pages cannot rank for business queries AND actively cannibalize the residential pages.',
                    'siloq_angle' => 'Siloq: commercial content architecture',
                );
            }
        }
        $roadmap     = array();
        $priority    = 1;

        foreach ( $dimensions as $key => $dim ) {
            if ( empty( $dim['issues'] ) ) continue;
            $issues_str = implode( '; ', array_slice( $dim['issues'], 0, 2 ) );
            $suppressors[] = array(
                'severity'    => $sev_map[ $key ] ?? 'medium',
                'title'       => ( $label_map[ $key ] ?? ucwords( str_replace( '_', ' ', $key ) ) ) . ' issues detected',
                'desc'        => $issues_str,
                'siloq_angle' => 'Siloq: ' . ( $angle_map[ $key ] ?? $key ),
            );
            $tf = $priority <= 2 ? 'Days 0–30' : ( $priority <= 4 ? 'Days 30–90' : 'Days 60–180' );
            $roadmap[] = array(
                'priority'   => $priority++,
                'timeframe'  => $tf,
                'title'      => 'Fix ' . ( $label_map[ $key ] ?? ucwords( str_replace( '_', ' ', $key ) ) ),
                'desc'       => 'Address: ' . $issues_str,
                'siloq_chip' => 'Siloq: ' . ( $angle_map[ $key ] ?? $key ),
            );
        }

        $grade_label = $score >= 70 ? 'Solid Foundation' : ( $score >= 50 ? 'Needs Attention' : 'Critical Issues Found' );
        $fp          = min( max( $score - 10, 5 ), 85 );

        return array(
            'grade_label'     => $grade_label,
            'veto'            => array(
                'title' => ( $auto + $cont ) > 0 ? 'Ranking suppressors detected' : 'Diagnostic complete',
                'desc'  => ( $auto + $cont ) > 0
                    ? $domain . ' has ' . ( $auto + $cont ) . ' issues suppressing search visibility — ' . $auto . ' can be fixed automatically, ' . $cont . ' require content changes.'
                    : 'The scan completed. Review the pillar scores and findings below.',
            ),
            'suppressors'     => array_slice( $suppressors, 0, 3 ),
            'opportunities'   => array(
                'Siloq can automatically fix ' . $auto . ' of your ' . ( $auto + $cont ) . ' issues without manual edits.',
                'Governed silo architecture prevents keyword cannibalization as your site grows.',
                'Entity health monitoring ensures AI search engines correctly classify and cite your business.',
            ),
            'roadmap'         => array_slice( $roadmap, 0, 4 ),
            'pitch'           => $domain . ' has the core business signals in place but lacks the governance layer to make them work together — that is exactly what Siloq provides.',
            'feasibility_pct' => $fp,
            'feasibility_note'=> 'Recovery is achievable. The issues found are addressable with proper SEO architecture governance. Most critical fixes can be applied within 30 days.',
        );
    }

    /* ── SendGrid email ───────────────────────────────────────── */

    public static function send_results_email( $to_email, $to_name, $domain, $score, $grade, $results_url, $narrative ) {
        if ( empty( $to_email ) ) return false;

        $name_disp   = ! empty( $to_name ) ? $to_name : 'there';
        $subject     = 'Your SEO Diagnostic — ' . $domain . ' scored ' . $score . '/100';
        $pitch       = $narrative['pitch'] ?? 'Your site has opportunities to significantly improve search visibility.';
        $top_issue   = $narrative['suppressors'][0]['title'] ?? 'Multiple ranking suppressors detected';
        $score_color = $score >= 70 ? '#1a6e3a' : ( $score >= 45 ? '#8a6a1a' : '#8a1a1a' );

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#0d0d0d;font-family:Arial,sans-serif">'
            . '<div style="max-width:560px;margin:0 auto;padding:40px 20px">'
            . '<div style="text-align:center;margin-bottom:32px">'
            . '<div style="font-size:11px;font-family:monospace;color:#5a5650;text-transform:uppercase;letter-spacing:3px;margin-bottom:12px">Siloq · Site Diagnostic</div>'
            . '<div style="font-size:64px;font-weight:900;color:' . $score_color . ';line-height:1;letter-spacing:-2px">' . $score . '<span style="font-size:20px;color:#5a5650;letter-spacing:0">/100</span></div>'
            . '<div style="font-size:14px;color:#9a9488;margin-top:8px;font-family:monospace">' . esc_html( $grade ) . ' — ' . esc_html( $domain ) . '</div>'
            . '</div>'
            . '<div style="background:#141414;border:1px solid rgba(201,168,76,.25);border-radius:8px;padding:24px;margin-bottom:24px">'
            . '<div style="font-size:11px;font-family:monospace;color:#c9a84c;text-transform:uppercase;letter-spacing:2px;margin-bottom:12px">Hi ' . esc_html( $name_disp ) . '</div>'
            . '<p style="font-size:14px;color:#9a9488;line-height:1.7;margin:0 0 16px">' . esc_html( $pitch ) . '</p>'
            . '<div style="background:#1a1a1a;border-left:3px solid #e05252;border-radius:0 4px 4px 0;padding:10px 14px">'
            . '<div style="font-size:10px;font-family:monospace;color:#e05252;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Top issue</div>'
            . '<div style="font-size:13px;color:#9a9488">' . esc_html( $top_issue ) . '</div>'
            . '</div></div>'
            . '<div style="text-align:center;margin-bottom:20px">'
            . '<a href="' . esc_url( $results_url ) . '" style="display:inline-block;padding:16px 36px;background:#c9a84c;color:#0d0d0d;text-decoration:none;font-size:15px;font-weight:700;font-family:Arial;border-radius:6px;letter-spacing:.5px">VIEW FULL REPORT →</a>'
            . '</div>'
            . '<div style="text-align:center;margin-bottom:28px">'
            . '<a href="https://calendly.com/kyle-getprecisionmarketing/website-audit-review" style="display:inline-block;padding:12px 28px;color:#c9a84c;text-decoration:none;font-size:13px;font-family:Arial;border:1px solid rgba(201,168,76,.4);border-radius:6px">Book a Free Strategy Call</a>'
            . '</div>'
            . '<div style="border-top:1px solid rgba(255,255,255,.07);padding-top:16px;text-align:center;font-size:10px;font-family:monospace;color:#5a5650">'
            . 'Siloq · SEO Architecture Governance · <a href="https://siloq.ai" style="color:#5a5650;text-decoration:none">siloq.ai</a>'
            . '</div></div></body></html>';

        $text = "Hi {$name_disp},\n\nYour SEO diagnostic for {$domain} is ready.\n\nScore: {$score}/100 — {$grade}\n\n{$pitch}\n\nTop issue: {$top_issue}\n\nView your full report:\n{$results_url}\n\nBook a free strategy call:\nhttps://calendly.com/kyle-getprecisionmarketing/website-audit-review\n\n— Siloq\nsiloq.ai";

        // Read Resend key from the "Send Emails with Resend" plugin settings or our own option
        $resend_settings = get_option( 'resend_settings', array() );
        $resend_key      = $resend_settings['api_key'] ?? '';
        if ( empty( $resend_key ) ) $resend_key = get_option( 'siloq_resend_api_key', '' );
        if ( empty( $resend_key ) ) $resend_key = getenv( 'RESEND_API_KEY' ) ?: '';

        if ( ! empty( $resend_key ) ) {
            // Direct Resend API call — bypasses wp_mail entirely
            $from_email = $resend_settings['from_email'] ?? 'support@updates.siloq.ai';
            $from_name  = $resend_settings['from_name']  ?? 'Siloq';

            $api_resp = wp_remote_post( 'https://api.resend.com/emails', array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $resend_key,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode( array(
                    'from'    => $from_name . ' <' . $from_email . '>',
                    'to'      => array( $to_email ),
                    'subject' => $subject,
                    'html'    => $html,
                    'text'    => $text,
                ) ),
            ) );

            $code = wp_remote_retrieve_response_code( $api_resp );
            return ! is_wp_error( $api_resp ) && $code >= 200 && $code < 300;
        }

        // Fallback: wp_mail
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        return wp_mail( $to_email, $subject, $html, $headers );
    }


    /* ── Google entity signals via Places API ─────────────────── */

    private static function check_entity_signals( $biz, $domain, $scan_url ) {
        $places_key = get_option( 'siloq_google_places_api_key', '' );
        if ( empty( $places_key ) ) $places_key = getenv( 'GOOGLE_PLACES_API_KEY' ) ?: '';
        if ( empty( $places_key ) || empty( $biz ) ) return array();

        $place     = null;
        $place_id  = '';
        $website_from_scan = $scan_url ?: 'https://' . $domain;

        // Use full name for search — legal suffixes ARE part of GBP listings
        $biz_clean = $biz;
        // Prepare fallback without suffix for Method 2
        $biz_no_suffix = trim( preg_replace( '/[,\s]*(LLC|Inc\.?|Corp\.?|Ltd\.?|L\.L\.C\.?|Incorporated|Limited|Co\.|Company)\s*$/i', '', $biz ) );
        if ( empty( $biz_no_suffix ) ) $biz_no_suffix = $biz;

        // ── Method 1: name + domain (finds the primary/correct location) ─
        $url1 = 'https://maps.googleapis.com/maps/api/place/textsearch/json?' . http_build_query( array(
            'query' => $biz_clean . ' ' . $domain,
            'key'   => $places_key,
        ) );
        $r1 = wp_remote_get( $url1, array( 'timeout' => 8 ) );
        if ( ! is_wp_error( $r1 ) && 200 === wp_remote_retrieve_response_code( $r1 ) ) {
            $d1 = json_decode( wp_remote_retrieve_body( $r1 ), true );
            if ( ! empty( $d1['results'] ) ) {
                $place    = $d1['results'][0];
                $place_id = $place['place_id'] ?? '';
            }
        }

        // ── Method 2: name only — finds ALL locations for multi-location detection ─
        // Always run this regardless of Method 1 result, to get full picture
        $url2 = 'https://maps.googleapis.com/maps/api/place/textsearch/json?' . http_build_query( array(
            'query' => $biz_clean,
            'key'   => $places_key,
        ) );
        $r2 = wp_remote_get( $url2, array( 'timeout' => 8 ) );
        if ( ! is_wp_error( $r2 ) && 200 === wp_remote_retrieve_response_code( $r2 ) ) {
            $d2 = json_decode( wp_remote_retrieve_body( $r2 ), true );
            if ( ! empty( $d2['results'] ) ) {
                // Always store all results for disambiguation
                $all_results = $d2['results'];
                // If Method 1 found nothing, pick best match from name-only results
                if ( empty( $place ) ) {
                    foreach ( $d2['results'] as $candidate ) {
                        similar_text( strtolower( $biz ), strtolower( $candidate['name'] ?? '' ), $pct );
                        if ( $pct >= 50 ) {
                            $place    = $candidate;
                            $place_id = $candidate['place_id'] ?? '';
                            break;
                        }
                    }
                    if ( empty( $place ) ) {
                        $top = $d2['results'][0];
                        similar_text( strtolower( $biz ), strtolower( $top['name'] ?? '' ), $pct2 );
                        if ( $pct2 >= 35 ) {
                            $place    = $top;
                            $place_id = $top['place_id'] ?? '';
                        }
                    }
                }
            }
        }

        // ── Method 3: Find by Place Details if we have a place_id ─────
        $detail        = array();
        $gbp_website   = '';
        $gbp_phone     = '';
        $gbp_address   = $place['formatted_address'] ?? '';
        $gbp_name      = $place['name'] ?? '';
        $gbp_rating    = $place['rating'] ?? null;
        $gbp_reviews   = $place['user_ratings_total'] ?? null;
        $confirmed_ago = '';
        $has_hours     = false;
        $has_photos    = ! empty( $place['photos'] );
        $business_status = $place['business_status'] ?? '';

        if ( ! empty( $place_id ) ) {
            $url3 = 'https://maps.googleapis.com/maps/api/place/details/json?' . http_build_query( array(
                'place_id' => $place_id,
                'fields'   => 'name,rating,user_ratings_total,formatted_address,website,business_status,opening_hours,photos,formatted_phone_number,reviews',
                'key'      => $places_key,
            ) );
            $r3 = wp_remote_get( $url3, array( 'timeout' => 8 ) );
            if ( ! is_wp_error( $r3 ) && 200 === wp_remote_retrieve_response_code( $r3 ) ) {
                $d3     = json_decode( wp_remote_retrieve_body( $r3 ), true );
                $detail = $d3['result'] ?? array();

                $gbp_website     = $detail['website'] ?? '';
                $gbp_phone       = $detail['formatted_phone_number'] ?? '';
                $gbp_address     = $detail['formatted_address'] ?? $gbp_address;
                $gbp_name        = $detail['name'] ?? $gbp_name;
                $gbp_rating      = $detail['rating'] ?? $gbp_rating;
                $gbp_reviews     = $detail['user_ratings_total'] ?? $gbp_reviews;
                $has_hours       = ! empty( $detail['opening_hours'] );
                $has_photos      = ! empty( $detail['photos'] );
                $business_status = $detail['business_status'] ?? $business_status;

                // Last review date
                $reviews_list = $detail['reviews'] ?? array();
                $last_review_time = 0;
                $owner_responses  = 0;
                foreach ( $reviews_list as $rev ) {
                    if ( isset( $rev['time'] ) && $rev['time'] > $last_review_time ) {
                        $last_review_time = $rev['time'];
                    }
                    if ( ! empty( $rev['owner_response'] ) ) $owner_responses++;
                }
            }
        }

        $gbp_found = ! empty( $place );

        // NAP match: does GBP website match scanned domain?
        $website_match = false;
        if ( ! empty( $gbp_website ) ) {
            $gbp_host   = preg_replace( '/^www\./i', '', wp_parse_url( $gbp_website, PHP_URL_HOST ) ?? '' );
            $scan_host  = preg_replace( '/^www\./i', '', $domain );
            $website_match = ( strtolower( $gbp_host ) === strtolower( $scan_host ) );
        }

        // Website address vs GBP address — extract from schema if possible
        $schema_address = self::extract_schema_address( $website_from_scan );
        $address_mismatch = false;
        $schema_addr_str  = '';
        if ( ! empty( $schema_address ) && ! empty( $gbp_address ) ) {
            $schema_addr_str = $schema_address['streetAddress'] ?? '';
            $gbp_street      = strtolower( preg_replace( '/[^a-z0-9]/i', ' ', explode( ',', $gbp_address )[0] ?? '' ) );
            $sc_street       = strtolower( preg_replace( '/[^a-z0-9]/i', ' ', $schema_addr_str ) );
            // Extract just the street number to compare
            preg_match( '/\d+/', $gbp_street, $gm );
            preg_match( '/\d+/', $sc_street,  $sm );
            if ( ! empty( $gm[0] ) && ! empty( $sm[0] ) && $gm[0] !== $sm[0] ) {
                $address_mismatch = true;
            }
        }

        // Disambiguation: count other GBPs with the SAME name (multi-location or competitor)
        $disambig_count = 0;
        $disambig_names = array();
        $is_multi_location = false;
        foreach ( $all_results ?? array() as $r ) {
            if ( $r['place_id'] === $place_id ) continue;
            similar_text( strtolower( $biz ), strtolower( $r['name'] ?? '' ), $dp );
            if ( $dp >= 70 ) { // High threshold — same/near-identical name only
                $disambig_count++;
                $disambig_names[] = ( $r['name'] ?? '' ) . ' — ' . ( $r['formatted_address'] ?? '' );
                // Same name = likely multi-location, not unrelated competitor
                if ( $dp >= 90 ) $is_multi_location = true;
            }
        }

        // Last review human-readable
        $last_review_label = '';
        if ( ! empty( $last_review_time ) ) {
            $days_ago = (int) round( ( time() - $last_review_time ) / 86400 );
            if ( $days_ago < 30 )        $last_review_label = $days_ago . ' days ago';
            elseif ( $days_ago < 365 )   $last_review_label = round( $days_ago / 30 ) . ' months ago';
            else                          $last_review_label = round( $days_ago / 365, 1 ) . ' years ago';
        }

        return array(
            'gbp_found'          => $gbp_found,
            'gbp_name'           => $gbp_name,
            'gbp_address'        => $gbp_address,
            'gbp_rating'         => $gbp_rating,
            'gbp_reviews'        => $gbp_reviews,
            'gbp_website'        => $gbp_website,
            'gbp_phone'          => $gbp_phone,
            'gbp_website_match'  => $website_match,
            'gbp_has_hours'      => $has_hours,
            'gbp_has_photos'     => $has_photos,
            'business_status'    => $business_status,
            'last_review_label'  => $last_review_label,
            'last_review_time'   => $last_review_time ?? 0,
            'owner_responses'    => $owner_responses ?? 0,
            'reviews_list_count' => count( $reviews_list ?? array() ),
            'address_mismatch'   => $address_mismatch,
            'schema_address'     => $schema_addr_str,
            'disambiguation_count' => $disambig_count,
            'disambiguation_names' => $disambig_names,
            'is_multi_location'    => $is_multi_location ?? false,
        );
    }

    /* ── Extract LocalBusiness address from website schema ───────── */

    private static function extract_schema_address( $url ) {
        $resp = wp_remote_get( $url, array( 'timeout' => 8, 'user-agent' => 'Siloq/1.0' ) );
        if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) return array();
        $html = wp_remote_retrieve_body( $resp );
        // Extract JSON-LD
        preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches );
        foreach ( $matches[1] ?? array() as $json_str ) {
            $data = json_decode( $json_str, true );
            if ( empty( $data ) ) continue;
            $items = isset( $data[0] ) ? $data : array( $data );
            foreach ( $items as $item ) {
                $type = $item['@type'] ?? '';
                if ( in_array( $type, array( 'LocalBusiness', 'Organization', 'ProfessionalService', 'MedicalBusiness' ), true )
                    || ( is_array( $type ) && array_intersect( $type, array( 'LocalBusiness', 'Organization' ) ) ) ) {
                    if ( ! empty( $item['address'] ) ) {
                        return is_array( $item['address'] ) ? $item['address'] : array( 'streetAddress' => $item['address'] );
                    }
                }
            }
        }
        return array();
    }


    /* ── Auto-detect services from website ───────────────────── */

    private static function extract_services_from_site( $url ) {
        $stop_words = array(
            'home','about','contact','blog','faq','resources','login','privacy',
            'terms','sitemap','search','cart','checkout','account','register',
            'news','gallery','portfolio','testimonials','reviews','press',
            'team','staff','careers','jobs','support','help','legal','policy',
            'page','pages','category','tag','author','feed','wp','admin',
            'menu','navigation','content','skip','main','primary','secondary',
            'footer','header','sidebar','widget','section','wrapper','container',
            'accessibility','mobile','desktop','close','open','toggle','more',
            'read','view','click','here','learn','see','get','our','we','us',
            'the','and','for','with','your','this','that','all','from','not',
        );
        $location_suffixes = array(
            'county','city','state','area','region','district','metro','local',
        );

        $services = array();

        $resp = wp_remote_get( $url, array(
            'timeout'    => 10,
            'user-agent' => 'Siloq/1.0',
            'headers'    => array( 'Accept' => 'text/html' ),
        ) );
        if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
            return $services;
        }
        $html = wp_remote_retrieve_body( $resp );

        // 1. Schema serviceType values
        preg_match_all( '/"serviceType"\s*:\s*"([^"]{3,60})"/i', $html, $schema_m );
        foreach ( $schema_m[1] ?? array() as $s ) {
            $services[] = trim( $s );
        }

        // 2. Nav menu links — extract readable text from <a> inside nav/header
        preg_match_all( '/<(?:nav|header|ul)[^>]*>.*?<\/(?:nav|header|ul)>/si', $html, $nav_m );
        foreach ( $nav_m[0] ?? array() as $nav_block ) {
            preg_match_all( '/<a[^>]*>\s*([^<]{3,50})\s*<\/a>/i', $nav_block, $link_m );
            foreach ( $link_m[1] ?? array() as $link_text ) {
                $clean = trim( strip_tags( $link_text ) );
                if ( strlen( $clean ) >= 4 && strlen( $clean ) <= 55 ) {
                    $services[] = $clean;
                }
            }
        }

        // 3. H1 / H2 headings on page
        preg_match_all( '/<h[12][^>]*>\s*(.*?)\s*<\/h[12]>/si', $html, $h_m );
        foreach ( $h_m[1] ?? array() as $h ) {
            $clean = trim( strip_tags( $h ) );
            if ( strlen( $clean ) >= 5 && strlen( $clean ) <= 60 ) {
                $services[] = $clean;
            }
        }

        // Clean + filter
        $filtered = array();
        foreach ( $services as $svc ) {
            $svc = trim( preg_replace( '/\s+/', ' ', $svc ) );
            if ( strlen( $svc ) < 4 || strlen( $svc ) > 60 ) continue;

            $lower = strtolower( $svc );
            $skip  = false;

            // Skip emails and URLs
            if ( preg_match( '/@|https?:\/\/|www\./', $svc ) ) $skip = true;
            // Skip if it looks like an accessibility skip link
            if ( preg_match( '/^skip\s+to/i', $svc ) ) $skip = true;
            // Skip if all words are stop words or it's navigation/UI chrome
            if ( preg_match( '/^(skip|toggle|close|open|menu|nav|mobile|accessibility)/i', $svc ) ) $skip = true;

            // Skip stop words (exact or first word)
            $first_word = strtolower( preg_split( '/\s+/', $lower )[0] ?? '' );
            if ( in_array( $first_word, $stop_words, true ) ) $skip = true;
            if ( in_array( $lower, $stop_words, true ) )       $skip = true;

            // Skip pure location terms
            foreach ( $location_suffixes as $loc ) {
                if ( str_ends_with( $lower, $loc ) ) { $skip = true; break; }
            }

            // Skip numbers-only or very short
            if ( is_numeric( $svc ) ) $skip = true;
            if ( strlen( $svc ) < 5 ) $skip = true;

            // Must contain at least one word that looks like a service/action
            if ( ! preg_match( '/[a-zA-Z]{4,}/', $svc ) ) $skip = true;

            if ( ! $skip ) {
                $filtered[] = $svc;
            }
        }

        // Deduplicate case-insensitively, keep first occurrence
        $seen   = array();
        $unique = array();
        foreach ( $filtered as $svc ) {
            $key = strtolower( $svc );
            if ( ! isset( $seen[ $key ] ) ) {
                $seen[ $key ] = true;
                $unique[]     = $svc;
            }
        }

        // Prioritise schema results first, then limit to 10
        return array_slice( $unique, 0, 10 );
    }

    /* ── AJAX: run entity analysis with confirmed services ───── */

    public static function ajax_run_entity_analysis() {
        check_ajax_referer( 'siloq_scanner_nonce', '_wpnonce' );

        $biz      = sanitize_text_field( wp_unslash( $_POST['biz']      ?? '' ) );
        $location = sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) );
        $domain   = sanitize_text_field( wp_unslash( $_POST['domain']   ?? '' ) );
        $scan_url = esc_url_raw( wp_unslash( $_POST['scan_url']         ?? '' ) );
        $raw_svcs = wp_unslash( $_POST['services'] ?? '' );
        $services = array();
        if ( is_array( $raw_svcs ) ) {
            $services = array_map( 'sanitize_text_field', $raw_svcs );
        } elseif ( is_string( $raw_svcs ) ) {
            $decoded = json_decode( $raw_svcs, true );
            if ( is_array( $decoded ) ) {
                $services = array_map( 'sanitize_text_field', $decoded );
            }
        }

        if ( empty( $biz ) ) {
            wp_send_json_error( array( 'message' => 'Business name required' ) );
        }

        $rseo_key = get_option( 'siloq_rseo_api_key', '' );
        if ( empty( $rseo_key ) ) $rseo_key = getenv( 'RELATIONAL_SEO_API_KEY' ) ?: '';

        $anthropic_key = get_option( 'siloq_anthropic_api_key', '' );

        if ( empty( $rseo_key ) && empty( $anthropic_key ) ) {
            wp_send_json_error( array( 'message' => 'No entity analysis API configured' ) );
        }

        // Build payload
        $payload = array( 'entityName' => $biz );
        if ( ! empty( $location ) )   $payload['location'] = $location;
        if ( ! empty( $services ) )   $payload['services'] = $services;

        // Try RSEO first
        if ( ! empty( $rseo_key ) ) {
            $resp = wp_remote_post( 'https://login.relationalseo.com/api/v1/diagnostic', array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $rseo_key,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                'body' => wp_json_encode( $payload ),
            ) );

            if ( ! is_wp_error( $resp ) ) {
                $code = wp_remote_retrieve_response_code( $resp );
                $body = json_decode( wp_remote_retrieve_body( $resp ), true );
                if ( $code >= 200 && $code < 300 && is_array( $body ) ) {
                    wp_send_json_success( array(
                        'source'  => 'rseo',
                        'data'    => $body,
                        'services' => $services,
                    ) );
                }
            }
        }

        // Fallback: Claude generates entity analysis with services context
        if ( ! empty( $anthropic_key ) ) {
            $svc_text = ! empty( $services ) ? implode( ', ', $services ) : 'not specified';
            $prompt   = 'Analyze the brand entity health for this business and return ONLY raw JSON (no markdown).' . "\n\n"
                . 'Business: ' . $biz . "\n"
                . 'Location: ' . ( $location ?: 'not provided' ) . "\n"
                . 'Services: ' . $svc_text . "\n"
                . 'Domain: '   . $domain   . "\n\n"
                . 'Return JSON with keys: current_perception, target_classification, service_drift (array of {service,status,desc} where status is recognized|overshadowed|misclassified|weak), authority_gaps (array of {name,severity,desc}), key_finding.';

            $ai_resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
                'timeout' => 30,
                'headers' => array(
                    'x-api-key'         => $anthropic_key,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ),
                'body' => wp_json_encode( array(
                    'model'      => 'claude-3-5-haiku-20241022',
                    'max_tokens' => 1200,
                    'system'     => 'You are a brand entity analyst. Return only raw JSON.',
                    'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
                ) ),
            ) );

            if ( ! is_wp_error( $ai_resp ) && 200 === wp_remote_retrieve_response_code( $ai_resp ) ) {
                $ai_body = json_decode( wp_remote_retrieve_body( $ai_resp ), true );
                $text    = $ai_body['content'][0]['text'] ?? '';
                $text    = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
                $text    = preg_replace( '/\s*```$/', '', $text );
                $entity  = json_decode( $text, true );
                if ( is_array( $entity ) ) {
                    wp_send_json_success( array(
                        'source'   => 'claude',
                        'data'     => $entity,
                        'services' => $services,
                    ) );
                }
            }
        }

        wp_send_json_error( array( 'message' => 'Entity analysis unavailable' ) );
    }

    /* ── HTML template ────────────────────────────────────────── */

    private static function html( $scan_data, $results, $dimensions, $total_score, $grade, $pages, $duration, $domain, $biz, $benchmark, $auto_count, $content_count, $top_issues, $narrative, $scan_id, $cannibal = array(), $entity_signals = array(), $detected_services = array() ) {

        // ── Score override logic ────────────────────────────────────
        // Scores from the API are optimistic — they don't account for
        // architecture-level issues we detect independently. Override before render.
        $cannibal_detected = ! empty( $cannibal['detected'] );
        $has_critical      = $cannibal_detected; // more flags can be added here

        // Count critical suppressors from narrative
        $narrative_suppressors = $narrative['suppressors'] ?? array();
        foreach ( $narrative_suppressors as $_s ) {
            if ( strtolower( $_s['severity'] ?? '' ) === 'critical' ) {
                $has_critical = true;
                break;
            }
        }

        // Cap individual pillar scores
        if ( $cannibal_detected ) {
            // Site Architecture (cannibalization) — cap at 40% of max
            if ( isset( $dimensions['cannibalization'] ) ) {
                $max_arch = intval( $dimensions['cannibalization']['max'] ?? 30 );
                $dimensions['cannibalization']['score'] = min(
                    intval( $dimensions['cannibalization']['score'] ?? 0 ),
                    (int) round( $max_arch * 0.40 )
                );
            }
            // Content Depth — cap at 50% when duplicate content structure
            if ( isset( $dimensions['content_structure'] ) ) {
                $max_cs = intval( $dimensions['content_structure']['max'] ?? 15 );
                $dimensions['content_structure']['score'] = min(
                    intval( $dimensions['content_structure']['score'] ?? 0 ),
                    (int) round( $max_cs * 0.50 )
                );
            }
        }
        // GEO Ready — cap at 2/25 if schema is missing from homepage
        $has_schema_issue = false;
        foreach ( $top_issues as $_ti ) {
            if ( stripos( $_ti, 'schema' ) !== false || stripos( $_ti, 'LocalBusiness' ) !== false ) {
                $has_schema_issue = true;
                break;
            }
        }
        if ( $has_schema_issue && isset( $dimensions['ai_visibility'] ) ) {
            $max_geo = intval( $dimensions['ai_visibility']['max'] ?? 25 );
            $dimensions['ai_visibility']['score'] = min(
                intval( $dimensions['ai_visibility']['score'] ?? 0 ),
                (int) round( $max_geo * 0.30 )
            );
        }

        // Recalculate overall score from capped pillars
        if ( $cannibal_detected || $has_schema_issue ) {
            $pillar_sum = 0;
            $pillar_max = 0;
            foreach ( $dimensions as $_dim ) {
                $pillar_sum += intval( $_dim['score'] ?? 0 );
                $pillar_max += intval( $_dim['max'] ?? 0 );
            }
            if ( $pillar_max > 0 ) {
                $total_score = (int) round( ( $pillar_sum / $pillar_max ) * 100 );
            }
            // Hard cap: critical issues → max 65
            if ( $has_critical ) {
                $total_score = min( $total_score, 65 );
            }
            // Cannibalization alone → max 58
            if ( $cannibal_detected ) {
                $total_score = min( $total_score, 58 );
            }
        }

        $score_cls   = $total_score >= 70 ? 'good' : ( $total_score >= 45 ? 'mid' : 'poor' );

        // Override grade_label to match the capped score — Claude's label may not reflect overrides
        if ( $has_critical || $cannibal_detected ) {
            if ( $total_score < 45 ) {
                $narrative['grade_label'] = 'Critical Issues Found';
            } elseif ( $total_score < 60 ) {
                $narrative['grade_label'] = 'Significant Issues Found';
            } elseif ( $total_score < 70 ) {
                $narrative['grade_label'] = 'Needs Attention';
            }
        }
        $grade_label = esc_html( $narrative['grade_label'] ?? $grade );

        $pillar_map = array(
            'ai_visibility'     => array( 'label' => "GEO\nReady",     'icon' => '🤖' ),
            'cannibalization'   => array( 'label' => "Site\nArch",     'icon' => '🔗' ),
            'meta_titles'       => array( 'label' => "Meta\nTitles",   'icon' => '🏷️' ),
            'content_structure' => array( 'label' => "Content\nDepth", 'icon' => '📐' ),
            'technical'         => array( 'label' => "Technical\nSEO", 'icon' => '⚙️' ),
        );

        ob_start();
        ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@300;400;500&family=IBM+Plex+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<style>
/* ── WP / Elementor overrides ── */
.entry-title,.page-title,h1.page-title,.wp-block-post-title{display:none!important}
html,body{background:#0d0d0d!important}
.elementor-section,.elementor-column,.elementor-widget-wrap,.elementor-widget-container,
.elementor-inner,.e-con,.e-con-inner,.elementor-top-section{
  background:#0d0d0d!important;max-width:100%!important;width:100%!important;padding-left:0!important;padding-right:0!important}
.site-content,.entry-content,.site-main,main#main,#content,#primary,.content-area{
  background:#0d0d0d!important;padding:0!important;max-width:100%!important}

/* ── Variables ── */
:root{
  --bg:#0d0d0d;--bg2:#141414;--bg3:#1a1a1a;--bg4:#252525;
  --gold:#c9a84c;--goldd:rgba(201,168,76,.15);--goldg:rgba(201,168,76,.07);--goldhi:#e0be6e;
  --text:#ffffff;--t2:#c8c4bc;--t3:#7a7670;
  --red:#e05252;--redd:rgba(224,82,82,.12);
  --amb:#d4973e;--ambd:rgba(212,151,62,.14);
  --grn:#52b87a;--grnd:rgba(82,184,122,.13);
  --brd:rgba(255,255,255,.09);--brdg:rgba(201,168,76,.25);--r:8px
}
*{box-sizing:border-box;margin:0;padding:0}
.sr-page{font-family:"Barlow",sans-serif;font-size:16px;background:var(--bg);color:var(--text);max-width:900px;margin:0 auto;padding:36px 24px 80px}
.sr-page *{box-sizing:border-box}
.mono{font-family:"IBM Plex Mono",monospace}
.cond{font-family:"Barlow Condensed",sans-serif}
.gold-text{color:var(--gold)}

/* ── Animations ── */
@keyframes rise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes blk{0%,100%{opacity:1}50%{opacity:.3}}
@keyframes plsg{0%,100%{opacity:1}50%{opacity:.4}}

/* ── Status bar ── */
.status-bar{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:var(--bg2);border:1px solid var(--brdg);border-radius:var(--r);margin-bottom:24px;animation:rise .4s ease both;flex-wrap:wrap;gap:8px}
.s-left{display:flex;align-items:center;gap:10px}
.s-dot{width:8px;height:8px;border-radius:50%;background:var(--grn);animation:plsg 2s ease-in-out infinite;flex-shrink:0}
.s-txt{font-family:"IBM Plex Mono",monospace;font-size:11px;color:var(--t2)}

/* ── Badges ── */
.badge{font-family:"IBM Plex Mono",monospace;font-size:10px;padding:3px 9px;border-radius:20px;white-space:nowrap;display:inline-block}
.b-amber{background:var(--ambd);color:var(--amb)}.b-red{background:var(--redd);color:var(--red)}
.b-gold{background:var(--goldd);color:var(--gold)}.b-green{background:var(--grnd);color:var(--grn)}
.badge-row{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}

/* ── Section heads ── */
.sh{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.sh-lbl{font-family:"IBM Plex Mono",monospace;font-size:10px;color:var(--t3);text-transform:uppercase;letter-spacing:.1em;flex-shrink:0}
.sh-line{flex:1;height:1px;background:var(--brd)}

/* ── Cards ── */
.card{background:var(--bg2);border:1px solid var(--brdg);border-radius:12px;padding:24px;margin-bottom:14px;position:relative;overflow:hidden;animation:rise .4s ease both}
.card::before{content:"";position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--gold) 40%,var(--goldhi) 60%,transparent);opacity:.5}
.card-plain{background:var(--bg2);border:1px solid var(--brd);border-radius:12px;padding:24px;margin-bottom:14px;animation:rise .4s ease both}

/* ── Score ── */
.score-top{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:22px}
.score-num{font-family:"Barlow Condensed",sans-serif;font-size:68px;font-weight:800;letter-spacing:-.04em;line-height:1}
.score-num.mid{color:var(--gold)}.score-num.good{color:var(--grn)}.score-num.poor{color:var(--red)}
.score-of{font-family:"IBM Plex Mono",monospace;font-size:11px;color:var(--t3);text-transform:uppercase;letter-spacing:.08em;text-align:right;margin-top:2px}
.score-vd{font-size:12px;font-weight:500;text-align:right;margin-top:4px}
.score-vd.mid{color:var(--amb)}.score-vd.good{color:var(--grn)}.score-vd.poor{color:var(--red)}

/* ── Pillars ── */
.pillars{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}
.pillar{background:var(--bg3);border:1px solid var(--brd);border-radius:var(--r);padding:12px 8px;text-align:center}
.ps{font-family:"Barlow Condensed",sans-serif;font-size:24px;font-weight:700;line-height:1;margin-bottom:5px}
.ps.good{color:var(--grn)}.ps.mid{color:var(--gold)}.ps.poor{color:var(--red)}
.pn{font-family:"IBM Plex Mono",monospace;font-size:9px;color:var(--t3);text-transform:uppercase;letter-spacing:.05em;line-height:1.3}
.pb{height:2px;background:var(--bg4);border-radius:2px;margin-top:8px;overflow:hidden}
.pbf{height:100%;border-radius:2px;transition:width 1.2s cubic-bezier(.4,0,.2,1)}
.pbf.good{background:var(--grn)}.pbf.mid{background:var(--gold)}.pbf.poor{background:var(--red)}

/* ── Veto ── */
.veto{background:var(--redd);border:1px solid rgba(217,79,61,.25);border-left:3px solid var(--red);border-radius:var(--r);padding:12px 16px;margin-bottom:14px;display:flex;align-items:flex-start;gap:10px;animation:rise .4s .1s ease both}
.veto-dot{width:8px;height:8px;border-radius:50%;background:var(--red);flex-shrink:0;margin-top:4px;animation:blk 1.4s ease-in-out infinite}
.veto-lbl{font-family:"IBM Plex Mono",monospace;font-size:10px;color:var(--red);text-transform:uppercase;letter-spacing:.08em;margin-bottom:3px}
.veto-txt{font-size:15px;color:var(--t2);line-height:1.6;font-weight:400}
.veto-txt strong{color:var(--text);font-weight:500}

/* ── Finding chips ── */
.findings{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:22px;animation:rise .4s .12s ease both}
.fc{background:var(--bg2);border:1px solid var(--brd);border-radius:var(--r);padding:14px;border-top:2px solid}
.fc.flag{border-top-color:var(--red)}.fc.warn{border-top-color:var(--amb)}.fc.pass{border-top-color:var(--grn)}
.fct{font-family:"IBM Plex Mono",monospace;font-size:9px;text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px}
.fc.flag .fct{color:var(--red)}.fc.warn .fct{color:var(--amb)}.fc.pass .fct{color:var(--grn)}
.fctx{font-size:14px;color:var(--t2);line-height:1.5;font-weight:400}

/* ── Signal score rows ── */
.sig-row{display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--brd)}
.sig-row:last-child{border-bottom:none}
.sig-name{font-family:"IBM Plex Mono",monospace;font-size:13px;color:var(--t2);width:210px;flex-shrink:0}
.sig-track{flex:1;height:5px;background:var(--bg4);border-radius:3px;overflow:hidden}
.sig-fill{height:100%;border-radius:3px}
.sf-good{background:var(--grn)}.sf-mid{background:var(--gold)}.sf-poor{background:var(--red)}
.sig-val{font-family:"IBM Plex Mono",monospace;font-size:13px;color:var(--t2);width:44px;text-align:right;flex-shrink:0}

/* ── Suppressors ── */
.sup-block{border-radius:var(--r);padding:12px 14px;margin-bottom:8px;border-left:3px solid}
.sb-red{background:var(--redd);border-left-color:var(--red)}
.sb-amb{background:var(--ambd);border-left-color:var(--amb)}
.sb-grn{background:var(--grnd);border-left-color:var(--grn)}
.sup-title{font-size:15px;font-weight:600;color:var(--text);margin-bottom:5px}
.sup-desc{font-size:14px;color:var(--t2);line-height:1.6;font-weight:400}
.impact-pill{font-family:"IBM Plex Mono",monospace;font-size:10px;margin-top:6px;display:inline-block;padding:2px 8px;border-radius:20px}
.ip-red{background:var(--redd);color:var(--red)}.ip-amb{background:var(--ambd);color:var(--amb)}.ip-grn{background:var(--grnd);color:var(--grn)}

/* ── Insights ── */
.insight{background:var(--bg3);border-radius:var(--r);padding:14px 16px;margin-bottom:8px;font-size:15px;color:var(--text);line-height:1.65;border-left:3px solid var(--gold);font-weight:400}

/* ── Roadmap ── */
.rm-row{display:flex;gap:14px;align-items:flex-start;padding:14px 0;border-bottom:1px solid var(--brd)}
.rm-row:first-child{padding-top:0}.rm-row:last-child{border-bottom:none;padding-bottom:0}
.rm-num{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:"IBM Plex Mono",monospace;font-size:11px;font-weight:500;flex-shrink:0;margin-top:1px}
.rn-red{background:var(--redd);color:var(--red)}.rn-amb{background:var(--ambd);color:var(--amb)}.rn-blu{background:rgba(74,120,200,.1);color:#7aacff}
.rm-time{font-family:"IBM Plex Mono",monospace;font-size:9px;color:var(--t3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px}
.rm-title{font-size:15px;font-weight:600;color:var(--text);margin-bottom:4px}
.rm-desc{font-size:14px;color:var(--t2);line-height:1.55;font-weight:400}
.siloq-chip{display:inline-block;margin-top:5px;font-family:"IBM Plex Mono",monospace;font-size:9px;padding:2px 8px;background:rgba(201,168,76,.1);color:var(--gold);border-radius:20px;text-transform:uppercase;letter-spacing:.06em}

/* ── Feasibility ── */
.feas-wrap{margin-top:16px;padding-top:16px;border-top:1px solid var(--brd)}
.feas-lbl{font-family:"IBM Plex Mono",monospace;font-size:10px;color:var(--t3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}
.feas-track{height:8px;background:var(--bg4);border-radius:4px;overflow:hidden;display:flex}
.feas-done{background:var(--grn)}.feas-gap{background:var(--amb)}
.feas-ends{display:flex;justify-content:space-between;font-family:"IBM Plex Mono",monospace;font-size:10px;color:var(--t3);margin-top:5px}
.feas-note{font-size:14px;color:var(--t2);line-height:1.6;font-weight:400;margin-top:10px}

/* ── Entity section ── */
.e-loading{padding:36px 24px;display:flex;flex-direction:column;align-items:center;gap:14px}
.spin-ring{width:36px;height:36px;border-radius:50%;border:2px solid var(--bg4);border-top-color:var(--gold);animation:spin 1s linear infinite}
.e-msg{font-family:"IBM Plex Mono",monospace;font-size:12px;color:var(--t2);text-align:center}

/* ── Pitch ── */
.pitch-box{background:var(--goldd);border:1px solid rgba(201,168,76,.2);border-radius:var(--r);padding:14px 16px;font-family:"IBM Plex Mono",monospace;font-size:11px;color:var(--gold);line-height:1.65;margin-bottom:14px}

/* ── CTA ── */
.cta-card{background:var(--bg2);border:1px solid var(--brdg);border-radius:12px;padding:28px;position:relative;overflow:hidden;margin-bottom:24px;animation:rise .4s .25s ease both}
.cta-card::before{content:"";position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--gold) 40%,var(--goldhi) 60%,transparent);opacity:.5}
.cta-eye{font-family:"IBM Plex Mono",monospace;font-size:10px;color:var(--gold);text-transform:uppercase;letter-spacing:.1em;margin-bottom:10px;display:flex;align-items:center;gap:8px}
.cta-eye::before{content:"";display:block;width:16px;height:1px;background:var(--gold)}
.cta-title{font-family:"Barlow Condensed",sans-serif;font-size:32px;font-weight:800;text-transform:uppercase;letter-spacing:-.01em;margin-bottom:8px;line-height:1;color:var(--text)}
.cta-sub{font-size:15px;color:var(--t2);line-height:1.7;margin-bottom:22px;font-weight:400;max-width:520px}
.cta-btns{display:flex;gap:10px;flex-wrap:wrap}
.btn-gold{padding:13px 26px;background:var(--gold);color:#0d0d0d;font-family:"Barlow Condensed",sans-serif;font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;border:none;border-radius:6px;cursor:pointer;transition:background .15s;text-decoration:none;display:inline-block}
.btn-gold:hover{background:var(--goldhi);color:#0d0d0d}
.btn-out{padding:13px 26px;background:transparent;color:var(--text);font-family:"Barlow Condensed",sans-serif;font-size:15px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;border:1px solid var(--brdg);border-radius:6px;cursor:pointer;transition:border-color .15s;text-decoration:none;display:inline-block}
.btn-out:hover{border-color:var(--gold);background:var(--goldg);color:var(--text)}


/* ── Download bar ── */
.sr-download-bar{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;background:var(--bg2);border:1px solid var(--brd);border-radius:var(--r);margin-bottom:16px;flex-wrap:wrap;gap:8px}
.sr-download-bar .dl-label{font-family:"IBM Plex Mono",monospace;font-size:10px;color:var(--t3);text-transform:uppercase;letter-spacing:.1em}
.btn-dl{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;background:transparent;color:var(--gold);font-family:"IBM Plex Mono",monospace;font-size:11px;font-weight:500;border:1px solid rgba(201,168,76,.4);border-radius:4px;cursor:pointer;transition:background .15s,border-color .15s;text-decoration:none;letter-spacing:.05em}
.btn-dl:hover{background:var(--goldd);border-color:var(--gold)}
.btn-dl svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}

/* ── Light-mode print stylesheet ── */
@media print{
  @page{margin:14mm;size:A4}

  /* Hide UI chrome */
  .sr-download-bar,.cta-btns .btn-out,#wpadminbar,.site-header,header,
  .site-footer,footer,nav,.elementor-section:not(:has(.sr-page)){display:none!important}

  /* White page */
  html,body,
  .elementor-section,.elementor-column,.elementor-widget-wrap,
  .elementor-widget-container,.e-con,.e-con-inner,
  .site-content,.entry-content,.site-main,main,#content,#primary{
    background:#ffffff!important;color:#111!important
  }

  .sr-page{
    background:#fff!important;color:#111!important;
    padding:0!important;max-width:100%!important;font-size:11pt
  }
  .sr-page *{color:inherit}

  /* Status bar */
  .status-bar{background:#f5f5f5!important;border-color:#ddd!important;margin-bottom:12pt}
  .s-dot{background:#2a7a4f!important}
  .s-txt,.gold-text{color:#111!important}
  .gold-text{color:#8a6a1a!important}

  /* Section heads */
  .sh-lbl{color:#666!important}
  .sh-line{background:#ddd!important}

  /* Cards */
  .card,.card-plain{
    background:#fafafa!important;border:1pt solid #ddd!important;
    break-inside:avoid;page-break-inside:avoid;margin-bottom:10pt
  }
  .card::before{display:none!important}

  /* Score */
  .score-num.good{color:#1a6e3a!important}
  .score-num.mid{color:#7a4e10!important}
  .score-num.poor{color:#8a1a1a!important}
  .score-of,.score-vd.mid{color:#555!important}
  .score-vd.good{color:#1a6e3a!important}
  .score-vd.poor{color:#8a1a1a!important}

  /* Badges */
  .badge{border:1pt solid #ccc!important}
  .b-amber{background:#fff8e6!important;color:#7a4e10!important;border-color:#e6c87a!important}
  .b-red{background:#fff0f0!important;color:#8a1a1a!important;border-color:#f5a0a0!important}
  .b-gold{background:#fffbe6!important;color:#7a4e10!important;border-color:#e6c87a!important}
  .b-green{background:#f0fff4!important;color:#1a6e3a!important;border-color:#7acca0!important}

  /* Pillars */
  .pillar{background:#f5f5f5!important;border-color:#ddd!important}
  .ps.good{color:#1a6e3a!important}.ps.mid{color:#7a4e10!important}.ps.poor{color:#8a1a1a!important}
  .pn{color:#555!important}
  .pb{background:#e0e0e0!important}
  .pbf.good{background:#2a7a4f!important}.pbf.mid{background:#b8860b!important}.pbf.poor{background:#c0392b!important}

  /* Veto */
  .veto{background:#fff5f5!important;border-color:#f5a0a0!important;border-left-color:#c0392b!important}
  .veto-lbl{color:#c0392b!important}
  .veto-txt,.veto-txt strong{color:#333!important}
  .veto-dot{display:none}

  /* Finding chips */
  .fc{background:#fafafa!important;border-color:#ddd!important}
  .fc.flag{border-top-color:#c0392b!important}
  .fc.warn{border-top-color:#b8860b!important}
  .fc.pass{border-top-color:#2a7a4f!important}
  .fct{color:#555!important}
  .fc.flag .fct{color:#c0392b!important}
  .fc.warn .fct{color:#b8860b!important}
  .fc.pass .fct{color:#2a7a4f!important}
  .fctx{color:#333!important}

  /* Signal rows */
  .sig-row{border-bottom-color:#eee!important}
  .sig-name,.sig-val{color:#333!important}
  .sig-track{background:#e0e0e0!important}
  .sf-good{background:#2a7a4f!important}.sf-mid{background:#b8860b!important}.sf-poor{background:#c0392b!important}

  /* Suppressors */
  .sb-red{background:#fff5f5!important;border-left-color:#c0392b!important}
  .sb-amb{background:#fffbf0!important;border-left-color:#b8860b!important}
  .sb-grn{background:#f0fff4!important;border-left-color:#2a7a4f!important}
  .sup-title{color:#111!important}
  .sup-desc{color:#333!important}
  .ip-red{background:#ffe0e0!important;color:#8a1a1a!important}
  .ip-amb{background:#fff3cd!important;color:#7a4e10!important}
  .ip-grn{background:#d4edda!important;color:#1a6e3a!important}

  /* Insights */
  .insight{background:#fffbe6!important;border-left-color:#b8860b!important;color:#333!important}

  /* Signal scores */
  .sig-fill{print-color-adjust:exact!important;-webkit-print-color-adjust:exact!important}

  /* Roadmap */
  .rm-row{border-bottom-color:#eee!important;break-inside:avoid}
  .rm-num{print-color-adjust:exact!important;-webkit-print-color-adjust:exact!important}
  .rn-red{background:#ffe0e0!important;color:#8a1a1a!important}
  .rn-amb{background:#fff3cd!important;color:#7a4e10!important}
  .rn-blu{background:#e8f0fe!important;color:#1a3a8a!important}
  .rm-time{color:#888!important}
  .rm-title{color:#111!important}
  .rm-desc{color:#333!important}
  .siloq-chip{background:#fffbe6!important;color:#7a4e10!important}

  /* Feasibility */
  .feas-track{background:#e0e0e0!important}
  .feas-done{background:#2a7a4f!important;print-color-adjust:exact!important;-webkit-print-color-adjust:exact!important}
  .feas-gap{background:#f0c060!important;print-color-adjust:exact!important;-webkit-print-color-adjust:exact!important}
  .feas-lbl,.feas-ends{color:#666!important}
  .feas-note{color:#333!important}

  /* Pitch box */
  .pitch-box{background:#fffbe6!important;border-color:#e6c87a!important;color:#7a4e10!important}

  /* Entity */
  .e-loading{display:none!important}

  /* CTA */
  .cta-card{background:#f8f8f8!important;border-color:#ddd!important;break-inside:avoid}
  .cta-card::before{display:none!important}
  .cta-eye{color:#7a4e10!important}
  .cta-eye::before{background:#b8860b!important}
  .cta-title{color:#111!important}
  .cta-sub{color:#333!important}
  .btn-gold{background:#c9a84c!important;color:#000!important;print-color-adjust:exact!important;-webkit-print-color-adjust:exact!important}
  .btn-out{display:none!important}
}

/* ── Mobile ── */
@media(max-width:600px){
  .pillars{grid-template-columns:repeat(3,1fr)}
  .findings{grid-template-columns:1fr}
  .sig-name{width:130px}
  .cta-btns{flex-direction:column}
  .score-num{font-size:52px}
  .score-top{gap:12px}
}
</style>

<div class="sr-page">

<!-- DOWNLOAD BAR -->
<div class="sr-download-bar">
  <span class="dl-label">Siloq Diagnostic Report — <?php echo esc_html( $domain ); ?></span>
  <button class="btn-dl" onclick="window.print()">
    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Download PDF
  </button>
</div>

<!-- STATUS BAR -->
<div class="status-bar">
  <div class="s-left">
    <div class="s-dot"></div>
    <div class="s-txt">Diagnostic complete — <strong class="gold-text"><?php echo esc_html( $domain ); ?></strong></div>
  </div>
  <div class="mono" style="font-size:10px;color:var(--t3)">
    <?php echo $pages ? esc_html( $pages ) . ' pages' : ''; ?><?php echo $duration ? ' · ' . esc_html( $duration ) . 's' : ''; ?>
  </div>
</div>

<!-- LAYER 1 -->
<div class="sh"><span class="sh-lbl">Layer 1 — Technical score</span><div class="sh-line"></div></div>
<div class="card">
  <div class="score-top">
    <div>
      <div class="mono" style="font-size:13px;color:var(--t2);margin-bottom:4px">
        Report for <strong class="gold-text"><?php echo esc_html( $domain ); ?></strong>
      </div>
      <?php if ( $benchmark ) : ?>
      <div class="mono" style="font-size:10px;color:var(--t3);margin-bottom:8px"><?php echo esc_html( $benchmark ); ?></div>
      <?php endif; ?>
      <div class="badge-row">
        <?php
        $sb   = $total_score >= 70 ? 'b-green' : ( $total_score >= 45 ? 'b-amber' : 'b-red' );
        $sbtx = $total_score >= 70 ? 'Good standing' : ( $total_score >= 45 ? 'Needs attention' : 'Critical issues' );
        ?>
        <span class="badge <?php echo esc_attr( $sb ); ?>"><?php echo esc_html( $sbtx ); ?></span>
        <?php if ( $auto_count > 0 ) : ?><span class="badge b-gold"><?php echo $auto_count; ?> auto-fixable</span><?php endif; ?>
        <?php if ( $content_count > 0 ) : ?><span class="badge b-amber"><?php echo $content_count; ?> need content</span><?php endif; ?>
      </div>
    </div>
    <div style="text-align:right;flex-shrink:0">
      <div class="score-num <?php echo esc_attr( $score_cls ); ?>"><?php echo $total_score; ?></div>
      <div class="score-of">/ 100</div>
      <div class="score-vd <?php echo esc_attr( $score_cls ); ?>"><?php echo $grade_label; ?></div>
    </div>
  </div>

  <!-- Pillars -->
  <div class="pillars">
    <?php foreach ( $pillar_map as $key => $meta ) :
      $dim   = $dimensions[ $key ] ?? array();
      $sv    = intval( $dim['score'] ?? 0 );
      $mx    = intval( $dim['max'] ?? 1 );
      $pct   = $mx > 0 ? round( ( $sv / $mx ) * 100 ) : 0;
      $pcls  = $pct >= 70 ? 'good' : ( $pct >= 45 ? 'mid' : 'poor' );
    ?>
    <div class="pillar">
      <div class="ps <?php echo esc_attr( $pcls ); ?>"><?php echo $sv; ?></div>
      <div class="pn"><?php echo nl2br( esc_html( $meta['label'] ) ); ?></div>
      <div class="pb"><div class="pbf <?php echo esc_attr( $pcls ); ?>" style="width:0" data-w="<?php echo $pct; ?>%"></div></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- VETO -->
<?php $veto = $narrative['veto'] ?? array(); if ( ! empty( $veto['title'] ) ) : ?>
<div class="veto">
  <div class="veto-dot"></div>
  <div>
    <div class="veto-lbl">Suppression signal detected</div>
    <div class="veto-txt"><strong><?php echo esc_html( $veto['title'] ); ?></strong> <?php echo esc_html( $veto['desc'] ?? '' ); ?></div>
  </div>
</div>
<?php endif; ?>

<!-- TOP ISSUE CHIPS -->
<?php if ( count( $top_issues ) >= 2 ) :
  // Build deduplicated chips — cannibalization first, then distinct issues
  $chip_issues = array();

  // Slot 1: cannibalization always wins if detected
  if ( $cannibal_detected ) {
      $chip_issues[] = array( 'cls' => 'flag', 'lbl' => 'Critical', 'txt' => 'Duplicate service architecture — ' . ( $cannibal['pair_count'] ?? 0 ) . ' page pairs competing for same keywords' );
  }

  // Fill remaining slots from top_issues, deduplicating by normalized text
  $seen_normalized = array();
  foreach ( $top_issues as $_issue ) {
      if ( count( $chip_issues ) >= 3 ) break;
      // Normalize: strip numbers so "1 page" and "2 pages" don't count as distinct
      $normalized = preg_replace( '/\b\d+\b/', 'N', strtolower( trim( $_issue ) ) );
      if ( in_array( $normalized, $seen_normalized, true ) ) continue;
      $seen_normalized[] = $normalized;
      // Assign severity based on content
      $issue_lower = strtolower( $_issue );
      if ( strpos( $issue_lower, 'schema' ) !== false || strpos( $issue_lower, 'localbusiness' ) !== false || strpos( $issue_lower, 'canonical' ) !== false ) {
          $icls = 'flag'; $ilbl = 'Critical';
      } elseif ( strpos( $issue_lower, 'title' ) !== false && strpos( $issue_lower, '65' ) !== false ) {
          // Minor title length issue — demote to medium
          $icls = 'pass'; $ilbl = 'Medium';
      } elseif ( strpos( $issue_lower, 'duplicate' ) !== false || strpos( $issue_lower, 'overlap' ) !== false ) {
          $icls = 'flag'; $ilbl = 'Critical';
      } else {
          $icls = 'warn'; $ilbl = 'High';
      }
      $chip_issues[] = array( 'cls' => $icls, 'lbl' => $ilbl, 'txt' => $_issue );
  }

  // Pad to 3 if needed
  while ( count( $chip_issues ) < 3 ) {
      $chip_issues[] = array( 'cls' => 'pass', 'lbl' => 'Info', 'txt' => '' );
  }
  $chips = array_slice( $chip_issues, 0, 3 );
?>
<div class="findings">
  <?php foreach ( $chips as $chip ) : if ( empty( $chip['txt'] ) ) continue; ?>
  <div class="fc <?php echo esc_attr( $chip['cls'] ); ?>">
    <div class="fct"><?php echo esc_html( $chip['lbl'] ); ?></div>
    <div class="fctx"><?php echo esc_html( $chip['txt'] ); ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- LAYER 2 -->
<div class="sh" style="margin-top:8px"><span class="sh-lbl">Layer 2 — Site signal health</span><div class="sh-line"></div></div>
<div class="card-plain">
  <?php foreach ( $pillar_map as $key => $meta ) :
    $dim   = $dimensions[ $key ] ?? array();
    $sv    = intval( $dim['score'] ?? 0 );
    $mx    = intval( $dim['max'] ?? 1 );
    $pct   = $mx > 0 ? round( ( $sv / $mx ) * 100 ) : 0;
    $scls  = $pct >= 65 ? 'good' : ( $pct >= 40 ? 'mid' : 'poor' );
    $lbl   = str_replace( "\n", ' ', $meta['label'] );
  ?>
  <div class="sig-row">
    <span class="sig-name"><?php echo esc_html( $lbl ); ?></span>
    <div class="sig-track"><div class="sig-fill sf-<?php echo esc_attr( $scls ); ?>" style="width:<?php echo $pct; ?>%"></div></div>
    <span class="sig-val mono"><?php echo round( $pct / 10, 1 ); ?>/10</span>
  </div>
  <?php endforeach; ?>
</div>

<!-- SUPPRESSORS -->
<?php $suppressors = $narrative['suppressors'] ?? array(); if ( ! empty( $suppressors ) ) : ?>
<div class="sh" style="margin-top:18px"><span class="sh-lbl">Primary suppressors</span><div class="sh-line"></div></div>
<?php foreach ( $suppressors as $s ) :
  $sev    = strtolower( $s['severity'] ?? 'medium' );
  $sb_cls = $sev === 'critical' ? 'sb-red' : ( $sev === 'high' ? 'sb-amb' : 'sb-grn' );
  $ip_cls = $sev === 'critical' ? 'ip-red' : ( $sev === 'high' ? 'ip-amb' : 'ip-grn' );
?>
<div class="sup-block <?php echo esc_attr( $sb_cls ); ?>">
  <div class="sup-title"><?php echo esc_html( $s['title'] ?? '' ); ?></div>
  <div class="sup-desc"><?php echo esc_html( $s['desc'] ?? '' ); ?></div>
  <span class="impact-pill <?php echo esc_attr( $ip_cls ); ?>">Impact: <?php echo ucfirst( $sev ); ?><?php echo ! empty( $s['siloq_angle'] ) ? ' · ' . esc_html( $s['siloq_angle'] ) : ''; ?></span>
</div>
<?php endforeach; endif; ?>

<!-- OPPORTUNITIES -->
<?php $opps = $narrative['opportunities'] ?? array(); if ( ! empty( $opps ) ) : ?>
<div class="sh" style="margin-top:18px"><span class="sh-lbl">Siloq opportunity — what we can govern</span><div class="sh-line"></div></div>
<?php foreach ( $opps as $opp ) : ?>
<div class="insight"><?php echo esc_html( $opp ); ?></div>
<?php endforeach; endif; ?>

<!-- LAYER 3 ENTITY -->
<div class="sh" style="margin-top:24px"><span class="sh-lbl">Layer 3 — Brand entity analysis</span><div class="sh-line"></div></div>
<?php
$ajax_url_entity = admin_url( 'admin-ajax.php' );
$entity_nonce    = wp_create_nonce( 'siloq_scanner_nonce' );
$detected_svcs_json = wp_json_encode( $detected_services );
if ( ! empty( $biz ) ) :
  $es        = $entity_signals;
  $gbp_found = ! empty( $es['gbp_found'] );
?>
<div class="card" style="padding:24px">

  <!-- GBP manual lookup CTA (replaced unreliable Places API auto-lookup) -->
  <div style="background:var(--bg3);border-radius:var(--r);padding:18px;margin-bottom:14px">
    <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:6px">Google Business Profile Check</div>
    <div style="font-size:13px;color:var(--t2);line-height:1.6;margin-bottom:14px">Paste your Google Maps URL to check your GBP signals — review velocity, owner responses, photo count, and NAP consistency.</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input type="url" id="gbp-url-input" placeholder="https://maps.google.com/..." style="flex:1;min-width:200px;padding:10px 12px;background:#000;border:1px solid var(--brd);border-radius:2px;color:var(--text);font-size:13px;outline:none">
      <a id="gbp-check-btn" href="#" onclick="siloqCheckGBP(event)" class="btn-gold" style="font-size:13px;padding:10px 18px;white-space:nowrap">Check GBP →</a>
    </div>
    <div id="gbp-check-result" style="margin-top:10px;font-size:13px;color:var(--t2);display:none"></div>
  </div>
  <script>
  function siloqCheckGBP(e) {
    e.preventDefault();
    var url = document.getElementById('gbp-url-input').value.trim();
    if (!url) { document.getElementById('gbp-url-input').focus(); return; }
    // Open in new tab so they can review it themselves
    window.open(url, '_blank');
    var r = document.getElementById('gbp-check-result');
    r.style.display = '';
    r.innerHTML = '<div style="background:rgba(201,168,76,.1);border-left:2px solid var(--gold);padding:10px 14px;border-radius:0 4px 4px 0">'
      + '<strong style="color:var(--gold);display:block;margin-bottom:4px">What to check in your GBP:</strong>'
      + '<ul style="margin:0;padding-left:16px;line-height:1.8;color:var(--t2)">'
      + '<li>Review count and date of most recent review</li>'
      + '<li>Have you responded to reviews? (shows engagement to Google)</li>'
      + '<li>Do you have a business description? (most businesses don't)</li>'
      + '<li>Are your hours, address, and phone current?</li>'
      + '<li>Do you have photos — especially of your team and work?</li>'
      + '<li>Are you using Google Posts? (most ignore this completely)</li>'
      + '</ul>'
      + '<div style="margin-top:10px;font-size:12px;color:var(--t3)">Book a strategy call to go through your GBP audit in detail →</div>'
      + '</div>';
  }
  </script>
  <?php // GBP signals variables — kept for NAP injection into suppressors even without display
    $es        = $entity_signals;
    $gbp_found = ! empty( $es['gbp_found'] );
    $last_rev  = $es['last_review_label'] ?? '';
    $last_rt   = intval( $es['last_review_time'] ?? 0 );
    $rev_count = intval( $es['gbp_reviews'] ?? 0 );
    $owner_resp= intval( $es['owner_responses'] ?? 0 );
    $addr_mism = ! empty( $es['address_mismatch'] );
    $sc_addr   = $es['schema_address'] ?? '';
  ?>

    <!-- Signal 3: Service confirm + DriftOS entity analysis -->
  <div style="padding:14px 0;border-top:1px solid var(--brd);margin-top:4px" id="entity-drift-section">

    <!-- Service confirm widget -->
    <div id="entity-confirm-widget">
      <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:6px">Confirm your services for full entity drift analysis</div>
      <div style="font-size:13px;color:var(--t2);margin-bottom:14px">We detected these from your site — check/uncheck and add any we missed. This runs a 12-factor drift analysis on each service.</div>
      <div id="entity-service-pills" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
        <?php foreach ( $detected_services as $svc ) : ?>
        <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:var(--bg3);border:1px solid var(--border, var(--brd));border-radius:20px;cursor:pointer;font-size:13px;color:var(--t2);transition:border-color .15s">
          <input type="checkbox" value="<?php echo esc_attr($svc); ?>" checked style="accent-color:var(--gold)">
          <?php echo esc_html($svc); ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px">
        <input type="text" id="entity-add-service" placeholder="Add a service we missed…" style="flex:1;padding:9px 12px;background:#000;border:1px solid var(--brd);border-radius:2px;color:var(--text);font-size:13px;outline:none">
        <button onclick="siloqAddService()" style="padding:9px 14px;background:var(--surface,var(--bg2));color:var(--gold);font-size:12px;border:1px solid var(--brd);border-radius:2px;cursor:pointer">Add</button>
      </div>
      <button onclick="siloqRunEntityAnalysis()" id="entity-run-btn" class="btn-gold" style="font-size:14px;padding:12px 24px">Run Entity Analysis →</button>
    </div>

    <!-- Loading state -->
    <div id="entity-drift-loading" style="display:none;padding:24px 0;text-align:center">
      <div style="display:inline-block;width:28px;height:28px;border-radius:50%;border:2px solid var(--bg4);border-top-color:var(--gold);animation:spin 1s linear infinite;margin-bottom:12px"></div>
      <div class="mono" id="entity-drift-msg" style="font-size:12px;color:var(--t2)">Checking how Google classifies each service…</div>
    </div>

    <!-- Results -->
    <div id="entity-drift-results" style="display:none"></div>
  </div>

  <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--brd);display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <a href="https://calendly.com/kyle-getprecisionmarketing/website-audit-review" target="_blank" rel="noopener" class="btn-gold" style="font-size:13px;padding:10px 20px">Book a Strategy Call →</a>
    <span style="font-size:12px;color:var(--t3)">Walk through your full entity profile and fix plan</span>
  </div>
</div>

<script>
(function(){
  var ajaxUrl   = <?php echo wp_json_encode( $ajax_url_entity ); ?>;
  var nonce     = <?php echo wp_json_encode( $entity_nonce ); ?>;
  var biz       = <?php echo wp_json_encode( $biz ); ?>;
  var domain    = <?php echo wp_json_encode( $domain ); ?>;
  var scanUrl   = <?php echo wp_json_encode( $scan_url ?? 'https://' . $domain ); ?>;

  window.siloqAddService = function() {
    var inp  = document.getElementById('entity-add-service');
    var val  = inp ? inp.value.trim() : '';
    if (!val) return;
    var pills = document.getElementById('entity-service-pills');
    if (!pills) return;
    var lbl = document.createElement('label');
    lbl.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:var(--bg3);border:1px solid var(--brd);border-radius:20px;cursor:pointer;font-size:13px;color:var(--t2)';
    lbl.innerHTML = '<input type="checkbox" value="' + val.replace(/"/g,'&quot;') + '" checked style="accent-color:var(--gold)"> ' + val;
    pills.appendChild(lbl);
    inp.value = '';
  };

  window.siloqRunEntityAnalysis = function() {
    var checkboxes = document.querySelectorAll('#entity-service-pills input[type=checkbox]:checked');
    var services   = Array.from(checkboxes).map(function(c){ return c.value; });

    document.getElementById('entity-confirm-widget').style.display = 'none';
    document.getElementById('entity-drift-loading').style.display  = '';
    document.getElementById('entity-drift-results').style.display  = 'none';

    var msgs = [
      'Checking how Google classifies each service…',
      'Mapping entity drift across services…',
      'Identifying overshadowed services…',
      'Scoring 12 authority factors…',
      'Building your entity drift report…',
    ];
    var mi = 0;
    var ticker = setInterval(function(){
      var el = document.getElementById('entity-drift-msg');
      if (el) el.textContent = msgs[mi % msgs.length];
      mi++;
    }, 4000);

    var fd = new FormData();
    fd.append('action',   'siloq_run_entity_analysis');
    fd.append('biz',      biz);
    fd.append('domain',   domain);
    fd.append('scan_url', scanUrl);
    fd.append('services', JSON.stringify(services));

    // Always get fresh nonce (page may be cached)
    fetch(ajaxUrl + '?action=siloq_fresh_nonce&_=' + Date.now(), {credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(nr){
        var fn = (nr && nr.success && nr.data && nr.data.nonce) ? nr.data.nonce : nonce;
        fd.append('_wpnonce', fn);
        return fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
      })
      .then(function(r){ return r.json(); })
      .then(function(resp) {
        clearInterval(ticker);
        document.getElementById('entity-drift-loading').style.display = 'none';
        if (resp.success && resp.data) {
          renderEntityDrift(resp.data);
        } else {
          document.getElementById('entity-drift-results').innerHTML = '<div style="font-size:13px;color:var(--t2);padding:12px 0">Entity analysis unavailable. <a href="https://calendly.com/kyle-getprecisionmarketing/website-audit-review" style="color:var(--gold)">Book a call to review manually →</a></div>';
          document.getElementById('entity-drift-results').style.display = '';
        }
      })
      .catch(function(){
        clearInterval(ticker);
        document.getElementById('entity-drift-loading').style.display = 'none';
        document.getElementById('entity-drift-results').innerHTML = '<div style="font-size:13px;color:var(--t2)">Network error during entity analysis.</div>';
        document.getElementById('entity-drift-results').style.display = '';
      });
  };

  function renderEntityDrift(data) {
    var d    = data.data || data;
    var html = '';

    // Perception gap
    if (d.current_perception || d.target_classification) {
      html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">';
      if (d.current_perception) {
        html += '<div style="background:rgba(224,82,82,.07);border:1px solid rgba(224,82,82,.18);border-radius:8px;padding:12px">';
        html += '<div style="font-family:IBM Plex Mono,monospace;font-size:9px;color:#e05252;text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px">How Google sees you now</div>';
        html += '<div style="font-size:13px;color:#c8c4bc;line-height:1.55">' + esc(d.current_perception) + '</div></div>';
      }
      if (d.target_classification) {
        html += '<div style="background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.18);border-radius:8px;padding:12px">';
        html += '<div style="font-family:IBM Plex Mono,monospace;font-size:9px;color:#c9a84c;text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px">Target classification</div>';
        html += '<div style="font-size:13px;color:#c8c4bc;line-height:1.55">' + esc(d.target_classification) + '</div></div>';
      }
      html += '</div>';
    }

    // Service drift table
    var drifts = d.service_drift || [];
    if (drifts.length) {
      html += '<div style="font-family:IBM Plex Mono,monospace;font-size:10px;color:#5a5650;text-transform:uppercase;letter-spacing:.1em;margin-bottom:10px">Service drift analysis</div>';
      var statusMap = {
        recognized:     {color:'#52b87a', bg:'rgba(82,184,122,.12)', label:'RECOGNIZED'},
        overshadowed:   {color:'#e05252', bg:'rgba(224,82,82,.1)',   label:'OVERSHADOWED'},
        misclassified:  {color:'#e05252', bg:'rgba(224,82,82,.1)',   label:'MISCLASSIFIED'},
        weak:           {color:'#d4973e', bg:'rgba(212,151,62,.12)', label:'WEAK'},
      };
      drifts.forEach(function(item) {
        var st  = (item.status || 'weak').toLowerCase();
        var sm  = statusMap[st] || statusMap.weak;
        html += '<div style="display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.07)">';
        html += '<div style="flex:1"><div style="font-size:13px;font-weight:600;color:#fff;margin-bottom:3px">' + esc(item.service || '') + '</div>';
        html += '<div style="font-size:12px;color:#9a9488;line-height:1.5">' + esc(item.desc || '') + '</div></div>';
        html += '<span style="font-family:IBM Plex Mono,monospace;font-size:9px;padding:3px 9px;border-radius:20px;white-space:nowrap;flex-shrink:0;background:' + sm.bg + ';color:' + sm.color + '">' + sm.label + '</span>';
        html += '</div>';
      });
    }

    // Authority gaps
    var gaps = d.authority_gaps || [];
    if (gaps.length) {
      html += '<div style="font-family:IBM Plex Mono,monospace;font-size:10px;color:#5a5650;text-transform:uppercase;letter-spacing:.1em;margin:14px 0 10px">Authority gaps</div>';
      gaps.forEach(function(g) {
        var sev  = (g.severity || 'medium').toLowerCase();
        var gcol = sev === 'critical' ? '#e05252' : (sev === 'high' ? '#d4973e' : '#52b87a');
        html += '<div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.07)">';
        html += '<span style="font-family:IBM Plex Mono,monospace;font-size:9px;padding:2px 7px;border-radius:20px;color:' + gcol + ';border:1px solid ' + gcol + ';flex-shrink:0;text-transform:uppercase;margin-top:2px">' + sev + '</span>';
        html += '<div><div style="font-size:13px;font-weight:600;color:#fff;margin-bottom:2px">' + esc(g.name || '') + '</div>';
        html += '<div style="font-size:12px;color:#9a9488;line-height:1.5">' + esc(g.desc || '') + '</div></div></div>';
      });
    }

    // Key finding
    if (d.key_finding) {
      html += '<div style="margin-top:14px;background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.2);border-radius:8px;padding:12px 14px;font-family:IBM Plex Mono,monospace;font-size:11px;color:#c9a84c;line-height:1.65">' + esc(d.key_finding) + '</div>';
    }

    var resultsEl = document.getElementById('entity-drift-results');
    if (resultsEl) { resultsEl.innerHTML = html; resultsEl.style.display = ''; }
  }

  function esc(s) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
  }
})();
</script>
<?php else : ?>
<div class="card-plain" style="text-align:center;padding:28px">
  <div class="mono" style="font-size:11px;color:var(--t3);margin-bottom:8px;text-transform:uppercase;letter-spacing:.1em">Brand Entity Analysis</div>
  <div style="font-size:15px;color:var(--t2);line-height:1.65;margin-bottom:16px;max-width:480px;margin-left:auto;margin-right:auto">Re-run the diagnostic with your business name to verify your Google Knowledge Graph presence, brand disambiguation, and entity classification signals.</div>
  <a href="/scan/" class="btn-gold" style="font-size:13px;padding:10px 20px">Re-run With Business Name →</a>
</div>
<?php endif; ?>

<!-- ROADMAP -->
<?php $roadmap = $narrative['roadmap'] ?? array(); if ( ! empty( $roadmap ) ) : ?>
<div class="sh" style="margin-top:24px"><span class="sh-lbl">Recovery roadmap — prioritized</span><div class="sh-line"></div></div>
<div class="card-plain">
<?php
$num_cls = array( 'rn-red', 'rn-red', 'rn-amb', 'rn-amb', 'rn-blu', 'rn-blu' );
foreach ( $roadmap as $i => $item ) :
?>
  <div class="rm-row">
    <div class="rm-num <?php echo esc_attr( $num_cls[ min( $i, 5 ) ] ); ?>"><?php echo intval( $item['priority'] ?? $i + 1 ); ?></div>
    <div>
      <div class="rm-time"><?php echo esc_html( $item['timeframe'] ?? '' ); ?></div>
      <div class="rm-title"><?php echo esc_html( $item['title'] ?? '' ); ?></div>
      <div class="rm-desc"><?php echo esc_html( $item['desc'] ?? '' ); ?></div>
      <?php if ( ! empty( $item['siloq_chip'] ) ) : ?><span class="siloq-chip"><?php echo esc_html( $item['siloq_chip'] ); ?></span><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php $fp = intval( $narrative['feasibility_pct'] ?? 20 ); $fn = $narrative['feasibility_note'] ?? ''; if ( $fn ) : ?>
<div class="feas-wrap">
  <div class="feas-lbl">Recovery feasibility</div>
  <div class="feas-track">
    <div class="feas-done" style="width:<?php echo $fp; ?>%"></div>
    <div class="feas-gap" style="width:<?php echo ( 100 - $fp ); ?>%"></div>
  </div>
  <div class="feas-ends"><span>Current (~<?php echo $fp; ?>%)</span><span>Full visibility (100%)</span></div>
  <div class="feas-note"><?php echo esc_html( $fn ); ?></div>
</div>
<?php endif; ?>
</div>
<?php endif; ?>

<!-- SILOQ PITCH -->
<?php $pitch = $narrative['pitch'] ?? ''; if ( $pitch ) : ?>
<div class="pitch-box"><?php echo esc_html( $pitch ); ?></div>
<?php endif; ?>

<!-- CTA -->
<div class="cta-card">
  <div class="cta-eye">Next step</div>
  <div class="cta-title">Let's fix the architecture.</div>
  <div class="cta-sub">Siloq governs your site structure, entity signals, and content depth simultaneously — so every page works toward the same classification goal in Google, Google Maps, and AI search.</div>
  <div class="cta-btns">
    <a href="https://calendly.com/kyle-getprecisionmarketing/website-audit-review" target="_blank" rel="noopener" class="btn-gold">Book a Free Strategy Call →</a>
    <a href="https://app.siloq.ai/register" target="_blank" rel="noopener" class="btn-out">Start Free Trial →</a>
  </div>
</div>

<div style="text-align:center;font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--t3)">
  Run another diagnostic? <a href="/scan/" style="color:var(--gold);text-decoration:none">← Back to scanner</a>
</div>

</div><!-- /.sr-page -->

<script>
(function(){
  // Animate pillar bars
  setTimeout(function(){
    document.querySelectorAll('.pbf[data-w]').forEach(function(el){
      el.style.width = el.getAttribute('data-w');
    });
  }, 150);

  // Entity section: show placeholder after 4s
  var el = document.getElementById('entity-loading');
  var ep = document.getElementById('entity-placeholder');
  if (el && ep) {
    setTimeout(function(){ el.style.display = 'none'; ep.style.display = 'block'; }, 4000);
  }
})();
</script>
        <?php
        return ob_get_clean();
    }

}
