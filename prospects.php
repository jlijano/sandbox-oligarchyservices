<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/access-management.php';

$user = require_login();
$role = strtolower((string) ($user['role'] ?? 'client'));
if (!in_array($role, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo 'Only admins and editors can access prospects.';
    exit;
}

$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$initials = strtoupper(substr($displayName, 0, 1));
$roleLabel = ucfirst($role);

$prospects = [
    ['company' => 'Northstar Logistics', 'contact' => 'Maya Chen', 'email' => 'maya.chen@northstar.example', 'phone' => '(312) 555-0148', 'source' => 'Referral', 'status' => 'Qualified', 'priority' => 'High', 'value' => 42000, 'owner' => 'Avery', 'owner_initials' => 'AV', 'follow_up' => '2026-06-22', 'activity' => 'Discovery call completed', 'notes' => 'Needs implementation plan and security overview.'],
    ['company' => 'Cobalt Harbor Group', 'contact' => 'Julian Price', 'email' => 'julian.price@cobalt.example', 'phone' => '(646) 555-0182', 'source' => 'Website', 'status' => 'Proposal', 'priority' => 'High', 'value' => 68500, 'owner' => 'Sentinel', 'owner_initials' => 'SE', 'follow_up' => '2026-06-24', 'activity' => 'Proposal sent', 'notes' => 'Review managed services scope and Q3 launch timing.'],
    ['company' => 'Verdant Ridge Health', 'contact' => 'Samira Holt', 'email' => 'samira.holt@verdant.example', 'phone' => '(202) 555-0134', 'source' => 'Partner', 'status' => 'Contacted', 'priority' => 'Medium', 'value' => 27500, 'owner' => 'Morgan', 'owner_initials' => 'MO', 'follow_up' => '2026-06-25', 'activity' => 'Intro email opened', 'notes' => 'Follow up with compliance-friendly automation examples.'],
    ['company' => 'Atlas Foundry', 'contact' => 'Nolan Reed', 'email' => 'nolan.reed@atlas.example', 'phone' => '(415) 555-0199', 'source' => 'Outbound', 'status' => 'New', 'priority' => 'Low', 'value' => 18000, 'owner' => 'Riley', 'owner_initials' => 'RI', 'follow_up' => '2026-06-27', 'activity' => 'Added from target account list', 'notes' => 'Research operations stack before outreach.'],
    ['company' => 'Signal Craft Studio', 'contact' => 'Elena Ortiz', 'email' => 'elena.ortiz@signalcraft.example', 'phone' => '(512) 555-0120', 'source' => 'Event', 'status' => 'Negotiation', 'priority' => 'High', 'value' => 53000, 'owner' => 'Avery', 'owner_initials' => 'AV', 'follow_up' => '2026-06-23', 'activity' => 'Contract terms discussed', 'notes' => 'Waiting on procurement review.'],
    ['company' => 'Prairie Byte Works', 'contact' => 'Drew Lambert', 'email' => 'drew.lambert@prairie.example', 'phone' => '(773) 555-0166', 'source' => 'Webinar', 'status' => 'Won', 'priority' => 'Medium', 'value' => 36000, 'owner' => 'Sentinel', 'owner_initials' => 'SE', 'follow_up' => '2026-06-30', 'activity' => 'Kickoff scheduled', 'notes' => 'Convert to onboarding after kickoff.'],
    ['company' => 'Blue Ash Capital', 'contact' => 'Iris Monroe', 'email' => 'iris.monroe@blueash.example', 'phone' => '(617) 555-0117', 'source' => 'Website', 'status' => 'Lost', 'priority' => 'Low', 'value' => 22000, 'owner' => 'Morgan', 'owner_initials' => 'MO', 'follow_up' => '2026-07-08', 'activity' => 'Chose internal team', 'notes' => 'Set nurture reminder for next quarter.'],
    ['company' => 'Keystone Civic Labs', 'contact' => 'Andre Bell', 'email' => 'andre.bell@keystone.example', 'phone' => '(215) 555-0175', 'source' => 'Referral', 'status' => 'Qualified', 'priority' => 'Medium', 'value' => 31000, 'owner' => 'Riley', 'owner_initials' => 'RI', 'follow_up' => '2026-07-02', 'activity' => 'Requirements mapped', 'notes' => 'Needs phased budget option.'],
];

$statuses = ['New', 'Contacted', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'];
$kanbanStatuses = ['New', 'Contacted', 'Qualified', 'Proposal', 'Negotiation', 'Won'];
$owners = array_values(array_unique(array_column($prospects, 'owner')));
sort($owners);

$totalProspects = count($prospects);
$qualifiedLeads = count(array_filter($prospects, fn(array $p): bool => in_array($p['status'], ['Qualified', 'Proposal', 'Negotiation'], true)));
$pipelineValue = array_sum(array_column($prospects, 'value'));
$followUpsDue = count(array_filter($prospects, fn(array $p): bool => $p['follow_up'] <= '2026-06-25' && !in_array($p['status'], ['Won', 'Lost'], true)));
$wonCount = count(array_filter($prospects, fn(array $p): bool => $p['status'] === 'Won'));
$conversionRate = $totalProspects > 0 ? (int) round(($wonCount / $totalProspects) * 100) : 0;

function money_value(int $value): string
{
    return '$' . number_format($value);
}

function prospect_timeline_group(string $date): string
{
    if ($date === '2026-06-22') return 'Today';
    if ($date <= '2026-06-28') return 'This week';
    return 'Later';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Prospects | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-blogs-nav">
    <link rel="stylesheet" href="/assets/prospects.css?v=20260622-prospects">
    <script defer src="/assets/dashboard.js?v=20260621-settings-modules"></script>
    <script defer src="/assets/prospects.js?v=20260622-prospects"></script>
  </head>
  <body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <?php access_sidebar('prospects', $roleLabel, $role); ?>
      <div class="sidebar-backdrop" data-sidebar-backdrop></div>
      <div class="dashboard-main">
        <header class="dashboard-topbar">
          <div class="topbar-left"><button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button><div><p class="eyebrow">Playground</p><h1 data-section-title>Prospects</h1></div></div>
          <div class="topbar-actions"><span class="role-pill"><?= e($roleLabel) ?></span><div class="user-chip" title="<?= e($user['email']) ?>"><span><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($user['email']) ?></small></div></div><a class="logout-link" href="/logout.php">Log out</a></div>
        </header>

        <main class="dashboard-content prospects-workspace">
          <header class="dashboard-hero compact-hero prospects-header">
            <div><p class="eyebrow">Playground</p><h2>Prospects</h2><p>Manage potential leads, CRM opportunities, follow-ups, and pipeline activity from one flexible workspace.</p></div>
            <div class="hero-actions"><button class="primary-action" type="button">Add Prospect</button><button class="secondary-action" type="button">Import Leads</button></div>
          </header>

          <nav class="prospect-view-switcher" aria-label="Prospect views">
            <button class="is-active" type="button" data-prospect-view-button="list">List</button>
            <button type="button" data-prospect-view-button="kanban">Kanban</button>
            <button type="button" data-prospect-view-button="timeline">Timeline</button>
            <button type="button" data-prospect-view-button="dashboard">Dashboard</button>
          </nav>

          <section class="prospect-view is-active" id="prospect-list" data-prospect-view="list">
            <form class="admin-panel prospect-filters" data-prospect-filters>
              <label>Search prospects<input type="search" data-prospect-search placeholder="Company, contact, source"></label>
              <label>Status<select data-prospect-status><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?></select></label>
              <label>Owner<select data-prospect-owner><option value="">All owners</option><?php foreach ($owners as $owner): ?><option value="<?= e($owner) ?>"><?= e($owner) ?></option><?php endforeach; ?></select></label>
              <label>Priority<select data-prospect-priority><option value="">All priorities</option><option>High</option><option>Medium</option><option>Low</option></select></label>
            </form>
            <div class="admin-panel table-panel">
              <div class="table-heading"><h3>Lead pipeline</h3><span data-prospect-result-count><?= e((string) $totalProspects) ?> prospects</span></div>
              <div class="table-scroll">
                <table class="data-table prospects-table">
                  <thead><tr><th>Prospect / company</th><th>Contact</th><th>Email</th><th>Phone</th><th>Lead source</th><th>Status</th><th>Priority</th><th>Estimated value</th><th>Owner</th><th>Next follow-up</th><th>Last activity</th><th>Notes</th></tr></thead>
                  <tbody>
                    <?php foreach ($prospects as $prospect): ?>
                      <tr data-prospect-row data-search="<?= e(strtolower(implode(' ', $prospect))) ?>" data-status="<?= e($prospect['status']) ?>" data-owner="<?= e($prospect['owner']) ?>" data-priority="<?= e($prospect['priority']) ?>">
                        <td><strong><?= e($prospect['company']) ?></strong><small><?= e($prospect['notes']) ?></small></td>
                        <td><?= e($prospect['contact']) ?></td>
                        <td><a href="mailto:<?= e($prospect['email']) ?>"><?= e($prospect['email']) ?></a></td>
                        <td class="nowrap"><?= e($prospect['phone']) ?></td>
                        <td><?= e($prospect['source']) ?></td>
                        <td><span class="prospect-pill status-<?= e(strtolower(str_replace(' ', '-', $prospect['status']))) ?>"><?= e($prospect['status']) ?></span></td>
                        <td><span class="prospect-pill priority-<?= e(strtolower($prospect['priority'])) ?>"><?= e($prospect['priority']) ?></span></td>
                        <td class="numeric-cell"><?= e(money_value((int) $prospect['value'])) ?></td>
                        <td><?= e($prospect['owner']) ?></td>
                        <td class="nowrap"><?= e($prospect['follow_up']) ?></td>
                        <td><?= e($prospect['activity']) ?></td>
                        <td><button class="table-action" type="button">Open</button></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <section class="prospect-view" id="prospect-kanban" data-prospect-view="kanban" hidden>
            <div class="prospect-board" aria-label="Prospect pipeline board">
              <?php foreach ($kanbanStatuses as $status): $cards = array_values(array_filter($prospects, fn(array $p): bool => $p['status'] === $status)); ?>
                <section class="kanban-column">
                  <div class="kanban-column-header"><h3><?= e($status) ?></h3><span><?= e((string) count($cards)) ?></span></div>
                  <?php foreach ($cards as $card): ?>
                    <article class="kanban-card">
                      <div><strong><?= e($card['company']) ?></strong><small><?= e($card['contact']) ?></small></div>
                      <div class="kanban-card-meta"><span><?= e(money_value((int) $card['value'])) ?></span><span class="prospect-pill priority-<?= e(strtolower($card['priority'])) ?>"><?= e($card['priority']) ?></span></div>
                      <div class="kanban-card-footer"><span><?= e($card['follow_up']) ?></span><span class="owner-avatar"><?= e($card['owner_initials']) ?></span></div>
                    </article>
                  <?php endforeach; ?>
                </section>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="prospect-view" id="prospect-timeline" data-prospect-view="timeline" hidden>
            <div class="timeline-groups">
              <?php foreach (['Today', 'This week', 'Later'] as $group): ?>
                <section class="admin-panel timeline-group">
                  <div class="table-heading"><h3><?= e($group) ?></h3><span>Follow-ups</span></div>
                  <?php foreach ($prospects as $prospect): ?>
                    <?php if (prospect_timeline_group($prospect['follow_up']) !== $group) continue; ?>
                    <article class="timeline-item">
                      <div class="timeline-date"><?= e($prospect['follow_up']) ?></div>
                      <div><strong><?= e($prospect['company']) ?></strong><small><?= e($prospect['status']) ?> &middot; <?= e($prospect['contact']) ?></small></div>
                      <span>Follow-up</span>
                      <span><?= e($prospect['owner']) ?></span>
                      <span class="prospect-pill status-<?= e(strtolower(str_replace(' ', '-', $prospect['status']))) ?>"><?= e($prospect['status']) ?></span>
                    </article>
                  <?php endforeach; ?>
                </section>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="prospect-view" id="prospect-dashboard" data-prospect-view="dashboard" hidden>
            <div class="section-heading-row"><div><p class="eyebrow">Configurable workspace</p><h2>Prospects dashboard</h2></div><button class="secondary-action" type="button" data-customize-dashboard>Customize dashboard</button></div>
            <section class="prospect-widget-grid" aria-label="Prospect dashboard widgets">
              <?php
                $widgets = [
                    ['Total prospects', (string) $totalProspects, 'All active sample records'],
                    ['Qualified leads', (string) $qualifiedLeads, 'Qualified through negotiation'],
                    ['Pipeline value', money_value($pipelineValue), 'Estimated opportunity value'],
                    ['Follow-ups due', (string) $followUpsDue, 'Due today or this week'],
                    ['Conversion rate', $conversionRate . '%', 'Won against all prospects'],
                    ['Leads by source', 'Referral 2', 'Website, partner, event, outbound, webinar'],
                    ['Pipeline by status', '6 stages', 'New through won'],
                    ['Top owners', 'Avery + Sentinel', 'Highest current value'],
                    ['Recent activity', '8 updates', 'Latest sample lead activity'],
                ];
              ?>
              <?php foreach ($widgets as $widget): ?>
                <article class="admin-panel prospect-widget" data-prospect-widget>
                  <div class="widget-actions"><button type="button" title="Move widget">Move</button><button type="button" title="Edit widget">Edit</button><button type="button" title="Remove widget">Remove</button></div>
                  <span><?= e($widget[0]) ?></span><strong><?= e($widget[1]) ?></strong><p><?= e($widget[2]) ?></p>
                </article>
              <?php endforeach; ?>
            </section>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>
