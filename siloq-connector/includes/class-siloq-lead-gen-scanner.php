<?php
/**
 * Siloq Lead Gen Scanner
 *
 * Provides a shortcode for embedding a lead magnet website scanner
 * that captures visitor emails and shows teaser results.
 *
 * @package Siloq_Connector
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Lead_Gen_Scanner {

    /**
     * API client instance
     */
    private $api_client;

    /**
     * Constructor
     */
    public function __construct($api_client) {
        $this->api_client = $api_client;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register shortcode
        add_shortcode('siloq_scanner', array($this, 'render_scanner_shortcode'));

        // AJAX handlers
        add_action('wp_ajax_siloq_submit_scan', array($this, 'ajax_submit_scan'));
        add_action('wp_ajax_nopriv_siloq_submit_scan', array($this, 'ajax_submit_scan'));

        add_action('wp_ajax_siloq_poll_scan', array($this, 'ajax_poll_scan'));
        add_action('wp_ajax_nopriv_siloq_poll_scan', array($this, 'ajax_poll_scan'));

        add_action('wp_ajax_siloq_get_full_report', array($this, 'ajax_get_full_report'));
        add_action('wp_ajax_nopriv_siloq_get_full_report', array($this, 'ajax_get_full_report'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets() {
        // Only enqueue if shortcode is present on the page
        global $post;
        if (!$post || !is_a($post, 'WP_Post')) {
            return;
        }
        $content = isset($post->post_content) && is_string($post->post_content) ? $post->post_content : '';
        if ($content === '' || !has_shortcode($content, 'siloq_scanner')) {
            return;
        }

        wp_enqueue_style(
                'siloq-lead-gen-scanner',
                SILOQ_PLUGIN_URL . 'assets/css/lead-gen-scanner.css',
                array(),
                SILOQ_VERSION
            );

            wp_enqueue_script(
                'siloq-lead-gen-scanner',
                SILOQ_PLUGIN_URL . 'assets/js/lead-gen-scanner.js',
                array('jquery'),
                SILOQ_VERSION,
                true
            );

        wp_localize_script('siloq-lead-gen-scanner', 'siloqScanner', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('siloq_scanner_nonce'),
            'signupUrl' => $this->get_signup_url(),
        ));
    }

    /**
     * Render scanner shortcode
     *
     * Usage: [siloq_scanner]
     * Attributes:
     *   - title: Custom heading (default: "Free SEO Audit")
     *   - button_text: Custom button text (default: "Scan My Site")
     *   - signup_url: Custom signup URL (default: from settings)
     */
    public function render_scanner_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Free SEO Audit',
            'button_text' => 'Scan My Site',
            'signup_url' => $this->get_signup_url(),
            'show_page_title' => 'yes',
            'show_header' => 'yes',
            'contact_url' => '#',
        ), $atts, 'siloq_scanner');

        ob_start();
        ?>
        <div class="siloq-lead-gen-wrap">
            <?php if ($atts['show_header'] === 'yes') : ?>
     
            <?php endif; ?>

            <?php if ($atts['show_page_title'] === 'yes') : ?>
            <h1 class="siloq-lead-gen-page-title">Lead Generation</h1>
            <?php endif; ?>

        <div class="siloq-scanner-widget" data-signup-url="<?php echo esc_url($atts['signup_url']); ?>">
            <!-- Step 1: Input Form -->
            <div class="siloq-scanner-form" id="siloq-scanner-form">
                <h3 class="siloq-scanner-title"><?php echo esc_html($atts['title']); ?></h3>
                <p class="siloq-scanner-subtitle">Discover what's holding your website back from ranking higher.</p>

                <form id="siloq-scanner-submit-form">
                    <div class="siloq-form-group">
                        <label for="siloq-website-url">Website URL</label>
                        <input
                            type="url"
                            id="siloq-website-url"
                            name="website_url"
                            placeholder="https://domain.com"
                            value=""
                            required
                            pattern="https?://.+"
                        />
                    </div>

                    <div class="siloq-form-group">
                        <label for="siloq-email">Email Address</label>
                        <input
                            type="email"
                            id="siloq-email"
                            name="email"
                            placeholder="name@domain.com"
                            value=""
                            required
                        />
                    </div>

                    <button type="submit" class="siloq-submit-btn">
                        <?php echo esc_html($atts['button_text']); ?>
                    </button>

                    <p class="siloq-privacy-note">We respect your privacy. No spam, ever.</p>

                    <div class="siloq-error-message" id="siloq-error-message" style="display: none;"></div>
                </form>
            </div>

            <!-- Step 2: Scanning Progress -->
            <div class="siloq-scanner-progress" id="siloq-scanner-progress" style="display: none;">
                <div class="siloq-spinner"></div>
                <h3>Analyzing Your Website...</h3>
                <p class="siloq-progress-text">This may take up to 30 seconds</p>
                <div class="siloq-progress-bar">
                    <div class="siloq-progress-fill" id="siloq-progress-fill"></div>
                </div>
            </div>

            <!-- Step 3: Results (real API data) -->
            <div class="siloq-scanner-results" id="siloq-scanner-results" style="display: none;">
                <div class="siloq-score-display">
                    <div class="siloq-score-circle">
                        <span class="siloq-score-value" id="siloq-score-value">0</span>
                        <span class="siloq-score-label">Score</span>
                    </div>
                    <div class="siloq-grade-badge" id="siloq-grade-badge">F</div>
                </div>

                <div class="siloq-scan-meta" id="siloq-scan-meta">
                    <span id="siloq-scan-url"></span>
                    <span id="siloq-pages-analyzed"></span>
                    <span id="siloq-scan-duration"></span>
                </div>

                <div class="siloq-score-breakdown" id="siloq-score-breakdown">
                    <!-- Technical, Content, Structure, Performance, SEO scores injected by JS -->
                </div>

                <h3 class="siloq-results-title">
                    <span id="siloq-issues-count">0</span> Critical Issues Found
                </h3>

                <div class="siloq-issues-preview" id="siloq-issues-preview">
                    <!-- All recommendations from API injected by JS -->
                </div>

                <div class="siloq-cta-section">
                    <p class="siloq-cta-text">
                        <strong>Want the full report?</strong><br>
                        Get detailed recommendations and actionable fixes to improve your SEO.
                    </p>
                    <a href="#" class="siloq-cta-btn" id="siloq-get-full-report">
                        Get Full Report &rarr;
                    </a>
                    <p class="siloq-cta-subtext">
                        <span id="siloq-hidden-issues">0</span> more issues + step-by-step fixes
                    </p>
                </div>
            </div>
        </div>
        </div><!-- .siloq-lead-gen-wrap -->
        <?php
        return ob_get_clean();
    }

    /**
     * Whether to use dummy scan API (no real backend)
     */
    private function use_dummy_scan() {
        if (get_option('siloq_use_dummy_scan', 'yes') === 'yes') {
            return true;
        }
        $api_url = get_option('siloq_api_url', '');
        return empty($api_url);
    }

    /**
     * Dummy teaser data for testing
     */
    private function get_dummy_teaser_data($url = '') {
        return array(
            'overall_score' => 62,
            'grade' => 'D',
            'issues' => array(
                array('category' => 'Technical SEO', 'issue' => 'Missing meta description on key pages', 'action' => 'Add unique meta descriptions under 160 characters'),
                array('category' => 'Content', 'issue' => 'Thin content on product pages', 'action' => 'Expand product copy with benefits and keywords'),
                array('category' => 'Performance', 'issue' => 'Large images slowing page load', 'action' => 'Compress and use next-gen formats (WebP)'),
            ),
            'total_issues' => 8,
            'hidden_issues' => 5,
            'url' => $url,
        );
    }

    /**
     * AJAX: Submit scan request
     */
    public function ajax_submit_scan() {
        // Verify nonce (wp_verify_nonce avoids 403 when Referer is stripped)
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'siloq_scanner_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
            return;
        }

        // Validate inputs
        $website_url = isset($_POST['website_url']) ? esc_url_raw($_POST['website_url']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($website_url) || !filter_var($website_url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(array(
                'message' => 'Please enter a valid website URL.',
            ));
        }

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array(
                'message' => 'Please enter a valid email address.',
            ));
        }

        // Store lead in WordPress (for future marketing)
        $this->store_lead($website_url, $email);

        // Require API to be configured – no dummy data
        $api_url = get_option('siloq_api_url', '');
        if (empty($api_url)) {
            wp_send_json_error(array(
                'message' => 'Please configure Siloq API URL and API Key in the plugin settings (Settings → Siloq).',
            ));
            return;
        }

        // Submit scan to Siloq API
        $response = $this->api_client->request('POST', '/scans', array(
            'url' => $website_url,
            'scan_type' => 'full',
        ));
        

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Network error. Please check your connection and try again.',
                'error' => $response->get_error_message(),
            ));
        }

        $scan_data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($scan_data['id'])) {
            wp_send_json_error(array(
                'message' => 'Invalid response from scanner. Please try again.',
            ));
        }

        // Update lead with scan ID
        $this->update_lead_scan_id($email, $scan_data['id']);

        wp_send_json_success(array(
            'scan_id' => $scan_data['id'],
            'status' => isset($scan_data['status']) ? $scan_data['status'] : 'processing',
            'message' => 'Scan started successfully.',
        ));
    }

    /**
     * AJAX: Poll scan results
     */
    public function ajax_poll_scan() {
        // Verify nonce (wp_verify_nonce avoids 403 when Referer is stripped)
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'siloq_scanner_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
            return;
        }

        $scan_id = isset($_POST['scan_id']) ? sanitize_text_field(wp_unslash($_POST['scan_id'])) : '';

        if (empty($scan_id)) {
            wp_send_json_error(array(
                'message' => 'Invalid scan ID.',
            ));
        }

        $api_url = get_option('siloq_api_url', '');
        if (empty($api_url)) {
            wp_send_json_error(array(
                'message' => 'API not configured. Please configure Siloq API in plugin settings.',
            ));
            return;
        }

        // Get scan results from Siloq API
        $response = $this->api_client->request('GET', '/scans/' . $scan_id);

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Network error. Please check your connection and try again.',
                'error' => $response->get_error_message(),
            ));
        }

        $scan_data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($scan_data['status'])) {
            wp_send_json_error(array(
                'message' => 'Invalid scan data.',
            ));
        }

        // If still processing, return status
        if (in_array($scan_data['status'], array('pending', 'processing'))) {
            wp_send_json_success(array(
                'status' => $scan_data['status'],
                'completed' => false,
            ));
        }

        // If failed, return error
        if ($scan_data['status'] === 'failed') {
            wp_send_json_error(array(
                'message' => 'Scan failed: ' . ($scan_data['error_message'] ?? 'Unknown error'),
            ));
        }

        // If completed, return full scan data for display (real API data only)
        if ($scan_data['status'] === 'completed') {
            $display_data = $this->build_scan_display_data($scan_data);

            wp_send_json_success(array(
                'status' => 'completed',
                'completed' => true,
                'data' => $display_data,
            ));
        }

        wp_send_json_error(array(
            'message' => 'Unknown scan status.',
        ));
    }

    /**
     * AJAX: Get full lead-gen report (Keyword Cannibalization Report)
     */
    public function ajax_get_full_report() {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'siloq_scanner_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
            return;
        }

        $scan_id = isset($_POST['scan_id']) ? sanitize_text_field(wp_unslash($_POST['scan_id'])) : '';

        if (empty($scan_id)) {
            wp_send_json_error(array('message' => 'Invalid scan ID.'));
        }

        $api_url = get_option('siloq_api_url', '');
        if (empty($api_url) || strpos($scan_id, 'dummy-') === 0) {
            wp_send_json_error(array(
                'message' => 'Configure Siloq API and run a real scan to view the full report.',
            ));
            return;
        }

        $response = $this->api_client->request('GET', '/scans/' . $scan_id . '/report', array());

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Could not load report. Please try again.',
                'error' => $response->get_error_message(),
            ));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body)) {
            wp_send_json_error(array(
                'message' => 'Invalid report response. Please try again.',
            ));
        }

        wp_send_json_success($body);
    }

    /**
     * Mock full report for dummy scan (no backend)
     */
    private function get_dummy_full_report($scan_id) {
        return array(
            'scan_id' => $scan_id,
            'scan_summary' => array(
                'website_url' => 'https://example.com',
                'total_pages_analyzed' => 12,
                'total_cannibalization_conflicts' => 4,
                'overall_risk_level' => 'Medium',
            ),
            'keyword_cannibalization_details' => array(
                array(
                    'keyword' => 'best running shoes',
                    'conflicting_urls' => array('https://example.com/shoes', 'https://example.com/best-shoes'),
                    'conflict_type' => 'same keyword',
                    'severity' => 'High',
                ),
                array(
                    'keyword' => 'running gear guide',
                    'conflicting_urls' => array('https://example.com/gear', 'https://example.com/guides'),
                    'conflict_type' => 'same intent',
                    'severity' => 'Medium',
                ),
            ),
            'educational_explanation' => array(
                'title' => 'What is keyword cannibalization?',
                'body' => 'Keyword cannibalization occurs when multiple pages on your site target the same or very similar keywords. Search engines may split rankings between these pages or pick the wrong one, which hurts your visibility and traffic. Consolidating or clearly differentiating content helps you rank better and gives users a clearer path.',
            ),
            'locked_recommendations' => array(
                'Page consolidation',
                'Primary keyword assignment',
                'Content silo restructuring',
            ),
            'upgrade_cta' => array(
                'label' => 'Unlock Full Report & Fix Issues',
                'scan_id_param' => 'scan_id',
            ),
        );
    }

    /**
     * Build full display data from scan API response (all real API data).
     */
    private function build_scan_display_data($scan_data) {
        $recommendations = isset($scan_data['recommendations']) ? $scan_data['recommendations'] : array();

        // Format all recommendations for frontend (category, issue, action, priority)
        $formatted_issues = array();
        foreach ($recommendations as $rec) {
            $formatted_issues[] = array(
                'category' => ucfirst($rec['category'] ?? 'SEO'),
                'issue' => $rec['issue'] ?? 'Issue detected',
                'action' => $rec['action'] ?? 'Action required',
                'priority' => $rec['priority'] ?? 'medium',
            );
        }

        $total_issues = count($recommendations);
        $hidden_count = max(0, $total_issues - 3);

        return array(
            // Summary (backward compatible)
            'overall_score' => round((float) ($scan_data['overall_score'] ?? 0)),
            'grade' => $scan_data['grade'] ?? 'F',
            'url' => $scan_data['url'] ?? '',
            'issues' => $formatted_issues,
            'total_issues' => $total_issues,
            'hidden_issues' => $hidden_count,
            // Full API data
            'pages_crawled' => (int) ($scan_data['pages_crawled'] ?? 0),
            'scan_duration_seconds' => isset($scan_data['scan_duration_seconds']) ? (int) $scan_data['scan_duration_seconds'] : null,
            'technical_score' => isset($scan_data['technical_score']) ? (float) $scan_data['technical_score'] : null,
            'content_score' => isset($scan_data['content_score']) ? (float) $scan_data['content_score'] : null,
            'structure_score' => isset($scan_data['structure_score']) ? (float) $scan_data['structure_score'] : null,
            'performance_score' => isset($scan_data['performance_score']) ? (float) $scan_data['performance_score'] : null,
            'seo_score' => isset($scan_data['seo_score']) ? (float) $scan_data['seo_score'] : null,
            'recommendations' => $formatted_issues,
            'technical_details' => isset($scan_data['technical_details']) ? $scan_data['technical_details'] : array(),
            'content_details' => isset($scan_data['content_details']) ? $scan_data['content_details'] : array(),
            'structure_details' => isset($scan_data['structure_details']) ? $scan_data['structure_details'] : array(),
            'performance_details' => isset($scan_data['performance_details']) ? $scan_data['performance_details'] : array(),
            'seo_details' => isset($scan_data['seo_details']) ? $scan_data['seo_details'] : array(),
        );
    }

    /**
     * Store lead in database
     */
    private function store_lead($website_url, $email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'siloq_leads';

        // Check if table exists, create if not
        $this->ensure_leads_table_exists();

        $wpdb->insert(
            $table_name,
            array(
                'email' => $email,
                'website_url' => $website_url,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Update lead with scan ID
     */
    private function update_lead_scan_id($email, $scan_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'siloq_leads';

        $wpdb->update(
            $table_name,
            array('scan_id' => $scan_id),
            array('email' => $email),
            array('%s'),
            array('%s')
        );
    }

    /**
     * Ensure leads table exists
     */
    private function ensure_leads_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'siloq_leads';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            website_url varchar(255) NOT NULL,
            scan_id varchar(36) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email(191))
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return 'UNKNOWN';
    }

    /**
     * Get signup URL from settings
     */
    private function get_signup_url() {
        $custom_url = get_option('siloq_signup_url', '');
        if (!empty($custom_url)) {
            return $custom_url;
        }

        // Default to Siloq signup with BLUEPRINT plan
        $base_url = get_option('siloq_api_base_url', 'https://api.siloq.io/v1');
        $app_url = str_replace('/v1', '', str_replace('api.', 'app.', $base_url));
        return $app_url . '/signup?plan=blueprint';
    }
}
