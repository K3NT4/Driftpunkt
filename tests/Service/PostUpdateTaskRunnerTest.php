<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\System\Service\BackgroundConsoleLauncher;
use App\Module\System\Service\PostUpdateTaskRunner;
use App\Module\System\Service\ProjectCommandExecutor;
use PHPUnit\Framework\TestCase;

final class PostUpdateTaskRunnerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/driftpunkt-post-update-'.bin2hex(random_bytes(4));
        mkdir($this->projectDir.'/var', 0777, true);
        file_put_contents($this->projectDir.'/composer', "#!/usr/bin/env php\n<?php\n");
        chmod($this->projectDir.'/composer', 0775);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    public function testItRunsSelectedTasksAndPersistsRunLog(): void
    {
        $executor = new class extends ProjectCommandExecutor {
            /** @var list<list<string>> */
            public array $commands = [];

            public function execute(array $command, string $workingDirectory): array
            {
                $this->commands[] = $command;

                return [
                    'exitCode' => 0,
                    'output' => sprintf('Ran in %s: %s', $workingDirectory, implode(' ', $command)),
                ];
            }
        };
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

        $runner = new PostUpdateTaskRunner($this->projectDir, $executor, $launcher);
        $queuedRun = $runner->queueTasks(['composer_install', 'cache_clear']);

        self::assertSame('queued', $queuedRun['status']);
        self::assertCount(1, $launcher->launches);

        $run = $runner->processQueuedRun($queuedRun['id']);

        self::assertSame('completed', $run['status']);
        self::assertTrue($run['succeeded']);
        self::assertCount(2, $run['taskResults']);
        self::assertCount(1, $executor->commands);
        self::assertCount(2, $launcher->launches);
        self::assertSame('cache_clear', $run['taskResults'][1]['id']);
        self::assertStringContainsString('fristående bakgrundssteg', $run['taskResults'][1]['output']);

        $recentRuns = $runner->listRecentRuns();
        self::assertCount(1, $recentRuns);
        self::assertSame($run['id'], $recentRuns[0]['id']);
        self::assertSame(['composer_install', 'cache_clear'], $recentRuns[0]['selectedTasks']);
        self::assertFileExists($this->projectDir.'/var/post_update_runs/'.$run['id'].'.json');
    }

    public function testItStopsWhenTaskFails(): void
    {
        $executor = new class extends ProjectCommandExecutor {
            public function execute(array $command, string $workingDirectory): array
            {
                if (str_contains(implode(' ', $command), 'doctrine:migrations:migrate')) {
                    return ['exitCode' => 1, 'output' => 'Migration failed'];
                }

                return ['exitCode' => 0, 'output' => 'OK'];
            }
        };
        $launcher = new class extends BackgroundConsoleLauncher {
            public function __construct()
            {
            }

            public function launch(array $arguments, string $workingDirectory): void
            {
            }
        };

        $runner = new PostUpdateTaskRunner($this->projectDir, $executor, $launcher);
        $queuedRun = $runner->queueTasks(['doctrine_migrate', 'cache_clear']);
        $run = $runner->processQueuedRun($queuedRun['id']);

        self::assertFalse($run['succeeded']);
        self::assertCount(1, $run['taskResults']);
        self::assertSame('doctrine_migrate', $run['taskResults'][0]['id']);
        self::assertSame('failed', $run['status']);
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
