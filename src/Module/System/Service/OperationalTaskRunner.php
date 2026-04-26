<?php

declare(strict_types=1);

namespace App\Module\System\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OperationalTaskRunner
{
    private const STATE_FILENAME = 'operational_tasks.json';
    private const LOCK_FILENAME = 'operational_tasks.lock';
    private const RUNNER_QUEUE_LOCK_SECONDS = 60;
    private readonly LoggerInterface $logger;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly ProjectCommandExecutor $commandExecutor,
        private readonly BackgroundConsoleLauncher $backgroundConsoleLauncher,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Queues the internal due-task command from the web app when at least one task is due.
     *
     * @return list<string>
     */
    public function queueDueRunner(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $shouldQueue = $this->withStateLock(function () use ($now): bool {
            $state = $this->readState();

            if (!$this->hasDueTask($state, $now)) {
                return false;
            }

            $lastQueuedAt = $this->parseDateTime($state['runnerQueuedAt'] ?? null);
            if ($lastQueuedAt instanceof \DateTimeImmutable && $lastQueuedAt->getTimestamp() + self::RUNNER_QUEUE_LOCK_SECONDS > $now->getTimestamp()) {
                return false;
            }

            $state['runnerQueuedAt'] = $now->format(DATE_ATOM);
            $this->writeState($state);

            return true;
        });

        if (!$shouldQueue) {
            return [];
        }

        $this->backgroundConsoleLauncher->launch(
            [\PHP_BINARY, 'bin/console', 'app:operations:run-due', $this->environmentArgument()],
            $this->projectDir,
        );

        return ['operational_tasks'];
    }

