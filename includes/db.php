<?php
declare(strict_types=1);

function read_config_value(array $config, array $keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $config) && trim((string) $config[$key]) !== '') {
            return trim((string) $config[$key]);
        }
    }

    return '';
}

function db_config_from_constants(): array
{
    return [
        'host' => defined('DB_HOST') ? (string) DB_HOST : '',
        'database' => defined('DB_DATABASE') ? (string) DB_DATABASE : (defined('DB_NAME') ? (string) DB_NAME : ''),
        'username' => defined('DB_USERNAME') ? (string) DB_USERNAME : (defined('DB_USER') ? (string) DB_USER : ''),
        'password' => defined('DB_PASSWORD') ? (string) DB_PASSWORD : '',
        'port' => defined('DB_PORT') ? (string) DB_PORT : '',
    ];
}

function db_config_from_environment(): array
{
    $read = static function (array $keys): string {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    };

    return [
        'host' => $read(['DB_HOST', 'MYSQL_HOST']),
        'database' => $read(['DB_DATABASE', 'DB_NAME', 'MYSQL_DATABASE']),
        'username' => $read(['DB_USERNAME', 'DB_USER', 'MYSQL_USER']),
        'password' => $read(['DB_PASSWORD', 'MYSQL_PASSWORD']),
        'port' => $read(['DB_PORT', 'MYSQL_PORT']),
    ];
}

function db_normalize_config(array $config): array
{
    return [
        'host' => read_config_value($config, ['host', 'db_host', 'DB_HOST']),
        'database' => read_config_value($config, ['database', 'dbname', 'db_name', 'DB_DATABASE', 'DB_NAME']),
        'username' => read_config_value($config, ['username', 'user', 'db_user', 'DB_USERNAME', 'DB_USER']),
        'password' => read_config_value($config, ['password', 'pass', 'db_password', 'DB_PASSWORD']),
        'port' => read_config_value($config, ['port', 'db_port', 'DB_PORT']),
    ];
}

function db_config_is_complete(array $config): bool
{
    $normalized = db_normalize_config($config);

    return $normalized['host'] !== ''
        && $normalized['database'] !== ''
        && $normalized['username'] !== '';
}

function db_local_config_path(): string
{
    return __DIR__ . '/config.php';
}

function db_backup_config_path(): string
{
    return dirname(__DIR__, 2) . '/oligarchy-config.php';
}

function db_install_lock_path(): string
{
    return __DIR__ . '/installed.lock';
}

function db_has_install_lock(): bool
{
    return is_file(db_install_lock_path());
}

function db_config_paths(): array
{
    return [
        __DIR__ . '/config.php',
        dirname(__DIR__, 2) . '/oligarchy-config.php',
    ];
}

function db_has_config(): bool
{
    foreach (db_config_paths() as $path) {
        if (is_file($path)) {
            return true;
        }
    }

    return db_config_is_complete(db_config_from_environment()) || db_config_is_complete(db_config_from_constants());
}

function db_primary_config_path(): string
{
    foreach (db_config_paths() as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
}

function load_db_config(string $configPath): array
{
    if (is_file($configPath)) {
        $loaded = require $configPath;
        $config = is_array($loaded) ? $loaded : db_config_from_constants();
    } else {
        $config = db_config_from_environment();
        if (!db_config_is_complete($config)) {
            $config = db_config_from_constants();
        }
    }

    $normalized = db_normalize_config($config);

    foreach (['host', 'database', 'username'] as $required) {
        if ($normalized[$required] === '') {
            throw new RuntimeException('Database config is missing the required "' . $required . '" value.');
        }
    }

    return $normalized;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = load_db_config(db_primary_config_path());
    $dsn = sprintf(
        'mysql:host=%s;%sdbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'] !== '' ? 'port=' . $config['port'] . ';' : '',
        $config['database']
    );

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
