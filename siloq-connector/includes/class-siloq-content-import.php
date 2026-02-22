<?php
/**
 * Siloq Content Import Handler
 * Manages importing AI-generated content from Siloq back to WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Content_Import {
    
    /**
     * API client instance
     */
    private $api_client;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new Siloq_API_Client();
    }
    
    /**
     * Import content from Siloq job
     * 
     * @param int $post_id Original WordPress post ID
     * @param string $job_id Siloq content generation job ID
     * @param array $options Import options
     * @return array Result with success status
     */
    public function import_from_job($post_id, $job_id, $options = array()) {
        // Get job status and content
        $job_result = $this->api_client->get_content_job_status($job_id);
        
        if (!$job_result['success']) {
            return array(
                'success' => false,
                'message' => $job_result['message']
            );
        }
        
        $job_data = $job_result['data'];
        
        // Validate job is complete
        if ($job_data['status'] !== 'completed') {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Job not completed. Current status: %s', 'siloq-connector'),
                    $job_data['status']
                )
            );
        }
        
        // Get original post for reference
        $original_post = get_post($post_id);
        if (!$original_post) {
            return array(
                'success' => false,
                'message' => __('Original post not found', 'siloq-connector')
            );
        }
        
        // Prepare new content
        $content = isset($job_data['content']) ? wp_kses_post($job_data['content']) : '';
        $title = isset($job_data['title']) ? sanitize_text_field($job_data['title']) : $original_post->post_title . ' (AI Generated)';
        
        // Validate content is not empty
        if (empty(trim($content))) {
            return array(
                'success' => false,
                'message' => __('Content is empty', 'siloq-connector')
            );
        }
        
        // Validate title is not empty
        if (empty(trim($title))) {
            $title = $original_post->post_title . ' (AI Generated)';
        }
        
        // Process FAQs if present
        if (!empty($job_data['faq_items']) && is_array($job_data['faq_items'])) {
            $content = $this->inject_faq_block($content, $job_data['faq_items']);
        }
        
        // Process internal links if present
        if (!empty($job_data['internal_links']) && is_array($job_data['internal_links'])) {
            $content = $this->inject_internal_links($content, $job_data['internal_links']);
        }
        
        // Determine action: replace or create new
        $action = isset($options['action']) ? $options['action'] : 'create_draft';
        
        if ($action === 'replace') {
            // Replace existing page (keep as draft for review)
            $result = $this->replace_content($post_id, $content, $title, $job_data);
        } else {
            // Create new draft page
            $result = $this->create_draft($post_id, $content, $title, $job_data);
        }
        
        return $result;
    }
    
    /**
     * Create a new draft page with imported content
     */
    private function create_draft($original_post_id, $content, $title, $metadata) {
        $original_post = get_post($original_post_id);
        
        // Ensure content length is reasonable (WordPress limit is ~2MB in post_content)
        $max_length = 1048576; // 1MB safety limit
        if (strlen($content) > $max_length) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Content is too large (%d bytes). Maximum allowed: %d bytes.', 'siloq-connector'),
                    strlen($content),
                    $max_length
                )
            );
        }
        
        $new_post_id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'page',
            'post_parent' => $original_post->post_parent,
            'menu_order' => $original_post->menu_order,
            'post_excerpt' => isset($metadata['excerpt']) ? sanitize_textarea_field($metadata['excerpt']) : ''
        ));
        
        if (is_wp_error($new_post_id)) {
            return array(
                'success' => false,
                'message' => $new_post_id->get_error_message()
            );
        }
        
        // Store metadata
        $this->store_import_metadata($new_post_id, $original_post_id, $metadata);
        
        return array(
            'success' => true,
            'message' => __('Content imported as new draft page', 'siloq-connector'),
            'data' => array(
                'post_id' => $new_post_id,
                'edit_url' => get_edit_post_link($new_post_id, 'raw'),
                'action' => 'created'
            )
        );
    }
    
    /**
     * Replace existing page content (saves as draft for review)
     */
    private function replace_content($post_id, $content, $title, $metadata) {
        // Validate content length
        $max_length = 1048576; // 1MB safety limit
        if (strlen($content) > $max_length) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Content is too large (%d bytes). Maximum allowed: %d bytes.', 'siloq-connector'),
                    strlen($content),
                    $max_length
                )
            );
        }
        
        // Backup current content
        $current_post = get_post($post_id);
        if (!$current_post) {
            return array(
                'success' => false,
                'message' => __('Post not found', 'siloq-connector')
            );
        }
        
        update_post_meta($post_id, '_siloq_backup_content', $current_post->post_content);
        update_post_meta($post_id, '_siloq_backup_title', $current_post->post_title);
        update_post_meta($post_id, '_siloq_backup_date', current_time('mysql'));
        
        // Update post (set to draft for review)
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $content,
            'post_title' => $title,
            'post_status' => 'draft' // Safety: always draft for review
        ));
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        // Store metadata
        $this->store_import_metadata($post_id, $post_id, $metadata);
        
        return array(
            'success' => true,
            'message' => __('Content replaced (saved as draft for review)', 'siloq-connector'),
            'data' => array(
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'raw'),
                'action' => 'replaced'
            )
        );
    }
    
    /**
     * Store import metadata
     */
    private function store_import_metadata($post_id, $original_post_id, $metadata) {
        update_post_meta($post_id, '_siloq_generated_from', $original_post_id);
        update_post_meta($post_id, '_siloq_imported_at', current_time('mysql'));
        
        if (isset($metadata['job_id'])) {
            update_post_meta($post_id, '_siloq_content_job_id', $metadata['job_id']);
        }
        
        if (isset($metadata['schema_markup'])) {
            update_post_meta($post_id, '_siloq_schema_markup', $metadata['schema_markup']);
        }
        
        if (isset($metadata['faq_items'])) {
            update_post_meta($post_id, '_siloq_faq_items', $metadata['faq_items']);
        }
        
        if (isset($metadata['internal_links'])) {
            update_post_meta($post_id, '_siloq_internal_links', $metadata['internal_links']);
        }
        
        if (isset($metadata['seo_metadata'])) {
            // Store Yoast SEO metadata if available
            if (isset($metadata['seo_metadata']['title'])) {
                update_post_meta($post_id, '_yoast_wpseo_title', $metadata['seo_metadata']['title']);
            }
            if (isset($metadata['seo_metadata']['description'])) {
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $metadata['seo_metadata']['description']);
            }
            if (isset($metadata['seo_metadata']['focus_keyword'])) {
                update_post_meta($post_id, '_yoast_wpseo_focuskw', $metadata['seo_metadata']['focus_keyword']);
            }
        }
    }
    
    /**
     * Inject FAQ block into content
     */
    private function inject_faq_block($content, $faq_items) {
        if (empty($faq_items) || !is_array($faq_items)) {
            return $content;
        }
        
        // Filter and sanitize FAQ items
        $valid_faq_items = array();
        foreach ($faq_items as $faq) {
            if (isset($faq['question']) && isset($faq['answer']) && 
                !empty(trim($faq['question'])) && !empty(trim($faq['answer']))) {
                $valid_faq_items[] = array(
                    'question' => sanitize_text_field($faq['question']),
                    'answer' => wp_kses_post($faq['answer'])
                );
            }
        }
        
        if (empty($valid_faq_items)) {
            return $content;
        }
        
        // Check if using Gutenberg blocks
        if (has_blocks($content)) {
            // Use Gutenberg FAQ block
            $faq_block = $this->generate_faq_gutenberg_block($valid_faq_items);
        } else {
            // Use classic editor HTML
            $faq_block = $this->generate_faq_html($valid_faq_items);
        }
        
        // Append FAQ at the end
        return $content . "\n\n" . $faq_block;
    }
    
    /**
     * Generate Gutenberg FAQ block
     */
    private function generate_faq_gutenberg_block($faq_items) {
        $block = "<!-- wp:heading -->\n";
        $block .= "<h2>Frequently Asked Questions</h2>\n";
        $block .= "<!-- /wp:heading -->\n\n";
        
        foreach ($faq_items as $faq) {
            $question = isset($faq['question']) ? esc_html($faq['question']) : '';
            $answer = isset($faq['answer']) ? wpautop($faq['answer']) : '';
            
            if (empty($question) || empty($answer)) {
                continue;
            }
            
            $block .= "<!-- wp:heading {\"level\":3} -->\n";
            $block .= "<h3>{$question}</h3>\n";
            $block .= "<!-- /wp:heading -->\n\n";
            
            $block .= "<!-- wp:paragraph -->\n";
            $block .= $answer;
            $block .= "<!-- /wp:paragraph -->\n\n";
        }
        
        return $block;
    }
    
    /**
     * Generate classic editor FAQ HTML
     */
    private function generate_faq_html($faq_items) {
        $html = '<div class="siloq-faq-section">';
        $html .= '<h2>Frequently Asked Questions</h2>';
        
        foreach ($faq_items as $faq) {
            $question = isset($faq['question']) ? esc_html($faq['question']) : '';
            $answer = isset($faq['answer']) ? wpautop($faq['answer']) : '';
            
            if (empty($question) || empty($answer)) {
                continue;
            }
            
            $html .= '<div class="siloq-faq-item">';
            $html .= '<h3 class="siloq-faq-question">' . $question . '</h3>';
            $html .= '<div class="siloq-faq-answer">' . $answer . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Inject internal links into content
     */
    private function inject_internal_links($content, $internal_links) {
        if (empty($internal_links)) {
            return $content;
        }
        
        foreach ($internal_links as $link) {
            if (!isset($link['anchor_text']) || !isset($link['target_url'])) {
                continue;
            }
            
            $anchor_text = $link['anchor_text'];
            $target_url = $link['target_url'];
            
            // Validate URL
            if (!filter_var($target_url, FILTER_VALIDATE_URL) && !preg_match('/^\/[^\/]/', $target_url)) {
                // Skip invalid URLs (but allow relative paths)
                continue;
            }
            
            // Convert relative URLs to absolute if needed
            if (preg_match('/^\/[^\/]/', $target_url)) {
                $target_url = home_url($target_url);
            }
            
            $linked_text = '<a href="' . esc_url($target_url) . '" class="siloq-internal-link">' . esc_html($anchor_text) . '</a>';
            
            // Check if anchor text is already linked (use stripos for case-insensitive, safer check)
            if (stripos($content, '<a') !== false && stripos($content, $anchor_text) !== false) {
                // Do more thorough check with DOMDocument if possible
                if (class_exists('DOMDocument')) {
                    libxml_use_internal_errors(true);
                    $dom = new DOMDocument();
                    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    libxml_clear_errors();
                    
                    $links = $dom->getElementsByTagName('a');
                    $already_linked = false;
                    foreach ($links as $existing_link) {
                        if (stripos($existing_link->textContent, $anchor_text) !== false) {
                            $already_linked = true;
                            break;
                        }
                    }
                    if ($already_linked) {
                        continue;
                    }
                }
            }
            
            // Simple string replacement (safer than regex for special characters)
            // Find first occurrence of exact anchor text and replace
            $pos = stripos($content, $anchor_text);
            if ($pos !== false) {
                // Only replace first occurrence
                $content = substr_replace($content, $linked_text, $pos, strlen($anchor_text));
            }
        }
        
        return $content;
    }
    
    /**
     * Restore backup content
     */
    public function restore_backup($post_id) {
        $backup_content = get_post_meta($post_id, '_siloq_backup_content', true);
        $backup_title = get_post_meta($post_id, '_siloq_backup_title', true);
        
        if (empty($backup_content)) {
            return array(
                'success' => false,
                'message' => __('No backup found', 'siloq-connector')
            );
        }
        
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $backup_content,
            'post_title' => $backup_title
        ));
        
        if (is_wp_error($result)) {
            return array(
                'success' => false,
                'message' => $result->get_error_message()
            );
        }
        
        // Clear backup
        delete_post_meta($post_id, '_siloq_backup_content');
        delete_post_meta($post_id, '_siloq_backup_title');
        delete_post_meta($post_id, '_siloq_backup_date');
        
        return array(
            'success' => true,
            'message' => __('Backup restored successfully', 'siloq-connector')
        );
    }
    
    /**
     * List available content jobs for a page
     */
    public function get_available_jobs($post_id) {
        $jobs = get_post_meta($post_id, '_siloq_content_jobs', true);
        
        if (empty($jobs) || !is_array($jobs)) {
            return array();
        }
        
        return $jobs;
    }
    
    /**
     * Store content job reference
     */
    public function store_job_reference($post_id, $job_id, $job_data) {
        $jobs = get_post_meta($post_id, '_siloq_content_jobs', true);
        
        if (empty($jobs) || !is_array($jobs)) {
            $jobs = array();
        }
        
        $jobs[$job_id] = array(
            'job_id' => $job_id,
            'created_at' => current_time('mysql'),
            'status' => $job_data['status'] ?? 'pending',
            'title' => $job_data['title'] ?? ''
        );
        
        update_post_meta($post_id, '_siloq_content_jobs', $jobs);
    }
}
