# Hostinger Deployment

This project is intentionally static-only so it can run within basic Hostinger
web hosting limits.

## Upload target

Upload these repository files into the domain's `public_html` directory:

- `index.html`
- `login.html`
- `privacy.html`
- `404.html`
- `.htaccess`
- `robots.txt`
- `sitemap.xml`
- `assets/`

Do not upload local scratch folders, dependency folders, or environment files.
This project does not require `node_modules`, `npm install`, a build command,
PHP, MySQL, cron jobs, or a long-running Node.js process.

## Login page

`login.html` is a static client login screen with browser-side validation,
password visibility controls, and optional remembered email support. It does not
store passwords in the browser.

Before using it for real client access, connect the form to a real
authentication endpoint or portal service. Basic Hostinger static hosting alone
cannot validate credentials securely.

## Before going live

1. Confirm `robots.txt` and `sitemap.xml` use the production domain.
2. Confirm SSL is enabled in Hostinger before relying on the HTTPS redirect in
   `.htaccess`.
3. If the domain uses a subdirectory install, update absolute paths in
   `.htaccess` and the sitemap.
4. Test `index.html`, `login.html`, `privacy.html`, and a fake missing URL after
   upload.

## Analytics

Analytics are disabled by default. Leave them disabled until the provider,
domain, consent posture, and privacy notice are confirmed.

For privacy-friendly analytics, update `window.OLIGARCHY_ANALYTICS` in every
HTML page and keep `respectDoNotTrack: true`.

## Hostinger fit

The site avoids features that commonly cause issues on entry-level shared
hosting plans:

- no server-side rendering;
- no API routes;
- no database connection;
- no dependency installation;
- no custom server process;
- no build output required.
