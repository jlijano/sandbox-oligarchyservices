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
- Public blog listing, detail, and dynamic sitemap routes are read-only. They read published blog rows when the database is available, but they do not create or alter blog tables.
- Authenticated clients can submit and view service requests at `/requests.php`; admin, editor, and support users can manage the full request queue and timeline updates.
- Admin and editor users can manage prospects, Carrier records, Switchboard conversations, CMS pages, blog posts, automation recipes, access records, and portal settings after the relevant schema updates are run.
- Admin-created portal users receive an account confirmation email, then create their own password from the confirmation link before signing in.
- The installer writes `includes/config.php` and `includes/installed.lock` on the live server; those generated files must not be committed to GitHub.
- The installer also writes persistent database config backups outside `public_html` when Hostinger file permissions allow it, so full file syncs do not force a database reinstall.
- The portal can also read `DB_HOST`, `DB_DATABASE`/`DB_NAME`, `DB_USERNAME`/`DB_USER`, `DB_PASSWORD`, and optional `DB_PORT` environment variables.

## Hostinger deployment

This project can be uploaded directly to Hostinger shared web hosting. Place the
public website files in the domain's `public_html` directory. No Node.js runtime,
package installation, or build command is required for the public website.

For static-only preview use, `login.html` can show the login UI without checking
credentials. For real client access, upload the PHP portal files and run the
one-time installer described in `HOSTINGER.md`.

Before going live:

1. Confirm `robots.txt`, `sitemap.xml`, and `sitemap.php` use the production domain.
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
   `carrier.php`, `switchboard.php`, `prospects.php`, `logout.php`,
   `privacy.html`, `career-timeline.html`, `account-confirmation.php`,
   `change-password.php`, and one missing URL after upload.

See `HOSTINGER.md` for the full checklist.

## Portal setup pages

- `/install.php`: first-time portal setup. Creates the server-local database
  config, required tables, and first admin account.
- `/update.php`: logged-in admin maintenance page. Applies safe, non-destructive
  table updates after CMS, blog, access-management, prospect, Carrier,
  Switchboard, automation, or client request code changes.
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

Newly created users receive an account confirmation link by email. They open the
link to confirm their email address and create their own password before signing
in. Confirmation emails intentionally do not include temporary passwords because
mail relays can classify credential-bearing messages as unsafe.

Confirmation links expire after 48 hours. The portal sends confirmation email
through PHP `mail()`. The sender defaults to
`no-reply@sandbox.oligarchyservices.com`; set `PORTAL_MAIL_FROM` to use a
different domain mailbox. Set `PORTAL_BASE_URL` when the portal runs behind a
proxy or when generated email links must always use the production domain.

For reliable Gmail delivery, configure SPF and DKIM for the sender mailbox/domain
in Hostinger and publish a DMARC record that aligns with the visible From domain.
The PHP mail call also requests the configured sender as the envelope sender, but
DNS authentication still needs to be correct at the hosting/domain level.

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

## Blog and sitemap behavior

`blogs.php`, `blog.php`, and `sitemap.php` are public read paths. They may open a
database connection and query published blog rows, but they must not create,
alter, or repair the blog schema during public requests. Run `/update.php` as an
admin after blog or CMS code deployments so the required blog tables and indexes
exist before visitors or crawlers request those public routes.

`robots.txt` references both the static `sitemap.xml` and dynamic `sitemap.php`.
`sitemap.php` keeps the same core public URLs as `sitemap.xml` and adds published
blog detail URLs from the portal database. If the database is unavailable or the
blog table is missing, the dynamic sitemap still returns the static public URLs
and logs the blog lookup error server-side.

## CI validation

`.github/workflows/validate.yml` runs repository reference checks and production
availability diagnostics. The repository check validates local public references
while allowing known generated server files such as `includes/config.php` and
`includes/installed.lock`. The availability job records DNS, TLS, HTTP status,
and response-preview diagnostics for public and protected routes. Curl tunnel
failures or 403 responses from the GitHub runner are reported as diagnostics and
should be verified from a browser or Hostinger logs before treating production as
down.

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

- 2026-07-03: Changed account confirmations to email only a confirmation link; users now create their own password from the confirmation page, and the PHP mail envelope sender uses the configured sender address.
- 2026-06-30: Aligned README deployment notes with the current prospects, Carrier, Switchboard, automation, and portal update flow documented in `HOSTINGER.md`.
- 2026-06-28: Made public blog listing, detail, and dynamic sitemap routes read-only and refined CI reference checks plus availability diagnostics.
- 2026-06-28: Removed the Sentinel mail orchestrator from account confirmations; the portal now uses PHP `mail()` only for account confirmation emails.
- 2026-06-22: Added a polished `career-timeline.html` page for Jan Christian L.'s LinkedIn work history using the current site design system.
- 2026-06-22: Added request timeline updates with client-visible comments and internal-only staff notes.
- 2026-06-21: Added a Client Requests portal module for authenticated client submissions and admin/support/editor queue management.
- 2026-06-21: Added generated temporary passwords to account confirmation emails for new portal users.
- 2026-06-21: Required newly confirmed portal users to create their own password before dashboard access.
- 2026-06-21: Added email confirmation for admin-created portal users before first login.
- 2026-06-21: Hardened PHP portal config persistence so Hostinger file syncs do not require database reinstallation when `includes/config.php` is removed.
- 2026-06-21: Split portal setup into `/install.php`, `/update.php`, and `/repair.php` so first setup, schema updates, and missing-config repair use separate flows.
- 2026-06-20: Aligned README architecture and deployment notes with the PHP/MySQL client portal documented in `HOSTINGER.md`.
- 2026-06-20: Added a Hostinger-compatible static client login page at `/login.html` with responsive styling and browser-side validation.
- 2026-06-20: Updated `robots.txt` and `sitemap.xml` to use the live Hostinger sandbox domain.
- 2026-06-18: Updated the homepage hero consultation button to use the same red as the top navigation Get Quote button.
- 2026-06-18: Removed the duplicate homepage hero tagline and visible homepage header brand text so the hero and navigation match the requested layout.
