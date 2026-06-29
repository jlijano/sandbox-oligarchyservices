<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/access-management.php';
require_once __DIR__ . '/includes/automation.php';

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
$notice = $_SESSION['automation_notice'] ?? null;
$error = $_SESSION['automation_error'] ?? null;
unset($_SESSION['automation_notice'], $_SESSION['automation_error']);

function automation_flash_success(string $message): void { $_SESSION['automation_notice'] = $message; }
function automation_flash_error(string $message): void { $_SESSION['automation_error'] = $message; }
function automation_redirect(array $params = []): void { redirect_to('/automation.php' . ($params ? '?' . http_build_query($params) : '')); }

$pdo = null;
$schemaReady = false;
try {
    $pdo = db();
    $schemaReady = automation_schema_ready($pdo);
} catch (Throwable $dbError) {
    error_log('Automation database unavailable: ' . $dbError->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo instanceof PDO || !$schemaReady) {
        automation_flash_error('Automation tables are not ready. Log in as an admin and run /update.php after deployment.');
        automation_redirect();
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        automation_flash_error('Your session expired. Please refresh and try again.');
        automation_redirect();
    }
    try {
        $action = trim((string) ($_POST['action'] ?? ''));
        if ($action !== 'create_recipe') {
            throw new RuntimeException('Choose a valid automation action.');
        }
        $payload = automation_payload_from_post();
        $recipeId = automation_insert_recipe($pdo, $payload, (int) $user['id']);
        automation_log_activity($pdo, (int) $user['id'], 'automation recipe created', $recipeId, (string) $payload['name']);
        automation_flash_success('Automation recipe saved. Execution is still manual until an automation runner is approved and implemented.');
        automation_redirect(['created' => $recipeId]);
    } catch (Throwable $postError) {
        automation_flash_error($postError->getMessage());
        automation_redirect();
    }
}

