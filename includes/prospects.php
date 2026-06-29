<?php
declare(strict_types=1);

function prospect_statuses(): array { return ['New Lead', 'InProgress', 'Warm', 'Converted', 'Client', 'Closed / Lost']; }
function prospect_kanban_statuses(): array { return ['New Lead', 'InProgress', 'Warm', 'Converted', 'Client']; }
function prospect_priorities(): array { return ['High', 'Medium', 'Low']; }
function prospect_status(string $value): string { return in_array($value, prospect_statuses(), true) ? $value : 'New Lead'; }
function prospect_priority(string $value): string { return in_array($value, prospect_priorities(), true) ? $value : 'Medium'; }
function prospect_money(int $value): string { return '$' . number_format($value); }
function prospect_excerpt(string $value, int $length = 120): string
{
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
    return strlen($plain) <= $length ? $plain : rtrim(substr($plain, 0, $length - 3)) . '...';
}
function prospect_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') return 'OS';
    $parts = preg_split('/\s+/', $name) ?: [];
    $first = strtoupper(substr((string) ($parts[0] ?? ''), 0, 1));
    $second = strtoupper(substr((string) ($parts[1] ?? ''), 0, 1));
    return substr($first . ($second !== '' ? $second : strtoupper(substr($name, 1, 1))), 0, 2);
}
function prospect_timeline_group(?string $date): string
{
    if (!$date) return 'Later';
    $target = DateTimeImmutable::createFromFormat('Y-m-d', $date) ?: null;
    if (!$target) return 'Later';
    $today = new DateTimeImmutable('today');
    if ($target <= $today) return 'Today';
    return $target <= $today->modify('+7 days') ? 'This week' : 'Later';
}
function prospect_sql_name(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) throw new InvalidArgumentException('Invalid SQL identifier.');
    return '`' . $name . '`';
}
function prospect_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}
function prospect_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}
function prospect_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!prospect_column_exists($pdo, $table, $column)) $pdo->exec('ALTER TABLE ' . prospect_sql_name($table) . ' ADD COLUMN ' . prospect_sql_name($column) . ' ' . $definition);
}
function prospect_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}
function prospect_add_index_if_missing(PDO $pdo, string $table, string $index, string $columns): void
{
    if (!prospect_index_exists($pdo, $table, $index)) $pdo->exec('ALTER TABLE ' . prospect_sql_name($table) . ' ADD INDEX ' . prospect_sql_name($index) . ' (' . $columns . ')');
}
function prospect_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS prospects (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company VARCHAR(190) NOT NULL,
        status ENUM('New Lead','InProgress','Warm','Converted','Client','Closed / Lost') NOT NULL DEFAULT 'New Lead',
        website VARCHAR(255) NOT NULL DEFAULT '',
        industry_category VARCHAR(190) NOT NULL DEFAULT '',
        conversion_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        notes TEXT NULL,
        contact VARCHAR(190) NOT NULL DEFAULT '',
        email VARCHAR(190) NOT NULL DEFAULT '',
        phone VARCHAR(80) NOT NULL DEFAULT '',
        social_media_links TEXT NULL,
        location VARCHAR(190) NOT NULL DEFAULT '',
        reason_relevant TEXT NULL,
        pain_point_trigger TEXT NULL,
        outreach_angle TEXT NULL,
        priority ENUM('High','Medium','Low') NOT NULL DEFAULT 'Medium',
        last_contact DATE NULL,
        next_step VARCHAR(255) NOT NULL DEFAULT '',
        additional_notes TEXT NULL,
        source VARCHAR(120) NOT NULL DEFAULT '',
        value INT UNSIGNED NOT NULL DEFAULT 0,
        owner VARCHAR(120) NOT NULL DEFAULT '',
        follow_up DATE NULL,
        last_activity VARCHAR(255) NOT NULL DEFAULT '',
        created_by INT UNSIGNED NULL,
        updated_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_prospects_status (status),
        INDEX idx_prospects_priority (priority),
        INDEX idx_prospects_industry (industry_category),
        INDEX idx_prospects_conversion (conversion_percentage),
        INDEX idx_prospects_last_contact (last_contact),
        INDEX idx_prospects_updated (updated_at),
        CONSTRAINT fk_prospects_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_prospects_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    prospect_add_column_if_missing($pdo, 'prospects', 'company', "VARCHAR(190) NOT NULL DEFAULT ''");
    prospect_add_column_if_missing($pdo, 'prospects', 'status', "ENUM('New Lead','InProgress','Warm','Converted','Client','Closed / Lost') NOT NULL DEFAULT 'New Lead'");
    prospect_add_column_if_missing($pdo, 'prospects', 'website', "VARCHAR(255) NOT NULL DEFAULT ''");
    prospect_add_column_if_missing($pdo, 'prospects', 'industry_category', "VARCHAR(190) NOT NULL DEFAULT ''");
    prospect_add_column_if_missing($pdo, 'prospects', 'conversion_percentage', 'DECIMAL(5,2) NOT NULL DEFAULT 0.00');
    prospect_add_column_if_missing($pdo, 'prospects', 'notes', 'TEXT NULL');
    prospect_add_column_if_missing($pdo, 'prospects', 'contact', "VARCHAR(190) NOT NULL DEFAULT ''");
    prospect_add_column_if_missing($pdo, 'prospects', 'email', "VARCHAR(190) NOT NULL DEFAULT ''");
    prospect_add_column_if_missing($pdo, 'prospects', 'phone', "VARCHAR(80) NOT NULL DEFAULT ''");
    prospect_add_column_if_missing($pdo, 'prospects', 'social_media_links', 'TEXT NULL');
    prospect_add_column_if_missing($pdo, 'prospects', 'location', "VARCHAR(190) NOT NULL DEFAULT ''");
    prospect_add_column_if_missing($pdo, 'prospects', 'reason_relevant', 'TEXT NULL');
    prospect_add_column_if_missing($pdo, 'prospects', 'pain_point_trigger', 'TEXT NULL');
    prospect_add_column_if_missing($pdo, 'prospects', 'outreach_angle', 'TEXT NULL');
    prospect_add_column_if_missing($pdo, 'prospects', 'priority', "ENUM('High','Medium','Low') NOT NULL DEFAULT 'Medium'");
    prospect_add_column_if_missing($pdo, 'prospects', 'last_contact', 'DATE NULL');
    prospect_add_column_if_missing($pdo, 'prospects', 'next_step', "VARCHAR(255) NOT NULL DEFAULT ''");
    prospect_add_column_if_missing($pdo, 'prospects', 'additional_notes', 'TEXT NULL');
    prospect_add_column_if_missing($pdo, 'prospects', 'source', "VARCHAR(120) NOT NULL DEFAULT ''");
    prospect_add_column_if_missing($pdo, 'prospects', 'value', 'INT UNSIGNED NOT NULL DEFAULT 0');
    prospect_add_column_if_missing($pdo, 'prospects', 'owner', "VARCHAR(120) NOT NULL DEFAULT ''");
    prospect_add_column_if_missing($pdo, 'prospects', 'follow_up', 'DATE NULL');
    prospect_add_column_if_missing($pdo, 'prospects', 'last_activity', "VARCHAR(255) NOT NULL DEFAULT ''");
    prospect_add_column_if_missing($pdo, 'prospects', 'created_by', 'INT UNSIGNED NULL');
    prospect_add_column_if_missing($pdo, 'prospects', 'updated_by', 'INT UNSIGNED NULL');
    prospect_add_column_if_missing($pdo, 'prospects', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    prospect_add_column_if_missing($pdo, 'prospects', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    prospect_add_index_if_missing($pdo, 'prospects', 'idx_prospects_status', '`status`');
    prospect_add_index_if_missing($pdo, 'prospects', 'idx_prospects_priority', '`priority`');
    prospect_add_index_if_missing($pdo, 'prospects', 'idx_prospects_industry', '`industry_category`');
    prospect_add_index_if_missing($pdo, 'prospects', 'idx_prospects_conversion', '`conversion_percentage`');
    prospect_add_index_if_missing($pdo, 'prospects', 'idx_prospects_last_contact', '`last_contact`');
    prospect_add_index_if_missing($pdo, 'prospects', 'idx_prospects_updated', '`updated_at`');
}
function prospect_schema_ready(PDO $pdo): bool { return prospect_table_exists($pdo, 'prospects'); }
function prospect_log_activity(PDO $pdo, int $actorId, string $action, ?int $targetId = null, string $details = ''): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, 'prospect', $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $error) { error_log('Prospect activity log skipped: ' . $error->getMessage()); }
}
function prospect_clean_text(string $key, int $maxLength, string $default = ''): string
{
    $value = trim((string) ($_POST[$key] ?? $default));
    $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $value) ?? '';
    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
}
function prospect_clean_int(string $key, int $default = 0): int
{
    $raw = str_replace([',', '$'], '', trim((string) ($_POST[$key] ?? (string) $default)));
    if ($raw === '') return $default;
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999999999]]);
    if ($value === false) throw new RuntimeException('Estimated value must be a whole dollar amount.');
    return (int) $value;
}
function prospect_clean_date(string $key): ?string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) throw new RuntimeException('Date fields must use YYYY-MM-DD format.');
    return $value;
}
function prospect_valid_email(string $email): string
{
    if ($email === '') return '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
    return strtolower($email);
}
function prospect_conversion_percentage(array $payload, float $fallbackBase = 30.0): float
{
    $statusBase = [
        'New Lead' => 30.0,
        'InProgress' => 55.0,
        'Warm' => 75.0,
        'Converted' => 95.0,
        'Client' => 95.0,
        'Closed / Lost' => 5.0,
    ];
    $score = $statusBase[(string) ($payload['status'] ?? '')] ?? $fallbackBase;
    $priority = strtolower((string) ($payload['priority'] ?? ''));
    $score += $priority === 'high' ? 12.0 : ($priority === 'medium' ? 6.0 : ($priority === 'low' ? 2.0 : 0.0));
    foreach ([
        'website' => 4.0,
        'industry_category' => 5.0,
        'notes' => 3.0,
        'contact' => 5.0,
        'email' => 8.0,
        'phone' => 5.0,
        'social_media_links' => 3.0,
        'location' => 3.0,
        'reason_relevant' => 7.0,
        'pain_point_trigger' => 8.0,
        'outreach_angle' => 7.0,
        'next_step' => 6.0,
        'additional_notes' => 3.0,
    ] as $field => $points) {
        if (trim((string) ($payload[$field] ?? '')) !== '') $score += $points;
    }
    $lastContact = (string) ($payload['last_contact'] ?? '');
    if ($lastContact !== '') {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $lastContact) ?: null;
        if ($date && $date >= (new DateTimeImmutable('today'))->modify('-30 days')) $score += 6.0;
    }
    return min(99.0, max(1.0, round($score, 2)));
}
function prospect_payload_from_post(): array
{
    $company = prospect_clean_text('company', 190);
    if ($company === '') throw new RuntimeException('Business name is required.');
    $payload = [
        'company' => $company,
        'status' => prospect_status(prospect_clean_text('status', 40, 'New Lead')),
        'website' => prospect_clean_text('website', 255),
        'industry_category' => prospect_clean_text('industry_category', 190),
        'notes' => prospect_clean_text('notes', 5000),
        'contact' => prospect_clean_text('contact', 190),
        'email' => prospect_valid_email(prospect_clean_text('email', 190)),
        'phone' => prospect_clean_text('phone', 80),
        'social_media_links' => prospect_clean_text('social_media_links', 5000),
        'location' => prospect_clean_text('location', 190),
        'reason_relevant' => prospect_clean_text('reason_relevant', 5000),
        'pain_point_trigger' => prospect_clean_text('pain_point_trigger', 5000),
        'outreach_angle' => prospect_clean_text('outreach_angle', 5000),
        'priority' => prospect_priority(prospect_clean_text('priority', 40, 'Medium')),
        'last_contact' => prospect_clean_date('last_contact'),
        'next_step' => prospect_clean_text('next_step', 255),
        'additional_notes' => prospect_clean_text('additional_notes', 5000),
        'source' => prospect_clean_text('source', 120),
        'value' => prospect_clean_int('value'),
        'owner' => prospect_clean_text('owner', 120),
        'follow_up' => prospect_clean_date('follow_up'),
        'last_activity' => prospect_clean_text('last_activity', 255),
    ];
    $payload['conversion_percentage'] = prospect_conversion_percentage($payload);
    return $payload;
}
function prospect_fetch(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM prospects WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
function prospect_insert(PDO $pdo, array $payload, int $actorId): int
{
    $stmt = $pdo->prepare('INSERT INTO prospects (company, status, website, industry_category, conversion_percentage, notes, contact, email, phone, social_media_links, location, reason_relevant, pain_point_trigger, outreach_angle, priority, last_contact, next_step, additional_notes, source, value, owner, follow_up, last_activity, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$payload['company'], $payload['status'], $payload['website'], $payload['industry_category'], $payload['conversion_percentage'], $payload['notes'], $payload['contact'], $payload['email'], $payload['phone'], $payload['social_media_links'], $payload['location'], $payload['reason_relevant'], $payload['pain_point_trigger'], $payload['outreach_angle'], $payload['priority'], $payload['last_contact'], $payload['next_step'], $payload['additional_notes'], $payload['source'], $payload['value'], $payload['owner'], $payload['follow_up'], $payload['last_activity'], $actorId, $actorId]);
    return (int) $pdo->lastInsertId();
}
function prospect_update(PDO $pdo, int $id, array $payload, int $actorId): void
{
    $stmt = $pdo->prepare('UPDATE prospects SET company = ?, status = ?, website = ?, industry_category = ?, conversion_percentage = ?, notes = ?, contact = ?, email = ?, phone = ?, social_media_links = ?, location = ?, reason_relevant = ?, pain_point_trigger = ?, outreach_angle = ?, priority = ?, last_contact = ?, next_step = ?, additional_notes = ?, source = ?, value = ?, owner = ?, follow_up = ?, last_activity = ?, updated_by = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$payload['company'], $payload['status'], $payload['website'], $payload['industry_category'], $payload['conversion_percentage'], $payload['notes'], $payload['contact'], $payload['email'], $payload['phone'], $payload['social_media_links'], $payload['location'], $payload['reason_relevant'], $payload['pain_point_trigger'], $payload['outreach_angle'], $payload['priority'], $payload['last_contact'], $payload['next_step'], $payload['additional_notes'], $payload['source'], $payload['value'], $payload['owner'], $payload['follow_up'], $payload['last_activity'], $actorId, $id]);
}
function prospect_parse_import_rows(string $raw): array
{
    $rows = [];
    $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
    foreach ($lines as $index => $line) {
        if (trim($line) === '') continue;
        $fields = str_getcsv($line);
        $firstHeader = strtolower(trim((string) ($fields[0] ?? '')));
        if ($index === 0 && in_array($firstHeader, ['company', 'business name'], true)) continue;
        $company = substr(trim((string) ($fields[0] ?? '')), 0, 190);
        if ($company === '') continue;
        $payload = [
            'company' => $company,
            'status' => prospect_status(trim((string) ($fields[1] ?? 'New Lead'))),
            'website' => substr(trim((string) ($fields[2] ?? '')), 0, 255),
            'industry_category' => substr(trim((string) ($fields[3] ?? '')), 0, 190),
            'notes' => substr(trim((string) ($fields[5] ?? '')), 0, 5000),
            'contact' => substr(trim((string) ($fields[6] ?? '')), 0, 190),
            'email' => prospect_valid_email(substr(trim((string) ($fields[7] ?? '')), 0, 190)),
            'phone' => substr(trim((string) ($fields[8] ?? '')), 0, 80),
            'social_media_links' => substr(trim((string) ($fields[9] ?? '')), 0, 5000),
            'location' => substr(trim((string) ($fields[10] ?? '')), 0, 190),
            'reason_relevant' => substr(trim((string) ($fields[11] ?? '')), 0, 5000),
            'pain_point_trigger' => substr(trim((string) ($fields[12] ?? '')), 0, 5000),
            'outreach_angle' => substr(trim((string) ($fields[13] ?? '')), 0, 5000),
            'priority' => prospect_priority(trim((string) ($fields[14] ?? 'Medium'))),
            'last_contact' => null,
            'next_step' => substr(trim((string) ($fields[16] ?? '')), 0, 255),
            'additional_notes' => substr(trim((string) ($fields[17] ?? '')), 0, 5000),
            'source' => 'Import',
            'value' => 0,
            'owner' => '',
            'follow_up' => null,
            'last_activity' => 'Imported lead',
        ];
        $date = trim((string) ($fields[15] ?? ''));
        if ($date !== '' && DateTimeImmutable::createFromFormat('Y-m-d', $date)) $payload['last_contact'] = $date;
        $percentage = trim((string) ($fields[4] ?? ''));
        $payload['conversion_percentage'] = is_numeric(str_replace('%', '', $percentage)) ? min(99.0, max(1.0, (float) str_replace('%', '', $percentage))) : prospect_conversion_percentage($payload);
        $rows[] = $payload;
    }
    return $rows;
}
