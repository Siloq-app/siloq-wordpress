<?php
/**
 * Plugin Name: Siloq Connector
<<<<<<< HEAD
 * Plugin URI: https://siloq.com
 * Description: Connect WordPress to Siloq platform for AI-powered SEO content management and lead generation
 * Version: 1.0.0
 * Author: Siloq
 * Author URI: https://siloq.com
 * License: GPL v2 or later
=======
 * Plugin URI: https://github.com/Siloq-seo/siloq-wordpress-plugin
 * Description: Connects WordPress to Siloq platform for SEO content silo management and AI-powered content generation
 * Version: 1.3.0
 * Version: 1.5.7
 * Author: Siloq
 * Author URI: https://siloq.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
>>>>>>> pr-17
 * Text Domain: siloq-connector
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
<<<<<<< HEAD
    exit;
}

// Define plugin constants
define('SILOQ_VERSION', '1.0.0');
define('SILOQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SILOQ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SILOQ_PLUGIN_BASENAME', plugin_basename(__FILE__));
=======
    exit; // Exit if accessed directly
}

// Define plugin constants
define('SILOQ_VERSION', '1.5.7');
define('SILOQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SILOQ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SILOQ_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SILOQ_PLUGIN_FILE', __FILE__);
>>>>>>> pr-17

/**
 * Main Siloq Connector Class
 */
class Siloq_Connector {
    
<<<<<<< HEAD
    private static $instance = null;
    
=======
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
>>>>>>> pr-17
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
<<<<<<< HEAD
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_redirects'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    /**
     * Handle admin redirects
     */
    public function handle_redirects() {
        // Check if we're on the Create Page page and redirect
        if (isset($_GET['page']) && $_GET['page'] === 'siloq-create') {
            wp_redirect(admin_url('post-new.php?post_type=page'));
            exit;
        }
=======
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('save_post', array($this, 'sync_on_save'), 10, 3);
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // AJAX hooks
        add_action('wp_ajax_siloq_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_siloq_sync_page', array($this, 'ajax_sync_page'));
        add_action('wp_ajax_siloq_sync_all', array($this, 'ajax_sync_all_pages'));
        add_action('wp_ajax_siloq_get_sync_status', array($this, 'ajax_get_sync_status'));
        add_action('wp_ajax_siloq_import_content', array($this, 'ajax_import_content'));
        add_action('wp_ajax_siloq_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_siloq_check_job_status', array($this, 'ajax_check_job_status'));
        add_action('wp_ajax_siloq_restore_backup', array($this, 'ajax_restore_backup'));
        add_action('wp_ajax_siloq_sync_outdated', array($this, 'ajax_sync_outdated'));
        add_action('wp_ajax_siloq_get_business_profile', array($this, 'ajax_get_business_profile'));
        add_action('wp_ajax_siloq_save_business_profile', array($this, 'ajax_save_business_profile'));
        
        // AI Content Generator AJAX hooks
        add_action('wp_ajax_siloq_ai_generate_content', array('Siloq_AI_Content_Generator', 'ajax_generate_content'));
        add_action('wp_ajax_siloq_ai_get_content_preview', array('Siloq_AI_Content_Generator', 'ajax_get_content_preview'));
        add_action('wp_ajax_siloq_ai_insert_content', array('Siloq_AI_Content_Generator', 'ajax_insert_content'));
        add_action('wp_ajax_siloq_ai_regenerate_section', array('Siloq_AI_Content_Generator', 'ajax_regenerate_section'));
        
        // Schema injection (legacy meta key _siloq_schema_markup)
        add_action('wp_head', array($this, 'inject_schema_markup'));

        // Schema Manager output (new meta key _siloq_schema, runs first at priority 5)
        add_action('wp_head', array('Siloq_Schema_Manager', 'output_schema'), 5);
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-api-client.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-sync-engine.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-admin.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-content-import.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-webhook-handler.php';
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-lead-gen-scanner.php';
        
        // AI Content Generator
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-ai-content-generator.php';
        
        // Load TALI (Theme-Aware Layout Intelligence)
        if (!defined('SILOQ_TALI_DISABLED') || !SILOQ_TALI_DISABLED) {
            require_once SILOQ_PLUGIN_DIR . 'includes/tali/class-siloq-tali.php';
        }

        // Initialize webhook handler
        new Siloq_Webhook_Handler();
        
        // Initialize lead gen scanner
        $api_client = new Siloq_API_Client();
        new Siloq_Lead_Gen_Scanner($api_client);

        // Initialize TALI
        if (!defined('SILOQ_TALI_DISABLED') || !SILOQ_TALI_DISABLED) {
            Siloq_TALI::get_instance();
        }

        // Include and initialize redirect manager
        require_once SILOQ_PLUGIN_DIR . 'includes/class-siloq-redirect-manager.php';
        Siloq_Redirect_Manager::get_instance();
>>>>>>> pr-17
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
<<<<<<< HEAD
            'Siloq',
            'Siloq',
            'manage_options',
            'siloq-connector',
            array($this, 'render_admin_page'),
            'dashicons-networking',
            25
        );
        
        add_submenu_page(
            'siloq-connector',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'siloq-connector',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'siloq-connector',
            'Create Page',
            'Create Page',
            'manage_options',
            'siloq-create',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'siloq-connector',
            'Setup Wizard',
            'Setup Wizard',
            'manage_options',
            'siloq-wizard',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'siloq-connector',
            'Page Sync',
            'Page Sync',
            'manage_options',
            'siloq-sync',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'siloq-connector',
            'Lead Gen Scanner',
            'Lead Gen Scanner',
            'manage_options',
            'siloq-scanner',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'siloq-connector',
            'Settings',
            'Settings',
            'manage_options',
            'siloq-settings',
            array($this, 'render_admin_page')
=======
            __('Siloq Settings', 'siloq-connector'),
            __('Siloq', 'siloq-connector'),
            'manage_options',
            'siloq-settings',
            array('Siloq_Admin', 'render_settings_page'),
            'dashicons-networking',
            80
        );
        
        // Explicit first submenu replaces auto-generated parent duplicate
        add_submenu_page(
            'siloq-settings',
            __('Setup', 'siloq-connector'),
            __('Setup', 'siloq-connector'),
            'manage_options',
            'siloq-settings',
            array('Siloq_Admin', 'render_settings_page')
        );
        
        add_submenu_page(
            'siloq-settings',
            __('Sync Status', 'siloq-connector'),
            __('Sync Status', 'siloq-connector'),
            'manage_options',
            'siloq-sync-status',
            array('Siloq_Admin', 'render_sync_status_page')
        );
        
        add_submenu_page(
            'siloq-settings',
            __('Theme Intelligence', 'siloq-connector'),
            __('Theme Intelligence', 'siloq-connector'),
            'manage_options',
            'siloq-theme-intelligence',
            array(Siloq_TALI::get_instance(), 'render_admin_page')
        );
        
