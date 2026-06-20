<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('/login.html');
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $_SESSION['login_error'] = 'Your session expired. Please try again.';
    redirect_to('/login.html');
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    $_SESSION['login_error'] = 'Enter a valid email and password.';
    redirect_to('/login.html');
}

$pdo = db();
$recentFailures = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
$recentFailures->execute([$email]);

if ((int) $recentFailures->fetchColumn() >= 5) {
    $_SESSION['login_error'] = 'Too many failed attempts. Try again in 15 minutes.';
    redirect_to('/login.html');
}

$stmt = $pdo->prepare('SELECT id, email, password_hash, is_active FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();
$success = $user && (int) $user['is_active'] === 1 && password_verify($password, $user['password_hash']);

$log = $pdo->prepare('INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)');
$log->execute([$email, $ipAddress, $success ? 1 : 0]);

if (!$success) {
    $_SESSION['login_error'] = 'Invalid email or password.';
    redirect_to('/login.html');
}

$update = $pdo->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = ?');
$update->execute([(int) $user['id']]);

login_user((int) $user['id']);
redirect_to('/dashboard.php');
