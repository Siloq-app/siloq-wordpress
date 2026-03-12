<?php
/**
 * Siloq Goals — syncs owner goals to the API
 * WP option: siloq_site_goals (JSON)
 */
class Siloq_Goals {

    public static function get_goals() {
        $raw = get_option( 'siloq_site_goals', '' );
        if ( ! $raw ) return array();
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : array();
    }

    public static function save_goals( $goals ) {
        update_option( 'siloq_site_goals', wp_json_encode( $goals ) );
    }

    public static function sync_to_api( $site_id, $api_client ) {
        $goals = self::get_goals();
        if ( empty( $goals ) ) {
            return array( 'success' => false, 'message' => 'No goals set' );
        }

        // Pre-populate from existing WP options if not already in goals
        if ( empty( $goals['priority_services'] ) ) {
            $services_raw = get_option( 'siloq_primary_services', '' );
            if ( $services_raw ) {
                $services = json_decode( $services_raw, true );
                if ( is_array( $services ) ) {
                    $goals['priority_services'] = $services;
                }
            }
        }

        if ( empty( $goals['priority_locations'] ) ) {
            $cities_raw = get_option( 'siloq_service_cities', '' );
            if ( $cities_raw ) {
                $cities = json_decode( $cities_raw, true );
                if ( is_array( $cities ) ) {
                    $locations = array();
                    $rank      = 1;
                    foreach ( $cities as $city ) {
                        // city may be string "Kansas City, MO" or array
                        if ( is_string( $city ) ) {
                            $parts       = explode( ',', $city );
                            $locations[] = array(
                                'city'  => trim( isset( $parts[0] ) ? $parts[0] : $city ),
                                'state' => trim( isset( $parts[1] ) ? $parts[1] : '' ),
                                'rank'  => $rank++,
                            );
                        }
                    }
                    $goals['priority_locations'] = $locations;
                }
            }
        }

        // Include target_keywords if saved (new field, replaces geo_priority_pages)
        if ( empty( $goals['target_keywords'] ) ) {
            $kw_raw = get_option( 'siloq_target_keywords', '' );
            if ( $kw_raw ) {
                $keywords = json_decode( $kw_raw, true );
                if ( is_array( $keywords ) && ! empty( $keywords ) ) {
                    $goals['target_keywords'] = $keywords;
                }
            }
        }

        return $api_client->make_request( '/sites/' . intval( $site_id ) . '/goals/', 'POST', $goals );
    }
}
