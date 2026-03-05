/**
 * siloq-bricks-intelligence.js
 *
 * Bricks Builder Widget Intelligence integration.
 * Uses MutationObserver + bricksData to detect active elements and
 * injects the Siloq Intelligence panel.
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
            panelSelector: '.brx-element-settings:visible, .brxe-panel-inner:visible',

            detectActiveWidget: function () {
                try {
                    var bData    = window.bricksData || {};
                    var activeId = bData.activeElement;

                    if (activeId && bData.elements && bData.elements[activeId]) {
                        var el       = bData.elements[activeId];
                        var type     = el.name || 'text';
                        var settings = el.settings || {};
                        var content  = settings.text || settings.content || settings.heading || '';
                        return { type: type, content: content, widget_id: activeId };
                    }

                    // Fallback: read from visible settings panel
                    var $panel   = $(BUILDER_CONFIG.panelSelector);
                    var content2 = $panel.find('textarea:visible, input[type="text"]:visible').first().val() || '';
                    return { type: 'text', content: content2, widget_id: 'brx_' + Date.now() };
                } catch (e) {
                    return null;
                }
            },

            buildPageMap: function () {
                var headings = [];
                try {
                    var bData = window.bricksData || {};
                    var els   = bData.elements || {};
                    Object.keys(els).forEach(function (id) {
                        var el = els[id];
                        if (el && el.name === 'heading' && el.settings) {
                            headings.push({
                                tag:       el.settings.tag  || 'h2',
                                text:      el.settings.text || '',
                                widget_id: el.id || id,
                            });
                        }
                    });
                } catch (e) {}

                if (!headings.length) {
                    $('h1,h2,h3,h4,h5,h6').each(function () {
                        headings.push({ tag: this.tagName.toLowerCase(), text: $(this).text(), widget_id: '' });
                    });
                }

                return { headings: headings, containers: [] };
            },

            applyContent: function (suggestion) {
                try {
                    var bData    = window.bricksData || {};
                    var activeId = bData.activeElement;
                    if (activeId && bData.elements && bData.elements[activeId] && bData.elements[activeId].settings) {
                        bData.elements[activeId].settings.text = suggestion;
                    }
                    // Also update visible input/textarea for immediate visual feedback
                    var $ta = $(
                        '.brx-element-settings textarea:visible,' +
                        '.brx-element-settings input[type="text"]:visible'
                    ).first();
                    if ($ta.length) {
                        $ta.val(suggestion).trigger('input').trigger('change');
                    }
                } catch (e) {}
            },

            applyHeadingTag: function (tag) {
                try {
                    var bData    = window.bricksData || {};
                    var activeId = bData.activeElement;
                    if (activeId && bData.elements && bData.elements[activeId] && bData.elements[activeId].settings) {
                        bData.elements[activeId].settings.tag = tag;
                    }
                    var $select = $('.brx-element-settings select[name="tag"]:visible').first();
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
            console.warn('[Siloq] Bricks intelligence init error:', globalErr);
        }
    }

}(jQuery));
