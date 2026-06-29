<?php
declare(strict_types=1);

function switchboard_statuses(): array { return ['Open', 'Pending', 'Waiting', 'Resolved', 'Archived']; }
function switchboard_priorities(): array { return ['Low', 'Normal', 'High', 'Urgent']; }
function switchboard_types(): array { return ['Support', 'Sales', 'Carrier', 'Internal', 'General']; }
function switchboard_status(string $value): string { return in_array($value, switchboard_statuses(), true) ? $value : 'Open'; }
function switchboard_priority(string $value): string { return in_array($value, switchboard_priorities(), true) ? $value : 'Normal'; }
function switchboard_type(string $value): string { return in_array($value, switchboard_types(), true) ? $value : 'General'; }
function switchboard_sql_name(string $name): string { if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) throw new InvalidArgumentException('Invalid SQL identifier.'); return '`' . $name . '`'; }
function switchboard_table_exists(PDO $pdo, string $table): bool { $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'); $stmt->execute([$table]); return (int) $stmt->fetchColumn() > 0; }
function switchboard_column_exists(PDO $pdo, string $table, string $column): bool { $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'); $stmt->execute([$table, $column]); return (int) $stmt->fetchColumn() > 0; }
function switchboard_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void { if (!switchboard_column_exists($pdo, $table, $column)) $pdo->exec('ALTER TABLE ' . switchboard_sql_name($table) . ' ADD COLUMN ' . switchboard_sql_name($column) . ' ' . $definition); }
function switchboard_index_exists(PDO $pdo, string $table, string $index): bool { $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'); $stmt->execute([$table, $index]); return (int) $stmt->fetchColumn() > 0; }
function switchboard_add_index_if_missing(PDO $pdo, string $table, string $index, string $columns): void { if (!switchboard_index_exists($pdo, $table, $index)) $pdo->exec('ALTER TABLE ' . switchboard_sql_name($table) . ' ADD INDEX ' . switchboard_sql_name($index) . ' (' . $columns . ')'); }

function switchboard_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS switchboard_conversations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(190) NOT NULL,
        conversation_type ENUM('Support','Sales','Carrier','Internal','General') NOT NULL DEFAULT 'General',
        status ENUM('Open','Pending','Waiting','Resolved','Archived') NOT NULL DEFAULT 'Open',
        priority ENUM('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
        last_message_preview VARCHAR(320) NOT NULL DEFAULT '',
        last_message_at DATETIME NULL,
        assigned_to INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_switchboard_conversation_status (status),
        INDEX idx_switchboard_conversation_priority (priority),
        INDEX idx_switchboard_conversation_last_message_at (last_message_at),
        INDEX idx_switchboard_conversation_assigned_to (assigned_to),
        CONSTRAINT fk_switchboard_conversations_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS switchboard_participants (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        email VARCHAR(190) NOT NULL DEFAULT '',
        phone VARCHAR(80) NOT NULL DEFAULT '',
        role VARCHAR(80) NOT NULL DEFAULT 'participant',
        is_internal TINYINT(1) NOT NULL DEFAULT 0,
        last_seen_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_switchboard_participant_conversation_id (conversation_id),
        INDEX idx_switchboard_participant_email (email),
        CONSTRAINT fk_switchboard_participants_conversation FOREIGN KEY (conversation_id) REFERENCES switchboard_conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS switchboard_messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT UNSIGNED NOT NULL,
        sender_name VARCHAR(190) NOT NULL,
        sender_email VARCHAR(190) NOT NULL DEFAULT '',
        sender_type ENUM('internal','external','system') NOT NULL DEFAULT 'internal',
        message_body MEDIUMTEXT NOT NULL,
        message_type VARCHAR(40) NOT NULL DEFAULT 'text',
        attachments TEXT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_switchboard_message_conversation_id (conversation_id),
        INDEX idx_switchboard_message_sender_email (sender_email),
        INDEX idx_switchboard_message_is_read (is_read),
        INDEX idx_switchboard_message_sent_at (sent_at),
        CONSTRAINT fk_switchboard_messages_conversation FOREIGN KEY (conversation_id) REFERENCES switchboard_conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach ([
        ['switchboard_conversations','idx_switchboard_conversation_status','`status`'], ['switchboard_conversations','idx_switchboard_conversation_priority','`priority`'], ['switchboard_conversations','idx_switchboard_conversation_last_message_at','`last_message_at`'], ['switchboard_conversations','idx_switchboard_conversation_assigned_to','`assigned_to`'],
        ['switchboard_participants','idx_switchboard_participant_conversation_id','`conversation_id`'], ['switchboard_participants','idx_switchboard_participant_email','`email`'],
        ['switchboard_messages','idx_switchboard_message_conversation_id','`conversation_id`'], ['switchboard_messages','idx_switchboard_message_sender_email','`sender_email`'], ['switchboard_messages','idx_switchboard_message_is_read','`is_read`'], ['switchboard_messages','idx_switchboard_message_sent_at','`sent_at`'],
    ] as $index) switchboard_add_index_if_missing($pdo, $index[0], $index[1], $index[2]);
}

function switchboard_schema_ready(PDO $pdo): bool { return switchboard_table_exists($pdo, 'switchboard_conversations') && switchboard_table_exists($pdo, 'switchboard_participants') && switchboard_table_exists($pdo, 'switchboard_messages'); }
function switchboard_clean_text(string $key, int $maxLength, string $default = ''): string { $value = trim((string) ($_POST[$key] ?? $default)); $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $value) ?? ''; return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value; }
function switchboard_clean_email(string $key): string { $email = strtolower(switchboard_clean_text($key, 190)); if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.'); return $email; }
function switchboard_post_int(string $key, int $default = 0): int { return filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT) !== false ? (int) ($_POST[$key] ?? $default) : $default; }
function switchboard_preview(string $message): string { $preview = trim(preg_replace('/\s+/', ' ', strip_tags($message)) ?: ''); return strlen($preview) > 320 ? substr($preview, 0, 317) . '...' : $preview; }
function switchboard_assignee_id(PDO $pdo, int $id): ?int { if ($id <= 0) return null; $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1 AND role IN ('admin','editor','support') LIMIT 1"); $stmt->execute([$id]); if (!$stmt->fetch()) throw new RuntimeException('Choose an active admin, editor, or support assignee.'); return $id; }
function switchboard_fetch_conversation(PDO $pdo, int $id): ?array { $stmt = $pdo->prepare('SELECT c.*, u.full_name AS assignee_name, u.email AS assignee_email FROM switchboard_conversations c LEFT JOIN users u ON u.id = c.assigned_to WHERE c.id = ? LIMIT 1'); $stmt->execute([$id]); $row = $stmt->fetch(); return $row ?: null; }
function switchboard_fetch_messages(PDO $pdo, int $conversationId): array { $stmt = $pdo->prepare('SELECT * FROM switchboard_messages WHERE conversation_id = ? ORDER BY sent_at ASC, id ASC'); $stmt->execute([$conversationId]); return $stmt->fetchAll(); }
function switchboard_fetch_participants(PDO $pdo, int $conversationId): array { $stmt = $pdo->prepare('SELECT * FROM switchboard_participants WHERE conversation_id = ? ORDER BY is_internal ASC, name ASC'); $stmt->execute([$conversationId]); return $stmt->fetchAll(); }
function switchboard_send_message(PDO $pdo, int $conversationId, string $body, array $sender, string $senderType = 'internal'): int { $body = trim($body); if ($conversationId <= 0 || !switchboard_fetch_conversation($pdo, $conversationId)) throw new RuntimeException('Choose a valid conversation.'); if ($body === '') throw new RuntimeException('Enter a message before sending.'); if (strlen($body) > 12000) throw new RuntimeException('Message must be 12,000 characters or fewer.'); if (!in_array($senderType, ['internal','external','system'], true)) $senderType = 'internal'; $senderName = trim((string) ($sender['full_name'] ?? $sender['name'] ?? $sender['email'] ?? 'Team member')); $senderEmail = strtolower(trim((string) ($sender['email'] ?? ''))); $pdo->beginTransaction(); $stmt = $pdo->prepare('INSERT INTO switchboard_messages (conversation_id, sender_name, sender_email, sender_type, message_body, message_type, attachments, is_read, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'); $stmt->execute([$conversationId, $senderName, $senderEmail, $senderType, $body, 'text', '', $senderType === 'internal' ? 1 : 0]); $messageId = (int) $pdo->lastInsertId(); $pdo->prepare('UPDATE switchboard_conversations SET last_message_preview = ?, last_message_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([switchboard_preview($body), $conversationId]); $pdo->commit(); return $messageId; }
function switchboard_log_activity(PDO $pdo, int $actorId, string $action, ?int $targetId = null, string $details = ''): void { try { $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)'); $stmt->execute([$actorId, $action, 'switchboard_conversation', $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']); } catch (Throwable $error) { error_log('Switchboard activity log skipped: ' . $error->getMessage()); } }
function switchboard_manageable_users(PDO $pdo): array { try { return $pdo->query("SELECT id, email, full_name FROM users WHERE is_active = 1 AND role IN ('admin','editor','support') ORDER BY full_name ASC, email ASC")->fetchAll(); } catch (Throwable $error) { return []; } }
