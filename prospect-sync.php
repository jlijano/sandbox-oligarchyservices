<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/access-management.php';
require_once __DIR__ . '/includes/prospects.php';
require_once __DIR__ . '/includes/prospect-sheet-sync.php';

$user = require_login();
$role = strtolower((string) ($user['role'] ?? 'client'));
if (!in_array($role, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo 'Only admins and editors can sync prospects.';
    exit;
}

$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$initials = strtoupper(substr($displayName, 0, 1));
$roleLabel = ucfirst($role);
$notice = '';
$error = '';
$summary = null;
$pdo = null;
$schemaReady = false;
$schemaMessage = '';

try {
    $pdo = db();
    $schemaReady = prospect_schema_ready($pdo);
} catch (Throwable $dbError) {
    error_log('Prospect sync database unavailable: ' . $dbError->getMessage());
    $schemaMessage = 'Prospect sync cannot connect to the portal database yet. Check the Hostinger database config or run repair.php if the config is missing.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo instanceof PDO || !$schemaReady) {
        $error = 'Prospects database tables are not ready. Log in as an admin and run /update.php after deployment.';
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please refresh and try again.';
    } else {
        try {
            $summary = prospect_sheet_sync($pdo, (int) $user['id']);
            prospect_log_activity($pdo, (int) $user['id'], 'prospects sheet synced', null, prospect_sheet_sync_message($summary));
            $notice = 'Google Sheet sync complete: ' . prospect_sheet_sync_message($summary);
        } catch (Throwable $syncError) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Prospect sheet sync failed: ' . $syncError->getMessage());
            $error = $syncError->getMessage();
        }
    }
}

$sources = prospect_sheet_sync_sources();
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Sync Prospects | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-blogs-nav">
    <link rel="stylesheet" href="/assets/prospects.css?v=20260629-live-crm">
    <script defer src="/assets/dashboard.js?v=20260621-settings-modules"></script>
  </head>
  <body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <?php access_sidebar('prospects', $roleLabel, $role); ?>
      <div class="sidebar-backdrop" data-sidebar-backdrop></div>
      <div class="dashboard-main">
        <header class="dashboard-topbar">
          <div class="topbar-left"><button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button><div><p class="eyebrow">Playground</p><h1>Sync Prospects</h1></div></div>
          <div class="topbar-actions"><span class="role-pill"><?= e($roleLabel) ?></span><div class="user-chip" title="<?= e($user['email']) ?>"><span><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($user['email']) ?></small></div></div><a class="logout-link" href="/logout.php">Log out</a></div>
        </header>
        <main class="dashboard-content prospects-workspace">
          <?php if ($notice !== ''): ?><div class="dashboard-alert is-success" role="status"><?= e($notice) ?></div><?php endif; ?>
          <?php if ($error !== ''): ?><div class="dashboard-alert is-error" role="alert"><?= e($error) ?></div><?php endif; ?>
          <?php if (!$schemaReady): ?><div class="dashboard-alert is-error" role="alert"><?= e($schemaMessage ?: 'Prospects database tables are not ready. Log in as an admin and run /update.php after deployment.') ?></div><?php endif; ?>
          <header class="dashboard-hero compact-hero prospects-header">
            <div><p class="eyebrow">Google Sheet staging</p><h2>Sync Sheet Rows Into The Portal Database</h2><p>This sync reads the configured Google Sheet CSV tabs, maps the tracker fields to the live prospects table, and upserts records by website, email, or business name/location.</p></div>
            <div class="hero-actions"><a class="secondary-action" href="/prospects.php">Back to prospects</a></div>
          </header>
          <section class="admin-panel prospect-detail">
            <div class="table-heading"><h3>Run sync</h3><span><?= e(count($sources) . ' source' . (count($sources) === 1 ? '' : 's')) ?></span></div>
            <p class="empty-state">Use this after researching leads into the Google Sheet. The sync updates existing matching records and creates new records for unmatched rows.</p>
            <form class="prospect-form" method="post">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <button class="button primary" type="submit" <?= $schemaReady ? '' : 'disabled' ?>>Sync Google Sheet now</button>
            </form>
          </section>
          <section class="admin-panel table-panel">
            <div class="table-heading"><h3>Configured sheet sources</h3><span>CSV export tabs</span></div>
            <div class="table-scroll"><table class="data-table"><thead><tr><th>Source</th><th>Default Status</th><th>CSV URL</th></tr></thead><tbody><?php foreach ($sources as $source): ?><tr><td><?= e((string) $source['label']) ?></td><td><?= e((string) $source['status']) ?></td><td><a href="<?= e((string) $source['url']) ?>" target="_blank" rel="noopener">Open CSV export</a></td></tr><?php endforeach; ?></tbody></table></div>
          </section>
          <?php if (is_array($summary)): ?>
          <section class="admin-panel table-panel">
            <div class="table-heading"><h3>Last sync result</h3><span><?= e((string) date('Y-m-d H:i:s')) ?></span></div>
            <div class="prospect-detail-summary"><span class="prospect-pill priority-low"><?= e((string) $summary['created']) ?> created</span><span class="prospect-pill priority-medium"><?= e((string) $summary['updated']) ?> updated</span><span class="prospect-pill priority-low"><?= e((string) $summary['skipped']) ?> skipped</span></div>
            <?php if (!empty($summary['sources'])): ?><div class="table-scroll"><table class="data-table"><thead><tr><th>Source</th><th>Created</th><th>Updated</th><th>Skipped</th></tr></thead><tbody><?php foreach ($summary['sources'] as $sourceSummary): ?><tr><td><?= e((string) $sourceSummary['label']) ?></td><td><?= e((string) $sourceSummary['created']) ?></td><td><?= e((string) $sourceSummary['updated']) ?></td><td><?= e((string) $sourceSummary['skipped']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
          </section>
          <?php endif; ?>
        </main>
      </div>
    </div>
  </body>
</html>
