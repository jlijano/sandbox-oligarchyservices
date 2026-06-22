# Account Confirmation Email Setup

The portal sends new-user confirmation emails from the account-confirmation helper loaded by `includes/bootstrap.php`.

## Hostinger config file fallback

If the PHP host does not provide environment variables, create this file on the live Hostinger server:

```text
includes/mail-config.php
```

Use `includes/mail-config.sample.php` as the template:

```php
<?php
return [
    'portal_base_url' => 'https://sandbox.oligarchyservices.com',
    'portal_mail_from' => 'sentinel@oligarchyservices.com',
    'portal_mail_orchestrator_url' => 'https://your-orchestrator-service.example.com',
    'portal_mail_orchestrator_token' => 'replace-with-the-orchestrator-token',
];
```

Replace `portal_mail_orchestrator_url` with the deployed Node orchestrator base URL. Do not include `/send-email`; the PHP helper appends it automatically.

Replace `portal_mail_orchestrator_token` with the same secret value as `ORCHESTRATOR_TOKEN` on the Node orchestrator service.

The real `includes/mail-config.php` file is ignored by Git and must not be committed because it contains a live token.

## Portal environment variables

If your PHP host supports environment variables, use these instead of `includes/mail-config.php`:

```env
PORTAL_BASE_URL=https://sandbox.oligarchyservices.com
PORTAL_MAIL_FROM=sentinel@oligarchyservices.com
PORTAL_MAIL_ORCHESTRATOR_URL=https://your-orchestrator-host.example.com
PORTAL_MAIL_ORCHESTRATOR_TOKEN=store-this-only-as-a-host-secret
```

`PORTAL_MAIL_ORCHESTRATOR_URL` should be the base URL of the deployed orchestrator service. The PHP helper appends `/send-email` automatically.

Do not commit `PORTAL_MAIL_ORCHESTRATOR_TOKEN`, mailbox passwords, app passwords, SMTP credentials, or live secret values to GitHub.

## Orchestrator environment variables

Set these in the Node orchestrator service host:

```env
MAIL_FROM=sentinel@oligarchyservices.com
MAIL_USERNAME=sentinel@oligarchyservices.com
MAIL_PASSWORD=store-this-only-as-a-host-secret
MAIL_DEFAULT_TO=jlijano@gmail.com

SMTP_HOST=smtp.hostinger.com
SMTP_PORT=465
SMTP_SECURE=true

ORCHESTRATOR_TOKEN=store-this-only-as-a-host-secret
DRY_RUN=false
```

`PORTAL_MAIL_ORCHESTRATOR_TOKEN` on the PHP portal must match `ORCHESTRATOR_TOKEN` on the orchestrator service.

## Smoke test

1. Confirm the orchestrator `/health` endpoint returns `ok: true`.
2. Keep `DRY_RUN=true` for the first `/send-email` test.
3. Set `DRY_RUN=false` only after the dry-run request succeeds.
4. Create a test user from `/users.php` or `/dashboard.php#users`.
5. Check the portal Mail Trace section for a `sent` or `failed` record.
6. Check the recipient inbox and spam folder.
