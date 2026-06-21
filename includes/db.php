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

function db_config_paths(): array
{
    $paths = [__DIR__ . '/config.php'];

    foreach ([4, 2] as $levels) {
        $directory = dirname(__DIR__, $levels);
        if ($directory !== '.' && $directory !== DIRECTORY_SEPARATOR) {
            $paths[] = $directory . '/oligarchy-config.php';
        }
    }

    return array_values(array_unique($paths));
}

function db_has_config(): bool
{
    foreach (db_config_paths() as $path) {
        if (is_file($path)) {
            return true;
        }
    }

    return false;
}

function db_primary_config_path(): string
{
    foreach (db_config_paths() as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return __DIR__ . '/config.php';
}

function load_db_config(string $configPath): array
{
    if (!is_file($configPath)) {
        throw new RuntimeException('Database config is missing. Use repair.php to recreate it.');
    }

    $loaded = require $configPath;
    $config = is_array($loaded) ? $loaded : db_config_from_constants();

    $normalized = [
        'host' => read_config_value($config, ['host', 'db_host', 'DB_HOST']),
        'database' => read_config_value($config, ['database', 'dbname', 'db_name', 'DB_DATABASE', 'DB_NAME']),
        'username' => read_config_value($config, ['username', 'user', 'db_user', 'DB_USERNAME', 'DB_USER']),
        'password' => read_config_value($config, ['password', 'pass', 'db_password', 'DB_PASSWORD']),
        'port' => read_config_value($config, ['port', 'db_port', 'DB_PORT']),
    ];

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
