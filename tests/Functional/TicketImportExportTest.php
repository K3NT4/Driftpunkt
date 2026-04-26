<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Ticket\Entity\ImportedTicketPerson;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\ImportedTicketPersonRole;
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

    public function testAdminCsvImportCreatesShadowPeopleWhenSharepointNamesDoNotMatchUsers(): void
    {
        $admin = new User('admin@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'AdminPassword123'));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/import-export/arendeimport');
        $token = (string) $this->client->getCrawler()->filter('input[name="_token"]')->attr('value');

        $this->client->request(
            'POST',
            '/portal/technician/tickets',
            $this->sharepointImportRequest($token, $this->sharepointCsvPayload()),
            [],
            ['HTTP_REFERER' => '/portal/admin/import-export/arendeimport'],
        );

        self::assertResponseRedirects('/portal/admin/import-export/arendeimport');

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy([
            'subject' => 'Importerat CSV-ärende från SharePoint',
        ]);
        self::assertInstanceOf(Ticket::class, $ticket);
        self::assertNull($ticket->getRequester());
        self::assertNull($ticket->getAssignee());
        self::assertSame('Gisle', $ticket->getImportedRequesterPerson()?->getDisplayName());
        self::assertSame('Paul', $ticket->getImportedAssigneePerson()?->getDisplayName());
        self::assertSame('Gisle', $ticket->getRequesterDisplayName());
        self::assertSame('Paul', $ticket->getAssigneeDisplayName());
    }

    public function testAdminCsvImportLinksUniqueExistingRequesterAndAssigneeByDisplayName(): void
    {
        $admin = new User('admin@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'AdminPassword123'));
        $requester = new User('gisle@example.test', 'Gisle', '', UserType::PRIVATE_CUSTOMER);
        $assignee = new User('paul@example.test', 'Paul', '', UserType::TECHNICIAN);

        $this->entityManager->persist($admin);
        $this->entityManager->persist($requester);
        $this->entityManager->persist($assignee);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/import-export/arendeimport');
        $token = (string) $this->client->getCrawler()->filter('input[name="_token"]')->attr('value');

        $this->client->request(
            'POST',
            '/portal/technician/tickets',
            $this->sharepointImportRequest($token, $this->sharepointCsvPayload()),
            [],
            ['HTTP_REFERER' => '/portal/admin/import-export/arendeimport'],
        );

        self::assertResponseRedirects('/portal/admin/import-export/arendeimport');

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy([
            'subject' => 'Importerat CSV-ärende från SharePoint',
        ]);
        self::assertInstanceOf(Ticket::class, $ticket);
        self::assertSame($requester->getId(), $ticket->getRequester()?->getId());
        self::assertSame($assignee->getId(), $ticket->getAssignee()?->getId());
        self::assertTrue($ticket->getImportedRequesterPerson()?->isLinked());
        self::assertTrue($ticket->getImportedAssigneePerson()?->isLinked());
        self::assertSame(ImportedTicketPersonRole::REQUESTER, $ticket->getImportedRequesterPerson()?->getRole());
        self::assertSame(ImportedTicketPersonRole::ASSIGNEE, $ticket->getImportedAssigneePerson()?->getRole());
    }

    public function testAdminCsvPreviewShowsShadowPeopleBeforeImport(): void
    {
        $admin = new User('admin@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'AdminPassword123'));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/import-export/arendeimport');
        $token = (string) $this->client->getCrawler()->filter('input[name="_token"]')->attr('value');

        $this->client->request(
            'POST',
            '/portal/admin/import-export/arendeimport/forhandsgranska',
            $this->sharepointImportRequest($token, $this->sharepointCsvPayload()),
        );

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Gisle', $content);
        self::assertStringContainsString('Paul', $content);
        self::assertStringContainsString('Skuggperson', $content);
    }

    public function testAdminCanLinkShadowAssigneeToExistingTechnician(): void
    {
        $admin = new User('admin@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'AdminPassword123'));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/import-export/arendeimport');
        $createToken = (string) $this->client->getCrawler()->filter('input[name="_token"]')->attr('value');
        $this->client->request(
            'POST',
            '/portal/technician/tickets',
            $this->sharepointImportRequest($createToken, $this->sharepointCsvPayload()),
            [],
            ['HTTP_REFERER' => '/portal/admin/import-export/arendeimport'],
        );

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy([
            'subject' => 'Importerat CSV-ärende från SharePoint',
        ]);
        self::assertInstanceOf(Ticket::class, $ticket);
        $person = $ticket->getImportedAssigneePerson();
        self::assertInstanceOf(ImportedTicketPerson::class, $person);

        $technician = new User('paul@example.test', 'Paul', '', UserType::TECHNICIAN);
        $this->entityManager->persist($technician);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/portal/admin/import-export/arendeimport');
        $linkForm = $crawler->filter(sprintf('form[data-imported-person-link-form="%d"]', $person->getId()));
        self::assertCount(1, $linkForm);
        $linkToken = (string) $linkForm->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', sprintf('/portal/admin/import-export/skuggpersoner/%d/koppla', $person->getId()), [
            '_token' => $linkToken,
            'user_id' => (string) $technician->getId(),
        ]);

        self::assertResponseRedirects('/portal/admin/import-export/arendeimport');
        $updatedTicket = $this->entityManager->getRepository(Ticket::class)->find($ticket->getId());
        $updatedPerson = $this->entityManager->getRepository(ImportedTicketPerson::class)->find($person->getId());
        self::assertInstanceOf(Ticket::class, $updatedTicket);
        self::assertInstanceOf(ImportedTicketPerson::class, $updatedPerson);
        self::assertSame($technician->getId(), $updatedTicket->getAssignee()?->getId());
        self::assertSame($technician->getId(), $updatedPerson->getLinkedUser()?->getId());
        self::assertTrue($updatedPerson->isLinked());
    }

    /**
     * @return array<string, mixed>
     */
    private function sharepointCsvPayload(): array
    {
        return [
            'filename' => 'Åtgärdslistan.csv',
            'delimiter' => ',',
            'headers' => ['Ärende ID', 'Datum', 'Beskrivning av problem', 'Namn', 'Ansvarig Tekniker', 'Status', 'Prio'],
            'rows' => [[
                'Ärende ID' => '2',
                'Datum' => '2025-08-06',
                'Beskrivning av problem' => 'Hei ! Kan dere bistå med å sjekke stasjonær pc ?',
                'Namn' => 'Gisle',
                'Ansvarig Tekniker' => 'Paul',
                'Status' => 'Avslutad',
                'Prio' => 'Mellan prio',
            ]],
            'fieldMapping' => [
                'reference' => 'Ärende ID',
                'event_date' => 'Datum',
                'summary' => 'Beskrivning av problem',
                'requester_name' => 'Namn',
                'assignee_name' => 'Ansvarig Tekniker',
                'status' => 'Status',
                'priority' => 'Prio',
            ],
            'rowTargets' => ['0' => 'ticket'],
        ];
    }

    /**
     * @param array<string, mixed> $csvPayload
     * @return array<string, mixed>
     */
    private function sharepointImportRequest(string $token, array $csvPayload): array
    {
        return [
            '_token' => $token,
            'subject' => '',
            'summary' => '',
            'import_source_system' => 'sharepoint',
            'import_csv_payload' => json_encode($csvPayload, \JSON_THROW_ON_ERROR),
            'duplicate_strategy' => 'warn',
            'status' => TicketStatus::NEW->value,
            'visibility' => TicketVisibility::PRIVATE->value,
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            'priority' => TicketPriority::NORMAL->value,
            'escalation_level' => 'none',
            'company_id' => '',
            'requester_id' => '',
            'assignee_id' => '',
            'assigned_team_id' => '',
            'sla_policy_id' => '',
        ];
    }
}
