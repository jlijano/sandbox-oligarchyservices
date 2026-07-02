<?php
declare(strict_types=1);

function setup_send_safety_headers(): void
{
    header('X-Robots-Tag: noindex, nofollow', true);
    header('Cache-Control: no-store, max-age=0', true);
    header('Pragma: no-cache', true);
    header('X-Content-Type-Options: nosniff', true);
    header('Referrer-Policy: no-referrer', true);
}

function setup_unlock_env_name(string $purpose): string
{
    return $purpose === 'repair' ? 'OLIGARCHY_REPAIR_UNLOCK' : 'OLIGARCHY_INSTALL_UNLOCK';
}

function setup_unlock_file_name(string $purpose): string
{
    return $purpose === 'repair' ? 'oligarchy-repair.unlock' : 'oligarchy-install.unlock';
}

function setup_unlock_value_enabled(string $value): bool
{
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on', 'unlock'], true);
}

function setup_unlock_paths(string $configPath, string $purpose): array
{
    $fileName = setup_unlock_file_name($purpose);
    $paths = [];

    foreach (installer_backup_config_paths($configPath) as $backupPath) {
        $paths[] = dirname($backupPath) . '/' . $fileName;
    }

    return array_values(array_unique($paths));
}

function setup_unlock_is_present(string $configPath, string $purpose): bool
{
    $envValue = getenv(setup_unlock_env_name($purpose));
    if ($envValue !== false && setup_unlock_value_enabled((string) $envValue)) {
        return true;
    }

    foreach (setup_unlock_paths($configPath, $purpose) as $path) {
        if (is_file($path)) {
            return true;
        }
    }

    return false;
}

function setup_locked_message(string $purpose): string
{
    $action = $purpose === 'repair' ? 'Manual repair' : 'Install';
    $envName = setup_unlock_env_name($purpose);
    $fileName = setup_unlock_file_name($purpose);

    return $action . ' is locked. Temporarily set ' . $envName . '=1 in the hosting environment or create ' . $fileName . ' beside the persistent database config backup outside public_html, then remove it after this one setup task is complete.';
}

function setup_exit_locked(string $purpose): void
{
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo setup_locked_message($purpose);
    exit;
}
