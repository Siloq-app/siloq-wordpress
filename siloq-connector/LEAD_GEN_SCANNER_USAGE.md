# Lead Gen Scanner - Usage Guide

The Lead Gen Scanner is a powerful WordPress shortcode that embeds a website scanner widget on any page to capture leads and generate scan results.

## Quick Start

Add this shortcode to any WordPress page or post:

```
[siloq_scanner]
```

## How It Works

### User Flow

1. **Visitor Input** - User enters their website URL and email address
2. **Scan Execution** - Scanner analyzes the website via Siloq API across 5 categories:
   - Technical SEO (25% weight)
   - Content Quality (20% weight)
   - Site Structure (20% weight)
   - Performance (20% weight)
   - SEO Factors (15% weight)
3. **Teaser Results** - Shows:
   - Overall score (0-100)
   - Letter grade (A+ to F)
   - Top 3 high-priority issues
4. **CTA Conversion** - "Get Full Report" button redirects to Siloq signup (BLUEPRINT plan by default)

### Lead Capture

All submissions are automatically stored in the `wp_siloq_leads` database table with:
- Email address
- Website URL
- Scan ID
- IP address
- User agent
- Timestamp

## Shortcode Options

### Basic Usage

```
[siloq_scanner]
```

### Custom Title

```
[siloq_scanner title="Get Your Free SEO Report"]
```

### Custom Button Text

```
[siloq_scanner button_text="Analyze My Website"]
```

### Custom Signup URL

```
[siloq_scanner signup_url="https://yourdomain.com/custom-signup"]
```

### All Options Combined

```
[siloq_scanner
    title="Free Website Audit"
    button_text="Start Scan"
    signup_url="https://app.siloq.io/signup?plan=operator&ref=partner123"
]
```

## Admin Configuration

### Settings Location

Go to **Settings → Siloq** in WordPress admin, scroll to the **Lead Gen Scanner** section.

### Signup URL Setting

Configure the default signup URL that users are redirected to after viewing scan results:
- Leave empty to use the default: `https://app.siloq.io/signup?plan=blueprint`
- Set a custom URL to redirect to your own funnel or custom Siloq signup link

## Styling Customization

The scanner uses CSS classes that can be customized in your theme:

### Main Container
```css
.siloq-scanner-widget {
    /* Customize the widget container */
}
```

### Form Section
```css
.siloq-scanner-form { }
.siloq-scanner-title { }
.siloq-form-group input { }
.siloq-submit-btn { }
```

### Results Section
```css
.siloq-scanner-results { }
.siloq-score-circle { }
.siloq-grade-badge { }
.siloq-issue-item { }
.siloq-cta-btn { }
```

## Lead Management

### Viewing Leads

Leads are stored in the database table `{prefix}_siloq_leads`. You can query them directly or use a plugin like:
- WP Data Access
- Advanced Custom Fields
- Custom admin pages

### Sample Query

```sql
SELECT
    email,
    website_url,
    scan_id,
    created_at
FROM wp_siloq_leads
ORDER BY created_at DESC
LIMIT 100;
```

### Export Leads

Use a database management tool or create a custom export function to download leads for your marketing campaigns.

## API Integration

### Scan Endpoint

The scanner uses the Siloq API endpoint:
```
POST /api/v1/scans
```

Request body:
```json
{
    "url": "https://example.com",
    "scan_type": "full"
}
```

### Polling Results

Results are polled from:
```
GET /api/v1/scans/{scan_id}
```

Polling occurs every 3 seconds until status is `completed` or `failed`.

## Troubleshooting

### Scanner Not Appearing

1. Check that the shortcode is spelled correctly: `[siloq_scanner]`
2. Verify the plugin is activated
3. Check JavaScript console for errors

### Scans Not Starting

1. Verify API connection in **Settings → Siloq**
2. Test API connection using the "Test Connection" button
3. Check that API key has scan permissions
4. Review WordPress error logs

### Leads Not Saving

1. Check database table exists: `{prefix}_siloq_leads`
2. Verify database write permissions
3. Check WordPress debug logs

### Results Not Displaying

1. Ensure scan completes successfully (check API response)
2. Verify scan returns valid data structure
3. Check browser console for JavaScript errors

## Advanced Usage

### Custom Landing Page

Create a dedicated landing page with custom messaging:

```html
<!-- wp:heading -->
<h2>Is Your Website Losing Customers?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Over 75% of users judge a business's credibility based on their website.
Find out what's hurting your online presence in just 30 seconds.</p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[siloq_scanner title="Free 30-Second Website Audit" button_text="Check My Site Now"]
<!-- /wp:shortcode -->
```

### A/B Testing

Test different copy variations:

**Page A:**
```
[siloq_scanner title="Free SEO Audit" button_text="Scan My Site"]
```

**Page B:**
```
[siloq_scanner title="Get Your SEO Score" button_text="Check My Score"]
```

Track conversions using Google Analytics events or a WordPress A/B testing plugin.

### Sidebar Widget

Add the scanner to a sidebar using the "Shortcode" widget:

1. Go to **Appearance → Widgets**
2. Add a "Shortcode" widget to your sidebar
3. Enter: `[siloq_scanner]`

Note: The scanner is optimized for content areas (max-width: 600px). Sidebar display may require CSS adjustments.

## Best Practices

### Placement

- Above the fold on landing pages
- After a compelling value proposition
- On service pages (SEO, web design, marketing)
- In blog posts about website optimization

### Messaging

- Emphasize the "free" and "instant" nature
- Use social proof ("Join 1,000+ businesses who've improved their SEO")
- Address pain points ("Is your website invisible to Google?")
- Create urgency ("Find out what's wrong in 30 seconds")

### Follow-Up

- Set up automated email sequences for captured leads
- Include scan results summary in follow-up emails
- Offer consultation calls for high-score leads
- Provide SEO guides for low-score leads

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review WordPress error logs
3. Contact Siloq support with:
   - WordPress version
   - Plugin version
   - Error messages
   - Steps to reproduce
