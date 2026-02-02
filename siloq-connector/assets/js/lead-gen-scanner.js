/**
 * Siloq Lead Gen Scanner JavaScript
 *
 * @package Siloq_Connector
 * @since 2.1.0
 */

(function($) {
    'use strict';

    let scanId = null;
    let pollInterval = null;
    let progressInterval = null;
    let progressPercent = 0;

    $(document).ready(function() {
        // Handle form submission
        $('#siloq-scanner-submit-form').on('submit', handleFormSubmit);

        // Handle CTA button click
        $(document).on('click', '#siloq-get-full-report', handleCtaClick);
    });

    /**
     * Handle form submission
     */
    function handleFormSubmit(e) {
        e.preventDefault();

        const websiteUrl = $('#siloq-website-url').val().trim();
        const email = $('#siloq-email').val().trim();

        // Basic validation
        if (!websiteUrl || !email) {
            showError('Please fill in all fields.');
            return;
        }

        if (!isValidUrl(websiteUrl)) {
            showError('Please enter a valid website URL (starting with http:// or https://).');
            return;
        }

        if (!isValidEmail(email)) {
            showError('Please enter a valid email address.');
            return;
        }

        // Hide error message
        $('#siloq-error-message').hide();

        // Disable submit button
        $('.siloq-submit-btn').prop('disabled', true).text('Starting Scan...');

        // Submit to server
        $.ajax({
            url: siloqScanner.ajaxUrl,
            type: 'POST',
            data: {
                action: 'siloq_submit_scan',
                nonce: siloqScanner.nonce,
                website_url: websiteUrl,
                email: email
            },
            success: function(response) {
                if (response.success) {
                    scanId = response.data.scan_id;
                    showProgress();
                    startPolling();
                } else {
                    showError(response.data.message || 'Unable to start scan. Please try again.');
                    $('.siloq-submit-btn').prop('disabled', false).text('Scan My Site');
                }
            },
            error: function() {
                showError('Network error. Please check your connection and try again.');
                $('.siloq-submit-btn').prop('disabled', false).text('Scan My Site');
            }
        });
    }

    /**
     * Show progress screen
     */
    function showProgress() {
        $('#siloq-scanner-form').fadeOut(300, function() {
            $('#siloq-scanner-progress').fadeIn(300);
        });

        // Animate progress bar (fake progress until real results arrive)
        progressPercent = 0;
        progressInterval = setInterval(function() {
            progressPercent += 2;
            if (progressPercent > 90) {
                progressPercent = 90; // Stop at 90% until real results come
                clearInterval(progressInterval);
            }
            $('#siloq-progress-fill').css('width', progressPercent + '%');
        }, 300);
    }

    /**
     * Start polling for scan results
     */
    function startPolling() {
        pollInterval = setInterval(function() {
            pollScanResults();
        }, 3000); // Poll every 3 seconds

        // Also poll immediately
        pollScanResults();
    }

    /**
     * Poll scan results from server
     */
    function pollScanResults() {
        $.ajax({
            url: siloqScanner.ajaxUrl,
            type: 'POST',
            data: {
                action: 'siloq_poll_scan',
                nonce: siloqScanner.nonce,
                scan_id: scanId
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.completed) {
                        // Scan completed - stop polling
                        clearInterval(pollInterval);
                        clearInterval(progressInterval);

                        // Complete progress bar
                        $('#siloq-progress-fill').css('width', '100%');

                        // Show results after brief delay
                        setTimeout(function() {
                            showResults(response.data.data);
                        }, 500);
                    }
                    // If not completed, keep polling
                } else {
                    // Scan failed
                    clearInterval(pollInterval);
                    clearInterval(progressInterval);
                    showError(response.data.message || 'Scan failed. Please try again.');
                    resetToForm();
                }
            },
            error: function() {
                clearInterval(pollInterval);
                clearInterval(progressInterval);
                showError('Network error while retrieving results.');
                resetToForm();
            }
        });
    }

    /**
     * Show scan results (all real API data)
     */
    function showResults(data) {
        // Populate score and grade
        $('#siloq-score-value').text(data.overall_score);
        $('#siloq-grade-badge').text(data.grade);

        // Apply color based on score
        const scoreCircle = $('.siloq-score-circle');
        if (data.overall_score >= 90) {
            scoreCircle.css('background', 'linear-gradient(135deg, #10b981 0%, #059669 100%)');
        } else if (data.overall_score >= 70) {
            scoreCircle.css('background', 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)');
        } else {
            scoreCircle.css('background', 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)');
        }

        // Scan meta (URL, pages analyzed, duration)
        $('#siloq-scan-url').text(data.url ? 'Website: ' + data.url : '');
        $('#siloq-pages-analyzed').text(data.pages_crawled != null ? 'Pages analyzed: ' + data.pages_crawled : '');
        $('#siloq-scan-duration').text(data.scan_duration_seconds != null ? 'Scan time: ' + data.scan_duration_seconds + 's' : '');
        if (data.url || data.pages_crawled != null || data.scan_duration_seconds != null) {
            $('#siloq-scan-meta').show();
        }

        // Score breakdown (Technical, Content, Structure, Performance, SEO)
        const breakdown = $('#siloq-score-breakdown');
        breakdown.empty();
        const scores = [
            { label: 'Technical', value: data.technical_score },
            { label: 'Content', value: data.content_score },
            { label: 'Structure', value: data.structure_score },
            { label: 'Performance', value: data.performance_score },
            { label: 'SEO', value: data.seo_score }
        ];
        scores.forEach(function(s) {
            if (s.value != null && s.value !== '') {
                const v = Math.round(parseFloat(s.value));
                breakdown.append('<span class="siloq-score-pill">' + escapeHtml(s.label) + ': <strong>' + v + '</strong></span>');
            }
        });
        if (breakdown.children().length) {
            breakdown.show();
        }

        // Populate issues count
        $('#siloq-issues-count').text(data.total_issues);
        $('#siloq-hidden-issues').text(data.hidden_issues);

        // All recommendations from API (use recommendations or fallback to issues)
        const list = data.recommendations && data.recommendations.length ? data.recommendations : (data.issues || []);
        const issuesContainer = $('#siloq-issues-preview');
        issuesContainer.empty();

        if (list.length > 0) {
            list.forEach(function(issue) {
                const issueHtml = `
                    <div class="siloq-issue-item">
                        <div class="siloq-issue-category">${escapeHtml(issue.category)}</div>
                        <div class="siloq-issue-text">${escapeHtml(issue.issue)}</div>
                        <div class="siloq-issue-action">${escapeHtml(issue.action)}</div>
                    </div>
                `;
                issuesContainer.append(issueHtml);
            });
        } else {
            issuesContainer.html('<p style="text-align: center; color: #666;">No critical issues found! Your site looks good.</p>');
        }

        // Show results section
        $('#siloq-scanner-progress').fadeOut(300, function() {
            $('#siloq-scanner-results').fadeIn(300);
        });
    }

    /**
     * Handle CTA button click – fetch and show full report inline
     */
    function handleCtaClick(e) {
        e.preventDefault();

        if (!scanId) {
            return;
        }

        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Loading report...');

        $.ajax({
            url: siloqScanner.ajaxUrl,
            type: 'POST',
            data: {
                action: 'siloq_get_full_report',
                nonce: siloqScanner.nonce,
                scan_id: scanId
            },
            success: function(response) {
                if (response.success && response.data) {
                    replaceWithFullReport(response.data);
                } else {
                    showReportError(response.data && response.data.message ? response.data.message : 'Could not load report.');
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, err) {
                showReportError('Network error. Please try again.');
                $btn.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Replace results area with full Keyword Cannibalization Report
     */
    function replaceWithFullReport(report) {
        // CTA redirects to https://siloq.ai signup with scan_id (custom signup_url origin used if set)
        let baseUrl = 'https://siloq.ai';
        if (siloqScanner.signupUrl && siloqScanner.signupUrl !== '#') {
            try {
                baseUrl = new URL(siloqScanner.signupUrl).origin;
            } catch (e) {
                baseUrl = 'https://siloq.ai';
            }
        }
        const signupPath = '/signup?plan=blueprint';
        const sep = signupPath.indexOf('?') >= 0 ? '&' : '?';
        const scanIdParam = (report.upgrade_cta && report.upgrade_cta.scan_id_param) ? report.upgrade_cta.scan_id_param : 'scan_id';
        const ctaUrl = baseUrl + signupPath + sep + scanIdParam + '=' + encodeURIComponent(report.scan_id);

        const summary = report.scan_summary || {};
        const details = report.keyword_cannibalization_details || [];
        const educational = report.educational_explanation || {};
        const locked = report.locked_recommendations || [];
        const ctaLabel = (report.upgrade_cta && report.upgrade_cta.label) ? report.upgrade_cta.label : 'Unlock Full Report & Fix Issues';

        let summaryHtml = '<div class="siloq-full-report">' +
            '<h3 class="siloq-report-heading">Keyword Cannibalization Report</h3>' +
            '<section class="siloq-report-summary">' +
            '<h4>Scan Summary</h4>' +
            '<ul class="siloq-report-summary-list">' +
            '<li><strong>Website scanned</strong>: ' + escapeHtml(summary.website_url || '') + '</li>' +
            '<li><strong>Pages analyzed</strong>: ' + escapeHtml(String(summary.total_pages_analyzed != null ? summary.total_pages_analyzed : 0)) + '</li>' +
            '<li><strong>Cannibalization conflicts</strong>: ' + escapeHtml(String(summary.total_cannibalization_conflicts != null ? summary.total_cannibalization_conflicts : 0)) + '</li>' +
            '<li><strong>Overall risk</strong>: <span class="siloq-risk-badge siloq-risk-' + (summary.overall_risk_level || 'low').toLowerCase() + '">' + escapeHtml(summary.overall_risk_level || 'Low') + '</span></li>' +
            '</ul></section>';

        if (details.length) {
            summaryHtml += '<section class="siloq-report-keywords"><h4>Keyword Cannibalization Details</h4><div class="siloq-keyword-list">';
            details.forEach(function(item) {
                const urls = item.conflicting_urls || [];
                const urlsList = urls.slice(0, 3).map(function(u) { return '<span class="siloq-conflict-url">' + escapeHtml(u) + '</span>'; }).join(', ');
                summaryHtml += '<div class="siloq-keyword-detail">' +
                    '<div class="siloq-keyword-name">' + escapeHtml(item.keyword || '') + '</div>' +
                    '<div class="siloq-keyword-meta">' +
                    '<span class="siloq-conflict-type">' + escapeHtml(item.conflict_type || '') + '</span> ' +
                    '<span class="siloq-severity siloq-severity-' + (item.severity || 'medium').toLowerCase() + '">' + escapeHtml(item.severity || '') + '</span>' +
                    '</div>' +
                    (urlsList ? '<div class="siloq-conflicting-urls">' + urlsList + '</div>' : '') +
                    '</div>';
            });
            summaryHtml += '</div></section>';
        }

        summaryHtml += '<section class="siloq-report-educational">' +
            '<h4>' + escapeHtml(educational.title || 'What is keyword cannibalization?') + '</h4>' +
            '<p>' + escapeHtml(educational.body || '') + '</p></section>';

        summaryHtml += '<section class="siloq-report-locked">' +
            '<h4>Recommended fixes (unlock to view)</h4>' +
            '<ul class="siloq-locked-list">';
        locked.forEach(function(title) {
            summaryHtml += '<li class="siloq-locked-item">' + escapeHtml(title) + '</li>';
        });
        summaryHtml += '</ul></section>';

        summaryHtml += '<div class="siloq-report-cta-wrap">' +
            '<a href="' + escapeHtml(ctaUrl) + '" class="siloq-cta-btn siloq-upgrade-cta">' + escapeHtml(ctaLabel) + '</a>' +
            '</div></div>';

        $('#siloq-scanner-results').html(summaryHtml);
    }

    /**
     * Show error message in results area
     */
    function showReportError(message) {
        $('#siloq-scanner-results').prepend(
            '<div class="siloq-error-message siloq-report-error">' + escapeHtml(message) + '</div>'
        );
    }

    /**
     * Show error message
     */
    function showError(message) {
        $('#siloq-error-message').text(message).fadeIn(300);
    }

    /**
     * Reset to form
     */
    function resetToForm() {
        $('#siloq-scanner-progress').fadeOut(300, function() {
            $('#siloq-scanner-form').fadeIn(300);
            $('.siloq-submit-btn').prop('disabled', false).text('Scan My Site');
        });
    }

    /**
     * Validate URL format
     */
    function isValidUrl(url) {
        try {
            const urlObj = new URL(url);
            return urlObj.protocol === 'http:' || urlObj.protocol === 'https:';
        } catch (e) {
            return false;
        }
    }

    /**
     * Validate email format
     */
    function isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

})(jQuery);
