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

function request_sql_name(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }

    return '`' . $name . '`';
}

function request_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function request_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!request_column_exists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . request_sql_name($table) . ' ADD COLUMN ' . request_sql_name($column) . ' ' . $definition);
    }
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS client_request_updates (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NULL,
        body TEXT NOT NULL,
        is_internal TINYINT(1) NOT NULL DEFAULT 0,
        status VARCHAR(40) NOT NULL DEFAULT '',
        priority VARCHAR(40) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_request_updates_request (request_id, created_at),
        INDEX idx_request_updates_user (user_id),
        INDEX idx_request_updates_internal (is_internal),
        CONSTRAINT fk_request_updates_request FOREIGN KEY (request_id) REFERENCES client_requests(id) ON DELETE CASCADE,
        CONSTRAINT fk_request_updates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    request_add_column_if_missing($pdo, 'client_request_updates', 'status', "VARCHAR(40) NOT NULL DEFAULT ''");
    request_add_column_if_missing($pdo, 'client_request_updates', 'priority', "VARCHAR(40) NOT NULL DEFAULT ''");
}

function request_add_update(PDO $pdo, int $requestId, ?int $userId, string $body, bool $isInternal, string $status = '', string $priority = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO client_request_updates (request_id, user_id, body, is_internal, status, priority) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$requestId, $userId, $body, $isInternal ? 1 : 0, $status, $priority]);
}

function request_fetch_updates(PDO $pdo, array $requestIds, bool $includeInternal): array
{
    $ids = array_values(array_unique(array_map('intval', $requestIds)));
    $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = 'SELECT u.*, actor.email AS actor_email, actor.full_name AS actor_name FROM client_request_updates u LEFT JOIN users actor ON actor.id = u.user_id WHERE u.request_id IN (' . $placeholders . ')';
    $params = $ids;
    if (!$includeInternal) {
        $sql .= ' AND u.is_internal = ?';
        $params[] = 0;
    }
    $sql .= ' ORDER BY u.created_at DESC, u.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $grouped[(int) $row['request_id']][] = $row;
    }

    return $grouped;
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
