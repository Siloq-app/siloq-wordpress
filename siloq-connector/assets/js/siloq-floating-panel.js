/**
 * siloq-floating-panel.js
 * Edit Content tab — reads Elementor widgets, gets AI suggestions, applies via Elementor JS API.
 * Runs inside the existing #siloq-schema-el-panel tab system.
 */
(function($) {
    'use strict';

    if (typeof siloqContentEditor === 'undefined') return;

    var cfg      = siloqContentEditor;
    var postId   = cfg.postId;
    var widgets  = [];
    var suggestions = {};  // keyed by widget id

    // ── Tab switching ──────────────────────────────────────────────────────
    $(document).on('click', '.siloq-ep-tab', function() {
        var tab = $(this).data('siloq-tab');

        // Update tab button styles
        $('.siloq-ep-tab').css({
            'border-bottom-color': 'transparent',
            'color': '#6b7280'
        });
        $(this).css({
            'border-bottom-color': '#4f46e5',
            'color': '#4f46e5'
        });

        // Show/hide panels
        $('#siloq-edit-content-tab, #siloq-links-tab').hide();
        $('#siloq-schema-el-status, .siloq-schema-actions, #siloq-schema-spinner-el, #siloq-schema-errors-el, #siloq-schema-preview-el').hide();

        if (tab === 'schema') {
            $('#siloq-schema-el-status, .siloq-schema-actions, #siloq-schema-spinner-el, #siloq-schema-errors-el, #siloq-schema-preview-el').show();
        } else if (tab === 'edit-content') {
            $('#siloq-edit-content-tab').show();
        } else if (tab === 'links') {
            $('#siloq-links-tab').show();
        }
    });

    // ── Load widgets ───────────────────────────────────────────────────────
    $(document).on('click', '#siloq-load-widgets-btn', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Loading...');
        $('#siloq-widget-loading').show();
        $('#siloq-widget-list').empty();

        $.ajax({
            url: cfg.ajaxUrl,
            type: 'POST',
            data: {
                action: 'siloq_get_elementor_widgets',
                nonce: cfg.nonce,
                post_id: postId
            },
            success: function(res) {
                $('#siloq-widget-loading').hide();
                $btn.prop('disabled', false).text('📋 Reload Content');

                if (!res.success || !res.data || !res.data.widgets) {
                    showEcStatus('Could not load widgets: ' + (res.data && res.data.message || 'Unknown error'), 'error');
                    return;
                }

                widgets = res.data.widgets;
                if (widgets.length === 0) {
                    $('#siloq-widget-list').html('<p style="font-size:12px;color:#6b7280;text-align:center;padding:20px;">No editable text widgets found on this page.</p>');
                    return;
                }

                renderWidgetList(widgets);
            },
            error: function() {
                $('#siloq-widget-loading').hide();
                $btn.prop('disabled', false).text('📋 Load Page Content');
                showEcStatus('Network error. Please try again.', 'error');
            }
        });
    });

    // ── Render widget list ─────────────────────────────────────────────────
    function renderWidgetList(widgetList) {
        var typeLabels = {
            'heading': '🔤 Heading',
            'text-editor': '📝 Text',
            'button':    '🔘 Button',
            'icon-box':  '📦 Box',
            'image-box': '🖼️ Box',
            'faq-item':  '❓ FAQ'
        };
        var html = '';
        widgetList.forEach(function(w) {
            var label = typeLabels[w.type] || w.type;
            var preview = (w.content_plain || w.content || '').substring(0, 60);
            if ((w.content_plain || w.content || '').length > 60) preview += '…';

            html += '<div class="siloq-widget-item" id="siloq-wi-' + esc(w.id) + '" style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-bottom:8px;background:#fafafa;">';
            html += '<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">';
            html += '<span style="font-size:10px;font-weight:600;padding:2px 6px;background:#ede9fe;color:#4f46e5;border-radius:10px;">' + esc(label) + '</span>';
            html += '</div>';
            html += '<p style="font-size:11px;color:#6b7280;margin:0 0 8px;font-style:italic;">' + esc(preview) + '</p>';

            if (w.readonly || w.type === 'faq-item') {
                // FAQ items: show as read-only with note (apply not supported per-tab)
                html += '<p style="font-size:10px;color:#9ca3af;margin:0;font-style:italic;">✅ Detected for FAQPage schema · Edit in Elementor accordion widget</p>';
            } else {
                html += '<button class="siloq-suggest-btn" data-id="' + esc(w.id) + '" data-type="' + esc(w.type) + '" data-field="' + esc(w.field) + '" data-content="' + encodeURIComponent(w.content || '') + '" style="font-size:11px;padding:4px 10px;background:#fff;border:1px solid #d1d5db;border-radius:5px;cursor:pointer;color:#374151;">💡 Suggest Edit</button>';
                // Suggestion result area
                html += '<div class="siloq-suggestion-result" id="siloq-sr-' + esc(w.id) + '" style="display:none;margin-top:10px;border-top:1px solid #e5e7eb;padding-top:10px;">';
                html += '<p style="font-size:10px;font-weight:600;color:#6b7280;margin:0 0 4px;text-transform:uppercase;">Current</p>';
                html += '<p class="siloq-sr-before" style="font-size:11px;color:#9ca3af;text-decoration:line-through;margin:0 0 8px;"></p>';
                html += '<p style="font-size:10px;font-weight:600;color:#6b7280;margin:0 0 4px;text-transform:uppercase;">Suggested</p>';
                html += '<p class="siloq-sr-after" style="font-size:11px;color:#065f46;background:#d1fae5;padding:6px 8px;border-radius:5px;margin:0 0 8px;"></p>';
                html += '<div style="display:flex;gap:6px;">';
                html += '<button class="siloq-apply-btn" data-id="' + esc(w.id) + '" data-field="' + esc(w.field) + '" style="font-size:11px;padding:4px 10px;background:#4f46e5;color:#fff;border:none;border-radius:5px;cursor:pointer;">✅ Apply</button>';
                html += '<button class="siloq-skip-btn" data-id="' + esc(w.id) + '" style="font-size:11px;padding:4px 10px;background:#fff;border:1px solid #d1d5db;border-radius:5px;cursor:pointer;">Skip</button>';
                html += '</div></div>';
            }
            html += '</div>';
        });
        $('#siloq-widget-list').html(html);
    }

    // ── Suggest edit ───────────────────────────────────────────────────────
    $(document).on('click', '.siloq-suggest-btn', function() {
        var $btn   = $(this);
        var id     = $btn.data('id');
        var type   = $btn.data('type');
        var field  = $btn.data('field');
        var content = decodeURIComponent($btn.data('content') || '');

        $btn.prop('disabled', true).text('Thinking…');

        $.ajax({
            url: cfg.ajaxUrl,
            type: 'POST',
            data: {
                action: 'siloq_suggest_widget_edit',
                nonce: cfg.nonce,
                post_id: postId,
                widget_id: id,
                widget_type: type,
                current_content: content
            },
            success: function(res) {
                $btn.prop('disabled', false).text('💡 Re-suggest');

                if (!res.success || !res.data || !res.data.suggestion) {
                    showEcStatus('Could not generate suggestion.', 'error');
                    return;
                }

                var suggestion = res.data.suggestion;
                suggestions[id] = { suggestion: suggestion, field: field };

                var $sr = $('#siloq-sr-' + id);
                $sr.find('.siloq-sr-before').text(content.substring(0, 120) + (content.length > 120 ? '…' : ''));
                $sr.find('.siloq-sr-after').text(suggestion);
                $sr.find('.siloq-apply-btn').data('suggestion', suggestion);
                $sr.show();
            },
            error: function() {
                $btn.prop('disabled', false).text('💡 Suggest Edit');
                showEcStatus('Network error.', 'error');
            }
        });
    });

    // ── Apply edit via Elementor JS API ───────────────────────────────────
    $(document).on('click', '.siloq-apply-btn', function() {
        var widgetId   = $(this).data('id');
        var field      = $(this).data('field');
        var suggestion = $(this).data('suggestion') || $('#siloq-sr-' + widgetId).find('.siloq-sr-after').text();

        if (!suggestion) {
            showEcStatus('No suggestion to apply.', 'error');
            return;
        }

        applyWidgetEdit(widgetId, field, suggestion);
    });

    // ── Skip ──────────────────────────────────────────────────────────────
    $(document).on('click', '.siloq-skip-btn', function() {
        $('#siloq-sr-' + $(this).data('id')).hide();
    });

    // ── Link preservation ─────────────────────────────────────────────────
    // When the AI suggestion is plain text but the original widget content
    // had <a> tags, re-insert the original links where the anchor text
    // still appears in the new suggestion. Prevents Apply from silently
    // destroying internal links.

    function extractLinks(html) {
        var links = [];
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        var anchors = tmp.querySelectorAll('a');
        anchors.forEach(function(a) {
            var text = (a.textContent || a.innerText || '').trim();
            if (text) {
                links.push({ text: text, outerHTML: a.outerHTML });
            }
        });
        return links;
    }

    function restoreLinks(originalHtml, newText) {
        var links = extractLinks(originalHtml);
        if (!links.length) return newText;

        var result = newText;
        var restored = 0;
        links.forEach(function(link) {
            // Escape special regex chars in anchor text
            var escaped = link.text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var re = new RegExp(escaped, 'i');
            if (re.test(result)) {
                result = result.replace(re, link.outerHTML);
                restored++;
            }
        });

        if (restored < links.length) {
            var missed = links.length - restored;
            showEcStatus(
                '✅ Applied! Note: ' + missed + ' link(s) from the original could not be matched in the new text — add them back manually.',
                'success'
            );
        }

        return result;
    }

    // ── Apply via Elementor JS API ────────────────────────────────────────
    function applyWidgetEdit(widgetId, field, newValue) {
        if (typeof elementor === 'undefined' || !elementor.documents) {
            showEcStatus('Elementor not ready. Try again after the editor finishes loading.', 'error');
            return;
        }

        try {
            var doc = elementor.documents.getCurrent();
            if (!doc) throw new Error('No current document');

            var container = findContainer(doc.container, widgetId);
            if (!container) throw new Error('Widget not found: ' + widgetId);

            // For text-editor widgets: preserve <a> tags from the original HTML.
            // The AI suggestion is plain text — without this, all links are lost.
            var safeValue = newValue;
            if (field === 'editor') {
                var originalHtml = '';
                try {
                    originalHtml = container.model.getSetting('editor') || '';
                } catch(e2) {}
                if (originalHtml) {
                    safeValue = restoreLinks(originalHtml, newValue);
                }
            }

            // Use Elementor command system (safe, undoable)
            if (typeof $e !== 'undefined' && $e.run) {
                $e.run('document/elements/settings', {
                    container: container,
                    settings: { [field]: safeValue },
                    options: { external: true }
                });
            } else {
                // Fallback: direct model set
                container.model.setSetting(field, safeValue);
                if (elementor.saver) elementor.saver.setFlagEditorChange();
            }

            // restoreLinks() shows its own status when links are missed.
            // Show generic success only when no links were involved.
            if (safeValue === newValue) {
                showEcStatus('✅ Applied! Click Save in Elementor to keep the change.', 'success');
            }
            $('#siloq-sr-' + widgetId).find('.siloq-apply-btn').text('✅ Applied').prop('disabled', true);
            setTimeout(function(){ window.location.reload(); }, 1200);

        } catch (e) {
            showEcStatus('Could not apply: ' + e.message + '. Copy the suggestion and paste manually.', 'error');
        }
    }

    // ── Find container recursively ────────────────────────────────────────
    function findContainer(container, targetId) {
        if (!container) return null;
        if (container.id === targetId) return container;
        var children = container.children || [];
        if (typeof children.each === 'function') {
            var found = null;
            children.each(function(child) {
                if (!found) found = findContainer(child, targetId);
            });
            return found;
        }
        for (var i = 0; i < children.length; i++) {
            var f = findContainer(children[i], targetId);
            if (f) return f;
        }
        return null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    function esc(str) {
        return String(str || '').replace(/[&<>"']/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function showEcStatus(msg, type) {
        var $s = $('#siloq-ec-status');
        var bg = type === 'error' ? '#fef2f2' : '#d1fae5';
        var color = type === 'error' ? '#991b1b' : '#065f46';
        $s.css({'background': bg, 'color': color}).text(msg).show();
        setTimeout(function() { $s.fadeOut(); }, 5000);
    }

    // ── Internal Links Tab ───────────────────────────────────────────────
    var linksLoaded = false;

    $(document).on('click', '#siloq-load-links-btn', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Loading...');
        $('#siloq-links-loading').show();
        $('#siloq-links-content').empty();

        $.ajax({
            url: cfg.ajaxUrl,
            type: 'POST',
            data: {
                action: 'siloq_get_internal_links',
                nonce: cfg.nonce,
                post_id: postId
            },
            success: function(res) {
                $('#siloq-links-loading').hide();
                $btn.prop('disabled', false).text('🔗 Reload Link Map');

                if (!res.success || !res.data) {
                    showLinksStatus('Could not load link data: ' + (res.data && res.data.message || 'Unknown error'), 'error');
                    return;
                }

                var shouldLinkTo   = res.data.should_link_to   || [];
                var shouldLinkFrom = res.data.should_link_from || [];

                if (!shouldLinkTo.length && !shouldLinkFrom.length) {
                    $('#siloq-links-content').html(
                        '<p style="font-size:12px;color:#6b7280;text-align:center;padding:20px;">No content structure detected. Sync your pages first.</p>'
                    );
                    return;
                }

                var html = '';

                if (shouldLinkTo.length) {
                    html += '<div style="margin-bottom:16px;">';
                    html += '<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#4f46e5;margin:0 0 8px;">This page should link TO:</p>';
                    shouldLinkTo.forEach(function(page) {
                        html += renderLinkCard(page);
                    });
                    html += '</div>';
                }

                if (shouldLinkFrom.length) {
                    html += '<div>';
                    html += '<p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#4f46e5;margin:0 0 8px;">Pages that should link TO this page:</p>';
                    shouldLinkFrom.forEach(function(page) {
                        html += renderLinkCard(page);
                    });
                    html += '</div>';
                }

                $('#siloq-links-content').html(html);
                linksLoaded = true;
            },
            error: function() {
                $('#siloq-links-loading').hide();
                $btn.prop('disabled', false).text('🔗 Load Link Map');
                showLinksStatus('Network error. Please try again.', 'error');
            }
        });
    });

    function renderLinkCard(page) {
        var title      = page.title       || 'Untitled';
        var url        = page.url         || '#';
        var pageType   = page.page_type   || '';
        var anchor     = page.anchor_text || title;
        var linked     = page.already_linked;

        var typeBadgeColor = '#6b7280';
        var typeBadgeBg    = '#f3f4f6';
        var typeLabel      = pageType === 'apex_hub' ? 'APEX HUB' : pageType.toUpperCase();
        if (pageType === 'apex_hub')   { typeBadgeColor = '#fff'; typeBadgeBg = '#7c3aed'; }
        if (pageType === 'hub')        { typeBadgeColor = '#4f46e5'; typeBadgeBg = '#ede9fe'; }
        if (pageType === 'spoke')      { typeBadgeColor = '#0891b2'; typeBadgeBg = '#cffafe'; }
        if (pageType === 'supporting') { typeBadgeColor = '#059669'; typeBadgeBg = '#d1fae5'; }

        var statusHtml = '';
        if (linked === true) {
            statusHtml = '<span style="font-size:10px;color:#065f46;font-weight:600;">Already linked ✅</span>';
        } else if (linked === false) {
            statusHtml = '<span style="font-size:10px;color:#92400e;font-weight:600;">Not yet linked ⚠️</span>';
        }

        var linkTag = '<a href="' + esc(url) + '">' + esc(anchor) + '</a>';

        var card = '';
        card += '<div style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;margin-bottom:8px;background:#fafafa;">';

        // Title row + badge
        card += '<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">';
        if (typeLabel) {
            card += '<span style="font-size:10px;font-weight:600;padding:2px 6px;background:' + typeBadgeBg + ';color:' + typeBadgeColor + ';border-radius:10px;">' + esc(typeLabel) + '</span>';
        }
        card += '<span style="font-size:12px;font-weight:500;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(title) + '</span>';
        card += '</div>';

        // URL
        card += '<p style="font-size:10px;color:#9ca3af;margin:0 0 4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(url) + '</p>';

        // Anchor text
        card += '<p style="font-size:11px;margin:0 0 6px;">';
        card += '<span style="color:#6b7280;">Anchor: </span>';
        card += '<span style="color:#7c3aed;font-weight:600;background:#ede9fe;padding:1px 5px;border-radius:3px;">' + esc(anchor) + '</span>';
        card += '</p>';

        // Status + Copy button row
        card += '<div style="display:flex;align-items:center;justify-content:space-between;gap:6px;">';
        card += statusHtml;
        card += '<button class="siloq-copy-link-btn" data-link="' + esc(linkTag) + '" style="font-size:10px;padding:3px 8px;background:#fff;border:1px solid #d1d5db;border-radius:5px;cursor:pointer;color:#374151;">📋 Copy Link</button>';
        card += '</div>';

        card += '</div>';
        return card;
    }

    // Copy link HTML to clipboard
    $(document).on('click', '.siloq-copy-link-btn', function() {
        var linkHtml = $(this).data('link');
        var $btn = $(this);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(linkHtml).then(function() {
                $btn.text('✅ Copied!');
                setTimeout(function() { $btn.text('📋 Copy Link'); }, 2000);
            });
        } else {
            // Fallback for older browsers
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(linkHtml).select();
            document.execCommand('copy');
            $temp.remove();
            $btn.text('✅ Copied!');
            setTimeout(function() { $btn.text('📋 Copy Link'); }, 2000);
        }
    });

    function showLinksStatus(msg, type) {
        var $s = $('#siloq-links-status');
        var bg = type === 'error' ? '#fef2f2' : '#d1fae5';
        var color = type === 'error' ? '#991b1b' : '#065f46';
        $s.css({'background': bg, 'color': color}).text(msg).show();
        setTimeout(function() { $s.fadeOut(); }, 5000);
    }

})(jQuery);
