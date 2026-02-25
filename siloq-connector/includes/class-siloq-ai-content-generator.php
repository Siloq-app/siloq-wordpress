<?php
/**
 * Siloq AI Content Generator
 * Handles AI content generation requests and processing
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_AI_Content_Generator {
    
    /**
     * Initialize AJAX handlers
     */
    public static function init() {
        // Only register AJAX handlers if WordPress functions are available
        if (function_exists('add_action')) {
            add_action('wp_ajax_siloq_ai_generate_content', array(__CLASS__, 'ajax_generate_content'));
            add_action('wp_ajax_siloq_ai_get_content_preview', array(__CLASS__, 'ajax_get_content_preview'));
            add_action('wp_ajax_siloq_ai_insert_content', array(__CLASS__, 'ajax_insert_content'));
            add_action('wp_ajax_siloq_ai_regenerate_section', array(__CLASS__, 'ajax_regenerate_section'));
        }
    }
    
    /**
     * AJAX: Generate AI content
     */
    public static function ajax_generate_content() {
        check_ajax_referer('siloq_ai_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $preferences = isset($_POST['preferences']) ? $_POST['preferences'] : array();
        $content_type = isset($_POST['content_type']) ? sanitize_text_field($_POST['content_type']) : 'auto';
        
        if (!$post_id) {
            wp_send_json_error(array('message' => 'Missing post ID'));
            return;
        }
        
        // Get page title and context
        $post = get_post($post_id);
        $page_title = $post ? $post->post_title : '';
        $page_content = $post ? $post->post_content : '';
        
        // Mock AI generation for now
        $job_id = 'ai_job_' . time() . '_' . rand(1000, 9999);
        
        wp_send_json_success(array(
            'job_id' => $job_id,
            'estimated_time' => 30,
            'status' => 'processing'
        ));
    }
    
    /**
     * AJAX: Get content preview
     */
    public static function ajax_get_content_preview() {
        check_ajax_referer('siloq_ai_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        
        if (!$job_id) {
            wp_send_json_error(array('message' => 'Missing job ID'));
            return;
        }
        
        // Mock content generation
        $mock_content = array(
            'sections' => array(
                array(
                    'id' => 'intro',
                    'type' => 'introduction',
                    'title' => 'Introduction',
                    'content' => 'Welcome to our comprehensive guide. This section provides an overview of the key concepts and principles that will be explored throughout this content.',
                    'word_count' => 45
                ),
                array(
                    'id' => 'main',
                    'type' => 'main_content',
                    'title' => 'Main Content',
                    'content' => 'In this detailed section, we explore the core aspects and provide valuable insights. Our approach focuses on delivering practical information that readers can immediately apply to their specific situations.',
                    'word_count' => 52
                ),
                array(
                    'id' => 'conclusion',
                    'type' => 'conclusion',
                    'title' => 'Conclusion',
                    'content' => 'As we conclude, it\'s important to remember that these concepts work together to create a comprehensive understanding. Take the next step by implementing these strategies in your own context.',
                    'word_count' => 38
                )
            ),
            'total_word_count' => 135,
            'quality_score' => 92,
            'suggestions' => array(
                'Consider adding more specific examples',
                'Add internal links to related content',
                'Include a stronger call-to-action'
            )
        );
        
        wp_send_json_success(array(
            'status' => 'completed',
            'content' => $mock_content
        ));
    }
    
    /**
     * AJAX: Insert content into WordPress editor
     */
    public static function ajax_insert_content() {
        check_ajax_referer('siloq_ai_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $insert_mode = isset($_POST['insert_mode']) ? sanitize_text_field($_POST['insert_mode']) : 'append';
        
        if (!$post_id || !$content) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => 'Post not found'));
            return;
        }
        
        // Insert content based on mode
        switch ($insert_mode) {
            case 'replace':
                $updated_content = $content;
                break;
            case 'append':
                $updated_content = $post->post_content . "\n\n" . $content;
                break;
            case 'draft':
                // Create new draft post
                $new_post_id = wp_insert_post(array(
                    'post_title' => $post->post_title . ' (AI Generated)',
                    'post_content' => $content,
                    'post_status' => 'draft',
                    'post_type' => $post->post_type,
                    'post_author' => get_current_user_id()
                ));
                
                if ($new_post_id && !is_wp_error($new_post_id)) {
                    update_post_meta($new_post_id, '_siloq_generated_from', $post_id);
                    update_post_meta($new_post_id, '_siloq_ai_generated', true);
                    
                    wp_send_json_success(array(
                        'message' => 'Content created as draft',
                        'new_post_id' => $new_post_id,
                        'edit_url' => get_edit_post_link($new_post_id)
                    ));
                    return;
                } else {
                    wp_send_json_error(array('message' => 'Failed to create draft'));
                    return;
                }
                break;
            default:
                $updated_content = $post->post_content . "\n\n" . $content;
        }
        
        // Update the post
        $update_result = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $updated_content
        ));
        
        if ($update_result && !is_wp_error($update_result)) {
            // Mark as AI generated
            update_post_meta($post_id, '_siloq_ai_generated', true);
            update_post_meta($post_id, '_siloq_ai_generated_at', current_time('mysql'));
            
            wp_send_json_success(array(
                'message' => 'Content inserted successfully',
                'insert_mode' => $insert_mode
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to insert content'));
        }
    }
    
    /**
     * AJAX: Regenerate specific section
     */
    public static function ajax_regenerate_section() {
        check_ajax_referer('siloq_ai_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $section_id = isset($_POST['section_id']) ? sanitize_text_field($_POST['section_id']) : '';
        $section_type = isset($_POST['section_type']) ? sanitize_text_field($_POST['section_type']) : '';
        
        if (!$section_id || !$section_type) {
            wp_send_json_error(array('message' => 'Missing section information'));
            return;
        }
        
        // Mock section regeneration
        $mock_content = 'This is regenerated content for the ' . $section_type . ' section. The AI has created fresh, relevant content that maintains the original intent while providing new perspectives and insights.';
        
        wp_send_json_success(array(
            'section_id' => $section_id,
            'content' => $mock_content,
            'quality' => 88
        ));
    }
    
    /**
     * Get content generation preferences
     */
    public static function get_default_preferences() {
        return array(
            'tone' => 'professional',
            'length' => 'medium',
            'includeFAQ' => true,
            'includeCTA' => true,
            'includeInternalLinks' => true,
            'targetKeywords' => array(),
            'customInstructions' => ''
        );
    }
    
    /**
     * Validate content preferences
     */
    public static function validate_preferences($preferences) {
        $valid_tones = array('professional', 'casual', 'friendly', 'authoritative');
        $valid_lengths = array('short', 'medium', 'long');
        
        $validated = array();
        
        if (isset($preferences['tone']) && in_array($preferences['tone'], $valid_tones)) {
            $validated['tone'] = $preferences['tone'];
        } else {
            $validated['tone'] = 'professional';
        }
        
        if (isset($preferences['length']) && in_array($preferences['length'], $valid_lengths)) {
            $validated['length'] = $preferences['length'];
        } else {
            $validated['length'] = 'medium';
        }
        
        $validated['includeFAQ'] = isset($preferences['includeFAQ']) ? (bool) $preferences['includeFAQ'] : true;
        $validated['includeCTA'] = isset($preferences['includeCTA']) ? (bool) $preferences['includeCTA'] : true;
        $validated['includeInternalLinks'] = isset($preferences['includeInternalLinks']) ? (bool) $preferences['includeInternalLinks'] : true;
        
        if (isset($preferences['targetKeywords']) && is_array($preferences['targetKeywords'])) {
            $validated['targetKeywords'] = array_map('sanitize_text_field', $preferences['targetKeywords']);
        } else {
            $validated['targetKeywords'] = array();
        }
        
        if (isset($preferences['customInstructions'])) {
            $validated['customInstructions'] = sanitize_textarea_field($preferences['customInstructions']);
        } else {
            $validated['customInstructions'] = '';
        }
        
        return $validated;
    }
}

// Initialize the AI Content Generator
Siloq_AI_Content_Generator::init();
