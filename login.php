<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';

$loginError = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Client Login | Oligarchy Services</title>
    <meta name="description" content="Client access portal for Oligarchy Services technology operations, support, and reporting.">
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/footer.css?v=20260618-crawford-layout">
    <link rel="stylesheet" href="/assets/login.css?v=20260620-php-login">
    <script>window.OLIGARCHY_ANALYTICS={enabled:false,provider:"plausible",domain:"",respectDoNotTrack:true};</script>
    <script defer src="/assets/analytics.js?v=20260620-centered-login"></script>
    <script defer src="/assets/login.js?v=20260620-php-login"></script>
  </head>
  <body>
    <main class="login-page">
      <section class="login-hero" aria-labelledby="login-heading">
        <a class="login-brand-logo" href="/" aria-label="Oligarchy Services home">OLIGARCHY</a>
        <form class="login-panel" id="client-login-form" action="/api/login.php" method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <div class="login-panel-heading">
            <p class="eyebrow">Client portal</p>
            <h1 id="login-heading">Log in</h1>
            <p>Use the email assigned to your organization workspace.</p>
          </div>

          <div class="form-alert<?= $loginError !== '' ? ' is-visible is-error' : '' ?>" id="login-message" role="status" aria-live="polite"><?= e($loginError) ?></div>

          <label class="field" for="email">
            <span>Email address</span>
            <input id="email" name="email" type="email" inputmode="email" autocomplete="username" placeholder="name@company.com" required>
            <small class="field-error" id="email-error"></small>
          </label>

          <label class="field" for="password">
            <span>Password</span>
            <span class="password-control">
              <input id="password" name="password" type="password" autocomplete="current-password" minlength="8" required>
              <button class="password-toggle" type="button" aria-controls="password" aria-pressed="false">Show</button>
            </span>
            <small class="field-error" id="password-error"></small>
          </label>

          <div class="login-options">
            <label class="remember-option">
              <input id="remember-email" name="rememberEmail" type="checkbox">
              <span>Remember email</span>
            </label>
            <a href="/contact.html">Request access</a>
          </div>

          <button class="button primary login-submit" type="submit">Sign in</button>
          <p class="login-note">Access is managed by Oligarchy Services. Contact your workspace lead if your account is not active yet.</p>
        </form>
      </section>
    </main>
  </body>
</html>
