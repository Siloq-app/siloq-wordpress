<?php
/**
 * TALI Admin Settings Page
 * 
 * @package Siloq
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap siloq-tali-settings">
    <h1><?php esc_html_e('Theme Intelligence (TALI)', 'siloq'); ?></h1>
    
    <div class="siloq-tali-status-card">
        <h2><?php esc_html_e('Theme Status', 'siloq'); ?></h2>
        
        <?php
        $theme = wp_get_theme();
        $is_block_theme = wp_is_block_theme();
        ?>
        
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Active Theme', 'siloq'); ?></th>
                <td><strong><?php echo esc_html($theme->get('Name')); ?></strong> (v<?php echo esc_html($theme->get('Version')); ?>)</td>
            </tr>
            <tr>
                <th><?php esc_html_e('Theme Type', 'siloq'); ?></th>
                <td>
                    <?php if ($is_block_theme): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <?php esc_html_e('Block Theme (Full Site Editing)', 'siloq'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-info" style="color: orange;"></span>
                        <?php esc_html_e('Classic Theme', 'siloq'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Design Profile', 'siloq'); ?></th>
                <td>
                    <?php if (!empty($design_profile)): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <?php esc_html_e('Generated', 'siloq'); ?>
                        <small>(<?php echo esc_html($design_profile['generated_at'] ?? 'Unknown'); ?>)</small>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning" style="color: red;"></span>
                        <?php esc_html_e('Not generated - run fingerprint', 'siloq'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Capability Map', 'siloq'); ?></th>
                <td>
                    <?php if (!empty($capability_map)): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <?php esc_html_e('Generated', 'siloq'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning" style="color: red;"></span>
                        <?php esc_html_e('Not generated - run fingerprint', 'siloq'); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <p>
            <button type="button" class="button button-primary" id="siloq-rerun-fingerprint">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Re-Fingerprint Theme', 'siloq'); ?>
            </button>
        </p>
    </div>
    
    <?php if (!empty($design_profile)): ?>
    <div class="siloq-tali-design-profile">
        <h2><?php esc_html_e('Design Profile', 'siloq'); ?></h2>
        
        <div class="siloq-tali-tokens">
            <h3><?php esc_html_e('Colors', 'siloq'); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Token', 'siloq'); ?></th>
                        <th><?php esc_html_e('Value', 'siloq'); ?></th>
                        <th><?php esc_html_e('Preview', 'siloq'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($design_profile['tokens']['colors'] as $name => $value): ?>
                    <tr>
                        <td><code><?php echo esc_html($name); ?></code></td>
                        <td><code><?php echo esc_html($value); ?></code></td>
                        <td>
                            <div style="width: 30px; height: 30px; background: <?php echo esc_attr($value); ?>; border: 1px solid #ccc; border-radius: 3px;"></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h3><?php esc_html_e('Typography', 'siloq'); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Token', 'siloq'); ?></th>
                        <th><?php esc_html_e('Value', 'siloq'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($design_profile['tokens']['typography'] as $name => $value): ?>
                    <tr>
                        <td><code><?php echo esc_html($name); ?></code></td>
                        <td><code><?php echo esc_html($value); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h3><?php esc_html_e('Spacing', 'siloq'); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Token', 'siloq'); ?></th>
                        <th><?php esc_html_e('Value', 'siloq'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($design_profile['tokens']['spacing'] as $name => $value): ?>
                    <tr>
                        <td><code><?php echo esc_html($name); ?></code></td>
                        <td><code><?php echo esc_html($value); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h3><?php esc_html_e('Layout', 'siloq'); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Token', 'siloq'); ?></th>
                        <th><?php esc_html_e('Value', 'siloq'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($design_profile['tokens']['layout'] as $name => $value): ?>
                    <tr>
                        <td><code><?php echo esc_html($name); ?></code></td>
                        <td><code><?php echo esc_html($value); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <p>
            <strong><?php esc_html_e('Confidence Score:', 'siloq'); ?></strong>
            <?php 
            $confidence = $design_profile['confidence'] ?? 0;
            $confidence_pct = round($confidence * 100);
            $confidence_color = $confidence >= 0.9 ? 'green' : ($confidence >= 0.7 ? 'orange' : 'red');
            ?>
            <span style="color: <?php echo $confidence_color; ?>; font-weight: bold;">
                <?php echo $confidence_pct; ?>%
            </span>
        </p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($capability_map)): ?>
    <div class="siloq-tali-capability-map">
        <h2><?php esc_html_e('Component Capabilities', 'siloq'); ?></h2>
        
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('Component', 'siloq'); ?></th>
                    <th><?php esc_html_e('Supported', 'siloq'); ?></th>
                    <th><?php esc_html_e('Confidence', 'siloq'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($capability_map['supports'] as $component => $supported): ?>
                <?php if (is_bool($supported)): ?>
                <tr>
                    <td><code><?php echo esc_html($component); ?></code></td>
                    <td>
                        <?php if ($supported): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-no-alt" style="color: red;"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $conf = $capability_map['confidence'][$component] ?? 0;
                        $conf_pct = round($conf * 100);
                        $conf_color = $conf >= 0.9 ? 'green' : ($conf >= 0.7 ? 'orange' : 'red');
                        ?>
                        <span style="color: <?php echo $conf_color; ?>;">
                            <?php echo $conf_pct; ?>%
                        </span>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($history)): ?>
    <div class="siloq-tali-history">
        <h2><?php esc_html_e('Fingerprint History', 'siloq'); ?></h2>
        
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'siloq'); ?></th>
                    <th><?php esc_html_e('Theme', 'siloq'); ?></th>
                    <th><?php esc_html_e('TALI Version', 'siloq'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $entry): ?>
                <tr>
                    <td><?php echo esc_html($entry['timestamp']); ?></td>
                    <td><?php echo esc_html($entry['theme']); ?></td>
                    <td><?php echo esc_html($entry['tali_version']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('#siloq-rerun-fingerprint').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'siloq_rerun_fingerprint',
                nonce: '<?php echo wp_create_nonce('siloq_tali_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Theme fingerprint updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Request failed. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            }
        });
    });
});
</script>

<style>
.siloq-tali-settings .siloq-tali-status-card,
.siloq-tali-settings .siloq-tali-design-profile,
.siloq-tali-settings .siloq-tali-capability-map,
.siloq-tali-settings .siloq-tali-history {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.siloq-tali-settings .siloq-tali-tokens h3 {
    margin-top: 20px;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.siloq-tali-settings .widefat {
    margin-top: 10px;
}

.siloq-tali-settings .dashicons.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    100% { transform: rotate(360deg); }
}
</style>
