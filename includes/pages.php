<?php
declare(strict_types=1);

function cms_slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
    return trim($slug, '-');
}

function cms_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $error) {
        return false;
    }
}

function cms_post_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function cms_post_int(string $key, int $default = 0): int
{
    return filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT) !== false ? (int) $_POST[$key] : $default;
}

function cms_flash(string $type, string $message): void
{
    $_SESSION['pages_' . $type] = $message;
}

function cms_pages_redirect(array $params = []): void
{
    redirect_to('/pages.php' . ($params ? '?' . http_build_query($params) : ''));
}

function cms_require_editor(array $user): void
{
    $role = strtolower((string) ($user['role'] ?? 'client'));
    if (!in_array($role, ['admin', 'editor'], true)) {
        http_response_code(403);
        echo 'Only admins and editors can manage pages.';
        exit;
    }
}

function cms_log_activity(PDO $pdo, int $actorId, string $action, ?int $targetId = null, string $details = ''): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, 'page', $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $error) {
        error_log('Page activity log skipped: ' . $error->getMessage());
    }
}

function cms_page_excerpt(string $value, int $length = 96): string
{
    $plain = cms_page_plain_text($value);
    if (strlen($plain) <= $length) {
        return $plain;
    }
    return substr($plain, 0, $length - 3) . '...';
}

function cms_decode_builder(string $body): ?array
{
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return null;
    }
    if (($decoded['type'] ?? '') !== 'oligarchy-page-builder' || !isset($decoded['blocks']) || !is_array($decoded['blocks'])) {
        return null;
    }
    return $decoded;
}

function cms_page_is_builder(string $body): bool
{
    return cms_decode_builder($body) !== null;
}

function cms_allowed_block_types(): array
{
    return ['hero', 'text', 'two-column', 'image', 'cta', 'feature-grid', 'faq', 'button', 'spacer'];
}

function cms_clean_text($value, int $limit = 4000): string
{
    $text = trim((string) $value);
    if (strlen($text) > $limit) {
        return substr($text, 0, $limit);
    }
    return $text;
}

function cms_clean_url($value): string
{
    $url = trim((string) $value);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^(https?://|/|mailto:)#i', $url)) {
        return $url;
    }
    return '';
}

function cms_normalize_builder_blocks($raw): array
{
    $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
    if (is_array($decoded) && isset($decoded['blocks']) && is_array($decoded['blocks'])) {
        $decoded = $decoded['blocks'];
    }
    if (!is_array($decoded)) {
        return [];
    }

    $allowed = cms_allowed_block_types();
    $blocks = [];
    foreach ($decoded as $block) {
        if (!is_array($block)) {
            continue;
        }
        $type = (string) ($block['type'] ?? '');
        if (!in_array($type, $allowed, true)) {
            continue;
        }
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $blocks[] = [
            'id' => preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($block['id'] ?? uniqid('block_', false))) ?: uniqid('block_', false),
            'type' => $type,
            'data' => cms_normalize_block_data($type, $data),
        ];
    }
    return $blocks;
}