$recipes = [];
$runs = [];
$counts = ['Draft' => 0, 'Ready' => 0, 'Paused' => 0, 'Retired' => 0];
if ($pdo instanceof PDO && $schemaReady) {
    $stmt = $pdo->prepare('SELECT r.*, creator.email AS creator_email, creator.full_name AS creator_name FROM automation_recipes r LEFT JOIN users creator ON creator.id = r.created_by ORDER BY r.updated_at DESC, r.id DESC');
    $stmt->execute();
    $recipes = $stmt->fetchAll();
    foreach ($recipes as $recipe) {
        $status = (string) ($recipe['status'] ?? 'Draft');
        if (isset($counts[$status])) $counts[$status]++;
    }
    $runStmt = $pdo->prepare('SELECT ar.name AS flow, ar.owner, COALESCE(latest.status, ?) AS status, COALESCE(DATE_FORMAT(latest.ran_at, "%Y-%m-%d %H:%i"), ?) AS last_run FROM automation_recipes ar LEFT JOIN (SELECT r1.* FROM automation_runs r1 INNER JOIN (SELECT recipe_id, MAX(id) AS max_id FROM automation_runs GROUP BY recipe_id) latest_ids ON latest_ids.max_id = r1.id) latest ON latest.recipe_id = ar.id ORDER BY ar.updated_at DESC, ar.id DESC');
    $runStmt->execute(['Not run yet', 'Not run yet']);
    $runs = $runStmt->fetchAll();
}
$csrf = csrf_token();
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
    <style>
      .automation-form { display: grid; gap: 14px; }
      .automation-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
      .automation-form label { display: grid; gap: 7px; color: #e6e6e8; font-size: .86rem; font-weight: 800; }
      .automation-form .wide-field { grid-column: 1 / -1; }
      .automation-note { color: var(--muted); font-size: .9rem; line-height: 1.5; }
      @media (max-width: 760px) { .automation-form-grid { grid-template-columns: 1fr; } .automation-form .wide-field { grid-column: auto; } }
    </style>
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
          <?php if ($notice): ?><div class="dashboard-alert is-success" role="status"><?= e((string) $notice) ?></div><?php endif; ?>
          <?php if ($error): ?><div class="dashboard-alert is-error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>
          <?php if (!$schemaReady): ?><div class="dashboard-alert is-error" role="alert">Automation tables are not ready yet. Log in as an admin and run <a href="/update.php">/update.php</a> once after deployment.</div><?php endif; ?>
          <section class="dashboard-section is-active" data-dashboard-section data-section-label="Automation">
            <div class="dashboard-hero compact-hero">
              <div><p class="eyebrow">Workflow builder</p><h2>Automation</h2><p>Create governed trigger-condition-action recipes for portal operations. Recipes are saved now; execution remains disabled until an approved runner is added.</p></div>
              <div class="hero-actions"><a class="primary-action" href="#create-recipe">Create recipe</a><a class="secondary-action" href="#run-history">Run history</a></div>
            </div>

            <section class="section-summary-grid three-up" aria-label="Automation summary">
              <article><span>Draft recipes</span><strong><?= e((string) $counts['Draft']) ?></strong></article>
              <article><span>Ready recipes</span><strong><?= e((string) $counts['Ready']) ?></strong></article>
              <article><span>Connectors</span><strong>Planned</strong></article>
            </section>

            <?php if ($schemaReady): ?>
            <section class="admin-panel" id="create-recipe">
              <div class="table-heading"><h3>Create automation recipe</h3><span>Saved recipe, not auto-run</span></div>
              <form class="automation-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="create_recipe">
                <div class="automation-form-grid">
                  <label>Name<input name="name" maxlength="190" required placeholder="New client onboarding"></label>
                  <label>Owner<input name="owner" maxlength="120" value="<?= e($displayName) ?>" placeholder="Operations"></label>
                  <label>Status<select name="status"><?php foreach (automation_statuses() as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?></select></label>
                  <label>Importance<select name="importance"><?php foreach (automation_importance_levels() as $importance): ?><option value="<?= e($importance) ?>" <?= $importance === 'Medium' ? 'selected' : '' ?>><?= e($importance) ?></option><?php endforeach; ?></select></label>
                  <label class="wide-field">Trigger<textarea name="trigger_event" rows="3" required placeholder="When a client account is confirmed"></textarea></label>
                  <label class="wide-field">Condition<textarea name="condition_rules" rows="3" placeholder="Client status is active"></textarea></label>
                  <label class="wide-field">Action<textarea name="action_steps" rows="4" required placeholder="Create onboarding task, notify owner, and schedule follow-up"></textarea></label>
                </div>
                <p class="automation-note">Saving a recipe does not execute it. Real execution will need an approval-gated runner, connector permissions, failure handling, and audit controls.</p>
                <button class="button primary" type="submit">Save recipe</button>
              </form>
            </section>
            <?php endif; ?>

            <section class="workspace-grid two-up">
              <article class="admin-panel"><div class="panel-title-row"><h3>Recipe builder</h3><span>Trigger + condition + action</span></div><div class="automation-recipe"><div><span class="status-badge">When</span><strong>Account, page, email, request, prospect, or schedule event occurs</strong></div><div><span class="status-badge">If</span><strong>Field values, role, status, owner, or delivery outcome match</strong></div><div><span class="status-badge">Then</span><strong>Notify, assign, update, trace, approve, or create activity</strong></div></div></article>
              <article class="admin-panel"><div class="panel-title-row"><h3>Governance</h3><span>Required controls</span></div><ul class="mini-list"><li><strong>Owner</strong><span>Every automation needs an accountable owner.</span></li><li><strong>Least privilege</strong><span>Connectors should only access the data the recipe needs.</span></li><li><strong>Failure path</strong><span>Every recipe should log failed runs and route them to review.</span></li><li><strong>Change lifecycle</strong><span>Draft, test, activate, monitor, then retire safely.</span></li></ul></article>
            </section>

            <section class="admin-panel" id="recipes">
              <div class="table-heading"><h3>Automation recipes</h3><span><?= e((string) count($recipes)) ?> saved</span></div>
              <?php if (!$schemaReady): ?><p class="empty-state">Run /update.php to enable saved automation recipes.</p><?php elseif (!$recipes): ?><p class="empty-state">No automation recipes have been created yet.</p><?php else: ?>
              <div class="table-scroll"><table class="data-table activity-table"><thead><tr><th>Name</th><th>Trigger</th><th>Condition</th><th>Action</th><th>Status</th><th>Importance</th><th>Owner</th></tr></thead><tbody><?php foreach ($recipes as $recipe): ?><tr><td><strong><?= e((string) $recipe['name']) ?></strong><small><?= e((string) $recipe['updated_at']) ?></small></td><td><?= e((string) $recipe['trigger_event']) ?></td><td><?= e((string) $recipe['condition_rules']) ?></td><td><?= e((string) $recipe['action_steps']) ?></td><td><span class="status-badge <?= $recipe['status'] === 'Ready' ? 'is-active' : 'is-muted' ?>"><?= e((string) $recipe['status']) ?></span></td><td><span class="status-badge"><?= e((string) $recipe['importance']) ?></span></td><td><?= e((string) ($recipe['owner'] ?: $recipe['creator_name'] ?: $recipe['creator_email'] ?: 'Unassigned')) ?></td></tr><?php endforeach; ?></tbody></table></div>
              <?php endif; ?>
            </section>

            <section class="admin-panel" id="run-history">
              <div class="table-heading"><h3>Run history</h3><span>Execution disabled</span></div>
              <?php if (!$runs): ?><p class="empty-state">No automation runs yet. Recipes are saved, but automatic execution has not been enabled.</p><?php else: ?><div class="table-scroll"><table class="data-table compact-table"><thead><tr><th>Flow</th><th>Status</th><th>Last run</th><th>Owner</th></tr></thead><tbody><?php foreach ($runs as $run): ?><tr><td><strong><?= e((string) $run['flow']) ?></strong></td><td><span class="status-badge is-muted"><?= e((string) $run['status']) ?></span></td><td><?= e((string) $run['last_run']) ?></td><td><?= e((string) $run['owner']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </section>

            <section class="admin-panel">
              <div class="panel-title-row"><h3>Connector catalog</h3><span>Planned</span></div>
              <div class="quick-actions"><span class="status-badge">Portal users</span><span class="status-badge">CMS pages</span><span class="status-badge">Blogs</span><span class="status-badge">Requests</span><span class="status-badge">Prospects</span><span class="status-badge">Mail trace</span><span class="status-badge">Activity log</span></div>
            </section>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>
