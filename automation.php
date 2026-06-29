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
function automation_get_string(string $key): string { return trim((string) ($_GET[$key] ?? '')); }
function automation_post_int(string $key): int
{
    $value = filter_var($_POST[$key] ?? 0, FILTER_VALIDATE_INT);
    return $value === false ? 0 : (int) $value;
}
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
function automation_filter_url(array $overrides = []): string
{
    $params = array_filter(array_merge([
        'automation_search' => automation_get_string('automation_search'),
        'automation_status' => automation_get_string('automation_status'),
        'automation_importance' => automation_get_string('automation_importance'),
        'automation_owner' => automation_get_string('automation_owner'),
    ], $overrides), static function ($value): bool {
        return trim((string) $value) !== '';
    });
    return '/automation.php' . ($params ? '?' . http_build_query($params) : '');
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
        $payload = automation_payload_from_post();
        if ($action === 'create_recipe') {
            $recipeId = automation_insert_recipe($pdo, $payload, (int) $user['id']);
            automation_log_activity($pdo, (int) $user['id'], 'automation recipe created', $recipeId, (string) $payload['name']);
            automation_flash_success('Automation recipe saved. Execution is still manual until an automation runner is approved and implemented.');
            automation_redirect(['created' => $recipeId]);
        }
        if ($action === 'update_recipe') {
            $recipeId = automation_post_int('recipe_id');
            if ($recipeId <= 0 || !automation_fetch_recipe($pdo, $recipeId)) {
                throw new RuntimeException('Choose a valid automation recipe.');
            }
            automation_update_recipe($pdo, $recipeId, $payload, (int) $user['id']);
            automation_log_activity($pdo, (int) $user['id'], 'automation recipe updated', $recipeId, (string) $payload['name']);
            automation_flash_success('Automation recipe updated.');
            automation_redirect(['open' => $recipeId]);
        }
        throw new RuntimeException('Choose a valid automation action.');
    } catch (Throwable $postError) {
        automation_flash_error($postError->getMessage());
        automation_redirect();
    }
}

$recipes = [];
$runs = [];
$owners = [];
$openedRecipe = null;
$counts = ['Draft' => 0, 'Ready' => 0, 'Paused' => 0, 'Retired' => 0];
$automationSearch = automation_get_string('automation_search');
$automationStatus = automation_get_string('automation_status');
if (!in_array($automationStatus, automation_statuses(), true)) $automationStatus = '';
$automationImportance = automation_get_string('automation_importance');
if (!in_array($automationImportance, automation_importance_levels(), true)) $automationImportance = '';
$automationOwner = automation_get_string('automation_owner');

