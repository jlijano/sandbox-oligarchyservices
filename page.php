<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/db.php';

function public_slug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
    return trim($slug, '-');
}

$slug = public_slug((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    require __DIR__ . '/404.html';
    exit;
}

try {
    $stmt = db()->prepare("SELECT title, slug, meta_description, body, updated_at FROM pages WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
} catch (Throwable $error) {
    error_log('CMS page render error: ' . $error->getMessage());
    $page = false;
}

if (!$page) {
    http_response_code(404);
    require __DIR__ . '/404.html';
    exit;
}

$title = (string) $page['title'];
$description = (string) ($page['meta_description'] ?: substr(trim(strip_tags((string) $page['body'])), 0, 155));
$body = nl2br(e((string) $page['body']));
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | Oligarchy Services</title>
    <meta name="description" content="<?= e($description) ?>">
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
  </head>
  <body>
    <header class="site-header">
      <a class="brand" href="/" aria-label="Oligarchy Services home"><span class="brand-mark">OS</span><span>Oligarchy Services</span></a>
      <nav class="nav-links is-open" aria-label="Primary navigation"><a href="/">Home</a><a href="/contact.html">Contact</a><a class="nav-cta" href="/login.html">Client login</a></nav>
    </header>
    <main>
      <section class="page-hero">
        <p class="eyebrow">Oligarchy Services</p>
        <h1><?= e($title) ?></h1>
        <?php if ($description !== ''): ?><p><?= e($description) ?></p><?php endif; ?>
      </section>
      <section class="section policy-section">
        <div class="cms-content"><?= $body ?></div>
      </section>
    </main>
  </body>
</html>
