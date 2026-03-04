/**
 * siloq-classic-intelligence.js
 *
 * Classic Editor Widget Intelligence integration.
 * Injects a Siloq Intelligence metabox into the post edit screen sidebar.
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
            detectActiveWidget: function () {
                try {
                    var content = '';
                    if (window.tinymce && tinymce.get('content')) {
                        content = tinymce.get('content').getContent({ format: 'text' });
                    } else {
                        content = $('#content').val() || '';
                    }
                    return { type: 'text-editor', content: content, widget_id: 'classic_editor' };
                } catch (e) {
                    return { type: 'text-editor', content: '', widget_id: 'classic_editor' };
                }
            },

            buildPageMap: function () {
                var headings = [];
                try {
                    var html = '';
                    if (window.tinymce && tinymce.get('content')) {
                        html = tinymce.get('content').getContent();
                    } else {
                        html = $('#content').val() || '';
                    }
                    // Parse HTML string for heading tags
                    var $parsed = $('<div>').html(html);
                    $parsed.find('h1,h2,h3,h4,h5,h6').each(function () {
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
                    if (window.tinymce && tinymce.get('content')) {
                        // Wrap plain text in a paragraph tag
                        tinymce.get('content').setContent('<p>' + suggestion + '</p>');
                    } else {
                        $('#content').val(suggestion);
                    }
                } catch (e) {}
            },

            applyHeadingTag: function (/* tag */) {
                // Not applicable for classic editor full-page content
            },
        };

        // ── Inject panel into sidebar metabox area ────────────────────────

        function injectClassicPanel() {
            if ($('#siloq-classic-wi-panel').length) return; // already injected

            var $metaboxArea = $('#side-sortables, #normal-sortables');
            if (!$metaboxArea.length) return;

            var $box = $(
                '<div id="siloq-classic-wi-panel" class="postbox">' +
                    '<div class="postbox-header">' +
                        '<h2 class="hndle ui-sortable-handle" style="cursor:default;">' +
                            '<span>⚡ Siloq Intelligence</span>' +
                        '</h2>' +
                    '</div>' +
                    '<div class="inside"></div>' +
                '</div>'
            );

            $metaboxArea.first().prepend($box);

            core.renderSiloqPanel($box.find('.inside')[0], { widgetType: 'text-editor' });
        }

        // ── Analyze button handler ────────────────────────────────────────

        $(document).on('click', '.siloq-wi-analyze-btn', function () {
            var $container = $(this).closest('.siloq-wi-container');
            currentWidgetData = BUILDER_CONFIG.detectActiveWidget();

            if (!currentWidgetData) {
                core.showStatus($container, 'Could not read editor content.', 'error');
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
                    core.renderResults($container, data, 'text-editor');
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

        // ── DOM ready ─────────────────────────────────────────────────────

        $(document).ready(function () {
            injectClassicPanel();
        });

    } catch (globalErr) {
        if (window.console && console.warn) {
            console.warn('[Siloq] Classic Editor intelligence init error:', globalErr);
        }
    }

}(jQuery));
