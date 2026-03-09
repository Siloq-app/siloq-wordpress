<?php
/**
 * Siloq Custom Post Type Crawler Fix
 *
 * Fixes two issues observed on sites using custom post types (e.g. "our-services"):
 *
 * BUG 1 — Custom post types not discovered during site crawl because the
 *          sync engine only queries "post" and "page" post types.
 *
 * BUG 2 — Internal links on hub pages (e.g. /services/) reported as zero
 *          because linked URLs belong to CPT pages not in Siloq's index.
 *          Resolves automatically once BUG 1 is fixed.
 *
 * @package Siloq
 * @since 1.5.40
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =============================================================================
// SECTION 1 — DYNAMIC POST TYPE DISCOVERY
// =============================================================================

/**
 * Returns all post type slugs that Siloq should crawl and index.
 * Replaces any hardcoded array( 'post', 'page' ) in the sync engine.
 *
 * @return string[]
 */
function get_siloq_crawlable_post_types() {
    // Get ALL registered post types — no public/queryable filter.
    // Some CPTs (e.g. JetEngine "our-services") are registered with public=false
    // but still have accessible front-end URLs. We include everything then exclude
    // known non-content types below.
    $all_types = get_post_types( array(), 'names' );

    // Always ensure standard types are present
    $all_types = array_unique( array_merge( array_values( $all_types ), array( 'post', 'page' ) ) );

    $excluded = array(
        'attachment',          // binary files
        'revision',            // WP internals
        'nav_menu_item',       // menus
        'custom_css',          // WP internals
        'customize_changeset', // WP internals
        'wp_block',            // reusable blocks
        'wp_template',         // FSE templates
        'wp_template_part',    // FSE template parts
        'wp_global_styles',    // FSE
        'wp_navigation',       // FSE
        'elementor_library',   // Elementor templates (not real pages)
        'e-floating-buttons',  // Elementor popups
        'elementor-hf',        // Elementor Header/Footer
        // Slider / widget CPTs — visual components, not indexable content
        'slider',              // generic slider CPT
        'slides',
        'slide',
        'home_slider',         // JetEngine "Home Slider" and similar
        'home-slider',
        'smart-slider',
        'rev_slider',          // Revolution Slider
        'ml-slider',           // MetaSlider
        'soliloquy',           // Soliloquy Slider
        // Code snippet CPTs — PHP/JS snippets stored as posts, never real pages
        'wpcode_snippet',      // WPCode (formerly Code Snippets)
        'code_snippet',        // Code Snippets plugin
        'cs_snippet',          // another Code Snippets variant
        'wpcode',
        // Form / popup CPTs
        'wpcf7_contact_form',  // CF7
        'popup',
        'popups',
        'optinmonster',
        // WooCommerce internals (both legacy and HPOS formats)
        'product_variation',
        'shop_order',
        'shop_order_refund',
        'shop_order_placehold',  // HPOS compatibility placeholder — orders stored as wp_posts stubs
        'shop_coupon',
        'wc_order',
        'wc_order_refund',
        'wc_order_placehold',    // alias used on some WC versions
        'wc_product_download',
        'wc_user_csv_import_session',
        'wc_webhook',
        'wc_order_status',
        'wc_shipping_zone',
        // Action Scheduler (used by WC, WP Mail SMTP, etc.) — never real content
        'scheduled-action',
        // WP/plugin cache
        'oembed_cache',
    );

    /**
     * Filter: add/remove post types from Siloq's crawl.
     *
     * add_filter( 'siloq_exclude_post_types', function( $ex ) {
     *     return array_merge( $ex, array( 'product_variation' ) );
     * });
     *
     * @param string[] $excluded
     */
    $excluded = apply_filters( 'siloq_exclude_post_types', $excluded );

    $crawlable = array_values( array_diff( $all_types, $excluded ) );

    // If admin has explicitly selected content types in Advanced Settings,
    // intersect with that selection (so unchecked types are skipped).
    $saved_types = get_option( 'siloq_content_types', array() );
    if ( ! empty( $saved_types ) && is_array( $saved_types ) ) {
        // Always include page + post regardless of saved selection
        $saved_types = array_unique( array_merge( $saved_types, array( 'page', 'post' ) ) );
        $crawlable = array_values( array_intersect( $crawlable, $saved_types ) );
    }

    return $crawlable;
}

// =============================================================================
// SECTION 2 — FULL SITE SYNC
// =============================================================================

/**
 * Syncs every public, published post/page/CPT into Siloq's page index.
 * Uses the dynamic post type list — no CPT left behind.
 *
 * @param int|null    $site_id
 * @param string|null $api_key
 * @return array { indexed, skipped, errors[], post_types[] }
 */
