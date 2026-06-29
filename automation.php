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
function automation_excerpt(string $value, int $length = 120): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if (strlen($value) <= $length) return $value;
    return substr($value, 0, $length - 3) . '...';
}
function automation_status_class(string $value): string
{
    return strtolower(str_replace(' ', '-', $value));
}

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
    <link rel="stylesheet" href="/assets/prospects.css?v=20260630-layout-system">
    <style>
      .automation-workspace { max-width: 1760px; gap: 20px; }
      .automation-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
      .automation-toolbar .hero-actions { justify-content: flex-start; }
      .automation-list-panel { overflow: hidden; border-color: rgba(158, 167, 184, 0.28); background: #0f1012; box-shadow: 0 18px 70px rgba(0,0,0,0.42); }
      .automation-list { display: grid; gap: 10px; }
      .automation-row { display: grid; grid-template-columns: minmax(220px, 1.05fr) minmax(0, 1.35fr) minmax(0, 1.35fr) minmax(120px, .45fr); gap: 12px; align-items: stretch; border: 1px solid rgba(158,167,184,.22); border-radius: 8px; padding: 12px; background: #15171b; }
      .automation-row:hover { border-color: rgba(49,196,189,.48); background: #191d22; }
      .automation-title { display: grid; align-content: start; gap: 8px; min-width: 0; }
      .automation-title strong { color: #fff; font-size: .98rem; line-height: 1.25; }
      .automation-title small, .automation-cell span { color: var(--prospect-muted); line-height: 1.45; }
      .automation-cell { display: grid; gap: 6px; min-width: 0; }
      .automation-cell b { color: #dce2eb; font-size: .74rem; text-transform: uppercase; }
      .automation-cell span { overflow-wrap: anywhere; }
      .automation-statuses { display: flex; flex-wrap: wrap; gap: 7px; align-content: start; justify-content: flex-end; }
      .automation-pill { display: inline-flex; min-height: 26px; align-items: center; justify-content: center; border: 1px solid rgba(158,167,184,.38); border-radius: 6px; padding: 4px 9px; color: #fff; font-size: .74rem; font-weight: 900; line-height: 1; white-space: nowrap; }
      .automation-pill.status-ready { background: #16a34a; border-color: rgba(54,194,117,.5); }
      .automation-pill.status-draft { background: #1f8bff; border-color: rgba(91,140,255,.5); }
      .automation-pill.status-paused { background: #d88924; border-color: rgba(227,182,83,.5); }
      .automation-pill.status-retired { background: #4b5563; border-color: rgba(124,132,146,.5); }
      .automation-pill.importance-critical, .automation-pill.importance-high { background: #eb3b63; border-color: rgba(235,59,99,.62); }
      .automation-pill.importance-medium { background: #8b5cf6; border-color: rgba(139,92,246,.62); }
      .automation-pill.importance-low { background: #eab308; border-color: rgba(234,179,8,.62); color: #241a04; }
      .automation-builder-modal { display: none; }
      .automation-builder-modal:target { position: fixed; inset: 50% auto auto 50%; z-index: 100; display: grid; width: min(1120px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); overflow: auto; transform: translate(-50%, -50%); border: 1px solid rgba(158,167,184,.46); border-radius: 8px; padding: 16px; background: linear-gradient(145deg, rgba(24,28,35,.99), rgba(9,10,13,.99)); box-shadow: 0 0 0 100vmax rgba(0,0,0,.72), 0 28px 90px rgba(0,0,0,.56); }
      .automation-modal-close { position: sticky; top: 0; justify-self: end; display: inline-grid; width: 38px; height: 38px; margin: -4px -2px -8px 0; place-items: center; border: 1px solid rgba(158,167,184,.44); border-radius: 8px; background: #101217; color: #fff; font-size: 1.35rem; line-height: 1; text-decoration: none; }
      .block-builder { display: grid; grid-template-columns: minmax(210px, .38fr) minmax(0, 1fr); gap: 14px; }
      .block-palette, .block-canvas { border: 1px solid rgba(158,167,184,.28); border-radius: 8px; background: #101114; }
      .block-palette { align-content: start; padding: 12px; }
      .block-palette h3, .block-canvas h3 { margin: 0; }
      .palette-blocks { display: grid; gap: 8px; margin-top: 12px; }
      .palette-chip { display: inline-flex; min-height: 34px; align-items: center; border-radius: 7px; padding: 7px 10px; color: #fff; font-size: .82rem; font-weight: 900; }
      .palette-chip.when { background: #c36a24; }
      .palette-chip.if { background: #2f75d6; }
      .palette-chip.then { background: #8e5bbd; }
      .palette-chip.owner { background: #1c9f8e; }
      .block-canvas { position: relative; overflow: hidden; min-height: 560px; padding: 14px; background: #2f2f2f; }
      .block-canvas::before { content: ""; position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px); background-size: 28px 28px; pointer-events: none; }
      .block-form { position: relative; z-index: 1; display: grid; gap: 10px; max-width: 760px; }
      .block-stack { display: grid; gap: 0; margin-top: 6px; }
      .logic-block { position: relative; display: grid; gap: 8px; width: min(100%, 680px); border-radius: 8px 8px 8px 3px; padding: 10px 12px 12px 18px; color: #fff; box-shadow: inset 0 -3px 0 rgba(0,0,0,.18), 0 8px 20px rgba(0,0,0,.2); }
      .logic-block + .logic-block { margin-top: -2px; }
      .logic-block::before { content: ""; position: absolute; left: 0; top: 20px; width: 14px; height: 18px; border-radius: 0 999px 999px 0; background: rgba(255,255,255,.16); }
      .logic-block label { display: grid; gap: 7px; font-size: .78rem; font-weight: 900; letter-spacing: 0; text-transform: uppercase; }
      .logic-block input, .logic-block select, .logic-block textarea { border: 1px solid rgba(255,255,255,.28); border-radius: 6px; background: rgba(255,255,255,.96); color: #171717; font-weight: 750; }
      .logic-block textarea { min-height: 78px; resize: vertical; }
      .logic-block .block-inline { display: grid; grid-template-columns: minmax(0, 1fr) minmax(120px, .35fr); gap: 10px; }
      .logic-block.event { background: #c56d24; }
      .logic-block.condition { margin-left: 32px; background: #2f72d2; }
      .logic-block.action { margin-left: 64px; background: #8153a5; }
      .logic-block.meta { margin-left: 32px; background: #1c9f8e; }
      .builder-note { max-width: 760px; margin: 0; color: #d6d9df; line-height: 1.5; }
      .builder-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 4px; }
      @media (max-width: 1060px) {
        .automation-row { grid-template-columns: 1fr; }
        .automation-statuses { justify-content: flex-start; }
        .block-builder { grid-template-columns: 1fr; }
        .block-canvas { min-height: auto; }
      }
      @media (max-width: 720px) {
        .automation-toolbar { align-items: flex-start; flex-direction: column; }
        .automation-toolbar .hero-actions, .builder-actions { display: grid; grid-template-columns: 1fr; width: 100%; }
        .logic-block, .logic-block.condition, .logic-block.action, .logic-block.meta { margin-left: 0; }
        .logic-block .block-inline { grid-template-columns: 1fr; }
        .automation-builder-modal:target { align-content: start; padding: 12px; }
      }
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
        <main class="dashboard-content automation-workspace">
          <?php if ($notice): ?><div class="dashboard-alert is-success" role="status"><?= e((string) $notice) ?></div><?php endif; ?>
          <?php if ($error): ?><div class="dashboard-alert is-error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>
          <?php if (!$schemaReady): ?><div class="dashboard-alert is-error" role="alert">Automation tables are not ready yet. Log in as an admin and run <a href="/update.php">/update.php</a> once after deployment.</div><?php endif; ?>
          <section class="dashboard-section is-active" data-dashboard-section data-section-label="Automation">
            <header class="automation-toolbar" aria-label="Automation actions">
              <div><p class="eyebrow">Playground automation</p><h2>Automation recipes</h2></div>
              <div class="hero-actions"><a class="primary-action" href="#create-automation">Create Automation</a><a class="secondary-action" href="#run-history">Run History</a></div>
            </header>

            <section class="section-summary-grid three-up" aria-label="Automation summary">
              <article><span>Draft</span><strong><?= e((string) $counts['Draft']) ?></strong></article>
              <article><span>Ready</span><strong><?= e((string) $counts['Ready']) ?></strong></article>
              <article><span>Execution</span><strong>Manual</strong></article>
            </section>

            <section class="admin-panel automation-list-panel" id="recipes">
              <div class="table-heading"><h3>Automations</h3><span><?= e((string) count($recipes)) ?> saved</span></div>
              <?php if (!$schemaReady): ?><p class="empty-state">Run /update.php to enable saved automation recipes.</p><?php elseif (!$recipes): ?><p class="empty-state">No automations have been created yet. Use Create Automation to build the first recipe.</p><?php else: ?>
              <div class="automation-list">
                <?php foreach ($recipes as $recipe): ?>
                <article class="automation-row">
                  <div class="automation-title">
                    <strong><?= e((string) $recipe['name']) ?></strong>
                    <small><?= e((string) ($recipe['owner'] ?: $recipe['creator_name'] ?: $recipe['creator_email'] ?: 'Unassigned')) ?> &middot; Updated <?= e((string) $recipe['updated_at']) ?></small>
                  </div>
                  <div class="automation-cell"><b>When</b><span><?= e(automation_excerpt((string) $recipe['trigger_event'])) ?></span></div>
                  <div class="automation-cell"><b>Then</b><span><?= e(automation_excerpt((string) $recipe['action_steps'])) ?></span></div>
                  <div class="automation-statuses">
                    <span class="automation-pill status-<?= e(automation_status_class((string) $recipe['status'])) ?>"><?= e((string) $recipe['status']) ?></span>
                    <span class="automation-pill importance-<?= e(automation_status_class((string) $recipe['importance'])) ?>"><?= e((string) $recipe['importance']) ?></span>
                  </div>
                </article>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </section>

            <?php if ($schemaReady): ?>
            <section class="automation-builder-modal" id="create-automation" aria-label="Create automation">
              <a class="automation-modal-close" href="/automation.php" aria-label="Close automation builder">x</a>
              <div class="block-builder">
                <aside class="block-palette" aria-label="Automation blocks">
                  <p class="eyebrow">Block palette</p>
                  <h3>Recipe parts</h3>
                  <div class="palette-blocks">
                    <span class="palette-chip when">when event happens</span>
                    <span class="palette-chip if">if rule matches</span>
                    <span class="palette-chip then">then run action</span>
                    <span class="palette-chip owner">owner and status</span>
                  </div>
                </aside>
                <section class="block-canvas" aria-label="Automation block editor">
                  <form class="block-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="create_recipe">
                    <p class="eyebrow">Automation designer</p>
                    <h3>Create Automation</h3>
                    <div class="block-stack">
                      <div class="logic-block event">
                        <div class="block-inline">
                          <label>Automation name<input name="name" maxlength="190" required placeholder="New client onboarding"></label>
                          <label>Status<select name="status"><?php foreach (automation_statuses() as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?></select></label>
                        </div>
                        <label>When<textarea name="trigger_event" rows="3" required placeholder="A client confirms their account"></textarea></label>
                      </div>
                      <div class="logic-block condition">
                        <label>If<textarea name="condition_rules" rows="3" placeholder="Client is active and has no onboarding request"></textarea></label>
                      </div>
                      <div class="logic-block action">
                        <label>Then<textarea name="action_steps" rows="4" required placeholder="Create onboarding task, notify the owner, and add an activity entry"></textarea></label>
                      </div>
                      <div class="logic-block meta">
                        <div class="block-inline">
                          <label>Owner<input name="owner" maxlength="120" value="<?= e($displayName) ?>" placeholder="Operations"></label>
                          <label>Importance<select name="importance"><?php foreach (automation_importance_levels() as $importance): ?><option value="<?= e($importance) ?>" <?= $importance === 'Medium' ? 'selected' : '' ?>><?= e($importance) ?></option><?php endforeach; ?></select></label>
                        </div>
                      </div>
                    </div>
                    <p class="builder-note">Saving creates a governed recipe only. Automatic execution still requires an approved runner, connector permissions, failure handling, and audit controls.</p>
                    <div class="builder-actions"><button class="button primary" type="submit">Save Automation</button><a class="secondary-action" href="/automation.php">Cancel</a></div>
                  </form>
                </section>
              </div>
            </section>
            <?php endif; ?>

            <section class="admin-panel" id="run-history">
              <div class="table-heading"><h3>Run history</h3><span>Execution disabled</span></div>
              <?php if (!$runs): ?><p class="empty-state">No automation runs yet. Recipes are saved, but automatic execution has not been enabled.</p><?php else: ?><div class="table-scroll"><table class="data-table compact-table"><thead><tr><th>Flow</th><th>Status</th><th>Last run</th><th>Owner</th></tr></thead><tbody><?php foreach ($runs as $run): ?><tr><td><strong><?= e((string) $run['flow']) ?></strong></td><td><span class="status-badge is-muted"><?= e((string) $run['status']) ?></span></td><td><?= e((string) $run['last_run']) ?></td><td><?= e((string) $run['owner']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </section>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>