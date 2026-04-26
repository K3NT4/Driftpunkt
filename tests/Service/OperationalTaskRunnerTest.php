<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\System\Service\BackgroundConsoleLauncher;
use App\Module\System\Service\OperationalTaskRunner;
use App\Module\System\Service\ProjectCommandExecutor;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class OperationalTaskRunnerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/driftpunkt-operational-tasks-'.bin2hex(random_bytes(4));
        mkdir($this->projectDir.'/var', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    public function testItQueuesInternalRunnerWhenAnyScheduledTaskIsDue(): void
    {
        $executor = new RecordingCommandExecutor();
        $launcher = new RecordingBackgroundLauncher();
        $runner = new OperationalTaskRunner($this->projectDir, $executor, $launcher, 'prod');

        $queued = $runner->queueDueRunner(new \DateTimeImmutable('2026-04-25 12:00:00'));

        self::assertSame(['operational_tasks'], $queued);
        self::assertCount(1, $launcher->launches);
        self::assertSame([\PHP_BINARY, 'bin/console', 'app:operations:run-due', '--env=prod'], $launcher->launches[0]['arguments']);
        self::assertSame($this->projectDir, $launcher->launches[0]['workingDirectory']);

        $queuedAgain = $runner->queueDueRunner(new \DateTimeImmutable('2026-04-25 12:00:30'));

        self::assertSame([], $queuedAgain);
        self::assertCount(1, $launcher->launches);
    }

    public function testItRunsDueTasksAndPersistsIntervals(): void
    {
        $executor = new RecordingCommandExecutor();
        $launcher = new RecordingBackgroundLauncher();
        $runner = new OperationalTaskRunner($this->projectDir, $executor, $launcher, 'prod');

        $firstRun = $runner->runDueTasks(new \DateTimeImmutable('2026-04-25 12:00:00'));

        self::assertSame([
            'mail_poll',
            'ticket_sla',
            'ticket_attachment_archive',
            'company_monthly_reports',
        ], array_column($firstRun, 'id'));
        self::assertCount(4, $executor->commands);
        self::assertSame([\PHP_BINARY, 'bin/console', 'app:mail:poll', '--env=prod'], $executor->commands[0]);
        self::assertSame([\PHP_BINARY, 'bin/console', 'app:check-ticket-sla', '--env=prod'], $executor->commands[1]);
        self::assertSame([\PHP_BINARY, 'bin/console', 'app:archive-ticket-attachments', '--env=prod'], $executor->commands[2]);
        self::assertSame([\PHP_BINARY, 'bin/console', 'app:reports:send-monthly', '--env=prod'], $executor->commands[3]);
        self::assertFileExists($this->projectDir.'/var/operational_tasks.json');

        $secondRun = $runner->runDueTasks(new \DateTimeImmutable('2026-04-25 12:04:59'));

        self::assertSame([], $secondRun);
        self::assertCount(4, $executor->commands);

        $thirdRun = $runner->runDueTasks(new \DateTimeImmutable('2026-04-25 12:05:00'));

        self::assertSame(['mail_poll'], array_column($thirdRun, 'id'));
        self::assertCount(5, $executor->commands);
        self::assertSame([\PHP_BINARY, 'bin/console', 'app:mail:poll', '--env=prod'], $executor->commands[4]);
    }

    public function testItLogsExecutorExceptionsFromDueTasks(): void
    {
        $executor = new ThrowingCommandExecutor();
        $launcher = new RecordingBackgroundLauncher();
        $logger = new OperationalTaskRecordingLogger();
        $runner = new OperationalTaskRunner($this->projectDir, $executor, $launcher, 'prod', $logger);

        $results = $runner->runDueTasks(new \DateTimeImmutable('2026-04-25 12:00:00'));

        self::assertCount(4, $results);
        self::assertFalse($results[0]['succeeded']);
        self::assertTrue($logger->hasRecord('error', 'Internt driftjobb misslyckades.'));
        self::assertSame('mail_poll', $logger->records[0]['context']['task_id'] ?? null);
        self::assertInstanceOf(\Throwable::class, $logger->records[0]['context']['exception'] ?? null);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());

                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}

final class RecordingCommandExecutor extends ProjectCommandExecutor
{
    /**
     * @var list<list<string>>
     */
    public array $commands = [];

    public function execute(array $command, string $workingDirectory): array
    {
        $this->commands[] = $command;

        return [
            'exitCode' => 0,
            'output' => sprintf('Ran in %s: %s', $workingDirectory, implode(' ', $command)),
        ];
    }
}

final class ThrowingCommandExecutor extends ProjectCommandExecutor
{
    public function execute(array $command, string $workingDirectory): array
    {
        throw new \RuntimeException('Kommandot kunde inte startas.');
    }
}

final class RecordingBackgroundLauncher extends BackgroundConsoleLauncher
{
    /**
     * @var list<array{arguments: list<string>, workingDirectory: string}>
     */
    public array $launches = [];

    public function __construct()
    {
    }

    public function launch(array $arguments, string $workingDirectory): void
    {
        $this->launches[] = [
            'arguments' => $arguments,
            'workingDirectory' => $workingDirectory,
        ];
    }
}

final class OperationalTaskRecordingLogger extends AbstractLogger
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
