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
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_url varchar(500) NOT NULL,
            target_url varchar(500) NOT NULL,
            status_code int(3) DEFAULT 301,
            enabled tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY source_url (source_url),
            KEY enabled (enabled)
        ) $charset_collate;";
        
        // Check if WordPress admin upgrade file exists
        $upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (file_exists($upgrade_file)) {
            require_once($upgrade_file);
            dbDelta($sql);
        } else {
            // Fallback: execute SQL directly if dbDelta is not available
            $wpdb->query($sql);
        }
        
        return true;
    }
    
    /**
     * Check if current URL needs redirect
     */
    public function maybe_redirect() {
        if (is_admin()) {
            return;
        }
        
        $current_url = $this->get_current_url();
        $redirect = $this->get_redirect($current_url);
        
        if ($redirect && $redirect->enabled) {
            wp_redirect($redirect->target_url, $redirect->status_code);
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
    public function get_redirect($source_url) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE source_url = %s AND enabled = 1",
            $source_url
        ));
    }
    
    /**
     * Add redirect
     */
    public function add_redirect($source_url, $target_url, $status_code = 301) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        
        // Normalize URLs
        $source_url = $this->normalize_url($source_url);
        $target_url = $this->normalize_url($target_url);
        
        // Check if redirect already exists
        $existing = $this->get_redirect($source_url);
        if ($existing) {
            return $this->update_redirect($existing->id, $target_url, $status_code);
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'source_url' => $source_url,
                'target_url' => $target_url,
                'status_code' => $status_code,
                'enabled' => 1
            ),
            array('%s', '%s', '%d', '%d')
        );
        
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
