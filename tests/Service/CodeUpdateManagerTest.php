<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\System\Service\CodeUpdateManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CodeUpdateManagerTest extends TestCase
{
    private string $projectDir;
    private CodeUpdateManager $manager;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/driftpunkt-code-update-'.bin2hex(random_bytes(4));
        mkdir($this->projectDir.'/bin', 0777, true);
        mkdir($this->projectDir.'/config/packages', 0777, true);
        mkdir($this->projectDir.'/migrations', 0777, true);
        mkdir($this->projectDir.'/public', 0777, true);
        mkdir($this->projectDir.'/src', 0777, true);
        mkdir($this->projectDir.'/templates', 0777, true);
        mkdir($this->projectDir.'/var', 0777, true);

        file_put_contents($this->projectDir.'/bin/console', "#!/usr/bin/env php\n<?php\n");
        file_put_contents($this->projectDir.'/composer.json', json_encode([
            'name' => 'driftpunkt/local',
            'version' => '1.0.0',
            'description' => 'Lokal testinstallation',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        file_put_contents($this->projectDir.'/composer.lock', json_encode([
            'packages' => [],
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        file_put_contents($this->projectDir.'/config/packages/framework.yaml', "framework:\n    secret: old\n");
        file_put_contents($this->projectDir.'/migrations/Version20260419000000.php', "<?php\n");
        file_put_contents($this->projectDir.'/public/index.php', "<?php echo 'old';\n");
        file_put_contents($this->projectDir.'/src/Version.php', "<?php\nreturn '1.0.0';\n");
        file_put_contents($this->projectDir.'/src/Obsolete.php', "<?php\nreturn 'old-obsolete';\n");
        file_put_contents($this->projectDir.'/templates/base.html.twig', "<html>old</html>\n");

        $this->manager = new CodeUpdateManager($this->projectDir);
    }

    protected function tearDown(): void
    {
        if (is_file($this->projectDir.'/composer.lock')) {
            chmod($this->projectDir.'/composer.lock', 0666);
        }

        $this->removeDirectory($this->projectDir);
    }

    public function testItDescribesInstalledReleaseFromReleaseMetadata(): void
    {
        file_put_contents($this->projectDir.'/release-metadata.json', json_encode([
            'applicationName' => 'driftpunkt/nas-release',
            'packageType' => 'upgrade',
            'version' => '2026.04.26',
            'builtAt' => '2026-04-26T12:00:00+02:00',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        $application = $this->manager->describeCurrentApplication();

        self::assertSame('driftpunkt/nas-release', $application['name']);
        self::assertSame('2026.04.26', $application['version']);
        self::assertStringContainsString('Installerad release', $application['summary']);
        self::assertStringContainsString('2026-04-26 12:00', $application['summary']);
    }

    public function testItCanStageAndApplyValidZipPackage(): void
    {
        $zipPath = $this->createUpdatePackageZip();
        $expectedPackageChecksum = hash_file('sha256', $zipPath);
        $uploadedFile = new UploadedFile($zipPath, 'release.zip', 'application/zip', null, true);

        $package = $this->manager->stageUploadedPackage($uploadedFile);
        self::assertTrue($package['valid']);
        self::assertSame('driftpunkt/release', $package['packageName']);
        self::assertSame('2.1.0', $package['packageVersion']);
        self::assertSame('upgrade', $package['releasePackageType']);
        self::assertTrue($package['releaseMetadataPresent']);
        self::assertSame($expectedPackageChecksum, $package['packageChecksumSha256']);
        self::assertGreaterThanOrEqual(1, $package['changeSummary']['modified']);
        self::assertGreaterThanOrEqual(1, $package['changeSummary']['removed']);
        self::assertContains('public/index.php', array_column($package['changedFiles'], 'path'));
        self::assertNotEmpty($package['preflightChecks']);

        $result = $this->manager->applyStagedPackage($package['id']);

        self::assertNotNull($result['package']['appliedAt']);
        self::assertFileExists($result['applicationBackup']['path']);
        self::assertStringContainsString("echo 'new';", (string) file_get_contents($this->projectDir.'/public/index.php'));
        self::assertStringContainsString("return '2.1.0';", (string) file_get_contents($this->projectDir.'/src/Version.php'));
        self::assertStringContainsString('new-secret', (string) file_get_contents($this->projectDir.'/config/packages/framework.yaml'));
        self::assertStringContainsString('"lock": "new"', (string) file_get_contents($this->projectDir.'/composer.lock'));
        self::assertFileDoesNotExist($this->projectDir.'/src/Obsolete.php');
        self::assertFileDoesNotExist($this->projectDir.'/migrations/Version20260419000000.php');
    }

    public function testPreflightFailsWhenManagedTargetFileIsNotWritable(): void
    {
        $packageRoot = $this->createPackageManifestWithComposerLockOnly();
        chmod($this->projectDir.'/composer.lock', 0444);
        file_put_contents($packageRoot.'/composer.lock', json_encode(['lock' => 'new'], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('composer.lock är inte skrivbar');

        $this->manager->assertStagedPackageCanBeApplied('preflight-package');
    }

    public function testPreflightPassesWhenPackageTargetsAreWritable(): void
    {
        $packageRoot = $this->createPackageManifestWithComposerLockOnly();
        file_put_contents($packageRoot.'/composer.lock', json_encode(['lock' => 'new'], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        $this->manager->assertStagedPackageCanBeApplied('preflight-package');

        self::assertTrue(true);
    }

    private function createPackageManifestWithComposerLockOnly(): string
    {
        $stagingRoot = $this->projectDir.'/var/code_update_staging/preflight-package';
        $packageRoot = $stagingRoot.'/extracted/release';
        mkdir($packageRoot, 0777, true);

        file_put_contents($stagingRoot.'/manifest.json', json_encode([
            'id' => 'preflight-package',
            'originalFilename' => 'release.zip',
            'packageName' => 'driftpunkt/preflight',
            'packageVersion' => 'test',
            'uploadedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'valid' => true,
            'validationMessages' => [],
            'fileCount' => 1,
            'includesVendor' => true,
            'packageRoot' => $packageRoot,
            'appliedAt' => null,
            'applicationBackupFilename' => null,
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        return $packageRoot;
    }

    private function createUpdatePackageZip(string $packageType = 'upgrade', bool $corruptManifest = false): string
    {
        $packageRoot = sys_get_temp_dir().'/driftpunkt-code-package-'.bin2hex(random_bytes(4));
        mkdir($packageRoot.'/release/bin', 0777, true);
        mkdir($packageRoot.'/release/config/packages', 0777, true);
        mkdir($packageRoot.'/release/public', 0777, true);
        mkdir($packageRoot.'/release/src', 0777, true);
        mkdir($packageRoot.'/release/templates', 0777, true);
        mkdir($packageRoot.'/release/vendor', 0777, true);

        file_put_contents($packageRoot.'/release/bin/console', "#!/usr/bin/env php\n<?php\n");
        file_put_contents($packageRoot.'/release/composer.json', json_encode([
            'name' => 'driftpunkt/release',
            'version' => '2.1.0',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        file_put_contents($packageRoot.'/release/composer.lock', json_encode([
            'lock' => 'new',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        file_put_contents($packageRoot.'/release/config/packages/framework.yaml', "framework:\n    secret: new-secret\n");
        file_put_contents($packageRoot.'/release/public/index.php', "<?php echo 'new';\n");
        file_put_contents($packageRoot.'/release/src/Version.php', "<?php\nreturn '2.1.0';\n");
        file_put_contents($packageRoot.'/release/templates/base.html.twig', "<html>new</html>\n");
        file_put_contents($packageRoot.'/release/vendor/autoload.php', "<?php\n");
        $this->writeReleaseMetadata($packageRoot.'/release', $packageType, $corruptManifest);

        $zipPath = tempnam(sys_get_temp_dir(), 'driftpunkt-release-');
        if (false === $zipPath) {
            throw new \RuntimeException('Kunde inte skapa zipfil för koduppdateringstestet.');
        }
        @unlink($zipPath);
        $zipPath .= '.zip';

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if (true !== $result) {
            throw new \RuntimeException('Kunde inte öppna zipfil för koduppdateringstestet.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($packageRoot.'/release', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), \strlen($packageRoot.'/release/'));
            $zip->addFile($file->getPathname(), 'release/'.$relativePath);
        }

        $zip->close();
        $this->removeDirectory($packageRoot);

        return $zipPath;
    }

    private function writeReleaseMetadata(string $packageDirectory, string $packageType, bool $corruptManifest = false): void
    {
        $files = $this->collectReleaseFiles($packageDirectory);
        $contentManifestSha256 = hash('sha256', json_encode($files, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));
        if ($corruptManifest) {
            $contentManifestSha256 = str_repeat('0', 64);
        }

        file_put_contents($packageDirectory.'/release-metadata.json', json_encode([
            'applicationName' => 'driftpunkt/release',
            'packageType' => $packageType,
            'version' => '2.1.0',
            'builtAt' => (new \DateTimeImmutable('2026-04-26T10:00:00+02:00'))->format(DATE_ATOM),
            'installationMode' => 'install' === $packageType ? 'fresh_install' : 'upgrade',
            'contentManifestSha256' => $contentManifestSha256,
            'fileCount' => \count($files),
            'managedPaths' => ['bin', 'config', 'public', 'src', 'templates', 'composer.json', 'composer.lock'],
            'preflightChecklist' => [
                'Aktivera underhållsläge',
                'Verifiera databasanslutning',
                'Behåll automatisk kodbackup',
            ],
            'files' => $files,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<array{path: string, sizeBytes: int, sha256: string}>
     */
    private function collectReleaseFiles(string $packageDirectory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($packageDirectory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), \strlen($packageDirectory.'/')));
            if ('release-metadata.json' === $relativePath) {
                continue;
            }

            $files[] = [
                'path' => $relativePath,
                'sizeBytes' => (int) $file->getSize(),
                'sha256' => hash_file('sha256', $file->getPathname()),
            ];
        }

        usort($files, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);

        return $files;
    }

    public function testItRejectsPackageWithoutCoreApplicationFiles(): void
    {
        $zipPath = $this->createInvalidUpdatePackageZip();
        $uploadedFile = new UploadedFile($zipPath, 'release.zip', 'application/zip', null, true);

        $package = $this->manager->stageUploadedPackage($uploadedFile);

        self::assertFalse($package['valid']);
        self::assertContains('Saknar bin/console i paketet.', $package['validationMessages']);
        self::assertContains('Saknar config i paketet.', $package['validationMessages']);
        self::assertContains('Saknar composer.lock i paketet.', $package['validationMessages']);
        self::assertContains('Saknar vendor/autoload.php i paketet.', $package['validationMessages']);
    }

    public function testItRejectsInstallPackageWhenStagingCodeUpdate(): void
    {
        $zipPath = $this->createUpdatePackageZip('install');
        $uploadedFile = new UploadedFile($zipPath, 'driftpunkt-install.zip', 'application/zip', null, true);

        $package = $this->manager->stageUploadedPackage($uploadedFile);

        self::assertFalse($package['valid']);
        self::assertSame('install', $package['releasePackageType']);
        self::assertContains('Paketet är ett install-paket och ska användas för nyinstallation, inte som koduppgradering.', $package['validationMessages']);
    }

    public function testItRejectsPackageWhenReleaseManifestChecksumDoesNotMatch(): void
    {
        $zipPath = $this->createUpdatePackageZip('upgrade', true);
        $uploadedFile = new UploadedFile($zipPath, 'driftpunkt-upgrade.zip', 'application/zip', null, true);

        $package = $this->manager->stageUploadedPackage($uploadedFile);

        self::assertFalse($package['valid']);
        self::assertContains('Filmanifestet i release-metadata stämmer inte med paketets innehåll.', $package['validationMessages']);
    }

    private function createInvalidUpdatePackageZip(): string
    {
        $packageRoot = sys_get_temp_dir().'/driftpunkt-invalid-code-package-'.bin2hex(random_bytes(4));
        mkdir($packageRoot.'/release/public', 0777, true);
        mkdir($packageRoot.'/release/src', 0777, true);
        mkdir($packageRoot.'/release/templates', 0777, true);

        file_put_contents($packageRoot.'/release/composer.json', json_encode([
            'name' => 'driftpunkt/broken-release',
            'version' => '2.1.0',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        file_put_contents($packageRoot.'/release/public/index.php', "<?php echo 'new';\n");
        file_put_contents($packageRoot.'/release/src/Version.php', "<?php\nreturn '2.1.0';\n");
        file_put_contents($packageRoot.'/release/templates/base.html.twig', "<html>new</html>\n");

        $zipPath = $this->createZipFromDirectory($packageRoot.'/release', 'release');
        $this->removeDirectory($packageRoot);

        return $zipPath;
    }

    private function createZipFromDirectory(string $sourceDirectory, string $archiveRoot): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'driftpunkt-release-');
        if (false === $zipPath) {
            throw new \RuntimeException('Kunde inte skapa zipfil för koduppdateringstestet.');
        }
        @unlink($zipPath);
        $zipPath .= '.zip';

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if (true !== $result) {
            throw new \RuntimeException('Kunde inte öppna zipfil för koduppdateringstestet.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDirectory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), \strlen($sourceDirectory.'/'));
            $zip->addFile($file->getPathname(), $archiveRoot.'/'.$relativePath);
        }

        $zip->close();

        return $zipPath;
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

            @chmod($item->getPathname(), 0666);
            @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
