<?php
declare(strict_types=1);

function access_modules(): array
{
    return [
        'overview' => 'Dashboard / Overview',
        'users' => 'Users',
        'roles' => 'Roles',
        'companies' => 'Companies',
        'departments' => 'Departments',
        'pages' => 'Pages',
        'blogs' => 'Blogs',
        'navigation' => 'Navigation',
        'system_settings' => 'System Settings',
        'activity' => 'Activity',
        'system_health' => 'System Health',
        'mail_trace' => 'Mail Trace',
    ];
}

function access_sql_name(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }

    return '`' . $name . '`';
}

function access_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function access_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function access_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!access_column_exists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . access_sql_name($table) . ' ADD COLUMN ' . access_sql_name($column) . ' ' . $definition);
    }
}

function access_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function access_add_index_if_missing(PDO $pdo, string $table, string $index, string $columns): void
{
    if (!access_index_exists($pdo, $table, $index)) {
        $pdo->exec('ALTER TABLE ' . access_sql_name($table) . ' ADD INDEX ' . access_sql_name($index) . ' (' . $columns . ')');
    }
}

function access_management_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        description TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        module_permissions TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_roles_name (name),
        INDEX idx_roles_status (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS companies (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(140) NOT NULL,
        notes TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        module_permissions TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_companies_name (name),
        INDEX idx_companies_status (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_id INT UNSIGNED NULL,
        name VARCHAR(140) NOT NULL,
        notes TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        module_permissions TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_departments_company_name (company_id, name),
        INDEX idx_departments_status (is_active),
        INDEX idx_departments_company (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    access_add_column_if_missing($pdo, 'roles', 'description', 'TEXT NULL');
    access_add_column_if_missing($pdo, 'roles', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    access_add_column_if_missing($pdo, 'roles', 'module_permissions', 'TEXT NULL');
    access_add_index_if_missing($pdo, 'roles', 'idx_roles_status', '`is_active`');

    access_add_column_if_missing($pdo, 'companies', 'notes', 'TEXT NULL');
    access_add_column_if_missing($pdo, 'companies', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    access_add_column_if_missing($pdo, 'companies', 'module_permissions', 'TEXT NULL');
    access_add_index_if_missing($pdo, 'companies', 'idx_companies_status', '`is_active`');

    access_add_column_if_missing($pdo, 'departments', 'company_id', 'INT UNSIGNED NULL');
    access_add_column_if_missing($pdo, 'departments', 'notes', 'TEXT NULL');
    access_add_column_if_missing($pdo, 'departments', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    access_add_column_if_missing($pdo, 'departments', 'module_permissions', 'TEXT NULL');
    access_add_index_if_missing($pdo, 'departments', 'idx_departments_status', '`is_active`');
    access_add_index_if_missing($pdo, 'departments', 'idx_departments_company', '`company_id`');

    if (access_table_exists($pdo, 'users')) {
        access_add_column_if_missing($pdo, 'users', 'company_id', 'INT UNSIGNED NULL');
        access_add_column_if_missing($pdo, 'users', 'department_id', 'INT UNSIGNED NULL');
        access_add_index_if_missing($pdo, 'users', 'idx_users_company', '`company_id`');
        access_add_index_if_missing($pdo, 'users', 'idx_users_department', '`department_id`');
    }

    $seed = $pdo->prepare('INSERT INTO roles (name, description, is_active, module_permissions) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
    $all = array_keys(access_modules());
    $seed->execute(['admin', 'Full administrative access.', access_encode_modules($all)]);
    $seed->execute(['editor', 'Content manager access.', access_encode_modules(['overview', 'pages', 'blogs', 'navigation', 'system_settings', 'activity', 'system_health', 'mail_trace'])]);
    $seed->execute(['support', 'Support and activity review access.', access_encode_modules(['overview', 'users', 'activity', 'system_health', 'mail_trace'])]);
    $seed->execute(['client', 'Client dashboard access.', access_encode_modules(['overview'])]);
}

function access_admin_user(): array
{
    $user = require_login();
    if (strtolower((string) ($user['role'] ?? 'client')) !== 'admin') {
        http_response_code(403);
        echo 'Only admins can manage access settings.';
        exit;
    }

    return $user;
}

function access_post_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function access_post_int(string $key, int $default = 0): int
{
    return filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT) !== false ? (int) $_POST[$key] : $default;
}

function access_encode_modules(array $modules): string
{
    $allowed = array_keys(access_modules());
    $clean = array_values(array_intersect($allowed, array_unique(array_map('strval', $modules))));
    return json_encode($clean, JSON_UNESCAPED_SLASHES) ?: '[]';
}

function access_decode_modules(?string $stored): array
{
    $decoded = json_decode((string) $stored, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_intersect(array_keys(access_modules()), array_map('strval', $decoded)));
}

function access_module_labels(array $moduleKeys): string
{
    $modules = access_modules();
    $labels = [];
    foreach ($moduleKeys as $key) {
        $labels[] = $modules[$key] ?? $key;
    }

    return implode(', ', $labels);
}

function access_selected_modules(): array
{
    $raw = $_POST['modules'] ?? [];
    return is_array($raw) ? $raw : [];
}

function access_flash(string $scope, string $type, string $message): void
{
    $_SESSION[$scope . '_' . $type] = $message;
}

function access_redirect(string $path, array $params = []): void
{
    redirect_to($path . ($params ? '?' . http_build_query($params) : ''));
}

function access_log_activity(PDO $pdo, int $actorId, string $action, string $targetType, ?int $targetId = null, string $details = ''): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, $targetType, $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $error) {
        error_log('Access activity log skipped: ' . $error->getMessage());
    }
}

function access_fetch_options(PDO $pdo, string $table): array
{
    if (!access_table_exists($pdo, $table)) {
        return [];
    }

    return $pdo->query('SELECT id, name FROM ' . access_sql_name($table) . ' WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
}

function access_sidebar(string $active, string $roleLabel, string $role = 'admin'): void
{
    $role = strtolower($role);
    $isAdmin = $role === 'admin';
    $canManageContent = in_array($role, ['admin', 'editor'], true);
    $canViewActivity = in_array($role, ['admin', 'editor', 'support'], true);
    $settingsItems = [];
    if ($canManageContent) {
        $settingsItems[] = ['href' => '/dashboard.php#system-settings', 'label' => 'System Settings', 'key' => 'system-settings'];
    }
    if ($canViewActivity) {
        $settingsItems[] = ['href' => '/dashboard.php#activity', 'label' => 'Activity', 'key' => 'activity'];
        $settingsItems[] = ['href' => '/dashboard.php#system-health', 'label' => 'System Health', 'key' => 'system-health'];
        $settingsItems[] = ['href' => '/dashboard.php#mail-trace', 'label' => 'Mail Trace', 'key' => 'mail-trace'];
    }
    $settingsActiveKeys = array_column($settingsItems, 'key');
    $items = [
        ['href' => '/companies.php', 'label' => 'Companies', 'key' => 'companies'],
        ['href' => '/departments.php', 'label' => 'Departments', 'key' => 'departments'],
        ['href' => '/users.php', 'label' => 'Users', 'key' => 'users'],
        ['href' => '/roles.php', 'label' => 'Roles', 'key' => 'roles'],
    ];
    ?>
    <aside class="dashboard-sidebar" id="portal-sidebar" aria-label="Portal navigation">
      <div class="sidebar-brand"><a href="/dashboard.php#overview" aria-label="Oligarchy Services dashboard">OLIGARCHY</a><button class="sidebar-collapse" type="button" data-sidebar-collapse aria-label="Collapse sidebar" aria-expanded="true">‹</button></div>
      <nav class="sidebar-nav">
        <a class="<?= $active === 'overview' ? 'is-active' : '' ?>" href="/dashboard.php#overview" data-section-link="overview" <?= $active === 'overview' ? 'aria-current="page"' : '' ?>><span class="nav-icon" aria-hidden="true">O</span><span class="nav-label">Overview</span></a>
        <?php if ($isAdmin): ?>
        <div class="sidebar-group <?= in_array($active, ['companies', 'departments', 'users', 'roles'], true) ? 'is-open is-active' : '' ?>" data-valley-group>
          <button class="sidebar-group-toggle" type="button" data-valley-toggle aria-expanded="<?= in_array($active, ['companies', 'departments', 'users', 'roles'], true) ? 'true' : 'false' ?>"><span class="nav-icon" aria-hidden="true">V</span><span class="nav-label">Valley</span><span class="sidebar-group-caret" aria-hidden="true">&gt;</span></button>
          <div class="sidebar-subnav" data-valley-subnav>
            <?php foreach ($items as $item): ?>
              <a class="<?= $active === $item['key'] ? 'is-active' : '' ?>" href="<?= e($item['href']) ?>" <?= $active === $item['key'] ? 'aria-current="page"' : '' ?>><span class="nav-icon" aria-hidden="true"><?= e(substr($item['label'], 0, 1)) ?></span><span class="nav-label"><?= e($item['label']) ?></span></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($isAdmin || $canManageContent): ?>
        <div class="sidebar-group <?= in_array($active, ['agents', 'pages', 'blogs', 'navigation'], true) ? 'is-open is-active' : '' ?>" data-playground-group>
          <button class="sidebar-group-toggle" type="button" data-playground-toggle aria-expanded="<?= in_array($active, ['agents', 'pages', 'blogs', 'navigation'], true) ? 'true' : 'false' ?>"><span class="nav-icon" aria-hidden="true">P</span><span class="nav-label">Playground</span><span class="sidebar-group-caret" aria-hidden="true">&gt;</span></button>
          <div class="sidebar-subnav" data-playground-subnav>
            <?php if ($isAdmin): ?>
            <a class="<?= $active === 'agents' ? 'is-active' : '' ?>" href="/agents.php" <?= $active === 'agents' ? 'aria-current="page"' : '' ?>><span class="nav-icon" aria-hidden="true">A</span><span class="nav-label">Agents</span></a>
            <?php endif; ?>
            <?php if ($canManageContent): ?>
            <a class="<?= $active === 'pages' ? 'is-active' : '' ?>" href="/dashboard.php#pages" data-section-link="pages" <?= $active === 'pages' ? 'aria-current="page"' : '' ?>><span class="nav-icon" aria-hidden="true">P</span><span class="nav-label">Pages</span></a>
            <a class="<?= $active === 'blogs' ? 'is-active' : '' ?>" href="/admin-blogs.php" <?= $active === 'blogs' ? 'aria-current="page"' : '' ?>><span class="nav-icon" aria-hidden="true">B</span><span class="nav-label">Blogs</span></a>
            <a class="<?= $active === 'navigation' ? 'is-active' : '' ?>" href="/dashboard.php#navigation" data-section-link="navigation" <?= $active === 'navigation' ? 'aria-current="page"' : '' ?>><span class="nav-icon" aria-hidden="true">N</span><span class="nav-label">Navigation</span></a>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($settingsItems): ?>
        <div class="sidebar-group <?= in_array($active, $settingsActiveKeys, true) ? 'is-open is-active' : '' ?>" data-settings-group>
          <button class="sidebar-group-toggle" type="button" data-settings-toggle aria-expanded="<?= in_array($active, $settingsActiveKeys, true) ? 'true' : 'false' ?>"><span class="nav-icon" aria-hidden="true">S</span><span class="nav-label">Settings</span><span class="sidebar-group-caret" aria-hidden="true">&gt;</span></button>
          <div class="sidebar-subnav" data-settings-subnav>
            <?php foreach ($settingsItems as $item): ?>
              <a class="<?= $active === $item['key'] ? 'is-active' : '' ?>" href="<?= e($item['href']) ?>" data-section-link="<?= e($item['key']) ?>" <?= $active === $item['key'] ? 'aria-current="page"' : '' ?>><span class="nav-icon" aria-hidden="true"><?= e(substr($item['label'], 0, 1)) ?></span><span class="nav-label"><?= e($item['label']) ?></span></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </nav>
      <div class="sidebar-footer"><span class="sidebar-status">Role</span><strong><?= e($roleLabel) ?></strong></div>
    </aside>
    <?php
}

function access_entity_config(string $entity): array
{
    $configs = [
        'roles' => [
            'table' => 'roles',
            'path' => '/roles.php',
            'scope' => 'roles',
            'active' => 'roles',
            'title' => 'Roles',
            'single' => 'role',
            'heading' => 'Roles',
            'description' => 'Manage access roles and the modules each role can see.',
            'name_label' => 'Role name',
            'notes_label' => 'Description',
            'target_type' => 'role',
        ],
        'companies' => [
            'table' => 'companies',
            'path' => '/companies.php',
            'scope' => 'companies',
            'active' => 'companies',
            'title' => 'Companies',
            'single' => 'company',
            'heading' => 'Companies',
            'description' => 'Manage company records and company-level module visibility.',
            'name_label' => 'Company name',
            'notes_label' => 'Notes',
            'target_type' => 'company',
        ],
        'departments' => [
            'table' => 'departments',
            'path' => '/departments.php',
            'scope' => 'departments',
            'active' => 'departments',
            'title' => 'Departments',
            'single' => 'department',
            'heading' => 'Departments',
            'description' => 'Manage departments, company association, and department-level module visibility.',
            'name_label' => 'Department name',
            'notes_label' => 'Notes',
            'target_type' => 'department',
            'has_company' => true,
        ],
    ];

    if (!isset($configs[$entity])) {
        throw new InvalidArgumentException('Unknown access-management page.');
    }

    return $configs[$entity];
}

function access_entity_references(PDO $pdo, string $entity, int $id, string $name): int
{
    if ($entity === 'roles') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
        $stmt->execute([$name]);
        return (int) $stmt->fetchColumn();
    }
    if ($entity === 'companies') {
        $stmt = $pdo->prepare('SELECT (SELECT COUNT(*) FROM users WHERE company_id = ?) + (SELECT COUNT(*) FROM departments WHERE company_id = ?)');
        $stmt->execute([$id, $id]);
        return (int) $stmt->fetchColumn();
    }
    if ($entity === 'departments') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE department_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    return 0;
}

function access_render_entity_page(string $entity): void
{
    require_once __DIR__ . '/bootstrap.php';
    require_once __DIR__ . '/csrf.php';
    require_once __DIR__ . '/auth.php';

    $config = access_entity_config($entity);
    $user = access_admin_user();
    $pdo = db();
    access_management_ensure_schema($pdo);

    $displayName = trim((string) ($user['full_name'] ?: $user['email']));
    $initials = strtoupper(substr($displayName, 0, 1));
    $roleLabel = ucfirst(strtolower((string) ($user['role'] ?? 'admin')));
    $scope = $config['scope'];
    $notice = $_SESSION[$scope . '_notice'] ?? null;
    $error = $_SESSION[$scope . '_error'] ?? null;
    unset($_SESSION[$scope . '_notice'], $_SESSION[$scope . '_error']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            access_flash($scope, 'error', 'Your session expired. Please refresh and try again.');
            access_redirect($config['path']);
        }

        try {
            $action = access_post_string('action');
            $id = access_post_int('id');
            if ($action === 'save') {
                $name = access_post_string('name');
                $notes = access_post_string('notes');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $modules = access_encode_modules(access_selected_modules());
                $companyId = !empty($config['has_company']) ? access_post_int('company_id') : null;
                $companyId = $companyId && $companyId > 0 ? $companyId : null;

                if ($name === '') {
                    throw new RuntimeException($config['name_label'] . ' is required.');
                }

                if (!empty($config['has_company']) && $companyId !== null) {
                    $company = $pdo->prepare('SELECT id FROM companies WHERE id = ? LIMIT 1');
                    $company->execute([$companyId]);
                    if (!$company->fetch()) {
                        throw new RuntimeException('Choose a valid company.');
                    }
                }

                if (!empty($config['has_company'])) {
                    if ($companyId === null) {
                        $dupe = $pdo->prepare('SELECT id FROM departments WHERE name = ? AND company_id IS NULL AND id <> ? LIMIT 1');
                        $dupe->execute([$name, $id]);
                    } else {
                        $dupe = $pdo->prepare('SELECT id FROM departments WHERE name = ? AND company_id = ? AND id <> ? LIMIT 1');
                        $dupe->execute([$name, $companyId, $id]);
                    }
                } else {
                    $dupe = $pdo->prepare('SELECT id FROM ' . access_sql_name($config['table']) . ' WHERE name = ? AND id <> ? LIMIT 1');
                    $dupe->execute([$name, $id]);
                }
                if ($dupe->fetch()) {
                    throw new RuntimeException('That ' . $config['single'] . ' name is already in use.');
                }

                if ($id > 0) {
                    if (!empty($config['has_company'])) {
                        $stmt = $pdo->prepare('UPDATE departments SET company_id = ?, name = ?, notes = ?, is_active = ?, module_permissions = ?, updated_at = NOW() WHERE id = ?');
                        $stmt->execute([$companyId, $name, $notes, $isActive, $modules, $id]);
                    } else {
                        $notesColumn = $config['table'] === 'roles' ? 'description' : 'notes';
                        $stmt = $pdo->prepare('UPDATE ' . access_sql_name($config['table']) . ' SET name = ?, ' . access_sql_name($notesColumn) . ' = ?, is_active = ?, module_permissions = ?, updated_at = NOW() WHERE id = ?');
                        $stmt->execute([$name, $notes, $isActive, $modules, $id]);
                    }
                    access_log_activity($pdo, (int) $user['id'], $config['single'] . ' updated', $config['target_type'], $id, $name);
                    access_flash($scope, 'notice', ucfirst($config['single']) . ' updated.');
                } else {
                    if (!empty($config['has_company'])) {
                        $stmt = $pdo->prepare('INSERT INTO departments (company_id, name, notes, is_active, module_permissions) VALUES (?, ?, ?, ?, ?)');
                        $stmt->execute([$companyId, $name, $notes, $isActive, $modules]);
                    } else {
                        $notesColumn = $config['table'] === 'roles' ? 'description' : 'notes';
                        $stmt = $pdo->prepare('INSERT INTO ' . access_sql_name($config['table']) . ' (name, ' . access_sql_name($notesColumn) . ', is_active, module_permissions) VALUES (?, ?, ?, ?)');
                        $stmt->execute([$name, $notes, $isActive, $modules]);
                    }
                    $newId = (int) $pdo->lastInsertId();
                    access_log_activity($pdo, (int) $user['id'], $config['single'] . ' created', $config['target_type'], $newId, $name);
                    access_flash($scope, 'notice', ucfirst($config['single']) . ' created.');
                }
                access_redirect($config['path']);
            }

            if ($action === 'deactivate' || $action === 'delete') {
                $lookup = $pdo->prepare('SELECT id, name FROM ' . access_sql_name($config['table']) . ' WHERE id = ? LIMIT 1');
                $lookup->execute([$id]);
                $row = $lookup->fetch();
                if (!$row) {
                    throw new RuntimeException('Choose a valid ' . $config['single'] . '.');
                }
                $references = access_entity_references($pdo, $entity, $id, (string) $row['name']);
                if ($action === 'delete' && $references === 0) {
                    $pdo->prepare('DELETE FROM ' . access_sql_name($config['table']) . ' WHERE id = ?')->execute([$id]);
                    access_log_activity($pdo, (int) $user['id'], $config['single'] . ' deleted', $config['target_type'], $id, (string) $row['name']);
                    access_flash($scope, 'notice', ucfirst($config['single']) . ' deleted.');
                } else {
                    $pdo->prepare('UPDATE ' . access_sql_name($config['table']) . ' SET is_active = 0, updated_at = NOW() WHERE id = ?')->execute([$id]);
                    access_log_activity($pdo, (int) $user['id'], $config['single'] . ' deactivated', $config['target_type'], $id, (string) $row['name']);
                    access_flash($scope, 'notice', ucfirst($config['single']) . ' deactivated.');
                }
                access_redirect($config['path']);
            }
        } catch (Throwable $postError) {
            access_flash($scope, 'error', $postError->getMessage());
            access_redirect($config['path']);
        }
    }

    $search = trim((string) ($_GET['search'] ?? ''));
    $statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
    if (!in_array($statusFilter, ['', 'active', 'inactive'], true)) {
        $statusFilter = '';
    }

    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = 'a.name LIKE ?';
        $params[] = '%' . $search . '%';
    }
    if ($statusFilter !== '') {
        $where[] = 'a.is_active = ?';
        $params[] = $statusFilter === 'active' ? 1 : 0;
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $selectNotes = $config['table'] === 'roles' ? 'a.description AS notes' : 'a.notes';
    $sql = 'SELECT a.*, ' . $selectNotes;
    if (!empty($config['has_company'])) {
        $sql .= ', c.name AS company_name';
    }
    $sql .= ' FROM ' . access_sql_name($config['table']) . ' a';
    if (!empty($config['has_company'])) {
        $sql .= ' LEFT JOIN companies c ON c.id = a.company_id';
    }
    $sql .= $whereSql . ' ORDER BY a.created_at DESC, a.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $counts = [
        'total' => (int) $pdo->query('SELECT COUNT(*) FROM ' . access_sql_name($config['table']))->fetchColumn(),
        'active' => (int) $pdo->query('SELECT COUNT(*) FROM ' . access_sql_name($config['table']) . ' WHERE is_active = 1')->fetchColumn(),
        'inactive' => (int) $pdo->query('SELECT COUNT(*) FROM ' . access_sql_name($config['table']) . ' WHERE is_active = 0')->fetchColumn(),
    ];
    $companies = !empty($config['has_company']) ? access_fetch_options($pdo, 'companies') : [];
    $csrf = csrf_token();
    $modules = access_modules();
    ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= e($config['title']) ?> | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-access-management">
    <style>
      .access-toolbar { align-items: center; }
      .icon-action { gap: 9px; }
      .icon-action .plus-mark { display: inline-grid; width: 22px; height: 22px; place-items: center; border-radius: 999px; background: rgba(255,255,255,0.16); font-size: 1.15rem; line-height: 1; }
      .module-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
      .module-chip { display: flex !important; grid-template-columns: auto 1fr; align-items: center; gap: 9px !important; border: 1px solid rgba(90,93,99,0.42); border-radius: 8px; padding: 9px 10px; background: rgba(255,255,255,0.03); font-size: 0.82rem !important; }
      .module-chip input { width: auto; }
      .access-modal[hidden] { display: none; }
      .access-modal { position: fixed; inset: 0; z-index: 80; display: grid; place-items: center; padding: 18px; }
      .access-modal-backdrop { position: absolute; inset: 0; border: 0; background: rgba(0,0,0,0.72); cursor: pointer; }
      .access-modal-dialog { position: relative; width: min(680px, 100%); max-height: min(780px, calc(100dvh - 36px)); overflow: auto; border: 1px solid rgba(90,93,99,0.48); border-radius: 8px; background: linear-gradient(135deg, rgba(32,32,36,0.98), rgba(13,13,15,0.98)); box-shadow: 0 28px 80px rgba(0,0,0,0.46); }
      .access-modal-header { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 18px 18px 0; }
      .access-modal-header h3 { margin: 0; }
      .access-modal-close { display: inline-grid; width: 38px; height: 38px; place-items: center; border: 1px solid rgba(90,93,99,0.5); border-radius: 8px; background: #17171a; color: #fff; cursor: pointer; font-size: 1.35rem; line-height: 1; }
      .access-modal .admin-panel { border: 0; border-radius: 0; background: transparent; box-shadow: none; }
      body.access-modal-open { overflow: hidden; }
      @media (max-width: 680px) { .access-toolbar { align-items: stretch; } .access-toolbar .primary-action { width: 100%; } .module-grid { grid-template-columns: 1fr; } .access-modal { align-items: end; padding: 12px; } .access-modal-dialog { width: 100%; max-height: calc(100dvh - 24px); } }
    </style>
    <script defer src="/assets/dashboard.js?v=20260621-settings-modules"></script>
  </head>
  <body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <?php access_sidebar($config['active'], $roleLabel); ?>
      <div class="sidebar-backdrop" data-sidebar-backdrop></div>
      <div class="dashboard-main">
        <header class="dashboard-topbar"><div class="topbar-left"><button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button><div><p class="eyebrow">Access control</p><h1 data-section-title><?= e($config['heading']) ?></h1></div></div><div class="topbar-actions"><span class="role-pill"><?= e($roleLabel) ?></span><div class="user-chip" title="<?= e($user['email']) ?>"><span><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($user['email']) ?></small></div></div><a class="logout-link" href="/logout.php">Log out</a></div></header>
        <main class="dashboard-content">
          <?php if ($notice): ?><div class="dashboard-alert is-success" role="status"><?= e((string) $notice) ?></div><?php endif; ?>
          <?php if ($error): ?><div class="dashboard-alert is-error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>
          <section class="dashboard-section is-active" data-dashboard-section data-section-label="<?= e($config['heading']) ?>">
            <div class="section-heading-row access-toolbar"><div><p class="eyebrow">Access management</p><h2><?= e($config['heading']) ?></h2><p class="empty-state"><?= e($config['description']) ?></p></div><button class="primary-action icon-action" type="button" data-add-access aria-haspopup="dialog" aria-controls="access-modal"><span class="plus-mark" aria-hidden="true">+</span><span>Add <?= e($config['single']) ?></span></button></div>
            <section class="section-summary-grid three-up" aria-label="<?= e($config['heading']) ?> summary"><article><span>Total</span><strong><?= e((string) $counts['total']) ?></strong></article><article><span>Active</span><strong><?= e((string) $counts['active']) ?></strong></article><article><span>Inactive</span><strong><?= e((string) $counts['inactive']) ?></strong></article></section>
            <form class="admin-panel filter-panel" method="get" action="<?= e($config['path']) ?>"><label>Search<input name="search" type="search" value="<?= e($search) ?>" placeholder="<?= e($config['name_label']) ?>"></label><label>Status<select name="status"><option value="">All statuses</option><option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></label><div class="filter-actions"><button class="button primary" type="submit">Apply filters</button><a class="secondary-action" href="<?= e($config['path']) ?>">Clear</a></div></form>
            <div class="admin-panel table-panel">
              <div class="table-heading"><h3><?= e($config['heading']) ?></h3><span><?= e((string) count($rows)) ?> result<?= count($rows) === 1 ? '' : 's' ?></span></div>
              <?php if (!$rows): ?><p class="empty-state">No <?= e($config['heading']) ?> match the current filters.</p><?php else: ?>
                <div class="table-scroll"><table class="data-table"><thead><tr><th>Name</th><?php if (!empty($config['has_company'])): ?><th>Company</th><?php endif; ?><th>Status</th><th>Modules</th><th>Updated</th><th></th></tr></thead><tbody>
                  <?php foreach ($rows as $row): $rowModules = access_decode_modules($row['module_permissions'] ?? null); ?>
                    <tr>
                      <td><strong><?= e($row['name']) ?></strong><small><?= e((string) ($row['notes'] ?? '')) ?></small></td>
                      <?php if (!empty($config['has_company'])): ?><td><?= e((string) ($row['company_name'] ?? 'Unassigned')) ?></td><?php endif; ?>
                      <td><span class="status-badge <?= (int) $row['is_active'] === 1 ? 'is-active' : 'is-muted' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                      <td><?php if (!$rowModules): ?><span class="status-badge is-muted">None selected</span><?php else: ?><small><?= e(access_module_labels($rowModules)) ?></small><?php endif; ?></td>
                      <td class="nowrap"><?= e((string) $row['updated_at']) ?></td>
                      <td class="row-actions"><button class="table-action" type="button" data-edit-access data-id="<?= e((string) $row['id']) ?>" data-name="<?= e($row['name']) ?>" data-notes="<?= e((string) ($row['notes'] ?? '')) ?>" data-active="<?= e((string) $row['is_active']) ?>" data-company="<?= e((string) ($row['company_id'] ?? '')) ?>" data-modules="<?= e(implode(',', $rowModules)) ?>">Edit</button><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= e((string) $row['id']) ?>"><button class="table-action" type="submit">Deactivate</button></form><form method="post" data-confirm="Delete this <?= e($config['single']) ?> if it is not referenced? Referenced records will be deactivated instead."><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string) $row['id']) ?>"><button class="table-action danger" type="submit">Delete</button></form></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody></table></div>
              <?php endif; ?>
            </div>
            <div class="access-modal" id="access-modal" data-access-modal role="dialog" aria-modal="true" aria-labelledby="access-modal-title" hidden>
              <button class="access-modal-backdrop" type="button" data-access-modal-close aria-label="Close form"></button>
              <div class="access-modal-dialog">
                <div class="access-modal-header"><h3 id="access-modal-title" data-access-form-title>Create <?= e($config['single']) ?></h3><button class="access-modal-close" type="button" data-access-modal-close aria-label="Close form">×</button></div>
                <form class="admin-panel" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" data-access-id value="0">
                  <label><?= e($config['name_label']) ?><input name="name" data-access-name required></label>
                  <?php if (!empty($config['has_company'])): ?><label>Company<select name="company_id" data-access-company><option value="">Unassigned</option><?php foreach ($companies as $company): ?><option value="<?= e((string) $company['id']) ?>"><?= e($company['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
                  <label><?= e($config['notes_label']) ?><textarea name="notes" data-access-notes rows="3"></textarea></label>
                  <label class="check-row"><input name="is_active" type="checkbox" value="1" data-access-active checked><span>Active</span></label>
                  <fieldset><legend>Visible modules</legend><div class="module-grid"><?php foreach ($modules as $key => $label): ?><label class="module-chip"><input type="checkbox" name="modules[]" value="<?= e($key) ?>" data-access-module="<?= e($key) ?>"><span><?= e($label) ?></span></label><?php endforeach; ?></div></fieldset>
                  <div class="form-actions"><button class="button primary" type="submit">Save <?= e($config['single']) ?></button><button class="button secondary" type="button" data-reset-access-form>Cancel</button></div>
                </form>
              </div>
            </div>
          </section>
        </main>
      </div>
    </div>
  </body>
</html>
    <?php
}