function cms_normalize_block_data(string $type, array $data): array
{
    if ($type === 'hero') {
        return ['eyebrow' => cms_clean_text($data['eyebrow'] ?? '', 120), 'heading' => cms_clean_text($data['heading'] ?? '', 220), 'body' => cms_clean_text($data['body'] ?? '', 700), 'buttonLabel' => cms_clean_text($data['buttonLabel'] ?? '', 80), 'buttonUrl' => cms_clean_url($data['buttonUrl'] ?? '')];
    }
    if ($type === 'text') {
        return ['heading' => cms_clean_text($data['heading'] ?? '', 220), 'body' => cms_clean_text($data['body'] ?? '', 4000)];
    }
    if ($type === 'two-column') {
        return ['leftHeading' => cms_clean_text($data['leftHeading'] ?? '', 180), 'leftBody' => cms_clean_text($data['leftBody'] ?? '', 1800), 'rightHeading' => cms_clean_text($data['rightHeading'] ?? '', 180), 'rightBody' => cms_clean_text($data['rightBody'] ?? '', 1800)];
    }
    if ($type === 'image') {
        return ['src' => cms_clean_url($data['src'] ?? ''), 'alt' => cms_clean_text($data['alt'] ?? '', 160), 'caption' => cms_clean_text($data['caption'] ?? '', 260)];
    }
    if ($type === 'cta') {
        return ['heading' => cms_clean_text($data['heading'] ?? '', 220), 'body' => cms_clean_text($data['body'] ?? '', 700), 'buttonLabel' => cms_clean_text($data['buttonLabel'] ?? '', 80), 'buttonUrl' => cms_clean_url($data['buttonUrl'] ?? '')];
    }
    if ($type === 'feature-grid') {
        return ['heading' => cms_clean_text($data['heading'] ?? '', 220), 'items' => cms_clean_items($data['items'] ?? [])];
    }
    if ($type === 'faq') {
        return ['heading' => cms_clean_text($data['heading'] ?? '', 220), 'items' => cms_clean_items($data['items'] ?? [])];
    }
    if ($type === 'button') {
        return ['label' => cms_clean_text($data['label'] ?? '', 80), 'url' => cms_clean_url($data['url'] ?? '')];
    }
    if ($type === 'spacer') {
        $size = (string) ($data['size'] ?? 'medium');
        return ['size' => in_array($size, ['small', 'medium', 'large'], true) ? $size : 'medium'];
    }
    return [];
}

function cms_clean_items($items): array
{
    if (!is_array($items)) {
        return [];
    }
    $clean = [];
    foreach (array_slice($items, 0, 12) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = cms_clean_text($item['title'] ?? $item['question'] ?? '', 180);
        $body = cms_clean_text($item['body'] ?? $item['answer'] ?? '', 900);
        if ($title === '' && $body === '') {
            continue;
        }
        $clean[] = ['title' => $title, 'body' => $body];
    }
    return $clean;
}

function cms_encode_builder(array $blocks): string
{
    return json_encode(['type' => 'oligarchy-page-builder', 'version' => 1, 'blocks' => cms_normalize_builder_blocks($blocks)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
}

function cms_default_builder_blocks(string $title = ''): array
{
    return [[
        'id' => 'block_' . bin2hex(random_bytes(4)),
        'type' => 'hero',
        'data' => ['eyebrow' => 'Oligarchy Services', 'heading' => $title !== '' ? $title : 'New page', 'body' => '', 'buttonLabel' => '', 'buttonUrl' => ''],
    ]];
}

function cms_page_plain_text(string $body): string
{
    $builder = cms_decode_builder($body);
    if (!$builder) {
        return trim(strip_tags($body));
    }
    $parts = [];
    foreach ($builder['blocks'] as $block) {
        foreach (($block['data'] ?? []) as $value) {
            if (is_string($value)) {
                $parts[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $parts[] = implode(' ', array_map('strval', $item));
                    }
                }
            }
        }
    }
    return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)) ?: '');
}

function cms_render_paragraphs(string $text): string
{
    $paragraphs = preg_split('/\n{2,}/', trim($text)) ?: [];
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph !== '') {
            $html .= '<p>' . nl2br(e($paragraph)) . '</p>';
        }
    }
    return $html;
}

function cms_render_builder(string $body): string
{
    $builder = cms_decode_builder($body);
    if (!$builder) {
        return '<div class="cms-content">' . nl2br(e($body)) . '</div>';
    }
    $html = '<div class="cms-builder-content">';
    foreach ($builder['blocks'] as $block) {
        $html .= cms_render_block((string) ($block['type'] ?? ''), is_array($block['data'] ?? null) ? $block['data'] : []);
    }
    return $html . '</div>';
}

