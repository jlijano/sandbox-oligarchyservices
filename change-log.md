# Change Log

## 2026-06-30

- Aligned README deployment notes with the current prospects, Carrier, Switchboard, automation, and portal update flow documented in `HOSTINGER.md`.

## 2026-06-28

- Made public blog listing, detail, and dynamic sitemap routes read-only.
- Refined CI reference checks and production availability diagnostics.
- Removed the Sentinel mail orchestrator from account confirmations; the portal now uses PHP `mail()` only for account confirmation emails.

## 2026-06-22

- Added a polished `career-timeline.html` page for Jan Christian L.'s LinkedIn work history using the current site design system.
- Added request timeline updates with client-visible comments and internal-only staff notes.

## 2026-06-21

- Added a Client Requests portal module for authenticated client submissions and admin/support/editor queue management.
- Added generated temporary passwords to account confirmation emails for new portal users.
- Required newly confirmed portal users to create their own password before dashboard access.
- Added email confirmation for admin-created portal users before first login.
- Hardened PHP portal config persistence so Hostinger file syncs do not require database reinstallation when `includes/config.php` is removed.
- Split portal setup into `/install.php`, `/update.php`, and `/repair.php` so first setup, schema updates, and missing-config repair use separate flows.

## 2026-06-20

- Aligned README architecture and deployment notes with the PHP/MySQL client portal documented in `HOSTINGER.md`.
- Added a Hostinger-compatible static client login page at `/login.html` with responsive styling and browser-side validation.
- Updated `robots.txt` and `sitemap.xml` to use the live Hostinger sandbox domain.

## 2026-06-18

- Updated the homepage hero consultation button to use the same red as the top navigation Get Quote button.
- Removed the duplicate homepage hero tagline under the main `Technology + People` headline.
- Removed the visible homepage header brand text while preserving the home link's accessible label.
