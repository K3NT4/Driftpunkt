<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Mail\Entity\IncomingMail;
use App\Module\Mail\Entity\MailServer;
use App\Module\Mail\Entity\SupportMailbox;
use App\Module\Mail\Enum\MailEncryption;
use App\Module\Mail\Enum\MailServerDirection;
use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketComment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ImapPollingCommandTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private SystemSettings $systemSettings;
    private mixed $serverProcess = null;
    /** @var resource[] */
    private array $pipes = [];
    private ?string $fixturePath = null;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $this->systemSettings = $container->get(SystemSettings::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $this->systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ENABLED, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->pipes as $pipe) {
            if (\is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (\is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }

        if (\is_string($this->fixturePath) && is_file($this->fixturePath)) {
            @unlink($this->fixturePath);
        }

        $this->entityManager->clear();
        $this->entityManager->getConnection()->close();

        parent::tearDown();
        self::ensureKernelShutdown();
    }

    public function testPollingCommandImportsMessagesFromImapServer(): void
    {
        $port = $this->reservePort();
        $this->fixturePath = tempnam(sys_get_temp_dir(), 'driftpunkt-imap-');
        file_put_contents($this->fixturePath, json_encode([
            [
                'raw_message' => <<<EOM
From: Inez Imap <customer@imap.test>
Subject: IMAP-incident
MIME-Version: 1.0
Content-Type: multipart/mixed; boundary="BOUNDARY-123"

--BOUNDARY-123
Content-Type: text/plain; charset=UTF-8

Det här kom från fake-IMAP.
--BOUNDARY-123
Content-Type: text/plain; name="imap-log.txt"
Content-Disposition: attachment; filename="imap-log.txt"
Content-Transfer-Encoding: base64

SU1BUCBiaWxhZ2EK
--BOUNDARY-123--
EOM,
            ],
        ], JSON_THROW_ON_ERROR));

        $this->startFakeImapServer($port, $this->fixturePath);
        $mailbox = $this->createImapMailboxFixture($port);

        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:mail:poll');
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'IMAP-incident']);
        self::assertNotNull($ticket);

        $incomingMail = $this->entityManager->getRepository(IncomingMail::class)->findOneBy(['subject' => 'IMAP-incident']);
        self::assertNotNull($incomingMail);
        self::assertCount(1, $incomingMail->getAttachmentMetadata());

        $comment = $this->entityManager->getRepository(TicketComment::class)->findOneBy(['ticket' => $ticket], ['id' => 'ASC']);
        self::assertNotNull($comment);
        self::assertCount(1, $comment->getAttachments());

        $this->entityManager->clear();
        $mailbox = $this->entityManager->getRepository(SupportMailbox::class)->find($mailbox->getId());
        self::assertNotNull($mailbox);
        self::assertNotNull($mailbox->getLastPolledAt());
    }

    private function createImapMailboxFixture(int $port): SupportMailbox
    {
        $company = new Company('IMAP AB');
        $customer = new User('customer@imap.test', 'Inez', 'Imap', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $server = new MailServer('IMAP Server', MailServerDirection::INCOMING, '127.0.0.1', $port);
        $server
            ->setTransportType('imap')
            ->setEncryption(MailEncryption::NONE)
            ->setUsername('imap-user')
            ->setPassword('imap-pass');

        $mailbox = new SupportMailbox('IMAP Inbox', 'imap@example.test');
        $mailbox->setCompany($company)->setIncomingServer($server);

        $this->entityManager->persist($company);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($server);
        $this->entityManager->persist($mailbox);
        $this->entityManager->flush();

        return $mailbox;
    }

    private function reservePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (!\is_resource($socket)) {
            self::fail(sprintf('Kunde inte reservera testport: %s (%d)', $errorMessage, $errorCode));
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr((string) $name, strrpos((string) $name, ':') + 1);
    }

    private function startFakeImapServer(int $port, string $fixturePath): void
    {
        $command = sprintf(
            'php %s %d %s',
            escapeshellarg(dirname(__DIR__).'/Fixtures/fake_imap_server.php'),
            $port,
            escapeshellarg($fixturePath),
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $this->serverProcess = proc_open($command, $descriptorSpec, $this->pipes, dirname(__DIR__, 2));
        if (!\is_resource($this->serverProcess)) {
            self::fail('Kunde inte starta fake IMAP-servern.');
        }

        usleep(300000);
    }

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath);
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }
}
