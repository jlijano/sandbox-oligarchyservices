<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/carrier.php';

$user = require_login();
$role = strtolower((string) ($user['role'] ?? 'client'));
if (!in_array($role, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo 'Only admins and editors can set up Carrier mail.';
    exit;
}

function carrier_sync_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : trim((string) $value);
}

function carrier_sync_post_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function carrier_sync_return_to_carrier(): bool
{
    return carrier_sync_post_string('return_to') === 'carrier';
}

function carrier_sync_context_params(array $extra = []): array
{
    $statuses = carrier_statuses();
    $folder = trim((string) ($_POST['folder'] ?? 'inbox'));
    $status = trim((string) ($_POST['status'] ?? ''));
    $search = trim((string) ($_POST['search'] ?? ''));
    $open = filter_var($_POST['open'] ?? 0, FILTER_VALIDATE_INT) !== false ? (int) ($_POST['open'] ?? 0) : 0;
    if (!in_array($folder, ['inbox', 'unread', 'starred', 'archived', 'all'], true)) $folder = 'inbox';
    if (!in_array($status, array_merge([''], $statuses), true)) $status = '';
    $params = ['folder' => $folder];
    if ($status !== '') $params['status'] = $status;
    if ($search !== '') $params['search'] = $search;
    if ($open > 0) $params['open'] = $open;
    foreach ($extra as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    return $params;
}

function carrier_sync_redirect_to_carrier(?string $notice = null, ?string $error = null, array $extra = []): void
{
    if ($notice !== null) $_SESSION['carrier_notice'] = $notice;
    if ($error !== null) $_SESSION['carrier_error'] = $error;
    $params = carrier_sync_context_params($extra);
    redirect_to('/carrier' . ($params ? '?' . http_build_query($params) : ''));
}

function carrier_sync_secret_key(): string
{
    $material = carrier_sync_env('CARRIER_SETTINGS_KEY');
    if ($material === '') {
        $dbConfig = load_db_config(db_primary_config_path());
        $material = implode('|', [
            $dbConfig['host'] ?? '',
            $dbConfig['database'] ?? '',
            $dbConfig['username'] ?? '',
            $dbConfig['password'] ?? '',
            'carrier-imap-settings',
        ]);
    }
    if (trim($material) === '') {
        throw new RuntimeException('Carrier settings encryption is unavailable. Set CARRIER_SETTINGS_KEY in Hostinger, then save the mailbox again.');
    }
    return hash('sha256', $material, true);
}

function carrier_sync_encrypt_password(string $password): array
{
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('PHP OpenSSL is required to save the mailbox password. Enable OpenSSL or set CARRIER_IMAP_PASSWORD in the hosting environment.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($password, 'aes-256-gcm', carrier_sync_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false || $tag === '') {
        throw new RuntimeException('Carrier could not encrypt the mailbox password.');
    }
    return [
        'ciphertext' => base64_encode($ciphertext),
        'nonce' => base64_encode($iv),
        'tag' => base64_encode($tag),
    ];
}

function carrier_sync_decrypt_password(array $settings): string
{
    $ciphertext = base64_decode((string) ($settings['password_ciphertext'] ?? ''), true);
    $iv = base64_decode((string) ($settings['password_nonce'] ?? ''), true);
    $tag = base64_decode((string) ($settings['password_tag'] ?? ''), true);
    if ($ciphertext === false || $iv === false || $tag === false || $ciphertext === '' || $iv === '' || $tag === '') {
        return '';
    }
    if (!function_exists('openssl_decrypt')) {
        throw new RuntimeException('PHP OpenSSL is required to read the saved mailbox password.');
    }
    $password = openssl_decrypt($ciphertext, 'aes-256-gcm', carrier_sync_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $password === false ? '' : $password;
}

function carrier_sync_default_settings(): array
{
    $limit = (int) carrier_sync_env('CARRIER_IMAP_LIMIT', '50');
    if ($limit < 1) $limit = 50;
    if ($limit > 200) $limit = 200;
    return [
        'host' => carrier_sync_env('CARRIER_IMAP_HOST', 'imap.hostinger.com'),
        'port' => (int) carrier_sync_env('CARRIER_IMAP_PORT', '993') ?: 993,
        'flags' => carrier_sync_env('CARRIER_IMAP_FLAGS', '/imap/ssl') ?: '/imap/ssl',
        'mailbox' => carrier_sync_env('CARRIER_IMAP_MAILBOX', 'INBOX') ?: 'INBOX',
        'username' => carrier_sync_env('CARRIER_IMAP_USERNAME'),
        'password_ciphertext' => '',
        'password_nonce' => '',
        'password_tag' => '',
        'limit_count' => $limit,
        'is_enabled' => 1,
        'updated_at' => null,
    ];
}

function carrier_sync_ensure_schema(PDO $pdo): void
{
    carrier_ensure_schema($pdo);
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'source_mailbox', 'VARCHAR(190) NULL');
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'source_uid', 'VARCHAR(190) NULL');
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'source_message_id', 'VARCHAR(255) NULL');
    carrier_add_index_if_missing($pdo, 'carrier_emails', 'idx_carrier_source', '`source_mailbox`, `source_uid`');

    $pdo->exec("CREATE TABLE IF NOT EXISTS carrier_imap_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
        host VARCHAR(190) NOT NULL DEFAULT 'imap.hostinger.com',
        port INT UNSIGNED NOT NULL DEFAULT 993,
        flags VARCHAR(80) NOT NULL DEFAULT '/imap/ssl',
        mailbox VARCHAR(190) NOT NULL DEFAULT 'INBOX',
        username VARCHAR(190) NOT NULL DEFAULT '',
        password_ciphertext TEXT NULL,
        password_nonce VARCHAR(120) NULL,
        password_tag VARCHAR(120) NULL,
        limit_count INT UNSIGNED NOT NULL DEFAULT 50,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        updated_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function carrier_sync_row_to_array($row): array
{
    if (!$row) return [];
    return is_array($row) ? $row : get_object_vars($row);
}

function carrier_sync_saved_settings(PDO $pdo): array
{
    $settings = carrier_sync_default_settings();
    $row = carrier_sync_row_to_array($pdo->query('SELECT * FROM carrier_imap_settings WHERE id = 1 LIMIT 1')->fetch());
    if (!$row) return $settings;
    return array_merge($settings, $row);
}

function carrier_sync_effective_config(PDO $pdo): array
{
    $settings = carrier_sync_saved_settings($pdo);
    $password = carrier_sync_decrypt_password($settings);
    if ($password === '') $password = carrier_sync_env('CARRIER_IMAP_PASSWORD');
    $limit = (int) ($settings['limit_count'] ?? 50);
    if ($limit < 1) $limit = 50;
    if ($limit > 200) $limit = 200;
    return [
        'host' => trim((string) ($settings['host'] ?? 'imap.hostinger.com')) ?: 'imap.hostinger.com',
        'port' => (int) ($settings['port'] ?? 993) ?: 993,
        'flags' => trim((string) ($settings['flags'] ?? '/imap/ssl')) ?: '/imap/ssl',
        'mailbox' => trim((string) ($settings['mailbox'] ?? 'INBOX')) ?: 'INBOX',
        'username' => trim((string) ($settings['username'] ?? '')) ?: carrier_sync_env('CARRIER_IMAP_USERNAME'),
        'password' => $password,
        'limit' => $limit,
        'is_enabled' => (int) ($settings['is_enabled'] ?? 1) === 1,
        'updated_at' => $settings['updated_at'] ?? null,
        'has_saved_password' => trim((string) ($settings['password_ciphertext'] ?? '')) !== '',
    ];
}

function carrier_sync_save_settings(PDO $pdo, int $actorId, array $existing): array
{
    $host = carrier_sync_post_string('host', 'imap.hostinger.com') ?: 'imap.hostinger.com';
    $port = (int) carrier_sync_post_string('port', '993');
    $flags = carrier_sync_post_string('flags', '/imap/ssl') ?: '/imap/ssl';
    $mailbox = carrier_sync_post_string('mailbox', 'INBOX') ?: 'INBOX';
    $username = strtolower(carrier_sync_post_string('username'));
    $limit = (int) carrier_sync_post_string('limit_count', '50');
    $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
    $newPassword = carrier_sync_post_string('password');

    if ($username === '' || !filter_var($username, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter the full Hostinger email address as the username.');
    }
    if ($port < 1 || $port > 65535) throw new RuntimeException('Enter a valid IMAP port.');
    if ($limit < 1) $limit = 50;
    if ($limit > 200) $limit = 200;

    $encrypted = [
        'ciphertext' => (string) ($existing['password_ciphertext'] ?? ''),
        'nonce' => (string) ($existing['password_nonce'] ?? ''),
        'tag' => (string) ($existing['password_tag'] ?? ''),
    ];
    if ($newPassword !== '') {
        $encrypted = carrier_sync_encrypt_password($newPassword);
    } elseif ($encrypted['ciphertext'] === '' && carrier_sync_env('CARRIER_IMAP_PASSWORD') === '') {
        throw new RuntimeException('Enter the Hostinger mailbox password before saving setup.');
    }

    $stmt = $pdo->prepare("INSERT INTO carrier_imap_settings (id, host, port, flags, mailbox, username, password_ciphertext, password_nonce, password_tag, limit_count, is_enabled, updated_by) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE host = VALUES(host), port = VALUES(port), flags = VALUES(flags), mailbox = VALUES(mailbox), username = VALUES(username), password_ciphertext = VALUES(password_ciphertext), password_nonce = VALUES(password_nonce), password_tag = VALUES(password_tag), limit_count = VALUES(limit_count), is_enabled = VALUES(is_enabled), updated_by = VALUES(updated_by), updated_at = NOW()");
    $stmt->execute([$host, $port, $flags, $mailbox, $username, $encrypted['ciphertext'], $encrypted['nonce'], $encrypted['tag'], $limit, $isEnabled, $actorId]);
    return carrier_sync_effective_config($pdo);
}

function carrier_sync_mailbox_string(array $config): string
{
    $mailbox = str_replace(['{', '}'], '', (string) $config['mailbox']);
    return sprintf('{%s:%d%s}%s', $config['host'], $config['port'], $config['flags'], $mailbox);
}

function carrier_sync_decode_header(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '' || !function_exists('imap_mime_header_decode')) return $value;
    $decoded = '';
    foreach (imap_mime_header_decode($value) ?: [] as $part) {
        $text = (string) ($part->text ?? '');
        $charset = strtoupper((string) ($part->charset ?? 'UTF-8'));
        if ($charset !== '' && $charset !== 'DEFAULT' && $charset !== 'UTF-8' && function_exists('iconv')) {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
            if ($converted !== false) $text = $converted;
        }
        $decoded .= $text;
    }
    return trim($decoded);
}

function carrier_sync_decode_body(string $body, int $encoding): string
{
    if ($encoding === 3) return base64_decode($body, true) ?: '';
    if ($encoding === 4) return quoted_printable_decode($body);
    return $body;
}

function carrier_sync_imap_parameter_list($value): array
{
    if (!$value) return [];
    if (is_array($value)) return $value;
    if ($value instanceof Traversable) return iterator_to_array($value);
    if (is_object($value)) return [$value];
    return [];
}

function carrier_sync_part_parameters($part): array
{
    return array_merge(
        carrier_sync_imap_parameter_list($part->dparameters ?? []),
        carrier_sync_imap_parameter_list($part->parameters ?? [])
    );
}

function carrier_sync_part_filename($part): string
{
    foreach (carrier_sync_part_parameters($part) as $parameter) {
        $attribute = strtolower((string) ($parameter->attribute ?? ''));
        if (!in_array($attribute, ['filename', 'name'], true)) continue;
        $name = carrier_sync_decode_header((string) ($parameter->value ?? ''));
        if ($name !== '') return $name;
    }
    return '';
}

function carrier_sync_part_charset($part): string
{
    foreach (carrier_sync_part_parameters($part) as $parameter) {
        if (strtolower((string) ($parameter->attribute ?? '')) !== 'charset') continue;
        return strtoupper(trim((string) ($parameter->value ?? '')));
    }
    return '';
}

function carrier_sync_body_to_utf8(string $body, string $charset): string
{
    $charset = strtoupper($charset);
    if ($charset !== '' && $charset !== 'DEFAULT' && $charset !== 'UTF-8' && function_exists('iconv')) {
        $converted = @iconv($charset, 'UTF-8//IGNORE', $body);
        if ($converted !== false) return $converted;
    }
    return $body;
}

function carrier_sync_collect_text_parts($imap, int $uid, $part, string $partNumber, array &$plainParts, array &$htmlParts): void
{
    if (!$part) return;
    if (isset($part->parts) && is_array($part->parts)) {
        foreach ($part->parts as $index => $childPart) {
            $childNumber = $partNumber === '' ? (string) ($index + 1) : $partNumber . '.' . ($index + 1);
            carrier_sync_collect_text_parts($imap, $uid, $childPart, $childNumber, $plainParts, $htmlParts);
        }
        return;
    }

    if (carrier_sync_part_filename($part) !== '') return;
    $type = (int) ($part->type ?? 0);
    $subtype = strtolower((string) ($part->subtype ?? ''));
    if ($type !== 0 || !in_array($subtype, ['plain', 'html'], true)) return;

    $body = $partNumber !== '' ? imap_fetchbody($imap, $uid, $partNumber, FT_UID | FT_PEEK) : imap_body($imap, $uid, FT_UID | FT_PEEK);
    if (!is_string($body) || trim($body) === '') return;
    $decoded = carrier_sync_decode_body($body, (int) ($part->encoding ?? 0));
    $decoded = carrier_sync_body_to_utf8($decoded, carrier_sync_part_charset($part));
    if (trim($decoded) === '') return;
    if ($subtype === 'plain') {
        $plainParts[] = $decoded;
    } else {
        $htmlParts[] = $decoded;
    }
}

function carrier_sync_fetch_body($imap, int $uid): string
{
    $structure = imap_fetchstructure($imap, $uid, FT_UID);
    $plainParts = [];
    $htmlParts = [];

    if ($structure) carrier_sync_collect_text_parts($imap, $uid, $structure, '', $plainParts, $htmlParts);

    $body = '';
    if ($plainParts) {
        $body = implode("\n\n", array_map('trim', $plainParts));
    } elseif ($htmlParts) {
        $body = implode("\n\n", array_map(static fn($part) => html_entity_decode(strip_tags($part), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $htmlParts));
    }

    if (trim($body) === '') {
        $raw = imap_body($imap, $uid, FT_UID | FT_PEEK);
        if (is_string($raw)) $body = carrier_sync_decode_body($raw, (int) ($structure ? ($structure->encoding ?? 0) : 0));
    }

    $text = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return preg_replace('/[ \t]+/', ' ', $text) ?? $text;
}

function carrier_sync_sender($header): array
{
    $address = $header->from[0] ?? null;
    if (!$address) return ['name' => 'Unknown sender', 'email' => ''];
    $mailbox = (string) ($address->mailbox ?? '');
    $host = (string) ($address->host ?? '');
    $email = $mailbox !== '' && $host !== '' ? strtolower($mailbox . '@' . $host) : '';
    $name = carrier_sync_decode_header((string) ($address->personal ?? ''));
    if ($name === '') $name = $email !== '' ? $email : 'Unknown sender';
    return ['name' => substr($name, 0, 190), 'email' => substr($email, 0, 190)];
}

function carrier_sync_collect_attachment_names($part, array &$names): void
{
    if (!$part) return;
    $name = carrier_sync_part_filename($part);
    if ($name !== '') $names[] = $name;
    foreach (($part->parts ?? []) as $childPart) {
        carrier_sync_collect_attachment_names($childPart, $names);
    }
}

function carrier_sync_attachments($structure): string
{
    $names = [];
    carrier_sync_collect_attachment_names($structure, $names);
    $names = array_values(array_unique(array_filter($names, static fn($name) => trim((string) $name) !== '')));
    if (!$names) return '';
    $summary = implode(', ', array_slice($names, 0, 8));
    if (count($names) > 8) $summary .= ', +' . (count($names) - 8) . ' more';
    return substr($summary, 0, 2000);
}

function carrier_sync_already_imported(PDO $pdo, string $sourceMailbox, string $uid): bool
{
    $stmt = $pdo->prepare('SELECT id FROM carrier_emails WHERE source_mailbox = ? AND source_uid = ? LIMIT 1');
    $stmt->execute([$sourceMailbox, $uid]);
    return (bool) $stmt->fetchColumn();
}

function carrier_sync_insert(PDO $pdo, array $payload, int $actorId): int
{
    $stmt = $pdo->prepare('INSERT INTO carrier_emails (carrier_name, carrier_email, subject, message, preview_text, status, is_read, is_starred, priority, attachments, received_at, source_mailbox, source_uid, source_message_id, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $payload['carrier_name'],
        $payload['carrier_email'],
        $payload['subject'],
        $payload['message'],
        $payload['preview_text'],
        'New',
        $payload['is_read'],
        0,
        'Normal',
        $payload['attachments'],
        $payload['received_at'],
        $payload['source_mailbox'],
        $payload['source_uid'],
        $payload['source_message_id'],
        $actorId,
        $actorId,
    ]);
    return (int) $pdo->lastInsertId();
}

function carrier_sync_run(PDO $pdo, array $config, int $actorId): array
{
    if (!$config['is_enabled']) throw new RuntimeException('Carrier IMAP setup is disabled. Enable it before syncing.');
    if (!extension_loaded('imap') || !function_exists('imap_open')) {
        throw new RuntimeException('PHP IMAP is not enabled. In Hostinger, open PHP Configuration and enable the imap extension.');
    }
    if ($config['username'] === '' || $config['password'] === '') {
        throw new RuntimeException('Carrier IMAP setup is incomplete. Enter the Hostinger email address and password, then save setup.');
    }

    $imap = @imap_open(carrier_sync_mailbox_string($config), $config['username'], $config['password']);
    if (!$imap) {
        $errors = function_exists('imap_errors') ? imap_errors() : [];
        throw new RuntimeException('Carrier could not connect to Hostinger IMAP. Check the mailbox address, password, and IMAP settings. ' . trim(implode(' ', $errors ?: [])));
    }

    $imported = 0;
    $skipped = 0;
    $sourceMailbox = $config['username'] . ':' . $config['mailbox'];
    try {
        $uids = imap_search($imap, 'ALL', SE_UID);
        if (!$uids) return ['imported' => 0, 'skipped' => 0];
        rsort($uids, SORT_NUMERIC);
        $uids = array_slice($uids, 0, (int) $config['limit']);

        foreach ($uids as $uidValue) {
            $uid = (string) $uidValue;
            $uidNumber = (int) $uidValue;
            if ($uidNumber <= 0) {
                $skipped++;
                continue;
            }
            if (carrier_sync_already_imported($pdo, $sourceMailbox, $uid)) {
                $skipped++;
                continue;
            }
            $overviewList = imap_fetch_overview($imap, (string) $uidNumber, FT_UID);
            $overview = $overviewList[0] ?? null;
            if (!$overview) {
                $skipped++;
                continue;
            }
            $header = imap_headerinfo($imap, (int) ($overview->msgno ?? 0));
            $sender = carrier_sync_sender($header);
            $subject = carrier_sync_decode_header((string) ($overview->subject ?? 'No subject')) ?: 'No subject';
            $body = carrier_sync_fetch_body($imap, $uidNumber);
            if ($body === '') $body = '(No readable message body imported.)';
            $receivedAt = strtotime((string) ($overview->date ?? ''));
            $structure = imap_fetchstructure($imap, $uidNumber, FT_UID);
            carrier_sync_insert($pdo, [
                'carrier_name' => $sender['name'],
                'carrier_email' => $sender['email'],
                'subject' => substr($subject, 0, 255),
                'message' => substr($body, 0, 12000),
                'preview_text' => carrier_preview($body),
                'is_read' => !empty($overview->seen) ? 1 : 0,
                'attachments' => $structure ? carrier_sync_attachments($structure) : '',
                'received_at' => $receivedAt ? gmdate('Y-m-d H:i:s', $receivedAt) : gmdate('Y-m-d H:i:s'),
                'source_mailbox' => $sourceMailbox,
                'source_uid' => $uid,
                'source_message_id' => substr(trim((string) ($overview->message_id ?? '')), 0, 255),
            ], $actorId);
            $imported++;
        }
    } finally {
        imap_close($imap);
    }

    return ['imported' => $imported, 'skipped' => $skipped];
}

$displayName = trim((string) ($user['full_name'] ?: $user['email']));
$errors = [];
$notice = null;
$pdo = null;

try {
    $pdo = db();
    carrier_sync_ensure_schema($pdo);
    $settings = carrier_sync_saved_settings($pdo);
    $config = carrier_sync_effective_config($pdo);
} catch (Throwable $error) {
    $errors[] = 'Carrier cannot connect to the portal database yet. Check the database config or run /update.php after deployment.';
    error_log('Carrier sync database unavailable: ' . $error->getMessage());
    $settings = carrier_sync_default_settings();
    $config = array_merge($settings, ['password' => '', 'limit' => (int) $settings['limit_count'], 'has_saved_password' => false]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo instanceof PDO) {
        if (carrier_sync_return_to_carrier()) {
            carrier_sync_redirect_to_carrier(null, 'Carrier database tables are not ready.', ['open' => null]);
        }
        $errors[] = 'Carrier database tables are not ready.';
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        if (carrier_sync_return_to_carrier()) {
            carrier_sync_redirect_to_carrier(null, 'Your session expired. Refresh the page and try again.', ['open' => null]);
        }
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            $action = carrier_sync_post_string('action');
            $savedSettings = false;
            if ($action === 'save_settings' || $action === 'save_and_sync') {
                $config = carrier_sync_save_settings($pdo, (int) $user['id'], $settings);
                $settings = carrier_sync_saved_settings($pdo);
                $notice = 'Carrier mail setup saved.';
                $savedSettings = true;
                carrier_log_activity($pdo, (int) $user['id'], 'carrier imap setup updated', null, $config['username']);
            }
            if ($action === 'sync_mail' || $action === 'save_and_sync') {
                $result = carrier_sync_run($pdo, $config, (int) $user['id']);
                carrier_log_activity($pdo, (int) $user['id'], 'carrier imap synced', null, 'Imported ' . $result['imported'] . ', skipped ' . $result['skipped']);
                $message = 'Carrier mail sync complete. Imported ' . $result['imported'] . ' new email' . ((int) $result['imported'] === 1 ? '' : 's') . '.';
                if ($savedSettings) $message = 'Carrier mail setup saved. ' . $message;
                if (carrier_sync_return_to_carrier()) {
                    carrier_sync_redirect_to_carrier($message, null, ['open' => null]);
                }
                $_SESSION['carrier_notice'] = $message;
                redirect_to('/carrier');
            }
            if ($action === 'save_settings' && carrier_sync_return_to_carrier()) {
                carrier_sync_redirect_to_carrier('Carrier mail setup saved.', null);
            }
        } catch (Throwable $error) {
            if (carrier_sync_return_to_carrier()) {
                carrier_sync_redirect_to_carrier(null, $error->getMessage(), ['open' => null]);
            }
            $errors[] = $error->getMessage();
            error_log('Carrier IMAP setup/sync failed: ' . $error->getMessage());
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Carrier Mail Setup | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons">
    <link rel="stylesheet" href="/assets/dashboard.css?v=20260621-automation">
    <link rel="stylesheet" href="/assets/login.css?v=20260620-php-install">
  </head>
  <body class="dashboard-body">
    <main class="login-page">
      <section class="login-hero" aria-labelledby="carrier-sync-heading">
        <a class="login-brand-logo" href="/carrier" aria-label="Back to Carrier">OLIGARCHY</a>
        <form class="login-panel" method="post">
          <div class="login-panel-heading">
            <p class="eyebrow">Carrier</p>
            <h1 id="carrier-sync-heading">Set up Hostinger mail</h1>
            <p>Enter the Hostinger mailbox settings once, save them, then import messages into Carrier whenever you need a refresh.</p>
          </div>
          <?php if ($notice): ?><div class="form-alert is-visible is-success"><?= e($notice) ?></div><?php endif; ?>
          <?php foreach ($errors as $error): ?><div class="form-alert is-visible is-error"><?= e($error) ?></div><?php endforeach; ?>
          <?php if (!extension_loaded('imap')): ?><div class="form-alert is-visible is-error">PHP IMAP is not enabled on this server yet.</div><?php endif; ?>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <label>Email address<input name="username" type="email" autocomplete="username" value="<?= e($config['username'] ?? '') ?>" placeholder="name@yourdomain.com" required></label>
          <label>Password<input name="password" type="password" autocomplete="current-password" placeholder="<?= !empty($config['has_saved_password']) ? 'Saved - leave blank to keep' : 'Mailbox password' ?>"></label>
          <label>IMAP host<input name="host" type="text" value="<?= e($config['host'] ?? 'imap.hostinger.com') ?>" required></label>
          <label>Port<input name="port" type="number" min="1" max="65535" value="<?= e((string) ($config['port'] ?? 993)) ?>" required></label>
          <label>Security flags<input name="flags" type="text" value="<?= e($config['flags'] ?? '/imap/ssl') ?>" required></label>
          <label>Mailbox<input name="mailbox" type="text" value="<?= e($config['mailbox'] ?? 'INBOX') ?>" required></label>
          <label>Messages to check<input name="limit_count" type="number" min="1" max="200" value="<?= e((string) ($config['limit'] ?? 50)) ?>" required></label>
          <label class="login-note"><input name="is_enabled" type="checkbox" value="1" <?= !empty($config['is_enabled']) ? 'checked' : '' ?>> Enable this Carrier mail setup</label>
          <button class="button primary login-submit" type="submit" name="action" value="save_settings">Save setup</button>
          <button class="button primary login-submit" type="submit" name="action" value="save_and_sync">Save and sync now</button>
          <button class="button secondary login-submit" type="submit" name="action" value="sync_mail">Sync using saved setup</button>
          <p class="login-note">Defaults match Hostinger IMAP: imap.hostinger.com, port 993, SSL, mailbox INBOX. Passwords are stored encrypted in the portal database. <a href="/carrier">Back to Carrier</a>.</p>
        </form>
      </section>
    </main>
  </body>
</html>
