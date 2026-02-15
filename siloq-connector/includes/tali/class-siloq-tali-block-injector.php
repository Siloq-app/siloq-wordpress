<?php
/**
 * Siloq TALI - Block Injector
 * 
 * Injects authority content as native Gutenberg blocks.
 * Handles claim anchors, access states, and template mapping.
 * 
 * @package Siloq
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_TALI_Block_Injector {
    
    /**
     * Component mapper instance
     */
    private $component_mapper;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->component_mapper = new Siloq_TALI_Component_Mapper();
    }
    
    /**
     * Inject authority content into a post
     * 
     * @param int    $post_id        The post ID
     * @param string $template       Template type
     * @param array  $content_data   Content and claims
     * @param array  $design_profile Theme design profile
     * @param array  $capability_map Component capability map
     * @param string $access_state   ENABLED or FROZEN
     * @return array Result with blocks, confidence, warnings
     */
    public function inject($post_id, $template, $content_data, $design_profile, $capability_map, $access_state = 'ENABLED') {
        $result = array(
            'success' => false,
            'blocks' => array(),
            'confidence' => 1.0,
            'warnings' => array(),
            'block_count' => 0,
        );
        
        // Validate inputs
        if (empty($post_id) || empty($template) || empty($content_data)) {
            $result['warnings'][] = 'Missing required parameters';
            return $result;
        }
        
        // Get theme slug for wrapper
        $theme_slug = $design_profile['theme']['stylesheet'] ?? 'unknown';
        
        // Build blocks based on template
        $blocks = array();
        $confidences = array();
        
        switch ($template) {
            case 'service_city':
                list($blocks, $confidences) = $this->build_service_city_blocks($content_data, $design_profile, $capability_map, $access_state, $theme_slug);
                break;
                
            case 'blog_post':
                list($blocks, $confidences) = $this->build_blog_post_blocks($content_data, $design_profile, $capability_map, $access_state, $theme_slug);
                break;
                
            case 'project_post':
                list($blocks, $confidences) = $this->build_project_post_blocks($content_data, $design_profile, $capability_map, $access_state, $theme_slug);
                break;
                
            default:
                list($blocks, $confidences) = $this->build_generic_blocks($content_data, $design_profile, $capability_map, $access_state, $theme_slug);
        }
        
        // Calculate overall confidence
        $overall_confidence = !empty($confidences) ? array_sum($confidences) / count($confidences) : 0;
        
        // Serialize blocks to post content
        $block_content = $this->serialize_blocks($blocks);
        
        // Update post content
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $block_content,
        ));
        
        $result['success'] = true;
        $result['blocks'] = $blocks;
        $result['confidence'] = round($overall_confidence, 2);
        $result['block_count'] = count($blocks);
        
        return $result;
    }
    
    /**
     * Build blocks for service + city page template
     */
    private function build_service_city_blocks($content_data, $design_profile, $capability_map, $access_state, $theme_slug) {
        $blocks = array();
        $confidences = array();
        
        // Extract content sections
        $service_name = $content_data['service_name'] ?? '';
        $city = $content_data['city'] ?? '';
        $introduction = $content_data['introduction'] ?? '';
        $services = $content_data['services'] ?? array();
        $benefits = $content_data['benefits'] ?? array();
        $process = $content_data['process'] ?? array();
        $faq = $content_data['faq'] ?? array();
        $cta = $content_data['cta'] ?? array();
        $claim_prefix = $content_data['claim_prefix'] ?? 'SRV';
        
        $claim_counter = 100;
        
        // Hero/Introduction Section
        if (!empty($introduction)) {
            $claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
            $claim_counter++;
            
            $intro_block = $this->create_authority_container(
                $this->create_heading_block($service_name . ' in ' . $city, 1) .
                $this->create_paragraph_block($introduction),
                $claim_id,
                'service_city',
                $theme_slug,
                $access_state
            );
            $blocks[] = $intro_block;
            $confidences[] = 0.99;
        }
        
        // Services Section
        if (!empty($services)) {
            $claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
            $claim_counter++;
            
            $services_content = $this->create_heading_block('Our ' . $service_name . ' Services', 2);
            $services_content .= $this->create_list_block($services);
            
            $services_block = $this->create_authority_container(
                $services_content,
                $claim_id,
                'service_city',
                $theme_slug,
                $access_state
            );
            $blocks[] = $services_block;
            $confidences[] = 0.99;
        }
        
        // Benefits Section
        if (!empty($benefits)) {
            $claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
            $claim_counter++;
            
            $benefits_content = $this->create_heading_block('Why Choose Us', 2);
            $benefits_content .= $this->create_list_block($benefits);
            
            $benefits_block = $this->create_authority_container(
                $benefits_content,
                $claim_id,
                'service_city',
                $theme_slug,
                $access_state
            );
            $blocks[] = $benefits_block;
            $confidences[] = 0.99;
        }
        
        // Process Section
        if (!empty($process)) {
            $claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
            $claim_counter++;
            
            $process_content = $this->create_heading_block('Our Process', 2);
            $process_content .= $this->create_numbered_list_block($process);
            
            $process_block = $this->create_authority_container(
                $process_content,
                $claim_id,
                'service_city',
                $theme_slug,
                $access_state
            );
            $blocks[] = $process_block;
            $confidences[] = 0.95;
        }
        
        // FAQ Section
        if (!empty($faq)) {
            $claim_id = "CLAIM:FAQ-{$claim_counter}-A";
            $claim_counter++;
            
            $faq_rec = $this->component_mapper->get_recommended_block('faq', $capability_map);
            $faq_content = $this->create_heading_block('Frequently Asked Questions', 2);
            
            foreach ($faq as $item) {
                $question = $item['question'] ?? '';
                $answer = $item['answer'] ?? '';
                $faq_content .= $this->create_faq_item($question, $answer, $faq_rec['block']);
            }
            
            $faq_block = $this->create_authority_container(
                $faq_content,
                $claim_id,
                'service_city',
                $theme_slug,
                $access_state
            );
            $blocks[] = $faq_block;
            $confidences[] = $faq_rec['confidence'];
        }
        
        // CTA Section
        if (!empty($cta)) {
            $claim_id = "CLAIM:CTA-{$claim_counter}-A";
            
            $cta_heading = $cta['heading'] ?? 'Get Started Today';
            $cta_text = $cta['text'] ?? '';
            $cta_button_text = $cta['button_text'] ?? 'Contact Us';
            $cta_button_url = $cta['button_url'] ?? '#contact';
            
            $cta_content = $this->create_heading_block($cta_heading, 2);
            if (!empty($cta_text)) {
                $cta_content .= $this->create_paragraph_block($cta_text);
            }
            $cta_content .= $this->create_button_block($cta_button_text, $cta_button_url, $design_profile);
            
            $cta_block = $this->create_authority_container(
                $cta_content,
                $claim_id,
                'service_city',
                $theme_slug,
                $access_state
            );
            $blocks[] = $cta_block;
            $confidences[] = 0.95;
        }
        
        return array($blocks, $confidences);
    }
    
    /**
     * Build blocks for blog post template
     */
    private function build_blog_post_blocks($content_data, $design_profile, $capability_map, $access_state, $theme_slug) {
        $blocks = array();
        $confidences = array();
        
        $title = $content_data['title'] ?? '';
        $sections = $content_data['sections'] ?? array();
        $claim_prefix = $content_data['claim_prefix'] ?? 'BLOG';
        
        $claim_counter = 100;
        
        foreach ($sections as $section) {
            $heading = $section['heading'] ?? '';
            $content = $section['content'] ?? '';
            $items = $section['items'] ?? array();
            
            $claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
            $claim_counter++;
            
            $section_content = '';
            if (!empty($heading)) {
                $section_content .= $this->create_heading_block($heading, 2);
            }
            if (!empty($content)) {
                // Split content into paragraphs
                $paragraphs = explode("\n\n", $content);
                foreach ($paragraphs as $p) {
                    if (trim($p)) {
                        $section_content .= $this->create_paragraph_block(trim($p));
                    }
                }
            }
            if (!empty($items)) {
                $section_content .= $this->create_list_block($items);
            }
            
            if (!empty($section_content)) {
                $section_block = $this->create_authority_container(
                    $section_content,
                    $claim_id,
                    'blog_post',
                    $theme_slug,
                    $access_state
                );
                $blocks[] = $section_block;
                $confidences[] = 0.99;
            }
        }
        
        return array($blocks, $confidences);
    }
    
    /**
     * Build blocks for project/job post template
     */
    private function build_project_post_blocks($content_data, $design_profile, $capability_map, $access_state, $theme_slug) {
        $blocks = array();
        $confidences = array();
        
        $title = $content_data['title'] ?? '';
        $description = $content_data['description'] ?? '';
        $images = $content_data['images'] ?? array();
        $details = $content_data['details'] ?? array();
        $claim_prefix = $content_data['claim_prefix'] ?? 'JOB';
        
        $claim_counter = 200;
        
        // Project Description
        if (!empty($description)) {
            $claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
            $claim_counter++;
            
            $desc_block = $this->create_authority_container(
                $this->create_heading_block($title, 1) .
                $this->create_paragraph_block($description),
                $claim_id,
                'project_post',
                $theme_slug,
                $access_state
            );
            $blocks[] = $desc_block;
            $confidences[] = 0.99;
        }
        
        // Project Images (Gallery)
        if (!empty($images)) {
            $claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
            $claim_counter++;
            
            $gallery_block = $this->create_authority_container(
                $this->create_gallery_block($images),
                $claim_id,
                'project_post',
                $theme_slug,
                $access_state
            );
            $blocks[] = $gallery_block;
            $confidences[] = $capability_map['confidence']['gallery'] ?? 0.95;
        }
        
        // Project Details
        if (!empty($details)) {
            $claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
            
            $details_content = $this->create_heading_block('Project Details', 2);
            foreach ($details as $key => $value) {
                $details_content .= $this->create_paragraph_block("<strong>{$key}:</strong> {$value}");
            }
            
            $details_block = $this->create_authority_container(
                $details_content,
                $claim_id,
                'project_post',
                $theme_slug,
                $access_state
            );
            $blocks[] = $details_block;
            $confidences[] = 0.99;
        }
        
        return array($blocks, $confidences);
    }
    
    /**
     * Build generic blocks from content data
     */
    private function build_generic_blocks($content_data, $design_profile, $capability_map, $access_state, $theme_slug) {
        $blocks = array();
        $confidences = array();
        
        $claim_prefix = $content_data['claim_prefix'] ?? 'GEN';
        $claim_counter = 100;
        
        foreach ($content_data as $key => $value) {
            if ($key === 'claim_prefix') continue;
            
            $claim_id = "CLAIM:{$claim_prefix}-{$claim_counter}-A";
            $claim_counter++;
            
            $content = '';
            
            if (is_string($value)) {
                $content = $this->create_paragraph_block($value);
            } elseif (is_array($value) && isset($value['heading'])) {
                $content = $this->create_heading_block($value['heading'], 2);
                if (isset($value['content'])) {
                    $content .= $this->create_paragraph_block($value['content']);
                }
                if (isset($value['items'])) {
                    $content .= $this->create_list_block($value['items']);
                }
            }
            
            if (!empty($content)) {
                $block = $this->create_authority_container(
                    $content,
                    $claim_id,
                    'generic',
                    $theme_slug,
                    $access_state
                );
                $blocks[] = $block;
                $confidences[] = 0.95;
            }
        }
        
        return array($blocks, $confidences);
    }
    
    /**
     * Create the authority container wrapper
     */
    private function create_authority_container($inner_content, $claim_id, $template, $theme_slug, $access_state) {
        $receipt_comment = "<!-- SiloqAuthorityReceipt: {$claim_id} -->";
        
        if ($access_state === 'FROZEN') {
            // Frozen: Only render receipt, suppress body
            return "<!-- wp:group {\"className\":\"siloq-authority-container\"} -->\n" .
                   "<div class=\"wp-block-group siloq-authority-container\" " .
                   "data-siloq-claim-id=\"{$claim_id}\" " .
                   "data-siloq-governance=\"V1\" " .
                   "data-siloq-template=\"{$template}\" " .
                   "data-siloq-theme=\"{$theme_slug}\" " .
                   "data-siloq-access=\"FROZEN\">\n" .
                   "{$receipt_comment}\n" .
                   "<!-- SiloqNotice: Authority preserved; rendering suppressed due to access state -->\n" .
                   "</div>\n" .
                   "<!-- /wp:group -->\n";
        }
        
        // Enabled: Full content
        return "<!-- wp:group {\"className\":\"siloq-authority-container\"} -->\n" .
               "<div class=\"wp-block-group siloq-authority-container\" " .
               "data-siloq-claim-id=\"{$claim_id}\" " .
               "data-siloq-governance=\"V1\" " .
               "data-siloq-template=\"{$template}\" " .
               "data-siloq-theme=\"{$theme_slug}\">\n" .
               "{$receipt_comment}\n" .
               "{$inner_content}" .
               "</div>\n" .
               "<!-- /wp:group -->\n";
    }
    
    /**
     * Create a heading block
     */
    private function create_heading_block($text, $level = 2) {
        $text = esc_html($text);
        return "<!-- wp:heading {\"level\":{$level}} -->\n" .
               "<h{$level} class=\"wp-block-heading\">{$text}</h{$level}>\n" .
               "<!-- /wp:heading -->\n";
    }
    
    /**
     * Create a paragraph block
     */
    private function create_paragraph_block($text) {
        $text = wp_kses_post($text);
        return "<!-- wp:paragraph -->\n" .
               "<p>{$text}</p>\n" .
               "<!-- /wp:paragraph -->\n";
    }
    
    /**
     * Create a list block
     */
    private function create_list_block($items) {
        $list_items = '';
        foreach ($items as $item) {
            $item = esc_html($item);
            $list_items .= "<li>{$item}</li>";
        }
        return "<!-- wp:list -->\n" .
               "<ul class=\"wp-block-list\">{$list_items}</ul>\n" .
               "<!-- /wp:list -->\n";
    }
    
    /**
     * Create a numbered list block
     */
    private function create_numbered_list_block($items) {
        $list_items = '';
        foreach ($items as $item) {
            $item = esc_html($item);
            $list_items .= "<li>{$item}</li>";
        }
        return "<!-- wp:list {\"ordered\":true} -->\n" .
               "<ol class=\"wp-block-list\">{$list_items}</ol>\n" .
               "<!-- /wp:list -->\n";
    }
    
    /**
     * Create a button block
     */
    private function create_button_block($text, $url, $design_profile) {
        $text = esc_html($text);
        $url = esc_url($url);
        $bg_color = $design_profile['tokens']['colors']['primary'] ?? '#0073aa';
        
        return "<!-- wp:buttons -->\n" .
               "<div class=\"wp-block-buttons\">\n" .
               "<!-- wp:button -->\n" .
               "<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"{$url}\">{$text}</a></div>\n" .
               "<!-- /wp:button -->\n" .
               "</div>\n" .
               "<!-- /wp:buttons -->\n";
    }
    
    /**
     * Create an FAQ item (using details/summary or available block)
     */
    private function create_faq_item($question, $answer, $block_type = 'core/details') {
        $question = esc_html($question);
        $answer = wp_kses_post($answer);
        
        // Use details block (WP 6.3+) or fallback to heading+paragraph
        if ($block_type === 'core/details') {
            return "<!-- wp:details -->\n" .
                   "<details class=\"wp-block-details\"><summary>{$question}</summary>\n" .
                   "<!-- wp:paragraph -->\n" .
                   "<p>{$answer}</p>\n" .
                   "<!-- /wp:paragraph -->\n" .
                   "</details>\n" .
                   "<!-- /wp:details -->\n";
        }
        
        // Fallback: heading + paragraph
        return $this->create_heading_block($question, 3) .
               $this->create_paragraph_block($answer);
    }
    
    /**
     * Create a gallery block
     */
    private function create_gallery_block($images) {
        $image_blocks = '';
        foreach ($images as $image) {
            $url = esc_url($image['url'] ?? '');
            $alt = esc_attr($image['alt'] ?? '');
            $id = intval($image['id'] ?? 0);
            
            $image_blocks .= "<!-- wp:image {\"id\":{$id}} -->\n" .
                            "<figure class=\"wp-block-image\"><img src=\"{$url}\" alt=\"{$alt}\" class=\"wp-image-{$id}\"/></figure>\n" .
                            "<!-- /wp:image -->\n";
        }
        
        return "<!-- wp:gallery -->\n" .
               "<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped\">\n" .
               $image_blocks .
               "</figure>\n" .
               "<!-- /wp:gallery -->\n";
    }
    
    /**
     * Serialize blocks array to string
     */
    private function serialize_blocks($blocks) {
        return implode("\n", $blocks);
    }
}
