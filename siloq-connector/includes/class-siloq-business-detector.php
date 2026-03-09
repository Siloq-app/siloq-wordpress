<?php
/**
 * Siloq Business Type Auto-Detector
 *
 * Analyzes site content to automatically detect the business type.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Business_Detector {

    /**
     * US state abbreviations for city+state pattern matching.
     */
    private static $state_abbrevs = [
        'al','ak','az','ar','ca','co','ct','de','fl','ga',
        'hi','id','il','in','ia','ks','ky','la','me','md',
        'ma','mi','mn','ms','mo','mt','ne','nv','nh','nj',
        'nm','ny','nc','nd','oh','ok','or','pa','ri','sc',
        'sd','tn','tx','ut','vt','va','wa','wv','wi','wy',
        'dc',
    ];

    private static $service_keywords = [
        'electrician', 'plumber', 'hvac', 'roofing', 'contractor',
        'electrical', 'heating', 'cooling',
    ];

    private static $event_keywords = [
        'event', 'venue', 'wedding', 'corporate', 'party',
        'gala', 'reception', 'catering',
    ];

    private static $medical_keywords = [
        'treatment', 'patient', 'doctor', 'clinic', 'therapy',
        'dental', 'medical', 'health',
    ];

    private static $restaurant_keywords = [
        'menu', 'reservation', 'dining', 'restaurant', 'cuisine',
        'appetizer',
    ];

    /**
     * Detect business type from site content.
     *
     * @param array|null $pages Array of WP_Post objects. If null, fetches published pages/posts.
     * @return string Business type slug.
     */
    public static function detect( $pages = null ) {
        // (a) WooCommerce check
        if ( class_exists( 'WooCommerce' ) ) {
            $products = get_posts( [
                'post_type'   => 'product',
                'numberposts' => 1,
                'post_status' => 'publish',
                'fields'      => 'ids',
            ] );
            if ( ! empty( $products ) ) {
                return 'ecommerce';
            }
        }

        // Fetch pages if not provided
        if ( $pages === null ) {
            $pages = get_posts( [
                'post_type'   => [ 'page', 'post' ],
                'post_status' => 'publish',
                'numberposts' => 500,
            ] );
        }

        if ( empty( $pages ) ) {
            return 'general';
        }

        // Build a regex pattern for state abbreviations at end of slug
        $state_pattern = '/[-_](' . implode( '|', self::$state_abbrevs ) . ')$/i';
        $state_title_pattern = '/,\s*[A-Z]{2}\s*$/';

        $city_state_count    = 0;
        $has_service_keyword = false;
        $event_count         = 0;
        $medical_count       = 0;
        $restaurant_count    = 0;
        $post_count          = 0;
        $page_count          = 0;

        foreach ( $pages as $page ) {
            $title_lower = strtolower( $page->post_title );
            $slug_lower  = strtolower( $page->post_name );

            // Count post types
            if ( $page->post_type === 'post' ) {
                $post_count++;
            } else {
                $page_count++;
            }

            // (b) City+state detection
            $is_city_state = false;
            if ( preg_match( $state_pattern, $slug_lower ) || preg_match( $state_title_pattern, $page->post_title ) ) {
                $city_state_count++;
                $is_city_state = true;
            }

            // Check for service keywords in titles
            if ( ! $has_service_keyword ) {
                foreach ( self::$service_keywords as $kw ) {
                    if ( strpos( $title_lower, $kw ) !== false || strpos( $slug_lower, $kw ) !== false ) {
                        $has_service_keyword = true;
                        break;
                    }
                }
            }

            // (c) Event keywords
            foreach ( self::$event_keywords as $kw ) {
                if ( strpos( $title_lower, $kw ) !== false || strpos( $slug_lower, $kw ) !== false ) {
                    $event_count++;
                    break;
                }
            }

            // (d) Medical keywords
            foreach ( self::$medical_keywords as $kw ) {
                if ( strpos( $title_lower, $kw ) !== false || strpos( $slug_lower, $kw ) !== false ) {
                    $medical_count++;
                    break;
                }
            }

            // (e) Restaurant keywords
            foreach ( self::$restaurant_keywords as $kw ) {
                if ( strpos( $title_lower, $kw ) !== false || strpos( $slug_lower, $kw ) !== false ) {
                    $restaurant_count++;
                    break;
                }
            }
        }

        // (b) Local service: >3 city+state pages AND service keywords present
        if ( $city_state_count > 3 && $has_service_keyword ) {
            return 'local_service';
        }

        // (c) Event venue
        if ( $event_count > 3 ) {
            return 'event_venue';
        }

        // (d) Medical practice
        if ( $medical_count > 3 ) {
            return 'medical_practice';
        }

        // (e) Restaurant
        if ( $restaurant_count > 3 ) {
            return 'restaurant';
        }

        // (f) Content publisher: majority of content is blog posts
        $total = $post_count + $page_count;
        if ( $total > 0 && $post_count > $page_count ) {
            return 'content_publisher';
        }

        // (g) Default
        return 'general';
    }

    /**
     * Get or detect business type. User override wins.
     *
     * @return string Business type slug.
     */
    public static function get_or_detect() {
        $manual = get_option( 'siloq_business_type', '' );
        if ( ! empty( $manual ) ) {
            return $manual;
        }

        $result = self::detect();
        update_option( 'siloq_business_type_auto', $result );
        return $result;
    }

    /**
     * Get the effective business type (manual > auto > general).
     *
     * @return string Business type slug.
     */
    public static function get_effective_type() {
        $manual = get_option( 'siloq_business_type', '' );
        if ( ! empty( $manual ) ) {
            return $manual;
        }

        $auto = get_option( 'siloq_business_type_auto', '' );
        if ( ! empty( $auto ) ) {
            return $auto;
        }

        return 'general';
    }

    /**
     * Get human-readable label for a business type.
     *
     * @param string $type Business type slug.
     * @return string Human-readable label.
     */
    public static function get_label( $type ) {
        $labels = [
            'local_service'     => 'Local Service Business',
            'local_service_multi' => 'Local Service Business (Multi-Location)',
            'ecommerce'         => 'E-Commerce Store',
            'event_venue'       => 'Event Venue / Planning',
            'medical_practice'  => 'Medical Practice',
            'restaurant'        => 'Restaurant',
            'content_publisher' => 'Content Publisher / Blog',
            'general'           => 'General Business',
        ];

        return isset( $labels[ $type ] ) ? $labels[ $type ] : 'General Business';
    }
}
