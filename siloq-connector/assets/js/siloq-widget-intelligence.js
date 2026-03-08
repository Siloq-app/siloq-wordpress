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

                // Dynamic widget (JetEngine, ACF, etc.) — show info panel instead
                if (res.data.is_dynamic_widget) {
                    var source = res.data.listing_source || 'custom post type';
                    var posts = res.data.cpt_posts || [];
                    var postsHtml = '';
                    if (posts.length > 0) {
                        postsHtml = '<ul style="margin:6px 0;padding-left:16px;font-size:11px;">';
                        posts.forEach(function(p) {
                            postsHtml += '<li><strong>' + esc(p.title) + '</strong> — ' + esc(p.excerpt) + '</li>';
                        });
                        postsHtml += '</ul>';
                    }
                    var $results = $container.find('.siloq-wi-results');
                    $results.html(
                        '<div style="background:#fffbeb;border:1px solid #fbbf24;border-radius:6px;padding:12px;font-size:12px;">' +
                        '<p style="margin:0 0 6px;font-weight:700;color:#92400e;">⚡ Dynamic Widget Detected</p>' +
                        '<p style="margin:0 0 6px;color:#78350f;">This is a <strong>' + esc(res.data.widget_type) + '</strong> widget pulling content from <strong>' + esc(source) + '</strong>.</p>' +
                        '<p style="margin:0 0 6px;color:#78350f;">Siloq cannot rewrite dynamic content directly. To optimize, edit the individual ' + esc(source) + ' posts in the WordPress editor.</p>' +
                        (postsHtml ? '<p style="margin:0 0 4px;font-weight:600;color:#92400e;">Posts in this listing:</p>' + postsHtml : '') +
                        '</div>'
                    ).show();
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
                    '<div style="background:#fffbeb;border:1px solid #fbbf24;border-radius:4px;padding:6px 8px;margin-bottom:6px;font-size:10px;color:#92400e;">' +
                    '<strong>📸 Real photos perform better.</strong> Google E-E-A-T rewards authentic images. If you have job photos, upload them to your Media Library and assign them to this page for best results.' +
                    '</div>' +
                    '<button class="siloq-wi-gen-image-btn"' +
                    ' data-prompt="'   + esc(rec.ai_prompt          || '') + '"' +
                    ' data-filename="' + esc(rec.suggested_filename  || '') + '"' +
                    ' data-alt="'      + esc(rec.suggested_alt       || '') + '"' +
                    ' style="font-size:11px;padding:4px 10px;background:#4f46e5;color:#fff;border:none;border-radius:5px;cursor:pointer;">' +
                    '🎨 Generate Image (AI)</button>' +
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
                            '<p style="font-size:10px;color:#b45309;background:#fffbeb;padding:4px 6px;border-radius:4px;margin:4px 0 0;">⚠️ AI-generated — consider replacing with a real job photo for stronger Google E-E-A-T trust signals.</p>' +
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

    // ── Internal Links in Intelligence Panel ─────────────────────────────

    $(document).on('click', '.siloq-wi-links-load-btn', function() {
        var $btn      = $(this);
        var $section  = $btn.closest('.siloq-wi-links-section');
        var $loading  = $section.find('.siloq-wi-links-loading');
        var $content  = $section.find('.siloq-wi-links-content');
        var $status   = $section.find('.siloq-wi-links-status');
        var postId    = cfg.postId;

        $btn.prop('disabled', true).text('Loading...');
        $loading.show();
        $content.hide().empty();
        $status.hide();

        $.ajax({
            url:  cfg.ajaxUrl,
            type: 'POST',
            data: {
                action:  'siloq_get_internal_links',
                nonce:   cfg.nonce,
                post_id: postId,
            },
            success: function(res) {
                $loading.hide();
                $btn.prop('disabled', false).text('Refresh');

                if (!res.success || !res.data) {
                    $status.text('⚠️ ' + ((res.data && res.data.message) || 'Could not load link data'))
                           .css({'background':'#fef2f2','color':'#991b1b'}).show();
                    return;
                }

                var linkTo   = res.data.should_link_to   || [];
                var linkFrom = res.data.should_link_from || [];

                if (!linkTo.length && !linkFrom.length) {
                    $content.html('<p style="font-size:11px;color:#6b7280;padding:4px 0;margin:0;">No silo structure detected. Sync pages first.</p>').show();
                    return;
                }

                var html = '';

                if (linkTo.length) {
                    html += '<div style="margin-bottom:10px;">';
                    html += '<p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#4f46e5;margin:0 0 5px;">This page should link to:</p>';
                    linkTo.forEach(function(p) {
                        html += renderWILinkRow(p);
                    });
                    html += '</div>';
                }

                if (linkFrom.length) {
                    html += '<div>';
                    html += '<p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#4f46e5;margin:0 0 5px;">Should link to this page:</p>';
                    linkFrom.forEach(function(p) {
                        html += renderWILinkRow(p);
                    });
                    html += '</div>';
                }

                $content.html(html).show();
            },
            error: function() {
                $loading.hide();
                $btn.prop('disabled', false).text('Retry');
                $status.text('⚠️ Network error — check your connection.')
                       .css({'background':'#fef2f2','color':'#991b1b'}).show();
            }
        });
    });

    function renderWILinkRow(page) {
        var linked = page.already_linked;
        var isHub  = page.hub_link === true;
        var typeColors = {apex_hub:'#7c3aed', hub:'#4f46e5', spoke:'#0891b2', supporting:'#059669', orphan:'#9ca3af'};
        var tColor = typeColors[page.page_type] || '#6b7280';
        var applyBtn = '';
        if (!linked) {
            var encodedUrl    = esc(page.url    || '');
            var encodedAnchor = esc(page.anchor_text || page.title || '');
            var hubFlag = isHub ? ' data-is-hub="1"' : '';
            applyBtn = '<button class="siloq-link-apply-btn" '
                + 'data-url="' + encodedUrl + '" '
                + 'data-anchor="' + encodedAnchor + '"'
                + hubFlag
                + ' style="margin-top:3px;font-size:10px;padding:2px 8px;background:#4f46e5;color:#fff;border:none;border-radius:3px;cursor:pointer;white-space:nowrap;">'
                + (isHub ? '⭐ Insert Hub Link' : 'Insert Link')
                + '</button>';
        }
        return '<div class="siloq-link-row" style="display:flex;align-items:flex-start;gap:6px;padding:6px 0;border-bottom:1px solid #f3f4f6;'
            + (isHub ? 'background:#f5f3ff;margin:0 -8px;padding:6px 8px;border-left:3px solid #7c3aed;' : '') + '">'
            + '<span style="margin-top:2px;font-size:10px;">' + (linked ? '✅' : '⬜') + '</span>'
            + '<div style="flex:1;min-width:0;">'
            + '<a href="' + esc(page.url || '#') + '" target="_blank" style="font-size:11px;font-weight:' + (isHub ? '700' : '600') + ';color:#1e40af;text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'
            + esc(page.title) + (isHub ? ' <span style="font-size:9px;color:#7c3aed;">(hub)</span>' : '') + '</a>'
            + '<span style="font-size:10px;color:' + tColor + ';font-weight:600;">' + esc(page.page_type || '') + '</span>'
            + (page.anchor_text && !linked ? '<span style="font-size:10px;color:#6b7280;"> · "' + esc(page.anchor_text) + '"</span>' : '')
            + '<br>' + applyBtn
            + '</div>'
            + '</div>';
    }

    // ── Insert link into Elementor content ────────────────────────────────
    // Finds anchor text in text-editor widgets and wraps it with <a href>.
    // Falls back to appending a "See also" section if anchor text not found.
    $(document).on('click', '.siloq-link-apply-btn', function() {
        var $btn    = $(this);
        var url     = $btn.data('url');
        var anchor  = $btn.data('anchor');
        var isHub   = $btn.data('is-hub');

        if (!url || !anchor) return;
        $btn.text('Inserting...').prop('disabled', true);

        var applied = siloqInsertLink(url, String(anchor), !!isHub);

        if (applied === true) {
            $btn.text('✅ Inserted').css({'background':'#059669'});
            $btn.closest('.siloq-link-row').find('> span:first-child').text('✅');
            // Mark Elementor as changed
            if (window.elementor && elementor.saver) {
                elementor.saver.setFlagEditorChange(true);
            }
        } else if (applied === 'exists') {
            $btn.text('Already linked').css({'background':'#6b7280'});
        } else {
            // Not found in content — offer append
            $btn.text('Not found in content').css({'background':'#f59e0b','color':'#1c1917'});
            var $append = $('<button style="margin-left:4px;font-size:10px;padding:2px 6px;background:#e0e7ff;color:#3730a3;border:none;border-radius:3px;cursor:pointer;">Add at bottom?</button>');
            $btn.after($append);
            $append.on('click', function() {
                var appended = siloqAppendLink(url, anchor);
                if (appended) {
                    $btn.text('✅ Added at bottom').css({'background':'#059669','color':'#fff'});
                    $append.remove();
                    if (window.elementor && elementor.saver) elementor.saver.setFlagEditorChange(true);
                } else {
                    $append.text('Could not add — no text widget found').css({'background':'#fef2f2','color':'#991b1b'});
                }
            });
            $btn.prop('disabled', false);
        }
    });

    /**
     * Walk Elementor model elements and insert <a href> around first occurrence of anchorText.
     * Returns true if inserted, 'exists' if already linked, false if not found.
     */
    function siloqInsertLink(url, anchorText, preferLast) {
        if (!window.elementor) return false;
        var container = elementor.getPreviewContainer ? elementor.getPreviewContainer() : null;
        if (!container) return false;

        var result = false;
        var alreadyLinked = false;

        function escRegex(s) {
            return s.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
        }

        function walkElements(elements) {
            if (!elements || !elements.length) return;
            elements.each(function(element) {
                if (result) return;
                var widgetType = element.get('widgetType');
                // Target: text-editor, heading (for anchor), theme-post-content
                if (widgetType === 'text-editor' || widgetType === 'theme-post-content') {
                    var content = element.getSetting('editor') || '';
                    if (!content) return;

                    // Already has this exact link?
                    var linkPattern = new RegExp('<a[^>]+href=["\']' + escRegex(url) + '["\'][^>]*>', 'i');
                    if (linkPattern.test(content)) {
                        alreadyLinked = true;
                        return;
                    }

                    // Find anchor text not already inside an <a> tag
                    // Regex: anchorText surrounded by word boundaries, not inside HTML tag
                    var searchRegex = new RegExp('(?<![">])\\b(' + escRegex(anchorText) + ')\\b(?![^<]*>)', 'i');
                    if (searchRegex.test(content)) {
                        var newContent = content.replace(searchRegex, '<a href="' + url + '">$1</a>');
                        element.setSetting('editor', newContent);
                        result = true;
                    }
                }
                var children = element.get('elements');
                if (children && children.length) walkElements(children);
            });
        }

        try {
            walkElements(container.model.get('elements'));
        } catch(e) {
            console.error('Siloq link insert error:', e);
        }

        if (alreadyLinked) return 'exists';
        return result;
    }

    /**
     * Append a "See also: [anchor](url)" paragraph to the last text-editor widget.
     */
    function siloqAppendLink(url, anchorText) {
        if (!window.elementor) return false;
        var container = elementor.getPreviewContainer ? elementor.getPreviewContainer() : null;
        if (!container) return false;

        var lastTextWidget = null;

        function walkForLast(elements) {
            if (!elements || !elements.length) return;
            elements.each(function(element) {
                if (element.get('widgetType') === 'text-editor') lastTextWidget = element;
                var children = element.get('elements');
                if (children && children.length) walkForLast(children);
            });
        }

        try {
            walkForLast(container.model.get('elements'));
        } catch(e) { return false; }

        if (!lastTextWidget) return false;

        var current = lastTextWidget.getSetting('editor') || '';
        var appendHtml = '\n<p>See also: <a href="' + url + '">' + anchorText + '</a></p>';
        lastTextWidget.setSetting('editor', current + appendHtml);
        return true;
    }

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
