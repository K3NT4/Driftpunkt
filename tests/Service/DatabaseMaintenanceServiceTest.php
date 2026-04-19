<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\System\Service\DatabaseMaintenanceService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class DatabaseMaintenanceServiceTest extends TestCase
{
    private string $projectDir;
    private string $databasePath;
    private DatabaseMaintenanceService $service;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/driftpunkt-db-service-'.bin2hex(random_bytes(4));
        mkdir($this->projectDir.'/var', 0777, true);

        $this->databasePath = $this->projectDir.'/var/test.sqlite';
        $database = new \SQLite3($this->databasePath);
        $database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL)');
        $database->exec("INSERT INTO users (email) VALUES ('before@test.local')");
        $database->close();

        $this->service = new DatabaseMaintenanceService($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    public function testItCanCreateOptimizeAndRestoreSqliteBackup(): void
    {
        $backup = $this->service->createSqliteBackup($this->databasePath);

        self::assertSame('sqlite_zip_backup', $backup['format']);
        self::assertFileExists($backup['path']);

        $optimization = $this->service->optimizeSqliteDatabase($this->databasePath);
        self::assertGreaterThan(0, $optimization['beforeSizeBytes']);
        self::assertGreaterThan(0, $optimization['afterSizeBytes']);

        $database = new \SQLite3($this->databasePath);
        $database->exec('DELETE FROM users');
        $database->exec("INSERT INTO users (email) VALUES ('after@test.local')");
        $database->close();

        $uploadedBackup = new UploadedFile($backup['path'], $backup['filename'], 'application/zip', null, true);
        $restore = $this->service->restoreSqliteBackup($this->databasePath, $uploadedBackup);

        self::assertSame($backup['filename'], $restore['restoredFrom']);
        self::assertFileExists($restore['previousBackup']['path']);

        $database = new \SQLite3($this->databasePath, \SQLITE3_OPEN_READONLY);
        $result = $database->querySingle('SELECT email FROM users LIMIT 1');
        $database->close();

        self::assertSame('before@test.local', $result);
        self::assertNotSame($restore['previousBackup']['filename'], $backup['filename']);
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
