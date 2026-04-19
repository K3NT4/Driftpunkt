<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Mail\Entity\CompanyMailOverride;
use App\Module\Mail\Entity\IncomingMail;
use App\Module\Mail\Entity\MailServer;
use App\Module\Mail\Entity\SupportMailbox;
use App\Module\Mail\Enum\IncomingMailProcessingResult;
use App\Module\Mail\Enum\MailEncryption;
use App\Module\Mail\Enum\MailServerDirection;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketCategory;
use App\Module\Ticket\Entity\TicketComment;
use App\Module\Ticket\Entity\TicketCommentAttachment;
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

final class AdminMailFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    /** @var list<string> */
    private array $tempFiles = [];

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
        foreach ($this->tempFiles as $tempFile) {
            @unlink($tempFile);
        }

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

    public function testAdminCanCreateMailServerMailboxAndCompanyOverride(): void
    {
        $admin = $this->createAdminUser();

        $company = new Company('Acme AB');
        $category = new TicketCategory('Support');
        $this->entityManager->persist($company);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Skapa e-postserver', [
            'name' => 'Primär SMTP',
            'direction' => MailServerDirection::BOTH->value,
            'transport_type' => 'smtp',
            'host' => 'mail.acme.test',
            'port' => '587',
            'encryption' => MailEncryption::TLS->value,
            'username' => 'mailer',
            'password' => 'sekret-token',
            'from_address' => 'support@acme.test',
            'from_name' => 'Acme Support',
            'description' => 'Gemensam server för inkommande och utgående trafik',
            'is_primary_outbound' => '1',
            'fallback_to_primary' => '1',
        ]);

        self::assertResponseRedirects('/portal/admin/mail');
        $this->client->followRedirect();

        $server = $this->entityManager->getRepository(MailServer::class)->findOneBy(['name' => 'Primär SMTP']);
        self::assertNotNull($server);
        self::assertSame(MailServerDirection::BOTH, $server->getDirection());
        self::assertTrue($server->isPrimaryOutbound());

        $crawler = $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Skapa inkorg', [
            'name' => 'Acme support',
            'email_address' => 'support-in@acme.test',
            'company_id' => (string) $company->getId(),
            'incoming_server_id' => (string) $server->getId(),
            'default_category_id' => (string) $category->getId(),
            'default_priority' => TicketPriority::HIGH->value,
            'default_team_id' => '',
            'polling_interval_minutes' => '3',
            'allow_unknown_senders' => '1',
            'create_draft_tickets_for_unknown_senders' => '1',
            'allow_attachments' => '1',
        ]);

        self::assertResponseRedirects('/portal/admin/mail');
        $this->client->followRedirect();

        $mailbox = $this->entityManager->getRepository(SupportMailbox::class)->findOneBy(['name' => 'Acme support']);
        self::assertNotNull($mailbox);
        self::assertSame('support-in@acme.test', $mailbox->getEmailAddress());
        self::assertSame('Acme AB', $mailbox->getCompany()?->getName());
        self::assertSame(TicketPriority::HIGH, $mailbox->getDefaultPriority());
        self::assertTrue($mailbox->createsDraftTicketsForUnknownSenders());

        $crawler = $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Skapa override', [
            'company_id' => (string) $company->getId(),
            'outbound_server_id' => (string) $server->getId(),
            'from_address' => 'reply@acme.test',
            'from_name' => 'Acme Kundsupport',
            'fallback_to_primary' => '1',
        ]);

        self::assertResponseRedirects('/portal/admin/mail');
        $this->client->followRedirect();

        $override = $this->entityManager->getRepository(CompanyMailOverride::class)->findOneBy(['company' => $company]);
        self::assertNotNull($override);
        self::assertSame('reply@acme.test', $override->getFromAddress());
        self::assertSame('Acme Kundsupport', $override->getFromName());
        self::assertSame('Primär SMTP', $override->getOutboundServer()?->getName());
    }

    public function testAdminMailPageShowsGoogleAndMicrosoftProviderGuidance(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Google Workspace SMTP-relay', $content);
        self::assertStringContainsString('Microsoft 365 SMTP (OAuth)', $content);
        self::assertStringContainsString('OAuth är den långsiktigt säkra vägen', $content);
        self::assertStringContainsString('SPF, DKIM och DMARC', $content);
        self::assertStringContainsString('Microsoft OAuth 2.0 (appregistrering)', $content);
        self::assertStringContainsString('Tenant ID', $content);
    }

    public function testAdminCanSeeAndDownloadIncomingMailAttachments(): void
    {
        $admin = $this->createAdminUser();
        $mailbox = new SupportMailbox('Inbound', 'support@example.test');
        $attachmentPath = dirname(__DIR__, 2).'/var/admin-mail-test-attachment.txt';
        file_put_contents($attachmentPath, 'Logg från inkommande mail');
        $this->tempFiles[] = $attachmentPath;

        $incomingMail = new IncomingMail('customer@example.test', 'Bilaga från kund', 'Här kommer loggen.');
        $incomingMail
            ->setMailbox($mailbox)
            ->setAttachmentMetadata([[
                'displayName' => 'kundlogg.txt',
                'mimeType' => 'text/plain',
                'fileSize' => (int) filesize($attachmentPath),
                'filePath' => $attachmentPath,
            ]]);

        $this->entityManager->persist($mailbox);
        $this->entityManager->persist($incomingMail);
        $this->entityManager->flush();

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('kundlogg.txt', $this->client->getResponse()->getContent() ?? '');
        self::assertCount(1, $crawler->selectLink('Ladda ner')->links());

        $this->client->request('GET', sprintf('/portal/admin/incoming-mails/%d/attachments/0/download', $incomingMail->getId()));
        self::assertResponseIsSuccessful();
        self::assertSame('text/plain; charset=UTF-8', $this->client->getResponse()->headers->get('content-type'));
        self::assertStringContainsString('attachment;', (string) $this->client->getResponse()->headers->get('content-disposition'));
        self::assertSame(
            'Logg från inkommande mail',
            file_get_contents((string) $this->client->getResponse()->getFile()?->getPathname()) ?: '',
        );
    }

    public function testAdminCanPreviewIncomingMailAttachmentWhenFileTypeIsSupported(): void
    {
        $admin = $this->createAdminUser();
        $mailbox = new SupportMailbox('Inbound', 'preview@example.test');
        $attachmentPath = dirname(__DIR__, 2).'/var/admin-mail-test-preview.txt';
        file_put_contents($attachmentPath, 'Förhandsvisning från inkommande mail');
        $this->tempFiles[] = $attachmentPath;

        $incomingMail = new IncomingMail('customer@example.test', 'Previewbar bilaga', 'Textfil med preview.');
        $incomingMail
            ->setMailbox($mailbox)
            ->setAttachmentMetadata([[
                'displayName' => 'preview.txt',
                'mimeType' => 'text/plain',
                'fileSize' => (int) filesize($attachmentPath),
                'filePath' => $attachmentPath,
            ]]);

        $this->entityManager->persist($mailbox);
        $this->entityManager->persist($incomingMail);
        $this->entityManager->flush();

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->selectLink('Förhandsvisa')->links());

        $this->client->request('GET', sprintf('/portal/admin/incoming-mails/%d/attachments/0/preview', $incomingMail->getId()));
        self::assertResponseIsSuccessful();
        self::assertSame('text/plain; charset=UTF-8', $this->client->getResponse()->headers->get('content-type'));
        self::assertStringContainsString('inline;', (string) $this->client->getResponse()->headers->get('content-disposition'));
        self::assertSame(
            'Förhandsvisning från inkommande mail',
            file_get_contents((string) $this->client->getResponse()->getFile()?->getPathname()) ?: '',
        );
    }

    public function testAdminCanSeeWhenIncomingAttachmentHasBeenCopiedToTicket(): void
    {
        $admin = $this->createAdminUser();
        $requester = new User('customer@example.test', 'Klara', 'Kund', UserType::CUSTOMER);
        $requester->setPassword($this->passwordHasher->hashPassword($requester, 'Customer123'));

        $mailbox = new SupportMailbox('Inbound', 'status@example.test');
        $attachmentPath = dirname(__DIR__, 2).'/var/admin-mail-copied-attachment.txt';
        file_put_contents($attachmentPath, 'Kopierad bilaga');
        $this->tempFiles[] = $attachmentPath;

        $ticket = new Ticket(
            'DP-1001',
            'Bilaga kopplad till ticket',
            'Sammanfattning',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
            TicketEscalationLevel::NONE,
        );
        $ticket->setRequester($requester);

        $comment = new TicketComment($ticket, $requester, 'Kommentar med bilaga.');
        $storedAttachment = TicketCommentAttachment::fromLocalFile(
            $comment,
            'kundlogg.txt',
            $attachmentPath,
            'text/plain',
            (int) filesize($attachmentPath),
        );
        $comment->addAttachment($storedAttachment);
        $ticket->addComment($comment);

        $incomingMail = new IncomingMail('customer@example.test', 'Bilaga har kopierats', 'Mailtext');
        $incomingMail
            ->setMailbox($mailbox)
            ->setMatchedTicket($ticket)
            ->markProcessed(IncomingMailProcessingResult::TICKET_CREATED, 'Ticket skapades från inkommande mail.')
            ->setAttachmentMetadata([[
                'displayName' => 'kundlogg.txt',
                'mimeType' => 'text/plain',
                'fileSize' => (int) filesize($attachmentPath),
                'filePath' => $attachmentPath,
            ]]);

        $this->entityManager->persist($requester);
        $this->entityManager->persist($mailbox);
        $this->entityManager->persist($ticket);
        $this->entityManager->persist($comment);
        $this->entityManager->persist($storedAttachment);
        $this->entityManager->persist($incomingMail);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/mail');

        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Kopierad till DP-1001', $html);
        self::assertStringContainsString('1 bilaga kopierades till ticketen', $html);
    }

    public function testAdminCanSeeWhenIncomingAttachmentWasStoppedAfterRejection(): void
    {
        $admin = $this->createAdminUser();
        $mailbox = new SupportMailbox('Inbound', 'rejected@example.test');
        $attachmentPath = dirname(__DIR__, 2).'/var/admin-mail-rejected-attachment.txt';
        file_put_contents($attachmentPath, 'Stoppad bilaga');
        $this->tempFiles[] = $attachmentPath;

        $incomingMail = new IncomingMail('unknown@example.test', 'Bilaga stoppad', 'Mailtext');
        $incomingMail
            ->setMailbox($mailbox)
            ->markProcessed(IncomingMailProcessingResult::REJECTED, 'Mail avvisades.')
            ->setAttachmentMetadata([[
                'displayName' => 'stoppad.txt',
                'mimeType' => 'text/plain',
                'fileSize' => (int) filesize($attachmentPath),
                'filePath' => $attachmentPath,
            ]]);

        $this->entityManager->persist($mailbox);
        $this->entityManager->persist($incomingMail);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/mail');

        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('stoppad.txt', $html);
        self::assertStringContainsString('1 stoppad', $html);
    }

    public function testAdminCanFilterIncomingMailHistory(): void
    {
        $admin = $this->createAdminUser();
        $mailbox = new SupportMailbox('Inbound', 'filters@example.test');

        $copiedPath = dirname(__DIR__, 2).'/var/admin-mail-filter-copied.txt';
        $stoppedPath = dirname(__DIR__, 2).'/var/admin-mail-filter-stopped.txt';
        file_put_contents($copiedPath, 'Kopierad');
        file_put_contents($stoppedPath, 'Stoppad');
        $this->tempFiles[] = $copiedPath;
        $this->tempFiles[] = $stoppedPath;

        $ticket = new Ticket(
            'DP-1001',
            'Filterticket',
            'Sammanfattning',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
            TicketEscalationLevel::NONE,
        );
        $requester = new User('filter@example.test', 'Fil', 'Ter', UserType::CUSTOMER);
        $requester->setPassword($this->passwordHasher->hashPassword($requester, 'Customer123'));
        $ticket->setRequester($requester);
        $comment = new TicketComment($ticket, $requester, 'Kommentar');
        $ticketAttachment = TicketCommentAttachment::fromLocalFile($comment, 'copied.txt', $copiedPath, 'text/plain', (int) filesize($copiedPath));
        $comment->addAttachment($ticketAttachment);
        $ticket->addComment($comment);

        $copiedMail = new IncomingMail('a@example.test', 'Kopierat mail', 'Body');
        $copiedMail
            ->setMailbox($mailbox)
            ->setMatchedTicket($ticket)
            ->markProcessed(IncomingMailProcessingResult::TICKET_CREATED, 'Ticket skapades.')
            ->setAttachmentMetadata([[
                'displayName' => 'copied.txt',
                'mimeType' => 'text/plain',
                'fileSize' => (int) filesize($copiedPath),
                'filePath' => $copiedPath,
            ]]);

        $waitingMail = new IncomingMail('b@example.test', 'Väntande mail', 'Body');
        $waitingMail
            ->setMailbox($mailbox)
            ->markProcessed(IncomingMailProcessingResult::DRAFT_REVIEW_CREATED, 'Väntar.')
            ->setAttachmentMetadata([[
                'displayName' => 'waiting.txt',
                'mimeType' => 'text/plain',
                'fileSize' => 7,
                'filePath' => $stoppedPath,
            ]]);

        $stoppedMail = new IncomingMail('c@example.test', 'Avvisat mail', 'Body');
        $stoppedMail
            ->setMailbox($mailbox)
            ->markProcessed(IncomingMailProcessingResult::REJECTED, 'Avvisat.')
            ->setAttachmentMetadata([[
                'displayName' => 'stopped.txt',
                'mimeType' => 'text/plain',
                'fileSize' => (int) filesize($stoppedPath),
                'filePath' => $stoppedPath,
            ]]);

        $this->entityManager->persist($mailbox);
        $this->entityManager->persist($requester);
        $this->entityManager->persist($ticket);
        $this->entityManager->persist($comment);
        $this->entityManager->persist($ticketAttachment);
        $this->entityManager->persist($copiedMail);
        $this->entityManager->persist($waitingMail);
        $this->entityManager->persist($stoppedMail);
        $this->entityManager->flush();

        $this->client->loginUser($admin);

        $this->client->request('GET', '/portal/admin/mail?incoming_status=draft_review_created&incoming_attachment_state=waiting');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Väntande mail', $html);
        self::assertStringNotContainsString('Kopierat mail', $html);
        self::assertStringNotContainsString('Avvisat mail', $html);

        $this->client->request('GET', '/portal/admin/mail?incoming_status=ticket_created&incoming_attachment_state=copied');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Kopierat mail', $html);
        self::assertStringNotContainsString('Väntande mail', $html);
        self::assertStringNotContainsString('Avvisat mail', $html);

        $this->client->request('GET', '/portal/admin/mail?incoming_status=ticket_created&incoming_attachment_state=copied&incoming_query=dp-1001');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Kopierat mail', $html);
        self::assertStringContainsString('value="dp-1001"', $html);
        self::assertStringNotContainsString('Väntande mail', $html);
        self::assertStringNotContainsString('Avvisat mail', $html);

        $this->client->request('GET', '/portal/admin/mail?incoming_query=avvisat');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Avvisat mail', $html);
        self::assertStringNotContainsString('Kopierat mail', $html);
        self::assertStringNotContainsString('Väntande mail', $html);

        $this->setEntityCreatedAt(IncomingMail::class, $copiedMail->getId(), new \DateTimeImmutable('yesterday 08:00'));
        $this->setEntityCreatedAt(IncomingMail::class, $waitingMail->getId(), new \DateTimeImmutable('today 09:00'));
        $this->setEntityCreatedAt(IncomingMail::class, $stoppedMail->getId(), new \DateTimeImmutable('today 10:00'));

        $this->client->request('GET', '/portal/admin/mail?incoming_date=today&incoming_sort=oldest');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('option value="today" selected', $html);
        self::assertStringContainsString('option value="oldest" selected', $html);
        self::assertStringContainsString('Väntande mail', $html);
        self::assertStringContainsString('Avvisat mail', $html);
        self::assertStringNotContainsString('Kopierat mail', $html);
        self::assertLessThan(
            strpos($html, 'Avvisat mail'),
            strpos($html, 'Väntande mail'),
        );
    }

    public function testAdminCanPaginateIncomingMailHistory(): void
    {
        $admin = $this->createAdminUser();
        $mailbox = new SupportMailbox('Paged Inbox', 'paged@example.test');
        $this->entityManager->persist($mailbox);
        $this->entityManager->flush();

        for ($index = 1; $index <= 12; ++$index) {
            $mail = new IncomingMail(sprintf('sender%02d@example.test', $index), sprintf('Paged incoming %02d', $index), 'Body');
            $mail->setMailbox($mailbox);
            $this->entityManager->persist($mail);
        }
        $this->entityManager->flush();

        $mails = $this->entityManager->getRepository(IncomingMail::class)->findBy([], ['id' => 'ASC']);
        foreach (array_values($mails) as $index => $mail) {
            $this->setEntityCreatedAt(IncomingMail::class, $mail->getId(), new \DateTimeImmutable(sprintf('2026-04-18 08:%02d:00', $index)));
        }

        $this->client->loginUser($admin);

        $this->client->request('GET', '/portal/admin/mail?incoming_sort=oldest&incoming_page=1');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('1–10 av 12', $html);
        self::assertStringContainsString('Paged incoming 01', $html);
        self::assertStringContainsString('Paged incoming 10', $html);
        self::assertStringNotContainsString('Paged incoming 11', $html);
        self::assertStringContainsString('incoming_page=2', $html);

        $this->client->request('GET', '/portal/admin/mail?incoming_sort=oldest&incoming_page=2');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('11–12 av 12', $html);
        self::assertStringContainsString('Paged incoming 11', $html);
        self::assertStringContainsString('Paged incoming 12', $html);
        self::assertStringNotContainsString('Paged incoming 01', $html);
        self::assertStringContainsString('incoming_page=1', $html);
    }

    public function testAdminCanCreateCorrectionDraftFromIncomingMail(): void
    {
        $admin = $this->createAdminUser();
        $mailbox = new SupportMailbox('Correction Inbox', 'correction@example.test');
        $incomingMail = new IncomingMail('customer@example.test', 'Felkopplat mail', 'Det här behöver rättas.');
        $incomingMail
            ->setMailbox($mailbox)
            ->markProcessed(IncomingMailProcessingResult::REJECTED, 'Mailet avvisades först.');

        $this->entityManager->persist($mailbox);
        $this->entityManager->persist($incomingMail);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/admin/incoming-mails/%d/correction-draft"]', $incomingMail->getId()))->form([
            'reason' => 'Mailet avvisades felaktigt och ska granskas manuellt.',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/mail');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $incomingMail = $this->entityManager->getRepository(IncomingMail::class)->find($incomingMail->getId());
        $review = $this->entityManager->getRepository(\App\Module\Mail\Entity\DraftTicketReview::class)->findOneBy(['incomingMail' => $incomingMail], ['id' => 'DESC']);
        self::assertNotNull($incomingMail);
        self::assertNotNull($review);
        self::assertSame(IncomingMailProcessingResult::DRAFT_REVIEW_CREATED, $incomingMail->getProcessingResult());
        self::assertSame('Mailet avvisades felaktigt och ska granskas manuellt.', $review->getReason());
        self::assertTrue($review->isPending());
        self::assertNotNull($review->getDraftTicket());

        $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Öppna korrigeringsdraft', $html);
        self::assertStringContainsString('Korrigering: nytt korrekt ticket', $html);
    }

    public function testAdminCanCreateCorrectionDraftForExistingTicket(): void
    {
        $admin = $this->createAdminUser();
        $mailbox = new SupportMailbox('Correction Inbox', 'existing-correction@example.test');
        $ticket = new Ticket(
            'DP-1001',
            'Befintligt ärende',
            'Sammanfattning',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
            TicketEscalationLevel::NONE,
        );
        $incomingMail = new IncomingMail('customer@example.test', 'Ska till befintligt ärende', 'Det här hör till ett tidigare ärende.');
        $incomingMail
            ->setMailbox($mailbox)
            ->markProcessed(IncomingMailProcessingResult::REJECTED, 'Mailet avvisades först.');

        $this->entityManager->persist($mailbox);
        $this->entityManager->persist($ticket);
        $this->entityManager->persist($incomingMail);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/admin/incoming-mails/%d/correction-draft"]', $incomingMail->getId()))->form([
            'correction_mode' => 'existing_ticket',
            'existing_ticket_reference' => 'DP-1001',
            'reason' => 'Mailet ska kopplas om till befintligt ticket.',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/mail');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $incomingMail = $this->entityManager->getRepository(IncomingMail::class)->find($incomingMail->getId());
        $review = $this->entityManager->getRepository(\App\Module\Mail\Entity\DraftTicketReview::class)->findOneBy(['incomingMail' => $incomingMail], ['id' => 'DESC']);
        self::assertNotNull($incomingMail);
        self::assertNotNull($review);
        self::assertNull($review->getDraftTicket());
        self::assertSame('DP-1001', $review->getMatchedTicket()?->getReference());
        self::assertSame(IncomingMailProcessingResult::DRAFT_REVIEW_CREATED, $incomingMail->getProcessingResult());
        self::assertStringContainsString('väntar på omkoppling till DP-1001', (string) $incomingMail->getProcessingNote());

        $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Korrigering: omkoppling till DP-1001', $html);
    }

    private function createAdminUser(): User
    {
        $user = new User('admin@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'SuperSecure123'));
        $user->enableMfa();

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function setEntityCreatedAt(string $entityClass, ?int $id, \DateTimeImmutable $createdAt): void
    {
        $entity = $this->entityManager->getRepository($entityClass)->find($id);
        self::assertNotNull($entity);

        $createdProperty = new \ReflectionProperty($entity, 'createdAt');
        $createdProperty->setValue($entity, $createdAt);

        $updatedProperty = new \ReflectionProperty($entity, 'updatedAt');
        $updatedProperty->setValue($entity, $createdAt);

        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
