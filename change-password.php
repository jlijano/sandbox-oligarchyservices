<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/installer.php';
require_once __DIR__ . '/includes/password-change.php';

$user = require_login();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh and try again.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            try {
                $pdo = db();
                create_or_update_schema($pdo);
                password_change_ensure_schema($pdo);
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, password_change_required = 0, updated_at = NOW() WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
                unset($_SESSION['password_change_required']);
                $success = true;
            } catch (Throwable $error) {
                error_log('Password change failed: ' . $error->getMessage());
                $errors[] = 'Password could not be updated. Check the server log and try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Change Password | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/login.css?v=20260620-php-login">
  </head>
  <body>
    <main class="login-page">
      <section class="login-hero" aria-labelledby="password-heading">
        <a class="login-brand-logo" href="/" aria-label="Oligarchy Services home">OLIGARCHY</a>
        <form class="login-panel" method="post">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <div class="login-panel-heading">
            <p class="eyebrow">Client portal</p>
            <h1 id="password-heading">Change password</h1>
            <p>Create your own password before opening the dashboard.</p>
          </div>

          <?php if ($success): ?>
            <div class="form-alert is-visible is-success" role="status">Password updated. You can continue to the dashboard.</div>
            <a class="button primary login-submit" href="/dashboard.php">Open dashboard</a>
          <?php else: ?>
            <?php foreach ($errors as $error): ?>
              <div class="form-alert is-visible is-error" role="alert"><?= e($error) ?></div>
            <?php endforeach; ?>
            <label class="field" for="password">
              <span>New password</span>
              <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
            </label>
            <label class="field" for="confirm_password">
              <span>Confirm new password</span>
              <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="12" required>
            </label>
            <button class="button primary login-submit" type="submit">Update password</button>
            <p class="login-note">This step is required after confirming a newly created account.</p>
          <?php endif; ?>
        </form>
      </section>
    </main>
  </body>
</html>