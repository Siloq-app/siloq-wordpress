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
     * Show scan results
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

        // Populate issues count
        $('#siloq-issues-count').text(data.total_issues);
        $('#siloq-hidden-issues').text(data.hidden_issues);

        // Populate issues list
        const issuesContainer = $('#siloq-issues-preview');
        issuesContainer.empty();

        if (data.issues && data.issues.length > 0) {
            data.issues.forEach(function(issue) {
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
     * Handle CTA button click
     */
    function handleCtaClick(e) {
        e.preventDefault();

        const widget = $(this).closest('.siloq-scanner-widget');
        const signupUrl = widget.data('signup-url') || siloqScanner.signupUrl;

        // Redirect to signup page
        window.location.href = signupUrl;
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
