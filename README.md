# Oligarchy Services

Static site foundation for `jlijano/sandbox-oligarchyservices`.

## Current architecture

- Static-only HTML, CSS, and JavaScript.
- No backend framework.
- No API routes.
- No database.
- No build step required.
- Analytics loader exists, but analytics are disabled by default.
- Hostinger-compatible Apache configuration is included in `.htaccess`.

## Project structure

The site keeps public HTML files at the repository root so existing Hostinger URLs continue to work without rewrite changes. The new folders provide a cleaner structure for future maintenance while preserving the current live look.

```text
/
├── index.html, about.html, contact.html, privacy.html, 404.html
├── itad.html, itam.html, help-desk.html, business-systems.html, ai-automation.html, projects.html
├── pages/                  # planned page source organization and page-level notes
└── assets/
    ├── css/                # structured CSS entry points for future migration
    ├── js/                 # structured JavaScript entry points for future migration
    ├── img/                # image, logo, icon, screenshot, and graphic assets
    └── *.css, *.js, *.mp4  # current compatibility assets used by the live pages
```

For now, do not move the root HTML files unless deployment/routing is updated and tested. New CSS should be staged in `assets/css/`, new browser scripts in `assets/js/`, and new image files in `assets/img/`.

## Hostinger deployment

This project can be uploaded directly to Hostinger shared web hosting. Place the
repository contents in the domain's `public_html` directory. No Node.js runtime,
package installation, database, or build command is required.

Before going live:

1. Replace `https://example.com/` in `robots.txt` and `sitemap.xml` with the
   production domain.
2. Confirm SSL is active in Hostinger.
3. Upload `.htaccess` along with the visible files. It is a hidden file, but it
   controls the default index page, 404 page, security headers, caching, and
   HTTPS redirect.
4. Test `index.html`, `privacy.html`, and one missing URL after upload.

See `HOSTINGER.md` for the full checklist.

## Analytics approach

The project is prepared for privacy-friendly analytics, with Plausible-style loading as the first supported provider. Tracking is intentionally disabled in `index.html` and `privacy.html` until a real provider/domain decision is made.

To enable Plausible:

1. Create the site in Plausible or a compatible self-hosted Plausible instance.
2. Confirm the public domain that should be tracked.
3. Update `window.OLIGARCHY_ANALYTICS` in each HTML page:

```html
window.OLIGARCHY_ANALYTICS = {
  enabled: true,
  provider: "plausible",
  domain: "example.com",
  respectDoNotTrack: true
};
```

If using a self-hosted script, add `scriptUrl`.

```html
scriptUrl: "https://analytics.example.com/js/script.js"
```

## Privacy and consent

Before analytics is enabled, review:

- whether analytics is cookie-free and aggregate-only;
- whether the site needs a consent banner for visitors in specific jurisdictions;
- whether IP addresses are anonymized or discarded by the provider;
- data retention settings;
- whether referrer, browser, device type, and approximate location are necessary;
- whether the privacy page accurately names the analytics provider.

Avoid fingerprinting, precise location tracking, advertising identifiers, and collecting unnecessary personal data.

## What can be tracked when analytics is enabled

With a privacy-friendly analytics provider, the site can typically report:

- visitor count and page views;
- device category;
- browser and operating system;
- referrer;
- approximate country or region.

The exact fields depend on the provider and hosting platform. This static site does not store analytics data itself.

## Local preview

Open `index.html` directly in a browser, or run a static server:

```sh
python3 -m http.server 8000
```

Then visit `http://localhost:8000`.

## Change notes

- 2026-06-19: Added organized `pages/`, `assets/css/`, `assets/js/`, and `assets/img/` folders for cleaner future site maintenance while preserving existing public page URLs and current flat asset compatibility.
- 2026-06-18: Updated the homepage hero consultation button to use the same red as the top navigation Get Quote button.
- 2026-06-18: Removed the duplicate homepage hero tagline and visible homepage header brand text so the hero and navigation match the requested layout.
