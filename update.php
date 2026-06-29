<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/installer.php';
require_once __DIR__ . '/includes/access-management.php';
require_once __DIR__ . '/includes/blogs.php';
require_once __DIR__ . '/includes/requests.php';
require_once __DIR__ . '/includes/prospects.php';
require_once __DIR__ . '/includes/automation.php';
require_once __DIR__ . '/includes/db.php';

$configPath = db_local_config_path();
$lockPath = db_install_lock_path();

if (!is_file($configPath)) {
    installer_restore_config_from_backup($configPath);
}

if (installer_existing_config_path($configPath) === null && !db_has_config()) {
    header('Location: ' . (is_file($lockPath) ? '/repair.php' : '/install.php'), true, 302);
    exit;
}

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Only admins can run portal updates.';
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            $pdo = db();
            create_or_update_schema($pdo);
            access_management_ensure_schema($pdo);
            blog_ensure_schema($pdo);
            request_ensure_schema($pdo);
            prospect_ensure_schema($pdo);
            prospect_add_column_if_missing($pdo, 'prospects', 'recommended_services', 'TEXT NULL');
            automation_ensure_schema($pdo);
            try {
                $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([(int) $user['id'], 'portal updated', 'system', null, 'Schema update ran from update.php', $_SERVER['REMOTE_ADDR'] ?? '']);
            } catch (Throwable $auditError) {
                error_log('Update audit skipped: ' . $auditError->getMessage());
            }
            $success = true;
        } catch (Throwable $exception) {
            error_log('Portal update failed: ' . $exception->getMessage());
            $errors[] = 'Update failed. Check the Hostinger PHP error log for the exact database message.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex">
    <title>Update Portal | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons"><link rel="stylesheet" href="/assets/login.css?v=20260620-php-install">
  </head>
  <body>
    <main class="login-page"><section class="login-hero" aria-labelledby="update-heading"><a class="login-brand-logo" href="/dashboard.php" aria-label="Back to dashboard">OLIGARCHY</a><form class="login-panel" method="post"><div class="login-panel-heading"><p class="eyebrow">Portal update</p><h1 id="update-heading">Update database tables</h1><p>Run safe, non-destructive table updates for the current CMS, access-management, blog, client request, prospects, and automation code. Existing users, pages, settings, activity, blogs, companies, departments, roles, requests, prospects, and automation recipes are kept.</p></div>
      <?php if ($success): ?><div class="form-alert is-visible is-success">Update complete. Return to <a href="/dashboard.php">dashboard</a>.</div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="form-alert is-visible is-error"><?= e($error) ?></div><?php endforeach; ?>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <button class="button primary login-submit" type="submit">Run update</button><p class="login-note">This page requires an active admin session. Use repair.php only when includes/config.php is missing.</p>
    </form></section></main>
  </body>
</html>
