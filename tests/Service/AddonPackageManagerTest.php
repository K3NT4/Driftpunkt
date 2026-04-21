<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\System\Service\AddonPackageManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AddonPackageManagerTest extends TestCase
{
    private string $projectDir;
    private AddonPackageManager $manager;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/driftpunkt-addon-package-'.bin2hex(random_bytes(4));
        mkdir($this->projectDir.'/var', 0777, true);

        $this->manager = new AddonPackageManager($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    public function testItCanInstallAddonZipPackageAndListIt(): void
    {
        $zipPath = $this->createValidAddonZip();
        $uploadedFile = new UploadedFile($zipPath, 'status-board.zip', 'application/zip', null, true);

        $package = $this->manager->installUploadedPackage($uploadedFile);

        self::assertSame('status-board', $package['slug']);
        self::assertSame('Status Board', $package['name']);
        self::assertSame('1.4.0', $package['version']);
        self::assertSame('Zip-import', $package['sourceLabel']);
        self::assertSame(['Publik status', 'Adminöversikt'], $package['impactAreas']);
        self::assertSame(['STATUS_BOARD_API_KEY'], $package['environmentVariables']);
        self::assertFileExists($package['archivePath']);
        self::assertFileExists($package['installPath'].'/src/Module/StatusBoard/Controller/StatusBoardController.php');

        $packages = $this->manager->listInstalledPackages();
        self::assertCount(1, $packages);
        self::assertSame('status-board', $packages[0]['slug']);
        self::assertSame('1.4.0', $packages[0]['version']);
        self::assertTrue($packages[0]['isActiveVersion']);
    }

    public function testItCanSwitchActiveAddonVersionToAnEarlierPackage(): void
    {
        $this->manager->installUploadedPackage(new UploadedFile(
            $this->createValidAddonZip('1.4.0'),
            'status-board-1.4.0.zip',
            'application/zip',
            null,
            true,
        ));
        $this->manager->installUploadedPackage(new UploadedFile(
            $this->createValidAddonZip('1.5.0'),
            'status-board-1.5.0.zip',
            'application/zip',
            null,
            true,
        ));

        $activatedPackage = $this->manager->activateInstalledPackageVersion('status-board', '1.4.0');

        self::assertSame('1.4.0', $activatedPackage['version']);
        self::assertTrue($activatedPackage['isActiveVersion']);

        $packages = $this->manager->listInstalledPackagesForSlug('status-board');
        self::assertCount(2, $packages);
        $activePackages = array_values(array_filter($packages, static fn (array $package): bool => $package['isActiveVersion']));
        $inactivePackages = array_values(array_filter($packages, static fn (array $package): bool => !$package['isActiveVersion']));
        self::assertCount(1, $activePackages);
        self::assertCount(1, $inactivePackages);
        self::assertSame('1.4.0', $activePackages[0]['version']);
        self::assertSame('1.5.0', $inactivePackages[0]['version']);
    }

    public function testItRejectsAddonPackageWithoutModuleCode(): void
    {
        $zipPath = $this->createInvalidAddonZip();
        $uploadedFile = new UploadedFile($zipPath, 'broken-addon.zip', 'application/zip', null, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('src/Module/');

        $this->manager->installUploadedPackage($uploadedFile);
    }

    private function createValidAddonZip(string $version = '1.4.0'): string
    {
        $root = sys_get_temp_dir().'/driftpunkt-addon-valid-'.bin2hex(random_bytes(4));
        mkdir($root.'/package/files/src/Module/StatusBoard/Controller', 0777, true);
        mkdir($root.'/package/files/templates/status_board', 0777, true);

        file_put_contents($root.'/package/addon.json', json_encode([
            'slug' => 'status-board',
            'name' => 'Status Board',
            'description' => 'Visar statuskort och driftinformation.',
            'version' => $version,
            'files' => 'files',
            'install_status' => 'configuring',
            'health_status' => 'unknown',
            'environment_variables' => ['STATUS_BOARD_API_KEY'],
            'impact_areas' => ['Publik status', 'Adminöversikt'],
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        file_put_contents($root.'/package/files/src/Module/StatusBoard/Controller/StatusBoardController.php', "<?php\n");
        file_put_contents($root.'/package/files/templates/status_board/index.html.twig', "<section>Status board</section>\n");

        $zipPath = $this->zipDirectory($root.'/package', 'package');
        $this->removeDirectory($root);

        return $zipPath;
    }

    private function createInvalidAddonZip(): string
    {
        $root = sys_get_temp_dir().'/driftpunkt-addon-invalid-'.bin2hex(random_bytes(4));
        mkdir($root.'/package/files/templates/broken_addon', 0777, true);

        file_put_contents($root.'/package/addon.json', json_encode([
            'slug' => 'broken-addon',
            'name' => 'Broken Addon',
            'description' => 'Saknar modulkod.',
            'version' => '0.1.0',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        file_put_contents($root.'/package/files/templates/broken_addon/index.html.twig', "<p>Broken</p>\n");

        $zipPath = $this->zipDirectory($root.'/package', 'package');
        $this->removeDirectory($root);

        return $zipPath;
    }

    private function zipDirectory(string $sourceDirectory, string $archiveRoot): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'driftpunkt-addon-');
        if (false === $zipPath) {
            throw new \RuntimeException('Kunde inte skapa zipfil för addon-testet.');
        }
        @unlink($zipPath);
        $zipPath .= '.zip';

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if (true !== $result) {
            throw new \RuntimeException('Kunde inte öppna zipfil för addon-testet.');
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

            @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
