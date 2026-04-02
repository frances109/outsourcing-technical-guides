# outsourcing-technical-guides

Magellan Solutions — Executive Outsourcing Guides Lead Capture  
Two-page static site with WordPress REST API + WP Mail SMTP + reCAPTCHA v3 + Flamingo integration.

---

## Project Structure

```
outsourcing-technical-guides/
├── package.json
├── README.md
├── wordpress/
│   └── magellan-guides-plugin.php     ← Upload to WordPress as a plugin
└── public/
    ├── css/
    │   ├── base.css                   ← Shared tokens, navbar, footer, animations
    │   ├── technical-guides.css       ← Page 1 styles only
    │   └── download-guides.css        ← Page 2 styles only
    ├── js/
    │   └── form.js                    ← intl-tel-input, reCAPTCHA v3, WP REST submit
    ├── pdf/
    │   ├── omnichannel-contact-center.pdf       ← Drop real PDFs here
    │   ├── back-office-process-support.pdf
    │   └── technical-support-helpdesk.pdf
    └── pages/
        ├── outsourcing-technical-guides.html    ← Page 1: Lead capture form
        └── outsourcing-download-guides.html     ← Page 2: Guide downloads
```

---

## How to Run

### Option A — Quickest (no install)
```bash
npx serve public
# Open: http://localhost:3000/pages/outsourcing-technical-guides.html
```

### Option B — npm install first
```bash
npm install
npm start
# Open: http://localhost:3000/pages/outsourcing-technical-guides.html
```

### Option C — VS Code Live Server
1. Install the **Live Server** extension in VS Code
2. Right-click `public/pages/outsourcing-technical-guides.html`
3. Click **"Open with Live Server"**
4. It auto-reloads on every save

### Option D — PHP built-in server (if you have PHP)
```bash
cd public
php -S localhost:8080
# Open: http://localhost:8080/pages/outsourcing-technical-guides.html
```

---

## Dropping Real PDFs

Replace the placeholder files in `public/pdf/` with your actual PDFs.  
File names must match exactly:

| File | Guide |
|------|-------|
| `omnichannel-contact-center.pdf` | Omnichannel Contact Center |
| `back-office-process-support.pdf` | Back-Office & Process Support |
| `technical-support-helpdesk.pdf` | Technical Support & Helpdesk |

---

## WordPress Integration

### Can I run this from a blank WordPress page?

**Yes — two approaches:**

#### Approach 1: Custom Page Template (Recommended)
Create `page-outsourcing-technical-guides.php` in your theme:

```php
<?php
/* Template Name: Outsourcing Technical Guides */
get_header(); // optional – omit for full custom layout
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- All CDN links and your CSS here -->
  <?php wp_head(); ?>
</head>
<body>
  <!-- Paste your page HTML body here -->
  <?php wp_footer(); // loads form.js via plugin's wp_enqueue_scripts ?>
</body>
</html>
```

Then in WP Admin: Pages → Edit → Template → select "Outsourcing Technical Guides".

#### Approach 2: Custom HTML Block (No-code)
1. Create a new WordPress page (slug: `outsourcing-technical-guides`)
2. Add a **Custom HTML** block
3. Paste the entire contents of `outsourcing-technical-guides.html` inside it
4. The plugin's `wp_enqueue_scripts` hook will automatically load `form.js` on this page

> ⚠️ Some page builders (Elementor, Divi) or themes strip `<script>` tags from Custom HTML blocks.
> Use the Page Template approach if that happens.

---

### Required WordPress Plugins

| Plugin | Purpose |
|--------|---------|
| **WP Mail SMTP** | Routes `wp_mail()` through Gmail, SendGrid, Mailgun etc. |
| **Flamingo** | Stores every lead submission in WP Admin → Flamingo → Inbound Messages |
| **Google reCAPTCHA (any v3 plugin)** | Provides Site Key + Secret Key |

### Plugin Setup Steps

1. **Upload the plugin**: zip `wordpress/magellan-guides-plugin.php` → upload via WP Admin → Plugins → Add New → Upload
2. **Activate** the plugin
3. **Configure**: WP Admin → Settings → Magellan Guides → fill in:
   - reCAPTCHA v3 Site Key
   - reCAPTCHA v3 Secret Key
   - Notification Email
   - Download Page URL (`/outsourcing-download-guides`)
4. **WP Mail SMTP**: configure your preferred mailer (Gmail OAuth2 recommended)
5. **Flamingo**: install and activate — leads appear automatically after the plugin is active

### REST Endpoint Reference

`POST /wp-json/magellan/v1/submit`

```json
// Request body
{
  "first_name":      "Jane",
  "last_name":       "Doe",
  "company_name":    "Acme Corp",
  "work_email":      "jane@acme.com",
  "phone_number":    "+639123456789",
  "recaptcha_token": "<token>"
}

// Success response
{
  "success": true,
  "message": "Submission received.",
  "redirect_url": "/outsourcing-download-guides"
}
```

---

## CDN Dependencies

| Library | CDN |
|---------|-----|
| Bootstrap 5.3 CSS | `cdn.jsdelivr.net/npm/bootstrap@5.3.3` |
| Bootstrap 5.3 JS | `cdn.jsdelivr.net/npm/bootstrap@5.3.3` |
| Bootstrap Icons 1.11 | `cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3` |
| intl-tel-input 21 | `cdn.jsdelivr.net/npm/intl-tel-input@21.1.4` |
| jQuery 3.7 | `cdn.jsdelivr.net/npm/jquery@3.7.1` |
| Google Fonts (Poppins) | `fonts.googleapis.com` |
| reCAPTCHA v3 | `google.com/recaptcha/api.js` |

> jsPDF removed — PDFs are now pre-built files served from `public/pdf/`
