<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TicketImportExportTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->client->disableReboot();
    }

    public function testAdminCsvExportContainsClosedAtColumnAndValue(): void
    {
        $admin = new User('admin@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'AdminPassword123'));

        $ticket = new Ticket(
            'DP-9001',
            'Stängt ärende',
            'Test för export',
            TicketStatus::CLOSED,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
        );
        $ticket->setClosedAt(new \DateTimeImmutable('2026-04-18 12:34:00'));

        $this->entityManager->persist($admin);
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/import-export/arendeexport/csv');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Stängd', $content);
        self::assertStringContainsString('2026-04-18 12:34', $content);
    }

    public function testAdminCanPreviewUploadedSharepointCsvWithoutClientPayload(): void
    {
        $admin = new User('admin@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'AdminPassword123'));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $path = tempnam(sys_get_temp_dir(), 'sharepoint-import-');
        self::assertIsString($path);
        file_put_contents($path, "\xEF\xBB\xBF\"Ärende ID\",\"Datum\",\"Beskrivning av problem\",\"Namn\",\"Ansvarig Tekniker\",\"Klart  datum\",\"Status\",\"Prio\",\"Ev. kommentar\",\"Åtgärd\"\n\"2\",\"2025-08-06\",\"Hei !\nKan dere bistå med å sjekke stasjonær pc ?\",\"Gisle\",\"Paul\",,\"Avslutad\",\"Mellan prio\",\"Ringt och mailat utan svar\",\"Fjärrstyrt och rensat disk\"\n");

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/import-export/arendeimport');
        $token = (string) $this->client->getCrawler()->filter('input[name="_token"]')->attr('value');

        $this->client->request(
            'POST',
            '/portal/admin/import-export/arendeimport/forhandsgranska',
            [
                '_token' => $token,
                'import_source_system' => 'sharepoint',
                'import_csv_payload' => '',
                'status' => TicketStatus::NEW->value,
                'visibility' => TicketVisibility::PRIVATE->value,
                'request_type' => TicketRequestType::INCIDENT->value,
                'impact_level' => TicketImpactLevel::SINGLE_USER->value,
                'priority' => TicketPriority::NORMAL->value,
            ],
            [
                'import_csv_file' => new UploadedFile($path, 'Åtgärdslistan.csv', 'text/csv', null, true),
            ],
        );

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Torrkörning och förhandsgranskning', $content);
        self::assertStringContainsString('Hei !', $content);
        self::assertStringContainsString('SharePoint / 2', $content);
    }
}
