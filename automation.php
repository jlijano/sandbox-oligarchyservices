<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/access-management.php';

$user = require_login();
$role = strtolower((string) ($user['role'] ?? 'client'));
if (!in_array($role, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo 'Only admins and editors can manage automation.';
    exit;
}

$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$initials = strtoupper(substr($displayName, 0, 1));
$roleLabel = ucfirst($role);
$recipes = [
    ['name' => 'New client onboarding', 'trigger' => 'When a client account is confirmed', 'condition' => 'Client status is active', 'action' => 'Create onboarding task, notify owner, and schedule follow-up', 'importance' => 'Critical'],
    ['name' => 'Blog publish review', 'trigger' => 'When a blog status changes to published', 'condition' => 'Author is editor or admin', 'action' => 'Notify operations and log a content release event', 'importance' => 'Medium'],
    ['name' => 'Account email trace alert', 'trigger' => 'When an email send attempt fails', 'condition' => 'Provider returns failed status', 'action' => 'Create an activity event and assign review to support', 'importance' => 'High'],
];
$runs = [
    ['flow' => 'New client onboarding', 'status' => 'Ready', 'last_run' => 'Not run yet', 'owner' => 'Operations'],
    ['flow' => 'Blog publish review', 'status' => 'Draft', 'last_run' => 'Not run yet', 'owner' => 'Content'],
    ['flow' => 'Account email trace alert', 'status' => 'Draft', 'last_run' => 'Not run yet', 'owner' => 'Support'],
];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Automation | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-automation">
    <script defer src="/assets/dashboard.js?v=20260621-automation"></script>
  </head>
  <body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <?php access_sidebar('automation', $roleLabel, $role); ?>
      <div class="sidebar-backdrop" data-sidebar-backdrop></div>
      <div class="dashboard-main">
        <header class="dashboard-topbar">
          <div class="topbar-left"><button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button><div><p class="eyebrow">Playground</p><h1 data-section-title>Automation</h1></div></div>
          <div class="topbar-actions"><span class="role-pill"><?= e($roleLabel) ?></span><div class="user-chip" title="<?= e($user['email']) ?>"><span><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($user['email']) ?></small></div></div><a class="logout-link" href="/logout.php">Log out</a></div>
        </header>
        <main class="dashboard-content">
          <section class="dashboard-section is-active" data-dashboard-section data-section-label="Automation">
            <div class="dashboard-hero compact-hero">
              <div><p class="eyebrow">Workflow builder</p><h2>Automation</h2><p>Design governed trigger-condition-action workflows for portal operations, notifications, task routing, approvals, and audit-ready follow-up.</p></div>
              <div class="hero-actions"><a class="primary-action" href="#recipes">Create recipe</a><a class="secondary-action" href="#run-history">Run history</a></div>
            </div>

            <section class="section-summary-grid three-up" aria-label="Automation summary">
              <article><span>Draft recipes</span><strong>2</strong></article>
              <article><span>Ready recipes</span><strong>1</strong></article>
              <article><span>Connectors</span><strong>4</strong></article>
            </section>

            <section class="workspace-grid two-up">
              <article class="admin-panel"><div class="panel-title-row"><h3>Recipe builder</h3><span>Trigger + condition + action</span></div><div class="automation-recipe"><div><span class="status-badge">When</span><strong>Account, page, email, or schedule event occurs</strong></div><div><span class="status-badge">If</span><strong>Field values, role, status, owner, or delivery outcome match</strong></div><div><span class="status-badge">Then</span><strong>Notify, assign, update, trace, approve, or create activity</strong></div></div></article>
              <article class="admin-panel"><div class="panel-title-row"><h3>Governance</h3><span>Required controls</span></div><ul class="mini-list"><li><strong>Owner</strong><span>Every automation needs an accountable owner.</span></li><li><strong>Least privilege</strong><span>Connectors should only access the data the recipe needs.</span></li><li><strong>Failure path</strong><span>Every recipe should log failed runs and route them to review.</span></li><li><strong>Change lifecycle</strong><span>Draft, test, activate, monitor, then retire safely.</span></li></ul></article>
            </section>

            <section class="admin-panel" id="recipes">
              <div class="table-heading"><h3>Automation recipes</h3><span><?= e((string) count($recipes)) ?> templates</span></div>
              <div class="table-scroll"><table class="data-table activity-table"><thead><tr><th>Name</th><th>Trigger</th><th>Condition</th><th>Action</th><th>Importance</th></tr></thead><tbody><?php foreach ($recipes as $recipe): ?><tr><td><strong><?= e($recipe['name']) ?></strong></td><td><?= e($recipe['trigger']) ?></td><td><?= e($recipe['condition']) ?></td><td><?= e($recipe['action']) ?></td><td><span class="status-badge"><?= e($recipe['importance']) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
            </section>

            <section class="admin-panel" id="run-history">
              <div class="table-heading"><h3>Run history</h3><span>Design-time preview</span></div>
              <div class="table-scroll"><table class="data-table compact-table"><thead><tr><th>Flow</th><th>Status</th><th>Last run</th><th>Owner</th></tr></thead><tbody><?php foreach ($runs as $run): ?><tr><td><strong><?= e($run['flow']) ?></strong></td><td><span class="status-badge <?= $run['status'] === 'Ready' ? 'is-active' : 'is-muted' ?>"><?= e($run['status']) ?></span></td><td><?= e($run['last_run']) ?></td><td><?= e($run['owner']) ?></td></tr><?php endforeach; ?></tbody></table></div>
            </section>

            <section class="admin-panel">
              <div class="panel-title-row"><h3>Connector catalog</h3><span>Planned</span></div>
              <div class="quick-actions"><span class="status-badge">Portal users</span><span class="status-badge">CMS pages</span><span class="status-badge">Blogs</span><span class="status-badge">Mail trace</span><span class="status-badge">Activity log</span></div>
            </section>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>
