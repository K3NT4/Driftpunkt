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
use App\Module\Mail\Service\IncomingMailSpoolReader;
use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketComment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MailboxPollingCommandTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private IncomingMailSpoolReader $incomingMailSpoolReader;
    private SystemSettings $systemSettings;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $this->incomingMailSpoolReader = $container->get(IncomingMailSpoolReader::class);
        $this->systemSettings = $container->get(SystemSettings::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $this->systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ENABLED, true);
        $this->cleanupMailSpoolDirectories();
    }

    protected function tearDown(): void
    {
        $this->cleanupMailSpoolDirectories();
        $this->entityManager->clear();
        $this->entityManager->getConnection()->close();

        parent::tearDown();
        self::ensureKernelShutdown();
    }

    public function testPollingCommandImportsQueuedMailboxFiles(): void
    {
        [$mailbox, $customer] = $this->createMailboxFixture();
        $inboxDirectory = $this->incomingMailSpoolReader->inboxDirectory($mailbox);
        if (!is_dir($inboxDirectory) && !mkdir($inboxDirectory, 0775, true) && !is_dir($inboxDirectory)) {
            self::fail('Kunde inte skapa testkatalog för mailspool.');
        }

        file_put_contents($inboxDirectory.'/known.json', json_encode([
            'from' => $customer->getEmail(),
            'subject' => 'Kundrapport via polling',
            'body' => 'Det här mailet kom via pollning.',
            'attachments' => [
                [
                    'displayName' => 'rapport.txt',
                    'mimeType' => 'text/plain',
                    'content_base64' => base64_encode('Loggrad 1'),
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        file_put_contents($inboxDirectory.'/unknown.json', json_encode([
            'from' => 'unknown@example.test',
            'subject' => 'Okänd avsändare via polling',
            'body' => 'Det här ska bli draftgranskning.',
        ], JSON_THROW_ON_ERROR));

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:mail:poll');
        $tester = new CommandTester($command);

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Kundrapport via polling']);
        self::assertNotNull($ticket);
        $comment = $this->entityManager->getRepository(TicketComment::class)->findOneBy(['ticket' => $ticket], ['id' => 'ASC']);
        self::assertNotNull($comment);
        self::assertCount(1, $comment->getAttachments());

        $incomingMails = $this->entityManager->getRepository(IncomingMail::class)->findBy([], ['id' => 'ASC']);
        self::assertCount(2, $incomingMails);
        self::assertCount(1, $incomingMails[0]->getAttachmentMetadata());

        $review = $this->entityManager->getRepository(DraftTicketReview::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($review);
        self::assertNotNull($review->getDraftTicket());
        $this->entityManager->clear();

        $mailbox = $this->entityManager->getRepository(SupportMailbox::class)->find($mailbox->getId());
        self::assertNotNull($mailbox);
        self::assertNotNull($mailbox->getLastPolledAt());
        self::assertFileDoesNotExist($inboxDirectory.'/known.json');
        self::assertFileDoesNotExist($inboxDirectory.'/unknown.json');
        self::assertDirectoryExists($this->incomingMailSpoolReader->processedDirectory($mailbox));
    }

    /**
     * @return array{SupportMailbox, User}
     */
    private function createMailboxFixture(): array
    {
        $company = new Company('Polling AB');
        $server = new MailServer('Polling Server', \App\Module\Mail\Enum\MailServerDirection::INCOMING, 'mail.polling.test', 993);
        $mailbox = new SupportMailbox('Polling Inbox', 'polling@acme.test');
        $mailbox->setCompany($company)->setIncomingServer($server);

        $customer = new User('customer@polling.test', 'Pia', 'Polling', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $this->entityManager->persist($company);
        $this->entityManager->persist($server);
        $this->entityManager->persist($mailbox);
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return [$mailbox, $customer];
    }

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath);
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }

    private function cleanupMailSpoolDirectories(): void
    {
        $basePaths = [
            dirname(__DIR__, 2).'/var/mail_ingest',
            dirname(__DIR__, 2).'/var/mail_ingest_processed',
            dirname(__DIR__, 2).'/var/mail_ingest_failed',
            dirname(__DIR__, 2).'/var/incoming_mail_attachments',
        ];

        foreach ($basePaths as $basePath) {
            if (!is_dir($basePath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }

            @rmdir($basePath);
        }
    }
}
