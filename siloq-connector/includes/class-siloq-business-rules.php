<?php
/**
 * Siloq Business Rules
 * Auto-detects business type and provides per-type SEO recommendations.
 *
 * @since 1.5.164
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Detects the business type from installed plugins / site name.
 * Only runs on admin screens for the Siloq plugin page.
 * Never overwrites an existing value.
 */
class Siloq_Business_Detector {

    /**
     * Detect business type and persist if not already set.
     * Must be called explicitly from an admin screen hook.
     */
    public static function detect() {
        // Guard: only run on admin Siloq plugin screens
        if ( ! is_admin() ) {
            return;
        }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || strpos( $screen->id, 'siloq' ) === false ) {
            return;
        }

        // Never overwrite an existing value
        $current = get_option( 'siloq_business_type', '' );
        if ( ! empty( $current ) ) {
            return;
        }

        // Detection order
        if ( class_exists( 'WooCommerce' ) ) {
            $detected = 'ecommerce';
        } elseif ( class_exists( 'Tribe__Events__Main' ) ) {
            $detected = 'event_venue';
        } else {
            $name = strtolower( get_bloginfo( 'name' ) . ' ' . get_bloginfo( 'description' ) );
            if ( strpos( $name, 'dental' ) !== false
                || strpos( $name, 'medical' ) !== false
                || strpos( $name, 'health' ) !== false
            ) {
                $detected = 'medical';
            } else {
                $detected = 'local_service';
            }
        }

        update_option( 'siloq_business_type', $detected );
    }
}

/**
 * Factory: returns the correct rules class for a given business type.
 */
class Siloq_Rules_Factory {

    /**
     * @param string $type Business type slug.
     * @return Siloq_Business_Rules_Base
     */
    public static function get_rules( $type ) {
        switch ( $type ) {
            case 'ecommerce':
                return new Siloq_Ecommerce_Rules();
            case 'event_venue':
                return new Siloq_Event_Venue_Rules();
            case 'local_service_multi':
            case 'local_service':
            default:
                return new Siloq_Local_Service_Rules();
        }
    }
}

/**
 * Base class for business-type-specific SEO rules.
 */
abstract class Siloq_Business_Rules_Base {

    /**
     * Return prioritised action items for the given page data.
     *
     * @param array $page_data
     * @return array
     */
    abstract public function get_priority_actions( $page_data );

    /**
     * Human-readable description of the recommended site architecture.
     *
     * @return string
     */
    abstract public function get_architecture_description();

    /**
     * Whether the URL-restructure panel should be shown.
     *
     * @return bool
     */
    abstract public function should_show_url_restructure();

    /**
     * Whether the city-gaps panel should be shown.
     *
     * @return bool
     */
    abstract public function should_show_city_gaps();
}

/**
 * Local-service business rules (default).
 */
class Siloq_Local_Service_Rules extends Siloq_Business_Rules_Base {

    public function should_show_url_restructure() {
        return true;
    }

    public function should_show_city_gaps() {
        return true;
    }

    public function get_architecture_description() {
        return 'Hub-and-spoke: target city hubs with supporting service area pages';
    }

    public function get_priority_actions( $page_data ) {
        return array(
            array(
                'label'       => 'City hub nesting',
                'description' => 'Group service pages under their target city hub.',
            ),
            array(
                'label'       => 'Redirect opportunities',
                'description' => 'Consolidate thin or duplicate city pages via redirects.',
            ),
            array(
                'label'       => 'City page gaps',
                'description' => 'Identify cities you serve but have no dedicated landing page for.',
            ),
        );
    }
}

/**
 * E-commerce business rules.
 */
class Siloq_Ecommerce_Rules extends Siloq_Business_Rules_Base {

    public function should_show_url_restructure() {
        return false;
    }

    public function should_show_city_gaps() {
        return false;
    }

    public function get_architecture_description() {
        return 'Category-product hierarchy: categories as hubs, products as spokes';
    }

    public function get_priority_actions( $page_data ) {
        return array(
            array(
                'label'       => 'Product page optimization',
                'description' => 'Ensure every product has unique meta, schema, and internal links to its category hub.',
            ),
            array(
                'label'       => 'Category hub structure',
                'description' => 'Strengthen category pages as topical hubs with curated product collections.',
            ),
        );
    }
}

/**
 * Event / venue business rules.
 */
class Siloq_Event_Venue_Rules extends Siloq_Business_Rules_Base {

    public function should_show_url_restructure() {
        return false;
    }

    public function should_show_city_gaps() {
        return false;
    }

    public function get_architecture_description() {
        return 'Event authority: venue page as hub, events and services as spokes';
    }

    public function get_priority_actions( $page_data ) {
        return array(
            array(
                'label'       => 'Event listing optimization',
                'description' => 'Add structured data and unique descriptions to each event page.',
            ),
            array(
                'label'       => 'Venue authority pages',
                'description' => 'Build a strong venue hub page linking to all upcoming and past events.',
            ),
        );
    }
}
