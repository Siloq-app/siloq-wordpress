<?php
/**
 * Siloq Admin Interface
 * Handles admin pages and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Admin {
    
    /**
     * Render dashboard page
     */
    public static function render_dashboard_page() {
        // Get real stats from WordPress
        $post_types = get_option('siloq_post_types', array('page'));
        $total_pages = 0;
        foreach ($post_types as $post_type) {
            $counts = wp_count_posts($post_type);
            if (isset($counts->publish)) {
                $total_pages += $counts->publish;
            }
        }
        
        $api_url = get_option('siloq_api_url', '');
        $api_key = get_option('siloq_api_key', '');
        $site_url = get_site_url();
        $site_domain = parse_url($site_url, PHP_URL_HOST);
        
        // Sample data for demo (would come from API in production)
        $health_score = 72;
        $health_change = 8;
        $cannibalization_count = 3;
        $silo_count = 2;
        $pending_changes_count = 5;
        $safe_changes = 3;
        $destructive_changes = 2;
        $total_silo_pages = 10;
        
        ?>
        <div class="wrap">
            <div class="siloq-dashboard">
                <div class="dashboard-wrapper">
                    <!-- Header -->
                    <header class="dashboard-header">
                        <div class="header-logo">
                            <div class="logo-icon">S</div>
                            <span class="logo-text">Siloq</span>
                            <span class="version-badge">V1</span>
                        </div>
                        
                        <div class="header-right">
                            <!-- Automation Mode Selector -->
                            <div class="automation-dropdown-container">
                                <button class="automation-selector">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                    <span class="automation-label">Automation:</span>
                                    <span class="automation-mode-badge automation-mode-manual">Manual</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </button>
                                
                                <div class="automation-dropdown">
                                    <button class="automation-option active" data-mode="manual">
                                        <div>
                                            <div class="automation-option-label">Manual</div>
                                            <div class="automation-option-desc">All changes require approval</div>
                                        </div>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="check-icon" style="color: #818cf8;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    </button>
                                    <button class="automation-option" data-mode="semi">
                                        <div>
                                            <div class="automation-option-label">Semi-Auto</div>
                                            <div class="automation-option-desc">Safe changes auto-execute</div>
                                        </div>
                                    </button>
                                    <button class="automation-option" data-mode="full">
                                        <div>
                                            <div class="automation-option-label">Full-Auto</div>
                                            <div class="automation-option-desc">48-hour rollback window</div>
                                        </div>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Site Status -->
                            <div class="site-status">
                                <span class="site-status-text"><?php echo esc_html($site_domain); ?></span>
                                <div class="status-indicator"></div>
                            </div>
                        </div>
                    </header>
                    
                    <div class="dashboard-body">
                        <!-- Sidebar -->
                        <nav class="dashboard-sidebar">
                            <button class="nav-item active" data-tab="dashboard">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg>
                                Dashboard
                            </button>
                            <button class="nav-item" data-tab="silos">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="3" x2="6" y2="15"></line><circle cx="18" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><path d="M18 9a9 9 0 0 1-9 9"></path></svg>
                                Silos
                            </button>
                            <button class="nav-item" data-tab="approvals">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                Approvals
                                <?php if ($pending_changes_count > 0): ?>
                                    <span class="nav-badge"><?php echo esc_html($pending_changes_count); ?></span>
                                <?php endif; ?>
                            </button>
                            <button class="nav-item" data-tab="content">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                Content
                            </button>
                            <button class="nav-item" data-tab="links">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                Internal Links
                            </button>
                            <button class="nav-item" data-tab="settings">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                Settings
                            </button>
                        </nav>
                        
                        <!-- Main Content -->
                        <main class="dashboard-main">
                            
                            <!-- Dashboard Tab -->
                            <div id="tab-dashboard" class="tab-content active">
                                <!-- Health Score + Quick Stats -->
                                <div class="grid-2 mb-8">
                                    <!-- Health Score Card -->
                                    <div class="card health-score-card">
                                        <div class="health-score-title">Content Health Score</div>
                                        <div class="health-score-circle">
                                            <svg viewBox="0 0 100 100">
                                                <circle class="bg-circle" cx="50" cy="50" r="45"></circle>
                                                <circle class="progress-circle" cx="50" cy="50" r="45" stroke-dasharray="203.58 283"></circle>
                                                <defs>
                                                    <linearGradient id="scoreGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                                        <stop offset="0%" stop-color="#3b82f6"></stop>
                                                        <stop offset="100%" stop-color="#8b5cf6"></stop>
                                                    </linearGradient>
                                                </defs>
                                            </svg>
                                            <div class="health-score-value">
                                                <span class="health-score-number"><?php echo esc_html($health_score); ?></span>
                                                <span class="health-score-total">/ 100</span>
                                            </div>
                                        </div>
                                        <div class="health-score-change">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                                            <span>+<?php echo esc_html($health_change); ?> from last week</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Quick Stats Grid -->
                                    <div class="grid-3">
                                        <div class="card stat-card card-hover">
                                            <div class="stat-label">Cannibalization Issues</div>
                                            <div class="stat-value stat-red"><?php echo esc_html($cannibalization_count); ?></div>
                                            <div class="stat-subtext">Detected by Siloq</div>
                                        </div>
                                        <div class="card stat-card card-hover">
                                            <div class="stat-label">Silos Mapped</div>
                                            <div class="stat-value stat-blue"><?php echo esc_html($silo_count); ?></div>
                                            <div class="stat-subtext"><?php echo esc_html($total_silo_pages); ?> pages organized</div>
                                        </div>
                                        <div class="card stat-card card-hover">
                                            <div class="stat-label">Pending Actions</div>
                                            <div class="stat-value stat-amber"><?php echo esc_html($pending_changes_count); ?></div>
                                            <div class="stat-subtext">Awaiting approval</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Siloq Remediation Banner -->
                                <div class="remediation-banner">
                                    <div class="banner-content">
                                        <div class="banner-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                                        </div>
                                        <div>
                                            <div class="banner-title">Siloq has analyzed your site</div>
                                            <div class="banner-text">
                                                Found <?php echo esc_html($cannibalization_count); ?> cannibalization issues. Generated <?php echo esc_html($pending_changes_count); ?> recommended actions
                                                (<?php echo esc_html($safe_changes); ?> safe, <?php echo esc_html($destructive_changes); ?> destructive).
                                            </div>
                                        </div>
                                    </div>
                                    <button class="btn btn-primary" data-action="view-approvals">
                                        Review Plan
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                                    </button>
                                </div>
                                
                                <!-- Cannibalization Alerts -->
                                <div class="card mb-8">
                                    <div class="section-header">
                                        <div class="section-icon red">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                        </div>
                                        <div>
                                            <div class="section-title">Cannibalization Detected</div>
                                            <p class="section-subtitle">Pages competing for the same keywords</p>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-3">
                                        <!-- Issue 1 -->
                                        <div class="issue-card" data-modal="approval">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <div class="issue-header">
                                                        <span class="severity-badge severity-high">high</span>
                                                        <span class="keyword-text">"kitchen remodeling"</span>
                                                    </div>
                                                    <div class="issue-meta">
                                                        <span class="highlight">3 pages</span> competing • 12,400 monthly impressions • Split: 34% / 41% / 25%
                                                    </div>
                                                    <div class="page-tags">
                                                        <span class="page-tag">/kitchen-remodel-cost</span>
                                                        <span class="page-tag">/kitchen-renovation-guide</span>
                                                        <span class="page-tag">/remodel-your-kitchen</span>
                                                    </div>
                                                    <div class="recommendation">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                                                        Siloq recommendation: Consolidate into single Target Page
                                                    </div>
                                                </div>
                                                <button class="btn btn-primary whitespace-nowrap ml-4" data-modal="approval">
                                                    View Fix
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Issue 2 -->
                                        <div class="issue-card" data-modal="approval">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <div class="issue-header">
                                                        <span class="severity-badge severity-medium">medium</span>
                                                        <span class="keyword-text">"bathroom vanity ideas"</span>
                                                    </div>
                                                    <div class="issue-meta">
                                                        <span class="highlight">2 pages</span> competing • 8,200 monthly impressions • Split: 52% / 48%
                                                    </div>
                                                    <div class="page-tags">
                                                        <span class="page-tag">/bathroom-vanity-styles</span>
                                                        <span class="page-tag">/vanity-buying-guide</span>
                                                    </div>
                                                    <div class="recommendation">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                                                        Siloq recommendation: Differentiate entity targeting
                                                    </div>
                                                </div>
                                                <button class="btn btn-primary whitespace-nowrap ml-4" data-modal="approval">
                                                    View Fix
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Issue 3 -->
                                        <div class="issue-card" data-modal="approval">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <div class="issue-header">
                                                        <span class="severity-badge severity-low">low</span>
                                                        <span class="keyword-text">"hardwood floor installation"</span>
                                                    </div>
                                                    <div class="issue-meta">
                                                        <span class="highlight">2 pages</span> competing • 3,100 monthly impressions • Split: 78% / 22%
                                                    </div>
                                                    <div class="page-tags">
                                                        <span class="page-tag">/hardwood-installation</span>
                                                        <span class="page-tag">/flooring-installation-cost</span>
                                                    </div>
                                                    <div class="recommendation">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                                                        Siloq recommendation: Add internal links to strengthen Target
                                                    </div>
                                                </div>
                                                <button class="btn btn-primary whitespace-nowrap ml-4" data-modal="approval">
                                                    View Fix
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Silo Overview -->
                                <div class="card">
                                    <div class="section-header">
                                        <div class="section-icon indigo">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="3" x2="6" y2="15"></line><circle cx="18" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><path d="M18 9a9 9 0 0 1-9 9"></path></svg>
                                        </div>
                                        <div>
                                            <div class="section-title">Reverse Silo Architecture</div>
                                            <p class="section-subtitle">Target Pages (Kings) and Supporting Pages (Soldiers)</p>
                                        </div>
                                    </div>
                                    
                                    <div class="silo-grid">
                                        <!-- Silo 1 -->
                                        <div class="silo-card" data-silo-id="1">
                                            <div class="silo-header">
                                                <h3 class="silo-name">Kitchen Remodeling</h3>
                                                <span class="silo-meta">1 Target • 4 Supporting</span>
                                            </div>
                                            
                                            <div class="silo-visual">
                                                <div class="silo-line"></div>
                                                
                                                <div class="silo-target">
                                                    <div class="target-icon">👑</div>
                                                    <span class="target-title">Complete Kitchen Remodeling Guide</span>
                                                </div>
                                                
                                                <div class="silo-page">
                                                    <div class="page-icon published">⚔️</div>
                                                    <span class="page-title">Kitchen Cabinet Styles 2024</span>
                                                </div>
                                                <div class="silo-page">
                                                    <div class="page-icon published">⚔️</div>
                                                    <span class="page-title">Countertop Materials Compared</span>
                                                </div>
                                                <div class="silo-page">
                                                    <div class="page-icon draft">⚔️</div>
                                                    <span class="page-title">Kitchen Layout Ideas</span>
                                                </div>
                                                
                                                <span class="silo-more">+1 more</span>
                                            </div>
                                            
                                            <div class="silo-arrow">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                            </div>
                                        </div>
                                        
                                        <!-- Silo 2 -->
                                        <div class="silo-card" data-silo-id="2">
                                            <div class="silo-header">
                                                <h3 class="silo-name">Bathroom Renovation</h3>
                                                <span class="silo-meta">1 Target • 2 Supporting</span>
                                            </div>
                                            
                                            <div class="silo-visual">
                                                <div class="silo-line"></div>
                                                
                                                <div class="silo-target">
                                                    <div class="target-icon">👑</div>
                                                    <span class="target-title">Bathroom Renovation Planning</span>
                                                </div>
                                                
                                                <div class="silo-page">
                                                    <div class="page-icon published">⚔️</div>
                                                    <span class="page-title">Bathroom Tile Options</span>
                                                </div>
                                                <div class="silo-page">
                                                    <div class="page-icon published">⚔️</div>
                                                    <span class="page-title">Vanity Selection Guide</span>
                                                </div>
                                            </div>
                                            
                                            <div class="silo-arrow">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Silos Tab -->
                            <div id="tab-silos" class="tab-content">
                                <div class="card">
                                    <div class="silo-planner-header">
                                        <h2 class="silo-planner-title">All Silos</h2>
                                        <button class="btn btn-primary" data-modal="generate">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                            Generate Supporting Page
                                        </button>
                                    </div>
                                    
                                    <!-- Kitchen Remodeling Silo -->
                                    <div class="mb-8">
                                        <div class="target-page-card">
                                            <div class="target-page-header">
                                                <div class="target-page-icon">👑</div>
                                                <div class="target-page-info">
                                                    <div class="target-page-label">Target Page (King)</div>
                                                    <h3 class="target-page-name">Complete Kitchen Remodeling Guide</h3>
                                                    <span class="target-page-url">/kitchen-remodel-guide</span>
                                                    <div class="mt-2">
                                                        <span class="entity-tag">kitchen remodel</span>
                                                        <span class="entity-tag">renovation cost</span>
                                                        <span class="entity-tag">kitchen design</span>
                                                    </div>
                                                </div>
                                                <div class="flex gap-2">
                                                    <button class="btn btn-secondary btn-small">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                        View
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="supporting-pages-container">
                                            <div class="supporting-pages-label">Supporting Pages (Soldiers) — Link UP to Target</div>
                                            
                                            <!-- Supporting Page 1 -->
                                            <div class="supporting-page-item">
                                                <div class="supporting-page-info">
                                                    <div class="supporting-page-status published">⚔️</div>
                                                    <div>
                                                        <div class="supporting-page-title">Kitchen Cabinet Styles 2024</div>
                                                        <div class="supporting-page-url">/kitchen-cabinets</div>
                                                        <div class="mt-2">
                                                            <span class="entity-tag">cabinet styles</span>
                                                            <span class="entity-tag">shaker cabinets</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="supporting-page-actions">
                                                    <span class="link-status linked">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                        Linked to Target
                                                    </span>
                                                    <span class="status-badge published">published</span>
                                                </div>
                                            </div>
                                            
                                            <!-- Supporting Page 2 -->
                                            <div class="supporting-page-item">
                                                <div class="supporting-page-info">
                                                    <div class="supporting-page-status published">⚔️</div>
                                                    <div>
                                                        <div class="supporting-page-title">Countertop Materials Compared</div>
                                                        <div class="supporting-page-url">/countertop-materials</div>
                                                        <div class="mt-2">
                                                            <span class="entity-tag">granite</span>
                                                            <span class="entity-tag">quartz</span>
                                                            <span class="entity-tag">marble</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="supporting-page-actions">
                                                    <span class="link-status linked">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                        Linked to Target
                                                    </span>
                                                    <span class="status-badge published">published</span>
                                                </div>
                                            </div>
                                            
                                            <!-- Supporting Page 3 -->
                                            <div class="supporting-page-item">
                                                <div class="supporting-page-info">
                                                    <div class="supporting-page-status draft">⚔️</div>
                                                    <div>
                                                        <div class="supporting-page-title">Kitchen Layout Ideas</div>
                                                        <div class="supporting-page-url">/kitchen-layouts</div>
                                                        <div class="mt-2">
                                                            <span class="entity-tag">galley kitchen</span>
                                                            <span class="entity-tag">L-shaped</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="supporting-page-actions">
                                                    <button class="btn btn-secondary btn-small">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                        Add Link
                                                    </button>
                                                    <span class="status-badge draft">draft</span>
                                                </div>
                                            </div>
                                            
                                            <!-- Supporting Page 4 -->
                                            <div class="supporting-page-item">
                                                <div class="supporting-page-info">
                                                    <div class="supporting-page-status suggested">⚔️</div>
                                                    <div>
                                                        <div class="supporting-page-title">Kitchen Lighting Guide</div>
                                                        <div class="supporting-page-url">/kitchen-lighting</div>
                                                        <div class="mt-2">
                                                            <span class="entity-tag">pendant lights</span>
                                                            <span class="entity-tag">under-cabinet</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="supporting-page-actions">
                                                    <button class="btn btn-secondary btn-small">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                        Add Link
                                                    </button>
                                                    <span class="status-badge suggested">suggested</span>
                                                </div>
                                            </div>
                                            
                                            <!-- Generate CTA -->
                                            <div class="generate-cta" data-modal="generate">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                                <div class="generate-cta-title">Generate New Supporting Page</div>
                                                <div class="generate-cta-desc">Siloq will create content with proper entity targeting and links</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Approvals Tab -->
                            <div id="tab-approvals" class="tab-content">
                                <div class="card">
                                    <div class="flex items-center justify-between mb-8">
                                        <div>
                                            <h2 class="text-2xl font-bold mb-2">Approval Queue</h2>
                                            <p class="section-subtitle">Siloq-generated remediation plan — review and approve</p>
                                        </div>
                                        <div class="flex gap-3">
                                            <button class="btn btn-secondary">
                                                Approve All Safe (<?php echo esc_html($safe_changes); ?>)
                                            </button>
                                            <button class="btn btn-primary">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                Approve All
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Queue Stats -->
                                    <div class="grid-3 mb-6">
                                        <div class="queue-stat">
                                            <div class="queue-stat-label">Total Pending</div>
                                            <div class="queue-stat-value"><?php echo esc_html($pending_changes_count); ?></div>
                                        </div>
                                        <div class="queue-stat safe">
                                            <div class="queue-stat-label">Safe Changes</div>
                                            <div class="queue-stat-value"><?php echo esc_html($safe_changes); ?></div>
                                        </div>
                                        <div class="queue-stat destructive">
                                            <div class="queue-stat-label">Destructive Changes</div>
                                            <div class="queue-stat-value"><?php echo esc_html($destructive_changes); ?></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Change Cards -->
                                    <div>
                                        <!-- Change 1 -->
                                        <div class="change-card">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="change-header">
                                                        <span class="risk-safe">Safe</span>
                                                        <span class="change-type">link add</span>
                                                    </div>
                                                    <div class="change-title">Add internal link from /kitchen-cabinets to /kitchen-remodel-guide</div>
                                                    <div class="change-doctrine"><span>DOCTRINE:</span> LINK_EQUITY_001</div>
                                                    <div class="change-impact">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                                                        Expected impact: Strengthens Target Page authority
                                                    </div>
                                                </div>
                                                <div class="change-actions">
                                                    <button class="btn btn-deny">Deny</button>
                                                    <button class="btn btn-approve">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                        Approve
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Change 2 - Destructive -->
                                        <div class="change-card">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="change-header">
                                                        <span class="risk-destructive">Destructive</span>
                                                        <span class="change-type">redirect</span>
                                                    </div>
                                                    <div class="change-title">Redirect /old-kitchen-page → /kitchen-remodel-guide</div>
                                                    <div class="change-doctrine"><span>DOCTRINE:</span> CANN_RESTORE_001</div>
                                                    <div class="change-impact">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                                                        Expected impact: Consolidates 890 monthly impressions
                                                    </div>
                                                    <div class="rollback-notice">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
                                                        48-hour rollback available after execution
                                                    </div>
                                                </div>
                                                <div class="change-actions">
                                                    <button class="btn btn-deny">Deny</button>
                                                    <button class="btn btn-approve">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                        Approve
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Change 3 -->
                                        <div class="change-card">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="change-header">
                                                        <span class="risk-safe">Safe</span>
                                                        <span class="change-type">content generate</span>
                                                    </div>
                                                    <div class="change-title">Generate Supporting Page: "Kitchen Lighting Guide"</div>
                                                    <div class="change-doctrine"><span>DOCTRINE:</span> ARCH_003</div>
                                                    <div class="change-impact">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                                                        Expected impact: Fills entity gap in silo
                                                    </div>
                                                </div>
                                                <div class="change-actions">
                                                    <button class="btn btn-deny">Deny</button>
                                                    <button class="btn btn-approve">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                        Approve
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Content Tab -->
                            <div id="tab-content" class="tab-content">
                                <div class="card">
                                    <h2 class="text-2xl font-bold mb-2">Content Generation</h2>
                                    <p class="section-subtitle mb-8">Generate content that fits your Reverse Silo architecture</p>
                                    
                                    <div class="grid-2-cols">
                                        <!-- Action 1 -->
                                        <div class="content-action-card" data-modal="generate">
                                            <div class="content-action-icon amber">👑</div>
                                            <h3 class="content-action-title">Generate Target Page</h3>
                                            <p class="content-action-desc">Create a new pillar page (King) that will receive links from Supporting Pages</p>
                                        </div>
                                        
                                        <!-- Action 2 -->
                                        <div class="content-action-card" data-modal="generate">
                                            <div class="content-action-icon indigo">⚔️</div>
                                            <h3 class="content-action-title">Generate Supporting Page</h3>
                                            <p class="content-action-desc">Create a Soldier page that links UP to a Target Page</p>
                                        </div>
                                        
                                        <!-- Action 3 -->
                                        <div class="content-action-card" data-modal="generate">
                                            <div class="content-action-icon red">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                                            </div>
                                            <h3 class="content-action-title">Differentiate Page</h3>
                                            <p class="content-action-desc">Rewrite content to target different entities and eliminate cannibalization</p>
                                        </div>
                                        
                                        <!-- Action 4 -->
                                        <div class="content-action-card" data-modal="generate">
                                            <div class="content-action-icon emerald">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg>
                                            </div>
                                            <h3 class="content-action-title">Fill Entity Gap</h3>
                                            <p class="content-action-desc">Generate content for entity clusters not yet covered in a silo</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Terminal Preview -->
                                    <div class="terminal-preview">
                                        <div class="terminal-label">Agent Console Preview</div>
                                        <div class="terminal-content">
                                            <div class="terminal-line green">&gt; Scanning site architecture...</div>
                                            <div class="terminal-line blue">&gt; Locking primary intent...</div>
                                            <div class="terminal-line violet">&gt; Enforcing entity inheritance...</div>
                                            <div class="terminal-line amber">&gt; Blocking unauthorized outbound links...</div>
                                            <div class="terminal-line green">&gt; Generating structured output...</div>
                                        </div>
                                        <div class="terminal-note">
                                            This terminal animation appears before content generation — differentiates Siloq from generic AI tools.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Links Tab -->
                            <div id="tab-links" class="tab-content">
                                <div class="card">
                                    <h2 class="text-2xl font-bold mb-2">Internal Links</h2>
                                    <p class="section-subtitle mb-8">Manage and optimize internal linking structure</p>
                                    
                                    <div class="bg-slate-900/40 rounded-xl p-8 text-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: #64748b; margin: 0 auto 16px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                        <h3 class="text-lg font-semibold mb-2">Internal Link Management</h3>
                                        <p class="section-subtitle">Link optimization features coming soon...</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Settings Tab -->
                            <div id="tab-settings" class="tab-content">
                                <div class="card">
                                    <h2 class="text-2xl font-bold mb-8">Settings</h2>
                                    
                                    <!-- Automation Preferences -->
                                    <div class="mb-8">
                                        <h3 class="text-base font-semibold mb-4">Automation Preferences</h3>
                                        
                                        <div class="settings-option active" data-mode="manual">
                                            <div>
                                                <div class="settings-option-title">Manual</div>
                                                <div class="settings-option-desc">All changes require explicit approval before execution</div>
                                            </div>
                                            <div class="settings-check">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            </div>
                                        </div>
                                        
                                        <div class="settings-option" data-mode="semi">
                                            <div>
                                                <div class="settings-option-title">Semi-Auto</div>
                                                <div class="settings-option-desc">Safe changes auto-execute immediately. Destructive changes require explicit approval.</div>
                                            </div>
                                        </div>
                                        
                                        <div class="settings-option" data-mode="full">
                                            <div>
                                                <div class="settings-option-title">Full-Auto</div>
                                                <div class="settings-option-desc">All changes auto-execute immediately. 48-hour rollback window on destructive changes. Daily digest email notification.</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Change Classification Legend -->
                                    <div class="settings-legend mb-8">
                                        <h4 class="settings-legend-title">Change Classification Reference</h4>
                                        <div class="settings-legend-grid">
                                            <div>
                                                <div class="legend-column-title safe">Safe (can auto-approve)</div>
                                                <div class="legend-list">
                                                    <div>• Internal link additions</div>
                                                    <div>• Entity assignments</div>
                                                    <div>• New content generation</div>
                                                    <div>• Anchor text optimization</div>
                                                    <div>• Schema markup updates</div>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="legend-column-title destructive">Destructive (approval or rollback)</div>
                                                <div class="legend-list">
                                                    <div>• URL redirects (301s)</div>
                                                    <div>• Page deletions/archival</div>
                                                    <div>• Content merges</div>
                                                    <div>• Keyword reassignment</div>
                                                    <div>• Silo restructuring</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Notification Preferences -->
                                    <div>
                                        <h3 class="text-base font-semibold mb-4">Notification Preferences</h3>
                                        
                                        <div class="toggle-item">
                                            <span class="toggle-label">Daily digest email (Full-Auto mode)</span>
                                            <div class="toggle-switch active"></div>
                                        </div>
                                        
                                        <div class="toggle-item">
                                            <span class="toggle-label">Immediate alerts for BLOCK errors</span>
                                            <div class="toggle-switch active"></div>
                                        </div>
                                        
                                        <div class="toggle-item">
                                            <span class="toggle-label">Weekly governance report</span>
                                            <div class="toggle-switch"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        </main>
                    </div>
                </div>
                
                <!-- Generate Modal -->
                <div id="generate-modal" class="modal-overlay">
                    <div class="modal-content" style="max-width: 500px;">
                        <div class="modal-header">
                            <h2 class="modal-title">Generate Supporting Page</h2>
                            <button class="modal-close">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Target Silo (links UP to this Target Page)</label>
                            <select class="form-select">
                                <option>👑 Kitchen Remodeling → Complete Kitchen Remodeling Guide</option>
                                <option>👑 Bathroom Renovation → Bathroom Renovation Planning</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Content Type</label>
                            <select class="form-select">
                                <option>Supporting Article (Soldier)</option>
                                <option>FAQ Page</option>
                                <option>How-To Guide</option>
                                <option>Comparison Article</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Target Entity Cluster</label>
                            <input type="text" class="form-input" placeholder="e.g., kitchen lighting, under-cabinet lights, pendant lights">
                            <div class="form-hint">Entity sources: NLP extraction • Google Knowledge Graph • GSC queries</div>
                        </div>
                        
                        <div class="form-info-box">
                            <div class="form-info-title">Siloq will automatically:</div>
                            <div class="form-info-list">
                                <div>• Check for entity overlap with sibling pages</div>
                                <div>• Include internal link to Target Page</div>
                                <div>• Apply schema markup</div>
                                <div>• Queue for your approval before publishing</div>
                            </div>
                        </div>
                        
                        <div class="modal-actions">
                            <button class="btn btn-secondary modal-close-btn">Cancel</button>
                            <button class="btn btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                                Generate Content
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Approval Modal -->
                <div id="approval-modal" class="modal-overlay">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title">Siloq Recommendation</h2>
                            <button class="modal-close">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                        </div>
                        
                        <div class="modal-alert">
                            <div class="modal-alert-label">CANNIBALIZATION DETECTED</div>
                            <div class="modal-alert-title">3 pages competing for "kitchen remodeling"</div>
                            <div class="modal-alert-text">Splitting 12,400 monthly impressions across URLs</div>
                        </div>
                        
                        <div style="margin-bottom: 24px;">
                            <div class="text-sm font-semibold mb-3" style="color: #e2e8f0;">Recommended Fix:</div>
                            <div class="modal-steps">
                                <div class="modal-step">
                                    <span class="modal-step-number">1.</span>
                                    Designate <code>/kitchen-remodel-guide</code> as Target Page
                                </div>
                                <div class="modal-step">
                                    <span class="modal-step-number">2.</span>
                                    Redirect <code>/remodel-your-kitchen</code> → Target (301)
                                </div>
                                <div class="modal-step">
                                    <span class="modal-step-number">3.</span>
                                    Differentiate <code>/kitchen-remodel-cost</code> to target "cost" entities only
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-outcome">
                            <div class="modal-outcome-label">EXPECTED OUTCOME</div>
                            <div class="modal-outcome-text">Consolidate ranking signals → single Target Page receives full 12,400 impression authority</div>
                        </div>
                        
                        <div class="modal-actions">
                            <button class="btn btn-deny">Deny</button>
                            <button class="btn btn-secondary modal-close-btn">Modify</button>
                            <button class="btn btn-approve">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                Approve All 3 Actions
                            </button>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        // Handle form submission
        if (isset($_POST['siloq_save_settings']) && check_admin_referer('siloq_settings_nonce')) {
            self::save_settings();
        }
        
        // Get current settings
        $api_url = get_option('siloq_api_url', '');
        $api_key = get_option('siloq_api_key', '');
        $auto_sync = get_option('siloq_auto_sync', 'no');
        $signup_url = get_option('siloq_signup_url', '');
        
        ?>
        <div class="wrap">
            <h1><?php _e('Siloq Settings', 'siloq-connector'); ?></h1>
            
            <div class="siloq-settings-container">
                <form method="post" action="">
                    <?php wp_nonce_field('siloq_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="siloq_api_url">
                                    <?php _e('API URL', 'siloq-connector'); ?>
                                    <span class="required">*</span>
                                </label>
                            </th>
                            <td>
                                <input 
                                    type="url" 
                                    id="siloq_api_url" 
                                    name="siloq_api_url" 
                                    value="<?php echo esc_attr($api_url); ?>" 
                                    class="regular-text"
                                    placeholder="https://api.siloq.com/api/v1"
                                    required
                                />
                                <p class="description">
                                    <?php _e('The base URL of your Siloq API endpoint (e.g., http://your-server-ip:3000/api/v1)', 'siloq-connector'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="siloq_api_key">
                                    <?php _e('API Key', 'siloq-connector'); ?>
                                    <span class="required">*</span>
                                </label>
                            </th>
                            <td>
                                <input 
                                    type="password" 
                                    id="siloq_api_key" 
                                    name="siloq_api_key" 
                                    value="<?php echo esc_attr($api_key); ?>" 
                                    class="regular-text"
                                    placeholder="sk_..."
                                    required
                                />
                                <p class="description">
                                    <?php _e('Your Siloq API authentication key', 'siloq-connector'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <?php _e('Auto-Sync', 'siloq-connector'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="siloq_auto_sync"
                                            value="yes"
                                            <?php checked($auto_sync, 'yes'); ?>
                                        />
                                        <?php _e('Automatically sync pages when published or updated', 'siloq-connector'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="siloq_signup_url">
                                    <?php _e('Lead Gen Signup URL', 'siloq-connector'); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                    type="url"
                                    id="siloq_signup_url"
                                    name="siloq_signup_url"
                                    value="<?php echo esc_attr($signup_url); ?>"
                                    class="regular-text"
                                    placeholder="https://app.siloq.ai/signup?plan=blueprint"
                                />
                                <p class="description">
                                    <?php _e('The URL where users are redirected after viewing scan results. Leave empty to use the default Siloq signup URL. Use shortcode: [siloq_scanner]', 'siloq-connector'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="siloq_save_settings" class="button button-primary">
                            <?php _e('Save Settings', 'siloq-connector'); ?>
                        </button>
                        
                        <button type="button" id="siloq-test-connection" class="button button-secondary">
                            <?php _e('Test Connection', 'siloq-connector'); ?>
                        </button>
                        
                        <span id="siloq-connection-status" class="siloq-status-message"></span>
                    </p>
                </form>
                
                <hr>
                
                <h2><?php _e('Bulk Actions', 'siloq-connector'); ?></h2>
                
                <p>
                    <button type="button" id="siloq-sync-all-pages" class="button button-secondary">
                        <?php _e('Sync All Pages', 'siloq-connector'); ?>
                    </button>
                    <span class="description">
                        <?php _e('Sync all published pages to Siloq. This may take a few minutes.', 'siloq-connector'); ?>
                    </span>
                </p>
                
                <div id="siloq-sync-progress" class="siloq-sync-progress" style="display:none;">
                    <p><strong><?php _e('Syncing pages...', 'siloq-connector'); ?></strong></p>
                    <div class="siloq-progress-bar">
                        <div class="siloq-progress-fill" style="width: 0%"></div>
                    </div>
                    <p class="siloq-progress-text">0 / 0</p>
                </div>
                
                <div id="siloq-sync-results" class="siloq-sync-results" style="display:none;"></div>
                
                <hr>
                
                <h2><?php _e('Documentation', 'siloq-connector'); ?></h2>
                
                <p>
                    <?php _e('For more information about setting up and using the Siloq Connector plugin, please visit:', 'siloq-connector'); ?>
                </p>
                <ul>
                    <li><a href="https://github.com/Siloq-seo/siloq-wordpress-plugin" target="_blank"><?php _e('Plugin Documentation', 'siloq-connector'); ?></a></li>
                    <li><a href="https://siloq.com/docs" target="_blank"><?php _e('Siloq Platform Documentation', 'siloq-connector'); ?></a></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $api_url = isset($_POST['siloq_api_url']) ? sanitize_text_field($_POST['siloq_api_url']) : '';
        $api_key = isset($_POST['siloq_api_key']) ? sanitize_text_field($_POST['siloq_api_key']) : '';
        $auto_sync = isset($_POST['siloq_auto_sync']) ? 'yes' : 'no';
        $signup_url = isset($_POST['siloq_signup_url']) ? esc_url_raw($_POST['siloq_signup_url']) : '';
        
        // Validate
        $errors = array();
        
        if (empty($api_url)) {
            $errors[] = __('API URL is required', 'siloq-connector');
        } elseif (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            $errors[] = __('API URL is not valid', 'siloq-connector');
        }
        
        if (empty($api_key)) {
            $errors[] = __('API Key is required', 'siloq-connector');
        }
        
        if (!empty($errors)) {
            add_settings_error(
                'siloq_settings',
                'siloq_validation_error',
                implode('<br>', $errors),
                'error'
            );
            return;
        }
        
        // Save
        $old_api_url = get_option('siloq_api_url');
        $old_api_key = get_option('siloq_api_key');
        
        update_option('siloq_api_url', $api_url);
        update_option('siloq_api_key', $api_key);
        update_option('siloq_auto_sync', $auto_sync);
        update_option('siloq_signup_url', $signup_url);
        
        // If API credentials changed, clear cached sync statuses (optional)
        if ($old_api_url !== $api_url || $old_api_key !== $api_key) {
            // Could optionally clear all sync statuses here if needed
            // This is a design decision - you may want to keep existing sync data
        }
        
        add_settings_error(
            'siloq_settings',
            'siloq_settings_saved',
            __('Settings saved successfully!', 'siloq-connector'),
            'success'
        );
    }
    
    /**
     * Render sync status page
     */
    public static function render_sync_status_page() {
        $sync_engine = new Siloq_Sync_Engine();
        $pages_status = $sync_engine->get_all_sync_status();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Sync Status', 'siloq-connector'); ?></h1>
            
            <div class="siloq-sync-status-container">
                <p>
                    <button type="button" id="siloq-refresh-status" class="button button-secondary">
                        <?php _e('Refresh', 'siloq-connector'); ?>
                    </button>
                    
                    <?php
                    $pages_needing_resync = $sync_engine->get_pages_needing_resync();
                    if (!empty($pages_needing_resync)) {
                        ?>
                        <button type="button" id="siloq-sync-outdated" class="button button-secondary">
                            <?php printf(__('Sync %d Outdated Pages', 'siloq-connector'), count($pages_needing_resync)); ?>
                        </button>
                        <?php
                    }
                    ?>
                </p>
                
                <?php if (empty($pages_status)): ?>
                    <p><?php _e('No pages found.', 'siloq-connector'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Page Title', 'siloq-connector'); ?></th>
                                <th><?php _e('Status', 'siloq-connector'); ?></th>
                                <th><?php _e('Last Synced', 'siloq-connector'); ?></th>
                                <th><?php _e('Schema', 'siloq-connector'); ?></th>
                                <th><?php _e('Actions', 'siloq-connector'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pages_status as $page): ?>
                                <?php
                                $needs_resync = $sync_engine->needs_resync($page['id']);
                                $status_class = '';
                                $status_text = '';
                                
                                switch ($page['sync_status']) {
                                    case 'synced':
                                        $status_class = $needs_resync ? 'warning' : 'success';
                                        $status_text = $needs_resync ? __('Needs Re-sync', 'siloq-connector') : __('Synced', 'siloq-connector');
                                        break;
                                    case 'error':
                                        $status_class = 'error';
                                        $status_text = __('Error', 'siloq-connector');
                                        break;
                                    default:
                                        $status_class = 'not-synced';
                                        $status_text = __('Not Synced', 'siloq-connector');
                                }
                                ?>
                                <tr data-page-id="<?php echo esc_attr($page['id']); ?>">
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url($page['edit_url']); ?>">
                                                <?php echo esc_html($page['title']); ?>
                                            </a>
                                        </strong>
                                        <br>
                                        <small>
                                            <a href="<?php echo esc_url($page['url']); ?>" target="_blank">
                                                <?php _e('View', 'siloq-connector'); ?>
                                            </a>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="siloq-status-badge siloq-status-<?php echo esc_attr($status_class); ?>">
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo esc_html($page['last_synced']); ?>
                                    </td>
                                    <td>
                                        <?php if ($page['has_schema']): ?>
                                            <span class="dashicons dashicons-yes-alt" style="color: green;" title="<?php _e('Schema markup present', 'siloq-connector'); ?>"></span>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-minus" style="color: #999;" title="<?php _e('No schema markup', 'siloq-connector'); ?>"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button 
                                            type="button" 
                                            class="button button-small siloq-sync-single" 
                                            data-page-id="<?php echo esc_attr($page['id']); ?>"
                                        >
                                            <?php _e('Sync Now', 'siloq-connector'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render content import page
     */
    public static function render_content_import_page() {
        $import_handler = new Siloq_Content_Import();
        
        // Get all pages with available jobs
        $pages = get_posts(array(
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft'),
            'meta_query' => array(
                array(
                    'key' => '_siloq_content_ready',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Content Import', 'siloq-connector'); ?></h1>
            
            <div class="siloq-content-import-container">
                <p class="description">
                    <?php _e('AI-generated content from Siloq is ready to be imported. Review and import content for your pages below.', 'siloq-connector'); ?>
                </p>
                
                <?php if (empty($pages)): ?>
                    <div class="notice notice-info">
                        <p><?php _e('No AI-generated content available yet. Generate content for your pages first.', 'siloq-connector'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Page Title', 'siloq-connector'); ?></th>
                                <th><?php _e('Content Ready', 'siloq-connector'); ?></th>
                                <th><?php _e('Actions', 'siloq-connector'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pages as $page): ?>
                                <?php
                                $jobs = $import_handler->get_available_jobs($page->ID);
                                $ready_at = get_post_meta($page->ID, '_siloq_content_ready_at', true);
                                $has_backup = !empty(get_post_meta($page->ID, '_siloq_backup_content', true));
                                ?>
                                <tr data-page-id="<?php echo esc_attr($page->ID); ?>">
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url(get_edit_post_link($page->ID)); ?>">
                                                <?php echo esc_html($page->post_title); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($ready_at) {
                                            echo esc_html(human_time_diff(strtotime($ready_at), current_time('timestamp'))) . ' ' . __('ago', 'siloq-connector');
                                        } else {
                                            _e('Recently', 'siloq-connector');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($jobs)): ?>
                                            <?php foreach ($jobs as $job): ?>
                                                <button 
                                                    type="button" 
                                                    class="button button-primary siloq-import-content" 
                                                    data-page-id="<?php echo esc_attr($page->ID); ?>"
                                                    data-job-id="<?php echo esc_attr($job['job_id']); ?>"
                                                    data-action="create_draft"
                                                >
                                                    <?php _e('Import as Draft', 'siloq-connector'); ?>
                                                </button>
                                                
                                                <button 
                                                    type="button" 
                                                    class="button button-secondary siloq-import-content" 
                                                    data-page-id="<?php echo esc_attr($page->ID); ?>"
                                                    data-job-id="<?php echo esc_attr($job['job_id']); ?>"
                                                    data-action="replace"
                                                >
                                                    <?php _e('Replace Content', 'siloq-connector'); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($has_backup): ?>
                                            <button 
                                                type="button" 
                                                class="button button-link-delete siloq-restore-backup" 
                                                data-page-id="<?php echo esc_attr($page->ID); ?>"
                                            >
                                                <?php _e('Restore Backup', 'siloq-connector'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <hr>
                
                <h2><?php _e('Generate New Content', 'siloq-connector'); ?></h2>
                
                <p class="description">
                    <?php _e('Select a page to generate AI-powered content using Siloq.', 'siloq-connector'); ?>
                </p>
                
                <?php
                // Get all published pages
                $all_pages = get_posts(array(
                    'post_type' => 'page',
                    'posts_per_page' => -1,
                    'post_status' => 'publish'
                ));
                ?>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('Select Page', 'siloq-connector'); ?></th>
                        <td>
                            <select id="siloq-generate-page-select" class="regular-text">
                                <option value=""><?php _e('-- Select a page --', 'siloq-connector'); ?></option>
                                <?php foreach ($all_pages as $p): ?>
                                    <option value="<?php echo esc_attr($p->ID); ?>">
                                        <?php echo esc_html($p->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button type="button" id="siloq-generate-content" class="button button-primary">
                                <?php _e('Generate Content', 'siloq-connector'); ?>
                            </button>
                            
                            <p class="description">
                                <?php _e('This will create an AI content generation job. You will be notified when the content is ready.', 'siloq-connector'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div id="siloq-generation-status" style="display:none;"></div>
                
                <hr>
                
                <h2><?php _e('Webhook Configuration', 'siloq-connector'); ?></h2>
                
                <p>
                    <?php _e('Configure this webhook URL in your Siloq backend to receive real-time updates:', 'siloq-connector'); ?>
                </p>
                
                <code style="display: block; padding: 10px; background: #f0f0f1; margin: 10px 0;">
                    <?php echo esc_html(Siloq_Webhook_Handler::get_webhook_url()); ?>
                </code>
                
                <p class="description">
                    <?php _e('The webhook allows Siloq to notify WordPress when content is generated, schema is updated, or other events occur.', 'siloq-connector'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
