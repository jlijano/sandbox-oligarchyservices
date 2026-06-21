<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/installer.php';
require_once __DIR__ . '/includes/access-management.php';
require_once __DIR__ . '/includes/requests.php';

$user = require_login();
$pdo = db();
create_or_update_schema($pdo);
request_ensure_schema($pdo);

$role = strtolower((string) ($user['role'] ?? 'client'));
$canManageAll = request_can_manage_all($role);
$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$initials = strtoupper(substr($displayName, 0, 1));
$roleLabel = ucfirst($role);
$notice = $_SESSION['requests_notice'] ?? null;
$error = $_SESSION['requests_error'] ?? null;
unset($_SESSION['requests_notice'], $_SESSION['requests_error']);

function requests_flash_success(string $message): void { $_SESSION['requests_notice'] = $message; }
function requests_flash_error(string $message): void { $_SESSION['requests_error'] = $message; }
function requests_redirect(): void { redirect_to('/requests.php'); }
function requests_post_string(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $default)); }
function requests_post_int(string $key, int $default = 0): int { return filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT) !== false ? (int) $_POST[$key] : $default; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        requests_flash_error('Your session expired. Please refresh and try again.');
        requests_redirect();
    }

    try {
        $action = requests_post_string('action');

        if ($action === 'create_request') {
            $title = requests_post_string('title');
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($title === '' || $description === '') {
                throw new RuntimeException('Title and request details are required.');
            }
            if (strlen($title) > 190) {
                throw new RuntimeException('Request title must be 190 characters or fewer.');
            }

            $stmt = $pdo->prepare("INSERT INTO client_requests (user_id, title, description, status, priority) VALUES (?, ?, ?, 'new', 'normal')");
            $stmt->execute([(int) $user['id'], $title, $description]);
            $requestId = (int) $pdo->lastInsertId();
            request_log_activity($pdo, (int) $user['id'], 'request created', $requestId, $title);
            requests_flash_success('Request submitted.');
            requests_redirect();
        }

        if ($action === 'update_request') {
            if (!$canManageAll) {
                throw new RuntimeException('You do not have permission to update requests.');
            }

            $requestId = requests_post_int('request_id');
            $status = request_status(requests_post_string('status'));
            $priority = request_priority(requests_post_string('priority'));
            $assignedTo = requests_post_int('assigned_to');
            $assignedTo = $assignedTo > 0 ? $assignedTo : null;
            $internalNotes = trim((string) ($_POST['internal_notes'] ?? ''));

            $lookup = $pdo->prepare('SELECT id, title FROM client_requests WHERE id = ? LIMIT 1');
            $lookup->execute([$requestId]);
            $existing = $lookup->fetch();
            if (!$existing) {
                throw new RuntimeException('Choose a valid request.');
            }

            if ($assignedTo !== null) {
                $assignee = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1 AND role IN ('admin', 'editor', 'support') LIMIT 1");
                $assignee->execute([$assignedTo]);
                if (!$assignee->fetch()) {
                    throw new RuntimeException('Choose an active admin, editor, or support assignee.');
                }
            }

            $stmt = $pdo->prepare('UPDATE client_requests SET status = ?, priority = ?, assigned_to = ?, internal_notes = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$status, $priority, $assignedTo, $internalNotes, $requestId]);
            request_log_activity($pdo, (int) $user['id'], 'request updated', $requestId, $status . ' / ' . $priority);
            requests_flash_success('Request updated.');
            requests_redirect();
        }
    } catch (Throwable $postError) {
        requests_flash_error($postError->getMessage());
        requests_redirect();
    }
}

$statusFilter = request_status((string) ($_GET['status'] ?? ''));
if (!isset($_GET['status']) || !in_array($_GET['status'], request_statuses(), true)) {
    $statusFilter = '';
}
$priorityFilter = request_priority((string) ($_GET['priority'] ?? ''));
if (!isset($_GET['priority']) || !in_array($_GET['priority'], request_priorities(), true)) {
    $priorityFilter = '';
}
$search = trim((string) ($_GET['search'] ?? ''));

