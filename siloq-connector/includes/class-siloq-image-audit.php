<?php
if (!defined('ABSPATH')) {
    exit;
}

class Siloq_Image_Audit {

    public static function run_audit(array $posts) {
        $business_name = get_option('siloq_business_name', get_bloginfo('name'));
        $primary_services = json_decode(get_option('siloq_primary_services', '[]'), true);
        if (!is_array($primary_services)) {
            $primary_services = array();
        }
        $results = array();

        foreach ($posts as $post) {
            $post_id = $post->ID;
            $title   = get_the_title($post_id);
            $content = $post->post_content;

            // Collect attached images
            $images = array();
            $attached = get_attached_media('image', $post_id);
            foreach ($attached as $att) {
                $images[] = array(
                    'id'       => $att->ID,
                    'url'      => wp_get_attachment_url($att->ID),
                    'filename' => basename(get_attached_file($att->ID)),
                    'alt'      => get_post_meta($att->ID, '_wp_attachment_image_alt', true),
                );
            }

            // Parse img src from post_content
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
                foreach ($matches[1] as $src) {
                    $att_id  = attachment_url_to_postid($src);
                    $fname   = basename(parse_url($src, PHP_URL_PATH));
                    $alt_val = $att_id ? get_post_meta($att_id, '_wp_attachment_image_alt', true) : '';
                    $images[] = array(
                        'id'       => $att_id,
                        'url'      => $src,
                        'filename' => $fname,
                        'alt'      => $alt_val,
                    );
                }
            }

            // Deduplicate by URL
            $seen = array();
            $unique = array();
            foreach ($images as $img) {
                if (!isset($seen[$img['url']])) {
                    $seen[$img['url']] = true;
                    $unique[] = $img;
                }
            }
            $images = $unique;

            // Classify status
            $status = 'good';
            if (empty($images)) {
                $status = 'no_images';
            } else {
                $has_stock   = false;
                $has_no_alt  = false;
                $optimized   = true;

                // Extract city/service keywords from title
                $title_lower = strtolower($title);

                foreach ($images as $img) {
                    if (self::is_stock_photo($img['filename'], $img['url'])) {
                        $has_stock = true;
                    }
                    if (empty($img['alt'])) {
                        $has_no_alt = true;
                    }
                    $fname_lower = strtolower($img['filename']);
                    $alt_lower   = strtolower($img['alt']);
                    $combined    = $fname_lower . ' ' . $alt_lower;
                    // Check if filename or alt contains any city/service keyword from title
                    $has_keyword = false;
                    $words = preg_split('/[\s,\-_|]+/', $title_lower);
                    foreach ($words as $w) {
                        if (strlen($w) > 2 && strpos($combined, $w) !== false) {
                            $has_keyword = true;
                            break;
                        }
                    }
                    if (!$has_keyword) {
                        $optimized = false;
                    }
                }

                if ($has_stock) {
                    $status = 'stock_photo';
                } elseif ($has_no_alt) {
                    $status = 'missing_alt';
                } elseif (!$optimized) {
                    $status = 'unoptimized';
                }
            }

            // Derive photo_type from title
            $photo_type = '';
            if (preg_match('/(.+),\s*[A-Z]{2}\b/i', $title, $city_match)) {
                $city = trim($city_match[1]);
                $photo_type = "Tradesperson working in {$city}";
            } elseif (preg_match('/\b(ev|charger)\b/i', $title)) {
                $photo_type = 'EV charger installation';
            } elseif (preg_match('/\bpanel\b/i', $title)) {
                $photo_type = 'Electrical panel work';
            } elseif (!empty($primary_services[0])) {
                $svc = is_array($primary_services[0]) ? $primary_services[0]['label'] ?? $primary_services[0]['name'] ?? $primary_services[0] : $primary_services[0];
                $photo_type = (string) $svc;
            }

            // Build slugs for recommendations
            $service_slug = sanitize_title(preg_replace('/,\s*[A-Z]{2}$/i', '', $title));
            $city_slug    = '';
            if (preg_match('/^(.+?)\s+([\w\s]+),\s*[A-Z]{2}/i', $title, $parts)) {
                $city_slug    = sanitize_title($parts[2]);
                $service_slug = sanitize_title($parts[1]);
            }

            $city_label    = $city_slug ? ucwords(str_replace('-', ' ', $city_slug)) : '';
            $service_label = ucwords(str_replace('-', ' ', $service_slug));

            $results[] = array(
                'post_id'              => $post_id,
                'title'                => $title,
                'status'               => $status,
                'images'               => $images,
                'needs'                => array(
                    'photo_type'            => $photo_type,
                    'recommended_filename'  => ($service_slug && $city_slug) ? "{$service_slug}-{$city_slug}.jpg" : "{$service_slug}.jpg",
                    'recommended_alt'       => trim("{$business_name} {$service_label} service" . ($city_label ? " in {$city_label}" : '')),
                ),
            );
        }

