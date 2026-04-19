<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Notification\Entity\NotificationLog;
use App\Module\System\Entity\SystemSetting;
use App\Module\Ticket\Entity\SlaPolicy;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SlaNotificationCommandTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        $this->entityManager->getConnection()->close();

        parent::tearDown();
        self::ensureKernelShutdown();
    }

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath);
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }

    public function testCommandSendsSlaWarningAndAvoidsDuplicates(): void
    {
        $ticket = $this->createSlaTicketFixture();
        $this->backdateTicket($ticket, '-3 hours');

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:check-ticket-sla');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--first-response-warning-hours' => '2',
            '--resolution-warning-hours' => '8',
        ]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertEmailCount(1);

        $log = $this->entityManager->getRepository(NotificationLog::class)->findOneBy(['eventType' => 'sla_first_response_due_soon']);
        self::assertNotNull($log);
        self::assertTrue($log->isSent());

        $commandTester->execute([
            '--first-response-warning-hours' => '2',
            '--resolution-warning-hours' => '8',
        ]);

        self::assertEmailCount(1);
    }

    public function testCommandLogsSkippedSlaAlertWhenTechnicianOptedOut(): void
    {
        $ticket = $this->createSlaTicketFixture();
        $ticket->getAssignee()?->disableEmailNotifications();
        $this->entityManager->flush();
        $this->backdateTicket($ticket, '-30 hours');

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:check-ticket-sla');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--first-response-warning-hours' => '2',
            '--resolution-warning-hours' => '8',
        ]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertEmailCount(0);

        $log = $this->entityManager->getRepository(NotificationLog::class)->findOneBy(['eventType' => 'sla_resolution_breached']);
        self::assertNotNull($log);
        self::assertFalse($log->isSent());
    }

    public function testCommandUsesAdminConfiguredWarningThresholds(): void
    {
        $ticket = $this->createSlaTicketFixture();
        $this->entityManager->persist(new SystemSetting('sla.first_response_warning_hours', '5'));
        $this->entityManager->persist(new SystemSetting('sla.resolution_warning_hours', '10'));
        $this->entityManager->flush();
        $this->backdateTicket($ticket, '-1 hours');

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:check-ticket-sla');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertEmailCount(1);

        $log = $this->entityManager->getRepository(NotificationLog::class)->findOneBy(['eventType' => 'sla_first_response_due_soon']);
        self::assertNotNull($log);
        self::assertTrue($log->isSent());
    }

    public function testCommandPrefersPolicySpecificWarningThresholdsOverSystemDefaults(): void
    {
        $ticket = $this->createSlaTicketFixture();
        $ticket->getSlaPolicy()?->setFirstResponseWarningHours(1);
        $ticket->getSlaPolicy()?->setResolutionWarningHours(3);
        $this->entityManager->persist(new SystemSetting('sla.first_response_warning_hours', '6'));
        $this->entityManager->persist(new SystemSetting('sla.resolution_warning_hours', '12'));
        $this->entityManager->flush();
        $this->backdateTicket($ticket, '-1 hours');

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:check-ticket-sla');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertEmailCount(0);

        $log = $this->entityManager->getRepository(NotificationLog::class)->findOneBy(['eventType' => 'sla_resolution_due_soon']);
        self::assertNull($log);
    }

    private function createSlaTicketFixture(): Ticket
    {
        $company = new Company('SLA AB');
        $technician = new User('sla-tech@example.test', 'Sara', 'SLA', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'TechPassword123'));
        $technician->enableMfa();

        $customer = new User('sla-customer@example.test', 'Kund', 'SLA', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $slaPolicy = new SlaPolicy('Kritisk 4/24', 4, 24);

        $ticket = new Ticket(
            'DP-5001',
            'SLA ticket',
            'Ticket för SLA-varningar.',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::HIGH,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::COMPANY,
        );
        $ticket->setCompany($company);
        $ticket->setRequester($customer);
        $ticket->setAssignee($technician);
        $ticket->setSlaPolicy($slaPolicy);

        $this->entityManager->persist($company);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($slaPolicy);
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        return $ticket;
    }

    private function backdateTicket(Ticket $ticket, string $interval): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE tickets SET created_at = :createdAt, updated_at = :updatedAt WHERE id = :id',
            [
                'createdAt' => (new \DateTimeImmutable($interval))->format('Y-m-d H:i:s'),
                'updatedAt' => (new \DateTimeImmutable($interval))->format('Y-m-d H:i:s'),
                'id' => $ticket->getId(),
            ],
        );

        $this->entityManager->clear();
    }
}
