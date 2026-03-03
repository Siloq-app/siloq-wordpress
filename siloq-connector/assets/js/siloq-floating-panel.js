/**
 * Siloq Floating Panel — shared JS component
 *
 * All builder integrations (Elementor, Divi, Beaver, WPBakery, Bricks,
 * Oxygen, Cornerstone) call:
 *
 *   SiloqFloatingPanel.init(postId, ajaxUrl, nonce);
 *
 * The Gutenberg sidebar uses SiloqFloatingPanel.analyze() / individual
 * action methods directly via the siloqGB localized object.
 *
 * @package Siloq_Connector
 * @since   1.5.50
 */

/* global jQuery, window */

var SiloqFloatingPanel = (function ($) {
    'use strict';

    // -----------------------------------------------------------------------
    // Private state
    // -----------------------------------------------------------------------

    var _postId   = 0;
    var _ajaxUrl  = '';
    var _nonce    = '';
    var _lastData = null; // last analysis result

    // DOM references (populated after inject())
    var $trigger, $panel, $status, $dot;
    var _injected = false;

    // -----------------------------------------------------------------------
    // Init
    // -----------------------------------------------------------------------

    /**
     * Called by each builder integration after the DOM is ready.
     *
     * @param {number} postId
     * @param {string} ajaxUrl
     * @param {string} nonce
     */
    function init(postId, ajaxUrl, nonce) {
        _postId  = parseInt(postId, 10) || 0;
        _ajaxUrl = ajaxUrl || '';
        _nonce   = nonce   || '';

        if (!_injected) {
            _inject();
        }

        _bindEvents();
        _checkRecommendationsDot();
    }

    // -----------------------------------------------------------------------
    // DOM injection
    // -----------------------------------------------------------------------

    function _inject() {
        if (_injected) return;
        _injected = true;

        var html = [
            '<div id="siloq-fp-trigger">',
                '&#9889; Siloq',
                '<span class="siloq-fp-dot"></span>',
            '</div>',

            '<div id="siloq-fp-panel">',
                '<div class="siloq-fp-header">',
                    '<span>&#9889; Siloq SEO</span>',
                    '<button class="siloq-fp-close" aria-label="Close">&#x2715;</button>',
                '</div>',

                '<div class="siloq-fp-tabs">',
                    '<button class="siloq-fp-tab active" data-tab="recommendations">Recommendations</button>',
                    '<button class="siloq-fp-tab" data-tab="content">Content</button>',
                    '<button class="siloq-fp-tab" data-tab="structure">Structure</button>',
                '</div>',

                '<div class="siloq-fp-body">',

                    // Recommendations tab
                    '<div class="siloq-fp-tab-content active" data-tab="recommendations">',
                        '<div id="siloq-fp-score"></div>',
                        '<div id="siloq-fp-recs"></div>',
                        '<button id="siloq-fp-analyze" class="siloq-fp-btn-primary">&#128269; Analyze Page</button>',
                        '<div id="siloq-fp-apply-row" style="display:none">',
                            '<button id="siloq-fp-apply-meta" class="siloq-fp-btn">&#9989; Apply Title &amp; Meta</button>',
                            '<button id="siloq-fp-apply-schema" class="siloq-fp-btn">&#128203; Apply Schema</button>',
                        '</div>',
                    '</div>',

                    // Content tab
                    '<div class="siloq-fp-tab-content" data-tab="content">',
                        '<p class="siloq-fp-hint">Generate SEO content for this page.</p>',
                        '<label><input type="radio" name="siloq_ct" value="faq" checked> FAQ Section</label><br>',
                        '<label><input type="radio" name="siloq_ct" value="services"> Services List</label><br>',
                        '<label><input type="radio" name="siloq_ct" value="about"> About / Trust</label>',
                        '<button id="siloq-fp-generate" class="siloq-fp-btn-primary" style="margin-top:12px">&#10024; Generate</button>',
                        '<div id="siloq-fp-generated" style="display:none">',
                            '<div id="siloq-fp-faq-list"></div>',
                            '<div id="siloq-fp-raw-output" style="display:none">',
                                '<textarea id="siloq-fp-content-out" rows="8" readonly></textarea>',
                                '<button id="siloq-fp-copy" class="siloq-fp-btn">&#128203; Copy to Clipboard</button>',
                            '</div>',
                        '</div>',
                    '</div>',

                    // Structure tab
                    '<div class="siloq-fp-tab-content" data-tab="structure">',
                        '<p class="siloq-fp-section-label">Supporting Pages</p>',
                        '<div id="siloq-fp-structure-list"></div>',
                        '<button id="siloq-fp-create-draft" class="siloq-fp-btn" style="margin-top:10px">&#10133; Create Draft</button>',
                    '</div>',

                '</div>', // .siloq-fp-body

                '<div id="siloq-fp-status"></div>',
            '</div>' // #siloq-fp-panel
        ].join('');

        $('body').append(html);

        // Cache DOM refs
        $trigger = $('#siloq-fp-trigger');
        $panel   = $('#siloq-fp-panel');
        $status  = $('#siloq-fp-status');
        $dot     = $trigger.find('.siloq-fp-dot');
    }

    // -----------------------------------------------------------------------
    // Event binding
    // -----------------------------------------------------------------------

    function _bindEvents() {
        // Open/close
        $(document).on('click', '#siloq-fp-trigger', _open);
        $(document).on('click', '.siloq-fp-close',   _close);

        // Click outside panel to close
        $(document).on('click', function (e) {
            if (
                $panel && $panel.hasClass('open') &&
                !$(e.target).closest('#siloq-fp-panel').length &&
                !$(e.target).closest('#siloq-fp-trigger').length
            ) {
                _close();
            }
        });

        // ESC to close
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $panel && $panel.hasClass('open')) {
                _close();
            }
        });

        // Tab switching
        $(document).on('click', '.siloq-fp-tab', function () {
            var tab = $(this).data('tab');
            $('.siloq-fp-tab').removeClass('active');
            $(this).addClass('active');
            $('.siloq-fp-tab-content').removeClass('active');
            $('.siloq-fp-tab-content[data-tab="' + tab + '"]').addClass('active');

            if (tab === 'structure') {
                _loadStructure();
            }
        });

        // Analyze
        $(document).on('click', '#siloq-fp-analyze', analyze);

        // Apply meta
        $(document).on('click', '#siloq-fp-apply-meta', _applyMeta);

        // Apply schema
        $(document).on('click', '#siloq-fp-apply-schema', _applySchema);

        // Generate content
        $(document).on('click', '#siloq-fp-generate', _generate);

        // Copy to clipboard
        $(document).on('click', '#siloq-fp-copy', function () {
            var text = $('#siloq-fp-content-out').val();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    _setStatus('Copied!', 'success');
                });
            } else {
                $('#siloq-fp-content-out').select();
                document.execCommand('copy');
                _setStatus('Copied!', 'success');
            }
        });

        // Create draft (structure tab)
        $(document).on('click', '#siloq-fp-create-draft', _createDraft);

        // FAQ: enable Apply button when needs_input textarea is edited
        $(document).on('input', '.siloq-fp-faq-edit', function () {
            var idx = $(this).data('index');
            var $btn = $('.siloq-fp-apply-faq[data-index="' + idx + '"]');
            $btn.prop('disabled', $(this).val().trim() === '');
        });

        // FAQ: apply individual FAQ item
        $(document).on('click', '.siloq-fp-apply-faq', function () {
            var idx      = parseInt($(this).data('index'), 10);
            var $item    = $('.siloq-fp-faq-item[data-index="' + idx + '"]');
            var question = $item.find('.siloq-fp-question strong').text().replace(/^Q:\s*/, '');
            var $edit    = $item.find('.siloq-fp-faq-edit');
            var answer   = $edit.length ? $edit.val().trim() : $item.find('.siloq-fp-answer').text().trim();

            _ajax({ action: 'siloq_apply_faq_item', post_id: _postId, question: question, answer: answer, confirmed: 1 }, function (res) {
                if (res.success) {
                    $item.css('opacity', '0.5');
                    $item.find('.siloq-fp-apply-faq').prop('disabled', true).text('Applied ✓');
                    _setStatus('FAQ saved!', 'success');
                } else {
                    _setStatus('Error saving FAQ', 'error');
                }
            });
        });
    }

    // -----------------------------------------------------------------------
    // Open / Close
    // -----------------------------------------------------------------------

    function _open() {
        if ($panel) $panel.addClass('open');
    }

    function _close() {
        if ($panel) $panel.removeClass('open');
    }

    // -----------------------------------------------------------------------
    // Dot (pulsing indicator)
    // -----------------------------------------------------------------------

    function _checkRecommendationsDot() {
        if (window.siloqHasRecommendations === true) {
            if ($dot) $dot.addClass('active');
        } else {
            if ($dot) $dot.removeClass('active');
        }
    }

    // -----------------------------------------------------------------------
    // Analyze (public — called by Gutenberg sidebar too)
    // -----------------------------------------------------------------------

    function analyze() {
        _setStatus('<span class="siloq-spinner"></span> Analyzing…', 'loading');
        $('#siloq-fp-score').empty();
        $('#siloq-fp-recs').empty();
        $('#siloq-fp-apply-row').hide();

        _ajax({ action: 'siloq_analyze_page', post_id: _postId }, function (res) {
            if (!res.success) {
                _setStatus('Analysis failed: ' + (res.data && res.data.message ? res.data.message : 'Unknown error'), 'error');
                return;
            }

            var data = res.data;
            _lastData = data;

            // Score
            if (data.score !== undefined) {
                $('#siloq-fp-score').html(
                    '<div class="siloq-score-number">' + data.score + '</div>' +
                    '<div class="siloq-score-label">SEO Score</div>'
                );
            }

            // Recommendations
            if (data.recommendations && data.recommendations.length) {
                var html = '';
                data.recommendations.forEach(function (rec) {
                    var cls = rec.priority || 'warning';
                    html += '<div class="siloq-rec-item ' + cls + '">' + rec.message + '</div>';
                });
                $('#siloq-fp-recs').html(html);

                window.siloqHasRecommendations = true;
                _checkRecommendationsDot();
            } else {
                $('#siloq-fp-recs').html('<p class="siloq-fp-hint">&#10003; No issues found.</p>');
                window.siloqHasRecommendations = false;
                _checkRecommendationsDot();
            }

            // Show apply buttons if suggestions are available
            if (data.suggested_title || data.meta_description || data.schema) {
                $('#siloq-fp-apply-row').show();
            }

            _setStatus('Analysis complete', 'success');
        });
    }

    // -----------------------------------------------------------------------
    // Apply meta
    // -----------------------------------------------------------------------

    function _applyMeta() {
        if (!_lastData) { _setStatus('Run analysis first', 'error'); return; }
        _setStatus('<span class="siloq-spinner"></span> Applying meta…', 'loading');

        _ajax({
            action:           'siloq_apply_meta',
            post_id:          _postId,
            suggested_title:  _lastData.suggested_title  || '',
            meta_description: _lastData.meta_description || ''
        }, function (res) {
            if (res.success) {
                _setStatus('Title &amp; meta applied!', 'success');
            } else {
                _setStatus('Error applying meta: ' + (res.data && res.data.message ? res.data.message : ''), 'error');
            }
        });
    }

    // -----------------------------------------------------------------------
    // Apply schema
    // -----------------------------------------------------------------------

    function _applySchema() {
        if (!_lastData || !_lastData.schema) { _setStatus('No schema data — run analysis first', 'error'); return; }
        _setStatus('<span class="siloq-spinner"></span> Applying schema…', 'loading');

        _ajax({
            action:  'siloq_apply_schema',
            post_id: _postId,
            schema:  JSON.stringify(_lastData.schema)
        }, function (res) {
            if (res.success) {
                _setStatus('Schema applied!', 'success');
            } else {
                _setStatus('Error applying schema', 'error');
            }
        });
    }

    // -----------------------------------------------------------------------
    // Generate content
    // -----------------------------------------------------------------------

    function _generate() {
        var contentType = $('input[name="siloq_ct"]:checked').val() || 'faq';
        _setStatus('<span class="siloq-spinner"></span> Generating…', 'loading');
        $('#siloq-fp-generated').hide();
        $('#siloq-fp-faq-list').empty();
        $('#siloq-fp-raw-output').hide();

        _ajax({
            action:       'siloq_generate_content_snippet',
            post_id:      _postId,
            content_type: contentType
        }, function (res) {
            if (!res.success) {
                _setStatus('Generation failed: ' + (res.data && res.data.message ? res.data.message : ''), 'error');
                return;
            }

            $('#siloq-fp-generated').show();

            if (contentType === 'faq' && res.data.faqs && res.data.faqs.length) {
                $('#siloq-fp-faq-list').html(_renderFaqList(res.data.faqs));
                $('#siloq-fp-raw-output').hide();
            } else {
                // Non-FAQ: show textarea
                $('#siloq-fp-content-out').val(res.data.content || '');
                $('#siloq-fp-raw-output').show();
                $('#siloq-fp-faq-list').empty();
            }

            _setStatus('Generated!', 'success');
        });
    }

    // -----------------------------------------------------------------------
    // FAQ rendering with tagging
    // -----------------------------------------------------------------------

    /**
     * Render an array of FAQ objects as HTML.
     *
     * Each FAQ object: { question, answer, type: 'auto'|'needs_input', suggested_answer }
     *
     * @param  {Array}  faqs
     * @return {string} HTML string
     */
    function _renderFaqList(faqs) {
        return faqs.map(function (faq, i) {
            var badge = faq.type === 'auto'
                ? '<span class="siloq-badge-green">&#10003; Ready</span>'
                : '<span class="siloq-badge-amber">&#9998; Your answer needed</span>';

            var answerHtml = faq.type === 'auto'
                ? '<p class="siloq-fp-answer">' + _esc(faq.answer) + '</p>'
                : '<p class="siloq-fp-hint">Based on industry averages — edit to match your business:</p>' +
                  '<textarea class="siloq-fp-faq-edit" data-index="' + i + '" rows="3">' + _esc(faq.suggested_answer || '') + '</textarea>';

            var disabled = faq.type === 'needs_input' ? ' disabled' : '';

            return [
                '<div class="siloq-fp-faq-item" data-index="' + i + '">',
                    badge,
                    '<p class="siloq-fp-question"><strong>Q: ' + _esc(faq.question) + '</strong></p>',
                    answerHtml,
                    '<button class="siloq-fp-apply-faq siloq-fp-btn" data-index="' + i + '"' + disabled + '>',
                        'Apply this FAQ',
                    '</button>',
                '</div>'
            ].join('');
        }).join('');
    }

    // -----------------------------------------------------------------------
    // Structure tab
    // -----------------------------------------------------------------------

    function _loadStructure() {
        var $list = $('#siloq-fp-structure-list');
        if ($list.data('loaded')) return; // only load once per panel open

        $list.html('<p class="siloq-fp-hint"><span class="siloq-spinner"></span> Loading…</p>');

        _ajax({ action: 'siloq_get_supporting_pages', post_id: _postId }, function (res) {
            if (!res.success || !res.data.pages || !res.data.pages.length) {
                $list.html('<p class="siloq-fp-hint">No supporting pages found.</p>');
                return;
            }

            var html = '';
            res.data.pages.forEach(function (page) {
                html += '<div class="siloq-struct-item">' +
                    '<span class="siloq-struct-title">' + _esc(page.title) + '</span>' +
                    '<span class="siloq-struct-status">' + _esc(page.status) + '</span>' +
                    '</div>';
            });
            $list.html(html);
            $list.data('loaded', true);
        });
    }

    function _createDraft() {
        _setStatus('<span class="siloq-spinner"></span> Creating draft…', 'loading');

        _ajax({ action: 'siloq_create_supporting_draft', post_id: _postId }, function (res) {
            if (res.success) {
                _setStatus('Draft created!', 'success');
                // Invalidate structure cache so it reloads
                $('#siloq-fp-structure-list').removeData('loaded');
            } else {
                _setStatus('Error creating draft', 'error');
            }
        });
    }

    // -----------------------------------------------------------------------
    // Status bar
    // -----------------------------------------------------------------------

    function _setStatus(html, type) {
        if (!$status) return;
        $status.removeClass('error success loading').addClass(type || '');
        $status.html(html);
    }

    // -----------------------------------------------------------------------
    // AJAX helper
    // -----------------------------------------------------------------------

    function _ajax(data, callback) {
        data.nonce = _nonce;
        $.post(_ajaxUrl, data, function (res) {
            if (typeof callback === 'function') callback(res);
        }, 'json').fail(function () {
            if (typeof callback === 'function') {
                callback({ success: false, data: { message: 'Network error' } });
            }
        });
    }

    // -----------------------------------------------------------------------
    // Utility
    // -----------------------------------------------------------------------

    /** Basic HTML escaping */
    function _esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    return {
        init:    init,
        analyze: analyze
    };

}(jQuery));
