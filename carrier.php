<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/access-management.php';
require_once __DIR__ . '/includes/carrier.php';

$user = require_login();
$role = strtolower((string) ($user['role'] ?? 'client'));
if (!in_array($role, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo 'Only admins and editors can access Carrier.';
    exit;
}

$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$initials = strtoupper(substr($displayName, 0, 1));
$roleLabel = ucfirst($role);
$notice = $_SESSION['carrier_notice'] ?? null;
$error = $_SESSION['carrier_error'] ?? null;
unset($_SESSION['carrier_notice'], $_SESSION['carrier_error']);

function carrier_flash_success(string $message): void { $_SESSION['carrier_notice'] = $message; }
function carrier_flash_error(string $message): void { $_SESSION['carrier_error'] = $message; }
function carrier_redirect(array $params = [], string $anchor = ''): void { redirect_to('/carrier' . ($params ? '?' . http_build_query($params) : '') . $anchor); }
function carrier_post_int(string $key, int $default = 0): int { return filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT) !== false ? (int) ($_POST[$key] ?? $default) : $default; }
function carrier_status_class(string $value): string { return strtolower(str_replace([' ', '/'], '-', $value)); }
function carrier_datetime_input(?string $value): string
{
    if (!$value) return '';
    $time = strtotime($value);
    return $time ? date('Y-m-d\TH:i', $time) : '';
}
function carrier_display_date(?string $value): string
{
    if (!$value) return '';
    $time = strtotime($value);
    return $time ? date('M j, g:i A', $time) : $value;
}
function carrier_context_params(array $extra = []): array
{
    $statuses = carrier_statuses();
    $folder = trim((string) ($_POST['folder'] ?? $_GET['folder'] ?? 'inbox'));
    $status = trim((string) ($_POST['status'] ?? $_GET['status'] ?? ''));
    $search = trim((string) ($_POST['search'] ?? $_GET['search'] ?? ''));
    $open = filter_var($_POST['open'] ?? $_GET['open'] ?? 0, FILTER_VALIDATE_INT) !== false ? (int) ($_POST['open'] ?? $_GET['open'] ?? 0) : 0;
    if (!in_array($folder, ['inbox', 'unread', 'starred', 'archived', 'all'], true)) $folder = 'inbox';
    if (!in_array($status, array_merge([''], $statuses), true)) $status = '';
    $params = ['folder' => $folder];
    if ($status !== '') $params['status'] = $status;
    if ($search !== '') $params['search'] = $search;
    if ($open > 0) $params['open'] = $open;
    foreach ($extra as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    return $params;
}
function carrier_context_url(array $extra = [], string $anchor = ''): string
{
    $params = carrier_context_params($extra);
    return '/carrier' . ($params ? '?' . http_build_query($params) : '') . $anchor;
}
function carrier_context_hidden_inputs(array $extra = []): string
{
    $html = '';
    foreach (carrier_context_params($extra) as $key => $value) {
        $html .= '<input type="hidden" name="' . e((string) $key) . '" value="' . e((string) $value) . '">';
    }
    return $html;
}
function carrier_sync_form_hidden_inputs(): string
{
    return '<input type="hidden" name="return_to" value="carrier">' . carrier_context_hidden_inputs();
}
function carrier_mail_settings_defaults(): array
{
    return [
        'host' => getenv('CARRIER_IMAP_HOST') !== false ? trim((string) getenv('CARRIER_IMAP_HOST')) : 'imap.hostinger.com',
        'port' => getenv('CARRIER_IMAP_PORT') !== false ? (int) getenv('CARRIER_IMAP_PORT') : 993,
        'flags' => getenv('CARRIER_IMAP_FLAGS') !== false ? trim((string) getenv('CARRIER_IMAP_FLAGS')) : '/imap/ssl',
        'mailbox' => getenv('CARRIER_IMAP_MAILBOX') !== false ? trim((string) getenv('CARRIER_IMAP_MAILBOX')) : 'INBOX',
        'username' => getenv('CARRIER_IMAP_USERNAME') !== false ? trim((string) getenv('CARRIER_IMAP_USERNAME')) : '',
        'limit_count' => getenv('CARRIER_IMAP_LIMIT') !== false ? (int) getenv('CARRIER_IMAP_LIMIT') : 50,
        'is_enabled' => 1,
        'has_saved_password' => false,
    ];
}
function carrier_mail_settings_for_form(?PDO $pdo): array
{
    $settings = carrier_mail_settings_defaults();
    if ($settings['port'] < 1 || $settings['port'] > 65535) $settings['port'] = 993;
    if ($settings['limit_count'] < 1 || $settings['limit_count'] > 200) $settings['limit_count'] = 50;
    if (!$pdo instanceof PDO) return $settings;
    try {
        $row = $pdo->query('SELECT host, port, flags, mailbox, username, password_ciphertext, limit_count, is_enabled FROM carrier_imap_settings WHERE id = 1 LIMIT 1')->fetch();
        if (!$row) return $settings;
        $settings['host'] = trim((string) ($row['host'] ?? $settings['host'])) ?: $settings['host'];
        $settings['port'] = (int) ($row['port'] ?? $settings['port']) ?: $settings['port'];
        $settings['flags'] = trim((string) ($row['flags'] ?? $settings['flags'])) ?: $settings['flags'];
        $settings['mailbox'] = trim((string) ($row['mailbox'] ?? $settings['mailbox'])) ?: $settings['mailbox'];
        $settings['username'] = trim((string) ($row['username'] ?? $settings['username']));
        $settings['limit_count'] = (int) ($row['limit_count'] ?? $settings['limit_count']) ?: $settings['limit_count'];
        $settings['is_enabled'] = (int) ($row['is_enabled'] ?? 1) === 1 ? 1 : 0;
        $settings['has_saved_password'] = trim((string) ($row['password_ciphertext'] ?? '')) !== '';
    } catch (Throwable $settingsError) {
        error_log('Carrier mail settings unavailable: ' . $settingsError->getMessage());
    }
    if ($settings['port'] < 1 || $settings['port'] > 65535) $settings['port'] = 993;
    if ($settings['limit_count'] < 1 || $settings['limit_count'] > 200) $settings['limit_count'] = 50;
    return $settings;
}

$pdo = null;
$schemaReady = false;
$schemaMessage = '';
try {
    $pdo = db();
    carrier_ensure_schema($pdo);
    $schemaReady = carrier_schema_ready($pdo);
} catch (Throwable $dbError) {
    error_log('Carrier database unavailable: ' . $dbError->getMessage());
    $schemaMessage = 'Carrier cannot connect to the portal database yet. Check the database config or run /update.php after deployment.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo instanceof PDO || !$schemaReady) {
        carrier_flash_error('Carrier database tables are not ready. Log in as an admin and run /update.php after deployment.');
        carrier_redirect(carrier_context_params(['open' => null]));
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        carrier_flash_error('Your session expired. Please refresh and try again.');
        carrier_redirect(carrier_context_params(['open' => null]));
    }
    try {
        $action = trim((string) ($_POST['action'] ?? ''));
        $id = carrier_post_int('carrier_id');
        if ($action === 'create_carrier_email') {
            $payload = carrier_payload_from_post();
            $newId = carrier_insert($pdo, $payload, (int) $user['id']);
            carrier_log_activity($pdo, (int) $user['id'], 'carrier email created', $newId, (string) $payload['subject']);
            carrier_flash_success('Carrier email added.');
            carrier_redirect(carrier_context_params(['open' => $newId]), '#carrier-preview');
        }
        if ($action === 'update_carrier_email') {
            if ($id <= 0 || !carrier_fetch($pdo, $id)) throw new RuntimeException('Choose a valid carrier email.');
            $payload = carrier_payload_from_post();
            carrier_update($pdo, $id, $payload, (int) $user['id']);
            carrier_log_activity($pdo, (int) $user['id'], 'carrier email updated', $id, (string) $payload['status']);
            carrier_flash_success('Carrier email updated.');
            carrier_redirect(carrier_context_params(['open' => $id]), '#carrier-preview');
        }
        if (in_array($action, ['archive_carrier_email', 'delete_carrier_email', 'mark_read', 'mark_unread', 'star_carrier_email', 'unstar_carrier_email'], true)) {
            if ($id <= 0 || !carrier_fetch($pdo, $id)) throw new RuntimeException('Choose a valid carrier email.');
            if ($action === 'delete_carrier_email') {
                $pdo->prepare('DELETE FROM carrier_emails WHERE id = ?')->execute([$id]);
                carrier_log_activity($pdo, (int) $user['id'], 'carrier email deleted', $id);
                carrier_flash_success('Carrier email deleted.');
                carrier_redirect(carrier_context_params(['open' => null]));
            }
            if ($action === 'archive_carrier_email') {
                $pdo->prepare("UPDATE carrier_emails SET status = 'Archived', updated_by = ?, updated_at = NOW() WHERE id = ?")->execute([(int) $user['id'], $id]);
                carrier_log_activity($pdo, (int) $user['id'], 'carrier email archived', $id);
                carrier_flash_success('Carrier email archived.');
                carrier_redirect(carrier_context_params(['open' => null]));
            }
            if ($action === 'mark_read' || $action === 'mark_unread') {
                $pdo->prepare('UPDATE carrier_emails SET is_read = ?, updated_by = ?, updated_at = NOW() WHERE id = ?')->execute([$action === 'mark_read' ? 1 : 0, (int) $user['id'], $id]);
                carrier_flash_success($action === 'mark_read' ? 'Marked as read.' : 'Marked as unread.');
            }
            if ($action === 'star_carrier_email' || $action === 'unstar_carrier_email') {
                $pdo->prepare('UPDATE carrier_emails SET is_starred = ?, updated_by = ?, updated_at = NOW() WHERE id = ?')->execute([$action === 'star_carrier_email' ? 1 : 0, (int) $user['id'], $id]);
                carrier_flash_success($action === 'star_carrier_email' ? 'Carrier email starred.' : 'Carrier email unstarred.');
            }
            carrier_redirect(carrier_context_params(['open' => $id]), '#carrier-preview');
        }
        throw new RuntimeException('Choose a valid Carrier action.');
    } catch (Throwable $postError) {
        carrier_flash_error($postError->getMessage());
        carrier_redirect(carrier_context_params(['open' => null]));
    }
}

