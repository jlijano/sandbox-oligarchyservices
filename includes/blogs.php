<?php
declare(strict_types=1);

function blog_slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
    return trim($slug, '-');
}

function blog_status(string $value): string
{
    return $value === 'published' ? 'published' : 'draft';
}

function blog_excerpt(string $value, int $length = 150): string
{
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
    if (strlen($plain) <= $length) {
        return $plain;
    }
    return rtrim(substr($plain, 0, $length - 3)) . '...';
}

function blog_sql_name(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }

    return '`' . $name . '`';
}

function blog_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function blog_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function blog_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!blog_column_exists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . blog_sql_name($table) . ' ADD COLUMN ' . blog_sql_name($column) . ' ' . $definition);
    }
}

function blog_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function blog_add_index_if_missing(PDO $pdo, string $table, string $index, string $columns): void
{
    if (!blog_index_exists($pdo, $table, $index)) {
        $pdo->exec('ALTER TABLE ' . blog_sql_name($table) . ' ADD INDEX ' . blog_sql_name($index) . ' (' . $columns . ')');
    }
}

function blog_upload_public_path(string $relativePath): string
{
    return '/' . ltrim($relativePath, '/');
}

function blog_upload_directory(): string
{
    return app_base_path('uploads/blog');
}

function blog_write_upload_htaccess(string $directory): void
{
    $path = rtrim($directory, '/') . '/.htaccess';
    if (is_file($path)) {
        return;
    }

    $rules = "Options -ExecCGI\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .phps\nRemoveType .php .phtml .php3 .php4 .php5 .php7 .phps\n<FilesMatch \"\\.(php|phtml|php[0-9]?|phps)$\">\n  Require all denied\n</FilesMatch>\n";
    @file_put_contents($path, $rules, LOCK_EX);
    @chmod($path, 0644);
}

function blog_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS blogs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(190) NOT NULL,
        slug VARCHAR(190) NOT NULL UNIQUE,
        excerpt VARCHAR(500) NOT NULL DEFAULT '',
        body MEDIUMTEXT NOT NULL,
        featured_image VARCHAR(255) NOT NULL DEFAULT '',
        featured_image_alt VARCHAR(190) NOT NULL DEFAULT '',
        status ENUM('draft','published') NOT NULL DEFAULT 'draft',
        author_id INT UNSIGNED NULL,
        category VARCHAR(120) NOT NULL DEFAULT '',
        published_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        seo_title VARCHAR(190) NOT NULL DEFAULT '',
        seo_description VARCHAR(255) NOT NULL DEFAULT '',
        social_share_title VARCHAR(190) NOT NULL DEFAULT '',
        social_share_description VARCHAR(255) NOT NULL DEFAULT '',
        INDEX idx_blogs_status (status),
        INDEX idx_blogs_published_at (published_at),
        INDEX idx_blogs_slug_status (slug, status),
        CONSTRAINT fk_blogs_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!blog_table_exists($pdo, 'blogs')) {
        return;
    }

    blog_add_column_if_missing($pdo, 'blogs', 'title', "VARCHAR(190) NOT NULL DEFAULT ''");
    blog_add_column_if_missing($pdo, 'blogs', 'slug', "VARCHAR(190) NOT NULL DEFAULT ''");
    blog_add_column_if_missing($pdo, 'blogs', 'excerpt', "VARCHAR(500) NOT NULL DEFAULT ''");
    blog_add_column_if_missing($pdo, 'blogs', 'body', 'MEDIUMTEXT NULL');
    blog_add_column_if_missing($pdo, 'blogs', 'featured_image', "VARCHAR(255) NOT NULL DEFAULT ''");
    blog_add_column_if_missing($pdo, 'blogs', 'featured_image_alt', "VARCHAR(190) NOT NULL DEFAULT ''");
    blog_add_column_if_missing($pdo, 'blogs', 'status', "ENUM('draft','published') NOT NULL DEFAULT 'draft'");
    blog_add_column_if_missing($pdo, 'blogs', 'author_id', 'INT UNSIGNED NULL');
    blog_add_column_if_missing($pdo, 'blogs', 'category', "VARCHAR(120) NOT NULL DEFAULT ''");
    blog_add_column_if_missing($pdo, 'blogs', 'published_at', 'DATETIME NULL');
    blog_add_column_if_missing($pdo, 'blogs', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    blog_add_column_if_missing($pdo, 'blogs', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    blog_add_column_if_missing($pdo, 'blogs', 'seo_title', "VARCHAR(190) NOT NULL DEFAULT ''");
    blog_add_column_if_missing($pdo, 'blogs', 'seo_description', "VARCHAR(255) NOT NULL DEFAULT ''");
    blog_add_column_if_missing($pdo, 'blogs', 'social_share_title', "VARCHAR(190) NOT NULL DEFAULT ''");
    blog_add_column_if_missing($pdo, 'blogs', 'social_share_description', "VARCHAR(255) NOT NULL DEFAULT ''");
    blog_add_index_if_missing($pdo, 'blogs', 'idx_blogs_status', '`status`');
    blog_add_index_if_missing($pdo, 'blogs', 'idx_blogs_published_at', '`published_at`');
    blog_add_index_if_missing($pdo, 'blogs', 'idx_blogs_slug_status', '`slug`, `status`');
}

function blog_store_uploaded_image(array $file, string $slug): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The featured image could not be uploaded. Try again with a smaller image.');
    }

    if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Featured images must be 2 MB or smaller.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('The featured image upload was not valid.');
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Featured images must be JPG, PNG, or WebP files.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpName);
    $allowedMimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    if (($allowedMimeTypes[$extension] ?? '') !== $mime) {
        throw new RuntimeException('The featured image file type does not match its extension.');
    }

    $directory = blog_upload_directory();
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Could not create the blog image upload directory.');
    }
    blog_write_upload_htaccess($directory);

    $safeSlug = blog_slugify($slug) ?: 'blog';
    $filename = $safeSlug . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $directory . '/' . $filename;
    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('Could not save the featured image.');
    }

    @chmod($targetPath, 0644);
    return '/uploads/blog/' . $filename;
}

