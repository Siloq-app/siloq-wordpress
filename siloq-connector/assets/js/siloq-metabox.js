/**
 * Siloq Admin Meta Box — JavaScript
 *
 * Handles AJAX actions for the "⚡ Siloq SEO" sidebar panel.
 * Depends on: jQuery, siloqAdminData (ajaxUrl, nonce, postId)
 *
 * @since 1.5.47
 */
(function ($) {
    'use strict';

    if (typeof siloqAdminData === 'undefined') {
        return;
    }

    var ajaxUrl = siloqAdminData.ajaxUrl;
    var nonce   = siloqAdminData.nonce;
    var postId  = siloqAdminData.postId;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Spinner HTML.
     * @returns {string}
     */
    function spinner() {
        return '<span class="siloq-spinner"></span>';
    }

    /**
     * Set a button into loading state.
     *
     * @param {jQuery} $btn
     */
    function setLoading($btn) {
        $btn.prop('disabled', true)
            .data('original-text', $btn.html())
            .append(spinner());
    }

    /**
     * Restore a button from loading state.
     *
     * @param {jQuery} $btn
     */
    function clearLoading($btn) {
        $btn.prop('disabled', false)
            .html($btn.data('original-text') || $btn.html().replace(/<span class="siloq-spinner"><\/span>/, ''));
    }

    /**
     * Show a success or error message beneath a button.
     *
     * @param {jQuery} $msgEl  The .siloq-mb-btn-msg element
     * @param {string} text
     * @param {string} type    'success' | 'error'
     */
    function showMsg($msgEl, text, type) {
        $msgEl
            .text(text)
            .removeClass('success error')
            .addClass(type)
            .show();

        // Auto-hide after 4 s
        setTimeout(function () {
            $msgEl.fadeOut(300);
        }, 4000);
    }

    /**
     * Refresh the meta box inner HTML via AJAX after an analysis.
     */
    function refreshMetabox() {
        $.post(ajaxUrl, {
            action:  'siloq_metabox_refresh',
            nonce:   nonce,
            post_id: postId
        }, function (resp) {
            if (resp && resp.success && resp.data && resp.data.html) {
                $('#siloq-metabox-inner').html(resp.data.html);
                bindEvents(); // re-bind after DOM replacement
            }
        });
    }

    // -----------------------------------------------------------------------
    // Event bindings (called on init and after meta box refresh)
    // -----------------------------------------------------------------------

    function bindEvents() {
        var $inner = $('#siloq-metabox-inner');

        // -- Analyze Page --
        $inner.off('click', '.siloq-btn-analyze').on('click', '.siloq-btn-analyze', function () {
            var $btn = $(this);
            var $msg = $inner.find('.siloq-msg-analyze');

            setLoading($btn);
            $msg.hide();

            $.post(ajaxUrl, {
                action:  'siloq_analyze_page',
                nonce:   nonce,
                post_id: postId
            }, function (resp) {
                clearLoading($btn);
                if (resp && resp.success) {
                    showMsg($msg, resp.data && resp.data.message ? resp.data.message : 'Analysis complete!', 'success');
                    // Reload meta box to show new score & recommendations
                    setTimeout(refreshMetabox, 800);
                } else {
                    var errMsg = resp && resp.data && resp.data.message
                        ? resp.data.message
                        : 'Analysis failed. Please try again.';
                    showMsg($msg, errMsg, 'error');
                }
            }).fail(function () {
                clearLoading($btn);
                showMsg($msg, 'Request failed. Check your connection.', 'error');
            });
        });

        // -- Apply Title & Meta --
        $inner.off('click', '.siloq-btn-apply-meta').on('click', '.siloq-btn-apply-meta', function () {
            var $btn = $(this);
            var $msg = $inner.find('.siloq-msg-apply-meta');

            setLoading($btn);
            $msg.hide();

            $.post(ajaxUrl, {
                action:  'siloq_apply_meta',
                nonce:   nonce,
                post_id: postId
            }, function (resp) {
                clearLoading($btn);
                if (resp && resp.success) {
                    showMsg($msg, resp.data && resp.data.message ? resp.data.message : 'Applied!', 'success');
                } else {
                    var errMsg = resp && resp.data && resp.data.message
                        ? resp.data.message
                        : 'Could not apply meta. Run an analysis first.';
                    showMsg($msg, errMsg, 'error');
                }
            }).fail(function () {
                clearLoading($btn);
                showMsg($msg, 'Request failed. Check your connection.', 'error');
            });
        });

        // -- Apply Schema --
        $inner.off('click', '.siloq-btn-apply-schema').on('click', '.siloq-btn-apply-schema', function () {
            var $btn = $(this);
            var $msg = $inner.find('.siloq-msg-apply-schema');

            setLoading($btn);
            $msg.hide();

            $.post(ajaxUrl, {
                action:  'siloq_apply_schema',
                nonce:   nonce,
                post_id: postId
            }, function (resp) {
                clearLoading($btn);
                if (resp && resp.success) {
                    showMsg($msg, resp.data && resp.data.message ? resp.data.message : 'Schema applied!', 'success');
                } else {
                    var errMsg = resp && resp.data && resp.data.message
                        ? resp.data.message
                        : 'Could not apply schema. Run an analysis first.';
                    showMsg($msg, errMsg, 'error');
                }
            }).fail(function () {
                clearLoading($btn);
                showMsg($msg, 'Request failed. Check your connection.', 'error');
            });
        });

        // -- Create Supporting Draft --
        $inner.off('click', '.siloq-btn-create-draft').on('click', '.siloq-btn-create-draft', function () {
            var $btn = $(this);
            var $msg = $inner.find('.siloq-msg-create-draft');

            setLoading($btn);
            $msg.hide();

            $.post(ajaxUrl, {
                action:  'siloq_create_supporting_draft',
                nonce:   nonce,
                post_id: postId
            }, function (resp) {
                clearLoading($btn);
                if (resp && resp.success) {
                    var editUrl = resp.data && resp.data.edit_url ? resp.data.edit_url : null;
                    var msg     = resp.data && resp.data.message  ? resp.data.message  : 'Draft created!';

                    if (editUrl) {
                        msg += ' <a href="' + editUrl + '" target="_blank">Edit draft →</a>';
                    }

                    $msg.html(msg)
                        .removeClass('success error')
                        .addClass('success')
                        .show();

                    // Refresh meta box to reflect updated count
                    setTimeout(refreshMetabox, 1200);
                } else {
                    var errMsg = resp && resp.data && resp.data.message
                        ? resp.data.message
                        : 'Could not create draft.';
                    showMsg($msg, errMsg, 'error');
                }
            }).fail(function () {
                clearLoading($btn);
                showMsg($msg, 'Request failed. Check your connection.', 'error');
            });
        });
    }

    // -----------------------------------------------------------------------
    // Init
    // -----------------------------------------------------------------------

    $(document).ready(function () {
        bindEvents();
    });

}(jQuery));
