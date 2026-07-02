<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/carrier.php';

$user = require_login();
$role = strtolower((string) ($user['role'] ?? 'client'));
$openedId = filter_var($_GET['open'] ?? 0, FILTER_VALIDATE_INT) !== false ? (int) ($_GET['open'] ?? 0) : 0;
$startedInUnread = (string) ($_GET['folder'] ?? '') === 'unread';

if ($openedId > 0 && in_array($role, ['admin', 'editor'], true)) {
    try {
        $pdo = db();
        carrier_ensure_schema($pdo);
        if (carrier_schema_ready($pdo)) {
            $stmt = $pdo->prepare('UPDATE carrier_emails SET is_read = 1, updated_by = ?, updated_at = NOW() WHERE id = ? AND is_read = 0');
            $stmt->execute([(int) $user['id'], $openedId]);
        }
    } catch (Throwable $error) {
        error_log('Carrier auto-read test page skipped read update: ' . $error->getMessage());
    }

    if ($startedInUnread) {
        $params = $_GET;
        $params['folder'] = 'inbox';
        $params['open'] = $openedId;
        redirect_to('/carrier-read.php?' . http_build_query($params) . '#carrier-preview');
    }
}

ob_start();
require __DIR__ . '/carrier.php';
$html = ob_get_clean();

$html = str_replace(
    ['href="/carrier?', 'href="/carrier#', 'href="/carrier"', 'action="/carrier"', '<title>Carrier | Oligarchy Services</title>'],
    ['href="/carrier-read.php?', 'href="/carrier-read.php#', 'href="/carrier-read.php"', 'action="/carrier-read.php"', '<title>Carrier Auto Read Test | Oligarchy Services</title>'],
    $html
);
$html = str_replace(
    '<h1 data-section-title>Carrier</h1>',
    '<h1 data-section-title>Carrier Auto Read Test</h1>',
    $html
);
$html = str_replace(
    '<main class="carrier-workspace">',
    '<main class="carrier-workspace"><div class="dashboard-alert is-success" role="status">Test page: opening an unread Carrier email marks it read, updates the Unread count, and returns you to Inbox so the opened message stays visible. Inbox count is the total non-archived mail count.</div>',
    $html
);

echo $html;
