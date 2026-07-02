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

function restore_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS restore_points (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(190) NOT NULL,
        file_name VARCHAR(190) NOT NULL DEFAULT '',
        config_fingerprint CHAR(64) NOT NULL DEFAULT '',
        created_by VARCHAR(190) NOT NULL DEFAULT '',
        restored_at DATETIME NULL,
        restored_by VARCHAR(190) NOT NULL DEFAULT '',
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_restore_points_created (created_at),
        INDEX idx_restore_points_restored (restored_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function restore_storage_dirs(string $configPath): array
{
    $dirs = [];
    foreach (installer_backup_config_paths($configPath) as $backupPath) {
        $dirs[] = dirname($backupPath) . '/oligarchy-restore-points';
    }

    return array_values(array_unique($dirs));
}

function restore_prepare_storage_dirs(string $configPath): array
{
    $dirs = restore_storage_dirs($configPath);
    foreach ($dirs as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create restore point storage. Check Hostinger file permissions.');
        }
        @chmod($dir, 0700);
    }

    return $dirs;
}

function restore_savepoint_paths(string $configPath): array
{
    $paths = [];
    foreach (installer_backup_config_paths($configPath) as $backupPath) {
        $paths[] = dirname($backupPath) . '/oligarchy-known-good-config.php';
    }

    return array_values(array_unique($paths));
}

function restore_point_file_name(int $id): string
{
    return 'restore-point-' . $id . '.php';
}

function restore_point_paths(string $configPath, string $fileName): array
{
    $safeName = basename($fileName);
    if ($safeName === '' || $safeName !== $fileName || !preg_match('/^restore-point-[0-9]+\.php$/', $safeName)) {
        throw new RuntimeException('The restore point file reference is invalid.');
    }

    $paths = [];
    foreach (restore_storage_dirs($configPath) as $dir) {
        $paths[] = $dir . '/' . $safeName;
    }

    return $paths;
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

function restore_config_fingerprint(array $config): string
{
    return hash('sha256', implode('|', [$config['host'], $config['database'], $config['username'], $config['port'] ?? '']));
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
        throw new RuntimeException('Could not write the restore point file. Check Hostinger file permissions.');
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

function restore_first_valid_point_file(string $configPath, string $fileName): ?string
{
    foreach (restore_point_paths($configPath, $fileName) as $path) {
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

function restore_fetch_points(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT * FROM restore_points ORDER BY created_at DESC, id DESC');
    return $stmt->fetchAll();
}

function restore_fetch_point(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM restore_points WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $point = $stmt->fetch();

    return $point ?: null;
}

function restore_savepoint_summary(array $points, string $configPath): string
{
    if (!$points) {
        return 'No restore points have been created yet.';
    }

    $latest = $points[0];
    $filePath = restore_first_valid_point_file($configPath, (string) $latest['file_name']);
    $status = $filePath !== null ? 'available' : 'missing its server-local file';
    return 'Latest restore point: ' . $latest['label'] . ' from ' . $latest['created_at'] . ' (' . $status . ').';
}

restore_ensure_schema($pdo);
$savepointPath = restore_first_valid_savepoint($configPath);
$restorePoints = restore_fetch_points($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        try {
            if ($action === 'create_savepoint') {
                restore_prepare_storage_dirs($configPath);
                $sourcePath = installer_existing_config_path($configPath) ?: db_primary_config_path();
                if ($sourcePath === '') {
                    $config = load_db_config('');
                } else {
                    $config = restore_read_config($sourcePath);
                }
                restore_test_config($config);
                $content = restore_config_contents($config);
                $label = trim((string) ($_POST['label'] ?? ''));
                if ($label === '') {
                    $label = 'Known-good point ' . date('Y-m-d H:i');
                }
                if (strlen($label) > 190) {
                    throw new RuntimeException('Restore point label must be 190 characters or fewer.');
                }
                $notes = trim((string) ($_POST['notes'] ?? ''));
                $stmt = $pdo->prepare('INSERT INTO restore_points (label, created_by, notes, config_fingerprint) VALUES (?, ?, ?, ?)');
                $stmt->execute([$label, (string) $user['email'], $notes, restore_config_fingerprint($config)]);
                $pointId = (int) $pdo->lastInsertId();
                $fileName = restore_point_file_name($pointId);
                foreach (restore_point_paths($configPath, $fileName) as $path) {
                    restore_write_file($path, $content);
                }
                foreach (restore_savepoint_paths($configPath) as $path) {
                    restore_write_file($path, $content);
                }
                $pdo->prepare('UPDATE restore_points SET file_name = ? WHERE id = ?')->execute([$fileName, $pointId]);
                restore_save_setting($pdo, 'restore_savepoint_created_at', gmdate('c'));
                restore_save_setting($pdo, 'restore_savepoint_created_by', (string) $user['email']);
                restore_save_setting($pdo, 'restore_savepoint_latest_id', (string) $pointId);
                restore_log_activity($pdo, (int) $user['id'], 'restore point created', 'Restore point #' . $pointId . ' saved: ' . $label);
                $notice = 'Restore point saved.';
                $savepointPath = restore_first_valid_savepoint($configPath);
            } elseif ($action === 'restore_savepoint') {
                $pointId = filter_var($_POST['restore_point_id'] ?? 0, FILTER_VALIDATE_INT);
                if ($pointId === false || (int) $pointId <= 0) {
                    throw new RuntimeException('Choose a restore point to restore.');
                }
                $point = restore_fetch_point($pdo, (int) $pointId);
                if (!$point) {
                    throw new RuntimeException('The selected restore point was not found.');
                }
                $pointFile = restore_first_valid_point_file($configPath, (string) $point['file_name']);
                if ($pointFile === null) {
                    throw new RuntimeException('The selected restore point file is missing or invalid.');
                }
                $config = restore_read_config($pointFile);
                restore_test_config($config);
                $content = restore_config_contents($config);
                restore_write_file($configPath, $content);
                foreach (installer_backup_config_paths($configPath) as $backupPath) {
                    restore_write_file($backupPath, $content);
                }
                restore_save_setting($pdo, 'restore_savepoint_restored_at', gmdate('c'));
                restore_save_setting($pdo, 'restore_savepoint_restored_by', (string) $user['email']);
                restore_save_setting($pdo, 'restore_savepoint_restored_id', (string) $pointId);
                $pdo->prepare('UPDATE restore_points SET restored_at = NOW(), restored_by = ? WHERE id = ?')->execute([(string) $user['email'], $pointId]);
                restore_log_activity($pdo, (int) $user['id'], 'restore point applied', 'Restore point #' . $pointId . ' restored: ' . $point['label']);
                $notice = 'Restore point restored.';
            } else {
                throw new RuntimeException('Choose a valid restore action.');
            }
        } catch (Throwable $exception) {
            error_log('Restore action failed: ' . $exception->getMessage());
            $error = $exception->getMessage();
        }
    }
    $restorePoints = restore_fetch_points($pdo);
}

$csrf = csrf_token();
$summary = restore_savepoint_summary($restorePoints, $configPath);
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
        <div class="login-panel">
          <div class="login-panel-heading"><p class="eyebrow">Known-good restore</p><h1 id="restore-heading">Restore savepoints</h1><p>Create named restore points, then choose the exact known-good configuration to restore. This tool is restricted to admin@admin.com.</p></div>
          <?php if ($notice !== ''): ?><div class="form-alert is-visible is-success"><?= e($notice) ?></div><?php endif; ?>
          <?php if ($error !== ''): ?><div class="form-alert is-visible is-error"><?= e($error) ?></div><?php endif; ?>
          <div class="form-alert is-visible"><?= e($summary) ?></div>

          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="create_savepoint">
            <label class="field"><span>Restore point name</span><input name="label" maxlength="190" value="<?= e('Known-good point ' . date('Y-m-d H:i')) ?>" required></label>
            <label class="field"><span>Notes</span><textarea name="notes" rows="3" placeholder="What was working at this point?"></textarea></label>
            <button class="button primary login-submit" type="submit">Save restore point</button>
          </form>

          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="restore_savepoint">
            <label class="field"><span>Select restore point</span><select name="restore_point_id" required><option value="">Choose a restore point</option><?php foreach ($restorePoints as $point): ?><option value="<?= e((string) $point['id']) ?>"><?= e('#' . $point['id'] . ' - ' . $point['label'] . ' (' . $point['created_at'] . ')') ?></option><?php endforeach; ?></select></label>
            <button class="button secondary login-submit" type="submit" data-confirm="Restore the selected known-good configuration now?">Restore selected point</button>
          </form>

          <div class="form-alert is-visible">
            <strong><?= e((string) count($restorePoints)) ?> restore point<?= count($restorePoints) === 1 ? '' : 's' ?></strong><br>
            <?php if (!$restorePoints): ?>No restore points are available yet. Save the first known-good point before restoring.<?php else: ?>
              <?php foreach (array_slice($restorePoints, 0, 8) as $point): ?>
                #<?= e((string) $point['id']) ?> <?= e($point['label']) ?> - created <?= e($point['created_at']) ?><?= $point['restored_at'] ? ' - last restored ' . e($point['restored_at']) : '' ?><br>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <p class="login-note">Restore point records are stored in the database; the actual config snapshots are stored as protected server-local PHP files outside normal browser access. This does not drop tables, delete data, roll back code, or expose stored credentials.</p>
        </div>
      </section>
    </main>
  </body>
</html>