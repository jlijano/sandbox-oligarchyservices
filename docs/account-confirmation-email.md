# Account Confirmation Email Setup

The portal sends new-user confirmation emails from the account-confirmation helper loaded by `includes/bootstrap.php`. The current repository flow uses PHP `mail()` directly; it does not require a separate mail orchestrator service.

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
];
```

The real `includes/mail-config.php` file is ignored by Git and must not be committed if it contains live environment-specific values.

## Portal environment variables

If your PHP host supports environment variables, use these instead of `includes/mail-config.php`:

```env
PORTAL_BASE_URL=https://sandbox.oligarchyservices.com
PORTAL_MAIL_FROM=sentinel@oligarchyservices.com
```

If `PORTAL_BASE_URL` is not set, the portal builds links from the current request host. If `PORTAL_MAIL_FROM` is not set, the sender defaults to `sentinel@oligarchyservices.com`.

Do not commit mailbox passwords, app passwords, SMTP credentials, tokens, or other live secret values to GitHub.

## Hostinger mail check

1. Confirm Hostinger allows PHP mail for the domain.
2. Set `PORTAL_MAIL_FROM` only to a sender address that is valid for the hosted domain.
3. Create a test user from `/users.php` or `/dashboard.php#users`.
4. Check that the account confirmation email arrives, including the confirmation link, temporary password, and stable `/login.html` link.
5. Check the recipient inbox and spam folder.
