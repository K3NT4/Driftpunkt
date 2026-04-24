<?php

declare(strict_types=1);

$projectDir = dirname(__DIR__);
$envPath = $projectDir.'/.env';

if (!is_file($envPath)) {
    $envDefaults = [
        'APP_ENV' => getenv('APP_ENV') ?: 'prod',
        'APP_SECRET' => getenv('APP_SECRET') ?: 'change-me-in-production',
        'APP_SHARE_DIR' => getenv('APP_SHARE_DIR') ?: 'var/share',
        'DEFAULT_URI' => getenv('DEFAULT_URI') ?: 'http://localhost',
        'MAILER_DSN' => getenv('MAILER_DSN') ?: 'null://null',
        'MAILER_FROM' => getenv('MAILER_FROM') ?: 'notifications@driftpunkt.local',
        'ADDON_RELEASE_OWNER_EMAIL' => getenv('ADDON_RELEASE_OWNER_EMAIL') ?: '',
        'RESERVED_SUPER_ADMIN_PASSWORD' => getenv('RESERVED_SUPER_ADMIN_PASSWORD') ?: '',
        'DATABASE_URL' => getenv('DATABASE_URL') ?: 'mysql://driftpunkt:driftpunkt@127.0.0.1:33060/driftpunkt?serverVersion=mariadb-11.8.6&charset=utf8mb4',
    ];

    $content = '';
    foreach ($envDefaults as $key => $value) {
        $normalizedValue = str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '', ''], (string) $value);
        $content .= sprintf("%s=\"%s\"\n", $key, $normalizedValue);
    }

    file_put_contents($envPath, $content);
}
