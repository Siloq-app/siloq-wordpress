<?php
/**
 * Siloq Redirect Manager
 * Native redirect execution engine for Siloq plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Redirect_Manager {
    
    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'siloq_redirects';
    
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Last DB error from add_redirect() — surfaced in AJAX responses for debugging
     */
    public static $last_error = '';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'maybe_redirect'));
    }
    
    /**
     * Create redirects table
     */
    public static function create_table() {
        global $wpdb;
        
        // Check if WordPress database object is available
        if (!$wpdb || !isset($wpdb->prefix) || !method_exists($wpdb, 'get_charset_collate')) {
            return false;
        }
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // NOTE: Do NOT use ON UPDATE CURRENT_TIMESTAMP — dbDelta() has a known bug
        // where it misparses that syntax and silently skips table creation entirely.
        // We update `updated_at` manually in update_redirect() instead.
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_url varchar(500) NOT NULL,
            target_url varchar(500) NOT NULL,
            status_code int(3) NOT NULL DEFAULT 301,
            enabled tinyint(1) NOT NULL DEFAULT 1,
            hits bigint(20) unsigned NOT NULL DEFAULT 0,
            redirect_type varchar(20) NOT NULL DEFAULT 'manual',
            created_at datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
            updated_at datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY source_url (source_url(191)),
            KEY enabled (enabled)
        ) $charset_collate;";

        // Always try direct query first — more reliable than dbDelta for CREATE TABLE.
        $created = $wpdb->query( $sql );

        // dbDelta as secondary pass (handles ALTER TABLE for existing installs).
        $upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
        if ( file_exists( $upgrade_file ) ) {
            require_once( $upgrade_file );
            dbDelta( $sql );
        }

        // Verify the table actually exists — return false if creation failed.
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;

        // Self-heal: if the table exists but the `enabled` column is missing
        // (can happen on sites where the plugin was installed before the column
        // was added and dbDelta silently skipped the schema change), add it now.
        if ( $exists ) {
            $col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_name}` LIKE 'enabled'" );
            if ( empty( $col ) ) {
                $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status_code`" );
                $wpdb->query( "ALTER TABLE `{$table_name}` ADD KEY `enabled` (`enabled`)" );
            }
            // Add status_code column if missing (older installs created before the column was added)
            $col_exists = $wpdb->get_var( "SHOW COLUMNS FROM `{$table_name}` LIKE 'status_code'" );
            if ( ! $col_exists ) {
                $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `status_code` int(3) NOT NULL DEFAULT 301 AFTER `hits`" );
            }
        }

        return $exists;
    }
    
    /**
     * Check if current URL needs redirect
     */
    public function maybe_redirect() {
        // Skip admin, AJAX, REST API, and static asset requests — these never
        // need redirects and each one would otherwise hit the DB unnecessarily.
        if ( is_admin() ) return;
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) return;
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;

        $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        if ( strpos( $uri, '/wp-json/' ) !== false ) return;
        if ( strpos( $uri, 'wp-content/' ) !== false ) return;
        if ( strpos( $uri, 'wp-admin/' ) !== false ) return;

        $current_url = $this->get_current_url();
        $redirect    = $this->get_redirect( $current_url );

        if ( $redirect && $redirect->enabled ) {
            global $wpdb;
            $wpdb->query( $wpdb->prepare(
                'UPDATE ' . $wpdb->prefix . self::TABLE_NAME . ' SET hits = hits + 1 WHERE id = %d',
                $redirect->id
            ) );
            wp_redirect( $redirect->target_url, $redirect->status_code );
            exit;
        }
    }
    
    /**
     * Get current URL
     */
    private function get_current_url() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    
    /**
     * Get redirect for URL
     */
    public function get_redirect( $source_url ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Guard: if the table doesn't exist yet, fail silently rather than
        // logging a DB error on every front-end request.
        static $table_verified = null;
        if ( $table_verified === null ) {
            $table_verified = ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name );
        }
        if ( ! $table_verified ) return null;

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE source_url = %s AND enabled = 1",
            $source_url
        ) );
    }
    
    /**
     * Add redirect
     */
    public function add_redirect($source_url, $target_url, $status_code = 301) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Self-heal: ensure the table exists before every insert.
        // create_table() is cheap (SHOW TABLES check) and idempotent.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
            $table_created = self::create_table();
            // Verify the table actually got created — surface the failure immediately
            // rather than letting the INSERT produce a cryptic "Table doesn't exist" error.
            if ( ! $table_created ) {
                self::$last_error = 'Redirect table could not be created. DB error: ' . $wpdb->last_error;
                return false;
            }
        }

        // Normalize URLs
        $source_url = $this->normalize_url($source_url);
        $target_url = $this->normalize_url($target_url);

        // Check if redirect already exists (regardless of enabled status so we
        // don't hit a UNIQUE KEY violation when re-inserting a disabled redirect).
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE source_url = %s",
            $source_url
        ) );

        if ( $existing ) {
            // Re-enable and update if it was disabled or points to a different target.
            return $this->update_redirect( $existing->id, $target_url, $status_code );
        }

        $now    = current_time('mysql');
        $result = $wpdb->insert(
            $table_name,
            array(
                'source_url'    => $source_url,
                'target_url'    => $target_url,
                'status_code'   => $status_code,
                'enabled'       => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ),
            array('%s', '%s', '%d', '%d', '%s', '%s')
        );

        // Store last error for callers to surface in debug output
        self::$last_error = $result === false ? $wpdb->last_error : '';

        return $result !== false;
    }
    
    /**
     * Update redirect
     */
    public function update_redirect($id, $target_url, $status_code = 301) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        return $wpdb->update(
            $table_name,
            array(
                'target_url' => $this->normalize_url($target_url),
                'status_code' => $status_code,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%d', '%s'),
            array('%d')
        ) !== false;
    }
    
    /**
     * Delete redirect
     */
    public function delete_redirect($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        return $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        ) !== false;
    }
    
    /**
     * Toggle redirect status
     */
    public function toggle_redirect($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        $redirect = $wpdb->get_row($wpdb->prepare(
            "SELECT enabled FROM $table_name WHERE id = %d",
            $id
        ));
        
        if ($redirect) {
            $new_status = $redirect->enabled ? 0 : 1;
            
            return $wpdb->update(
                $table_name,
                array('enabled' => $new_status),
                array('id' => $id),
                array('%d'),
                array('%d')
            ) !== false;
        }
        
        return false;
    }
    
    /**
     * Get all redirects
     */
    public function get_all_redirects($enabled_only = false) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        $sql = "SELECT * FROM $table_name";
        if ($enabled_only) {
            $sql .= " WHERE enabled = 1";
        }
        $sql .= " ORDER BY created_at DESC";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Normalize URL
     */
    private function normalize_url($url) {
        // Remove trailing slash unless it's the homepage
        $url = rtrim($url, '/');
        if (empty($url)) {
            $url = '/';
        }
        
        // Ensure URL starts with /
        if (strpos($url, 'http') !== 0 && strpos($url, '/') !== 0) {
            $url = '/' . $url;
        }
        
        return $url;
    }
    
    /**
     * Import redirects from array
     */
    public function import_redirects($redirects) {
        $success_count = 0;
        $error_count = 0;
        
        foreach ($redirects as $redirect) {
            $source_url = isset($redirect['source_url']) ? $redirect['source_url'] : '';
            $target_url = isset($redirect['target_url']) ? $redirect['target_url'] : '';
            $status_code = isset($redirect['status_code']) ? intval($redirect['status_code']) : 301;
            
            if (empty($source_url) || empty($target_url)) {
                $error_count++;
                continue;
            }
            
            if ($this->add_redirect($source_url, $target_url, $status_code)) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        return array(
            'success_count' => $success_count,
            'error_count' => $error_count,
            'total' => count($redirects)
        );
    }
}
