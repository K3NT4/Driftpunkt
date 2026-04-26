<?php

declare(strict_types=1);

namespace App\Module\System\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CodeUpdateApplyRunner
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly CodeUpdateManager $codeUpdateManager,
        private readonly ProjectCommandExecutor $commandExecutor,
        private readonly BackgroundConsoleLauncher $backgroundConsoleLauncher,
        private readonly SystemSettings $systemSettings,
    ) {
    }

    /**
     * @return list<array{
     *     id: string,
     *     packageId: string,
     *     packageName: string,
     *     packageVersion: string,
     *     queuedAt: \DateTimeImmutable,
     *     startedAt: ?\DateTimeImmutable,
     *     finishedAt: ?\DateTimeImmutable,
     *     status: string,
     *     succeeded: ?bool,
     *     output: string,
     *     backupFilename: ?string
     * }>
     */
    public function listRecentRuns(): array
    {
        $runs = [];
        foreach (glob($this->runsDirectory().\DIRECTORY_SEPARATOR.'*.json') ?: [] as $path) {
            $normalized = $this->normalizeRun($this->readRawRun(pathinfo($path, \PATHINFO_FILENAME)));
            if (null !== $normalized) {
                $runs[] = $normalized;
            }
        }

        usort(
            $runs,
            static fn (array $left, array $right): int => $right['queuedAt']->getTimestamp() <=> $left['queuedAt']->getTimestamp(),
        );

        return array_slice($runs, 0, 8);
    }

    /**
     * @return array{
     *     id: string,
     *     packageId: string,
     *     packageName: string,
     *     packageVersion: string,
     *     queuedAt: \DateTimeImmutable,
     *     startedAt: ?\DateTimeImmutable,
     *     finishedAt: ?\DateTimeImmutable,
     *     status: string,
     *     succeeded: ?bool,
     *     output: string,
     *     backupFilename: ?string
     * }
     */
    public function queueApply(string $packageId): array
    {
        if ($this->hasActiveRun()) {
            throw new \RuntimeException('En koduppdatering är redan köad eller körs. Vänta tills den är klar innan nästa version köas.');
        }

        $releaseState = $this->systemSettings->getUpdateReleaseState();
        if ($releaseState['pendingConfirmation'] ?? false) {
            throw new \RuntimeException('Slutför verifieringen av föregående uppdatering innan nästa version köas.');
        }

        $package = null;
        foreach ($this->codeUpdateManager->listStagedPackages() as $candidate) {
            if (($candidate['id'] ?? null) === $packageId) {
                $package = $candidate;
                break;
            }
        }

        if (!\is_array($package) || !($package['valid'] ?? false)) {
            throw new \RuntimeException('Det valda uppdateringspaketet finns inte eller är inte redo att appliceras.');
        }

        $this->assertWritableUpdatePaths((string) $package['id']);

        $runId = sprintf('%s_%s', (new \DateTimeImmutable())->format('Ymd_His'), bin2hex(random_bytes(4)));
        $run = [
            'id' => $runId,
            'packageId' => (string) $package['id'],
            'packageName' => (string) $package['packageName'],
            'packageVersion' => (string) $package['packageVersion'],
            'queuedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'startedAt' => null,
            'finishedAt' => null,
            'status' => 'queued',
            'succeeded' => null,
            'output' => '',
            'backupFilename' => null,
        ];

        $this->persistRun($run);
        $this->backgroundConsoleLauncher->launch(
            [\PHP_BINARY, 'bin/console', 'app:code-update:apply-run', $runId],
            $this->projectDir,
        );

        $normalized = $this->normalizeRun($run);
        if (null === $normalized) {
            throw new \RuntimeException('Kunde inte läsa tillbaka den köade koduppdateringen.');
        }

        return $normalized;
    }

    /**
     * @return array{
     *     id: string,
     *     packageId: string,
     *     packageName: string,
     *     packageVersion: string,
     *     queuedAt: \DateTimeImmutable,
     *     startedAt: ?\DateTimeImmutable,
     *     finishedAt: ?\DateTimeImmutable,
     *     status: string,
     *     succeeded: ?bool,
     *     output: string,
     *     backupFilename: ?string
     * }
     */
    public function processQueuedRun(string $runId): array
    {
        $run = $this->readRawRun($runId);
        if (null === $run) {
            throw new \RuntimeException('Den köade koduppdateringen kunde inte hittas.');
        }

        if (\in_array((string) ($run['status'] ?? ''), ['completed', 'failed'], true)) {
            $normalizedExisting = $this->normalizeRun($run);
            if (null === $normalizedExisting) {
                throw new \RuntimeException('Kunde inte läsa loggen för koduppdateringen.');
            }

            return $normalizedExisting;
        }

        if (!isset($run['packageId']) || !\is_string($run['packageId']) || '' === trim($run['packageId'])) {
            $run['status'] = 'failed';
            $run['succeeded'] = false;
            $run['output'] = 'Körningen saknar packageId och kan därför inte applicera ett versionspaket. Kör efter-uppdateringssteg med app:post-update:run i stället.';
            $run['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            $this->persistRun($run);

            throw new \RuntimeException($run['output']);
        }

        $run['status'] = 'running';
        $run['startedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->persistRun($run);

        try {
            $this->assertWritableUpdatePaths((string) $run['packageId']);
            $preApplyResults = $this->runPreApplyChecks();
            $failedPreApplyResult = $this->firstFailedTaskResult($preApplyResults);

            if (null !== $failedPreApplyResult) {
                $run['status'] = 'failed';
                $run['succeeded'] = false;
                $run['output'] = sprintf(
                    "Uppdateringen stoppades före kodkopiering eftersom förkontrollen \"%s\" misslyckades.\n\n%s",
                    $failedPreApplyResult['label'],
                    $this->formatTaskResults($preApplyResults),
                );
                $run['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
                $this->persistRun($run);

                $normalized = $this->normalizeRun($run);
                if (null === $normalized) {
                    throw new \RuntimeException('Kunde inte läsa tillbaka resultatet för koduppdateringen.');
                }

                return $normalized;
            }

            $result = $this->codeUpdateManager->applyStagedPackage((string) $run['packageId']);
            $postApplyResults = $this->runRequiredPostApplyTasks();
            $failedPostApplyResult = $this->firstFailedTaskResult($postApplyResults);

            if (null !== $failedPostApplyResult) {
                $run['status'] = 'failed';
                $run['succeeded'] = false;
                $run['backupFilename'] = $result['applicationBackup']['filename'] ?? null;
                $run['output'] = sprintf(
                    "Paketet %s (%s) kopierades och backup %s skapades, men eftersteget \"%s\" misslyckades.\n\nFörkontroller:\n%s\n\nEftersteg:\n%s",
                    $run['packageName'],
                    $run['packageVersion'],
                    $run['backupFilename'] ?? 'okänd backup',
                    $failedPostApplyResult['label'],
                    $this->formatTaskResults($preApplyResults),
                    $this->formatTaskResults($postApplyResults),
                );
                $run['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
                $this->persistRun($run);

                $normalized = $this->normalizeRun($run);
                if (null === $normalized) {
                    throw new \RuntimeException('Kunde inte läsa tillbaka resultatet för koduppdateringen.');
                }

                return $normalized;
            }

            $run['status'] = 'completed';
            $run['succeeded'] = true;
            $run['backupFilename'] = $result['applicationBackup']['filename'] ?? null;
            $run['output'] = sprintf(
                "Paketet %s (%s) applicerades, backup %s skapades och obligatoriska eftersteg kördes klart.\n\nFörkontroller:\n%s\n\nEftersteg:\n%s",
                $run['packageName'],
                $run['packageVersion'],
                $run['backupFilename'] ?? 'okänd backup',
                $this->formatTaskResults($preApplyResults),
                $this->formatTaskResults($postApplyResults),
            );
            $this->systemSettings->markUpdateReleasePendingConfirmation(
                (string) $run['packageName'],
                (string) $run['packageVersion'],
                new \DateTimeImmutable(),
            );
        } catch (\Throwable $exception) {
            $run['status'] = 'failed';
            $run['succeeded'] = false;
            $run['output'] = $exception->getMessage();
        }

        $run['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->persistRun($run);

        $normalized = $this->normalizeRun($run);
        if (null === $normalized) {
            throw new \RuntimeException('Kunde inte läsa tillbaka resultatet för koduppdateringen.');
        }

        return $normalized;
    }

    /**
     * @return array{
     *     id: string,
     *     packageId: string,
     *     packageName: string,
     *     packageVersion: string,
     *     queuedAt: \DateTimeImmutable,
     *     startedAt: ?\DateTimeImmutable,
     *     finishedAt: ?\DateTimeImmutable,
     *     status: string,
     *     succeeded: ?bool,
     *     output: string,
     *     backupFilename: ?string
     * }|null
     */
    public function getRunById(string $runId): ?array
    {
        return $this->normalizeRun($this->readRawRun($runId));
    }

    public function purgeFinishedRuns(): int
    {
        $deleted = 0;

        foreach (glob($this->runsDirectory().\DIRECTORY_SEPARATOR.'*.json') ?: [] as $path) {
            $run = $this->readRawRun(pathinfo($path, \PATHINFO_FILENAME));
            if (null === $run || !isset($run['packageId'])) {
                continue;
            }

            if (!\in_array((string) ($run['status'] ?? ''), ['completed', 'failed'], true)) {
                continue;
            }

            if (@unlink($path)) {
                ++$deleted;
            }
        }

        return $deleted;
    }

    public function failStaleQueuedRuns(\DateTimeImmutable $queuedBefore, string $reason): int
    {
        $failed = 0;

        foreach (glob($this->runsDirectory().\DIRECTORY_SEPARATOR.'*.json') ?: [] as $path) {
            $run = $this->readRawRun(pathinfo($path, \PATHINFO_FILENAME));
            $normalizedRun = $this->normalizeRun($run);
            if (null === $normalizedRun || 'queued' !== $normalizedRun['status']) {
                continue;
            }

            if ($normalizedRun['queuedAt'] > $queuedBefore) {
                continue;
            }

            $run['status'] = 'failed';
            $run['succeeded'] = false;
            $run['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            $existingOutput = trim((string) ($run['output'] ?? ''));
            $run['output'] = '' !== $existingOutput ? $existingOutput."\n".$reason : $reason;
            $this->persistRun($run);
            ++$failed;
        }

        return $failed;
    }

    public function failStaleRunningRuns(\DateTimeImmutable $startedBefore, string $reason): int
    {
        $failed = 0;

        foreach (glob($this->runsDirectory().\DIRECTORY_SEPARATOR.'*.json') ?: [] as $path) {
            $run = $this->readRawRun(pathinfo($path, \PATHINFO_FILENAME));
            $normalizedRun = $this->normalizeRun($run);
            if (null === $normalizedRun || 'running' !== $normalizedRun['status'] || null === $normalizedRun['startedAt']) {
                continue;
            }

            if ($normalizedRun['startedAt'] > $startedBefore) {
                continue;
            }

            $run['status'] = 'failed';
            $run['succeeded'] = false;
            $run['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            $existingOutput = trim((string) ($run['output'] ?? ''));
            $run['output'] = '' !== $existingOutput ? $existingOutput."\n".$reason : $reason;
            $this->persistRun($run);
            ++$failed;
        }

        return $failed;
    }

    public function purgeOldFinishedRuns(\DateTimeImmutable $completedBefore, \DateTimeImmutable $failedBefore): int
    {
        $deleted = 0;

        foreach (glob($this->runsDirectory().\DIRECTORY_SEPARATOR.'*.json') ?: [] as $path) {
            $run = $this->readRawRun(pathinfo($path, \PATHINFO_FILENAME));
            if (null === $run || !isset($run['packageId'])) {
                continue;
            }

            if (!$this->isOldFinishedRecord($run, $completedBefore, $failedBefore)) {
                continue;
            }

            if (@unlink($path)) {
                ++$deleted;
            }
        }

        return $deleted;
    }

    private function hasActiveRun(): bool
    {
        foreach ($this->listRecentRuns() as $run) {
            if (\in_array($run['status'], ['queued', 'running'], true)) {
                return true;
            }
        }

        return false;
    }

    private function assertWritableUpdatePaths(string $packageId): void
    {
        $problems = [];

        foreach ([
            $this->projectDir => 'projektmappen',
            $this->projectDir.\DIRECTORY_SEPARATOR.'var' => 'var-mappen',
        ] as $directory => $label) {
            if (!is_dir($directory)) {
                $problems[] = sprintf('%s saknas', $label);

                continue;
            }

            if (!is_writable($directory)) {
                $problems[] = sprintf('%s är inte skrivbar', $label);
            }
        }

        if ([] !== $problems) {
            throw new \RuntimeException(sprintf(
                "Förkontrollen misslyckades: webbcontainern kan inte skriva alla filer som uppdateraren hanterar.\n%s\n\nKör exempelvis `chown -R www-data:www-data` på applikationsfilerna innan du applicerar versionen.",
                implode("\n", array_map(static fn (string $problem): string => '- '.$problem, array_slice($problems, 0, 12))),
            ));
        }

        $this->codeUpdateManager->assertStagedPackageCanBeApplied($packageId);
    }

    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     command: list<string>,
     *     exitCode: int,
     *     succeeded: bool,
     *     output: string
     * }>
     */
    private function runPreApplyChecks(): array
    {
        return $this->executeTasks($this->preApplyTasks());
    }

    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     command: list<string>,
     *     exitCode: int,
     *     succeeded: bool,
     *     output: string
     * }>
     */
    private function runRequiredPostApplyTasks(): array
    {
        return $this->executeTasks($this->requiredPostApplyTasks());
    }

    /**
     * @param list<array{id: string, label: string, command: list<string>, deferred?: bool}> $tasks
     *
     * @return list<array{
     *     id: string,
     *     label: string,
     *     command: list<string>,
     *     exitCode: int,
     *     succeeded: bool,
     *     output: string
     * }>
     */
    private function executeTasks(array $tasks): array
    {
        $results = [];

        foreach ($tasks as $task) {
            if ($task['deferred'] ?? false) {
                $this->backgroundConsoleLauncher->launch($task['command'], $this->projectDir);
                $execution = [
                    'exitCode' => 0,
                    'output' => 'Cache-refresh köades som fristående bakgrundssteg så den inte kan avbryta den aktiva Symfony-processen.',
                ];
            } else {
                $execution = $this->commandExecutor->execute($task['command'], $this->projectDir);
            }

            $result = [
                'id' => $task['id'],
                'label' => $task['label'],
                'command' => $task['command'],
                'exitCode' => $execution['exitCode'],
                'succeeded' => 0 === $execution['exitCode'],
                'output' => $execution['output'],
            ];
            $results[] = $result;

            if (!$result['succeeded']) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return list<array{id: string, label: string, command: list<string>, deferred?: bool}>
     */
    private function preApplyTasks(): array
    {
        return [
            [
                'id' => 'database_connection',
                'label' => 'Databasanslutning',
                'command' => [\PHP_BINARY, 'bin/console', 'doctrine:migrations:status', '--no-interaction', '--env=prod'],
            ],
        ];
    }

    /**
     * These commands intentionally run inside the apply process, after files are copied but before
     * the update is marked complete. A separate Symfony command can fail to boot when new code
     * references dependencies that are not installed yet.
     *
     * @return list<array{id: string, label: string, command: list<string>, deferred?: bool}>
     */
    private function requiredPostApplyTasks(): array
    {
        $phpBinary = \PHP_BINARY;
        $composerBinary = is_file($this->projectDir.\DIRECTORY_SEPARATOR.'composer')
            ? $this->projectDir.\DIRECTORY_SEPARATOR.'composer'
            : 'composer';
        $deferredCacheRefresh = 'sleep 2; rm -rf var/cache/prod var/cache/prod_*; '.$phpBinary.' bin/console cache:warmup --env=prod';

        return [
            [
                'id' => 'composer_install',
                'label' => 'Composer install',
                'command' => [$phpBinary, $composerBinary, 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--optimize-autoloader'],
            ],
            [
                'id' => 'doctrine_migrate',
                'label' => 'Databasmigreringar',
                'command' => [$phpBinary, 'bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration', '--all-or-nothing', '--env=prod'],
            ],
            [
                'id' => 'cache_refresh',
                'label' => 'Refresh prod-cache',
                'command' => ['bash', '-lc', $deferredCacheRefresh],
                'deferred' => true,
            ],
        ];
    }

    /**
     * @param list<array{
     *     id: string,
     *     label: string,
     *     command: list<string>,
     *     exitCode: int,
     *     succeeded: bool,
     *     output: string
     * }> $results
     *
     * @return array{
     *     id: string,
     *     label: string,
     *     command: list<string>,
     *     exitCode: int,
     *     succeeded: bool,
     *     output: string
     * }|null
     */
    private function firstFailedTaskResult(array $results): ?array
    {
        foreach ($results as $result) {
            if (!$result['succeeded']) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param list<array{
     *     id: string,
     *     label: string,
     *     command: list<string>,
     *     exitCode: int,
     *     succeeded: bool,
     *     output: string
     * }> $results
     */
    private function formatTaskResults(array $results): string
    {
        $lines = [];
        foreach ($results as $result) {
            $lines[] = sprintf(
                '[%s] %s (exit %d): %s',
                $result['succeeded'] ? 'OK' : 'FEL',
                $result['label'],
                $result['exitCode'],
                '' !== trim($result['output']) ? trim($result['output']) : 'Ingen output.',
            );
        }

        return implode("\n\n", $lines);
    }

    private function runsDirectory(): string
    {
        $directory = $this->projectDir.\DIRECTORY_SEPARATOR.'var'.\DIRECTORY_SEPARATOR.'code_update_runs';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Kunde inte skapa körloggen för koduppdateringar.');
        }

        return $directory;
    }

    /**
     * @param array<string, mixed> $run
     */
    private function persistRun(array $run): void
    {
        file_put_contents(
            $this->runsDirectory().\DIRECTORY_SEPARATOR.(string) $run['id'].'.json',
            json_encode($run, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readRawRun(string $runId): ?array
    {
        $path = $this->runsDirectory().\DIRECTORY_SEPARATOR.basename($runId).'.json';
        if (!is_file($path)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $raw */
            $raw = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return $raw;
    }

    /**
     * @param array<string, mixed>|null $raw
     * @return array{
     *     id: string,
     *     packageId: string,
     *     packageName: string,
     *     packageVersion: string,
     *     queuedAt: \DateTimeImmutable,
     *     startedAt: ?\DateTimeImmutable,
     *     finishedAt: ?\DateTimeImmutable,
     *     status: string,
     *     succeeded: ?bool,
     *     output: string,
     *     backupFilename: ?string
     * }|null
     */
    private function normalizeRun(?array $raw): ?array
    {
        if (!\is_array($raw) || !isset($raw['id'], $raw['packageId'], $raw['queuedAt'], $raw['status'])) {
            return null;
        }

        try {
            $queuedAt = new \DateTimeImmutable((string) $raw['queuedAt']);
            $startedAt = \is_string($raw['startedAt'] ?? null) && '' !== trim((string) $raw['startedAt'])
                ? new \DateTimeImmutable((string) $raw['startedAt'])
                : null;
            $finishedAt = \is_string($raw['finishedAt'] ?? null) && '' !== trim((string) $raw['finishedAt'])
                ? new \DateTimeImmutable((string) $raw['finishedAt'])
                : null;
        } catch (\Throwable) {
            return null;
        }

        return [
            'id' => (string) $raw['id'],
            'packageId' => (string) $raw['packageId'],
            'packageName' => (string) ($raw['packageName'] ?? 'Okänt paket'),
            'packageVersion' => (string) ($raw['packageVersion'] ?? 'okänd'),
            'queuedAt' => $queuedAt,
            'startedAt' => $startedAt,
            'finishedAt' => $finishedAt,
            'status' => (string) $raw['status'],
            'succeeded' => \is_bool($raw['succeeded'] ?? null) ? $raw['succeeded'] : null,
            'output' => (string) ($raw['output'] ?? ''),
            'backupFilename' => \is_string($raw['backupFilename'] ?? null) && '' !== trim((string) $raw['backupFilename'])
                ? (string) $raw['backupFilename']
                : null,
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function isOldFinishedRecord(array $record, \DateTimeImmutable $completedBefore, \DateTimeImmutable $failedBefore): bool
    {
        $status = (string) ($record['status'] ?? '');
        if (!\in_array($status, ['completed', 'failed'], true)) {
            return false;
        }

        try {
            $finishedAt = new \DateTimeImmutable((string) ($record['finishedAt'] ?? ''));
        } catch (\Throwable) {
            return false;
        }

        return 'completed' === $status
            ? $finishedAt <= $completedBefore
            : $finishedAt <= $failedBefore;
    }
}
