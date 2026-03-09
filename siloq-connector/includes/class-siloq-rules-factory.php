<?php
/**
 * Siloq Rules Factory + Business-Type Rulesets
 *
 * Provides per-business-type configuration for SEO recommendations,
 * schema types, hub/spoke patterns, and UI visibility flags.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Rules_Factory {

    /**
     * Get the ruleset instance for a business type.
     *
     * @param string|null $business_type Business type slug, or null to auto-detect.
     * @return object Ruleset instance.
     */
    public static function get_rules( $business_type = null ) {
        if ( $business_type === null ) {
            $business_type = Siloq_Business_Detector::get_effective_type();
        }

        switch ( $business_type ) {
            case 'local_service':
            case 'local_service_multi':
                return new Siloq_Local_Service_Rules();

            case 'ecommerce':
                return new Siloq_Ecommerce_Rules();

            case 'event_venue':
                return new Siloq_Event_Venue_Rules();

            default:
                // For medical, restaurant, content_publisher, general — use local service as base
                return new Siloq_Local_Service_Rules();
        }
    }
}

class Siloq_Local_Service_Rules {

    public $hub_signals = [ 'services', 'service-areas', 'service-area', 'what-we-do' ];

    public $spoke_patterns = [ 'city+state in slug', 'us_state_abbreviation_in_slug' ];

    public $schema_primary = 'LocalBusiness';

    public $url_restructure_enabled = true;

    public $show_city_nesting = true;

    public $show_service_areas_hub = true;

    public $show_ecommerce_gaps = false;

    public $show_event_gaps = false;

    public $priority_action_types = [
        'schema_application',
        'meta_title_fix',
        'city_internal_links',
        'service_areas_hub',
        'cannibalization',
    ];

    public $blocked_recommendation_types = [
        'product_category_hub',
        'event_type_hub',
        'woocommerce_schema',
    ];

    public $content_gap_description = 'Missing city + service pages for your service area';
}

class Siloq_Ecommerce_Rules {

    public $hub_signals = [ 'shop', 'collections', 'categories', 'products' ];

    public $spoke_patterns = [ 'product under category', 'post_type product' ];

    public $schema_primary = 'Product';

    public $url_restructure_enabled = false;

    public $show_city_nesting = false;

    public $show_service_areas_hub = false;

    public $show_ecommerce_gaps = true;

    public $show_event_gaps = false;

    public $priority_action_types = [
        'product_category_hub',
        'missing_meta_descriptions',
        'duplicate_product_titles',
        'woocommerce_schema',
        'thin_category_content',
    ];

    public $blocked_recommendation_types = [
        'city_nesting',
        'service_areas_hub',
        'local_service_gaps',
        'city_internal_links',
    ];

    public $content_gap_description = 'Missing product category hub pages based on your product taxonomy';

    public $exclude_from_architecture = [ 'product' ];
}

class Siloq_Event_Venue_Rules {

    public $hub_signals = [
        'events',
        'services',
        'what-we-do',
        'corporate-events',
        'weddings',
        'social-events',
    ];

    public $spoke_patterns = [ 'event type page', 'venue service page' ];

    public $schema_primary = 'EventVenue';

    public $url_restructure_enabled = false;

    public $show_city_nesting = false;

    public $show_service_areas_hub = false;

    public $show_ecommerce_gaps = false;

    public $show_event_gaps = true;

    public $priority_action_types = [
        'event_type_hub',
        'missing_event_categories',
        'service_internal_links',
        'event_schema',
        'faq_content_gap',
    ];

    public $blocked_recommendation_types = [
        'city_nesting',
        'service_areas_hub',
        'local_service_gaps',
        'city_internal_links',
        'product_category_hub',
    ];

    public $content_gap_description = 'Missing event type hub pages (corporate, wedding, social, nonprofit)';
}
