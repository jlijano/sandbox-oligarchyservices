# Hostinger Deployment

This project can run as a static Hostinger site, and it now also includes an
optional PHP/MySQL client portal backend for real login support.

## Upload target

Upload these repository files into the domain's `public_html` directory:

- `index.html`
- `login.html`
- `login.php`
- `dashboard.php`
- `logout.php`
- `install.php` for first setup
- `update.php` for admin-run database updates after deployments
- `repair.php` for recreating a missing `includes/config.php`
- `privacy.html`
- `404.html`
- `.htaccess`
- `robots.txt`
- `sitemap.xml`
- `assets/`
- `api/`
- `includes/` without committing or exposing generated credentials

Do not upload local scratch folders, dependency folders, or environment files.
This project does not require `node_modules`, `npm install`, a build command,
cron jobs, or a long-running Node.js process.

## Login page

`login.html` is rewritten by `.htaccess` to `login.php`, which renders the same
login UI with a CSRF token and submits to `api/login.php`.

The PHP/MySQL backend stores password hashes only. It never stores plain-text
passwords.

## PHP/MySQL setup

Use the setup page that matches the job:

- `/install.php`: first-time setup only. It creates `includes/config.php`, the
  required tables, and the first admin account. If the portal is already
  installed, this page stays locked.
- `/update.php`: logged-in admin updates only. It applies safe, non-destructive
  table updates after new CMS code is deployed.
- `/repair.php`: config repair only. It recreates `includes/config.php` when
  `includes/installed.lock` exists but `includes/config.php` is missing.

First install:

1. Create the MySQL database and user in Hostinger hPanel.
2. Upload the repository files to `public_html`.
3. Open `/install.php` in the browser.
4. Enter the database host, database name, database user, and database password.
5. Create the first admin account.
6. Confirm the installer completes successfully.
7. Log in through `/login.html`.

After deploying CMS changes:

1. Log in as an admin.
2. Open `/update.php`.
3. Click **Run update**.
4. Return to `/dashboard.php` and test the changed section.

If login says the database config is missing:

1. Open `/repair.php`.
2. Re-enter the Hostinger database values.
3. If no active admin exists, fill in the optional admin fields.
4. Submit the form, then log in through `/login.html`.

The installer writes `includes/config.php` and `includes/installed.lock` on the
server. These files must not be committed to GitHub. Direct browser access to
`/includes/config.php` is blocked on purpose because it contains the database
password.

## Before going live

1. Confirm `robots.txt` and `sitemap.xml` use the production domain.
2. Confirm SSL is enabled in Hostinger before relying on the HTTPS redirect in
   `.htaccess`.
3. If the domain uses a subdirectory install, update absolute paths in
   `.htaccess` and the sitemap.
4. Test `index.html`, `login.html`, `dashboard.php`, `logout.php`,
   `privacy.html`, and a fake missing URL after upload.

## Analytics

Analytics are disabled by default. Leave them disabled until the provider,
domain, consent posture, and privacy notice are confirmed.

For privacy-friendly analytics, update `window.OLIGARCHY_ANALYTICS` in every
HTML page and keep `respectDoNotTrack: true`.

## Hostinger fit

The site avoids features that commonly cause issues on entry-level shared
hosting plans:

- no dependency installation;
- no custom server process;
- no build output required.
