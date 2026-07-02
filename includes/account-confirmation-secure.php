<?php
declare(strict_types=1);

function account_confirmation_base_url(): string
{
    foreach (['PORTAL_BASE_URL', 'APP_URL'] as $key) {
        $value = trim((string) getenv($key));
        if ($value !== '' && preg_match('#^https?://#i', $value)) {
            return rtrim($value, '/');
        }
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'sandbox.oligarchyservices.com'));
    $host = preg_replace('/[^a-z0-9.\-:]/', '', $host) ?: 'sandbox.oligarchyservices.com';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    return ($https ? 'https://' : 'http://') . $host;
}

function account_confirmation_url(string $path): string
{
    return account_confirmation_base_url() . '/' . ltrim($path, '/');
}

function account_confirmation_from_address(): string
{
    $from = trim((string) getenv('PORTAL_MAIL_FROM'));
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'no-reply@sandbox.oligarchyservices.com';
    }

    return $from;
}

function account_confirmation_from_header(): string
{
    return 'Oligarchy Services <' . account_confirmation_from_address() . '>';
}

function account_confirmation_generate_temporary_password(): string
{
    return bin2hex(random_bytes(24));
}

function account_confirmation_subject(): string
{
    return 'Confirm your Oligarchy Services account';
}

function account_confirmation_message_parts(string $name, string $token): array
{
    $confirmUrl = account_confirmation_url('/account-confirmation.php?token=' . rawurlencode($token));
    $displayName = $name !== '' ? $name : 'there';
    $escapedName = e($displayName);
    $escapedConfirmUrl = e($confirmUrl);

    $text = "Hi {$displayName},\n\n"
        . "Your Oligarchy Services account has been created. Confirm your email address and create your password here:\n\n"
        . "{$confirmUrl}\n\n"
        . "This confirmation link expires in 48 hours.\n\n"
        . "Oligarchy Services\n";

    $html = '<p style="Margin:0 0 18px 0;">Hi ' . $escapedName . ',</p>'
        . '<p style="Margin:0 0 18px 0;">Your Oligarchy Services account has been created. Confirm your email address and create your password before signing in.</p>'
        . '<p style="Margin:0 0 20px 0;"><a href="' . $escapedConfirmUrl . '" style="display:inline-block; background-color:#d2222a; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:bold; line-height:20px; text-decoration:none; padding:12px 18px; border-radius:6px;">Confirm account</a></p>'
        . '<p style="Margin:0 0 18px 0; color:#5d6b7a;">This confirmation link expires in 48 hours.</p>';

    return ['text' => $text, 'html' => $html];
}

function account_confirmation_send_via_php_mail(string $email, string $subject, array $parts): bool
{
    $alternativeBoundary = 'oligarchy-alternative-' . bin2hex(random_bytes(12));
    $headers = [
        'From: ' . account_confirmation_from_header(),
        'Reply-To: ' . account_confirmation_from_header(),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $alternativeBoundary . '"',
        'X-Mailer: Oligarchy Services Portal',
    ];

    $htmlBody = '<!doctype html><html><body style="Margin:0; padding:24px; background-color:#f4f7fb; color:#263238; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:23px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; background-color:#ffffff; border:1px solid #dfe7f0; border-radius:8px;"><tr><td style="background-color:#101820; color:#ffffff; padding:22px 26px; font-size:20px; font-weight:bold;">Oligarchy Services</td></tr><tr><td style="padding:26px;">'
        . $parts['html']
        . '</td></tr></table></td></tr></table></body></html>';

    $body = "--{$alternativeBoundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $parts['text'] . "\r\n\r\n"
        . "--{$alternativeBoundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $htmlBody . "\r\n\r\n"
        . "--{$alternativeBoundary}--\r\n";

    $envelopeSender = account_confirmation_from_address();
    return mail($email, $subject, $body, implode("\r\n", $headers), '-f' . $envelopeSender);
}

