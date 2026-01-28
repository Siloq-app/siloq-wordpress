<?php
/**
 * Lead Gen Scanner Verification Script
 *
 * Run this file from WordPress admin or via WP-CLI to verify scanner installation
 *
 * Usage:
 * 1. Via browser: Navigate to /wp-content/plugins/siloq-connector/verify-scanner.php
 * 2. Via WP-CLI: wp eval-file verify-scanner.php
 */

// Load WordPress
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Must be admin
if (!current_user_can('manage_options')) {
    die('You must be an administrator to run this verification script.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Siloq Lead Gen Scanner - Verification</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        h2 {
            color: #667eea;
            margin-top: 30px;
        }
        .status {
            padding: 10px 15px;
            border-radius: 4px;
            margin: 10px 0;
            display: inline-block;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background: #667eea;
            color: white;
        }
        .next-steps {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔍 Siloq Lead Gen Scanner Verification</h1>
        <p><strong>Version Check:</strong> <?php echo SILOQ_VERSION; ?></p>

        <?php
        $all_passed = true;

        // Check 1: Version
        echo "<h2>1. Plugin Version</h2>";
        if (SILOQ_VERSION === '1.1.0') {
            echo '<div class="status success">✅ Version 1.1.0 detected</div>';
        } else {
            echo '<div class="status error">❌ Expected version 1.1.0, found ' . SILOQ_VERSION . '</div>';
            $all_passed = false;
        }

        // Check 2: Scanner class file
        echo "<h2>2. Scanner Class File</h2>";
        $scanner_file = SILOQ_PLUGIN_DIR . 'includes/class-siloq-lead-gen-scanner.php';
        if (file_exists($scanner_file)) {
            echo '<div class="status success">✅ Scanner class file exists</div>';
            echo '<p><code>' . $scanner_file . '</code> (' . round(filesize($scanner_file)/1024, 1) . ' KB)</p>';
        } else {
            echo '<div class="status error">❌ Scanner class file missing</div>';
            $all_passed = false;
        }

        // Check 3: Scanner class loaded
        echo "<h2>3. Scanner Class Loaded</h2>";
        if (class_exists('Siloq_Lead_Gen_Scanner')) {
            echo '<div class="status success">✅ Scanner class loaded successfully</div>';
        } else {
            echo '<div class="status error">❌ Scanner class not loaded</div>';
            $all_passed = false;
        }

        // Check 4: Assets
        echo "<h2>4. Frontend Assets</h2>";
        $css_file = SILOQ_PLUGIN_DIR . 'assets/css/lead-gen-scanner.css';
        $js_file = SILOQ_PLUGIN_DIR . 'assets/js/lead-gen-scanner.js';

        if (file_exists($css_file)) {
            echo '<div class="status success">✅ CSS file exists (' . round(filesize($css_file)/1024, 1) . ' KB)</div>';
        } else {
            echo '<div class="status error">❌ CSS file missing</div>';
            $all_passed = false;
        }

        if (file_exists($js_file)) {
            echo '<div class="status success">✅ JS file exists (' . round(filesize($js_file)/1024, 1) . ' KB)</div>';
        } else {
            echo '<div class="status error">❌ JS file missing</div>';
            $all_passed = false;
        }

        // Check 5: Shortcode
        echo "<h2>5. Shortcode Registration</h2>";
        if (shortcode_exists('siloq_scanner')) {
            echo '<div class="status success">✅ Shortcode [siloq_scanner] registered</div>';
        } else {
            echo '<div class="status error">❌ Shortcode [siloq_scanner] not registered</div>';
            $all_passed = false;
        }

        // Check 6: Database table
        echo "<h2>6. Database Table</h2>";
        global $wpdb;
        $table_name = $wpdb->prefix . 'siloq_leads';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if ($table_exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            echo '<div class="status success">✅ Leads table exists</div>';
            echo '<p>Table: <code>' . $table_name . '</code></p>';
            echo '<p>Total leads: <strong>' . $count . '</strong></p>';
        } else {
            echo '<div class="status warning">⚠️ Leads table does not exist yet (will be created on first scan)</div>';
        }

        // Check 7: Settings
        echo "<h2>7. Plugin Settings</h2>";
        $api_url = get_option('siloq_api_url', '');
        $api_key = get_option('siloq_api_key', '');
        $signup_url = get_option('siloq_signup_url', '');

        echo '<table>';
        echo '<tr><th>Setting</th><th>Value</th><th>Status</th></tr>';

        echo '<tr>';
        echo '<td>API URL</td>';
        echo '<td>' . (!empty($api_url) ? '<code>' . esc_html($api_url) . '</code>' : '<em>Not set</em>') . '</td>';
        echo '<td>' . (!empty($api_url) ? '<span class="status success">✅</span>' : '<span class="status warning">⚠️</span>') . '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<td>API Key</td>';
        echo '<td>' . (!empty($api_key) ? '<code>' . str_repeat('*', 20) . '</code>' : '<em>Not set</em>') . '</td>';
        echo '<td>' . (!empty($api_key) ? '<span class="status success">✅</span>' : '<span class="status warning">⚠️</span>') . '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<td>Signup URL</td>';
        echo '<td>' . (!empty($signup_url) ? '<code>' . esc_html($signup_url) . '</code>' : '<em>Using default</em>') . '</td>';
        echo '<td><span class="status info">ℹ️ Optional</span></td>';
        echo '</tr>';

        echo '</table>';

        // Check 8: AJAX handlers
        echo "<h2>8. AJAX Handlers</h2>";
        if (has_action('wp_ajax_siloq_submit_scan') && has_action('wp_ajax_nopriv_siloq_submit_scan')) {
            echo '<div class="status success">✅ AJAX handlers registered</div>';
        } else {
            echo '<div class="status error">❌ AJAX handlers not registered</div>';
            $all_passed = false;
        }

        // Overall status
        echo "<h2>📋 Overall Status</h2>";
        if ($all_passed) {
            echo '<div class="status success" style="font-size: 18px; padding: 20px;">
                ✅ <strong>All checks passed!</strong> Lead Gen Scanner is ready to use.
            </div>';
        } else {
            echo '<div class="status error" style="font-size: 18px; padding: 20px;">
                ❌ <strong>Some checks failed.</strong> Please review the errors above.
            </div>';
        }
        ?>

        <div class="next-steps">
            <h3>📝 Next Steps</h3>
            <ol>
                <li>Configure API settings in <strong>Siloq → Settings</strong></li>
                <li>Create a new page or post</li>
                <li>Add shortcode: <code>[siloq_scanner]</code></li>
                <li>Publish and test the scanner</li>
                <li>View captured leads in database table: <code><?php echo $wpdb->prefix; ?>siloq_leads</code></li>
            </ol>
        </div>

        <p style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;">
            <strong>Note:</strong> Delete this file after verification for security reasons:<br>
            <code><?php echo __FILE__; ?></code>
        </p>
    </div>
</body>
</html>
