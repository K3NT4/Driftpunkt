<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Notification\Entity\NotificationLog;
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
use Symfony\Component\Mime\Email;

final class CompanyMonthlyReportCommandTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

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

    public function testCommandSendsCompanyScopedMonthlyReport(): void
    {
        [$company] = $this->createReportFixture();

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:reports:send-monthly');
        $tester = new CommandTester($command);
        $tester->execute([
            '--from' => '2026-03-01',
            '--to' => '2026-03-31',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertEmailCount(1);

        /** @var Email $email */
        $email = $this->getMailerMessage();
        self::assertSame(['rapport@company.test'], array_map(static fn ($address) => $address->getAddress(), $email->getTo()));
        self::assertStringContainsString('Månadsrapport för Report AB', $email->getSubject());
        self::assertStringContainsString('DP-3001', $email->getTextBody() ?? '');
        self::assertStringNotContainsString('DP-9001', $email->getTextBody() ?? '');

        $log = $this->entityManager->getRepository(NotificationLog::class)->findOneBy(['eventType' => 'company_monthly_report']);
        self::assertNotNull($log);
        self::assertTrue($log->isSent());

        $this->entityManager->clear();
        $company = $this->entityManager->getRepository(Company::class)->find($company->getId());
        self::assertNotNull($company);
        self::assertNotNull($company->getMonthlyReportLastSentAt());
    }

    public function testCommandDryRunDoesNotSendEmailOrMarkCompanySent(): void
    {
        [$company] = $this->createReportFixture();

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:reports:send-monthly');
        $tester = new CommandTester($command);
        $tester->execute([
            '--from' => '2026-03-01',
            '--to' => '2026-03-31',
            '--company-id' => (string) $company->getId(),
            '--dry-run' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertEmailCount(0);

        $log = $this->entityManager->getRepository(NotificationLog::class)->findOneBy(['eventType' => 'company_monthly_report']);
        self::assertNotNull($log);
        self::assertFalse($log->isSent());
        self::assertStringContainsString('Dry-run', $log->getStatusMessage());

        $this->entityManager->clear();
        $company = $this->entityManager->getRepository(Company::class)->find($company->getId());
        self::assertNotNull($company);
        self::assertNull($company->getMonthlyReportLastSentAt());
    }

    public function testCommandSkipsAlreadySentPeriodUnlessForced(): void
    {
        [$company] = $this->createReportFixture();
        $company->markMonthlyReportSent(new \DateTimeImmutable('2026-04-01 08:00:00'));
        $this->entityManager->flush();

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:reports:send-monthly');
        $tester = new CommandTester($command);
        $tester->execute([
            '--from' => '2026-03-01',
            '--to' => '2026-03-31',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertEmailCount(0);
        self::assertStringContainsString('Skickade: 0. Hoppade över: 1.', $tester->getDisplay());

        $forceTester = new CommandTester($command);
        $forceTester->execute([
            '--from' => '2026-03-01',
            '--to' => '2026-03-31',
            '--force' => true,
        ]);

        self::assertSame(0, $forceTester->getStatusCode());
        self::assertEmailCount(1);
        self::assertStringContainsString('Skickade: 1. Hoppade över: 0.', $forceTester->getDisplay());
    }

    public function testCommandSkipsInactiveCompanyWhenTargeted(): void
    {
        [$company] = $this->createReportFixture();
        $company->deactivate();
        $this->entityManager->flush();

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:reports:send-monthly');
        $tester = new CommandTester($command);
        $tester->execute([
            '--from' => '2026-03-01',
            '--to' => '2026-03-31',
            '--company-id' => (string) $company->getId(),
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertEmailCount(0);

        $log = $this->entityManager->getRepository(NotificationLog::class)->findOneBy(['eventType' => 'company_monthly_report']);
        self::assertNotNull($log);
        self::assertFalse($log->isSent());
        self::assertStringContainsString('inaktivt', $log->getStatusMessage());

        $this->entityManager->clear();
        $company = $this->entityManager->getRepository(Company::class)->find($company->getId());
        self::assertNotNull($company);
        self::assertNull($company->getMonthlyReportLastSentAt());
    }

    public function testCommandExcludesInternalOnlyTicketsFromMonthlyReport(): void
    {
        [$company] = $this->createReportFixture();
        $internalTicket = $this->ticket('DP-SECRET', $company, TicketStatus::OPEN, '2026-03-12 08:00:00', TicketVisibility::INTERNAL_ONLY);
        $this->entityManager->persist($internalTicket);
        $this->entityManager->flush();
        $internalTicket->setCreatedAt(new \DateTimeImmutable('2026-03-12 08:00:00'));
        $internalTicket->setUpdatedAt(new \DateTimeImmutable('2026-03-12 08:00:00'));
        $this->entityManager->flush();

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:reports:send-monthly');
        $tester = new CommandTester($command);
        $tester->execute([
            '--from' => '2026-03-01',
            '--to' => '2026-03-31',
            '--company-id' => (string) $company->getId(),
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertEmailCount(1);

        /** @var Email $email */
        $email = $this->getMailerMessage();
        self::assertStringContainsString('Öppna: 1', $email->getTextBody() ?? '');
        self::assertStringContainsString('DP-3001', $email->getTextBody() ?? '');
        self::assertStringNotContainsString('DP-SECRET', $email->getTextBody() ?? '');
        self::assertStringNotContainsString('DP-SECRET', $email->getHtmlBody() ?? '');
    }

    public function testCommandLogsDisabledCompanyWhenTargeted(): void
    {
        [$company] = $this->createReportFixture();
        $company->setMonthlyReportEnabled(false);
        $this->entityManager->flush();

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:reports:send-monthly');
        $tester = new CommandTester($command);
        $tester->execute([
            '--from' => '2026-03-01',
            '--to' => '2026-03-31',
            '--company-id' => (string) $company->getId(),
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertEmailCount(0);

        $log = $this->entityManager->getRepository(NotificationLog::class)->findOneBy(['eventType' => 'company_monthly_report']);
        self::assertNotNull($log);
        self::assertFalse($log->isSent());
        self::assertStringContainsString('inte aktiverad', $log->getStatusMessage());
    }

    private function createReportFixture(): array
    {
        $company = new Company('Report AB');
        $company
            ->setMonthlyReportEnabled(true)
            ->setMonthlyReportRecipientEmail('rapport@company.test');

        $otherCompany = new Company('Other AB');
        $otherCompany
            ->setMonthlyReportEnabled(false)
            ->setMonthlyReportRecipientEmail('rapport@other.test');

        $customer = new User('report-customer@example.test', 'Rita', 'Report', UserType::CUSTOMER);
        $customer->setCompany($company);
        $customer->setPassword('not-used');

        $included = $this->ticket('DP-3001', $company, TicketStatus::OPEN, '2026-03-10 08:00:00');
        $other = $this->ticket('DP-9001', $otherCompany, TicketStatus::OPEN, '2026-03-10 08:00:00');

        foreach ([$company, $otherCompany, $customer, $included, $other] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        $included->setCreatedAt(new \DateTimeImmutable('2026-03-10 08:00:00'));
        $other->setCreatedAt(new \DateTimeImmutable('2026-03-10 08:00:00'));
        $this->entityManager->flush();

        return [$company, $otherCompany];
    }

    private function ticket(
        string $reference,
        Company $company,
        TicketStatus $status,
        string $createdAt,
        TicketVisibility $visibility = TicketVisibility::PRIVATE,
    ): Ticket {
        $ticket = new Ticket(
            $reference,
            'Rapportärende '.$reference,
            'Sammanfattning '.$reference,
            $status,
            $visibility,
            TicketPriority::HIGH,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::COMPANY,
        );
        $ticket->setCompany($company);
        $ticket->setCreatedAt(new \DateTimeImmutable($createdAt));
        $ticket->setUpdatedAt(new \DateTimeImmutable($createdAt));

        return $ticket;
    }

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath);
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }
}