        update_option('siloq_image_audit_results', wp_json_encode($results));
        update_option('siloq_image_audit_date', current_time('timestamp'));

        return $results;
    }

    public static function is_stock_photo(string $filename, string $url): bool {
        $fname = strtolower($filename);
        $stock_markers = array(
            'istock', 'shutterstock', 'getty', 'adobestock', 'pexels',
            'unsplash', '123rf', 'dreamstime', 'depositphotos', 'bigstock', 'canstock',
        );
        foreach ($stock_markers as $marker) {
            if (strpos($fname, $marker) !== false) {
                return true;
            }
        }

        // 6+ digit number pattern
        if (preg_match('/\d{6,}/', $fname)) {
            return true;
        }

        // photo-1234 / DSC_1234 / IMG_1234 patterns
        if (preg_match('/^(photo-\d+|dsc[_-]\d+|img[_-]\d+)/i', $fname)) {
            return true;
        }

        // URL domain check
        $url_lower = strtolower($url);
        if (strpos($url_lower, 'unsplash.com') !== false
            || strpos($url_lower, 'pexels.com') !== false
            || strpos($url_lower, 'pixabay.com') !== false) {
            return true;
        }

        return false;
    }

    public static function ajax_get_image_audit() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $raw = get_option('siloq_image_audit_results', '');
        $items = $raw ? json_decode($raw, true) : array();
        if (!is_array($items)) {
            $items = array();
        }

        $counts = array(
            'no_images'   => 0,
            'stock_photo' => 0,
            'missing_alt' => 0,
            'unoptimized' => 0,
            'good'        => 0,
        );
        foreach ($items as $item) {
            $s = $item['status'] ?? 'good';
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
        }

        wp_send_json_success(array(
            'counts' => $counts,
            'items'  => $items,
        ));
    }

    public static function ajax_apply_image_seo() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $attachment_id   = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        $post_id         = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $recommended_alt = isset($_POST['recommended_alt']) ? sanitize_text_field(wp_unslash($_POST['recommended_alt'])) : '';

        if (!$attachment_id || !$recommended_alt) {
            wp_send_json_error(array('message' => 'Missing required fields'));
        }

        // Update alt text
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $recommended_alt);

        // Update attachment title
        wp_update_post(array(
            'ID'         => $attachment_id,
            'post_title' => $recommended_alt,
        ));

        // Mark post as good in stored audit
        if ($post_id) {
            $raw   = get_option('siloq_image_audit_results', '');
            $items = $raw ? json_decode($raw, true) : array();
            if (is_array($items)) {
                foreach ($items as &$item) {
                    if (intval($item['post_id']) === $post_id) {
                        $item['status'] = 'good';
                        break;
                    }
                }
                unset($item);
                update_option('siloq_image_audit_results', wp_json_encode($items));
            }
        }

        wp_send_json_success(array('message' => 'Image SEO applied'));
    }

    public static function render_photo_brief() {
        $business_name = get_option('siloq_business_name', get_bloginfo('name'));
        $raw   = get_option('siloq_image_audit_results', '');
        $items = $raw ? json_decode($raw, true) : array();
        if (!is_array($items)) {
            $items = array();
        }
        $date = get_option('siloq_image_audit_date', '');
        $date_display = $date ? date('F j, Y', (int) $date) : 'Not yet generated';

        // Group by status priority
        $groups = array(
            'no_images'   => array('label' => 'No Images',    'color' => '#dc2626', 'items' => array()),
            'stock_photo' => array('label' => 'Stock Photos', 'color' => '#d97706', 'items' => array()),
            'missing_alt' => array('label' => 'Missing Alt',  'color' => '#d97706', 'items' => array()),
            'unoptimized' => array('label' => 'Unoptimized',  'color' => '#d97706', 'items' => array()),
            'good'        => array('label' => 'Good',         'color' => '#16a34a', 'items' => array()),
        );
        foreach ($items as $item) {
            $s = $item['status'] ?? 'good';
            if (isset($groups[$s])) {
                $groups[$s]['items'][] = $item;
            }
        }

        ob_start();
        ?>
        <style>
            @media print {
                #adminmenuwrap, #wpadminbar, #wpfooter, #wpcontent { padding: 0 !important; }
                #adminmenuwrap, #wpadminbar, #wpfooter { display: none !important; }
                .siloq-print-hide { display: none !important; }
            }
            .siloq-photo-brief { max-width: 900px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .siloq-photo-brief h1 { font-size: 22px; margin: 0 0 4px; }
            .siloq-photo-brief .brief-meta { font-size: 12px; color: #6b7280; margin-bottom: 20px; }
            .siloq-photo-brief .group-header { font-size: 16px; font-weight: 700; margin: 24px 0 12px; padding: 8px 12px; border-radius: 8px; }
            .siloq-photo-brief .brief-item { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 16px; margin-bottom: 10px; }
            .siloq-photo-brief .brief-item dt { font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
            .siloq-photo-brief .brief-item dd { font-size: 13px; margin: 0 0 8px; }
        </style>
        <div class="siloq-photo-brief">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:8px">
                <div>
                    <h1><?php echo esc_html($business_name); ?> &mdash; Image Brief</h1>
                    <div class="brief-meta">Generated: <?php echo esc_html($date_display); ?></div>
                </div>
                <button class="button button-primary siloq-print-hide" onclick="window.print()">Print Brief</button>
            </div>
        <?php foreach ($groups as $status => $group):
            if (empty($group['items'])) continue;
        ?>
            <div class="group-header" style="background:<?php echo $group['color']; ?>15;color:<?php echo $group['color']; ?>">
                <?php echo esc_html($group['label']); ?> (<?php echo count($group['items']); ?>)
            </div>
            <?php foreach ($group['items'] as $item): $needs = $item['needs'] ?? array(); ?>
            <div class="brief-item">
                <dl style="margin:0;display:grid;grid-template-columns:120px 1fr;gap:2px 12px">
                    <dt>Page</dt>
                    <dd><strong><?php echo esc_html($item['title']); ?></strong></dd>
                    <dt>Status</dt>
                    <dd><?php echo esc_html(ucwords(str_replace('_', ' ', $item['status']))); ?></dd>
                    <?php if (!empty($needs['photo_type'])): ?>
                    <dt>Photo Type</dt>
                    <dd><?php echo esc_html($needs['photo_type']); ?></dd>
                    <?php endif; ?>
                    <?php if ($item['status'] !== 'good'): ?>
                    <dt>Shot Brief</dt>
                    <dd><?php echo esc_html($needs['photo_type'] ?? 'Professional service photo'); ?> &mdash; natural light, real location, no stock poses</dd>
                    <dt>Filename</dt>
                    <dd><code><?php echo esc_html($needs['recommended_filename'] ?? ''); ?></code></dd>
                    <dt>Alt Text</dt>
                    <dd><code><?php echo esc_html($needs['recommended_alt'] ?? ''); ?></code></dd>
                    <?php endif; ?>
                </dl>
            </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
