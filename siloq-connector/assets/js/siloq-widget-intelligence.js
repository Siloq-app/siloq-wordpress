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

    // Guard: siloqWI is set by wp_localize_script; elementor by Elementor's editor JS.
    // Even with elementor-editor as a script dependency, elementor JS object may not
    // be instantiated yet. We retry up to 50 times (5 seconds) before giving up.
    if (typeof siloqWI === 'undefined') return;

    if (typeof elementor === 'undefined') {
        // Hook into Elementor's own init event first (most reliable)
        $(window).on('elementor:init', function() {
            initSiloqWI($);
        });
        // Polling fallback for environments where the event fires before our script
        var _retries = 0;
        var _retryTimer = setInterval(function() {
            _retries++;
            if (typeof elementor !== 'undefined') {
                clearInterval(_retryTimer);
                initSiloqWI($);
            } else if (_retries >= 50) {
                clearInterval(_retryTimer);
            }
        }, 100);
        return;
    }

    initSiloqWI($);

    function initSiloqWI($) {

    var cfg            = siloqWI;
    var pageMap        = null;  // Cached page structure
    var _cachedModel   = null;  // Cached from panel/open_editor/widget hook — most reliable source

    // ── Page map builder ──────────────────────────────────────────────────

    function buildPageMap() {
        if (!elementor.documents || !elementor.documents.getCurrent()) return null;

        var doc      = elementor.documents.getCurrent();
        var elements;
        // Elementor 3.x: doc is a Backbone Model — use .get(). Some builds return
        // a plain object without Backbone methods; fall back to elementor.elements.
        if (doc && typeof doc.get === 'function') {
            elements = doc.get('elements');
        } else if (typeof elementor.elements !== 'undefined') {
            elements = elementor.elements;
        } else {
            console.warn('[Siloq WI] buildPageMap: cannot access elements collection.');
            return null;
        }
        if (!elements) return null;
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

    // ── Widget model caching — hook fires when panel opens for a widget ────
    // This is the RELIABLE source. Elementor passes the view directly.
    // We cache it here so getActiveWidgetContent() always has it at click time.

    function cacheWidgetModel(view) {
        if (view && view.model) {
            _cachedModel = view.model;
        }
    }

    // Hook into Elementor's panel open event (fires every time a widget is selected)
    if (elementor.hooks) {
        elementor.hooks.addAction('panel/open_editor/widget', function(panel, model, view) {
            // Elementor passes (panel, model, view) — model is the widget model directly
            if (model) { _cachedModel = model; return; }
            cacheWidgetModel(view);
        });
    }

    // Backup: also hook the editor channel event
    if (elementor.channels && elementor.channels.editor) {
        elementor.channels.editor.on('change', function() {
            try {
                var panel = elementor.getPanelView && elementor.getPanelView();
                if (panel) {
                    var view = panel.currentPageView || (panel.getCurrentPageView && panel.getCurrentPageView());
                    if (view && view.model) _cachedModel = view.model;
                }
            } catch(e) {}
        });
    }

    // ── Active widget reader ──────────────────────────────────────────────
    // Priority: cached model from hook → live panel APIs → DOM fallback

    function getModelFromPanel() {
        // 1. Use hook-cached model (most reliable — set when panel opened)
        if (_cachedModel) return _cachedModel;

        try {
            // 2. Elementor 3.x selection API
            if (elementor.selection && elementor.selection.getElements) {
                var els = elementor.selection.getElements();
                if (els && els.length) return els[0].model || els[0];
            }
        } catch(e) {}

        try {
            // 3. panel currentPageView (Elementor 2.x / early 3.x)
            var panel = elementor.getPanelView && elementor.getPanelView();
            if (panel) {
                var view = panel.currentPageView || (panel.getCurrentPageView && panel.getCurrentPageView());
                if (view && view.model) return view.model;
                if (view && view.currentView && view.currentView.model) return view.currentView.model;
            }
        } catch(e) {}

        return null;
    }

    function readContentFromDOM() {
        // Last-resort: read visible content from the Elementor panel DOM directly.
        // Works regardless of JS API version.
        var $panel    = $('#elementor-panel');
        var widgetType = 'text-editor';
        var content    = '';
        var widgetId   = 'dom_' + Date.now();

        // Try to detect widget type from panel heading
        var panelTitle = $panel.find('.elementor-panel-navigation-tab.elementor-active, .elementor-editor-element-title').text().trim().toLowerCase();
        if (panelTitle.indexOf('heading') >= 0)   widgetType = 'heading';
        if (panelTitle.indexOf('icon')    >= 0)   widgetType = 'icon-box';
        if (panelTitle.indexOf('image')   >= 0)   widgetType = 'image-box';

        // Read from visible inputs — TinyMCE textarea, plain textarea, or input
        var $editor = $panel.find('.wp-editor-area:visible, textarea.elementor-input:visible').first();
        if ($editor.length)  content = $editor.val() || '';

        if (!content) {
            var $input = $panel.find('input[type="text"]:visible').first();
            if ($input.length) content = $input.val() || '';
        }

        // For heading widgets: check the title input field name
        if (!content) {
            var $title = $panel.find('[data-setting="title"]:visible, [data-setting="heading"]:visible').first();
            if ($title.length) content = $title.val() || '';
        }

        return { type: widgetType, content: content, widget_id: widgetId, raw_content: content };
    }

    function getActiveWidgetContent() {
        var model = getModelFromPanel();

        if (model) {
            var type     = model.get('widgetType') || model.get('elType');
            var id       = model.get('id');
            var settings = model.get('settings');

            if (settings && type) {
                var content = '';
                if (type === 'text-editor') content = settings.get('editor')       || '';
                if (type === 'heading')     content = settings.get('title')        || '';
                if (type === 'button')      content = settings.get('text')         || '';
                if (type === 'icon-box')    content = (settings.get('title_text')  || '') + ' ' + (settings.get('description_text') || '');
                if (type === 'image-box')   content = (settings.get('title_text')  || '') + ' ' + (settings.get('description_text') || '');

                return {
                    type:        type,
                    // Preserve HTML for text-editor so bullets/lists survive round-trip.
                    // For heading/button strip tags (they store plain text natively).
                    content:     (type === 'text-editor') ? content : $('<div>').html(content).text(),
                    widget_id:   id,
                    raw_content: content,
                };
            }
        }

        // All model strategies failed — fall back to DOM reading
        console.warn('[Siloq WI] Model read failed, falling back to DOM.');
        return readContentFromDOM();
    }

    // ── Apply setting to widget ────────────────────────────────────────────

    function applyToWidget(widgetId, field, value) {
        try {
            var model = getModelFromPanel();
            if (!model) throw new Error('No model');

            if (typeof $e !== 'undefined' && $e.run) {
                $e.run('document/elements/settings', {
                    container: elementor.getContainer ? elementor.getContainer(widgetId) : model,
                    settings:  { [field]: value },
                    options:   { external: true },
                });
            } else {
                model.get('settings').set(field, value);
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

    // ── Panel position handled by CSS sticky (see siloq-widget-intelligence.css)
    // .elementor-control-section[data-section="siloq_intelligence"] { position: sticky; top: 0 }
    // No DOM manipulation needed — CSS pins it to the top of the scrollable panel.

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
        // Never bail out — if we couldn't read content, still run analysis.
        // The server-side local fallback returns layer/heading advice even with empty content.
        if (!activeWidget) {
            activeWidget = {
                type:        $container.data('widget-type') || 'text-editor',
                content:     '',
                widget_id:   'unknown_' + Date.now(),
                raw_content: '',
            };
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
            url:         cfg.ajaxUrl,
            type:        'POST',
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            data: {
                action:  'siloq_analyze_widget',
                nonce:   cfg.nonce,
                page_id: cfg.postId,
                payload: JSON.stringify(payload),   // stringify so nested arrays survive WP serialization
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
        var layerColors = { apex_hub: '#7c3aed', hub: '#4f46e5', spoke: '#0891b2', supporting: '#059669' };
        var layerLabel  = { apex_hub: 'Apex Hub', hub: 'Hub Page', spoke: 'Spoke Page', supporting: 'Supporting Content' };
        var layer = data.layer || 'spoke';
        var lBg = layer === 'apex_hub' ? '#7c3aed' : (layerColors[layer] || '#6b7280') + '20';
        var lFg = layer === 'apex_hub' ? '#fff' : (layerColors[layer] || '#6b7280');
        $container.find('.siloq-wi-layer-badge').html(
            '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;' +
            'background:' + lBg + ';' +
            'color:'      + lFg + '">' +
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

        // Content suggestion with similarity check
        var suggestion = data.suggested_content || '';

        function stripHtmlForCompare(html) {
            return (html || '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim().toLowerCase();
        }

        var currentContent = activeWidget ? (activeWidget.raw_content || activeWidget.content || '') : '';
        var currentClean   = stripHtmlForCompare(currentContent);
        var suggestedClean = stripHtmlForCompare(suggestion);

        function similarityScore(a, b) {
            if (!a || !b) return 0;
            var aWords = a.split(' ').filter(Boolean);
            var bWords = b.split(' ').filter(Boolean);
            if (!aWords.length) return 0;
            var matches = aWords.filter(function(w) { return bWords.indexOf(w) !== -1; }).length;
            return matches / aWords.length;
        }

        var isTooSimilar = (currentClean === suggestedClean) || similarityScore(currentClean, suggestedClean) >= 0.80;

        if (isTooSimilar || !suggestion || data.no_suggestion_reason) {
            $container.find('.siloq-wi-suggestion-text').html(
                '<em style="color:#6b7280;font-size:12px;">' +
                (data.no_suggestion_reason || 'This content is well-optimized — no changes needed, or connect to Siloq API for AI-powered suggestions.') +
                '</em>'
            );
            $container.find('.siloq-wi-apply-btn').hide();
        } else {
            $container.find('.siloq-wi-suggestion-text').html(suggestion);
            $container.find('.siloq-wi-apply-btn').show();
        }

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

        // For text-editor widgets, TinyMCE must be used to preserve HTML structure
        // (ul/li, strong, etc.). Elementor's settings API alone strips formatting.
        var ok = false;
        if (widgetType === 'text-editor') {
            // Try TinyMCE first — it's the only way to preserve HTML in text-editor
            var editorId = 'elementor-controls-editor-' + widgetId;
            var tmce = (typeof tinymce !== 'undefined') ? tinymce.get(editorId) : null;
            if (!tmce) {
                // Elementor sometimes uses a generic ID — check all active editors
                if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                    tmce = tinymce.activeEditor;
                }
            }
            if (tmce) {
                tmce.setContent(suggestion);
                tmce.fire('change');
                // Sync back to Elementor model so save picks it up
                applyToWidget(widgetId, field, suggestion);
                ok = true;
            } else {
                // TinyMCE not ready — fall back to Elementor settings API
                ok = applyToWidget(widgetId, field, suggestion);
            }
        } else {
            ok = applyToWidget(widgetId, field, suggestion);
        }

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

    // ── Image generation via DALL-E API ─────────────────────────────────

    window.siloqImageGenerator = {
        generate: function(prompt, filename, alt, widgetId) {
            var $genBtn = $('.siloq-wi-gen-image-btn').filter(function() {
                return $(this).data('prompt') === prompt;
            }).first();
            if (!$genBtn.length) $genBtn = $('.siloq-wi-gen-image-btn').first();

            $genBtn.text('Generating...').prop('disabled', true);

            $.ajax({
                url: siloqWI.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'siloq_generate_and_insert_image',
                    nonce: siloqWI.nonce,
                    prompt: prompt,
                    filename: filename,
                    alt_text: alt,
                    post_id: siloqWI.postId || 0
                },
                timeout: 90000,
                success: function(resp) {
                    if (resp.success && resp.data && resp.data.url) {
                        $genBtn.text('Image Added to Media Library').prop('disabled', true);
                        var $container = $genBtn.closest('div');
                        $container.append(
                            '<div style="margin-top:8px;">' +
                            '<img src="' + resp.data.url + '" style="max-width:100%;border-radius:6px;border:1px solid #e5e7eb;" />' +
                            '<p style="font-size:10px;color:#6b7280;margin:4px 0 0;">Added to media library. Insert it from Media Library or use attachment ID: ' + (resp.data.attachment_id || '') + '</p>' +
                            '</div>'
                        );
                    } else {
                        $genBtn.text((resp.data && resp.data.message ? resp.data.message : 'Generation failed')).prop('disabled', false);
                    }
                },
                error: function() {
                    $genBtn.text('Request timed out — try again').prop('disabled', false);
                }
            });
        }
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

    } // end initSiloqWI

})(jQuery);
