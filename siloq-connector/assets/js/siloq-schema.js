/**
 * Siloq Schema Intelligence — Shared UI Module
 *
 * Handles the Generate → Preview → Apply workflow for both the Admin Metabox
 * and the Elementor Panel surfaces. Surface is detected from siloqSchema.surface.
 *
 * Workflow (enforced — G4 guardrail):
 *   1. User clicks Generate → POST siloq_generate_schema → display preview.
 *   2. User reviews JSON-LD + schema types in the preview panel.
 *   3. User clicks Apply  → POST siloq_apply_schema → show success/errors.
 *   Auto-apply is NEVER triggered.
 *
 * @since 1.5.49
 */

/* global jQuery, siloqSchema */

(function ($) {
    'use strict';

    // ── Config ────────────────────────────────────────────────────────────────

    const cfg = window.siloqSchema || {};
    const AJAX_URL  = cfg.ajaxUrl || '';
    const NONCE     = cfg.nonce   || '';
    const POST_ID   = parseInt( cfg.postId, 10 ) || 0;
    const SURFACE   = cfg.surface || 'metabox'; // 'metabox' | 'elementor'
    const STR       = cfg.strings || {};

    // ── DOM ID maps (metabox vs Elementor) ────────────────────────────────────

    const IDS = {
        metabox: {
            surface:     '#siloq-schema-metabox',
            generateBtn: '#siloq-generate-schema-btn',
            applyBtn:    '#siloq-apply-schema-btn',
            spinner:     '#siloq-schema-spinner',
            errors:      '#siloq-schema-errors',
            preview:     '#siloq-schema-preview',
            pageType:    '#siloq-schema-page-type',
            bizType:     '#siloq-schema-business-type',
            typesList:   '#siloq-schema-types-list',
            jsonPre:     '#siloq-schema-json-preview',
            warnings:    '#siloq-schema-validation-warnings',
        },
        elementor: {
            surface:     '#siloq-schema-el-panel',
            generateBtn: '#siloq-generate-schema-btn-el',
            applyBtn:    '#siloq-apply-schema-btn-el',
            spinner:     '#siloq-schema-spinner-el',
            errors:      '#siloq-schema-errors-el',
            preview:     '#siloq-schema-preview-el',
            pageType:    '#siloq-schema-page-type-el',
            bizType:     '#siloq-schema-business-type-el',
            typesList:   '#siloq-schema-types-list-el',
            jsonPre:     '#siloq-schema-json-preview-el',
            warnings:    '#siloq-schema-validation-warnings-el',
        },
    };

    // ── State ─────────────────────────────────────────────────────────────────

    let state = {
        isGenerating : false,
        isApplying   : false,
        lastResponse : null,   // full AJAX success payload from generate
        postId       : POST_ID,
    };

    // ── Helpers ───────────────────────────────────────────────────────────────

    function sel( key ) {
        return IDS[ SURFACE ] && IDS[ SURFACE ][ key ] ? IDS[ SURFACE ][ key ] : '';
    }

    function $el( key ) {
        return $( sel( key ) );
    }

    function showSpinner( label ) {
        $el( 'spinner' ).find( '.siloq-schema-spinner-label' ).text( label || STR.generating );
        $el( 'spinner' ).show();
    }

    function hideSpinner() {
        $el( 'spinner' ).hide();
    }

    function showErrors( errors ) {
        const $err = $el( 'errors' );
        if ( ! errors || ! errors.length ) {
            $err.hide().empty();
            return;
        }
        const html = '<ul class="siloq-schema-error-list">'
            + errors.map( e => '<li>' + escHtml( e ) + '</li>' ).join( '' )
            + '</ul>';
        $err.html( html ).show();
    }

    function clearErrors() {
        $el( 'errors' ).hide().empty();
    }

    function escHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#039;' );
    }

    /**
     * Detect whether a visible rating element exists in the current DOM.
     * Used to pass has_visible_rating to the generate AJAX call (G2 guardrail).
     * Checks for common rating widget patterns.
     *
     * @returns {boolean}
     */
    function detectVisibleRating() {
        const selectors = [
            '[class*="rating"]',
            '[class*="review-score"]',
            '[class*="star-rating"]',
            '[itemprop="ratingValue"]',
            '[itemprop="aggregateRating"]',
            '.wprm-recipe-rating',
            '.review-stars',
            '.siloq-rating',
        ];
        for ( const s of selectors ) {
            const el = document.querySelector( s );
            if ( el ) {
                // Must be visible in the DOM (not display:none / visibility:hidden).
                const style = window.getComputedStyle( el );
                if ( style.display !== 'none' && style.visibility !== 'hidden' ) {
                    return true;
                }
            }
        }
        return false;
    }

    // ── Generate ──────────────────────────────────────────────────────────────

    function handleGenerate() {
        if ( state.isGenerating || state.isApplying ) return;
        if ( ! POST_ID ) {
            showErrors( [ 'No post ID available. Please save the post first.' ] );
            return;
        }

        state.isGenerating = true;
        clearErrors();
        $el( 'preview' ).hide();
        $el( 'generateBtn' ).prop( 'disabled', true );
        showSpinner( STR.generating );

        const hasVisibleRating = detectVisibleRating() ? 1 : 0;

        $.post( AJAX_URL, {
            action             : 'siloq_generate_schema',
            nonce              : NONCE,
            post_id            : POST_ID,
            has_visible_rating : hasVisibleRating,
        } )
        .done( function ( response ) {
            if ( response.success ) {
                state.lastResponse = response.data;
                renderPreview( response.data );
            } else {
                const errData = response.data || {};
                const msg     = errData.message || 'Generate failed.';
                // Fix 3: if the server returned a fix_url, render an inline link.
                if ( errData.fix_url ) {
                    $el( 'errors' )
                        .html( '⚠️ ' + escHtml( msg ) + ' <a href="' + escHtml( errData.fix_url ) + '" target="_blank">Fix now →</a>' )
                        .show();
                } else {
                    showErrors( [ msg ] );
                }
            }
        } )
        .fail( function () {
            showErrors( [ 'Network error during schema generation.' ] );
        } )
        .always( function () {
            state.isGenerating = false;
            hideSpinner();
            $el( 'generateBtn' ).prop( 'disabled', false );
        } );
    }

    // ── Render Preview ────────────────────────────────────────────────────────

    function renderPreview( data ) {
        // Page/business type metadata.
        $el( 'pageType' ).text( data.page_type || '—' );
        $el( 'bizType'  ).text( data.business_type || '—' );

        // Schema type chips.
        const types = data.schema_types || [];
        const chips = types.length
            ? types.map( t => '<span class="siloq-schema-type-chip">' + escHtml( t ) + '</span>' ).join( '' )
            : '<em>' + escHtml( STR.noSchema ) + '</em>';
        $el( 'typesList' ).html( chips );

        // JSON-LD preview (pretty-printed).
        $el( 'jsonPre' ).text( data.schema_json || '{}' );

        // Validation warnings (non-blocking — apply is still allowed).
        const validation = data.validation || {};
        if ( validation.errors && validation.errors.length ) {
            const warnHtml = '<strong>⚠ Validation warnings:</strong><ul>'
                + validation.errors.map( e => '<li>' + escHtml( e ) + '</li>' ).join( '' )
                + '</ul>';
            $el( 'warnings' ).html( warnHtml ).show();
        } else {
            $el( 'warnings' ).hide().empty();
        }

        // Show / hide the Apply button based on whether we have any schemas.
        if ( types.length ) {
            $el( 'applyBtn' ).show();
        } else {
            $el( 'applyBtn' ).hide();
        }

        $el( 'preview' ).show();
    }

    // ── Apply ─────────────────────────────────────────────────────────────────

    function handleApply() {
        if ( state.isGenerating || state.isApplying ) return;
        if ( ! POST_ID ) {
            showErrors( [ 'No post ID available.' ] );
            return;
        }
        if ( ! state.lastResponse || ! state.lastResponse.schemas ) {
            showErrors( [ 'No generated schema to apply. Click Generate first.' ] );
            return;
        }

        state.isApplying = true;
        clearErrors();
        $el( 'applyBtn' ).prop( 'disabled', true );
        showSpinner( STR.applying );

        $.post( AJAX_URL, {
            action  : 'siloq_apply_schema',
            nonce   : NONCE,
            post_id : POST_ID,
        } )
        .done( function ( response ) {
            if ( response.success ) {
                onApplySuccess( response.data );
            } else {
                const errors = ( response.data && response.data.errors )
                    ? response.data.errors
                    : [ ( response.data && response.data.message ) || STR.applyFail ];
                showErrors( errors );
            }
        } )
        .fail( function () {
            showErrors( [ 'Network error during schema apply.' ] );
        } )
        .always( function () {
            state.isApplying = false;
            hideSpinner();
            $el( 'applyBtn' ).prop( 'disabled', false );
        } );
    }

    function onApplySuccess( data ) {
        const types   = data.applied_types || [];
        const message = data.message || STR.applySuccess;

        // Update the Apply button to a success state (don't remove — user can re-apply).
        $el( 'applyBtn' )
            .text( '✅ ' + escHtml( message ) )
            .addClass( 'siloq-schema-btn--success' );

        // Update the status badge at the top of the surface.
        const badgeHtml = '<span class="siloq-schema-badge siloq-schema-badge--applied">✅ Applied</span>'
            + '<div class="siloq-schema-applied-types">' + escHtml( types.join( ', ' ) ) + '</div>';

        $el( 'surface' ).find( '.siloq-schema-status' ).html( badgeHtml );

        // Reset apply button text after 3 s so user knows they can re-generate.
        setTimeout( function () {
            $el( 'applyBtn' )
                .text( '✅ Apply Schema' )
                .removeClass( 'siloq-schema-btn--success' );
        }, 3000 );
    }

    // ── Elementor Panel Toggle ────────────────────────────────────────────────

    function initElementorPanel() {
        const $trigger = $( '#siloq-schema-el-trigger' );
        const $panel   = $( '#siloq-schema-el-panel' );
        const $close   = $( '#siloq-schema-el-close' );

        $trigger.on( 'click keypress', function ( e ) {
            if ( e.type === 'keypress' && e.which !== 13 ) return;
            const isOpen = $panel.hasClass( 'is-open' );
            $panel.toggleClass( 'is-open', ! isOpen );
            $panel.attr( 'aria-hidden', isOpen ? 'true' : 'false' );
            $trigger.attr( 'aria-expanded', isOpen ? 'false' : 'true' );
        } );

        $close.on( 'click', function () {
            $panel.removeClass( 'is-open' ).attr( 'aria-hidden', 'true' );
            $trigger.attr( 'aria-expanded', 'false' );
        } );
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    $( document ).ready( function () {
        if ( ! AJAX_URL || ! NONCE ) {
            return; // Guard: not on a supported editor page.
        }

        // Bind Generate button (both surfaces use the same handler).
        $( document ).on( 'click', sel( 'generateBtn' ), handleGenerate );

        // Bind Apply button.
        $( document ).on( 'click', sel( 'applyBtn' ), handleApply );

        // Elementor-specific panel toggle.
        if ( SURFACE === 'elementor' ) {
            initElementorPanel();
        }
    } );

}( jQuery ));
