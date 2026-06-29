<?php
declare(strict_types=1);

function prospect_sheet_sync_spreadsheet_id(): string
{
    $configured = trim((string) getenv('PROSPECTS_SYNC_SPREADSHEET_ID'));
    return $configured !== '' ? $configured : '1-BzOqTRrJ9RWuro50_aLqgrWpNjKNetz2Y_o0UMCO1g';
}

function prospect_sheet_sync_sources(): array
{
    $singleCsvUrl = trim((string) getenv('PROSPECTS_SYNC_CSV_URL'));
    if ($singleCsvUrl !== '') {
        return [['label' => 'Configured CSV', 'status' => 'New Lead', 'url' => $singleCsvUrl]];
    }

    $spreadsheetId = rawurlencode(prospect_sheet_sync_spreadsheet_id());
    $tabs = [
        ['label' => 'Clients', 'status' => 'Client', 'gid' => '1600310510'],
        ['label' => 'New Leads', 'status' => 'New Lead', 'gid' => '1600310511'],
        ['label' => 'InProgress', 'status' => 'InProgress', 'gid' => '1600310512'],
        ['label' => 'Warm', 'status' => 'Warm', 'gid' => '1600310513'],
        ['label' => 'Converted', 'status' => 'Converted', 'gid' => '1600310514'],
        ['label' => 'Closed / Lost', 'status' => 'Closed / Lost', 'gid' => '1600310515'],
    ];

    return array_map(static function (array $tab) use ($spreadsheetId): array {
        $tab['url'] = 'https://docs.google.com/spreadsheets/d/' . $spreadsheetId . '/export?format=csv&gid=' . rawurlencode((string) $tab['gid']);
        return $tab;
    }, $tabs);
}

function prospect_sheet_fetch_csv(string $url): string
{
    $url = trim($url);
    if ($url === '' || !preg_match('/^https:\/\//i', $url)) {
        throw new RuntimeException('The Google Sheet CSV URL is missing or invalid.');
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_USERAGENT => 'OligarchyProspectSync/1.0',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status >= 400) {
            throw new RuntimeException('Could not fetch the Google Sheet CSV export' . ($error !== '' ? ': ' . $error : '.'));
        }
        $body = (string) $body;
    } else {
        $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 25, 'header' => "User-Agent: OligarchyProspectSync/1.0\r\n"]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Could not fetch the Google Sheet CSV export.');
        }
    }

    $trimmed = ltrim((string) $body);
    if (stripos($trimmed, '<!doctype') === 0 || stripos($trimmed, '<html') === 0) {
        throw new RuntimeException('Google Sheet CSV export returned an HTML page instead of CSV. Publish the sheet/tab or configure PROSPECTS_SYNC_CSV_URL with an accessible CSV export.');
    }

    return (string) $body;
}

function prospect_sheet_csv_rows(string $csv): array
{
    $rows = [];
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) throw new RuntimeException('Could not open a temporary CSV buffer.');
    fwrite($handle, $csv);
    rewind($handle);
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) === 1 && trim((string) $row[0]) === '') continue;
        $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($row[0] ?? '')) ?? (string) ($row[0] ?? '');
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function prospect_sheet_text(array $fields, int $index, int $maxLength): string
{
    $value = trim((string) ($fields[$index] ?? ''));
    $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $value) ?? '';
    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
}

function prospect_sheet_date(array $fields, int $index): ?string
{
    $value = trim((string) ($fields[$index] ?? ''));
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function prospect_sheet_status(string $rawStatus, string $defaultStatus): string
{
    $value = trim($rawStatus) !== '' ? trim($rawStatus) : $defaultStatus;
    $aliases = ['New' => 'New Lead', 'Validated' => 'Warm', 'For Review' => 'New Lead', 'Contacted' => 'InProgress', 'Not Fit' => 'Closed / Lost', 'Duplicate' => 'Closed / Lost', 'Clients' => 'Client', 'New Leads' => 'New Lead'];
    return prospect_status($aliases[$value] ?? $value);
}

function prospect_sheet_email(array $fields, int $index, array &$warnings): string
{
    $email = prospect_sheet_text($fields, $index, 190);
    if ($email === '') return '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $warnings[] = 'Invalid public email skipped: ' . $email;
        return '';
    }
    return strtolower($email);
}

