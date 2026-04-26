<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Command\BuildReleasePackagesCommand;
use App\Module\System\Service\ReleasePackageBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsoleCommandFailureLoggingTest extends TestCase
{
    public function testCaughtCommandFailureIsWrittenToApplicationLogger(): void
    {
        $logger = new RecordingLogger();
        $command = new BuildReleasePackagesCommand(
            new ReleasePackageBuilder(sys_get_temp_dir()),
            $logger,
        );
        $tester = new CommandTester($command);

        $tester->execute(['--type' => 'felaktig']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertTrue($logger->hasRecord('error', 'Kunde inte bygga releasepaket.'));
        self::assertInstanceOf(\Throwable::class, $logger->records[0]['context']['exception'] ?? null);
    }
}

final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function hasRecord(string $level, string $message): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message) {
                return true;
            }
        }

        return false;
    }
}