$statuses = carrier_statuses();
$priorities = carrier_priorities();
$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$folder = trim((string) ($_GET['folder'] ?? 'inbox'));
if (!in_array($statusFilter, array_merge([''], $statuses), true)) $statusFilter = '';
if (!in_array($folder, ['inbox', 'unread', 'starred', 'archived', 'all'], true)) $folder = 'inbox';
$openedId = filter_var($_GET['open'] ?? 0, FILTER_VALIDATE_INT) !== false ? (int) ($_GET['open'] ?? 0) : 0;
$emails = [];
$openedEmail = null;
$counts = ['all' => 0, 'inbox' => 0, 'unread' => 0, 'starred' => 0, 'archived' => 0];
$statusCounts = array_fill_keys($statuses, 0);

if ($pdo instanceof PDO && $schemaReady) {
    $counts['all'] = (int) $pdo->query('SELECT COUNT(*) FROM carrier_emails')->fetchColumn();
    $counts['inbox'] = (int) $pdo->query("SELECT COUNT(*) FROM carrier_emails WHERE status <> 'Archived'")->fetchColumn();
    $counts['unread'] = (int) $pdo->query('SELECT COUNT(*) FROM carrier_emails WHERE is_read = 0')->fetchColumn();
    $counts['starred'] = (int) $pdo->query('SELECT COUNT(*) FROM carrier_emails WHERE is_starred = 1')->fetchColumn();
    $counts['archived'] = (int) $pdo->query("SELECT COUNT(*) FROM carrier_emails WHERE status = 'Archived'")->fetchColumn();
    foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM carrier_emails GROUP BY status')->fetchAll() as $row) {
        $statusCounts[(string) $row['status']] = (int) $row['total'];
    }

    $where = [];
    $params = [];
    if ($folder === 'inbox') $where[] = "status <> 'Archived'";
    if ($folder === 'unread') $where[] = 'is_read = 0';
    if ($folder === 'starred') $where[] = 'is_starred = 1';
    if ($folder === 'archived') $where[] = "status = 'Archived'";
    if ($statusFilter !== '') { $where[] = 'status = ?'; $params[] = $statusFilter; }
    if ($search !== '') {
        $where[] = '(carrier_name LIKE ? OR carrier_email LIKE ? OR subject LIKE ? OR message LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare('SELECT * FROM carrier_emails' . $whereSql . ' ORDER BY is_read ASC, is_starred DESC, received_at DESC, id DESC LIMIT 100');
    $stmt->execute($params);
    $emails = $stmt->fetchAll();
    if ($openedId > 0) $openedEmail = carrier_fetch($pdo, $openedId);
}

