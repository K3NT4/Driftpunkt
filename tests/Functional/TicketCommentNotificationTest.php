<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\TechnicianTeam;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Notification\Entity\NotificationLog;
use App\Module\System\Entity\SystemSetting;
use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\TicketCategory;
use App\Module\Ticket\Entity\TicketComment;
use App\Module\Ticket\Entity\TicketCommentAttachment;
use App\Module\Ticket\Entity\ExternalTicketImport;
use App\Module\Ticket\Entity\ExternalTicketEvent;
use App\Module\Ticket\Entity\TicketImportTemplate;
use App\Module\Ticket\Entity\TicketIntakeField;
use App\Module\Ticket\Entity\TicketIntakeTemplate;
use App\Module\Ticket\Entity\TicketRoutingRule;
use App\Module\Ticket\Entity\SlaPolicy;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketAuditLog;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketIntakeFieldType;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TicketCommentNotificationTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

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

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }

    public function testCustomerCommentNotifiesAssignedTechnician(): void
    {
        [$company, $technician, $customer, $ticket] = $this->createTicketFixture();

        $this->client->loginUser($customer);
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/customer/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Vi har testat igen och felet kvarstår.',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/customer');
        self::assertEmailCount(1);

        /** @var Email $email */
        $email = $this->getMailerMessage();
        self::assertSame(['tech@example.test'], array_map(static fn ($address) => $address->getAddress(), $email->getTo()));
        self::assertStringContainsString('väntar på teknikersvar', $email->getSubject());

        $ticket = $this->entityManager->getRepository(Ticket::class)->find($ticket->getId());
        self::assertNotNull($ticket);
        self::assertSame(TicketStatus::OPEN, $ticket->getStatus());
        self::assertCount(1, $ticket->getComments());

        $log = $this->entityManager->getRepository(NotificationLog::class)->findOneBy(['eventType' => 'technician_waiting_reply']);
        self::assertNotNull($log);
        self::assertTrue($log->isSent());

        $auditLog = $this->entityManager->getRepository(TicketAuditLog::class)->findOneBy(['action' => 'customer_comment_added']);
        self::assertNotNull($auditLog);
        self::assertSame('Kunden har skickat ett nytt svar.', $auditLog->getMessage());
    }

    public function testTechnicianPublicCommentNotifiesCustomerUnlessOptedOut(): void
    {
        [, $technician, $customer, $ticket] = $this->createTicketFixture();
        $customer->disableEmailNotifications();
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/portal/technician/arenden');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Vi behöver att ni verifierar om problemet kvarstår efter omstart.',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician/arenden');
        self::assertEmailCount(0);

        $ticket = $this->entityManager->getRepository(Ticket::class)->find($ticket->getId());
        self::assertNotNull($ticket);
        self::assertSame(TicketStatus::PENDING_CUSTOMER, $ticket->getStatus());
        self::assertCount(1, $ticket->getComments());

        $log = $this->entityManager->getRepository(NotificationLog::class)->findOneBy(['eventType' => 'customer_waiting_reply']);
        self::assertNotNull($log);
        self::assertFalse($log->isSent());

        $auditLog = $this->entityManager->getRepository(TicketAuditLog::class)->findOneBy(['action' => 'customer_visible_comment_added']);
        self::assertNotNull($auditLog);
        self::assertSame('Kundsynlig kommentar tillagd av tekniker.', $auditLog->getMessage());
    }

    public function testInternalTechnicianCommentIsHiddenFromCustomerAndSendsNoEmail(): void
    {
        [, $technician, $customer, $ticket] = $this->createTicketFixture();

        $this->client->loginUser($technician);
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/portal/technician/arenden');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Internt: vi misstänker fel i upstream-konfigurationen.',
            'internal' => '1',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician/arenden');
        self::assertEmailCount(0);

        $ticket = $this->entityManager->getRepository(Ticket::class)->find($ticket->getId());
        self::assertNotNull($ticket);
        self::assertCount(1, $ticket->getComments());

        $latestComment = $ticket->getComments()->first();
        self::assertInstanceOf(TicketComment::class, $latestComment);
        self::assertTrue($latestComment->isInternal());

        $auditLog = $this->entityManager->getRepository(TicketAuditLog::class)->findOneBy(['action' => 'internal_comment_added']);
        self::assertNotNull($auditLog);

        self::ensureKernelShutdown();
        $customerClient = static::createClient();
        $customerClient->loginUser($customer);
        $customerCrawler = $customerClient->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Internt: vi misstänker fel i upstream-konfigurationen.', $customerCrawler->html());
    }

    public function testCreatingAssignedTicketNotifiesTechnician(): void
    {
        $creator = new User('creator@example.test', 'Asta', 'Admin', UserType::ADMIN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'CreatorPassword123'));
        $creator->enableMfa();

        $company = new Company('Notify AB');
        $technician = new User('assigned@example.test', 'Arne', 'Assigned', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'AssignedPassword123'));
        $technician->enableMfa();

        $customer = new User('requester@example.test', 'Rita', 'Requester', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'RequesterPassword123'));
        $customer->setCompany($company);

        $this->entityManager->persist($company);
        $this->entityManager->persist($creator);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $this->client->loginUser($creator);
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets"]')->form([
            'subject' => 'Ny notifierad ticket',
            'summary' => 'Den här ticketen ska skicka tilldelningsmail.',
            'request_type' => TicketRequestType::SERVICE_REQUEST->value,
            'impact_level' => TicketImpactLevel::TEAM->value,
            'priority' => TicketPriority::HIGH->value,
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'assignee_id' => (string) $technician->getId(),
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician/arenden');
        self::assertEmailCount(1);

        /** @var Email $email */
        $email = $this->getMailerMessage();
        self::assertSame(['assigned@example.test'], array_map(static fn ($address) => $address->getAddress(), $email->getTo()));
        self::assertStringContainsString('Ticket tilldelad dig', $email->getSubject());
        self::assertStringContainsString('En ny ticket har skapats och tilldelats dig.', $email->getTextBody() ?? '');
    }

    public function testTechnicianCanCreateTicketFromSharepointImportWithoutLosingHistory(): void
    {
        $creator = new User('importer@example.test', 'Inga', 'Importer', UserType::TECHNICIAN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'ImporterPassword123'));
        $creator->enableMfa();

        $company = new Company('SharePoint AB');
        $customer = new User('requester@sharepoint.test', 'Sara', 'Skickare', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'RequesterPassword123'));
        $customer->setCompany($company);

        $this->entityManager->persist($company);
        $this->entityManager->persist($creator);
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $payload = json_encode([
            'Title' => 'Skrivare offline i receptionen',
            'Description' => 'Ärendet kommer från SharePoint-listan och ska behålla sin tidigare historik.',
            'ID' => 442,
            'Status' => 'Pågående',
            'Priority' => 'Hög',
            'Author' => [
                'Title' => 'Sara Skickare',
                'EMail' => 'requester@sharepoint.test',
            ],
            'History' => [
                [
                    'created' => '2026-04-18T08:15:00+00:00',
                    'title' => 'Ärende skapat',
                    'comment' => 'Registrerat i SharePoint av kund.',
                    'Author' => [
                        'Title' => 'Sara Skickare',
                        'EMail' => 'requester@sharepoint.test',
                    ],
                ],
                [
                    'created' => '2026-04-18T10:45:00+00:00',
                    'title' => 'Tekniker uppdaterade status',
                    'comment' => 'Felsökning påbörjad.',
                    'Editor' => [
                        'Title' => 'Teo Tekniker',
                        'EMail' => 'teo@example.test',
                    ],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->client->loginUser($creator);
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets"]')->form([
            'subject' => '',
            'summary' => '',
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            'priority' => 'auto',
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'import_source_system' => 'sharepoint',
            'import_source_reference' => 'SP-442',
            'import_source_url' => 'https://sharepoint.example.test/sites/support/lists/incidents/442',
            'import_payload' => $payload,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician/arenden');
        $this->client->followRedirect();

        /** @var Ticket|null $ticket */
        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Skrivare offline i receptionen']);
        self::assertNotNull($ticket);
        self::assertSame('Ärendet kommer från SharePoint-listan och ska behålla sin tidigare historik.', $ticket->getSummary());

        $import = $this->entityManager->getRepository(ExternalTicketImport::class)->findOneBy(['ticket' => $ticket]);
        self::assertNotNull($import);
        self::assertSame('sharepoint', $import->getSourceSystem());
        self::assertSame('SP-442', $import->getSourceReference());
        self::assertCount(2, $import->getEvents());
        self::assertSame('Sara Skickare', $import->getRequesterName());
        self::assertSame('requester@sharepoint.test', $import->getRequesterEmail());

        $auditLog = $this->entityManager->getRepository(TicketAuditLog::class)->findOneBy(['ticket' => $ticket, 'action' => 'external_ticket_imported']);
        self::assertNotNull($auditLog);

        $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Importerad historik', $html);
        self::assertStringContainsString('SharePoint', $html);
        self::assertStringContainsString('Registrerat i SharePoint av kund.', $html);
        self::assertStringContainsString('Felsökning påbörjad.', $html);
    }

    public function testTechnicianCanCreateTicketFromCsvImportWithSuggestedRowTargets(): void
    {
        $creator = new User('csv-importer@example.test', 'Cia', 'Csv', UserType::TECHNICIAN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'CsvImporterPassword123'));
        $creator->enableMfa();

        $company = new Company('CSV AB');
        $customer = new User('kund@csv.test', 'Cecilia', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $this->entityManager->persist($company);
        $this->entityManager->persist($creator);
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $csvPayload = json_encode([
            'filename' => 'sharepoint-export.csv',
            'delimiter' => ';',
            'headers' => ['Ämne', 'Beskrivning', 'Referens', 'Avsändare', 'E-post', 'Status', 'Prioritet', 'Händelse', 'Kommentar', 'Datum', 'Aktör'],
            'rows' => [
                [
                    'Ämne' => 'Accesskort fungerar inte',
                    'Beskrivning' => 'Kundens accesskort öppnar inte ytterdörren längre.',
                    'Referens' => 'CSV-88',
                    'Avsändare' => 'Cecilia Kund',
                    'E-post' => 'kund@csv.test',
                    'Status' => 'Öppen',
                    'Prioritet' => 'Hög',
                    'Händelse' => 'Ärende skapat',
                    'Kommentar' => 'Registrerat via exportfil.',
                    'Datum' => '2026-04-18T08:00:00+00:00',
                    'Aktör' => 'Cecilia Kund',
                ],
                [
                    'Ämne' => '',
                    'Beskrivning' => '',
                    'Referens' => 'CSV-88',
                    'Avsändare' => '',
                    'E-post' => '',
                    'Status' => '',
                    'Prioritet' => '',
                    'Händelse' => 'Tekniker uppdaterade ärendet',
                    'Kommentar' => 'Kortläsaren startades om och loggar samlades in.',
                    'Datum' => '2026-04-18T10:15:00+00:00',
                    'Aktör' => 'Tina Tekniker',
                ],
            ],
            'fieldMapping' => [
                'subject' => 'Ämne',
                'summary' => 'Beskrivning',
                'reference' => 'Referens',
                'requester_name' => 'Avsändare',
                'requester_email' => 'E-post',
                'status' => 'Status',
                'priority' => 'Prioritet',
                'event_title' => 'Händelse',
                'event_body' => 'Kommentar',
                'event_date' => 'Datum',
                'event_actor_name' => 'Aktör',
            ],
            'rowTargets' => [
                '0' => 'ticket',
                '1' => 'history',
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->client->loginUser($creator);
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets"]')->form([
            'subject' => '',
            'summary' => '',
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            'priority' => 'auto',
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'import_source_system' => 'generic',
            'import_source_reference' => '',
            'import_source_url' => '',
            'import_csv_payload' => $csvPayload,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician/arenden');
        $this->client->followRedirect();

        /** @var Ticket|null $ticket */
        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Accesskort fungerar inte']);
        self::assertNotNull($ticket);
        self::assertSame('Kundens accesskort öppnar inte ytterdörren längre.', $ticket->getSummary());

        $import = $this->entityManager->getRepository(ExternalTicketImport::class)->findOneBy(['ticket' => $ticket]);
        self::assertNotNull($import);
        self::assertSame('CSV-88', $import->getSourceReference());
        self::assertSame('Cecilia Kund', $import->getRequesterName());
        self::assertSame('kund@csv.test', $import->getRequesterEmail());
        self::assertCount(1, $import->getEvents());
        self::assertSame('Tekniker uppdaterade ärendet', $import->getEvents()->first()->getTitle());

        $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('sharepoint-export.csv', $html);
        self::assertStringContainsString('Tekniker uppdaterade ärendet', $html);
        self::assertStringContainsString('Kortläsaren startades om och loggar samlades in.', $html);
    }

    public function testTechnicianCanCreateMultipleTicketsFromCsvImport(): void
    {
        $creator = new User('csv-batch@example.test', 'Bodil', 'Batch', UserType::TECHNICIAN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'CsvBatchPassword123'));
        $creator->enableMfa();

        $company = new Company('Batch CSV AB');
        $customer = new User('batch@csv.test', 'Britta', 'CSV', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $this->entityManager->persist($company);
        $this->entityManager->persist($creator);
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $csvPayload = json_encode([
            'filename' => 'multi.csv',
            'delimiter' => ';',
            'headers' => ['Ämne', 'Beskrivning', 'Referens', 'Avsändare', 'E-post', 'Status'],
            'rows' => [
                ['Ämne' => 'Laptop startar inte', 'Beskrivning' => 'Svart skärm vid uppstart.', 'Referens' => 'CSV-101', 'Avsändare' => 'Britta CSV', 'E-post' => 'batch@csv.test', 'Status' => 'Öppen'],
                ['Ämne' => 'VPN kopplar ner', 'Beskrivning' => 'Tappar anslutning var femte minut.', 'Referens' => 'CSV-102', 'Avsändare' => 'Britta CSV', 'E-post' => 'batch@csv.test', 'Status' => 'Öppen'],
            ],
            'fieldMapping' => [
                'subject' => 'Ämne',
                'summary' => 'Beskrivning',
                'reference' => 'Referens',
                'requester_name' => 'Avsändare',
                'requester_email' => 'E-post',
                'status' => 'Status',
            ],
            'rowTargets' => [
                '0' => 'ticket',
                '1' => 'ticket',
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->client->loginUser($creator);
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets"]')->form([
            'subject' => '',
            'summary' => '',
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            'priority' => 'auto',
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'import_source_system' => 'generic',
            'import_csv_payload' => $csvPayload,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician/arenden');
        $this->client->followRedirect();

        self::assertNotNull($this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Laptop startar inte']));
        self::assertNotNull($this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'VPN kopplar ner']));
        self::assertSame(2, $this->entityManager->getRepository(ExternalTicketImport::class)->count([]));

        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('2 tickets skapades från importen.', $html);
    }

    public function testTechnicianCanCreateMultipleTicketsFromSharepointListImport(): void
    {
        $creator = new User('sp-batch@example.test', 'Stina', 'Batch', UserType::TECHNICIAN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'SharePointBatchPassword123'));
        $creator->enableMfa();

        $company = new Company('Batch SharePoint AB');
        $customer = new User('sharepoint@batch.test', 'Siv', 'Point', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $this->entityManager->persist($company);
        $this->entityManager->persist($creator);
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $payload = json_encode([
            'value' => [
                [
                    'Title' => 'Skrivare 1 offline',
                    'Description' => 'Ingen utskrift kommer fram.',
                    'ID' => 9001,
                    'Author' => ['Title' => 'Siv Point', 'EMail' => 'sharepoint@batch.test'],
                ],
                [
                    'Title' => 'Skrivare 2 offline',
                    'Description' => 'Felkö i utskriftsservern.',
                    'ID' => 9002,
                    'Author' => ['Title' => 'Siv Point', 'EMail' => 'sharepoint@batch.test'],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->client->loginUser($creator);
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets"]')->form([
            'subject' => '',
            'summary' => '',
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            'priority' => 'auto',
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'import_source_system' => 'sharepoint',
            'import_payload' => $payload,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician/arenden');
        $this->client->followRedirect();

        self::assertNotNull($this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Skrivare 1 offline']));
        self::assertNotNull($this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Skrivare 2 offline']));
        self::assertSame(2, $this->entityManager->getRepository(ExternalTicketImport::class)->count([]));

        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('2 tickets skapades från importen.', $html);
    }

    public function testAdminCanOpenImportExportTicketImportPage(): void
    {
        $admin = new User('menu-import@example.test', 'Mira', 'Menu', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'MenuImportPassword123'));
        $admin->enableMfa();
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/import-export/arendeimport');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Import / Export / Ärendeimport', $crawler->html());
        self::assertStringContainsString('Importera från SharePoint eller annat ärendesystem', $crawler->html());
        self::assertStringContainsString('Ärendeimport', $crawler->html());

        $dashboardCrawler = $this->client->request('GET', '/portal/admin');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/portal/admin/import-export/arendeimport', $dashboardCrawler->html());
        self::assertStringContainsString('Import / Export', $dashboardCrawler->html());
    }

    public function testAdminCanOpenImportExportTicketExportPage(): void
    {
        $admin = new User('menu-export@example.test', 'Mira', 'Export', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'MenuExportPassword123'));
        $admin->enableMfa();
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/import-export/arendeexport');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Import / Export / Ärendeexport', $crawler->html());
        self::assertStringContainsString('CSV-export är igång', $crawler->html());
        self::assertStringContainsString('Ärendeimport', $crawler->html());
        self::assertStringContainsString('Ärendeexport', $crawler->html());
    }

    public function testAdminCanDownloadTicketExportCsv(): void
    {
        $admin = new User('export-admin@example.test', 'Ellen', 'Export', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'ExportPassword123'));
        $admin->enableMfa();

        $company = new Company('Export AB');
        $requester = new User('requester@export.test', 'Rakel', 'Rapport', UserType::CUSTOMER);
        $requester->setPassword($this->passwordHasher->hashPassword($requester, 'RequesterPassword123'));
        $requester->setCompany($company);

        $ticket = new Ticket(
            'DP-1001',
            'Exportbar ticket',
            'Denna ticket ska komma med i CSV-exporten.',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::HIGH,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
        );
        $ticket->setCompany($company);
        $ticket->setRequester($requester);

        $this->entityManager->persist($admin);
        $this->entityManager->persist($company);
        $this->entityManager->persist($requester);
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/import-export/arendeexport/csv?q=exportbar');

        self::assertResponseIsSuccessful();
        self::assertTrue($this->client->getResponse()->headers->contains('Content-Type', 'text/csv; charset=UTF-8'));

        $content = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Referens;Ämne;Sammanfattning;Status;Prioritet', $content);
        self::assertStringContainsString('DP-1001;"Exportbar ticket";"Denna ticket ska komma med i CSV-exporten.";Öppen;Hög;Incident', $content);
    }

    public function testAdminCanPreviewImportWithDuplicateWarnings(): void
    {
        $admin = new User('preview-admin@example.test', 'Pia', 'Preview', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'PreviewPassword123'));
        $admin->enableMfa();

        $company = new Company('Preview AB');
        $requester = new User('preview-requester@example.test', 'Petra', 'Preview', UserType::CUSTOMER);
        $requester->setPassword($this->passwordHasher->hashPassword($requester, 'RequesterPassword123'));
        $requester->setCompany($company);

        $ticket = new Ticket(
            'DP-1002',
            'Samma ämne från SharePoint',
            'Befintlig ticket för dubblettkontroll.',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
        );
        $ticket->setCompany($company);
        $ticket->setRequester($requester);

        $existingImport = new ExternalTicketImport($ticket, 'sharepoint', 'SharePoint');
        $existingImport
            ->setSourceReference('SP-900')
            ->setRequesterEmail('preview-requester@example.test');
        $ticket->setExternalImport($existingImport);

        $this->entityManager->persist($admin);
        $this->entityManager->persist($company);
        $this->entityManager->persist($requester);
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        $payload = json_encode([
            'Title' => 'Samma ämne från SharePoint',
            'Description' => 'Ny import som ska ge dubblettvarning i torrkörningen.',
            'ID' => 900,
            'Author' => [
                'Title' => 'Petra Preview',
                'EMail' => 'preview-requester@example.test',
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/import-export/arendeimport');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/portal/admin/import-export/arendeimport/forhandsgranska', [
            '_token' => $token,
            'import_source_system' => 'sharepoint',
            'import_source_reference' => 'SP-900',
            'import_payload' => $payload,
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            'priority' => 'auto',
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'duplicate_strategy' => 'warn',
        ]);

        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Torrkörning och förhandsgranskning', $html);
        self::assertStringContainsString('Dubblettvarning', $html);
        self::assertStringContainsString('SP-900', $html);
    }

    public function testAdminCanSaveTicketImportTemplate(): void
    {
        $admin = new User('template-admin@example.test', 'Tina', 'Template', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'TemplatePassword123'));
        $admin->enableMfa();
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $payload = json_encode([
            'Title' => 'Mallad import',
            'Description' => 'Payload som ska sparas som mall.',
        ], \JSON_THROW_ON_ERROR);

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/import-export/arendeimport');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/portal/admin/import-export/arendeimport/mallar', [
            '_token' => $token,
            'import_template_name' => 'SharePoint standardimport',
            'import_source_system' => 'sharepoint',
            'import_payload' => $payload,
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            'priority' => 'auto',
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'duplicate_strategy' => 'warn',
        ]);

        self::assertResponseRedirects();
        self::assertSame(1, $this->entityManager->getRepository(TicketImportTemplate::class)->count([]));

        $template = $this->entityManager->getRepository(TicketImportTemplate::class)->findOneBy(['name' => 'SharePoint standardimport']);
        self::assertNotNull($template);
        self::assertSame('sharepoint', $template->getSourceSystem());
    }

    public function testAdminCanDownloadTicketExportCsvWithHistoryAndImportColumns(): void
    {
        $admin = new User('history-export-admin@example.test', 'Hedda', 'Historik', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'HistoryExportPassword123'));
        $admin->enableMfa();

        $company = new Company('Historik AB');
        $requester = new User('history-requester@example.test', 'Harald', 'Historik', UserType::CUSTOMER);
        $requester->setPassword($this->passwordHasher->hashPassword($requester, 'RequesterPassword123'));
        $requester->setCompany($company);

        $ticket = new Ticket(
            'DP-1003',
            'Historikexport',
            'Denna ticket ska exporteras med kommentarer och importhistorik.',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::HIGH,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
        );
        $ticket->setCompany($company);
        $ticket->setRequester($requester);

        $comment = new TicketComment($ticket, $admin, 'Intern kommentar för exporttest.', true);
        $import = new ExternalTicketImport($ticket, 'sharepoint', 'SharePoint');
        $import->setSourceReference('SP-1003');
        $event = new ExternalTicketEvent('note', 'Tidigare status', new \DateTimeImmutable('2026-04-18 10:00:00'));
        $event->setBody('Importerad historikrad för exporten.');
        $import->addEvent($event);
        $ticket->setExternalImport($import);

        $this->entityManager->persist($admin);
        $this->entityManager->persist($company);
        $this->entityManager->persist($requester);
        $this->entityManager->persist($ticket);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/import-export/arendeexport/csv?include_history=1&include_import_details=1&q=historikexport');

        self::assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Importkälla;"Extern referens";"Importerade historikposter";Kommentarer;"Importerad historik"', $content);
        self::assertStringContainsString('SharePoint;SP-1003;1;', $content);
        self::assertStringContainsString('Intern kommentar för exporttest.', $content);
        self::assertStringContainsString('Importerad historikrad för exporten.', $content);
    }

    public function testCreatingTicketCanUseSlaPolicyDefaultsWhenEnabled(): void
    {
        $creator = new User('policy-creator@example.test', 'Pia', 'Policy', UserType::ADMIN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'PolicyCreator123'));
        $creator->enableMfa();

        $company = new Company('SLA Default AB');
        $team = new TechnicianTeam('Onboarding');
        $technician = new User('policy-tech@example.test', 'Tom', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'PolicyTech123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('policy-customer@example.test', 'Klara', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'PolicyCustomer123'));
        $customer->setCompany($company);

        $slaPolicy = new SlaPolicy('Styrd SLA', 4, 24);
        $slaPolicy
            ->setDefaultPriorityEnabled(true)
            ->setDefaultPriority(TicketPriority::CRITICAL)
            ->setDefaultAssigneeEnabled(true)
            ->setDefaultAssignee($technician)
            ->setDefaultTeamEnabled(true)
            ->setDefaultTeam($team)
            ->setDefaultEscalationEnabled(true)
            ->setDefaultEscalationLevel(TicketEscalationLevel::INCIDENT);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($creator);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($slaPolicy);
        $this->entityManager->flush();

        $this->client->loginUser($creator);
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Skapa ticket')->form([
            'subject' => 'Policydefault ticket',
            'summary' => 'Ska få prioritet och tilldelning via SLA.',
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::DEPARTMENT->value,
            'priority' => 'auto',
            'escalation_level' => 'auto',
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'assignee_id' => 'auto',
            'assigned_team_id' => 'auto',
            'sla_policy_id' => (string) $slaPolicy->getId(),
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician');
        self::assertEmailCount(1);

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Policydefault ticket']);
        self::assertNotNull($ticket);
        self::assertSame(TicketPriority::CRITICAL, $ticket->getPriority());
        self::assertSame($technician->getId(), $ticket->getAssignee()?->getId());
        self::assertSame($team->getId(), $ticket->getAssignedTeam()?->getId());
        self::assertSame(TicketEscalationLevel::INCIDENT, $ticket->getEscalationLevel());
    }

    public function testCreatingTicketDoesNotUseDisabledSlaDefaults(): void
    {
        $creator = new User('policy-off@example.test', 'Petra', 'Off', UserType::ADMIN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'PolicyOff123'));
        $creator->enableMfa();

        $company = new Company('SLA Av AB');
        $team = new TechnicianTeam('VIP');
        $technician = new User('disabled-tech@example.test', 'Tove', 'Avstangd', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'DisabledTech123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('disabled-customer@example.test', 'Kund', 'Av', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'DisabledCustomer123'));
        $customer->setCompany($company);

        $slaPolicy = new SlaPolicy('Passiv automatik', 4, 24);
        $slaPolicy
            ->setDefaultPriorityEnabled(false)
            ->setDefaultPriority(TicketPriority::CRITICAL)
            ->setDefaultAssigneeEnabled(false)
            ->setDefaultAssignee($technician)
            ->setDefaultTeamEnabled(false)
            ->setDefaultTeam($team)
            ->setDefaultEscalationEnabled(false)
            ->setDefaultEscalationLevel(TicketEscalationLevel::INCIDENT);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($creator);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($slaPolicy);
        $this->entityManager->flush();

        $this->client->loginUser($creator);
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Skapa ticket')->form([
            'subject' => 'Ingen policydefault',
            'summary' => 'Ska inte få automatiska värden när admin slagit av funktionen.',
            'request_type' => TicketRequestType::SERVICE_REQUEST->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            'priority' => 'auto',
            'escalation_level' => 'auto',
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'assignee_id' => 'auto',
            'assigned_team_id' => 'auto',
            'sla_policy_id' => (string) $slaPolicy->getId(),
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician');

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Ingen policydefault']);
        self::assertNotNull($ticket);
        self::assertSame(TicketPriority::NORMAL, $ticket->getPriority());
        self::assertNull($ticket->getAssignee());
        self::assertNull($ticket->getAssignedTeam());
        self::assertSame(TicketEscalationLevel::NONE, $ticket->getEscalationLevel());
    }

    public function testCreatingTicketCanAutoRouteByCategoryAndCustomerType(): void
    {
        $creator = new User('auto-route@example.test', 'Ari', 'Route', UserType::ADMIN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'AutoRoutePassword123'));
        $creator->enableMfa();

        $company = new Company('Routing AB');
        $team = new TechnicianTeam('NOC');
        $technician = new User('noc-tech@example.test', 'Nina', 'Noc', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'NocPassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('routing-customer@example.test', 'Rune', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'RoutingCustomer123'));
        $customer->setCompany($company);

        $slaPolicy = new SlaPolicy('NOC SLA', 2, 8);
        $category = new TicketCategory('Nätverk');
        $template = new TicketIntakeTemplate('NOC mall', TicketRequestType::INCIDENT);
        $intakeField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'affected_service', 'Påverkad tjänst'))
            ->setTemplate($template)
            ->setRequired(true)
            ->setSortOrder(10);
        $rule = (new TicketRoutingRule('NOC kategori', $team))
            ->setCategory($category)
            ->setCustomerType(UserType::CUSTOMER)
            ->setRequestType(TicketRequestType::INCIDENT)
            ->setImpactLevel(TicketImpactLevel::CRITICAL_SERVICE)
            ->setIntakeTemplateFamily($template->getVersionFamily())
            ->setIntakeFieldKey('affected_service')
            ->setIntakeFieldValue('VPN gateway Stockholm')
            ->setDefaultPriority(TicketPriority::CRITICAL)
            ->setDefaultEscalationLevel(TicketEscalationLevel::INCIDENT)
            ->setDefaultSlaPolicy($slaPolicy)
            ->setDefaultAssignee($technician)
            ->setSortOrder(5);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($creator);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($slaPolicy);
        $this->entityManager->persist($category);
        $this->entityManager->persist($template);
        $this->entityManager->persist($intakeField);
        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        $this->client->loginUser($creator);
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Skapa ticket')->form([
            'subject' => 'VPN går ned',
            'summary' => 'Behöver auto-routas till rätt team.',
            'category_id' => (string) $category->getId(),
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::CRITICAL_SERVICE->value,
            'priority' => 'auto',
            'escalation_level' => 'auto',
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'intake_answers[affected_service]' => 'VPN gateway Stockholm',
            'assignee_id' => 'auto',
            'assigned_team_id' => 'auto',
            'sla_policy_id' => 'auto',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician');

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'VPN går ned']);
        self::assertNotNull($ticket);
        self::assertSame('Nätverk', $ticket->getCategory()?->getName());
        self::assertSame(TicketRequestType::INCIDENT, $ticket->getRequestType());
        self::assertSame(TicketImpactLevel::CRITICAL_SERVICE, $ticket->getImpactLevel());
        self::assertSame('VPN gateway Stockholm', $ticket->getIntakeAnswers()['affected_service'] ?? null);
        self::assertSame($template->getVersionFamily(), $ticket->getIntakeTemplate()?->getVersionFamily());
        self::assertSame(TicketPriority::CRITICAL, $ticket->getPriority());
        self::assertSame(TicketEscalationLevel::INCIDENT, $ticket->getEscalationLevel());
        self::assertSame('NOC', $ticket->getAssignedTeam()?->getName());
        self::assertSame($technician->getId(), $ticket->getAssignee()?->getId());
        self::assertSame('NOC SLA', $ticket->getSlaPolicy()?->getName());
    }

    public function testRoutingSelectedSlaCanDriveAutoDefaultsDownstream(): void
    {
        $creator = new User('sla-route@example.test', 'Selma', 'Route', UserType::ADMIN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'SlaRoutePassword123'));
        $creator->enableMfa();

        $company = new Company('SLA Route AB');
        $team = new TechnicianTeam('Core Ops');
        $technician = new User('sla-route-tech@example.test', 'Sten', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'SlaRouteTech123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('sla-route-customer@example.test', 'Sara', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'SlaRouteCustomer123'));
        $customer->setCompany($company);

        $category = new TicketCategory('Plattform');
        $slaPolicy = new SlaPolicy('Plattform SLA', 1, 4);
        $slaPolicy
            ->setDefaultPriorityEnabled(true)
            ->setDefaultPriority(TicketPriority::CRITICAL)
            ->setDefaultAssigneeEnabled(true)
            ->setDefaultAssignee($technician)
            ->setDefaultTeamEnabled(true)
            ->setDefaultTeam($team)
            ->setDefaultEscalationEnabled(true)
            ->setDefaultEscalationLevel(TicketEscalationLevel::INCIDENT);

        $rule = (new TicketRoutingRule('Plattform routning', $team))
            ->setCategory($category)
            ->setRequestType(TicketRequestType::INCIDENT)
            ->setDefaultSlaPolicy($slaPolicy)
            ->setSortOrder(1);

        $this->entityManager->persist($creator);
        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($category);
        $this->entityManager->persist($slaPolicy);
        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        $this->client->loginUser($creator);
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Skapa ticket')->form([
            'subject' => 'Routingstyrd SLA-default',
            'summary' => 'SLA via routing ska kunna sätta övriga auto-värden.',
            'category_id' => (string) $category->getId(),
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::TEAM->value,
            'priority' => 'auto',
            'escalation_level' => 'auto',
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'assignee_id' => 'auto',
            'assigned_team_id' => 'auto',
            'sla_policy_id' => 'auto',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician');

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Routingstyrd SLA-default']);
        self::assertNotNull($ticket);
        self::assertSame('Plattform SLA', $ticket->getSlaPolicy()?->getName());
        self::assertSame(TicketPriority::CRITICAL, $ticket->getPriority());
        self::assertSame(TicketEscalationLevel::INCIDENT, $ticket->getEscalationLevel());
        self::assertSame($technician->getId(), $ticket->getAssignee()?->getId());
        self::assertSame($team->getId(), $ticket->getAssignedTeam()?->getId());
    }

    public function testRoutingRuleBoundToTemplateFamilySurvivesPublishedTemplateVersion(): void
    {
        $creator = new User('family-route@example.test', 'Fia', 'Route', UserType::ADMIN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'FamilyRoute123'));
        $creator->enableMfa();

        $company = new Company('Family Route AB');
        $team = new TechnicianTeam('Network Ops');
        $technician = new User('family-tech@example.test', 'Nettan', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'FamilyTech123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('family-customer@example.test', 'Kia', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'FamilyCustomer123'));
        $customer->setCompany($company);

        $category = new TicketCategory('Nätverk');
        $templateV1 = new TicketIntakeTemplate('Familjemall', TicketRequestType::INCIDENT);
        $fieldV1 = (new TicketIntakeField(TicketRequestType::INCIDENT, 'affected_service', 'Påverkad tjänst'))
            ->setTemplate($templateV1)
            ->setRequired(true)
            ->setSortOrder(10);

        $templateV2 = (new TicketIntakeTemplate('Familjemall v2', TicketRequestType::INCIDENT))
            ->setVersionFamily($templateV1->getVersionFamily())
            ->setVersionNumber(2)
            ->markAsCurrentVersion();
        $fieldV2 = (new TicketIntakeField(TicketRequestType::INCIDENT, 'affected_service', 'Påverkad tjänst'))
            ->setTemplate($templateV2)
            ->setRequired(true)
            ->setSortOrder(10);

        $templateV1
            ->retireCurrentVersion()
            ->deactivate();

        $rule = (new TicketRoutingRule('Familjeregel', $team))
            ->setCategory($category)
            ->setRequestType(TicketRequestType::INCIDENT)
            ->setCustomerType(UserType::CUSTOMER)
            ->setImpactLevel(TicketImpactLevel::CRITICAL_SERVICE)
            ->setIntakeTemplateFamily($templateV1->getVersionFamily())
            ->setIntakeFieldKey('affected_service')
            ->setIntakeFieldValue('VPN gateway Stockholm')
            ->setDefaultAssignee($technician)
            ->setSortOrder(1);

        $this->entityManager->persist($creator);
        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($category);
        $this->entityManager->persist($templateV1);
        $this->entityManager->persist($fieldV1);
        $this->entityManager->persist($templateV2);
        $this->entityManager->persist($fieldV2);
        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        $templateV2Id = $templateV2->getId();

        $this->client->loginUser($creator);
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Skapa ticket')->form([
            'subject' => 'Mallfamilj routas',
            'summary' => 'Ska routas även efter ny mallversion.',
            'category_id' => (string) $category->getId(),
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::CRITICAL_SERVICE->value,
            'priority' => TicketPriority::NORMAL->value,
            'escalation_level' => TicketEscalationLevel::NONE->value,
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'intake_answers[affected_service]' => 'VPN gateway Stockholm',
            'assignee_id' => 'auto',
            'assigned_team_id' => 'auto',
            'sla_policy_id' => '',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician');

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Mallfamilj routas']);
        self::assertNotNull($ticket);
        self::assertSame($templateV2Id, $ticket->getIntakeTemplate()?->getId());
        self::assertSame($technician->getId(), $ticket->getAssignee()?->getId());
        self::assertSame($team->getId(), $ticket->getAssignedTeam()?->getId());
    }

    public function testCreatingTicketStoresConfiguredIntakeAnswers(): void
    {
        $creator = new User('intake-admin@example.test', 'Ina', 'Take', UserType::ADMIN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'IntakeAdmin123'));
        $creator->enableMfa();

        $company = new Company('Intake AB');
        $networkCategory = new TicketCategory('Nätverk');
        $financeCategory = new TicketCategory('Ekonomi');
        $networkTemplate = (new TicketIntakeTemplate('Nätverksmall', TicketRequestType::INCIDENT))
            ->setCategory($networkCategory)
            ->setCustomerType(UserType::CUSTOMER);
        $customer = new User('intake-customer@example.test', 'Ivar', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'IntakeCustomer123'));
        $customer->setCompany($company);

        $affectedServiceField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'affected_service', 'Påverkad tjänst'))
            ->setFieldType(TicketIntakeFieldType::SELECT)
            ->setSelectOptions(['VPN gateway Stockholm', 'VPN gateway Göteborg'])
            ->setTemplate($networkTemplate)
            ->setRequired(true)
            ->setSortOrder(10);
        $environmentField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'environment', 'Miljö'))
            ->setFieldType(TicketIntakeFieldType::SELECT)
            ->setSelectOptions(['Produktion', 'Test'])
            ->setTemplate($networkTemplate)
            ->setRequired(false)
            ->setSortOrder(20);
        $affectedUsersField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'affected_users', 'Påverkade användare'))
            ->setTemplate($networkTemplate)
            ->setDependsOnFieldKey('environment')
            ->setDependsOnFieldValue('Produktion')
            ->setRequired(true)
            ->setSortOrder(25);
        $invoiceNumberField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'invoice_number', 'Fakturanummer'))
            ->setCategory($financeCategory)
            ->setRequired(true)
            ->setSortOrder(30);

        $this->entityManager->persist($creator);
        $this->entityManager->persist($company);
        $this->entityManager->persist($networkCategory);
        $this->entityManager->persist($financeCategory);
        $this->entityManager->persist($networkTemplate);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($affectedServiceField);
        $this->entityManager->persist($environmentField);
        $this->entityManager->persist($affectedUsersField);
        $this->entityManager->persist($invoiceNumberField);
        $this->entityManager->flush();

        $this->client->loginUser($creator);
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Skapa ticket')->form([
            'subject' => 'Kritisk VPN-incident',
            'summary' => 'Behöver spara intake-svar för incidenten.',
            'category_id' => (string) $networkCategory->getId(),
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::COMPANY->value,
            'priority' => TicketPriority::HIGH->value,
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'intake_answers[affected_service]' => 'VPN gateway Stockholm',
            'intake_answers[environment]' => 'Produktion',
            'intake_answers[affected_users]' => '120',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician');

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Kritisk VPN-incident']);
        self::assertNotNull($ticket);
        self::assertSame('Nätverk', $ticket->getCategory()?->getName());
        self::assertSame([
            'affected_service' => 'VPN gateway Stockholm',
            'affected_users' => '120',
            'environment' => 'Produktion',
        ], $ticket->getIntakeAnswers());
    }

    public function testTemplateDefaultSlaIsUsedWhenTicketUsesAutoSla(): void
    {
        $creator = new User('template-sla-admin@example.test', 'Tea', 'Template', UserType::ADMIN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'TemplateSlaAdmin123'));
        $creator->enableMfa();

        $company = new Company('Template SLA AB');
        $category = new TicketCategory('Nätverk');
        $team = new TechnicianTeam('Template Team');
        $assignee = new User('template-default-tech@example.test', 'Tage', 'Tekniker', UserType::TECHNICIAN);
        $assignee->setPassword($this->passwordHasher->hashPassword($assignee, 'TemplateDefaultTech123'));
        $assignee->enableMfa();
        $slaPolicy = new SlaPolicy('Mallstandard SLA', 2, 10);
        $template = (new TicketIntakeTemplate('SLA-mall', TicketRequestType::INCIDENT))
            ->setCategory($category)
            ->setCustomerType(UserType::CUSTOMER)
            ->setDefaultSlaPolicy($slaPolicy)
            ->setDefaultPriority(TicketPriority::HIGH)
            ->setDefaultTeam($team)
            ->setDefaultAssignee($assignee)
            ->setDefaultEscalationLevel(TicketEscalationLevel::INCIDENT)
            ->setPlaybookText("Bekräfta påverkan\nStarta första felsökning")
            ->setChecklistItems(['Verifiera tjänst', 'Återkoppla till kund']);
        $customer = new User('template-sla-customer@example.test', 'Tina', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'TemplateSlaCustomer123'));
        $customer->setCompany($company);

        $field = (new TicketIntakeField(TicketRequestType::INCIDENT, 'affected_service', 'Påverkad tjänst'))
            ->setTemplate($template)
            ->setRequired(true)
            ->setSortOrder(10);

        $this->entityManager->persist($creator);
        $this->entityManager->persist($company);
        $this->entityManager->persist($category);
        $this->entityManager->persist($team);
        $this->entityManager->persist($assignee);
        $this->entityManager->persist($slaPolicy);
        $this->entityManager->persist($template);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($field);
        $this->entityManager->flush();

        $templateId = $template->getId();
        $slaPolicyId = $slaPolicy->getId();

        $this->client->loginUser($creator);
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Skapa ticket')->form([
            'subject' => 'Mallstyrd SLA-ticket',
            'summary' => 'Ska få SLA från mallversionen.',
            'category_id' => (string) $category->getId(),
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::COMPANY->value,
            'priority' => 'auto',
            'escalation_level' => 'auto',
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'intake_answers[affected_service]' => 'VPN gateway Stockholm',
            'assignee_id' => 'auto',
            'assigned_team_id' => 'auto',
            'sla_policy_id' => 'auto',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician');

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Mallstyrd SLA-ticket']);
        self::assertNotNull($ticket);
        self::assertSame($templateId, $ticket->getIntakeTemplate()?->getId());
        self::assertSame($slaPolicyId, $ticket->getSlaPolicy()?->getId());
        self::assertSame(TicketPriority::HIGH, $ticket->getPriority());
        self::assertSame($team->getId(), $ticket->getAssignedTeam()?->getId());
        self::assertSame($assignee->getId(), $ticket->getAssignee()?->getId());
        self::assertSame(TicketEscalationLevel::INCIDENT, $ticket->getEscalationLevel());

    }

    public function testTechnicianCanCompleteInteractiveChecklistWhenAdminHasEnabledIt(): void
    {
        $creator = new User('checklist-admin@example.test', 'Cleo', 'Checklist', UserType::ADMIN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'ChecklistAdmin123'));
        $creator->enableMfa();

        $company = new Company('Checklist AB');
        $category = new TicketCategory('Klientdrift');
        $template = (new TicketIntakeTemplate('Checklistmall', TicketRequestType::INCIDENT))
            ->setCategory($category)
            ->setCustomerType(UserType::CUSTOMER)
            ->setChecklistItems(['Bekräfta påverkan', 'Verifiera tjänst']);
        $customer = new User('checklist-customer@example.test', 'Cissi', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'ChecklistCustomer123'));
        $customer->setCompany($company);

        $ticket = (new Ticket('DP-7010', 'Checklist-ticket', 'Ticket med interaktiv checklista.'))
            ->setCategory($category)
            ->setCompany($company)
            ->setRequester($customer)
            ->setIntakeTemplate($template);

        $this->entityManager->persist($creator);
        $this->entityManager->persist($company);
        $this->entityManager->persist($category);
        $this->entityManager->persist($template);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        $systemSettings = static::getContainer()->get(SystemSettings::class);
        $systemSettings->setBool(SystemSettings::FEATURE_TICKET_TEMPLATE_CHECKLIST_ENABLED, true);
        $systemSettings->setBool(SystemSettings::FEATURE_TICKET_TEMPLATE_CHECKLIST_PROGRESS_ENABLED, true);

        $this->client->loginUser($creator);
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('0/2 klara', (string) $crawler->html());

        $form = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d/checklist"]', $ticket->getId()))->form([
            'checklist_items' => ['Bekräfta påverkan'],
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $updatedTicket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['reference' => 'DP-7010']);
        self::assertNotNull($updatedTicket);
        self::assertSame(
            ['Bekräfta påverkan' => true, 'Verifiera tjänst' => false],
            $updatedTicket->getChecklistProgress(),
        );

        $auditLog = $this->entityManager->getRepository(TicketAuditLog::class)->findOneBy(
            ['ticket' => $updatedTicket, 'action' => 'ticket_checklist_updated'],
            ['id' => 'DESC'],
        );
        self::assertNotNull($auditLog);
        self::assertSame('Checklistan uppdaterades: 1 av 2 punkter klara.', $auditLog->getMessage());

        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();
        self::assertStringContainsString('1/2 klara', $html);
        self::assertStringContainsString('Spara checklista', $html);
    }

    public function testCustomerOnlySeesChecklistSummaryWhenAdminAllowsIt(): void
    {
        $customer = new User('summary-customer@example.test', 'Sara', 'Summary', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'SummaryCustomer123'));
        $technician = new User('summary-tech@example.test', 'Ture', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'SummaryTech123'));
        $technician->enableMfa();

        $company = new Company('Summary AB');
        $category = new TicketCategory('Nätverk');
        $team = new TechnicianTeam('NOC');
        $slaPolicy = new SlaPolicy('Kund-SLA', 2, 8);
        $template = (new TicketIntakeTemplate('Kundsammanfattning', TicketRequestType::INCIDENT))
            ->setCategory($category)
            ->setCustomerType(UserType::CUSTOMER)
            ->setPlaybookText("Bekräfta påverkan\nIntern felsökning")
            ->setChecklistItems(['Verifiera tjänst', 'Samla loggar']);

        $ticket = (new Ticket('DP-7011', 'Kundöversikt', 'Kunden ska bara se rätt nivå av status.'))
            ->setCategory($category)
            ->setCompany($company)
            ->setRequester($customer)
            ->setStatus(TicketStatus::PENDING_CUSTOMER)
            ->setAssignee($technician)
            ->setAssignedTeam($team)
            ->setSlaPolicy($slaPolicy)
            ->setIntakeTemplate($template)
            ->setChecklistProgress([
                'Verifiera tjänst' => true,
                'Samla loggar' => false,
            ]);
        $olderWaitingTicket = (new Ticket('DP-7012', 'Äldre väntande ärende', 'Det här ärendet har väntat längre på kunden.'))
            ->setCategory($category)
            ->setCompany($company)
            ->setRequester($customer)
            ->setStatus(TicketStatus::PENDING_CUSTOMER);
        $inProgressTicket = (new Ticket('DP-7013', 'Pågående felsökning', 'Teamet arbetar fortfarande med det här ärendet.'))
            ->setCategory($category)
            ->setCompany($company)
            ->setRequester($customer)
            ->setStatus(TicketStatus::OPEN);
        $resolvedTicket = (new Ticket('DP-7014', 'Löst ärende', 'Det här ärendet är redan avslutat.'))
            ->setCategory($category)
            ->setCompany($company)
            ->setRequester($customer)
            ->setStatus(TicketStatus::RESOLVED);

        $customer->setCompany($company);

        $this->entityManager->persist($company);
        $this->entityManager->persist($category);
        $this->entityManager->persist($team);
        $this->entityManager->persist($slaPolicy);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($template);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($ticket);
        $this->entityManager->persist($olderWaitingTicket);
        $this->entityManager->persist($inProgressTicket);
        $this->entityManager->persist($resolvedTicket);
        $this->entityManager->persist(new TicketAuditLog($ticket, 'ticket_created', 'Ärendet skapades.', $customer));
        $this->entityManager->persist(new TicketAuditLog($ticket, 'customer_visible_comment_added', 'Tekniker svarade i ärendet.', $technician));
        $this->entityManager->persist(new TicketAuditLog($ticket, 'ticket_updated', 'status Ny -> Väntar på kund.', $technician));
        $this->entityManager->persist(new TicketComment($ticket, $technician, 'Vi behöver att du bekräftar om felet kvarstår efter senaste testet.'));
        $this->entityManager->persist(new TicketComment($olderWaitingTicket, $technician, 'Vi väntar fortfarande på din bekräftelse innan vi går vidare med felsökningen.'));
        $this->entityManager->flush();
        $this->backdateTicket($olderWaitingTicket, '-96 hours');

        $systemSettings = static::getContainer()->get(SystemSettings::class);
        $systemSettings->setBool(SystemSettings::FEATURE_TICKET_TEMPLATE_PLAYBOOK_ENABLED, true);
        $systemSettings->setBool(SystemSettings::FEATURE_TICKET_TEMPLATE_CHECKLIST_ENABLED, true);
        $systemSettings->setBool(SystemSettings::FEATURE_TICKET_TEMPLATE_CHECKLIST_PROGRESS_ENABLED, true);

        $customer = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'summary-customer@example.test']);
        self::assertNotNull($customer);

        $this->client->loginUser($customer);
        $crawler = $this->client->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();

        self::assertStringNotContainsString('Mallplaybook', $html);
        self::assertStringNotContainsString('Bekräfta påverkan', $html);
        self::assertStringNotContainsString('Verifiera tjänst', $html);
        self::assertStringNotContainsString('Arbetsstatus', $html);
        self::assertStringNotContainsString('Tilldelad:', $html);
        self::assertStringNotContainsString('Team:', $html);
        self::assertStringNotContainsString('SLA:', $html);

        $systemSettings->setBool(SystemSettings::FEATURE_TICKET_TEMPLATE_CHECKLIST_CUSTOMER_VISIBLE, true);
        $this->entityManager->clear();

        $crawler = $this->client->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();

        self::assertStringContainsString('Du har ärenden som väntar på svar', $html);
        self::assertStringContainsString('2 ärenden behöver återkoppling från dig för att vi ska kunna komma vidare snabbare.', $html);
        self::assertStringContainsString('Detta bör du ta först', $html);
        self::assertStringContainsString('DP-7012 · Äldre väntande ärende', $html);
        self::assertStringContainsString('Prioriterad återkoppling', $html);
        self::assertStringContainsString('Prioriterad återkoppling', $html);
        self::assertStringContainsString('Kräver svar från dig', $html);
        self::assertStringContainsString('Vi arbetar med', $html);
        self::assertStringContainsString('Klart senaste tiden', $html);
        self::assertStringContainsString('2 väntar', $html);
        self::assertStringContainsString('1 pågår', $html);
        self::assertStringContainsString('1 klara', $html);
        self::assertStringContainsString('Visa vid behov', $html);
        self::assertStringContainsString('DP-7011 · Kundöversikt', $html);
        self::assertStringContainsString('DP-7012 · Äldre väntande ärende', $html);
        self::assertStringContainsString('last_ticket=DP-7012', $html);
        self::assertStringContainsString('last_ticket=DP-7011', $html);
        self::assertStringContainsString('#ticket-dp-7012-reply', $html);
        self::assertStringContainsString('#ticket-dp-7011-reply', $html);
        self::assertStringContainsString('id="ticket-dp-7011-reply"', $html);
        self::assertStringContainsString('Nytt idag', $html);
        self::assertStringContainsString('Väntat länge', $html);
        self::assertStringContainsString('Bra att ta nu medan dialogen är färsk.', $html);
        self::assertStringContainsString('Det här ärendet har väntat en längre stund på din återkoppling.', $html);
        self::assertStringContainsString('Vi väntar fortfarande på din bekräftelse innan vi går vidare med felsökningen.', $html);
        self::assertStringContainsString('Vi behöver att du bekräftar om felet kvarstår efter senaste testet.', $html);
        self::assertStringContainsString('Svara till tekniker', $html);
        self::assertTrue(strpos($html, 'DP-7012 · Äldre väntande ärende') < strpos($html, 'DP-7011 · Kundöversikt'));
        self::assertStringContainsString('Arbetsstatus', $html);
        self::assertStringContainsString('1/2 steg klara', $html);
        self::assertStringContainsString('Ärendeaktivitet', $html);
        self::assertStringContainsString('Ärendet skapades.', $html);
        self::assertStringContainsString('Tekniker svarade i ärendet.', $html);
        self::assertStringContainsString('Status ändrades till väntar på kund.', $html);
        self::assertStringContainsString('Vi väntar på dig', $html);
        self::assertStringContainsString('Din tur', $html);
        self::assertStringContainsString('Hos oss nu', $html);
        self::assertStringContainsString('Teknikerteamet har återkopplat och väntar på svar eller bekräftelse från dig.', $html);
        self::assertStringContainsString('Vi behöver din återkoppling', $html);
        self::assertStringContainsString('Skicka uppdatering', $html);
        self::assertStringNotContainsString('Mallplaybook', $html);
        self::assertStringNotContainsString('Bekräfta påverkan', $html);
        self::assertStringNotContainsString('Verifiera tjänst', $html);
        self::assertStringNotContainsString('Spara checklista', $html);
        self::assertStringNotContainsString('Tilldelad:', $html);
        self::assertStringNotContainsString('Team:', $html);
        self::assertStringNotContainsString('SLA:', $html);

        $crawler = $this->client->request('GET', '/portal/customer?only_open=1');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();

        self::assertStringContainsString('Visar aktiva ärenden nu.', $html);
        self::assertStringContainsString('Visa alla', $html);
        self::assertStringContainsString('DP-7011 · Kundöversikt', $html);
        self::assertStringContainsString('DP-7012 · Äldre väntande ärende', $html);
        self::assertStringContainsString('DP-7013', $html);
        self::assertStringNotContainsString('DP-7014', $html);
        self::assertStringNotContainsString('1 klara', $html);
        self::assertStringNotContainsString('Visa vid behov', $html);

        $crawler = $this->client->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();

        self::assertStringContainsString('Visar aktiva ärenden nu.', $html);
        self::assertStringContainsString('DP-7013', $html);
        self::assertStringNotContainsString('DP-7014', $html);

        $crawler = $this->client->request('GET', '/portal/customer?only_open=0');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();

        self::assertStringContainsString('Visar både aktiva och avslutade ärenden.', $html);
        self::assertStringContainsString('DP-7014', $html);
        self::assertStringContainsString('1 klara', $html);

        $crawler = $this->client->request('GET', '/portal/customer?last_ticket=DP-7013');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();

        self::assertStringContainsString('Senaste vy', $html);
        self::assertStringContainsString('DP-7013', $html);
        self::assertStringContainsString('Pågående felsökning', $html);
        self::assertStringContainsString('Öppna ärendet igen', $html);
        self::assertStringContainsString('last_ticket=DP-7013', $html);

        $crawler = $this->client->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();

        self::assertStringContainsString('Senaste vy', $html);
        self::assertStringContainsString('DP-7013', $html);
        self::assertStringContainsString('Pågående felsökning', $html);

        $crawler = $this->client->request('GET', '/portal/customer?show_completed=1');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();

        self::assertStringContainsString('Dölj klara', $html);
        self::assertStringContainsString('class="customer-ticket-group" open', $html);
        self::assertStringContainsString('DP-7014', $html);

        $crawler = $this->client->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();

        self::assertStringContainsString('Dölj klara', $html);
        self::assertStringContainsString('DP-7014', $html);

        $crawler = $this->client->request('GET', '/portal/customer?show_completed=0');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();

        self::assertStringContainsString('Visa klara direkt', $html);
        self::assertStringContainsString('Visa vid behov', $html);
    }

    public function testConditionalIntakeFieldIsIgnoredWhenDependencyIsNotMet(): void
    {
        $creator = new User('conditional-admin@example.test', 'Conny', 'Admin', UserType::ADMIN);
        $creator->setPassword($this->passwordHasher->hashPassword($creator, 'ConditionalAdmin123'));
        $creator->enableMfa();

        $company = new Company('Conditional AB');
        $networkCategory = new TicketCategory('Nätverk');
        $customer = new User('conditional-customer@example.test', 'Cia', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'ConditionalCustomer123'));
        $customer->setCompany($company);

        $environmentField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'environment', 'Miljö'))
            ->setFieldType(TicketIntakeFieldType::SELECT)
            ->setSelectOptions(['Produktion', 'Test'])
            ->setRequired(true)
            ->setSortOrder(10);
        $affectedUsersField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'affected_users', 'Påverkade användare'))
            ->setCategory($networkCategory)
            ->setCustomerType(UserType::CUSTOMER)
            ->setDependsOnFieldKey('environment')
            ->setDependsOnFieldValue('Produktion')
            ->setRequired(true)
            ->setSortOrder(20);

        $this->entityManager->persist($creator);
        $this->entityManager->persist($company);
        $this->entityManager->persist($networkCategory);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($environmentField);
        $this->entityManager->persist($affectedUsersField);
        $this->entityManager->flush();

        $this->client->loginUser($creator);
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Skapa ticket')->form([
            'subject' => 'Testmiljö incident',
            'summary' => 'Följdfrågan ska inte krävas i testmiljö.',
            'category_id' => (string) $networkCategory->getId(),
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::TEAM->value,
            'priority' => TicketPriority::NORMAL->value,
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'intake_answers[environment]' => 'Test',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician');

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Testmiljö incident']);
        self::assertNotNull($ticket);
        self::assertSame([
            'environment' => 'Test',
        ], $ticket->getIntakeAnswers());
    }

    public function testReassigningTicketNotifiesNewTechnician(): void
    {
        [$company, $technician, $customer, $ticket] = $this->createTicketFixture();
        $newTechnician = new User('newtech@example.test', 'Nina', 'Nytekniker', UserType::TECHNICIAN);
        $newTechnician->setPassword($this->passwordHasher->hashPassword($newTechnician, 'NewTechPassword123'));
        $newTechnician->enableMfa();
        $this->entityManager->persist($newTechnician);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d"]', $ticket->getId()))->form([
            'subject' => $ticket->getSubject(),
            'summary' => $ticket->getSummary(),
            'priority' => TicketPriority::CRITICAL->value,
            'status' => $ticket->getStatus()->value,
            'visibility' => $ticket->getVisibility()->value,
            'company_id' => (string) $company->getId(),
            'requester_id' => (string) $customer->getId(),
            'assignee_id' => (string) $newTechnician->getId(),
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician');
        self::assertEmailCount(1);

        /** @var Email $email */
        $email = $this->getMailerMessage();
        self::assertSame(['newtech@example.test'], array_map(static fn ($address) => $address->getAddress(), $email->getTo()));
        self::assertStringContainsString('Ticket tilldelad dig', $email->getSubject());
        self::assertStringContainsString('Ticketen har tilldelats om och väntar nu på din hantering.', $email->getTextBody() ?? '');
    }

    public function testTechnicianPortalCanFilterAndPaginateTickets(): void
    {
        $company = new Company('Filter AB');
        $opsTeam = new TechnicianTeam('Ops');
        $fieldTeam = new TechnicianTeam('Field');
        $technician = new User('filter-tech@example.test', 'Filip', 'Filter', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'FilterPassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($opsTeam);

        $otherTechnician = new User('other-tech@example.test', 'Olga', 'Other', UserType::TECHNICIAN);
        $otherTechnician->setPassword($this->passwordHasher->hashPassword($otherTechnician, 'OtherPassword123'));
        $otherTechnician->enableMfa();
        $otherTechnician->setTechnicianTeam($fieldTeam);

        $customer = new User('filter-customer@example.test', 'Klara', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $this->entityManager->persist($company);
        $this->entityManager->persist($opsTeam);
        $this->entityManager->persist($fieldTeam);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($otherTechnician);
        $this->entityManager->persist($customer);

        for ($i = 1; $i <= 12; ++$i) {
            $ticket = new Ticket(
                sprintf('DP-%04d', 3000 + $i),
                sprintf('Filterticket %d', $i),
                'Ticket för att verifiera queryfilter och paginering i teknikervyn.',
                TicketStatus::OPEN,
                TicketVisibility::PRIVATE,
                12 === $i ? TicketPriority::CRITICAL : (11 === $i ? TicketPriority::HIGH : TicketPriority::NORMAL),
                12 === $i ? TicketRequestType::INCIDENT : TicketRequestType::SERVICE_REQUEST,
                12 === $i ? TicketImpactLevel::CRITICAL_SERVICE : TicketImpactLevel::TEAM,
                12 === $i ? TicketEscalationLevel::INCIDENT : (11 === $i ? TicketEscalationLevel::LEAD : TicketEscalationLevel::NONE),
            );
            $ticket->setCompany($company);
            $ticket->setRequester($customer);
            $ticket->setAssignee($technician);
            $ticket->setAssignedTeam(12 === $i ? $fieldTeam : $opsTeam);
            $this->entityManager->persist($ticket);
        }

        $excludedTicket = new Ticket(
            'DP-3999',
            'Ska inte synas',
            'Den här ticketen tillhör en annan tekniker och annan status.',
            TicketStatus::CLOSED,
            TicketVisibility::PRIVATE,
            TicketPriority::LOW,
            TicketRequestType::BILLING,
            TicketImpactLevel::SINGLE_USER,
        );
        $excludedTicket->setCompany($company);
        $excludedTicket->setRequester($customer);
        $excludedTicket->setAssignee($otherTechnician);
        $excludedTicket->setAssignedTeam($fieldTeam);
        $this->entityManager->persist($excludedTicket);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician?scope=mine&status=open&sort=reference_asc&page=2');
        self::assertResponseIsSuccessful();

        $html = $crawler->html();
        self::assertIsString($html);
        self::assertStringContainsString('Mina (12)', $html);
        self::assertStringContainsString('Öppen (12)', $html);
        self::assertStringContainsString('Stängd (1)', $html);
        self::assertStringContainsString('Kritisk (1)', $html);
        self::assertStringContainsString('Hög (1)', $html);
        self::assertStringContainsString('Mitt team (11)', $html);
        self::assertStringContainsString('12 träffar, sida 2 av 2', $html);
        self::assertStringContainsString('DP-3011', $html);
        self::assertStringContainsString('DP-3012', $html);
        self::assertStringNotContainsString('DP-3001', $html);
        self::assertStringNotContainsString('DP-3999', $html);

        $priorityCrawler = $this->client->request('GET', '/portal/technician?priority=critical&sort=priority_desc');
        self::assertResponseIsSuccessful();
        $priorityHtml = $priorityCrawler->html();
        self::assertIsString($priorityHtml);
        self::assertStringContainsString('Kritisk', $priorityHtml);
        self::assertStringContainsString('DP-3012', $priorityHtml);
        self::assertStringNotContainsString('DP-3011', $priorityHtml);

        $requestTypeCrawler = $this->client->request('GET', '/portal/technician?request_type=incident&impact=critical_service');
        self::assertResponseIsSuccessful();
        $requestTypeHtml = $requestTypeCrawler->html();
        self::assertIsString($requestTypeHtml);
        self::assertStringContainsString('DP-3012', $requestTypeHtml);
        self::assertStringNotContainsString('DP-3011', $requestTypeHtml);

        $escalationCrawler = $this->client->request('GET', '/portal/technician?escalation=incident&sort=escalation_desc');
        self::assertResponseIsSuccessful();

        $escalationHtml = $escalationCrawler->html();
        self::assertIsString($escalationHtml);
        self::assertStringContainsString('Incident', $escalationHtml);
        self::assertStringContainsString('DP-3012', $escalationHtml);
        self::assertStringNotContainsString('DP-3011', $escalationHtml);

        $teamCrawler = $this->client->request('GET', '/portal/technician?team='.$opsTeam->getId().'&scope=my_team');
        self::assertResponseIsSuccessful();
        $teamHtml = $teamCrawler->html();
        self::assertIsString($teamHtml);
        self::assertStringContainsString('Ops', $teamHtml);
        self::assertStringContainsString('DP-3011', $teamHtml);
        self::assertStringNotContainsString('DP-3012', $teamHtml);
    }

    public function testTechnicianPortalShowsOwnSlaQueues(): void
    {
        $company = new Company('SLA Queue AB');
        $technician = new User('queue-tech@example.test', 'Tess', 'Queue', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'QueuePassword123'));
        $technician->enableMfa();

        $otherTechnician = new User('queue-other@example.test', 'Olle', 'Other', UserType::TECHNICIAN);
        $otherTechnician->setPassword($this->passwordHasher->hashPassword($otherTechnician, 'OtherPassword123'));
        $otherTechnician->enableMfa();

        $customer = new User('queue-customer@example.test', 'Kim', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $slaPolicy = new SlaPolicy('Queue SLA', 4, 24);

        $ownBreachedTicket = new Ticket('DP-6201', 'Egen SLA-bruten', 'Ska synas i min SLA-kö.', TicketStatus::OPEN, TicketVisibility::PRIVATE, TicketPriority::HIGH, TicketRequestType::INCIDENT, TicketImpactLevel::COMPANY);
        $ownBreachedTicket->setCompany($company)->setRequester($customer)->setAssignee($technician)->setSlaPolicy($slaPolicy);

        $otherBreachedTicket = new Ticket('DP-6202', 'Annan teknikers SLA', 'Ska inte synas i min SLA-kö.', TicketStatus::OPEN, TicketVisibility::PRIVATE, TicketPriority::HIGH, TicketRequestType::INCIDENT, TicketImpactLevel::COMPANY);
        $otherBreachedTicket->setCompany($company)->setRequester($customer)->setAssignee($otherTechnician)->setSlaPolicy($slaPolicy);

        $this->entityManager->persist($company);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($otherTechnician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($slaPolicy);
        $this->entityManager->persist($ownBreachedTicket);
        $this->entityManager->persist($otherBreachedTicket);
        $this->entityManager->flush();

        $this->backdateTicket($ownBreachedTicket, '-30 hours');
        $this->backdateTicket($otherBreachedTicket, '-30 hours');

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $html = $crawler->html();
        self::assertIsString($html);
        self::assertStringContainsString('Min SLA-kö', $html);
        self::assertStringContainsString('SLA bruten</div>', $html);
        self::assertStringContainsString('>1</div>', $html);
        self::assertStringContainsString('DP-6201', $html);
    }

    public function testTicketKeepsUsingLockedIntakeTemplateVersionOnUpdate(): void
    {
        $company = new Company('Versioned Customer AB');
        $technician = new User('template-lock-tech@example.test', 'Tove', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'TechPassword123'));
        $technician->enableMfa();

        $customer = new User('template-lock-customer@example.test', 'Kim', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $legacyTemplate = (new TicketIntakeTemplate('Driftmall', TicketRequestType::INCIDENT))
            ->setVersionNumber(1)
            ->retireCurrentVersion()
            ->deactivate();
        $currentTemplate = (new TicketIntakeTemplate('Driftmall v2', TicketRequestType::INCIDENT))
            ->setVersionFamily($legacyTemplate->getVersionFamily())
            ->setVersionNumber(2)
            ->markAsCurrentVersion();

        $legacyField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'legacy_window', 'Äldre fält'))
            ->setTemplate($legacyTemplate)
            ->setSortOrder(10);
        $currentField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'current_environment', 'Nytt fält'))
            ->setTemplate($currentTemplate)
            ->setSortOrder(10);

        $ticket = (new Ticket(
            'DP-2099',
            'Versionslåst ticket',
            'Ska fortsätta använda gamla mallfält vid uppdatering.',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
        ))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setIntakeTemplate($legacyTemplate)
            ->setIntakeAnswers(['legacy_window' => 'Fönster A']);

        $this->entityManager->persist($company);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($legacyTemplate);
        $this->entityManager->persist($currentTemplate);
        $this->entityManager->persist($legacyField);
        $this->entityManager->persist($currentField);
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        $legacyTemplateId = $legacyTemplate->getId();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $updateFormNode = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d"]', $ticket->getId()));
        self::assertGreaterThan(0, $updateFormNode->count());
        $updateFormHtml = $updateFormNode->html();
        self::assertIsString($updateFormHtml);
        self::assertStringContainsString('intake_answers[legacy_window]', $updateFormHtml);
        self::assertStringNotContainsString('intake_answers[current_environment]', $updateFormHtml);

        $updateForm = $updateFormNode->form([
            'subject' => 'Versionslåst ticket',
            'summary' => 'Uppdaterad men fortsatt låst till gammal mallversion.',
            'status' => TicketStatus::OPEN->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            'priority' => TicketPriority::NORMAL->value,
            'escalation_level' => TicketEscalationLevel::NONE->value,
            'intake_answers[legacy_window]' => 'Fönster B',
        ]);
        $this->client->submit($updateForm);

        self::assertResponseRedirects('/portal/technician');

        $this->entityManager->clear();
        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['reference' => 'DP-2099']);
        self::assertNotNull($ticket);
        self::assertSame($legacyTemplateId, $ticket->getIntakeTemplate()?->getId());
        self::assertSame(['legacy_window' => 'Fönster B'], $ticket->getIntakeAnswers());
    }

    public function testCustomerCanAttachLocalFileAndExternalLinkWhenAdminAllowsIt(): void
    {
        [, $technician, $customer, $ticket] = $this->createTicketFixture();
        $ticket->setStatus(TicketStatus::PENDING_CUSTOMER);
        $this->entityManager->persist(new TicketComment($ticket, $technician, 'Skicka gärna skärmbild eller loggfil om du har det.'));
        $this->entityManager->flush();

        $systemSettings = static::getContainer()->get(SystemSettings::class);
        $systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ENABLED, true);
        $systemSettings->setInt(SystemSettings::TICKET_ATTACHMENTS_MAX_UPLOAD_MB, 5);
        $systemSettings->setString(SystemSettings::TICKET_ATTACHMENTS_STORAGE_PATH, 'var/test_ticket_uploads');
        $systemSettings->setString(SystemSettings::TICKET_ATTACHMENTS_ALLOWED_EXTENSIONS, 'png,txt,log');
        $systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_EXTERNAL_ENABLED, true);
        $systemSettings->setString(SystemSettings::TICKET_ATTACHMENTS_EXTERNAL_PROVIDER_LABEL, 'OneDrive');
        $systemSettings->setString(SystemSettings::TICKET_ATTACHMENTS_EXTERNAL_INSTRUCTIONS, 'Ladda upp stora filer i OneDrive och klistra in länken här.');

        $tempFileBase = tempnam(sys_get_temp_dir(), 'ticket-upload-');
        self::assertNotFalse($tempFileBase);
        $tempFile = $tempFileBase.'.txt';
        rename($tempFileBase, $tempFile);
        file_put_contents($tempFile, 'Test skarmbildsinnehall');

        $this->client->loginUser($customer);
        $crawler = $this->client->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();
        self::assertStringContainsString('Bilagor', $html);
        self::assertStringContainsString('upp till 5 MB', $html);
        self::assertStringContainsString('Tillåtna filtyper: png, txt, log', $html);
        self::assertStringContainsString('Större filer via OneDrive', $html);
        self::assertStringContainsString('Ladda upp stora filer i OneDrive och klistra in länken här.', $html);

        $form = $crawler->filter(sprintf('form[action="/portal/customer/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Här kommer skärmbilden.',
        ]);
        $form['attachment']->upload($tempFile);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/customer');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $attachment = $this->entityManager->getRepository(TicketCommentAttachment::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($attachment);
        self::assertFalse($attachment->isExternal());
        self::assertSame(basename($tempFile), $attachment->getDisplayName());

        $crawler = $this->client->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();
        self::assertStringContainsString('Bilageöversikt', $html);
        self::assertStringContainsString('1 dokument', $html);
        self::assertStringContainsString(basename($tempFile), $html);
        self::assertStringContainsString('Dokument', $html);
        self::assertStringContainsString('Ladda ner', $html);

        $this->client->request('GET', sprintf('/portal/ticket-attachments/%d/download', $attachment->getId()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment;', (string) $this->client->getResponse()->headers->get('content-disposition'));
        self::assertSame('Test skarmbildsinnehall', file_get_contents((string) $attachment->getFilePath()));

        $crawler = $this->client->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter(sprintf('form[action="/portal/customer/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Här är storfilslänken.',
            'external_attachment_label' => 'Stor loggfil',
            'external_attachment_url' => 'https://example.test/shared/loggfil',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/customer');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $externalAttachment = $this->entityManager->getRepository(TicketCommentAttachment::class)->findOneBy(
            ['displayName' => 'Stor loggfil'],
            ['id' => 'DESC'],
        );
        self::assertNotNull($externalAttachment);
        self::assertTrue($externalAttachment->isExternal());
        self::assertSame('https://example.test/shared/loggfil', $externalAttachment->getExternalUrl());

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('1 extern länk', $html);
        self::assertStringContainsString('Stor loggfil', $html);
        self::assertStringContainsString('Extern länk', $html);
        self::assertStringContainsString('Öppna länk', $html);

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['reference' => 'DP-2001']);
        self::assertNotNull($ticket);
        $auditLog = $this->entityManager->getRepository(TicketAuditLog::class)->findOneBy(
            ['ticket' => $ticket, 'action' => 'customer_comment_added'],
            ['id' => 'DESC'],
        );
        self::assertNotNull($auditLog);
        self::assertSame('Kunden har skickat ett nytt svar. 1 bilaga följde med.', $auditLog->getMessage());

        @unlink($tempFile);
    }

    public function testCustomerCannotUploadDisallowedAttachmentType(): void
    {
        [, $technician, $customer, $ticket] = $this->createTicketFixture();
        $ticket->setStatus(TicketStatus::PENDING_CUSTOMER);
        $this->entityManager->persist(new TicketComment($ticket, $technician, 'Skicka gärna underlag.'));
        $this->entityManager->flush();

        $systemSettings = static::getContainer()->get(SystemSettings::class);
        $systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ENABLED, true);
        $systemSettings->setInt(SystemSettings::TICKET_ATTACHMENTS_MAX_UPLOAD_MB, 5);
        $systemSettings->setString(SystemSettings::TICKET_ATTACHMENTS_STORAGE_PATH, 'var/test_ticket_uploads');
        $systemSettings->setString(SystemSettings::TICKET_ATTACHMENTS_ALLOWED_EXTENSIONS, 'png,pdf');

        $tempFile = tempnam(sys_get_temp_dir(), 'ticket-upload-blocked-');
        self::assertNotFalse($tempFile);
        $renamedFile = $tempFile.'.exe';
        rename($tempFile, $renamedFile);
        file_put_contents($renamedFile, 'blocked');

        $this->client->loginUser($customer);
        $crawler = $this->client->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/customer/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Försöker ladda upp fel filtyp.',
        ]);
        $form['attachment']->upload($renamedFile);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/customer');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Filtypen är inte tillåten. Tillåtna filtyper är: png, pdf.', (string) $crawler->html());

        $attachment = $this->entityManager->getRepository(TicketCommentAttachment::class)->findOneBy([]);
        self::assertNull($attachment);

        @unlink($renamedFile);
    }

    public function testCustomerSeesPreviewForImageAttachment(): void
    {
        [, $technician, $customer, $ticket] = $this->createTicketFixture();
        $ticket->setStatus(TicketStatus::PENDING_CUSTOMER);
        $this->entityManager->persist(new TicketComment($ticket, $technician, 'Här är en skärmbild på felet.'));
        $this->entityManager->flush();

        $systemSettings = static::getContainer()->get(SystemSettings::class);
        $systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ENABLED, true);
        $systemSettings->setInt(SystemSettings::TICKET_ATTACHMENTS_MAX_UPLOAD_MB, 5);
        $systemSettings->setString(SystemSettings::TICKET_ATTACHMENTS_STORAGE_PATH, 'var/test_ticket_uploads');
        $systemSettings->setString(SystemSettings::TICKET_ATTACHMENTS_ALLOWED_EXTENSIONS, 'png,jpg');

        $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0WQAAAAASUVORK5CYII=';
        $tempFileBase = tempnam(sys_get_temp_dir(), 'ticket-preview-');
        self::assertNotFalse($tempFileBase);
        $tempFile = $tempFileBase.'.png';
        rename($tempFileBase, $tempFile);
        file_put_contents($tempFile, base64_decode($pngBase64, true));

        $this->client->loginUser($customer);
        $crawler = $this->client->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/customer/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Här är skärmbilden.',
        ]);
        $form['attachment']->upload($tempFile);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/customer');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();

        $attachment = $this->entityManager->getRepository(TicketCommentAttachment::class)->findOneBy(
            ['displayName' => basename($tempFile)],
            ['id' => 'DESC'],
        );
        self::assertNotNull($attachment);
        self::assertTrue($attachment->isPreviewableImage());
        self::assertStringContainsString('Bilageöversikt', $html);
        self::assertStringContainsString('1 bild', $html);
        self::assertStringContainsString('Bild', $html);
        self::assertStringContainsString(sprintf('/portal/ticket-attachments/%d/preview', $attachment->getId()), $html);
        self::assertStringContainsString(sprintf('/portal/ticket-attachments/%d/download', $attachment->getId()), $html);

        $this->client->request('GET', sprintf('/portal/ticket-attachments/%d/preview', $attachment->getId()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('inline', (string) $this->client->getResponse()->headers->get('content-disposition'));
        self::assertSame('image/png', (string) $this->client->getResponse()->headers->get('content-type'));

        @unlink($tempFile);
    }

    public function testClosingTicketArchivesLocalAttachmentsIntoZip(): void
    {
        [, $technician, , $ticket] = $this->createTicketFixture();
        $ticket->setStatus(TicketStatus::OPEN);
        $systemSettings = static::getContainer()->get(SystemSettings::class);
        $systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ZIP_ARCHIVING_ENABLED, true);
        $systemSettings->setInt(SystemSettings::TICKET_ATTACHMENTS_ZIP_ARCHIVE_AFTER_DAYS, 0);

        $attachmentDirectory = dirname(__DIR__, 2).'/var/test_ticket_uploads/'.strtolower($ticket->getReference());
        if (!is_dir($attachmentDirectory)) {
            mkdir($attachmentDirectory, 0775, true);
        }

        $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0WQAAAAASUVORK5CYII=';
        $imagePath = $attachmentDirectory.'/archived-preview.png';
        file_put_contents($imagePath, base64_decode($pngBase64, true));

        $comment = new TicketComment($ticket, $technician, 'Bilagan ska zip-arkiveras när ticketen stängs.');
        $attachment = TicketCommentAttachment::fromLocalFile($comment, 'archived-preview.png', $imagePath, 'image/png', (int) filesize($imagePath));
        $comment->addAttachment($attachment);
        $ticket->addComment($comment);

        $this->entityManager->persist($comment);
        $this->entityManager->persist($attachment);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d"]', $ticket->getId()))->form([
            'subject' => $ticket->getSubject(),
            'summary' => $ticket->getSummary(),
            'status' => TicketStatus::CLOSED->value,
            'visibility' => $ticket->getVisibility()->value,
            'request_type' => $ticket->getRequestType()->value,
            'impact_level' => $ticket->getImpactLevel()->value,
            'priority' => $ticket->getPriority()->value,
            'escalation_level' => $ticket->getEscalationLevel()->value,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();

        $this->entityManager->clear();
        $attachment = $this->entityManager->getRepository(TicketCommentAttachment::class)->findOneBy(['displayName' => 'archived-preview.png']);
        self::assertNotNull($attachment);
        self::assertTrue($attachment->isArchivedInZip());
        self::assertNotNull($attachment->getArchiveEntryName());
        self::assertNotNull($attachment->getFilePath());
        self::assertFileDoesNotExist($imagePath);
        self::assertFileExists((string) $attachment->getFilePath());
        self::assertStringContainsString('.zip', (string) $attachment->getFilePath());
        self::assertStringContainsString('Zip-arkiverad', $html);

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['reference' => 'DP-2001']);
        self::assertNotNull($ticket);
        $auditLog = $this->entityManager->getRepository(TicketAuditLog::class)->findOneBy(
            ['ticket' => $ticket, 'action' => 'ticket_updated'],
            ['id' => 'DESC'],
        );
        self::assertNotNull($auditLog);
        self::assertStringContainsString('1 bilagor arkiverades i zip', $auditLog->getMessage());

        $this->client->request('GET', sprintf('/portal/ticket-attachments/%d/preview', $attachment->getId()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('inline', (string) $this->client->getResponse()->headers->get('content-disposition'));

        $this->client->request('GET', sprintf('/portal/ticket-attachments/%d/download', $attachment->getId()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment;', (string) $this->client->getResponse()->headers->get('content-disposition'));
    }

    public function testClosingTicketCanWaitForScheduledZipArchivingWhenAdminConfiguredDelay(): void
    {
        [, $technician, , $ticket] = $this->createTicketFixture();
        $systemSettings = static::getContainer()->get(SystemSettings::class);
        $systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ZIP_ARCHIVING_ENABLED, true);
        $systemSettings->setInt(SystemSettings::TICKET_ATTACHMENTS_ZIP_ARCHIVE_AFTER_DAYS, 3);

        $attachmentDirectory = dirname(__DIR__, 2).'/var/test_ticket_uploads/'.strtolower($ticket->getReference());
        if (!is_dir($attachmentDirectory)) {
            mkdir($attachmentDirectory, 0775, true);
        }

        $imagePath = $attachmentDirectory.'/delayed-archive.png';
        file_put_contents($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0WQAAAAASUVORK5CYII=', true));

        $comment = new TicketComment($ticket, $technician, 'Bilagan ska vänta till schemalagd zip-arkivering.');
        $attachment = TicketCommentAttachment::fromLocalFile($comment, 'delayed-archive.png', $imagePath, 'image/png', (int) filesize($imagePath));
        $comment->addAttachment($attachment);
        $ticket->addComment($comment);

        $this->entityManager->persist($comment);
        $this->entityManager->persist($attachment);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d"]', $ticket->getId()))->form([
            'subject' => $ticket->getSubject(),
            'summary' => $ticket->getSummary(),
            'status' => TicketStatus::CLOSED->value,
            'visibility' => $ticket->getVisibility()->value,
            'request_type' => $ticket->getRequestType()->value,
            'impact_level' => $ticket->getImpactLevel()->value,
            'priority' => $ticket->getPriority()->value,
            'escalation_level' => $ticket->getEscalationLevel()->value,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/technician');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $attachment = $this->entityManager->getRepository(TicketCommentAttachment::class)->findOneBy(['displayName' => 'delayed-archive.png']);
        self::assertNotNull($attachment);
        self::assertFalse($attachment->isArchivedInZip());
        self::assertFileExists($imagePath);

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:archive-ticket-attachments');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--days' => '0']);

        self::assertSame(0, $commandTester->getStatusCode());

        $this->entityManager->clear();
        $attachment = $this->entityManager->getRepository(TicketCommentAttachment::class)->findOneBy(['displayName' => 'delayed-archive.png']);
        self::assertNotNull($attachment);
        self::assertTrue($attachment->isArchivedInZip());
        self::assertFileDoesNotExist($imagePath);
        self::assertFileExists((string) $attachment->getFilePath());

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['reference' => 'DP-2001']);
        self::assertNotNull($ticket);
        $auditLog = $this->entityManager->getRepository(TicketAuditLog::class)->findOneBy(
            ['ticket' => $ticket, 'action' => 'ticket_attachments_archived'],
            ['id' => 'DESC'],
        );
        self::assertNotNull($auditLog);
        self::assertStringContainsString('1 bilagor arkiverades i zip via automatisk städning', $auditLog->getMessage());
    }

    /**
     * @return array{0: Company, 1: User, 2: User, 3: Ticket}
     */
    private function createTicketFixture(): array
    {
        $company = new Company('Example AB');
        $technician = new User('tech@example.test', 'Tina', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'TechPassword123'));
        $technician->enableMfa();

        $customer = new User('customer@example.test', 'Kalle', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $ticket = new Ticket(
            'DP-2001',
            'Testticket',
            'Det här är en testticket för kommentar- och mailflöden.',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
        );
        $ticket->setCompany($company);
        $ticket->setRequester($customer);
        $ticket->setAssignee($technician);

        $this->entityManager->persist($company);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        return [$company, $technician, $customer, $ticket];
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
