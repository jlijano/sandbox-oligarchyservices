<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$role = strtolower((string) ($user['role'] ?? 'client'));
$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$initials = strtoupper(substr($displayName, 0, 1));

$roleProfiles = [
    'admin' => [
        'label' => 'Admin',
        'headline' => 'Command center',
        'summary' => 'Manage portal access, review operational health, and keep client work moving.',
        'stats' => [
            ['label' => 'Active users', 'value' => '1', 'trend' => 'Installer admin ready'],
            ['label' => 'Open workstreams', 'value' => '4', 'trend' => 'Support, assets, projects, reports'],
            ['label' => 'Risk queue', 'value' => '0', 'trend' => 'No escalations logged'],
        ],
        'nav' => ['Overview', 'Users', 'Support', 'Assets', 'Reports', 'Settings'],
        'actions' => ['Invite a user', 'Review activity', 'Export audit log'],
        'cards' => [
            ['title' => 'User administration', 'body' => 'Create accounts, assign roles, and deactivate access when clients or staff change.'],
            ['title' => 'Operations visibility', 'body' => 'Track support requests, asset records, and implementation handoffs from one control plane.'],
            ['title' => 'Security controls', 'body' => 'Monitor failed logins, session activity, and account readiness before expanding the portal.'],
        ],
    ],
    'support' => [
        'label' => 'Support',
        'headline' => 'Support workspace',
        'summary' => 'Triage client requests, coordinate handoffs, and keep response activity visible.',
        'stats' => [
            ['label' => 'Assigned tickets', 'value' => '0', 'trend' => 'Ready for queue setup'],
            ['label' => 'Pending handoffs', 'value' => '0', 'trend' => 'No blocked items'],
            ['label' => 'SLA alerts', 'value' => '0', 'trend' => 'Healthy'],
        ],
        'nav' => ['Overview', 'Tickets', 'Clients', 'Assets', 'Knowledge', 'Reports'],
        'actions' => ['Create ticket', 'Open queue', 'View reports'],
        'cards' => [
            ['title' => 'Ticket triage', 'body' => 'Prioritize new requests and keep client communication in one place.'],
            ['title' => 'Client context', 'body' => 'See the systems, assets, and project notes connected to each account.'],
            ['title' => 'Escalation flow', 'body' => 'Route urgent issues to the right owner before service windows are missed.'],
        ],
    ],
    'client' => [
        'label' => 'Client',
        'headline' => 'Client workspace',
        'summary' => 'Access support, project handoffs, asset records, and reporting from a single secure portal.',
        'stats' => [
            ['label' => 'Open requests', 'value' => '0', 'trend' => 'No active tickets'],
            ['label' => 'Project updates', 'value' => '0', 'trend' => 'No pending handoffs'],
            ['label' => 'Asset records', 'value' => '0', 'trend' => 'Ready for import'],
        ],
        'nav' => ['Overview', 'Support', 'Projects', 'Assets', 'Reports', 'Account'],
        'actions' => ['Request support', 'View projects', 'Download report'],
        'cards' => [
            ['title' => 'Support requests', 'body' => 'Submit and track help desk requests as the support workflow is expanded.'],
            ['title' => 'Project handoffs', 'body' => 'Review implementation notes, next steps, and pending approvals.'],
            ['title' => 'Asset visibility', 'body' => 'Centralize device, lifecycle, and audit records as they come online.'],
        ],
    ],
];

$profile = $roleProfiles[$role] ?? $roleProfiles['client'];
$roleLabel = $profile['label'];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Dashboard | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-role-shell">
    <script defer src="/assets/dashboard.js?v=20260621-role-shell"></script>
  </head>
  <body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <aside class="dashboard-sidebar" id="portal-sidebar" aria-label="Portal navigation">
        <div class="sidebar-brand">
          <a href="/dashboard.php" aria-label="Oligarchy Services dashboard">OLIGARCHY</a>
          <button class="sidebar-collapse" type="button" data-sidebar-collapse aria-label="Collapse sidebar" aria-expanded="true">‹</button>
        </div>
        <nav class="sidebar-nav">
          <?php foreach ($profile['nav'] as $index => $item): ?>
            <a class="<?= $index === 0 ? 'is-active' : '' ?>" href="#<?= e(strtolower(str_replace(' ', '-', $item))) ?>">
              <span class="nav-icon" aria-hidden="true"><?= e(substr($item, 0, 1)) ?></span>
              <span class="nav-label"><?= e($item) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
          <span class="sidebar-status">Role</span>
          <strong><?= e($roleLabel) ?></strong>
        </div>
      </aside>

      <div class="sidebar-backdrop" data-sidebar-backdrop></div>

      <div class="dashboard-main">
        <header class="dashboard-topbar">
          <div class="topbar-left">
            <button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button>
            <div>
              <p class="eyebrow">Client portal</p>
              <h1><?= e($profile['headline']) ?></h1>
            </div>
          </div>
          <div class="topbar-actions">
            <span class="role-pill"><?= e($roleLabel) ?></span>
            <div class="user-chip" title="<?= e($user['email']) ?>">
              <span><?= e($initials) ?></span>
              <div>
                <strong><?= e($displayName) ?></strong>
                <small><?= e($user['email']) ?></small>
              </div>
            </div>
            <a class="logout-link" href="/logout.php">Log out</a>
          </div>
        </header>

        <main class="dashboard-content">
          <section class="dashboard-hero" id="overview">
            <div>
              <p class="eyebrow"><?= e($roleLabel) ?> overview</p>
              <h2>Welcome back, <?= e($displayName) ?></h2>
              <p><?= e($profile['summary']) ?></p>
            </div>
            <div class="hero-actions">
              <?php foreach ($profile['actions'] as $index => $action): ?>
                <a class="<?= $index === 0 ? 'primary-action' : 'secondary-action' ?>" href="/contact.html"><?= e($action) ?></a>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="stat-grid" aria-label="Dashboard metrics">
            <?php foreach ($profile['stats'] as $stat): ?>
              <article class="stat-card">
                <span><?= e($stat['label']) ?></span>
                <strong><?= e($stat['value']) ?></strong>
                <p><?= e($stat['trend']) ?></p>
              </article>
            <?php endforeach; ?>
          </section>

          <section class="workspace-grid">
            <?php foreach ($profile['cards'] as $card): ?>
              <article class="workspace-card">
                <h3><?= e($card['title']) ?></h3>
                <p><?= e($card['body']) ?></p>
              </article>
            <?php endforeach; ?>
          </section>

          <section class="activity-panel" aria-labelledby="activity-heading">
            <div>
              <p class="eyebrow">Activity</p>
              <h2 id="activity-heading">Portal readiness</h2>
              <p>The authentication layer is live. Next, connect real support, asset, project, and reporting data to these dashboard modules.</p>
            </div>
            <ol class="activity-list">
              <li><span></span><p>Secure login and session handling enabled.</p></li>
              <li><span></span><p>Role-based dashboard shell loaded for <?= e($roleLabel) ?> users.</p></li>
              <li><span></span><p>Next milestone: connect live portal records.</p></li>
            </ol>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>
