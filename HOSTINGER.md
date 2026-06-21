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
- `account-confirmation.php` for account email confirmation links
- `install.php` for first setup
- `update.php` for admin-run database updates after deployments
- `repair.php` for reconnecting a missing database config
- `privacy.html`
- `404.html`
- `.htaccess`
- `robots.txt`
- `sitemap.xml`
- `assets/`
- `api/`
- `includes/` without committing or exposing generated credentials

Do not upload local scratch folders, dependency folders, environment files, or
`automation/`. The Sentinel mail orchestrator in `automation/` is a separate
Node.js service and is not part of the Hostinger public website upload.

This project does not require `node_modules`, `npm install`, a build command,
cron jobs, or a long-running Node.js process for the public website.

## Login page

`login.html` is rewritten by `.htaccess` to `login.php`, which renders the same
login UI with a CSRF token and submits to `api/login.php`.

The PHP/MySQL backend stores password hashes only. It never stores plain-text
passwords. Admin-created users must confirm their email address before signing
in. The confirmation email includes both the account confirmation link and the
stable `/login.html` link.

## PHP/MySQL setup

Use the setup page that matches the job:

- `/install.php`: first-time setup only. It creates `includes/config.php`, the
  required tables, and the first admin account. If the portal is already
  installed, this page stays locked.
- `/update.php`: logged-in admin updates only. It applies safe, non-destructive
  table updates after new CMS code is deployed.
- `/repair.php`: config repair only. It reconnects the existing database when
  `includes/config.php` is missing. Existing tables and data are kept. If a
  persistent `oligarchy-config.php` backup exists, repair restores from it
  automatically.

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

After deploying account-confirmation changes, `/update.php` adds the email
confirmation columns without deleting users. Existing older users are marked
confirmed so they are not locked out. Newly created users receive a confirmation
link and cannot sign in until that link is used.

If login says the database config is missing:

1. Do not reinstall, drop, empty, or recreate the database.
2. Open `/repair.php`.
3. Re-enter the Hostinger database values for the existing database.
4. If no active admin exists, fill in the optional admin fields.
5. Submit the form, then log in through `/login.html`.

The installer writes `includes/config.php` and `includes/installed.lock` on the
server. It also tries to save persistent copies of the database config outside
`public_html` so full file syncs do not break login. These generated files must
not be committed to GitHub. Direct browser access to `/includes/config.php` is
blocked on purpose because it contains the database password.

If Hostinger file permissions prevent the persistent config copy from being
saved, the installer or repair page will show a warning. Login can still work,
but a future full file sync may require opening `/repair.php` once to reconnect
the existing database.

## Account confirmation email

The portal uses PHP `mail()` for account confirmation messages. Confirm Hostinger
allows PHP mail for the domain before relying on this flow.

Optional non-secret environment settings:

- `PORTAL_BASE_URL`: absolute portal URL used in confirmation emails, for
  example `https://sandbox.oligarchyservices.com`.
- `PORTAL_MAIL_FROM`: sender address for confirmation emails, for example
  `no-reply@oligarchyservices.com`.

If `PORTAL_BASE_URL` is not set, the portal builds links from the current request
host. If `PORTAL_MAIL_FROM` is not set, the sender defaults to a domain-based
`no-reply` address.

## Sentinel mail orchestrator

`automation/sentinel-mail-orchestrator/` is a separate Node.js service for
sending Sentinel automation emails through `sentinel@oligarchyservices.com`.
Deploy it on a Node-capable host such as Render, Fly.io, Railway, or Cloud Run.
Do not upload it to Hostinger `public_html`.

Store the mailbox password and orchestrator bearer token only in the deployment
host's secret manager. Do not add those values to GitHub, docs, prompts, or
local files that may be committed.

If a deployment replaces `public_html` and removes `includes/config.php`, the
portal still accepts the parent backup config or these environment variables:
`DB_HOST`, `DB_DATABASE` or `DB_NAME`, `DB_USERNAME` or `DB_USER`,
`DB_PASSWORD`, and optional `DB_PORT`.

## Before going live

1. Confirm `robots.txt` and `sitemap.xml` use the production domain.
2. Confirm SSL is enabled in Hostinger before relying on the HTTPS redirect in
   `.htaccess`.
3. If the domain uses a subdirectory install, update absolute paths in
   `.htaccess` and the sitemap.
4. Test `index.html`, `login.html`, `dashboard.php`, `logout.php`,
   `account-confirmation.php`, `privacy.html`, and a fake missing URL after
   upload.

## Analytics

Analytics are disabled by default. Leave them disabled until the provider,
domain, consent posture, and privacy notice are confirmed.

For privacy-friendly analytics, update `window.OLIGARCHY_ANALYTICS` in every
HTML page and keep `respectDoNotTrack: true`.

## Hostinger fit

The public website avoids features that commonly cause issues on entry-level
shared hosting plans:

- no dependency installation;
- no custom server process;
- no build output required.

The Sentinel mail orchestrator is intentionally outside that public website
runtime and should be deployed separately only when email automation is needed.
