/**
 * siloq-widget-intelligence.js
 * Native Elementor panel integration for Siloq Intelligence.
 *
 * Injects ⚡ Siloq analysis directly into the left widget-settings panel
 * for text-editor, heading, icon-box, image-box, accordion, and toggle
 * widgets. No floating overlays — fully native Elementor UX.
 *
 * @package Siloq
 * @since   1.5.57
 */
(function($) {
    'use strict';

    if (typeof siloqWI === 'undefined' || typeof elementor === 'undefined') return;

    var cfg     = siloqWI;
    var pageMap = null; // Cached page structure

    // ── Page map builder ──────────────────────────────────────────────────

    function buildPageMap() {
        if (!elementor.documents || !elementor.documents.getCurrent()) return null;

        var doc        = elementor.documents.getCurrent();
        var elements   = doc.get('elements');
        var headingMap = [];
        var containerMap = [];

        function traverse(elements, containerInfo) {
            elements.each(function(el) {
                var elType     = el.get('elType');
                var widgetType = el.get('widgetType');
                var id         = el.get('id');
                var settings   = el.get('settings');

                // Collect all heading widgets
                if (widgetType === 'heading') {
                    var tag  = settings.get('header_size') || 'h2';
                    var text = settings.get('title') || '';
                    headingMap.push({
                        tag:       tag,
                        text:      $('<div>').html(text).text(),
                        widget_id: id,
                    });
                }

                // Track containers / sections / columns for context
                if (elType === 'container' || elType === 'section' || elType === 'column') {
                    var children  = el.get('elements');
                    var childInfo = [];
                    if (children) {
                        children.each(function(child) {
                            childInfo.push({
                                id:   child.get('id'),
                                type: child.get('widgetType') || child.get('elType'),
                            });
                        });
                    }
                    containerMap.push({ container_id: id, children: childInfo });
                }

                // Recurse into nested elements
                var children = el.get('elements');
                if (children && children.length) {
                    traverse(children, { container_id: id });
                }
            });
        }

        if (elements) traverse(elements, null);

        return { headingMap: headingMap, containerMap: containerMap };
    }

    function getContainerContext(widgetId) {
        if (!pageMap) return {};
        for (var i = 0; i < pageMap.containerMap.length; i++) {
            var c = pageMap.containerMap[i];
            for (var j = 0; j < c.children.length; j++) {
                if (c.children[j].id === widgetId) {
                    return {
                        container_id:      c.container_id,
                        container_position: i + 1,
                        sibling_widgets:   c.children.filter(function(ch) {
                            return ch.id !== widgetId;
                        }),
                    };
                }
            }
        }
        return {};
    }

    // ── Active widget reader ──────────────────────────────────────────────

    function getActiveWidgetContent() {
        if (!elementor.getPanelView) return null;
        var panel = elementor.getPanelView();
        if (!panel) return null;

        var view = panel.getCurrentPageView ? panel.getCurrentPageView() : null;
        if (!view || !view.model) return null;

        var model    = view.model;
        var type     = model.get('widgetType') || model.get('elType');
        var id       = model.get('id');
        var settings = model.get('settings');
        if (!settings) return null;

        var content = '';
        if (type === 'text-editor') content = settings.get('editor')        || '';
        if (type === 'heading')     content = settings.get('title')         || '';
        if (type === 'button')      content = settings.get('text')          || '';
        if (type === 'icon-box')    content = (settings.get('title_text')   || '') + ' ' + (settings.get('description_text') || '');
        if (type === 'image-box')   content = (settings.get('title_text')   || '') + ' ' + (settings.get('description_text') || '');

        return {
            type:        type,
            content:     $('<div>').html(content).text(),
            widget_id:   id,
            raw_content: content,
        };
    }

    // ── Apply setting to widget ────────────────────────────────────────────

    function applyToWidget(widgetId, field, value) {
        try {
            var panel = elementor.getPanelView();
            if (!panel) throw new Error('No panel');

            var view = panel.getCurrentPageView ? panel.getCurrentPageView() : null;
            if (!view || !view.model) throw new Error('No model');

            if (typeof $e !== 'undefined' && $e.run) {
                $e.run('document/elements/settings', {
                    container: elementor.getContainer ? elementor.getContainer(widgetId) : view,
                    settings:  { [field]: value },
                    options:   { external: true },
                });
            } else {
                view.model.get('settings').set(field, value);
                if (elementor.saver) elementor.saver.setFlagEditorChange();
            }
            return true;
        } catch(e) {
            console.warn('[Siloq WI] Apply failed:', e);
            return false;
        }
    }

    // ── Initialise page map after editor loads ────────────────────────────

    elementor.on('document:loaded', function() {
        setTimeout(function() {
            pageMap = buildPageMap();
        }, 1000);
    });

    // ── Analyze button (delegated — panel re-renders on widget switch) ────

    $(document).on('click', '.siloq-wi-analyze-btn', function() {
        // Always rebuild map so heading changes are reflected
        pageMap = buildPageMap();

        var $container = $(this).closest('.siloq-wi-container');
        var $loading   = $container.find('.siloq-wi-loading');
        var $results   = $container.find('.siloq-wi-results');

        $container.find('.siloq-wi-analyze-btn')
            .prop('disabled', true)
            .text('Analyzing...');
        $loading.show();
        $results.hide();

        var activeWidget = getActiveWidgetContent();
        if (!activeWidget) {
            $loading.hide();
            $container.find('.siloq-wi-analyze-btn')
                .prop('disabled', false)
                .text('⚡ Analyze This Widget');
            showWIStatus($container, 'Could not read widget content.', 'error');
            return;
        }

        var containerCtx = getContainerContext(activeWidget.widget_id);
        var payload = {
            active_widget: {
                type:      activeWidget.type,
                content:   activeWidget.content,
                widget_id: activeWidget.widget_id,
            },
            container_context: containerCtx,
            page_heading_map:  pageMap ? pageMap.headingMap : [],
            page_id:           cfg.postId,
            site_id:           cfg.siteId,
        };

        $.ajax({
            url:  cfg.ajaxUrl,
            type: 'POST',
            data: {
                action:  'siloq_analyze_widget',
                nonce:   cfg.nonce,
                page_id: cfg.postId,
                payload: payload,
            },
            success: function(res) {
                $loading.hide();
                $container.find('.siloq-wi-analyze-btn')
                    .prop('disabled', false)
                    .text('⚡ Re-analyze');

                if (!res.success || !res.data) {
                    showWIStatus($container, 'Analysis failed.', 'error');
                    return;
                }

                renderResults($container, res.data, activeWidget);
            },
            error: function() {
                $loading.hide();
                $container.find('.siloq-wi-analyze-btn')
                    .prop('disabled', false)
                    .text('⚡ Analyze This Widget');
                showWIStatus($container, 'Network error. Check your connection.', 'error');
            },
        });
    });

    // ── Render analysis results ───────────────────────────────────────────

    function renderResults($container, data, activeWidget) {
        var $results = $container.find('.siloq-wi-results');

        // Layer badge
        var layerColors = { hub: '#4f46e5', spoke: '#0891b2', supporting: '#059669' };
        var layerLabel  = { hub: 'Hub Page', spoke: 'Spoke Page', supporting: 'Supporting Content' };
        var layer = data.layer || 'spoke';
        $container.find('.siloq-wi-layer-badge').html(
            '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;' +
            'background:' + (layerColors[layer] || '#6b7280') + '20;' +
            'color:'      + (layerColors[layer] || '#6b7280') + '">' +
            (layerLabel[layer] || layer.toUpperCase()) + '</span>'
        );

        // Layer advisory notes
        var $violations = $container.find('.siloq-wi-violations');
        $violations.empty();
        (data.layer_violations || []).forEach(function(v) {
            $violations.append(
                '<p style="font-size:11px;color:#92400e;background:#fef3c7;padding:6px 8px;border-radius:5px;margin:4px 0;">' +
                '⚠️ ' + esc(typeof v === 'string' ? v : (v.issue || '')) + '</p>'
            );
        });

        // Heading structure violations
        var $hwarn = $container.find('.siloq-wi-heading-warnings');
        $hwarn.empty();
        (data.heading_violations || []).forEach(function(v) {
            $hwarn.append(
                '<p style="font-size:11px;color:#92400e;background:#fef3c7;padding:6px 8px;border-radius:5px;margin:4px 0;">' +
                '⚠️ ' + esc(v.issue || '') + ' → ' + esc(v.fix || '') + '</p>'
            );
        });

        // Content suggestion
        var suggestion = data.suggested_content || '';
        $container.find('.siloq-wi-suggestion-text').text(suggestion);
        $container.find('.siloq-wi-apply-btn').data({
            'widget-id':  activeWidget.widget_id,
            'suggestion': suggestion,
            'widget-type': activeWidget.type,
        });

        // Heading tag recommendation (heading widgets only)
        if (activeWidget.type === 'heading' && data.suggested_heading_tag) {
            $container.find('.siloq-wi-heading-tag').show();
            $container.find('.siloq-wi-tag-display').html(
                '<span style="font-size:13px;font-weight:700;color:#4f46e5;">' +
                data.suggested_heading_tag.toUpperCase() + '</span>' +
                '<button class="siloq-wi-apply-tag-btn"' +
                ' data-widget-id="' + esc(activeWidget.widget_id) + '"' +
                ' data-tag="' + esc(data.suggested_heading_tag) + '"' +
                ' style="margin-left:8px;font-size:11px;padding:2px 8px;background:#ede9fe;color:#4f46e5;' +
                'border:none;border-radius:4px;cursor:pointer;">Apply Tag</button>'
            );
        } else {
            $container.find('.siloq-wi-heading-tag').hide();
        }

        // Image recommendations
        var $imgBlock = $container.find('.siloq-wi-image-block');
        var $imgRecs  = $container.find('.siloq-wi-image-recs');
        $imgRecs.empty();
        var recs = data.image_recommendations || [];

        if (recs.length > 0) {
            $imgBlock.show();
            recs.forEach(function(rec) {
                $imgRecs.append(
                    '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:10px;margin-bottom:8px;font-size:11px;">' +
                    '<p style="margin:0 0 4px;font-weight:600;color:#0369a1;">📸 ' + esc(rec.position || 'Image') + '</p>' +
                    '<p style="margin:0 0 2px;color:#374151;"><strong>Subject:</strong> ' + esc(rec.subject || '') + '</p>' +
                    '<p style="margin:0 0 2px;color:#374151;"><strong>Filename:</strong> ' + esc(rec.suggested_filename || '') + '</p>' +
                    '<p style="margin:0 0 6px;color:#374151;"><strong>Alt tag:</strong> ' + esc(rec.suggested_alt || '') + '</p>' +
                    '<button class="siloq-wi-gen-image-btn"' +
                    ' data-prompt="'   + esc(rec.ai_prompt          || '') + '"' +
                    ' data-filename="' + esc(rec.suggested_filename  || '') + '"' +
                    ' data-alt="'      + esc(rec.suggested_alt       || '') + '"' +
                    ' style="font-size:11px;padding:4px 10px;background:#4f46e5;color:#fff;border:none;border-radius:5px;cursor:pointer;">' +
                    '🎨 Generate Image</button>' +
                    '</div>'
                );
            });
        } else {
            $imgBlock.hide();
        }

        $results.show();
    }

    // ── Apply content suggestion ──────────────────────────────────────────

    $(document).on('click', '.siloq-wi-apply-btn', function() {
        var $btn       = $(this);
        var widgetId   = $btn.data('widget-id');
        var suggestion = $btn.data('suggestion');
        var widgetType = $btn.data('widget-type');

        var fieldMap = {
            'text-editor': 'editor',
            'heading':     'title',
            'button':      'text',
            'icon-box':    'title_text',
            'image-box':   'title_text',
        };
        var field = fieldMap[widgetType] || 'editor';

        var ok = applyToWidget(widgetId, field, suggestion);
        if (ok) {
            $btn.text('✅ Applied').prop('disabled', true);
        } else {
            if (navigator.clipboard) navigator.clipboard.writeText(suggestion);
            $btn.text('📋 Copied to clipboard');
        }
    });

    // ── Apply heading tag ─────────────────────────────────────────────────

    $(document).on('click', '.siloq-wi-apply-tag-btn', function() {
        var widgetId = $(this).data('widget-id');
        var tag      = $(this).data('tag');
        var ok       = applyToWidget(widgetId, 'header_size', tag);
        $(this).text(ok ? '✅ Tag Applied' : '⚠️ Apply manually').prop('disabled', true);
    });

    // ── Skip results ──────────────────────────────────────────────────────

    $(document).on('click', '.siloq-wi-skip-btn', function() {
        $(this).closest('.siloq-wi-results').hide();
    });

    // ── Image generation modal ────────────────────────────────────────────

    /**
     * siloqImageGenerator.generate()
     * Stub for future AI image API wiring (DALL-E, Midjourney, etc.).
     * Currently shows a modal with a ready-to-paste prompt.
     */
    window.siloqImageGenerator = {
        generate: function(prompt, filename, alt, widgetId) {
            var modal = $(
                '<div class="siloq-wi-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;' +
                'background:rgba(0,0,0,0.5);z-index:99999999;display:flex;align-items:center;justify-content:center;">' +
                '<div style="background:#fff;border-radius:12px;padding:24px;width:480px;max-width:90vw;">' +
                '<h3 style="margin:0 0 16px;font-size:16px;font-weight:700;">🎨 Generate Image</h3>' +
                '<p style="font-size:12px;color:#6b7280;margin:0 0 8px;">Copy this prompt into Midjourney, DALL-E, or your preferred image generator:</p>' +
                '<textarea style="width:100%;height:80px;padding:8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;margin-bottom:12px;" readonly>' +
                esc(prompt) + '</textarea>' +
                '<p style="font-size:12px;margin:0 0 4px;"><strong>Save as:</strong> ' + esc(filename) + '</p>' +
                '<p style="font-size:12px;margin:0 0 16px;"><strong>Alt tag:</strong> ' + esc(alt) + '</p>' +
                '<div style="display:flex;gap:8px;">' +
                '<button class="siloq-wi-modal-copy" style="flex:1;padding:8px;background:#4f46e5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;">📋 Copy Prompt</button>' +
                '<button class="siloq-wi-modal-close" style="flex:1;padding:8px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;font-size:13px;">Close</button>' +
                '</div></div></div>'
            );

            $('body').append(modal);

            modal.find('.siloq-wi-modal-copy').on('click', function() {
                if (navigator.clipboard) navigator.clipboard.writeText(prompt);
                $(this).text('✅ Copied!');
            });
            modal.find('.siloq-wi-modal-close').on('click', function() {
                modal.remove();
            });
            modal.on('click', function(e) {
                if ($(e.target).is(modal)) modal.remove();
            });
        },
    };

    $(document).on('click', '.siloq-wi-gen-image-btn', function() {
        var $btn = $(this);
        siloqImageGenerator.generate(
            $btn.data('prompt'),
            $btn.data('filename'),
            $btn.data('alt'),
            null
        );
    });

    // ── Helpers ───────────────────────────────────────────────────────────

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function(c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function showWIStatus($container, msg, type) {
        var $s = $container.find('.siloq-wi-status');
        if (!$s.length) {
            $s = $('<div class="siloq-wi-status" style="margin-top:8px;padding:6px 8px;border-radius:5px;font-size:11px;"></div>');
            $container.append($s);
        }
        $s.css({
            background: type === 'error' ? '#fef2f2' : '#d1fae5',
            color:      type === 'error' ? '#991b1b' : '#065f46',
        }).text(msg).show();
        setTimeout(function() { $s.fadeOut(); }, 4000);
    }

})(jQuery);
