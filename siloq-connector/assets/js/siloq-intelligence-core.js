/**
 * siloq-intelligence-core.js
 *
 * Shared logic for all builder intelligence integrations.
 * Must be loaded before any builder-specific intelligence JS file.
 *
 * @package Siloq
 * @since   1.5.58
 */
window.SiloqIntelligenceCore = (function ($) {
    'use strict';

    var cfg = window.siloqIntelligenceCore || {};

    // ── Build standard API payload ────────────────────────────────────────

    function buildWidgetAnalysisPayload(widgetData, pageMap, siteContext) {
        return {
            active_widget: {
                type:      widgetData.type      || 'text',
                content:   widgetData.content   || '',
                widget_id: widgetData.widget_id || '',
            },
            container_context: widgetData.container_context || {},
            page_heading_map:  (pageMap && pageMap.headings) ? pageMap.headings : [],
            page_id:  (siteContext && siteContext.postId)  || (cfg.postId  || 0),
            site_id:  (siteContext && siteContext.siteId)  || (cfg.siteId  || ''),
        };
    }

    // ── Heading hierarchy validation ──────────────────────────────────────

    function validateHeadingHierarchy(headingMap) {
        var violations = [];
        var prevLevel  = 0;
        var h1Count    = 0;

        (headingMap || []).forEach(function (h) {
            var level = parseInt(String(h.tag || 'h2').replace(/\D/g, ''), 10) || 2;
            if (level === 1) h1Count++;
            if (prevLevel > 0 && level > prevLevel + 1) {
                violations.push({
                    widget_id: h.widget_id || '',
                    tag:  h.tag,
                    issue: 'Hierarchy gap: H' + prevLevel + ' → H' + level,
                    fix:   'Change to H' + (prevLevel + 1),
                });
            }
            prevLevel = level;
        });

        if (h1Count > 1) {
            violations.push({
                widget_id: '',
                tag:  'h1',
                issue: 'Multiple H1 tags (' + h1Count + ')',
                fix:   'Change extra H1s to H2',
            });
        }

        return violations;
    }

    // ── Render standard Siloq panel UI into any container ────────────────

    function renderSiloqPanel(container, options) {
        options = options || {};
        var widgetType = options.widgetType || 'text';
        var html =
            '<div class="siloq-wi-container" data-widget-type="' + esc(widgetType) + '" ' +
            'style="padding:12px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:12px;">' +
                '<button type="button" class="siloq-wi-analyze-btn" ' +
                'style="width:100%;padding:8px;background:#D39938;color:#fff;border:none;border-radius:6px;' +
                'font-size:12px;font-weight:600;cursor:pointer;margin-bottom:6px;">⚡ Analyze This Widget</button>' +
                '<div class="siloq-wi-loading" style="display:none;text-align:center;padding:10px;color:#6b7280;font-size:11px;">' +
                    '<span class="spinner is-active" style="float:none"></span> Analyzing...</div>' +
                '<div class="siloq-wi-results" style="display:none;">' +
                    '<div class="siloq-wi-layer-badge" style="margin-bottom:6px;"></div>' +
                    '<div class="siloq-wi-violations"></div>' +
                    '<div class="siloq-wi-suggestion-block">' +
                        '<p style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;margin:6px 0 4px;">Suggested</p>' +
                        '<div class="siloq-wi-suggestion-text" style="font-size:12px;color:#065f46;background:#d1fae5;' +
                        'padding:8px;border-radius:5px;line-height:1.5;margin-bottom:8px;"></div>' +
                        '<div class="siloq-wi-heading-tag" style="display:none;margin-bottom:8px;">' +
                            '<p style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;margin:0 0 4px;">Recommended Tag</p>' +
                            '<div class="siloq-wi-tag-display"></div>' +
                        '</div>' +
                        '<div class="siloq-wi-heading-warnings"></div>' +
                        '<div style="display:flex;gap:6px;margin-top:6px;">' +
                            '<button class="siloq-wi-apply-btn" style="flex:1;padding:6px;background:#D39938;color:#fff;' +
                            'border:none;border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;">✅ Apply</button>' +
                            '<button class="siloq-wi-skip-btn" style="padding:6px 10px;background:#f3f4f6;' +
                            'border:1px solid #d1d5db;border-radius:5px;font-size:11px;cursor:pointer;">Skip</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="siloq-wi-image-block" style="display:none;margin-top:10px;border-top:1px solid #e5e7eb;padding-top:10px;">' +
                        '<p style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;margin:0 0 6px;">📸 Image Intelligence</p>' +
                        '<div class="siloq-wi-image-recs"></div>' +
                    '</div>' +
                    '<div class="siloq-wi-status" style="display:none;margin-top:6px;padding:6px 8px;border-radius:5px;font-size:11px;"></div>' +
                '</div>' +
            '</div>';
        $(container).html(html);
    }

    // ── Call the API (AJAX via WP) ────────────────────────────────────────

    function analyzeWidget(payload, callbacks) {
        callbacks = callbacks || {};
        $.ajax({
            url:  cfg.ajaxUrl || (window.ajaxurl || '/wp-admin/admin-ajax.php'),
            type: 'POST',
            data: {
                action:  'siloq_analyze_widget',
                nonce:   cfg.nonce || '',
                page_id: payload.page_id,
                payload: payload,
            },
            success: function (res) {
                if (res.success && res.data) {
                    if (callbacks.success) callbacks.success(res.data);
                } else {
                    if (callbacks.error) callbacks.error('Analysis failed');
                }
            },
            error: function () {
                if (callbacks.error) callbacks.error('Network error');
            },
        });
    }

    // ── Render analysis results into a panel ─────────────────────────────

    function renderResults($container, data, widgetType) {
        var layerColors = { apex_hub: '#D39938', hub: '#D39938', spoke: '#0891b2', supporting: '#059669' };
        var layerLabels = { apex_hub: 'Apex Hub', hub: 'Hub Page', spoke: 'Spoke Page', supporting: 'Supporting' };
        var layer = data.layer || 'spoke';

        var lBg = layer === 'apex_hub' ? '#D39938' : (layerColors[layer] || '#6b7280') + '20';
        var lFg = layer === 'apex_hub' ? '#fff' : (layerColors[layer] || '#6b7280');
        $container.find('.siloq-wi-layer-badge').html(
            '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;' +
            'background:' + lBg + ';color:' + lFg + ';">' +
            esc(layerLabels[layer] || layer) + '</span>'
        );

        var $v = $container.find('.siloq-wi-violations').empty();
        [].concat(data.layer_violations || [], data.heading_violations || []).forEach(function (v) {
            var msg = typeof v === 'string' ? v : (v.issue || '');
            var fix = typeof v === 'string' ? '' : (v.fix ? ' → ' + v.fix : '');
            $v.append(
                '<p style="font-size:11px;color:#92400e;background:#fef3c7;padding:5px 7px;' +
                'border-radius:4px;margin:3px 0;">⚠️ ' + esc(msg + fix) + '</p>'
            );
        });

        $container.find('.siloq-wi-suggestion-text').text(data.suggested_content || '');
        $container.find('.siloq-wi-apply-btn').data({
            suggestion:   data.suggested_content || '',
            'widget-type': widgetType,
        });

        if (widgetType === 'heading' && data.suggested_heading_tag) {
            $container.find('.siloq-wi-heading-tag').show();
            $container.find('.siloq-wi-tag-display').html(
                '<span style="font-size:13px;font-weight:700;color:#D39938;">' +
                esc(data.suggested_heading_tag.toUpperCase()) + '</span> ' +
                '<button class="siloq-wi-apply-tag-btn" data-tag="' + esc(data.suggested_heading_tag) + '" ' +
                'style="font-size:11px;padding:2px 7px;background:rgba(211,153,56,0.15);color:#D39938;' +
                'border:none;border-radius:4px;cursor:pointer;">Apply</button>'
            );
        }

        var $ib = $container.find('.siloq-wi-image-block');
        var $ir = $container.find('.siloq-wi-image-recs').empty();
        var recs = data.image_recommendations || [];

        if (recs.length) {
            $ib.show();
            recs.forEach(function (r) {
                $ir.append(
                    '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;' +
                    'padding:8px;margin-bottom:6px;font-size:11px;">' +
                        '<p style="margin:0 0 3px;font-weight:600;color:#0369a1;">📸 ' + esc(r.position || '') + '</p>' +
                        '<p style="margin:0 0 2px;"><strong>Subject:</strong> ' + esc(r.subject || '') + '</p>' +
                        '<p style="margin:0 0 2px;"><strong>Filename:</strong> ' + esc(r.suggested_filename || '') + '</p>' +
                        '<p style="margin:0 0 5px;"><strong>Alt:</strong> ' + esc(r.suggested_alt || '') + '</p>' +
                        '<button class="siloq-wi-gen-image-btn" ' +
                        'data-prompt="' + esc(r.ai_prompt || '') + '" ' +
                        'data-filename="' + esc(r.suggested_filename || '') + '" ' +
                        'data-alt="' + esc(r.suggested_alt || '') + '" ' +
                        'style="font-size:11px;padding:3px 9px;background:#D39938;color:#fff;' +
                        'border:none;border-radius:5px;cursor:pointer;">🎨 Generate Image</button>' +
                    '</div>'
                );
            });
        } else {
            $ib.hide();
        }

        $container.find('.siloq-wi-results').show();
    }

    // ── Image generation modal ────────────────────────────────────────────

    function openImageGenerationModal(prompt, filename, altTag) {
        var $modal = $(
            '<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);' +
            'z-index:99999999;display:flex;align-items:center;justify-content:center;">' +
                '<div style="background:#fff;border-radius:12px;padding:24px;width:480px;max-width:90vw;">' +
                    '<h3 style="margin:0 0 12px;font-size:15px;font-weight:700;">🎨 Generate Image</h3>' +
                    '<p style="font-size:12px;color:#6b7280;margin:0 0 6px;">Copy this prompt into Midjourney, DALL-E, or Ideogram:</p>' +
                    '<textarea style="width:100%;height:80px;padding:8px;border:1px solid #d1d5db;border-radius:6px;' +
                    'font-size:12px;box-sizing:border-box;margin-bottom:10px;" readonly>' + esc(prompt) + '</textarea>' +
                    '<p style="font-size:12px;margin:0 0 3px;"><strong>Save as:</strong> ' + esc(filename) + '</p>' +
                    '<p style="font-size:12px;margin:0 0 14px;"><strong>Alt tag:</strong> ' + esc(altTag) + '</p>' +
                    '<div style="display:flex;gap:8px;">' +
                        '<button class="siloq-modal-copy" style="flex:1;padding:8px;background:#D39938;color:#fff;' +
                        'border:none;border-radius:6px;cursor:pointer;font-size:13px;">📋 Copy Prompt</button>' +
                        '<button class="siloq-modal-close" style="flex:1;padding:8px;background:#f3f4f6;' +
                        'border:1px solid #d1d5db;border-radius:6px;cursor:pointer;font-size:13px;">Close</button>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );

        $('body').append($modal);

        $modal.find('.siloq-modal-copy').on('click', function () {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(prompt);
            }
            $(this).text('✅ Copied!');
        });

        $modal.find('.siloq-modal-close').on('click', function () {
            $modal.remove();
        });

        $modal.on('click', function (e) {
            if ($(e.target).is($modal)) $modal.remove();
        });
    }

    // ── Shared event delegation for all builder panels ────────────────────

    function bindPanelEvents(applyFn, applyTagFn) {
        $(document)
            .on('click', '.siloq-wi-skip-btn', function () {
                $(this).closest('.siloq-wi-results').hide();
            })
            .on('click', '.siloq-wi-apply-btn', function () {
                var $btn      = $(this);
                var suggestion = $btn.data('suggestion');
                var widgetType = $btn.data('widget-type');
                if (applyFn) applyFn(suggestion, widgetType, $btn);
            })
            .on('click', '.siloq-wi-apply-tag-btn', function () {
                var tag = $(this).data('tag');
                if (applyTagFn) applyTagFn(tag, $(this));
            })
            .on('click', '.siloq-wi-gen-image-btn', function () {
                var $b = $(this);
                openImageGenerationModal($b.data('prompt'), $b.data('filename'), $b.data('alt'));
            });
    }

    // ── Utilities ─────────────────────────────────────────────────────────

    function showStatus($container, msg, type) {
        var $s = $container.find('.siloq-wi-status');
        $s.css({
            background: type === 'error' ? '#fef2f2' : '#d1fae5',
            color:      type === 'error' ? '#991b1b' : '#065f46',
        }).text(msg).show();
        setTimeout(function () { $s.fadeOut(); }, 4000);
    }

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ── Public API ────────────────────────────────────────────────────────

    return {
        buildWidgetAnalysisPayload: buildWidgetAnalysisPayload,
        validateHeadingHierarchy:   validateHeadingHierarchy,
        renderSiloqPanel:           renderSiloqPanel,
        analyzeWidget:              analyzeWidget,
        renderResults:              renderResults,
        openImageGenerationModal:   openImageGenerationModal,
        bindPanelEvents:            bindPanelEvents,
        showStatus:                 showStatus,
    };

}(jQuery));
