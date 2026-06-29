<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/blogs.php';

$posts = [];
$loadError = false;

try {
    $pdo = db();
    $posts = blog_fetch_published($pdo, 24);
} catch (Throwable $error) {
    error_log('Blog listing error: ' . $error->getMessage());
    $loadError = true;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blogs | Oligarchy Services</title>
    <meta name="description" content="Read the latest Oligarchy Services notes on technology operations, asset control, business systems, support, and automation.">
    <link rel="canonical" href="<?= e(blog_canonical_url('/blogs.php')) ?>">
    <meta property="og:title" content="Blogs | Oligarchy Services">
    <meta property="og:description" content="Read the latest Oligarchy Services notes on technology operations, asset control, business systems, support, and automation.">
    <link rel="stylesheet" href="/assets/styles.css?v=20260621-blogs">
    <link rel="stylesheet" href="/assets/blogs.css?v=20260621-blogs">
    <link rel="stylesheet" href="/assets/footer.css?v=20260618-crawford-layout">
  </head>
  <body>
    <header class="site-header"><a class="brand" href="/" aria-label="Oligarchy Services home"><span class="brand-mark" aria-hidden="true"></span></a><button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">Menu</button><nav id="primary-navigation" class="nav-links" aria-label="Primary navigation"><a href="/">Home</a><a href="/about.html">About Us</a><div class="nav-dropdown"><button class="nav-dropdown-toggle" type="button" aria-expanded="false">Services</button><div class="nav-dropdown-menu"><a href="/ai-automation.html">AI &amp; Automation</a><a href="/help-desk.html">Help Desk</a><a href="/msp.html">MSP</a><a href="/itad.html">ITAD</a><a href="/itam.html">ITAM</a></div></div><a href="/blogs.php" aria-current="page">Blogs</a><a href="/contact.html">Contact Us</a><a class="nav-cta" href="/contact.html">Get Quote</a></nav></header>
    <main>
      <section class="page-hero blog-page-hero">
        <p class="eyebrow">Insights</p>
        <h1>Blogs</h1>
        <p>Practical notes on asset lifecycle control, support operations, business systems, and automation.</p>
      </section>
      <section class="section blogs-page-section" aria-labelledby="blogs-list-heading">
        <div class="section-heading">
          <p class="eyebrow">Latest posts</p>
          <h2 id="blogs-list-heading">Published articles</h2>
        </div>
        <?php if ($loadError): ?>
          <p class="empty-state">Blog posts are temporarily unavailable. Please check back soon.</p>
        <?php elseif (!$posts): ?>
          <p class="empty-state">No blog posts have been published yet.</p>
        <?php else: ?>
          <div class="blog-card-grid">
            <?php foreach ($posts as $post): $item = blog_public_fields($post); ?>
              <article class="blog-card">
                <a class="blog-card-image" href="<?= e($item['url']) ?>" aria-label="Read <?= e($item['title']) ?>">
                  <?php if ($item['featuredImage'] !== ''): ?><img src="<?= e($item['featuredImage']) ?>" alt="<?= e($item['title']) ?>" loading="lazy"><?php else: ?><span aria-hidden="true">OS</span><?php endif; ?>
                </a>
                <div class="blog-card-body">
                  <div class="blog-meta"><?php if ($item['category'] !== ''): ?><span><?= e($item['category']) ?></span><?php endif; ?><time datetime="<?= e(substr($item['publishedAt'], 0, 10)) ?>"><?= e(date('M j, Y', strtotime($item['publishedAt']))) ?></time></div>
                  <h3><a href="<?= e($item['url']) ?>"><?= e($item['title']) ?></a></h3>
                  <p><?= e($item['excerpt']) ?></p>
                  <a class="blog-read-more" href="<?= e($item['url']) ?>">Read More</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
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