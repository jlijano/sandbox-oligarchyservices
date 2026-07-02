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

function carrier_uploaded_attachments(): array
{
    if (empty($_FILES['attachments']) || !is_array($_FILES['attachments'])) {
        return [];
    }

    $files = $_FILES['attachments'];
    $names = is_array($files['name'] ?? null) ? $files['name'] : [$files['name'] ?? ''];
    $tmpNames = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [$files['tmp_name'] ?? ''];
    $types = is_array($files['type'] ?? null) ? $files['type'] : [$files['type'] ?? 'application/octet-stream'];
    $sizes = is_array($files['size'] ?? null) ? $files['size'] : [$files['size'] ?? 0];
    $errors = is_array($files['error'] ?? null) ? $files['error'] : [$files['error'] ?? UPLOAD_ERR_NO_FILE];
    $attachments = [];
    $totalBytes = 0;
    $maxFileBytes = 10 * 1024 * 1024;
    $maxTotalBytes = 20 * 1024 * 1024;

    foreach ($names as $index => $name) {
        $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('One attachment could not be uploaded.');
        }

        $tmpName = (string) ($tmpNames[$index] ?? '');
        $size = (int) ($sizes[$index] ?? 0);
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('One attachment could not be read.');
        }
        if ($size <= 0 || $size > $maxFileBytes) {
            throw new RuntimeException('Each attachment must be 10 MB or smaller.');
        }
        $totalBytes += $size;
        if ($totalBytes > $maxTotalBytes) {
            throw new RuntimeException('Total attachments must be 20 MB or smaller.');
        }

        $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename((string) $name)) ?: 'attachment';
        $type = trim((string) ($types[$index] ?? 'application/octet-stream'));
        if (!preg_match('/^[A-Za-z0-9.+_-]+\/[A-Za-z0-9.+_-]+$/', $type)) {
            $type = 'application/octet-stream';
        }
        $attachments[] = [
            'name' => $safeName,
            'tmp_name' => $tmpName,
            'type' => $type,
        ];
    }

    return $attachments;
}

function carrier_send_via_php_mail(string $to, string $subject, string $body, string $replyTo, array $attachments = []): bool
{
    $fromHeader = carrier_compose_sender_header();
    $headers = [
        'From: ' . $fromHeader,
        'Reply-To: ' . (filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : $fromHeader),
    ];

    if (!$attachments) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    $boundary = '=_Carrier_' . bin2hex(random_bytes(12));
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    $message = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $body . "\r\n";

    foreach ($attachments as $attachment) {
        $content = file_get_contents((string) $attachment['tmp_name']);
        if ($content === false) {
            throw new RuntimeException('One attachment could not be attached.');
        }
        $fileName = str_replace(['"', "\r", "\n"], '_', (string) $attachment['name']);
        $message .= '--' . $boundary . "\r\n"
            . 'Content-Type: ' . $attachment['type'] . '; name="' . $fileName . '"' . "\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . 'Content-Disposition: attachment; filename="' . $fileName . '"' . "\r\n\r\n"
            . chunk_split(base64_encode($content)) . "\r\n";
    }
    $message .= '--' . $boundary . "--\r\n";

    return mail($to, $subject, $message, implode("\r\n", $headers));
}

function carrier_handle_compose_send(PDO $pdo, array $user): void
{
    $id = carrier_post_int('carrier_id');
    $mode = trim((string) ($_POST['compose_mode'] ?? 'reply'));
    if (!in_array($mode, ['new', 'reply', 'forward'], true)) {
        throw new RuntimeException('Choose a valid send mode.');
    }

    $mail = null;
    if ($mode !== 'new') {
        $mail = $id > 0 ? carrier_fetch($pdo, $id) : null;
        if (!$mail) {
            throw new RuntimeException('Choose a valid carrier email.');
        }
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
    $attachments = $mode === 'new' ? carrier_uploaded_attachments() : [];
    $sent = carrier_send_via_php_mail($to, $subject, $body, $replyTo, $attachments);
    if (function_exists('account_confirmation_record_mail_trace')) {
        account_confirmation_record_mail_trace($to, $subject, 'carrier-php-mail', $sent, $sent ? 'Accepted by PHP mail().' : 'PHP mail() returned false.');
    }
    if (!$sent) {
        throw new RuntimeException('Carrier email could not be sent. Check Mail Trace and confirm PHP mail is enabled.');
    }

    if ($mode === 'new') {
        carrier_log_activity($pdo, (int) $user['id'], 'carrier email sent', null, $to);
        carrier_flash_success('Email sent to ' . $to . '.');
        carrier_redirect(carrier_context_params(['open' => null]));
    }

    $pdo->prepare("UPDATE carrier_emails SET status = CASE WHEN status = 'New' THEN 'Contacted' ELSE status END, is_read = 1, updated_by = ?, updated_at = NOW() WHERE id = ?")->execute([(int) $user['id'], $id]);
    carrier_log_activity($pdo, (int) $user['id'], 'carrier email ' . $mode . ' sent', $id, $to);
    carrier_flash_success(ucfirst($mode) . ' sent to ' . $to . '.');
    carrier_redirect(carrier_context_params(['open' => $id]), '#carrier-preview');
}