        add_submenu_page(
            'siloq-settings',
            __('Content Import', 'siloq-connector'),
            __('Content Import', 'siloq-connector'),
            'edit_pages',
            'siloq-content-import',
            array('Siloq_Admin', 'render_content_import_page')
>>>>>>> pr-17
        );
    }
    
    /**
<<<<<<< HEAD
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div id="siloq-root">
            <div style="background: #F0F0F1; min-height: 100vh; display: flex; align-items: center; justify-content: center;"></div>
        </div>
        <?php
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'siloq') === false && $hook !== 'post-new.php' && $hook !== 'post.php') {
            return;
        }
        
        // Enqueue Tailwind
        wp_enqueue_script('tailwindcss', 'https://cdn.tailwindcss.com', array(), null, false);
        
        // Enqueue Siloq Page Editor script for page editor
        if ($hook === 'post-new.php' || $hook === 'post.php') {
            wp_enqueue_script(
                'siloq-page-editor',
                SILOQ_PLUGIN_URL . 'assets/siloq-page-editor.js',
                array('jquery'),
                SILOQ_VERSION,
                true
            );
            
            // Also add inline script as fallback
            $inline_script = "
                console.log('Siloq AI: Inline script loaded');
                
                function injectSiloqAIButton() {
                    console.log('Siloq AI: Injecting button from inline script');
                    
                    // Remove existing button
                    var existingBtn = document.querySelector('.siloq-ai-generator');
                    if (existingBtn) {
                        existingBtn.remove();
                    }
                    
                    // Create button container - moved to bottom right to avoid covering WordPress UI
                    var buttonDiv = document.createElement('div');
                    buttonDiv.className = 'siloq-ai-generator';
                    buttonDiv.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 999999; background: white; border: 2px solid #2271b1; border-radius: 8px; padding: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 280px;';
                    
                    buttonDiv.innerHTML = 
                        '<button type=\"button\" class=\"siloq-generate-btn\" style=\"background: linear-gradient(135deg, #2271b1, #135e96); border: none; color: white; display: flex; align-items: center; gap: 10px; padding: 12px 20px; font-size: 14px; font-weight: 600; border-radius: 4px; cursor: pointer; width: 100%; justify-content: center;\">' +
                            '<svg width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\">' +
                                '<path d=\"M12 2L2 7L12 12L22 7L12 2Z\" stroke=\"white\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>' +
                                '<path d=\"M2 17L12 22L22 17\" stroke=\"white\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>' +
                                '<path d=\"M2 12L12 17L22 12\" stroke=\"white\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/>' +
                            '</svg>' +
                            '<span>AI Generate Content</span>' +
                        '</button>';
                    
                    // Add to page
                    document.body.appendChild(buttonDiv);
                    
                    // Add click handler
                    buttonDiv.querySelector('.siloq-generate-btn').addEventListener('click', function() {
                        console.log('Siloq AI: Button clicked');
                        
                        // Function to wait for and find the title field
                        function findTitleField(callback, attempts = 0) {
                            if (attempts > 10) {
                                console.log('Siloq AI: Max attempts reached, giving up');
                                callback('');
                                return;
                            }
                            
                            console.log('Siloq AI: Attempt', attempts + 1, 'to find title field');
                            console.log('Siloq AI: WordPress availability check:');
                            console.log('  - wp object:', typeof wp);
                            console.log('  - wp.data:', typeof wp !== 'undefined' ? typeof wp.data : 'undefined');
                            console.log('  - wp.data.select:', typeof wp !== 'undefined' && wp.data ? typeof wp.data.select : 'undefined');
                            console.log('  - core/editor available:', typeof wp !== 'undefined' && wp.data && wp.data.select ? typeof wp.data.select('core/editor') : 'undefined');
                            
                            // Try WordPress data store first (most reliable for Gutenberg)
                            var title = '';
                            if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
                                try {
                                    var editor = wp.data.select('core/editor');
                                    console.log('Siloq AI: Core editor object:', editor);
                                    
                                    if (editor && typeof editor.getEditedPostAttribute === 'function') {
                                        title = editor.getEditedPostAttribute('title');
                                        console.log('Siloq AI: WordPress data store title:', title);
                                        console.log('Siloq AI: Title type:', typeof title);
                                        console.log('Siloq AI: Title length:', title ? title.length : 'N/A');
                                        
                                        if (title && typeof title === 'string' && title.trim() && title.trim().length > 0) {
                                            console.log('Siloq AI: Calling callback with title:', title.trim());
                                            callback(title.trim());
                                            return;
                                        } else {
                                            console.log('Siloq AI: WordPress data store title is empty or invalid');
                                            console.log('Siloq AI: Title value:', JSON.stringify(title));
                                            console.log('Siloq AI: Trimmed title:', title ? title.trim() : 'N/A');
                                            console.log('Siloq AI: Trimmed length:', title ? title.trim().length : 'N/A');
                                        }
                                    } else {
                                        console.log('Siloq AI: getEditedPostAttribute method not available');
                                    }
                                } catch (e) {
                                    console.log('Siloq AI: WordPress data store failed:', e.message);
                                }
                            } else {
                                console.log('Siloq AI: WordPress data store not available');
                            }
                            
                            // Try multiple ways to get the title
                            var titleInput = null;
                            
                            // Comprehensive list of possible title selectors
                            var selectors = [
                                '#post-title-0',  // This is the correct ID based on debug
                                '.editor-post-title__input',  // This is the correct class
                                'input[name=\"post_title\"]',
                                '#title',
                                '.editor-post-title__block input',
                                '[data-block=\"core/post-title\"] input',
                                '.editor-post-title input',
                                'textarea[name=\"post_title\"]',
                                '.wp-block-post-title input',
                                '[aria-label*=\"Add title\"]',
                                '[placeholder*=\"Add title\"]',
                                '[placeholder*=\"Enter title\"]',
                                'input[type=\"text\"]:focus',
                                '.editor-post-title__field input'
                            ];
                            
                            console.log('Siloq AI: Checking', selectors.length, 'title selectors');
                            
                            for (var i = 0; i < selectors.length; i++) {
                                titleInput = document.querySelector(selectors[i]);
                                if (titleInput) {
                                    title = titleInput.value || titleInput.textContent || titleInput.innerText || '';
                                    console.log('Siloq AI: Found title input:', selectors[i], 'element:', titleInput);
                                    console.log('Siloq AI: Raw value methods:');
                                    console.log('  - .value:', titleInput.value);
                                    console.log('  - .textContent:', titleInput.textContent);
                                    console.log('  - .innerText:', titleInput.innerText);
                                    console.log('  - .getAttribute(\"value\"):', titleInput.getAttribute ? titleInput.getAttribute('value') : 'N/A');
                                    console.log('  - Combined title:', title);
                                    console.log('  - Title.trim():', title.trim());
                                    console.log('  - Title length:', title.length);
                                    
                                    if (title && title.trim() && title.trim().length > 0) {
                                        console.log('Siloq AI: ✅ Title found, breaking loop');
                                        callback(title);
                                        return;
                                    } else {
                                        console.log('Siloq AI: ❌ Title empty or invalid, trying next selector');
                                        title = ''; // Reset title
                                    }
                                } else {
                                    console.log('Siloq AI: Selector not found:', selectors[i]);
                                }
                            }
                            
                            // Try contenteditable elements (Gutenberg often uses these)
                            if (!title.trim()) {
                                console.log('Siloq AI: Trying contenteditable elements');
                                var contentEditables = document.querySelectorAll('[contenteditable=\"true\"]');
                                console.log('Siloq AI: Found', contentEditables.length, 'contenteditable elements');
                                
                                for (var j = 0; j < contentEditables.length; j++) {
                                    var editable = contentEditables[j];
                                    var editableText = editable.textContent || editable.innerText || '';
                                    
                                    console.log('Siloq AI: Contenteditable', j + 1, ':');
                                    console.log('  - Element:', editable);
                                    console.log('  - Classes:', editable.className);
                                    console.log('  - ID:', editable.id);
                                    console.log('  - Aria-label:', editable.getAttribute('aria-label'));
                                    console.log('  - Text:', editableText);
                                    console.log('  - Text length:', editableText.length);
                                    
                                    // Check if this looks like a title field
                                    if (editable.classList.contains('editor-post-title__input') ||
                                        editable.id.includes('title') ||
                                        (editable.getAttribute('aria-label') && editable.getAttribute('aria-label').includes('title')) ||
                                        editableText.length < 100 && editableText.trim()) {
                                        
                                        title = editableText.trim();
                                        console.log('Siloq AI: Found potential title in contenteditable:', title);
                                        
                                        if (title.trim()) break;
                                    }
                                }
                            }
                            
                            // If still no title, try to get from any input that might be the title
                            if (!title.trim()) {
                                console.log('Siloq AI: No title from selectors, trying broader search');
                                var allInputs = document.querySelectorAll('input[type=\"text\"], textarea');
                                console.log('Siloq AI: Found', allInputs.length, 'text inputs/textarea');
                                
                                for (var m = 0; m < allInputs.length; m++) {
                                    var input = allInputs[m];
                                    var inputValue = input.value || input.textContent || input.innerText || '';
                                    if (inputValue.trim() && inputValue.length > 0 && inputValue.length < 100) {
                                        // Check if this might be the title (short text, not empty)
                                        title = inputValue.trim();
                                        console.log('Siloq AI: Found potential title from input:', title);
                                        if (title.trim()) break;
                                    }
                                }
                            }
                            
                            // If still no title, try to get from the page title element
                            if (!title.trim()) {
                                console.log('Siloq AI: Trying to get title from page elements');
                                var titleElements = [
                                    '.editor-post-title',
                                    '#titlediv',
                                    '.post-title-wrap',
                                    '.editor-post-title__block',
                                    '[data-block=\"core/post-title\"]'
                                ];
                                
                                for (var n = 0; n < titleElements.length; n++) {
                                    var titleElement = document.querySelector(titleElements[n]);
                                    if (titleElement) {
                                        title = titleElement.textContent || titleElement.innerText || '';
                                        title = title.replace(/Add title|Enter title|Title/gi, '').trim();
                                        console.log('Siloq AI: Found title from element:', titleElements[n], 'value:', title);
                                        if (title.trim()) break;
                                    }
                                }
                            }
                            
                            // Last resort - check if user has typed anything recently
                            if (!title.trim()) {
                                console.log('Siloq AI: Last resort - checking for any user input');
                                // Check if there's any focused element that might be the title
                                var activeElement = document.activeElement;
                                if (activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA' || activeElement.getAttribute('contenteditable') === 'true')) {
                                    title = activeElement.value || activeElement.textContent || activeElement.innerText || '';
                                    console.log('Siloq AI: Found title from active element:', title);
                                }
                            }
                            
                            // ABSOLUTE LAST RESORT - Direct targeting of the element we know exists
                            if (!title.trim()) {
                                console.log('Siloq AI: ABSOLUTE LAST RESORT - Direct targeting');
                                var directElement = document.getElementById('post-title-0');
                                if (directElement) {
                                    console.log('Siloq AI: Found post-title-0 directly');
                                    console.log('Siloq AI: directElement.value:', directElement.value);
                                    console.log('Siloq AI: directElement.textContent:', directElement.textContent);
                                    console.log('Siloq AI: directElement.innerText:', directElement.innerText);
                                    console.log('Siloq AI: directElement.innerHTML:', directElement.innerHTML);
                                    
                                    // Try multiple ways to get the value
                                    title = directElement.value || directElement.getAttribute('value') || directElement.textContent || directElement.innerText || '';
                                    console.log('Siloq AI: Final direct title:', title);
                                    
                                    // Also try to force trigger any events that might update the value
                                    if (!title.trim() && directElement.value === undefined) {
                                        console.log('Siloq AI: Trying to trigger input event');
                                        var event = new Event('input', { bubbles: true });
                                        directElement.dispatchEvent(event);
                                        title = directElement.value || directElement.textContent || directElement.innerText || '';
                                        console.log('Siloq AI: Title after event trigger:', title);
                                    }
                                } else {
                                    console.log('Siloq AI: post-title-0 element not found');
                                }
                            }
                            
                            // If still no title and we haven't maxed out attempts, wait and try again
                            if (!title.trim() && attempts < 10) {
                                console.log('Siloq AI: No title found, waiting 500ms and trying again...');
                                setTimeout(function() {
                                    findTitleField(callback, attempts + 1);
                                }, 500);
                            } else {
                                console.log('Siloq AI: Final title found:', title);
                                console.log('Siloq AI: Title type:', typeof title);
                                console.log('Siloq AI: Title length:', title ? title.length : 'N/A');
                                callback(title);
                            }
                        }
                        
                        // Start the title detection process
                        findTitleField(function(title) {
                            console.log('Siloq AI: Title detection callback received');
                            console.log('Siloq AI: Callback title parameter:', title);
                            console.log('Siloq AI: Callback title type:', typeof title);
                            console.log('Siloq AI: Callback title length:', title ? title.length : 'N/A');
                            console.log('Siloq AI: Callback title.trim():', title ? title.trim() : 'N/A');
                            console.log('Siloq AI: Callback title.trim().length:', title ? title.trim().length : 'N/A');
                            console.log('Siloq AI: Boolean check - title:', !!title);
                            console.log('Siloq AI: Boolean check - title.trim():', title ? !!title.trim() : 'N/A');
                            console.log('Siloq AI: Boolean check - title.trim().length > 0:', title ? title.trim().length > 0 : 'N/A');
                            
                            if (!title || !title.trim() || title.trim().length === 0) {
                                alert('No page title detected\n\nPlease make sure you have entered a title in the page title field at the top of the editor.\n\nTroubleshooting tips:\n• Click in the title field and type your page title\n• Make sure the title field is not empty\n• Try refreshing the page and entering the title again\n\nIf you continue to see this message, please check the browser console (F12) for debugging information.');
                                return;
                            }
                            
                            console.log('Siloq AI: Title validation passed - proceeding with content generation');
                            console.log('Siloq AI: Button element:', this);
                            console.log('Siloq AI: Button disabled before:', this.disabled);
                            console.log('Siloq AI: Button HTML before:', this.innerHTML);
                            
                            var button = this;
                            var originalText = button.innerHTML;
                            
                            // Show loading
                            button.innerHTML = '<span style=\"display: flex; align-items: center; gap: 10px; justify-content: center;\"><svg width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\"><path d=\"M12 2L2 7L12 12L22 7L12 2Z\" stroke=\"white\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><path d=\"M2 17L12 22L22 17\" stroke=\"white\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><path d=\"M2 12L12 17L22 12\" stroke=\"white\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg><span>Generating...</span></span>';
                            button.disabled = true;
                            
                            console.log('Siloq AI: Button disabled after:', button.disabled);
                            console.log('Siloq AI: Button HTML after:', button.innerHTML);
                            console.log('Siloq AI: Starting content generation for:', title);
                            
                            // Generate content
                            setTimeout(function() {
                                console.log('Siloq AI: setTimeout callback triggered');
                                console.log('Siloq AI: Generating content...');
                                
                                // Create WordPress blocks instead of markdown
                                var blocks = [
                                    // Main title block
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 1, content: title },
                                        innerBlocks: []
                                    },
                                    // Introduction paragraph
                                    {
                                        blockName: 'core/paragraph',
                                        attrs: {},
                                        innerBlocks: [],
                                        innerHTML: '<p>Welcome to your comprehensive guide on ' + title + '. This page provides detailed information, insights, and resources to help you understand and make the most of this topic.</p>'
                                    },
                                    // Overview heading
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 2, content: '📋 Overview' },
                                        innerBlocks: []
                                    },
                                    // Overview paragraph
                                    {
                                        blockName: 'core/paragraph',
                                        attrs: {},
                                        innerBlocks: [],
                                        innerHTML: '<p>' + title + ' represents a critical component of modern business strategy. In today\'s competitive landscape, understanding and implementing effective approaches can significantly impact your success and growth trajectory.</p>'
                                    },
                                    // Key Benefits heading
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 2, content: '🔑 Key Benefits' },
                                        innerBlocks: []
                                    },
                                    // Enhanced Efficiency
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 3, content: 'Enhanced Efficiency' },
                                        innerBlocks: []
                                    },
                                    {
                                        blockName: 'core/paragraph',
                                        attrs: {},
                                        innerBlocks: [],
                                        innerHTML: '<p>Streamline your operations and achieve better results with optimized processes and methodologies.</p>'
                                    },
                                    // Cost Optimization
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 3, content: 'Cost Optimization' },
                                        innerBlocks: []
                                    },
                                    {
                                        blockName: 'core/paragraph',
                                        attrs: {},
                                        innerBlocks: [],
                                        innerHTML: '<p>Reduce unnecessary expenses while maintaining or improving quality through strategic resource allocation.</p>'
                                    },
                                    // Competitive Advantage
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 3, content: 'Competitive Advantage' },
                                        innerBlocks: []
                                    },
                                    {
                                        blockName: 'core/paragraph',
                                        attrs: {},
                                        innerBlocks: [],
                                        innerHTML: '<p>Stay ahead of the competition with innovative solutions and forward-thinking approaches.</p>'
                                    },
                                    // Scalability
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 3, content: 'Scalability' },
                                        innerBlocks: []
                                    },
                                    {
                                        blockName: 'core/paragraph',
                                        attrs: {},
                                        innerBlocks: [],
                                        innerHTML: '<p>Build systems and processes that grow with your business, ensuring long-term sustainability.</p>'
                                    },
                                    // Implementation Strategy heading
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 2, content: '🛠️ Implementation Strategy' },
                                        innerBlocks: []
                                    },
                                    // Phase 1
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 3, content: 'Phase 1: Assessment' },
                                        innerBlocks: []
                                    },
                                    {
                                        blockName: 'core/list',
                                        attrs: { ordered: false },
                                        innerBlocks: [],
                                        innerHTML: '<ul><li>Current state analysis</li><li>Gap identification</li><li>Opportunity evaluation</li></ul>'
                                    },
                                    // Phase 2
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 3, content: 'Phase 2: Planning' },
                                        innerBlocks: []
                                    },
                                    {
                                        blockName: 'core/list',
                                        attrs: { ordered: false },
                                        innerBlocks: [],
                                        innerHTML: '<ul><li>Goal setting and KPI definition</li><li>Resource allocation</li><li>Timeline development</li></ul>'
                                    },
                                    // Phase 3
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 3, content: 'Phase 3: Execution' },
                                        innerBlocks: []
                                    },
                                    {
                                        blockName: 'core/list',
                                        attrs: { ordered: false },
                                        innerBlocks: [],
                                        innerHTML: '<ul><li>Implementation of planned strategies</li><li>Progress monitoring</li><li>Adjustment and optimization</li></ul>'
                                    },
                                    // Phase 4
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 3, content: 'Phase 4: Evaluation' },
                                        innerBlocks: []
                                    },
                                    {
                                        blockName: 'core/list',
                                        attrs: { ordered: false },
                                        innerBlocks: [],
                                        innerHTML: '<ul><li>Results measurement</li><li>Success criteria validation</li><li>Lessons learned documentation</li></ul>'
                                    },
                                    // Success Metrics heading
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 2, content: '📈 Success Metrics' },
                                        innerBlocks: []
                                    },
                                    // Success metrics list
                                    {
                                        blockName: 'core/list',
                                        attrs: { ordered: false },
                                        innerBlocks: [],
                                        innerHTML: '<ul><li><strong>Performance Improvement</strong>: Measure efficiency gains and productivity increases</li><li><strong>Cost Savings</strong>: Track financial impact and ROI</li><li><strong>Customer Satisfaction</strong>: Monitor feedback and satisfaction scores</li><li><strong>Market Position</strong>: Assess competitive standing and market share</li></ul>'
                                    },
                                    // Partnership Opportunities heading
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 2, content: '🤝 Partnership Opportunities' },
                                        innerBlocks: []
                                    },
                                    {
                                        blockName: 'core/paragraph',
                                        attrs: {},
                                        innerBlocks: [],
                                        innerHTML: '<p>We believe in collaborative success. Whether you\'re looking for consultation, implementation support, or ongoing partnership, we\'re here to help you achieve your goals.</p>'
                                    },
                                    // Next Steps heading
                                    {
                                        blockName: 'core/heading',
                                        attrs: { level: 2, content: '📞 Next Steps' },
                                        innerBlocks: []
                                    },
                                    {
                                        blockName: 'core/paragraph',
                                        attrs: {},
                                        innerBlocks: [],
                                        innerHTML: '<p>Ready to take action? Contact our team today to discuss how we can help you implement ' + title + ' effectively:</p>'
                                    },
                                    // Next steps list
                                    {
                                        blockName: 'core/list',
                                        attrs: { ordered: false },
                                        innerBlocks: [],
                                        innerHTML: '<ul><li><strong>Schedule a consultation</strong></li><li><strong>Request a proposal</strong></li><li><strong>Get a free assessment</strong></li></ul>'
                                    },
                                    // Separator
                                    {
                                        blockName: 'core/separator',
                                        attrs: {},
                                        innerBlocks: []
                                    },
                                    // Footer
                                    {
                                        blockName: 'core/paragraph',
                                        attrs: {},
                                        innerBlocks: [],
                                        innerHTML: '<p><em>This content was generated by Siloq AI. Customize it with your specific details, examples, and brand voice.</em></p>'
                                    }
                                ];
                                
                                console.log('Siloq AI: Content generated as', blocks.length, 'WordPress blocks');
                                console.log('Siloq AI: First block sample:', blocks[0]);
                                console.log('Siloq AI: Attempting to insert content...');
                                
                                var inserted = false;
                                
                                // Try to insert content as WordPress blocks
                                console.log('Siloq AI: Checking WordPress editor availability...');
                                console.log('Siloq AI: typeof wp:', typeof wp);
                                console.log('Siloq AI: wp.data:', typeof wp !== 'undefined' ? typeof wp.data : 'undefined');
                                console.log('Siloq AI: wp.data.select:', typeof wp !== 'undefined' && wp.data ? typeof wp.data.select : 'undefined');
                                
                                if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
                                    console.log('Siloq AI: Trying Gutenberg editor with blocks');
                                    try {
                                        var editor = wp.data.select('core/editor');
                                        console.log('Siloq AI: Editor object:', editor);
                                        console.log('Siloq AI: typeof editor:', typeof editor);
                                        
                                        if (editor && typeof editor.getBlocks === 'function') {
                                            var currentBlocks = editor.getBlocks();
                                            console.log('Siloq AI: Found', currentBlocks.length, 'existing blocks');
                                            console.log('Siloq AI: Current blocks sample:', currentBlocks.slice(0, 2));
                                        } else {
                                            console.log('Siloq AI: getBlocks method not available');
                                        }
                                        
                                        // Create WordPress blocks from our structure
                                        var wpBlocks = [];
                                        console.log('Siloq AI: Creating WordPress blocks...');
                                        
                                        for (var i = 0; i < blocks.length; i++) {
                                            var block = blocks[i];
                                            var wpBlock;
                                            
                                            if (block.blockName === 'core/heading') {
                                                wpBlock = wp.blocks.createBlock('core/heading', {
                                                    level: block.attrs.level,
                                                    content: block.attrs.content
                                                });
                                                console.log('Siloq AI: Created heading block:', block.attrs.content);
                                            } else if (block.blockName === 'core/paragraph') {
                                                wpBlock = wp.blocks.createBlock('core/paragraph', {
                                                    content: block.innerHTML.replace(/<p[^>]*>|<\/p>/g, '')
                                                });
                                                console.log('Siloq AI: Created paragraph block');
                                            } else if (block.blockName === 'core/list') {
                                                var listItems = block.innerHTML.replace(/<ul[^>]*>|<\/ul>/g, '').replace(/<li[^>]*>|<\/li>/g, '').split('</li><li>').filter(Boolean);
                                                wpBlock = wp.blocks.createBlock('core/list', {
                                                    ordered: block.attrs.ordered,
                                                    values: listItems
                                                });
                                                console.log('Siloq AI: Created list block with', listItems.length, 'items');
                                            } else if (block.blockName === 'core/separator') {
                                                wpBlock = wp.blocks.createBlock('core/separator', {});
                                                console.log('Siloq AI: Created separator block');
                                            }
                                            
                                            if (wpBlock) {
                                                wpBlocks.push(wpBlock);
                                            }
                                        }
                                        
                                        console.log('Siloq AI: Created', wpBlocks.length, 'WordPress blocks');
                                        console.log('Siloq AI: wp.blocks object:', typeof wp !== 'undefined' && wp.blocks ? 'available' : 'not available');
                                        console.log('Siloq AI: wp.data.dispatch:', typeof wp !== 'undefined' && wp.data && wp.data.dispatch ? 'available' : 'not available');
                                        
                                        if (wpBlocks.length > 0 && typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
                                            var dispatch = wp.data.dispatch('core/editor');
                                            console.log('Siloq AI: Dispatch object:', dispatch);
                                            
                                            if (dispatch && typeof dispatch.resetBlocks === 'function') {
                                                dispatch.resetBlocks(wpBlocks);
                                                console.log('Siloq AI: ✅ Blocks inserted via resetBlocks');
                                                inserted = true;
                                            } else if (dispatch && typeof dispatch.insertBlocks === 'function') {
                                                dispatch.insertBlocks(wpBlocks);
                                                console.log('Siloq AI: ✅ Blocks inserted via insertBlocks');
                                                inserted = true;
                                            } else {
                                                console.log('Siloq AI: ❌ No block insertion methods available');
                                            }
                                        } else {
                                            console.log('Siloq AI: ❌ Cannot insert blocks - wpBlocks empty or dispatch unavailable');
                                        }
                                    } catch (e) {
                                        console.log('Siloq AI: Gutenberg blocks failed:', e.message);
                                        console.log('Siloq AI: Gutenberg blocks error stack:', e.stack);
                                    }
                                } else {
                                    console.log('Siloq AI: WordPress editor not available');
                                }
                                
                                // Fallback: Try HTML content
                                if (!inserted) {
                                    console.log('Siloq AI: Trying HTML content fallback');
                                    
                                    try {
                                        var htmlContent = '<h1>' + title + '</h1>' +
                                            '<p>Welcome to your comprehensive guide on ' + title + '. This page provides detailed information, insights, and resources to help you understand and make the most of this topic.</p>' +
                                            '<h2>📋 Overview</h2>' +
                                            '<p>' + title + ' represents a critical component of modern business strategy. In today\'s competitive landscape, understanding and implementing effective approaches can significantly impact your success and growth trajectory.</p>' +
                                            '<h2>🔑 Key Benefits</h2>' +
                                            '<h3>Enhanced Efficiency</h3>' +
                                            '<p>Streamline your operations and achieve better results with optimized processes and methodologies.</p>' +
                                            '<h3>Cost Optimization</h3>' +
                                            '<p>Reduce unnecessary expenses while maintaining or improving quality through strategic resource allocation.</p>' +
                                            '<h3>Competitive Advantage</h3>' +
                                            '<p>Stay ahead of the competition with innovative solutions and forward-thinking approaches.</p>' +
                                            '<h3>Scalability</h3>' +
                                            '<p>Build systems and processes that grow with your business, ensuring long-term sustainability.</p>' +
                                            '<h2>🛠️ Implementation Strategy</h2>' +
                                            '<h3>Phase 1: Assessment</h3>' +
                                            '<ul><li>Current state analysis</li><li>Gap identification</li><li>Opportunity evaluation</li></ul>' +
                                            '<h3>Phase 2: Planning</h3>' +
                                            '<ul><li>Goal setting and KPI definition</li><li>Resource allocation</li><li>Timeline development</li></ul>' +
                                            '<h3>Phase 3: Execution</h3>' +
                                            '<ul><li>Implementation of planned strategies</li><li>Progress monitoring</li><li>Adjustment and optimization</li></ul>' +
                                            '<h3>Phase 4: Evaluation</h3>' +
                                            '<ul><li>Results measurement</li><li>Success criteria validation</li><li>Lessons learned documentation</li></ul>' +
                                            '<h2>📈 Success Metrics</h2>' +
                                            '<ul><li><strong>Performance Improvement</strong>: Measure efficiency gains and productivity increases</li><li><strong>Cost Savings</strong>: Track financial impact and ROI</li><li><strong>Customer Satisfaction</strong>: Monitor feedback and satisfaction scores</li><li><strong>Market Position</strong>: Assess competitive standing and market share</li></ul>' +
                                            '<h2>🤝 Partnership Opportunities</h2>' +
                                            '<p>We believe in collaborative success. Whether you\'re looking for consultation, implementation support, or ongoing partnership, we\'re here to help you achieve your goals.</p>' +
                                            '<h2>📞 Next Steps</h2>' +
                                            '<p>Ready to take action? Contact our team today to discuss how we can help you implement ' + title + ' effectively:</p>' +
                                            '<ul><li><strong>Schedule a consultation</strong></li><li><strong>Request a proposal</strong></li><li><strong>Get a free assessment</strong></li></ul>' +
                                            '<hr>' +
                                            '<p><em>This content was generated by Siloq AI. Customize it with your specific details, examples, and brand voice.</em></p>';
                                        
                                        console.log('Siloq AI: HTML content created, length:', htmlContent.length);
                                        
                                        // Try TinyMCE
                                        if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
                                            console.log('Siloq AI: Trying TinyMCE with HTML');
                                            try {
                                                tinyMCE.activeEditor.setContent(htmlContent);
                                                console.log('Siloq AI: Content inserted via TinyMCE');
                                                inserted = true;
                                            } catch (e) {
                                                console.log('Siloq AI: TinyMCE failed:', e.message);
                                            }
                                        }
                                        
                                        // Try textarea
                                        if (!inserted) {
                                            console.log('Siloq AI: Trying textarea with HTML');
                                            var textarea = document.querySelector('#content') || document.querySelector('textarea[name=\"content\"]') || document.querySelector('textarea');
                                            if (textarea) {
                                                textarea.value = htmlContent;
                                                console.log('Siloq AI: Content inserted via textarea');
                                                inserted = true;
                                                
                                                // Trigger change event
                                                var event = new Event('input', { bubbles: true });
                                                textarea.dispatchEvent(event);
                                            }
                                        }
                                        
                                        // Try contenteditable
                                        if (!inserted) {
                                            console.log('Siloq AI: Trying contenteditable with HTML');
                                            var editable = document.querySelector('[contenteditable=\"true\"]') || document.querySelector('.editor-content') || document.querySelector('.block-editor-writing-flow');
                                            if (editable) {
                                                editable.innerHTML = htmlContent;
                                                console.log('Siloq AI: Content inserted via contenteditable');
                                                inserted = true;
                                            }
                                        }
                                    } catch (e) {
                                        console.log('Siloq AI: HTML fallback failed:', e.message);
                                        console.log('Siloq AI: HTML fallback error stack:', e.stack);
                                    }
                                } else {
                                    console.log('Siloq AI: Skipping HTML fallback - content already inserted');
                                }
                                
                                console.log('Siloq AI: Content insertion result:', inserted ? 'SUCCESS' : 'FAILED');
                                console.log('Siloq AI: inserted variable type:', typeof inserted);
                                console.log('Siloq AI: inserted variable value:', inserted);
                                console.log('Siloq AI: About to restore button...');
                                
                                // Restore button
                                try {
                                    console.log('Siloq AI: Restoring button state...');
                                    console.log('Siloq AI: Button before restore:', button);
                                    console.log('Siloq AI: Button disabled before restore:', button.disabled);
                                    console.log('Siloq AI: Original text:', originalText);
                                    
                                    button.innerHTML = originalText;
                                    button.disabled = false;
                                    
                                    console.log('Siloq AI: Button after restore:', button);
                                    console.log('Siloq AI: Button disabled after restore:', button.disabled);
                                    console.log('Siloq AI: Button HTML after restore:', button.innerHTML);
                                } catch (e) {
                                    console.log('Siloq AI: Error restoring button:', e.message);
                                    console.log('Siloq AI: Button restore error stack:', e.stack);
                                }
                                
                                // Show result notification
                                if (inserted) {
                                    alert('Content generated successfully!\n\nThe content has been added to your page editor with proper WordPress formatting including headings, paragraphs, and lists.');
                                } else {
                                    alert('Content generated but could not be inserted automatically.\n\nContent blocks created: ' + blocks.length + '\n\nPlease check the browser console for debugging information.');
                                }
                                
                                console.log('Siloq AI: Content generation process completed');
                            }, 1500);
                        }.bind(this));
                    });
                    
                    console.log('Siloq AI: Button injected from inline script');
                }
                
                // Try multiple times
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        injectSiloqAIButton();
                        setTimeout(injectSiloqAIButton, 1000);
                        setTimeout(injectSiloqAIButton, 3000);
                    });
                } else {
                    injectSiloqAIButton();
                    setTimeout(injectSiloqAIButton, 1000);
                    setTimeout(injectSiloqAIButton, 3000);
                }
            ";
            
            wp_add_inline_script('siloq-page-editor', $inline_script);
        }
        
        // Get current screen for initial view
        $screen = get_current_screen();
        $initial_view = 'dashboard';
        
        if ($screen && isset($screen->id)) {
            if (strpos($screen->id, 'wizard') !== false) {
                $initial_view = 'wizard';
            } elseif (strpos($screen->id, 'create') !== false) {
                $initial_view = 'create';
            } elseif (strpos($screen->id, 'sync') !== false) {
                $initial_view = 'sync';
            } elseif (strpos($screen->id, 'scanner') !== false) {
                $initial_view = 'scanner';
            } elseif (strpos($screen->id, 'settings') !== false) {
                $initial_view = 'settings';
            }
        }
        
        // Check if built assets exist
        $asset_file_path = SILOQ_PLUGIN_DIR . 'react-ui/dist/index.asset.php';
        
        if (file_exists($asset_file_path)) {
            $asset_file = include($asset_file_path);
            
            wp_enqueue_script(
                'siloq-script',
                SILOQ_PLUGIN_URL . 'react-ui/dist/index.js',
                array_merge($asset_file['dependencies'], array('tailwindcss')),
                $asset_file['version'],
                true
            );
        } else {
            // Development mode
            wp_enqueue_script(
                'siloq-script',
                'http://localhost:3000/index.tsx',
                array('tailwindcss', 'react', 'react-dom'),
                SILOQ_VERSION,
                true
            );
            
            add_action('admin_head', function() {
                $screen = get_current_screen();
                if ($screen && $screen->id === 'toplevel_page_siloq-connector') {
                    ?>
                    <style>#wpcontent { padding: 0 !important; }</style>
                    <?php
                }
                ?>
                <script type="importmap">
                {
                  "imports": {
                    "react/": "https://esm.sh/react@^19.2.4/",
                    "react": "https://esm.sh/react@^19.2.4",
                    "lucide-react": "https://esm.sh/lucide-react@^0.563.0",
                    "react-dom/": "https://esm.sh/react-dom@^19.2.4/"
                  }
                }
                </script>
                <?php
            });
        }
        
        // Localize script with WordPress data
        wp_localize_script('siloq-script', 'siloqData', array(
            'restUrl'     => esc_url_raw(rest_url('siloq/v1')),
            'nonce'       => wp_create_nonce('wp_rest'),
            'initialView' => $initial_view,
            'apiKey'      => get_option('siloq_api_key', ''),
            'siteId'      => get_option('siloq_site_id', ''),
            'connected'   => !empty(get_option('siloq_api_key')),
            'autoSync'    => get_option('siloq_auto_sync', false),
            'wizardCompleted' => get_option('siloq_wizard_completed', false),
=======
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'siloq') === false) {
            return;
        }
        
        wp_enqueue_style(
            'siloq-admin-css',
            SILOQ_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SILOQ_VERSION
        );
        
        wp_enqueue_script(
            'siloq-admin-js',
            SILOQ_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SILOQ_VERSION,
            true
        );
        
        // Pass data to JavaScript
        wp_localize_script('siloq-admin-js', 'siloqAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('siloq_ajax_nonce'),
            'strings' => array(
                'testing' => __('Testing connection...', 'siloq-connector'),
                'syncing' => __('Syncing...', 'siloq-connector'),
                'success' => __('Success!', 'siloq-connector'),
                'error' => __('Error:', 'siloq-connector')
            )
>>>>>>> pr-17
        ));
    }
    
    /**
<<<<<<< HEAD
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('siloq/v1', '/config', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_config'),
                'permission_callback' => array($this, 'check_permission'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'save_config'),
                'permission_callback' => array($this, 'check_permission'),
            ),
        ));
        
        register_rest_route('siloq/v1', '/disconnect', array(
            'methods' => 'POST',
            'callback' => array($this, 'disconnect'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        register_rest_route('siloq/v1', '/pages', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_pages'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        register_rest_route('siloq/v1', '/sync', array(
            'methods' => 'POST',
            'callback' => array($this, 'sync_pages'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        register_rest_route('siloq/v1', '/scan', array(
            'methods' => 'POST',
            'callback' => array($this, 'scan_content'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        register_rest_route('siloq/v1', '/wizard/complete', array(
            'methods' => 'POST',
            'callback' => array($this, 'complete_wizard'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        // Page creation endpoint
        register_rest_route('siloq/v1', '/pages/create', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_page'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        // Webhook endpoint for receiving Siloq notifications
        register_rest_route('siloq/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true', // Webhook uses HMAC signature verification
        ));
        
        // AI Content Generation endpoints
        register_rest_route('siloq/v1', '/content-jobs', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_content_job'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        register_rest_route('siloq/v1', '/content-jobs/(?P<id>[a-zA-Z0-9_-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_content_job'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        // Content Import endpoint
        register_rest_route('siloq/v1', '/import', array(
            'methods' => 'POST',
            'callback' => array($this, 'import_content'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        // Schema Markup endpoints
        register_rest_route('siloq/v1', '/pages/(?P<id>\d+)/schema', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_schema_markup'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        register_rest_route('siloq/v1', '/pages/(?P<id>\d+)/schema', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_schema_markup'),
            'permission_callback' => array($this, 'check_permission'),
        ));
        
        // Internal Links endpoint
        register_rest_route('siloq/v1', '/internal-links', array(
            'methods' => 'POST',
            'callback' => array($this, 'inject_internal_links'),
            'permission_callback' => array($this, 'check_permission'),
        ));
    }
    
    public function check_permission() {
        return current_user_can('manage_options');
    }
    
    public function get_config() {
        return rest_ensure_response(array(
            'apiKey' => get_option('siloq_api_key', ''),
            'siteId' => get_option('siloq_site_id', ''),
            'connected' => !empty(get_option('siloq_api_key')),
            'autoSync' => get_option('siloq_auto_sync', false),
            'wizardCompleted' => get_option('siloq_wizard_completed', false),
        ));
    }
    
    public function save_config($request) {
        $params = $request->get_json_params();
        
        if (isset($params['apiKey'])) {
            update_option('siloq_api_key', sanitize_text_field($params['apiKey']));
        }
        if (isset($params['autoSync'])) {
            update_option('siloq_auto_sync', (bool) $params['autoSync']);
        }
        if (isset($params['siteId'])) {
            update_option('siloq_site_id', sanitize_text_field($params['siteId']));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    public function disconnect() {
        delete_option('siloq_api_key');
        delete_option('siloq_site_id');
        delete_option('siloq_auto_sync');
        delete_option('siloq_wizard_completed');
        
        return rest_ensure_response(array('success' => true));
    }
    
    public function get_pages() {
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft'),
        ));
        
        $pages = array();
        foreach ($posts as $post) {
            $pages[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'type' => $post->post_type,
                'status' => $post->post_status,
                'author' => get_the_author_meta('display_name', $post->post_author),
                'modified' => $post->post_modified,
                'synced' => (bool) get_post_meta($post->ID, '_siloq_synced', true),
                'syncedAt' => get_post_meta($post->ID, '_siloq_synced_at', true),
            );
        }
        
        return rest_ensure_response($pages);
    }
    
    public function create_page($request) {
        $params = $request->get_json_params();
        
        // Validate required fields
        $title = isset($params['title']) ? sanitize_text_field($params['title']) : '';
        $content = isset($params['content']) ? $params['content'] : '';
        $status = isset($params['status']) ? sanitize_text_field($params['status']) : 'draft';
        $auto_sync = isset($params['autoSync']) ? (bool) $params['autoSync'] : true;
        
        // Sanitize content after checking if it exists
        $content_sanitized = !empty($content) ? wp_kses_post($content) : '';
        
        if (empty($title)) {
            return new WP_Error('missing_title', 'Page title is required', array('status' => 400));
        }
        
        // More flexible content validation - allow basic content
        if (empty($content)) {
            return new WP_Error('missing_content', 'Page content is required', array('status' => 400));
        }
        
        // Only check minimum length if content exists
        if (strlen(trim($content)) < 3) {
            return new WP_Error('missing_content', 'Page content is required (minimum 3 characters)', array('status' => 400));
        }
        
        // Create the page
        $post_data = array(
            'post_title' => $title,
            'post_content' => $content_sanitized,
            'post_status' => in_array($status, array('publish', 'draft', 'pending', 'private')) ? $status : 'draft',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
        );
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            return new WP_Error('create_failed', $post_id->get_error_message(), array('status' => 500));
        }
        
        // Mark as created by Siloq
        update_post_meta($post_id, '_siloq_created', true);
        update_post_meta($post_id, '_siloq_created_at', current_time('mysql'));
        
        // Auto-sync if requested
        if ($auto_sync) {
            // Get API key and site ID
            $api_key = get_option('siloq_api_key', '');
            $site_id = get_option('siloq_site_id', '');
            
            if (!empty($api_key)) {
                $post = get_post($post_id);
                $page_data = array(
                    'wp_post_id' => $post->ID,
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'url' => get_permalink($post->ID),
                    'type' => $post->post_type,
                    'status' => $post->post_status,
                    'author' => get_the_author_meta('display_name', $post->post_author),
                    'modified' => $post->post_modified,
                    'site_id' => $site_id,
                );
                
                // Send to Siloq backend
                $response = wp_remote_post('http://localhost:8000/api/v1/pages/sync/', array(
                    'method' => 'POST',
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'X-API-Key' => $api_key,
                        'X-Site-ID' => $site_id,
                    ),
                    'body' => json_encode($page_data),
                    'timeout' => 30,
                ));
                
                if (!is_wp_error($response)) {
                    $status_code = wp_remote_retrieve_response_code($response);
                    if ($status_code >= 200 && $status_code < 300) {
                        update_post_meta($post_id, '_siloq_synced', true);
                        update_post_meta($post_id, '_siloq_synced_at', current_time('mysql'));
                    }
                }
            }
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'pageId' => $post_id,
            'url' => get_permalink($post_id),
            'status' => get_post_status($post_id),
            'autoSynced' => $auto_sync && get_post_meta($post_id, '_siloq_synced', true),
            'message' => 'Page created successfully'
        ));
    }
    
    public function sync_pages($request) {
        $params = $request->get_json_params();
        $page_ids = isset($params['pageIds']) ? array_map('intval', $params['pageIds']) : array();
        
        // For testing - use valid API key format
        $api_key = 'sk_siloq_zvM_S1VvFD-Xe_MYHgB6iCZY4osoD54_ZWZH3ohENDk';
        $site_id = '1';
        
        if (empty($api_key)) {
            return new WP_Error('not_connected', 'No API key configured', array('status' => 400));
        }
        
        $synced_count = 0;
        $errors = array();
        
        foreach ($page_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                $errors[] = "Post {$post_id} not found";
                continue;
            }
            
            // Prepare page data for Siloq API
            $page_data = array(
                'wp_post_id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'url' => get_permalink($post->ID),
                'type' => $post->post_type,
                'status' => $post->post_status,
                'author' => get_the_author_meta('display_name', $post->post_author),
                'modified' => $post->post_modified,
                'site_id' => $site_id,
            );
            
            // Send to Siloq backend API
            $response = wp_remote_post('http://localhost:8000/api/v1/pages/sync/', array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $api_key,
                    'X-Site-ID' => $site_id,
                ),
                'body' => json_encode($page_data),
                'timeout' => 30,
            ));
            
            if (is_wp_error($response)) {
                $errors[] = "Failed to sync page {$post_id}: " . $response->get_error_message();
                continue;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code >= 200 && $status_code < 300) {
                // Mark as synced locally only if external sync succeeded
                update_post_meta($post_id, '_siloq_synced', true);
                update_post_meta($post_id, '_siloq_synced_at', current_time('mysql'));
                $synced_count++;
            } else {
                $body = wp_remote_retrieve_body($response);
                $errors[] = "Page {$post_id} sync failed with status {$status_code}: " . $body;
            }
        }
        
        return rest_ensure_response(array(
            'success' => $synced_count > 0,
            'synced' => $synced_count,
            'total' => count($page_ids),
            'errors' => $errors,
        ));
    }
    
    public function scan_content($request) {
        $params = $request->get_json_params();
        $content = isset($params['content']) ? sanitize_textarea_field($params['content']) : '';
        $page_id = isset($params['pageId']) ? intval($params['pageId']) : 0;
        
        // Mock scan results - in production this would call Siloq AI API
        return rest_ensure_response(array(
            'success' => true,
            'analysis' => array(
                'score' => rand(65, 95),
                'issues' => array(
                    array('type' => 'warning', 'message' => 'Add more keywords to improve SEO'),
                    array('type' => 'info', 'message' => 'Consider adding a stronger CTA'),
                ),
                'recommendations' => array(
                    'Add keyword-rich headings',
                    'Include internal links',
                    'Optimize meta description',
                ),
                'leadGenScore' => rand(70, 90),
            ),
        ));
    }
    
    public function complete_wizard($request) {
        $params = $request->get_json_params();
        
        if (isset($params['apiKey'])) {
            update_option('siloq_api_key', sanitize_text_field($params['apiKey']));
        }
        if (isset($params['siteId'])) {
            update_option('siloq_site_id', sanitize_text_field($params['siteId']));
        }
        
        update_option('siloq_wizard_completed', true);
        update_option('siloq_wizard_completed_at', current_time('mysql'));
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Webhook handler for Siloq notifications
     */
    public function handle_webhook($request) {
        $body = $request->get_json_params();
        $event = isset($body['event']) ? sanitize_text_field($body['event']) : '';
        $payload = isset($body['payload']) ? $body['payload'] : array();
        
        // Verify webhook signature if secret is configured
        $webhook_secret = get_option('siloq_webhook_secret', '');
        if (!empty($webhook_secret)) {
            $headers = $request->get_headers();
            $signature = isset($headers['x_siloq_signature']) ? $headers['x_siloq_signature'] : '';
            $expected = hash_hmac('sha256', $request->get_body(), $webhook_secret);
            
            if (!hash_equals($expected, $signature)) {
                return new WP_Error('invalid_signature', 'Invalid webhook signature', array('status' => 401));
            }
        }
        
        // Process webhook events
        switch ($event) {
            case 'content.generated':
                $this->handle_content_generated($payload);
                break;
                
            case 'schema.updated':
                $this->handle_schema_updated($payload);
                break;
                
            case 'page.analyzed':
                $this->handle_page_analyzed($payload);
                break;
                
            case 'sync.completed':
                $this->handle_sync_completed($payload);
                break;
                
            case 'internal_links.suggested':
                $this->handle_internal_links($payload);
                break;
        }
        
        return rest_ensure_response(array('success' => true, 'event' => $event));
    }
    
    /**
     * Handle content.generated webhook event
     */
    private function handle_content_generated($payload) {
        if (isset($payload['job_id'])) {
            update_option('siloq_job_' . $payload['job_id'], array(
                'status' => 'completed',
                'content' => isset($payload['content']) ? $payload['content'] : '',
                'completed_at' => current_time('mysql')
            ));
        }
        
        do_action('siloq_content_generated', $payload);
    }
    
    /**
     * Handle schema.updated webhook event
     */
    private function handle_schema_updated($payload) {
        if (isset($payload['page_id']) && isset($payload['schema'])) {
            update_post_meta(intval($payload['page_id']), '_siloq_schema_markup', $payload['schema']);
        }
        
        do_action('siloq_schema_updated', $payload);
    }
    
    /**
     * Handle page.analyzed webhook event
     */
    private function handle_page_analyzed($payload) {
        if (isset($payload['page_id'])) {
            update_post_meta(intval($payload['page_id']), '_siloq_analysis', $payload);
        }
        
        do_action('siloq_page_analyzed', $payload);
    }
    
    /**
     * Handle sync.completed webhook event
     */
    private function handle_sync_completed($payload) {
        if (isset($payload['page_ids']) && is_array($payload['page_ids'])) {
            foreach ($payload['page_ids'] as $page_id) {
                update_post_meta(intval($page_id), '_siloq_synced', true);
                update_post_meta(intval($page_id), '_siloq_synced_at', current_time('mysql'));
            }
        }
        
        do_action('siloq_sync_completed', $payload);
    }
    
    /**
     * Handle internal_links.suggested webhook event
     */
    private function handle_internal_links($payload) {
        if (isset($payload['page_id']) && isset($payload['links'])) {
            update_post_meta(intval($payload['page_id']), '_siloq_suggested_links', $payload['links']);
        }
        
        do_action('siloq_internal_links_suggested', $payload);
    }
    
    /**
     * Create AI content generation job
     */
    public function create_content_job($request) {
        $params = $request->get_json_params();
        
        // Validate required fields
        if (empty($params['topic'])) {
            return new WP_Error('missing_topic', 'Topic is required', array('status' => 400));
        }
        
        $job_id = 'job_' . uniqid();
        $job_data = array(
            'id' => $job_id,
            'topic' => sanitize_text_field($params['topic']),
            'keywords' => isset($params['keywords']) ? array_map('sanitize_text_field', $params['keywords']) : array(),
            'tone' => isset($params['tone']) ? sanitize_text_field($params['tone']) : 'professional',
            'length' => isset($params['length']) ? sanitize_text_field($params['length']) : 'medium',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'page_id' => isset($params['pageId']) ? intval($params['pageId']) : 0
        );
        
        // Store job
        update_option('siloq_job_' . $job_id, $job_data);
        
        // In production, this would queue the job with Siloq AI API
        // For now, simulate processing
        wp_schedule_single_event(time() + 30, 'siloq_process_content_job', array($job_id));
        
        return rest_ensure_response(array(
            'success' => true,
            'jobId' => $job_id,
            'status' => 'pending',
            'message' => 'Content generation job created'
        ));
    }
    
    /**
     * Get content job status
     */
    public function get_content_job($request) {
        $job_id = $request['id'];
        $job = get_option('siloq_job_' . $job_id);
        
        if (!$job) {
            return new WP_Error('job_not_found', 'Content job not found', array('status' => 404));
        }
        
        return rest_ensure_response($job);
    }
    
    /**
     * Import content from Siloq
     */
    public function import_content($request) {
        $params = $request->get_json_params();
        
        $content = isset($params['content']) ? $params['content'] : '';
        $title = isset($params['title']) ? sanitize_text_field($params['title']) : 'Imported Content';
        $page_id = isset($params['pageId']) ? intval($params['pageId']) : 0;
        $status = isset($params['status']) ? sanitize_text_field($params['status']) : 'draft';
        
        if (empty($content)) {
            return new WP_Error('missing_content', 'Content is required', array('status' => 400));
        }
        
        // Sanitize content
        $content = wp_kses_post($content);
        
        $post_data = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => in_array($status, array('publish', 'draft', 'pending')) ? $status : 'draft',
            'post_type' => 'page'
        );
        
        // Update existing page or create new
        if ($page_id > 0) {
            $post_data['ID'] = $page_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }
        
        if (is_wp_error($result)) {
            return new WP_Error('import_failed', $result->get_error_message(), array('status' => 500));
        }
        
        // Mark as imported from Siloq
        update_post_meta($result, '_siloq_imported', true);
        update_post_meta($result, '_siloq_imported_at', current_time('mysql'));
        
        return rest_ensure_response(array(
            'success' => true,
            'pageId' => $result,
            'url' => get_permalink($result),
            'message' => 'Content imported successfully'
        ));
    }
    
    /**
     * Get schema markup for a page
     */
    public function get_schema_markup($request) {
        $page_id = intval($request['id']);
        $post = get_post($page_id);
        
        if (!$post) {
            return new WP_Error('page_not_found', 'Page not found', array('status' => 404));
        }
        
        $schema = get_post_meta($page_id, '_siloq_schema_markup', true);
        
        if (empty($schema)) {
            // Generate default schema based on page type
            $schema = $this->generate_default_schema($post);
        }
        
        return rest_ensure_response(array(
            'pageId' => $page_id,
            'schema' => $schema,
            'type' => isset($schema['@type']) ? $schema['@type'] : 'WebPage'
        ));
    }
    
    /**
     * Save schema markup for a page
     */
    public function save_schema_markup($request) {
        $page_id = intval($request['id']);
        $params = $request->get_json_params();
        
        $post = get_post($page_id);
        if (!$post) {
            return new WP_Error('page_not_found', 'Page not found', array('status' => 404));
        }
        
        if (!isset($params['schema'])) {
            return new WP_Error('missing_schema', 'Schema markup is required', array('status' => 400));
        }
        
        $schema = $params['schema'];
        
        // Validate schema has required @type field
        if (!isset($schema['@type'])) {
            return new WP_Error('invalid_schema', 'Schema must have @type field', array('status' => 400));
        }
        
        // Store schema
        update_post_meta($page_id, '_siloq_schema_markup', $schema);
        update_post_meta($page_id, '_siloq_schema_updated', current_time('mysql'));
        
        return rest_ensure_response(array(
            'success' => true,
            'pageId' => $page_id,
            'message' => 'Schema markup saved successfully'
        ));
    }
    
    /**
     * Generate default schema markup
     */
    private function generate_default_schema($post) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $post->post_title,
            'url' => get_permalink($post->ID),
            'description' => wp_trim_words($post->post_content, 30)
        );
        
        // Add author if available
        $author = get_the_author_meta('display_name', $post->post_author);
        if ($author) {
            $schema['author'] = array(
                '@type' => 'Person',
                'name' => $author
            );
        }
        
        // Add date info
        $schema['datePublished'] = $post->post_date;
        $schema['dateModified'] = $post->post_modified;
        
        return $schema;
    }
    
    /**
     * Inject internal links into content
     */
    public function inject_internal_links($request) {
        $params = $request->get_json_params();
        
        $page_id = isset($params['pageId']) ? intval($params['pageId']) : 0;
        $links = isset($params['links']) ? $params['links'] : array();
        $auto_save = isset($params['autoSave']) ? (bool) $params['autoSave'] : false;
        
        if ($page_id === 0) {
            return new WP_Error('missing_page_id', 'Page ID is required', array('status' => 400));
        }
        
        $post = get_post($page_id);
        if (!$post) {
            return new WP_Error('page_not_found', 'Page not found', array('status' => 404));
        }
        
        $content = $post->post_content;
        $injected_count = 0;
        
        foreach ($links as $link) {
            if (!isset($link['keyword']) || !isset($link['url'])) {
                continue;
            }
            
            $keyword = preg_quote(sanitize_text_field($link['keyword']), '/');
            $url = esc_url($link['url']);
            $title = isset($link['title']) ? sanitize_text_field($link['title']) : '';
            
            // Find keyword and replace with link (only first occurrence)
            $pattern = '/\b' . $keyword . '\b/i';
            $replacement = '<a href="' . $url . '"' . ($title ? ' title="' . $title . '"' : '') . '>$0</a>';
            
            $new_content = preg_replace($pattern, $replacement, $content, 1);
            
            if ($new_content !== $content) {
                $content = $new_content;
                $injected_count++;
            }
        }
        
        if ($auto_save && $injected_count > 0) {
            wp_update_post(array(
                'ID' => $page_id,
                'post_content' => $content
            ));
        }
        
        // Store injection record
        update_post_meta($page_id, '_siloq_internal_links', array(
            'links' => $links,
            'injected_count' => $injected_count,
            'injected_at' => current_time('mysql'),
            'saved' => $auto_save
        ));
        
        return rest_ensure_response(array(
            'success' => true,
            'pageId' => $page_id,
            'injectedCount' => $injected_count,
            'saved' => $auto_save,
            'preview' => $auto_save ? null : $content,
            'message' => $injected_count . ' internal link(s) ' . ($auto_save ? 'injected and saved' : 'ready for injection')
        ));
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=siloq-settings') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize plugin
Siloq_Connector::get_instance();
=======
     * Enqueue page editor assets for AI content generator
     */
    public function enqueue_page_editor_assets() {
        global $post;
        
        // Only load on page editor
        if (!$post || $post->post_type !== 'page') {
            return;
        }
        
        // Enqueue the AI content generator script
        wp_enqueue_script(
            'siloq-ai-content-generator',
            SILOQ_PLUGIN_URL . 'assets/siloq-page-editor.js',
            array(),
            SILOQ_VERSION,
            true
        );
        
        // Pass data to script
        wp_localize_script('siloq-ai-content-generator', 'siloqAI', array(
            'apiUrl' => get_option('siloq_api_url', ''),
            'apiKey' => get_option('siloq_api_key', ''),
            'siteId' => get_option('siloq_site_id', ''),
            'postId' => $post->ID,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('siloq_ai_nonce'),
            'preferences' => Siloq_AI_Content_Generator::get_default_preferences()
        ));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load on pages with Siloq content
        if (!is_singular('page')) {
            return;
        }
        
        global $post;
        if (!$post || !is_a($post, 'WP_Post')) {
            return;
        }
        
        // Check if page has Siloq-generated content
        $has_siloq_content = get_post_meta($post->ID, '_siloq_generated_from', true);
        $has_faq = get_post_meta($post->ID, '_siloq_faq_items', true);
        
        if ($has_siloq_content || $has_faq) {
            wp_enqueue_style(
                'siloq-frontend-css',
                SILOQ_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                SILOQ_VERSION
            );
        }
    }
    
    /**
     * Auto-sync on page save/update
     */
    public function sync_on_save($post_id, $post, $update) {
        // Check if auto-sync is enabled
        if (get_option('siloq_auto_sync') !== 'yes') {
            return;
        }
        
        // Only sync pages (not posts or other post types)
        if ($post->post_type !== 'page') {
            return;
        }
        
        // Don't sync autosaves or revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Don't sync if post is not published
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Sync this page
        $sync_engine = new Siloq_Sync_Engine();
        $sync_engine->sync_page($post_id);
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // When using dummy scan only, skip API notice
        if (get_option('siloq_use_dummy_scan', 'yes') === 'yes') {
            return;
        }
        // Check if API settings are configured
        $api_url = get_option('siloq_api_url');
        $api_key = get_option('siloq_api_key');
        
        if (empty($api_url) || empty($api_key)) {
            $settings_url = admin_url('admin.php?page=siloq-settings');
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __('Siloq Connector:', 'siloq-connector') . '</strong> ';
            echo sprintf(
                __('Please configure your <a href="%s">API settings</a> to start syncing.', 'siloq-connector'),
                esc_url($settings_url)
            );
            echo '</p></div>';
        }
    }
    
    /**
     * Inject schema markup in page <head>
     */
    public function inject_schema_markup() {
        if (!is_singular('page')) {
            return;
        }
        
        global $post;
        if (!$post || !is_a($post, 'WP_Post')) {
            return;
        }
        $schema_markup = get_post_meta($post->ID, '_siloq_schema_markup', true);
        
        if (!empty($schema_markup)) {
            echo "\n<!-- Siloq Schema Markup -->\n";
            echo '<script type="application/ld+json">' . "\n";
            echo $schema_markup . "\n";
            echo '</script>' . "\n";
            echo "<!-- /Siloq Schema Markup -->\n";
        }
    }
    
    /**
     * AJAX: Test API connection.
     * Uses current form values (api_url, api_key) from POST; falls back to saved options if empty.
     */
    public function ajax_test_connection() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        // Prefer POST (form), then REQUEST (some servers), then saved options
        $api_url = '';
        $api_key = '';
        if (!empty($_POST['siloq_api_url']) || !empty($_POST['siloq_api_key'])) {
            $api_url = isset($_POST['siloq_api_url']) ? trim(sanitize_text_field(wp_unslash($_POST['siloq_api_url']))) : '';
            $api_key = isset($_POST['siloq_api_key']) ? trim(sanitize_text_field(wp_unslash($_POST['siloq_api_key']))) : '';
        }
        if (($api_url === '' || $api_key === '') && (isset($_REQUEST['siloq_api_url']) || isset($_REQUEST['siloq_api_key']))) {
            $api_url = isset($_REQUEST['siloq_api_url']) ? trim(sanitize_text_field(wp_unslash($_REQUEST['siloq_api_url']))) : $api_url;
            $api_key = isset($_REQUEST['siloq_api_key']) ? trim(sanitize_text_field(wp_unslash($_REQUEST['siloq_api_key']))) : $api_key;
        }
        if ($api_url === '' || $api_key === '') {
            $api_url = trim((string) get_option('siloq_api_url', 'https://api.siloq.ai/api/v1'));
            $api_key = trim((string) get_option('siloq_api_key', ''));
        }
        
        $api_client = new Siloq_API_Client();
        $result = $api_client->test_connection_with_credentials($api_url, $api_key);
        
        if ($result['success']) {
            // Extract and persist site_id so Business Profile works immediately
            $site_id = null;
            if (!empty($result['data']['site_id'])) {
                $site_id = $result['data']['site_id'];
            } elseif (!empty($result['data']['site']['id'])) {
                $site_id = $result['data']['site']['id'];
            }
            if ($site_id) {
                update_option('siloq_site_id', $site_id);
                set_transient('siloq_site_id', $site_id, HOUR_IN_SECONDS);
            }
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Sync single page
     */
    public function ajax_sync_page() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        
        $sync_engine = new Siloq_Sync_Engine();
        $result = $sync_engine->sync_page($post_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Sync all pages (batched to avoid PHP timeout)
     */
    public function ajax_sync_all_pages() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        // Extend execution time for large sites
        @set_time_limit(300);
        
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
        if ($batch_size < 1 || $batch_size > 200) {
            $batch_size = 50;
        }
        
        $sync_engine = new Siloq_Sync_Engine();
        $result = $sync_engine->sync_all_pages($offset, $batch_size);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Get sync status
     */
    public function ajax_get_sync_status() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $pages = get_posts(array(
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $status_data = array();
        foreach ($pages as $page) {
            $status_data[] = array(
                'id' => $page->ID,
                'title' => $page->post_title,
                'url' => get_permalink($page->ID),
                'last_synced' => get_post_meta($page->ID, '_siloq_last_synced', true),
                'sync_status' => get_post_meta($page->ID, '_siloq_sync_status', true),
                'has_schema' => !empty(get_post_meta($page->ID, '_siloq_schema_markup', true))
            );
        }
        
        wp_send_json_success(array('pages' => $status_data));
    }
    
    /**
     * AJAX: Import content from Siloq
     */
    public function ajax_import_content() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        $action = isset($_POST['import_action']) ? sanitize_text_field($_POST['import_action']) : 'create_draft';
        
        if (!$post_id || !$job_id) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
            return;
        }
        
        $import_handler = new Siloq_Content_Import();
        $result = $import_handler->import_from_job($post_id, $job_id, array('action' => $action));
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Generate content for a page
     */
    public function ajax_generate_content() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => 'Missing post ID'));
            return;
        }
        
        $api_client = new Siloq_API_Client();
        $result = $api_client->create_content_job($post_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Check job status
     */
    public function ajax_check_job_status() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
        
        if (!$job_id) {
            wp_send_json_error(array('message' => 'Missing job ID'));
            return;
        }
        
        $api_client = new Siloq_API_Client();
        $result = $api_client->get_content_job_status($job_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Restore backup content
     */
    public function ajax_restore_backup() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => 'Missing post ID'));
            return;
        }
        
        $import_handler = new Siloq_Content_Import();
        $result = $import_handler->restore_backup($post_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Sync outdated pages
     */
    public function ajax_sync_outdated() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $limit = min($limit, 50); // Max 50 pages at once
        
        $sync_engine = new Siloq_Sync_Engine();
        $result = $sync_engine->sync_outdated_pages($limit);
        
        wp_send_json_success($result);
    }

    /**
     * AJAX: Get business profile from Siloq
     */
    public function ajax_get_business_profile() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $api_client = new Siloq_API_Client();
        $site_id = $this->get_current_site_id();
        
        if (!$site_id) {
            wp_send_json_error(array('message' => 'No site connected. Please sync your pages first.'));
            return;
        }
        
        $result = $api_client->get_business_profile($site_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success($result);
    }

    /**
     * AJAX: Save business profile to Siloq
     */
    public function ajax_save_business_profile() {
        check_ajax_referer('siloq_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $api_client = new Siloq_API_Client();
        $site_id = $this->get_current_site_id();
        
        if (!$site_id) {
            wp_send_json_error(array('message' => 'No site connected. Please sync your pages first.'));
            return;
        }
        
        $profile_data = array(
            'business_type' => isset($_POST['business_type']) ? sanitize_text_field($_POST['business_type']) : '',
            'primary_services' => isset($_POST['primary_services']) ? array_map('sanitize_text_field', (array)$_POST['primary_services']) : array(),
            'service_areas' => isset($_POST['service_areas']) ? array_map('sanitize_text_field', (array)$_POST['service_areas']) : array(),
            'target_audience' => isset($_POST['target_audience']) ? sanitize_textarea_field($_POST['target_audience']) : '',
            'business_description' => isset($_POST['business_description']) ? sanitize_textarea_field($_POST['business_description']) : '',
        );
        
        $result = $api_client->save_business_profile($site_id, $profile_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'Business profile saved successfully!',
            'profile' => $result
        ));
    }

    /**
     * Get the current site ID from Siloq
     * Gets site_id from the auth/verify endpoint which works with API key auth
     */
    private function get_current_site_id() {
        // 1. Check persistent option first (survives restarts, no expiry)
        $site_id = get_option('siloq_site_id');
        if ($site_id) {
            return $site_id;
        }
        
        // Try to get it from the API
        $api_client = new Siloq_API_Client();
        $sites = $api_client->get_sites();
        
        if (is_wp_error($sites) || empty($sites)) {
            return null;
        }
        
        // Find the site matching this WordPress URL
        $site_url = get_site_url();
        foreach ($sites as $site) {
            if (isset($site['url']) && strpos($site['url'], parse_url($site_url, PHP_URL_HOST)) !== false) {
                set_transient('siloq_site_id', $site['id'], HOUR_IN_SECONDS);
                return $site['id'];
            }
        }
        
        // If no match, use the first site (user might only have one)
        if (!empty($sites[0]['id'])) {
            set_transient('siloq_site_id', $sites[0]['id'], HOUR_IN_SECONDS);
            return $sites[0]['id'];
        }
        
        return null;
    }
}

/**
 * Plugin activation
 */
function siloq_activate() {
    // Add default options
    add_option('siloq_api_url', '');
    add_option('siloq_api_key', '');
    add_option('siloq_auto_sync', 'no');
    add_option('siloq_use_dummy_scan', 'yes');
    
    // Create redirects table
    Siloq_Redirect_Manager::create_table();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'siloq_activate');

/**
 * Plugin deactivation
 */
function siloq_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'siloq_deactivate');

/**
 * Initialize the plugin
 */
function siloq_init() {
    return Siloq_Connector::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'siloq_init');
>>>>>>> pr-17