function siloq_sync_all_public_pages( $site_id = null, $api_key = null ) {
    $site_id  = $site_id  ?? get_option( 'siloq_site_id' );
    $api_key  = $api_key  ?? get_option( 'siloq_api_key' );
    $api_base = defined( 'SILOQ_API_BASE' ) ? SILOQ_API_BASE : 'https://api.siloq.app';

    $result = array(
        'indexed'    => 0,
        'skipped'    => 0,
        'errors'     => array(),
        'post_types' => array(),
    );

    if ( ! $site_id || ! $api_key ) {
        $result['errors'][] = 'Siloq not connected — check Settings.';
        return $result;
    }

    $post_types          = get_siloq_crawlable_post_types();
    $result['post_types'] = $post_types;

    $query = new WP_Query( array(
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'posts_per_page' => 500,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ) );

    if ( empty( $query->posts ) ) {
        $result['errors'][] = 'No published posts found for: ' . implode( ', ', $post_types );
        return $result;
    }

    foreach ( $query->posts as $post_id ) {
        $post      = get_post( $post_id );
        $permalink = get_permalink( $post_id );
        $post_type = get_post_type( $post_id );

        if ( ! $permalink || ! $post ) {
            $result['skipped']++;
            continue;
        }

        $pt_object = get_post_type_object( $post_type );
        $pt_label  = $pt_object ? $pt_object->labels->singular_name : $post_type;

        $response = wp_remote_post(
            $api_base . '/api/v1/sites/' . $site_id . '/pages/sync',
            array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
                ),
                'body' => wp_json_encode( array(
                    'site_id'         => $site_id,
                    'wp_post_id'      => $post_id,
                    'title'           => $post->post_title,
                    'url'             => $permalink,
                    'type'            => $post_type,
                    'post_type_label' => $pt_label,
                    'status'          => $post->post_status,
                    'modified'        => $post->post_modified,
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            $result['errors'][] = 'Post ' . $post_id . ' (' . $post_type . '): ' . $response->get_error_message();
            continue;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 200 || $code === 201 ) {
            $result['indexed']++;
        } else {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            $result['errors'][] = 'Post ' . $post_id . ' (' . $post_type . '): HTTP ' . $code . ' — ' . ( $body['detail'] ?? $body['message'] ?? 'Unknown' );
        }
    }

    return $result;
}

// =============================================================================
// SECTION 3 — REAL-TIME INDEX UPDATE ON SAVE / PUBLISH
// =============================================================================

add_action( 'save_post', 'siloq_on_save_post_sync', 20, 2 );

function siloq_on_save_post_sync( $post_id, $post ) {
    if ( $post->post_status !== 'publish' ) return;
    if ( ! in_array( $post->post_type, get_siloq_crawlable_post_types(), true ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;

    $site_id  = get_option( 'siloq_site_id' );
    $api_key  = get_option( 'siloq_api_key' );
    $api_base = defined( 'SILOQ_API_BASE' ) ? SILOQ_API_BASE : 'https://api.siloq.app';

    if ( ! $site_id || ! $api_key ) return;

    // Non-blocking — fire and forget, no impact on save experience
    wp_remote_post(
        $api_base . '/api/v1/sites/' . $site_id . '/pages/sync',
        array(
            'timeout'  => 10,
            'blocking' => false,
            'headers'  => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'Siloq/' . SILOQ_VERSION,
            ),
            'body' => wp_json_encode( array(
                'site_id'    => $site_id,
                'wp_post_id' => $post_id,
                'title'      => $post->post_title,
                'url'        => get_permalink( $post_id ),
                'type'       => $post->post_type,
                'status'     => $post->post_status,
                'modified'   => $post->post_modified,
            ) ),
        )
    );
}

// =============================================================================
// SECTION 4 — INTERNAL LINK RESOLVER
// =============================================================================

/**
 * Resolves a URL to determine if it's an internal link and what post it maps to.
 * Uses url_to_postid() which natively handles all registered CPT permalink structures.
 *
 * @param string $href
 * @return array { is_internal, post_id, post_type, permalink }
 */
function siloq_resolve_internal_link( $href ) {
    $result = array(
        'is_internal' => false,
        'post_id'     => 0,
        'post_type'   => '',
        'permalink'   => '',
    );

    if ( empty( $href ) || $href === '#' || strpos( $href, 'javascript:' ) === 0 ) {
        return $result;
    }

    $site_host = wp_parse_url( get_site_url(), PHP_URL_HOST );
    $link_host = wp_parse_url( $href, PHP_URL_HOST );

    $is_internal = empty( $link_host )
        || $link_host === $site_host
        || ( strlen( $link_host ) > strlen( $site_host ) && substr( $link_host, -strlen( $site_host ) - 1 ) === '.' . $site_host );

    $result['is_internal'] = $is_internal;

    if ( ! $is_internal ) {
        return $result;
    }

    // url_to_postid() handles CPT URLs natively
    $post_id = url_to_postid( $href );
    if ( $post_id ) {
        $result['post_id']   = $post_id;
        $result['post_type'] = get_post_type( $post_id );
        $result['permalink'] = get_permalink( $post_id );
    }

    return $result;
}

// =============================================================================
// SECTION 5 — AJAX: RE-SCAN SITE
// =============================================================================

add_action( 'wp_ajax_siloq_rescan_site', 'siloq_ajax_rescan_site' );

function siloq_ajax_rescan_site() {
    check_ajax_referer( 'siloq_ai_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized — admin access required.' ) );
        return;
    }

    $result = siloq_sync_all_public_pages();

    if ( $result['indexed'] === 0 && ! empty( $result['errors'] ) ) {
        wp_send_json_error( array(
            'message'    => 'Re-scan failed — no pages were indexed.',
            'errors'     => $result['errors'],
            'post_types' => $result['post_types'],
        ) );
        return;
    }

    wp_send_json_success( array(
        'message'            => sprintf( 'Re-scan complete. %d pages indexed, %d skipped.', $result['indexed'], $result['skipped'] ),
        'indexed'            => $result['indexed'],
        'skipped'            => $result['skipped'],
        'warnings'           => $result['errors'],
        'post_types_scanned' => $result['post_types'],
    ) );
}

// =============================================================================
// SECTION 6 — ACTIVATION HOOK
// =============================================================================

function siloq_on_activation_cpt_sync() {
    if ( get_option( 'siloq_site_id' ) && get_option( 'siloq_api_key' ) ) {
        siloq_sync_all_public_pages();
    }
}
