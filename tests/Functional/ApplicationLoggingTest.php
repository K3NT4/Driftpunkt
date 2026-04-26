<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Yaml\Yaml;

final class ApplicationLoggingTest extends KernelTestCase
{
    public function testApplicationLoggerWritesToEnvironmentLogFile(): void
    {
        self::bootKernel();

        $projectDir = (string) self::getContainer()->getParameter('kernel.project_dir');
        $logFile = $projectDir.'/var/log/test.log';
        $message = 'Driftpunkt logging smoke test '.bin2hex(random_bytes(6));

        @unlink($logFile);

        self::getContainer()
            ->get(LoggerInterface::class)
            ->info($message, ['source' => 'application_logging_test']);

        self::assertFileExists($logFile);
        self::assertStringContainsString($message, (string) file_get_contents($logFile));
    }

    public function testProductionLoggerFlushesWarningsToFile(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $config = Yaml::parseFile($projectDir.'/config/packages/monolog.yaml');
        $mainHandler = $config['when@prod']['monolog']['handlers']['main'] ?? [];

        self::assertSame(
            'warning',
            $mainHandler['action_level'] ?? null,
        );
        self::assertSame(['!event', '!doctrine', '!deprecation'], $mainHandler['channels'] ?? null);
    }
}
