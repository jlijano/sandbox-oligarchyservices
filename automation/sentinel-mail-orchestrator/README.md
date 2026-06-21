# Sentinel Mail Orchestrator

Small HTTP service for sending Sentinel automation emails through the Hostinger mailbox `sentinel@oligarchyservices.com`.

This service is intentionally separate from the public Hostinger website. Do not upload this directory to `public_html`. Deploy it as a private automation service on a host that can run Node.js, such as Render, Fly.io, Railway, Cloud Run, or another HTTPS-capable platform.

## What it does

- Sends email through Hostinger SMTP.
- Uses `sentinel@oligarchyservices.com` as the sender.
- Defaults recipients to `jlijano@gmail.com` when no `to` value is provided.
- Requires a bearer token for send requests.
- Starts in dry-run mode by default so the first deployment cannot send real mail accidentally.

## What it does not do yet

- It does not read IMAP inbox replies.
- It does not expose an MCP endpoint yet.
- It does not delete, archive, label, or move messages.

Those should be added only after send-only behavior is deployed and verified.

## Environment

Set these values in the deployment host. Never commit real secrets.

```env
PORT=8787
DRY_RUN=true
ORCHESTRATOR_TOKEN=replace-with-a-long-random-secret

MAIL_FROM=sentinel@oligarchyservices.com
MAIL_USERNAME=sentinel@oligarchyservices.com
MAIL_PASSWORD=store-this-only-as-a-host-secret
MAIL_DEFAULT_TO=jlijano@gmail.com

SMTP_HOST=smtp.hostinger.com
SMTP_PORT=465
SMTP_SECURE=true
```

Use `env.sample` as the non-secret template.

## Local install

```sh
npm install
npm run check
npm start
```

## Health check

```sh
curl http://localhost:8787/health
```

Expected response:

```json
{
  "ok": true,
  "service": "sentinel-mail-orchestrator",
  "dryRun": true
}
```

## Send request

Dry-run mode returns success without sending real email.

```sh
curl -X POST http://localhost:8787/send-email \
  -H "Authorization: Bearer $ORCHESTRATOR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "jlijano@gmail.com",
    "subject": "Sentinel test email",
    "text": "This is a Sentinel mail orchestrator test."
  }'
```

To send real mail, set `DRY_RUN=false` in the deployment host after a successful dry-run test.

## Deployment notes

1. Deploy this directory as its own Node.js service.
2. Set the environment values in the host dashboard.
3. Store `MAIL_PASSWORD` and `ORCHESTRATOR_TOKEN` only as host secrets.
4. Test `/health` first.
5. Test `/send-email` while `DRY_RUN=true`.
6. Set `DRY_RUN=false` only after the dry-run test succeeds.
7. Keep logs free of email body content, mailbox passwords, reset links, and tokens.

## Next additions

Recommended next steps:

1. Add IMAP read-only tools for approval replies.
2. Add an MCP endpoint so ChatGPT/Sentinel can call the orchestrator as a connector.
3. Keep destructive mailbox actions, such as delete or archive, approval-gated.
