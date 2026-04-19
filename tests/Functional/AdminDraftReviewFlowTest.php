<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Mail\Entity\DraftTicketReview;
use App\Module\Mail\Entity\IncomingMail;
use App\Module\Mail\Entity\MailServer;
use App\Module\Mail\Entity\SupportMailbox;
use App\Module\Mail\Enum\IncomingMailProcessingResult;
use App\Module\Mail\Enum\MailServerDirection;
use App\Module\Notification\Entity\NotificationLog;
use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\Ticket;
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

final class AdminDraftReviewFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private SystemSettings $systemSettings;
    /** @var list<string> */
    private array $tempFiles = [];
    private int $fixtureCounter = 0;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $this->systemSettings = $container->get(SystemSettings::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $this->systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ENABLED, true);
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

    public function testAdminCanApproveDraftReviewAndActivateTicket(): void
    {
        [$admin, $company, $customer, $technician, $review, $ticket] = $this->createDraftReviewFixture(true);
        $this->client->loginUser($admin);
        $this->client->enableProfiler();

        $crawler = $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/approve"]', $review->getId()))->form([
            'requester_id' => (string) $customer->getId(),
            'company_id' => (string) $company->getId(),
            'assignee_id' => (string) $technician->getId(),
            'assigned_team_id' => '',
            'category_id' => '',
            'status' => TicketStatus::OPEN->value,
            'visibility' => TicketVisibility::PRIVATE->value,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/mail');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $review = $this->entityManager->getRepository(DraftTicketReview::class)->find($review->getId());
        $ticket = $this->entityManager->getRepository(Ticket::class)->find($ticket->getId());
        self::assertNotNull($review);
        self::assertNotNull($ticket);
        self::assertSame('approved', $review->getStatus()->value);
        self::assertSame($customer->getId(), $ticket->getRequester()?->getId());
        self::assertSame($company->getId(), $ticket->getCompany()?->getId());
        self::assertSame($technician->getId(), $ticket->getAssignee()?->getId());
        self::assertSame(TicketStatus::OPEN, $ticket->getStatus());
        self::assertSame('Diskproblem från okänd avsändare', $ticket->getSubject());
        self::assertCount(1, $ticket->getComments());
        $notification = $this->entityManager->getRepository(NotificationLog::class)->findOneBy([
            'eventType' => 'incoming_mail_ticket_received',
            'ticket' => $ticket,
        ]);
        self::assertNotNull($notification);
        self::assertTrue($notification->isSent());
        self::assertSame($customer->getEmail(), $notification->getRecipientEmail());

        $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Godkänd draftgranskning', $html);
        self::assertStringContainsString('1 bilaga kopierades till ticketen', $html);
    }

    public function testAdminCanRejectDraftReviewAndCloseDraftTicket(): void
    {
        [$admin, , , , $review, $ticket] = $this->createDraftReviewFixture(true);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/reject"]', $review->getId()))->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/mail');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $review = $this->entityManager->getRepository(DraftTicketReview::class)->find($review->getId());
        $ticket = $this->entityManager->getRepository(Ticket::class)->find($ticket->getId());
        $incomingMail = $review?->getIncomingMail();
        self::assertNotNull($review);
        self::assertNotNull($ticket);
        self::assertSame('rejected', $review->getStatus()->value);
        self::assertSame(TicketStatus::CLOSED, $ticket->getStatus());
        self::assertSame(TicketVisibility::INTERNAL_ONLY, $ticket->getVisibility());
        self::assertSame(IncomingMailProcessingResult::REJECTED, $incomingMail?->getProcessingResult());

        $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Avvisad draftgranskning', $html);
        self::assertStringContainsString('1 stoppad i draftläget', $html);
    }

    public function testDraftReviewCardShowsAttachmentStatusAndActions(): void
    {
        [$admin, , , , $review] = $this->createDraftReviewFixture(true);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();

        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Bilagor i draftflödet', $html);
        self::assertStringContainsString('driftlogg.txt', $html);
        self::assertStringContainsString('Inte kopierad ännu', $html);
        self::assertStringContainsString('1 bilaga följer med vid godkännande', $html);
        self::assertStringContainsString(sprintf('/portal/admin/incoming-mails/%d/attachments/0/download', $review->getIncomingMail()->getId()), $html);
        self::assertStringContainsString(sprintf('/portal/admin/incoming-mails/%d/attachments/0/preview', $review->getIncomingMail()->getId()), $html);
    }

    public function testAdminCanFilterDraftReviewsByStatus(): void
    {
        [$admin] = $this->createDraftReviewFixture();
        $adminId = $admin->getId();
        $this->entityManager->clear();
        [, , , , $approvedReview] = $this->createDraftReviewFixture();
        $approvedReviewId = $approvedReview->getId();
        $this->entityManager->clear();
        [, , , , $rejectedReview] = $this->createDraftReviewFixture();
        $rejectedReviewId = $rejectedReview->getId();

        self::assertNotNull($adminId);
        self::assertNotNull($approvedReviewId);
        self::assertNotNull($rejectedReviewId);

        $admin = $this->entityManager->getRepository(User::class)->find($adminId);
        $approvedReview = $this->entityManager->getRepository(DraftTicketReview::class)->find($approvedReviewId);
        $rejectedReview = $this->entityManager->getRepository(DraftTicketReview::class)->find($rejectedReviewId);
        self::assertNotNull($admin);
        self::assertNotNull($approvedReview);
        self::assertNotNull($rejectedReview);
        $approvedReview->markApproved($admin);
        $rejectedReview->markRejected($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);

        $this->client->request('GET', '/portal/admin/mail?draft_status=approved');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Godkänd', $html);
        self::assertStringNotContainsString('Väntar på granskning', $html);

        $this->client->request('GET', '/portal/admin/mail?draft_status=rejected');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Avvisad', $html);
        self::assertStringNotContainsString('Väntar på granskning', $html);
    }

    public function testAdminCanSearchDraftReviewsBySenderMailboxAndCompany(): void
    {
        [$admin, $targetCompany, , , $targetReview] = $this->createDraftReviewFixture(false, [
            'companyName' => 'Beta Industri AB',
            'mailboxName' => 'Beta Support',
            'mailboxEmail' => 'beta-support@acme.test',
            'fromEmail' => 'alerts@beta.test',
            'subject' => 'Printer offline on floor 2',
        ]);
        $adminId = $admin->getId();
        $targetCompanyId = $targetCompany->getId();
        $targetReviewId = $targetReview->getId();
        $targetMailboxId = $targetReview->getMailbox()?->getId();
        $this->entityManager->clear();
        [, , , , $otherReview] = $this->createDraftReviewFixture(false, [
            'companyName' => 'Gamma Logistics AB',
            'mailboxName' => 'Gamma Drift',
            'mailboxEmail' => 'gamma-drift@acme.test',
            'fromEmail' => 'ops@gamma.test',
            'subject' => 'Warehouse scanner battery issue',
        ]);
        $otherReviewId = $otherReview->getId();

        self::assertNotNull($adminId);
        self::assertNotNull($targetCompanyId);
        self::assertNotNull($targetReviewId);
        self::assertNotNull($targetMailboxId);
        self::assertNotNull($otherReviewId);

        $admin = $this->entityManager->getRepository(User::class)->find($adminId);
        self::assertNotNull($admin);
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', sprintf(
            '/portal/admin/mail?draft_status=pending&draft_query=%s&draft_mailbox_id=%d&draft_company_id=%d',
            urlencode('beta'),
            $targetMailboxId,
            $targetCompanyId,
        ));
        self::assertResponseIsSuccessful();

        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('value="beta"', $html);
        self::assertStringContainsString(sprintf('option value="%d" selected', $targetMailboxId), $html);
        self::assertStringContainsString(sprintf('option value="%d" selected', $targetCompanyId), $html);
        self::assertCount(1, $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/approve"]', $targetReviewId)));
        self::assertCount(0, $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/approve"]', $otherReviewId)));
        self::assertStringContainsString('1–1 av 1', $html);

        self::assertNotSame($targetReviewId, $otherReviewId);
    }

    public function testAdminCanFilterAndSortDraftReviewsByDate(): void
    {
        [$admin, , , , $olderReview] = $this->createDraftReviewFixture(false, [
            'subject' => 'Gammal draft',
            'fromEmail' => 'older@example.test',
        ]);
        $adminId = $admin->getId();
        $olderReviewId = $olderReview->getId();
        $this->entityManager->clear();
        [, , , , $newerReview] = $this->createDraftReviewFixture(false, [
            'subject' => 'Ny draft',
            'fromEmail' => 'newer@example.test',
        ]);
        $newerReviewId = $newerReview->getId();

        self::assertNotNull($adminId);
        self::assertNotNull($olderReviewId);
        self::assertNotNull($newerReviewId);

        $this->setEntityCreatedAt(DraftTicketReview::class, $olderReviewId, new \DateTimeImmutable('yesterday 08:00'));
        $this->setEntityCreatedAt(DraftTicketReview::class, $newerReviewId, new \DateTimeImmutable('today 09:00'));

        $admin = $this->entityManager->getRepository(User::class)->find($adminId);
        self::assertNotNull($admin);
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/mail?draft_date=today&draft_sort=oldest');
        self::assertResponseIsSuccessful();

        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('option value="today" selected', $html);
        self::assertStringContainsString('option value="oldest" selected', $html);
        self::assertCount(1, $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/approve"]', $newerReviewId)));
        self::assertCount(0, $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/approve"]', $olderReviewId)));
    }

    public function testAdminCanApproveCorrectionDraftToExistingTicket(): void
    {
        $admin = new User('admin-existing@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'AdminPassword123'));
        $admin->enableMfa();

        $company = new Company('Existing Ticket AB');
        $customer = new User('customer-existing@example.test', 'Klara', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $mailbox = new SupportMailbox('Existing Link Inbox', 'existing-link@example.test');
        $mailbox->setCompany($company);

        $existingTicket = new Ticket(
            'DP-1001',
            'Pågående ärende',
            'Tidigare sammanfattning',
            TicketStatus::PENDING_CUSTOMER,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
        );
        $existingTicket->setRequester($customer)->setCompany($company);

        $incomingMail = new IncomingMail('customer-existing@example.test', 'Re: felkopplat', 'Detta borde ha hamnat på befintligt ticket.');
        $incomingMail
            ->setMailbox($mailbox)
            ->setMatchedTicket($existingTicket)
            ->setMatchedCompany($company)
            ->markProcessed(IncomingMailProcessingResult::DRAFT_REVIEW_CREATED, 'Manuell korrigeringsgranskning skapades och väntar på omkoppling till DP-1001.');

        $review = new DraftTicketReview($incomingMail, 'Mailet ska kopplas om till befintligt ticket.');
        $review
            ->setMailbox($mailbox)
            ->setMatchedTicket($existingTicket)
            ->setMatchedCompany($company);

        $this->entityManager->persist($company);
        $this->entityManager->persist($admin);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($mailbox);
        $this->entityManager->persist($existingTicket);
        $this->entityManager->persist($incomingMail);
        $this->entityManager->persist($review);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/mail');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Korrigeringstyp: omkoppling till DP-1001', $html);

        $form = $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/approve"]', $review->getId()))->form([
            'requester_id' => (string) $customer->getId(),
            'company_id' => (string) $company->getId(),
            'assignee_id' => '',
            'assigned_team_id' => '',
            'category_id' => '',
            'status' => TicketStatus::OPEN->value,
            'visibility' => TicketVisibility::PRIVATE->value,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/mail');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $review = $this->entityManager->getRepository(DraftTicketReview::class)->find($review->getId());
        $existingTicket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['reference' => 'DP-1001']);
        $incomingMail = $this->entityManager->getRepository(IncomingMail::class)->find($incomingMail->getId());
        self::assertNotNull($review);
        self::assertNotNull($existingTicket);
        self::assertNotNull($incomingMail);
        self::assertSame('approved', $review->getStatus()->value);
        self::assertSame(IncomingMailProcessingResult::COMMENT_ADDED, $incomingMail->getProcessingResult());
        self::assertSame('DP-1001', $incomingMail->getMatchedTicket()?->getReference());
        self::assertSame(TicketStatus::OPEN, $existingTicket->getStatus());
        self::assertCount(1, $existingTicket->getComments());

        $notification = $this->entityManager->getRepository(NotificationLog::class)->findOneBy([
            'eventType' => 'incoming_mail_ticket_received',
            'ticket' => $existingTicket,
        ], ['id' => 'DESC']);
        self::assertNotNull($notification);
        self::assertSame($customer->getEmail(), $notification->getRecipientEmail());
    }

    public function testAdminCanPaginateDraftReviews(): void
    {
        $adminId = null;
        $reviewIds = [];

        for ($index = 1; $index <= 12; ++$index) {
            [$currentAdmin, , , , $review] = $this->createDraftReviewFixture(false, [
                'subject' => sprintf('Paged draft %02d', $index),
                'fromEmail' => sprintf('draft%02d@example.test', $index),
            ]);
            $adminId ??= $currentAdmin->getId();
            $reviewIds[] = $review->getId();
            $this->entityManager->clear();
        }

        foreach ($reviewIds as $index => $reviewId) {
            $this->setEntityCreatedAt(DraftTicketReview::class, $reviewId, new \DateTimeImmutable(sprintf('2026-04-18 09:%02d:00', $index)));
        }

        self::assertNotNull($adminId);
        $admin = $this->entityManager->getRepository(User::class)->find($adminId);
        self::assertNotNull($admin);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/mail?draft_sort=oldest&draft_page=1');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('1–10 av 12', $html);
        self::assertCount(1, $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/approve"]', $reviewIds[0])));
        self::assertCount(1, $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/approve"]', $reviewIds[9])));
        self::assertCount(0, $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/approve"]', $reviewIds[10])));
        self::assertStringContainsString('draft_page=2', $html);

        $crawler = $this->client->request('GET', '/portal/admin/mail?draft_sort=oldest&draft_page=2');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('11–12 av 12', $html);
        self::assertCount(0, $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/approve"]', $reviewIds[0])));
        self::assertCount(1, $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/approve"]', $reviewIds[10])));
        self::assertCount(1, $crawler->filter(sprintf('form[action="/portal/admin/draft-ticket-reviews/%d/approve"]', $reviewIds[11])));
        self::assertStringContainsString('draft_page=1', $html);
    }

    /**
     * @return array{User, Company, User, User, DraftTicketReview, Ticket}
     */
    private function createDraftReviewFixture(bool $withAttachment = false, array $overrides = []): array
    {
        ++$this->fixtureCounter;
        $suffix = (string) $this->fixtureCounter;

        $company = new Company($overrides['companyName'] ?? 'Acme AB '.$suffix);

        $admin = new User(sprintf('admin%s@example.test', $suffix), 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'AdminPassword123'));
        $admin->enableMfa();

        $customer = new User(sprintf('customer%s@acme.test', $suffix), 'Karin', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $technician = new User(sprintf('tech%s@acme.test', $suffix), 'Tess', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'TechPassword123'));
        $technician->enableMfa();

        $server = new MailServer('Inbound Mail '.$suffix, MailServerDirection::INCOMING, 'mail.acme.test', 993);
        $mailbox = new SupportMailbox($overrides['mailboxName'] ?? 'Acme Support '.$suffix, $overrides['mailboxEmail'] ?? sprintf('support%s@acme.test', $suffix));
        $mailbox->setCompany($company)->setIncomingServer($server);

        $incomingMail = new IncomingMail(
            $overrides['fromEmail'] ?? 'unknown@example.test',
            $overrides['subject'] ?? 'Diskproblem från okänd avsändare',
            $overrides['body'] ?? 'Servern rapporterar diskfel.',
        );
        $incomingMail->setMailbox($mailbox);

        if ($withAttachment) {
            $attachmentPath = dirname(__DIR__, 2).'/var/admin-draft-review-attachment-'.$suffix.'.txt';
            file_put_contents($attachmentPath, 'Draftgranskningens bilaga');
            $this->tempFiles[] = $attachmentPath;

            $incomingMail->setAttachmentMetadata([[
                'displayName' => 'driftlogg.txt',
                'mimeType' => 'text/plain',
                'fileSize' => (int) filesize($attachmentPath),
                'filePath' => $attachmentPath,
            ]]);
        }

        $ticket = new Ticket(
            sprintf('DP-%04d', 1000 + $this->fixtureCounter),
            $overrides['ticketSubject'] ?? '[Draft] Diskproblem från okänd avsändare',
            $overrides['ticketBody'] ?? 'Servern rapporterar diskfel.',
            TicketStatus::NEW,
            TicketVisibility::INTERNAL_ONLY,
            TicketPriority::NORMAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
        );

        $review = new DraftTicketReview($incomingMail, $overrides['reason'] ?? 'Okänd avsändare kräver manuell granskning innan ticket kan kopplas.');
        $review->setMailbox($mailbox)->setDraftTicket($ticket)->setMatchedCompany($company);
        $incomingMail
            ->setMatchedCompany($company)
            ->setMatchedTicket($ticket)
            ->markProcessed(IncomingMailProcessingResult::DRAFT_REVIEW_CREATED, 'Draftgranskning skapades tillsammans med intern ticket DP-1001.');

        $this->entityManager->persist($company);
        $this->entityManager->persist($admin);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($server);
        $this->entityManager->persist($mailbox);
        $this->entityManager->persist($incomingMail);
        $this->entityManager->persist($ticket);
        $this->entityManager->persist($review);
        $this->entityManager->flush();

        return [$admin, $company, $customer, $technician, $review, $ticket];
    }

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
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
