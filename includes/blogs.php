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

function blog_upload_public_path(string $relativePath): string
{
    return '/' . ltrim($relativePath, '/');
}

function blog_upload_directory(): string
{
    return app_base_path('uploads/blog');
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
        CONSTRAINT fk_blogs_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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
    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'slug' => (string) $row['slug'],
        'excerpt' => (string) ($row['excerpt'] ?: blog_excerpt((string) ($row['body'] ?? ''))),
        'featuredImage' => (string) ($row['featured_image'] ?? ''),
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

function blog_fetch_published(PDO $pdo, int $limit = 9): array
{
    $limit = max(1, min(24, $limit));
    $stmt = $pdo->prepare("SELECT b.*, u.full_name AS author_name FROM blogs b LEFT JOIN users u ON u.id = b.author_id WHERE b.status = 'published' ORDER BY COALESCE(b.published_at, b.created_at) DESC, b.id DESC LIMIT " . $limit);
    $stmt->execute();
    return $stmt->fetchAll();
}

function blog_fetch_published_by_slug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare("SELECT b.*, u.full_name AS author_name FROM blogs b LEFT JOIN users u ON u.id = b.author_id WHERE b.slug = ? AND b.status = 'published' LIMIT 1");
    $stmt->execute([blog_slugify($slug)]);
    $row = $stmt->fetch();
    return $row ?: null;
}
