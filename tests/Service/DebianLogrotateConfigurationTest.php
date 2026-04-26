<?php

declare(strict_types=1);

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;

final class DebianLogrotateConfigurationTest extends TestCase
{
    public function testDebianLogrotateConfigRotatesProjectLogs(): void
    {
        $config = (string) file_get_contents(dirname(__DIR__, 2).'/deploy/debian/driftpunkt-logrotate');

        self::assertStringContainsString('/var/www/driftpunkt/var/log/*.log', $config);
        self::assertStringContainsString('daily', $config);
        self::assertStringContainsString('rotate 14', $config);
        self::assertStringContainsString('compress', $config);
        self::assertStringContainsString('copytruncate', $config);
        self::assertStringContainsString('create 0640', $config);
    }

    public function testDebianSetupInstallsLogrotateConfiguration(): void
    {
        $setup = (string) file_get_contents(dirname(__DIR__, 2).'/deploy/debian/setup.sh');

        self::assertStringContainsString('  logrotate \\', $setup);
        self::assertStringContainsString('cp "${APP_DIR}/deploy/debian/driftpunkt-logrotate" /etc/logrotate.d/driftpunkt', $setup);
        self::assertStringContainsString('sed -i "s#/var/www/driftpunkt#${APP_DIR}#g" /etc/logrotate.d/driftpunkt', $setup);
    }
}
