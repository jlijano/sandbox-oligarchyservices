<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Dashboard | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <style>
      .portal-shell{min-height:100dvh;display:grid;place-items:center;padding:32px 16px;background:#080809;color:#f4f4f5}.portal-panel{width:min(100%,760px);border:1px solid rgba(90,93,99,.58);border-radius:8px;padding:28px;background:#17171a;box-shadow:0 22px 60px rgba(0,0,0,.36)}.portal-panel h1{margin:0 0 12px;font-size:clamp(2rem,5vw,3rem)}.portal-panel p{color:#b5b8bf}.portal-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:22px}.portal-actions a{display:inline-flex;min-height:44px;align-items:center;justify-content:center;border-radius:8px;padding:10px 16px;text-decoration:none}.portal-primary{background:#a40712;color:#fff}.portal-secondary{border:1px solid #343238;color:#f4f4f5}
    </style>
  </head>
  <body>
    <main class="portal-shell">
      <section class="portal-panel">
        <p class="eyebrow">Client portal</p>
        <h1>Welcome, <?= e($user['full_name'] ?: $user['email']) ?></h1>
        <p>Your secure workspace is ready. Portal modules can be added here for support requests, asset records, project handoffs, and reporting.</p>
        <div class="portal-actions">
          <a class="portal-primary" href="/contact.html">Request support</a>
          <a class="portal-secondary" href="/logout.php">Log out</a>
        </div>
      </section>
    </main>
  </body>
</html>
