<?php
declare(strict_types=1);

function installer_post_value(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function installer_error_message(Throwable $exception, string $dbHost): string
{
    $message = $exception->getMessage();

    if (strpos($message, 'SQLSTATE[HY000] [1045]') !== false) {
        $hint = 'Access denied. Check the database username and password.';
        if ($dbHost !== 'localhost') {
            $hint .= ' On Hostinger shared hosting, the database host is usually localhost, not your website domain.';
        }
        return $hint;
    }

    if (strpos($message, 'SQLSTATE[HY000] [2002]') !== false) {
        return 'Could not reach the database host. On Hostinger shared hosting, try localhost unless hPanel shows a different MySQL host.';
    }

    if (strpos($message, 'Unknown database') !== false) {
        return 'Database not found. Check the full database name from Hostinger hPanel, including the u-number prefix.';
    }

    return 'Database setup failed. Check the database details and try again.';
}

function installer_pdo_from_credentials(string $dbHost, string $dbName, string $dbUser, string $dbPassword): PDO
{
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);
    return new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function installer_config_contents(string $dbHost, string $dbName, string $dbUser, string $dbPassword): string
{
    return "<?php\nreturn " . var_export([
        'host' => $dbHost,
        'database' => $dbName,
        'username' => $dbUser,
        'password' => $dbPassword,
    ], true) . ";\n";
}

function installer_backup_config_paths(string $configPath): array
{
    $paths = [];

    foreach ([5, 3] as $levels) {
        $directory = dirname($configPath, $levels);
        if ($directory !== '.' && $directory !== DIRECTORY_SEPARATOR) {
            $paths[] = $directory . '/oligarchy-config.php';
        }
    }

    return array_values(array_unique($paths));
}

function installer_backup_config_path(string $configPath): string
{
    return installer_backup_config_paths($configPath)[0] ?? dirname($configPath, 3) . '/oligarchy-config.php';
}

function installer_config_file_is_valid(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }

    try {
        $loaded = require $path;
    } catch (Throwable $error) {
        error_log('Database config could not be loaded at ' . $path . ': ' . $error->getMessage());
        return false;
    }

    if (!is_array($loaded)) {
        error_log('Database config is not a PHP config array at ' . $path);
        return false;
    }

    foreach (['host', 'database', 'username'] as $requiredKey) {
        if (trim((string) ($loaded[$requiredKey] ?? '')) === '') {
            error_log('Database config is missing required key at ' . $path . ': ' . $requiredKey);
            return false;
        }
    }

    return true;
}

function installer_existing_config_path(string $configPath): ?string
{
    $paths = array_merge([$configPath], installer_backup_config_paths($configPath));

    foreach ($paths as $path) {
        if (installer_config_file_is_valid($path)) {
            return $path;
        }
    }

    return null;
}

function installer_has_persistent_config(string $configPath): bool
{
    foreach (installer_backup_config_paths($configPath) as $path) {
        if (installer_config_file_is_valid($path)) {
            return true;
        }
    }

    return false;
}

function installer_write_config(string $configPath, string $dbHost, string $dbName, string $dbUser, string $dbPassword): array
{
    $config = installer_config_contents($dbHost, $dbName, $dbUser, $dbPassword);

    if (file_put_contents($configPath, $config, LOCK_EX) === false) {
        throw new RuntimeException('Could not write includes/config.php. Check file permissions.');
    }

    @chmod($configPath, 0600);

    $result = ['written' => [], 'failed' => []];
    foreach (installer_backup_config_paths($configPath) as $backupPath) {
        if (@file_put_contents($backupPath, $config, LOCK_EX) === false) {
            error_log('Could not write persistent database config at ' . $backupPath);
            $result['failed'][] = $backupPath;
            continue;
        }

        @chmod($backupPath, 0600);
        $result['written'][] = $backupPath;
    }

    if (!$result['written']) {
        error_log('No persistent database config backup could be written. Future full-file deploys may require repair.php.');
    }

    return $result;
}

function installer_config_write_warnings(array $writeResult): array
{
    if (empty($writeResult['written'])) {
        return ['The portal connected to the database, but Hostinger did not allow a persistent config backup to be saved. If a full file sync removes includes/config.php again, open /repair.php once to restore it.'];
    }

    if (!empty($writeResult['failed'])) {
        return ['The portal saved a persistent config backup, but one backup location was not writable. Login should survive normal deploys, but check the Hostinger PHP error log if repair appears again.'];
    }

    return [];
}

