/**
 * siloq-beaver-intelligence.js
 *
 * Beaver Builder Widget Intelligence integration.
 * Uses FLBuilder native hook events to detect module editing and inject
 * the Siloq Intelligence panel.
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
            panelSelector: '.fl-builder-settings-tab[data-tab="general"]:visible, .fl-builder-settings:visible',

            detectActiveWidget: function () {
                try {
                    var $panel = $('.fl-builder-settings:visible');
                    if (!$panel.length) return null;

                    // Determine type from panel heading or closest data attr
                    var type = 'rich-text';
                    var $heading = $panel.find('.fl-builder-settings-title').first();
                    if ($heading.length) {
                        type = $heading.text().trim().toLowerCase().replace(/\s+/g, '-') || 'rich-text';
                    }

                    // Check for heading vs rich-text
                    var $headingInput = $panel.find('.fl-heading-text input:visible, input[name="heading"]:visible');
                    var $richText     = $panel.find('.fl-rich-text textarea:visible, textarea[name="text"]:visible');
                    var content = '';

                    if ($headingInput.length) {
                        content = $headingInput.val() || '';
                        type = 'heading';
                    } else if ($richText.length) {
                        content = $richText.val() || '';
                        type = 'rich-text';
                    } else {
                        // Generic first visible textarea or input
                        var $first = $panel.find('textarea:visible, input[type="text"]:visible').first();
                        content = $first.val() || '';
                    }

                    return { type: type, content: content, widget_id: type + '_' + Date.now() };
                } catch (e) {
                    return null;
                }
            },

            buildPageMap: function () {
                var headings = [];
                try {
                    // Try Beaver layout object first
                    if (window.FLBuilderLayout && FLBuilderLayout.layout && FLBuilderLayout.layout.rows) {
                        $.each(FLBuilderLayout.layout.rows, function (id, row) {
                            $.each(row.cols || {}, function (cid, col) {
                                $.each(col.modules || {}, function (mid, mod) {
                                    if (mod.settings && mod.settings.tag && /^h[1-6]$/.test(mod.settings.tag)) {
                                        headings.push({
                                            tag:       mod.settings.tag,
                                            text:      mod.settings.heading || '',
                                            widget_id: mid,
                                        });
                                    }
                                });
                            });
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
                try {
                    var $headingInput = $('.fl-heading-text input:visible, input[name="heading"]:visible').first();
                    if ($headingInput.length) {
                        $headingInput.val(suggestion).trigger('change').trigger('keyup');
                        return;
                    }
                    var $ta = $('.fl-rich-text textarea:visible, textarea[name="text"]:visible').first();
                    if ($ta.length) {
                        $ta.val(suggestion).trigger('change');
                    }
                } catch (e) {}
            },

            applyHeadingTag: function (tag) {
                try {
                    var $select = $('select[name="tag"]:visible, select.fl-heading-tag:visible').first();
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
                widgetType: currentWidgetData ? currentWidgetData.type : 'rich-text',
            });
        }

        // ── Beaver Builder event hooks ─────────────────────────────────────

        function bindBeaverHooks() {
            if (!window.FLBuilder) return;
            try {
                FLBuilder.addHook('didStartEditingNode', function () {
                    setTimeout(function () {
                        try {
                            var $panel = $('.fl-builder-settings-tab[data-tab="general"]:visible');
                            if (!$panel.length) $panel = $('.fl-builder-settings:visible');
                            if ($panel.length) injectPanel($panel);
                        } catch (e) {}
                    }, 400);
                });
            } catch (e) {}
        }

        // Attempt immediately and also after DOM ready
        bindBeaverHooks();
        $(document).ready(function () { bindBeaverHooks(); });

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

        // ── MutationObserver fallback ─────────────────────────────────────

        var observer = new MutationObserver(function () {
            try {
                var $panel = $('.fl-builder-settings-tab[data-tab="general"]:visible');
                if (!$panel.length) $panel = $('.fl-builder-settings:visible');
                if ($panel.length) injectPanel($panel);
            } catch (e) {}
        });

        observer.observe(document.body, { childList: true, subtree: true });

    } catch (globalErr) {
        if (window.console && console.warn) {
            console.warn('[Siloq] Beaver Builder intelligence init error:', globalErr);
        }
    }

}(jQuery));
