<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/prospects.php';

header('Content-Type: application/json; charset=utf-8');

function prospect_status_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

try {
    $user = require_login();
    $role = strtolower((string) ($user['role'] ?? 'client'));
    if (!in_array($role, ['admin', 'editor'], true)) {
        prospect_status_response(403, ['ok' => false, 'message' => 'Only admins and editors can update prospect status.']);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        prospect_status_response(405, ['ok' => false, 'message' => 'Use POST to update prospect status.']);
    }

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        prospect_status_response(419, ['ok' => false, 'message' => 'Your session expired. Please refresh and try again.']);
    }

    $prospectId = filter_var($_POST['prospect_id'] ?? null, FILTER_VALIDATE_INT);
    if ($prospectId === false || (int) $prospectId <= 0) {
        prospect_status_response(422, ['ok' => false, 'message' => 'Choose a valid prospect.']);
    }

    $status = prospect_status(trim((string) ($_POST['status'] ?? '')));
    if (!in_array($status, prospect_statuses(), true)) {
        prospect_status_response(422, ['ok' => false, 'message' => 'Choose a valid prospect status.']);
    }

    $pdo = db();
    prospect_ensure_schema($pdo);
    if (!prospect_schema_ready($pdo)) {
        prospect_status_response(503, ['ok' => false, 'message' => 'Prospects database tables are not ready.']);
    }

    $prospect = prospect_fetch($pdo, (int) $prospectId);
    if (!$prospect) {
        prospect_status_response(404, ['ok' => false, 'message' => 'Prospect not found.']);
    }

    if ((string) $prospect['status'] !== $status) {
        $stmt = $pdo->prepare('UPDATE prospects SET status = ?, updated_by = ?, last_activity = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, (int) $user['id'], 'Status changed to ' . $status, (int) $prospectId]);
        prospect_log_activity($pdo, (int) $user['id'], 'prospect status updated', (int) $prospectId, (string) $prospect['status'] . ' -> ' . $status);
    }

    prospect_status_response(200, ['ok' => true, 'id' => (int) $prospectId, 'status' => $status]);
} catch (Throwable $error) {
    error_log('Prospect status update failed: ' . $error->getMessage());
    prospect_status_response(500, ['ok' => false, 'message' => 'Could not update prospect status.']);
}
