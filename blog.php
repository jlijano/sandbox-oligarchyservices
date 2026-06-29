<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/blogs.php';

$slug = blog_slugify((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    require __DIR__ . '/404.html';
    exit;
}

try {
    $pdo = db();
    $post = blog_fetch_published_by_slug($pdo, $slug);
    $latest = blog_fetch_published($pdo, 4);
} catch (Throwable $error) {
    error_log('Blog detail error: ' . $error->getMessage());
    $post = null;
    $latest = [];
}

if (!$post) {
    http_response_code(404);
    require __DIR__ . '/404.html';
    exit;
}

$public = blog_public_fields($post);
$title = (string) ($post['seo_title'] ?: $post['title']);
$description = (string) ($post['seo_description'] ?: $public['excerpt']);
$shareTitle = (string) ($post['social_share_title'] ?: $post['title']);
$shareDescription = (string) ($post['social_share_description'] ?: $description);
$canonical = blog_canonical_url('/blog.php?slug=' . rawurlencode((string) $post['slug']));
$encodedUrl = rawurlencode($canonical);
$encodedTitle = rawurlencode($shareTitle);
$encodedDescription = rawurlencode($shareDescription);
$encodedWhatsApp = rawurlencode($shareTitle . ' ' . $canonical);
$shareImage = $public['featuredImage'] !== '' ? blog_canonical_url($public['featuredImage']) : '';
$body = nl2br(e((string) $post['body']));
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | Oligarchy Services</title>
    <meta name="description" content="<?= e($description) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:title" content="<?= e($shareTitle) ?>">
    <meta property="og:description" content="<?= e($shareDescription) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <?php if ($shareImage !== ''): ?><meta property="og:image" content="<?= e($shareImage) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= $shareImage !== '' ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($shareTitle) ?>">
    <meta name="twitter:description" content="<?= e($shareDescription) ?>">
    <?php if ($shareImage !== ''): ?><meta name="twitter:image" content="<?= e($shareImage) ?>"><?php endif; ?>
    <link rel="stylesheet" href="/assets/styles.css?v=20260621-blogs">
    <link rel="stylesheet" href="/assets/blogs.css?v=20260622-blogs-complete">
    <link rel="stylesheet" href="/assets/footer.css?v=20260618-crawford-layout">
    <script defer src="/assets/blogs.js?v=20260622-blogs-complete"></script>
  </head>
  <body>
    <header class="site-header"><a class="brand" href="/" aria-label="Oligarchy Services home"><span class="brand-mark" aria-hidden="true"></span></a><button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">Menu</button><nav id="primary-navigation" class="nav-links" aria-label="Primary navigation"><a href="/">Home</a><a href="/about.html">About Us</a><div class="nav-dropdown"><button class="nav-dropdown-toggle" type="button" aria-expanded="false">Services</button><div class="nav-dropdown-menu"><a href="/ai-automation.html">AI &amp; Automation</a><a href="/help-desk.html">Help Desk</a><a href="/msp.html">MSP</a><a href="/business-systems.html">Business Systems</a><a href="/itad.html">ITAD</a><a href="/itam.html">ITAM</a></div></div><a href="/blogs.php">Blogs</a><a href="/contact.html">Contact Us</a><a class="nav-cta" href="/contact.html">Get Quote</a></nav></header>
    <main>
      <article class="blog-detail">
        <header class="blog-detail-header">
          <p class="eyebrow"><?= e($public['category'] !== '' ? $public['category'] : 'Blog') ?></p>
          <h1><?= e($post['title']) ?></h1>
          <div class="blog-detail-meta">
            <?php if ($public['author'] !== ''): ?><span>By <?= e($public['author']) ?></span><?php endif; ?>
            <time datetime="<?= e(substr($public['publishedAt'], 0, 10)) ?>"><?= e(date('M j, Y', strtotime($public['publishedAt']))) ?></time>
          </div>
        </header>
        <?php if ($public['featuredImage'] !== ''): ?>
          <img class="blog-detail-image" src="<?= e($public['featuredImage']) ?>" alt="<?= e($public['featuredImageAlt']) ?>">
        <?php endif; ?>
        <div class="blog-detail-layout">
          <div class="cms-content blog-body"><?= $body ?></div>
          <aside class="blog-share" aria-label="Share this blog post">
            <h2>Share</h2>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e($encodedUrl) ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
            <a href="https://twitter.com/intent/tweet?url=<?= e($encodedUrl) ?>&text=<?= e($encodedTitle) ?>" target="_blank" rel="noopener noreferrer">X / Twitter</a>
            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?= e($encodedUrl) ?>&title=<?= e($encodedTitle) ?>&summary=<?= e($encodedDescription) ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a>
            <a href="https://wa.me/?text=<?= e($encodedWhatsApp) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
            <button type="button" data-copy-link data-copy-value="<?= e($canonical) ?>">Copy Link</button>
            <span class="copy-feedback" data-copy-feedback aria-live="polite"></span>
          </aside>
        </div>
      </article>
      <?php $related = array_values(array_filter($latest, static fn(array $row): bool => (int) $row['id'] !== (int) $post['id'])); ?>
      <?php if ($related): ?>
        <section class="section related-blogs" aria-labelledby="related-blogs-heading">
          <div class="section-heading"><p class="eyebrow">Keep reading</p><h2 id="related-blogs-heading">Latest Blogs</h2></div>
          <div class="blog-card-grid">
            <?php foreach (array_slice($related, 0, 3) as $row): $item = blog_public_fields($row); ?>
              <article class="blog-card">
                <a class="blog-card-image" href="<?= e($item['url']) ?>"><?php if ($item['featuredImage'] !== ''): ?><img src="<?= e($item['featuredImage']) ?>" alt="<?= e($item['featuredImageAlt']) ?>" loading="lazy"><?php else: ?><span aria-hidden="true">OS</span><?php endif; ?></a>
                <div class="blog-card-body"><div class="blog-meta"><?php if ($item['category'] !== ''): ?><span><?= e($item['category']) ?></span><?php endif; ?><time datetime="<?= e(substr($item['publishedAt'], 0, 10)) ?>"><?= e(date('M j, Y', strtotime($item['publishedAt']))) ?></time></div><h3><a href="<?= e($item['url']) ?>"><?= e($item['title']) ?></a></h3><p><?= e($item['excerpt']) ?></p><a class="blog-read-more" href="<?= e($item['url']) ?>">Read More</a></div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    </main>
    <footer class="site-footer site-footer-refined">
      <div class="footer-main">
        <div class="footer-left">
          <a class="footer-center-brand" href="/" aria-label="Oligarchy Services home"><span class="footer-center-mark" aria-hidden="true">O</span><span>Oligarchy Services</span></a>
          <ul class="footer-social" aria-label="Social links">
            <li><a href="/contact.html" aria-label="LinkedIn">in</a></li>
            <li><a href="/contact.html" aria-label="Facebook">f</a></li>
            <li><a href="/contact.html" aria-label="Instagram">ig</a></li>
            <li><a href="/contact.html" aria-label="YouTube">yt</a></li>
          </ul>
        </div>
        <nav class="footer-column" aria-label="Footer services"><h2>Services</h2><a href="/ai-automation.html">AI &amp; Automation</a><a href="/help-desk.html">Help Desk</a><a href="/msp.html">MSP</a><a href="/business-systems.html">Business Systems</a><a href="/itad.html">ITAD</a><a href="/itam.html">ITAM</a></nav>
        <nav class="footer-column" aria-label="Footer industries"><h2>Industries</h2><a href="/contact.html">For IT Teams</a><a href="/contact.html">For Operations Teams</a><a href="/contact.html">For Asset Managers</a><a href="/contact.html">For Growing Businesses</a><a href="/contact.html">See More Solutions</a></nav>
        <nav class="footer-column" aria-label="Footer about"><h2>About</h2><a href="/about.html">Our Story</a><a href="/projects.html">Projects</a><a href="/career-timeline.html">Career Timeline</a><a href="/login.html">Client Login</a><a href="/blogs.php">Blog</a></nav>
        <nav class="footer-column" aria-label="Footer legal"><h2>Legal</h2><a href="/privacy.html">Privacy Notice</a><a href="/privacy.html#analytics-opt-out">Cookie Preferences</a><a href="/privacy.html">Analytics Notice</a><a href="/contact.html">Terms Requests</a></nav>
      </div>
      <div class="footer-bottom"><span>&copy; 2026 Oligarchy Services. All rights reserved.</span><a href="mailto:connect@oligarchyservices.com">connect@oligarchyservices.com</a></div>
    </footer>
  </body>
</html>