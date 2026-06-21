<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$role = strtolower((string) ($user['role'] ?? 'client'));
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Only admins can manage users.';
    exit;
}

$pdo = db();
$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$initials = strtoupper(substr($displayName, 0, 1));
$roleLabel = ucfirst($role);
$allowedRoles = ['admin', 'editor', 'support', 'client'];
$notice = $_SESSION['users_notice'] ?? null;
$error = $_SESSION['users_error'] ?? null;
unset($_SESSION['users_notice'], $_SESSION['users_error']);

function users_flash_success(string $message): void { $_SESSION['users_notice'] = $message; }
function users_flash_error(string $message): void { $_SESSION['users_error'] = $message; }
function users_redirect(array $params = []): void { redirect_to('/users.php' . ($params ? '?' . http_build_query($params) : '')); }
function users_post_string(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $default)); }
function users_post_int(string $key, int $default = 0): int { return filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT) !== false ? (int) $_POST[$key] : $default; }
function users_checked_value(string $key): int { return isset($_POST[$key]) ? 1 : 0; }
function users_role_badge(string $value): string { return in_array($value, ['admin','editor','support','client'], true) ? $value : 'client'; }
function users_log_activity(PDO $pdo, int $actorId, string $action, string $targetType = '', ?int $targetId = null, string $details = ''): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, $targetType, $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $error) {
        error_log('User activity log skipped: ' . $error->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        users_flash_error('Your session expired. Please refresh and try again.');
        users_redirect();
    }

    $action = users_post_string('action');

    try {
        if ($action === 'save_user') {
            $userId = users_post_int('user_id');
            $name = users_post_string('full_name');
            $email = strtolower(users_post_string('email'));
            $newRole = users_role_badge(strtolower(users_post_string('role', 'client')));
            $isActive = users_checked_value('is_active');
            $password = (string) ($_POST['password'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid user email.');
            if ($name === '') throw new RuntimeException('User name is required.');
            if ($userId === 0 && strlen($password) < 10) throw new RuntimeException('New users need a password of at least 10 characters.');
            if ($password !== '' && strlen($password) < 10) throw new RuntimeException('Password must be at least 10 characters.');

            if ($userId > 0) {
                $params = [$email, $name, $newRole, $isActive, $userId];
                $sql = 'UPDATE users SET email = ?, full_name = ?, role = ?, is_active = ?, updated_at = NOW() WHERE id = ?';
                if ($password !== '') {
                    $sql = 'UPDATE users SET email = ?, full_name = ?, role = ?, is_active = ?, password_hash = ?, updated_at = NOW() WHERE id = ?';
                    $params = [$email, $name, $newRole, $isActive, password_hash($password, PASSWORD_DEFAULT), $userId];
                }
                $pdo->prepare($sql)->execute($params);
                users_log_activity($pdo, (int) $user['id'], 'user updated', 'user', $userId, $email);
                users_flash_success('User updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name, $newRole, $isActive]);
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
if (!in_array($userRoleFilter, ['', 'admin', 'editor', 'support', 'client'], true)) $userRoleFilter = '';
if (!in_array($userStatusFilter, ['', 'active', 'inactive'], true)) $userStatusFilter = '';
$userPageRaw = filter_var($_GET['user_page'] ?? 1, FILTER_VALIDATE_INT);
$userPage = $userPageRaw === false ? 1 : max(1, (int) $userPageRaw);
$usersPerPage = 10;
$userWhere = [];
$userParams = [];
if ($userSearch !== '') {
    $userWhere[] = '(full_name LIKE ? OR email LIKE ?)';
    $searchLike = '%' . $userSearch . '%';
    $userParams[] = $searchLike;
    $userParams[] = $searchLike;
}
if ($userRoleFilter !== '') {
    $userWhere[] = 'role = ?';
    $userParams[] = $userRoleFilter;
}
if ($userStatusFilter !== '') {
    $userWhere[] = 'is_active = ?';
    $userParams[] = $userStatusFilter === 'active' ? 1 : 0;
}
$userWhereSql = $userWhere ? ' WHERE ' . implode(' AND ', $userWhere) : '';
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM users' . $userWhereSql);
$countStmt->execute($userParams);
$totalUsers = (int) $countStmt->fetchColumn();
$totalUserPages = max(1, (int) ceil($totalUsers / $usersPerPage));
$userPage = min($userPage, $totalUserPages);
$userOffset = ($userPage - 1) * $usersPerPage;
$userSql = 'SELECT id, email, full_name, role, is_active, last_login_at, created_at FROM users' . $userWhereSql . ' ORDER BY created_at DESC, id DESC LIMIT ' . $usersPerPage . ' OFFSET ' . $userOffset;
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
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-users-page">
    <script defer src="/assets/dashboard.js?v=20260621-users-page"></script>
  </head>
  <body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <aside class="dashboard-sidebar" id="portal-sidebar" aria-label="Portal navigation">
        <div class="sidebar-brand"><a href="/dashboard.php#overview" aria-label="Oligarchy Services dashboard">OLIGARCHY</a><button class="sidebar-collapse" type="button" data-sidebar-collapse aria-label="Collapse sidebar" aria-expanded="true">‹</button></div>
        <nav class="sidebar-nav">
          <a href="/dashboard.php#overview"><span class="nav-icon" aria-hidden="true">O</span><span class="nav-label">Overview</span></a>
          <div class="sidebar-group is-open is-active" data-valley-group><button class="sidebar-group-toggle" type="button" data-valley-toggle aria-expanded="true"><span class="nav-icon" aria-hidden="true">V</span><span class="nav-label">Valley</span><span class="sidebar-group-caret" aria-hidden="true">&gt;</span></button><div class="sidebar-subnav" data-valley-subnav><a class="is-active" href="/users.php" aria-current="page"><span class="nav-icon" aria-hidden="true">U</span><span class="nav-label">Users</span></a></div></div>
          <a href="/dashboard.php#pages"><span class="nav-icon" aria-hidden="true">P</span><span class="nav-label">Pages</span></a>
          <a href="/admin-blogs.php"><span class="nav-icon" aria-hidden="true">B</span><span class="nav-label">Blogs</span></a>
          <a href="/dashboard.php#navigation"><span class="nav-icon" aria-hidden="true">N</span><span class="nav-label">Navigation</span></a>
          <a href="/dashboard.php#settings"><span class="nav-icon" aria-hidden="true">S</span><span class="nav-label">Settings</span></a>
          <a href="/dashboard.php#activity"><span class="nav-icon" aria-hidden="true">A</span><span class="nav-label">Activity</span></a>
        </nav>
        <div class="sidebar-footer"><span class="sidebar-status">Role</span><strong><?= e($roleLabel) ?></strong></div>
      </aside>
      <div class="sidebar-backdrop" data-sidebar-backdrop></div>
      <div class="dashboard-main">
        <header class="dashboard-topbar"><div class="topbar-left"><button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button><div><p class="eyebrow">Access control</p><h1 data-section-title>Users</h1></div></div><div class="topbar-actions"><span class="role-pill"><?= e($roleLabel) ?></span><div class="user-chip" title="<?= e($user['email']) ?>"><span><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($user['email']) ?></small></div></div><a class="logout-link" href="/logout.php">Log out</a></div></header>
        <main class="dashboard-content">
          <?php if ($notice): ?><div class="dashboard-alert is-success" role="status"><?= e((string) $notice) ?></div><?php endif; ?>
          <?php if ($error): ?><div class="dashboard-alert is-error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>
          <section class="dashboard-section users-section is-active" id="users" data-dashboard-section data-section-label="Users">
            <div class="section-heading-row"><div><p class="eyebrow">Access control</p><h2>Users</h2></div></div>
            <section class="user-summary-grid" aria-label="User account summary"><article><span>Total users</span><strong><?= e((string) $counts['total_users']) ?></strong></article><article><span>Active</span><strong><?= e((string) $counts['active_users']) ?></strong></article><article><span>Inactive</span><strong><?= e((string) $counts['inactive_users']) ?></strong></article><article><span>Admins</span><strong><?= e((string) $counts['admin_users']) ?></strong></article></section>
            <form class="admin-panel filter-panel" method="get" action="/users.php"><label>Search users<input name="user_search" type="search" value="<?= e($userSearch) ?>" placeholder="Name or email"></label><label>Role<select name="user_role"><option value="">All roles</option><?php foreach ($allowedRoles as $r): ?><option value="<?= e($r) ?>" <?= $userRoleFilter === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option><?php endforeach; ?></select></label><label>Status<select name="user_status"><option value="">All statuses</option><option value="active" <?= $userStatusFilter === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $userStatusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></label><div class="filter-actions"><button class="button primary" type="submit">Apply filters</button><a class="secondary-action" href="/users.php">Clear</a></div></form>
            <div class="panel-grid form-and-table">
              <form class="admin-panel" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_user"><input type="hidden" name="user_id" data-user-id value="0"><h3 data-user-form-title>Create user</h3><label>Full name<input name="full_name" data-user-name required></label><label>Email<input name="email" type="email" data-user-email required></label><label>Role<select name="role" data-user-role><?php foreach ($allowedRoles as $r): ?><option value="<?= e($r) ?>"><?= e(ucfirst($r)) ?></option><?php endforeach; ?></select></label><label class="check-row"><input name="is_active" type="checkbox" value="1" data-user-active checked><span>Active</span></label><label>Password<input name="password" type="password" autocomplete="new-password" minlength="10" placeholder="Set or reset password"></label><div class="form-actions"><button class="button primary" type="submit">Save user</button><button class="button secondary" type="button" data-reset-user-form>Clear</button></div></form>
              <div class="admin-panel table-panel"><div class="table-heading"><h3>User accounts</h3><span><?= e((string) $totalUsers) ?> result<?= $totalUsers === 1 ? '' : 's' ?></span></div><?php if (!$users): ?><p class="empty-state">No users match the current filters.</p><?php else: ?><div class="table-scroll"><table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead><tbody><?php foreach ($users as $row): ?><tr><td><strong><?= e($row['full_name']) ?></strong></td><td><?= e($row['email']) ?></td><td><span class="status-badge"><?= e($row['role']) ?></span></td><td><span class="status-badge <?= (int) $row['is_active'] === 1 ? 'is-active' : 'is-muted' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td><td class="nowrap"><?= e((string) ($row['last_login_at'] ?? 'Never')) ?></td><td class="row-actions"><button class="table-action" type="button" data-edit-user data-id="<?= e((string) $row['id']) ?>" data-name="<?= e($row['full_name']) ?>" data-email="<?= e($row['email']) ?>" data-role="<?= e($row['role']) ?>" data-active="<?= e((string) $row['is_active']) ?>">Edit</button><?php if ((int) $row['id'] !== (int) $user['id']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="deactivate_user"><input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>"><button class="table-action" type="submit">Deactivate</button></form><form method="post" data-confirm="Delete this user permanently?"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>"><button class="table-action danger" type="submit">Delete</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php if ($totalUserPages > 1): ?><nav class="pagination" aria-label="User pages"><a class="secondary-action<?= $userPage <= 1 ? ' is-disabled' : '' ?>" href="<?= e($prevUserUrl) ?>">Previous</a><span>Page <?= e((string) $userPage) ?> of <?= e((string) $totalUserPages) ?></span><a class="secondary-action<?= $userPage >= $totalUserPages ? ' is-disabled' : '' ?>" href="<?= e($nextUserUrl) ?>">Next</a></nav><?php endif; ?><?php endif; ?></div>
            </div>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>
