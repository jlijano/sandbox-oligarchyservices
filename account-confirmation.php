<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/installer.php';

$status = 'error';
$message = 'This confirmation link is invalid or has already been used.';
$loginUrl = '/login.html';

$token = trim((string) ($_GET['token'] ?? ''));

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    try {
        $pdo = db();
        create_or_update_schema($pdo);
        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email_confirmation_token_hash = ? AND email_confirmed_at IS NULL AND email_confirmation_expires_at > NOW() LIMIT 1');
        $stmt->execute([$tokenHash]);
        $confirmingUser = $stmt->fetch();

        if ($confirmingUser) {
            $update = $pdo->prepare('UPDATE users SET email_confirmed_at = NOW(), email_confirmation_token_hash = NULL, email_confirmation_expires_at = NULL, updated_at = NOW() WHERE id = ?');
            $update->execute([(int) $confirmingUser['id']]);
            $status = 'success';
            $message = 'Your account email is confirmed. Log in with the temporary password from your email, then create your own password.';
        }
    } catch (Throwable $error) {
        error_log('Account confirmation failed: ' . $error->getMessage());
        $message = 'Account confirmation could not reach the database. Please try again or contact Oligarchy Services.';
    }
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
    <link rel="stylesheet" href="/assets/login.css?v=20260703-confirmation-flow">
  </head>
  <body>
    <main class="login-page">
      <section class="login-hero" aria-labelledby="confirm-heading">
        <a class="login-brand-logo" href="/" aria-label="Oligarchy Services home">OLIGARCHY</a>
        <div class="login-panel">
          <div class="login-panel-heading">
            <p class="eyebrow">Client portal</p>
            <h1 id="confirm-heading">Confirm account</h1>
            <p><?= e($message) ?></p>
          </div>
          <div class="form-alert is-visible <?= $status === 'success' ? 'is-success' : 'is-error' ?>" role="status"><?= e($message) ?></div>
          <a class="button primary login-submit" href="<?= e($loginUrl) ?>">Open login</a>
        </div>
      </section>
    </main>
  </body>
</html>