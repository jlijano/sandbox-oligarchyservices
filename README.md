# Oligarchy Services

Static website and optional PHP/MySQL client portal foundation for `jlijano/sandbox-oligarchyservices`.

## Current architecture

- Static HTML, CSS, and JavaScript public marketing pages.
- Optional PHP/MySQL client portal for real login support.
- No backend framework for the public website.
- No API routes beyond the lightweight PHP portal endpoints.
- No build step required for the public website.
- Analytics loader exists, but analytics are disabled by default.
- Hostinger-compatible Apache configuration is included in `.htaccess`.
- `login.html` is the stable public login URL and is rewritten to `login.php` when the PHP portal is deployed.
- Authenticated clients can submit and view service requests at `/requests.php`; admin, editor, and support users can manage the full request queue and timeline updates.
- Admin-created portal users receive a generated temporary password by email, confirm their email address before signing in, then create their own password before opening the dashboard.
- The installer writes `includes/config.php` and `includes/installed.lock` on the live server; those generated files must not be committed to GitHub.
- The installer also writes persistent database config backups outside `public_html` when Hostinger file permissions allow it, so full file syncs do not force a database reinstall.
- The portal can also read `DB_HOST`, `DB_DATABASE`/`DB_NAME`, `DB_USERNAME`/`DB_USER`, `DB_PASSWORD`, and optional `DB_PORT` environment variables.
- `automation/sentinel-mail-orchestrator/` contains a separate Node.js automation service for Sentinel email sending. It is not part of the Hostinger public website upload.

## Hostinger deployment

This project can be uploaded directly to Hostinger shared web hosting. Place the
public website files in the domain's `public_html` directory. No Node.js runtime,
package installation, or build command is required for the public website.

Do not upload `automation/` to Hostinger `public_html`. The Sentinel mail
orchestrator is a separate Node.js service that should run on a Node-capable host
such as Render, Fly.io, Railway, or Cloud Run when email automation is needed.

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
5. Confirm Hostinger PHP mail is allowed for the domain. Optionally set
   `PORTAL_BASE_URL` and `PORTAL_MAIL_FROM` in the hosting environment so account
   confirmation emails use the exact production URL and sender address.
6. If `includes/config.php` is missing on the live server, use `/repair.php` to
   reconnect the existing database. Do not reinstall, drop, empty, or recreate
   the database just because the config file is missing.
7. Test `index.html`, `login.html`, `dashboard.php`, `requests.php`,
   `logout.php`, `privacy.html`, `career-timeline.html`,
   `account-confirmation.php`, `change-password.php`, and one missing URL after
   upload.

See `HOSTINGER.md` for the full checklist.

## Portal setup pages

- `/install.php`: first-time portal setup. Creates the server-local database
  config, required tables, and first admin account.
- `/update.php`: logged-in admin maintenance page. Applies safe, non-destructive
  table updates after CMS, blog, access-management, or client request code
  changes.
- `/repair.php`: server repair page. Reconnects the existing database when the
  server-local config file is missing. It restores from a persistent backup when
  possible, or from entered Hostinger database values when the installer lock
  exists but config is missing. Existing tables and data are kept.

Direct browser access to `/includes/config.php` is blocked by `.htaccess` on
purpose because that file contains the database password. PHP can still read it
internally. After code deployments, login and update pages also accept the
parent-directory backup config or database environment variables, so a missing
`includes/config.php` is no longer fatal by itself.

## Login page and client portal

The stable client login page is available at `/login.html`.

In static preview mode, `login.html` includes responsive layout, browser-side
validation, password visibility controls, optional remembered email support, and
accessible field errors, but it does not verify credentials or store passwords.

When deployed with PHP/MySQL, `.htaccess` rewrites `/login.html` to `login.php`.
The PHP form includes a CSRF token and submits to `api/login.php`, which verifies
users against the database, records login attempts, regenerates the session ID on
successful login, and redirects authenticated users to `dashboard.php`.

Newly created users receive a generated temporary password in the account email.
They must confirm their email address first through the link sent when an admin
creates the account under `Valley > Users`. After confirmation, their first
successful login with the temporary password redirects to `/change-password.php`;
dashboard access remains blocked until they create their own password.

Confirmation links expire after 48 hours. The sender defaults to a domain-based
`no-reply` address; set `PORTAL_MAIL_FROM` to use a specific mailbox. Set
`PORTAL_BASE_URL` when the portal runs behind a proxy or when generated email
links must always use the production domain.

## Client requests

`/requests.php` adds a lightweight authenticated service request module for the
portal. Clients can submit requests, add client-visible follow-up comments, and
view only their own request history. Admin, editor, and support users can view
all requests, update status, assign priority, choose an internal assignee, add
client-visible updates, add internal-only notes, and write activity-log entries.

Request statuses are `new`, `in_review`, `waiting_on_client`, `in_progress`,
`resolved`, and `closed`. Request priorities are `low`, `normal`, `high`, and
`urgent`.

After deploying the request module to an existing Hostinger portal, log in as an
admin and run `/update.php` once. The update creates or updates the
`client_requests` and `client_request_updates` tables without deleting existing
users, pages, blogs, settings, or activity records.

## Sentinel mail orchestrator

`automation/sentinel-mail-orchestrator/` provides a small Node.js HTTP service
for sending Sentinel automation emails through the Hostinger mailbox
`sentinel@oligarchyservices.com`.

The orchestrator currently supports SMTP sending through a protected
`/send-email` endpoint and a `/health` endpoint. It defaults to `DRY_RUN=true`
so first deployments cannot send real email accidentally.

Required non-secret settings are documented in
`automation/sentinel-mail-orchestrator/env.sample`. Store `MAIL_PASSWORD` and
`ORCHESTRATOR_TOKEN` only in the deployment host's secret manager.

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

For the Sentinel mail orchestrator, run commands inside
`automation/sentinel-mail-orchestrator/` and keep real environment values only in
the service host or a local uncommitted environment file.

## Change notes

- 2026-06-22: Added a polished `career-timeline.html` page for Jan Christian L.'s LinkedIn work history using the current site design system.
- 2026-06-22: Added request timeline updates with client-visible comments and internal-only staff notes.
- 2026-06-21: Added a Client Requests portal module for authenticated client submissions and admin/support/editor queue management.
- 2026-06-21: Added generated temporary passwords to account confirmation emails for new portal users.
- 2026-06-21: Required newly confirmed portal users to create their own password before dashboard access.
- 2026-06-21: Added email confirmation for admin-created portal users before first login.
- 2026-06-21: Hardened PHP portal config persistence so Hostinger file syncs do not require database reinstallation when `includes/config.php` is removed.
- 2026-06-21: Added a separate Sentinel mail orchestrator scaffold for sending automation emails through `sentinel@oligarchyservices.com`.
- 2026-06-21: Split portal setup into `/install.php`, `/update.php`, and `/repair.php` so first setup, schema updates, and missing-config repair use separate flows.
- 2026-06-20: Aligned README architecture and deployment notes with the PHP/MySQL client portal documented in `HOSTINGER.md`.
- 2026-06-20: Added a Hostinger-compatible static client login page at `/login.html` with responsive styling and browser-side validation.
- 2026-06-20: Updated `robots.txt` and `sitemap.xml` to use the live Hostinger sandbox domain.
- 2026-06-18: Updated the homepage hero consultation button to use the same red as the top navigation Get Quote button.
- 2026-06-18: Removed the duplicate homepage hero tagline and visible homepage header brand text so the hero and navigation match the requested layout.
