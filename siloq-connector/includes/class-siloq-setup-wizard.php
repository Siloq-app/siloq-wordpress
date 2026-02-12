<?php
/**
 * Siloq Setup Wizard
 *
 * Guides users through business entity configuration and silo planning
 */

if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Setup_Wizard {

    private $api_client;
    private $steps = array(
        'welcome' => 'Welcome',
        'business' => 'Business Information',
        'entity' => 'Entity Configuration',
        'products' => 'Products & Services',
        'location' => 'Location & Service Area',
        'silo_strategy' => 'Silo Strategy',
        'complete' => 'Complete'
    );

    public function __construct() {
        $this->api_client = new Siloq_API_Client();

        add_action('admin_menu', array($this, 'add_wizard_page'), 5);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_wizard_scripts'));
        add_action('wp_ajax_siloq_save_wizard_step', array($this, 'ajax_save_wizard_step'));
        add_action('wp_ajax_siloq_complete_wizard', array($this, 'ajax_complete_wizard'));
    }

    /**
     * Enqueue wizard scripts
     */
    public function enqueue_wizard_scripts($hook) {
        if (strpos($hook, 'siloq-setup-wizard') === false) {
            return;
        }

        // Ensure jQuery is loaded
        wp_enqueue_script('jquery');

        // Add ajaxurl to page
        wp_add_inline_script('jquery', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";');
    }

    /**
     * Add wizard page to admin menu
     */
    public function add_wizard_page() {
        add_submenu_page(
            'siloq-settings',
            __('Setup Wizard', 'siloq-connector'),
            __('Setup Wizard', 'siloq-connector'),
            'manage_options',
            'siloq-setup-wizard',
            array($this, 'render_wizard_page')
        );
    }

    /**
     * Check if wizard has been completed
     */
    public static function is_wizard_completed() {
        return get_option('siloq_wizard_completed', false);
    }

    /**
     * Render wizard page
     */
    public function render_wizard_page() {
        $current_step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : 'welcome';
        $wizard_data = get_option('siloq_wizard_data', array());

        // Prevent browser caching
        header('Cache-Control: no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');

        ?>
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
        <meta http-equiv="Pragma" content="no-cache">
        <meta http-equiv="Expires" content="0">
        <div class="wrap siloq-setup-wizard">
            <h1><?php _e('Siloq Setup Wizard', 'siloq-connector'); ?></h1>

            <div class="siloq-wizard-container">
                <!-- Progress Bar -->
                <div class="siloq-wizard-progress">
                    <?php $this->render_progress_bar($current_step); ?>
                </div>

                <!-- Wizard Content -->
                <div class="siloq-wizard-content">
                    <?php $this->render_step($current_step, $wizard_data); ?>
                </div>
            </div>
        </div>

        <style>
            .siloq-setup-wizard {
                max-width: 1200px;
                margin: 20px auto;
            }

            .siloq-wizard-container {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 30px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .siloq-wizard-progress {
                margin-bottom: 40px;
            }

            .siloq-progress-steps {
                display: flex;
                justify-content: space-between;
                list-style: none;
                margin: 0;
                padding: 0;
                position: relative;
            }

            .siloq-progress-steps::before {
                content: '';
                position: absolute;
                top: 20px;
                left: 0;
                right: 0;
                height: 2px;
                background: #e0e0e0;
                z-index: 0;
            }

            .siloq-progress-step {
                flex: 1;
                text-align: center;
                position: relative;
                z-index: 1;
            }

            .siloq-step-circle {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #fff;
                border: 2px solid #e0e0e0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                color: #999;
                margin-bottom: 8px;
            }

            .siloq-progress-step.active .siloq-step-circle {
                background: #2271b1;
                border-color: #2271b1;
                color: #fff;
            }

            .siloq-progress-step.completed .siloq-step-circle {
                background: #4caf50;
                border-color: #4caf50;
                color: #fff;
            }

            .siloq-step-label {
                font-size: 12px;
                color: #666;
            }

            .siloq-progress-step.active .siloq-step-label {
                color: #2271b1;
                font-weight: 600;
            }

            .siloq-wizard-step {
                min-height: 400px;
            }

            .siloq-wizard-buttons {
                display: flex;
                justify-content: space-between;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #e0e0e0;
            }

            .siloq-form-group {
                margin-bottom: 20px;
            }

            .siloq-form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #333;
            }

            .siloq-form-group input[type="text"],
            .siloq-form-group input[type="email"],
            .siloq-form-group input[type="url"],
            .siloq-form-group select,
            .siloq-form-group textarea {
                width: 100%;
                max-width: 600px;
                padding: 10px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
            }

            .siloq-form-group small {
                display: block;
                margin-top: 4px;
                color: #666;
            }

            .siloq-entity-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
                margin-top: 20px;
            }

            .siloq-entity-item {
                padding: 15px;
                border: 2px solid #e0e0e0;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.2s;
            }

            .siloq-entity-item:hover {
                border-color: #2271b1;
                box-shadow: 0 2px 8px rgba(34,113,177,0.1);
            }

            .siloq-entity-item.selected {
                border-color: #2271b1;
                background: #f0f6fc;
            }

            .siloq-welcome-content {
                text-align: center;
                padding: 40px 20px;
            }

            .siloq-welcome-icon {
                font-size: 72px;
                margin-bottom: 20px;
            }

            .siloq-welcome-content h2 {
                font-size: 28px;
                margin-bottom: 15px;
            }

            .siloq-welcome-content p {
                font-size: 16px;
                color: #666;
                max-width: 600px;
                margin: 0 auto 30px;
            }

            .siloq-features-list {
                text-align: left;
                max-width: 500px;
                margin: 30px auto;
            }

            .siloq-features-list li {
                margin-bottom: 15px;
                font-size: 15px;
                display: flex;
                align-items: center;
            }

            .siloq-features-list li::before {
                content: '✓';
                display: inline-block;
                width: 24px;
                height: 24px;
                background: #4caf50;
                color: white;
                border-radius: 50%;
                text-align: center;
                line-height: 24px;
                margin-right: 12px;
                flex-shrink: 0;
            }

            .siloq-complete-content {
                text-align: center;
                padding: 40px 20px;
            }

            .siloq-complete-icon {
                width: 80px;
                height: 80px;
                background: #4caf50;
                color: white;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 48px;
                margin-bottom: 20px;
            }
        </style>
        <?php
    }

    /**
     * Render progress bar
     */
    private function render_progress_bar($current_step) {
        $step_keys = array_keys($this->steps);
        $current_index = array_search($current_step, $step_keys);

        echo '<ul class="siloq-progress-steps">';

        $display_steps = array('welcome', 'business', 'entity', 'silo_strategy', 'complete');

        foreach ($display_steps as $index => $step_key) {
            $step_index = array_search($step_key, $step_keys);
            $is_active = $step_key === $current_step;
            $is_completed = $step_index < $current_index;

            $class = 'siloq-progress-step';
            if ($is_active) $class .= ' active';
            if ($is_completed) $class .= ' completed';

            echo '<li class="' . esc_attr($class) . '">';
            echo '<div class="siloq-step-circle">' . ($index + 1) . '</div>';
            echo '<div class="siloq-step-label">' . esc_html($this->steps[$step_key]) . '</div>';
            echo '</li>';
        }

        echo '</ul>';
    }

    /**
     * Render current step
     */
    private function render_step($step, $data) {
        $method = 'render_' . $step . '_step';

        if (method_exists($this, $method)) {
            $this->$method($data);
        } else {
            $this->render_welcome_step($data);
        }
    }

    /**
     * Render welcome step
     */
    private function render_welcome_step($data) {
        ?>
        <div class="siloq-wizard-step siloq-welcome-content">
            <div class="siloq-welcome-icon">🚀</div>
            <h2><?php _e('Welcome to Siloq!', 'siloq-connector'); ?></h2>
            <p><?php _e('Let\'s set up your site for AI-powered SEO content silos. This wizard will help us understand your business so we can create the perfect content strategy.', 'siloq-connector'); ?></p>

            <ul class="siloq-features-list">
                <li><?php _e('Understand your business entity and offerings', 'siloq-connector'); ?></li>
                <li><?php _e('Plan content silos based on your products/services', 'siloq-connector'); ?></li>
                <li><?php _e('Generate SEO-optimized content automatically', 'siloq-connector'); ?></li>
                <li><?php _e('Capture leads with our scanner widget', 'siloq-connector'); ?></li>
                <li><?php _e('Track and manage leads from your dashboard', 'siloq-connector'); ?></li>
            </ul>

            <div class="siloq-wizard-buttons">
                <div></div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=siloq-setup-wizard&step=business')); ?>" class="button button-primary button-large">
                    <?php _e('Get Started', 'siloq-connector'); ?> →
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render business information step
     */
    private function render_business_step($data) {
        ?>
        <div class="siloq-wizard-step">
            <h2><?php _e('Tell us about your business', 'siloq-connector'); ?></h2>
            <p><?php _e('This information helps us understand your business and create relevant content.', 'siloq-connector'); ?></p>

            <form id="siloq-business-form" class="siloq-wizard-form">
                <div class="siloq-form-group">
                    <label for="business_name"><?php _e('Business Name', 'siloq-connector'); ?> *</label>
                    <input type="text" id="business_name" name="business_name" value="<?php echo esc_attr($data['business_name'] ?? get_bloginfo('name')); ?>" required>
                    <small><?php _e('Your official business or brand name', 'siloq-connector'); ?></small>
                </div>

                <div class="siloq-form-group">
                    <label for="business_type"><?php _e('Business Type', 'siloq-connector'); ?> *</label>
                    <select id="business_type" name="business_type" required>
                        <option value=""><?php _e('Select business type...', 'siloq-connector'); ?></option>
                        <option value="local_business" <?php selected($data['business_type'] ?? '', 'local_business'); ?>><?php _e('Local Business', 'siloq-connector'); ?></option>
                        <option value="ecommerce" <?php selected($data['business_type'] ?? '', 'ecommerce'); ?>><?php _e('E-commerce', 'siloq-connector'); ?></option>
                        <option value="saas" <?php selected($data['business_type'] ?? '', 'saas'); ?>><?php _e('SaaS / Software', 'siloq-connector'); ?></option>
                        <option value="professional_services" <?php selected($data['business_type'] ?? '', 'professional_services'); ?>><?php _e('Professional Services', 'siloq-connector'); ?></option>
                        <option value="media" <?php selected($data['business_type'] ?? '', 'media'); ?>><?php _e('Media / Publishing', 'siloq-connector'); ?></option>
                        <option value="other" <?php selected($data['business_type'] ?? '', 'other'); ?>><?php _e('Other', 'siloq-connector'); ?></option>
                    </select>
                </div>

                <div class="siloq-form-group">
                    <label for="industry"><?php _e('Industry', 'siloq-connector'); ?> *</label>
                    <input type="text" id="industry" name="industry" value="<?php echo esc_attr($data['industry'] ?? ''); ?>" required>
                    <small><?php _e('e.g., Real Estate, Healthcare, Technology, Automotive', 'siloq-connector'); ?></small>
                </div>

                <div class="siloq-form-group">
                    <label for="business_description"><?php _e('Business Description', 'siloq-connector'); ?></label>
                    <textarea id="business_description" name="business_description" rows="4"><?php echo esc_textarea($data['business_description'] ?? get_bloginfo('description')); ?></textarea>
                    <small><?php _e('A brief description of what your business does', 'siloq-connector'); ?></small>
                </div>

                <div class="siloq-form-group">
                    <label for="website_url"><?php _e('Website URL', 'siloq-connector'); ?></label>
                    <input type="url" id="website_url" name="website_url" value="<?php echo esc_url($data['website_url'] ?? home_url()); ?>">
                </div>

                <div class="siloq-wizard-buttons">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=siloq-setup-wizard&step=welcome')); ?>" class="button button-large">
                        ← <?php _e('Back', 'siloq-connector'); ?>
                    </a>
                    <button type="submit" class="button button-primary button-large">
                        <?php _e('Continue', 'siloq-connector'); ?> →
                    </button>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#siloq-business-form').on('submit', function(e) {
                e.preventDefault();

                var formData = $(this).serializeArray();
                var data = {
                    action: 'siloq_save_wizard_step',
                    step: 'business',
                    nonce: '<?php echo wp_create_nonce('siloq_wizard'); ?>',
                    data: {}
                };

                $.each(formData, function(i, field) {
                    data.data[field.name] = field.value;
                });

                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        window.location.href = '<?php echo esc_url(admin_url('admin.php?page=siloq-setup-wizard&step=entity')); ?>';
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }).fail(function(xhr, status, error) {
                    alert('Request failed: ' + error);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render entity configuration step
     */
    private function render_entity_step($data) {
        ?>
        <div class="siloq-wizard-step">
            <h2><?php _e('What do you offer?', 'siloq-connector'); ?></h2>
            <p><?php _e('Select the types of entities your business works with. This helps us structure your content silos.', 'siloq-connector'); ?></p>

            <form id="siloq-entity-form" class="siloq-wizard-form">
                <div class="siloq-entity-grid">
                    <?php
                    $entity_types = array(
                        'products' => array(
                            'label' => __('Products', 'siloq-connector'),
                            'description' => __('Physical or digital products you sell', 'siloq-connector'),
                            'icon' => '📦'
                        ),
                        'services' => array(
                            'label' => __('Services', 'siloq-connector'),
                            'description' => __('Services you provide', 'siloq-connector'),
                            'icon' => '🛠️'
                        ),
                        'locations' => array(
                            'label' => __('Locations', 'siloq-connector'),
                            'description' => __('Physical locations or service areas', 'siloq-connector'),
                            'icon' => '📍'
                        ),
                        'topics' => array(
                            'label' => __('Topics', 'siloq-connector'),
                            'description' => __('Content topics or categories', 'siloq-connector'),
                            'icon' => '📚'
                        )
                    );

                    $selected_entities = $data['entity_types'] ?? array();

                    foreach ($entity_types as $key => $entity) {
                        $is_selected = in_array($key, $selected_entities);
                        ?>
                        <div class="siloq-entity-item <?php echo $is_selected ? 'selected' : ''; ?>" data-entity="<?php echo esc_attr($key); ?>">
                            <div style="font-size: 32px; margin-bottom: 10px;"><?php echo $entity['icon']; ?></div>
                            <h3 style="margin: 0 0 8px 0; font-size: 16px;"><?php echo $entity['label']; ?></h3>
                            <p style="margin: 0; color: #666; font-size: 13px;"><?php echo $entity['description']; ?></p>
                            <input type="checkbox" name="entity_types[]" value="<?php echo esc_attr($key); ?>" <?php checked($is_selected); ?> style="display: none;">
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <div class="siloq-wizard-buttons">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=siloq-setup-wizard&step=business')); ?>" class="button button-large">
                        ← <?php _e('Back', 'siloq-connector'); ?>
                    </a>
                    <button type="submit" class="button button-primary button-large">
                        <?php _e('Continue', 'siloq-connector'); ?> →
                    </button>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.siloq-entity-item').on('click', function() {
                $(this).toggleClass('selected');
                var checkbox = $(this).find('input[type="checkbox"]');
                checkbox.prop('checked', !checkbox.prop('checked'));
            });

            $('#siloq-entity-form').on('submit', function(e) {
                e.preventDefault();

                var formData = $(this).serializeArray();
                var data = {
                    action: 'siloq_save_wizard_step',
                    step: 'entity',
                    nonce: '<?php echo wp_create_nonce('siloq_wizard'); ?>',
                    data: {
                        entity_types: []
                    }
                };

                $.each(formData, function(i, field) {
                    if (field.name === 'entity_types[]') {
                        data.data.entity_types.push(field.value);
                    }
                });

                if (data.data.entity_types.length === 0) {
                    alert('<?php _e('Please select at least one entity type.', 'siloq-connector'); ?>');
                    return;
                }

                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        window.location.href = '<?php echo esc_url(admin_url('admin.php?page=siloq-setup-wizard&step=silo_strategy')); ?>';
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }).fail(function(xhr, status, error) {
                    alert('Request failed: ' + error);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render silo strategy step
     */
    private function render_silo_strategy_step($data) {
        ?>
        <div class="siloq-wizard-step">
            <h2><?php _e('Silo Strategy', 'siloq-connector'); ?></h2>
            <p><?php _e('Choose how you want to structure your content silos.', 'siloq-connector'); ?></p>

            <form id="siloq-silo-form" class="siloq-wizard-form">
                <div class="siloq-form-group">
                    <label><?php _e('Silo Approach', 'siloq-connector'); ?> *</label>

                    <div class="siloq-entity-grid">
                        <div class="siloq-entity-item <?php echo ($data['silo_approach'] ?? '') === 'automatic' ? 'selected' : ''; ?>" data-approach="automatic">
                            <div style="font-size: 32px; margin-bottom: 10px;">🤖</div>
                            <h3 style="margin: 0 0 8px 0; font-size: 16px;"><?php _e('Automatic', 'siloq-connector'); ?></h3>
                            <p style="margin: 0; color: #666; font-size: 13px;"><?php _e('Let Siloq AI analyze your site and create optimal silos', 'siloq-connector'); ?></p>
                            <input type="radio" name="silo_approach" value="automatic" <?php checked($data['silo_approach'] ?? '', 'automatic'); ?> style="display: none;">
                        </div>

                        <div class="siloq-entity-item <?php echo ($data['silo_approach'] ?? '') === 'guided' ? 'selected' : ''; ?>" data-approach="guided">
                            <div style="font-size: 32px; margin-bottom: 10px;">🎯</div>
                            <h3 style="margin: 0 0 8px 0; font-size: 16px;"><?php _e('Guided', 'siloq-connector'); ?></h3>
                            <p style="margin: 0; color: #666; font-size: 13px;"><?php _e('Work with AI to plan and refine your silo structure', 'siloq-connector'); ?></p>
                            <input type="radio" name="silo_approach" value="guided" <?php checked($data['silo_approach'] ?? '', 'guided'); ?> style="display: none;">
                        </div>

                        <div class="siloq-entity-item <?php echo ($data['silo_approach'] ?? '') === 'manual' ? 'selected' : ''; ?>" data-approach="manual">
                            <div style="font-size: 32px; margin-bottom: 10px;">✏️</div>
                            <h3 style="margin: 0 0 8px 0; font-size: 16px;"><?php _e('Manual', 'siloq-connector'); ?></h3>
                            <p style="margin: 0; color: #666; font-size: 13px;"><?php _e('Create and manage your silos manually', 'siloq-connector'); ?></p>
                            <input type="radio" name="silo_approach" value="manual" <?php checked($data['silo_approach'] ?? '', 'manual'); ?> style="display: none;">
                        </div>
                    </div>
                </div>

                <div class="siloq-form-group">
                    <label for="target_keywords"><?php _e('Target Keywords (Optional)', 'siloq-connector'); ?></label>
                    <textarea id="target_keywords" name="target_keywords" rows="4" placeholder="<?php _e('Enter keywords or topics you want to rank for, one per line', 'siloq-connector'); ?>"><?php echo esc_textarea($data['target_keywords'] ?? ''); ?></textarea>
                    <small><?php _e('These will help guide the content strategy', 'siloq-connector'); ?></small>
                </div>

                <div class="siloq-form-group">
                    <label>
                        <input type="checkbox" name="enable_lead_gen" value="1" <?php checked($data['enable_lead_gen'] ?? true); ?>>
                        <?php _e('Enable Lead Generation Scanner Widget', 'siloq-connector'); ?>
                    </label>
                    <small><?php _e('Capture leads by offering free SEO scans to website visitors', 'siloq-connector'); ?></small>
                </div>

                <div class="siloq-wizard-buttons">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=siloq-setup-wizard&step=entity')); ?>" class="button button-large">
                        ← <?php _e('Back', 'siloq-connector'); ?>
                    </a>
                    <button type="submit" class="button button-primary button-large">
                        <?php _e('Complete Setup', 'siloq-connector'); ?> →
                    </button>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.siloq-entity-item[data-approach]').on('click', function() {
                $('.siloq-entity-item[data-approach]').removeClass('selected');
                $(this).addClass('selected');
                var radio = $(this).find('input[type="radio"]');
                radio.prop('checked', true);
            });

            $('#siloq-silo-form').on('submit', function(e) {
                e.preventDefault();

                if (!$('input[name="silo_approach"]:checked').val()) {
                    alert('<?php _e('Please select a silo approach.', 'siloq-connector'); ?>');
                    return;
                }

                var formData = $(this).serializeArray();
                var data = {
                    action: 'siloq_complete_wizard',
                    nonce: '<?php echo wp_create_nonce('siloq_wizard'); ?>',
                    data: {}
                };

                $.each(formData, function(i, field) {
                    data.data[field.name] = field.value;
                });

                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        window.location.href = '<?php echo esc_url(admin_url('admin.php?page=siloq-setup-wizard&step=complete')); ?>';
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }).fail(function(xhr, status, error) {
                    alert('Request failed: ' + error);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render complete step
     */
    private function render_complete_step($data) {
        ?>
        <div class="siloq-wizard-step siloq-complete-content">
            <div class="siloq-complete-icon">✓</div>
            <h2><?php _e('Setup Complete!', 'siloq-connector'); ?></h2>
            <p><?php _e('Your Siloq configuration is ready. We\'re now analyzing your site and preparing your content strategy.', 'siloq-connector'); ?></p>

            <div style="background: #f0f6fc; border: 1px solid #2271b1; border-radius: 4px; padding: 20px; margin: 30px auto; max-width: 600px; text-align: left;">
                <h3 style="margin-top: 0;"><?php _e('What happens next?', 'siloq-connector'); ?></h3>
                <ul style="margin-bottom: 0;">
                    <li><?php _e('Your site data is being synced to Siloq', 'siloq-connector'); ?></li>
                    <li><?php _e('AI is analyzing your content and structure', 'siloq-connector'); ?></li>
                    <li><?php _e('Silos are being planned based on your configuration', 'siloq-connector'); ?></li>
                    <li><?php _e('You\'ll be notified when the analysis is complete', 'siloq-connector'); ?></li>
                </ul>
            </div>

            <div style="display: flex; gap: 15px; justify-content: center; margin-top: 30px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=siloq-dashboard')); ?>" class="button button-primary button-large">
                    <?php _e('Go to Dashboard', 'siloq-connector'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=siloq-settings')); ?>" class="button button-large">
                    <?php _e('View Settings', 'siloq-connector'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save wizard step
     */
    public function ajax_save_wizard_step() {
        check_ajax_referer('siloq_wizard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $step = sanitize_text_field($_POST['step']);
        $data = $_POST['data'];

        // Get existing wizard data
        $wizard_data = get_option('siloq_wizard_data', array());

        // Merge new data
        $wizard_data = array_merge($wizard_data, $data);

        // Save to database
        update_option('siloq_wizard_data', $wizard_data);

        wp_send_json_success(array('message' => 'Step saved'));
    }

    /**
     * AJAX: Complete wizard
     */
    public function ajax_complete_wizard() {
        check_ajax_referer('siloq_wizard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $data = $_POST['data'];

        // Get existing wizard data
        $wizard_data = get_option('siloq_wizard_data', array());

        // Merge final data
        $wizard_data = array_merge($wizard_data, $data);

        // Save to database
        update_option('siloq_wizard_data', $wizard_data);
        update_option('siloq_wizard_completed', true);
        update_option('siloq_wizard_completed_at', current_time('mysql'));

        // Send configuration to Siloq backend
        $this->send_configuration_to_backend($wizard_data);

        wp_send_json_success(array('message' => 'Wizard completed'));
    }

    /**
     * Send configuration to Siloq backend
     */
    private function send_configuration_to_backend($wizard_data) {
        // Create or update site in Siloq
        $response = $this->api_client->create_site(array(
            'name' => $wizard_data['business_name'],
            'domain' => home_url(),
            'site_type' => $wizard_data['business_type'] ?? null,
            'metadata' => array(
                'industry' => $wizard_data['industry'] ?? null,
                'description' => $wizard_data['business_description'] ?? null,
                'entity_types' => $wizard_data['entity_types'] ?? array(),
                'silo_approach' => $wizard_data['silo_approach'] ?? 'automatic',
                'target_keywords' => $wizard_data['target_keywords'] ?? '',
                'lead_gen_enabled' => !empty($wizard_data['enable_lead_gen']),
                'wizard_completed_at' => current_time('mysql')
            )
        ));

        if (!is_wp_error($response) && isset($response['id'])) {
            update_option('siloq_site_id', $response['id']);
        }
    }
}