function cms_render_block(string $type, array $data): string
{
    if ($type === 'hero') {
        $button = ($data['buttonLabel'] ?? '') !== '' && ($data['buttonUrl'] ?? '') !== '' ? '<a class="button primary" href="' . e($data['buttonUrl']) . '">' . e($data['buttonLabel']) . '</a>' : '';
        return '<section class="page-hero cms-block-hero">' . (($data['eyebrow'] ?? '') !== '' ? '<p class="eyebrow">' . e($data['eyebrow']) . '</p>' : '') . '<h1>' . e($data['heading'] ?? '') . '</h1>' . (($data['body'] ?? '') !== '' ? '<p>' . e($data['body']) . '</p>' : '') . $button . '</section>';
    }
    if ($type === 'text') {
        return '<section class="section cms-block cms-block-text">' . (($data['heading'] ?? '') !== '' ? '<h2>' . e($data['heading']) . '</h2>' : '') . cms_render_paragraphs((string) ($data['body'] ?? '')) . '</section>';
    }
    if ($type === 'two-column') {
        return '<section class="section two-column cms-block cms-block-two-column"><div>' . (($data['leftHeading'] ?? '') !== '' ? '<h2>' . e($data['leftHeading']) . '</h2>' : '') . cms_render_paragraphs((string) ($data['leftBody'] ?? '')) . '</div><div>' . (($data['rightHeading'] ?? '') !== '' ? '<h2>' . e($data['rightHeading']) . '</h2>' : '') . cms_render_paragraphs((string) ($data['rightBody'] ?? '')) . '</div></section>';
    }
    if ($type === 'image') {
        if (($data['src'] ?? '') === '') {
            return '';
        }
        return '<section class="section cms-block cms-block-image"><figure><img src="' . e($data['src']) . '" alt="' . e($data['alt'] ?? '') . '">' . (($data['caption'] ?? '') !== '' ? '<figcaption>' . e($data['caption']) . '</figcaption>' : '') . '</figure></section>';
    }
    if ($type === 'cta') {
        $button = ($data['buttonLabel'] ?? '') !== '' && ($data['buttonUrl'] ?? '') !== '' ? '<a class="button primary" href="' . e($data['buttonUrl']) . '">' . e($data['buttonLabel']) . '</a>' : '';
        return '<section class="section cta-band cms-block cms-block-cta"><h2>' . e($data['heading'] ?? '') . '</h2>' . (($data['body'] ?? '') !== '' ? '<p>' . e($data['body']) . '</p>' : '') . $button . '</section>';
    }
    if ($type === 'feature-grid') {
        $items = '';
        foreach (($data['items'] ?? []) as $item) {
            $items .= '<article><h3>' . e($item['title'] ?? '') . '</h3><p>' . e($item['body'] ?? '') . '</p></article>';
        }
        return '<section class="section cms-block cms-block-feature-grid">' . (($data['heading'] ?? '') !== '' ? '<div class="section-heading"><h2>' . e($data['heading']) . '</h2></div>' : '') . '<div class="detail-grid">' . $items . '</div></section>';
    }
    if ($type === 'faq') {
        $items = '';
        foreach (($data['items'] ?? []) as $item) {
            $items .= '<details><summary>' . e($item['title'] ?? '') . '</summary><p>' . e($item['body'] ?? '') . '</p></details>';
        }
        return '<section class="section cms-block cms-block-faq">' . (($data['heading'] ?? '') !== '' ? '<h2>' . e($data['heading']) . '</h2>' : '') . '<div class="faq-list">' . $items . '</div></section>';
    }
    if ($type === 'button') {
        if (($data['label'] ?? '') === '' || ($data['url'] ?? '') === '') {
            return '';
        }
        return '<section class="section cms-block cms-block-button"><a class="button primary" href="' . e($data['url']) . '">' . e($data['label']) . '</a></section>';
    }
    if ($type === 'spacer') {
        $size = e((string) ($data['size'] ?? 'medium'));
        return '<div class="cms-spacer cms-spacer-' . $size . '" aria-hidden="true"></div>';
    }
    return '';
}