function installer_restore_config_from_backup(string $configPath): bool
{
    if (installer_config_file_is_valid($configPath)) {
        return true;
    }

    foreach (installer_backup_config_paths($configPath) as $backupPath) {
        if (!installer_config_file_is_valid($backupPath)) {
            continue;
        }

        $config = file_get_contents($backupPath);
        if ($config === false) {
            error_log('Could not read persistent database config at ' . $backupPath);
            return true;
        }

        if (file_put_contents($configPath, $config, LOCK_EX) === false) {
            error_log('Could not restore includes/config.php from persistent database config at ' . $backupPath);
            return true;
        }

        @chmod($configPath, 0600);
        return true;
    }

    return false;
}

function installer_sql_name(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }

    return '`' . $name . '`';
}

function installer_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function installer_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (installer_column_exists($pdo, $table, $column)) {
        return;
    }

    $pdo->exec('ALTER TABLE ' . installer_sql_name($table) . ' ADD COLUMN ' . installer_sql_name($column) . ' ' . $definition);
}

function installer_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function installer_add_index_if_missing(PDO $pdo, string $table, string $index, string $columns): void
{
    if (installer_index_exists($pdo, $table, $index)) {
        return;
    }

    $pdo->exec('ALTER TABLE ' . installer_sql_name($table) . ' ADD INDEX ' . installer_sql_name($index) . ' (' . $columns . ')');
}

