<?php
/**
 * Siloq Agent Pages — Approvals + Content Plan admin tabs
 *
 * @since 1.5.205
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Agent_Pages {

    /* ------------------------------------------------------------------ */
    /*  Action-type label map                                              */
    /* ------------------------------------------------------------------ */
    private static $action_labels = [
        'add_canonical'    => 'Add Canonical',
        'add_internal_link'=> 'Internal Link',
        'update_meta'      => 'Meta Update',
        'meta_update'      => 'Meta Update',
        'restructure_silo' => 'Silo Structure',
        'review_content'   => 'Review Content',
        'create_content'   => 'Create Page',
        'entity_fix'       => 'Entity Fix',
    ];

    /* ================================================================== */
    /*  1. APPROVALS PAGE                                                  */
    /* ================================================================== */
    public static function render_approvals_page() {
        $api_url = get_option('siloq_api_url', 'https://sea-lion-app-8rkgr.ondigitalocean.app');
        $api_key = get_option('siloq_api_key', '');
        $site_id = get_option('siloq_site_id', '');

        $actions = [];
        if ($api_key && $site_id) {
            $response = wp_remote_get(
                trailingslashit($api_url) . "sites/{$site_id}/pending-actions/?status=pending",
                [
                    'headers' => ['Authorization' => 'Bearer ' . $api_key],
                    'timeout' => 15,
                ]
            );
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $actions = is_array($body) ? $body : [];
                // Handle paginated response
                if (isset($body['results']) && is_array($body['results'])) {
                    $actions = $body['results'];
                }
            }
        }

        $total   = count($actions);
        $by_risk = ['low' => 0, 'medium' => 0, 'high' => 0];
        foreach ($actions as $a) {
            $r = strtolower($a['risk'] ?? 'low');
            if (isset($by_risk[$r])) $by_risk[$r]++;
        }

        $nonce = wp_create_nonce('siloq_agent_nonce');
        self::output_shared_css();
        ?>
        <div class="wrap siloq-approvals">
            <h1>&#9889; Agent Recommendations</h1>
            <p class="description">AI-generated fixes for your site. Review and approve.</p>

            <div class="siloq-stats-row">
                <div class="siloq-stat">
                    <span class="siloq-stat-num"><?php echo esc_html($total); ?></span>
                    <span class="siloq-stat-label">Pending</span>
                </div>
                <div class="siloq-stat">
                    <span class="siloq-stat-num" style="color:#46b450"><?php echo esc_html($by_risk['low']); ?></span>
                    <span class="siloq-stat-label">Low Risk</span>
                </div>
                <div class="siloq-stat">
                    <span class="siloq-stat-num" style="color:#f56e28"><?php echo esc_html($by_risk['medium']); ?></span>
                    <span class="siloq-stat-label">Medium Risk</span>
                </div>
                <div class="siloq-stat">
                    <span class="siloq-stat-num" style="color:#dc3232"><?php echo esc_html($by_risk['high']); ?></span>
                    <span class="siloq-stat-label">High Risk</span>
                </div>
            </div>

            <div class="siloq-cards">
            <?php if (empty($actions)): ?>
                <p>No pending recommendations. &#127881;</p>
            <?php else: ?>
                <?php foreach ($actions as $action):
                    $risk  = esc_attr(strtolower($action['risk'] ?? 'low'));
                    $type  = $action['action_type'] ?? '';
                    $label = self::$action_labels[$type] ?? ucwords(str_replace('_', ' ', $type));
                    $desc  = $action['description'] ?? '';
                    $doctrine = $action['doctrine'] ?? '';
                    $impact   = $action['impact'] ?? '';
                    $id       = intval($action['id'] ?? 0);
                ?>
                <div class="siloq-card siloq-card--<?php echo $risk; ?>">
                    <div class="siloq-card-header">
                        <span class="siloq-badge siloq-badge--<?php echo $risk; ?>"><?php echo esc_html($risk); ?></span>
                        <span class="siloq-badge siloq-badge--type"><?php echo esc_html($label); ?></span>
                    </div>
                    <div class="siloq-card-body">
                        <p class="siloq-card-desc"><?php echo esc_html($desc); ?></p>
                        <?php if ($doctrine): ?>
                            <p class="siloq-card-doctrine">&#128208; <?php echo esc_html($doctrine); ?></p>
                        <?php endif; ?>
                        <?php if ($impact): ?>
                            <p class="siloq-card-impact">&#128200; <?php echo esc_html($impact); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="siloq-card-footer">
                        <button class="button siloq-approve-btn"
                                data-action-id="<?php echo $id; ?>"
                                data-site-id="<?php echo esc_attr($site_id); ?>">&#9989; Approve</button>
                        <button class="button siloq-dismiss-btn"
                                data-action-id="<?php echo $id; ?>"
                                data-site-id="<?php echo esc_attr($site_id); ?>">&#10007; Dismiss</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            var siloqNonce = <?php echo wp_json_encode($nonce); ?>;

            $(document).on("click", ".siloq-approve-btn", function(){
                var btn = $(this);
                btn.prop("disabled", true).text("Approving...");
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "siloq_approve_action",
                        action_id: btn.data("action-id"),
                        site_id: btn.data("site-id"),
                        nonce: siloqNonce
                    },
                    success: function(resp){
                        if(resp.success){
                            btn.closest(".siloq-card").fadeOut(300, function(){ $(this).remove(); });
                        } else {
                            btn.prop("disabled", false).text("\u2705 Approve");
                            alert("Error: " + (resp.data || "Failed"));
                        }
                    },
                    error: function(){
                        btn.prop("disabled", false).text("\u2705 Approve");
                    }
                });
            });

            $(document).on("click", ".siloq-dismiss-btn", function(){
                var btn = $(this);
                btn.prop("disabled", true).text("Dismissing...");
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "siloq_dismiss_action",
                        action_id: btn.data("action-id"),
                        site_id: btn.data("site-id"),
                        nonce: siloqNonce
                    },
                    success: function(resp){
                        if(resp.success){
                            btn.closest(".siloq-card, .siloq-brief-card").fadeOut(300, function(){ $(this).remove(); });
                        } else {
                            btn.prop("disabled", false).text("\u2717 Dismiss");
                            alert("Error: " + (resp.data || "Failed"));
                        }
                    },
                    error: function(){
                        btn.prop("disabled", false).text("\u2717 Dismiss");
                    }
                });
            });
        });
        </script>
        <?php
    }

    /* ================================================================== */
    /*  2. CONTENT PLAN PAGE                                               */
    /* ================================================================== */
    public static function render_content_plan_page() {
        $api_url = get_option('siloq_api_url', 'https://sea-lion-app-8rkgr.ondigitalocean.app');
        $api_key = get_option('siloq_api_key', '');
        $site_id = get_option('siloq_site_id', '');

        $briefs = [];
        if ($api_key && $site_id) {
            $response = wp_remote_get(
                trailingslashit($api_url) . "sites/{$site_id}/pending-actions/?status=pending&action_type=create_content",
                [
                    'headers' => ['Authorization' => 'Bearer ' . $api_key],
                    'timeout' => 15,
                ]
            );
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $briefs = is_array($body) ? $body : [];
                if (isset($body['results']) && is_array($body['results'])) {
                    $briefs = $body['results'];
                }
            }
        }

        $nonce = wp_create_nonce('siloq_agent_nonce');
        self::output_shared_css();
        ?>
        <div class="wrap siloq-content-plan">
            <h1>&#128203; Content Plan</h1>
            <p class="description">AI-generated content briefs for missing pages in your silos.</p>

            <div class="siloq-toolbar">
                <button class="button button-primary" id="siloq-run-audit">
                    &#128269; Analyze Content Gaps
                </button>
                <span class="siloq-toolbar-note">Scans your site for missing content opportunities</span>
            </div>

            <div id="siloq-audit-status"></div>

            <div id="siloq-briefs-list">
            <?php if (empty($briefs)): ?>
                <p>No content briefs yet. Click "Analyze Content Gaps" to generate them.</p>
            <?php else: ?>
                <?php foreach ($briefs as $brief):
                    $desc    = $brief['description'] ?? '';
                    $title   = self::extract_title($desc);
                    $keyword = $brief['keyword'] ?? '';
                    $impact  = $brief['impact'] ?? '';
                    $id      = intval($brief['id'] ?? 0);
                ?>
                <?php
                    // Parse OS module and severity from description (Blueprint actions prefix with [Blueprint — OS])
                    $os_module  = '';
                    $severity   = strtolower( $impact ?: '' );
                    if ( preg_match( '/\[Blueprint — ([^\]]+)\]/', $desc, $m ) ) {
                        $os_module = $m[1];
                        $desc = trim( preg_replace( '/\[Blueprint — [^\]]+\]\s*/', '', $desc ) );
                        $title = $title ?: $desc;
                    }
                    $sev_color = $severity === 'critical' ? '#dc2626' : ( $severity === 'high' ? '#d97706' : '#6b7280' );
                ?>
                <div class="siloq-brief-card" style="border-left: 3px solid <?php echo esc_attr($sev_color); ?>;">
                    <div class="siloq-brief-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                        <h3 style="margin:0;"><?php echo esc_html($title ?: $desc); ?></h3>
                        <?php if ($os_module): ?>
                            <span style="background:<?php echo esc_attr($sev_color); ?>;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:4px;white-space:nowrap;flex-shrink:0;"><?php echo esc_html(strtoupper($severity)); ?> · <?php echo esc_html($os_module); ?></span>
                        <?php elseif ($keyword): ?>
                            <span class="siloq-brief-keyword"><?php echo esc_html($keyword); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="siloq-brief-body">
                        <p><?php echo esc_html($desc); ?></p>
                    </div>
                    <div class="siloq-brief-footer" style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                        <div>
                        <?php if ($impact && !$os_module): ?>
                            <span class="siloq-brief-impact" style="font-size:12px;color:#059669;"><?php echo esc_html(mb_substr($impact, 0, 120)); ?></span>
                        <?php endif; ?>
                        </div>
                        <div style="display:flex;gap:8px;flex-shrink:0;">
                        <?php
                            $action_type = $brief['action_type'] ?? 'create_content';
                            if ( $action_type === 'create_content' ) :
                                // Extract suggested title from description
                                $create_title = '';
                                if ( preg_match( '/Create page:\s*(.+?)(?:\s+targeting|\s*$)/i', $desc, $tm ) ) {
                                    $create_title = trim( $tm[1] );
                                } elseif ( preg_match( '/Create (.+?) page/i', $desc, $tm ) ) {
                                    $create_title = trim( $tm[1] );
                                }
                                $create_title = $create_title ?: $title;
                        ?>
                            <button class="button button-primary siloq-create-page-btn"
                                    data-title="<?php echo esc_attr( $create_title ); ?>"
                                    data-type="service">Create Page &rarr;</button>
                        <?php elseif ( $action_type === 'review_content' ) : ?>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=siloq&tab=pages') ); ?>"
                               class="button">Review Pages</a>
                        <?php elseif ( $action_type === 'restructure_silo' ) : ?>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=siloq&tab=silos') ); ?>"
                               class="button">View Architecture</a>
                        <?php elseif ( $action_type === 'add_internal_link' ) : ?>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=siloq&tab=links') ); ?>"
                               class="button">View Internal Links</a>
                        <?php elseif ( $action_type === 'add_canonical' ) : ?>
                            <a href="<?php echo esc_url( admin_url('admin.php?page=siloq&tab=pages') ); ?>"
                               class="button">Fix Canonical</a>
                        <?php endif; ?>
                            <button class="button siloq-dismiss-btn"
                                    data-action-id="<?php echo $id; ?>"
                                    data-site-id="<?php echo esc_attr($site_id); ?>">Dismiss</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            var siloqNonce = <?php echo wp_json_encode($nonce); ?>;

            $("#siloq-run-audit").on("click", function(){
                var btn = $(this);
                btn.prop("disabled", true).text("Scanning...");
                $("#siloq-audit-status").html('<p style="color:#0073aa">Running topical audit&hellip; this may take a moment.</p>');
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "siloq_run_topical_audit",
                        nonce: siloqNonce
                    },
                    success: function(resp){
                        if(resp.success){
                            $("#siloq-audit-status").html('<p style="color:#46b450">Audit complete. Reloading&hellip;</p>');
                            location.reload();
                        } else {
                            btn.prop("disabled", false).text("\uD83D\uDD0D Analyze Content Gaps");
                            $("#siloq-audit-status").html('<p style="color:#dc3232">Error: ' + (resp.data || "Failed") + '</p>');
                        }
                    },
                    error: function(){
                        btn.prop("disabled", false).text("\uD83D\uDD0D Analyze Content Gaps");
                        $("#siloq-audit-status").html('<p style="color:#dc3232">Request failed.</p>');
                    }
                });
            });

            $(document).on("click", ".siloq-dismiss-btn", function(){
                var btn = $(this);
                btn.prop("disabled", true).text("Dismissing...");
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "siloq_dismiss_action",
                        action_id: btn.data("action-id"),
                        site_id: btn.data("site-id"),
                        nonce: siloqNonce
                    },
                    success: function(resp){
                        if(resp.success){
                            btn.closest(".siloq-brief-card").fadeOut(300, function(){ $(this).remove(); });
                        } else {
                            btn.prop("disabled", false).text("Dismiss");
                            alert("Error: " + (resp.data || "Failed"));
                        }
                    },
                    error: function(){
                        btn.prop("disabled", false).text("Dismiss");
                    }
                });
            });
        });
        </script>
        <?php
    }

    /* ================================================================== */
    /*  AJAX handlers                                                      */
    /* ================================================================== */

    public static function ajax_approve_action() {
        check_ajax_referer('siloq_agent_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $action_id = intval($_POST['action_id']);
        $site_id   = sanitize_text_field($_POST['site_id']);
        $api_url   = get_option('siloq_api_url', 'https://sea-lion-app-8rkgr.ondigitalocean.app');
        $api_key   = get_option('siloq_api_key', '');

        $response = wp_remote_post(
            trailingslashit($api_url) . "sites/{$site_id}/approvals/{$action_id}/approve/",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            wp_send_json_error($body['error'] ?? 'API error');
        }

        wp_send_json_success(['status' => $body['status'] ?? 'actioned']);
    }

    public static function ajax_dismiss_action() {
        check_ajax_referer('siloq_agent_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $action_id = intval($_POST['action_id']);
        $site_id   = sanitize_text_field($_POST['site_id']);
        $api_url   = get_option('siloq_api_url', 'https://sea-lion-app-8rkgr.ondigitalocean.app');
        $api_key   = get_option('siloq_api_key', '');

        $response = wp_remote_post(
            trailingslashit($api_url) . "sites/{$site_id}/approvals/{$action_id}/dismiss/",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            wp_send_json_error($body['error'] ?? 'API error');
        }

        wp_send_json_success(['status' => $body['status'] ?? 'dismissed']);
    }

    public static function ajax_run_topical_audit() {
        check_ajax_referer('siloq_agent_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $site_id = get_option('siloq_site_id', '');
        $api_url = get_option('siloq_api_url', 'https://sea-lion-app-8rkgr.ondigitalocean.app');
        $api_key = get_option('siloq_api_key', '');

        $response = wp_remote_post(
            trailingslashit($api_url) . "sites/{$site_id}/agent/topical-audit/",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 120,  // Topical audit calls AI — needs longer timeout
            ]
        );

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            wp_send_json_error($body['error'] ?? 'Audit failed');
        }

        wp_send_json_success($body);
    }

    /* ================================================================== */
    /*  Helpers                                                            */
    /* ================================================================== */

    /**
     * Extract page title from content brief description.
     * Format: "[Silo Name] Create page: {TITLE} targeting..."
     */
    private static function extract_title($desc) {
        if (preg_match('/Create page:\s*(.+?)\s+targeting/i', $desc, $m)) {
            return trim($m[1]);
        }
        // Fallback: return first 60 chars
        return mb_substr($desc, 0, 60) . (mb_strlen($desc) > 60 ? '...' : '');
    }

    /**
     * Output shared CSS (called once per page render).
     */
    private static function output_shared_css() {
        static $output = false;
        if ($output) return;
        $output = true;
        ?>
        <style>
        .siloq-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(400px,1fr));gap:16px;margin-top:20px}
        .siloq-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;border-left:4px solid #ddd}
        .siloq-card--low{border-left-color:#46b450}
        .siloq-card--medium{border-left-color:#f56e28}
        .siloq-card--high{border-left-color:#dc3232}
        .siloq-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;text-transform:uppercase;margin-right:6px}
        .siloq-badge--low{background:#edfaee;color:#46b450}
        .siloq-badge--medium{background:#fef3ee;color:#f56e28}
        .siloq-badge--high{background:#fde8e8;color:#dc3232}
        .siloq-badge--type{background:#f0f6fc;color:#0073aa}
        .siloq-card-header{margin-bottom:8px}
        .siloq-card-desc{font-size:13px;line-height:1.5;margin:8px 0}
        .siloq-card-doctrine{font-size:12px;color:#666}
        .siloq-card-impact{font-size:12px;color:#46b450;font-weight:500}
        .siloq-card-footer{margin-top:12px;display:flex;gap:8px}
        .siloq-stats-row{display:flex;gap:16px;margin:16px 0}
        .siloq-stat{background:#fff;border:1px solid #ddd;border-radius:8px;padding:12px 20px;text-align:center}
        .siloq-stat-num{display:block;font-size:28px;font-weight:700;color:#0073aa}
        .siloq-stat-label{font-size:12px;color:#666}
        .siloq-brief-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px;margin-bottom:16px}
        .siloq-brief-header{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
        .siloq-brief-header h3{margin:0}
        .siloq-brief-keyword{background:#f0f6fc;color:#0073aa;padding:2px 8px;border-radius:12px;font-size:12px}
        .siloq-brief-body{margin:10px 0}
        .siloq-brief-footer{display:flex;align-items:center;gap:12px}
        .siloq-brief-impact{font-size:12px;color:#46b450;font-weight:500}
        .siloq-toolbar{margin:16px 0;display:flex;align-items:center;gap:12px}
        .siloq-toolbar-note{font-size:12px;color:#666}
        </style>
        <?php
    }
}
