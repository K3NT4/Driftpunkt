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
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketComment;
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

final class IncomingMailCommandTest extends WebTestCase
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

    public function testCommandCreatesNewTicketFromKnownSender(): void
    {
        [$mailbox, $customer] = $this->createMailboxFixture();

        $tester = $this->commandTester();
        $tester->execute([
            'mailbox' => $mailbox->getEmailAddress(),
            'from' => $customer->getEmail(),
            'subject' => 'Ny incident från kund',
            'body' => 'VPN går ner var 10:e minut.',
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Ny incident från kund']);
        self::assertNotNull($ticket);
        self::assertSame($customer->getId(), $ticket->getRequester()?->getId());

        $mail = $this->entityManager->getRepository(IncomingMail::class)->findOneBy(['subject' => 'Ny incident från kund']);
        self::assertNotNull($mail);
        self::assertSame(IncomingMailProcessingResult::TICKET_CREATED, $mail->getProcessingResult());
        self::assertEmailCount(1);
        $email = $this->getMailerMessage();
        self::assertEmailTextBodyContains($email, 'Ärendenummer: '.$ticket->getReference());
    }

    public function testCommandAppendsReplyToMatchedTicketWhenSenderIsAuthorized(): void
    {
        [$mailbox, $customer, $technician] = $this->createMailboxFixture();
        $ticket = new Ticket(
            'DP-1001',
            'Ursprungligt ärende',
            'Skapad sedan tidigare.',
            TicketStatus::PENDING_CUSTOMER,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
        );
        $ticket->setRequester($customer)->setCompany($customer->getCompany())->setAssignee($technician);
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        $tester = $this->commandTester();
        $tester->execute([
            'mailbox' => $mailbox->getName(),
            'from' => $customer->getEmail(),
            'subject' => 'Re: [DP-1001] Ursprungsärende',
            'body' => 'Här kommer mer information från kunden.',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        $this->entityManager->clear();

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['reference' => 'DP-1001']);
        self::assertNotNull($ticket);

        $comments = $this->entityManager->getRepository(TicketComment::class)->findBy(['ticket' => $ticket], ['id' => 'DESC']);
        self::assertNotEmpty($comments);
        self::assertSame('Här kommer mer information från kunden.', $comments[0]->getBody());
        self::assertSame(TicketStatus::OPEN, $ticket->getStatus());
        self::assertEmailCount(1);
    }

    public function testCommandCreatesDraftReviewForUnknownSender(): void
    {
        [$mailbox] = $this->createMailboxFixture();

        $tester = $this->commandTester();
        $tester->execute([
            'mailbox' => $mailbox->getEmailAddress(),
            'from' => 'unknown@example.test',
            'subject' => 'Nytt problem från okänd avsändare',
            'body' => 'Ingen användare finns registrerad än.',
        ]);

        self::assertSame(0, $tester->getStatusCode());

        $review = $this->entityManager->getRepository(DraftTicketReview::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($review);
        self::assertSame('Okänd avsändare kräver manuell granskning innan ticket kan kopplas.', $review->getReason());
        self::assertNotNull($review->getDraftTicket());
        self::assertSame(TicketVisibility::INTERNAL_ONLY, $review->getDraftTicket()?->getVisibility());
    }

    private function commandTester(): CommandTester
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        $command = $application->find('app:mail:ingest');

        return new CommandTester($command);
    }

    /**
     * @return array{SupportMailbox, User, User}
     */
    private function createMailboxFixture(): array
    {
        $company = new Company('Acme AB');
        $server = new MailServer('Inbound Mail', MailServerDirection::BOTH, 'mail.acme.test', 587);
        $mailbox = new SupportMailbox('Acme Support', 'support@acme.test');
        $mailbox->setCompany($company)->setIncomingServer($server);

        $customer = new User('customer@acme.test', 'Karin', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $technician = new User('tech@acme.test', 'Tess', 'Tech', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'TechPassword123'));
        $technician->enableMfa();

        $this->entityManager->persist($company);
        $this->entityManager->persist($server);
        $this->entityManager->persist($mailbox);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($technician);
        $this->entityManager->flush();

        return [$mailbox, $customer, $technician];
    }

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath);
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }
}
