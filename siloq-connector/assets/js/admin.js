/**
 * Siloq Connector Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /**
         * Test API Connection
         */
        $('#siloq-test-connection').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $form = $button.closest('form');
            const $status = $('#siloq-connection-status');
            const DEFAULT_API_URL = 'https://api.siloq.ai/api/v1';
            // Read from the same form by name (works even if IDs differ)
            const apiUrl = (($form.length ? $form.find('input[name="siloq_api_url"]') : $('input[name="siloq_api_url"]')).val() || '').trim() || DEFAULT_API_URL;
            const apiKey = ($form.length ? $form.find('input[name="siloq_api_key"]') : $('input[name="siloq_api_key"]')).val();
            
            // Disable button and show loading
            $button.prop('disabled', true).addClass('siloq-syncing');
            $status.removeClass('success error').addClass('loading')
                .html('<span class="siloq-loading"></span> ' + siloqAjax.strings.testing);
            
            $.ajax({
                url: siloqAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'siloq_test_connection',
                    nonce: siloqAjax.nonce,
                    siloq_api_url: apiUrl ? String(apiUrl).trim() : '',
                    siloq_api_key: apiKey ? String(apiKey).trim() : ''
                },
                success: function(response) {
                    $button.prop('disabled', false).removeClass('siloq-syncing');
                    
                    if (response.success) {
                        $status.removeClass('loading error').addClass('success')
                            .text(siloqAjax.strings.success + ' ' + response.data.message);
                    } else {
                        $status.removeClass('loading success').addClass('error')
                            .text(siloqAjax.strings.error + ' ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false).removeClass('siloq-syncing');
                    $status.removeClass('loading success').addClass('error')
                        .text(siloqAjax.strings.error + ' ' + error);
                }
            });
        });
        
        /**
         * Sync All Pages (batched to handle large sites)
         */
        $('#siloq-sync-all-pages').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to sync all pages? This may take a few minutes.')) {
                return;
            }
            
            const $button = $(this);
            const $progress = $('#siloq-sync-progress');
            const $results = $('#siloq-sync-results');
            const BATCH_SIZE = 50;
            
            // Disable button and show progress
            $button.prop('disabled', true).addClass('siloq-syncing');
            $progress.show();
            $results.hide().empty();
            
            // Reset progress bar
            $('.siloq-progress-fill').css('width', '0%');
            $('.siloq-progress-text').text('Starting sync...');
            
            var totalSynced = 0;
            var totalFailed = 0;
            var totalSkipped = 0;
            var allDetails = [];
            
            function syncBatch(offset) {
                $.ajax({
                    url: siloqAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'siloq_sync_all',
                        nonce: siloqAjax.nonce,
                        offset: offset,
                        batch_size: BATCH_SIZE
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            totalSynced += data.synced || 0;
                            totalFailed += data.failed || 0;
                            totalSkipped += data.skipped || 0;
                            if (data.details) {
                                allDetails = allDetails.concat(data.details);
                            }
                            
                            // Update progress
                            var processed = offset + (data.total || 0);
                            var totalAvail = data.total_available || processed;
                            var pct = totalAvail > 0 ? Math.min(100, Math.round((processed / totalAvail) * 100)) : 100;
                            $('.siloq-progress-fill').css('width', pct + '%');
                            $('.siloq-progress-text').text(processed + ' / ' + totalAvail + ' pages processed');
                            
                            // If more batches, continue
                            if (data.has_more) {
                                syncBatch(offset + BATCH_SIZE);
                            } else {
                                // Done!
                                $button.prop('disabled', false).removeClass('siloq-syncing');
                                $progress.hide();
                                displaySyncResults({
                                    total: totalSynced + totalFailed + totalSkipped,
                                    synced: totalSynced,
                                    failed: totalFailed,
                                    skipped: totalSkipped,
                                    details: allDetails
                                });
                            }
                        } else {
                            $button.prop('disabled', false).removeClass('siloq-syncing');
                            $progress.hide();
                            alert('Error: ' + (response.data.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $button.prop('disabled', false).removeClass('siloq-syncing');
                        $progress.hide();
                        alert('Sync error at batch offset ' + offset + ': ' + error);
                    }
                });
            }
            
            // Start first batch
            syncBatch(0);
        });
        
        /**
         * Sync Single Page
         */
        $(document).on('click', '.siloq-sync-single', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const postId = $button.data('page-id');
            const $row = $button.closest('tr');
            
            // Disable button
            $button.prop('disabled', true).text('Syncing...');
            
            // Make AJAX request
            $.ajax({
                url: siloqAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'siloq_sync_page',
                    nonce: siloqAjax.nonce,
                    post_id: postId
                },
                success: function(response) {
                    $button.prop('disabled', false).text('Sync Now');
                    
                    if (response.success) {
                        // Update row status
                        const $statusBadge = $row.find('.siloq-status-badge');
                        $statusBadge.removeClass('siloq-status-error siloq-status-not-synced siloq-status-warning')
                            .addClass('siloq-status-success')
                            .text('Synced');
                        
                        // Update last synced time
                        const now = new Date();
                        const timeString = now.toLocaleString();
                        $row.find('td:nth-child(3)').text(timeString);
                        
                        // Show success message briefly
                        showNotice('Page synced successfully!', 'success');
                    } else {
                        // Update row to show error
                        const $statusBadge = $row.find('.siloq-status-badge');
                        $statusBadge.removeClass('siloq-status-success siloq-status-not-synced siloq-status-warning')
                            .addClass('siloq-status-error')
                            .text('Error');
                        
                        showNotice('Sync failed: ' + response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false).text('Sync Now');
                    showNotice('Error: ' + error, 'error');
                }
            });
        });
        
        /**
         * Refresh Status
         */
        $('#siloq-refresh-status').on('click', function(e) {
            e.preventDefault();
            location.reload();
        });
        
        /**
         * Sync Outdated Pages
         */
        $('#siloq-sync-outdated').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to sync outdated pages?')) {
                return;
            }
            
            const $button = $(this);
            const $progress = $('#siloq-sync-progress');
            const $results = $('#siloq-sync-results');
            
            // Disable button and show progress
            $button.prop('disabled', true).addClass('siloq-syncing');
            $progress.show();
            $results.hide().empty();
            
            // Reset progress bar
            $('.siloq-progress-fill').css('width', '0%');
            $('.siloq-progress-text').text('0 / 0');
            
            // Make AJAX request
            $.ajax({
                url: siloqAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'siloq_sync_outdated',
                    nonce: siloqAjax.nonce,
                    limit: 50
                },
                success: function(response) {
                    $button.prop('disabled', false).removeClass('siloq-syncing');
                    $progress.hide();
                    
                    if (response.success) {
                        displaySyncResults(response.data);
                        
                        // Reload page after a moment to refresh status
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        alert('Error: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false).removeClass('siloq-syncing');
                    $progress.hide();
                    alert('Error: ' + error);
                }
            });
        });
        
        /**
         * Import Content
         */
        $(document).on('click', '.siloq-import-content', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const postId = $button.data('page-id');
            const jobId = $button.data('job-id');
            const action = $button.data('action');
            
            const confirmMsg = action === 'replace' 
                ? 'This will replace the existing content (a backup will be created). Continue?'
                : 'This will create a new draft page with the AI-generated content. Continue?';
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            // Disable button
            $button.prop('disabled', true).text('Importing...');
            
            // Make AJAX request
            $.ajax({
                url: siloqAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'siloq_import_content',
                    nonce: siloqAjax.nonce,
                    post_id: postId,
                    job_id: jobId,
                    import_action: action
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        
                        // Redirect to edit page after a moment
                        setTimeout(function() {
                            window.location.href = response.data.data.edit_url;
                        }, 1500);
                    } else {
                        $button.prop('disabled', false).text(action === 'replace' ? 'Replace Content' : 'Import as Draft');
                        showNotice('Import failed: ' + response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false).text(action === 'replace' ? 'Replace Content' : 'Import as Draft');
                    showNotice('Error: ' + error, 'error');
                }
            });
        });
        
        /**
         * Generate Content
         */
        $('#siloq-generate-content').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $select = $('#siloq-generate-page-select');
            const postId = $select.val();
            const $status = $('#siloq-generation-status');
            
            if (!postId) {
                alert('Please select a page first.');
                return;
            }
            
            // Disable button
            $button.prop('disabled', true).text('Generating...');
            $status.hide().empty();
            
            // Make AJAX request
            $.ajax({
                url: siloqAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'siloq_generate_content',
                    nonce: siloqAjax.nonce,
                    post_id: postId
                },
                success: function(response) {
                    $button.prop('disabled', false).text('Generate Content');
                    
                    if (response.success) {
                        $status.html(
                            '<div class="notice notice-success"><p>' +
                            '<strong>Success!</strong> Content generation job created. ' +
                            'Job ID: ' + response.data.data.job_id + '. ' +
                            'You will be notified when the content is ready.' +
                            '</p></div>'
                        ).show();
                        
                        // Start polling for job status
                        pollJobStatus(response.data.data.job_id, $status);
                    } else {
                        $status.html(
                            '<div class="notice notice-error"><p>' +
                            '<strong>Error:</strong> ' + response.data.message +
                            '</p></div>'
                        ).show();
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false).text('Generate Content');
                    $status.html(
                        '<div class="notice notice-error"><p>' +
                        '<strong>Error:</strong> ' + error +
                        '</p></div>'
                    ).show();
                }
            });
        });
        
        /**
         * Restore Backup
         */
        $(document).on('click', '.siloq-restore-backup', function(e) {
            e.preventDefault();
            
            if (!confirm('This will restore the original content before AI generation. Continue?')) {
                return;
            }
            
            const $button = $(this);
            const postId = $button.data('page-id');
            
            // Disable button
            $button.prop('disabled', true).text('Restoring...');
            
            // Make AJAX request
            $.ajax({
                url: siloqAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'siloq_restore_backup',
                    nonce: siloqAjax.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        
                        // Reload page after a moment
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $button.prop('disabled', false).text('Restore Backup');
                        showNotice('Restore failed: ' + response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $button.prop('disabled', false).text('Restore Backup');
                    showNotice('Error: ' + error, 'error');
                }
            });
        });
        
        /**
         * Poll job status
         */
        function pollJobStatus(jobId, $statusEl) {
            let attempts = 0;
            const maxAttempts = 60; // 5 minutes (5s intervals)
            
            const interval = setInterval(function() {
                attempts++;
                
                $.ajax({
                    url: siloqAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'siloq_check_job_status',
                        nonce: siloqAjax.nonce,
                        job_id: jobId
                    },
                    success: function(response) {
                        if (response.success && response.data.data) {
                            const status = response.data.data.status;
                            
                            $statusEl.find('p').html(
                                '<strong>Job Status:</strong> ' + status + '...'
                            );
                            
                            if (status === 'completed') {
                                clearInterval(interval);
                                $statusEl.find('p').html(
                                    '<strong>Success!</strong> Content is ready! Refresh the page to import it.'
                                );
                                
                                // Auto-refresh after 3 seconds
                                setTimeout(function() {
                                    location.reload();
                                }, 3000);
                            } else if (status === 'failed') {
                                clearInterval(interval);
                                $statusEl.removeClass('notice-success').addClass('notice-error')
                                    .find('p').html('<strong>Error:</strong> Content generation failed.');
                            }
                        }
                        
                        // Stop after max attempts
                        if (attempts >= maxAttempts) {
                            clearInterval(interval);
                            $statusEl.find('p').html(
                                '<strong>Note:</strong> Generation is taking longer than expected. ' +
                                'You will receive an email notification when it completes.'
                            );
                        }
                    }
                });
            }, 5000); // Check every 5 seconds
        }
        
        /**
         * Display Sync Results
         */
        function displaySyncResults(data) {
            const $results = $('#siloq-sync-results');
            let html = '';
            
            // Summary
            html += '<div class="siloq-result-summary">';
            html += '<strong>Sync Complete</strong><br>';
            html += 'Total: ' + data.total + ' | ';
            html += 'Synced: <span style="color: #00a32a;">' + data.synced + '</span> | ';
            html += 'Failed: <span style="color: #d63638;">' + data.failed + '</span>';
            html += '</div>';
            
            // Details
            if (data.details && data.details.length > 0) {
                html += '<h3>Details</h3>';
                data.details.forEach(function(item) {
                    html += '<div class="siloq-result-item ' + item.status + '">';
                    html += '<strong>' + item.title + '</strong><br>';
                    html += '<small>' + item.message + '</small>';
                    html += '</div>';
                });
            }
            
            $results.html(html).show();
        }
        
        /**
         * Show temporary notice
         */
        function showNotice(message, type) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        /**
         * Handle settings form submission with validation
         */
        $('form[action=""]').on('submit', function(e) {
            const apiKey = $('#siloq_api_key').val();
            
            // Only the API key is required — API URL has a server-side default
            if (!apiKey) {
                e.preventDefault();
                alert('Please enter your API Key.');
                return false;
            }
            
            // Validate API URL only if user explicitly set one
            const apiUrl = $('#siloq_api_url').val();
            if (apiUrl) {
                try {
                    new URL(apiUrl);
                } catch (err) {
                    e.preventDefault();
                    alert('Please enter a valid API URL.');
                    return false;
                }
            }
        });
        
    });
    
})(jQuery);
