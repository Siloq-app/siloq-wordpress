/**
 * Siloq Admin JavaScript
 * Handles form validation, user feedback, and enhanced admin functionality
 */

(function($) {
    'use strict';

    // Initialize admin functionality
    $(document).ready(function() {
        initAdminFeatures();
    });

    function initAdminFeatures() {
        // Form validation
        initFormValidation();
        
        // Enhanced user feedback
        initUserFeedback();
        
        // Accessibility improvements
        initAccessibility();
        
        // Loading states
        initLoadingStates();
        
        // Dashboard stats
        initDashboardStats();
    }

    function initFormValidation() {
        const $settingsForm = $('#siloq-settings-form');
        
        if ($settingsForm.length) {
            // Real-time validation
            $settingsForm.on('input', 'input, select, textarea', function() {
                validateField($(this));
            });
            
            // Form submission validation
            $settingsForm.on('submit', function(e) {
                const isValid = validateForm($(this));
                
                if (!isValid) {
                    e.preventDefault();
                    showNotification('Please fix the errors before submitting.', 'error');
                    return false;
                }
                
                // Show loading state
                showFormLoading($(this));
            });
        }
    }

    function validateField($field) {
        const fieldName = $field.attr('name');
        const fieldValue = $field.val().trim();
        const $formField = $field.closest('.siloq-form-field');
        let isValid = true;
        let errorMessage = '';

        // Remove previous validation states
        $formField.removeClass('has-error has-success');
        $formField.find('.siloq-form-validation').remove();

        // Validation rules
        switch (fieldName) {
            case 'siloq_api_key':
                if (!fieldValue) {
                    isValid = false;
                    errorMessage = 'API Key is required.';
                } else if (!fieldValue.startsWith('sk_siloq_')) {
                    isValid = false;
                    errorMessage = 'API Key should start with "sk_siloq_".';
                }
                break;
                
            case 'siloq_api_url':
                if (!fieldValue) {
                    isValid = false;
                    errorMessage = 'API URL is required.';
                } else if (!isValidUrl(fieldValue)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid URL.';
                }
                break;
                
            case 'siloq_api_timeout':
                const timeout = parseInt(fieldValue);
                if (isNaN(timeout) || timeout < 5 || timeout > 120) {
                    isValid = false;
                    errorMessage = 'Timeout must be between 5 and 120 seconds.';
                }
                break;
                
            case 'siloq_cache_duration':
                const duration = parseInt(fieldValue);
                if (isNaN(duration) || duration < 0 || duration > 1440) {
                    isValid = false;
                    errorMessage = 'Cache duration must be between 0 and 1440 minutes.';
                }
                break;
        }

        // Apply validation styling
        if (!isValid) {
            $formField.addClass('has-error');
            $formField.append('<div class="siloq-form-validation siloq-validation-error">' + errorMessage + '</div>');
        } else if (fieldValue) {
            $formField.addClass('has-success');
        }

        return isValid;
    }

    function validateForm($form) {
        let isValid = true;
        
        $form.find('input, select, textarea').each(function() {
            if (!validateField($(this))) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    function initUserFeedback() {
        // Enhanced notification system
        window.showNotification = function(message, type = 'info') {
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
        };

        // Form field feedback
        $('input, select, textarea').on('focus', function() {
            $(this).closest('.siloq-form-field').addClass('focused');
        });

        $('input, select, textarea').on('blur', function() {
            $(this).closest('.siloq-form-field').removeClass('focused');
        });
    }

    function initAccessibility() {
        // ARIA label improvements
        $('.siloq-button').each(function() {
            const $button = $(this);
            if (!$button.attr('aria-label') && $button.text().trim()) {
                $button.attr('aria-label', $button.text().trim());
            }
        });

        // Keyboard navigation
        $('.siloq-sync-actions-top .siloq-button').on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).click();
            }
        });

        // Focus management for modals
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.siloq-modal-overlay').fadeOut(200);
            }
        });
    }

    function initLoadingStates() {
        // Add loading states to buttons
        $('.siloq-button, .siloq-content-hub-button, .siloq-sync-now-button').on('click', function() {
            const $button = $(this);
            
            if ($button.hasClass('loading')) {
                return;
            }
            
            // Add loading class
            $button.addClass('loading');
            
            // Remove loading after 3 seconds (for demo)
            setTimeout(function() {
                $button.removeClass('loading');
            }, 3000);
        });
    }

    function showFormLoading($form) {
        $form.addClass('siloq-loading');
        
        // Disable all form inputs
        $form.find('input, select, textarea, button').prop('disabled', true);
    }

    // Enhanced sync status updates with real-time feedback
    function updateSyncStatus(status) {
        const $statusElements = $('.siloq-status, .siloq-status-badge');
        
        $statusElements.each(function() {
            const $element = $(this);
            $element.removeClass('siloq-status-connected siloq-status-syncing siloq-status-disconnected siloq-status-pending');
            
            switch (status) {
                case 'connected':
                    $element.addClass('siloq-status-connected');
                    $element.text('Connected');
                    break;
                case 'syncing':
                    $element.addClass('siloq-status-syncing');
                    $element.text('Syncing...');
                    break;
                case 'disconnected':
                    $element.addClass('siloq-status-disconnected');
                    $element.text('Disconnected');
                    break;
                default:
                    $element.addClass('siloq-status-pending');
                    $element.text('Pending');
            }
        });
    }

    function initDashboardStats() {
        // Update dashboard statistics with real data
        updateDashboardStats();
        
        // Refresh stats every 30 seconds
        setInterval(updateDashboardStats, 30000);
    }

    function updateDashboardStats() {
        // Get real statistics from WordPress
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'siloq_get_dashboard_stats',
                nonce: siloqAdminData.nonce
            },
            success: function(response) {
                if (response.success) {
                    const stats = response.data;
                    
                    // Update pages synced
                    $('#siloq-pages-synced').text(stats.pages_synced || '0');
                    
                    // Update content generated
                    $('#siloq-content-generated').text(stats.content_generated || '0');
                    
                    // Update SEO score
                    $('#siloq-seo-score').text(stats.seo_score || '--');
                    
                    // Animate number changes
                    animateNumber('#siloq-pages-synced');
                    animateNumber('#siloq-content-generated');
                }
            },
            error: function() {
                // Keep default values on error
                console.log('Failed to load dashboard stats');
            }
        });
    }

    function animateNumber(selector) {
        const $element = $(selector);
        const currentValue = $element.text();
        
        // Add animation class
        $element.addClass('siloq-stat-updating');
        
        // Create number animation effect
        const isNumeric = !isNaN(currentValue) && currentValue !== '--';
        if (isNumeric) {
            const targetValue = parseInt(currentValue);
            const startValue = targetValue > 0 ? 0 : targetValue;
            const duration = 800;
            const steps = 20;
            const stepValue = (targetValue - startValue) / steps;
            let currentStep = 0;
            
            const animationInterval = setInterval(() => {
                currentStep++;
                const newValue = Math.round(startValue + (stepValue * currentStep));
                $element.text(newValue);
                
                if (currentStep >= steps) {
                    clearInterval(animationInterval);
                    $element.text(targetValue);
                    
                    // Remove animation class after completion
                    setTimeout(function() {
                        $element.removeClass('siloq-stat-updating');
                    }, 200);
                }
            }, duration / steps);
        } else {
            // For non-numeric values (like '--'), just do the fade animation
            setTimeout(function() {
                $element.removeClass('siloq-stat-updating');
            }, 500);
        }
    }

    // Expose functions globally
    window.siloqAdmin = {
        updateSyncStatus: updateSyncStatus,
        showNotification: showNotification,
        updateDashboardStats: updateDashboardStats
    };

})(jQuery);
