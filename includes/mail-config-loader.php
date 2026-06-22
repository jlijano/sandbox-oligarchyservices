<?php
declare(strict_types=1);

function mail_config_apply_value(string $envName, array $config, string $configKey): void
{
    $current = trim((string) getenv($envName));
    if ($current !== '' || !array_key_exists($configKey, $config)) {
        return;
    }

    $value = trim((string) $config[$configKey]);
    if ($value === '') {
        return;
    }

    putenv($envName . '=' . $value);
    $_ENV[$envName] = $value;
    $_SERVER[$envName] = $value;
}

function mail_config_load(): void
{
    $configPath = __DIR__ . '/mail-config.php';
    if (!is_file($configPath)) {
        return;
    }

    try {
        $config = require $configPath;
    } catch (Throwable $error) {
        error_log('Mail config could not be loaded: ' . $error->getMessage());
        return;
    }

    if (!is_array($config)) {
        error_log('Mail config must return an array.');
        return;
    }

    mail_config_apply_value('PORTAL_BASE_URL', $config, 'portal_base_url');
    mail_config_apply_value('PORTAL_MAIL_FROM', $config, 'portal_mail_from');
    mail_config_apply_value('PORTAL_MAIL_ORCHESTRATOR_URL', $config, 'portal_mail_orchestrator_url');
    mail_config_apply_value('PORTAL_MAIL_ORCHESTRATOR_TOKEN', $config, 'portal_mail_orchestrator_token');
}

mail_config_load();
