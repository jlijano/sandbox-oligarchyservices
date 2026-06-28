<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/installer.php';
require_once __DIR__ . '/includes/access-management.php';
require_once __DIR__ . '/includes/pages.php';

$user = require_login();
cms_require_editor($user);
$pdo = db();
create_or_update_schema($pdo);

$role = strtolower((string) ($user['role'] ?? 'client'));
$roleLabel = ucfirst($role);
$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$initials = strtoupper(substr($displayName, 0, 1));

function cms_unique_slug(PDO $pdo, string $base, int $ignoreId = 0): string
{
    $slug = cms_slugify($base) ?: 'page';
    $candidate = $slug;
    $suffix = 2;
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM pages WHERE slug = ? AND id <> ? LIMIT 1');
        $stmt->execute([$candidate, $ignoreId]);
        if (!$stmt->fetch()) {
            return $candidate;
        }
        $candidate = $slug . '-' . $suffix;
        $suffix++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        cms_flash('error', 'Your session expired. Please refresh and try again.');
        cms_pages_redirect();
    }

    $action = cms_post_string('action');

    try {
        if ($action === 'save_page') {
            $pageId = cms_post_int('page_id');
            $title = cms_post_string('title');
            $slug = cms_slugify(cms_post_string('slug') ?: $title);
            $meta = cms_post_string('meta_description');
            $status = cms_post_string('status') === 'published' ? 'published' : 'draft';
            $editorMode = cms_post_string('editor_mode') === 'legacy' ? 'legacy' : 'builder';
            $body = '';

            if ($title === '') {
                throw new RuntimeException('Title is required.');
            }
            if ($slug === '') {
                throw new RuntimeException('Slug is required.');
            }

            if ($editorMode === 'legacy') {
                $body = trim((string) ($_POST['legacy_body'] ?? ''));
            } else {
                $blocks = cms_normalize_builder_blocks((string) ($_POST['builder_json'] ?? ''));
                if (!$blocks) {
                    throw new RuntimeException('Add at least one builder block before saving.');
                }
                $body = cms_encode_builder($blocks);
            }
            if ($body === '') {
                throw new RuntimeException('Page content is required.');
            }

            $dupe = $pdo->prepare('SELECT id FROM pages WHERE slug = ? AND id <> ? LIMIT 1');
            $dupe->execute([$slug, $pageId]);
            if ($dupe->fetch()) {
                throw new RuntimeException('That page slug is already in use.');
            }

            if ($pageId > 0) {
                $existing = $pdo->prepare('SELECT status FROM pages WHERE id = ? LIMIT 1');
                $existing->execute([$pageId]);
                $oldStatus = (string) ($existing->fetchColumn() ?: '');
                if ($oldStatus === '') {
                    throw new RuntimeException('Choose a valid page to update.');
                }
                $stmt = $pdo->prepare('UPDATE pages SET title = ?, slug = ?, meta_description = ?, body = ?, status = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$title, $slug, $meta, $body, $status, $pageId]);
                cms_log_activity($pdo, (int) $user['id'], 'page updated', $pageId, $slug);
                if ($oldStatus !== $status) {
                    cms_log_activity($pdo, (int) $user['id'], $status === 'published' ? 'page published' : 'page unpublished', $pageId, $slug);
                }
                cms_flash('notice', 'Page updated.');
                cms_pages_redirect(['edit' => $pageId]);
            }

            $stmt = $pdo->prepare('INSERT INTO pages (title, slug, meta_description, body, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$title, $slug, $meta, $body, $status]);
            $newId = (int) $pdo->lastInsertId();
            cms_log_activity($pdo, (int) $user['id'], 'page created', $newId, $slug);
            if ($status === 'published') {
                cms_log_activity($pdo, (int) $user['id'], 'page published', $newId, $slug);
            }
            cms_flash('notice', 'Page created.');
            cms_pages_redirect(['edit' => $newId]);
        }

        if ($action === 'toggle_page') {
            $pageId = cms_post_int('page_id');
            $status = cms_post_string('status') === 'published' ? 'published' : 'draft';
            $lookup = $pdo->prepare('SELECT slug FROM pages WHERE id = ? LIMIT 1');
            $lookup->execute([$pageId]);
            $slug = (string) ($lookup->fetchColumn() ?: '');
            if ($slug === '') {
                throw new RuntimeException('Choose a valid page.');
            }
            $pdo->prepare('UPDATE pages SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $pageId]);
            cms_log_activity($pdo, (int) $user['id'], $status === 'published' ? 'page published' : 'page unpublished', $pageId, $slug);
            cms_flash('notice', $status === 'published' ? 'Page published.' : 'Page unpublished.');
            cms_pages_redirect(['edit' => $pageId]);
        }

        if ($action === 'duplicate_page') {
            $pageId = cms_post_int('page_id');
            $lookup = $pdo->prepare('SELECT title, slug, meta_description, body FROM pages WHERE id = ? LIMIT 1');
            $lookup->execute([$pageId]);
            $source = $lookup->fetch();
            if (!$source) {
                throw new RuntimeException('Choose a valid page to duplicate.');
            }
            $title = (string) $source['title'] . ' Copy';
            $slug = cms_unique_slug($pdo, (string) $source['slug'] . '-copy');
            $stmt = $pdo->prepare("INSERT INTO pages (title, slug, meta_description, body, status) VALUES (?, ?, ?, ?, 'draft')");
            $stmt->execute([$title, $slug, (string) $source['meta_description'], (string) $source['body']]);
            $newId = (int) $pdo->lastInsertId();
            cms_log_activity($pdo, (int) $user['id'], 'page duplicated', $newId, $slug);
            cms_flash('notice', 'Page duplicated as a draft.');
            cms_pages_redirect(['edit' => $newId]);
        }

        if ($action === 'delete_page') {
            $pageId = cms_post_int('page_id');
            $lookup = $pdo->prepare('SELECT slug FROM pages WHERE id = ? LIMIT 1');
            $lookup->execute([$pageId]);
            $slug = (string) ($lookup->fetchColumn() ?: '');
            if ($slug === '') {
                throw new RuntimeException('Choose a valid page to delete.');
            }
            $pdo->prepare('DELETE FROM pages WHERE id = ?')->execute([$pageId]);
            cms_log_activity($pdo, (int) $user['id'], 'page deleted', $pageId, $slug);
            cms_flash('notice', 'Page deleted.');
            cms_pages_redirect();
        }
    } catch (Throwable $error) {
        cms_flash('error', $error->getMessage());
        $editId = cms_post_int('page_id');
        cms_pages_redirect($editId > 0 ? ['edit' => $editId] : []);
    }
}

$notice = $_SESSION['pages_notice'] ?? null;
$error = $_SESSION['pages_error'] ?? null;
unset($_SESSION['pages_notice'], $_SESSION['pages_error']);

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
if (!in_array($statusFilter, ['', 'draft', 'published'], true)) {
    $statusFilter = '';
}
$editIdRaw = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT);
$editId = $editIdRaw === false ? 0 : max(0, (int) $editIdRaw);
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(title LIKE ? OR slug LIKE ? OR meta_description LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($statusFilter !== '') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare('SELECT * FROM pages' . $whereSql . ' ORDER BY updated_at DESC, id DESC');
$stmt->execute($params);
$pages = $stmt->fetchAll();

$editing = null;
if ($editId > 0) {
    $editStmt = $pdo->prepare('SELECT * FROM pages WHERE id = ? LIMIT 1');
    $editStmt->execute([$editId]);
    $editing = $editStmt->fetch() ?: null;
}

$counts = [
    'total' => (int) $pdo->query('SELECT COUNT(*) FROM pages')->fetchColumn(),
    'published' => (int) $pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'published'")->fetchColumn(),
    'draft' => (int) $pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'draft'")->fetchColumn(),
];

$editingBody = (string) ($editing['body'] ?? '');
$builder = cms_decode_builder($editingBody);
$editorMode = $builder ? 'builder' : ($editing ? 'legacy' : 'builder');
$builderBlocks = $builder ? cms_normalize_builder_blocks($builder) : cms_default_builder_blocks((string) ($editing['title'] ?? ''));
$builderState = ['mode' => $editorMode, 'blocks' => $builderBlocks, 'legacyBody' => $builder ? '' : $editingBody];
$builderStateJson = json_encode($builderState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?: '{}';
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Pages | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-automation">
    <link rel="stylesheet" href="/assets/page-builder.css?v=20260628-pages-builder">
    <script defer src="/assets/dashboard.js?v=20260621-automation"></script>
    <script defer src="/assets/page-builder.js?v=20260628-pages-builder"></script>
  </head>
  <body class="dashboard-body">
    <div class="dashboard-shell" data-dashboard-shell>
      <?php access_sidebar('pages', $roleLabel, $role); ?>
      <div class="sidebar-backdrop" data-sidebar-backdrop></div>
      <div class="dashboard-main">
        <header class="dashboard-topbar">
          <div class="topbar-left"><button class="mobile-menu" type="button" data-mobile-menu aria-controls="portal-sidebar" aria-expanded="false">☰</button><div><p class="eyebrow">Website CMS</p><h1 data-section-title>Pages</h1></div></div>
          <div class="topbar-actions"><span class="role-pill"><?= e($roleLabel) ?></span><div class="user-chip" title="<?= e($user['email']) ?>"><span><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($user['email']) ?></small></div></div><a class="logout-link" href="/logout.php">Log out</a></div>
        </header>
        <main class="dashboard-content standalone-admin page-manager">
          <header class="dashboard-hero compact-hero">
            <div><p class="eyebrow">Website CMS</p><h1>Pages</h1><p>Create, edit, preview, publish, unpublish, duplicate, and delete public CMS pages.</p></div>
            <div class="hero-actions"><a class="secondary-action" href="/dashboard.php">Dashboard</a><a class="primary-action" href="/pages.php">New page</a></div>
          </header>

          <?php if ($notice): ?><div class="dashboard-alert is-success" role="status"><?= e((string) $notice) ?></div><?php endif; ?>
          <?php if ($error): ?><div class="dashboard-alert is-error" role="alert"><?= e((string) $error) ?></div><?php endif; ?>

          <section class="section-summary-grid three-up" aria-label="Page summary">
            <article><span>Total pages</span><strong><?= e((string) $counts['total']) ?></strong></article>
            <article><span>Published</span><strong><?= e((string) $counts['published']) ?></strong></article>
            <article><span>Drafts</span><strong><?= e((string) $counts['draft']) ?></strong></article>
          </section>

          <form class="admin-panel filter-panel page-filter-panel" method="get" action="/pages.php">
            <label>Search pages<input name="search" type="search" value="<?= e($search) ?>" placeholder="Title, slug, or description"></label>
            <label>Status<select name="status"><option value="">All statuses</option><option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Published</option><option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option></select></label>
            <div class="filter-actions"><button class="button primary" type="submit">Apply filters</button><a class="secondary-action" href="/pages.php">Clear</a></div>
          </form>

          <section class="page-builder-layout" aria-label="Page editor">
            <form class="admin-panel page-builder-form" method="post" data-page-builder-form>
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="save_page">
              <input type="hidden" name="page_id" value="<?= e((string) ($editing['id'] ?? 0)) ?>">
              <input type="hidden" name="editor_mode" value="<?= e($editorMode) ?>" data-editor-mode>
              <input type="hidden" name="builder_json" data-builder-json>
              <script type="application/json" id="page-builder-state"><?= $builderStateJson ?></script>

              <div class="page-builder-meta">
                <div><p class="eyebrow">Page details</p><h2><?= $editing ? 'Edit page' : 'Create page' ?></h2></div>
                <div class="form-actions page-builder-actions"><button class="button primary" type="submit">Save</button><button class="button secondary" type="button" data-preview-page>Preview</button></div>
              </div>
              <div class="builder-error" data-builder-error role="alert" hidden></div>
              <div class="page-meta-grid">
                <label>Title<input name="title" data-page-title value="<?= e((string) ($editing['title'] ?? '')) ?>" required></label>
                <label>Slug<input name="slug" data-page-slug value="<?= e((string) ($editing['slug'] ?? '')) ?>" placeholder="about" required></label>
                <label>Status<select name="status"><option value="draft" <?= ($editing['status'] ?? '') !== 'published' ? 'selected' : '' ?>>Draft</option><option value="published" <?= ($editing['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option></select></label>
                <label class="page-meta-description">Meta description<textarea name="meta_description" rows="2"><?= e((string) ($editing['meta_description'] ?? '')) ?></textarea></label>
              </div>

              <div class="builder-mode-toggle" role="group" aria-label="Editor mode"><button type="button" data-mode-button="builder" class="is-active">Builder</button><button type="button" data-mode-button="legacy">Text fallback</button></div>

              <div class="builder-workbench" data-builder-workbench>
                <aside class="builder-panel block-library" aria-label="Available blocks"><h3>Blocks</h3><div class="block-library-list" data-block-library></div></aside>
                <section class="builder-canvas-panel" aria-label="Page structure"><div class="builder-panel-heading"><h3>Canvas</h3><span data-block-count>0 blocks</span></div><div class="builder-canvas" data-builder-canvas></div></section>
                <aside class="builder-panel block-inspector" aria-label="Block settings"><h3>Inspector</h3><div data-block-inspector><p class="empty-state">Select a block to edit settings.</p></div></aside>
              </div>

              <div class="legacy-editor" data-legacy-editor hidden><label>Body<textarea name="legacy_body" rows="14" data-legacy-body><?= e($builder ? '' : $editingBody) ?></textarea></label></div>
            </form>

            <aside class="admin-panel page-library">
              <div class="table-heading"><h2>Content library</h2><span><?= e((string) count($pages)) ?> result<?= count($pages) === 1 ? '' : 's' ?></span></div>
              <?php if (!$pages): ?><p class="empty-state">No pages match the current filters.</p><?php else: ?>
              <div class="page-list">
                <?php foreach ($pages as $row): ?>
                  <article class="page-list-item <?= $editing && (int) $editing['id'] === (int) $row['id'] ? 'is-active' : '' ?>">
                    <div><h3><?= e($row['title']) ?></h3><p><?= e(cms_page_excerpt((string) ($row['meta_description'] ?: $row['body']))) ?></p><span class="code-link"><?= e($row['slug']) ?></span></div>
                    <div class="page-list-actions"><span class="status-badge <?= $row['status'] === 'published' ? 'is-active' : 'is-muted' ?>"><?= e($row['status']) ?></span><a class="table-action" href="/pages.php?edit=<?= e((string) $row['id']) ?>">Edit</a><?php if ($row['status'] === 'published'): ?><a class="table-action" href="/page.php?slug=<?= e($row['slug']) ?>" target="_blank" rel="noopener">View</a><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="toggle_page"><input type="hidden" name="page_id" value="<?= e((string) $row['id']) ?>"><input type="hidden" name="status" value="<?= $row['status'] === 'published' ? 'draft' : 'published' ?>"><button class="table-action" type="submit"><?= $row['status'] === 'published' ? 'Unpublish' : 'Publish' ?></button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="duplicate_page"><input type="hidden" name="page_id" value="<?= e((string) $row['id']) ?>"><button class="table-action" type="submit">Duplicate</button></form><form method="post" data-confirm="Delete this page permanently?"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_page"><input type="hidden" name="page_id" value="<?= e((string) $row['id']) ?>"><button class="table-action danger" type="submit">Delete</button></form></div>
                  </article>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </aside>
          </section>

          <div class="builder-preview-modal" data-preview-modal hidden><button class="builder-preview-backdrop" type="button" data-preview-close aria-label="Close preview"></button><section class="builder-preview-dialog" role="dialog" aria-modal="true" aria-label="Page preview"><div class="builder-preview-header"><h2>Preview</h2><button type="button" data-preview-close aria-label="Close preview">Close</button></div><div class="builder-preview-body" data-preview-body></div></section></div>
        </main>
      </div>
    </div>
  </body>
</html>
