<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/blogs.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    blog_ensure_schema($pdo);
    $slug = blog_slugify((string) ($_GET['slug'] ?? ''));

    if ($slug !== '') {
        $post = blog_fetch_published_by_slug($pdo, $slug);
        if (!$post) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Blog post not found.']);
            exit;
        }

        echo json_encode(['ok' => true, 'post' => array_merge(blog_public_fields($post), [
            'content' => (string) $post['body'],
            'seoTitle' => (string) ($post['seo_title'] ?? ''),
            'seoDescription' => (string) ($post['seo_description'] ?? ''),
        ])]);
        exit;
    }

    $limit = filter_var($_GET['limit'] ?? 6, FILTER_VALIDATE_INT);
    $rows = blog_fetch_published($pdo, $limit === false ? 6 : (int) $limit);
    echo json_encode(['ok' => true, 'posts' => array_map('blog_public_fields', $rows)]);
} catch (Throwable $error) {
    error_log('Public blogs API error: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Blog posts are temporarily unavailable.']);
}
