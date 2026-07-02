# Hostinger Deployment

This project can run as a static Hostinger site, and it now also includes an
optional PHP/MySQL client portal backend for real login support.

## Upload target

Upload these repository files into the domain's `public_html` directory:

- `index.html`
- `login.html`
- `login.php`
- `dashboard.php`
- `requests.php` for authenticated client service requests
- `carrier.php` and `carrier-sync.php` for admin/editor Carrier mailbox work
- `switchboard.php` for admin/editor operational conversations
- `pages.php`, `page.php`, `admin-blogs.php`, `blogs.php`, and `blog.php` for CMS and blog content
- `prospects.php`, `prospect-status.php`, `prospect-sync.php`, and `prospect-sync-job.php` for prospect management and optional sheet sync
- `users.php`, `roles.php`, `companies.php`, `departments.php`, and `agents.php` for portal access management
- `automation.php` for portal automation controls
- `logout.php`
- `account-confirmation.php` for account email confirmation links
- `change-password.php` for required first-login password changes
- `install.php` for first setup
- `update.php` for admin-run database updates after deployments
- `repair.php` for reconnecting a missing database config
- `privacy.html`
- `404.html`
- `.htaccess`
- `robots.txt`
- `sitemap.xml`
- `sitemap.php` for dynamic published blog URLs
- `assets/`
- `api/`
- `includes/` without committing or exposing generated credentials

Do not upload local scratch folders, dependency folders, environment files, generated Sentinel report archives, or other development-only files.

This project does not require `node_modules`, `npm install`, a build command,
cron jobs, or a long-running Node.js process for the public website.

## Login page

`login.html` is rewritten by `.htaccess` to `login.php`, which renders the same
login UI with a CSRF token and submits to `api/login.php`.

The PHP/MySQL backend stores password hashes only. It never stores plain-text
passwords. Admin-created users receive an account confirmation email, then create
their own password from the confirmation link before signing in. The confirmation
email intentionally does not include a temporary password.

## PHP/MySQL setup

Use the setup page that matches the job:

- `/install.php`: first-time setup only. It creates `includes/config.php`, the
  required tables, and the first admin account. If the portal is already
  installed, this page stays locked.
- `/update.php`: logged-in admin updates only. It applies safe, non-destructive
  table updates after new CMS, blog, access-management, prospect, Carrier,
  Switchboard, or client request code is deployed.
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

After deploying CMS, prospect, Carrier, Switchboard, or client request changes:

1. Log in as an admin.
2. Open `/update.php`.
3. Click **Run update**.
4. Return to `/dashboard.php` and test the changed section.

After deploying account-confirmation changes, `/update.php` adds the email
confirmation columns without deleting users. Existing older users are marked
confirmed so they are not locked out. Newly created users receive a confirmation
link by email. After they open the link, they confirm the account and create
their own password on `/account-confirmation.php`; no temporary password is sent
by email.

After deploying the Client Requests module, `/update.php` creates or updates the
`client_requests` and `client_request_updates` tables without deleting users or
existing portal content. Clients can submit requests, add client-visible
follow-up comments, and view their own timeline at `/requests.php`. Admin,
editor, and support users can view the full queue, update status and priority,
assign an internal owner, add client-visible updates, add internal-only notes,
and record activity-log entries.

## Carrier mail setup

After deploying Carrier changes, `/update.php` creates or updates the core
Carrier tables. The first Carrier mail settings save or sync creates the IMAP
settings table and import-tracking columns when needed.

Use `/carrier` for the Outlook-inspired Carrier workspace. The Mail Settings
modal saves settings through `/carrier-sync.php` and returns to `/carrier` with a
success or error message. Direct browser access to `/carrier-sync.php` remains
available as a standalone setup page. Saved mailbox passwords are never displayed;
leave the password field blank to keep an existing saved password.

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

## Blog sitemap

