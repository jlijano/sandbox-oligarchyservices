<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/access-management.php';

$user = access_admin_user();
$pdo = db();
access_management_ensure_schema($pdo);

$role = strtolower((string) ($user['role'] ?? 'admin'));
$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$initials = strtoupper(substr($displayName, 0, 1));
$roleLabel = ucfirst($role);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Agents | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-playground-agents">
    <script defer src="/assets/dashboard.js?v=20260621-sidebar-dropdowns"></script>
  </head>
  <body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <?php access_sidebar('agents', $roleLabel); ?>
      <div class="sidebar-backdrop" data-sidebar-backdrop></div>
      <div class="dashboard-main">
        <header class="dashboard-topbar">
          <div class="topbar-left"><button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button><div><p class="eyebrow">Playground</p><h1 data-section-title>Agents</h1></div></div>
          <div class="topbar-actions"><span class="role-pill"><?= e($roleLabel) ?></span><div class="user-chip" title="<?= e($user['email']) ?>"><span><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($user['email']) ?></small></div></div><a class="logout-link" href="/logout.php">Log out</a></div>
        </header>
        <main class="dashboard-content">
          <section class="dashboard-section is-active" data-dashboard-section data-section-label="Agents">
            <div class="dashboard-hero compact-hero">
              <div><p class="eyebrow">Playground</p><h2>Agents</h2><p>Agent playground tools can be added here as they are defined.</p></div>
            </div>
            <div class="admin-panel"><h3>Agents</h3><p class="empty-state">No agent playground tools are configured yet.</p></div>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>
