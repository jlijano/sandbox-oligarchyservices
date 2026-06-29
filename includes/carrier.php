<?php
declare(strict_types=1);

function carrier_statuses(): array { return ['New', 'Contacted', 'Pending', 'Approved', 'Rejected', 'Archived']; }
function carrier_priorities(): array { return ['High', 'Normal', 'Low']; }
function carrier_status(string $value): string { return in_array($value, carrier_statuses(), true) ? $value : 'New'; }
function carrier_priority(string $value): string { return in_array($value, carrier_priorities(), true) ? $value : 'Normal'; }

function carrier_sql_name(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) throw new InvalidArgumentException('Invalid SQL identifier.');
    return '`' . $name . '`';
}

function carrier_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function carrier_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function carrier_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!carrier_column_exists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . carrier_sql_name($table) . ' ADD COLUMN ' . carrier_sql_name($column) . ' ' . $definition);
    }
}

function carrier_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function carrier_add_index_if_missing(PDO $pdo, string $table, string $index, string $columns): void
{
    if (!carrier_index_exists($pdo, $table, $index)) {
        $pdo->exec('ALTER TABLE ' . carrier_sql_name($table) . ' ADD INDEX ' . carrier_sql_name($index) . ' (' . $columns . ')');
    }
}

function carrier_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS carrier_emails (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        carrier_name VARCHAR(190) NOT NULL,
        carrier_email VARCHAR(190) NOT NULL DEFAULT '',
        subject VARCHAR(255) NOT NULL,
        message MEDIUMTEXT NOT NULL,
        preview_text VARCHAR(320) NOT NULL DEFAULT '',
        status ENUM('New','Contacted','Pending','Approved','Rejected','Archived') NOT NULL DEFAULT 'New',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        is_starred TINYINT(1) NOT NULL DEFAULT 0,
        priority ENUM('High','Normal','Low') NOT NULL DEFAULT 'Normal',
        attachments TEXT NULL,
        received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by INT UNSIGNED NULL,
        updated_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_carrier_email (carrier_email),
        INDEX idx_carrier_status (status),
        INDEX idx_carrier_is_read (is_read),
        INDEX idx_carrier_received_at (received_at),
        INDEX idx_carrier_starred (is_starred),
        CONSTRAINT fk_carrier_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_carrier_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    carrier_add_column_if_missing($pdo, 'carrier_emails', 'carrier_name', "VARCHAR(190) NOT NULL DEFAULT ''");
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'carrier_email', "VARCHAR(190) NOT NULL DEFAULT ''");
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'subject', "VARCHAR(255) NOT NULL DEFAULT ''");
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'message', 'MEDIUMTEXT NULL');
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'preview_text', "VARCHAR(320) NOT NULL DEFAULT ''");
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'status', "ENUM('New','Contacted','Pending','Approved','Rejected','Archived') NOT NULL DEFAULT 'New'");
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'is_read', 'TINYINT(1) NOT NULL DEFAULT 0');
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'is_starred', 'TINYINT(1) NOT NULL DEFAULT 0');
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'priority', "ENUM('High','Normal','Low') NOT NULL DEFAULT 'Normal'");
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'attachments', 'TEXT NULL');
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'received_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'created_by', 'INT UNSIGNED NULL');
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'updated_by', 'INT UNSIGNED NULL');
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    carrier_add_index_if_missing($pdo, 'carrier_emails', 'idx_carrier_email', '`carrier_email`');
    carrier_add_index_if_missing($pdo, 'carrier_emails', 'idx_carrier_status', '`status`');
    carrier_add_index_if_missing($pdo, 'carrier_emails', 'idx_carrier_is_read', '`is_read`');
    carrier_add_index_if_missing($pdo, 'carrier_emails', 'idx_carrier_received_at', '`received_at`');
    carrier_add_index_if_missing($pdo, 'carrier_emails', 'idx_carrier_starred', '`is_starred`');
}

function carrier_schema_ready(PDO $pdo): bool { return carrier_table_exists($pdo, 'carrier_emails'); }

function carrier_clean_text(string $key, int $maxLength, string $default = ''): string
{
    $value = trim((string) ($_POST[$key] ?? $default));
    $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $value) ?? '';
    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
}

function carrier_clean_datetime(string $key): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') return gmdate('Y-m-d H:i:s');
    $normalized = str_replace('T', ' ', $value);
    if (strlen($normalized) === 16) $normalized .= ':00';
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalized);
    if (!$date || $date->format('Y-m-d H:i:s') !== $normalized) throw new RuntimeException('Received date must use a valid date and time.');
    return $normalized;
}

function carrier_preview(string $message, string $provided = ''): string
{
    $preview = trim($provided) !== '' ? trim($provided) : trim(preg_replace('/\s+/', ' ', strip_tags($message)) ?: '');
    return strlen($preview) > 320 ? substr($preview, 0, 317) . '...' : $preview;
}

function carrier_payload_from_post(): array
{
    $name = carrier_clean_text('carrier_name', 190);
    $subject = carrier_clean_text('subject', 255);
    $message = carrier_clean_text('message', 12000);
    $email = strtolower(carrier_clean_text('carrier_email', 190));
    if ($name === '' || $subject === '' || $message === '') throw new RuntimeException('Carrier name, subject, and message are required.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid carrier email address.');
    return [
        'carrier_name' => $name,
        'carrier_email' => $email,
        'subject' => $subject,
        'message' => $message,
        'preview_text' => carrier_preview($message, carrier_clean_text('preview_text', 320)),
        'status' => carrier_status(carrier_clean_text('status', 40, 'New')),
        'is_read' => isset($_POST['is_read']) ? 1 : 0,
        'is_starred' => isset($_POST['is_starred']) ? 1 : 0,
        'priority' => carrier_priority(carrier_clean_text('priority', 40, 'Normal')),
        'attachments' => carrier_clean_text('attachments', 2000),
        'received_at' => carrier_clean_datetime('received_at'),
    ];
}

function carrier_insert(PDO $pdo, array $payload, int $actorId): int
{
    $stmt = $pdo->prepare('INSERT INTO carrier_emails (carrier_name, carrier_email, subject, message, preview_text, status, is_read, is_starred, priority, attachments, received_at, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$payload['carrier_name'], $payload['carrier_email'], $payload['subject'], $payload['message'], $payload['preview_text'], $payload['status'], $payload['is_read'], $payload['is_starred'], $payload['priority'], $payload['attachments'], $payload['received_at'], $actorId, $actorId]);
    return (int) $pdo->lastInsertId();
}

function carrier_update(PDO $pdo, int $id, array $payload, int $actorId): void
{
    $stmt = $pdo->prepare('UPDATE carrier_emails SET carrier_name = ?, carrier_email = ?, subject = ?, message = ?, preview_text = ?, status = ?, is_read = ?, is_starred = ?, priority = ?, attachments = ?, received_at = ?, updated_by = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$payload['carrier_name'], $payload['carrier_email'], $payload['subject'], $payload['message'], $payload['preview_text'], $payload['status'], $payload['is_read'], $payload['is_starred'], $payload['priority'], $payload['attachments'], $payload['received_at'], $actorId, $id]);
}

function carrier_fetch(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM carrier_emails WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function carrier_log_activity(PDO $pdo, int $actorId, string $action, ?int $targetId = null, string $details = ''): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, 'carrier_email', $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $error) {
        error_log('Carrier activity log skipped: ' . $error->getMessage());
    }
}
