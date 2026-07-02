# Carrier Hostinger IMAP Sync

Carrier can import messages from a Hostinger mailbox through the authenticated `/carrier-sync.php` page.

## Hostinger settings

Use Hostinger Email's IMAP settings:

- Host: `imap.hostinger.com`
- Port: `993`
- Encryption: SSL
- Username: the full email address

## Server setup

1. In Hostinger, enable the PHP `imap` extension for the site.
2. Set these environment variables on the live server:
   - `CARRIER_IMAP_USERNAME`: full Hostinger mailbox address.
   - `CARRIER_IMAP_PASSWORD`: mailbox password. Keep this out of GitHub.
3. Optional environment variables:
   - `CARRIER_IMAP_HOST`: defaults to `imap.hostinger.com`.
   - `CARRIER_IMAP_PORT`: defaults to `993`.
   - `CARRIER_IMAP_FLAGS`: defaults to `/imap/ssl`.
   - `CARRIER_IMAP_MAILBOX`: defaults to `INBOX`.
   - `CARRIER_IMAP_LIMIT`: newest messages checked per sync, default `50`, maximum `200`.
4. Log in as an admin or editor and open `/carrier-sync.php`.
5. Click **Sync Hostinger Mail**. New imported messages appear in `/carrier`.

The sync stores `source_mailbox`, `source_uid`, and `source_message_id` metadata on imported rows so later syncs skip messages that were already imported. Attachments are not downloaded into public storage; visible attachment names are stored as metadata when available.
