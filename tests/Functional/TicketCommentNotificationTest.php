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
        $crawler = $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $ticket->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/customer/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Vi har testat igen och felet kvarstår.',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/portal/customer/tickets/%d', $ticket->getId()));
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
        $crawler = $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Vi behöver att ni verifierar om problemet kvarstår efter omstart.',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
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

    public function testResolvingTicketNotifiesCustomerAndShowsResolutionInTicket(): void
    {
        [, $technician, $customer, $ticket] = $this->createTicketFixture();

        $this->client->loginUser($technician);
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d"]', $ticket->getId()))->form([
            'subject' => $ticket->getSubject(),
            'summary' => $ticket->getSummary(),
            'resolution_summary' => 'Vi uppdaterade skrivardrivrutinen och startade om spoolern. Allt fungerar igen.',
            'request_type' => $ticket->getRequestType()->value,
            'impact_level' => $ticket->getImpactLevel()->value,
            'priority' => $ticket->getPriority()->value,
            'escalation_level' => $ticket->getEscalationLevel()->value,
            'status' => TicketStatus::RESOLVED->value,
            'visibility' => $ticket->getVisibility()->value,
            'company_id' => (string) $ticket->getCompany()?->getId(),
            'requester_id' => (string) $ticket->getRequester()?->getId(),
            'category_id' => '',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
        self::assertEmailCount(1);

        /** @var Email $email */
        $email = $this->getMailerMessage();
        self::assertSame(['customer@example.test'], array_map(static fn ($address) => $address->getAddress(), $email->getTo()));
        self::assertStringContainsString('ärendet är löst', mb_strtolower($email->getSubject()));
        self::assertStringContainsString('Vi uppdaterade skrivardrivrutinen och startade om spoolern. Allt fungerar igen.', $email->getTextBody() ?? '');

        $ticket = $this->entityManager->getRepository(Ticket::class)->find($ticket->getId());
        self::assertNotNull($ticket);
        self::assertSame(TicketStatus::RESOLVED, $ticket->getStatus());
        self::assertSame('Vi uppdaterade skrivardrivrutinen och startade om spoolern. Allt fungerar igen.', $ticket->getResolutionSummary());

        $log = $this->entityManager->getRepository(NotificationLog::class)->findOneBy(['eventType' => 'customer_ticket_resolved']);
        self::assertNotNull($log);
        self::assertTrue($log->isSent());

        self::ensureKernelShutdown();
        $customerClient = static::createClient();
        $customerClient->loginUser($customer);
        $customerClient->request('GET', sprintf('/portal/customer/tickets/%d', $ticket->getId()));
        self::assertResponseIsSuccessful();
        $html = $customerClient->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Lösning', $html);
        self::assertStringContainsString('Vi uppdaterade skrivardrivrutinen och startade om spoolern. Allt fungerar igen.', $html);
    }

    public function testInternalTechnicianCommentIsHiddenFromCustomerAndSendsNoEmail(): void
    {
        [, $technician, $customer, $ticket] = $this->createTicketFixture();

        $this->client->loginUser($technician);
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Internt: vi misstänker fel i upstream-konfigurationen.',
            'internal' => '1',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
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
        $customerCrawler = $customerClient->request('GET', sprintf('/portal/customer/tickets/%d', $ticket->getId()));
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
            'headers' => ['Ämne', 'Beskrivning', 'Referens', 'Avsändare', 'E-post', 'Status', 'Prioritet', 'Åtgärd', 'Händelse', 'Kommentar', 'Datum', 'Aktör'],
            'rows' => [
                [
                    'Ämne' => 'Accesskort fungerar inte',
                    'Beskrivning' => 'Kundens accesskort öppnar inte ytterdörren längre.',
                    'Referens' => 'CSV-88',
                    'Avsändare' => 'Cecilia Kund',
                    'E-post' => 'kund@csv.test',
                    'Status' => 'Öppen',
                    'Prioritet' => 'Hög',
                    'Åtgärd' => 'Kortläsaren startades om och lokal cache rensades.',
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
                    'Åtgärd' => '',
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
                'resolution_body' => 'Åtgärd',
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
        self::assertSame('Kortläsaren startades om och lokal cache rensades.', $ticket->getResolutionSummary());

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

    public function testTechnicianTicketDetailShowsSlaTimeline(): void
    {
        [, $technician, , $ticket] = $this->createTicketFixture();

        $slaPolicy = new SlaPolicy('Prioriterad 2/8', 2, 8);
        $ticket->setSlaPolicy($slaPolicy);

        $this->entityManager->persist($slaPolicy);
        $this->entityManager->flush();

        $this->backdateTicket($ticket, '-5 hours');

        /** @var Ticket $ticket */
        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['reference' => 'DP-2001']);
        self::assertNotNull($ticket);

        $this->client->loginUser($technician);
        $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
        self::assertResponseIsSuccessful();

        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('SLA-tidslinje', $html);
        self::assertStringContainsString('Prioriterad 2/8', $html);
        self::assertStringContainsString('Första svar försenat', $html);
        self::assertStringContainsString('Första svar senast', $html);
        self::assertStringContainsString('Lösning senast', $html);
    }

    public function testTechnicianTicketDetailShowsWorkflowTimeline(): void
    {
        [, $technician, $customer, $ticket] = $this->createTicketFixture();

        $createdLog = new TicketAuditLog($ticket, 'ticket_created', 'Ticket skapad med status Öppen.', $technician);
        $statusLog = new TicketAuditLog($ticket, 'ticket_updated', 'status Ny -> Väntar på kund, tilldelning ingen -> Tina Tekniker', $technician);
        $customerLog = new TicketAuditLog($ticket, 'customer_comment_added', 'Kunden har skickat ett nytt svar.', $customer);

        $ticket->addAuditLog($createdLog);
        $ticket->addAuditLog($statusLog);
        $ticket->addAuditLog($customerLog);
        $this->entityManager->persist($createdLog);
        $this->entityManager->persist($statusLog);
        $this->entityManager->persist($customerLog);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
        self::assertResponseIsSuccessful();

        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Arbetsflöde', $html);
        self::assertStringContainsString('Status ändrades', $html);
        self::assertStringContainsString('Från ny till väntar på kund.', $html);
        self::assertStringContainsString('Kund uppdaterade ärendet', $html);
        self::assertStringContainsString('Kalle Kund', $html);
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
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
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

        self::assertResponseRedirects('/portal/technician/arenden');
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
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
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

        self::assertResponseRedirects('/portal/technician/arenden');

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
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
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

        self::assertResponseRedirects('/portal/technician/arenden');

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
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
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

        self::assertResponseRedirects('/portal/technician/arenden');

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
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
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

        self::assertResponseRedirects('/portal/technician/arenden');

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
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
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

        self::assertResponseRedirects('/portal/technician/arenden');

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
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
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

        self::assertResponseRedirects('/portal/technician/arenden');

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
        $crawler = $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('0/2 klara', (string) $crawler->html());

        $form = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d/checklist"]', $ticket->getId()))->form([
            'checklist_items' => ['Bekräfta påverkan'],
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
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

        $crawler = $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
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
        self::assertStringContainsString('DP-7011 · Kundöversikt', $html);
        self::assertStringContainsString('DP-7012 · Äldre väntande ärende', $html);
        self::assertStringContainsString('Nytt idag', $html);
        self::assertStringContainsString('Väntat länge', $html);
        self::assertStringContainsString('Bra att ta nu medan dialogen är färsk.', $html);
        self::assertStringContainsString('Det här ärendet har väntat en längre stund på din återkoppling.', $html);
        self::assertStringContainsString('Svara till tekniker', $html);
        self::assertTrue(strpos($html, 'DP-7012 · Äldre väntande ärende') < strpos($html, 'DP-7011 · Kundöversikt'));
        self::assertStringNotContainsString('Mallplaybook', $html);
        self::assertStringNotContainsString('Bekräfta påverkan', $html);
        self::assertStringNotContainsString('Verifiera tjänst', $html);

        $detailTicket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['reference' => 'DP-7011']);
        self::assertNotNull($detailTicket);

        $detailCrawler = $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $detailTicket->getId()));
        self::assertResponseIsSuccessful();
        $detailHtml = (string) $detailCrawler->html();

        self::assertStringContainsString('Arbetsstatus', $detailHtml);
        self::assertStringContainsString('1/2 steg klara', $detailHtml);
        self::assertStringContainsString('Ärendeaktivitet', $detailHtml);
        self::assertStringContainsString('Ärendet skapades.', $detailHtml);
        self::assertStringContainsString('Tekniker svarade i ärendet.', $detailHtml);
        self::assertStringContainsString('Status ändrades till väntar på kund.', $detailHtml);
        self::assertStringContainsString('Vi behöver att du bekräftar om felet kvarstår efter senaste testet.', $detailHtml);
        self::assertStringContainsString('Skicka uppdatering', $detailHtml);
        self::assertStringNotContainsString('Mallplaybook', $detailHtml);
        self::assertStringNotContainsString('Bekräfta påverkan', $detailHtml);
        self::assertStringNotContainsString('Verifiera tjänst', $detailHtml);
        self::assertStringNotContainsString('Spara checklista', $detailHtml);
        self::assertStringNotContainsString('Tilldelad:', $detailHtml);
        self::assertStringNotContainsString('Team:', $detailHtml);
        self::assertStringNotContainsString('SLA:', $detailHtml);

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
        $crawler = $this->client->request('GET', '/portal/technician/tickets/ny');
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

        self::assertResponseRedirects('/portal/technician/arenden');

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
        $crawler = $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
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

        self::assertResponseRedirects(sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
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
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine&status=open&sort=reference_asc&page=2');
        self::assertResponseIsSuccessful();

        $html = $crawler->html();
        self::assertIsString($html);
        self::assertStringContainsString('Mina ärenden', $html);
        self::assertStringContainsString('DP-3011', $html);
        self::assertStringContainsString('DP-3012', $html);
        self::assertStringNotContainsString('DP-3001', $html);
        self::assertStringNotContainsString('DP-3999', $html);

        $priorityCrawler = $this->client->request('GET', '/portal/technician/arenden?priority=critical&sort=priority_desc');
        self::assertResponseIsSuccessful();
        $priorityHtml = $priorityCrawler->html();
        self::assertIsString($priorityHtml);
        self::assertStringContainsString('Kritisk', $priorityHtml);
        self::assertStringContainsString('DP-3012', $priorityHtml);
        self::assertStringNotContainsString('DP-3011', $priorityHtml);

        $requestTypeCrawler = $this->client->request('GET', '/portal/technician/arenden?request_type=incident&impact=critical_service');
        self::assertResponseIsSuccessful();
        $requestTypeHtml = $requestTypeCrawler->html();
        self::assertIsString($requestTypeHtml);
        self::assertStringContainsString('DP-3012', $requestTypeHtml);
        self::assertStringNotContainsString('DP-3011', $requestTypeHtml);

        $escalationCrawler = $this->client->request('GET', '/portal/technician/arenden?escalation=incident&sort=escalation_desc');
        self::assertResponseIsSuccessful();

        $escalationHtml = $escalationCrawler->html();
        self::assertIsString($escalationHtml);
        self::assertStringContainsString('Incident', $escalationHtml);
        self::assertStringContainsString('DP-3012', $escalationHtml);
        self::assertStringNotContainsString('DP-3011', $escalationHtml);

        $teamCrawler = $this->client->request('GET', '/portal/technician/arenden?team='.$opsTeam->getId().'&scope=my_team');
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

    public function testTechnicianOverviewCanSortByAttentionAndExplainWhy(): void
    {
        $company = new Company('Attention AB');
        $technician = new User('attention-tech@example.test', 'Tove', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'AttentionPassword123'));
        $technician->enableMfa();

        $customer = new User('attention-customer@example.test', 'Karin', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $slaPolicy = (new SlaPolicy('Snabb SLA', 1, 8))
            ->setFirstResponseWarningHours(1)
            ->setResolutionWarningHours(2);

        $breachedTicket = (new Ticket(
            'DP-4101',
            'Kritisk driftstörning',
            'Ska få högst uppmärksamhet på grund av bruten SLA.',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::CRITICAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::CRITICAL_SERVICE,
            TicketEscalationLevel::INCIDENT,
        ))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setSlaPolicy($slaPolicy);

        $customerReplyTicket = (new Ticket(
            'DP-4102',
            'Behöver svar från tekniker',
            'Kunden har svarat senast och väntar på återkoppling.',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::HIGH,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::TEAM,
            TicketEscalationLevel::TEAM,
        ))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician);
        $customerReplyComment = new TicketComment($customerReplyTicket, $customer, 'Hej, problemet kvarstår fortfarande.');
        $customerReplyTicket->addComment($customerReplyComment);

        $unassignedTicket = (new Ticket(
            'DP-4103',
            'Ej tilldelad ticket',
            'Det här ärendet saknar ansvarig tekniker.',
            TicketStatus::NEW,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::SERVICE_REQUEST,
            TicketImpactLevel::SINGLE_USER,
            TicketEscalationLevel::NONE,
        ))
            ->setCompany($company)
            ->setRequester($customer);

        $this->entityManager->persist($company);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($slaPolicy);
        $this->entityManager->persist($breachedTicket);
        $this->entityManager->persist($customerReplyTicket);
        $this->entityManager->persist($customerReplyComment);
        $this->entityManager->persist($unassignedTicket);
        $this->entityManager->flush();

        $this->backdateTicket($breachedTicket, '-3 days');

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?sort=attention_desc');
        self::assertResponseIsSuccessful();

        $html = $crawler->html();
        self::assertIsString($html);
        self::assertStringContainsString('Kräver uppmärksamhet först', $html);
        self::assertStringContainsString('SLA bruten', $html);
        self::assertStringContainsString('Kunden väntar', $html);
        self::assertStringContainsString('Ej tilldelad', $html);

        $breachedPosition = strpos($html, 'DP-4101');
        $customerReplyPosition = strpos($html, 'DP-4102');
        $unassignedPosition = strpos($html, 'DP-4103');

        self::assertNotFalse($breachedPosition);
        self::assertNotFalse($customerReplyPosition);
        self::assertNotFalse($unassignedPosition);
        self::assertLessThan($customerReplyPosition, $breachedPosition);
        self::assertLessThan($unassignedPosition, $customerReplyPosition);
    }

    public function testTechnicianCanBulkTakeOverTickets(): void
    {
        $company = new Company('Bulk AB');
        $team = new TechnicianTeam('Drift');
        $technician = new User('bulk-tech@example.test', 'Bo', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkPassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $otherTechnician = new User('bulk-other@example.test', 'Britt', 'Other', UserType::TECHNICIAN);
        $otherTechnician->setPassword($this->passwordHasher->hashPassword($otherTechnician, 'OtherPassword123'));
        $otherTechnician->enableMfa();

        $customer = new User('bulk-customer@example.test', 'Kim', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $unassignedTicket = (new Ticket('DP-7101', 'Ej tilldelad', 'Ska tas over i bulk.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer);
        $assignedTicket = (new Ticket('DP-7102', 'Annan tekniker', 'Ska ocksa tas over i bulk.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($otherTechnician);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($otherTechnician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($unassignedTicket);
        $this->entityManager->persist($assignedTicket);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'take_over',
            'bulk_status' => TicketStatus::NEW->value,
            'ticket_ids' => [(string) $unassignedTicket->getId(), (string) $assignedTicket->getId()],
        ]);

        self::assertResponseRedirects('/portal/technician/arenden');

        /** @var Ticket $unassignedTicket */
        $unassignedTicket = $this->entityManager->getRepository(Ticket::class)->find($unassignedTicket->getId());
        /** @var Ticket $assignedTicket */
        $assignedTicket = $this->entityManager->getRepository(Ticket::class)->find($assignedTicket->getId());
        self::assertSame($technician->getId(), $unassignedTicket->getAssignee()?->getId());
        self::assertSame($technician->getId(), $assignedTicket->getAssignee()?->getId());
        self::assertSame($team->getId(), $unassignedTicket->getAssignedTeam()?->getId());
        self::assertSame($team->getId(), $assignedTicket->getAssignedTeam()?->getId());
    }

    public function testTechnicianCanBulkChangeStatusForOwnTickets(): void
    {
        $company = new Company('Bulk Status AB');
        $technician = new User('bulk-status@example.test', 'Bella', 'Status', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkStatusPassword123'));
        $technician->enableMfa();

        $customer = new User('bulk-status-customer@example.test', 'Karl', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $ticketOne = (new Ticket('DP-7201', 'Forsta bulkstatus', 'Ska bli lost.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician);
        $ticketTwo = (new Ticket('DP-7202', 'Andra bulkstatus', 'Ska ocksa bli lost.', TicketStatus::PENDING_CUSTOMER, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician);

        $this->entityManager->persist($company);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Status-förhandsvisning', $html);
        self::assertStringContainsString('data-bulk-status-preview-main', $html);
        self::assertStringContainsString('data-bulk-status-preview-warning', $html);

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_status',
            'bulk_status' => TicketStatus::RESOLVED->value,
            'bulk_internal_note' => 'Löst i bulk efter gemensam verifiering.',
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);

        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');

        $ticketOneId = $ticketOne->getId();
        $ticketTwoId = $ticketTwo->getId();
        $this->entityManager->clear();

        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOneId);
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwoId);
        self::assertSame(TicketStatus::RESOLVED, $ticketOne->getStatus());
        self::assertSame(TicketStatus::RESOLVED, $ticketTwo->getStatus());

        $logs = $this->entityManager->getRepository(TicketAuditLog::class)->findBy(['action' => 'ticket_updated'], ['id' => 'DESC']);
        self::assertNotEmpty($logs);
        self::assertStringContainsString('status', $logs[0]->getMessage());
    }

    public function testTechnicianCanBulkChangePriorityAndEscalation(): void
    {
        $company = new Company('Bulk Triage AB');
        $team = new TechnicianTeam('Triage');
        $technician = new User('bulk-triage@example.test', 'Tina', 'Triage', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkTriagePassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('bulk-triage-customer@example.test', 'Klara', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $ticketOne = (new Ticket('DP-7251', 'Första triage', 'Ska bli kritisk.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team)
            ->setPriority(TicketPriority::NORMAL)
            ->setEscalationLevel(TicketEscalationLevel::NONE);
        $ticketTwo = (new Ticket('DP-7252', 'Andra triage', 'Ska också eskaleras.', TicketStatus::NEW, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team)
            ->setPriority(TicketPriority::LOW)
            ->setEscalationLevel(TicketEscalationLevel::TEAM);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Status-förhandsvisning', $html);
        self::assertStringContainsString('data-bulk-status-preview-main', $html);
        self::assertStringContainsString('data-bulk-status-preview-warning', $html);
        self::assertStringContainsString('data-bulk-status-preview-outcome', $html);

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_priority',
            'bulk_priority' => TicketPriority::CRITICAL->value,
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');

        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_escalation',
            'bulk_escalation_level' => TicketEscalationLevel::INCIDENT->value,
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');

        $this->entityManager->clear();
        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOne->getId());
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwo->getId());
        self::assertSame(TicketPriority::CRITICAL, $ticketOne->getPriority());
        self::assertSame(TicketPriority::CRITICAL, $ticketTwo->getPriority());
        self::assertSame(TicketEscalationLevel::INCIDENT, $ticketOne->getEscalationLevel());
        self::assertSame(TicketEscalationLevel::INCIDENT, $ticketTwo->getEscalationLevel());

        $logs = $this->entityManager->getRepository(TicketAuditLog::class)->findBy(['action' => 'ticket_updated'], ['id' => 'DESC']);
        self::assertNotEmpty($logs);
        self::assertStringContainsString('eskalering', $logs[0]->getMessage());
    }

    public function testTechnicianCanBulkChangeVisibilityAndRequestType(): void
    {
        $company = new Company('Bulk Klassning AB');
        $team = new TechnicianTeam('Klassning');
        $technician = new User('bulk-classify@example.test', 'Klara', 'Klassning', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkClassifyPassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('bulk-classify-customer@example.test', 'Kim', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $ticketOne = (new Ticket('DP-7253', 'Första klassning', 'Ska bli delad service request.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team)
            ->setRequestType(TicketRequestType::INCIDENT);
        $ticketTwo = (new Ticket('DP-7254', 'Andra klassning', 'Ska också byta typ och synlighet.', TicketStatus::NEW, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team)
            ->setRequestType(TicketRequestType::BILLING);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Kommentarsförhandsvisning', $html);
        self::assertStringContainsString('data-bulk-comment-preview-main', $html);
        self::assertStringContainsString('data-bulk-comment-preview-outcome', $html);

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_visibility',
            'bulk_visibility' => TicketVisibility::COMPANY_SHARED->value,
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');

        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_request_type',
            'bulk_request_type' => TicketRequestType::SERVICE_REQUEST->value,
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');

        $ticketOneId = $ticketOne->getId();
        $ticketTwoId = $ticketTwo->getId();
        $this->entityManager->clear();
        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOneId);
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwoId);
        self::assertSame(TicketVisibility::COMPANY_SHARED, $ticketOne->getVisibility());
        self::assertSame(TicketVisibility::COMPANY_SHARED, $ticketTwo->getVisibility());
        self::assertSame(TicketRequestType::SERVICE_REQUEST, $ticketOne->getRequestType());
        self::assertSame(TicketRequestType::SERVICE_REQUEST, $ticketTwo->getRequestType());

        $logs = $this->entityManager->getRepository(TicketAuditLog::class)->findBy(['action' => 'ticket_updated'], ['id' => 'DESC']);
        self::assertNotEmpty($logs);
        self::assertStringContainsString('ärendetyp', $logs[0]->getMessage());
    }

    public function testTechnicianCanBulkChangeImpactAndCategory(): void
    {
        $company = new Company('Bulk Routing AB');
        $team = new TechnicianTeam('Routing');
        $technician = new User('bulk-routing@example.test', 'Ruben', 'Routing', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkRoutingPassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('bulk-routing-customer@example.test', 'Kajsa', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $category = new TicketCategory('Nätverk');

        $ticketOne = (new Ticket('DP-7255', 'Första routing', 'Ska få ny påverkan och kategori.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team)
            ->setImpactLevel(TicketImpactLevel::SINGLE_USER);
        $ticketTwo = (new Ticket('DP-7256', 'Andra routing', 'Ska också rättklassas.', TicketStatus::NEW, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team)
            ->setImpactLevel(TicketImpactLevel::TEAM);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($category);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Kommentarsförhandsvisning', $html);
        self::assertStringContainsString('data-bulk-comment-preview-main', $html);
        self::assertStringContainsString('data-bulk-comment-preview-outcome', $html);
        self::assertStringContainsString('data-bulk-comment-preview-warning', $html);

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_impact',
            'bulk_impact_level' => TicketImpactLevel::CRITICAL_SERVICE->value,
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');

        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_category',
            'bulk_category_id' => (string) $category->getId(),
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');

        $ticketOneId = $ticketOne->getId();
        $ticketTwoId = $ticketTwo->getId();
        $this->entityManager->clear();
        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOneId);
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwoId);
        self::assertSame(TicketImpactLevel::CRITICAL_SERVICE, $ticketOne->getImpactLevel());
        self::assertSame(TicketImpactLevel::CRITICAL_SERVICE, $ticketTwo->getImpactLevel());
        self::assertSame($category->getName(), $ticketOne->getCategory()?->getName());
        self::assertSame($category->getName(), $ticketTwo->getCategory()?->getName());

        $logs = $this->entityManager->getRepository(TicketAuditLog::class)->findBy(['action' => 'ticket_updated'], ['id' => 'DESC']);
        self::assertNotEmpty($logs);
        self::assertStringContainsString('kategori', $logs[0]->getMessage());
    }

    public function testTechnicianCanBulkChangeSlaPolicy(): void
    {
        $company = new Company('Bulk SLA AB');
        $team = new TechnicianTeam('Operations');
        $technician = new User('bulk-sla@example.test', 'Stina', 'SLA', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkSlaPassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('bulk-sla-customer@example.test', 'Linnea', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $oldSlaPolicy = new SlaPolicy('Bas 8/24', 8, 24);
        $newSlaPolicy = new SlaPolicy('Snabb 2/8', 2, 8);

        $ticketOne = (new Ticket('DP-7260', 'Första SLA-bytet', 'Ska flyttas till snabbare SLA.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team)
            ->setSlaPolicy($oldSlaPolicy);
        $ticketTwo = (new Ticket('DP-7261', 'Andra SLA-bytet', 'Ska få samma policy.', TicketStatus::NEW, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team)
            ->setSlaPolicy($oldSlaPolicy);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($oldSlaPolicy);
        $this->entityManager->persist($newSlaPolicy);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_sla_policy',
            'bulk_sla_policy_id' => (string) $newSlaPolicy->getId(),
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');

        $ticketOneId = $ticketOne->getId();
        $ticketTwoId = $ticketTwo->getId();
        $newSlaPolicyId = $newSlaPolicy->getId();
        $this->entityManager->clear();
        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOneId);
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwoId);
        self::assertSame($newSlaPolicyId, $ticketOne->getSlaPolicy()?->getId());
        self::assertSame($newSlaPolicyId, $ticketTwo->getSlaPolicy()?->getId());

        $logs = $this->entityManager->getRepository(TicketAuditLog::class)->findBy(['action' => 'ticket_updated'], ['id' => 'DESC']);
        self::assertNotEmpty($logs);
        self::assertStringContainsString('sla', $logs[0]->getMessage());
    }

    public function testAdminCanBulkAssignTechnicianAndTeam(): void
    {
        $company = new Company('Bulk Admin AB');
        $admin = new User('bulk-admin@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'BulkAdminPassword123'));
        $admin->enableMfa();

        $assignee = new User('bulk-assignee@example.test', 'Ture', 'Tech', UserType::TECHNICIAN);
        $assignee->setPassword($this->passwordHasher->hashPassword($assignee, 'BulkAssignPassword123'));
        $assignee->enableMfa();

        $team = new TechnicianTeam('NOC');

        $customer = new User('bulk-admin-customer@example.test', 'Kerstin', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $ticketOne = (new Ticket('DP-7301', 'Admin bulk ett', 'Ska fa ansvarig och team.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer);
        $ticketTwo = (new Ticket('DP-7302', 'Admin bulk tva', 'Ska fa samma ansvarig och team.', TicketStatus::NEW, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer);

        $this->entityManager->persist($company);
        $this->entityManager->persist($admin);
        $this->entityManager->persist($assignee);
        $this->entityManager->persist($team);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/technician/arenden');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();

        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'assign_assignee',
            'bulk_assignee_id' => (string) $assignee->getId(),
            'bulk_team_id' => '',
            'bulk_status' => TicketStatus::NEW->value,
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden');

        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'assign_team',
            'bulk_assignee_id' => '',
            'bulk_team_id' => (string) $team->getId(),
            'bulk_status' => TicketStatus::NEW->value,
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden');

        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOne->getId());
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwo->getId());
        self::assertSame($assignee->getId(), $ticketOne->getAssignee()?->getId());
        self::assertSame($assignee->getId(), $ticketTwo->getAssignee()?->getId());
        self::assertSame($team->getId(), $ticketOne->getAssignedTeam()?->getId());
        self::assertSame($team->getId(), $ticketTwo->getAssignedTeam()?->getId());
    }

    public function testAdminCanBulkRerouteTicketsWithSlaDefaultTeam(): void
    {
        $company = new Company('Bulk Reroute AB');
        $admin = new User('bulk-reroute-admin@example.test', 'Alma', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'BulkReroutePassword123'));
        $admin->enableMfa();

        $oldTeam = new TechnicianTeam('Helpdesk');
        $targetTeam = new TechnicianTeam('NOC');

        $customer = new User('bulk-reroute-customer@example.test', 'Siv', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $oldSlaPolicy = new SlaPolicy('Standard 8/24', 8, 24);
        $newSlaPolicy = (new SlaPolicy('NOC 1/4', 1, 4))
            ->setDefaultTeamEnabled(true)
            ->setDefaultTeam($targetTeam);

        $ticketOne = (new Ticket('DP-7310', 'Reroute ett', 'Ska routas om till nytt team.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignedTeam($oldTeam)
            ->setSlaPolicy($oldSlaPolicy);
        $ticketTwo = (new Ticket('DP-7311', 'Reroute två', 'Ska få samma omrouting.', TicketStatus::NEW, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignedTeam($oldTeam)
            ->setSlaPolicy($oldSlaPolicy);

        $this->entityManager->persist($company);
        $this->entityManager->persist($admin);
        $this->entityManager->persist($oldTeam);
        $this->entityManager->persist($targetTeam);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($oldSlaPolicy);
        $this->entityManager->persist($newSlaPolicy);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/technician/arenden');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('SLA-förhandsvisning', $html);
        self::assertStringContainsString('data-bulk-sla-preview', $html);
        self::assertStringContainsString('data-bulk-sla-preview-team-source', $html);
        self::assertStringContainsString('data-bulk-sla-preview-action', $html);
        self::assertStringContainsString('data-manual-team-name', $html);
        self::assertStringContainsString('ingen standard', $html);

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'reroute_team_and_sla',
            'bulk_team_id' => '',
            'bulk_sla_policy_id' => (string) $newSlaPolicy->getId(),
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden');

        $ticketOneId = $ticketOne->getId();
        $ticketTwoId = $ticketTwo->getId();
        $targetTeamId = $targetTeam->getId();
        $newSlaPolicyId = $newSlaPolicy->getId();
        $this->entityManager->clear();
        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOneId);
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwoId);
        self::assertSame($targetTeamId, $ticketOne->getAssignedTeam()?->getId());
        self::assertSame($targetTeamId, $ticketTwo->getAssignedTeam()?->getId());
        self::assertSame($newSlaPolicyId, $ticketOne->getSlaPolicy()?->getId());
        self::assertSame($newSlaPolicyId, $ticketTwo->getSlaPolicy()?->getId());

        $logs = $this->entityManager->getRepository(TicketAuditLog::class)->findBy(['action' => 'ticket_updated'], ['id' => 'DESC']);
        self::assertNotEmpty($logs);
        self::assertStringContainsString('team', $logs[0]->getMessage());
        self::assertStringContainsString('sla', $logs[0]->getMessage());
    }

    public function testAdminCanBulkRerouteTicketsAndOptionallySyncSlaDefaults(): void
    {
        $company = new Company('Bulk Reroute Sync AB');
        $admin = new User('bulk-reroute-sync-admin@example.test', 'Aron', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'BulkRerouteSyncPassword123'));
        $admin->enableMfa();

        $oldTeam = new TechnicianTeam('Service Desk');
        $targetTeam = new TechnicianTeam('Operations');

        $customer = new User('bulk-reroute-sync-customer@example.test', 'Solveig', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $oldSlaPolicy = new SlaPolicy('Standard 8/24', 8, 24);
        $newSlaPolicy = (new SlaPolicy('Operations 1/4', 1, 4))
            ->setDefaultTeamEnabled(true)
            ->setDefaultTeam($targetTeam)
            ->setDefaultPriorityEnabled(true)
            ->setDefaultPriority(TicketPriority::HIGH)
            ->setDefaultEscalationEnabled(true)
            ->setDefaultEscalationLevel(TicketEscalationLevel::TEAM);

        $ticketOne = (new Ticket('DP-7312', 'Reroute sync av', 'Ska inte synka standarder först.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignedTeam($oldTeam)
            ->setSlaPolicy($oldSlaPolicy)
            ->setPriority(TicketPriority::LOW)
            ->setEscalationLevel(TicketEscalationLevel::NONE);
        $ticketTwo = (new Ticket('DP-7313', 'Reroute sync pa', 'Ska synka standarder nar rutan markeras.', TicketStatus::NEW, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignedTeam($oldTeam)
            ->setSlaPolicy($oldSlaPolicy)
            ->setPriority(TicketPriority::NORMAL)
            ->setEscalationLevel(TicketEscalationLevel::LEAD);

        $this->entityManager->persist($company);
        $this->entityManager->persist($admin);
        $this->entityManager->persist($oldTeam);
        $this->entityManager->persist($targetTeam);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($oldSlaPolicy);
        $this->entityManager->persist($newSlaPolicy);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/technician/arenden');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'reroute_team_and_sla',
            'bulk_team_id' => '',
            'bulk_sla_policy_id' => (string) $newSlaPolicy->getId(),
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketOne->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden');

        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'reroute_team_and_sla',
            'bulk_team_id' => '',
            'bulk_sla_policy_id' => (string) $newSlaPolicy->getId(),
            'bulk_reroute_sync_sla_defaults' => '1',
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketTwo->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden');

        $ticketOneId = $ticketOne->getId();
        $ticketTwoId = $ticketTwo->getId();
        $targetTeamId = $targetTeam->getId();
        $newSlaPolicyId = $newSlaPolicy->getId();
        $this->entityManager->clear();
        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOneId);
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwoId);

        self::assertSame($targetTeamId, $ticketOne->getAssignedTeam()?->getId());
        self::assertSame($newSlaPolicyId, $ticketOne->getSlaPolicy()?->getId());
        self::assertSame(TicketPriority::LOW, $ticketOne->getPriority());
        self::assertSame(TicketEscalationLevel::NONE, $ticketOne->getEscalationLevel());

        self::assertSame($targetTeamId, $ticketTwo->getAssignedTeam()?->getId());
        self::assertSame($newSlaPolicyId, $ticketTwo->getSlaPolicy()?->getId());
        self::assertSame(TicketPriority::HIGH, $ticketTwo->getPriority());
        self::assertSame(TicketEscalationLevel::TEAM, $ticketTwo->getEscalationLevel());
    }

    public function testAdminCanBulkRerouteTicketsAndOptionallySyncSlaAssignee(): void
    {
        $company = new Company('Bulk Reroute Assignee AB');
        $admin = new User('bulk-reroute-assignee-admin@example.test', 'Astrid', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'BulkRerouteAssigneePassword123'));
        $admin->enableMfa();

        $oldTechnician = new User('bulk-reroute-old-tech@example.test', 'Olle', 'Old', UserType::TECHNICIAN);
        $oldTechnician->setPassword($this->passwordHasher->hashPassword($oldTechnician, 'OldTechPassword123'));
        $oldTechnician->enableMfa();

        $targetTechnician = new User('bulk-reroute-target-tech@example.test', 'Tora', 'Target', UserType::TECHNICIAN);
        $targetTechnician->setPassword($this->passwordHasher->hashPassword($targetTechnician, 'TargetTechPassword123'));
        $targetTechnician->enableMfa();

        $oldTeam = new TechnicianTeam('Field');
        $targetTeam = new TechnicianTeam('Core');
        $oldTechnician->setTechnicianTeam($oldTeam);
        $targetTechnician->setTechnicianTeam($targetTeam);

        $customer = new User('bulk-reroute-assignee-customer@example.test', 'Signe', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $oldSlaPolicy = new SlaPolicy('Standard 8/24', 8, 24);
        $newSlaPolicy = (new SlaPolicy('Core 1/4', 1, 4))
            ->setDefaultTeamEnabled(true)
            ->setDefaultTeam($targetTeam)
            ->setDefaultAssigneeEnabled(true)
            ->setDefaultAssignee($targetTechnician);

        $ticketOne = (new Ticket('DP-7314', 'Reroute assignee av', 'Ska behalla gammal ansvarig utan checkbox.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignedTeam($oldTeam)
            ->setAssignee($oldTechnician)
            ->setSlaPolicy($oldSlaPolicy);
        $ticketTwo = (new Ticket('DP-7315', 'Reroute assignee pa', 'Ska fa SLA-standardansvarig med checkbox.', TicketStatus::NEW, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignedTeam($oldTeam)
            ->setAssignee($oldTechnician)
            ->setSlaPolicy($oldSlaPolicy);

        $this->entityManager->persist($company);
        $this->entityManager->persist($admin);
        $this->entityManager->persist($oldTechnician);
        $this->entityManager->persist($targetTechnician);
        $this->entityManager->persist($oldTeam);
        $this->entityManager->persist($targetTeam);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($oldSlaPolicy);
        $this->entityManager->persist($newSlaPolicy);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/technician/arenden');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'reroute_team_and_sla',
            'bulk_team_id' => '',
            'bulk_sla_policy_id' => (string) $newSlaPolicy->getId(),
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketOne->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden');

        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'reroute_team_and_sla',
            'bulk_team_id' => '',
            'bulk_sla_policy_id' => (string) $newSlaPolicy->getId(),
            'bulk_reroute_sync_sla_assignee' => '1',
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketTwo->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden');

        $ticketOneId = $ticketOne->getId();
        $ticketTwoId = $ticketTwo->getId();
        $oldTechnicianId = $oldTechnician->getId();
        $targetTechnicianId = $targetTechnician->getId();
        $this->entityManager->clear();
        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOneId);
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwoId);

        self::assertSame($oldTechnicianId, $ticketOne->getAssignee()?->getId());
        self::assertSame($targetTechnicianId, $ticketTwo->getAssignee()?->getId());

        $notifications = $this->entityManager->getRepository(NotificationLog::class)->findBy(['eventType' => 'ticket_assigned'], ['id' => 'DESC']);
        self::assertNotEmpty($notifications);
        self::assertSame($targetTechnicianId, $notifications[0]->getRecipient()?->getId());
    }

    public function testTechnicianCanSaveAndDeleteFilterPreset(): void
    {
        $technician = new User('preset-tech@example.test', 'Pia', 'Preset', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'PresetPassword123'));
        $technician->enableMfa();

        $this->entityManager->persist($technician);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        self::assertResponseIsSuccessful();

        $saveForm = $crawler->filter('form[action="/portal/technician/filter-presets/save"]')->form([
            'preset_label' => 'Min öppna högprio',
        ]);
        $this->client->submit($saveForm);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&priority=high&sort=attention_desc');

        $this->client->followRedirect();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Min öppna högprio', $html);

        $crawler = new \Symfony\Component\DomCrawler\Crawler($html, 'http://localhost/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        $deleteForm = $crawler->filter('form[action$="/delete"]')->form();
        $this->client->submit($deleteForm);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&priority=high&sort=attention_desc');

        $this->client->followRedirect();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringNotContainsString('Min öppna högprio', $html);
        self::assertStringContainsString('Inga sparade köer ännu.', $html);
    }

    public function testTechnicianCanClearAllFilterPresets(): void
    {
        $technician = new User('preset-clear-tech@example.test', 'Per', 'Preset', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'PresetPassword123'));
        $technician->enableMfa();

        $this->entityManager->persist($technician);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        self::assertResponseIsSuccessful();

        $saveForm = $crawler->filter('form[action="/portal/technician/filter-presets/save"]')->form([
            'preset_label' => 'Öppna högprio',
        ]);
        $this->client->submit($saveForm);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/portal/technician/arenden?status=new&sort=updated_desc');
        self::assertResponseIsSuccessful();

        $saveForm = $crawler->filter('form[action="/portal/technician/filter-presets/save"]')->form([
            'preset_label' => 'Mina senaste',
        ]);
        $this->client->submit($saveForm);
        self::assertResponseRedirects('/portal/technician/arenden?status=new&sort=updated_desc');

        $this->client->followRedirect();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Öppna högprio', $html);
        self::assertStringContainsString('Mina senaste', $html);
        self::assertStringContainsString('Rensa mina köer', $html);

        $crawler = new \Symfony\Component\DomCrawler\Crawler($html, 'http://localhost/portal/technician/arenden?status=new&sort=updated_desc');
        $clearForm = $crawler->filter('form[action="/portal/technician/filter-presets/clear"]')->eq(0)->form();
        $this->client->submit($clearForm);
        self::assertResponseRedirects('/portal/technician/arenden?status=new&sort=updated_desc');

        $this->client->followRedirect();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringNotContainsString('Öppna högprio', $html);
        self::assertStringNotContainsString('Mina senaste', $html);
        self::assertStringNotContainsString('Rensa mina köer', $html);
        self::assertStringContainsString('Inga sparade köer ännu.', $html);
    }

    public function testTechnicianCanShareTeamFilterPreset(): void
    {
        $team = new TechnicianTeam('Team Delning');
        $owner = new User('team-preset-owner@example.test', 'Tina', 'Owner', UserType::TECHNICIAN);
        $owner->setPassword($this->passwordHasher->hashPassword($owner, 'PresetPassword123'));
        $owner->enableMfa();
        $owner->setTechnicianTeam($team);

        $teammate = new User('team-preset-mate@example.test', 'Tom', 'Mate', UserType::TECHNICIAN);
        $teammate->setPassword($this->passwordHasher->hashPassword($teammate, 'PresetPassword123'));
        $teammate->enableMfa();
        $teammate->setTechnicianTeam($team);

        $this->entityManager->persist($team);
        $this->entityManager->persist($owner);
        $this->entityManager->persist($teammate);
        $this->entityManager->flush();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        self::assertResponseIsSuccessful();

        $saveForm = $crawler->filter('form[action="/portal/technician/filter-presets/save"]')->form();
        $this->client->request('POST', '/portal/technician/filter-presets/save', [
            '_token' => $saveForm->get('_token')->getValue(),
            'return_url' => '/portal/technician/arenden?status=open&priority=high&sort=attention_desc',
            'q' => $saveForm->get('q')->getValue(),
            'status' => $saveForm->get('status')->getValue(),
            'priority' => $saveForm->get('priority')->getValue(),
            'request_type' => $saveForm->get('request_type')->getValue(),
            'impact' => $saveForm->get('impact')->getValue(),
            'escalation' => $saveForm->get('escalation')->getValue(),
            'team' => $saveForm->get('team')->getValue(),
            'visibility' => $saveForm->get('visibility')->getValue(),
            'assignee' => $saveForm->get('assignee')->getValue(),
            'scope' => 'my_team',
            'sort' => $saveForm->get('sort')->getValue(),
            'page' => $saveForm->get('page')->getValue(),
            'preset_label' => 'Teamets högprio',
            'preset_description' => 'Används i morgonrundan för teamets öppna incidenter.',
            'preset_tag' => 'Jour',
            'preset_tone' => 'amber',
            'preset_scope' => 'team',
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&priority=high&sort=attention_desc');

        $this->client->followRedirect();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Teamköer för Team Delning', $html);
        self::assertStringContainsString('Teamets högprio', $html);
        self::assertStringContainsString('Används i morgonrundan för teamets öppna incidenter.', $html);
        self::assertStringContainsString('Jour', $html);
        self::assertStringContainsString('skapad av Tina Owner', $html);
        self::assertStringContainsString('>Rensa<', $html);

        $this->client->loginUser($teammate);
        $this->client->request('GET', '/portal/technician/arenden');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Teamköer för Team Delning', $html);
        self::assertStringContainsString('Teamets högprio', $html);
        self::assertStringContainsString('Används i morgonrundan för teamets öppna incidenter.', $html);
        self::assertStringContainsString('Jour', $html);
        self::assertStringContainsString('skapad av Tina Owner', $html);
        self::assertStringNotContainsString('Rensa teamköer', $html);
    }

    public function testAdminCanClearAllTeamFilterPresets(): void
    {
        $team = new TechnicianTeam('Admin Team');
        $owner = new User('team-preset-admin-owner@example.test', 'Tina', 'Owner', UserType::TECHNICIAN);
        $owner->setPassword($this->passwordHasher->hashPassword($owner, 'PresetPassword123'));
        $owner->enableMfa();
        $owner->setTechnicianTeam($team);

        $admin = new User('team-preset-admin@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'PresetPassword123'));
        $admin->enableMfa();
        $admin->setTechnicianTeam($team);

        $this->entityManager->persist($team);
        $this->entityManager->persist($owner);
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        self::assertResponseIsSuccessful();

        $saveForm = $crawler->filter('form[action="/portal/technician/filter-presets/save"]')->form();
        $this->client->request('POST', '/portal/technician/filter-presets/save', [
            '_token' => $saveForm->get('_token')->getValue(),
            'return_url' => '/portal/technician/arenden?status=open&priority=high&sort=attention_desc',
            'q' => $saveForm->get('q')->getValue(),
            'status' => $saveForm->get('status')->getValue(),
            'priority' => $saveForm->get('priority')->getValue(),
            'request_type' => $saveForm->get('request_type')->getValue(),
            'impact' => $saveForm->get('impact')->getValue(),
            'escalation' => $saveForm->get('escalation')->getValue(),
            'team' => $saveForm->get('team')->getValue(),
            'visibility' => $saveForm->get('visibility')->getValue(),
            'assignee' => $saveForm->get('assignee')->getValue(),
            'scope' => 'my_team',
            'sort' => $saveForm->get('sort')->getValue(),
            'page' => $saveForm->get('page')->getValue(),
            'preset_label' => 'Adminrensning',
            'preset_scope' => 'team',
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        $this->client->followRedirect();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/technician/arenden');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Adminrensning', $html);
        self::assertStringContainsString('Rensa teamköer', $html);

        $crawler = new \Symfony\Component\DomCrawler\Crawler($html, 'http://localhost/portal/technician/arenden');
        $clearForm = $crawler->filter('form[action="/portal/technician/filter-presets/clear"]')->eq(0)->form();
        $this->client->submit($clearForm);
        self::assertResponseRedirects('/portal/technician/arenden');

        $this->client->followRedirect();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringNotContainsString('Adminrensning', $html);
    }

    public function testTeamPresetOwnerCanTransferOwnership(): void
    {
        $team = new TechnicianTeam('Transfer Team');
        $owner = new User('team-preset-transfer-owner@example.test', 'Tina', 'Owner', UserType::TECHNICIAN);
        $owner->setPassword($this->passwordHasher->hashPassword($owner, 'PresetPassword123'));
        $owner->enableMfa();
        $owner->setTechnicianTeam($team);

        $recipient = new User('team-preset-transfer-recipient@example.test', 'Robin', 'Recipient', UserType::TECHNICIAN);
        $recipient->setPassword($this->passwordHasher->hashPassword($recipient, 'PresetPassword123'));
        $recipient->enableMfa();
        $recipient->setTechnicianTeam($team);

        $this->entityManager->persist($team);
        $this->entityManager->persist($owner);
        $this->entityManager->persist($recipient);
        $this->entityManager->flush();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        self::assertResponseIsSuccessful();

        $saveForm = $crawler->filter('form[action="/portal/technician/filter-presets/save"]')->form();
        $this->client->request('POST', '/portal/technician/filter-presets/save', [
            '_token' => $saveForm->get('_token')->getValue(),
            'return_url' => '/portal/technician/arenden?status=open&priority=high&sort=attention_desc',
            'q' => $saveForm->get('q')->getValue(),
            'status' => $saveForm->get('status')->getValue(),
            'priority' => $saveForm->get('priority')->getValue(),
            'request_type' => $saveForm->get('request_type')->getValue(),
            'impact' => $saveForm->get('impact')->getValue(),
            'escalation' => $saveForm->get('escalation')->getValue(),
            'team' => $saveForm->get('team')->getValue(),
            'visibility' => $saveForm->get('visibility')->getValue(),
            'assignee' => $saveForm->get('assignee')->getValue(),
            'scope' => 'my_team',
            'sort' => $saveForm->get('sort')->getValue(),
            'page' => $saveForm->get('page')->getValue(),
            'preset_label' => 'Ägarbyte',
            'preset_scope' => 'team',
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&priority=high&sort=attention_desc');

        $this->client->followRedirect();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('skapad av Tina Owner', $html);
        self::assertStringContainsString('Byt ägare', $html);

        $crawler = new \Symfony\Component\DomCrawler\Crawler($html, 'http://localhost/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        $transferForm = $crawler->filter('form[action*="/transfer-owner"]')->eq(0)->form([
            'new_owner_id' => (string) $recipient->getId(),
        ]);
        $this->client->submit($transferForm);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&priority=high&sort=attention_desc');

        $this->client->followRedirect();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('skapad av Robin Recipient', $html);

        $this->client->loginUser($owner);
        $this->client->request('GET', '/portal/technician/arenden');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('skapad av Robin Recipient', $html);
        self::assertStringNotContainsString('Byt ägare', $html);

        $this->client->loginUser($recipient);
        $this->client->request('GET', '/portal/technician/arenden');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('skapad av Robin Recipient', $html);
        self::assertStringContainsString('Byt ägare', $html);
    }

    public function testTeamPresetOwnerCanFavoritePreset(): void
    {
        $team = new TechnicianTeam('Favorit Team');
        $owner = new User('team-preset-favorite-owner@example.test', 'Fanny', 'Favorit', UserType::TECHNICIAN);
        $owner->setPassword($this->passwordHasher->hashPassword($owner, 'PresetPassword123'));
        $owner->enableMfa();
        $owner->setTechnicianTeam($team);

        $this->entityManager->persist($team);
        $this->entityManager->persist($owner);
        $this->entityManager->flush();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        self::assertResponseIsSuccessful();

        $saveForm = $crawler->filter('form[action="/portal/technician/filter-presets/save"]')->form();

        $this->client->request('POST', '/portal/technician/filter-presets/save', [
            '_token' => $saveForm->get('_token')->getValue(),
            'return_url' => '/portal/technician/arenden?status=open&priority=high&sort=attention_desc',
            'q' => $saveForm->get('q')->getValue(),
            'status' => $saveForm->get('status')->getValue(),
            'priority' => $saveForm->get('priority')->getValue(),
            'request_type' => $saveForm->get('request_type')->getValue(),
            'impact' => $saveForm->get('impact')->getValue(),
            'escalation' => $saveForm->get('escalation')->getValue(),
            'team' => $saveForm->get('team')->getValue(),
            'visibility' => $saveForm->get('visibility')->getValue(),
            'assignee' => $saveForm->get('assignee')->getValue(),
            'scope' => 'my_team',
            'sort' => $saveForm->get('sort')->getValue(),
            'page' => $saveForm->get('page')->getValue(),
            'preset_label' => 'Zulu kö',
            'preset_scope' => 'team',
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        self::assertResponseIsSuccessful();
        $saveForm = $crawler->filter('form[action="/portal/technician/filter-presets/save"]')->form();

        $this->client->request('POST', '/portal/technician/filter-presets/save', [
            '_token' => $saveForm->get('_token')->getValue(),
            'return_url' => '/portal/technician/arenden?status=open&priority=high&sort=attention_desc',
            'q' => $saveForm->get('q')->getValue(),
            'status' => $saveForm->get('status')->getValue(),
            'priority' => $saveForm->get('priority')->getValue(),
            'request_type' => $saveForm->get('request_type')->getValue(),
            'impact' => $saveForm->get('impact')->getValue(),
            'escalation' => $saveForm->get('escalation')->getValue(),
            'team' => $saveForm->get('team')->getValue(),
            'visibility' => $saveForm->get('visibility')->getValue(),
            'assignee' => $saveForm->get('assignee')->getValue(),
            'scope' => 'my_team',
            'sort' => $saveForm->get('sort')->getValue(),
            'page' => $saveForm->get('page')->getValue(),
            'preset_label' => 'Alpha kö',
            'preset_scope' => 'team',
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&priority=high&sort=attention_desc');

        $this->client->followRedirect();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertLessThan(
            strpos($html, 'Zulu kö'),
            strpos($html, 'Alpha kö'),
            'Alphabetisk ordning ska gälla innan favoritmarkering.',
        );

        $crawler = new \Symfony\Component\DomCrawler\Crawler($html, 'http://localhost/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        $favoriteForm = $crawler->filter('form[action*="/toggle-favorite"]')->eq(1)->form();
        $this->client->submit($favoriteForm);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&priority=high&sort=attention_desc');

        $this->client->followRedirect();
        $this->client->request('GET', '/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Favorit', $html);
        self::assertStringContainsString('Ta bort favorit', $html);
        self::assertLessThan(
            strpos($html, 'Alpha kö'),
            strpos($html, 'Zulu kö'),
            'Favoritkön ska ligga överst efter markering.',
        );

        $crawler = new \Symfony\Component\DomCrawler\Crawler($html, 'http://localhost/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        $favoriteForm = $crawler->filter('form[action*="/toggle-favorite"]')->eq(1)->form();
        $this->client->submit($favoriteForm);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        $favoriteForm = (new \Symfony\Component\DomCrawler\Crawler($html, 'http://localhost/portal/technician/arenden?status=open&priority=high&sort=attention_desc'))
            ->filter('form[action*="/toggle-favorite"]')
            ->eq(1)
            ->form();
        $this->client->submit($favoriteForm);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&priority=high&sort=attention_desc');

        $this->client->followRedirect();
        $this->client->request('GET', '/portal/technician/arenden?status=open&priority=high&sort=attention_desc');
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertLessThan(
            strpos($html, 'Alpha kö'),
            strpos($html, 'Zulu kö'),
            'Favoritkö ska fortsatt ligga före icke-favoriter efter ommarkering.',
        );
    }

    public function testTeamPresetOwnerCanReorderFavoritePresets(): void
    {
        $team = new TechnicianTeam('Favoritordning Team');
        $owner = new User('team-preset-order-owner@example.test', 'Frida', 'Ordning', UserType::TECHNICIAN);
        $owner->setPassword($this->passwordHasher->hashPassword($owner, 'PresetPassword123'));
        $owner->enableMfa();
        $owner->setTechnicianTeam($team);

        $this->entityManager->persist($team);
        $this->entityManager->persist($owner);
        $this->entityManager->flush();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?status=open&sort=attention_desc');
        self::assertResponseIsSuccessful();
        $saveForm = $crawler->filter('form[action="/portal/technician/filter-presets/save"]')->form();

        foreach (['Alpha kö', 'Zulu kö'] as $label) {
            $this->client->request('POST', '/portal/technician/filter-presets/save', [
                '_token' => $saveForm->get('_token')->getValue(),
                'return_url' => '/portal/technician/arenden?status=open&sort=attention_desc',
                'q' => $saveForm->get('q')->getValue(),
                'status' => $saveForm->get('status')->getValue(),
                'priority' => $saveForm->get('priority')->getValue(),
                'request_type' => $saveForm->get('request_type')->getValue(),
                'impact' => $saveForm->get('impact')->getValue(),
                'escalation' => $saveForm->get('escalation')->getValue(),
                'team' => $saveForm->get('team')->getValue(),
                'visibility' => $saveForm->get('visibility')->getValue(),
                'assignee' => $saveForm->get('assignee')->getValue(),
                'scope' => 'my_team',
                'sort' => $saveForm->get('sort')->getValue(),
                'page' => $saveForm->get('page')->getValue(),
                'preset_label' => $label,
                'preset_scope' => 'team',
            ]);
            self::assertResponseRedirects('/portal/technician/arenden?status=open&sort=attention_desc');
            $this->client->followRedirect();
            $crawler = $this->client->request('GET', '/portal/technician/arenden?status=open&sort=attention_desc');
            self::assertResponseIsSuccessful();
        }

        $html = $this->client->getResponse()->getContent() ?? '';
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html, 'http://localhost/portal/technician/arenden?status=open&sort=attention_desc');
        $favoriteForms = $crawler->filter('form[action*="/toggle-favorite"]');
        $this->client->submit($favoriteForms->eq(0)->form());
        self::assertResponseRedirects('/portal/technician/arenden?status=open&sort=attention_desc');
        $this->client->followRedirect();

        $html = $this->client->getResponse()->getContent() ?? '';
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html, 'http://localhost/portal/technician/arenden?status=open&sort=attention_desc');
        $favoriteForms = $crawler->filter('form[action*="/toggle-favorite"]');
        $this->client->submit($favoriteForms->eq(1)->form());
        self::assertResponseRedirects('/portal/technician/arenden?status=open&sort=attention_desc');

        $this->client->followRedirect();
        $this->client->request('GET', '/portal/technician/arenden?status=open&sort=attention_desc');
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertIsString($html);
        $teamSection = explode('Snabbköer', strstr($html, 'Teamköer för Favoritordning Team') ?: $html)[0];
        self::assertLessThan(
            strpos($teamSection, 'Zulu kö'),
            strpos($teamSection, 'Alpha kö'),
            'Senast favoritmarkerade kö visas först innan manuell omordning.',
        );

        $crawler = new \Symfony\Component\DomCrawler\Crawler($html, 'http://localhost/portal/technician/arenden?status=open&sort=attention_desc');
        $moveUpForm = $crawler->filter('form[action*="/move-favorite"]')->eq(2)->form();
        $this->client->submit($moveUpForm);
        self::assertResponseRedirects('/portal/technician/arenden?status=open&sort=attention_desc');

        $this->client->followRedirect();
        $this->client->request('GET', '/portal/technician/arenden?status=open&sort=attention_desc');
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertIsString($html);
        $teamSection = explode('Snabbköer', strstr($html, 'Teamköer för Favoritordning Team') ?: $html)[0];
        self::assertLessThan(
            strpos($teamSection, 'Alpha kö'),
            strpos($teamSection, 'Zulu kö'),
            'Den flyttade favoriten ska kunna hamna först.',
        );
    }

    public function testTechnicianCanBulkAddInternalNote(): void
    {
        $company = new Company('Bulk Note AB');
        $team = new TechnicianTeam('Ops');
        $technician = new User('bulk-note@example.test', 'Boris', 'Note', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkNotePassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('bulk-note-customer@example.test', 'Klara', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $ticketOne = (new Ticket('DP-7401', 'Bulknote ett', 'Ska få intern anteckning.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team);
        $ticketTwo = (new Ticket('DP-7402', 'Bulknote två', 'Ska också få intern anteckning.', TicketStatus::PENDING_CUSTOMER, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'add_internal_note',
            'bulk_internal_note' => 'Samordnas med kvällspasset innan nästa uppdatering.',
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);

        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');

        $this->entityManager->clear();

        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOne->getId());
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwo->getId());
        self::assertCount(1, $ticketOne->getComments());
        self::assertCount(1, $ticketTwo->getComments());
        self::assertTrue($ticketOne->getComments()->first()->isInternal());
        self::assertTrue($ticketTwo->getComments()->first()->isInternal());
        self::assertSame('Samordnas med kvällspasset innan nästa uppdatering.', $ticketOne->getComments()->first()->getBody());
        self::assertSame('Samordnas med kvällspasset innan nästa uppdatering.', $ticketTwo->getComments()->first()->getBody());
        self::assertSame(TicketStatus::OPEN, $ticketOne->getStatus());
        self::assertSame(TicketStatus::PENDING_CUSTOMER, $ticketTwo->getStatus());

        $logs = $this->entityManager->getRepository(TicketAuditLog::class)->findBy(['action' => 'internal_comment_added'], ['id' => 'DESC']);
        self::assertGreaterThanOrEqual(2, \count($logs));
        self::assertStringContainsString('Intern kommentar tillagd via bulkåtgärd.', $logs[0]->getMessage());
    }

    public function testTechnicianCanBulkSendCustomerReply(): void
    {
        $company = new Company('Bulk Reply AB');
        $team = new TechnicianTeam('Support');
        $technician = new User('bulk-reply@example.test', 'Bella', 'Reply', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkReplyPassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customerOne = new User('bulk-reply-one@example.test', 'Klara', 'Kund', UserType::CUSTOMER);
        $customerOne->setPassword($this->passwordHasher->hashPassword($customerOne, 'CustomerPassword123'));
        $customerOne->setCompany($company);

        $customerTwo = new User('bulk-reply-two@example.test', 'Kalle', 'Kund', UserType::CUSTOMER);
        $customerTwo->setPassword($this->passwordHasher->hashPassword($customerTwo, 'CustomerPassword123'));
        $customerTwo->setCompany($company);

        $ticketOne = (new Ticket('DP-7501', 'Bulksvar ett', 'Ska få kunduppdatering.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customerOne)
            ->setAssignee($technician)
            ->setAssignedTeam($team);
        $ticketTwo = (new Ticket('DP-7502', 'Bulksvar två', 'Ska också få kunduppdatering.', TicketStatus::NEW, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customerTwo)
            ->setAssignee($technician)
            ->setAssignedTeam($team);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customerOne);
        $this->entityManager->persist($customerTwo);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Kommentarsförhandsvisning', $html);
        self::assertStringContainsString('data-bulk-comment-preview-warning', $html);

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'add_customer_reply',
            'bulk_customer_reply' => 'Vi har felsökt klart och behöver att ni bekräftar resultatet av senaste testet.',
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);

        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');

        $this->entityManager->clear();
        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOne->getId());
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwo->getId());
        self::assertCount(1, $ticketOne->getComments());
        self::assertCount(1, $ticketTwo->getComments());
        self::assertFalse($ticketOne->getComments()->first()->isInternal());
        self::assertFalse($ticketTwo->getComments()->first()->isInternal());
        self::assertSame(TicketStatus::PENDING_CUSTOMER, $ticketOne->getStatus());
        self::assertSame(TicketStatus::PENDING_CUSTOMER, $ticketTwo->getStatus());

        $logs = $this->entityManager->getRepository(NotificationLog::class)->findBy(['eventType' => 'customer_waiting_reply']);
        self::assertCount(2, $logs);
        self::assertTrue($logs[0]->isSent());

        $auditLogs = $this->entityManager->getRepository(TicketAuditLog::class)->findBy(['action' => 'customer_visible_comment_added'], ['id' => 'DESC']);
        self::assertGreaterThanOrEqual(2, \count($auditLogs));
        self::assertStringContainsString('Kundsynlig kommentar tillagd via bulkåtgärd.', $auditLogs[0]->getMessage());
    }

    public function testTechnicianCanBulkUpdateEntireFilteredSelection(): void
    {
        $company = new Company('Bulk Filter AB');
        $team = new TechnicianTeam('Filterteam');
        $technician = new User('bulk-filter@example.test', 'Filip', 'Filter', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkFilterPassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('bulk-filter-customer@example.test', 'Kira', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);
        $admin = new User('bulk-filter-admin@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'BulkAdminPassword123'));
        $admin->enableMfa();
        $admin->setTechnicianTeam($team);
        $adminAssignee = new User('bulk-filter-admin-assignee@example.test', 'Alex', 'Assign', UserType::TECHNICIAN);
        $adminAssignee->setPassword($this->passwordHasher->hashPassword($adminAssignee, 'BulkAssignPassword123'));
        $adminAssignee->enableMfa();
        $adminAssignee->setTechnicianTeam($team);
        $adminCategory = new TicketCategory('Bulkminne kategori');
        $adminSla = (new SlaPolicy('Bulkminne SLA', 2, 8))
            ->setDefaultTeamEnabled(true)
            ->setDefaultTeam($team)
            ->setDefaultAssigneeEnabled(true)
            ->setDefaultAssignee($adminAssignee);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($admin);
        $this->entityManager->persist($adminAssignee);
        $this->entityManager->persist($adminCategory);
        $this->entityManager->persist($adminSla);

        $openTickets = [];
        for ($index = 1; $index <= 11; ++$index) {
            $ticket = (new Ticket(sprintf('DP-77%02d', $index), sprintf('Filterticket %d', $index), 'Ska uppdateras via hela filtreringen.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
                ->setCompany($company)
                ->setRequester($customer)
                ->setAssignee($technician)
                ->setAssignedTeam($team);
            $this->entityManager->persist($ticket);
            $openTickets[] = $ticket;
        }

        $closedTicket = (new Ticket('DP-7799', 'Stängd filterticket', 'Ska inte träffas av open-filtret.', TicketStatus::CLOSED, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team);
        $this->entityManager->persist($closedTicket);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine&status=open');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Använd hela filtreringen (11 träffar)', $html);
        self::assertStringContainsString('data-bulk-selection-preview-text', $html);
        self::assertStringContainsString('Markera alla öppna på sidan', $html);
        self::assertStringContainsString('Markera alla väntar på kund', $html);
        self::assertStringContainsString('Markera alla högprio på sidan', $html);
        self::assertStringContainsString('Markera alla teameskalerade', $html);
        self::assertStringContainsString('Markera alla ej tilldelade', $html);
        self::assertStringContainsString('Markera alla i mitt team', $html);
        self::assertStringContainsString('data-ticket-status="open"', $html);
        self::assertStringContainsString('data-ticket-priority=', $html);
        self::assertStringContainsString('data-ticket-escalation=', $html);
        self::assertStringContainsString('data-ticket-assignee=', $html);
        self::assertStringContainsString('data-ticket-team=', $html);
        self::assertStringContainsString('data-current-team=', $html);
        self::assertStringContainsString('data-bulk-selection-preview-mode', $html);
        self::assertStringContainsString('data-bulk-selection-preview-action', $html);
        self::assertStringContainsString('Senaste urval: Manuella markeringar på sidan.', $html);
        self::assertStringContainsString('Bulkåtgärd: Ta över till mig för 0 markerade ärenden i manuella markeringar på sidan.', $html);
        self::assertStringContainsString('<option value="take_over" selected>', $html);
        self::assertStringContainsString('const affectedCount = selectFiltered?.checked ? totalFiltered : selectedCount;', $html);
        self::assertStringContainsString('const affectedLabel = selectFiltered?.checked', $html);
        self::assertStringContainsString('ändra status till ${selectedOptionText(bulkStatusSelect) || \'vald status\'}', $html);
        self::assertStringContainsString("actionLabel += ' och skicka kunduppdatering';", $html);
        self::assertStringContainsString("actionLabel += ' med intern notering';", $html);
        self::assertStringContainsString('ändra prioritet till ${selectedOptionText(bulkPrioritySelect) || \'vald prioritet\'}', $html);
        self::assertStringContainsString('Bulk körs just nu på markerade rader på den här sidan.', $html);
        self::assertStringContainsString('data-bulk-select-filtered', $html);
        self::assertStringContainsString('Markeringar på sidan används inte i det läget.', $html);
        self::assertMatchesRegularExpression('/type="checkbox"[^>]*name="bulk_remember_drafts"[^>]*checked/', $html);
        self::assertStringContainsString('Rensa utkast nu', $html);
        self::assertStringContainsString('Utkaststatus', $html);
        self::assertStringContainsString('Inga sparade utkast just nu. Formuläret är rent.', $html);
        self::assertStringContainsString('Utkastminne är påslaget för den här användaren.', $html);
        self::assertStringContainsString('const syncDraftStatus = () => {', $html);

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'add_internal_note',
            'bulk_selection_mode' => 'filtered',
            'bulk_internal_note' => 'Hela filtreringen uppdaterades i ett steg.',
            'bulk_remember_drafts' => '1',
            'bulk_status' => TicketStatus::OPEN->value,
            'bulk_use_filtered_selection' => '1',
            'selection_q' => '',
            'selection_status' => 'open',
            'selection_priority' => 'all',
            'selection_request_type' => 'all',
            'selection_impact' => 'all',
            'selection_escalation' => 'all',
            'selection_team' => 'all',
            'selection_visibility' => 'all',
            'selection_assignee' => 'all',
            'selection_scope' => 'mine',
            'selection_sort' => 'priority_desc',
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine&status=open');

        $this->client->request('GET', '/portal/technician/arenden?scope=mine&status=open');
        self::assertResponseIsSuccessful();
        $rememberedHtml = $this->client->getResponse()->getContent();
        self::assertIsString($rememberedHtml);
        self::assertStringContainsString('name="bulk_selection_mode" value="filtered"', $rememberedHtml);
        self::assertStringContainsString('data-bulk-initial-mode="filtered"', $rememberedHtml);
        self::assertStringContainsString('Senaste urval: Hela aktuella filtreringen.', $rememberedHtml);
        self::assertStringContainsString('Bulkåtgärd: Lägg intern anteckning för 11 ärenden i hela aktuella filtreringen.', $rememberedHtml);
        self::assertStringContainsString('<option value="add_internal_note" selected>', $rememberedHtml);
        self::assertStringContainsString('Bulk körs på hela aktuella filtreringen: 11 ärenden. Markeringar på sidan används inte i det läget.', $rememberedHtml);
        self::assertStringContainsString('>Hela filtreringen uppdaterades i ett steg.</textarea>', $rememberedHtml);
        self::assertStringContainsString('Utkast sparat: intern anteckning finns i formuläret just nu.', $rememberedHtml);

        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'add_internal_note',
            'bulk_selection_mode' => 'status:open',
            'bulk_internal_note' => 'Snabburval öppna på sidan.',
            'bulk_remember_drafts' => '1',
            'bulk_status' => TicketStatus::OPEN->value,
            'ticket_ids' => [(string) $openTickets[0]->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine&status=open');

        $this->client->request('GET', '/portal/technician/arenden?scope=mine&status=open');
        self::assertResponseIsSuccessful();
        $rememberedQuickHtml = $this->client->getResponse()->getContent();
        self::assertIsString($rememberedQuickHtml);
        self::assertStringContainsString('name="bulk_selection_mode" value="status:open"', $rememberedQuickHtml);
        self::assertStringContainsString('data-bulk-initial-mode="status:open"', $rememberedQuickHtml);
        self::assertStringContainsString('Senaste urval: Öppna ärenden på sidan.', $rememberedQuickHtml);
        self::assertStringContainsString('Bulkåtgärd: Lägg intern anteckning för', $rememberedQuickHtml);
        self::assertStringContainsString('<option value="add_internal_note" selected>', $rememberedQuickHtml);
        self::assertStringContainsString('markerade ärenden i öppna ärenden på sidan.', $rememberedQuickHtml);
        self::assertStringContainsString('>Snabburval öppna på sidan.</textarea>', $rememberedQuickHtml);

        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_status',
            'bulk_selection_mode' => 'manual',
            'bulk_status' => TicketStatus::RESOLVED->value,
            'bulk_internal_note' => 'Statusminne test.',
            'bulk_remember_drafts' => '1',
            'ticket_ids' => [(string) $openTickets[1]->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine&status=open');

        $this->client->request('GET', '/portal/technician/arenden?scope=mine&status=open');
        self::assertResponseIsSuccessful();
        $rememberedStatusHtml = $this->client->getResponse()->getContent();
        self::assertIsString($rememberedStatusHtml);
        self::assertStringContainsString('<option value="set_status" selected>', $rememberedStatusHtml);
        self::assertStringContainsString('<option value="resolved" selected>', $rememberedStatusHtml);
        self::assertStringContainsString('Bulkåtgärd: Ändra status till Löst för 0 markerade ärenden i manuella markeringar på sidan.', $rememberedStatusHtml);

        $this->client->loginUser($admin);
        $adminCrawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine&status=open');
        self::assertResponseIsSuccessful();
        $adminForm = $adminCrawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $adminForm->get('_token')->getValue(),
            'return_url' => $adminForm->get('return_url')->getValue(),
            'bulk_action' => 'assign_assignee',
            'bulk_assignee_id' => (string) $adminAssignee->getId(),
            'bulk_category_id' => (string) $adminCategory->getId(),
            'bulk_sla_policy_id' => (string) $adminSla->getId(),
            'bulk_status' => TicketStatus::OPEN->value,
            'bulk_remember_drafts' => '1',
            'ticket_ids' => [(string) $openTickets[2]->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine&status=open');

        $this->client->request('GET', '/portal/technician/arenden?scope=mine&status=open');
        self::assertResponseIsSuccessful();
        $rememberedAdminHtml = $this->client->getResponse()->getContent();
        self::assertIsString($rememberedAdminHtml);
        self::assertStringContainsString('<option value="assign_assignee" selected>', $rememberedAdminHtml);
        self::assertStringContainsString(sprintf('<option value="%d" selected>%s</option>', $adminAssignee->getId(), $adminAssignee->getDisplayName()), $rememberedAdminHtml);
        self::assertStringContainsString(sprintf('<option value="%d" selected>%s</option>', $adminCategory->getId(), $adminCategory->getName()), $rememberedAdminHtml);
        self::assertStringContainsString(sprintf('value="%d" selected', $adminSla->getId()), $rememberedAdminHtml);
        self::assertStringContainsString('Bulkåtgärd: Tilldela tekniker '.$adminAssignee->getDisplayName(), $rememberedAdminHtml);

        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $adminForm->get('_token')->getValue(),
            'return_url' => $adminForm->get('return_url')->getValue(),
            'bulk_action' => 'add_customer_reply',
            'bulk_customer_reply' => 'Utkastet ska ligga kvar efter omladdning.',
            'bulk_remember_drafts' => '1',
            'ticket_ids' => [(string) $openTickets[3]->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine&status=open');

        $this->client->request('GET', '/portal/technician/arenden?scope=mine&status=open');
        self::assertResponseIsSuccessful();
        $rememberedDraftHtml = $this->client->getResponse()->getContent();
        self::assertIsString($rememberedDraftHtml);
        self::assertStringContainsString('<option value="add_customer_reply" selected>', $rememberedDraftHtml);
        self::assertStringContainsString('>Utkastet ska ligga kvar efter omladdning.</textarea>', $rememberedDraftHtml);
        self::assertStringContainsString('name="bulk_remember_drafts"', $rememberedDraftHtml);
        self::assertStringContainsString('Utkast sparat: kunduppdatering finns i formuläret just nu.', $rememberedDraftHtml);

        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $adminForm->get('_token')->getValue(),
            'return_url' => $adminForm->get('return_url')->getValue(),
            'bulk_action' => 'add_customer_reply',
            'bulk_remember_drafts' => '0',
            'bulk_customer_reply' => 'Det här utkastet ska rensas direkt.',
            'ticket_ids' => [(string) $openTickets[4]->getId()],
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine&status=open');

        $this->client->request('GET', '/portal/technician/arenden?scope=mine&status=open');
        self::assertResponseIsSuccessful();
        $rememberedClearedDraftHtml = $this->client->getResponse()->getContent();
        self::assertIsString($rememberedClearedDraftHtml);
        self::assertStringContainsString('<option value="add_customer_reply" selected>', $rememberedClearedDraftHtml);
        self::assertStringContainsString('name="bulk_customer_reply"', $rememberedClearedDraftHtml);
        self::assertStringNotContainsString('>Det här utkastet ska rensas direkt.</textarea>', $rememberedClearedDraftHtml);
        self::assertStringContainsString('Inga sparade utkast just nu. Formuläret är rent.', $rememberedClearedDraftHtml);
        self::assertStringContainsString('Utkastminne är avstängt. Ny text sparas inte mellan omladdningar.', $rememberedClearedDraftHtml);

        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $adminForm->get('_token')->getValue(),
            'return_url' => $adminForm->get('return_url')->getValue(),
            'bulk_action' => 'add_internal_note',
            'bulk_internal_note' => 'Det här ska rensas via knappen.',
            'bulk_customer_reply' => 'Även kundutkastet ska bort.',
            'bulk_remember_drafts' => '1',
            'bulk_clear_drafts' => '1',
        ]);
        self::assertResponseRedirects('/portal/technician/arenden?scope=mine&status=open');

        $this->client->request('GET', '/portal/technician/arenden?scope=mine&status=open');
        self::assertResponseIsSuccessful();
        $clearedByButtonHtml = $this->client->getResponse()->getContent();
        self::assertIsString($clearedByButtonHtml);
        self::assertStringNotContainsString('>Det här ska rensas via knappen.</textarea>', $clearedByButtonHtml);
        self::assertStringNotContainsString('>Även kundutkastet ska bort.</textarea>', $clearedByButtonHtml);
        self::assertStringContainsString('Inga sparade utkast just nu. Formuläret är rent.', $clearedByButtonHtml);

        $this->client->loginUser($technician);
        $this->entityManager->clear();
        foreach ($openTickets as $index => $ticket) {
            /** @var Ticket $reloaded */
            $reloaded = $this->entityManager->getRepository(Ticket::class)->find($ticket->getId());
            self::assertCount($index < 2 || $index === 3 || $index === 4 ? 2 : 1, $reloaded->getComments());
        }

        /** @var Ticket $closedTicket */
        $closedTicket = $this->entityManager->getRepository(Ticket::class)->find($closedTicket->getId());
        self::assertCount(0, $closedTicket->getComments());
    }

    public function testBulkResolveRequiresClosingNoteOrCustomerUpdate(): void
    {
        $company = new Company('Bulk Resolve Guard AB');
        $team = new TechnicianTeam('Guard');
        $technician = new User('bulk-resolve-guard@example.test', 'Gina', 'Guard', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkGuardPassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('bulk-resolve-guard-customer@example.test', 'Kurt', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $ticket = (new Ticket('DP-7601', 'Guardad bulkstatus', 'Ska kräva slutnotering.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_status',
            'bulk_status' => TicketStatus::RESOLVED->value,
            'bulk_internal_note' => '',
            'bulk_customer_reply' => '',
            'ticket_ids' => [(string) $ticket->getId()],
        ]);

        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');
        $this->client->followRedirect();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('När flera ärenden löses eller stängs samtidigt behöver du skriva en intern slutnotering eller en kunduppdatering.', $html);

        /** @var Ticket $ticket */
        $ticket = $this->entityManager->getRepository(Ticket::class)->find($ticket->getId());
        self::assertSame(TicketStatus::OPEN, $ticket->getStatus());
        self::assertCount(0, $ticket->getComments());
    }

    public function testTechnicianCanBulkResolveWithCustomerUpdate(): void
    {
        $company = new Company('Bulk Resolve Update AB');
        $team = new TechnicianTeam('Resolve');
        $technician = new User('bulk-resolve-update@example.test', 'Rita', 'Resolve', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkResolvePassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customerOne = new User('bulk-resolve-update-one@example.test', 'Kim', 'Kund', UserType::CUSTOMER);
        $customerOne->setPassword($this->passwordHasher->hashPassword($customerOne, 'CustomerPassword123'));
        $customerOne->setCompany($company);

        $customerTwo = new User('bulk-resolve-update-two@example.test', 'Kia', 'Kund', UserType::CUSTOMER);
        $customerTwo->setPassword($this->passwordHasher->hashPassword($customerTwo, 'CustomerPassword123'));
        $customerTwo->setCompany($company);

        $ticketOne = (new Ticket('DP-7602', 'Bulkresolve ett', 'Ska lösas med kunduppdatering.', TicketStatus::OPEN, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customerOne)
            ->setAssignee($technician)
            ->setAssignedTeam($team);
        $ticketTwo = (new Ticket('DP-7603', 'Bulkresolve två', 'Ska också lösas med kunduppdatering.', TicketStatus::NEW, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customerTwo)
            ->setAssignee($technician)
            ->setAssignedTeam($team);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customerOne);
        $this->entityManager->persist($customerTwo);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $this->client->enableProfiler();
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Kommentarsförhandsvisning', $html);
        self::assertStringContainsString('data-bulk-comment-preview-main', $html);
        self::assertStringContainsString('data-bulk-comment-preview-outcome', $html);
        self::assertStringContainsString('data-bulk-comment-preview-warning', $html);

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_status',
            'bulk_status' => TicketStatus::RESOLVED->value,
            'bulk_internal_note' => '',
            'bulk_customer_reply' => 'Vi har nu avslutat arbetet och återkommer bara om något nytt skulle dyka upp.',
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);

        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');

        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOne->getId());
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwo->getId());
        self::assertSame(TicketStatus::RESOLVED, $ticketOne->getStatus());
        self::assertSame(TicketStatus::RESOLVED, $ticketTwo->getStatus());
        self::assertCount(1, $ticketOne->getComments());
        self::assertCount(1, $ticketTwo->getComments());
        self::assertFalse($ticketOne->getComments()->first()->isInternal());
        self::assertFalse($ticketTwo->getComments()->first()->isInternal());

        $notificationLogs = $this->entityManager->getRepository(NotificationLog::class)->findBy(['eventType' => 'customer_ticket_update']);
        self::assertCount(2, $notificationLogs);
        self::assertTrue($notificationLogs[0]->isSent());
        self::assertTrue($notificationLogs[1]->isSent());

        $auditLogs = $this->entityManager->getRepository(TicketAuditLog::class)->findBy(['action' => 'customer_visible_comment_added'], ['id' => 'DESC']);
        self::assertGreaterThanOrEqual(2, \count($auditLogs));
        self::assertStringContainsString('Kundsynlig kommentar tillagd via bulkåtgärd.', $auditLogs[0]->getMessage());
    }

    public function testBulkReopenRequiresInternalReason(): void
    {
        $company = new Company('Bulk Reopen Guard AB');
        $team = new TechnicianTeam('Reopen');
        $technician = new User('bulk-reopen-guard@example.test', 'Rebecka', 'Guard', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkReopenPassword123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customer = new User('bulk-reopen-guard-customer@example.test', 'Kerstin', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $ticket = (new Ticket('DP-7604', 'Återöppna bulk', 'Ska kräva intern orsak.', TicketStatus::RESOLVED, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customer)
            ->setAssignee($technician)
            ->setAssignedTeam($team);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_status',
            'bulk_status' => TicketStatus::OPEN->value,
            'bulk_internal_note' => '',
            'bulk_customer_reply' => '',
            'ticket_ids' => [(string) $ticket->getId()],
        ]);

        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');
        $this->client->followRedirect();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('När lösta eller stängda ärenden återöppnas i bulk behöver du skriva en intern orsak.', $html);

        $this->entityManager->clear();
        /** @var Ticket $ticket */
        $ticket = $this->entityManager->getRepository(Ticket::class)->find($ticket->getId());
        self::assertSame(TicketStatus::RESOLVED, $ticket->getStatus());
        self::assertCount(0, $ticket->getComments());
    }

    public function testTechnicianCanBulkReopenWithInternalReason(): void
    {
        $company = new Company('Bulk Reopen Reason AB');
        $team = new TechnicianTeam('Reopen Team');
        $technician = new User('bulk-reopen-reason@example.test', 'Ronja', 'Reason', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'BulkReopenReason123'));
        $technician->enableMfa();
        $technician->setTechnicianTeam($team);

        $customerOne = new User('bulk-reopen-reason-one@example.test', 'Kai', 'Kund', UserType::CUSTOMER);
        $customerOne->setPassword($this->passwordHasher->hashPassword($customerOne, 'CustomerPassword123'));
        $customerOne->setCompany($company);

        $customerTwo = new User('bulk-reopen-reason-two@example.test', 'Kia', 'Kund', UserType::CUSTOMER);
        $customerTwo->setPassword($this->passwordHasher->hashPassword($customerTwo, 'CustomerPassword123'));
        $customerTwo->setCompany($company);

        $ticketOne = (new Ticket('DP-7605', 'Återöppna ett', 'Ska återöppnas med intern orsak.', TicketStatus::RESOLVED, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customerOne)
            ->setAssignee($technician)
            ->setAssignedTeam($team);
        $ticketTwo = (new Ticket('DP-7606', 'Återöppna två', 'Ska också återöppnas med intern orsak.', TicketStatus::CLOSED, TicketVisibility::PRIVATE))
            ->setCompany($company)
            ->setRequester($customerTwo)
            ->setAssignee($technician)
            ->setAssignedTeam($team);

        $this->entityManager->persist($company);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customerOne);
        $this->entityManager->persist($customerTwo);
        $this->entityManager->persist($ticketOne);
        $this->entityManager->persist($ticketTwo);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/arenden?scope=mine');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/technician/tickets/bulk-update"]')->form();
        $this->client->request('POST', '/portal/technician/tickets/bulk-update', [
            '_token' => $form->get('_token')->getValue(),
            'return_url' => $form->get('return_url')->getValue(),
            'bulk_action' => 'set_status',
            'bulk_status' => TicketStatus::OPEN->value,
            'bulk_internal_note' => 'Återöppnas efter ny information från driftövervakningen.',
            'bulk_customer_reply' => '',
            'ticket_ids' => [(string) $ticketOne->getId(), (string) $ticketTwo->getId()],
        ]);

        self::assertResponseRedirects('/portal/technician/arenden?scope=mine');

        $this->entityManager->clear();
        /** @var Ticket $ticketOne */
        $ticketOne = $this->entityManager->getRepository(Ticket::class)->find($ticketOne->getId());
        /** @var Ticket $ticketTwo */
        $ticketTwo = $this->entityManager->getRepository(Ticket::class)->find($ticketTwo->getId());
        self::assertSame(TicketStatus::OPEN, $ticketOne->getStatus());
        self::assertSame(TicketStatus::OPEN, $ticketTwo->getStatus());
        self::assertCount(1, $ticketOne->getComments());
        self::assertCount(1, $ticketTwo->getComments());
        self::assertTrue($ticketOne->getComments()->first()->isInternal());
        self::assertTrue($ticketTwo->getComments()->first()->isInternal());
        self::assertSame('Återöppnas efter ny information från driftövervakningen.', $ticketOne->getComments()->first()->getBody());

        $logs = $this->entityManager->getRepository(TicketAuditLog::class)->findBy(['action' => 'internal_comment_added'], ['id' => 'DESC']);
        self::assertGreaterThanOrEqual(2, \count($logs));
        self::assertStringContainsString('Intern kommentar tillagd via bulkåtgärd.', $logs[0]->getMessage());
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
        $crawler = $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
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

        self::assertResponseRedirects(sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));

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
        $crawler = $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $ticket->getId()));
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

        self::assertResponseRedirects(sprintf('/portal/customer/tickets/%d', $ticket->getId()));
        $this->client->followRedirect();

        $this->entityManager->clear();
        $attachment = $this->entityManager->getRepository(TicketCommentAttachment::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($attachment);
        self::assertFalse($attachment->isExternal());
        self::assertSame(basename($tempFile), $attachment->getDisplayName());

        $crawler = $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $ticket->getId()));
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

        $crawler = $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $ticket->getId()));
        self::assertResponseIsSuccessful();
        $form = $crawler->filter(sprintf('form[action="/portal/customer/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Här är storfilslänken.',
            'external_attachment_label' => 'Stor loggfil',
            'external_attachment_url' => 'https://example.test/shared/loggfil',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/portal/customer/tickets/%d', $ticket->getId()));
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
        $crawler = $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $ticket->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/customer/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Försöker ladda upp fel filtyp.',
        ]);
        $form['attachment']->upload($renamedFile);
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/portal/customer/tickets/%d', $ticket->getId()));
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
        $crawler = $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $ticket->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/customer/tickets/%d/comments"]', $ticket->getId()))->form([
            'body' => 'Här är skärmbilden.',
        ]);
        $form['attachment']->upload($tempFile);
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/portal/customer/tickets/%d', $ticket->getId()));
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
        $crawler = $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d"]', $ticket->getId()))->form([
            'subject' => $ticket->getSubject(),
            'summary' => $ticket->getSummary(),
            'resolution_summary' => 'Bilagorna verifierades och ärendet kan nu stängas.',
            'status' => TicketStatus::CLOSED->value,
            'visibility' => $ticket->getVisibility()->value,
            'request_type' => $ticket->getRequestType()->value,
            'impact_level' => $ticket->getImpactLevel()->value,
            'priority' => $ticket->getPriority()->value,
            'escalation_level' => $ticket->getEscalationLevel()->value,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
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
        $crawler = $this->client->request('GET', sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/technician/tickets/%d"]', $ticket->getId()))->form([
            'subject' => $ticket->getSubject(),
            'summary' => $ticket->getSummary(),
            'resolution_summary' => 'Bilagorna verifierades och kan arkiveras senare.',
            'status' => TicketStatus::CLOSED->value,
            'visibility' => $ticket->getVisibility()->value,
            'request_type' => $ticket->getRequestType()->value,
            'impact_level' => $ticket->getImpactLevel()->value,
            'priority' => $ticket->getPriority()->value,
            'escalation_level' => $ticket->getEscalationLevel()->value,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/portal/technician/tickets/%d/visa', $ticket->getId()));
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
