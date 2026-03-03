<?php
/**
 * Siloq FAQ Manager
 *
 * Handles FAQ tagging (auto vs. needs_input classification) and the
 * wp_ajax_siloq_apply_faq_item AJAX endpoint that stores user-confirmed
 * FAQs in post meta.
 *
 * The tagging helper siloq_tag_faq() is a global function so it can be
 * called from Siloq_AI_Content_Generator and any other class that builds
 * FAQ output.
 *
 * @package Siloq_Connector
 * @since   1.5.50
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =========================================================================
// Global helper — usable by all classes without instantiation
// =========================================================================

if ( ! function_exists( 'siloq_tag_faq' ) ) {
    /**
     * Classify a FAQ question as 'auto' (can be answered generically) or
     * 'needs_input' (requires the business owner's specific answer).
     *
     * @param  string $question The FAQ question text.
     * @return string           'auto' | 'needs_input'
     */
    function siloq_tag_faq( $question ) {
        $q = strtolower( $question );

        $needs_input_patterns = array(
            'price',
            'cost',
            'how much',
            'charge',
            'fee',
            'financing',
            'payment',
            'warranty',
            'guarantee',
            'how long',
            'area',
            'serve',
            'cover',
            'location',
            'available in',
            'emergency',
            'weekend',
            'after hours',
            'same day',
            'licensed',
            'certified',
            'insured',
            'bonded',
        );

        foreach ( $needs_input_patterns as $pattern ) {
            if ( strpos( $q, $pattern ) !== false ) {
                return 'needs_input';
            }
        }

        return 'auto';
    }
}

// =========================================================================
// Class
// =========================================================================

class Siloq_FAQ_Manager {

    /**
     * Register AJAX hooks.
     */
    public static function init() {
        add_action( 'wp_ajax_siloq_apply_faq_item', array( __CLASS__, 'ajax_apply_faq_item' ) );
    }

    // -----------------------------------------------------------------------
    // AJAX: apply / confirm a single FAQ item
    // -----------------------------------------------------------------------

    /**
     * AJAX handler for wp_ajax_siloq_apply_faq_item.
     *
     * Stores the confirmed FAQ in:
     *   _siloq_confirmed_faqs  — append-only audit log
     *   _siloq_faq_items       — working list used when rendering schema / analysis
     */
    public static function ajax_apply_faq_item() {
        check_ajax_referer( 'siloq_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
            return;
        }

        $post_id   = intval( $_POST['post_id'] ?? 0 );
        $question  = sanitize_text_field( wp_unslash( $_POST['question']  ?? '' ) );
        $answer    = sanitize_textarea_field( wp_unslash( $_POST['answer'] ?? '' ) );
        $confirmed = ! empty( $_POST['confirmed'] );

        if ( ! $post_id || ! $question || ! $answer ) {
            wp_send_json_error( array( 'message' => 'Missing required fields' ) );
            return;
        }

        // --- Append-only confirmed log -------------------------------------
        $confirmed_faqs   = json_decode( get_post_meta( $post_id, '_siloq_confirmed_faqs', true ), true );
        $confirmed_faqs   = is_array( $confirmed_faqs ) ? $confirmed_faqs : array();
        $confirmed_faqs[] = array(
            'question'     => $question,
            'answer'       => $answer,
            'confirmed_at' => current_time( 'mysql' ),
        );
        update_post_meta( $post_id, '_siloq_confirmed_faqs', wp_json_encode( $confirmed_faqs ) );

        // --- Working FAQ list (includes confirmed items) -------------------
        $faq_items   = json_decode( get_post_meta( $post_id, '_siloq_faq_items', true ), true );
        $faq_items   = is_array( $faq_items ) ? $faq_items : array();
        $faq_items[] = array(
            'question' => $question,
            'answer'   => $answer,
            'type'     => 'confirmed',
        );
        update_post_meta( $post_id, '_siloq_faq_items', wp_json_encode( $faq_items ) );

        wp_send_json_success( array( 'message' => 'FAQ saved' ) );
    }

    // -----------------------------------------------------------------------
    // Helper: tag a list of FAQ objects
    // -----------------------------------------------------------------------

    /**
     * Given an array of FAQ arrays with at least a 'question' key, add a
     * 'type' key ('auto' | 'needs_input') to each.
     *
     * @param  array $faqs Array of ['question' => '...', 'answer' => '...']
     * @return array       Same array with 'type' key added.
     */
    public static function tag_faqs( array $faqs ) {
        foreach ( $faqs as &$faq ) {
            if ( empty( $faq['type'] ) ) {
                $faq['type'] = siloq_tag_faq( $faq['question'] ?? '' );
            }
        }
        unset( $faq );
        return $faqs;
    }
}