function blog_public_fields(array $row): array
{
    $published = (string) ($row['published_at'] ?? $row['created_at'] ?? '');
    $title = (string) ($row['title'] ?? '');
    $imageAlt = trim((string) ($row['featured_image_alt'] ?? ''));

    return [
        'id' => (int) $row['id'],
        'title' => $title,
        'slug' => (string) $row['slug'],
        'excerpt' => (string) ($row['excerpt'] ?: blog_excerpt((string) ($row['body'] ?? ''))),
        'featuredImage' => (string) ($row['featured_image'] ?? ''),
        'featuredImageAlt' => $imageAlt !== '' ? $imageAlt : $title,
        'category' => (string) ($row['category'] ?? ''),
        'author' => (string) ($row['author_name'] ?? ''),
        'publishedAt' => $published,
        'url' => '/blog.php?slug=' . rawurlencode((string) $row['slug']),
    ];
}

function blog_current_url(): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'sandbox.oligarchyservices.com';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $uri;
}

function blog_canonical_url(string $path): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'sandbox.oligarchyservices.com';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function blog_fetch_published_count(PDO $pdo): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blogs WHERE status = ?");
    $stmt->execute(['published']);
    return (int) $stmt->fetchColumn();
}

function blog_fetch_published(PDO $pdo, int $limit = 9): array
{
    $limit = max(1, min(24, $limit));
    $stmt = $pdo->prepare("SELECT b.*, u.full_name AS author_name FROM blogs b LEFT JOIN users u ON u.id = b.author_id WHERE b.status = ? ORDER BY COALESCE(b.published_at, b.created_at) DESC, b.id DESC LIMIT " . $limit);
    $stmt->execute(['published']);
    return $stmt->fetchAll();
}

function blog_fetch_published_by_slug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare("SELECT b.*, u.full_name AS author_name FROM blogs b LEFT JOIN users u ON u.id = b.author_id WHERE b.slug = ? AND b.status = ? LIMIT 1");
    $stmt->execute([blog_slugify($slug), 'published']);
    $row = $stmt->fetch();
    return $row ?: null;
}