function installer_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function installer_upgrade_existing_schema(PDO $pdo): void
{
    if (installer_table_exists($pdo, 'users')) {
        installer_add_column_if_missing($pdo, 'users', 'email', "VARCHAR(190) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'users', 'password_hash', "VARCHAR(255) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'users', 'full_name', "VARCHAR(190) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'users', 'role', "VARCHAR(50) NOT NULL DEFAULT 'client'");
        installer_add_column_if_missing($pdo, 'users', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
        installer_add_column_if_missing($pdo, 'users', 'last_login_at', 'DATETIME NULL');
        installer_add_column_if_missing($pdo, 'users', 'email_confirmed_at', 'DATETIME NULL');
        installer_add_column_if_missing($pdo, 'users', 'email_confirmation_token_hash', 'VARCHAR(255) NULL');
        installer_add_column_if_missing($pdo, 'users', 'email_confirmation_expires_at', 'DATETIME NULL');
        installer_add_column_if_missing($pdo, 'users', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        installer_add_column_if_missing($pdo, 'users', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        installer_add_index_if_missing($pdo, 'users', 'idx_users_email_confirmation_token', '`email_confirmation_token_hash`');
        $pdo->exec('UPDATE users SET email_confirmed_at = COALESCE(email_confirmed_at, created_at, NOW()) WHERE email_confirmed_at IS NULL AND email_confirmation_token_hash IS NULL AND created_at < (NOW() - INTERVAL 1 MINUTE)');
    }

    if (installer_table_exists($pdo, 'login_attempts')) {
        installer_add_column_if_missing($pdo, 'login_attempts', 'email', "VARCHAR(190) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'login_attempts', 'ip_address', "VARCHAR(45) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'login_attempts', 'success', 'TINYINT(1) NOT NULL DEFAULT 0');
        installer_add_column_if_missing($pdo, 'login_attempts', 'attempted_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        installer_add_index_if_missing($pdo, 'login_attempts', 'idx_login_attempts_email_time', '`email`, `attempted_at`');
        installer_add_index_if_missing($pdo, 'login_attempts', 'idx_login_attempts_ip_time', '`ip_address`, `attempted_at`');
    }

    if (installer_table_exists($pdo, 'password_resets')) {
        installer_add_column_if_missing($pdo, 'password_resets', 'user_id', 'INT UNSIGNED NULL');
        installer_add_column_if_missing($pdo, 'password_resets', 'token_hash', "VARCHAR(255) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'password_resets', 'expires_at', 'DATETIME NULL');
        installer_add_column_if_missing($pdo, 'password_resets', 'used_at', 'DATETIME NULL');
        installer_add_column_if_missing($pdo, 'password_resets', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        installer_add_index_if_missing($pdo, 'password_resets', 'idx_password_resets_user', '`user_id`');
    }

    if (installer_table_exists($pdo, 'pages')) {
        installer_add_column_if_missing($pdo, 'pages', 'title', "VARCHAR(190) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'pages', 'slug', "VARCHAR(190) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'pages', 'body', 'MEDIUMTEXT NULL');
        installer_add_column_if_missing($pdo, 'pages', 'meta_description', "VARCHAR(255) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'pages', 'status', "ENUM('draft','published') NOT NULL DEFAULT 'draft'");
        installer_add_column_if_missing($pdo, 'pages', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        installer_add_column_if_missing($pdo, 'pages', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        installer_add_index_if_missing($pdo, 'pages', 'idx_pages_status', '`status`');
        installer_add_index_if_missing($pdo, 'pages', 'idx_pages_slug_status', '`slug`, `status`');
    }

    if (installer_table_exists($pdo, 'navigation_items')) {
        installer_add_column_if_missing($pdo, 'navigation_items', 'label', "VARCHAR(120) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'navigation_items', 'url', "VARCHAR(255) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'navigation_items', 'sort_order', 'INT NOT NULL DEFAULT 10');
        installer_add_column_if_missing($pdo, 'navigation_items', 'is_visible', 'TINYINT(1) NOT NULL DEFAULT 1');
        installer_add_column_if_missing($pdo, 'navigation_items', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        installer_add_column_if_missing($pdo, 'navigation_items', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        installer_add_index_if_missing($pdo, 'navigation_items', 'idx_navigation_visible_sort', '`is_visible`, `sort_order`');
    }

    if (installer_table_exists($pdo, 'settings')) {
        installer_add_column_if_missing($pdo, 'settings', 'setting_key', "VARCHAR(120) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'settings', 'setting_value', 'TEXT NULL');
        installer_add_column_if_missing($pdo, 'settings', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        installer_add_column_if_missing($pdo, 'settings', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }

    if (installer_table_exists($pdo, 'activity_log')) {
        installer_add_column_if_missing($pdo, 'activity_log', 'user_id', 'INT UNSIGNED NULL');
        installer_add_column_if_missing($pdo, 'activity_log', 'action', "VARCHAR(120) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'activity_log', 'target_type', "VARCHAR(80) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'activity_log', 'target_id', 'INT UNSIGNED NULL');
        installer_add_column_if_missing($pdo, 'activity_log', 'details', 'TEXT NULL');
        installer_add_column_if_missing($pdo, 'activity_log', 'ip_address', "VARCHAR(45) NOT NULL DEFAULT ''");
        installer_add_column_if_missing($pdo, 'activity_log', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        installer_add_index_if_missing($pdo, 'activity_log', 'idx_activity_created', '`created_at`');
        installer_add_index_if_missing($pdo, 'activity_log', 'idx_activity_user', '`user_id`');
    }
}

function create_or_update_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(190) NOT NULL DEFAULT '',
        role VARCHAR(50) NOT NULL DEFAULT 'client',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login_at DATETIME NULL,
        email_confirmed_at DATETIME NULL,
        email_confirmation_token_hash VARCHAR(255) NULL,
        email_confirmation_expires_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_users_email_confirmation_token (email_confirmation_token_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL,
        ip_address VARCHAR(45) NOT NULL DEFAULT '',
        success TINYINT(1) NOT NULL DEFAULT 0,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_attempts_email_time (email, attempted_at),
        INDEX idx_login_attempts_ip_time (ip_address, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        token_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_password_resets_user (user_id),
        CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(190) NOT NULL,
        slug VARCHAR(190) NOT NULL UNIQUE,
        meta_description VARCHAR(255) NOT NULL DEFAULT '',
        body MEDIUMTEXT NOT NULL,
        status ENUM('draft','published') NOT NULL DEFAULT 'draft',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_pages_status (status),
        INDEX idx_pages_slug_status (slug, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS navigation_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(120) NOT NULL,
        url VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 10,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_navigation_visible_sort (is_visible, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(120) NOT NULL UNIQUE,
        setting_value TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        action VARCHAR(120) NOT NULL,
        target_type VARCHAR(80) NOT NULL DEFAULT '',
        target_id INT UNSIGNED NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_created (created_at),
        INDEX idx_activity_user (user_id),
        CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    installer_upgrade_existing_schema($pdo);

    $settings = $pdo->prepare('INSERT INTO settings (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`');
    foreach (['site_name' => 'Oligarchy Services', 'contact_email' => '', 'analytics_enabled' => '0', 'analytics_provider' => 'plausible', 'analytics_domain' => ''] as $key => $value) {
        $settings->execute([$key, $value]);
    }

    $navCount = (int) $pdo->query('SELECT COUNT(*) FROM navigation_items')->fetchColumn();
    if ($navCount === 0) {
        $nav = $pdo->prepare('INSERT INTO navigation_items (label, url, sort_order, is_visible) VALUES (?, ?, ?, 1)');
        foreach ([['Home', '/', 10], ['Services', '/#services', 20], ['Contact', '/contact.html', 30]] as $item) {
            $nav->execute($item);
        }
    }
}

function installer_upsert_admin(PDO $pdo, string $adminEmail, string $adminPassword, string $adminName): void
{
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, role, is_active, email_confirmed_at) VALUES (?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), role = VALUES(role), is_active = 1, email_confirmed_at = COALESCE(email_confirmed_at, NOW()), updated_at = NOW()');
    $stmt->execute([$adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), $adminName, 'admin']);
}

function installer_admin_count(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
}
