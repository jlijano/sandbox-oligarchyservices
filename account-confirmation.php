<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/installer.php';
require_once __DIR__ . '/includes/password-change.php';

$status = 'error';
$message = 'This confirmation link is invalid, expired, or has already been used.';
$loginUrl = '/login.html';
$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$tokenIsValidFormat = $token !== '' && preg_match('/^[a-f0-9]{64}$/', $token);
$canSetPassword = false;
$email = '';
$errors = [];

try {
    if ($tokenIsValidFormat) {
        $pdo = db();
        create_or_update_schema($pdo);
        password_change_ensure_schema($pdo);
        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email_confirmation_token_hash = ? AND email_confirmed_at IS NULL AND email_confirmation_expires_at > NOW() LIMIT 1');
        $stmt->execute([$tokenHash]);
        $confirmingUser = $stmt->fetch();

        if ($confirmingUser) {
            $canSetPassword = true;
            $email = (string) $confirmingUser['email'];
            $status = 'ready';
            $message = 'Confirm your account and create your password.';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!csrf_verify($_POST['csrf_token'] ?? null)) {
                    $errors[] = 'Your session expired. Refresh and try again.';
                }

                $password = (string) ($_POST['password'] ?? '');
                $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
                if (strlen($password) < 12) {
                    $errors[] = 'Password must be at least 12 characters.';
                }
                if ($password !== $confirmPassword) {
                    $errors[] = 'Passwords do not match.';
                }

                if (!$errors) {
                    $update = $pdo->prepare('UPDATE users SET password_hash = ?, email_confirmed_at = NOW(), email_confirmation_token_hash = NULL, email_confirmation_expires_at = NULL, password_change_required = 0, updated_at = NOW() WHERE id = ?');
                    $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $confirmingUser['id']]);
                    $canSetPassword = false;
                    $status = 'success';
                    $message = 'Your account is confirmed and your password is set. You can log in now.';
                }
            }
        }
    }
} catch (Throwable $error) {
    error_log('Account confirmation failed: ' . $error->getMessage());
    $message = 'Account confirmation could not reach the database. Please try again or contact Oligarchy Services.';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Confirm Account | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/login.css?v=20260620-php-login">
  </head>
  <body>
    <main class="login-page">
      <section class="login-hero" aria-labelledby="confirm-heading">
        <a class="login-brand-logo" href="/" aria-label="Oligarchy Services home">OLIGARCHY</a>
        <?php if ($canSetPassword): ?>
          <form class="login-panel" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="login-panel-heading">
              <p class="eyebrow">Client portal</p>
              <h1 id="confirm-heading">Create password</h1>
              <p><?= e($message) ?></p>
            </div>
            <?php if ($email !== ''): ?><div class="form-alert is-visible is-success" role="status">Setting password for <?= e($email) ?>.</div><?php endif; ?>
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
            <button class="button primary login-submit" type="submit">Confirm account</button>
            <p class="login-note">This link expires 48 hours after the invite is sent.</p>
          </form>
        <?php else: ?>
          <div class="login-panel">
            <div class="login-panel-heading">
              <p class="eyebrow">Client portal</p>
              <h1 id="confirm-heading">Confirm account</h1>
              <p><?= e($message) ?></p>
            </div>
            <div class="form-alert is-visible <?= $status === 'success' ? 'is-success' : 'is-error' ?>" role="status"><?= e($message) ?></div>
            <a class="button primary login-submit" href="<?= e($loginUrl) ?>">Open login</a>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </body>
</html>
