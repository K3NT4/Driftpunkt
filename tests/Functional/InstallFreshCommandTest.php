<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Identity\Service\SystemAccountProvisioner;
use App\Module\News\Entity\NewsArticle;
use App\Module\News\Enum\NewsCategory;
use App\Module\Ticket\Entity\SlaPolicy;
use App\Module\Ticket\Entity\TicketCategory;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketPriority;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InstallFreshCommandTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        $this->entityManager->getConnection()->close();

        parent::tearDown();
        self::ensureKernelShutdown();
    }

    public function testFreshInstallCreatesStandardAccountsByDefault(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:install:fresh');
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Obligatoriska admin-konton', $tester->getDisplay());
        $reservedSuperAdmin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => SystemAccountProvisioner::RESERVED_SUPER_ADMIN_EMAIL]);
        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => SystemAccountProvisioner::STANDARD_ADMIN_EMAIL]);
        $technician = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'tech@test.local']);
        $customer = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'customer@test.local']);

        self::assertNotNull($reservedSuperAdmin);
        self::assertNotNull($admin);
        self::assertNotNull($technician);
        self::assertNotNull($customer);
        self::assertSame(UserType::SUPER_ADMIN, $reservedSuperAdmin->getType());
        self::assertSame(UserType::ADMIN, $admin->getType());
        self::assertContains('ROLE_SUPER_ADMIN', $reservedSuperAdmin->getRoles());
        self::assertContains('ROLE_ADMIN', $admin->getRoles());
        self::assertNotContains('ROLE_SUPER_ADMIN', $admin->getRoles());
        self::assertTrue($reservedSuperAdmin->isPasswordChangeRequired());
        self::assertTrue($admin->isPasswordChangeRequired());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($reservedSuperAdmin, SystemAccountProvisioner::RESERVED_SUPER_ADMIN_PASSWORD));
        self::assertFalse($admin->isMfaEnabled());
        self::assertFalse($technician->isMfaEnabled());
        self::assertFalse($customer->isMfaEnabled());
        self::assertTrue($technician->isPasswordChangeRequired());
        self::assertTrue($customer->isPasswordChangeRequired());
        $this->assertStandardTicketDataWasCreated();
        $this->assertDefaultNewsArticleWasCreated();
    }

    public function testFreshInstallCanSkipStandardAccounts(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:install:fresh');
        $tester = new CommandTester($command);

        $tester->execute([
            '--skip-test-accounts' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Obligatoriska admin-konton', $tester->getDisplay());
        $reservedSuperAdmin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => SystemAccountProvisioner::RESERVED_SUPER_ADMIN_EMAIL]);
        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => SystemAccountProvisioner::STANDARD_ADMIN_EMAIL]);

        self::assertNotNull($reservedSuperAdmin);
        self::assertNotNull($admin);
        self::assertSame(UserType::SUPER_ADMIN, $reservedSuperAdmin->getType());
        self::assertSame(UserType::ADMIN, $admin->getType());
        self::assertTrue($reservedSuperAdmin->isPasswordChangeRequired());
        self::assertTrue($admin->isPasswordChangeRequired());
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'tech@test.local']));
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'customer@test.local']));
        $this->assertStandardTicketDataWasCreated();
        $this->assertDefaultNewsArticleWasCreated();
    }

    public function testCreateAdminRejectsReservedSuperAdminEmail(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $installTester = new CommandTester($application->find('app:install:fresh'));
        $installTester->execute([
            '--skip-test-accounts' => true,
        ]);

        self::assertSame(0, $installTester->getStatusCode());

        $adminTester = new CommandTester($application->find('app:create-admin'));
        $adminTester->execute([
            'email' => SystemAccountProvisioner::RESERVED_SUPER_ADMIN_EMAIL,
            'password' => 'AdminPassword123',
            'first-name' => 'Kenta',
            'last-name' => 'Spelhubben',
            'type' => 'super_admin',
        ]);

        self::assertSame(1, $adminTester->getStatusCode());
        self::assertStringContainsString('reserved for a protected system account', $adminTester->getDisplay());
    }

    public function testCreateAdminRejectsInvalidAccountType(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $installTester = new CommandTester($application->find('app:install:fresh'));
        $installTester->execute([
            '--skip-test-accounts' => true,
        ]);

        self::assertSame(0, $installTester->getStatusCode());

        $adminTester = new CommandTester($application->find('app:create-admin'));
        $adminTester->execute([
            'email' => 'admin@example.test',
            'password' => 'AdminPassword123',
            'first-name' => 'Admin',
            'last-name' => 'User',
            'type' => 'technician',
        ]);

        self::assertSame(2, $adminTester->getStatusCode());
        self::assertStringContainsString('admin or super_admin', $adminTester->getDisplay());
        self::assertNull($this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.test']));
    }

    public function testCreateAdminCreatesSuperAdminAccount(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $installTester = new CommandTester($application->find('app:install:fresh'));
        $installTester->execute([
            '--skip-test-accounts' => true,
        ]);

        self::assertSame(0, $installTester->getStatusCode());

        $adminTester = new CommandTester($application->find('app:create-admin'));
        $adminTester->execute([
            'email' => 'admin@example.test',
            'password' => 'AdminPassword123',
            'first-name' => 'Admin',
            'last-name' => 'User',
            'type' => 'super_admin',
        ]);

        self::assertSame(0, $adminTester->getStatusCode());
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@example.test']);

        self::assertNotNull($user);
        self::assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertFalse($user->isMfaEnabled());
    }

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath);
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }

    private function assertStandardTicketDataWasCreated(): void
    {
        $categories = $this->entityManager->getRepository(TicketCategory::class)->findAll();
        $slaPolicies = $this->entityManager->getRepository(SlaPolicy::class)->findAll();

        self::assertCount(9, $categories);
        self::assertCount(2, $slaPolicies);

        $standardSla = $this->entityManager->getRepository(SlaPolicy::class)->findOneBy(['name' => 'Standard 8/24']);

        foreach ([
            'Allmän support',
            'Nätverk',
            'Arbetsplats',
            'E-post',
            'Behörighet',
            'Server & drift',
            'Applikationer',
            'Hårdvara',
            'Beställning',
        ] as $categoryName) {
            $category = $this->entityManager->getRepository(TicketCategory::class)->findOneBy(['name' => $categoryName]);

            self::assertNotNull($category);
            self::assertTrue($category->isActive());
            self::assertNotNull($category->getDescription());
        }

        self::assertNotNull($standardSla);
        self::assertSame(8, $standardSla->getFirstResponseHours());
        self::assertSame(24, $standardSla->getResolutionHours());
        self::assertTrue($standardSla->isDefaultPriorityEnabled());
        self::assertSame(TicketPriority::NORMAL, $standardSla->getDefaultPriority());
        self::assertTrue($standardSla->isDefaultEscalationEnabled());
        self::assertSame(TicketEscalationLevel::NONE, $standardSla->getDefaultEscalationLevel());
    }

    private function assertDefaultNewsArticleWasCreated(): void
    {
        $article = $this->entityManager->getRepository(NewsArticle::class)->findOneBy([
            'title' => 'Välkommen till Driftpunkt',
        ]);

        self::assertNotNull($article);
        self::assertSame(NewsCategory::GENERAL, $article->getCategory());
        self::assertTrue($article->isPublished());
        self::assertTrue($article->isPinned());
        self::assertStringContainsString('nyhetsflödet är redo', $article->getSummary());
        self::assertStringContainsString('Driftpunkt är nu installerat', $article->getBody());
    }
}
