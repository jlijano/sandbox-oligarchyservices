<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$pdo = db();
$role = strtolower((string) ($user['role'] ?? 'client'));
$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$initials = strtoupper(substr($displayName, 0, 1));
$allowedRoles = ['admin', 'editor', 'support', 'client'];
$canManageUsers = $role === 'admin';
$canManageContent = in_array($role, ['admin', 'editor'], true);
$canViewActivity = in_array($role, ['admin', 'editor', 'support'], true);
$notice = $_SESSION['dashboard_notice'] ?? null;
$error = $_SESSION['dashboard_error'] ?? null;
unset($_SESSION['dashboard_notice'], $_SESSION['dashboard_error']);

function flash_success(string $message): void { $_SESSION['dashboard_notice'] = $message; }
function flash_error(string $message): void { $_SESSION['dashboard_error'] = $message; }
function dashboard_redirect(string $section): void { redirect_to('/dashboard.php#' . $section); }
function post_string(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $default)); }
function post_int(string $key, int $default = 0): int { return filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT) !== false ? (int) $_POST[$key] : $default; }
function checked_value(string $key): int { return isset($_POST[$key]) ? 1 : 0; }
function role_badge(string $value): string { return in_array($value, ['admin','editor','support','client'], true) ? $value : 'client'; }
function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
    return trim($slug, '-');
}
function content_excerpt(string $value, int $length = 96): string
{
    $plain = trim(strip_tags($value));
    if (strlen($plain) <= $length) return $plain;
    return substr($plain, 0, $length - 3) . '...';
}
function table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}
function log_activity(PDO $pdo, int $actorId, string $action, string $targetType = '', ?int $targetId = null, string $details = ''): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, $targetType, $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $e) {
        error_log('Activity log skipped: ' . $e->getMessage());
    }
}
function setting_value(array $settings, string $key, string $default = ''): string
{
    return array_key_exists($key, $settings) ? (string) $settings[$key] : $default;
}
function require_content_permission(bool $allowed): void
{
    if (!$allowed) {
        flash_error('You do not have permission to manage this section.');
        dashboard_redirect('overview');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash_error('Your session expired. Please refresh and try again.');
        dashboard_redirect('overview');
    }

    $action = post_string('action');

    try {
        if ($action === 'save_user') {
            require_content_permission($canManageUsers);
            $userId = post_int('user_id');
            $name = post_string('full_name');
            $email = strtolower(post_string('email'));
            $newRole = role_badge(strtolower(post_string('role', 'client')));
            $isActive = checked_value('is_active');
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
                log_activity($pdo, (int) $user['id'], 'user updated', 'user', $userId, $email);
                flash_success('User updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name, $newRole, $isActive]);
                log_activity($pdo, (int) $user['id'], 'user created', 'user', (int) $pdo->lastInsertId(), $email);
                flash_success('User created.');
            }
            dashboard_redirect('users');
        }

        if ($action === 'delete_user' || $action === 'deactivate_user') {
            require_content_permission($canManageUsers);
            $userId = post_int('user_id');
            if ($userId <= 0 || $userId === (int) $user['id']) throw new RuntimeException('Choose another user account.');
            if ($action === 'delete_user') {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
                log_activity($pdo, (int) $user['id'], 'user deleted', 'user', $userId);
                flash_success('User deleted.');
            } else {
                $pdo->prepare('UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?')->execute([$userId]);
                log_activity($pdo, (int) $user['id'], 'user deactivated', 'user', $userId);
                flash_success('User deactivated.');
            }
            dashboard_redirect('users');
        }

        if ($action === 'save_page') {
            require_content_permission($canManageContent);
            $pageId = post_int('page_id');
            $title = post_string('title');
            $slug = slugify(post_string('slug') ?: $title);
            $meta = post_string('meta_description');
            $body = trim((string) ($_POST['body'] ?? ''));
            $status = post_string('status') === 'published' ? 'published' : 'draft';
            if ($title === '' || $slug === '' || $body === '') throw new RuntimeException('Title, slug, and body are required.');

            $dupe = $pdo->prepare('SELECT id FROM pages WHERE slug = ? AND id <> ? LIMIT 1');
            $dupe->execute([$slug, $pageId]);
            if ($dupe->fetch()) throw new RuntimeException('That page slug is already in use.');

            if ($pageId > 0) {
                $stmt = $pdo->prepare('UPDATE pages SET title = ?, slug = ?, meta_description = ?, body = ?, status = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$title, $slug, $meta, $body, $status, $pageId]);
                log_activity($pdo, (int) $user['id'], $status === 'published' ? 'page updated/published' : 'page updated', 'page', $pageId, $slug);
                flash_success('Page updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO pages (title, slug, meta_description, body, status) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$title, $slug, $meta, $body, $status]);
                log_activity($pdo, (int) $user['id'], $status === 'published' ? 'page created/published' : 'page created', 'page', (int) $pdo->lastInsertId(), $slug);
                flash_success('Page saved.');
            }
            dashboard_redirect('pages');
        }

        if ($action === 'delete_page' || $action === 'toggle_page') {
            require_content_permission($canManageContent);
            $pageId = post_int('page_id');
            if ($action === 'delete_page') {
                $pdo->prepare('DELETE FROM pages WHERE id = ?')->execute([$pageId]);
                log_activity($pdo, (int) $user['id'], 'page deleted', 'page', $pageId);
                flash_success('Page deleted.');
            } else {
                $status = post_string('status') === 'published' ? 'published' : 'draft';
                $pdo->prepare('UPDATE pages SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $pageId]);
                log_activity($pdo, (int) $user['id'], $status === 'published' ? 'page published' : 'page unpublished', 'page', $pageId);
                flash_success($status === 'published' ? 'Page published.' : 'Page unpublished.');
            }
            dashboard_redirect('pages');
        }

        if ($action === 'save_nav') {
            require_content_permission($canManageContent);
            $navId = post_int('nav_id');
            $label = post_string('label');
            $url = post_string('url');
            $sort = post_int('sort_order');
            $visible = checked_value('is_visible');
            if ($label === '' || $url === '') throw new RuntimeException('Navigation label and URL are required.');
            if ($navId > 0) {
                $pdo->prepare('UPDATE navigation_items SET label = ?, url = ?, sort_order = ?, is_visible = ?, updated_at = NOW() WHERE id = ?')->execute([$label, $url, $sort, $visible, $navId]);
                flash_success('Navigation item updated.');
            } else {
                $pdo->prepare('INSERT INTO navigation_items (label, url, sort_order, is_visible) VALUES (?, ?, ?, ?)')->execute([$label, $url, $sort, $visible]);
                flash_success('Navigation item created.');
            }
            log_activity($pdo, (int) $user['id'], 'navigation updated', 'navigation', $navId ?: (int) $pdo->lastInsertId(), $label);
            dashboard_redirect('navigation');
        }

        if ($action === 'delete_nav') {
            require_content_permission($canManageContent);
            $navId = post_int('nav_id');
            $pdo->prepare('DELETE FROM navigation_items WHERE id = ?')->execute([$navId]);
            log_activity($pdo, (int) $user['id'], 'navigation deleted', 'navigation', $navId);
            flash_success('Navigation item deleted.');
            dashboard_redirect('navigation');
        }

        if ($action === 'save_settings') {
            require_content_permission($canManageContent);
            $pairs = [
                'site_name' => post_string('site_name', 'Oligarchy Services'),
                'contact_email' => strtolower(post_string('contact_email')),
                'analytics_enabled' => checked_value('analytics_enabled') ? '1' : '0',
                'analytics_provider' => post_string('analytics_provider', 'plausible'),
                'analytics_domain' => post_string('analytics_domain'),
            ];
            if ($pairs['contact_email'] !== '' && !filter_var($pairs['contact_email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid contact email.');
            $stmt = $pdo->prepare('INSERT INTO settings (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), updated_at = NOW()');
            foreach ($pairs as $key => $value) $stmt->execute([$key, $value]);
            log_activity($pdo, (int) $user['id'], 'settings updated', 'settings', null, 'portal settings');
            flash_success('Settings saved.');
            dashboard_redirect('settings');
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
        dashboard_redirect(post_string('return_section', 'overview'));
    }
}

$users = $pages = $navItems = $activity = [];
$settings = [];
$userSearch = trim((string) ($_GET['user_search'] ?? ''));
$userRoleFilter = strtolower(trim((string) ($_GET['user_role'] ?? '')));
$userStatusFilter = strtolower(trim((string) ($_GET['user_status'] ?? '')));
if (!in_array($userRoleFilter, ['', 'admin', 'editor', 'support', 'client'], true)) $userRoleFilter = '';
if (!in_array($userStatusFilter, ['', 'active', 'inactive'], true)) $userStatusFilter = '';
$userPageRaw = filter_var($_GET['user_page'] ?? 1, FILTER_VALIDATE_INT);
$userPage = $userPageRaw === false ? 1 : max(1, (int) $userPageRaw);
$usersPerPage = 10;
$totalUsers = 0;
$totalUserPages = 1;
$counts = ['active_users' => 0, 'pages' => 0, 'published_pages' => 0, 'nav_items' => 0];
try {
    $counts['active_users'] = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
    if (table_exists($pdo, 'pages')) {
        $counts['pages'] = (int) $pdo->query('SELECT COUNT(*) FROM pages')->fetchColumn();
        $counts['published_pages'] = (int) $pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'published'")->fetchColumn();
        $pages = $pdo->query('SELECT * FROM pages ORDER BY updated_at DESC, id DESC')->fetchAll();
    }
    if (table_exists($pdo, 'navigation_items')) {
        $counts['nav_items'] = (int) $pdo->query('SELECT COUNT(*) FROM navigation_items')->fetchColumn();
        $navItems = $pdo->query('SELECT * FROM navigation_items ORDER BY sort_order ASC, label ASC')->fetchAll();
    }
    if ($canManageUsers) {
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
    }
    if (table_exists($pdo, 'settings')) {
        foreach ($pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) $settings[$row['setting_key']] = $row['setting_value'];
    }
    if (table_exists($pdo, 'activity_log')) $activity = $pdo->query('SELECT a.*, u.email FROM activity_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 20')->fetchAll();
} catch (Throwable $e) {
    $error = $error ?: 'Some CMS tables are missing. Run the installer once to add the new dashboard tables.';
}

$nav = [
    ['id' => 'overview', 'label' => 'Overview', 'roles' => ['admin','editor','support','client']],
    ['id' => 'users', 'label' => 'Users', 'roles' => ['admin']],
    ['id' => 'pages', 'label' => 'Pages', 'roles' => ['admin','editor']],
    ['id' => 'navigation', 'label' => 'Navigation', 'roles' => ['admin','editor']],
    ['id' => 'settings', 'label' => 'Settings', 'roles' => ['admin','editor']],
    ['id' => 'activity', 'label' => 'Activity', 'roles' => ['admin','editor','support']],
];
$visibleNav = array_values(array_filter($nav, fn($item) => in_array($role, $item['roles'], true)));
$userFilterParams = [];
if ($userSearch !== '') $userFilterParams['user_search'] = $userSearch;
if ($userRoleFilter !== '') $userFilterParams['user_role'] = $userRoleFilter;
if ($userStatusFilter !== '') $userFilterParams['user_status'] = $userStatusFilter;
$roleLabel = ucfirst($role);
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Dashboard | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-cms-panel">
    <script defer src="/assets/dashboard.js?v=20260621-cms-panel"></script>
  </head>
  <body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <aside class="dashboard-sidebar" id="portal-sidebar" aria-label="Portal navigation">
        <div class="sidebar-brand">
          <a href="/dashboard.php#overview" aria-label="Oligarchy Services dashboard">OLIGARCHY</a>
          <button class="sidebar-collapse" type="button" data-sidebar-collapse aria-label="Collapse sidebar" aria-expanded="true">‹</button>
        </div>
        <nav class="sidebar-nav">
          <?php foreach ($visibleNav as $index => $item): ?>
            <a class="<?= $index === 0 ? 'is-active' : '' ?>" href="#<?= e($item['id']) ?>" data-section-link="<?= e($item['id']) ?>">
              <span class="nav-icon" aria-hidden="true"><?= e(substr($item['label'], 0, 1)) ?></span>
              <span class="nav-label"><?= e($item['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer"><span class="sidebar-status">Role</span><strong><?= e($roleLabel) ?></strong></div>
      </aside>

      <div class="sidebar-backdrop" data-sidebar-backdrop></div>

      <div class="dashboard-main">
        <header class="dashboard-topbar">
          <div class="topbar-left">
            <button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button>
            <div><p class="eyebrow">Admin portal</p><h1 data-section-title>Overview</h1></div>
          </div>
          <div class="topbar-actions">
            <span class="role-pill"><?= e($roleLabel) ?></span>
            <div class="user-chip" title="<?= e($user['email']) ?>"><span><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($user['email']) ?></small></div></div>
            <a class="logout-link" href="/logout.php">Log out</a>
          </div>
        </header>

        <main class="dashboard-content">
          <?php if ($notice): ?><div class="dashboard-alert is-success" role="status"><?= e((string) $notice) ?></div><?php endif; ?>
          <?php if ($error): ?><div class="dashboard-alert is-error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>

          <section class="dashboard-section is-active" id="overview" data-dashboard-section data-section-label="Overview">
            <div class="dashboard-hero compact-hero">
              <div><p class="eyebrow">Portal health</p><h2>Welcome back, <?= e($displayName) ?></h2><p>Manage accounts, website content, navigation, settings, and operational activity from one control panel.</p></div>
              <div class="hero-actions">
                <?php if ($canManageUsers): ?><a class="primary-action" href="#users" data-section-link="users">Create user</a><?php endif; ?>
                <?php if ($canManageContent): ?><a class="secondary-action" href="#pages" data-section-link="pages">Create page</a><a class="secondary-action" href="#settings" data-section-link="settings">Settings</a><?php endif; ?>
              </div>
            </div>
            <section class="stat-grid" aria-label="Dashboard metrics">
              <article class="stat-card"><span>Active users</span><strong><?= e((string) $counts['active_users']) ?></strong><p>Accounts currently allowed to sign in.</p></article>
              <article class="stat-card"><span>Pages</span><strong><?= e((string) $counts['pages']) ?></strong><p><?= e((string) $counts['published_pages']) ?> published.</p></article>
              <article class="stat-card"><span>Navigation links</span><strong><?= e((string) $counts['nav_items']) ?></strong><p>Visible and hidden menu records.</p></article>
            </section>
            <section class="workspace-grid two-up">
              <article class="workspace-card"><h3>Recent activity</h3><?php if (!$activity): ?><p>No audit activity has been recorded yet.</p><?php else: ?><ul class="mini-list"><?php foreach (array_slice($activity, 0, 5) as $item): ?><li><strong><?= e($item['action']) ?></strong><span><?= e($item['email'] ?? 'System') ?> · <?= e($item['created_at']) ?></span></li><?php endforeach; ?></ul><?php endif; ?></article>
              <article class="workspace-card"><h3>Quick actions</h3><div class="quick-actions"><?php foreach ($visibleNav as $item): ?><a class="secondary-action" href="#<?= e($item['id']) ?>" data-section-link="<?= e($item['id']) ?>"><?= e($item['label']) ?></a><?php endforeach; ?></div></article>
            </section>
          </section>

          <?php if ($canManageUsers): ?>
          <section class="dashboard-section" id="users" data-dashboard-section data-section-label="Users">
            <div class="section-heading-row"><div><p class="eyebrow">Access control</p><h2>Users</h2></div></div>
            <form class="admin-panel filter-panel" method="get" action="/dashboard.php#users">
              <label>Search users<input name="user_search" type="search" value="<?= e($userSearch) ?>" placeholder="Name or email"></label>
              <label>Role<select name="user_role"><option value="">All roles</option><?php foreach ($allowedRoles as $r): ?><option value="<?= e($r) ?>" <?= $userRoleFilter === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option><?php endforeach; ?></select></label>
              <label>Status<select name="user_status"><option value="">All statuses</option><option value="active" <?= $userStatusFilter === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $userStatusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></label>
              <div class="filter-actions"><button class="button primary" type="submit">Apply filters</button><a class="secondary-action" href="/dashboard.php#users">Clear</a></div>
            </form>
            <div class="panel-grid form-and-table">
              <form class="admin-panel" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_user"><input type="hidden" name="return_section" value="users"><input type="hidden" name="user_id" data-user-id value="0"><h3 data-user-form-title>Create user</h3><label>Full name<input name="full_name" data-user-name required></label><label>Email<input name="email" type="email" data-user-email required></label><label>Role<select name="role" data-user-role><?php foreach ($allowedRoles as $r): ?><option value="<?= e($r) ?>"><?= e(ucfirst($r)) ?></option><?php endforeach; ?></select></label><label class="check-row"><input name="is_active" type="checkbox" value="1" data-user-active checked><span>Active</span></label><label>Password<input name="password" type="password" autocomplete="new-password" minlength="10" placeholder="Set or reset password"></label><div class="form-actions"><button class="button primary" type="submit">Save user</button><button class="button secondary" type="button" data-reset-user-form>Clear</button></div></form>
              <div class="admin-panel table-panel"><div class="table-heading"><h3>User accounts</h3><span><?= e((string) $totalUsers) ?> result<?= $totalUsers === 1 ? '' : 's' ?></span></div><?php if (!$users): ?><p class="empty-state">No users match the current filters.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead><tbody><?php foreach ($users as $row): ?><tr><td><?= e($row['full_name']) ?></td><td><?= e($row['email']) ?></td><td><?= e($row['role']) ?></td><td><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></td><td><?= e((string) ($row['last_login_at'] ?? 'Never')) ?></td><td class="row-actions"><button class="table-action" type="button" data-edit-user data-id="<?= e((string) $row['id']) ?>" data-name="<?= e($row['full_name']) ?>" data-email="<?= e($row['email']) ?>" data-role="<?= e($row['role']) ?>" data-active="<?= e((string) $row['is_active']) ?>">Edit</button><?php if ((int) $row['id'] !== (int) $user['id']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="deactivate_user"><input type="hidden" name="return_section" value="users"><input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>"><button class="table-action" type="submit">Deactivate</button></form><form method="post" data-confirm="Delete this user permanently?"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="return_section" value="users"><input type="hidden" name="user_id" value="<?= e((string) $row['id']) ?>"><button class="table-action danger" type="submit">Delete</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php if ($totalUserPages > 1): ?><nav class="pagination" aria-label="User pages"><?php $prevParams = array_merge($userFilterParams, ['user_page' => max(1, $userPage - 1)]); $nextParams = array_merge($userFilterParams, ['user_page' => min($totalUserPages, $userPage + 1)]); ?><a class="secondary-action<?= $userPage <= 1 ? ' is-disabled' : '' ?>" href="/dashboard.php?<?= e(http_build_query($prevParams)) ?>#users">Previous</a><span>Page <?= e((string) $userPage) ?> of <?= e((string) $totalUserPages) ?></span><a class="secondary-action<?= $userPage >= $totalUserPages ? ' is-disabled' : '' ?>" href="/dashboard.php?<?= e(http_build_query($nextParams)) ?>#users">Next</a></nav><?php endif; ?><?php endif; ?></div>
            </div>
          </section>
          <?php endif; ?>

          <?php if ($canManageContent): ?>
          <section class="dashboard-section" id="pages" data-dashboard-section data-section-label="Pages">
            <div class="section-heading-row"><div><p class="eyebrow">Website CMS</p><h2>Pages</h2></div><a class="secondary-action" href="/page.php?slug=about" target="_blank" rel="noopener">View renderer</a></div>
            <div class="panel-grid form-and-table">
              <form class="admin-panel page-editor" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_page"><input type="hidden" name="return_section" value="pages"><input type="hidden" name="page_id" data-page-id value="0"><h3 data-page-form-title>Create page</h3><label>Title<input name="title" data-page-title required></label><label>Slug<input name="slug" data-page-slug placeholder="about" required></label><label>Meta description<textarea name="meta_description" data-page-meta rows="2"></textarea></label><label>Body<textarea name="body" data-page-body rows="12" required></textarea></label><label>Status<select name="status" data-page-status><option value="draft">Draft</option><option value="published">Published</option></select></label><div class="form-actions"><button class="button primary" type="submit">Save page</button><button class="button secondary" type="button" data-reset-page-form>Clear</button></div></form>
              <div class="admin-panel table-panel"><h3>Content library</h3><?php if (!$pages): ?><p class="empty-state">No pages yet. Create the first CMS page.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody><?php foreach ($pages as $row): ?><tr><td><strong><?= e($row['title']) ?></strong><small><?= e(content_excerpt($row['meta_description'] ?: $row['body'])) ?></small></td><td><a href="/page.php?slug=<?= e($row['slug']) ?>" target="_blank" rel="noopener"><?= e($row['slug']) ?></a></td><td><?= e($row['status']) ?></td><td><?= e($row['updated_at']) ?></td><td class="row-actions"><button class="table-action" type="button" data-edit-page data-id="<?= e((string) $row['id']) ?>" data-title="<?= e($row['title']) ?>" data-slug="<?= e($row['slug']) ?>" data-meta="<?= e($row['meta_description']) ?>" data-body="<?= e($row['body']) ?>" data-status="<?= e($row['status']) ?>">Edit</button><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="toggle_page"><input type="hidden" name="return_section" value="pages"><input type="hidden" name="page_id" value="<?= e((string) $row['id']) ?>"><input type="hidden" name="status" value="<?= $row['status'] === 'published' ? 'draft' : 'published' ?>"><button class="table-action" type="submit"><?= $row['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button></form><form method="post" data-confirm="Delete this page?"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_page"><input type="hidden" name="return_section" value="pages"><input type="hidden" name="page_id" value="<?= e((string) $row['id']) ?>"><button class="table-action danger" type="submit">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
            </div>
          </section>

          <section class="dashboard-section" id="navigation" data-dashboard-section data-section-label="Navigation">
            <div class="section-heading-row"><div><p class="eyebrow">Menu manager</p><h2>Navigation</h2></div></div>
            <div class="panel-grid form-and-table">
              <form class="admin-panel" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_nav"><input type="hidden" name="return_section" value="navigation"><input type="hidden" name="nav_id" data-nav-id value="0"><h3 data-nav-form-title>Create link</h3><label>Label<input name="label" data-nav-label required></label><label>URL or slug<input name="url" data-nav-url placeholder="/page.php?slug=about" required></label><label>Sort order<input name="sort_order" type="number" data-nav-sort value="10"></label><label class="check-row"><input name="is_visible" type="checkbox" value="1" data-nav-visible checked><span>Visible</span></label><div class="form-actions"><button class="button primary" type="submit">Save link</button><button class="button secondary" type="button" data-reset-nav-form>Clear</button></div></form>
              <div class="admin-panel table-panel"><h3>Menu links</h3><?php if (!$navItems): ?><p class="empty-state">No navigation records yet.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Label</th><th>URL</th><th>Sort</th><th>Visible</th><th></th></tr></thead><tbody><?php foreach ($navItems as $row): ?><tr><td><?= e($row['label']) ?></td><td><?= e($row['url']) ?></td><td><?= e((string) $row['sort_order']) ?></td><td><?= (int) $row['is_visible'] === 1 ? 'Yes' : 'No' ?></td><td class="row-actions"><button class="table-action" type="button" data-edit-nav data-id="<?= e((string) $row['id']) ?>" data-label="<?= e($row['label']) ?>" data-url="<?= e($row['url']) ?>" data-sort="<?= e((string) $row['sort_order']) ?>" data-visible="<?= e((string) $row['is_visible']) ?>">Edit</button><form method="post" data-confirm="Delete this navigation item?"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_nav"><input type="hidden" name="return_section" value="navigation"><input type="hidden" name="nav_id" value="<?= e((string) $row['id']) ?>"><button class="table-action danger" type="submit">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
            </div>
          </section>

          <section class="dashboard-section" id="settings" data-dashboard-section data-section-label="Settings">
            <div class="section-heading-row"><div><p class="eyebrow">Portal configuration</p><h2>Settings</h2></div></div>
            <form class="admin-panel settings-form" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_settings"><input type="hidden" name="return_section" value="settings"><label>Site name<input name="site_name" value="<?= e(setting_value($settings, 'site_name', 'Oligarchy Services')) ?>" required></label><label>Contact email<input name="contact_email" type="email" value="<?= e(setting_value($settings, 'contact_email')) ?>"></label><label class="check-row"><input name="analytics_enabled" type="checkbox" value="1" <?= setting_value($settings, 'analytics_enabled') === '1' ? 'checked' : '' ?>><span>Enable analytics</span></label><label>Analytics provider<input name="analytics_provider" value="<?= e(setting_value($settings, 'analytics_provider', 'plausible')) ?>"></label><label>Analytics domain<input name="analytics_domain" value="<?= e(setting_value($settings, 'analytics_domain')) ?>"></label><button class="button primary" type="submit">Save settings</button></form>
          </section>
          <?php endif; ?>

          <?php if ($canViewActivity): ?>
          <section class="dashboard-section" id="activity" data-dashboard-section data-section-label="Activity">
            <div class="section-heading-row"><div><p class="eyebrow">Audit log</p><h2>Activity</h2></div></div>
            <div class="admin-panel table-panel"><?php if (!$activity): ?><p class="empty-state">No activity has been recorded yet.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Action</th><th>Actor</th><th>Target</th><th>Details</th><th>Time</th></tr></thead><tbody><?php foreach ($activity as $row): ?><tr><td><?= e($row['action']) ?></td><td><?= e($row['email'] ?? 'System') ?></td><td><?= e(trim(($row['target_type'] ?? '') . ' #' . ($row['target_id'] ?? ''), ' #')) ?></td><td><?= e((string) $row['details']) ?></td><td><?= e($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
          </section>
          <?php endif; ?>
        </main>
      </div>
    </div>
  </body>
</html>
