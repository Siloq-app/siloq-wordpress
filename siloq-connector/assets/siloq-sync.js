/**
 * Siloq Sync Page JavaScript
 * Handles page synchronization functionality
 */

(function($) {
    'use strict';

    // Initialize sync functionality
    $(document).ready(function() {
        initSyncPage();
    });

    function initSyncPage() {
        // Load sync status on page load
        loadSyncStatus();
        
        // Load pages list
        loadPagesList();
        
        // Bind event handlers
        bindSyncEvents();
    }

    function bindSyncEvents() {
        // Sync all pages button
        $('#siloq-sync-all').on('click', function() {
            const $button = $(this);
            if ($button.hasClass('loading')) {
                return;
            }
            
            $button.addClass('loading').prop('disabled', true);
            $button.html('<span class="siloq-spinner"></span> Syncing...');
            
            $.ajax({
                url: siloqAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'siloq_sync_all_pages',
                    nonce: siloqAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('success', response.data.message || 'All pages synced successfully');
                        loadSyncStatus();
                        loadPagesList();
                    } else {
                        showNotification('error', response.data.message || 'Sync failed');
                    }
                },
                error: function() {
                    showNotification('error', 'Network error occurred');
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false);
                    $button.html('<span class="dashicons dashicons-update"></span> Sync All Pages');
                }
            });
        });
        
        // Sync outdated pages button
        $('#siloq-sync-outdated').on('click', function() {
            const $button = $(this);
            if ($button.hasClass('loading')) {
                return;
            }
            
            $button.addClass('loading').prop('disabled', true);
            $button.html('<span class="siloq-spinner"></span> Syncing...');
            
            $.ajax({
                url: siloqAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'siloq_sync_outdated',
                    nonce: siloqAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('success', response.data.message || 'Outdated pages synced successfully');
                        loadSyncStatus();
                        loadPagesList();
                    } else {
                        showNotification('error', response.data.message || 'Sync failed');
                    }
                },
                error: function() {
                    showNotification('error', 'Network error occurred');
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false);
                    $button.html('<span class="dashicons dashicons-clock"></span> Sync Outdated Pages');
                }
            });
        });
        
        // Individual page sync buttons (using event delegation)
        $(document).on('click', '.siloq-page-sync-button', function() {
            const $button = $(this);
            const postId = $button.data('post-id');
            
            if ($button.hasClass('loading')) {
                return;
            }
            
            $button.addClass('loading').prop('disabled', true);
            $button.html('<span class="siloq-spinner"></span> Syncing...');
            
            $.ajax({
                url: siloqAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'siloq_sync_page',
                    post_id: postId,
                    nonce: siloqAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('success', response.data.message || 'Page synced successfully');
                        loadSyncStatus();
                        loadPagesList();
                    } else {
                        showNotification('error', response.data.message || 'Sync failed');
                    }
                },
                error: function() {
                    showNotification('error', 'Network error occurred');
                },
                complete: function() {
                    $button.removeClass('loading').prop('disabled', false);
                    $button.html('Sync');
                }
            });
        });
    }

    function loadSyncStatus() {
        const $statusContainer = $('#siloq-sync-status');
        $statusContainer.html('<p>Loading sync status...</p>');
        
        $.ajax({
            url: siloqAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'siloq_get_sync_status',
                nonce: siloqAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderSyncStatus(response.data);
                } else {
                    $statusContainer.html('<p>Error loading sync status</p>');
                }
            },
            error: function() {
                $statusContainer.html('<p>Network error occurred</p>');
            }
        });
    }

    function renderSyncStatus(data) {
        const $statusContainer = $('#siloq-sync-status');
        
        let html = '<div class="siloq-sync-overview">';
        html += '<div class="siloq-sync-stats">';
        html += '<div class="siloq-sync-stat">';
        html += '<span class="siloq-sync-stat-number">' + (data.total_pages || 0) + '</span>';
        html += '<span class="siloq-sync-stat-label">Total Pages</span>';
        html += '</div>';
        html += '<div class="siloq-sync-stat">';
        html += '<span class="siloq-sync-stat-number">' + (data.synced_pages || 0) + '</span>';
        html += '<span class="siloq-sync-stat-label">Synced</span>';
        html += '</div>';
        html += '<div class="siloq-sync-stat">';
        html += '<span class="siloq-sync-stat-number">' + (data.outdated_pages || 0) + '</span>';
        html += '<span class="siloq-sync-stat-label">Outdated</span>';
        html += '</div>';
        html += '<div class="siloq-sync-stat">';
        html += '<span class="siloq-sync-stat-number">' + (data.failed_pages || 0) + '</span>';
        html += '<span class="siloq-sync-stat-label">Failed</span>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        
        if (data.last_sync) {
            html += '<div class="siloq-last-sync">';
            html += '<p><strong>Last Sync:</strong> ' + data.last_sync + '</p>';
            html += '</div>';
        }
        
        $statusContainer.html(html);
    }

    function loadPagesList() {
        const $pagesContainer = $('#siloq-pages-list');
        $pagesContainer.html('<p>Loading pages...</p>');
        
        $.ajax({
            url: siloqAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'siloq_get_sync_status',
                nonce: siloqAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.pages) {
                    renderPagesList(response.data.pages);
                } else {
                    $pagesContainer.html('<p>No pages found</p>');
                }
            },
            error: function() {
                $pagesContainer.html('<p>Error loading pages</p>');
            }
        });
    }

    function renderPagesList(pages) {
        const $pagesContainer = $('#siloq-pages-list');
        
        if (!pages || pages.length === 0) {
            $pagesContainer.html('<p>No pages found</p>');
            return;
        }
        
        let html = '';
        pages.forEach(function(page) {
            const statusClass = getStatusClass(page.status);
            const statusText = getStatusText(page.status);
            
            html += '<div class="siloq-page-item">';
            html += '<div class="siloq-page-info">';
            html += '<div class="siloq-page-title">' + page.title + '</div>';
            html += '<div class="siloq-page-meta">Last modified: ' + page.modified + '</div>';
            html += '</div>';
            html += '<div class="siloq-page-status">';
            html += '<span class="siloq-status ' + statusClass + '">' + statusText + '</span>';
            html += '<button type="button" class="siloq-page-sync-button siloq-button siloq-button-secondary" data-post-id="' + page.id + '">Sync</button>';
            html += '</div>';
            html += '</div>';
        });
        
        $pagesContainer.html(html);
    }

    function getStatusClass(status) {
        switch (status) {
            case 'synced':
                return 'siloq-status-connected';
            case 'outdated':
                return 'siloq-status-syncing';
            case 'failed':
                return 'siloq-status-disconnected';
            default:
                return 'siloq-status-pending';
        }
    }

    function getStatusText(status) {
        switch (status) {
            case 'synced':
                return 'Synced';
            case 'outdated':
                return 'Outdated';
            case 'failed':
                return 'Failed';
            case 'syncing':
                return 'Syncing';
            default:
                return 'Pending';
        }
    }

    function showNotification(type, message) {
        // Create notification element
        const $notification = $('<div class="siloq-ai-notification siloq-ai-notification-' + type + '">' + message + '</div>');
        
        // Add to page
        $('body').append($notification);
        
        // Show notification
        setTimeout(function() {
            $notification.fadeIn(200);
        }, 100);
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(200, function() {
                $notification.remove();
            });
        }, 5000);
    }

    // Make sure to localize script data
    if (typeof siloqAdmin === 'undefined') {
        window.siloqAdmin = {
            ajaxUrl: ajaxurl,
            nonce: ''
        };
    }

})(jQuery);
