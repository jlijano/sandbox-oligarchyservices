<?php
declare(strict_types=1);

function carrier_compose_subject(string $subject, string $mode): string
{
    $subject = trim($subject) !== '' ? trim($subject) : 'Carrier email';
    $prefix = $mode === 'forward' ? 'Fwd: ' : 'Re: ';
    if (!preg_match('/^(re|fw|fwd):\s*/i', $subject)) {
        $subject = $prefix . $subject;
    }

    return $subject;
}

function carrier_compose_quoted_message(array $mail, string $mode): string
{
    $subject = trim((string) ($mail['subject'] ?? 'Carrier email'));
    $senderName = trim((string) ($mail['carrier_name'] ?? ''));
    $senderEmail = trim((string) ($mail['carrier_email'] ?? ''));
    $sender = trim($senderName . ($senderEmail !== '' ? ' <' . $senderEmail . '>' : ''));
    $received = carrier_display_date((string) ($mail['received_at'] ?? ''));
    $message = trim((string) ($mail['message'] ?? ''));
    $quoted = preg_replace('/^/m', '> ', $message) ?? $message;

    if ($mode === 'forward') {
        return "\n\n---------- Forwarded message ---------\n"
            . "From: {$sender}\n"
            . "Date: {$received}\n"
            . "Subject: {$subject}\n\n"
            . $message;
    }

    return "\n\nOn {$received}, {$sender} wrote:\n" . $quoted;
}

function carrier_compose_sender_header(): string
{
    if (function_exists('account_confirmation_from_header')) {
        return account_confirmation_from_header();
    }

    $from = trim((string) getenv('PORTAL_MAIL_FROM'));
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'no-reply@sandbox.oligarchyservices.com';
    }

    return 'Oligarchy Services <' . $from . '>';
}

function carrier_send_via_php_mail(string $to, string $subject, string $body, string $replyTo): bool
{
    $fromHeader = carrier_compose_sender_header();
    $headers = [
        'From: ' . $fromHeader,
        'Reply-To: ' . (filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : $fromHeader),
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($to, $subject, $body, implode("\r\n", $headers));
}

function carrier_handle_compose_send(PDO $pdo, array $user): void
{
    $id = carrier_post_int('carrier_id');
    $mode = trim((string) ($_POST['compose_mode'] ?? 'reply'));
    if (!in_array($mode, ['reply', 'forward'], true)) {
        throw new RuntimeException('Choose a valid send mode.');
    }

    $mail = $id > 0 ? carrier_fetch($pdo, $id) : null;
    if (!$mail) {
        throw new RuntimeException('Choose a valid carrier email.');
    }

    $to = strtolower(trim((string) ($_POST['to'] ?? '')));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid recipient email address.');
    }
    if ($subject === '') {
        throw new RuntimeException('Subject is required.');
    }
    if ($body === '') {
        throw new RuntimeException('Message is required.');
    }

    $replyTo = trim((string) ($user['email'] ?? ''));
    $sent = carrier_send_via_php_mail($to, $subject, $body, $replyTo);
    if (function_exists('account_confirmation_record_mail_trace')) {
        account_confirmation_record_mail_trace($to, $subject, 'carrier-php-mail', $sent, $sent ? 'Accepted by PHP mail().' : 'PHP mail() returned false.');
    }
    if (!$sent) {
        throw new RuntimeException('Carrier email could not be sent. Check Mail Trace and confirm PHP mail is enabled.');
    }

    $pdo->prepare("UPDATE carrier_emails SET status = CASE WHEN status = 'New' THEN 'Contacted' ELSE status END, is_read = 1, updated_by = ?, updated_at = NOW() WHERE id = ?")->execute([(int) $user['id'], $id]);
    carrier_log_activity($pdo, (int) $user['id'], 'carrier email ' . $mode . ' sent', $id, $to);
    carrier_flash_success(ucfirst($mode) . ' sent to ' . $to . '.');
    carrier_redirect(carrier_context_params(['open' => $id]), '#carrier-preview');
}
