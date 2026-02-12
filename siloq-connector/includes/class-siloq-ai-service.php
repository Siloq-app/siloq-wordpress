<?php
/**
 * Siloq AI Service
 * Handles Gemini AI integration for content analysis and optimization
 * Based on geminiService.ts from wp-nextgen-plugin-demo
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_AI_Service {

    /**
     * Gemini API key
     */
    private $api_key;

    /**
     * Gemini API endpoint
     */
    private $api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('siloq_gemini_api_key', '');
    }

    /**
     * Check if AI service is configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Analyze page content using Gemini AI
     *
     * @param string $title Page title
     * @param string $content Page content/excerpt
     * @return array Analysis result
     */
    public function analyze_content($title, $content) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('Gemini API key is not configured', 'siloq-connector'),
                'data' => $this->get_fallback_analysis()
            );
        }

        $prompt = "
Analyze the following webpage content for SEO effectiveness and Lead Generation potential.
Title: \"{$title}\"
Content: \"{$content}\"

Provide a JSON response with:
- score: A number between 0-100 indicating quality.
- summary: A brief 1-sentence summary of the page intent.
- keywords: An array of 3-5 relevant SEO keywords.
- improvements: An array of 3 specific actionable tips to improve the content.
- leadGenHook: A catchy 1-sentence call-to-action (CTA) suggested for this page.
";

        $response = $this->call_gemini_api($prompt, array(
            'type' => 'object',
            'properties' => array(
                'score' => array('type' => 'integer'),
                'summary' => array('type' => 'string'),
                'keywords' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string')
                ),
                'improvements' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string')
                ),
                'leadGenHook' => array('type' => 'string')
            ),
            'required' => array('score', 'summary', 'keywords', 'improvements', 'leadGenHook')
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'data' => $this->get_fallback_analysis()
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $body['candidates'][0]['content']['parts'][0]['text'];
            $analysis = json_decode($text, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return array(
                    'success' => true,
                    'message' => __('Analysis completed successfully', 'siloq-connector'),
                    'data' => $analysis
                );
            }
        }

        return array(
            'success' => false,
            'message' => __('Failed to parse AI response', 'siloq-connector'),
            'data' => $this->get_fallback_analysis()
        );
    }

    /**
     * Optimize content using Gemini AI
     *
     * @param string $current_content Current content
     * @param array $improvements List of improvements to address
     * @return array Optimization result
     */
    public function optimize_content($current_content, $improvements) {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => __('Gemini API key is not configured', 'siloq-connector'),
                'data' => array('optimized_content' => $current_content)
            );
        }

        $improvements_text = implode("\n", array_map(function($i) { return "- {$i}"; }, $improvements));

        $prompt = "
Act as a professional copywriter and SEO specialist.

Task: Rewrite the following website content excerpt to address the listed improvements.

Current Content: \"{$current_content}\"

Improvements needed:
{$improvements_text}

Requirements:
- Keep the tone professional but persuasive.
- Keep the length similar to the original (approx 30-50 words).
- Do not include markdown or explanations, just the new text.
";

        $response = $this->call_gemini_api($prompt);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
                'data' => array('optimized_content' => $current_content)
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            $optimized_content = trim($body['candidates'][0]['content']['parts'][0]['text']);

            return array(
                'success' => true,
                'message' => __('Content optimized successfully', 'siloq-connector'),
                'data' => array('optimized_content' => $optimized_content)
            );
        }

        return array(
            'success' => false,
            'message' => __('Failed to optimize content', 'siloq-connector'),
            'data' => array('optimized_content' => $current_content)
        );
    }

    /**
     * Call Gemini API
     *
     * @param string $prompt The prompt to send
     * @param array $response_schema Optional response schema for structured output
     * @return array|WP_Error API response or error
     */
    private function call_gemini_api($prompt, $response_schema = null) {
        $url = $this->api_endpoint . '?key=' . $this->api_key;

        $data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            )
        );

        // Add response schema if provided
        if ($response_schema) {
            $data['generationConfig'] = array(
                'responseMimeType' => 'application/json',
                'responseSchema' => $response_schema
            );
        }

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($data),
            'timeout' => 60,
            'sslverify' => true
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
            return new WP_Error('gemini_error', $error_message);
        }

        return $response;
    }

    /**
     * Get fallback analysis data when AI is unavailable
     */
    private function get_fallback_analysis() {
        return array(
            'score' => 75,
            'summary' => 'Analysis unavailable (Simulated mode).',
            'keywords' => array('simulated', 'content', 'marketing'),
            'improvements' => array(
                'Add more content length.',
                'Include internal links.',
                'Optimize meta description.'
            ),
            'leadGenHook' => 'Sign up today to get more insights like this!'
        );
    }

    /**
     * Save analysis results to post meta
     *
     * @param int $post_id Post ID
     * @param array $analysis Analysis data
     */
    public function save_analysis($post_id, $analysis) {
        update_post_meta($post_id, '_siloq_ai_analysis', $analysis);
        update_post_meta($post_id, '_siloq_ai_analysis_date', current_time('mysql'));
    }

    /**
     * Get saved analysis for a post
     *
     * @param int $post_id Post ID
     * @return array|null Analysis data or null
     */
    public function get_saved_analysis($post_id) {
        $analysis = get_post_meta($post_id, '_siloq_ai_analysis', true);
        return !empty($analysis) ? $analysis : null;
    }

    /**
     * Get all posts with AI analysis
     *
     * @param array $post_types Post types to query
     * @return array Posts with analysis data
     */
    public function get_analyzed_posts($post_types = array('page')) {
        $posts = get_posts(array(
            'post_type' => $post_types,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_siloq_ai_analysis',
                    'compare' => 'EXISTS'
                )
            )
        ));

        $results = array();
        foreach ($posts as $post) {
            $analysis = $this->get_saved_analysis($post->ID);
            if ($analysis) {
                $results[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => get_permalink($post->ID),
                    'analysis' => $analysis,
                    'analysis_date' => get_post_meta($post->ID, '_siloq_ai_analysis_date', true)
                );
            }
        }

        return $results;
    }
}