function prospect_sheet_ensure_recommended_services_column(PDO $pdo): void
{
    prospect_add_column_if_missing($pdo, 'prospects', 'recommended_services', 'TEXT NULL');
}

function prospect_sheet_payload_from_fields(array $fields, string $defaultStatus, string $sourceLabel): ?array
{
    $first = strtolower(trim((string) ($fields[0] ?? '')));
    if ($first === '' || in_array($first, ['business name', 'company'], true)) return null;

    $warnings = [];
    $additionalNotes = prospect_sheet_text($fields, 18, 5000);
    $dataGaps = prospect_sheet_text($fields, 26, 5000);
    $payload = [
        'company' => prospect_sheet_text($fields, 0, 190),
        'status' => prospect_sheet_status((string) ($fields[1] ?? ''), $defaultStatus),
        'website' => prospect_sheet_text($fields, 2, 255),
        'industry_category' => prospect_sheet_text($fields, 3, 190),
        'recommended_services' => prospect_sheet_text($fields, 4, 5000),
        'notes' => prospect_sheet_text($fields, 6, 5000),
        'contact' => prospect_sheet_text($fields, 7, 190),
        'email' => prospect_sheet_email($fields, 8, $warnings),
        'phone' => prospect_sheet_text($fields, 9, 80),
        'social_media_links' => prospect_sheet_text($fields, 10, 5000),
        'location' => prospect_sheet_text($fields, 11, 190),
        'reason_relevant' => prospect_sheet_text($fields, 12, 5000),
        'pain_point_trigger' => prospect_sheet_text($fields, 13, 5000),
        'outreach_angle' => prospect_sheet_text($fields, 14, 5000),
        'priority' => prospect_priority(prospect_sheet_text($fields, 15, 40) ?: 'Medium'),
        'last_contact' => prospect_sheet_date($fields, 16),
        'next_step' => prospect_sheet_text($fields, 17, 255),
        'additional_notes' => $additionalNotes,
        'decision_maker_title' => prospect_sheet_text($fields, 19, 190),
        'decision_maker_department' => prospect_sheet_text($fields, 20, 190),
        'decision_maker_profile_url' => prospect_sheet_text($fields, 21, 255),
        'decision_maker_source' => prospect_sheet_text($fields, 22, 5000),
        'contact_confidence' => prospect_contact_confidence(prospect_sheet_text($fields, 23, 40) ?: 'Not Verified'),
        'company_source_url' => prospect_sheet_text($fields, 24, 255),
        'last_verified' => prospect_sheet_date($fields, 25),
        'data_gaps_validation_notes' => trim($dataGaps . ($warnings ? "\n" . implode("\n", $warnings) : '')),
        'source' => 'Google Sheet: ' . substr($sourceLabel, 0, 100),
        'value' => 0,
        'owner' => '',
        'follow_up' => null,
        'last_activity' => 'Synced from Google Sheet',
    ];

    $percentage = trim((string) ($fields[5] ?? ''));
    $payload['conversion_percentage'] = is_numeric(str_replace('%', '', $percentage))
        ? min(99.0, max(1.0, (float) str_replace('%', '', $percentage)))
        : prospect_conversion_percentage($payload);

    return $payload['company'] !== '' ? $payload : null;
}

function prospect_sheet_user_exists(PDO $pdo, int $userId): bool
{
    if ($userId <= 0 || !prospect_table_exists($pdo, 'users')) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn() > 0;
}

function prospect_sheet_sync_actor_id(PDO $pdo, int $preferredActorId = 0): int
{
    if (prospect_sheet_user_exists($pdo, $preferredActorId)) return $preferredActorId;
    $configuredActorId = (int) (getenv('PROSPECTS_SYNC_ACTOR_ID') ?: 0);
    if (prospect_sheet_user_exists($pdo, $configuredActorId)) return $configuredActorId;
    if (!prospect_table_exists($pdo, 'users')) throw new RuntimeException('No users table was found for prospect sync attribution. Run /update.php or create an admin user first.');

    $stmt = $pdo->query("SELECT id FROM users WHERE LOWER(role) IN ('admin', 'editor') ORDER BY id ASC LIMIT 1");
    $fallbackId = $stmt ? $stmt->fetchColumn() : false;
    if ($fallbackId !== false && (int) $fallbackId > 0) return (int) $fallbackId;

    $stmt = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1');
    $fallbackId = $stmt ? $stmt->fetchColumn() : false;
    if ($fallbackId !== false && (int) $fallbackId > 0) return (int) $fallbackId;

    throw new RuntimeException('No portal user was found for prospect sync attribution. Create an admin/editor user or set PROSPECTS_SYNC_ACTOR_ID.');
}