if ($pdo instanceof PDO && $schemaReady) {
    $allStmt = $pdo->prepare('SELECT r.*, creator.email AS creator_email, creator.full_name AS creator_name FROM automation_recipes r LEFT JOIN users creator ON creator.id = r.created_by ORDER BY r.updated_at DESC, r.id DESC');
    $allStmt->execute();
    $allRecipes = $allStmt->fetchAll();
    foreach ($allRecipes as $recipe) {
        $status = (string) ($recipe['status'] ?? 'Draft');
        if (isset($counts[$status])) $counts[$status]++;
        $ownerName = trim((string) ($recipe['owner'] ?: $recipe['creator_name'] ?: $recipe['creator_email'] ?: ''));
        if ($ownerName !== '') $owners[$ownerName] = true;
    }
    $where = [];
    $params = [];
    if ($automationSearch !== '') {
        $where[] = '(r.name LIKE ? OR r.trigger_event LIKE ? OR r.condition_rules LIKE ? OR r.action_steps LIKE ? OR r.owner LIKE ?)';
        $like = '%' . $automationSearch . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($automationStatus !== '') {
        $where[] = 'r.status = ?';
        $params[] = $automationStatus;
    }
    if ($automationImportance !== '') {
        $where[] = 'r.importance = ?';
        $params[] = $automationImportance;
    }
    if ($automationOwner !== '') {
        $where[] = '(r.owner = ? OR creator.full_name = ? OR creator.email = ?)';
        array_push($params, $automationOwner, $automationOwner, $automationOwner);
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare('SELECT r.*, creator.email AS creator_email, creator.full_name AS creator_name FROM automation_recipes r LEFT JOIN users creator ON creator.id = r.created_by' . $whereSql . ' ORDER BY r.updated_at DESC, r.id DESC');
    $stmt->execute($params);
    $recipes = $stmt->fetchAll();

    $openId = filter_var($_GET['open'] ?? 0, FILTER_VALIDATE_INT);
    if ($openId !== false && (int) $openId > 0) {
        $openedRecipe = automation_fetch_recipe($pdo, (int) $openId);
    }

    $runStmt = $pdo->prepare('SELECT ar.name AS flow, ar.owner, COALESCE(latest.status, ?) AS status, COALESCE(DATE_FORMAT(latest.ran_at, "%Y-%m-%d %H:%i"), ?) AS last_run FROM automation_recipes ar LEFT JOIN (SELECT r1.* FROM automation_runs r1 INNER JOIN (SELECT recipe_id, MAX(id) AS max_id FROM automation_runs GROUP BY recipe_id) latest_ids ON latest_ids.max_id = r1.id) latest ON latest.recipe_id = ar.id ORDER BY ar.updated_at DESC, ar.id DESC');
    $runStmt->execute(['Not run yet', 'Not run yet']);
    $runs = $runStmt->fetchAll();
}
ksort($owners);
$builderRecipe = $openedRecipe ?: [
    'id' => 0,
    'name' => '',
    'trigger_event' => '',
    'condition_rules' => '',
    'action_steps' => '',
    'status' => 'Draft',
    'importance' => 'Medium',
    'owner' => $displayName,
];
$isEditingRecipe = !empty($builderRecipe['id']);
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
    <link rel="stylesheet" href="/assets/automation.css?v=20260630-editor-filters">
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
              <div class="hero-actions"><a class="primary-action" href="<?= e(automation_filter_url()) ?>#automation-builder"><?= $isEditingRecipe ? 'Create New Automation' : 'Create Automation' ?></a><a class="secondary-action" href="#run-history">Run History</a></div>
            </header>

            <section class="section-summary-grid three-up" aria-label="Automation summary">
              <article><span>Draft</span><strong><?= e((string) $counts['Draft']) ?></strong></article>
              <article><span>Ready</span><strong><?= e((string) $counts['Ready']) ?></strong></article>
              <article><span>Execution</span><strong>Manual</strong></article>
            </section>

            <form class="admin-panel automation-filters" method="get" action="/automation.php#recipes">
              <label>Search automations<input type="search" name="automation_search" value="<?= e($automationSearch) ?>" placeholder="Name, trigger, condition, action, owner"></label>
              <label>Status<select name="automation_status"><option value="">All statuses</option><?php foreach (automation_statuses() as $status): ?><option value="<?= e($status) ?>" <?= $automationStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
              <label>Importance<select name="automation_importance"><option value="">All importance</option><?php foreach (automation_importance_levels() as $importance): ?><option value="<?= e($importance) ?>" <?= $automationImportance === $importance ? 'selected' : '' ?>><?= e($importance) ?></option><?php endforeach; ?></select></label>
              <label>Owner<select name="automation_owner"><option value="">All owners</option><?php foreach (array_keys($owners) as $owner): ?><option value="<?= e($owner) ?>" <?= $automationOwner === $owner ? 'selected' : '' ?>><?= e($owner) ?></option><?php endforeach; ?></select></label>
              <div class="filter-actions"><button class="button primary" type="submit">Apply</button><a class="secondary-action" href="/automation.php#recipes">Clear</a></div>
            </form>

            <section class="admin-panel automation-list-panel" id="recipes">
              <div class="table-heading"><h3>Automations</h3><span><?= e((string) count($recipes)) ?> shown</span></div>
              <?php if (!$schemaReady): ?><p class="empty-state">Run /update.php to enable saved automation recipes and run history.</p><?php elseif (!$recipes): ?><p class="empty-state">No automations match this view. Create a recipe or clear filters to see the full list.</p><?php else: ?>
              <div class="automation-list">
                <?php foreach ($recipes as $recipe): $owner = (string) ($recipe['owner'] ?: $recipe['creator_name'] ?: $recipe['creator_email'] ?: 'Unassigned'); $editUrl = automation_filter_url(['open' => (string) $recipe['id']]) . '#automation-builder'; ?>
                <article class="automation-row">
                  <div class="automation-title">
                    <strong><?= e((string) $recipe['name']) ?></strong>
                    <small><?= e($owner) ?> &middot; Updated <?= e((string) $recipe['updated_at']) ?></small>
                    <a class="table-action" href="<?= e($editUrl) ?>">Open/Edit</a>
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
            <section class="automation-builder-modal" id="automation-builder" aria-label="<?= $isEditingRecipe ? 'Edit automation' : 'Create automation' ?>">
              <a class="automation-modal-close" href="<?= e(automation_filter_url()) ?>" aria-label="Close automation builder">x</a>
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
                    <input type="hidden" name="action" value="<?= $isEditingRecipe ? 'update_recipe' : 'create_recipe' ?>">
                    <input type="hidden" name="recipe_id" value="<?= e((string) ($builderRecipe['id'] ?? 0)) ?>">
                    <p class="eyebrow">Automation designer</p>
                    <h3><?= $isEditingRecipe ? 'Edit Automation' : 'Create Automation' ?></h3>
                    <div class="block-stack">
                      <div class="logic-block event">
                        <div class="block-inline">
                          <label>Automation name<input name="name" maxlength="190" required value="<?= e((string) $builderRecipe['name']) ?>" placeholder="New client onboarding"></label>
                          <label>Status<select name="status"><?php foreach (automation_statuses() as $status): ?><option value="<?= e($status) ?>" <?= (string) $builderRecipe['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
                        </div>
                        <label>When<textarea name="trigger_event" rows="3" required placeholder="A client confirms their account"><?= e((string) $builderRecipe['trigger_event']) ?></textarea></label>
                      </div>
                      <div class="logic-block condition">
                        <label>If<textarea name="condition_rules" rows="3" placeholder="Client is active and has no onboarding request"><?= e((string) $builderRecipe['condition_rules']) ?></textarea></label>
                      </div>
                      <div class="logic-block action">
                        <label>Then<textarea name="action_steps" rows="4" required placeholder="Create onboarding task, notify the owner, and add an activity entry"><?= e((string) $builderRecipe['action_steps']) ?></textarea></label>
                      </div>
                      <div class="logic-block meta">
                        <div class="block-inline">
                          <label>Owner<input name="owner" maxlength="120" value="<?= e((string) $builderRecipe['owner']) ?>" placeholder="Operations"></label>
                          <label>Importance<select name="importance"><?php foreach (automation_importance_levels() as $importance): ?><option value="<?= e($importance) ?>" <?= (string) $builderRecipe['importance'] === $importance ? 'selected' : '' ?>><?= e($importance) ?></option><?php endforeach; ?></select></label>
                        </div>
                      </div>
                    </div>
                    <p class="builder-note">Saving creates or updates a governed recipe only. Automatic execution still requires an approved runner, connector permissions, failure handling, and audit controls.</p>
                    <div class="builder-actions"><button class="button primary" type="submit"><?= $isEditingRecipe ? 'Save Changes' : 'Save Automation' ?></button><a class="secondary-action" href="<?= e(automation_filter_url()) ?>">Cancel</a></div>
                  </form>
                </section>
              </div>
            </section>
            <?php endif; ?>

            <section class="admin-panel" id="run-history">
              <div class="table-heading"><h3>Run history</h3><span>Execution disabled</span></div>
              <?php if (!$schemaReady): ?><p class="empty-state">Run /update.php to enable run-history storage.</p><?php elseif (!$runs): ?><p class="empty-state">No automation runs yet. Recipes are saved, but automatic execution has not been enabled.</p><?php else: ?><div class="table-scroll"><table class="data-table compact-table"><thead><tr><th>Flow</th><th>Status</th><th>Last run</th><th>Owner</th></tr></thead><tbody><?php foreach ($runs as $run): ?><tr><td><strong><?= e((string) $run['flow']) ?></strong></td><td><span class="status-badge is-muted"><?= e((string) $run['status']) ?></span></td><td><?= e((string) $run['last_run']) ?></td><td><?= e((string) $run['owner']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </section>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>
