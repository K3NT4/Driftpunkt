<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\System\Service\BackgroundConsoleLauncher;
use App\Module\System\Service\DatabaseMaintenanceJobRunner;
use App\Module\System\Service\DatabaseMaintenanceService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class DatabaseMaintenanceJobRunnerTest extends TestCase
{
    private string $projectDir;
    private string $databasePath;
    private DatabaseMaintenanceService $databaseMaintenanceService;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/driftpunkt-db-jobs-'.bin2hex(random_bytes(4));
        mkdir($this->projectDir.'/var', 0777, true);

        $this->databasePath = $this->projectDir.'/var/test.sqlite';
        $database = new \SQLite3($this->databasePath);
        $database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL)');
        $database->exec("INSERT INTO users (email) VALUES ('before@test.local')");
        $database->close();

        $this->databaseMaintenanceService = new DatabaseMaintenanceService($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    public function testItQueuesAndProcessesBackupJob(): void
    {
        $launcher = new class extends BackgroundConsoleLauncher {
            /** @var list<array{arguments: list<string>, workingDirectory: string}> */
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
        };

        $runner = new DatabaseMaintenanceJobRunner($this->projectDir, $this->databaseMaintenanceService, $launcher);
        $queuedJob = $runner->queueBackupJob($this->databasePath);

        self::assertSame('queued', $queuedJob['status']);
        self::assertCount(1, $launcher->launches);

        $job = $runner->processQueuedJob($queuedJob['id']);
        self::assertSame('completed', $job['status']);
        self::assertTrue($job['succeeded']);
        self::assertStringContainsString('Backup skapades', (string) $job['resultSummary']);

        $backups = glob($this->projectDir.'/var/database_backups/*.zip') ?: [];
        self::assertCount(1, $backups);
    }

    public function testItQueuesAndProcessesRestoreJob(): void
    {
        $launcher = new class extends BackgroundConsoleLauncher {
            public function __construct()
            {
            }

            public function launch(array $arguments, string $workingDirectory): void
            {
            }
        };

        $backup = $this->databaseMaintenanceService->createSqliteBackup($this->databasePath);
        $database = new \SQLite3($this->databasePath);
        $database->exec('DELETE FROM users');
        $database->exec("INSERT INTO users (email) VALUES ('after@test.local')");
        $database->close();

        $runner = new DatabaseMaintenanceJobRunner($this->projectDir, $this->databaseMaintenanceService, $launcher);
        $queuedJob = $runner->queueRestoreJob(
            $this->databasePath,
            new UploadedFile($backup['path'], $backup['filename'], 'application/zip', null, true),
        );
        $job = $runner->processQueuedJob($queuedJob['id']);

        self::assertSame('completed', $job['status']);
        self::assertTrue($job['succeeded']);
        self::assertStringContainsString('lästes in', (string) $job['resultSummary']);

        $database = new \SQLite3($this->databasePath, \SQLITE3_OPEN_READONLY);
        $result = $database->querySingle('SELECT email FROM users LIMIT 1');
        $database->close();
        self::assertSame('before@test.local', $result);
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
