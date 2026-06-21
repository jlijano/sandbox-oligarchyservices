<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/blogs.php';
require_once __DIR__ . '/includes/installer.php';

$user = require_login();
$role = strtolower((string) ($user['role'] ?? 'client'));
if (!in_array($role, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo 'Only admins and editors can manage blogs.';
    exit;
}

$pdo = db();
create_or_update_schema($pdo);
blog_ensure_schema($pdo);

function blog_flash_success(string $message): void { $_SESSION['blog_notice'] = $message; }
function blog_flash_error(string $message): void { $_SESSION['blog_error'] = $message; }
function blog_admin_redirect(string $query = ''): void { redirect_to('/admin-blogs.php' . ($query !== '' ? '?' . $query : '')); }
function blog_post_string(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $default)); }
function blog_post_int(string $key, int $default = 0): int { return filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT) !== false ? (int) $_POST[$key] : $default; }
function blog_admin_excerpt(string $value, int $length = 90): string { return blog_excerpt($value, $length); }
function blog_log_activity(PDO $pdo, int $actorId, string $action, ?int $targetId, string $details = ''): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, 'blog', $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $error) {
        error_log('Blog activity log skipped: ' . $error->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        blog_flash_error('Your session expired. Please refresh and try again.');
        blog_admin_redirect();
    }

    $action = blog_post_string('action');

    try {
        if ($action === 'save_blog') {
            $blogId = blog_post_int('blog_id');
            $title = blog_post_string('title');
            $slug = blog_slugify(blog_post_string('slug') ?: $title);
            $excerpt = blog_post_string('excerpt');
            $body = trim((string) ($_POST['body'] ?? ''));
            $status = blog_status(blog_post_string('status'));
            $category = blog_post_string('category');
            $seoTitle = blog_post_string('seo_title');
            $seoDescription = blog_post_string('seo_description');
            $shareTitle = blog_post_string('social_share_title');
            $shareDescription = blog_post_string('social_share_description');
            $existingImage = blog_post_string('existing_featured_image');
            $uploadedImage = blog_store_uploaded_image($_FILES['featured_image'] ?? [], $slug);
            $featuredImage = $uploadedImage !== '' ? $uploadedImage : $existingImage;

            if ($title === '' || $slug === '' || $excerpt === '' || $body === '') {
                throw new RuntimeException('Title, slug, excerpt, and body are required.');
            }

            $dupe = $pdo->prepare('SELECT id FROM blogs WHERE slug = ? AND id <> ? LIMIT 1');
            $dupe->execute([$slug, $blogId]);
            if ($dupe->fetch()) {
                throw new RuntimeException('That blog slug is already in use.');
            }

            if ($featuredImage !== '' && !preg_match('#^(/uploads/blog/|https?://)#i', $featuredImage)) {
                throw new RuntimeException('Use an uploaded blog image or a valid image URL.');
            }

            if ($blogId > 0) {
                $stmt = $pdo->prepare("UPDATE blogs SET title = ?, slug = ?, excerpt = ?, body = ?, featured_image = ?, status = ?, author_id = ?, category = ?, published_at = CASE WHEN ? = 'published' AND published_at IS NULL THEN NOW() WHEN ? = 'published' THEN published_at ELSE NULL END, seo_title = ?, seo_description = ?, social_share_title = ?, social_share_description = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$title, $slug, $excerpt, $body, $featuredImage, $status, (int) $user['id'], $category, $status, $status, $seoTitle, $seoDescription, $shareTitle, $shareDescription, $blogId]);
                blog_log_activity($pdo, (int) $user['id'], $status === 'published' ? 'blog updated/published' : 'blog updated', $blogId, $slug);
                blog_flash_success('Blog post updated.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO blogs (title, slug, excerpt, body, featured_image, status, author_id, category, published_at, seo_title, seo_description, social_share_title, social_share_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = 'published' THEN NOW() ELSE NULL END, ?, ?, ?, ?)");
                $stmt->execute([$title, $slug, $excerpt, $body, $featuredImage, $status, (int) $user['id'], $category, $status, $seoTitle, $seoDescription, $shareTitle, $shareDescription]);
                $newId = (int) $pdo->lastInsertId();
                blog_log_activity($pdo, (int) $user['id'], $status === 'published' ? 'blog created/published' : 'blog created', $newId, $slug);
                blog_flash_success('Blog post saved.');
            }

            blog_admin_redirect();
        }

        if ($action === 'toggle_blog') {
            $blogId = blog_post_int('blog_id');
            $status = blog_status(blog_post_string('status'));
            $stmt = $pdo->prepare("UPDATE blogs SET status = ?, published_at = CASE WHEN ? = 'published' AND published_at IS NULL THEN NOW() WHEN ? = 'published' THEN published_at ELSE NULL END, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $status, $status, $blogId]);
            blog_log_activity($pdo, (int) $user['id'], $status === 'published' ? 'blog published' : 'blog unpublished', $blogId);
            blog_flash_success($status === 'published' ? 'Blog post published.' : 'Blog post unpublished.');
            blog_admin_redirect();
        }

        if ($action === 'delete_blog') {
            $blogId = blog_post_int('blog_id');
            $pdo->prepare('DELETE FROM blogs WHERE id = ?')->execute([$blogId]);
            blog_log_activity($pdo, (int) $user['id'], 'blog deleted', $blogId);
            blog_flash_success('Blog post deleted.');
            blog_admin_redirect();
        }
    } catch (Throwable $error) {
        blog_flash_error($error->getMessage());
        blog_admin_redirect();
    }
}

$notice = $_SESSION['blog_notice'] ?? null;
$error = $_SESSION['blog_error'] ?? null;
unset($_SESSION['blog_notice'], $_SESSION['blog_error']);

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = blog_status((string) ($_GET['status'] ?? ''));
if (!isset($_GET['status']) || !in_array($_GET['status'], ['draft', 'published'], true)) {
    $statusFilter = '';
}
$editIdRaw = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT);
$editId = $editIdRaw === false ? 0 : (int) $editIdRaw;
$editing = null;
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(b.title LIKE ? OR b.slug LIKE ? OR b.category LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}
if ($statusFilter !== '') {
    $where[] = 'b.status = ?';
    $params[] = $statusFilter;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare('SELECT b.*, u.full_name AS author_name FROM blogs b LEFT JOIN users u ON u.id = b.author_id' . $whereSql . ' ORDER BY b.updated_at DESC, b.id DESC');
$stmt->execute($params);
$blogs = $stmt->fetchAll();

if ($editId > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM blogs WHERE id = ? LIMIT 1');
    $editStmt->execute([$editId]);
    $editing = $editStmt->fetch() ?: null;
}

$counts = [
    'total' => (int) $pdo->query('SELECT COUNT(*) FROM blogs')->fetchColumn(),
    'published' => (int) $pdo->query("SELECT COUNT(*) FROM blogs WHERE status = 'published'")->fetchColumn(),
    'draft' => (int) $pdo->query("SELECT COUNT(*) FROM blogs WHERE status = 'draft'")->fetchColumn(),
];
$csrf = csrf_token();
$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$initials = strtoupper(substr($displayName, 0, 1));
$roleLabel = ucfirst($role);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Blogs Admin | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-blogs-nav">
    <link rel="stylesheet" href="/assets/blogs.css?v=20260621-blogs">
    <script defer src="/assets/dashboard.js?v=20260621-blogs-nav"></script>
  </head>
  <body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <aside class="dashboard-sidebar" id="portal-sidebar" aria-label="Portal navigation">
        <div class="sidebar-brand">
          <a href="/dashboard.php#overview" aria-label="Oligarchy Services dashboard">OLIGARCHY</a>
          <button class="sidebar-collapse" type="button" data-sidebar-collapse aria-label="Collapse sidebar" aria-expanded="true">‹</button>
        </div>
        <nav class="sidebar-nav">
          <a href="/dashboard.php#overview"><span class="nav-icon" aria-hidden="true">O</span><span class="nav-label">Overview</span></a>
          <?php if ($role === 'admin'): ?>
            <div class="sidebar-group" data-valley-group>
              <button class="sidebar-group-toggle" type="button" data-valley-toggle aria-expanded="false"><span class="nav-icon" aria-hidden="true">V</span><span class="nav-label">Valley</span><span class="sidebar-group-caret" aria-hidden="true">&gt;</span></button>
              <div class="sidebar-subnav" data-valley-subnav><a href="/dashboard.php#users"><span class="nav-icon" aria-hidden="true">U</span><span class="nav-label">Users</span></a></div>
            </div>
          <?php endif; ?>
          <a href="/dashboard.php#pages"><span class="nav-icon" aria-hidden="true">P</span><span class="nav-label">Pages</span></a>
          <a class="is-active" href="/admin-blogs.php" aria-current="page"><span class="nav-icon" aria-hidden="true">B</span><span class="nav-label">Blogs</span></a>
          <a href="/dashboard.php#navigation"><span class="nav-icon" aria-hidden="true">N</span><span class="nav-label">Navigation</span></a>
          <a href="/dashboard.php#settings"><span class="nav-icon" aria-hidden="true">S</span><span class="nav-label">Settings</span></a>
          <a href="/dashboard.php#activity"><span class="nav-icon" aria-hidden="true">A</span><span class="nav-label">Activity</span></a>
        </nav>
        <div class="sidebar-footer"><span class="sidebar-status">Role</span><strong><?= e($roleLabel) ?></strong></div>
      </aside>
      <div class="sidebar-backdrop" data-sidebar-backdrop></div>
      <div class="dashboard-main">
        <header class="dashboard-topbar">
          <div class="topbar-left"><button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button><div><p class="eyebrow">Website CMS</p><h1 data-section-title>Blogs</h1></div></div>
          <div class="topbar-actions"><span class="role-pill"><?= e($roleLabel) ?></span><div class="user-chip" title="<?= e($user['email']) ?>"><span><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($user['email']) ?></small></div></div><a class="logout-link" href="/logout.php">Log out</a></div>
        </header>
        <main class="dashboard-content standalone-admin">
          <header class="dashboard-hero compact-hero">
            <div><p class="eyebrow">Website CMS</p><h1>Blogs</h1><p>Create, edit, publish, unpublish, and delete public blog posts.</p></div>
            <div class="hero-actions"><a class="secondary-action" href="/dashboard.php">Dashboard</a><a class="secondary-action" href="/blogs.php" target="_blank" rel="noopener">Public Blogs</a></div>
          </header>

          <?php if ($notice): ?><div class="dashboard-alert is-success" role="status"><?= e((string) $notice) ?></div><?php endif; ?>
          <?php if ($error): ?><div class="dashboard-alert is-error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>

          <section class="section-summary-grid three-up" aria-label="Blog summary">
            <article><span>Total posts</span><strong><?= e((string) $counts['total']) ?></strong></article>
            <article><span>Published</span><strong><?= e((string) $counts['published']) ?></strong></article>
            <article><span>Drafts</span><strong><?= e((string) $counts['draft']) ?></strong></article>
          </section>

          <form class="admin-panel filter-panel blog-filter-panel" method="get" action="/admin-blogs.php">
            <label>Search blogs<input name="search" type="search" value="<?= e($search) ?>" placeholder="Title, slug, or category"></label>
            <label>Status<select name="status"><option value="">All statuses</option><option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Published</option><option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option></select></label>
            <div class="filter-actions"><button class="button primary" type="submit">Apply filters</button><a class="secondary-action" href="/admin-blogs.php">Clear</a></div>
          </form>

          <section class="panel-grid form-and-table blog-admin-grid">
            <form class="admin-panel page-editor" method="post" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="save_blog">
              <input type="hidden" name="blog_id" value="<?= e((string) ($editing['id'] ?? 0)) ?>">
              <h2><?= $editing ? 'Edit blog post' : 'Create blog post' ?></h2>
              <label>Title<input name="title" data-blog-title value="<?= e((string) ($editing['title'] ?? '')) ?>" required></label>
              <label>Slug<input name="slug" data-blog-slug value="<?= e((string) ($editing['slug'] ?? '')) ?>" placeholder="technology-operations" required></label>
              <label>Excerpt<textarea name="excerpt" rows="3" required><?= e((string) ($editing['excerpt'] ?? '')) ?></textarea></label>
              <label>Body<textarea name="body" rows="14" required><?= e((string) ($editing['body'] ?? '')) ?></textarea></label>
              <label>Category or tag<input name="category" value="<?= e((string) ($editing['category'] ?? '')) ?>" placeholder="Operations"></label>
              <label>Featured image<input name="featured_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
              <label>Current image URL<input name="existing_featured_image" value="<?= e((string) ($editing['featured_image'] ?? '')) ?>" placeholder="/uploads/blog/example.webp"></label>
              <label>Status<select name="status"><option value="draft" <?= ($editing['status'] ?? '') !== 'published' ? 'selected' : '' ?>>Draft</option><option value="published" <?= ($editing['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option></select></label>
              <label>SEO title<input name="seo_title" value="<?= e((string) ($editing['seo_title'] ?? '')) ?>"></label>
              <label>SEO description<textarea name="seo_description" rows="2"><?= e((string) ($editing['seo_description'] ?? '')) ?></textarea></label>
              <label>Social share title<input name="social_share_title" value="<?= e((string) ($editing['social_share_title'] ?? '')) ?>"></label>
              <label>Social share description<textarea name="social_share_description" rows="2"><?= e((string) ($editing['social_share_description'] ?? '')) ?></textarea></label>
              <div class="form-actions"><button class="button primary" type="submit">Save blog</button><?php if ($editing): ?><a class="button secondary" href="/admin-blogs.php">Cancel edit</a><?php endif; ?></div>
            </form>

            <div class="admin-panel table-panel">
              <div class="table-heading"><h2>Blog library</h2><span><?= e((string) count($blogs)) ?> result<?= count($blogs) === 1 ? '' : 's' ?></span></div>
              <?php if (!$blogs): ?>
                <p class="empty-state">No blog posts match the current filters.</p>
              <?php else: ?>
                <div class="table-scroll"><table class="data-table blog-table"><thead><tr><th>Title</th><th>Status</th><th>Date</th><th>Author</th><th></th></tr></thead><tbody>
                  <?php foreach ($blogs as $row): ?>
                    <tr><td><strong><?= e($row['title']) ?></strong><small><?= e(blog_admin_excerpt($row['excerpt'] ?: $row['body'])) ?></small><small class="code-link"><?= e($row['slug']) ?></small></td><td><span class="status-badge <?= $row['status'] === 'published' ? 'is-active' : 'is-muted' ?>"><?= e($row['status']) ?></span></td><td class="nowrap"><?= e((string) ($row['published_at'] ?: $row['updated_at'])) ?></td><td><?= e((string) ($row['author_name'] ?? '')) ?></td><td class="row-actions"><a class="table-action" href="/admin-blogs.php?edit=<?= e((string) $row['id']) ?>">Edit</a><?php if ($row['status'] === 'published'): ?><a class="table-action" href="/blog.php?slug=<?= e($row['slug']) ?>" target="_blank" rel="noopener">View</a><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="toggle_blog"><input type="hidden" name="blog_id" value="<?= e((string) $row['id']) ?>"><input type="hidden" name="status" value="<?= $row['status'] === 'published' ? 'draft' : 'published' ?>"><button class="table-action" type="submit"><?= $row['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button></form><form method="post" data-confirm="Delete this blog post?"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_blog"><input type="hidden" name="blog_id" value="<?= e((string) $row['id']) ?>"><button class="table-action danger" type="submit">Delete</button></form></td></tr>
                  <?php endforeach; ?>
                </tbody></table></div>
              <?php endif; ?>
            </div>
          </section>
        </main>
      </div>
    </div>
    <script>
      document.querySelectorAll('form[data-confirm]').forEach((form) => { form.addEventListener('submit', (event) => { if (!window.confirm(form.dataset.confirm || 'Continue?')) event.preventDefault(); }); });
      const title = document.querySelector('[data-blog-title]');
      const slug = document.querySelector('[data-blog-slug]');
      const slugify = (value) => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      if (title && slug) {
        title.addEventListener('input', () => { if (!slug.value || slug.dataset.autogenerated === 'true') { slug.value = slugify(title.value); slug.dataset.autogenerated = 'true'; } });
        slug.addEventListener('input', () => { slug.dataset.autogenerated = 'false'; });
      }
    </script>
  </body>
</html>
