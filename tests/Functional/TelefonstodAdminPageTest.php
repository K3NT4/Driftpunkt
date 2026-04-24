<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Telefonstod\Entity\PhoneChangeLogEntry;
use App\Module\Telefonstod\Entity\PhoneCustomerProfile;
use App\Module\Telefonstod\Entity\PhoneExtensionRecord;
use App\Module\Telefonstod\Entity\PhoneNumberRecord;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketCategory;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TelefonstodAdminPageTest extends WebTestCase
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

    public function testAdminCanOpenTelefonstodDashboard(): void
    {
        $admin = new User('telefonstod-admin@example.test', 'Tina', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Supersakert123'));
        $admin->enableMfa();

        $company = new Company('Telefonikund AB');
        $company->setPrimaryEmail('kontakt@telefonikund.test');

        $category = new TicketCategory('Telefoni');
        $category->setDescription('Wx3 och vaxelrelaterade arenden');

        $ticket = new Ticket(
            'TK-1001',
            'Wx3 inkommande samtal fungerar inte',
            'Kunden kan inte ta emot samtal via huvudnumret.',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::HIGH,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::TEAM,
            TicketEscalationLevel::TEAM,
        );
        $ticket->setCompany($company);
        $ticket->setCategory($category);
        $ticket->setAssignee($admin);

        $profile = new PhoneCustomerProfile($company);
        $profile->setWx3CustomerReference('WX3-999')
            ->setMainPhoneNumber('08-700100')
            ->setSolutionType('Vaxel Premium')
            ->setInternalDocumentation('Kunden har separat supportko for telefoni.');

        $number = new PhoneNumberRecord($profile, '08-700100', 'huvudnummer');
        $number->setDisplayName('Reception')
            ->setExtensionNumber('201')
            ->setStatus('aktiv')
            ->setLastChangedAt(new \DateTimeImmutable('2026-04-23 20:00:00'));

        $extension = new PhoneExtensionRecord($profile, '201', 'Anna Andersson');
        $extension->setDirectNumber('08-700101')
            ->setEmail('anna@telefonikund.test')
            ->setStatus('aktiv');

        $changeLogEntry = new PhoneChangeLogEntry($profile, 'phone_number', '08-700100', 'status', 'tina.admin@example.test');
        $changeLogEntry->setOldValue('test')
            ->setNewValue('aktiv')
            ->setTicket($ticket);

        $this->entityManager->persist($admin);
        $this->entityManager->persist($company);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ticket);
        $this->entityManager->persist($profile);
        $this->entityManager->persist($number);
        $this->entityManager->persist($extension);
        $this->entityManager->persist($changeLogEntry);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/telefonstod');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Telefonstod', $this->client->getResponse()->getContent() ?? '');
        self::assertStringContainsString('TK-1001', $this->client->getResponse()->getContent() ?? '');
        self::assertStringContainsString('Telefonikund AB', $this->client->getResponse()->getContent() ?? '');
        self::assertStringContainsString('08-700100', $this->client->getResponse()->getContent() ?? '');
        self::assertStringContainsString('Anna Andersson', $this->client->getResponse()->getContent() ?? '');
        self::assertGreaterThan(0, $crawler->filter('table.telefonstod-table')->count());
    }

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }
}
