<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/access-management.php';
require_once __DIR__ . '/includes/prospects.php';

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
$notice = $_SESSION['prospects_notice'] ?? null;
$error = $_SESSION['prospects_error'] ?? null;
unset($_SESSION['prospects_notice'], $_SESSION['prospects_error']);

function prospects_flash_success(string $message): void { $_SESSION['prospects_notice'] = $message; }
function prospects_flash_error(string $message): void { $_SESSION['prospects_error'] = $message; }
function prospects_redirect(array $params = []): void { redirect_to('/prospects.php' . ($params ? '?' . http_build_query($params) : '')); }
function prospects_post_string(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $default)); }
function prospects_post_int(string $key, int $default = 0): int { return filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT) !== false ? (int) $_POST[$key] : $default; }

$pdo = null;
$schemaReady = false;
$schemaMessage = '';
try {
    $pdo = db();
    $schemaReady = prospect_schema_ready($pdo);
} catch (Throwable $dbError) {
    error_log('Prospects database unavailable: ' . $dbError->getMessage());
    $schemaMessage = 'Prospects cannot connect to the portal database yet. Check the Hostinger database config or run repair.php if the config is missing.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo instanceof PDO || !$schemaReady) {
        prospects_flash_error('Prospects database tables are not ready. Log in as an admin and run /update.php after deployment.');
        prospects_redirect();
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        prospects_flash_error('Your session expired. Please refresh and try again.');
        prospects_redirect();
    }
    try {
        $action = prospects_post_string('action');
        if ($action === 'create_prospect') {
            $payload = prospect_payload_from_post();
            $prospectId = prospect_insert($pdo, $payload, (int) $user['id']);
            prospect_log_activity($pdo, (int) $user['id'], 'prospect created', $prospectId, (string) $payload['company']);
            prospects_flash_success('Prospect created.');
            prospects_redirect(['open' => $prospectId]);
        }
        if ($action === 'update_prospect') {
            $prospectId = prospects_post_int('prospect_id');
            if (!prospect_fetch($pdo, $prospectId)) throw new RuntimeException('Choose a valid prospect.');
            $payload = prospect_payload_from_post();
            prospect_update($pdo, $prospectId, $payload, (int) $user['id']);
            prospect_log_activity($pdo, (int) $user['id'], 'prospect updated', $prospectId, (string) $payload['status']);
            prospects_flash_success('Prospect updated.');
            prospects_redirect(['open' => $prospectId]);
        }
        if ($action === 'import_prospects') {
            $raw = trim((string) ($_POST['import_rows'] ?? ''));
            if ($raw === '') throw new RuntimeException('Paste at least one lead row before importing.');
            $rows = prospect_parse_import_rows($raw);
            if (!$rows) throw new RuntimeException('No valid import rows were found. Start each row with a company name.');
            $pdo->beginTransaction();
            $created = 0;
            foreach ($rows as $row) { prospect_insert($pdo, $row, (int) $user['id']); $created++; }
            $pdo->commit();
            prospect_log_activity($pdo, (int) $user['id'], 'prospects imported', null, (string) $created . ' lead rows');
            prospects_flash_success($created . ' prospect' . ($created === 1 ? '' : 's') . ' imported.');
            prospects_redirect();
        }
        throw new RuntimeException('Choose a valid prospect action.');
    } catch (Throwable $postError) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        prospects_flash_error($postError->getMessage());
        prospects_redirect();
    }
}

$statuses = prospect_statuses();
$kanbanStatuses = prospect_kanban_statuses();
$priorities = prospect_priorities();
$prospects = [];
$owners = [];
$openedProspect = null;
$counts = ['total' => 0, 'qualified' => 0, 'pipeline' => 0, 'followups' => 0, 'won' => 0];
$sourceCounts = [];
$ownerValues = [];

