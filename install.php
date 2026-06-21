<?php
declare(strict_types=1);

$lockPath = __DIR__ . '/includes/installed.lock';
$configPath = __DIR__ . '/includes/config.php';
$hasLock = is_file($lockPath);
$hasConfig = is_file($configPath);
$isRepairMode = $hasLock && !$hasConfig;
$errors = [];
$success = false;

if ($hasLock && $hasConfig) {
    http_response_code(403);
    echo 'Installer is locked. Delete includes/installed.lock only if you intentionally need to run installer updates.';
    exit;
}

function post_value(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function install_error_message(Throwable $exception, string $dbHost): string
{
    $message = $exception->getMessage();
    if (strpos($message, 'SQLSTATE[HY000] [1045]') !== false) {
        $hint = 'Access denied. Check the database username and password.';
        if ($dbHost !== 'localhost') $hint .= ' On Hostinger shared hosting, the database host is usually localhost, not your website domain.';
        return $hint;
    }
    if (strpos($message, 'SQLSTATE[HY000] [2002]') !== false) return 'Could not reach the database host. On Hostinger shared hosting, try localhost unless hPanel shows a different MySQL host.';
    if (strpos($message, 'Unknown database') !== false) return 'Database not found. Check the full database name from Hostinger hPanel, including the u-number prefix.';
    return 'Install failed. Check the database details and try again.';
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
    foreach (['site_name' => 'Oligarchy Services', 'contact_email' => '', 'analytics_enabled' => '0', 'analytics_provider' => 'plausible', 'analytics_domain' => ''] as $key => $value) $settings->execute([$key, $value]);

    $navCount = (int) $pdo->query('SELECT COUNT(*) FROM navigation_items')->fetchColumn();
    if ($navCount === 0) {
        $nav = $pdo->prepare('INSERT INTO navigation_items (label, url, sort_order, is_visible) VALUES (?, ?, ?, 1)');
        foreach ([['Home', '/', 10], ['Services', '/#services', 20], ['Contact', '/contact.html', 30]] as $item) $nav->execute($item);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = post_value('db_host', 'localhost');
    $dbName = post_value('db_name');
    $dbUser = post_value('db_user');
    $dbPassword = (string) ($_POST['db_password'] ?? '');
    $adminName = post_value('admin_name');
    $adminEmail = strtolower(post_value('admin_email'));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');

    if ($dbHost === '' || $dbName === '' || $dbUser === '') $errors[] = 'Database host, name, and user are required.';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid admin email.';
    if (strlen($adminPassword) < 12) $errors[] = 'Admin password must be at least 12 characters.';

    if (!$errors) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);
            $pdo = new PDO($dsn, $dbUser, $dbPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
            create_or_update_schema($pdo);

            $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), role = VALUES(role), is_active = 1, updated_at = NOW()');
            $stmt->execute([$adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), $adminName, 'admin']);

            $config = "<?php\nreturn " . var_export(['host' => $dbHost, 'database' => $dbName, 'username' => $dbUser, 'password' => $dbPassword], true) . ";\n";
            if (file_put_contents($configPath, $config, LOCK_EX) === false) throw new RuntimeException('Could not write includes/config.php. Check file permissions.');
            file_put_contents($lockPath, 'Installed at ' . gmdate('c') . PHP_EOL, LOCK_EX);
            $success = true;
        } catch (Throwable $exception) {
            $errors[] = install_error_message($exception, $dbHost);
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex">
    <title>Install Portal | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons"><link rel="stylesheet" href="/assets/login.css?v=20260620-php-install">
  </head>
  <body>
    <main class="login-page"><section class="login-hero" aria-labelledby="install-heading"><a class="login-brand-logo" href="/" aria-label="Oligarchy Services home">OLIGARCHY</a><form class="login-panel" method="post"><div class="login-panel-heading"><p class="eyebrow">Portal setup</p><h1 id="install-heading"><?= $isRepairMode ? 'Repair database config' : 'Install backend' ?></h1><p><?= $isRepairMode ? 'The installer lock exists, but includes/config.php is missing. Re-enter the Hostinger database details to recreate it without deleting existing data.' : 'Create or update the portal tables and first admin account inside the existing Hostinger database.' ?></p></div>
      <?php if ($isRepairMode && !$success): ?><div class="form-alert is-visible is-error">includes/config.php was not found, so login cannot connect. This repair will recreate the config file and keep existing tables/data.</div><?php endif; ?>
      <?php if ($success): ?><div class="form-alert is-visible is-success">Setup complete. The database config was written and the installer is locked. Open <a href="/login.html">login</a>.</div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="form-alert is-visible is-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
      <label class="field"><span>Database host</span><input name="db_host" value="<?= htmlspecialchars(post_value('db_host', 'localhost'), ENT_QUOTES, 'UTF-8') ?>" required><small class="field-error">For Hostinger, use localhost unless hPanel shows a separate MySQL host. Do not use the website domain here.</small></label>
      <label class="field"><span>Database name</span><input name="db_name" value="<?= htmlspecialchars(post_value('db_name'), ENT_QUOTES, 'UTF-8') ?>" required></label>
      <label class="field"><span>Database user</span><input name="db_user" value="<?= htmlspecialchars(post_value('db_user'), ENT_QUOTES, 'UTF-8') ?>" required></label>
      <label class="field"><span>Database password</span><input name="db_password" type="password" autocomplete="off" required></label>
      <label class="field"><span>Admin full name</span><input name="admin_name" value="<?= htmlspecialchars(post_value('admin_name', 'Admin'), ENT_QUOTES, 'UTF-8') ?>" required></label>
      <label class="field"><span>Admin email</span><input name="admin_email" type="email" value="<?= htmlspecialchars(post_value('admin_email'), ENT_QUOTES, 'UTF-8') ?>" required></label>
      <label class="field"><span>Admin password</span><input name="admin_password" type="password" minlength="12" autocomplete="new-password" required></label>
      <button class="button primary login-submit" type="submit"><?= $isRepairMode ? 'Repair config' : 'Install portal' ?></button><p class="login-note">The installer creates missing tables safely and locks itself after setup. Keep includes/config.php on Hostinger; it is intentionally not stored in GitHub.</p>
    </form></section></main>
  </body>
</html>