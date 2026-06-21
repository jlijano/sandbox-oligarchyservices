<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function setup_recovery_path(): string
{
    $lockPath = __DIR__ . '/installed.lock';
    return is_file($lockPath) ? '/repair.php' : '/install.php';
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT id, email, full_name, role, is_active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } catch (Throwable $error) {
        error_log('Current user lookup failed: ' . $error->getMessage());
        return null;
    }

    if (!$user || (int) $user['is_active'] !== 1) {
        logout_user();
        return null;
    }

    return $user;
}

function require_login(): array
{
    if (!db_has_config()) {
        redirect_to(setup_recovery_path());
    }

    $user = current_user();
    if (!$user) {
        if (!db_has_config()) {
            redirect_to(setup_recovery_path());
        }
        redirect_to('/login.html');
    }

    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['logged_in_at'] = time();
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