$where = [];
$params = [];
if (!$canManageAll) {
    $where[] = 'r.user_id = ?';
    $params[] = (int) $user['id'];
}
if ($statusFilter !== '') {
    $where[] = 'r.status = ?';
    $params[] = $statusFilter;
}
if ($priorityFilter !== '') {
    $where[] = 'r.priority = ?';
    $params[] = $priorityFilter;
}
if ($search !== '') {
    $where[] = '(r.title LIKE ? OR r.description LIKE ? OR owner.email LIKE ? OR owner.full_name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare('SELECT r.*, owner.email AS owner_email, owner.full_name AS owner_name, assignee.email AS assignee_email, assignee.full_name AS assignee_name FROM client_requests r LEFT JOIN users owner ON owner.id = r.user_id LEFT JOIN users assignee ON assignee.id = r.assigned_to' . $whereSql . ' ORDER BY r.updated_at DESC, r.id DESC');
$stmt->execute($params);
$requests = $stmt->fetchAll();

$counts = ['total' => 0, 'open' => 0, 'urgent' => 0, 'resolved' => 0];
if ($canManageAll) {
    $counts['total'] = (int) $pdo->query('SELECT COUNT(*) FROM client_requests')->fetchColumn();
    $counts['open'] = (int) $pdo->query("SELECT COUNT(*) FROM client_requests WHERE status NOT IN ('resolved','closed')")->fetchColumn();
    $counts['urgent'] = (int) $pdo->query("SELECT COUNT(*) FROM client_requests WHERE priority = 'urgent' AND status NOT IN ('resolved','closed')")->fetchColumn();
    $counts['resolved'] = (int) $pdo->query("SELECT COUNT(*) FROM client_requests WHERE status IN ('resolved','closed')")->fetchColumn();
} else {
    $countStmt = $pdo->prepare('SELECT COUNT(*) total, SUM(status NOT IN (\'resolved\',\'closed\')) open_count, SUM(priority = \'urgent\' AND status NOT IN (\'resolved\',\'closed\')) urgent_count, SUM(status IN (\'resolved\',\'closed\')) resolved_count FROM client_requests WHERE user_id = ?');
    $countStmt->execute([(int) $user['id']]);
    $row = $countStmt->fetch() ?: [];
    $counts = [
        'total' => (int) ($row['total'] ?? 0),
        'open' => (int) ($row['open_count'] ?? 0),
        'urgent' => (int) ($row['urgent_count'] ?? 0),
        'resolved' => (int) ($row['resolved_count'] ?? 0),
    ];
}

$assignees = $canManageAll ? request_manageable_users($pdo) : [];
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Requests | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-client-requests">
    <style>
      .request-filter-panel { margin: 0 0 14px; border: 0; padding: 0; background: transparent; box-shadow: none; }
      .inline-editor { position: relative; }
      .inline-editor summary { list-style: none; }
      .inline-editor summary::-webkit-details-marker { display: none; }
      .request-update-form { position: absolute; right: 0; z-index: 10; display: grid; gap: 12px; width: min(360px, 86vw); margin-top: 8px; border: 1px solid rgba(90,93,99,0.48); border-radius: 8px; padding: 14px; background: #141417; box-shadow: 0 18px 54px rgba(0,0,0,0.42); text-align: left; }
      .request-update-form label { display: grid; gap: 7px; color: #e6e6e8; font-size: 0.86rem; font-weight: 800; }
      .request-update-form textarea { min-height: 110px; }
      @media (max-width: 680px) { .request-update-form { position: fixed; inset: auto 12px 12px; width: auto; max-height: calc(100dvh - 24px); overflow: auto; } }
    </style>
    <script defer src="/assets/dashboard.js?v=20260621-client-requests"></script>
  </head>
  <body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <?php access_sidebar('requests', $roleLabel, $role); ?>
      <div class="sidebar-backdrop" data-sidebar-backdrop></div>
      <div class="dashboard-main">
        <header class="dashboard-topbar">
          <div class="topbar-left"><button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button><div><p class="eyebrow">Client portal</p><h1 data-section-title>Requests</h1></div></div>
          <div class="topbar-actions"><span class="role-pill"><?= e($roleLabel) ?></span><div class="user-chip" title="<?= e($user['email']) ?>"><span><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($user['email']) ?></small></div></div><a class="logout-link" href="/logout.php">Log out</a></div>
        </header>
        <main class="dashboard-content standalone-admin">
          <?php if ($notice): ?><div class="dashboard-alert is-success" role="status"><?= e((string) $notice) ?></div><?php endif; ?>
          <?php if ($error): ?><div class="dashboard-alert is-error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>

          <header class="dashboard-hero compact-hero">
            <div><p class="eyebrow"><?= $canManageAll ? 'Service operations' : 'Client requests' ?></p><h2>Requests</h2><p><?= $canManageAll ? 'Review incoming client requests, assign priority, track status, and keep internal notes.' : 'Submit service requests and track their current status from your portal workspace.' ?></p></div>
            <div class="hero-actions"><a class="secondary-action" href="/dashboard.php">Dashboard</a></div>
          </header>

          <section class="section-summary-grid" aria-label="Request summary">
            <article><span>Total</span><strong><?= e((string) $counts['total']) ?></strong></article>
            <article><span>Open</span><strong><?= e((string) $counts['open']) ?></strong></article>
            <article><span>Urgent</span><strong><?= e((string) $counts['urgent']) ?></strong></article>
            <article><span>Resolved or closed</span><strong><?= e((string) $counts['resolved']) ?></strong></article>
          </section>

          <section class="panel-grid form-and-table">
            <form class="admin-panel page-editor" method="post">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="create_request">
              <h2>Create request</h2>
              <label>Title<input name="title" maxlength="190" required placeholder="Brief request summary"></label>
              <label>Details<textarea name="description" rows="8" required placeholder="Describe what you need, the affected system, and any timing constraints."></textarea></label>
              <button class="button primary" type="submit">Submit request</button>
            </form>

            <div class="admin-panel table-panel">
              <div class="table-heading"><h2><?= $canManageAll ? 'Request queue' : 'My requests' ?></h2><span><?= e((string) count($requests)) ?> result<?= count($requests) === 1 ? '' : 's' ?></span></div>
              <form class="filter-panel request-filter-panel" method="get" action="/requests.php">
                <label>Search<input name="search" type="search" value="<?= e($search) ?>" placeholder="Title, details, or client"></label>
                <label>Status<select name="status"><option value="">All statuses</option><?php foreach (request_statuses() as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e(request_label($status)) ?></option><?php endforeach; ?></select></label>
                <label>Priority<select name="priority"><option value="">All priorities</option><?php foreach (request_priorities() as $priority): ?><option value="<?= e($priority) ?>" <?= $priorityFilter === $priority ? 'selected' : '' ?>><?= e(request_label($priority)) ?></option><?php endforeach; ?></select></label>
                <div class="filter-actions"><button class="button primary" type="submit">Apply filters</button><a class="secondary-action" href="/requests.php">Clear</a></div>
              </form>

              <?php if (!$requests): ?>
                <p class="empty-state">No requests match the current view.</p>
              <?php else: ?>
                <div class="table-scroll"><table class="data-table activity-table"><thead><tr><th>Request</th><th>Client</th><th>Status</th><th>Priority</th><th>Assigned</th><th>Updated</th><?php if ($canManageAll): ?><th></th><?php endif; ?></tr></thead><tbody>
                  <?php foreach ($requests as $row): ?>
                    <tr>
                      <td><strong><?= e($row['title']) ?></strong><small><?= e(request_excerpt((string) $row['description'])) ?></small><?php if ($canManageAll && trim((string) ($row['internal_notes'] ?? '')) !== ''): ?><small class="code-link">Internal note: <?= e(request_excerpt((string) $row['internal_notes'], 80)) ?></small><?php endif; ?></td>
                      <td><?= e((string) ($row['owner_name'] ?: $row['owner_email'])) ?></td>
                      <td><span class="status-badge <?= in_array($row['status'], ['resolved', 'closed'], true) ? 'is-active' : '' ?>"><?= e(request_label($row['status'])) ?></span></td>
                      <td><span class="status-badge <?= $row['priority'] === 'urgent' ? 'is-muted' : '' ?>"><?= e(request_label($row['priority'])) ?></span></td>
                      <td><?= e((string) ($row['assignee_name'] ?: $row['assignee_email'] ?: 'Unassigned')) ?></td>
                      <td class="nowrap"><?= e((string) $row['updated_at']) ?></td>
                      <?php if ($canManageAll): ?>
                        <td class="row-actions">
                          <details class="inline-editor"><summary class="table-action">Update</summary>
                            <form class="request-update-form" method="post">
                              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                              <input type="hidden" name="action" value="update_request">
                              <input type="hidden" name="request_id" value="<?= e((string) $row['id']) ?>">
                              <label>Status<select name="status"><?php foreach (request_statuses() as $status): ?><option value="<?= e($status) ?>" <?= $row['status'] === $status ? 'selected' : '' ?>><?= e(request_label($status)) ?></option><?php endforeach; ?></select></label>
                              <label>Priority<select name="priority"><?php foreach (request_priorities() as $priority): ?><option value="<?= e($priority) ?>" <?= $row['priority'] === $priority ? 'selected' : '' ?>><?= e(request_label($priority)) ?></option><?php endforeach; ?></select></label>
                              <label>Assignee<select name="assigned_to"><option value="0">Unassigned</option><?php foreach ($assignees as $assignee): ?><option value="<?= e((string) $assignee['id']) ?>" <?= (int) ($row['assigned_to'] ?? 0) === (int) $assignee['id'] ? 'selected' : '' ?>><?= e((string) ($assignee['full_name'] ?: $assignee['email'])) ?></option><?php endforeach; ?></select></label>
                              <label>Internal notes<textarea name="internal_notes" rows="4"><?= e((string) ($row['internal_notes'] ?? '')) ?></textarea></label>
                              <button class="button primary" type="submit">Save</button>
                            </form>
                          </details>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody></table></div>
              <?php endif; ?>
            </div>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>
