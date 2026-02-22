# How to Access the Lead Generation Page

## 1. Create a WordPress page

1. In WordPress admin go to **Pages → Add New**.
2. Set the title to **Lead Generation** (or any title you like).
3. In the content area, add this shortcode:
   ```
   [siloq_scanner]
   ```
4. Publish the page.

## 2. View the page

- Open the page URL (e.g. `https://yoursite.com/lead-generation/` or `http://localhost:8080/lead-generation/`).
- You’ll see the Lead Gen UI: header, “Lead Generation” title, Free SEO Audit card (Website URL, Email, “Scan My Site” button, privacy text, error box when something fails).

## 3. Dummy API (first version)

- In **Siloq → Settings**, the option **“Use dummy scan API (for testing without real backend)”** is **on by default**.
- With it on, you don’t need to set API URL or API Key. “Scan My Site” uses mock data and shows a sample report (score, grade, 3 sample issues, “Get Full Report” CTA).
- To use the real Siloq scan API later: turn off the dummy option, enter API URL and API Key, then save.

## 4. Shortcode options (optional)

You can customize the shortcode:

- `[siloq_scanner]` — default (header + “Lead Generation” title + card).
- `[siloq_scanner show_header="no"]` — hide the golden header bar.
- `[siloq_scanner show_page_title="no"]` — hide the “Lead Generation” heading.
- `[siloq_scanner contact_url="https://yoursite.com/contact"]` — set “Contact Us” link.
- `[siloq_scanner title="Free SEO Audit" button_text="Scan My Site"]` — change card title and button text.

## Summary

| Step | Action |
|------|--------|
| 1 | Create a new Page. |
| 2 | Add shortcode: `[siloq_scanner]`. |
| 3 | Publish. |
| 4 | Open the page URL to see and test the Lead Gen form (dummy API works without backend). |
