<?php

declare(strict_types=1);

namespace App\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SystemdServiceLoggingConfigurationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function serviceFiles(): iterable
    {
        yield 'generic mail poll service' => ['deploy/systemd/driftpunkt-mail-poll.service', '/path/to/driftpunkt/var/log/mail-poll.log'];
        yield 'debian mail poll service' => ['deploy/debian/driftpunkt-mail-poll.service', '/var/www/driftpunkt/var/log/mail-poll.log'];
        yield 'generic attachment archive service' => ['deploy/systemd/driftpunkt-attachment-archive.service', '/path/to/driftpunkt/var/log/ticket-attachment-archive.log'];
        yield 'debian attachment archive service' => ['deploy/debian/driftpunkt-attachment-archive.service', '/var/www/driftpunkt/var/log/ticket-attachment-archive.log'];
    }

    #[DataProvider('serviceFiles')]
    public function testSystemdServiceAppendsOutputToProjectLogFile(string $servicePath, string $logPath): void
    {
        $content = (string) file_get_contents(dirname(__DIR__, 2).'/'.$servicePath);

        self::assertStringContainsString('StandardOutput=append:'.$logPath, $content);
        self::assertStringContainsString('StandardError=append:'.$logPath, $content);
    }
}
