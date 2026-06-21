<?php
declare(strict_types=1);

function request_statuses(): array
{
    return ['new', 'in_review', 'waiting_on_client', 'in_progress', 'resolved', 'closed'];
}

function request_priorities(): array
{
    return ['low', 'normal', 'high', 'urgent'];
}

function request_status(string $value): string
{
    return in_array($value, request_statuses(), true) ? $value : 'new';
}

function request_priority(string $value): string
{
    return in_array($value, request_priorities(), true) ? $value : 'normal';
}

function request_label(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

function request_excerpt(string $value, int $length = 120): string
{
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
    if (strlen($plain) <= $length) {
        return $plain;
    }

    return rtrim(substr($plain, 0, $length - 3)) . '...';
}

function request_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        assigned_to INT UNSIGNED NULL,
        title VARCHAR(190) NOT NULL,
        description TEXT NOT NULL,
        status ENUM('new','in_review','waiting_on_client','in_progress','resolved','closed') NOT NULL DEFAULT 'new',
        priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
        internal_notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_client_requests_user (user_id),
        INDEX idx_client_requests_assigned (assigned_to),
        INDEX idx_client_requests_status (status),
        INDEX idx_client_requests_priority (priority),
        INDEX idx_client_requests_updated (updated_at),
        CONSTRAINT fk_client_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_client_requests_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function request_log_activity(PDO $pdo, int $actorId, string $action, ?int $targetId = null, string $details = ''): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, 'request', $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $error) {
        error_log('Request activity log skipped: ' . $error->getMessage());
    }
}

function request_can_manage_all(string $role): bool
{
    return in_array(strtolower($role), ['admin', 'editor', 'support'], true);
}

function request_manageable_users(PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT id, email, full_name, role FROM users WHERE is_active = 1 AND role IN ('admin', 'editor', 'support') ORDER BY full_name ASC, email ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}
