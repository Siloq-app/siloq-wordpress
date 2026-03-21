/**
 * Siloq Sync Page JavaScript
 * Handles page synchronization functionality with full pagination support.
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        loadSyncStatus();
        loadPagesList();
        bindSyncEvents();
    });

    function bindSyncEvents() {

        // ── Sync All Pages (paginated loop) ───────────────────────────────
        $('#siloq-sync-all').on('click', function() {
            var $button = $(this);
            if ($button.hasClass('loading')) return;

            $button.addClass('loading').prop('disabled', true)
                   .html('<span class="siloq-spinner"></span> Syncing...');

            // Progress UI
            if (!$('#siloq-sync-progress-wrap').length) {
                $button.after(
                    '<div id="siloq-sync-progress-wrap" style="margin-top:12px;">' +
                    '<div style="background:#e5e7eb;border-radius:4px;height:8px;width:100%;">' +
                    '<div id="siloq-sync-bar" style="background:#1a56db;height:8px;border-radius:4px;width:0%;transition:width 0.3s;"></div>' +
                    '</div>' +
                    '<p id="siloq-sync-progress-text" style="font-size:13px;color:#555;margin-top:6px;">Starting sync...</p>' +
                    '</div>'
                );
            } else {
                $('#siloq-sync-bar').css('width', '0%');
                $('#siloq-sync-progress-text').text('Starting sync...');
                $('#siloq-sync-progress-wrap').show();
            }

            runBatch(0, 0, 0);

            function runBatch(offset, totalSynced, knownTotal) {
                $.ajax({
                    url: siloqAdmin.ajaxUrl,
                    type: 'POST',
                    timeout: 120000,
                    data: {
                        action: 'siloq_sync_all_pages',
                        nonce:  siloqAdmin.nonce,
                        offset: offset
                    },
                    success: function(response) {
                        if (!response.success) {
                            showNotification('error', (response.data && response.data.message) ? response.data.message : 'Sync failed');
                            finishSync($button);
                            return;
                        }

                        var d          = response.data;
                        var nowSynced  = totalSynced + (d.synced_count || d.synced || 0);
                        var total      = d.total || knownTotal || 1;
                        var nextOffset = d.next_offset || (offset + 50);
                        var pct        = Math.min(100, Math.round((nextOffset / total) * 100));

                        $('#siloq-sync-bar').css('width', pct + '%');
                        $('#siloq-sync-progress-text').text(
                            'Synced ' + Math.min(nextOffset, total) + ' of ' + total + ' pages (' + pct + '%)...'
                        );

                        if (d.has_more) {
                            // Pause 300 ms then fetch the next batch
                            setTimeout(function() {
                                runBatch(nextOffset, nowSynced, total);
                            }, 300);
                        } else {
                            // All done
                            $('#siloq-sync-bar').css('width', '100%');
                            $('#siloq-sync-progress-text').text(
                                'Sync complete — ' + nowSynced + ' pages synced.'
                            );
                            showNotification('success', 'All ' + nowSynced + ' pages synced successfully.');
                            finishSync($button);
                            loadSyncStatus();
                            loadPagesList();
                        }
                    },
                    error: function(xhr) {
                        // On gateway timeout try the same offset once more; abort on other errors
                        if (xhr.status === 504 && offset < 50000) {
                            $('#siloq-sync-progress-text').text(
                                'Batch timed out — retrying offset ' + offset + '...'
                            );
                            setTimeout(function() { runBatch(offset, totalSynced, knownTotal); }, 2000);
                        } else {
                            showNotification('error', 'Network error at offset ' + offset + ' (HTTP ' + xhr.status + ')');
                            finishSync($button);
                        }
                    }
                });
            }

            function finishSync($btn) {
                $btn.removeClass('loading').prop('disabled', false)
                    .html('<span class="dashicons dashicons-update"></span> Sync All Pages');
                setTimeout(function() {
                    $('#siloq-sync-progress-wrap').fadeOut(function() { $(this).remove(); });
                }, 5000);
            }
        });

        // ── Sync Outdated Pages ───────────────────────────────────────────
        $('#siloq-sync-outdated').on('click', function() {
            var $button = $(this);
            if ($button.hasClass('loading')) return;

            $button.addClass('loading').prop('disabled', true)
                   .html('<span class="siloq-spinner"></span> Syncing...');

            $.ajax({
                url: siloqAdmin.ajaxUrl,
                type: 'POST',
                timeout: 120000,
                data: { action: 'siloq_sync_outdated', nonce: siloqAdmin.nonce },
                success: function(response) {
                    if (response.success) {
                        showNotification('success', (response.data && response.data.message) ? response.data.message : 'Outdated pages synced successfully');
                        loadSyncStatus();
                        loadPagesList();
                    } else {
                        showNotification('error', (response.data && response.data.message) ? response.data.message : 'Sync failed');
                    }
                },
                error: function() { showNotification('error', 'Network error occurred'); },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false)
                           .html('<span class="dashicons dashicons-clock"></span> Sync Outdated Pages');
                }
            });
        });

        // ── Individual page sync ─────────────────────────────────────────
        $(document).on('click', '.siloq-page-sync-button', function() {
            var $button = $(this);
            var postId  = $button.data('post-id');
            if ($button.hasClass('loading')) return;

            $button.addClass('loading').prop('disabled', true)
                   .html('<span class="siloq-spinner"></span> Syncing...');

            $.ajax({
                url: siloqAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'siloq_sync_page', post_id: postId, nonce: siloqAdmin.nonce },
                success: function(response) {
                    if (response.success) {
                        showNotification('success', (response.data && response.data.message) ? response.data.message : 'Page synced successfully');
                        loadSyncStatus();
                        loadPagesList();
                    } else {
                        showNotification('error', (response.data && response.data.message) ? response.data.message : 'Sync failed');
                    }
                },
                error: function() { showNotification('error', 'Network error occurred'); },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false).html('Sync');
                }
            });
        });
    }

    function loadSyncStatus() {
        var $c = $('#siloq-sync-status');
        $c.html('<p>Loading sync status...</p>');
        $.ajax({
            url: siloqAdmin.ajaxUrl, type: 'POST',
            data: { action: 'siloq_get_sync_status', nonce: siloqAdmin.nonce },
            success: function(r) {
                if (r.success) renderSyncStatus(r.data);
                else $c.html('<p>Error loading sync status</p>');
            },
            error: function() { $c.html('<p>Network error occurred</p>'); }
        });
    }

    function renderSyncStatus(data) {
        var $c = $('#siloq-sync-status');
        var html = '<div class="siloq-sync-overview"><div class="siloq-sync-stats">';
        html += stat(data.total_pages || 0, 'Total Pages');
        html += stat(data.synced_pages || 0, 'Synced');
        html += stat(data.outdated_pages || 0, 'Outdated');
        html += stat(data.failed_pages || 0, 'Failed');
        html += '</div></div>';
        if (data.last_sync) {
            html += '<div class="siloq-last-sync"><p><strong>Last Sync:</strong> ' + data.last_sync + '</p></div>';
        }
        $c.html(html);
    }

    function stat(n, label) {
        return '<div class="siloq-sync-stat">' +
               '<span class="siloq-sync-stat-number">' + n + '</span>' +
               '<span class="siloq-sync-stat-label">' + label + '</span></div>';
    }

    function loadPagesList() {
        var $c = $('#siloq-pages-list');
        $c.html('<p>Loading pages...</p>');
        $.ajax({
            url: siloqAdmin.ajaxUrl, type: 'POST',
            data: { action: 'siloq_get_sync_status', nonce: siloqAdmin.nonce },
            success: function(r) {
                if (r.success && r.data.pages) renderPagesList(r.data.pages);
                else $c.html('<p>No pages found</p>');
            },
            error: function() { $c.html('<p>Error loading pages</p>'); }
        });
    }

    function renderPagesList(pages) {
        var $c = $('#siloq-pages-list');
        if (!pages || pages.length === 0) { $c.html('<p>No pages found</p>'); return; }
        var html = '';
        pages.forEach(function(page) {
            var sc = statusClass(page.status), st = statusText(page.status);
            html += '<div class="siloq-page-item">' +
                    '<div class="siloq-page-info">' +
                    '<div class="siloq-page-title">' + page.title + '</div>' +
                    '<div class="siloq-page-meta">Last modified: ' + page.modified + '</div></div>' +
                    '<div class="siloq-page-status">' +
                    '<span class="siloq-status ' + sc + '">' + st + '</span>' +
                    '<button type="button" class="siloq-page-sync-button siloq-button siloq-button-secondary" data-post-id="' + page.id + '">Sync</button>' +
                    '</div></div>';
        });
        $c.html(html);
    }

    function statusClass(s) {
        return s === 'synced' ? 'siloq-status-connected' : s === 'outdated' ? 'siloq-status-syncing' : s === 'failed' ? 'siloq-status-disconnected' : 'siloq-status-pending';
    }

    function statusText(s) {
        return s === 'synced' ? 'Synced' : s === 'outdated' ? 'Outdated' : s === 'failed' ? 'Failed' : s === 'syncing' ? 'Syncing' : 'Pending';
    }

    function showNotification(type, message) {
        var $n = $('<div class="siloq-ai-notification siloq-ai-notification-' + type + '">' + message + '</div>');
        $('body').append($n);
        setTimeout(function() { $n.fadeIn(200); }, 100);
        setTimeout(function() { $n.fadeOut(200, function() { $n.remove(); }); }, 5000);
    }

    if (typeof siloqAdmin === 'undefined') {
        window.siloqAdmin = { ajaxUrl: ajaxurl, nonce: '' };
    }

})(jQuery);
