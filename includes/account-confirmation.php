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
        $from = 'sentinel@oligarchyservices.com';
    }

    return $from;
}

function account_confirmation_from_header(): string
{
    return 'Oligarchy Services <' . account_confirmation_from_address() . '>';
}

function account_confirmation_mail_additional_parameters(): string
{
    $from = account_confirmation_from_address();
    if (!preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $from)) {
        return '';
    }

    return '-f' . $from;
}

function account_confirmation_generate_temporary_password(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $password = '';
    $max = strlen($alphabet) - 1;
    for ($index = 0; $index < 18; $index++) {
        $password .= $alphabet[random_int(0, $max)];
    }

    return $password;
}

function account_confirmation_message_parts(string $name, string $token, string $temporaryPassword): array
{
    $confirmUrl = account_confirmation_url('/account-confirmation.php?token=' . rawurlencode($token));
    $loginUrl = account_confirmation_url('/login.html');
    $displayName = $name !== '' ? $name : 'there';
    $escapedName = e($displayName);
    $escapedConfirmUrl = e($confirmUrl);
    $escapedLoginUrl = e($loginUrl);
    $escapedPassword = e($temporaryPassword);

    $text = "Hi {$displayName},\n\n"
        . "Your Oligarchy Services account has been created. Confirm your email address before signing in:\n\n"
        . "{$confirmUrl}\n\n"
        . "Temporary password:\n{$temporaryPassword}\n\n"
        . "After confirming, log in here with the temporary password, then create your own password before opening the dashboard:\n\n"
        . "{$loginUrl}\n\n"
        . "This confirmation link expires in 48 hours.\n";

    $html = '<p style="Margin:0 0 18px 0;">Hi ' . $escapedName . ',</p>'
        . '<p style="Margin:0 0 18px 0;">Your Oligarchy Services account has been created. Confirm your email address before signing in.</p>'
        . '<p style="Margin:0 0 20px 0;"><a href="' . $escapedConfirmUrl . '" style="display:inline-block; background-color:#d2222a; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:bold; line-height:20px; text-decoration:none; padding:12px 18px; border-radius:6px;">Confirm account</a></p>'
        . '<p style="Margin:0 0 8px 0;"><strong>Temporary password</strong></p>'
        . '<p style="Margin:0 0 18px 0; font-family:Consolas, Monaco, monospace; font-size:16px; line-height:22px; background-color:#eef3f8; color:#101820; padding:12px 14px; border-radius:6px;">' . $escapedPassword . '</p>'
        . '<p style="Margin:0 0 18px 0;">After confirming, log in with the temporary password, then create your own password before opening the dashboard.</p>'
        . '<p style="Margin:0 0 18px 0;"><a href="' . $escapedLoginUrl . '" style="color:#d2222a; font-weight:bold;">Open login</a></p>'
        . '<p style="Margin:0 0 18px 0; color:#5d6b7a;">This confirmation link expires in 48 hours.</p>';

    return ['text' => $text, 'html' => $html];
}

function account_confirmation_send_via_php_mail(string $email, string $subject, array $parts): bool
{
    $boundary = 'oligarchy-' . bin2hex(random_bytes(12));
    $headers = [
        'From: ' . account_confirmation_from_header(),
        'Reply-To: ' . account_confirmation_from_header(),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: Oligarchy Services Portal',
    ];

    $body = "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $parts['text'] . "\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . '<!doctype html><html><body style="Margin:0; padding:24px; background-color:#f4f7fb; color:#263238; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:23px;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px; background-color:#ffffff; border:1px solid #dfe7f0; border-radius:8px;"><tr><td style="background-color:#101820; color:#ffffff; padding:22px 26px; font-size:20px; font-weight:bold;">Oligarchy Services</td></tr><tr><td style="padding:26px;">'
        . $parts['html']
        . '</td></tr></table></td></tr></table></body></html>'
        . "\r\n--{$boundary}--\r\n";

    $additionalParameters = account_confirmation_mail_additional_parameters();
    if ($additionalParameters !== '') {
        return mail($email, $subject, $body, implode("\r\n", $headers), $additionalParameters);
    }

    return mail($email, $subject, $body, implode("\r\n", $headers));
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

function account_confirmation_send_email(string $email, string $name, string $token, string $temporaryPassword): bool
{
    $subject = 'Confirm your Oligarchy Services account';
    $parts = account_confirmation_message_parts($name, $token, $temporaryPassword);
    $phpMailResult = account_confirmation_send_via_php_mail($email, $subject, $parts);
    account_confirmation_record_mail_trace($email, $subject, 'php-mail', $phpMailResult, $phpMailResult ? 'Accepted by PHP mail().' : 'PHP mail() returned false.');
    return $phpMailResult;
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

        $stmt = $pdo->prepare('SELECT id, full_name, email_confirmed_at, email_confirmation_token_hash FROM users WHERE email = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$email]);
        $createdUser = $stmt->fetch();
        if (!$createdUser || !empty($createdUser['email_confirmed_at']) || !empty($createdUser['email_confirmation_token_hash'])) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $temporaryPassword = account_confirmation_generate_temporary_password();
        $update = $pdo->prepare('UPDATE users SET password_hash = ?, email_confirmation_token_hash = ?, email_confirmation_expires_at = DATE_ADD(NOW(), INTERVAL 2 DAY), password_change_required = 1, updated_at = NOW() WHERE id = ?');
        $update->execute([password_hash($temporaryPassword, PASSWORD_DEFAULT), $tokenHash, (int) $createdUser['id']]);

        if (account_confirmation_send_email($email, (string) ($createdUser['full_name'] ?? ''), $token, $temporaryPassword)) {
            account_confirmation_flash_notice('User created. Confirmation email and temporary password sent to ' . $email . '.');
        } else {
            account_confirmation_flash_error('User created, but the confirmation email could not be sent. Check Mail Trace for the PHP mail result and confirm Hostinger PHP mail is enabled for the sender address.');
        }
    } catch (Throwable $error) {
        error_log('Account confirmation setup failed: ' . $error->getMessage());
        account_confirmation_flash_error('User created, but account confirmation setup failed. Check the PHP error log.');
    }
}
