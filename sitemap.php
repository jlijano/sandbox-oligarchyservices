<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/blogs.php';

$staticPaths = [
    '/',
    '/itad.html',
    '/itam.html',
    '/help-desk.html',
    '/msp.html',
    '/business-systems.html',
    '/ai-automation.html',
    '/projects.html',
    '/about.html',
    '/career-timeline.html',
    '/blogs.php',
    '/contact.html',
    '/privacy.html',
];

function sitemap_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function sitemap_lastmod(?string $value): string
{
    if (!$value) {
        return gmdate('Y-m-d');
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? gmdate('Y-m-d') : gmdate('Y-m-d', $timestamp);
}

function sitemap_entry(string $url, ?string $lastmod = null): string
{
    $xml = '  <url><loc>' . sitemap_xml_escape($url) . '</loc>';
    if ($lastmod !== null) {
        $xml .= '<lastmod>' . sitemap_xml_escape(sitemap_lastmod($lastmod)) . '</lastmod>';
    }
    return $xml . '</url>';
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=900');

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ($staticPaths as $path) {
    echo sitemap_entry(blog_canonical_url($path)) . "\n";
}

try {
    $pdo = db();
    foreach (blog_fetch_published($pdo, 24) as $post) {
        $item = blog_public_fields($post);
        echo sitemap_entry(blog_canonical_url($item['url']), (string) ($post['updated_at'] ?? $post['published_at'] ?? '')) . "\n";
    }
} catch (Throwable $error) {
    error_log('Dynamic sitemap blog URL error: ' . $error->getMessage());
}

echo "</urlset>\n";