$mailSettings = carrier_mail_settings_for_form($pdo);
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Carrier | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-automation">
    <link rel="stylesheet" href="/assets/carrier.css?v=20260630-wide-modal">
    <link rel="stylesheet" href="/assets/carrier-outlook.css?v=20260703-outlook">
    <link rel="stylesheet" href="/assets/carrier-compose.css?v=20260703-compose">
    <script defer src="/assets/dashboard.js?v=20260630-carrier"></script>
  </head>
  <body class="dashboard-body carrier-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <?php access_sidebar('carrier', $roleLabel, $role); ?>
      <div class="sidebar-backdrop" data-sidebar-backdrop></div>
      <div class="dashboard-main carrier-main">
        <header class="dashboard-topbar carrier-topbar">
          <div class="topbar-left"><button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button><div><p class="eyebrow">Playground</p><h1 data-section-title>Carrier</h1></div></div>
          <div class="topbar-actions"><span class="role-pill"><?= e($roleLabel) ?></span><div class="user-chip" title="<?= e($user['email']) ?>"><span><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($user['email']) ?></small></div></div><a class="logout-link" href="/logout.php">Log out</a></div>
        </header>
        <main class="carrier-workspace">
          <?php if ($notice): ?><div class="dashboard-alert is-success" role="status"><?= e((string) $notice) ?></div><?php endif; ?>
          <?php if ($error): ?><div class="dashboard-alert is-error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>
          <?php if (!$schemaReady): ?><div class="dashboard-alert is-error" role="alert"><?= e($schemaMessage ?: 'Carrier tables are not ready. Run /update.php as an admin.') ?></div><?php endif; ?>

          <section class="carrier-ribbon" aria-label="Carrier mail actions">
            <div class="ribbon-tabs" aria-label="Carrier shortcuts">
              <a class="is-active" href="<?= e(carrier_context_url(['open' => null])) ?>">Home</a>
              <form method="post" action="/carrier-sync.php"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><?= carrier_sync_form_hidden_inputs() ?><button type="submit" name="action" value="sync_mail">Sync IMAP</button></form>
              <a href="#carrier-folders">Folders</a>
              <a href="#carrier-preview">Reading pane</a>
              <a href="#mail-settings">Settings</a>
            </div>
            <div class="ribbon-actions">
              <form method="post" action="/carrier-sync.php"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><?= carrier_sync_form_hidden_inputs() ?><button class="ribbon-action primary" type="submit" name="action" value="sync_mail"><strong>Sync</strong><span>Import IMAP</span></button></form>
              <form method="post" action="/carrier-sync.php"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><?= carrier_sync_form_hidden_inputs() ?><button class="ribbon-action" type="submit" name="action" value="sync_mail"><strong>Send / Receive</strong><span>Sync mailbox</span></button></form>
              <a class="ribbon-action" href="#compose-carrier"><strong>Manual add</strong><span>New record</span></a>
              <a class="ribbon-action" href="#mail-settings"><strong>Account</strong><span>Mail settings</span></a>
              <span class="ribbon-divider"></span>
              <?php if ($openedEmail): ?>
                <form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><?= carrier_context_hidden_inputs(['open' => (int) $openedEmail['id']]) ?><input type="hidden" name="carrier_id" value="<?= e((string) $openedEmail['id']) ?>"><button class="ribbon-action" type="submit" name="action" value="<?= (int) $openedEmail['is_read'] === 1 ? 'mark_unread' : 'mark_read' ?>"><strong><?= (int) $openedEmail['is_read'] === 1 ? 'Unread' : 'Read' ?></strong><span>Mark message</span></button></form>
                <form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><?= carrier_context_hidden_inputs(['open' => (int) $openedEmail['id']]) ?><input type="hidden" name="carrier_id" value="<?= e((string) $openedEmail['id']) ?>"><button class="ribbon-action" type="submit" name="action" value="archive_carrier_email"><strong>Archive</strong><span>Move message</span></button></form>
                <form method="post" data-confirm="Delete this carrier email permanently?"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><?= carrier_context_hidden_inputs(['open' => (int) $openedEmail['id']]) ?><input type="hidden" name="carrier_id" value="<?= e((string) $openedEmail['id']) ?>"><button class="ribbon-action danger" type="submit" name="action" value="delete_carrier_email"><strong>Delete</strong><span>Remove</span></button></form>
              <?php else: ?>
                <button class="ribbon-action is-disabled" type="button" disabled><strong>Read</strong><span>Select message</span></button>
                <button class="ribbon-action is-disabled" type="button" disabled><strong>Archive</strong><span>Select message</span></button>
              <?php endif; ?>
            </div>
          </section>

          <section class="carrier-shell" aria-label="Carrier inbox">
            <aside class="carrier-folders" id="carrier-folders" aria-label="Carrier folders">
              <div class="mailbox-title"><span>▾</span><strong>Carrier Mailbox</strong></div>
              <?php foreach ([['inbox','Inbox'],['unread','Unread'],['starred','Starred'],['archived','Archived'],['all','All mail']] as $folderItem): ?>
                <a class="folder-link <?= $folder === $folderItem[0] ? 'is-active' : '' ?>" href="<?= e(carrier_context_url(['folder' => $folderItem[0], 'open' => null])) ?>"><span><?= e($folderItem[1]) ?></span><strong><?= e((string) $counts[$folderItem[0]]) ?></strong></a>
              <?php endforeach; ?>
              <div class="folder-divider"></div>
              <p class="folder-heading">Status</p>
              <?php foreach ($statuses as $status): ?>
                <a class="folder-link compact <?= $statusFilter === $status ? 'is-active' : '' ?>" href="<?= e(carrier_context_url(['status' => $status, 'folder' => 'all', 'open' => null])) ?>"><span><?= e($status) ?></span><strong><?= e((string) ($statusCounts[$status] ?? 0)) ?></strong></a>
              <?php endforeach; ?>
            </aside>

            <section class="carrier-inbox" aria-label="Carrier email list">
              <div class="message-list-title"><strong><?= e(ucfirst($folder)) ?></strong><span><?= e((string) count($emails)) ?> shown</span></div>
              <form class="carrier-searchbar" method="get" action="/carrier">
                <input type="hidden" name="folder" value="<?= e($folder) ?>">
                <label><span class="sr-only">Search carrier emails</span><input name="search" type="search" value="<?= e($search) ?>" placeholder="Search mail"></label>
                <select name="status" aria-label="Filter by status"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select>
                <button class="button primary" type="submit">Search</button>
                <a class="secondary-action" href="<?= e(carrier_context_url(['status' => null, 'search' => null, 'open' => null])) ?>">Clear</a>
              </form>

              <div class="carrier-list" role="list">
                <?php if (!$emails): ?><p class="carrier-empty">No carrier emails found.</p><?php endif; ?>
                <?php foreach ($emails as $mail): ?>
                  <article class="carrier-row <?= (int) $mail['is_read'] === 0 ? 'is-unread' : '' ?> <?= $openedEmail && (int) $openedEmail['id'] === (int) $mail['id'] ? 'is-selected' : '' ?>" role="listitem">
                    <form method="post" class="star-form"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><?= carrier_context_hidden_inputs(['open' => (int) $mail['id']]) ?><input type="hidden" name="carrier_id" value="<?= e((string) $mail['id']) ?>"><input type="hidden" name="action" value="<?= (int) $mail['is_starred'] === 1 ? 'unstar_carrier_email' : 'star_carrier_email' ?>"><button type="submit" class="star-button <?= (int) $mail['is_starred'] === 1 ? 'is-starred' : '' ?>" aria-label="<?= (int) $mail['is_starred'] === 1 ? 'Unstar' : 'Star' ?> carrier email">★</button></form>
                    <a class="carrier-row-link" href="<?= e(carrier_context_url(['open' => (int) $mail['id']], '#carrier-preview')) ?>">
                      <span class="sender"><?= e($mail['carrier_name']) ?></span>
                      <span class="subject"><strong><?= e($mail['subject']) ?></strong><small><?= e($mail['preview_text']) ?></small></span>
                      <span class="status status-<?= e(carrier_status_class((string) $mail['status'])) ?>"><?= e($mail['status']) ?></span>
                      <span class="priority"><?= e($mail['priority']) ?></span>
                      <time datetime="<?= e((string) $mail['received_at']) ?>"><?= e(carrier_display_date((string) $mail['received_at'])) ?></time>
                    </a>
                  </article>
                <?php endforeach; ?>
              </div>
            </section>

            <aside class="carrier-preview" id="carrier-preview" aria-label="Carrier email preview">
              <?php if (!$openedEmail): ?>
                <div class="preview-empty"><h2>Select an item to read</h2><p>Choose a message from the list to view it in the reading pane.</p></div>
              <?php else: ?>
                <div class="preview-card">
                  <div class="preview-actions"><span class="status status-<?= e(carrier_status_class((string) $openedEmail['status'])) ?>"><?= e($openedEmail['status']) ?></span><a class="secondary-action" href="#edit-carrier">Edit details</a></div>
                  <div class="reading-pane-header"><h2><?= e($openedEmail['subject']) ?></h2></div>
                  <p class="preview-from"><strong><?= e($openedEmail['carrier_name']) ?></strong><?php if ($openedEmail['carrier_email']): ?> &lt;<?= e($openedEmail['carrier_email']) ?>&gt;<?php endif; ?></p>
                  <p class="preview-meta">Received <?= e(carrier_display_date((string) $openedEmail['received_at'])) ?> · <?= e($openedEmail['priority']) ?> priority</p>
                  <div class="preview-message"><?= nl2br(e((string) $openedEmail['message'])) ?></div>
                  <?php if (trim((string) $openedEmail['attachments']) !== ''): ?><p class="preview-attachments"><strong>Attachments:</strong> <?= e($openedEmail['attachments']) ?></p><?php endif; ?>
                  <div class="preview-button-row">
                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><?= carrier_context_hidden_inputs(['open' => (int) $openedEmail['id']]) ?><input type="hidden" name="carrier_id" value="<?= e((string) $openedEmail['id']) ?>"><input type="hidden" name="action" value="<?= (int) $openedEmail['is_read'] === 1 ? 'mark_unread' : 'mark_read' ?>"><button class="secondary-action" type="submit"><?= (int) $openedEmail['is_read'] === 1 ? 'Mark unread' : 'Mark read' ?></button></form>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><?= carrier_context_hidden_inputs(['open' => (int) $openedEmail['id']]) ?><input type="hidden" name="carrier_id" value="<?= e((string) $openedEmail['id']) ?>"><input type="hidden" name="action" value="archive_carrier_email"><button class="secondary-action" type="submit">Archive</button></form>
                    <form method="post" data-confirm="Delete this carrier email permanently?"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><?= carrier_context_hidden_inputs(['open' => (int) $openedEmail['id']]) ?><input type="hidden" name="carrier_id" value="<?= e((string) $openedEmail['id']) ?>"><input type="hidden" name="action" value="delete_carrier_email"><button class="table-action danger" type="submit">Delete</button></form>
                  </div>
                </div>
              <?php endif; ?>
            </aside>
          </section>

          <section class="carrier-modal" id="compose-carrier" aria-labelledby="compose-carrier-title" role="dialog" aria-modal="true" tabindex="-1">
            <a class="modal-close" href="<?= e(carrier_context_url()) ?>" aria-label="Close compose form">×</a>
            <form class="carrier-form" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><?= carrier_context_hidden_inputs() ?><input type="hidden" name="action" value="create_carrier_email"><h2 id="compose-carrier-title">Add Carrier Email</h2><?php $formMail = null; require __DIR__ . '/includes/carrier-form-fields.php'; ?><button class="button primary" type="submit">Save email</button></form>
          </section>

          <section class="carrier-modal" id="mail-settings" aria-labelledby="mail-settings-title" role="dialog" aria-modal="true" tabindex="-1">
            <a class="modal-close" href="<?= e(carrier_context_url()) ?>" aria-label="Close mail settings">×</a>
            <form class="carrier-form" method="post" action="/carrier-sync.php">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <?= carrier_sync_form_hidden_inputs() ?>
              <h2 id="mail-settings-title">Carrier Mail Settings</h2>
              <p class="preview-meta">Connect a Hostinger mailbox with IMAP, similar to adding an account in Thunderbird, Outlook, or Gmail. Defaults are already set for Hostinger.</p>
              <?php if (!extension_loaded('imap')): ?><div class="dashboard-alert is-error" role="alert">PHP IMAP is not enabled on this server yet. Enable the <strong>imap</strong> PHP extension in Hostinger PHP Configuration before syncing.</div><?php endif; ?>
              <div class="carrier-form-grid">
                <label>Email address<input name="username" type="email" autocomplete="username" value="<?= e($mailSettings['username']) ?>" placeholder="name@yourdomain.com" required></label>
                <label>Password<input name="password" type="password" autocomplete="current-password" placeholder="<?= !empty($mailSettings['has_saved_password']) ? 'Saved - leave blank to keep' : 'Mailbox password' ?>"></label>
                <label>IMAP host<input name="host" type="text" value="<?= e($mailSettings['host']) ?>" required></label>
                <label>Port<input name="port" type="number" min="1" max="65535" value="<?= e((string) $mailSettings['port']) ?>" required></label>
                <label>Security flags<input name="flags" type="text" value="<?= e($mailSettings['flags']) ?>" required></label>
                <label>Mailbox<input name="mailbox" type="text" value="<?= e($mailSettings['mailbox']) ?>" required></label>
                <label>Messages to check<input name="limit_count" type="number" min="1" max="200" value="<?= e((string) $mailSettings['limit_count']) ?>" required></label>
                <label><span>Enabled</span><span class="preview-meta"><input name="is_enabled" type="checkbox" value="1" <?= !empty($mailSettings['is_enabled']) ? 'checked' : '' ?>> Use this account for Carrier imports</span></label>
              </div>
              <p class="preview-meta">Hostinger default: imap.hostinger.com, port 993, SSL, mailbox INBOX. Passwords are stored encrypted in the portal database.</p>
              <div class="preview-button-row">
                <button class="button primary" type="submit" name="action" value="save_settings">Save settings</button>
                <button class="button primary" type="submit" name="action" value="save_and_sync">Save and sync now</button>
                <button class="secondary-action" type="submit" name="action" value="sync_mail">Sync using saved settings</button>
              </div>
            </form>
          </section>

          <?php if ($openedEmail): ?>
          <section class="carrier-modal" id="edit-carrier" aria-labelledby="edit-carrier-title" role="dialog" aria-modal="true" tabindex="-1">
            <a class="modal-close" href="<?= e(carrier_context_url(['open' => (int) $openedEmail['id']], '#carrier-preview')) ?>" aria-label="Close edit form">×</a>
            <form class="carrier-form" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><?= carrier_context_hidden_inputs(['open' => (int) $openedEmail['id']]) ?><input type="hidden" name="action" value="update_carrier_email"><input type="hidden" name="carrier_id" value="<?= e((string) $openedEmail['id']) ?>"><h2 id="edit-carrier-title">Edit Carrier Email</h2><?php $formMail = $openedEmail; require __DIR__ . '/includes/carrier-form-fields.php'; ?><button class="button primary" type="submit">Update email</button></form>
          </section>
          <?php endif; ?>
        </main>
      </div>
    </div>
    <script>
      (() => {
        const modalSelector = '.carrier-modal';
        let lastFocus = null;
        const activeModal = () => {
          if (!window.location.hash) return null;
          const target = document.getElementById(window.location.hash.slice(1));
          return target && target.matches(modalSelector) ? target : null;
        };
        const focusModal = () => {
          const modal = activeModal();
          if (!modal) return;
          lastFocus = document.activeElement instanceof HTMLElement ? document.activeElement : lastFocus;
          const focusTarget = modal.querySelector('input, select, textarea, button, a[href]') || modal;
          if (focusTarget instanceof HTMLElement) focusTarget.focus({ preventScroll: true });
        };
        window.addEventListener('hashchange', focusModal);
        document.addEventListener('keydown', (event) => {
          if (event.key !== 'Escape') return;
          const modal = activeModal();
          if (!modal) return;
          const close = modal.querySelector('.modal-close');
          if (close instanceof HTMLAnchorElement) {
            event.preventDefault();
            window.location.href = close.href;
          }
        });
        window.addEventListener('hashchange', () => {
          if (activeModal() || !(lastFocus instanceof HTMLElement)) return;
          lastFocus.focus({ preventScroll: true });
        });
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', focusModal, { once: true });
        } else {
          focusModal();
        }
      })();
    </script>
  </body>
</html>
