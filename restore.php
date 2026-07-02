<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/installer.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
if (strtolower((string) ($user['email'] ?? '')) !== 'admin@admin.com') {
    http_response_code(403);
    echo 'Only admin@admin.com can manage restore savepoints.';
    exit;
}

$pdo = db();
$configPath = db_local_config_path();
$notice = '';
$error = '';

function restore_savepoint_paths(string $configPath): array
{
    $paths = [];
    foreach (installer_backup_config_paths($configPath) as $backupPath) {
        $paths[] = dirname($backupPath) . '/oligarchy-known-good-config.php';
    }

    return array_values(array_unique($paths));
}

function restore_read_config(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('The selected configuration file is missing.');
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
        throw new RuntimeException('The selected configuration file is not valid.');
    }

    $config = db_normalize_config($loaded);
    foreach (['host', 'database', 'username'] as $required) {
        if ($config[$required] === '') {
            throw new RuntimeException('The selected configuration is missing the required ' . $required . ' value.');
        }
    }

    return $config;
}

function restore_config_contents(array $config): string
{
    $payload = [
        'host' => $config['host'],
        'database' => $config['database'],
        'username' => $config['username'],
        'password' => $config['password'],
    ];
    if (($config['port'] ?? '') !== '') {
        $payload['port'] = $config['port'];
    }

    return "<?php\nreturn " . var_export($payload, true) . ";\n";
}

function restore_test_config(array $config): void
{
    $dsn = sprintf(
        'mysql:host=%s;%sdbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'] !== '' ? 'port=' . $config['port'] . ';' : '',
        $config['database']
    );
    $test = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $test->query('SELECT 1')->fetchColumn();
}

function restore_write_file(string $path, string $content): void
{
    if (@file_put_contents($path, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not write the restore savepoint file. Check Hostinger file permissions.');
    }
    @chmod($path, 0600);
}

function restore_first_valid_savepoint(string $configPath): ?string
{
    foreach (restore_savepoint_paths($configPath) as $path) {
        if (installer_config_file_is_valid($path)) {
            return $path;
        }
    }

    return null;
}

function restore_save_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO settings (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), updated_at = NOW()');
    $stmt->execute([$key, $value]);
}

function restore_log_activity(PDO $pdo, int $actorId, string $action, string $details): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, 'restore', null, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $auditError) {
        error_log('Restore activity log skipped: ' . $auditError->getMessage());
    }
}

function restore_savepoint_summary(?string $path): string
{
    if ($path === null || !is_file($path)) {
        return 'No known-good savepoint has been created yet.';
    }

    $created = date('Y-m-d H:i:s T', (int) filemtime($path));
    return 'Known-good savepoint available. Last file update: ' . $created . '.';
}

$savepointPath = restore_first_valid_savepoint($configPath);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        try {
            if ($action === 'create_savepoint') {
                $sourcePath = installer_existing_config_path($configPath) ?: db_primary_config_path();
                if ($sourcePath === '') {
                    $config = load_db_config('');
                } else {
                    $config = restore_read_config($sourcePath);
                }
                restore_test_config($config);
                $content = restore_config_contents($config);
                $written = [];
                foreach (restore_savepoint_paths($configPath) as $path) {
                    restore_write_file($path, $content);
                    $written[] = $path;
                }
                restore_save_setting($pdo, 'restore_savepoint_created_at', gmdate('c'));
                restore_save_setting($pdo, 'restore_savepoint_created_by', (string) $user['email']);
                restore_log_activity($pdo, (int) $user['id'], 'restore savepoint created', 'Known-good configuration savepoint created.');
                $notice = 'Known-good savepoint saved.';
                $savepointPath = restore_first_valid_savepoint($configPath);
            } elseif ($action === 'restore_savepoint') {
                $savepointPath = restore_first_valid_savepoint($configPath);
                if ($savepointPath === null) {
                    throw new RuntimeException('No valid known-good savepoint is available yet.');
                }
                $config = restore_read_config($savepointPath);
                restore_test_config($config);
                $content = restore_config_contents($config);
                restore_write_file($configPath, $content);
                foreach (installer_backup_config_paths($configPath) as $backupPath) {
                    restore_write_file($backupPath, $content);
                }
                restore_save_setting($pdo, 'restore_savepoint_restored_at', gmdate('c'));
                restore_save_setting($pdo, 'restore_savepoint_restored_by', (string) $user['email']);
                restore_log_activity($pdo, (int) $user['id'], 'restore savepoint applied', 'Known-good configuration restored to config.php and persistent backups.');
                $notice = 'Known-good configuration restored.';
            } else {
                throw new RuntimeException('Choose a valid restore action.');
            }
        } catch (Throwable $exception) {
            error_log('Restore action failed: ' . $exception->getMessage());
            $error = $exception->getMessage();
        }
    }
}

$csrf = csrf_token();
$summary = restore_savepoint_summary($savepointPath);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex">
    <title>Restore Savepoint | Oligarchy Services</title>
    <link rel="stylesheet" href="/assets/styles.css?v=20260618-service-icons"><link rel="stylesheet" href="/assets/login.css?v=20260620-php-install">
  </head>
  <body>
    <main class="login-page">
      <section class="login-hero" aria-labelledby="restore-heading">
        <a class="login-brand-logo" href="/dashboard.php#system-settings" aria-label="Back to settings">OLIGARCHY</a>
        <form class="login-panel" method="post">
          <div class="login-panel-heading"><p class="eyebrow">Known-good restore</p><h1 id="restore-heading">Restore savepoint</h1><p>Create or restore the last known functional database configuration. This tool is restricted to admin@admin.com.</p></div>
          <?php if ($notice !== ''): ?><div class="form-alert is-visible is-success"><?= e($notice) ?></div><?php endif; ?>
          <?php if ($error !== ''): ?><div class="form-alert is-visible is-error"><?= e($error) ?></div><?php endif; ?>
          <div class="form-alert is-visible"><?= e($summary) ?></div>
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <button class="button primary login-submit" type="submit" name="action" value="create_savepoint">Save known-good point</button>
          <button class="button secondary login-submit" type="submit" name="action" value="restore_savepoint" data-confirm="Restore the known-good configuration now?">Restore known-good point</button>
          <p class="login-note">This restores server-local database configuration only. It does not drop tables, delete data, roll back code, or expose stored credentials.</p>
        </form>
      </section>
    </main>
  </body>
</html>