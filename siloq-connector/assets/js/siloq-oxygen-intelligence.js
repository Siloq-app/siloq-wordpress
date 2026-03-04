/**
 * siloq-oxygen-intelligence.js
 *
 * Oxygen Builder Widget Intelligence integration.
 * Uses MutationObserver + AngularJS scope access to detect active
 * components and inject the Siloq Intelligence panel.
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
            panelSelector: '.oxygen-sidebar-inner:visible',

            detectActiveWidget: function () {
                try {
                    // Try AngularJS scope
                    if (typeof angular !== 'undefined') {
                        var sidebarEl = document.querySelector('.oxygen-sidebar-inner');
                        if (sidebarEl) {
                            var scope = angular.element(sidebarEl).scope();
                            if (scope && scope.component) {
                                var c       = scope.component;
                                var type    = (c.name || 'Text').toLowerCase();
                                var content = (c.options && (c.options.ct_content || c.options.text)) || '';
                                return {
                                    type:      type,
                                    content:   content,
                                    widget_id: String(c.id || ('oxy_' + Date.now())),
                                };
                            }
                        }
                    }
                } catch (e) {}

                // Fallback: visible panel inputs
                try {
                    var $panel   = $('.oxygen-sidebar-inner:visible');
                    var $ta      = $panel.find('textarea:visible, input[type="text"]:visible').first();
                    var content2 = $ta.val() || '';
                    return { type: 'text', content: content2, widget_id: 'oxy_' + Date.now() };
                } catch (e2) {
                    return null;
                }
            },

            buildPageMap: function () {
                var headings = [];
                try {
                    // Try AngularJS component tree
                    if (typeof angular !== 'undefined') {
                        var rootEl = document.querySelector('[ng-app], [data-ng-app]');
                        if (rootEl) {
                            var rootScope = angular.element(rootEl).scope();
                            if (rootScope && rootScope.$$childHead) {
                                var comps = window.ct_template_components ||
                                            (window.OxygenInterface && OxygenInterface.components) || [];
                                var list  = Array.isArray(comps) ? comps : Object.values(comps);
                                list.forEach(function (c) {
                                    if (
                                        c.name === 'Heading' ||
                                        (c.options && c.options.tag && /^h[1-6]$/.test(c.options.tag))
                                    ) {
                                        headings.push({
                                            tag:       (c.options && c.options.tag) || 'h2',
                                            text:      (c.options && c.options.ct_content) || '',
                                            widget_id: String(c.id || ''),
                                        });
                                    }
                                });
                            }
                        }
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
                try {
                    // Try AngularJS scope first
                    if (typeof angular !== 'undefined') {
                        var sidebarEl = document.querySelector('.oxygen-sidebar-inner');
                        if (sidebarEl) {
                            var scope = angular.element(sidebarEl).scope();
                            if (scope && scope.component && scope.component.options) {
                                scope.component.options.ct_content = suggestion;
                                scope.$apply();
                                return;
                            }
                        }
                    }
                } catch (e) {}

                // Fallback: set value on first visible text field
                try {
                    var $ta = $('.oxygen-sidebar-inner textarea:visible, .oxygen-sidebar-inner input[type="text"]:visible').first();
                    if ($ta.length) {
                        $ta.val(suggestion).trigger('input').trigger('change');
                    }
                } catch (e2) {}
            },

            applyHeadingTag: function (tag) {
                try {
                    var $select = $('.oxygen-sidebar-inner select:visible').first();
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
            console.warn('[Siloq] Oxygen intelligence init error:', globalErr);
        }
    }

}(jQuery));
