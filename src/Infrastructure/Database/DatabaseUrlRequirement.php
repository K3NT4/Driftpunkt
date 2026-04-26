<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

final class DatabaseUrlRequirement
{
    public static function assertSatisfied(string $environment, ?string $databaseUrl): void
    {
        if ('test' === strtolower($environment)) {
            return;
        }

        $databaseUrl = trim((string) $databaseUrl);
        if ('' === $databaseUrl) {
            throw new \RuntimeException('DATABASE_URL saknas. Live-, dev- och Docker-miljöer måste konfigureras mot MariaDB.');
        }

        $parts = parse_url($databaseUrl);
        if (!\is_array($parts)) {
            throw new \RuntimeException('DATABASE_URL kunde inte läsas. Live-, dev- och Docker-miljöer måste konfigureras mot MariaDB.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ('mariadb' === $scheme) {
            return;
        }

        if ('mysql' !== $scheme) {
            throw new \RuntimeException('Driftpunkt kräver MariaDB utanför testmiljön. SQLite är bara tillåtet när APP_ENV=test.');
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $serverVersion = strtolower((string) ($query['serverVersion'] ?? $query['server_version'] ?? ''));
        if (str_contains($serverVersion, 'mariadb')) {
            return;
        }

        throw new \RuntimeException('Driftpunkt kräver MariaDB utanför testmiljön. DATABASE_URL med mysql:// måste ange serverVersion som innehåller mariadb.');
    }
}
