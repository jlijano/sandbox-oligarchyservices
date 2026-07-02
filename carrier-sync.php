<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/carrier.php';

$user = require_login();
$role = strtolower((string) ($user['role'] ?? 'client'));
if (!in_array($role, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo 'Only admins and editors can sync Carrier mail.';
    exit;
}

function carrier_sync_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : trim((string) $value);
}

function carrier_sync_config(): array
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
        'password' => carrier_sync_env('CARRIER_IMAP_PASSWORD'),
        'limit' => $limit,
    ];
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

function carrier_sync_fetch_body($imap, int $uid): string
{
    $structure = imap_fetchstructure($imap, (string) $uid, FT_UID);
    $body = '';
    $encoding = (int) ($structure->encoding ?? 0);

    if (isset($structure->parts) && is_array($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            $subtype = strtolower((string) ($part->subtype ?? ''));
            if ($subtype !== 'plain' && $subtype !== 'html') continue;
            $section = (string) ($index + 1);
            $partBody = imap_fetchbody($imap, (string) $uid, $section, FT_UID | FT_PEEK);
            if (is_string($partBody) && trim($partBody) !== '') {
                $body = carrier_sync_decode_body($partBody, (int) ($part->encoding ?? 0));
                break;
            }
        }
    }

    if (trim($body) === '') {
        $raw = imap_body($imap, (string) $uid, FT_UID | FT_PEEK);
        if (is_string($raw)) $body = carrier_sync_decode_body($raw, $encoding);
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

function carrier_sync_attachments($structure): string
{
    $names = [];
    foreach (($structure->parts ?? []) as $part) {
        foreach (array_merge($part->dparameters ?? [], $part->parameters ?? []) as $parameter) {
            $attribute = strtolower((string) ($parameter->attribute ?? ''));
            if (!in_array($attribute, ['filename', 'name'], true)) continue;
            $name = carrier_sync_decode_header((string) ($parameter->value ?? ''));
            if ($name !== '') $names[] = $name;
        }
    }
    $names = array_values(array_unique($names));
    if (!$names) return '';
    $summary = implode(', ', array_slice($names, 0, 8));
    if (count($names) > 8) $summary .= ', +' . (count($names) - 8) . ' more';
    return substr($summary, 0, 2000);
}

function carrier_sync_ensure_schema(PDO $pdo): void
{
    carrier_ensure_schema($pdo);
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'source_mailbox', 'VARCHAR(190) NULL');
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'source_uid', 'VARCHAR(190) NULL');
    carrier_add_column_if_missing($pdo, 'carrier_emails', 'source_message_id', 'VARCHAR(255) NULL');
    carrier_add_index_if_missing($pdo, 'carrier_emails', 'idx_carrier_source', '`source_mailbox`, `source_uid`');
}

