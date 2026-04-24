<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PortalSlaDashboardSchemaFallbackTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        $this->client = static::createClient();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $this->entityManager->clear();
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        $this->entityManager->getConnection()->close();

        parent::tearDown();
        self::ensureKernelShutdown();
    }

    public function testAdminOverviewSkipsSlaDashboardQueryWhenResolutionSummaryColumnIsMissing(): void
    {
        $admin = new User('schema-admin@example.test', 'Sara', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Supersakert123'));
        $admin->enableMfa();

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->downgradeTicketsTableToPreResolutionSummarySchema($this->entityManager->getConnection());
        $this->entityManager->clear();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin');

        self::assertResponseIsSuccessful();
    }

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }

    private function downgradeTicketsTableToPreResolutionSummarySchema(Connection $connection): void
    {
        $createTableSql = $connection->fetchOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'tickets'");
        self::assertIsString($createTableSql);

        $legacyCreateTableSql = str_replace(
            ', resolution_summary CLOB DEFAULT NULL',
            '',
            $createTableSql,
        );

        self::assertNotSame($createTableSql, $legacyCreateTableSql);

        $connection->executeStatement('PRAGMA foreign_keys = OFF');
        $connection->executeStatement('DROP TABLE tickets');
        $connection->executeStatement($legacyCreateTableSql);
        $connection->executeStatement('PRAGMA foreign_keys = ON');
    }
}
