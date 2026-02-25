<?php
/**
 * Siloq Content Import Handler
 * Handles importing AI-generated content from Siloq platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Content_Import {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Initialize hooks if needed
    }
    
    /**
     * Get available content jobs for a page
     */
    public function get_page_jobs($page_id) {
        $jobs = get_post_meta($page_id, '_siloq_content_jobs', true);
        return is_array($jobs) ? $jobs : array();
    }
    
    /**
     * Import content to a page
     */
    public function import_content($page_id, $job_id) {
        $jobs = $this->get_page_jobs($page_id);
        
        if (!isset($jobs[$job_id])) {
            return array(
                'success' => false,
                'message' => 'Job not found'
            );
        }
        
        $job = $jobs[$job_id];
        $content = isset($job['content']) ? $job['content'] : '';
        
        if (empty($content)) {
            return array(
                'success' => false,
                'message' => 'No content available for this job'
            );
        }
        
        // Update the page content
        $result = wp_update_post(array(
            'ID' => $page_id,
            'post_content' => wp_kses_post($content)
        ), true);
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        // Mark as imported
        update_post_meta($page_id, '_siloq_content_imported', $job_id);
        update_post_meta($page_id, '_siloq_content_imported_at', current_time('mysql'));
        
        // Remove the job from available jobs
        unset($jobs[$job_id]);
        update_post_meta($page_id, '_siloq_content_jobs', $jobs);
        
        return array(
            'success' => true,
            'message' => 'Content imported successfully',
            'page_url' => get_permalink($page_id)
        );
    }
    
    /**
     * Get content preview for a job
     */
    public function get_content_preview($page_id, $job_id) {
        $jobs = $this->get_page_jobs($page_id);
        
        if (!isset($jobs[$job_id])) {
            return null;
        }
        
        $job = $jobs[$job_id];
        $content = isset($job['content']) ? $job['content'] : '';
        
        // Return a preview (first 200 characters)
        return substr($content, 0, 200) . (strlen($content) > 200 ? '...' : '');
    }
    
    /**
     * Add a new content job for a page
     */
    public function add_content_job($page_id, $job_data) {
        $jobs = $this->get_page_jobs($page_id);
        
        $job_id = uniqid('job_');
        $jobs[$job_id] = array(
            'id' => $job_id,
            'created_at' => current_time('mysql'),
            'status' => 'ready',
            'content' => isset($job_data['content']) ? $job_data['content'] : '',
            'title' => isset($job_data['title']) ? $job_data['title'] : '',
            'word_count' => isset($job_data['word_count']) ? $job_data['word_count'] : 0
        );
        
        update_post_meta($page_id, '_siloq_content_jobs', $jobs);
        update_post_meta($page_id, '_siloq_content_ready', 'yes');
        
        return $job_id;
    }
    
    /**
     * Remove a content job
     */
    public function remove_content_job($page_id, $job_id) {
        $jobs = $this->get_page_jobs($page_id);
        
        if (isset($jobs[$job_id])) {
            unset($jobs[$job_id]);
            update_post_meta($page_id, '_siloq_content_jobs', $jobs);
            
            // Update ready status
            if (empty($jobs)) {
                delete_post_meta($page_id, '_siloq_content_ready');
            }
            
            return true;
        }
        
        return false;
    }
}
