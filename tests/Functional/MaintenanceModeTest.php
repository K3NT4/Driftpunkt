<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Maintenance\Service\MaintenanceMode;
use App\Module\News\Entity\NewsArticle;
use App\Module\News\Enum\NewsCategory;
use App\Module\System\Service\SystemSettings;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MaintenanceModeTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private MaintenanceMode $maintenanceMode;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $this->maintenanceMode = $container->get(MaintenanceMode::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $this->maintenanceMode->disable();
    }

    protected function tearDown(): void
    {
        $this->maintenanceMode->disable();
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

    public function testUpcomingMaintenanceNoticeIsShownAcrossPublicPages(): void
    {
        static::getContainer()->get(SystemSettings::class)->setInt(SystemSettings::MAINTENANCE_NOTICE_LOOKAHEAD_DAYS, 3);

        $article = new NewsArticle(
            'Planerat underhåll i kundportalen',
            'Vi uppdaterar inloggning och ärendevyer under kvällstid.',
            'Detaljerad driftinformation.',
        );
        $article
            ->setCategory(NewsCategory::PLANNED_MAINTENANCE)
            ->setMaintenanceStartsAt(new \DateTimeImmutable('+2 days 22:00'))
            ->setMaintenanceEndsAt(new \DateTimeImmutable('+2 days 23:30'));

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $homeCrawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Planerat underhåll i kundportalen', $homeCrawler->html());
        self::assertStringContainsString('Planerat underhåll: <strong>1 kommande</strong>', $homeCrawler->html());

        $contactCrawler = $this->client->request('GET', '/kontakta-oss');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Planerat underhåll i kundportalen', $contactCrawler->html());
        self::assertStringContainsString('Las driftinfo', $contactCrawler->html());
    }

    public function testAdminCanScheduleMaintenanceAndStatusPageShowsIt(): void
    {
        $admin = new User('maintenance-admin@example.test', 'Mira', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Supersakert123'));
        $admin->enableMfa();
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/settings');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Spara underhållsläge')->form([
            'maintenance_message' => 'Planerat databasunderhåll under kvällen.',
            'maintenance_scheduled_start_at' => '2026-04-20T22:00',
            'maintenance_scheduled_end_at' => '2026-04-20T23:30',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/settings');
        $state = $this->maintenanceMode->getState();
        self::assertFalse($state['enabled']);
        self::assertSame('Planerat databasunderhåll under kvällen.', $state['message']);
        self::assertIsString($state['scheduledStartAt']);
        self::assertStringContainsString('2026-04-20T22:00', (string) $state['scheduledStartAt']);

        $statusCrawler = $this->client->request('GET', '/portal/admin/status');
        self::assertResponseIsSuccessful();

        $statusForm = $statusCrawler->selectButton('Spara driftstatus')->form([
            'status_monitor_lines' => "manual | Kortterminal | https://status.example.test | Kortinlösen via extern partner | Visa driftstatus | /driftstatus | check",
            'status_page_show_system_checked_at' => '1',
            'status_page_show_system_source' => '1',
            'status_page_show_recent_updates' => '1',
            'status_page_recent_updates_title' => 'Senaste handelser',
            'status_page_recent_updates_intro' => 'Alla nya driftmeddelanden samlas har.',
            'status_page_recent_updates_max_items' => '3',
            'status_page_show_impact' => '1',
            'status_page_impact_title' => 'Detta paverkas',
            'status_page_impact_intro' => 'Admin styr vad som syns for kunder och tekniker.',
            'status_page_impact_lines' => "Kundlogin | Tillganglig | Planerat arbete | Pausad | Kundportalen kan paverkas under driftfonstret.\nSupport | Oppet | Oppet | Oppet | Kontaktsidan ar alltid oppen.",
            'status_page_show_history' => '1',
            'status_page_show_subscribe_box' => '1',
            'status_page_subscribe_title' => 'Folj fler uppdateringar',
            'status_page_subscribe_text' => 'Las nyheter eller peka vidare till extern statussida.',
            'status_page_subscribe_link_label' => 'Se nyheter',
            'status_page_subscribe_link_url' => '/nyheter',
        ]);
        $this->client->submit($statusForm);

        self::assertResponseRedirects('/portal/admin/status');

        self::ensureKernelShutdown();
        $publicClient = static::createClient();
        $statusCrawler = $publicClient->request('GET', '/driftstatus');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Planerat databasunderhåll under kvällen.', $statusCrawler->html());
        self::assertStringContainsString('2026-04-20 22:00 - 2026-04-20 23:30', $statusCrawler->html());
        self::assertStringContainsString('Kortterminal', $statusCrawler->html());
        self::assertStringContainsString('Kortinlösen via extern partner', $statusCrawler->html());
        self::assertStringContainsString('Senast kontrollerad:', $statusCrawler->html());
        self::assertStringContainsString('Källa:', $statusCrawler->html());
        self::assertStringContainsString('Detta paverkas', $statusCrawler->html());
        self::assertStringContainsString('Planerat arbete', $statusCrawler->html());
        self::assertStringContainsString('Folj fler uppdateringar', $statusCrawler->html());

        $homeCrawler = $publicClient->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Kortterminal', $homeCrawler->html());
    }

    public function testMaintenanceModeBlocksTechniciansButKeepsAdminAreaReachable(): void
    {
        $admin = new User('admin-maint@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Supersakert123'));
        $admin->enableMfa();

        $technician = new User('tech-maint@example.test', 'Ture', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'Supersakert123'));
        $technician->enableMfa();

        $this->entityManager->persist($admin);
        $this->entityManager->persist($technician);
        $this->entityManager->flush();

        $this->maintenanceMode->enable('Planerat arbete pågår just nu.');

        $this->client->loginUser($technician);
        $this->client->request('GET', '/portal/technician');
        self::assertResponseStatusCodeSame(503);
        self::assertStringContainsString('Kund- och teknikerinloggning är pausad', (string) $this->client->getResponse()->getContent());

        self::ensureKernelShutdown();
        $adminClient = static::createClient();
        $adminClient->loginUser($admin);
        $adminClient->request('GET', '/portal');
        self::assertResponseRedirects('/portal/admin');
        $adminClient->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Underhallslage aktivt', (string) $adminClient->getResponse()->getContent());

        self::ensureKernelShutdown();
        $publicClient = static::createClient();
        $homeCrawler = $publicClient->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Välkommen till Driftpunkt', $homeCrawler->html());
        self::assertStringContainsString('Underhåll pågår just nu.', $homeCrawler->html());

        $contactCrawler = $publicClient->request('GET', '/kontakta-oss');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Kontakta', $contactCrawler->html());

        $publicClient->request('GET', '/driftstatus');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Planerat arbete pågår just nu.', (string) $publicClient->getResponse()->getContent());
    }

    public function testCustomerAndTechnicianLoginViewsAreLockedDuringMaintenance(): void
    {
        $this->maintenanceMode->enable('Planerat arbete pågår just nu.');

        $customerCrawler = $this->client->request('GET', '/login?role=customer');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Inloggningen är tillfälligt pausad', $customerCrawler->html());
        self::assertStringContainsString('Inloggning pausad under underhåll', $customerCrawler->html());

        $adminCrawler = $this->client->request('GET', '/login?role=admin');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Inloggning pausad under underhåll', $adminCrawler->html());
        self::assertStringContainsString('Logga in', $adminCrawler->html());
    }

    public function testScheduledActiveMaintenanceLocksCustomerLogin(): void
    {
        $this->maintenanceMode->updateSettings(
            false,
            'Schemalagt fönster pågår just nu.',
            new \DateTimeImmutable('-10 minutes'),
            new \DateTimeImmutable('+20 minutes'),
        );

        $customerCrawler = $this->client->request('GET', '/login?role=customer');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Schemalagt fönster pågår just nu.', $customerCrawler->html());
        self::assertStringContainsString('Inloggning pausad under underhåll', $customerCrawler->html());
    }

    public function testMaintenanceModeLogsOutAuthenticatedCustomerAndTechnicianUsers(): void
    {
        $customer = new User('customer-maint@example.test', 'Klara', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'Supersakert123'));
        $customer->enableMfa();

        $technician = new User('tech-logout@example.test', 'Ture', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'Supersakert123'));
        $technician->enableMfa();

        $this->entityManager->persist($customer);
        $this->entityManager->persist($technician);
        $this->entityManager->flush();

        $this->client->loginUser($customer);
        $this->maintenanceMode->enable('Akut underhåll pågår.');

        $homeCrawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Välkommen till Driftpunkt', $homeCrawler->html());

        $customerLoginCrawler = $this->client->request('GET', '/login?role=customer');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Inloggning pausad under underhåll', $customerLoginCrawler->html());

        self::ensureKernelShutdown();
        $technicianClient = static::createClient();
        $technicianClient->loginUser($technician);
        $technicianClient->request('GET', '/portal/technician');
        self::assertResponseStatusCodeSame(503);

        $technicianLoginCrawler = $technicianClient->request('GET', '/login?role=technician');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Inloggning pausad under underhåll', $technicianLoginCrawler->html());
    }
}
