# Oligarchy Services

Static website and optional PHP/MySQL client portal foundation for `jlijano/sandbox-oligarchyservices`.

## Current architecture

- Static HTML, CSS, and JavaScript public marketing pages.
- Optional PHP/MySQL client portal for real login support.
- No backend framework.
- No API routes beyond the lightweight PHP portal endpoints.
- No build step required.
- Analytics loader exists, but analytics are disabled by default.
- Hostinger-compatible Apache configuration is included in `.htaccess`.
- `login.html` is the stable public login URL and is rewritten to `login.php` when the PHP portal is deployed.
- The installer writes `includes/config.php` and `includes/installed.lock` on the live server; those generated files must not be committed to GitHub.

## Hostinger deployment

This project can be uploaded directly to Hostinger shared web hosting. Place the
repository contents in the domain's `public_html` directory. No Node.js runtime,
package installation, or build command is required.

For static-only preview use, `login.html` can show the login UI without checking
credentials. For real client access, upload the PHP portal files and run the
one-time installer described in `HOSTINGER.md`.

Before going live:

1. Confirm `robots.txt` and `sitemap.xml` use the production domain.
2. Confirm SSL is active in Hostinger.
3. Upload `.htaccess` along with the visible files. It is a hidden file, but it
   controls the default index page, 404 page, security headers, caching, HTTPS
   redirect, login rewrite, and protected helper paths.
4. If enabling the client portal, create the Hostinger MySQL database, run
   `/install.php`, confirm login works, and then use `/update.php` for future
   schema updates after deployments.
5. If `includes/config.php` is missing on the live server, use `/repair.php` to
   recreate it from the Hostinger database values.
6. Test `index.html`, `login.html`, `dashboard.php`, `logout.php`,
   `privacy.html`, and one missing URL after upload.

See `HOSTINGER.md` for the full checklist.

## Portal setup pages

- `/install.php`: first-time portal setup. Creates the server-local database
  config, required tables, and first admin account.
- `/update.php`: logged-in admin maintenance page. Applies safe, non-destructive
  table updates after CMS code changes.
- `/repair.php`: server repair page. Recreates `includes/config.php` only when
  the installer lock exists but the config file is missing.

Direct browser access to `/includes/config.php` is blocked by `.htaccess` on
purpose because that file contains the database password. PHP can still read it
internally.

## Login page and client portal

The stable client login page is available at `/login.html`.

In static preview mode, `login.html` includes responsive layout, browser-side
validation, password visibility controls, optional remembered email support, and
accessible field errors, but it does not verify credentials or store passwords.

When deployed with PHP/MySQL, `.htaccess` rewrites `/login.html` to `login.php`.
The PHP form includes a CSRF token and submits to `api/login.php`, which verifies
users against the database, records login attempts, regenerates the session ID on
successful login, and redirects authenticated users to `dashboard.php`.

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

For PHP portal testing, use a PHP-capable local server with a MySQL database and
generated local `includes/config.php`. Do not commit generated credentials.

## Change notes

- 2026-06-21: Split portal setup into `/install.php`, `/update.php`, and `/repair.php` so first setup, schema updates, and missing-config repair use separate flows.
- 2026-06-20: Aligned README architecture and deployment notes with the PHP/MySQL client portal documented in `HOSTINGER.md`.
- 2026-06-20: Added a Hostinger-compatible static client login page at `/login.html` with responsive styling and browser-side validation.
- 2026-06-20: Updated `robots.txt` and `sitemap.xml` to use the live Hostinger sandbox domain.
- 2026-06-18: Updated the homepage hero consultation button to use the same red as the top navigation Get Quote button.
- 2026-06-18: Removed the duplicate homepage hero tagline and visible homepage header brand text so the hero and navigation match the requested layout.
