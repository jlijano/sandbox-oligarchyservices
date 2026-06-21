<?php
declare(strict_types=1);

function password_change_column_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute(['users', 'password_change_required']);
    return (int) $stmt->fetchColumn() > 0;
}

function password_change_ensure_schema(PDO $pdo): void
{
    if (password_change_column_exists($pdo)) {
        return;
    }

    $pdo->exec('ALTER TABLE users ADD COLUMN password_change_required TINYINT(1) NOT NULL DEFAULT 0');
}
