/**
 * Siloq Dashboard JavaScript
 * Handles tab switching, modals, automation mode, and AJAX interactions
 */

(function($) {
    'use strict';

    // Dashboard state
    var state = {
        activeTab: 'dashboard',
        automationMode: 'manual',
        showGenerateModal: false,
        showApprovalModal: false,
        selectedSilo: null
    };

    // Sample data (would come from API in production)
    var data = {
        healthScore: 72,
        healthChange: 8,
        cannibalizationIssues: [
            { 
                id: 1, 
                keyword: 'kitchen remodeling', 
                pages: ['/kitchen-remodel-cost', '/kitchen-renovation-guide', '/remodel-your-kitchen'], 
                severity: 'high', 
                impressions: 12400, 
                splitClicks: '34% / 41% / 25%', 
                recommendation: 'Consolidate into single Target Page'
            },
            { 
                id: 2, 
                keyword: 'bathroom vanity ideas', 
                pages: ['/bathroom-vanity-styles', '/vanity-buying-guide'], 
                severity: 'medium', 
                impressions: 8200, 
                splitClicks: '52% / 48%', 
                recommendation: 'Differentiate entity targeting'
            },
            { 
                id: 3, 
                keyword: 'hardwood floor installation', 
                pages: ['/hardwood-installation', '/flooring-installation-cost'], 
                severity: 'low', 
                impressions: 3100, 
                splitClicks: '78% / 22%', 
                recommendation: 'Add internal links to strengthen Target'
            }
        ],
        silos: [
            {
                id: 1,
                name: 'Kitchen Remodeling',
                targetPage: { 
                    title: 'Complete Kitchen Remodeling Guide', 
                    url: '/kitchen-remodel-guide', 
                    status: 'published', 
                    entities: ['kitchen remodel', 'renovation cost', 'kitchen design']
                },
                supportingPages: [
                    { title: 'Kitchen Cabinet Styles 2024', url: '/kitchen-cabinets', status: 'published', linked: true, entities: ['cabinet styles', 'shaker cabinets'] },
                    { title: 'Countertop Materials Compared', url: '/countertop-materials', status: 'published', linked: true, entities: ['granite', 'quartz', 'marble'] },
                    { title: 'Kitchen Layout Ideas', url: '/kitchen-layouts', status: 'draft', linked: false, entities: ['galley kitchen', 'L-shaped'] },
                    { title: 'Kitchen Lighting Guide', url: '/kitchen-lighting', status: 'suggested', linked: false, entities: ['pendant lights', 'under-cabinet'] }
                ]
            },
            {
                id: 2,
                name: 'Bathroom Renovation',
                targetPage: { 
                    title: 'Bathroom Renovation Planning', 
                    url: '/bathroom-renovation', 
                    status: 'published', 
                    entities: ['bathroom remodel', 'renovation timeline']
                },
                supportingPages: [
                    { title: 'Bathroom Tile Options', url: '/bathroom-tile', status: 'published', linked: true, entities: ['ceramic tile', 'porcelain'] },
                    { title: 'Vanity Selection Guide', url: '/bathroom-vanity', status: 'published', linked: false, entities: ['floating vanity', 'double sink'] }
                ]
            }
        ],
        pendingChanges: [
            { id: 1, type: 'link_add', description: 'Add internal link from /kitchen-cabinets to /kitchen-remodel-guide', risk: 'safe', impact: 'Strengthens Target Page authority', doctrine: 'LINK_EQUITY_001' },
            { id: 2, type: 'redirect', description: 'Redirect /old-kitchen-page → /kitchen-remodel-guide', risk: 'destructive', impact: 'Consolidates 890 monthly impressions', doctrine: 'CANN_RESTORE_001' },
            { id: 3, type: 'content_generate', description: 'Generate Supporting Page: "Kitchen Lighting Guide"', risk: 'safe', impact: 'Fills entity gap in silo', doctrine: 'ARCH_003' },
            { id: 4, type: 'entity_assign', description: 'Assign entities [pendant lights, task lighting] to /kitchen-lighting', risk: 'safe', impact: 'Improves semantic targeting', doctrine: 'ENTITY_001' },
            { id: 5, type: 'content_merge', description: 'Merge /remodel-your-kitchen into /kitchen-remodel-guide', risk: 'destructive', impact: 'Eliminates cannibalization, consolidates 4,100 impressions', doctrine: 'CANN_RESTORE_002' }
        ]
    };

    /**
     * Initialize the dashboard
     */
    function init() {
        bindEvents();
        renderHealthScore();
        updateAutomationBadge();
    }

    /**
     * Bind event listeners
     */
    function bindEvents() {
        // Navigation tabs
        $(document).on('click', '.nav-item', function() {
            var tab = $(this).data('tab');
            if (tab) {
                switchTab(tab);
            }
        });

        // Automation dropdown toggle
        $(document).on('click', '.automation-selector', function(e) {
            e.stopPropagation();
            $('.automation-dropdown').toggleClass('active');
        });

        // Automation mode selection
        $(document).on('click', '.automation-option', function() {
            var mode = $(this).data('mode');
            setAutomationMode(mode);
            $('.automation-dropdown').removeClass('active');
        });

        // Close dropdown when clicking outside
        $(document).on('click', function() {
            $('.automation-dropdown').removeClass('active');
        });

        // Modal triggers
        $(document).on('click', '[data-modal="generate"]', function() {
            openModal('generate');
        });

        $(document).on('click', '[data-modal="approval"]', function() {
            openModal('approval');
        });

        // Modal close
        $(document).on('click', '.modal-overlay', function(e) {
            if ($(e.target).hasClass('modal-overlay')) {
                closeAllModals();
            }
        });

        $(document).on('click', '.modal-close', function() {
            closeAllModals();
        });

        // Prevent modal content clicks from closing
        $(document).on('click', '.modal-content', function(e) {
            e.stopPropagation();
        });

        // Silo view
        $(document).on('click', '.silo-card', function() {
            var siloId = $(this).data('silo-id');
            viewSilo(siloId);
        });

        // View approvals button
        $(document).on('click', '[data-action="view-approvals"]', function() {
            switchTab('approvals');
        });

        // View all silos button
        $(document).on('click', '[data-action="view-all-silos"]', function() {
            state.selectedSilo = null;
            switchTab('silos');
        });

        // Settings options
        $(document).on('click', '.settings-option', function() {
            var mode = $(this).data('mode');
            setAutomationMode(mode);
        });

        // Toggle switches
        $(document).on('click', '.toggle-switch', function() {
            $(this).toggleClass('active');
        });

        // Content action cards
        $(document).on('click', '.content-action-card', function() {
            openModal('generate');
        });

        // Generate CTA
        $(document).on('click', '.generate-cta', function() {
            openModal('generate');
        });

        // Approve/Deny buttons (demo feedback)
        $(document).on('click', '.btn-approve, .btn-deny', function() {
            var $btn = $(this);
            var action = $btn.hasClass('btn-approve') ? 'approved' : 'denied';
            
            // Show feedback
            $btn.text(action === 'approved' ? 'Approved!' : 'Denied').prop('disabled', true);
            
            setTimeout(function() {
                $btn.prop('disabled', false);
                if ($btn.hasClass('btn-approve')) {
                    $btn.html('<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Approve');
                } else {
                    $btn.text('Deny');
                }
            }, 1500);
        });
    }

    /**
     * Switch active tab
     */
    function switchTab(tab) {
        state.activeTab = tab;
        
        // Update navigation
        $('.nav-item').removeClass('active');
        $('.nav-item[data-tab="' + tab + '"]').addClass('active');
        
        // Update content visibility
        $('.tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');
        
        // Update page title
        var titles = {
            'dashboard': 'Dashboard',
            'silos': 'Silos',
            'approvals': 'Approvals',
            'content': 'Content',
            'links': 'Internal Links',
            'settings': 'Settings'
        };
        
        if (titles[tab]) {
            document.title = titles[tab] + ' - Siloq';
        }
    }

    /**
     * Set automation mode
     */
    function setAutomationMode(mode) {
        state.automationMode = mode;
        updateAutomationBadge();
        
        // Update settings UI if on settings tab
        $('.settings-option').removeClass('active');
        $('.settings-option[data-mode="' + mode + '"]').addClass('active');
    }

    /**
     * Update automation badge in header
     */
    function updateAutomationBadge() {
        var mode = state.automationMode;
        var labels = {
            'manual': 'Manual',
            'semi': 'Semi-Auto',
            'full': 'Full-Auto'
        };
        
        var $badge = $('.automation-mode-badge');
        $badge.removeClass('automation-mode-manual automation-mode-semi automation-mode-full');
        $badge.addClass('automation-mode-' + mode);
        $badge.text(labels[mode]);
    }

    /**
     * Open modal
     */
    function openModal(type) {
        if (type === 'generate') {
            $('#generate-modal').addClass('active');
            state.showGenerateModal = true;
        } else if (type === 'approval') {
            $('#approval-modal').addClass('active');
            state.showApprovalModal = true;
        }
    }

    /**
     * Close all modals
     */
    function closeAllModals() {
        $('.modal-overlay').removeClass('active');
        state.showGenerateModal = false;
        state.showApprovalModal = false;
    }

    /**
     * View specific silo
     */
    function viewSilo(siloId) {
        var silo = data.silos.find(function(s) { return s.id === siloId; });
        if (silo) {
            state.selectedSilo = silo;
            switchTab('silos');
        }
    }

    /**
     * Render health score SVG
     */
    function renderHealthScore() {
        var score = data.healthScore;
        var circumference = 2 * Math.PI * 45;
        var dashArray = (score / 100) * circumference;
        
        // Update the stroke-dasharray
        $('.progress-circle').attr('stroke-dasharray', dashArray + ' ' + circumference);
    }

    /**
     * AJAX: Load dashboard data from server
     */
    function loadDashboardData() {
        $.ajax({
            url: siloqDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'siloq_dashboard_stats',
                nonce: siloqDashboard.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateDashboardStats(response.data);
                }
            },
            error: function() {
                console.error('Failed to load dashboard data');
            }
        });
    }

    /**
     * Update dashboard with server data
     */
    function updateDashboardStats(stats) {
        // Update connection status indicator
        if (stats.api_connected) {
            $('.status-indicator').css('background', '#34d399');
        } else {
            $('.status-indicator').css('background', '#ef4444');
        }
        
        // Could update more stats here from server data
    }

    /**
     * AJAX: Test API connection
     */
    function testConnection() {
        var $btn = $('[data-action="test-connection"]');
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text(siloqDashboard.strings.testing);
        
        $.ajax({
            url: siloqDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'siloq_test_connection',
                nonce: siloqDashboard.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice(siloqDashboard.strings.success, 'success');
                    $('.status-indicator').css('background', '#34d399');
                } else {
                    showNotice(siloqDashboard.strings.error + ' ' + response.data.message, 'error');
                    $('.status-indicator').css('background', '#ef4444');
                }
            },
            error: function() {
                showNotice(siloqDashboard.strings.error + ' Connection failed', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Show notice message
     */
    function showNotice(message, type) {
        var $notice = $('<div class="siloq-dashboard-notice ' + type + '">' + message + '</div>');
        $('.siloq-dashboard').prepend($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Initialize on document ready
    $(document).ready(function() {
        init();
        
        // Load real data from server
        loadDashboardData();
    });

})(jQuery);
