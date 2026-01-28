<?php
/**
 * Siloq Admin Interface
 * Handles admin pages and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Admin {
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        // Handle form submission
        if (isset($_POST['siloq_save_settings']) && check_admin_referer('siloq_settings_nonce')) {
            self::save_settings();
        }
        
        // Get current settings
        $api_url = get_option('siloq_api_url', '');
        $api_key = get_option('siloq_api_key', '');
        $auto_sync = get_option('siloq_auto_sync', 'no');
        $signup_url = get_option('siloq_signup_url', '');
        
        ?>
        <div class="wrap">
            <h1><?php _e('Siloq Settings', 'siloq-connector'); ?></h1>
            
            <div class="siloq-settings-container">
                <form method="post" action="">
                    <?php wp_nonce_field('siloq_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="siloq_api_url">
                                    <?php _e('API URL', 'siloq-connector'); ?>
                                    <span class="required">*</span>
                                </label>
                            </th>
                            <td>
                                <input 
                                    type="url" 
                                    id="siloq_api_url" 
                                    name="siloq_api_url" 
                                    value="<?php echo esc_attr($api_url); ?>" 
                                    class="regular-text"
                                    placeholder="https://api.siloq.com/api/v1"
                                    required
                                />
                                <p class="description">
                                    <?php _e('The base URL of your Siloq API endpoint (e.g., http://your-server-ip:3000/api/v1)', 'siloq-connector'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="siloq_api_key">
                                    <?php _e('API Key', 'siloq-connector'); ?>
                                    <span class="required">*</span>
                                </label>
                            </th>
                            <td>
                                <input 
                                    type="password" 
                                    id="siloq_api_key" 
                                    name="siloq_api_key" 
                                    value="<?php echo esc_attr($api_key); ?>" 
                                    class="regular-text"
                                    placeholder="sk_..."
                                    required
                                />
                                <p class="description">
                                    <?php _e('Your Siloq API authentication key', 'siloq-connector'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <?php _e('Auto-Sync', 'siloq-connector'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="siloq_auto_sync"
                                            value="yes"
                                            <?php checked($auto_sync, 'yes'); ?>
                                        />
                                        <?php _e('Automatically sync pages when published or updated', 'siloq-connector'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="siloq_signup_url">
                                    <?php _e('Lead Gen Signup URL', 'siloq-connector'); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                    type="url"
                                    id="siloq_signup_url"
                                    name="siloq_signup_url"
                                    value="<?php echo esc_attr($signup_url); ?>"
                                    class="regular-text"
                                    placeholder="https://app.siloq.io/signup?plan=blueprint"
                                />
                                <p class="description">
                                    <?php _e('The URL where users are redirected after viewing scan results. Leave empty to use the default Siloq signup URL. Use shortcode: [siloq_scanner]', 'siloq-connector'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="siloq_save_settings" class="button button-primary">
                            <?php _e('Save Settings', 'siloq-connector'); ?>
                        </button>
                        
                        <button type="button" id="siloq-test-connection" class="button button-secondary">
                            <?php _e('Test Connection', 'siloq-connector'); ?>
                        </button>
                        
                        <span id="siloq-connection-status" class="siloq-status-message"></span>
                    </p>
                </form>
                
                <hr>
                
                <h2><?php _e('Bulk Actions', 'siloq-connector'); ?></h2>
                
                <p>
                    <button type="button" id="siloq-sync-all-pages" class="button button-secondary">
                        <?php _e('Sync All Pages', 'siloq-connector'); ?>
                    </button>
                    <span class="description">
                        <?php _e('Sync all published pages to Siloq. This may take a few minutes.', 'siloq-connector'); ?>
                    </span>
                </p>
                
                <div id="siloq-sync-progress" class="siloq-sync-progress" style="display:none;">
                    <p><strong><?php _e('Syncing pages...', 'siloq-connector'); ?></strong></p>
                    <div class="siloq-progress-bar">
                        <div class="siloq-progress-fill" style="width: 0%"></div>
                    </div>
                    <p class="siloq-progress-text">0 / 0</p>
                </div>
                
                <div id="siloq-sync-results" class="siloq-sync-results" style="display:none;"></div>
                
                <hr>
                
                <h2><?php _e('Documentation', 'siloq-connector'); ?></h2>
                
                <p>
                    <?php _e('For more information about setting up and using the Siloq Connector plugin, please visit:', 'siloq-connector'); ?>
                </p>
                <ul>
                    <li><a href="https://github.com/Siloq-seo/siloq-wordpress-plugin" target="_blank"><?php _e('Plugin Documentation', 'siloq-connector'); ?></a></li>
                    <li><a href="https://siloq.com/docs" target="_blank"><?php _e('Siloq Platform Documentation', 'siloq-connector'); ?></a></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $api_url = isset($_POST['siloq_api_url']) ? sanitize_text_field($_POST['siloq_api_url']) : '';
        $api_key = isset($_POST['siloq_api_key']) ? sanitize_text_field($_POST['siloq_api_key']) : '';
        $auto_sync = isset($_POST['siloq_auto_sync']) ? 'yes' : 'no';
        $signup_url = isset($_POST['siloq_signup_url']) ? esc_url_raw($_POST['siloq_signup_url']) : '';
        
        // Validate
        $errors = array();
        
        if (empty($api_url)) {
            $errors[] = __('API URL is required', 'siloq-connector');
        } elseif (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            $errors[] = __('API URL is not valid', 'siloq-connector');
        }
        
        if (empty($api_key)) {
            $errors[] = __('API Key is required', 'siloq-connector');
        }
        
        if (!empty($errors)) {
            add_settings_error(
                'siloq_settings',
                'siloq_validation_error',
                implode('<br>', $errors),
                'error'
            );
            return;
        }
        
        // Save
        $old_api_url = get_option('siloq_api_url');
        $old_api_key = get_option('siloq_api_key');
        
        update_option('siloq_api_url', $api_url);
        update_option('siloq_api_key', $api_key);
        update_option('siloq_auto_sync', $auto_sync);
        update_option('siloq_signup_url', $signup_url);
        
        // If API credentials changed, clear cached sync statuses (optional)
        if ($old_api_url !== $api_url || $old_api_key !== $api_key) {
            // Could optionally clear all sync statuses here if needed
            // This is a design decision - you may want to keep existing sync data
        }
        
        add_settings_error(
            'siloq_settings',
            'siloq_settings_saved',
            __('Settings saved successfully!', 'siloq-connector'),
            'success'
        );
    }
    
    /**
     * Render sync status page
     */
    public static function render_sync_status_page() {
        $sync_engine = new Siloq_Sync_Engine();
        $pages_status = $sync_engine->get_all_sync_status();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Sync Status', 'siloq-connector'); ?></h1>
            
            <div class="siloq-sync-status-container">
                <p>
                    <button type="button" id="siloq-refresh-status" class="button button-secondary">
                        <?php _e('Refresh', 'siloq-connector'); ?>
                    </button>
                    
                    <?php
                    $pages_needing_resync = $sync_engine->get_pages_needing_resync();
                    if (!empty($pages_needing_resync)) {
                        ?>
                        <button type="button" id="siloq-sync-outdated" class="button button-secondary">
                            <?php printf(__('Sync %d Outdated Pages', 'siloq-connector'), count($pages_needing_resync)); ?>
                        </button>
                        <?php
                    }
                    ?>
                </p>
                
                <?php if (empty($pages_status)): ?>
                    <p><?php _e('No pages found.', 'siloq-connector'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Page Title', 'siloq-connector'); ?></th>
                                <th><?php _e('Status', 'siloq-connector'); ?></th>
                                <th><?php _e('Last Synced', 'siloq-connector'); ?></th>
                                <th><?php _e('Schema', 'siloq-connector'); ?></th>
                                <th><?php _e('Actions', 'siloq-connector'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pages_status as $page): ?>
                                <?php
                                $needs_resync = $sync_engine->needs_resync($page['id']);
                                $status_class = '';
                                $status_text = '';
                                
                                switch ($page['sync_status']) {
                                    case 'synced':
                                        $status_class = $needs_resync ? 'warning' : 'success';
                                        $status_text = $needs_resync ? __('Needs Re-sync', 'siloq-connector') : __('Synced', 'siloq-connector');
                                        break;
                                    case 'error':
                                        $status_class = 'error';
                                        $status_text = __('Error', 'siloq-connector');
                                        break;
                                    default:
                                        $status_class = 'not-synced';
                                        $status_text = __('Not Synced', 'siloq-connector');
                                }
                                ?>
                                <tr data-page-id="<?php echo esc_attr($page['id']); ?>">
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url($page['edit_url']); ?>">
                                                <?php echo esc_html($page['title']); ?>
                                            </a>
                                        </strong>
                                        <br>
                                        <small>
                                            <a href="<?php echo esc_url($page['url']); ?>" target="_blank">
                                                <?php _e('View', 'siloq-connector'); ?>
                                            </a>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="siloq-status-badge siloq-status-<?php echo esc_attr($status_class); ?>">
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo esc_html($page['last_synced']); ?>
                                    </td>
                                    <td>
                                        <?php if ($page['has_schema']): ?>
                                            <span class="dashicons dashicons-yes-alt" style="color: green;" title="<?php _e('Schema markup present', 'siloq-connector'); ?>"></span>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-minus" style="color: #999;" title="<?php _e('No schema markup', 'siloq-connector'); ?>"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button 
                                            type="button" 
                                            class="button button-small siloq-sync-single" 
                                            data-page-id="<?php echo esc_attr($page['id']); ?>"
                                        >
                                            <?php _e('Sync Now', 'siloq-connector'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render content import page
     */
    public static function render_content_import_page() {
        $import_handler = new Siloq_Content_Import();
        
        // Get all pages with available jobs
        $pages = get_posts(array(
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft'),
            'meta_query' => array(
                array(
                    'key' => '_siloq_content_ready',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Content Import', 'siloq-connector'); ?></h1>
            
            <div class="siloq-content-import-container">
                <p class="description">
                    <?php _e('AI-generated content from Siloq is ready to be imported. Review and import content for your pages below.', 'siloq-connector'); ?>
                </p>
                
                <?php if (empty($pages)): ?>
                    <div class="notice notice-info">
                        <p><?php _e('No AI-generated content available yet. Generate content for your pages first.', 'siloq-connector'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Page Title', 'siloq-connector'); ?></th>
                                <th><?php _e('Content Ready', 'siloq-connector'); ?></th>
                                <th><?php _e('Actions', 'siloq-connector'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pages as $page): ?>
                                <?php
                                $jobs = $import_handler->get_available_jobs($page->ID);
                                $ready_at = get_post_meta($page->ID, '_siloq_content_ready_at', true);
                                $has_backup = !empty(get_post_meta($page->ID, '_siloq_backup_content', true));
                                ?>
                                <tr data-page-id="<?php echo esc_attr($page->ID); ?>">
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url(get_edit_post_link($page->ID)); ?>">
                                                <?php echo esc_html($page->post_title); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($ready_at) {
                                            echo esc_html(human_time_diff(strtotime($ready_at), current_time('timestamp'))) . ' ' . __('ago', 'siloq-connector');
                                        } else {
                                            _e('Recently', 'siloq-connector');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($jobs)): ?>
                                            <?php foreach ($jobs as $job): ?>
                                                <button 
                                                    type="button" 
                                                    class="button button-primary siloq-import-content" 
                                                    data-page-id="<?php echo esc_attr($page->ID); ?>"
                                                    data-job-id="<?php echo esc_attr($job['job_id']); ?>"
                                                    data-action="create_draft"
                                                >
                                                    <?php _e('Import as Draft', 'siloq-connector'); ?>
                                                </button>
                                                
                                                <button 
                                                    type="button" 
                                                    class="button button-secondary siloq-import-content" 
                                                    data-page-id="<?php echo esc_attr($page->ID); ?>"
                                                    data-job-id="<?php echo esc_attr($job['job_id']); ?>"
                                                    data-action="replace"
                                                >
                                                    <?php _e('Replace Content', 'siloq-connector'); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($has_backup): ?>
                                            <button 
                                                type="button" 
                                                class="button button-link-delete siloq-restore-backup" 
                                                data-page-id="<?php echo esc_attr($page->ID); ?>"
                                            >
                                                <?php _e('Restore Backup', 'siloq-connector'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <hr>
                
                <h2><?php _e('Generate New Content', 'siloq-connector'); ?></h2>
                
                <p class="description">
                    <?php _e('Select a page to generate AI-powered content using Siloq.', 'siloq-connector'); ?>
                </p>
                
                <?php
                // Get all published pages
                $all_pages = get_posts(array(
                    'post_type' => 'page',
                    'posts_per_page' => -1,
                    'post_status' => 'publish'
                ));
                ?>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('Select Page', 'siloq-connector'); ?></th>
                        <td>
                            <select id="siloq-generate-page-select" class="regular-text">
                                <option value=""><?php _e('-- Select a page --', 'siloq-connector'); ?></option>
                                <?php foreach ($all_pages as $p): ?>
                                    <option value="<?php echo esc_attr($p->ID); ?>">
                                        <?php echo esc_html($p->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button type="button" id="siloq-generate-content" class="button button-primary">
                                <?php _e('Generate Content', 'siloq-connector'); ?>
                            </button>
                            
                            <p class="description">
                                <?php _e('This will create an AI content generation job. You will be notified when the content is ready.', 'siloq-connector'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div id="siloq-generation-status" style="display:none;"></div>
                
                <hr>
                
                <h2><?php _e('Webhook Configuration', 'siloq-connector'); ?></h2>
                
                <p>
                    <?php _e('Configure this webhook URL in your Siloq backend to receive real-time updates:', 'siloq-connector'); ?>
                </p>
                
                <code style="display: block; padding: 10px; background: #f0f0f1; margin: 10px 0;">
                    <?php echo esc_html(Siloq_Webhook_Handler::get_webhook_url()); ?>
                </code>
                
                <p class="description">
                    <?php _e('The webhook allows Siloq to notify WordPress when content is generated, schema is updated, or other events occur.', 'siloq-connector'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
