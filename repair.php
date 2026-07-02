<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/installer.php';
require_once __DIR__ . '/includes/setup-access.php';

setup_send_safety_headers();

$lockPath = db_install_lock_path();
$configPath = db_local_config_path();
$restoredFromBackup = false;
if (!is_file($configPath)) {
    $restoredFromBackup = installer_restore_config_from_backup($configPath);
    if ($restoredFromBackup && !is_file($lockPath)) {
        file_put_contents($lockPath, 'Installed at ' . gmdate('c') . PHP_EOL, LOCK_EX);
    }
}
$hasLock = db_has_install_lock();
$hasConfig = installer_existing_config_path($configPath) !== null || db_has_config();
$errors = [];
$warnings = [];
$success = $restoredFromBackup;

if (is_file($configPath) && !$restoredFromBackup) {
    http_response_code(403);
    echo 'Repair is not needed because includes/config.php already exists. Use /update.php while logged in as an admin if tables need updates.';
    exit;
}

if ($hasConfig && !$restoredFromBackup) {
    http_response_code(403);
    echo 'Repair is not needed because database configuration is available from a persistent backup or server environment. Use /update.php while logged in as an admin if tables need updates.';
    exit;
}

if (!$hasLock && !$hasConfig) {
    header('Location: /install.php', true, 302);
    exit;
}

if (!$success && !setup_unlock_is_present($configPath, 'repair')) {
    setup_exit_locked('repair');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = installer_post_value('db_host', 'localhost');
    $dbName = installer_post_value('db_name');
    $dbUser = installer_post_value('db_user');
    $dbPassword = (string) ($_POST['db_password'] ?? '');
    $adminName = installer_post_value('admin_name', 'Admin');
    $adminEmail = strtolower(installer_post_value('admin_email'));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $wantsAdminReset = $adminEmail !== '' || $adminPassword !== '';

    if ($dbHost === '' || $dbName === '' || $dbUser === '') $errors[] = 'Database host, name, and user are required.';
    if ($wantsAdminReset && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid admin email, or leave the admin fields blank.';
    if ($wantsAdminReset && strlen($adminPassword) < 12) $errors[] = 'Admin password must be at least 12 characters.';

    if (!$errors) {
        try {
            $pdo = installer_pdo_from_credentials($dbHost, $dbName, $dbUser, $dbPassword);
            create_or_update_schema($pdo);

            if ($wantsAdminReset) {
                installer_upsert_admin($pdo, $adminEmail, $adminPassword, $adminName);
            } elseif (installer_admin_count($pdo) === 0) {
                $errors[] = 'No active admin account was found. Fill in the admin fields to create or restore one.';
            }

            if (!$errors) {
                $warnings = installer_config_write_warnings(installer_write_config($configPath, $dbHost, $dbName, $dbUser, $dbPassword));
                file_put_contents($lockPath, 'Installed at ' . gmdate('c') . PHP_EOL, LOCK_EX);
                $success = true;
            }
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
    <title>Repair Portal | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons"><link rel="stylesheet" href="/assets/login.css?v=20260620-php-install">
  </head>
  <body>
    <main class="login-page"><section class="login-hero" aria-labelledby="repair-heading"><a class="login-brand-logo" href="/" aria-label="Oligarchy Services home">OLIGARCHY</a><form class="login-panel" method="post"><div class="login-panel-heading"><p class="eyebrow">Portal repair</p><h1 id="repair-heading">Repair database config</h1><p>Reconnect the existing Hostinger database when the server-local config file is missing. Existing tables and data are kept.</p></div>
      <?php if (!$success): ?><div class="form-alert is-visible is-error">The portal database tables still exist, but the website config file was not found. Enter the Hostinger database details to reconnect; do not reinstall or drop the database.</div><?php endif; ?>
      <?php if ($success): ?><div class="form-alert is-visible is-success">Repair complete. Open <a href="/login.html">login</a>.</div><?php endif; ?>
      <?php foreach ($warnings as $warning): ?><div class="form-alert is-visible is-error"><?= htmlspecialchars($warning, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
      <?php foreach ($errors as $error): ?><div class="form-alert is-visible is-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
      <label class="field"><span>Database host</span><input name="db_host" value="<?= htmlspecialchars(installer_post_value('db_host', 'localhost'), ENT_QUOTES, 'UTF-8') ?>" required><small class="field-error">For Hostinger, use localhost unless hPanel shows a separate MySQL host.</small></label>
      <label class="field"><span>Database name</span><input name="db_name" value="<?= htmlspecialchars(installer_post_value('db_name'), ENT_QUOTES, 'UTF-8') ?>" required></label>
      <label class="field"><span>Database user</span><input name="db_user" value="<?= htmlspecialchars(installer_post_value('db_user'), ENT_QUOTES, 'UTF-8') ?>" required></label>
      <label class="field"><span>Database password</span><input name="db_password" type="password" autocomplete="off" required></label>
      <label class="field"><span>Admin full name <small class="field-error">Optional unless no active admin exists</small></span><input name="admin_name" value="<?= htmlspecialchars(installer_post_value('admin_name', 'Admin'), ENT_QUOTES, 'UTF-8') ?>"></label>
      <label class="field"><span>Admin email <small class="field-error">Optional password reset</small></span><input name="admin_email" type="email" value="<?= htmlspecialchars(installer_post_value('admin_email'), ENT_QUOTES, 'UTF-8') ?>"></label>
      <label class="field"><span>Admin password <small class="field-error">Optional password reset</small></span><input name="admin_password" type="password" minlength="12" autocomplete="new-password"></label>
      <button class="button primary login-submit" type="submit">Repair config</button><p class="login-note">Use repair.php only when config.php is missing. Use update.php for normal schema updates after login.</p>
    </form></section></main>
  </body>
</html>
