<?php
/**
 * Siloq TALI (Theme-Aware Layout Intelligence)
 * Main TALI class initialization
 */

if (!defined('ABSPATH')) {
    exit;
}

function siloq_tali() {
    return Siloq_TALI::get_instance();
}

class Siloq_TALI {
    
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
        // Don't initialize during plugin activation/deactivation
        if (defined('WP_INSTALLING') && WP_INSTALLING) {
            return;
        }
        
        // Initialize TALI components
        $this->init_components();
    }
    
    /**
     * Initialize TALI components
     */
    private function init_components() {
        // Initialize fingerprinter if available
        if (class_exists('Siloq_TALI_Fingerprinter')) {
            Siloq_TALI_Fingerprinter::get_instance();
        }
        
        // Initialize block injector if available
        if (class_exists('Siloq_TALI_Block_Injector')) {
            Siloq_TALI_Block_Injector::get_instance();
        }
        
        // Initialize component mapper if available
        if (class_exists('Siloq_TALI_Component_Mapper')) {
            Siloq_TALI_Component_Mapper::get_instance();
        }
    }
    
    /**
     * Get theme information
     */
    public function get_theme_info() {
        $theme = wp_get_theme();
        
        return array(
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'author' => $theme->get('Author'),
            'template' => $theme->get('Template'),
            'stylesheet' => $theme->get('Stylesheet'),
            'is_child' => $theme->parent() ? true : false,
            'parent_theme' => $theme->parent() ? $theme->parent()->get('Name') : null
        );
    }
    
    /**
     * Detect page template
     */
    public function detect_page_template($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        $template = get_page_template_slug($post_id);
        
        if ($template) {
            return $template;
        }
        
        // Fallback to default template detection
        return 'default';
    }
    
    /**
     * Get layout information
     */
    public function get_layout_info($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        return array(
            'template' => $this->detect_page_template($post_id),
            'theme' => $this->get_theme_info(),
            'has_sidebar' => is_active_sidebar('main-sidebar'),
            'post_type' => get_post_type($post_id),
            'page_builder' => $this->detect_page_builder($post_id)
        );
    }
    
    /**
     * Detect page builder in use
     */
    private function detect_page_builder($post_id) {
        // Check for common page builders
        $content = get_post_field('post_content', $post_id);
        
        if (strpos($content, 'wp:block') !== false) {
            return 'gutenberg';
        }
        
        if (class_exists('Elementor\Plugin')) {
            if (get_post_meta($post_id, '_elementor_edit_mode', true)) {
                return 'elementor';
            }
        }
        
        if (class_exists('FLBuilderLoader')) {
            if (get_post_meta($post_id, '_fl_builder_enabled', true)) {
                return 'beaver-builder';
            }
        }
        
        if (defined('DIVI_VERSION')) {
            if (get_post_meta($post_id, '_et_pb_use_builder', true) === 'on') {
                return 'divi';
            }
        }
        
        return 'classic';
    }
    
    /**
     * Render admin page for TALI
     */
    public function render_admin_page() {
        ?>
        <div class="wrap siloq-admin-wrap">
            <div class="siloq-header">
                <h1><?php _e('Theme Intelligence', 'siloq-connector'); ?></h1>
                <p class="siloq-tagline"><?php _e('Analyze your theme structure and optimize content placement', 'siloq-connector'); ?></p>
            </div>
            
            <div class="siloq-card">
                <h2><?php _e('Theme Analysis', 'siloq-connector'); ?></h2>
                
                <?php
                $theme_info = $this->get_theme_info();
                $layout_info = $this->get_layout_info();
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Theme Name', 'siloq-connector'); ?></th>
                        <td><?php echo esc_html($theme_info['name']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Theme Version', 'siloq-connector'); ?></th>
                        <td><?php echo esc_html($theme_info['version']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Page Builder', 'siloq-connector'); ?></th>
                        <td><?php echo esc_html($layout_info['page_builder']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Has Sidebar', 'siloq-connector'); ?></th>
                        <td><?php echo $layout_info['has_sidebar'] ? __('Yes', 'siloq-connector') : __('No', 'siloq-connector'); ?></td>
                    </tr>
                </table>
                
                <p class="description">
                    <?php _e('TALI (Theme-Aware Layout Intelligence) analyzes your WordPress theme to optimize content placement and ensure the best user experience.', 'siloq-connector'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
