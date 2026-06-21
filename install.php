<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/installer.php';
require_once __DIR__ . '/includes/access-management.php';

$lockPath = db_install_lock_path();
$configPath = db_local_config_path();
installer_restore_config_from_backup($configPath);
$hasLock = db_has_install_lock();
$hasConfig = installer_existing_config_path($configPath) !== null || db_has_config();
$errors = [];
$warnings = [];
$success = false;

if ($hasLock && !$hasConfig) {
    header('Location: /repair.php', true, 302);
    exit;
}

if ($hasLock || $hasConfig) {
    http_response_code(403);
    echo 'Install is not available because this portal already has setup files. Use /update.php while logged in as an admin, or use /repair.php only if the database config is missing.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = installer_post_value('db_host', 'localhost');
    $dbName = installer_post_value('db_name');
    $dbUser = installer_post_value('db_user');
    $dbPassword = (string) ($_POST['db_password'] ?? '');
    $adminName = installer_post_value('admin_name', 'Admin');
    $adminEmail = strtolower(installer_post_value('admin_email'));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');

    if ($dbHost === '' || $dbName === '' || $dbUser === '') $errors[] = 'Database host, name, and user are required.';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid admin email.';
    if (strlen($adminPassword) < 12) $errors[] = 'Admin password must be at least 12 characters.';

    if (!$errors) {
        try {
            $pdo = installer_pdo_from_credentials($dbHost, $dbName, $dbUser, $dbPassword);
            create_or_update_schema($pdo);
            access_management_ensure_schema($pdo);
            installer_upsert_admin($pdo, $adminEmail, $adminPassword, $adminName);
            $warnings = installer_config_write_warnings(installer_write_config($configPath, $dbHost, $dbName, $dbUser, $dbPassword));
            file_put_contents($lockPath, 'Installed at ' . gmdate('c') . PHP_EOL, LOCK_EX);
            $success = true;
        } catch (Throwable $exception) {
            $errors[] = installer_error_message($exception, $dbHost);
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
    <main class="login-page"><section class="login-hero" aria-labelledby="install-heading"><a class="login-brand-logo" href="/" aria-label="Oligarchy Services home">OLIGARCHY</a><form class="login-panel" method="post"><div class="login-panel-heading"><p class="eyebrow">Portal setup</p><h1 id="install-heading">Install backend</h1><p>Create the portal config, required tables, and first admin account inside the Hostinger database.</p></div>
      <?php if ($success): ?><div class="form-alert is-visible is-success">Install complete. Open <a href="/login.html">login</a>.</div><?php endif; ?>
      <?php foreach ($warnings as $warning): ?><div class="form-alert is-visible is-error"><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
      <?php foreach ($errors as $error): ?><div class="form-alert is-visible is-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
      <label class="field"><span>Database host</span><input name="db_host" value="<?= htmlspecialchars(installer_post_value('db_host', 'localhost'), ENT_QUOTES, 'UTF-8') ?>" required><small class="field-error">For Hostinger, use localhost unless hPanel shows a separate MySQL host. Do not use the website domain here.</small></label>
      <label class="field"><span>Database name</span><input name="db_name" value="<?= htmlspecialchars(installer_post_value('db_name'), ENT_QUOTES, 'UTF-8') ?>" required></label>
      <label class="field"><span>Database user</span><input name="db_user" value="<?= htmlspecialchars(installer_post_value('db_user'), ENT_QUOTES, 'UTF-8') ?>" required></label>
      <label class="field"><span>Database password</span><input name="db_password" type="password" autocomplete="off" required></label>
      <label class="field"><span>Admin full name</span><input name="admin_name" value="<?= htmlspecialchars(installer_post_value('admin_name', 'Admin'), ENT_QUOTES, 'UTF-8') ?>" required></label>
      <label class="field"><span>Admin email</span><input name="admin_email" type="email" value="<?= htmlspecialchars(installer_post_value('admin_email'), ENT_QUOTES, 'UTF-8') ?>" required></label>
      <label class="field"><span>Admin password</span><input name="admin_password" type="password" minlength="12" autocomplete="new-password" required></label>
      <button class="button primary login-submit" type="submit">Install portal</button><p class="login-note">Use this page only for first setup. Use update.php for database updates and repair.php when config.php is missing.</p>
    </form></section></main>
  </body>
</html>