`robots.txt` references both the static `sitemap.xml` and dynamic `sitemap.php`.
The dynamic sitemap keeps the same core public URLs as `sitemap.xml` and adds
published blog detail URLs from the portal database. `blogs.php`, `blog.php`, and
`sitemap.php` are public read paths only. They do not create or alter blog tables
during visitor or crawler requests.

If the database is briefly unavailable, or if the blog schema has not been
created yet, `sitemap.php` still returns the static public URLs and logs the blog
lookup error server-side. After deploying blog or CMS changes, log in as an admin
and run `/update.php` once so blog schema updates happen in the authenticated
maintenance flow instead of public routes.

## Account confirmation email

The portal uses PHP `mail()` for account confirmation messages. Confirm Hostinger
allows PHP mail for the domain before relying on this flow.

Optional non-secret environment settings:

- `PORTAL_BASE_URL`: absolute portal URL used in confirmation emails, for
  example `https://sandbox.oligarchyservices.com`.
- `PORTAL_MAIL_FROM`: sender address for confirmation emails, for example
  `no-reply@sandbox.oligarchyservices.com`.

If `PORTAL_BASE_URL` is not set, the portal builds links from the current request
host. If `PORTAL_MAIL_FROM` is not set, the sender defaults to
`no-reply@sandbox.oligarchyservices.com`.

For best Gmail delivery, enable SPF and DKIM for the sender domain in Hostinger
DNS/email settings and publish a DMARC record that aligns with the visible From
domain. The app no longer sends temporary passwords in account emails because
mail relays can classify credential-bearing messages as unsafe.

If a deployment replaces `public_html` and removes `includes/config.php`, the
portal still accepts the parent backup config or these environment variables:
`DB_HOST`, `DB_DATABASE` or `DB_NAME`, `DB_USERNAME` or `DB_USER`,
`DB_PASSWORD`, and optional `DB_PORT`.

## Prospect sheet sync

Prospect management works without a scheduled sync job. To enable the optional
Google Sheet sync, configure the source in the hosting environment before any
manual or scheduled sync runs. Do not commit private sheet values to the
repository.

Set one of these non-secret source values:

- `PROSPECTS_SYNC_CSV_URL`: direct HTTPS CSV export URL.
- `PROSPECTS_SYNC_SPREADSHEET_ID`: spreadsheet ID used by the default tab exports.

Additional sync setting:

- `PROSPECTS_SYNC_JOB_TOKEN`: required only for `/prospect-sync-job.php` scheduled sync calls.

Use `/prospect-sync.php` while logged in as an admin or editor for a manual sync.
If `/prospect-sync-job.php` is exposed to a scheduler, send the token over a
private scheduler configuration and rotate it if it has been shared in logs,
bookmarks, or support tickets.

Sentinel report archive expectations are documented in
`docs/sentinel-report-archive.md`. Generated report archives should stay in
Sentinel's private durable workspace or the automation host's private persistent
storage, not in Hostinger `public_html`.

## Availability diagnostics

The GitHub Actions workflow checks public routes and protected helper paths, but
external probes from GitHub runners can be blocked by network tunnels, Hostinger
edge rules, WAF behavior, or proxy policy. A curl tunnel failure or unexpected
403 from the runner is diagnostic evidence only. Confirm availability from a
normal browser session, Hostinger hPanel, or server logs before treating the site
as down.

## Before going live

1. Confirm `robots.txt`, `sitemap.xml`, and `sitemap.php` use the production
   domain.
2. Confirm SSL is enabled in Hostinger before relying on the HTTPS redirect in
   `.htaccess`.
3. If the domain uses a subdirectory install, update absolute paths in
   `.htaccess` and the sitemaps.
4. Test `index.html`, `login.html`, `dashboard.php`, `requests.php`,
   `carrier.php`, `switchboard.php`, `prospects.php`, `logout.php`,
   `account-confirmation.php`, `change-password.php`, `privacy.html`,
   `sitemap.php`, and a fake missing URL after upload.

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
