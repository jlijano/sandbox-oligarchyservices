<?php
declare(strict_types=1);

function automation_statuses(): array { return ['Draft', 'Ready', 'Paused', 'Retired']; }
function automation_importance_levels(): array { return ['Critical', 'High', 'Medium', 'Low']; }
function automation_status(string $value): string { return in_array($value, automation_statuses(), true) ? $value : 'Draft'; }
function automation_importance(string $value): string { return in_array($value, automation_importance_levels(), true) ? $value : 'Medium'; }

function automation_sql_name(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }
    return '`' . $name . '`';
}

function automation_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function automation_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function automation_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!automation_column_exists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . automation_sql_name($table) . ' ADD COLUMN ' . automation_sql_name($column) . ' ' . $definition);
    }
}

function automation_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function automation_add_index_if_missing(PDO $pdo, string $table, string $index, string $columns): void
{
    if (!automation_index_exists($pdo, $table, $index)) {
        $pdo->exec('ALTER TABLE ' . automation_sql_name($table) . ' ADD INDEX ' . automation_sql_name($index) . ' (' . $columns . ')');
    }
}

function automation_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS automation_recipes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        trigger_event TEXT NOT NULL,
        condition_rules TEXT NULL,
        action_steps TEXT NOT NULL,
        importance ENUM('Critical','High','Medium','Low') NOT NULL DEFAULT 'Medium',
        status ENUM('Draft','Ready','Paused','Retired') NOT NULL DEFAULT 'Draft',
        owner VARCHAR(120) NOT NULL DEFAULT '',
        created_by INT UNSIGNED NULL,
        updated_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_automation_recipes_status (status),
        INDEX idx_automation_recipes_importance (importance),
        INDEX idx_automation_recipes_updated (updated_at),
        CONSTRAINT fk_automation_recipes_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_automation_recipes_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS automation_runs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        recipe_id INT UNSIGNED NOT NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'Not run yet',
        message TEXT NULL,
        ran_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_automation_runs_recipe (recipe_id, created_at),
        INDEX idx_automation_runs_status (status),
        CONSTRAINT fk_automation_runs_recipe FOREIGN KEY (recipe_id) REFERENCES automation_recipes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    automation_add_column_if_missing($pdo, 'automation_recipes', 'owner', "VARCHAR(120) NOT NULL DEFAULT ''");
    automation_add_column_if_missing($pdo, 'automation_recipes', 'created_by', 'INT UNSIGNED NULL');
    automation_add_column_if_missing($pdo, 'automation_recipes', 'updated_by', 'INT UNSIGNED NULL');
    automation_add_index_if_missing($pdo, 'automation_recipes', 'idx_automation_recipes_status', '`status`');
    automation_add_index_if_missing($pdo, 'automation_recipes', 'idx_automation_recipes_importance', '`importance`');
    automation_add_index_if_missing($pdo, 'automation_recipes', 'idx_automation_recipes_updated', '`updated_at`');
}

function automation_schema_ready(PDO $pdo): bool
{
    return automation_table_exists($pdo, 'automation_recipes') && automation_table_exists($pdo, 'automation_runs');
}

function automation_clean_text(string $key, int $maxLength, string $default = ''): string
{
    $value = trim((string) ($_POST[$key] ?? $default));
    $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $value) ?? '';
    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
}

function automation_payload_from_post(): array
{
    $name = automation_clean_text('name', 190);
    $trigger = automation_clean_text('trigger_event', 2000);
    $action = automation_clean_text('action_steps', 2000);
    if ($name === '' || $trigger === '' || $action === '') {
        throw new RuntimeException('Name, trigger, and action are required.');
    }
    return [
        'name' => $name,
        'trigger_event' => $trigger,
        'condition_rules' => automation_clean_text('condition_rules', 2000),
        'action_steps' => $action,
        'importance' => automation_importance(automation_clean_text('importance', 40, 'Medium')),
        'status' => automation_status(automation_clean_text('status', 40, 'Draft')),
        'owner' => automation_clean_text('owner', 120),
    ];
}

function automation_insert_recipe(PDO $pdo, array $payload, int $actorId): int
{
    $stmt = $pdo->prepare('INSERT INTO automation_recipes (name, trigger_event, condition_rules, action_steps, importance, status, owner, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$payload['name'], $payload['trigger_event'], $payload['condition_rules'], $payload['action_steps'], $payload['importance'], $payload['status'], $payload['owner'], $actorId, $actorId]);
    return (int) $pdo->lastInsertId();
}

function automation_update_recipe(PDO $pdo, int $recipeId, array $payload, int $actorId): void
{
    $stmt = $pdo->prepare('UPDATE automation_recipes SET name = ?, trigger_event = ?, condition_rules = ?, action_steps = ?, importance = ?, status = ?, owner = ?, updated_by = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$payload['name'], $payload['trigger_event'], $payload['condition_rules'], $payload['action_steps'], $payload['importance'], $payload['status'], $payload['owner'], $actorId, $recipeId]);
}

function automation_fetch_recipe(PDO $pdo, int $recipeId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM automation_recipes WHERE id = ? LIMIT 1');
    $stmt->execute([$recipeId]);
    $recipe = $stmt->fetch();
    return is_array($recipe) ? $recipe : null;
}

function automation_log_activity(PDO $pdo, int $actorId, string $action, ?int $targetId = null, string $details = ''): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, 'automation', $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $error) {
        error_log('Automation activity log skipped: ' . $error->getMessage());
    }
}