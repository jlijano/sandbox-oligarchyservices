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

## Before going live

1. Replace `https://example.com/` in `robots.txt` and `sitemap.xml` with the
   production domain.
2. Confirm SSL is enabled in Hostinger before relying on the HTTPS redirect in
   `.htaccess`.
3. If the domain uses a subdirectory install, update absolute paths in
   `.htaccess` and the sitemap.
4. Test `index.html`, `login.html`, `privacy.html`, and a fake missing URL after upload.
5. Connect `login.html` to the approved authentication provider before accepting
   real credentials. The current login page is a front-end placeholder only.

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
