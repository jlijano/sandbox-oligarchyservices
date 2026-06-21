<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/installer.php';
require_once __DIR__ . '/../includes/password-change.php';

function wants_json(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function login_response(string $message, int $status = 400, string $redirect = '/login.html'): void
{
    if (wants_json()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $message, 'redirect' => $redirect]);
        exit;
    }
    $_SESSION['login_error'] = $message;
    redirect_to($redirect);
}

function login_success_response(string $redirect = '/dashboard.php'): void
{
    if (wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'redirect' => $redirect]);
        exit;
    }
    redirect_to($redirect);
}

function ensure_login_schema(PDO $pdo): void
{
    create_or_update_schema($pdo);
    password_change_ensure_schema($pdo);
}

function audit_login(PDO $pdo, ?int $userId, string $action, string $email, string $ipAddress): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $action, 'user', $userId, $email, $ipAddress]);
    } catch (Throwable $error) {
        error_log('Login audit skipped: ' . $error->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect_to('/login.html');
if (!csrf_verify($_POST['csrf_token'] ?? null)) login_response('Your session expired. Please refresh the page and try again.', 403);

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') login_response('Enter a valid email and password.', 422);

try {
    $pdo = db();
    ensure_login_schema($pdo);

    $recentFailures = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
    $recentFailures->execute([$email]);
    if ((int) $recentFailures->fetchColumn() >= 5) login_response('Too many failed attempts. Try again in 15 minutes.', 429);

    $stmt = $pdo->prepare('SELECT id, email, password_hash, is_active, email_confirmed_at, password_change_required FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    $passwordMatches = $user && password_verify($password, $user['password_hash']);
    $isConfirmed = $user && !empty($user['email_confirmed_at']);
    $success = $user && (int) $user['is_active'] === 1 && $passwordMatches && $isConfirmed;

    $log = $pdo->prepare('INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)');
    $log->execute([$email, $ipAddress, $success ? 1 : 0]);

    if (!$success) {
        $userId = $user ? (int) $user['id'] : null;
        if ($user && (int) $user['is_active'] === 1 && $passwordMatches && !$isConfirmed) {
            audit_login($pdo, $userId, 'unconfirmed login', $email, $ipAddress);
            login_response('Please confirm your email address before signing in. Check the confirmation email for your account link.', 403);
        }

        audit_login($pdo, $userId, 'failed login', $email, $ipAddress);
        login_response('Invalid email or password.', 401);
    }

    $passwordChangeRequired = (int) ($user['password_change_required'] ?? 0) === 1;
    $redirect = $passwordChangeRequired ? '/change-password.php' : '/dashboard.php';
    $update = $pdo->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = ?');
    $update->execute([(int) $user['id']]);
    audit_login($pdo, (int) $user['id'], 'login', $email, $ipAddress);
    login_user((int) $user['id'], $passwordChangeRequired);
    login_success_response($redirect);
} catch (RuntimeException $error) {
    error_log('Login configuration error: ' . $error->getMessage());
    login_response('Database configuration is missing on the server. Open /repair.php to reconnect the existing Hostinger database; do not reinstall or drop the database. Server admins can also provide DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD environment variables.', 503, '/repair.php');
} catch (Throwable $error) {
    error_log('Login error: ' . $error->getMessage());
    login_response('Login could not reach the database. Check the Hostinger database settings or PHP error log for the exact cause.', 503);
}
