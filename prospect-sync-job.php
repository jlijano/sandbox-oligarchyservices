<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/prospects.php';
require_once __DIR__ . '/includes/prospect-sheet-sync.php';

header('Content-Type: application/json; charset=utf-8');

$token = trim((string) getenv('PROSPECTS_SYNC_JOB_TOKEN'));
$provided = trim((string) ($_GET['token'] ?? ($_SERVER['HTTP_X_SYNC_TOKEN'] ?? '')));

if ($token === '') {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'Prospect sync job is not configured. Set PROSPECTS_SYNC_JOB_TOKEN on the server.',
    ]);
    exit;
}

if (!hash_equals($token, $provided)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid sync token.',
    ]);
    exit;
}

try {
    $pdo = db();
    prospect_ensure_schema($pdo);
    if (!prospect_schema_ready($pdo)) {
        throw new RuntimeException('Prospects database tables are not ready. Check the database config, then run /update.php if needed.');
    }

    $actorId = prospect_sheet_sync_actor_id($pdo);
    $summary = prospect_sheet_sync($pdo, $actorId);
    prospect_log_activity($pdo, $actorId, 'prospects scheduled sheet sync', null, prospect_sheet_sync_message($summary));

    $hasErrors = !empty($summary['errors']);
    http_response_code($hasErrors ? 207 : 200);
    echo json_encode([
        'ok' => !$hasErrors,
        'message' => prospect_sheet_sync_message($summary),
        'summary' => $summary,
    ]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Prospect scheduled sync failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $error->getMessage(),
    ]);
}