if ($pdo instanceof PDO && $schemaReady) {
    $stmt = $pdo->prepare('SELECT * FROM prospects ORDER BY updated_at DESC, id DESC');
    $stmt->execute();
    $prospects = $stmt->fetchAll();
    $owners = array_values(array_unique(array_filter(array_map(static fn(array $p): string => (string) ($p['owner'] ?? ''), $prospects))));
    sort($owners);
    foreach ($prospects as $prospect) {
        $status = (string) $prospect['status'];
        $value = (int) $prospect['value'];
        $counts['total']++;
        if (in_array($status, ['Qualified', 'Proposal', 'Negotiation'], true)) $counts['qualified']++;
        if ($status !== 'Lost') $counts['pipeline'] += $value;
        if ((string) ($prospect['follow_up'] ?? '') !== '' && (string) $prospect['follow_up'] <= date('Y-m-d') && !in_array($status, ['Won', 'Lost'], true)) $counts['followups']++;
        if ($status === 'Won') $counts['won']++;
        $source = trim((string) ($prospect['source'] ?? ''));
        if ($source !== '') $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;
        $ownerName = trim((string) ($prospect['owner'] ?? ''));
        if ($ownerName !== '') $ownerValues[$ownerName] = ($ownerValues[$ownerName] ?? 0) + $value;
    }
    $openId = filter_var($_GET['open'] ?? 0, FILTER_VALIDATE_INT) !== false ? (int) ($_GET['open'] ?? 0) : 0;
    if ($openId > 0) $openedProspect = prospect_fetch($pdo, $openId);
}
arsort($sourceCounts);
arsort($ownerValues);
$conversionRate = $counts['total'] > 0 ? (int) round(($counts['won'] / $counts['total']) * 100) : 0;
$csrf = csrf_token();
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
    <link rel="stylesheet" href="/assets/prospects.css?v=20260629-live-crm">
    <script defer src="/assets/dashboard.js?v=20260621-settings-modules"></script>
    <script defer src="/assets/prospects.js?v=20260629-live-crm"></script>
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
          <?php if ($notice): ?><div class="dashboard-alert is-success" role="status"><?= e((string) $notice) ?></div><?php endif; ?>
          <?php if ($error): ?><div class="dashboard-alert is-error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>
          <?php if (!$schemaReady): ?><div class="dashboard-alert is-error" role="alert"><?= e($schemaMessage ?: 'Prospects database tables are not ready. Log in as an admin and run /update.php after deployment.') ?></div><?php endif; ?>
          <header class="dashboard-hero compact-hero prospects-header">
            <div><p class="eyebrow">Playground</p><h2>Prospects</h2><p>Manage potential leads, CRM opportunities, follow-ups, and pipeline activity from one flexible workspace.</p></div>
            <div class="hero-actions"><a class="primary-action" href="#prospect-form">Add Prospect</a><a class="secondary-action" href="#prospect-import">Import Leads</a></div>
          </header>
          <?php if ($schemaReady): ?>
          <section class="prospect-action-grid" aria-label="Prospect actions">
            <form class="admin-panel prospect-form" id="prospect-form" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_prospect"><div class="table-heading"><h3>Add prospect</h3><span>Live database record</span></div><div class="prospect-form-grid"><?php $blank = ['company'=>'','contact'=>'','email'=>'','phone'=>'','source'=>'','status'=>'New','priority'=>'Medium','value'=>'','owner'=>$displayName,'follow_up'=>'','last_activity'=>'','notes'=>'']; ?><?php include __DIR__ . '/includes/prospect-form-fields.php'; ?></div><button class="button primary" type="submit">Create prospect</button></form>
            <form class="admin-panel prospect-form" id="prospect-import" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="import_prospects"><div class="table-heading"><h3>Import leads</h3><span>CSV rows</span></div><label>Lead rows<textarea name="import_rows" rows="8" placeholder="company,contact,email,phone,source,status,priority,value,owner,notes"></textarea></label><button class="button primary" type="submit">Import leads</button></form>
          </section>
          <?php if ($openedProspect): ?><section class="admin-panel prospect-detail" id="prospect-detail" aria-label="Open prospect detail"><div class="table-heading"><h3><?= e((string) $openedProspect['company']) ?></h3><span>Prospect #<?= e((string) $openedProspect['id']) ?></span></div><div class="prospect-detail-summary"><span class="prospect-pill status-<?= e(strtolower(str_replace(' ', '-', (string) $openedProspect['status']))) ?>"><?= e((string) $openedProspect['status']) ?></span><span class="prospect-pill priority-<?= e(strtolower((string) $openedProspect['priority'])) ?>"><?= e((string) $openedProspect['priority']) ?></span><span><?= e(prospect_money((int) $openedProspect['value'])) ?></span><span><?= e((string) ($openedProspect['owner'] ?: 'Unassigned')) ?></span></div><form class="prospect-form" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="update_prospect"><input type="hidden" name="prospect_id" value="<?= e((string) $openedProspect['id']) ?>"><div class="prospect-form-grid"><?php $blank = $openedProspect; include __DIR__ . '/includes/prospect-form-fields.php'; ?></div><div class="form-actions"><button class="button primary" type="submit">Save prospect</button><a class="secondary-action" href="/prospects.php">Close</a></div></form></section><?php endif; ?>
          <nav class="prospect-view-switcher" aria-label="Prospect views"><button class="is-active" type="button" data-prospect-view-button="list">List</button><button type="button" data-prospect-view-button="kanban">Kanban</button><button type="button" data-prospect-view-button="timeline">Timeline</button><button type="button" data-prospect-view-button="dashboard">Dashboard</button></nav>
          <section class="prospect-view is-active" id="prospect-list" data-prospect-view="list"><form class="admin-panel prospect-filters" data-prospect-filters><label>Search prospects<input type="search" data-prospect-search placeholder="Company, contact, source"></label><label>Status<select data-prospect-status><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?></select></label><label>Owner<select data-prospect-owner><option value="">All owners</option><?php foreach ($owners as $owner): ?><option value="<?= e($owner) ?>"><?= e($owner) ?></option><?php endforeach; ?></select></label><label>Priority<select data-prospect-priority><option value="">All priorities</option><?php foreach ($priorities as $priority): ?><option><?= e($priority) ?></option><?php endforeach; ?></select></label></form><div class="admin-panel table-panel"><div class="table-heading"><h3>Lead pipeline</h3><span data-prospect-result-count><?= e((string) $counts['total']) ?> prospects</span></div><?php if (!$prospects): ?><p class="empty-state">No prospects yet. Add a prospect or import lead rows to start the CRM workspace.</p><?php else: ?><div class="table-scroll"><table class="data-table prospects-table"><thead><tr><th>Prospect / company</th><th>Contact</th><th>Email</th><th>Phone</th><th>Lead source</th><th>Status</th><th>Priority</th><th>Estimated value</th><th>Owner</th><th>Next follow-up</th><th>Last activity</th><th>Notes</th></tr></thead><tbody><?php foreach ($prospects as $prospect): ?><tr data-prospect-row data-search="<?= e(strtolower(implode(' ', array_map('strval', $prospect)))) ?>" data-status="<?= e((string) $prospect['status']) ?>" data-owner="<?= e((string) $prospect['owner']) ?>" data-priority="<?= e((string) $prospect['priority']) ?>"><td><strong><?= e((string) $prospect['company']) ?></strong><small><?= e(prospect_excerpt((string) $prospect['notes'])) ?></small></td><td><?= e((string) $prospect['contact']) ?></td><td><?php if ($prospect['email']): ?><a href="mailto:<?= e((string) $prospect['email']) ?>"><?= e((string) $prospect['email']) ?></a><?php endif; ?></td><td class="nowrap"><?= e((string) $prospect['phone']) ?></td><td><?= e((string) $prospect['source']) ?></td><td><span class="prospect-pill status-<?= e(strtolower(str_replace(' ', '-', (string) $prospect['status']))) ?>"><?= e((string) $prospect['status']) ?></span></td><td><span class="prospect-pill priority-<?= e(strtolower((string) $prospect['priority'])) ?>"><?= e((string) $prospect['priority']) ?></span></td><td class="numeric-cell"><?= e(prospect_money((int) $prospect['value'])) ?></td><td><?= e((string) $prospect['owner']) ?></td><td class="nowrap"><?= e((string) $prospect['follow_up']) ?></td><td><?= e((string) $prospect['last_activity']) ?></td><td><a class="table-action" href="/prospects.php?open=<?= e((string) $prospect['id']) ?>#prospect-detail">Open</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
          <section class="prospect-view" id="prospect-kanban" data-prospect-view="kanban" hidden><div class="prospect-board" aria-label="Prospect pipeline board"><?php foreach ($kanbanStatuses as $status): $cards = array_values(array_filter($prospects, fn(array $p): bool => $p['status'] === $status)); ?><section class="kanban-column"><div class="kanban-column-header"><h3><?= e($status) ?></h3><span><?= e((string) count($cards)) ?></span></div><?php foreach ($cards as $card): ?><article class="kanban-card"><div><strong><?= e((string) $card['company']) ?></strong><small><?= e((string) $card['contact']) ?></small></div><div class="kanban-card-meta"><span><?= e(prospect_money((int) $card['value'])) ?></span><span class="prospect-pill priority-<?= e(strtolower((string) $card['priority'])) ?>"><?= e((string) $card['priority']) ?></span></div><div class="kanban-card-footer"><span><?= e((string) $card['follow_up']) ?></span><span class="owner-avatar"><?= e(prospect_initials((string) $card['owner'])) ?></span></div><a class="table-action" href="/prospects.php?open=<?= e((string) $card['id']) ?>#prospect-detail">Open</a></article><?php endforeach; ?></section><?php endforeach; ?></div></section>
          <section class="prospect-view" id="prospect-timeline" data-prospect-view="timeline" hidden><div class="timeline-groups"><?php foreach (['Today', 'This week', 'Later'] as $group): ?><section class="admin-panel timeline-group"><div class="table-heading"><h3><?= e($group) ?></h3><span>Follow-ups</span></div><?php $groupHasRows = false; foreach ($prospects as $prospect): ?><?php if (prospect_timeline_group($prospect['follow_up'] ?? null) !== $group) continue; $groupHasRows = true; ?><article class="timeline-item"><div class="timeline-date"><?= e((string) ($prospect['follow_up'] ?: 'No date')) ?></div><div><strong><?= e((string) $prospect['company']) ?></strong><small><?= e((string) $prospect['status']) ?> &middot; <?= e((string) $prospect['contact']) ?></small></div><span>Follow-up</span><span><?= e((string) $prospect['owner']) ?></span><span class="prospect-pill status-<?= e(strtolower(str_replace(' ', '-', (string) $prospect['status']))) ?>"><?= e((string) $prospect['status']) ?></span></article><?php endforeach; ?><?php if (!$groupHasRows): ?><p class="empty-state">No follow-ups in this group.</p><?php endif; ?></section><?php endforeach; ?></div></section>
          <section class="prospect-view" id="prospect-dashboard" data-prospect-view="dashboard" hidden><div class="section-heading-row"><div><p class="eyebrow">Configurable workspace</p><h2>Prospects dashboard</h2></div><button class="secondary-action" type="button" data-customize-dashboard>Customize dashboard</button></div><section class="prospect-widget-grid" aria-label="Prospect dashboard widgets"><?php $topSource = $sourceCounts ? array_key_first($sourceCounts) . ' ' . reset($sourceCounts) : 'No sources yet'; $topOwner = $ownerValues ? array_key_first($ownerValues) : 'No owners yet'; $widgets = [['Total prospects', (string) $counts['total'], 'All live CRM records'], ['Qualified leads', (string) $counts['qualified'], 'Qualified through negotiation'], ['Pipeline value', prospect_money((int) $counts['pipeline']), 'Estimated opportunity value excluding lost'], ['Follow-ups due', (string) $counts['followups'], 'Due today or earlier'], ['Conversion rate', $conversionRate . '%', 'Won against all prospects'], ['Top source', (string) $topSource, 'Highest current lead source count'], ['Pipeline stages', (string) count($statuses), 'New through lost'], ['Top owner', (string) $topOwner, 'Highest current value owner'], ['Recent activity', $prospects ? (string) $prospects[0]['updated_at'] : 'No updates', 'Latest CRM record update']]; ?><?php foreach ($widgets as $widget): ?><article class="admin-panel prospect-widget" data-prospect-widget><div class="widget-actions"><button type="button" title="Move widget">Move</button><button type="button" title="Edit widget">Edit</button><button type="button" title="Remove widget">Remove</button></div><span><?= e($widget[0]) ?></span><strong><?= e($widget[1]) ?></strong><p><?= e($widget[2]) ?></p></article><?php endforeach; ?></section></section>
          <?php endif; ?>
        </main>
      </div>
    </div>
  </body>
</html>
