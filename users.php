<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password-change.php';
require_once __DIR__ . '/includes/access-management.php';

$user = access_admin_user();
$pdo = db();
password_change_ensure_schema($pdo);
access_management_ensure_schema($pdo);

$role = strtolower((string) ($user['role'] ?? 'client'));
$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$initials = strtoupper(substr($displayName, 0, 1));
$roleLabel = ucfirst($role);
$notice = $_SESSION['users_notice'] ?? null;
$error = $_SESSION['users_error'] ?? null;
unset($_SESSION['users_notice'], $_SESSION['users_error']);

function users_flash_success(string $message): void { $_SESSION['users_notice'] = $message; }
function users_flash_error(string $message): void { $_SESSION['users_error'] = $message; }
function users_redirect(array $params = []): void { redirect_to('/users.php' . ($params ? '?' . http_build_query($params) : '')); }
function users_post_string(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $default)); }
function users_post_int(string $key, int $default = 0): int { return filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT) !== false ? (int) $_POST[$key] : $default; }
function users_checked_value(string $key): int { return isset($_POST[$key]) ? 1 : 0; }
function users_log_activity(PDO $pdo, int $actorId, string $action, string $targetType = '', ?int $targetId = null, string $details = ''): void
{
    access_log_activity($pdo, $actorId, $action, $targetType, $targetId, $details);
}
function users_mail_trace_error(Throwable $error): string
{
    $message = trim(str_replace(["\r", "\n"], ' ', $error->getMessage()));
    return $message !== '' ? substr($message, 0, 220) : 'Unknown Mail Trace write error.';
}
function users_record_invite_mail_trace(PDO $pdo, array $invite, string $sender): void
{
    account_confirmation_mail_trace_ensure_schema($pdo);
    $email = (string) ($invite['email'] ?? 'unknown recipient');
    $sent = !empty($invite['sent']);
    $stmt = $pdo->prepare('INSERT INTO mail_trace (recipient, subject, provider, status, message) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $email,
        account_confirmation_subject(),
        'resend-invite-button',
        $sent ? 'sent' : 'failed',
        'Resend invite button used. Sender: ' . $sender . '. PHP mail result: ' . ($sent ? 'accepted' : 'not accepted') . '.',
    ]);
}
function users_option_exists(PDO $pdo, string $table, int $id): bool
{
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT id FROM ' . access_sql_name($table) . ' WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return (bool) $stmt->fetch();
}
function users_department_matches_company(PDO $pdo, int $departmentId, int $companyId): bool
{
    if ($departmentId <= 0 || $companyId <= 0) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT id FROM departments WHERE id = ? AND (company_id = ? OR company_id IS NULL) LIMIT 1');
    $stmt->execute([$departmentId, $companyId]);
    return (bool) $stmt->fetch();
}