function prospect_find_existing_id(PDO $pdo, array $payload): ?int
{
    $lookups = [];
    if (trim((string) ($payload['website'] ?? '')) !== '') $lookups[] = ['SELECT id FROM prospects WHERE website = ? LIMIT 1', [$payload['website']]];
    if (trim((string) ($payload['email'] ?? '')) !== '') $lookups[] = ['SELECT id FROM prospects WHERE email = ? LIMIT 1', [$payload['email']]];
    if (trim((string) ($payload['company'] ?? '')) !== '' && trim((string) ($payload['location'] ?? '')) !== '') $lookups[] = ['SELECT id FROM prospects WHERE LOWER(company) = LOWER(?) AND LOWER(location) = LOWER(?) LIMIT 1', [$payload['company'], $payload['location']]];
    if (trim((string) ($payload['company'] ?? '')) !== '') $lookups[] = ['SELECT id FROM prospects WHERE LOWER(company) = LOWER(?) LIMIT 1', [$payload['company']]];

    foreach ($lookups as [$sql, $params]) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        if ($id !== false) return (int) $id;
    }
    return null;
}

function prospect_sheet_apply_recommended_services(PDO $pdo, int $prospectId, array $payload): void
{
    $stmt = $pdo->prepare('UPDATE prospects SET recommended_services = ? WHERE id = ?');
    $stmt->execute([(string) ($payload['recommended_services'] ?? ''), $prospectId]);
}

function prospect_sheet_sync_upsert(PDO $pdo, array $payload, int $actorId): string
{
    $actorId = prospect_sheet_sync_actor_id($pdo, $actorId);
    $existingId = prospect_find_existing_id($pdo, $payload);
    if ($existingId !== null) {
        prospect_update($pdo, $existingId, $payload, $actorId);
        prospect_sheet_apply_recommended_services($pdo, $existingId, $payload);
        return 'updated';
    }

    $prospectId = prospect_insert($pdo, $payload, $actorId);
    prospect_sheet_apply_recommended_services($pdo, $prospectId, $payload);
    return 'created';
}

function prospect_sheet_sync(PDO $pdo, int $actorId): array
{
    prospect_sheet_ensure_recommended_services_column($pdo);
    $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'sources' => [], 'errors' => []];
    $actorId = prospect_sheet_sync_actor_id($pdo, $actorId);

    foreach (prospect_sheet_sync_sources() as $source) {
        $sourceLabel = (string) $source['label'];
        try {
            $csv = prospect_sheet_fetch_csv((string) $source['url']);
            $rows = prospect_sheet_csv_rows($csv);
            $sourceSummary = ['label' => $sourceLabel, 'created' => 0, 'updated' => 0, 'skipped' => 0];
            $pdo->beginTransaction();
            foreach ($rows as $fields) {
                $payload = prospect_sheet_payload_from_fields($fields, (string) $source['status'], $sourceLabel);
                if ($payload === null) {
                    $summary['skipped']++;
                    $sourceSummary['skipped']++;
                    continue;
                }
                $result = prospect_sheet_sync_upsert($pdo, $payload, $actorId);
                $summary[$result]++;
                $sourceSummary[$result]++;
            }
            $pdo->commit();
            $summary['sources'][] = $sourceSummary;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $summary['errors'][] = $sourceLabel . ': ' . $error->getMessage();
        }
    }

    return $summary;
}

function prospect_sheet_sync_message(array $summary): string
{
    $message = (int) $summary['created'] . ' created, ' . (int) $summary['updated'] . ' updated, ' . (int) $summary['skipped'] . ' skipped.';
    if (!empty($summary['errors'])) $message .= ' Errors: ' . implode(' | ', array_map('strval', $summary['errors']));
    return $message;
}
