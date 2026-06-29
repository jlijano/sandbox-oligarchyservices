# Prospects CRM module

`/prospects.php` is an authenticated portal workspace for admin and editor users. It stores live CRM records in the portal MySQL database instead of hardcoded sample data.

## Database update

After deploying this module to Hostinger, log in as an admin and run `/update.php` once. The update path calls `prospect_ensure_schema()` and creates or updates the `prospects` table with non-destructive `CREATE TABLE IF NOT EXISTS`, missing-column checks, and missing-index checks.

The update keeps existing users, pages, blogs, settings, activity records, access-management records, client requests, and existing prospects. It does not drop, truncate, or overwrite existing portal tables.

## Table created

`prospects` includes:

- company, contact, email, phone, and source
- status: `New`, `Contacted`, `Qualified`, `Proposal`, `Negotiation`, `Won`, `Lost`
- priority: `High`, `Medium`, `Low`
- estimated value, owner, follow-up date, last activity, and notes
- created/updated user references and timestamps
- indexes for status, priority, owner, follow-up date, and update time

## Access and writes

Access stays restricted to `admin` and `editor` roles in `prospects.php`. All write actions use the existing portal CSRF helper, validated inputs, sanitized output through `e()`, and parameterized PDO statements.

Supported actions:

- create a prospect
- open/view prospect details
- edit prospect fields
- update status, priority, owner, value, follow-up date, source, notes, and last activity
- mark a prospect won or lost by changing its status
- import simple CSV lead rows

No delete action is included because destructive CRM data removal should be approval-gated.

## Import format

Use one prospect per line:

```csv
company,contact,email,phone,source,status,priority,value,owner,notes
Acme Co,Jane Doe,jane@example.com,555-0100,Website,New,Medium,12000,Avery,Needs follow-up
```

The header row is optional. Invalid email values are rejected before import.
