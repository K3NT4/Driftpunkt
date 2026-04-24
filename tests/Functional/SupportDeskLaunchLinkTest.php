<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SupportDeskLaunchLinkTest extends WebTestCase
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
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }

    public function testTechnicianTicketDetailShowsSupportDeskLaunchLinkWithPrefilledTicketData(): void
    {
        $company = new Company('Acme Drift AB');

        $technician = new User('tech@example.test', 'Tove', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'TechPassword123'));

        $customer = new User('anna@example.test', 'Anna', 'Andersson', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $ticket = new Ticket(
            'ACME-2048',
            'Skrivaren svarar inte',
            'Fjärrstöd behövs för att kontrollera skrivarkö och klientinställningar.',
            TicketStatus::OPEN,
            TicketVisibility::COMPANY_SHARED,
        );
        $ticket->setCompany($company);
        $ticket->setRequester($customer);

        $this->entityManager->persist($company);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();
        static::getContainer()->get(SystemSettings::class)->setBool(SystemSettings::FEATURE_REMOTE_SUPPORT_ANYDESK_ENABLED, true);
        static::getContainer()->get(SystemSettings::class)->setBool(SystemSettings::FEATURE_REMOTE_SUPPORT_TEAMVIEWER_ENABLED, false);

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));

        self::assertResponseIsSuccessful();

        self::assertSame(1, $crawler->filter('body:contains("Aktiva fjärrsupportverktyg")')->count());
        self::assertSame(1, $crawler->filter('body:contains("AnyDesk")')->count());
        self::assertSame(0, $crawler->filter('body:contains("AnyDesk · TeamViewer")')->count());

        $link = $crawler->selectLink('Öppna AnyDesk i SupportDesk');
        self::assertCount(1, $link);
        self::assertCount(0, $crawler->selectLink('Öppna TeamViewer i SupportDesk'));

        $href = (string) $link->link()->getUri();
        self::assertStringContainsString('/portal/technician/supportdesk?', $href);
        self::assertStringContainsString('provider=anydesk', $href);
        self::assertStringContainsString('ticket_reference=ACME-2048', $href);
        self::assertStringContainsString('customer_name=Anna+Andersson', $href);
        self::assertStringContainsString('customer_email=anna%40example.test', $href);
        self::assertStringContainsString('company_name=Acme+Drift+AB', $href);
        self::assertStringContainsString('return_to=%2Fportal%2Ftechnician%2Ftickets%2F', $href);
    }
}
