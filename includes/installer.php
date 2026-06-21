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

function installer_write_config(string $configPath, string $dbHost, string $dbName, string $dbUser, string $dbPassword): void
{
    $config = "<?php\nreturn " . var_export([
        'host' => $dbHost,
        'database' => $dbName,
        'username' => $dbUser,
        'password' => $dbPassword,
    ], true) . ";\n";

    if (file_put_contents($configPath, $config, LOCK_EX) === false) {
        throw new RuntimeException('Could not write includes/config.php. Check file permissions.');
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
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), role = VALUES(role), is_active = 1, updated_at = NOW()');
    $stmt->execute([$adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), $adminName, 'admin']);
}

function installer_admin_count(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
}