    /**
     * @return list<array{id: string, label: string, exitCode: int, succeeded: bool, output: string}>
     */
    public function runDueTasks(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $tasks = $this->withStateLock(function () use ($now): array {
            $state = $this->readState();
            $dueTasks = [];

            foreach ($this->taskDefinitions() as $task) {
                if (!$this->isTaskDue($task, $state, $now)) {
                    continue;
                }

                $dueTasks[] = $task;
                $state['tasks'][$task['id']] = array_merge(
                    \is_array($state['tasks'][$task['id']] ?? null) ? $state['tasks'][$task['id']] : [],
                    [
                        'lastRunAt' => $now->format(DATE_ATOM),
                        'lastStatus' => 'running',
                        'lastExitCode' => null,
                        'lastOutput' => '',
                    ],
                );
            }

            $state['runnerStartedAt'] = $now->format(DATE_ATOM);
            $this->writeState($state);

            return $dueTasks;
        });

        $results = [];
        foreach ($tasks as $task) {
            try {
                $execution = $this->commandExecutor->execute($task['command'], $this->projectDir);
            } catch (\Throwable $exception) {
                $this->logger->error('Internt driftjobb misslyckades.', [
                    'task_id' => $task['id'],
                    'task_label' => $task['label'],
                    'command' => $task['command'],
                    'exception' => $exception,
                ]);
                $execution = [
                    'exitCode' => 1,
                    'output' => $exception->getMessage(),
                ];
            }

            $succeeded = 0 === $execution['exitCode'];
            $result = [
                'id' => $task['id'],
                'label' => $task['label'],
                'exitCode' => $execution['exitCode'],
                'succeeded' => $succeeded,
                'output' => $execution['output'],
            ];
            $results[] = $result;

            $this->persistTaskResult($task['id'], $result, new \DateTimeImmutable());
        }

        $this->withStateLock(function (): void {
            $state = $this->readState();
            $state['runnerFinishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            $this->writeState($state);
        });

        return $results;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function hasDueTask(array $state, \DateTimeImmutable $now): bool
    {
        foreach ($this->taskDefinitions() as $task) {
            if ($this->isTaskDue($task, $state, $now)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{id: string, intervalSeconds: int, command: list<string>, label: string} $task
     * @param array<string, mixed> $state
     */
    private function isTaskDue(array $task, array $state, \DateTimeImmutable $now): bool
    {
        $taskState = \is_array($state['tasks'][$task['id']] ?? null) ? $state['tasks'][$task['id']] : [];
        $lastRunAt = $this->parseDateTime($taskState['lastRunAt'] ?? null);

        return !$lastRunAt instanceof \DateTimeImmutable || $lastRunAt->getTimestamp() + $task['intervalSeconds'] <= $now->getTimestamp();
    }

    /**
     * @param array{id: string, label: string, exitCode: int, succeeded: bool, output: string} $result
     */
    private function persistTaskResult(string $taskId, array $result, \DateTimeImmutable $finishedAt): void
    {
        $this->withStateLock(function () use ($taskId, $result, $finishedAt): void {
            $state = $this->readState();
            $state['tasks'][$taskId] = array_merge(
                \is_array($state['tasks'][$taskId] ?? null) ? $state['tasks'][$taskId] : [],
                [
                    'lastFinishedAt' => $finishedAt->format(DATE_ATOM),
                    'lastStatus' => $result['succeeded'] ? 'completed' : 'failed',
                    'lastExitCode' => $result['exitCode'],
                    'lastOutput' => mb_substr($result['output'], 0, 4000),
                ],
            );
            $this->writeState($state);
        });
    }

    /**
     * @return list<array{id: string, label: string, intervalSeconds: int, command: list<string>}>
     */
    private function taskDefinitions(): array
    {
        return [
            [
                'id' => 'mail_poll',
                'label' => 'Mailpolling',
                'intervalSeconds' => 300,
                'command' => [\PHP_BINARY, 'bin/console', 'app:mail:poll', $this->environmentArgument()],
            ],
            [
                'id' => 'ticket_sla',
                'label' => 'SLA-kontroll',
                'intervalSeconds' => 900,
                'command' => [\PHP_BINARY, 'bin/console', 'app:check-ticket-sla', $this->environmentArgument()],
            ],
            [
                'id' => 'ticket_attachment_archive',
                'label' => 'Bilagearkivering',
                'intervalSeconds' => 86400,
                'command' => [\PHP_BINARY, 'bin/console', 'app:archive-ticket-attachments', $this->environmentArgument()],
            ],
            [
                'id' => 'company_monthly_reports',
                'label' => 'Månadsrapporter',
                'intervalSeconds' => 86400,
                'command' => [\PHP_BINARY, 'bin/console', 'app:reports:send-monthly', $this->environmentArgument()],
            ],
        ];
    }

    private function environmentArgument(): string
    {
        return '--env='.$this->environment;
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(): array
    {
        $path = $this->statePath();
        if (!is_file($path)) {
            return $this->defaultState();
        }

        try {
            /** @var array<string, mixed> $state */
            $state = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);

            return array_replace_recursive($this->defaultState(), $state);
        } catch (\JsonException) {
            return $this->defaultState();
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function writeState(array $state): void
    {
        file_put_contents(
            $this->statePath(),
            json_encode($state, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array{version: int, runnerQueuedAt: ?string, runnerStartedAt: ?string, runnerFinishedAt: ?string, tasks: array<string, mixed>}
     */
    private function defaultState(): array
    {
        return [
            'version' => 1,
            'runnerQueuedAt' => null,
            'runnerStartedAt' => null,
            'runnerFinishedAt' => null,
            'tasks' => [],
        ];
    }

    private function parseDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withStateLock(callable $callback): mixed
    {
        $lockHandle = fopen($this->lockPath(), 'c');
        if (false === $lockHandle) {
            throw new \RuntimeException('Kunde inte öppna låsfilen för interna driftjobb.');
        }

        try {
            flock($lockHandle, \LOCK_EX);

            return $callback();
        } finally {
            flock($lockHandle, \LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function statePath(): string
    {
        return $this->stateDirectory().\DIRECTORY_SEPARATOR.self::STATE_FILENAME;
    }

    private function lockPath(): string
    {
        return $this->stateDirectory().\DIRECTORY_SEPARATOR.self::LOCK_FILENAME;
    }

    private function stateDirectory(): string
    {
        $directory = $this->projectDir.\DIRECTORY_SEPARATOR.'var';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Kunde inte skapa var-katalogen för interna driftjobb.');
        }

        return $directory;
    }
}