function carrier_sync_already_imported(PDO $pdo, string $mailbox, string $uid): bool
{
    $stmt = $pdo->prepare('SELECT id FROM carrier_emails WHERE source_mailbox = ? AND source_uid = ? LIMIT 1');
    $stmt->execute([$mailbox, $uid]);
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

function carrier_sync_run(PDO $pdo, int $actorId): array
{
    if (!extension_loaded('imap') || !function_exists('imap_open')) {
        throw new RuntimeException('PHP IMAP is not enabled. In Hostinger, open PHP Configuration and enable the imap extension.');
    }

    $config = carrier_sync_config();
    if ($config['username'] === '' || $config['password'] === '') {
        throw new RuntimeException('Carrier IMAP is not configured. Set CARRIER_IMAP_USERNAME and CARRIER_IMAP_PASSWORD in the Hostinger environment.');
    }

    carrier_sync_ensure_schema($pdo);
    $imap = @imap_open(carrier_sync_mailbox_string($config), $config['username'], $config['password']);
    if (!$imap) {
        $errors = function_exists('imap_errors') ? imap_errors() : [];
        throw new RuntimeException('Carrier could not connect to Hostinger IMAP. Check the mailbox address, password, and IMAP settings. ' . trim(implode(' ', $errors ?: [])));
    }

    $imported = 0;
    $skipped = 0;
    try {
        $uids = imap_search($imap, 'ALL', SE_UID);
        if (!$uids) return ['imported' => 0, 'skipped' => 0];
        rsort($uids, SORT_NUMERIC);
        $uids = array_slice($uids, 0, (int) $config['limit']);

        foreach ($uids as $uidValue) {
            $uid = (string) $uidValue;
            if (carrier_sync_already_imported($pdo, (string) $config['mailbox'], $uid)) {
                $skipped++;
                continue;
            }
            $overviewList = imap_fetch_overview($imap, $uid, FT_UID);
            $overview = $overviewList[0] ?? null;
            if (!$overview) {
                $skipped++;
                continue;
            }
            $header = imap_headerinfo($imap, (int) ($overview->msgno ?? 0));
            $sender = carrier_sync_sender($header);
            $subject = carrier_sync_decode_header((string) ($overview->subject ?? 'No subject')) ?: 'No subject';
            $body = carrier_sync_fetch_body($imap, (int) $uidValue);
            if ($body === '') $body = '(No readable message body imported.)';
            $receivedAt = strtotime((string) ($overview->date ?? ''));
            $structure = imap_fetchstructure($imap, $uid, FT_UID);
            $preview = carrier_preview($body);
            carrier_sync_insert($pdo, [
                'carrier_name' => $sender['name'],
                'carrier_email' => $sender['email'],
                'subject' => substr($subject, 0, 255),
                'message' => substr($body, 0, 12000),
                'preview_text' => $preview,
                'is_read' => !empty($overview->seen) ? 1 : 0,
                'attachments' => $structure ? carrier_sync_attachments($structure) : '',
                'received_at' => $receivedAt ? gmdate('Y-m-d H:i:s', $receivedAt) : gmdate('Y-m-d H:i:s'),
                'source_mailbox' => (string) $config['mailbox'],
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
$initials = strtoupper(substr($displayName, 0, 1));
$config = carrier_sync_config();
$errors = [];
$notice = null;

try {
    $pdo = db();
    carrier_sync_ensure_schema($pdo);
} catch (Throwable $error) {
    $errors[] = 'Carrier cannot connect to the portal database yet. Check the database config or run /update.php after deployment.';
    error_log('Carrier sync database unavailable: ' . $error->getMessage());
    $pdo = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo instanceof PDO) {
        $errors[] = 'Carrier database tables are not ready.';
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            $result = carrier_sync_run($pdo, (int) $user['id']);
            carrier_log_activity($pdo, (int) $user['id'], 'carrier imap synced', null, 'Imported ' . $result['imported'] . ', skipped ' . $result['skipped']);
            $_SESSION['carrier_notice'] = 'Carrier mail sync complete. Imported ' . $result['imported'] . ' new email' . ((int) $result['imported'] === 1 ? '' : 's') . '.';
            redirect_to('/carrier');
        } catch (Throwable $error) {
            $errors[] = $error->getMessage();
            error_log('Carrier IMAP sync failed: ' . $error->getMessage());
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
    <title>Sync Carrier Mail | Oligarchy Services</title>
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
            <h1 id="carrier-sync-heading">Sync Hostinger mail</h1>
            <p>Import the newest Hostinger mailbox messages into Carrier. Imported messages are matched by mailbox and IMAP UID so repeated syncs skip duplicates.</p>
          </div>
          <?php foreach ($errors as $error): ?><div class="form-alert is-visible is-error"><?= e($error) ?></div><?php endforeach; ?>
          <?php if (!extension_loaded('imap')): ?><div class="form-alert is-visible is-error">PHP IMAP is not enabled on this server yet.</div><?php endif; ?>
          <?php if ($config['username'] === '' || $config['password'] === ''): ?><div class="form-alert is-visible is-error">Set CARRIER_IMAP_USERNAME and CARRIER_IMAP_PASSWORD in Hostinger before syncing.</div><?php endif; ?>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <button class="button primary login-submit" type="submit">Sync Hostinger Mail</button>
          <p class="login-note">Using <?= e($config['host']) ?>:<?= e((string) $config['port']) ?><?= e($config['flags']) ?>, mailbox <?= e($config['mailbox']) ?>, checking up to <?= e((string) $config['limit']) ?> messages. <a href="/carrier">Back to Carrier</a>.</p>
        </form>
      </section>
    </main>
  </body>
</html>