$roles = $pdo->query('SELECT name FROM roles WHERE is_active = 1 ORDER BY FIELD(name, "admin", "editor", "support", "client") DESC, name ASC')->fetchAll(PDO::FETCH_COLUMN);
$allowedRoles = array_values(array_unique(array_merge(['admin', 'editor', 'support', 'client'], array_map('strval', $roles))));
$companies = access_fetch_options($pdo, 'companies');
$departments = $pdo->query('SELECT d.id, d.name, d.company_id, c.name AS company_name FROM departments d LEFT JOIN companies c ON c.id = d.company_id WHERE d.is_active = 1 ORDER BY c.name ASC, d.name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        users_flash_error('Your session expired. Please refresh and try again.');
        users_redirect();
    }

    $action = users_post_string('action');

    try {
        if ($action === 'resend_invite') {
            $userId = users_post_int('user_id');
            if ($userId <= 0) {
                throw new RuntimeException('Choose a valid user account.');
            }

            $invite = account_confirmation_issue_invite($pdo, $userId);
            users_log_activity($pdo, (int) $user['id'], 'invite resent', 'user', $userId, (string) $invite['email']);

            $sender = account_confirmation_from_address();
            $traceRecorded = false;
            $traceError = '';
            try {
                users_record_invite_mail_trace($pdo, $invite, $sender);
                $traceRecorded = true;
            } catch (Throwable $traceException) {
                $traceError = users_mail_trace_error($traceException);
                error_log('Resend invite Mail Trace write failed: ' . $traceException->getMessage());
            }

            $traceStatus = $traceRecorded ? ' Mail Trace recorded.' : ' Mail Trace could not be written: ' . $traceError . '.';
            if ($invite['sent']) {
                users_flash_success('PHP accepted the invite for ' . $invite['email'] . ' from ' . $sender . '.' . $traceStatus);
            } else {
                users_flash_error('Invite was generated, but PHP mail did not accept it from ' . $sender . '.' . $traceStatus);
            }
            users_redirect();
        }

        if ($action === 'save_user') {
            $userId = users_post_int('user_id');
            $name = users_post_string('full_name');
            $email = strtolower(users_post_string('email'));
            $newRole = strtolower(users_post_string('role', 'client'));
            $isActive = users_checked_value('is_active');
            $password = (string) ($_POST['password'] ?? '');
            $companyId = users_post_int('company_id');
            $departmentId = users_post_int('department_id');
            $companyId = $companyId > 0 ? $companyId : null;
            $departmentId = $departmentId > 0 ? $departmentId : null;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid user email.');
            if ($name === '') throw new RuntimeException('User name is required.');
            if (!in_array($newRole, $allowedRoles, true)) throw new RuntimeException('Choose a valid role.');
            if ($userId === 0 && $password === '') $password = account_confirmation_generate_temporary_password();
            if ($password !== '' && strlen($password) < 12) throw new RuntimeException('Password must be at least 12 characters.');
            if ($companyId !== null && !users_option_exists($pdo, 'companies', $companyId)) throw new RuntimeException('Choose a valid company.');
            if ($departmentId !== null && !users_option_exists($pdo, 'departments', $departmentId)) throw new RuntimeException('Choose a valid department.');
            if ($companyId !== null && $departmentId !== null && !users_department_matches_company($pdo, $departmentId, $companyId)) throw new RuntimeException('Choose a department that belongs to the selected company.');

            if ($userId > 0) {
                $params = [$email, $name, $newRole, $isActive, $companyId, $departmentId, $userId];
                $sql = 'UPDATE users SET email = ?, full_name = ?, role = ?, is_active = ?, company_id = ?, department_id = ?, updated_at = NOW() WHERE id = ?';
                if ($password !== '') {
                    $sql = 'UPDATE users SET email = ?, full_name = ?, role = ?, is_active = ?, company_id = ?, department_id = ?, password_hash = ?, updated_at = NOW() WHERE id = ?';
                    $params = [$email, $name, $newRole, $isActive, $companyId, $departmentId, password_hash($password, PASSWORD_DEFAULT), $userId];
                }
                $pdo->prepare($sql)->execute($params);
                users_log_activity($pdo, (int) $user['id'], 'user updated', 'user', $userId, $email);
                users_flash_success('User updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, role, is_active, company_id, department_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name, $newRole, $isActive, $companyId, $departmentId]);
                users_log_activity($pdo, (int) $user['id'], 'user created', 'user', (int) $pdo->lastInsertId(), $email);
                users_flash_success('User created.');
            }
            users_redirect();
        }

        if ($action === 'delete_user' || $action === 'deactivate_user') {
            $userId = users_post_int('user_id');
            if ($userId <= 0 || $userId === (int) $user['id']) throw new RuntimeException('Choose another user account.');
            if ($action === 'delete_user') {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
                users_log_activity($pdo, (int) $user['id'], 'user deleted', 'user', $userId);
                users_flash_success('User deleted.');
            } else {
                $pdo->prepare('UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?')->execute([$userId]);
                users_log_activity($pdo, (int) $user['id'], 'user deactivated', 'user', $userId);
                users_flash_success('User deactivated.');
            }
            users_redirect();
        }
    } catch (Throwable $error) {
        users_flash_error($error->getMessage());
        users_redirect();
    }
}

