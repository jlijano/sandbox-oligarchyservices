# Sentinel Report Archive

Sentinel approval and maintenance reports should be archived as durable operational records after each scheduled repository pass.

## Purpose

The archive keeps a concise history of what Sentinel checked, what it recommended, which approval token was issued, and what action was taken. It is meant for repository operations continuity, not for secrets or raw production logs.

## Recommended archive path

Store generated report copies outside Hostinger `public_html` when the automation host supports persistent storage. A safe default path is:

```text
sentinel-reports/YYYY/MM/YYYY-MM-DDTHH-mm-ssZ-token.md
```

For example:

```text
sentinel-reports/2026/06/2026-06-28T10-17-00Z-SENTINEL-20260628-1017-c5bafb58.md
```

If the automation host does not provide durable storage, copy the report into the workspace memory process used by Sentinel and summarize the archive location in the next maintenance report.

## What to include

Each archived report should include:

- repository name and default branch;
- run time in UTC;
- approval token;
- scope checked;
- findings grouped by approved, held, declined, and awaiting approval;
- low-risk fixes applied, including commit SHAs when available;
- high-risk items that still need explicit approval;
- checks that could not run and why.

## What not to include

Do not archive or commit:

- passwords, API keys, deploy hooks, cookies, 2FA codes, or recovery codes;
- database credentials or generated `includes/config.php` contents;
- mailbox passwords or orchestrator bearer tokens;
- full raw server logs that may contain private data;
- private customer data unrelated to the repository maintenance decision.

## Retention notes

Keep the archive concise enough to review quickly. Prefer one report per scheduled run, and update the pending-approvals memory file only with current unresolved items rather than copying every historical report there.

## GitHub repository note

Do not commit live generated report archives to this repository unless the user explicitly asks for a public, sanitized sample. This file documents the process; actual operational reports should live in Sentinel's durable private workspace or the automation host's private persistent storage.
