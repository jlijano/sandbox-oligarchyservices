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
function prospects_percent($value): string { return number_format((float) $value, 0) . '%'; }

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
            if (!$rows) throw new RuntimeException('No valid import rows were found. Start each row with a business name.');
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
$openedProspect = null;
$counts = ['total' => 0, 'new' => 0, 'in_progress' => 0, 'warm' => 0, 'converted' => 0, 'clients' => 0, 'closed' => 0, 'avg_percentage' => 0];
$industryCounts = [];
$priorityCounts = [];

if ($pdo instanceof PDO && $schemaReady) {
    $stmt = $pdo->prepare('SELECT * FROM prospects ORDER BY updated_at DESC, id DESC');
    $stmt->execute();
    $prospects = $stmt->fetchAll();
    $percentageTotal = 0.0;
    foreach ($prospects as $prospect) {
        $status = (string) $prospect['status'];
        $counts['total']++;
        $percentageTotal += (float) ($prospect['conversion_percentage'] ?? 0);
        if ($status === 'New Lead') $counts['new']++;
        if ($status === 'InProgress') $counts['in_progress']++;
        if ($status === 'Warm') $counts['warm']++;
        if ($status === 'Converted') $counts['converted']++;
        if ($status === 'Client') $counts['clients']++;
        if ($status === 'Closed / Lost') $counts['closed']++;
        $industry = trim((string) ($prospect['industry_category'] ?? ''));
        if ($industry !== '') $industryCounts[$industry] = ($industryCounts[$industry] ?? 0) + 1;
        $priority = trim((string) ($prospect['priority'] ?? ''));
        if ($priority !== '') $priorityCounts[$priority] = ($priorityCounts[$priority] ?? 0) + 1;
    }
    $counts['avg_percentage'] = $counts['total'] > 0 ? (int) round($percentageTotal / $counts['total']) : 0;
    $openId = filter_var($_GET['open'] ?? 0, FILTER_VALIDATE_INT) !== false ? (int) ($_GET['open'] ?? 0) : 0;
    if ($openId > 0) $openedProspect = prospect_fetch($pdo, $openId);
}
arsort($industryCounts);
arsort($priorityCounts);
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
            <div><p class="eyebrow">Playground</p><h2>Prospects</h2><p>Manage leads using the same structure as the Google Sheets pipeline: fit data, contact paths, buying triggers, outreach angles, and calculated conversion percentage.</p></div>
            <div class="hero-actions"><a class="primary-action" href="#prospect-form">Add Prospect</a><a class="secondary-action" href="#prospect-import">Import Leads</a></div>
          </header>
          <?php if ($schemaReady): ?>
          <section class="prospect-action-grid" aria-label="Prospect actions">
            <form class="admin-panel prospect-form" id="prospect-form" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_prospect"><div class="table-heading"><h3>Add prospect</h3><span>Live database record</span></div><div class="prospect-form-grid"><?php $blank = ['company'=>'','status'=>'New Lead','website'=>'','industry_category'=>'','conversion_percentage'=>'','notes'=>'','contact'=>'','email'=>'','phone'=>'','social_media_links'=>'','location'=>'','reason_relevant'=>'','pain_point_trigger'=>'','outreach_angle'=>'','priority'=>'Medium','last_contact'=>'','next_step'=>'','additional_notes'=>'','source'=>'','value'=>'0','owner'=>$displayName,'follow_up'=>'','last_activity'=>'']; ?><?php include __DIR__ . '/includes/prospect-form-fields.php'; ?></div><button class="button primary" type="submit">Create prospect</button></form>
            <form class="admin-panel prospect-form" id="prospect-import" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="import_prospects"><div class="table-heading"><h3>Import leads</h3><span>CSV rows</span></div><label>Lead rows<textarea name="import_rows" rows="8" placeholder="Business Name,Status,Website,Industry / Category,Percentage,Notes,Contact Person,Email,Phone,Social Media Links,Location,Reason Relevant,Pain Point / Buying Trigger,Outreach Angle,Priority,Last Contact,Next Step,Notes"></textarea></label><button class="button primary" type="submit">Import leads</button></form>
          </section>
          <?php if ($openedProspect): ?><section class="admin-panel prospect-detail" id="prospect-detail" aria-label="Open prospect detail"><div class="table-heading"><h3><?= e((string) $openedProspect['company']) ?></h3><span>Prospect #<?= e((string) $openedProspect['id']) ?></span></div><div class="prospect-detail-summary"><span class="prospect-pill status-<?= e(strtolower(str_replace([' ', '/'], '-', (string) $openedProspect['status']))) ?>"><?= e((string) $openedProspect['status']) ?></span><span class="prospect-pill priority-<?= e(strtolower((string) $openedProspect['priority'])) ?>"><?= e((string) $openedProspect['priority']) ?></span><span><?= e(prospects_percent($openedProspect['conversion_percentage'] ?? 0)) ?></span><span><?= e((string) ($openedProspect['industry_category'] ?: 'No industry')) ?></span></div><form class="prospect-form" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="update_prospect"><input type="hidden" name="prospect_id" value="<?= e((string) $openedProspect['id']) ?>"><div class="prospect-form-grid"><?php $blank = $openedProspect; include __DIR__ . '/includes/prospect-form-fields.php'; ?></div><div class="form-actions"><button class="button primary" type="submit">Save prospect</button><a class="secondary-action" href="/prospects.php">Close</a></div></form></section><?php endif; ?>
          <nav class="prospect-view-switcher" aria-label="Prospect views"><button class="is-active" type="button" data-prospect-view-button="list">List</button><button type="button" data-prospect-view-button="kanban">Kanban</button><button type="button" data-prospect-view-button="timeline">Timeline</button><button type="button" data-prospect-view-button="dashboard">Dashboard</button></nav>
          <section class="prospect-view is-active" id="prospect-list" data-prospect-view="list"><form class="admin-panel prospect-filters" data-prospect-filters><label>Search prospects<input type="search" data-prospect-search placeholder="Business, contact, industry, location"></label><label>Status<select data-prospect-status><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>"><?= e($status) ?></option><?php endforeach; ?></select></label><label>Priority<select data-prospect-priority><option value="">All priorities</option><?php foreach ($priorities as $priority): ?><option><?= e($priority) ?></option><?php endforeach; ?></select></label></form><div class="admin-panel table-panel"><div class="table-heading"><h3>Lead pipeline</h3><span data-prospect-result-count><?= e((string) $counts['total']) ?> prospects</span></div><?php if (!$prospects): ?><p class="empty-state">No prospects yet. Add a prospect or import lead rows to start the CRM workspace.</p><?php else: ?><div class="table-scroll"><table class="data-table prospects-table"><thead><tr><th>Business Name</th><th>Status</th><th>Website</th><th>Industry / Category</th><th>Percentage</th><th>Notes</th><th>Contact Person</th><th>Email</th><th>Phone</th><th>Social Media Links</th><th>Location</th><th>Reason Relevant</th><th>Pain Point / Buying Trigger</th><th>Outreach Angle</th><th>Priority</th><th>Last Contact</th><th>Next Step</th><th>Notes</th></tr></thead><tbody><?php foreach ($prospects as $prospect): ?><tr data-prospect-row data-search="<?= e(strtolower(implode(' ', array_map('strval', $prospect)))) ?>" data-status="<?= e((string) $prospect['status']) ?>" data-owner="" data-priority="<?= e((string) $prospect['priority']) ?>"><td><strong><?= e((string) $prospect['company']) ?></strong><small><a class="table-action" href="/prospects.php?open=<?= e((string) $prospect['id']) ?>#prospect-detail">Open</a></small></td><td><span class="prospect-pill status-<?= e(strtolower(str_replace([' ', '/'], '-', (string) $prospect['status']))) ?>"><?= e((string) $prospect['status']) ?></span></td><td><?php if ($prospect['website']): ?><a href="<?= e((string) $prospect['website']) ?>" target="_blank" rel="noopener"><?= e((string) $prospect['website']) ?></a><?php endif; ?></td><td><?= e((string) $prospect['industry_category']) ?></td><td class="numeric-cell"><?= e(prospects_percent($prospect['conversion_percentage'] ?? 0)) ?></td><td><?= e(prospect_excerpt((string) ($prospect['notes'] ?? ''))) ?></td><td><?= e((string) $prospect['contact']) ?></td><td><?php if ($prospect['email']): ?><a href="mailto:<?= e((string) $prospect['email']) ?>"><?= e((string) $prospect['email']) ?></a><?php endif; ?></td><td class="nowrap"><?= e((string) $prospect['phone']) ?></td><td><?= e(prospect_excerpt((string) ($prospect['social_media_links'] ?? ''), 80)) ?></td><td><?= e((string) $prospect['location']) ?></td><td><?= e(prospect_excerpt((string) ($prospect['reason_relevant'] ?? ''))) ?></td><td><?= e(prospect_excerpt((string) ($prospect['pain_point_trigger'] ?? ''))) ?></td><td><?= e(prospect_excerpt((string) ($prospect['outreach_angle'] ?? ''))) ?></td><td><span class="prospect-pill priority-<?= e(strtolower((string) $prospect['priority'])) ?>"><?= e((string) $prospect['priority']) ?></span></td><td class="nowrap"><?= e((string) $prospect['last_contact']) ?></td><td><?= e((string) $prospect['next_step']) ?></td><td><?= e(prospect_excerpt((string) ($prospect['additional_notes'] ?? ''))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
          <section class="prospect-view" id="prospect-kanban" data-prospect-view="kanban" hidden><div class="prospect-board" aria-label="Prospect pipeline board"><?php foreach ($kanbanStatuses as $status): $cards = array_values(array_filter($prospects, fn(array $p): bool => $p['status'] === $status)); ?><section class="kanban-column"><div class="kanban-column-header"><h3><?= e($status) ?></h3><span><?= e((string) count($cards)) ?></span></div><?php foreach ($cards as $card): ?><article class="kanban-card"><div><strong><?= e((string) $card['company']) ?></strong><small><?= e((string) $card['contact']) ?></small></div><div class="kanban-card-meta"><span><?= e(prospects_percent($card['conversion_percentage'] ?? 0)) ?></span><span class="prospect-pill priority-<?= e(strtolower((string) $card['priority'])) ?>"><?= e((string) $card['priority']) ?></span></div><div class="kanban-card-footer"><span><?= e((string) $card['next_step']) ?></span><span class="owner-avatar"><?= e(prospect_initials((string) $card['company'])) ?></span></div><a class="table-action" href="/prospects.php?open=<?= e((string) $card['id']) ?>#prospect-detail">Open</a></article><?php endforeach; ?></section><?php endforeach; ?></div></section>
          <section class="prospect-view" id="prospect-timeline" data-prospect-view="timeline" hidden><div class="timeline-groups"><?php foreach (['Today', 'This week', 'Later'] as $group): ?><section class="admin-panel timeline-group"><div class="table-heading"><h3><?= e($group) ?></h3><span>Last-contact recency</span></div><?php $groupHasRows = false; foreach ($prospects as $prospect): ?><?php if (prospect_timeline_group($prospect['last_contact'] ?? null) !== $group) continue; $groupHasRows = true; ?><article class="timeline-item"><div class="timeline-date"><?= e((string) ($prospect['last_contact'] ?: 'No date')) ?></div><div><strong><?= e((string) $prospect['company']) ?></strong><small><?= e((string) $prospect['status']) ?> &middot; <?= e((string) $prospect['contact']) ?></small></div><span><?= e(prospects_percent($prospect['conversion_percentage'] ?? 0)) ?></span><span><?= e((string) $prospect['next_step']) ?></span><span class="prospect-pill status-<?= e(strtolower(str_replace([' ', '/'], '-', (string) $prospect['status']))) ?>"><?= e((string) $prospect['status']) ?></span></article><?php endforeach; ?><?php if (!$groupHasRows): ?><p class="empty-state">No prospects in this group.</p><?php endif; ?></section><?php endforeach; ?></div></section>
          <section class="prospect-view" id="prospect-dashboard" data-prospect-view="dashboard" hidden><div class="section-heading-row"><div><p class="eyebrow">Configurable workspace</p><h2>Prospects dashboard</h2></div><button class="secondary-action" type="button" data-customize-dashboard>Customize dashboard</button></div><section class="prospect-widget-grid" aria-label="Prospect dashboard widgets"><?php $topIndustry = $industryCounts ? array_key_first($industryCounts) . ' ' . reset($industryCounts) : 'No industries yet'; $topPriority = $priorityCounts ? array_key_first($priorityCounts) . ' ' . reset($priorityCounts) : 'No priorities yet'; $widgets = [['Total prospects', (string) $counts['total'], 'All live CRM records'], ['New leads', (string) $counts['new'], 'Unqualified or newly found leads'], ['In progress', (string) $counts['in_progress'], 'Active outreach or research'], ['Warm', (string) $counts['warm'], 'Highest follow-up attention'], ['Converted', (string) $counts['converted'], 'Converted but not yet client'], ['Clients', (string) $counts['clients'], 'Active client records'], ['Closed / Lost', (string) $counts['closed'], 'Lost or inactive opportunities'], ['Average percentage', $counts['avg_percentage'] . '%', 'Average calculated conversion likelihood'], ['Top industry', (string) $topIndustry, 'Highest current industry count'], ['Top priority', (string) $topPriority, 'Most common priority']]; ?><?php foreach ($widgets as $widget): ?><article class="admin-panel prospect-widget" data-prospect-widget><div class="widget-actions"><button type="button" title="Move widget">Move</button><button type="button" title="Edit widget">Edit</button><button type="button" title="Remove widget">Remove</button></div><span><?= e($widget[0]) ?></span><strong><?= e($widget[1]) ?></strong><p><?= e($widget[2]) ?></p></article><?php endforeach; ?></section></section>
          <?php endif; ?>
        </main>
      </div>
    </div>
  </body>
</html>