function account_confirmation_mail_trace_column_exists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute(['mail_trace', $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function account_confirmation_mail_trace_add_column_if_missing(PDO $pdo, string $column, string $definition): void
{
    if (!account_confirmation_mail_trace_column_exists($pdo, $column)) {
        $pdo->exec('ALTER TABLE mail_trace ADD COLUMN `' . $column . '` ' . $definition);
    }
}

function account_confirmation_mail_trace_index_exists(PDO $pdo, string $index): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute(['mail_trace', $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function account_confirmation_mail_trace_add_index_if_missing(PDO $pdo, string $index, string $columns): void
{
    if (!account_confirmation_mail_trace_index_exists($pdo, $index)) {
        $pdo->exec('ALTER TABLE mail_trace ADD INDEX `' . $index . '` (' . $columns . ')');
    }
}

function account_confirmation_mail_trace_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_trace (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        recipient VARCHAR(190) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        provider VARCHAR(80) NOT NULL,
        status VARCHAR(40) NOT NULL,
        message TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mail_trace_created (created_at),
        INDEX idx_mail_trace_recipient (recipient),
        INDEX idx_mail_trace_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    account_confirmation_mail_trace_add_column_if_missing($pdo, 'recipient', "VARCHAR(190) NOT NULL DEFAULT ''");
    account_confirmation_mail_trace_add_column_if_missing($pdo, 'subject', "VARCHAR(255) NOT NULL DEFAULT ''");
    account_confirmation_mail_trace_add_column_if_missing($pdo, 'provider', "VARCHAR(80) NOT NULL DEFAULT ''");
    account_confirmation_mail_trace_add_column_if_missing($pdo, 'status', "VARCHAR(40) NOT NULL DEFAULT ''");
    account_confirmation_mail_trace_add_column_if_missing($pdo, 'message', 'TEXT NULL');
    account_confirmation_mail_trace_add_column_if_missing($pdo, 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    account_confirmation_mail_trace_add_index_if_missing($pdo, 'idx_mail_trace_created', '`created_at`');
    account_confirmation_mail_trace_add_index_if_missing($pdo, 'idx_mail_trace_recipient', '`recipient`');
    account_confirmation_mail_trace_add_index_if_missing($pdo, 'idx_mail_trace_status', '`status`');
}

function account_confirmation_record_mail_trace(string $email, string $subject, string $provider, bool $sent, string $message = ''): void
{
    try {
        $pdo = db();
        account_confirmation_mail_trace_ensure_schema($pdo);
        $stmt = $pdo->prepare('INSERT INTO mail_trace (recipient, subject, provider, status, message) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$email, $subject, $provider, $sent ? 'sent' : 'failed', $message]);
    } catch (Throwable $error) {
        error_log('Mail trace skipped: ' . $error->getMessage());
    }
}

function account_confirmation_send_email(string $email, string $name, string $token, string $temporaryPassword = ''): bool
{
    $subject = account_confirmation_subject();

    try {
        $parts = account_confirmation_message_parts($name, $token);
        $phpMailResult = account_confirmation_send_via_php_mail($email, $subject, $parts);
        account_confirmation_record_mail_trace($email, $subject, 'php-mail', $phpMailResult, $phpMailResult ? 'Accepted by PHP mail without emailing a temporary password.' : 'PHP mail() returned false.');
        return $phpMailResult;
    } catch (Throwable $error) {
        account_confirmation_record_mail_trace($email, $subject, 'php-mail', false, 'Send failed before completion: ' . $error->getMessage());
        throw $error;
    }
}

function account_confirmation_issue_invite(PDO $pdo, int $userId): array
{
    $traceEmail = 'user #' . $userId;
    $subject = account_confirmation_subject();

    try {
        require_once __DIR__ . '/password-change.php';

        password_change_ensure_schema($pdo);

        $stmt = $pdo->prepare('SELECT id, email, full_name, email_confirmed_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $createdUser = $stmt->fetch();
        if (!$createdUser) {
            throw new RuntimeException('Choose a valid user account.');
        }

        $traceEmail = (string) $createdUser['email'];
        if (!empty($createdUser['email_confirmed_at'])) {
            throw new RuntimeException('That user is already confirmed.');
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $lockedPassword = account_confirmation_generate_temporary_password();
        $update = $pdo->prepare('UPDATE users SET password_hash = ?, email_confirmation_token_hash = ?, email_confirmation_expires_at = DATE_ADD(NOW(), INTERVAL 2 DAY), password_change_required = 0, updated_at = NOW() WHERE id = ?');
        $update->execute([password_hash($lockedPassword, PASSWORD_DEFAULT), $tokenHash, $userId]);

        $sent = account_confirmation_send_email($traceEmail, (string) ($createdUser['full_name'] ?? ''), $token);

        return ['email' => $traceEmail, 'sent' => $sent];
    } catch (Throwable $error) {
        account_confirmation_record_mail_trace($traceEmail, $subject, 'invite-generation', false, 'Invite failed before PHP mail: ' . $error->getMessage());
        throw $error;
    }
}

function account_confirmation_flash_keys(): array
{
    return request_path() === '/users.php'
        ? ['notice' => 'users_notice', 'error' => 'users_error']
        : ['notice' => 'dashboard_notice', 'error' => 'dashboard_error'];
}

function account_confirmation_existing_error(): bool
{
    $keys = account_confirmation_flash_keys();
    return !empty($_SESSION[$keys['error']]) || !empty($_SESSION['dashboard_error']) || !empty($_SESSION['users_error']);
}

function account_confirmation_flash_notice(string $message): void
{
    $keys = account_confirmation_flash_keys();
    $_SESSION[$keys['notice']] = $message;
}

function account_confirmation_flash_error(string $message): void
{
    $keys = account_confirmation_flash_keys();
    unset($_SESSION[$keys['notice']]);
    $_SESSION[$keys['error']] = $message;
}

function account_confirmation_register_dashboard_hook(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }

    $registered = true;
    register_shutdown_function('account_confirmation_finalize_dashboard_create');
}

function account_confirmation_finalize_dashboard_create(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    if (trim((string) ($_POST['action'] ?? '')) !== 'save_user') {
        return;
    }
    if ((int) ($_POST['user_id'] ?? 0) !== 0) {
        return;
    }
    if (account_confirmation_existing_error()) {
        return;
    }

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    try {
        require_once __DIR__ . '/installer.php';
        require_once __DIR__ . '/password-change.php';

        $pdo = db();
        create_or_update_schema($pdo);
        password_change_ensure_schema($pdo);

        $stmt = $pdo->prepare('SELECT id, email_confirmed_at, email_confirmation_token_hash FROM users WHERE email = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$email]);
        $createdUser = $stmt->fetch();
        if (!$createdUser || !empty($createdUser['email_confirmed_at']) || !empty($createdUser['email_confirmation_token_hash'])) {
            return;
        }

        $invite = account_confirmation_issue_invite($pdo, (int) $createdUser['id']);
        if ($invite['sent']) {
            account_confirmation_flash_notice('User created. Confirmation email sent to ' . $invite['email'] . '.');
        } else {
            account_confirmation_flash_error('User created, but the confirmation email could not be sent. Check Mail Trace for the PHP mail result and confirm Hostinger PHP mail is enabled for the sender address.');
        }
    } catch (Throwable $error) {
        error_log('Account confirmation setup failed: ' . $error->getMessage());
        account_confirmation_flash_error('User created, but account confirmation setup failed. Check Mail Trace and the PHP error log.');
    }
}