$userSearch = trim((string) ($_GET['user_search'] ?? ''));
$userRoleFilter = strtolower(trim((string) ($_GET['user_role'] ?? '')));
$userStatusFilter = strtolower(trim((string) ($_GET['user_status'] ?? '')));
if (!in_array($userRoleFilter, array_merge([''], $allowedRoles), true)) $userRoleFilter = '';
if (!in_array($userStatusFilter, ['', 'active', 'inactive'], true)) $userStatusFilter = '';
$userPageRaw = filter_var($_GET['user_page'] ?? 1, FILTER_VALIDATE_INT);
$userPage = $userPageRaw === false ? 1 : max(1, (int) $userPageRaw);
$usersPerPage = 10;
$userWhere = [];
$userParams = [];
if ($userSearch !== '') {
    $userWhere[] = '(u.full_name LIKE ? OR u.email LIKE ? OR c.name LIKE ? OR d.name LIKE ?)';
    $searchLike = '%' . $userSearch . '%';
    array_push($userParams, $searchLike, $searchLike, $searchLike, $searchLike);
}
if ($userRoleFilter !== '') {
    $userWhere[] = 'u.role = ?';
    $userParams[] = $userRoleFilter;
}
if ($userStatusFilter !== '') {
    $userWhere[] = 'u.is_active = ?';
    $userParams[] = $userStatusFilter === 'active' ? 1 : 0;
}
$joinSql = ' FROM users u LEFT JOIN companies c ON c.id = u.company_id LEFT JOIN departments d ON d.id = u.department_id';
$userWhereSql = $userWhere ? ' WHERE ' . implode(' AND ', $userWhere) : '';
$countStmt = $pdo->prepare('SELECT COUNT(*)' . $joinSql . $userWhereSql);
$countStmt->execute($userParams);
$totalUsers = (int) $countStmt->fetchColumn();
$totalUserPages = max(1, (int) ceil($totalUsers / $usersPerPage));
$userPage = min($userPage, $totalUserPages);
$userOffset = ($userPage - 1) * $usersPerPage;
$userSql = 'SELECT u.id, u.email, u.full_name, u.role, u.is_active, u.company_id, u.department_id, u.last_login_at, u.created_at, u.email_confirmed_at, u.email_confirmation_expires_at, c.name AS company_name, d.name AS department_name' . $joinSql . $userWhereSql . ' ORDER BY u.created_at DESC, u.id DESC LIMIT ' . $usersPerPage . ' OFFSET ' . $userOffset;
$userStmt = $pdo->prepare($userSql);
$userStmt->execute($userParams);
$users = $userStmt->fetchAll();
$counts = [
    'total_users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'active_users' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn(),
    'inactive_users' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 0')->fetchColumn(),
    'admin_users' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
];
$userFilterParams = [];
if ($userSearch !== '') $userFilterParams['user_search'] = $userSearch;
if ($userRoleFilter !== '') $userFilterParams['user_role'] = $userRoleFilter;
if ($userStatusFilter !== '') $userFilterParams['user_status'] = $userStatusFilter;
$prevUserUrl = '/users.php?' . http_build_query(array_merge($userFilterParams, ['user_page' => max(1, $userPage - 1)]));
$nextUserUrl = '/users.php?' . http_build_query(array_merge($userFilterParams, ['user_page' => min($totalUserPages, $userPage + 1)]));
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Users | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-automation">
    <style>
      .users-toolbar { align-items: center; }
      .icon-action { gap: 9px; }
      .icon-action .plus-mark { display: inline-grid; width: 22px; height: 22px; place-items: center; border-radius: 999px; background: rgba(255,255,255,0.16); font-size: 1.15rem; line-height: 1; }
      .users-table-wrap { display: grid; gap: 16px; }
      .user-modal[hidden] { display: none; }
      .user-modal { position: fixed; inset: 0; z-index: 80; display: grid; place-items: center; padding: 18px; }
      .user-modal-backdrop { position: absolute; inset: 0; border: 0; background: rgba(0,0,0,0.72); cursor: pointer; }
      .user-modal-dialog { position: relative; width: min(640px, 100%); max-height: min(760px, calc(100dvh - 36px)); overflow: auto; border: 1px solid rgba(90,93,99,0.48); border-radius: 8px; background: linear-gradient(135deg, rgba(32,32,36,0.98), rgba(13,13,15,0.98)); box-shadow: 0 28px 80px rgba(0,0,0,0.46); }
      .user-modal-header { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 18px 18px 0; }
      .user-modal-header h3 { margin: 0; }
      .user-modal-close { display: inline-grid; width: 38px; height: 38px; place-items: center; border: 1px solid rgba(90,93,99,0.5); border-radius: 8px; background: #17171a; color: #fff; cursor: pointer; font-size: 1.35rem; line-height: 1; }
      .user-modal .admin-panel { border: 0; border-radius: 0; background: transparent; box-shadow: none; }
      body.user-modal-open { overflow: hidden; }
      @media (max-width: 680px) { .users-toolbar { align-items: stretch; } .users-toolbar .primary-action { width: 100%; } .user-modal { align-items: end; padding: 12px; } .user-modal-dialog { width: 100%; max-height: calc(100dvh - 24px); } }
    </style>
    <script defer src="/assets/dashboard.js?v=20260621-automation"></script>
  </head>
  <body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <?php access_sidebar('users', $roleLabel); ?>
      <div class="sidebar-backdrop" data-sidebar-backdrop></div>
      <div class="dashboard-main">
        <header class="dashboard-topbar"><div class="topbar-left"><button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button><div><p class="eyebrow">Access control</p><h1 data-section-title>Users</h1></div></div><div class="topbar-actions"><span class="role-pill"><?= e($roleLabel) ?></span><div class="user-chip" title="<?= e($user['email']) ?>"><span><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($user['email']) ?></small></div></div><a class="logout-link" href="/logout.php">Log out</a></div></header>
        <main class="dashboard-content">
          <?php if ($notice): ?><div class="dashboard-alert is-success" role="status"><?= e((string) $notice) ?></div><?php endif; ?>
          <?php if ($error): ?><div class="dashboard-alert is-error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>
          <section class="dashboard-section users-section is-active" id="users" data-dashboard-section data-section-label="Users">
            <div class="section-heading-row users-toolbar"><div><p class="eyebrow">Access control</p><h2>Users</h2></div><button class="primary-action icon-action" type="button" data-add-user aria-haspopup="dialog" aria-controls="user-modal"><span class="plus-mark" aria-hidden="true">+</span><span>Add user</span></button></div>
            <section class="user-summary-grid" aria-label="User account summary"><article><span>Total users</span><strong><?= e((string) $counts['total_users']) ?></strong></article><article><span>Active</span><strong><?= e((string) $counts['active_users']) ?></strong></article><article><span>Inactive</span><strong><?= e((string) $counts['inactive_users']) ?></strong></article><article><span>Admins</span><strong><?= e((string) $counts['admin_users']) ?></strong></article></section>
            <form class="admin-panel filter-panel" method="get" action="/users.php"><label>Search users<input name="user_search" type="search" value="<?= e($userSearch) ?>" placeholder="Name, email, company, or department"></label><label>Role<select name="user_role"><option value="">All roles</option><?php foreach ($allowedRoles as $r): ?><option value="<?= e($r) ?>" <?= $userRoleFilter === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option><?php endforeach; ?></select></label><label>Status<select name="user_status"><option value="">All statuses</option><option value="active" <?= $userStatusFilter === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $userStatusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></label><div class="filter-actions"><button class="button primary" type="submit">Apply filters</button><a class="secondary-action" href="/users.php">Clear</a></div></form>
            <div class="users-table-wrap">
              <div class="admin-panel table-panel"><div class="table-heading"><h3>User accounts</h3><span><?= e((string) $totalUsers) ?> result<?= $totalUsers === 1 ? '' : 's' ?></span></div><?php if (!$users): ?><p class="empty-state">No users match the current filters.</p><?php else: ?><div class="table-scroll"><table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Company</th><th>Department</th><th>Status</th><th>Confirmation</th><th>Last login</th><th></th></tr></thead><tbody><?php foreach ($users as $row): $isConfirmed = !empty($row['email_confirmed_at']); ?><tr><td><strong><?= e($row['full_name']) ?></strong></td><td><?= e($row['email']) ?></td><td><span class="status-badge"><?= e($row['role']) ?></span></td><td><?= e((string) ($row['company_name'] ?? 'Unassigned')) ?></td><td><?= e((string) ($row['department_name'] ?? 'Unassigned')) ?></td><td><span class="status-badge <?= (int) $row['is_active'] === 1 ? 'is-active' : 'is-muted' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td><span class="status-badge <?= $isConfirmed ? 'is-active' : 'is-muted' ?>"><?= $isConfirmed ? 'Confirmed' : 'Pending confirmation' ?></span></td><td class="nowrap"><?= e((string) ($row['last_login_at'] ?? 'Never')) ?></td><td class="row-actions"><button class="table-action" type="button" data-edit-user data-id="<?= e((string) $row['id']) ?>" data-name="<?= e($row['full_name']) ?>" data-email="<?= e($row['email']) ?>" data-role="<?= e($row['role']) ?>" data-company="<?= e((string) ($row['company_id'] ?? '')) ?>" data-department="<?= e((string) ($row['department_id'] ?? '')) ?>" data-active="<?= e((string) $row['is_active']) ?>">Edit</button><?php if (!$isConfirmed): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="resend_invite"><input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>"><button class="table-action" type="submit">Resend invite</button></form><?php endif; ?><?php if ((int) $row['id'] !== (int) $user['id']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="deactivate_user"><input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>"><button class="table-action" type="submit">Deactivate</button></form><form method="post" data-confirm="Delete this user permanently?"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>"><button class="table-action danger" type="submit">Delete</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php if ($totalUserPages > 1): ?><nav class="pagination" aria-label="User pages"><a class="secondary-action<?= $userPage <= 1 ? ' is-disabled' : '' ?>" href="<?= e($prevUserUrl) ?>">Previous</a><span>Page <?= e((string) $userPage) ?> of <?= e((string) $totalUserPages) ?></span><a class="secondary-action<?= $userPage >= $totalUserPages ? ' is-disabled' : '' ?>" href="<?= e($nextUserUrl) ?>">Next</a></nav><?php endif; ?><?php endif; ?></div>
            </div>
            <div class="user-modal" id="user-modal" data-user-modal role="dialog" aria-modal="true" aria-labelledby="user-modal-title" hidden>
              <button class="user-modal-backdrop" type="button" data-user-modal-close aria-label="Close user form"></button>
              <div class="user-modal-dialog">
                <div class="user-modal-header"><h3 id="user-modal-title" data-user-form-title>Create user</h3><button class="user-modal-close" type="button" data-user-modal-close aria-label="Close user form">×</button></div>
                <form class="admin-panel" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_user"><input type="hidden" name="user_id" data-user-id value="0"><label>Full name<input name="full_name" data-user-name required></label><label>Email<input name="email" type="email" data-user-email required></label><label>Role<select name="role" data-user-role><?php foreach ($allowedRoles as $r): ?><option value="<?= e($r) ?>"><?= e(ucfirst($r)) ?></option><?php endforeach; ?></select></label><label>Company<select name="company_id" data-user-company><option value="">Unassigned</option><?php foreach ($companies as $company): ?><option value="<?= e((string) $company['id']) ?>"><?= e($company['name']) ?></option><?php endforeach; ?></select></label><label>Department<select name="department_id" data-user-department><option value="">Unassigned</option><?php foreach ($departments as $department): ?><option value="<?= e((string) $department['id']) ?>" data-company-id="<?= e((string) ($department['company_id'] ?? '')) ?>"><?= e(($department['company_name'] ? $department['company_name'] . ' / ' : '') . $department['name']) ?></option><?php endforeach; ?></select></label><label class="check-row"><input name="is_active" type="checkbox" value="1" data-user-active checked><span>Active</span></label><label>Password<input name="password" type="password" autocomplete="new-password" minlength="12" placeholder="Set or reset password"></label><div class="form-actions"><button class="button primary" type="submit">Save user</button><button class="button secondary" type="button" data-reset-user-form>Cancel</button></div></form>
              </div>
            </div>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>