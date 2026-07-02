# Carrier Hostinger IMAP Setup

Carrier can import messages from a Hostinger mailbox through the authenticated `/carrier-sync.php` page. Admins and editors can set up the mailbox manually in the portal, then sync mail on demand.

## Hostinger settings

Use Hostinger Email's IMAP settings:

- Host: `imap.hostinger.com`
- Port: `993`
- Encryption: SSL
- Username: the full email address

## Manual portal setup

1. In Hostinger, enable the PHP `imap` extension for the site.
2. Log in to the portal as an admin or editor.
3. Open `/carrier-sync.php`.
4. Enter the Hostinger mailbox email address and password.
5. Leave these defaults unless Hostinger gives different values:
   - IMAP host: `imap.hostinger.com`
   - Port: `993`
   - Security flags: `/imap/ssl`
   - Mailbox: `INBOX`
   - Messages to check: `50`
6. Click **Save and sync now**. New imported messages appear in `/carrier`.

The mailbox password is stored encrypted in the portal database. For stronger key separation, set `CARRIER_SETTINGS_KEY` in the hosting environment before saving the mailbox. If that key is not set, the portal uses its existing server-side database configuration as the encryption key source.

## Optional environment fallback

Manual setup is preferred. These environment variables are still supported as a fallback or override source:

- `CARRIER_IMAP_USERNAME`: full Hostinger mailbox address.
- `CARRIER_IMAP_PASSWORD`: mailbox password.
- `CARRIER_IMAP_HOST`: defaults to `imap.hostinger.com`.
- `CARRIER_IMAP_PORT`: defaults to `993`.
- `CARRIER_IMAP_FLAGS`: defaults to `/imap/ssl`.
- `CARRIER_IMAP_MAILBOX`: defaults to `INBOX`.
- `CARRIER_IMAP_LIMIT`: newest messages checked per sync, default `50`, maximum `200`.

The sync stores `source_mailbox`, `source_uid`, and `source_message_id` metadata on imported rows so later syncs skip messages that were already imported. Attachments are not downloaded into public storage; visible attachment names are stored as metadata when available.
