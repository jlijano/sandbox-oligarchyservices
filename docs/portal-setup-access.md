# Portal setup access controls

`install.php` and manual `repair.php` are intentionally locked unless a temporary server-side unlock is present.

This protects first install, database reconnection, and optional admin recovery from being exposed just because the PHP files exist in `public_html`.

## First install

Before opening `/install.php`, temporarily enable one of these on Hostinger:

- Environment variable: `OLIGARCHY_INSTALL_UNLOCK=1`
- File: `oligarchy-install.unlock` beside the persistent database config backup outside `public_html`

Remove the environment variable or unlock file after install succeeds.

## Manual repair

Automatic restore from an existing persistent database config backup does not require an unlock.

If `includes/config.php` is missing and manual repair is needed, temporarily enable one of these before opening `/repair.php`:

- Environment variable: `OLIGARCHY_REPAIR_UNLOCK=1`
- File: `oligarchy-repair.unlock` beside the persistent database config backup outside `public_html`

Remove the environment variable or unlock file after repair succeeds.

## Normal updates

`update.php` remains the normal maintenance path after deployment. It still requires an authenticated admin session and CSRF token.

Do not commit generated config files, lock files, unlock files, database passwords, or Hostinger environment values to GitHub.
