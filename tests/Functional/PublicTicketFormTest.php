<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PublicTicketFormTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private SystemSettings $systemSettings;

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
        $this->entityManager->clear();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname(__DIR__, 2).'/var/test_public_ticket_uploads');
        $this->entityManager->clear();
        $this->entityManager->getConnection()->close();

        parent::tearDown();
        self::ensureKernelShutdown();
    }

    public function testPublicTicketFormIsHiddenAndUnavailableByDefault(): void
    {
        self::assertFalse($this->systemSettings->getPublicTicketFormSettings()['enabled']);

        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('a[href="/skapa-arende"]')->count());

        $this->client->request('GET', '/skapa-arende');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/skapa-arende', [
            'subject' => 'Publikt ärende ska inte skapas',
            'summary' => 'Formuläret är avstängt som standard.',
            'name' => 'Publik Besökare',
            'email' => 'public@example.test',
        ]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->entityManager->getRepository(Ticket::class)->count([]));
    }

    public function testSuperAdminCanTogglePublicTicketForm(): void
    {
        $superAdmin = $this->createAdminUserWithType('super-admin-public-ticket@example.test', UserType::SUPER_ADMIN);
        $this->client->loginUser($superAdmin);

        $crawler = $this->client->request('GET', '/portal/admin/settings-content');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Publikt ärendeformulär', (string) $crawler->html());

        $form = $crawler->selectButton('Spara publikt ärendeformulär')->form([
            'public_ticket_form_enabled' => '1',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/settings-content');
        $this->client->followRedirect();

        self::assertTrue($this->systemSettings->getPublicTicketFormSettings()['enabled']);
    }

    public function testRegularAdminCannotTogglePublicTicketForm(): void
    {
        $admin = $this->createAdminUserWithType('admin-public-ticket@example.test', UserType::ADMIN);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/settings-content');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Publikt ärendeformulär', (string) $crawler->html());

        $this->client->request('POST', '/portal/admin/public-ticket-form', [
            '_token' => 'invalid-token',
            'public_ticket_form_enabled' => '1',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->systemSettings->getPublicTicketFormSettings()['enabled']);
    }

    public function testPublicVisitorCanCreateStandaloneTicket(): void
    {
        $this->systemSettings->setBool(SystemSettings::FEATURE_PUBLIC_TICKET_FORM_ENABLED, true);

        $crawler = $this->client->request('GET', '/skapa-arende');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Skapa ärende', (string) $crawler->html());

        $form = $crawler->filter('form[action="/skapa-arende"]')->form([
            'name' => 'Petra Publik',
            'email' => 'petra.public@example.test',
            'phone' => '+46 70 123 45 67',
            'subject' => 'Kan inte logga in',
            'summary' => 'Inloggningen avbryts direkt efter BankID.',
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
        ]);
        $this->client->submit($form);

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Ärendet har skapats', $html);
        self::assertStringNotContainsString('registrerad kund', mb_strtolower($html));

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Kan inte logga in']);
        self::assertNotNull($ticket);
        self::assertStringNotContainsString($ticket->getReference(), $html);
        self::assertSame(TicketStatus::NEW, $ticket->getStatus());
        self::assertSame(TicketVisibility::PRIVATE, $ticket->getVisibility());
        self::assertNull($ticket->getRequester());
        self::assertNull($ticket->getCompany());
        self::assertStringContainsString('Publik kontakt:', $ticket->getSummary());
        self::assertStringContainsString('Petra Publik', $ticket->getSummary());
        self::assertStringContainsString('petra.public@example.test', $ticket->getSummary());
        self::assertStringContainsString('+46 70 123 45 67', $ticket->getSummary());
    }

    public function testPublicTicketLinksExistingCustomerByEmailWithoutRevealingMatch(): void
    {
        $this->systemSettings->setBool(SystemSettings::FEATURE_PUBLIC_TICKET_FORM_ENABLED, true);

        $company = new Company('Kundbolaget AB');
        $company
            ->setUseCustomTicketSequence(true)
            ->setTicketReferencePrefix('KUND')
            ->setTicketSequenceNextNumber(44);
        $customer = new User('kund@example.test', 'Klara', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);
        $this->entityManager->persist($company);
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/skapa-arende');
        $form = $crawler->filter('form[action="/skapa-arende"]')->form([
            'name' => 'Extern text ska sparas',
            'email' => 'KUND@example.test',
            'subject' => 'Kundkopplat publikt ärende',
            'summary' => 'Beskrivning från publik sida.',
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::TEAM->value,
        ]);
        $this->client->submit($form);

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Ärendet har skapats', $html);
        self::assertStringNotContainsString('kund@example.test är registrerad', mb_strtolower($html));

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Kundkopplat publikt ärende']);
        self::assertNotNull($ticket);
        self::assertSame('KUND-0044', $ticket->getReference());
        self::assertStringNotContainsString($ticket->getReference(), $html);
        self::assertStringNotContainsString('KUND-', $html);
        self::assertSame($customer->getId(), $ticket->getRequester()?->getId());
        self::assertSame($company->getId(), $ticket->getCompany()?->getId());
        self::assertStringContainsString('Publik kontakt:', $ticket->getSummary());
    }

    public function testPublicTicketFormValidatesRequiredFieldsAndEmail(): void
    {
        $this->systemSettings->setBool(SystemSettings::FEATURE_PUBLIC_TICKET_FORM_ENABLED, true);

        $crawler = $this->client->request('GET', '/skapa-arende');
        $form = $crawler->filter('form[action="/skapa-arende"]')->form([
            'name' => '',
            'email' => 'inte-en-epost',
            'subject' => '',
            'summary' => '',
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Kontrollera namn, e-post, ämne och beskrivning.', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $this->entityManager->getRepository(Ticket::class)->count([]));
    }

    public function testPublicTicketFormHoneypotDoesNotCreateTicket(): void
    {
        $this->systemSettings->setBool(SystemSettings::FEATURE_PUBLIC_TICKET_FORM_ENABLED, true);

        $crawler = $this->client->request('GET', '/skapa-arende');
        $form = $crawler->filter('form[action="/skapa-arende"]')->form([
            'name' => 'Bot Test',
            'email' => 'bot@example.test',
            'subject' => 'Spam',
            'summary' => 'Spam.',
            'website' => 'https://spam.example.test',
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager->getRepository(Ticket::class)->count([]));
    }

    public function testPublicTicketFormRejectsInvalidCsrfWithoutCreatingTicket(): void
    {
        $this->systemSettings->setBool(SystemSettings::FEATURE_PUBLIC_TICKET_FORM_ENABLED, true);

        $this->client->request('POST', '/skapa-arende', [
            '_token' => 'invalid-token',
            'name' => 'Csrf Test',
            'email' => 'csrf@example.test',
            'subject' => 'Ogiltig csrf',
            'summary' => 'Det här ska inte bli ett ärende.',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager->getRepository(Ticket::class)->count([]));
    }

    public function testPublicTicketFormHandlesMalformedArrayInputWithoutServerError(): void
    {
        $this->systemSettings->setBool(SystemSettings::FEATURE_PUBLIC_TICKET_FORM_ENABLED, true);

        $crawler = $this->client->request('GET', '/skapa-arende');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/skapa-arende', [
            '_token' => $token,
            'name' => ['Array Test'],
            'email' => 'array@example.test',
            'subject' => 'Fel format',
            'summary' => 'Parametern name skickades som array.',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager->getRepository(Ticket::class)->count([]));
    }

    public function testPublicTicketFormRejectsTooLongExternalAttachmentUrl(): void
    {
        $this->systemSettings->setBool(SystemSettings::FEATURE_PUBLIC_TICKET_FORM_ENABLED, true);
        $this->systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ENABLED, true);
        $this->systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_EXTERNAL_ENABLED, true);

        $crawler = $this->client->request('GET', '/skapa-arende');
        $form = $crawler->filter('form[action="/skapa-arende"]')->form([
            'name' => 'Extern Länk',
            'email' => 'extern@example.test',
            'subject' => 'För lång extern länk',
            'summary' => 'Se extern länk.',
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            'external_attachment_url' => 'https://example.test/'.str_repeat('a', 1100),
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Delningslänken får vara högst 1000 tecken.', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $this->entityManager->getRepository(Ticket::class)->count([]));
    }

    public function testHomepageShowsPublicTicketLinkWhenEnabled(): void
    {
        $this->systemSettings->setBool(SystemSettings::FEATURE_PUBLIC_TICKET_FORM_ENABLED, true);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('a[href="/skapa-arende"]')->count());
    }

    public function testPublicTicketCanIncludeAttachmentWhenEnabled(): void
    {
        $this->systemSettings->setBool(SystemSettings::FEATURE_PUBLIC_TICKET_FORM_ENABLED, true);
        $this->systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ENABLED, true);
        $this->systemSettings->setInt(SystemSettings::TICKET_ATTACHMENTS_MAX_UPLOAD_MB, 5);
        $this->systemSettings->setString(SystemSettings::TICKET_ATTACHMENTS_STORAGE_PATH, 'var/test_public_ticket_uploads');
        $this->systemSettings->setString(SystemSettings::TICKET_ATTACHMENTS_ALLOWED_EXTENSIONS, 'txt,log,png');

        $admin = $this->createAdminUserWithType('existing-admin-public-attachment@example.test', UserType::SUPER_ADMIN);

        $uploadPath = dirname(__DIR__, 2).'/var/public-ticket-underlag.txt';
        file_put_contents($uploadPath, 'Publikt underlag.');

        try {
            $crawler = $this->client->request('GET', '/skapa-arende');
            $form = $crawler->filter('form[action="/skapa-arende"]')->form([
                'name' => 'Bilaga Publik',
                'email' => 'bilaga-public@example.test',
                'subject' => 'Publik bilaga',
                'summary' => 'Se bifogad logg.',
                'request_type' => TicketRequestType::INCIDENT->value,
                'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            ]);
            $form['attachment']->upload($uploadPath);
            $this->client->submit($form);

            self::assertResponseIsSuccessful();

            $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Publik bilaga']);
            self::assertNotNull($ticket);
            self::assertCount(1, $ticket->getComments());
            $comment = $ticket->getComments()->first();
            self::assertSame('Bilagor bifogades när ärendet skapades.', $comment->getBody());
            self::assertSame('public-ticket@driftpunkt.local', $comment->getAuthor()->getEmail());
            self::assertSame(UserType::TECHNICIAN, $comment->getAuthor()->getType());
            self::assertFalse($comment->getAuthor()->isActive());
            self::assertNotSame($admin->getId(), $comment->getAuthor()->getId());
            self::assertCount(1, $comment->getAttachments());
            self::assertSame('public-ticket-underlag.txt', $comment->getAttachments()->first()->getDisplayName());
        } finally {
            @unlink($uploadPath);
            $this->removeDirectory(dirname(__DIR__, 2).'/var/test_public_ticket_uploads');
        }
    }

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }

    private function createAdminUserWithType(string $email, UserType $type): User
    {
        $admin = new User($email, 'Ada', 'Admin', $type);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'AdminPassword123'));
        $admin->enableMfa();

        $this->entityManager->persist($admin);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($admin);

        return $admin;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $fullPath = $path.\DIRECTORY_SEPARATOR.$item;
            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath);
                continue;
            }

            @unlink($fullPath);
        }

        @rmdir($path);
    }
}
