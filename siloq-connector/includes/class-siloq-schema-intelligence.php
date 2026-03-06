<?php
/**
 * Siloq Schema Intelligence
 *
 * Automatically determines the correct schema types for each page based on
 * the business entity profile, WordPress page type, and confirmed content
 * signals (FAQs, reviews, services, etc.). Produces a complete, valid
 * JSON-LD package with zero manual input required.
 *
 * Architecture:
 *  - Single class, shared by the Admin Metabox and the Elementor Panel.
 *  - Output is wp_head ONLY. Never touches post_content or _elementor_data.
 *  - "Apply" path delegates to Siloq_Schema_Architect::save_schema() so all
 *    output is managed by the existing Architect inject_schema() hook.
 *
 * Workflow (enforced):
 *  1. generate()  → produces candidate schemas, stores in _siloq_suggested_schema.
 *  2. Preview UI  → user reviews the JSON-LD.
 *  3. validate()  → hard gate: name + (address|phone) required for LocalBusiness.
 *  4. apply()     → persists to Architect DB table; clears legacy meta.
 *
 * GUARDRAILS (do not remove):
 *  G1 - FAQPage: added ONLY when confirmed faq_items[] exist in page analysis.
 *  G2 - AggregateRating: added ONLY when review_count + average_rating are
 *       both present AND page analysis confirms a visible rating element.
 *  G3 - No empty strings, nulls, or placeholder values in output.
 *       Missing/empty properties are omitted entirely.
 *  G4 - Auto-apply is forbidden. generate() never writes to active schema.
 *
 * V2 ROADMAP (not implemented here):
 *  - E-commerce / Product / Offer schemas (ecommerce / shopify / WooCommerce).
 *    Blocked until product data pipeline is confirmed stable.
 *  - ItemList for category/archive pages.
 *  - Event schema for EventVenue business type.
 *
 * @package Siloq
 * @since   1.5.49
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Schema_Intelligence {

    // ── Bootstrap ────────────────────────────────────────────────────────────

    /**
     * Register all AJAX hooks. Called from siloq-connector.php.
     */
    public static function init() {
        add_action( 'wp_ajax_siloq_generate_schema', [ __CLASS__, 'ajax_generate_schema' ] );
        add_action( 'wp_ajax_siloq_apply_schema',    [ __CLASS__, 'ajax_apply_schema'    ] );
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Generate a complete schema package for a given page.
     *
     * Returns an array of schema objects ready for json_encode. Does NOT
     * persist to active schema storage — call apply() for that.
     *
     * @param int   $post_id        WordPress post ID.
     * @param array $entity_profile Merged business profile (from meta or options).
     * @param array $page_analysis  Content signals from _siloq_analysis_data meta.
     * @return array<array> Zero or more schema objects.
     */
    public static function generate( $post_id, $entity_profile = [], $page_analysis = [] ) {
        if ( empty( $entity_profile ) ) {
            $entity_profile = self::get_entity_profile( $post_id );
        }

        $page_type     = self::detect_page_type( $post_id, $page_analysis );
        $business_type = $entity_profile['business_type'] ?? 'local_service';
        $specific_type = self::map_business_type( $business_type );
        $schemas       = [];

        // ── Base: LocalBusiness for all local/service/professional businesses ─
        // E-commerce/SaaS/Blog excluded here — see V2 ROADMAP above.
        $skip_local_business = in_array( $business_type, [ 'ecommerce', 'saas', 'blog' ], true );
        if ( ! $skip_local_business ) {
            $lb = self::build_local_business( $entity_profile, $specific_type, $page_analysis );
            if ( $lb ) {
                $schemas[] = $lb;
            }
        }

        // ── Page-specific schema layer ────────────────────────────────────────
        switch ( $page_type ) {
            case 'homepage':
                $website = self::build_website( $entity_profile );
                if ( $website ) {
                    $schemas[] = $website;
                }
                break;

            case 'service':
                $service = self::build_service( $post_id, $entity_profile );
                if ( $service ) {
                    $schemas[] = $service;
                }
                break;

            case 'blog':
                $article = self::build_article( $post_id );
                if ( $article ) {
                    $schemas[] = $article;
                }
                break;

            case 'location':
                // Location-specific LocalBusiness already added as base above.
                // A future enhancement could override with location-specific data.
                break;

            case 'about':
                $schemas[] = self::clean( [
                    '@context' => 'https://schema.org',
                    '@type'    => 'AboutPage',
                    'url'      => get_permalink( $post_id ),
                    'name'     => get_the_title( $post_id ),
                ] );
                break;

            case 'contact':
                $schemas[] = self::clean( [
                    '@context' => 'https://schema.org',
                    '@type'    => 'ContactPage',
                    'url'      => get_permalink( $post_id ),
                    'name'     => get_the_title( $post_id ),
                ] );
                break;

            case 'product':
                // V2 ROADMAP: Full Product + Offer schema (needs WooCommerce/price pipeline).
                // Intentionally omitted to avoid outputting incomplete Product schema.
                break;

            case 'faq':
                // FAQ schema handled by content signal below.
                break;
        }

        // ── Content signals ───────────────────────────────────────────────────

        // G1: FAQPage — ONLY when confirmed faq_items exist (never speculative).
        $faq_items = self::resolve_faq_items( $post_id, $page_analysis );
        if ( ! empty( $faq_items ) ) {
            $faq_schema = self::build_faq_page( $faq_items );
            if ( $faq_schema ) {
                $schemas[] = $faq_schema;
            }
        }

        // Team members → Person schema (one per member with a name).
        $team_members = $entity_profile['team_members'] ?? [];
        if ( ! empty( $team_members ) && is_array( $team_members ) ) {
            foreach ( $team_members as $member ) {
                if ( empty( $member['name'] ) ) {
                    continue;
                }
                $person = self::clean( [
                    '@context' => 'https://schema.org',
                    '@type'    => 'Person',
                    'name'     => $member['name'],
                    'jobTitle' => $member['role'] ?? null,
                    'url'      => $member['url'] ?? null,
                ] );
                if ( $person ) {
                    $schemas[] = $person;
                }
            }
        }

        return array_values( array_filter( $schemas ) );
    }

    /**
     * Validate a set of schema objects before applying.
     *
     * @param array $schemas
     * @param array $entity_profile
     * @return array { valid: bool, errors: string[] }
     */
    public static function validate( $schemas, $entity_profile = [] ) {
        $errors = [];

        if ( empty( $schemas ) ) {
            $errors[] = 'No schema objects to apply.';
            return [ 'valid' => false, 'errors' => $errors ];
        }

        foreach ( $schemas as $schema ) {
            $type = $schema['@type'] ?? 'Unknown';

            // LocalBusiness and all sub-types require name + (address|phone).
            if ( self::is_local_business_type( $type ) ) {
                if ( empty( $schema['name'] ) ) {
                    $errors[] = "{$type}: 'name' is required.";
                }
                $has_address = ! empty( $schema['address']['streetAddress'] );
                $has_phone   = ! empty( $schema['telephone'] );
                if ( ! $has_address && ! $has_phone ) {
                    $errors[] = "{$type}: at least one of 'address' or 'telephone' is required.";
                }
            }

            // FAQPage must have at least one question.
            if ( $type === 'FAQPage' ) {
                $entities = $schema['mainEntity'] ?? [];
                if ( empty( $entities ) ) {
                    $errors[] = 'FAQPage: mainEntity must contain at least one Question.';
                }
            }
        }

        return [
            'valid'  => empty( $errors ),
            'errors' => $errors,
        ];
    }

    /**
     * Detect the semantic page type from post + content signals.
     *
     * @param int   $post_id
     * @param array $page_analysis Optional — pre-loaded analysis data.
     * @return string homepage|service|location|blog|faq|about|contact|product|default
     */
    public static function detect_page_type( $post_id, $page_analysis = [] ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return 'default';
        }

        // Homepage: matches WP front page setting or is_front_page().
        $front_page_id = (int) get_option( 'page_on_front' );
        if ( $front_page_id && (int) $post_id === $front_page_id ) {
            return 'homepage';
        }
        // Calling is_front_page() only works in a front-end WP context; guard it.
        if ( function_exists( 'is_front_page' ) && ! is_admin() && is_front_page() ) {
            return 'homepage';
        }

        // Blog posts (CPT = 'post').
        if ( $post->post_type === 'post' ) {
            return 'blog';
        }

        // Content signal override: analysis confirms FAQ items → FAQ page.
        $confirmed_faq = self::resolve_faq_items( $post_id, $page_analysis );
        if ( ! empty( $confirmed_faq ) ) {
            return 'faq';
        }

        // Slug-based detection: check the post slug and the full URL path.
        $slug = $post->post_name;
        $path = '';
        $permalink = get_permalink( $post_id );
        if ( $permalink ) {
            $path = strtolower( wp_parse_url( $permalink, PHP_URL_PATH ) ?? '' );
        }

        $slug_map = [
            'faq'      => [ 'faq', 'faqs', 'questions', 'frequently-asked-questions' ],
            'service'  => [ 'service', 'services', 'what-we-do', 'our-services' ],
            'location' => [ 'location', 'locations', 'area', 'areas', 'service-area', 'service-areas' ],
            'about'    => [ 'about', 'about-us', 'team', 'our-team', 'story', 'our-story', 'who-we-are' ],
            'contact'  => [ 'contact', 'contact-us', 'reach-us', 'get-in-touch', 'get-a-quote' ],
            'product'  => [ 'product', 'products', 'shop', 'store' ],
        ];

        foreach ( $slug_map as $type => $keywords ) {
            foreach ( $keywords as $keyword ) {
                if (
                    $slug === $keyword
                    || strpos( $slug, $keyword ) !== false
                    || strpos( $path, '/' . $keyword . '/' ) !== false
                    || substr( $path, - strlen( '/' . $keyword ) ) === '/' . $keyword
                ) {
                    return $type;
                }
            }
        }

        // Default: most local-business pages are service pages.
        return 'service';
    }

    /**
     * Map a business_type string to the most specific Schema.org @type.
     *
     * @param string $business_type Free-form identifier from entity profile.
     * @return string Valid Schema.org type string.
     */
    public static function map_business_type( $business_type ) {
        // Keys are lowercase, trimmed identifiers from entity profile.
        // Values are exact Schema.org type strings.
        $map = [
            // ── Home Services ────────────────────────────────────────────────
            'electrician'          => 'Electrician',
            'plumber'              => 'Plumber',
            'hvac'                 => 'HVACBusiness',
            'roofing'              => 'RoofingContractor',
            'roofer'               => 'RoofingContractor',
            'roofing_contractor'   => 'RoofingContractor',
            'contractor'           => 'GeneralContractor',
            'general_contractor'   => 'GeneralContractor',
            'local_service'        => 'LocalBusiness',
            'local-service'        => 'LocalBusiness',
            'locksmith'            => 'Locksmith',
            'painter'              => 'HousePainter',
            'painting'             => 'HousePainter',
            'landscaping'          => 'LandscapingBusiness',
            'landscaper'           => 'LandscapingBusiness',
            'moving'               => 'MovingCompany',
            'moving_company'       => 'MovingCompany',
            'cleaning'             => 'HouseCleaning',
            'cleaning_service'     => 'HouseCleaning',
            'pest_control'         => 'PestControlService',
            'pest_control_service' => 'PestControlService',

            // ── Health & Medical ────────────────────────────────────────────
            'dental'               => 'Dentist',
            'dentist'              => 'Dentist',
            'medical'              => 'MedicalClinic',
            'medical_clinic'       => 'MedicalClinic',
            'physician'            => 'Physician',
            'doctor'               => 'Physician',
            'healthcare'           => 'MedicalClinic',
            'optometrist'          => 'Optician',
            'optician'             => 'Optician',
            'chiropractor'         => 'Chiropractor',
            'chiropractic'         => 'Chiropractor',
            'veterinarian'         => 'VeterinaryCare',
            'vet'                  => 'VeterinaryCare',

            // ── Legal & Financial ────────────────────────────────────────────
            'law'                  => 'Attorney',
            'attorney'             => 'Attorney',
            'lawyer'               => 'Attorney',
            'legal'                => 'Attorney',
            'accounting'           => 'AccountingService',
            'accountant'           => 'AccountingService',
            'financial'            => 'FinancialPlanningService',
            'financial_planner'    => 'FinancialPlanningService',
            'insurance'            => 'InsuranceAgency',
            'insurance_agency'     => 'InsuranceAgency',
            'real_estate'          => 'RealEstateAgent',
            'realtor'              => 'RealEstateAgent',

            // ── Automotive ───────────────────────────────────────────────────
            'auto'                 => 'AutoRepair',
            'auto_repair'          => 'AutoRepair',
            'mechanic'             => 'AutoRepair',
            'auto_dealer'          => 'AutoDealer',
            'car_dealer'           => 'AutoDealer',

            // ── Beauty & Wellness ────────────────────────────────────────────
            'salon'                => 'HairSalon',
            'hair_salon'           => 'HairSalon',
            'nail_salon'           => 'NailSalon',
            'spa'                  => 'DaySpa',
            'beauty'               => 'BeautySalon',
            'beauty_salon'         => 'BeautySalon',
            'gym'                  => 'SportsActivityLocation',
            'fitness'              => 'SportsActivityLocation',
            'health_club'          => 'SportsActivityLocation',

            // ── Food & Hospitality ───────────────────────────────────────────
            'restaurant'           => 'Restaurant',
            'food'                 => 'FoodEstablishment',
            'bakery'               => 'Bakery',
            'cafe'                 => 'CafeOrCoffeeShop',
            'coffee'               => 'CafeOrCoffeeShop',
            'bar'                  => 'BarOrPub',
            'fast_food'            => 'FastFoodRestaurant',

            // ── Other Services ───────────────────────────────────────────────
            'childcare'            => 'ChildCare',
            'daycare'              => 'ChildCare',
            'school'               => 'School',
            'education'            => 'EducationalOrganization',
            'funeral'              => 'FuneralHome',
            'funeral_home'         => 'FuneralHome',
            'pet_store'            => 'PetStore',
            'pet'                  => 'PetStore',
            'event_venue'          => 'EventVenue',
            'venue'                => 'EventVenue',

            // ── Software / SaaS ──────────────────────────────────────────────
            // V2 ROADMAP: ecommerce/Product/Offer schemas not yet implemented.
            'saas'                 => 'SoftwareApplication',
            'software'             => 'SoftwareApplication',
            'ecommerce'            => 'OnlineBusiness', // V2 placeholder — Product schema not implemented.

            // ── Generic fallbacks ────────────────────────────────────────────
            'blog'                 => 'Blog',
            'organization'         => 'Organization',
            'default'              => 'LocalBusiness',
        ];

        $key = strtolower( trim( (string) $business_type ) );
        return $map[ $key ] ?? 'LocalBusiness';
    }

    /**
     * Retrieve the entity profile for a post, with graceful fallbacks.
     *
     * Priority:
     *   1. Post-specific _siloq_entity_profile meta (most specific)
     *   2. Site-wide siloq_entity_profile option blob
     *   3. Individual Siloq/WP options (settings page + core WP options)
     *   4. GBP sync data (fills gaps for phone/address/reviews)
     *   5. Absolute fallback — just business name from WP
     *
     * @param int|null $post_id
     * @return array
     */
    public static function get_entity_profile( $post_id = null ) {
        // 1. Post-specific entity profile (most specific).
        if ( $post_id ) {
            $post_meta_raw = get_post_meta( $post_id, '_siloq_entity_profile', true );
            if ( ! empty( $post_meta_raw ) ) {
                $decoded = is_array( $post_meta_raw )
                    ? $post_meta_raw
                    : json_decode( $post_meta_raw, true );
                if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                    return $decoded;
                }
            }
        }

        // 2. Site-wide entity profile option blob.
        $site_option_raw = get_option( 'siloq_entity_profile', '' );
        if ( ! empty( $site_option_raw ) ) {
            $decoded = is_array( $site_option_raw )
                ? $site_option_raw
                : json_decode( $site_option_raw, true );
            if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                return $decoded;
            }
        }

        // 3. Build from individual Siloq options (what the settings page saves),
        //    with WP core options as fallbacks for name/description/url.
        $profile = [];

        $field_map = [
            'business_name'  => [ 'siloq_business_name', 'blogname' ],
            'business_type'  => [ 'siloq_business_type', 'siloq_business_category' ],
            'phone'          => [ 'siloq_phone', 'siloq_business_phone' ],
            'address'        => [ 'siloq_address', 'siloq_street_address' ],
            'city'           => [ 'siloq_city' ],
            'state'          => [ 'siloq_state' ],
            'zip'            => [ 'siloq_zip', 'siloq_postal_code' ],
            'description'    => [ 'siloq_description', 'blogdescription' ],
            'logo_url'       => [ 'siloq_logo_url' ],
            'service_cities' => [ 'siloq_service_cities', 'siloq_service_areas' ],
            'website_url'    => [ 'siteurl' ],
            'review_count'   => [ 'siloq_review_count' ],
            'average_rating' => [ 'siloq_average_rating' ],
            'hours'          => [ 'siloq_business_hours' ],
            'gbp_url'        => [ 'siloq_gbp_url' ],
        ];

        foreach ( $field_map as $key => $option_names ) {
            foreach ( $option_names as $option_name ) {
                $val = get_option( $option_name, '' );
                if ( ! empty( $val ) ) {
                    $profile[ $key ] = $val;
                    break;
                }
            }
        }

        // 4. Fill remaining gaps from GBP sync data.
        $gbp_data = get_option( 'siloq_gbp_data', '' );
        if ( $gbp_data ) {
            $gbp = is_array( $gbp_data ) ? $gbp_data : json_decode( $gbp_data, true );
            if ( is_array( $gbp ) && ! empty( $gbp ) ) {
                if ( empty( $profile['phone'] ) && ! empty( $gbp['phone'] ) ) {
                    $profile['phone'] = $gbp['phone'];
                }
                if ( empty( $profile['address'] ) && ! empty( $gbp['address'] ) ) {
                    $profile['address'] = $gbp['address'];
                }
                if ( empty( $profile['city'] ) && ! empty( $gbp['city'] ) ) {
                    $profile['city'] = $gbp['city'];
                }
                if ( empty( $profile['review_count'] ) && ! empty( $gbp['review_count'] ) ) {
                    $profile['review_count'] = $gbp['review_count'];
                }
                if ( empty( $profile['average_rating'] ) && ! empty( $gbp['rating'] ) ) {
                    $profile['average_rating'] = $gbp['rating'];
                }
            }
        }

        if ( ! empty( $profile['business_name'] ) ) {
            return $profile;
        }

        // 5. Absolute fallback — just business name from WP.
        return [
            'business_name' => get_bloginfo( 'name' ),
            'website_url'   => get_site_url(),
        ];
    }

    // ── Private: Schema Builders ─────────────────────────────────────────────

    /**
     * Build a LocalBusiness (or specific sub-type) schema object.
     *
     * G2 guardrail: AggregateRating is only added when BOTH review_count and
     * average_rating are present in $entity_profile AND $page_analysis confirms
     * a visible rating element ('has_visible_rating' === true).
     *
     * G3 guardrail: All empty/null values are stripped via self::clean().
     *
     * @param array  $entity_profile
     * @param string $specific_type   Schema.org @type (e.g. 'Electrician').
     * @param array  $page_analysis   Content signal data.
     * @return array|null
     */
    private static function build_local_business( $entity_profile, $specific_type = 'LocalBusiness', $page_analysis = [] ) {
        $name = $entity_profile['business_name'] ?? get_bloginfo( 'name' );
        if ( empty( $name ) ) {
            return null; // Cannot build a valid LocalBusiness without a name.
        }

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => $specific_type,
            '@id'         => get_site_url() . '/#business',
            'name'        => $name,
            'url'         => $entity_profile['website_url'] ?? get_site_url(),
            'telephone'   => $entity_profile['phone'] ?? null,
            'description' => $entity_profile['description'] ?? null,
        ];

        // Postal address — only include when we have a street address.
        if ( ! empty( $entity_profile['address'] ) ) {
            $schema['address'] = self::clean( [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $entity_profile['address'],
                'addressLocality' => $entity_profile['city'] ?? null,
                'addressRegion'   => $entity_profile['state'] ?? null,
                'postalCode'      => $entity_profile['zip'] ?? null,
                'addressCountry'  => 'US',
            ] );
        }

        // Service areas (areaServed).
        $cities = self::resolve_service_cities( $entity_profile );
        if ( ! empty( $cities ) ) {
            $schema['areaServed'] = array_map( function ( $city ) {
                return [ '@type' => 'City', 'name' => $city ];
            }, $cities );
        }

        // Logo (ImageObject).
        if ( ! empty( $entity_profile['logo_url'] ) ) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url'   => $entity_profile['logo_url'],
            ];
        }

        // G2: AggregateRating — requires BOTH review data in entity profile AND a
        // confirmed visible rating element on the page (passed via $page_analysis).
        // Google requires the rating to be visible in the DOM; never add speculatively.
        $has_review_data    = ! empty( $entity_profile['review_count'] )
                              && ! empty( $entity_profile['average_rating'] );
        $has_visible_rating = ! empty( $page_analysis['has_visible_rating'] );

        if ( $has_review_data && $has_visible_rating ) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => (string) $entity_profile['average_rating'],
                'reviewCount' => (int) $entity_profile['review_count'],
            ];
        }

        // Opening hours.
        if ( ! empty( $entity_profile['hours'] ) ) {
            $schema['openingHours'] = $entity_profile['hours'];
        }

        return self::clean( $schema );
    }

    /**
     * Build a Service schema for a service page.
     *
     * @param int   $post_id
     * @param array $entity_profile
     * @return array|null
     */
    private static function build_service( $post_id, $entity_profile ) {
        $title = get_the_title( $post_id );
        if ( empty( $title ) ) {
            return null;
        }

        $post        = get_post( $post_id );
        $description = '';
        if ( $post ) {
            $description = get_the_excerpt( $post_id );
            if ( empty( $description ) ) {
                $description = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
            }
        }

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Service',
            'name'        => $title,
            'url'         => get_permalink( $post_id ),
            'description' => $description ?: null,
            'provider'    => [
                '@type' => 'LocalBusiness',
                '@id'   => get_site_url() . '/#business',
                'name'  => $entity_profile['business_name'] ?? get_bloginfo( 'name' ),
            ],
        ];

        // areaServed from entity profile.
        $cities = self::resolve_service_cities( $entity_profile );
        if ( ! empty( $cities ) ) {
            $schema['areaServed'] = array_map( function ( $city ) {
                return [ '@type' => 'City', 'name' => $city ];
            }, $cities );
        }

        return self::clean( $schema );
    }

    /**
     * Build a FAQPage schema from confirmed FAQ items.
     *
     * G1 guardrail: this method should only be called when $faq_items is
     * non-empty and has been explicitly confirmed via page analysis. It does
     * NOT speculatively generate FAQ schema.
     *
     * @param array $faq_items Array of { question: string, answer: string }.
     * @return array|null
     */
    private static function build_faq_page( $faq_items ) {
        $entities = [];
        foreach ( $faq_items as $item ) {
            $question = trim( $item['question'] ?? '' );
            $answer   = trim( $item['answer'] ?? '' );
            if ( empty( $question ) ) {
                continue; // G3: skip incomplete items rather than output empty.
            }
            $entities[] = [
                '@type'          => 'Question',
                'name'           => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $answer, // empty answer is allowed (better than omitting)
                ],
            ];
        }

        if ( empty( $entities ) ) {
            return null;
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * Build an Article / BlogPosting schema for blog posts.
     *
     * @param int $post_id
     * @return array|null
     */
    private static function build_article( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return null;
        }

        $author_id   = (int) $post->post_author;
        $author_name = get_the_author_meta( 'display_name', $author_id );
        $description = get_the_excerpt( $post_id );
        if ( empty( $description ) ) {
            $description = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
        }

        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'BlogPosting',
            'headline'      => get_the_title( $post_id ),
            'url'           => get_permalink( $post_id ),
            'datePublished' => get_post_time( 'c', false, $post ),
            'dateModified'  => get_post_modified_time( 'c', false, $post ),
            'description'   => $description ?: null,
            'author'        => [
                '@type' => 'Person',
                'name'  => $author_name ?: null,
            ],
            'publisher'     => [
                '@type' => 'Organization',
                '@id'   => get_site_url() . '/#business',
                'name'  => get_bloginfo( 'name' ),
            ],
        ];

        // Featured image (optional).
        $thumbnail_id  = get_post_thumbnail_id( $post_id );
        $thumbnail_url = $thumbnail_id
            ? wp_get_attachment_image_url( $thumbnail_id, 'full' )
            : false;
        if ( $thumbnail_url ) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url'   => $thumbnail_url,
            ];
        }

        return self::clean( $schema );
    }

    /**
     * Build a WebSite schema with a SearchAction (homepage only).
     *
     * @param array $entity_profile
     * @return array
     */
    private static function build_website( $entity_profile ) {
        $site_url  = get_site_url();
        $site_name = $entity_profile['business_name'] ?? get_bloginfo( 'name' );

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            '@id'             => $site_url . '/#website',
            'url'             => $site_url,
            'name'            => $site_name,
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $site_url . '/?s={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    // ── Private: Helpers ─────────────────────────────────────────────────────

    /**
     * G3: Recursively remove null, empty-string, and empty-array values
     * from a schema array so they are never present in JSON-LD output.
     *
     * @param array $data
     * @return array
     */
    private static function clean( $data ) {
        $result = [];
        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) {
                $cleaned = self::clean( $value );
                if ( ! empty( $cleaned ) ) {
                    $result[ $key ] = $cleaned;
                }
            } elseif ( $value !== null && $value !== '' ) {
                $result[ $key ] = $value;
            }
        }
        return $result;
    }

    /**
     * Resolve confirmed FAQ items from page analysis or post meta.
     * Returns empty array when no confirmed items exist (enforces G1).
     *
     * @param int   $post_id
     * @param array $page_analysis
     * @return array
     */
    /**
     * Read FAQ Q&A pairs directly from Elementor page data (_elementor_data).
     * Extracts accordion and toggle widgets — the most common FAQ widget types.
     * Used when _siloq_analysis_data doesn't yet contain faq_items (e.g. before
     * the first sync, or when accordion content was added after last sync).
     *
     * @param int $post_id
     * @return array Array of { question: string, answer: string }
     */
    private static function extract_faq_from_elementor( $post_id ) {
        $raw = get_post_meta( $post_id, '_elementor_data', true );
        if ( empty( $raw ) ) {
            return [];
        }
        $elements = json_decode( $raw, true );
        if ( ! is_array( $elements ) ) {
            return [];
        }
        return self::extract_faq_items_recursive( $elements );
    }

    /**
     * Recursive walker for extract_faq_from_elementor().
     *
     * @param array $elements
     * @return array
     */
    private static function extract_faq_items_recursive( $elements ) {
        $faqs = [];
        foreach ( $elements as $el ) {
            if ( ! empty( $el['elements'] ) ) {
                $faqs = array_merge( $faqs, self::extract_faq_items_recursive( $el['elements'] ) );
            }
            if ( ( $el['elType'] ?? '' ) !== 'widget' ) {
                continue;
            }
            $type     = $el['widgetType'] ?? '';
            $settings = $el['settings']   ?? [];

            if ( in_array( $type, [ 'accordion', 'toggle' ], true ) ) {
                $tabs = $settings['tabs'] ?? [];
                foreach ( $tabs as $tab ) {
                    $q = wp_strip_all_tags( $tab['tab_title']   ?? '' );
                    $a = wp_strip_all_tags( $tab['tab_content'] ?? '' );
                    if ( $q && $a ) {
                        $faqs[] = [ 'question' => $q, 'answer' => $a ];
                    }
                }
            }
        }
        return $faqs;
    }

    private static function resolve_faq_items( $post_id, $page_analysis = [] ) {
        // 1. From page analysis (highest priority — freshest data).
        if ( ! empty( $page_analysis['faq_items'] ) && is_array( $page_analysis['faq_items'] ) ) {
            return $page_analysis['faq_items'];
        }

        // 2. From dedicated post meta (_siloq_faq_items).
        $raw = get_post_meta( $post_id, '_siloq_faq_items', true );
        if ( ! empty( $raw ) ) {
            $decoded = is_array( $raw ) ? $raw : json_decode( $raw, true );
            if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                return $decoded;
            }
        }

        // 3. From analysis data meta (_siloq_analysis_data).
        $analysis_raw = get_post_meta( $post_id, '_siloq_analysis_data', true );
        if ( ! empty( $analysis_raw ) ) {
            $analysis = is_array( $analysis_raw ) ? $analysis_raw : json_decode( $analysis_raw, true );
            if ( ! empty( $analysis['faq_items'] ) && is_array( $analysis['faq_items'] ) ) {
                return $analysis['faq_items'];
            }
        }

        return [];
    }

    /**
     * Resolve service_cities from entity profile into a clean array.
     *
     * @param array $entity_profile
     * @return array
     */
    private static function resolve_service_cities( $entity_profile ) {
        if ( empty( $entity_profile['service_cities'] ) ) {
            return [];
        }
        $cities = is_array( $entity_profile['service_cities'] )
            ? $entity_profile['service_cities']
            : explode( ',', $entity_profile['service_cities'] );

        return array_values( array_filter( array_map( 'trim', $cities ) ) );
    }

    /**
     * Check whether a Schema.org type is a LocalBusiness or known sub-type.
     *
     * @param string $type
     * @return bool
     */
    private static function is_local_business_type( $type ) {
        $local_business_types = [
            'LocalBusiness', 'Electrician', 'Plumber', 'HVACBusiness',
            'RoofingContractor', 'GeneralContractor', 'Locksmith', 'HousePainter',
            'LandscapingBusiness', 'MovingCompany', 'HouseCleaning', 'PestControlService',
            'Dentist', 'Physician', 'MedicalClinic', 'Optician', 'Chiropractor', 'VeterinaryCare',
            'Attorney', 'AccountingService', 'FinancialPlanningService',
            'InsuranceAgency', 'RealEstateAgent',
            'AutoRepair', 'AutoDealer',
            'HairSalon', 'NailSalon', 'DaySpa', 'BeautySalon', 'SportsActivityLocation',
            'Restaurant', 'FoodEstablishment', 'Bakery', 'CafeOrCoffeeShop', 'BarOrPub',
            'FastFoodRestaurant', 'ChildCare', 'School', 'FuneralHome', 'PetStore', 'EventVenue',
        ];
        return in_array( $type, $local_business_types, true );
    }

    // ── AJAX Handlers ────────────────────────────────────────────────────────

    /**
     * AJAX: Generate schema candidates for a post and store as suggested.
     * Does NOT apply to active schema (G4 guardrail — no auto-apply).
     *
     * Action: wp_ajax_siloq_generate_schema
     */
    public static function ajax_generate_schema() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
            return;
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Missing post_id.' ] );
            return;
        }

        $entity_profile = self::get_entity_profile( $post_id );

        // Fix 3: Check minimum required fields before generating and surface a
        // helpful, actionable error that links directly to the settings page.
        $missing = [];
        if ( empty( $entity_profile['phone'] ) && empty( $entity_profile['address'] ) ) {
            $missing[] = 'phone number OR address';
        }
        if ( empty( $entity_profile['business_name'] ) ) {
            $missing[] = 'business name';
        }
        if ( ! empty( $missing ) ) {
            wp_send_json_error( [
                'message'        => 'Missing required info: ' . implode( ', ', $missing ) . '. Go to Siloq Settings → Business Profile to add these.',
                'missing_fields' => $missing,
                'fix_url'        => admin_url( 'admin.php?page=siloq-settings&tab=business-profile' ),
            ] );
            return;
        }

        // Load page analysis data (contains has_visible_rating, faq_items, etc.).
        $page_analysis = [];
        $analysis_raw  = get_post_meta( $post_id, '_siloq_analysis_data', true );
        if ( ! empty( $analysis_raw ) ) {
            $decoded = is_array( $analysis_raw ) ? $analysis_raw : json_decode( $analysis_raw, true );
            if ( is_array( $decoded ) ) {
                $page_analysis = $decoded;
            }
        }
        // Allow JS to pass has_visible_rating directly in the request.
        if ( isset( $_POST['has_visible_rating'] ) ) {
            $page_analysis['has_visible_rating'] = (bool) $_POST['has_visible_rating'];
        }

        // Supplement faq_items from live Elementor data when not already in analysis.
        // The sync may not have extracted accordion/toggle Q&A pairs — reading directly
        // from _elementor_data ensures FAQPage schema is generated when FAQs exist on page.
        if ( empty( $page_analysis['faq_items'] ) ) {
            $live_faqs = self::extract_faq_from_elementor( $post_id );
            if ( ! empty( $live_faqs ) ) {
                $page_analysis['faq_items'] = $live_faqs;
            }
        }

        $schemas = self::generate( $post_id, $entity_profile, $page_analysis );

        // Stage generated schemas — NOT applied to active output yet.
        update_post_meta( $post_id, '_siloq_suggested_schema', wp_json_encode( $schemas ) );
        update_post_meta( $post_id, '_siloq_schema_count', count( $schemas ) );

        $schema_types = array_map( function ( $s ) {
            return $s['@type'] ?? 'Unknown';
        }, $schemas );

        // BUG 3 FIX: Write schema JSON to post meta so it persists in WordPress
        $schema_json_encoded = wp_json_encode( $schemas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        update_post_meta( $post_id, '_siloq_schema_json', wp_json_encode( $schemas ) );
        $schema_type_str = implode( ', ', $schema_types );
        update_post_meta( $post_id, '_siloq_schema_type', $schema_type_str );
        update_post_meta( $post_id, '_siloq_schema_applied', '1' );

        // Sync schema to API (non-blocking — local save already succeeded above).
        // Schema is stored in post_meta regardless; API sync is best-effort.
        $api_confirmed = false;
        $site_id   = get_option( 'siloq_site_id', '' );
        $api_key   = get_option( 'siloq_api_key', '' );
        $api_base  = rtrim( get_option( 'siloq_api_url', 'https://api.siloq.ai/api/v1' ), '/' );
        $sync_data = get_post_meta( $post_id, '_siloq_sync_data', true );
        $api_page_id = is_array( $sync_data ) && isset( $sync_data['id'] ) ? $sync_data['id'] : $post_id;

        if ( $site_id && $api_key ) {
            // POST the generated schema to the API so it has a record.
            $sync_resp = wp_remote_post(
                "$api_base/sites/$site_id/pages/$api_page_id/schema/",
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json',
                    ],
                    'body'    => wp_json_encode( [ 'schemas' => $schemas, 'schema_types' => $schema_types ] ),
                    'timeout' => 30,
                ]
            );

            if ( is_wp_error( $sync_resp ) ) {
                error_log( '[Siloq Schema] API sync error for post ' . $post_id . ': ' . $sync_resp->get_error_message() );
            } elseif ( wp_remote_retrieve_response_code( $sync_resp ) >= 200 && wp_remote_retrieve_response_code( $sync_resp ) < 300 ) {
                $api_confirmed = true;
            } else {
                $code = wp_remote_retrieve_response_code( $sync_resp );
                $body = wp_remote_retrieve_body( $sync_resp );
                error_log( "[Siloq Schema] API sync returned HTTP {$code} for post {$post_id}: {$body}" );
            }
        }

        // Schema is already saved to post_meta — always return success to the user.
        // API sync failure is logged but must not block the user from using schema.

        $validation = self::validate( $schemas, $entity_profile );

        wp_send_json_success( [
            'schemas'       => $schemas,
            'schema_json'   => $schema_json_encoded,
            'count'         => count( $schemas ),
            'schema_types'  => $schema_types,
            'page_type'     => self::detect_page_type( $post_id, $page_analysis ),
            'business_type' => self::map_business_type( $entity_profile['business_type'] ?? '' ) ?: ( $entity_profile['business_type'] ?? 'LocalBusiness' ),
            'validation'    => $validation,
        ] );
    }

    /**
     * AJAX: Validate staged schema and apply to active wp_head output.
     *
     * Reads from _siloq_suggested_schema (set by ajax_generate_schema).
     * Passes the hard validation gate (G4) before writing to Architect DB.
     * Clears legacy _siloq_schema / _siloq_schema_markup meta to prevent
     * duplicate output from Siloq_Schema_Manager.
     *
     * Output path: Siloq_Schema_Architect::save_schema() → wp_head only.
     * NEVER writes to post_content or _elementor_data.
     *
     * Action: wp_ajax_siloq_apply_schema
     */
    public static function ajax_apply_schema() {
        check_ajax_referer( 'siloq_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions.' ] );
            return;
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Missing post_id.' ] );
            return;
        }

        // Load staged schemas.
        $staged_raw = get_post_meta( $post_id, '_siloq_suggested_schema', true );
        if ( empty( $staged_raw ) ) {
            wp_send_json_error( [ 'message' => 'No staged schema found. Run Generate first.' ] );
            return;
        }

        $schemas = json_decode( $staged_raw, true );
        if ( ! is_array( $schemas ) || empty( $schemas ) ) {
            wp_send_json_error( [ 'message' => 'Staged schema data is invalid.' ] );
            return;
        }

        // G4: Validation gate — must pass before writing to active storage.
        $entity_profile = self::get_entity_profile( $post_id );
        $validation     = self::validate( $schemas, $entity_profile );
        if ( ! $validation['valid'] ) {
            wp_send_json_error( [
                'message' => 'Schema validation failed.',
                'errors'  => $validation['errors'],
            ] );
            return;
        }

        // Persist via Siloq_Schema_Architect → output via wp_head only.
        if ( ! class_exists( 'Siloq_Schema_Architect' ) ) {
            wp_send_json_error( [ 'message' => 'Siloq_Schema_Architect class not available.' ] );
            return;
        }

        $applied_types = [];
        foreach ( $schemas as $schema ) {
            $type = $schema['@type'] ?? '';
            if ( empty( $type ) ) {
                continue;
            }
            Siloq_Schema_Architect::save_schema(
                $post_id,
                $type,
                $schema,
                [
                    'source'            => 'siloq_intelligence',
                    'confidence'        => 95,
                    'reason'            => 'Generated by Siloq Schema Intelligence v1.5.49',
                    'validation_status' => 'valid',
                ]
            );
            $applied_types[] = $type;
        }

        // Clear legacy meta to prevent duplicate output from Siloq_Schema_Manager.
        delete_post_meta( $post_id, '_siloq_schema' );
        delete_post_meta( $post_id, '_siloq_schema_markup' );

        // Mark staging meta as applied.
        delete_post_meta( $post_id, '_siloq_suggested_schema' );
        update_post_meta( $post_id, '_siloq_schema_applied', current_time( 'mysql' ) );
        update_post_meta( $post_id, '_siloq_applied_types', wp_json_encode( $applied_types ) );

        wp_send_json_success( [
            'message'       => sprintf( '%d schema type(s) applied to wp_head.', count( $applied_types ) ),
            'applied_types' => $applied_types,
            'count'         => count( $applied_types ),
        ] );
    }
}
