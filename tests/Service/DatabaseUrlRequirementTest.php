<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Kernel;
use PHPUnit\Framework\TestCase;

final class DatabaseUrlRequirementTest extends TestCase
{
    private string|false|null $previousDatabaseUrl;

    protected function setUp(): void
    {
        $this->previousDatabaseUrl = getenv('DATABASE_URL');
    }

    protected function tearDown(): void
    {
        if (false === $this->previousDatabaseUrl || null === $this->previousDatabaseUrl) {
            putenv('DATABASE_URL');
            unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

            return;
        }

        $this->setDatabaseUrl($this->previousDatabaseUrl);
    }

    public function testKernelRejectsSqliteOutsideTestEnvironment(): void
    {
        $this->setDatabaseUrl('sqlite:////tmp/driftpunkt-live.sqlite');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MariaDB');

        new Kernel('prod', false);
    }

    public function testKernelAllowsSqliteInTestEnvironment(): void
    {
        $this->setDatabaseUrl('sqlite:////tmp/driftpunkt-test.sqlite');

        $kernel = new Kernel('test', true);

        self::assertSame('test', $kernel->getEnvironment());
    }

    public function testKernelAcceptsMysqlUrlPinnedToMariaDbOutsideTestEnvironment(): void
    {
        $this->setDatabaseUrl('mysql://driftpunkt:secret@database:3306/driftpunkt?serverVersion=mariadb-11.8.6&charset=utf8mb4');

        $kernel = new Kernel('prod', false);

        self::assertSame('prod', $kernel->getEnvironment());
    }

    public function testKernelRejectsGenericMysqlUrlOutsideTestEnvironment(): void
    {
        $this->setDatabaseUrl('mysql://driftpunkt:secret@database:3306/driftpunkt?charset=utf8mb4');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('serverVersion');

        new Kernel('dev', true);
    }

    private function setDatabaseUrl(string $databaseUrl): void
    {
        putenv('DATABASE_URL='.$databaseUrl);
        $_ENV['DATABASE_URL'] = $databaseUrl;
        $_SERVER['DATABASE_URL'] = $databaseUrl;
    }
}
