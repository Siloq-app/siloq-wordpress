/**
 * siloq-wpbakery-intelligence.js
 *
 * WPBakery Page Builder Widget Intelligence integration.
 * Uses MutationObserver to detect when a WPBakery element modal opens,
 * then injects the Siloq Intelligence panel.
 *
 * @package Siloq
 * @since   1.5.58
 */
(function ($) {
    'use strict';

    try {

        if (typeof SiloqIntelligenceCore === 'undefined') return;

        var core = SiloqIntelligenceCore;
        var cfg  = window.siloqIntelligenceCore || {};
        var currentWidgetData = null;

        // ── Builder config ────────────────────────────────────────────────

        var BUILDER_CONFIG = {
            panelSelector: '.vc_ui-panel-content-area:visible',

            detectActiveWidget: function () {
                try {
                    var $modal = $('.vc_ui-panel-window:visible');
                    if (!$modal.length) return null;

                    var typeRaw = $modal.find('.vc_ui-panel-header-title').first().text()
                                        .trim().toLowerCase().replace(/\s+/g, '-') || 'text';

                    // Try param named 'content' first, then textarea inside value wrapper
                    var content = $modal.find(
                        '[name="content"]:visible,' +
                        '.vc_column-option-value textarea:visible'
                    ).val() || '';

                    // If still empty, try heading-style inputs
                    if (!content) {
                        content = $modal.find(
                            '[name="title"]:visible,' +
                            '[name="heading"]:visible,' +
                            'input[type="text"]:visible'
                        ).first().val() || '';
                    }

                    return { type: typeRaw, content: content, widget_id: typeRaw + '_' + Date.now() };
                } catch (e) {
                    return null;
                }
            },

            buildPageMap: function () {
                var headings = [];
                try {
                    $('h1,h2,h3,h4,h5,h6').each(function () {
                        headings.push({
                            tag:       this.tagName.toLowerCase(),
                            text:      $(this).text(),
                            widget_id: '',
                        });
                    });
                } catch (e) {}
                return { headings: headings, containers: [] };
            },

            applyContent: function (suggestion) {
                try {
                    var $ta = $(
                        '[name="content"]:visible,' +
                        '.vc_column-option-value textarea:visible'
                    ).first();
                    if ($ta.length) {
                        $ta.val(suggestion).trigger('change').trigger('input');
                        return;
                    }
                    var $inp = $('[name="title"]:visible, [name="heading"]:visible').first();
                    if ($inp.length) {
                        $inp.val(suggestion).trigger('change').trigger('input');
                    }
                } catch (e) {}
            },

            applyHeadingTag: function (tag) {
                try {
                    var $select = $('select[name="tag"]:visible').first();
                    if ($select.length) {
                        $select.val(tag).trigger('change');
                    }
                } catch (e) {}
            },
        };

        // ── Panel injection ───────────────────────────────────────────────

        function injectPanel($panel) {
            if (!$panel.length || $panel.find('.siloq-wi-container').length) return;

            currentWidgetData = BUILDER_CONFIG.detectActiveWidget();

            var $section = $(
                '<div class="siloq-wi-section" ' +
                'style="border-top:1px solid #e5e7eb;padding:12px 0;margin-top:8px;">' +
                    '<p style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;' +
                    'letter-spacing:.05em;padding:0 12px;margin:0 0 8px;">⚡ SILOQ INTELLIGENCE</p>' +
                '</div>'
            );
            var $inner = $('<div style="padding:0 12px;"></div>');
            $section.append($inner);
            $panel.append($section);

            core.renderSiloqPanel($inner[0], {
                widgetType: currentWidgetData ? currentWidgetData.type : 'text',
            });
        }

        // ── Analyze button handler ────────────────────────────────────────

        $(document).on('click', '.siloq-wi-analyze-btn', function () {
            var $container = $(this).closest('.siloq-wi-container');
            currentWidgetData = BUILDER_CONFIG.detectActiveWidget();

            if (!currentWidgetData) {
                core.showStatus($container, 'Could not read widget data.', 'error');
                return;
            }

            $container.find('.siloq-wi-loading').show();
            $container.find('.siloq-wi-results').hide();
            $(this).prop('disabled', true).text('Analyzing…');

            var payload = core.buildWidgetAnalysisPayload(
                currentWidgetData,
                BUILDER_CONFIG.buildPageMap(),
                cfg
            );

            core.analyzeWidget(payload, {
                success: function (data) {
                    $container.find('.siloq-wi-loading').hide();
                    $container.find('.siloq-wi-analyze-btn').prop('disabled', false).text('⚡ Re-analyze');
                    core.renderResults($container, data, currentWidgetData.type);
                },
                error: function (msg) {
                    $container.find('.siloq-wi-loading').hide();
                    $container.find('.siloq-wi-analyze-btn').prop('disabled', false).text('⚡ Analyze This Widget');
                    core.showStatus($container, msg, 'error');
                },
            });
        });

        // ── Apply / Skip handlers ─────────────────────────────────────────

        core.bindPanelEvents(
            function (suggestion, widgetType, $btn) {
                BUILDER_CONFIG.applyContent(suggestion, widgetType);
                $btn.text('✅ Applied').prop('disabled', true);
            },
            function (tag, $btn) {
                BUILDER_CONFIG.applyHeadingTag(tag);
                $btn.text('✅ Applied').prop('disabled', true);
            }
        );

        // ── MutationObserver ──────────────────────────────────────────────

        var observer = new MutationObserver(function () {
            try {
                var $panel = $(BUILDER_CONFIG.panelSelector);
                if ($panel.length) injectPanel($panel.last());
            } catch (e) {}
        });

        observer.observe(document.body, { childList: true, subtree: true });

    } catch (globalErr) {
        if (window.console && console.warn) {
            console.warn('[Siloq] WPBakery intelligence init error:', globalErr);
        }
    }

}(jQuery));
