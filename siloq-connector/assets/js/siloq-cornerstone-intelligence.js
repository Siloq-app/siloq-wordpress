/**
 * siloq-cornerstone-intelligence.js
 *
 * Cornerstone (X Theme) Builder Widget Intelligence integration.
 * Uses MutationObserver + Redux store access to detect active elements
 * and inject the Siloq Intelligence panel.
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
            panelSelector: '.x-bar-content:visible, .x-inspector:visible',

            detectActiveWidget: function () {
                try {
                    // Try Redux store first (_x_app is Cornerstone's global)
                    var store = window._x_app && _x_app.store;
                    if (store) {
                        var state  = store.getState();
                        var active = state && state.ui && state.ui.activeElement;
                        if (active) {
                            return {
                                type:      active.type || 'text',
                                content:   (active.props && (active.props._prop_text || active.props.content)) || '',
                                widget_id: active.id || '',
                            };
                        }
                    }
                } catch (e) {}

                // Fallback: DOM scraping
                try {
                    var $bar   = $('.x-bar-content:visible, .x-inspector:visible');
                    var type   = $bar.find('.cs-control-label:visible').first().text().toLowerCase().trim() || 'text';
                    var $input = $bar.find(
                        '.cs-control-text input:visible,' +
                        '.cs-control-textarea textarea:visible'
                    ).first();
                    return { type: type, content: $input.val() || '', widget_id: type + '_' + Date.now() };
                } catch (e2) {
                    return null;
                }
            },

            buildPageMap: function () {
                var headings = [];
                try {
                    var store = window._x_app && _x_app.store;
                    if (store) {
                        var els = store.getState().elements || {};
                        Object.keys(els).forEach(function (id) {
                            var el = els[id];
                            if (el && el.type === 'headline' && el.props) {
                                headings.push({
                                    tag:       el.props.tag || 'h2',
                                    text:      el.props._prop_text || '',
                                    widget_id: el.id || id,
                                });
                            }
                        });
                    }
                } catch (e) {}

                if (!headings.length) {
                    $('h1,h2,h3,h4,h5,h6').each(function () {
                        headings.push({ tag: this.tagName.toLowerCase(), text: $(this).text(), widget_id: '' });
                    });
                }

                return { headings: headings, containers: [] };
            },

            applyContent: function (suggestion) {
                // Try Redux store dispatch (Cornerstone action pattern)
                try {
                    var store  = window._x_app && _x_app.store;
                    var active = store && store.getState().ui && store.getState().ui.activeElement;
                    if (active && active.id && store.dispatch) {
                        store.dispatch({
                            type:    'UPDATE_ELEMENT',
                            id:      active.id,
                            changes: { _prop_text: suggestion, content: suggestion },
                        });
                        return;
                    }
                } catch (e) {}

                // Fallback: native input setter + synthetic event
                try {
                    var $el = $(
                        '.x-bar-content .cs-control-textarea textarea:visible,' +
                        '.x-bar-content .cs-control-text input:visible,' +
                        '.x-inspector .cs-control-textarea textarea:visible,' +
                        '.x-inspector .cs-control-text input:visible'
                    ).first();

                    if ($el.length) {
                        var nativeSetter =
                            Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value') ||
                            Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
                        if (nativeSetter && nativeSetter.set) {
                            nativeSetter.set.call($el[0], suggestion);
                        } else {
                            $el.val(suggestion);
                        }
                        $el[0].dispatchEvent(new Event('input', { bubbles: true }));
                        $el[0].dispatchEvent(new Event('change', { bubbles: true }));
                    }
                } catch (e2) {}
            },

            applyHeadingTag: function (tag) {
                try {
                    var $select = $(
                        '.x-bar-content select[name="tag"]:visible,' +
                        '.x-inspector select[name="tag"]:visible'
                    ).first();
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
            console.warn('[Siloq] Cornerstone intelligence init error:', globalErr);
        }
    }

}(jQuery));
