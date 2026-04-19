<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\System\Service\ReleasePackageBuilder;
use PHPUnit\Framework\TestCase;

final class ReleasePackageBuilderTest extends TestCase
{
    private string $projectDir;
    private ReleasePackageBuilder $builder;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/driftpunkt-release-builder-'.bin2hex(random_bytes(4));
        mkdir($this->projectDir.'/bin', 0777, true);
        mkdir($this->projectDir.'/config/packages', 0777, true);
        mkdir($this->projectDir.'/deploy/systemd', 0777, true);
        mkdir($this->projectDir.'/docs', 0777, true);
        mkdir($this->projectDir.'/migrations', 0777, true);
        mkdir($this->projectDir.'/public/assets', 0777, true);
        mkdir($this->projectDir.'/src', 0777, true);
        mkdir($this->projectDir.'/templates', 0777, true);
        mkdir($this->projectDir.'/vendor/composer', 0777, true);
        mkdir($this->projectDir.'/var/cache', 0777, true);

        file_put_contents($this->projectDir.'/README.md', "# Driftpunkt\n");
        file_put_contents($this->projectDir.'/composer', "#!/usr/bin/env php\n<?php\n");
        file_put_contents($this->projectDir.'/composer.json', json_encode([
            'name' => 'driftpunkt/test-app',
            'version' => '9.9.9',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        file_put_contents($this->projectDir.'/composer.lock', json_encode(['packages' => []], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        file_put_contents($this->projectDir.'/compose.yaml', "services: {}\n");
        file_put_contents($this->projectDir.'/compose.override.yaml', "services: {}\n");
        file_put_contents($this->projectDir.'/symfony.lock', "{}\n");
        file_put_contents($this->projectDir.'/bin/console', "#!/usr/bin/env php\n<?php\n");
        file_put_contents($this->projectDir.'/config/packages/framework.yaml', "framework:\n");
        file_put_contents($this->projectDir.'/deploy/systemd/driftpunkt.service', "[Service]\n");
        file_put_contents($this->projectDir.'/docs/release.md', "release\n");
        file_put_contents($this->projectDir.'/migrations/Version20260419000000.php', "<?php\n");
        file_put_contents($this->projectDir.'/public/index.php', "<?php\n");
        file_put_contents($this->projectDir.'/public/assets/app.css', "body {}\n");
        file_put_contents($this->projectDir.'/src/Kernel.php', "<?php\n");
        file_put_contents($this->projectDir.'/templates/base.html.twig', "<html></html>\n");
        file_put_contents($this->projectDir.'/vendor/autoload.php', "<?php\n");
        file_put_contents($this->projectDir.'/vendor/composer/autoload_real.php', "<?php\n");
        file_put_contents($this->projectDir.'/var/cache/dev.txt', "skip\n");

        $this->builder = new ReleasePackageBuilder($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    public function testItBuildsUpgradeAndInstallPackages(): void
    {
        $packages = $this->builder->buildPackages('both');

        self::assertCount(2, $packages);
        self::assertSame(['upgrade', 'install'], array_column($packages, 'type'));

        $upgradeEntries = $this->listArchiveEntries($packages[0]['path']);
        self::assertContains('driftpunkt-upgrade-9.9.9/src/Kernel.php', $upgradeEntries);
        self::assertContains('driftpunkt-upgrade-9.9.9/deploy/systemd/driftpunkt.service', $upgradeEntries);
        self::assertContains('driftpunkt-upgrade-9.9.9/release-metadata.json', $upgradeEntries);
        self::assertNotContains('driftpunkt-upgrade-9.9.9/vendor/autoload.php', $upgradeEntries);
        self::assertNotContains('driftpunkt-upgrade-9.9.9/var/cache/dev.txt', $upgradeEntries);

        $installEntries = $this->listArchiveEntries($packages[1]['path']);
        self::assertContains('driftpunkt-install-9.9.9/vendor/autoload.php', $installEntries);
        self::assertContains('driftpunkt-install-9.9.9/composer', $installEntries);
        self::assertContains('driftpunkt-install-9.9.9/compose.yaml', $installEntries);
        self::assertContains('driftpunkt-install-9.9.9/release-metadata.json', $installEntries);
        self::assertNotContains('driftpunkt-install-9.9.9/var/cache/dev.txt', $installEntries);
    }

    public function testItCanBuildSpecificPackageTypeAndVersion(): void
    {
        $packages = $this->builder->buildPackages('upgrade', '2026.04.19', $this->projectDir.'/artifacts');

        self::assertCount(1, $packages);
        self::assertSame('upgrade', $packages[0]['type']);
        self::assertSame('2026.04.19', $packages[0]['version']);
        self::assertFileExists($this->projectDir.'/artifacts/driftpunkt-upgrade-2026.04.19.zip');
    }

    private function listArchiveEntries(string $path): array
    {
        $zip = new \ZipArchive();
        $result = $zip->open($path);
        if (true !== $result) {
            throw new \RuntimeException('Kunde inte öppna det skapade testarkivet.');
        }

        $entries = [];
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $entries[] = (string) $zip->getNameIndex($index);
        }
        $zip->close();

        return $entries;
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
