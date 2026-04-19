<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\System\Service\PublicRepoExporter;
use PHPUnit\Framework\TestCase;

final class PublicRepoExporterTest extends TestCase
{
    private string $projectDir;
    private string $exportDir;
    private PublicRepoExporter $exporter;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/driftpunkt-public-export-'.bin2hex(random_bytes(4));
        $this->exportDir = $this->projectDir.'/out';

        mkdir($this->projectDir.'/bin', 0777, true);
        mkdir($this->projectDir.'/config/packages', 0777, true);
        mkdir($this->projectDir.'/docs', 0777, true);
        mkdir($this->projectDir.'/migrations', 0777, true);
        mkdir($this->projectDir.'/public', 0777, true);
        mkdir($this->projectDir.'/src/Module/Ticket/Service', 0777, true);
        mkdir($this->projectDir.'/src/Module/Mail/Service', 0777, true);
        mkdir($this->projectDir.'/src/Module/Portal/Controller', 0777, true);
        mkdir($this->projectDir.'/src/Module/System/Service', 0777, true);
        mkdir($this->projectDir.'/templates/portal', 0777, true);
        mkdir($this->projectDir.'/templates/public', 0777, true);
        mkdir($this->projectDir.'/tests/Service', 0777, true);

        file_put_contents($this->projectDir.'/README.md', "# Driftpunkt\n");
        file_put_contents($this->projectDir.'/composer.json', "{}\n");
        file_put_contents($this->projectDir.'/composer.lock', "{}\n");
        file_put_contents($this->projectDir.'/symfony.lock', "{}\n");
        file_put_contents($this->projectDir.'/bin/console', "#!/usr/bin/env php\n");
        file_put_contents($this->projectDir.'/config/packages/framework.yaml', "framework:\n");
        file_put_contents($this->projectDir.'/config/packages/mailer.yaml', "framework:\n");
        file_put_contents($this->projectDir.'/docs/readme.md', "public docs\n");
        file_put_contents($this->projectDir.'/migrations/Version.php', "<?php\n");
        file_put_contents($this->projectDir.'/public/index.php', "<?php\n");
        file_put_contents($this->projectDir.'/src/Module/Ticket/Service/PublicTicketService.php', "<?php\n");
        file_put_contents($this->projectDir.'/src/Module/Mail/Service/PrivateMailService.php', "<?php\n");
        file_put_contents($this->projectDir.'/src/Module/Portal/Controller/PortalController.php', "<?php\n");
        file_put_contents($this->projectDir.'/src/Module/System/Service/SystemSettings.php', "<?php\n");
        file_put_contents($this->projectDir.'/src/Module/System/Service/CodeUpdateManager.php', "<?php\n");
        file_put_contents($this->projectDir.'/templates/public/index.html.twig', "<html></html>\n");
        file_put_contents($this->projectDir.'/templates/portal/admin.html.twig', "<html></html>\n");
        file_put_contents($this->projectDir.'/tests/Service/ExampleTest.php', "<?php\n");

        $this->exporter = new PublicRepoExporter($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    public function testItExportsOnlyPublicSafePaths(): void
    {
        $result = $this->exporter->export($this->exportDir);

        self::assertSame($this->exportDir, $result['outputDirectory']);
        self::assertFileExists($this->exportDir.'/src/Module/Ticket/Service/PublicTicketService.php');
        self::assertFileExists($this->exportDir.'/src/Module/System/Service/SystemSettings.php');
        self::assertFileExists($this->exportDir.'/templates/public/index.html.twig');
        self::assertFileExists($this->exportDir.'/PUBLIC_EXPORT_MANIFEST.md');

        self::assertFileDoesNotExist($this->exportDir.'/src/Module/Mail/Service/PrivateMailService.php');
        self::assertFileDoesNotExist($this->exportDir.'/src/Module/Portal/Controller/PortalController.php');
        self::assertFileDoesNotExist($this->exportDir.'/src/Module/System/Service/CodeUpdateManager.php');
        self::assertFileDoesNotExist($this->exportDir.'/templates/portal/admin.html.twig');
        self::assertFileDoesNotExist($this->exportDir.'/config/packages/mailer.yaml');
        self::assertGreaterThan(0, $result['fileCount']);
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
