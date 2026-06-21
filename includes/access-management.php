<?php
declare(strict_types=1);

function access_modules(): array
{
    return [
        'overview' => 'Dashboard / Overview',
        'requests' => 'Requests',
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
    $seed->execute(['editor', 'Content manager access.', access_encode_modules(['overview', 'requests', 'pages', 'blogs', 'navigation', 'system_settings', 'activity', 'system_health', 'mail_trace'])]);
    $seed->execute(['support', 'Support and activity review access.', access_encode_modules(['overview', 'requests', 'users', 'activity', 'system_health', 'mail_trace'])]);
    $seed->execute(['client', 'Client dashboard access.', access_encode_modules(['overview', 'requests'])]);
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
        <a class="<?= $active === 'requests' ? 'is-active' : '' ?>" href="/requests.php" <?= $active === 'requests' ? 'aria-current="page"' : '' ?>><span class="nav-icon" aria-hidden="true">R</span><span class="nav-label">Requests</span></a>
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