# Siloq TALI - Theme-Aware Layout Intelligence

## Overview

TALI automatically adapts Siloq-generated content to match the user's WordPress theme, ensuring pages look professional without manual styling.

## Files

```
siloq-tali/
├── class-siloq-tali.php              # Main TALI class (singleton)
├── class-siloq-tali-fingerprinter.php # Theme token extraction
├── class-siloq-tali-component-mapper.php # Gutenberg block discovery
├── class-siloq-tali-block-injector.php # Content injection with claim anchors
├── views/
│   └── admin-tali-settings.php       # Admin settings page
└── README.md                          # This file
```

## Integration

### 1. Add to WordPress Plugin

Copy these files to `siloq-connector/includes/tali/` and add to main plugin file:

```php
// In siloq-connector.php, after other includes:
require_once plugin_dir_path(__FILE__) . 'includes/tali/class-siloq-tali.php';

// Initialize TALI
add_action('plugins_loaded', function() {
    siloq_tali();
});

// Define plugin file constant if not exists
if (!defined('SILOQ_PLUGIN_FILE')) {
    define('SILOQ_PLUGIN_FILE', __FILE__);
}
```

### 2. Trigger Fingerprint

TALI automatically fingerprints on:
- Plugin activation
- Theme switch
- Customizer save
- Manual "Re-Fingerprint" button in admin

### 3. Inject Authority Content

```php
// Example: Inject service page content
$tali = siloq_tali();

$content_data = array(
    'service_name' => 'Plumbing',
    'city' => 'Kansas City',
    'introduction' => 'Professional plumbing services...',
    'services' => array(
        'Emergency repairs',
        'Drain cleaning',
        'Water heater installation',
    ),
    'benefits' => array(
        '24/7 availability',
        'Licensed & insured',
        'Satisfaction guaranteed',
    ),
    'faq' => array(
        array(
            'question' => 'How quickly can you respond?',
            'answer' => 'We offer same-day service for emergencies.',
        ),
    ),
    'cta' => array(
        'heading' => 'Need a Plumber?',
        'text' => 'Call us today for fast, reliable service.',
        'button_text' => 'Get a Quote',
        'button_url' => '/contact',
    ),
    'claim_prefix' => 'SRV',
);

$result = $tali->inject_authority(
    $post_id,           // WordPress post ID
    'service_city',     // Template type
    $content_data,      // Content data
    array(
        'access_state' => 'ENABLED', // or 'FROZEN'
    )
);

if ($result['success']) {
    echo "Injected {$result['block_count']} blocks with {$result['confidence']}% confidence";
}
```

## Templates

### service_city
Service + location pages with:
- Hero/intro section
- Services list
- Benefits
- Process steps
- FAQ
- CTA

### blog_post
Blog articles with:
- Sections (heading + content + optional list)

### project_post
Project/job showcases with:
- Description
- Image gallery
- Project details

## Claim Anchors

Every injected block is wrapped with:

```html
<div class="siloq-authority-container"
     data-siloq-claim-id="CLAIM:SRV-104-A"
     data-siloq-governance="V1"
     data-siloq-template="service_city"
     data-siloq-theme="theme-slug">
  <!-- SiloqAuthorityReceipt: CLAIM:SRV-104-A -->
  <!-- Content blocks here -->
</div>
```

## Access States

- **ENABLED**: Full content rendered
- **FROZEN**: Only receipt preserved, content suppressed

```php
$tali->inject_authority($post_id, 'service_city', $data, array(
    'access_state' => 'FROZEN'
));
```

## Confidence Threshold

If mapping confidence < 90%, content is saved as **Draft** with admin notice.

## REST API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/siloq/v1/tali/fingerprint` | POST | Run theme fingerprint |
| `/siloq/v1/tali/design-profile` | GET | Get design profile |
| `/siloq/v1/tali/capability-map` | GET | Get capability map |
| `/siloq/v1/tali/inject` | POST | Inject authority content |

### Inject Example

```bash
curl -X POST https://yoursite.com/wp-json/siloq/v1/tali/inject \
  -H "X-Siloq-API-Key: sk_siloq_xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "post_id": 123,
    "template": "service_city",
    "content_data": { ... }
  }'
```

## Output Files

TALI generates JSON files for debugging:
- `wp-content/uploads/siloq-tali/design_profile_wp.json`
- `wp-content/uploads/siloq-tali/wp_component_capability_map.json`

## Testing

1. Install plugin with TALI
2. Go to Siloq → Theme Intelligence
3. Verify design profile shows theme colors/typography
4. Create test post and inject content via API
5. Verify blocks match theme styling
6. Verify claim anchors in page source

## TODO for Devs

- [ ] Integrate with existing Siloq content generation flow
- [ ] Add backend API endpoint to store/retrieve design profiles
- [ ] Test with popular themes (Astra, GeneratePress, Kadence, Twenty Twenty-Four)
- [ ] Add unit tests for fingerprinting
- [ ] Add integration tests for block injection

## Spec Reference

Full spec: https://docs.google.com/document/d/13-BJUbxj81_aHh3PZmgtN03RjN8Y74t2LOdW3s4b-gw/edit
