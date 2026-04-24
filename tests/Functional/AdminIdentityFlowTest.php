<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\TechnicianTeam;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\KnowledgeBase\Entity\KnowledgeBaseEntry;
use App\Module\KnowledgeBase\Enum\KnowledgeBaseAudience;
use App\Module\KnowledgeBase\Enum\KnowledgeBaseEntryType;
use App\Module\Mail\Entity\MailServer;
use App\Module\Mail\Enum\MailServerDirection;
use App\Module\News\Entity\NewsArticle;
use App\Module\News\Enum\NewsCategory;
use App\Module\Notification\Entity\NotificationLog;
use App\Module\System\Entity\AddonModule;
use App\Module\System\Entity\AddonReleaseLog;
use App\Module\System\Entity\SystemSetting;
use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\TicketCategory;
use App\Module\Ticket\Entity\TicketIntakeField;
use App\Module\Ticket\Entity\TicketIntakeTemplate;
use App\Module\Ticket\Entity\TicketRoutingRule;
use App\Module\Ticket\Entity\SlaPolicy;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketIntakeFieldType;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminIdentityFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        $this->cleanupAdminMaintenanceArtifacts();
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
        $this->cleanupAdminMaintenanceArtifacts();

        parent::tearDown();
        self::ensureKernelShutdown();
    }

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }

    private function cleanupAdminMaintenanceArtifacts(): void
    {
        foreach ([
            dirname(__DIR__, 2).'/var/database_backups',
            dirname(__DIR__, 2).'/var/database_jobs',
            dirname(__DIR__, 2).'/var/database_restore_staging',
            dirname(__DIR__, 2).'/var/code_update_backups',
            dirname(__DIR__, 2).'/var/code_update_staging',
            dirname(__DIR__, 2).'/var/code_update_runs',
            dirname(__DIR__, 2).'/var/post_update_runs',
            dirname(__DIR__, 2).'/var/log',
            dirname(__DIR__, 2).'/var/addon_packages',
            dirname(__DIR__, 2).'/var/addon_package_staging',
            dirname(__DIR__, 2).'/public/assets/branding/custom-site-logo.png',
            dirname(__DIR__, 2).'/public/assets/branding/custom-site-logo.jpg',
            dirname(__DIR__, 2).'/public/assets/branding/custom-site-logo.jpeg',
            dirname(__DIR__, 2).'/public/assets/branding/custom-site-logo.webp',
            dirname(__DIR__, 2).'/public/assets/branding/custom-site-logo.gif',
        ] as $directory) {
            if (is_dir($directory)) {
                $this->removeDirectory($directory);

                continue;
            }

            @unlink($directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());

                continue;
            }

            @unlink($item->getPathname());
        }

        @rmdir($directory);
    }

    public function testAdminCanCreateCompanyAndCustomerUserThroughForms(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/identity');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Skapa företag', [
            'name' => 'Acme AB',
            'primary_email' => 'support@acme.test',
            'allow_shared_tickets' => '1',
        ]);

        self::assertResponseRedirects('/portal/admin/identity');
        $this->client->followRedirect();

        $company = $this->entityManager->getRepository(Company::class)->findOneBy(['name' => 'Acme AB']);
        self::assertNotNull($company);
        self::assertSame('support@acme.test', $company->getPrimaryEmail());
        self::assertTrue($company->allowsSharedTickets());

        $crawler = $this->client->request('GET', '/portal/admin/identity');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Skapa användare')->form([
            'first_name' => 'Karin',
            'last_name' => 'Kund',
            'email' => 'karin@acme.test',
            'password' => 'Supersakert123',
            'type' => UserType::CUSTOMER->value,
            'company_id' => (string) $company->getId(),
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/identity');
        $this->client->followRedirect();

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'karin@acme.test']);
        self::assertNotNull($user);
        self::assertSame('Karin Kund', $user->getDisplayName());
        self::assertSame(UserType::CUSTOMER, $user->getType());
        self::assertNotNull($user->getCompany());
        self::assertSame('Acme AB', $user->getCompany()?->getName());
        self::assertFalse($user->isMfaEnabled());
    }

    public function testAdminCanCreateSubsidiaryCompanyThroughForm(): void
    {
        $admin = $this->createAdminUserWithEmail('admin-company-tree@test.local');
        $parentCompany = new Company('HV AB');
        $this->entityManager->persist($parentCompany);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/identity');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Skapa företag', [
            'name' => 'Fika AB',
            'primary_email' => 'hej@fika.test',
            'parent_company_id' => (string) $parentCompany->getId(),
            'allow_shared_tickets' => '1',
            'allow_parent_company_access_to_shared_tickets' => '1',
        ]);

        self::assertResponseRedirects('/portal/admin/identity');
        $this->client->followRedirect();

        $childCompany = $this->entityManager->getRepository(Company::class)->findOneBy(['name' => 'Fika AB']);
        self::assertNotNull($childCompany);
        self::assertSame('HV AB', $childCompany->getParentCompany()?->getName());
        self::assertTrue($childCompany->allowsParentCompanyAccessToSharedTickets());
    }

    public function testAdminCanCreateTeamAndAssignTechnicianToIt(): void
    {
        $admin = $this->createAdminUser();
        $technician = new User('team-tech@example.test', 'Teo', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'InitialPassword123'));
        $technician->enableMfa();
        $this->entityManager->persist($technician);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/identity');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Skapa team', [
            'name' => 'NOC',
            'description' => 'Nätverk och övervakning',
        ]);

        self::assertResponseRedirects('/portal/admin/identity');
        $this->client->followRedirect();

        $team = $this->entityManager->getRepository(TechnicianTeam::class)->findOneBy(['name' => 'NOC']);
        self::assertNotNull($team);
        self::assertSame('Nätverk och övervakning', $team->getDescription());

        $crawler = $this->client->request('GET', '/portal/admin/identity');
        self::assertResponseIsSuccessful();

        $userForm = $crawler->filter(sprintf('form[action="/portal/admin/users/%d"]', $technician->getId()))->form([
            'first_name' => 'Teo',
            'last_name' => 'Tekniker',
            'email' => 'team-tech@example.test',
            'password' => '',
            'type' => UserType::TECHNICIAN->value,
            'company_id' => '',
            'technician_team_id' => (string) $team->getId(),
            'is_active' => '1',
            'mfa_enabled' => '1',
            'email_notifications_enabled' => '1',
        ]);
        $this->client->submit($userForm);

        self::assertResponseRedirects('/portal/admin/identity');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $technician = $this->entityManager->getRepository(User::class)->find($technician->getId());
        self::assertNotNull($technician);
        self::assertSame('NOC', $technician->getTechnicianTeam()?->getName());
    }

    public function testAdminIdentityShowsQrCodeForMfaEnabledUser(): void
    {
        $admin = $this->createAdminUser();
        $technician = new User('mfa-tech@example.test', 'Mira', 'MFA', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'InitialPassword123'));
        $technician->enableMfa();
        $this->entityManager->persist($technician);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/identity');
        self::assertResponseIsSuccessful();

        self::assertGreaterThan(0, $crawler->filter('img[alt="QR-kod för MFA till mfa-tech@example.test"]')->count());
        self::assertStringContainsString('Manuell kod:', $this->client->getResponse()->getContent() ?? '');

        $this->entityManager->clear();
        $technician = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'mfa-tech@example.test']);
        self::assertNotNull($technician);
        self::assertNotNull($technician->getMfaSecret());
    }

    public function testAdminCanUpdateCompanyAndTechnicianUserThroughForms(): void
    {
        $admin = $this->createAdminUserWithEmail('admin-update-company@test.local');
        $parentCompany = new Company('Parent Company');
        $company = new Company('Old Company');
        $company->setPrimaryEmail('old@company.test');

        $user = new User('tech@old.test', 'Tove', 'Tekniker', UserType::TECHNICIAN);
        $user->setCompany($company);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'InitialPassword123'));
        $user->enableMfa();

        $this->entityManager->persist($parentCompany);
        $this->entityManager->persist($company);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/identity');
        self::assertResponseIsSuccessful();

        $companyForm = $crawler->filter(sprintf('form[action="/portal/admin/companies/%d"]', $company->getId()))->form([
            'name' => 'New Company',
            'primary_email' => 'hello@new.test',
            'parent_company_id' => (string) $parentCompany->getId(),
            'allow_parent_company_access_to_shared_tickets' => '1',
            'is_active' => '1',
        ]);
        unset($companyForm['allow_shared_tickets']);
        $this->client->submit($companyForm);

        self::assertResponseRedirects('/portal/admin/identity');
        $this->client->followRedirect();

        $company = $this->entityManager->getRepository(Company::class)->find($company->getId());
        self::assertNotNull($company);
        self::assertSame('New Company', $company->getName());
        self::assertSame('hello@new.test', $company->getPrimaryEmail());
        self::assertSame('Parent Company', $company->getParentCompany()?->getName());
        self::assertTrue($company->allowsParentCompanyAccessToSharedTickets());
        self::assertFalse($company->allowsSharedTickets());
        self::assertTrue($company->isActive());

        $this->entityManager->clear();

        $crawler = $this->client->request('GET', '/portal/admin/identity');
        self::assertResponseIsSuccessful();

        $userForm = $crawler->filter(sprintf('form[action="/portal/admin/users/%d"]', $user->getId()))->form([
            'first_name' => 'Tom',
            'last_name' => 'Tekniker',
            'email' => 'tom@new.test',
            'password' => 'AnotherSecure123',
            'type' => UserType::ADMIN->value,
            'company_id' => '',
            'is_active' => '1',
        ]);
        unset($userForm['mfa_enabled']);
        $this->client->submit($userForm);

        self::assertResponseRedirects('/portal/admin/identity');
        $this->client->followRedirect();

        $user = $this->entityManager->getRepository(User::class)->find($user->getId());
        self::assertNotNull($user);
        self::assertSame('tom@new.test', $user->getEmail());
        self::assertSame('Tom Tekniker', $user->getDisplayName());
        self::assertSame(UserType::ADMIN, $user->getType());
        self::assertNull($user->getCompany());
        self::assertFalse($user->isMfaEnabled());
        self::assertTrue($this->passwordHasher->isPasswordValid($user, 'AnotherSecure123'));
    }

    public function testAdminCannotMoveCompanyUnderItsOwnSubsidiary(): void
    {
        $admin = $this->createAdminUserWithEmail('admin-no-cycle@test.local');
        $parentCompany = new Company('HV Moder AB');
        $childCompany = new Company('HV Zebra AB');
        $childCompany->setParentCompany($parentCompany);

        $this->entityManager->persist($parentCompany);
        $this->entityManager->persist($childCompany);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/identity');
        self::assertResponseIsSuccessful();

        $companyForm = $crawler->filter(sprintf('form[action="/portal/admin/companies/%d"]', $parentCompany->getId()))->form([
            'name' => 'HV Moder AB',
            'primary_email' => '',
            'parent_company_id' => (string) $childCompany->getId(),
            'is_active' => '1',
        ]);
        $this->client->submit($companyForm);

        self::assertResponseRedirects('/portal/admin/identity');
        $this->client->followRedirect();

        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Ett företag kan inte flyttas under sitt eget dotterbolag.', $html);

        $this->entityManager->clear();
        $parentCompany = $this->entityManager->getRepository(Company::class)->findOneBy(['name' => 'HV Moder AB']);
        self::assertNotNull($parentCompany);
        self::assertNull($parentCompany->getParentCompany());
    }

    public function testProtectedSystemUserCannotBeUpdatedThroughAdminForm(): void
    {
        $admin = $this->createAdminUser();
        $protectedUser = new User('kenta@spelhubben.se', 'Kenta', 'Seed Admin', UserType::SUPER_ADMIN);
        $protectedUser->setPassword($this->passwordHasher->hashPassword($protectedUser, 'OriginalSecure123'));
        $protectedUser->enableMfa();

        $this->entityManager->persist($protectedUser);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/identity');
        self::assertResponseIsSuccessful();

        $userForm = $crawler->filter(sprintf('form[action="/portal/admin/users/%d"]', $protectedUser->getId()))->form([
            'first_name' => 'Changed',
            'last_name' => 'User',
            'email' => 'changed@example.test',
            'password' => 'AnotherSecure123',
            'type' => UserType::ADMIN->value,
            'company_id' => '',
            'is_active' => '1',
            'mfa_enabled' => '1',
            'email_notifications_enabled' => '1',
        ]);
        $this->client->submit($userForm);

        self::assertResponseRedirects('/portal/admin/identity');
        $this->client->followRedirect();

        $html = $this->client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('Det här systemkontot är skyddat och kan inte ändras via adminpanelen.', $html);

        $this->entityManager->clear();
        $protectedUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'kenta@spelhubben.se']);
        self::assertNotNull($protectedUser);
        self::assertSame('Kenta Seed Admin', $protectedUser->getDisplayName());
        self::assertSame(UserType::SUPER_ADMIN, $protectedUser->getType());
        self::assertTrue($this->passwordHasher->isPasswordValid($protectedUser, 'OriginalSecure123'));
    }

    public function testAdminCanUpdateSiteBrandingWithLogoAndFooterText(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/identity');
        self::assertResponseIsSuccessful();

        $logoPath = $this->createTinyPng();
        $form = $crawler->selectButton('Spara webbplatsidentitet')->form([
            'site_name' => 'Kundportalen AB',
            'footer_text' => 'All support hanteras vardagar 08-17.',
        ]);
        $this->client->request(
            'POST',
            '/portal/admin/site-branding',
            [
                '_token' => (string) $form['_token']->getValue(),
                'site_name' => 'Kundportalen AB',
                'footer_text' => 'All support hanteras vardagar 08-17.',
            ],
            [
                'site_logo' => new UploadedFile($logoPath, 'kundportalen.png', 'image/png', null, true),
            ],
        );

        self::assertResponseRedirects('/portal/admin/identity');
        $this->client->followRedirect();

        self::assertSame('Kundportalen AB', $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::SITE_BRAND_NAME)?->getSettingValue());
        self::assertSame('All support hanteras vardagar 08-17.', $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::SITE_FOOTER_TEXT)?->getSettingValue());
        self::assertSame('/assets/branding/custom-site-logo.png', $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::SITE_BRAND_LOGO_PATH)?->getSettingValue());
        self::assertFileExists(dirname(__DIR__, 2).'/public/assets/branding/custom-site-logo.png');

        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Kundportalen AB | Start', $html);
        self::assertStringContainsString('/assets/branding/custom-site-logo.png', $html);
        self::assertStringContainsString('All support hanteras vardagar 08-17.', $html);
        self::assertStringContainsString('https://github.com/K3NT4/Driftpunkt', $html);
        self::assertStringContainsString(sprintf('© %s Driftpunkt', date('Y')), $html);
    }

    public function testAdminCanFilterNotificationLog(): void
    {
        $admin = $this->createAdminUser();

        $sentLog = (new NotificationLog(
            'ticket_assigned',
            'sent@example.test',
            '[DP-1001] Ticket tilldelad dig',
            true,
            'Mail skickat.',
        ));
        $skippedLog = (new NotificationLog(
            'customer_waiting_reply',
            'skipped@example.test',
            '[DP-1002] Ticket väntar på ditt svar',
            false,
            'Mailnotiser är avstängda för mottagaren.',
        ));

        $this->entityManager->persist($sentLog);
        $this->entityManager->persist($skippedLog);
        $this->entityManager->flush();

        $this->setEntityCreatedAt($sentLog, new \DateTimeImmutable('-10 days'));
        $this->setEntityCreatedAt($skippedLog, new \DateTimeImmutable('now'));

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/logs?delivery=skipped&q=skipped@example.test&event=customer_waiting_reply&date=today');
        self::assertResponseIsSuccessful();

        $html = $crawler->html();
        self::assertIsString($html);
        self::assertStringContainsString('skipped@example.test', $html);
        self::assertStringNotContainsString('sent@example.test', $html);
        self::assertStringContainsString('Hoppat över', $html);
        self::assertStringContainsString('option value="today" selected', $html);

        $crawler = $this->client->request('GET', '/portal/admin/logs?date=older');
        self::assertResponseIsSuccessful();

        $html = $crawler->html();
        self::assertIsString($html);
        self::assertStringContainsString('sent@example.test', $html);
        self::assertStringNotContainsString('skipped@example.test', $html);
        self::assertStringContainsString('option value="older" selected', $html);
    }

    public function testAdminOverviewShowsCombinedJobHistory(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->client->loginUser($admin);

        $databaseJobsDirectory = dirname(__DIR__, 2).'/var/database_jobs';
        $codeUpdateRunsDirectory = dirname(__DIR__, 2).'/var/code_update_runs';
        mkdir($databaseJobsDirectory, 0777, true);
        mkdir($codeUpdateRunsDirectory, 0777, true);

        file_put_contents($databaseJobsDirectory.'/db-job.json', json_encode([
            'id' => 'db-job',
            'queuedAt' => '2026-04-19T10:00:00+02:00',
            'startedAt' => '2026-04-19T10:00:02+02:00',
            'finishedAt' => null,
            'status' => 'running',
            'action' => 'backup',
            'label' => 'Skapa databasbackup',
            'databasePath' => dirname(__DIR__, 2).'/var/driftpunkt_test.db',
            'succeeded' => null,
            'resultSummary' => null,
            'output' => 'Backup pågår',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        file_put_contents($codeUpdateRunsDirectory.'/update-run.json', json_encode([
            'id' => 'update-run',
            'queuedAt' => '2026-04-19T09:00:00+02:00',
            'startedAt' => '2026-04-19T09:00:01+02:00',
            'finishedAt' => '2026-04-19T09:02:00+02:00',
            'status' => 'completed',
            'succeeded' => true,
            'selectedTasks' => ['composer_install', 'cache_clear'],
            'taskResults' => [
                [
                    'id' => 'composer_install',
                    'label' => 'Composer install',
                    'exitCode' => 0,
                    'succeeded' => true,
                    'output' => 'OK',
                ],
            ],
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        $crawler = $this->client->request('GET', '/portal/admin/jobs');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Jobbhistorik', $html);
        self::assertStringContainsString('Skapa databasbackup', $html);
        self::assertStringContainsString('Efter uppdatering', $html);
        self::assertStringContainsString('Aktiva', $html);
    }

    public function testAdminCanFilterAndInspectCombinedJobHistory(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->client->loginUser($admin);

        $databaseJobsDirectory = dirname(__DIR__, 2).'/var/database_jobs';
        $codeUpdateRunsDirectory = dirname(__DIR__, 2).'/var/code_update_runs';
        mkdir($databaseJobsDirectory, 0777, true);
        mkdir($codeUpdateRunsDirectory, 0777, true);

        file_put_contents($databaseJobsDirectory.'/db-job.json', json_encode([
            'id' => 'db-job',
            'queuedAt' => '2026-04-19T10:00:00+02:00',
            'startedAt' => '2026-04-19T10:00:02+02:00',
            'finishedAt' => null,
            'status' => 'running',
            'action' => 'backup',
            'label' => 'Skapa databasbackup',
            'databasePath' => dirname(__DIR__, 2).'/var/driftpunkt_test.db',
            'succeeded' => null,
            'resultSummary' => 'Backup pågår',
            'output' => 'Backup pågår',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        file_put_contents($codeUpdateRunsDirectory.'/update-run.json', json_encode([
            'id' => 'update-run',
            'queuedAt' => '2026-04-19T09:00:00+02:00',
            'startedAt' => '2026-04-19T09:00:01+02:00',
            'finishedAt' => '2026-04-19T09:02:00+02:00',
            'status' => 'completed',
            'succeeded' => true,
            'selectedTasks' => ['composer_install', 'cache_clear'],
            'taskResults' => [
                [
                    'id' => 'composer_install',
                    'label' => 'Composer install',
                    'exitCode' => 0,
                    'succeeded' => true,
                    'output' => 'OK',
                ],
            ],
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        $crawler = $this->client->request('GET', '/portal/admin/jobs?job_source=database&job_status=active&job_id=db-job');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Jobbfilter', $html);
        self::assertStringContainsString('Skapa databasbackup', $html);
        self::assertStringContainsString('db-job', $html);
        self::assertStringContainsString('Backup pågår', $html);
        self::assertStringContainsString('Full logg', $html);
        self::assertStringNotContainsString('[OK] Composer install', $html);
    }

    public function testAdminCanSearchCombinedJobHistory(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->client->loginUser($admin);

        $databaseJobsDirectory = dirname(__DIR__, 2).'/var/database_jobs';
        $codeUpdateRunsDirectory = dirname(__DIR__, 2).'/var/code_update_runs';
        mkdir($databaseJobsDirectory, 0777, true);
        mkdir($codeUpdateRunsDirectory, 0777, true);

        file_put_contents($databaseJobsDirectory.'/db-job.json', json_encode([
            'id' => 'db-job',
            'queuedAt' => '2026-04-19T10:00:00+02:00',
            'startedAt' => '2026-04-19T10:00:02+02:00',
            'finishedAt' => null,
            'status' => 'running',
            'action' => 'backup',
            'label' => 'Skapa databasbackup',
            'databasePath' => dirname(__DIR__, 2).'/var/driftpunkt_test.db',
            'succeeded' => null,
            'resultSummary' => 'Backup pa gar',
            'output' => 'Backup pågår just nu',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        file_put_contents($codeUpdateRunsDirectory.'/update-run.json', json_encode([
            'id' => 'update-run',
            'queuedAt' => '2026-04-19T09:00:00+02:00',
            'startedAt' => '2026-04-19T09:00:01+02:00',
            'finishedAt' => '2026-04-19T09:02:00+02:00',
            'status' => 'completed',
            'succeeded' => true,
            'selectedTasks' => ['composer_install'],
            'taskResults' => [
                [
                    'id' => 'composer_install',
                    'label' => 'Composer install',
                    'exitCode' => 0,
                    'succeeded' => true,
                    'output' => 'Composer completed cleanly',
                ],
            ],
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        $crawler = $this->client->request('GET', '/portal/admin/jobs?job_query=composer+completed');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('value="composer completed"', $html);
        self::assertStringContainsString('Efter uppdatering', $html);
        self::assertStringContainsString('update-run', $html);
        self::assertStringNotContainsString('db-job', $html);
        self::assertStringNotContainsString('Skapa databasbackup', $html);
    }

    public function testAdminCanFilterCombinedJobHistoryByDate(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->client->loginUser($admin);
        $today = new \DateTimeImmutable('today 10:00:00');
        $older = $today->modify('-20 days');

        $databaseJobsDirectory = dirname(__DIR__, 2).'/var/database_jobs';
        $codeUpdateRunsDirectory = dirname(__DIR__, 2).'/var/code_update_runs';
        mkdir($databaseJobsDirectory, 0777, true);
        mkdir($codeUpdateRunsDirectory, 0777, true);

        file_put_contents($databaseJobsDirectory.'/job-today.json', json_encode([
            'id' => 'job-today',
            'queuedAt' => $today->format(DATE_ATOM),
            'startedAt' => $today->modify('+2 seconds')->format(DATE_ATOM),
            'finishedAt' => null,
            'status' => 'running',
            'action' => 'backup',
            'label' => 'Dagens databasjobb',
            'databasePath' => dirname(__DIR__, 2).'/var/driftpunkt_test.db',
            'succeeded' => null,
            'resultSummary' => 'Pågår',
            'output' => 'Kör idag',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        file_put_contents($codeUpdateRunsDirectory.'/job-older.json', json_encode([
            'id' => 'job-older',
            'queuedAt' => $older->format(DATE_ATOM),
            'startedAt' => $older->modify('+1 second')->format(DATE_ATOM),
            'finishedAt' => $older->modify('+2 minutes')->format(DATE_ATOM),
            'status' => 'completed',
            'succeeded' => true,
            'selectedTasks' => ['composer_install'],
            'taskResults' => [
                [
                    'id' => 'composer_install',
                    'label' => 'Composer install',
                    'exitCode' => 0,
                    'succeeded' => true,
                    'output' => 'Kördes tidigare',
                ],
            ],
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        $crawler = $this->client->request('GET', '/portal/admin/jobs?job_date=older');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('option value="older" selected', $html);
        self::assertStringContainsString('job-older', $html);
        self::assertStringNotContainsString('job-today', $html);

        $crawler = $this->client->request('GET', '/portal/admin/jobs?job_date=today');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('option value="today" selected', $html);
        self::assertStringContainsString('job-today', $html);
        self::assertStringNotContainsString('job-older', $html);
    }

    public function testAdminCanRetryFailedUpdateJobFromJobView(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->client->loginUser($admin);

        $codeUpdateRunsDirectory = dirname(__DIR__, 2).'/var/code_update_runs';
        mkdir($codeUpdateRunsDirectory, 0777, true);

        file_put_contents($codeUpdateRunsDirectory.'/failed-update-run.json', json_encode([
            'id' => 'failed-update-run',
            'queuedAt' => '2026-04-19T09:00:00+02:00',
            'startedAt' => '2026-04-19T09:00:01+02:00',
            'finishedAt' => '2026-04-19T09:01:00+02:00',
            'status' => 'failed',
            'succeeded' => false,
            'selectedTasks' => ['composer_install', 'cache_clear'],
            'taskResults' => [
                [
                    'id' => 'composer_install',
                    'label' => 'Composer install',
                    'exitCode' => 1,
                    'succeeded' => false,
                    'output' => 'Composer failed',
                ],
            ],
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        $crawler = $this->client->request('GET', '/portal/admin/jobs?job_source=updates&job_status=failed&job_id=failed-update-run');
        self::assertResponseIsSuccessful();

        $this->client->submit(
            $crawler->filter('form[action*="/portal/admin/jobs/updates/failed-update-run/retry"]')->form(),
        );

        self::assertResponseRedirects('/portal/admin/jobs?job_id=failed-update-run&job_source=updates&job_status=failed');
        $this->client->followRedirect();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Uppdateringsjobbet köades om som', $html);
        $postUpdateRunsDirectory = dirname(__DIR__, 2).'/var/post_update_runs';
        self::assertGreaterThanOrEqual(1, \count(glob($postUpdateRunsDirectory.'/*.json') ?: []));
    }

    public function testAdminCanDownloadJobLogFromJobView(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->client->loginUser($admin);

        $codeUpdateRunsDirectory = dirname(__DIR__, 2).'/var/code_update_runs';
        mkdir($codeUpdateRunsDirectory, 0777, true);

        file_put_contents($codeUpdateRunsDirectory.'/failed-update-run.json', json_encode([
            'id' => 'failed-update-run',
            'queuedAt' => '2026-04-19T09:00:00+02:00',
            'startedAt' => '2026-04-19T09:00:01+02:00',
            'finishedAt' => '2026-04-19T09:01:00+02:00',
            'status' => 'failed',
            'succeeded' => false,
            'selectedTasks' => ['composer_install', 'cache_clear'],
            'taskResults' => [
                [
                    'id' => 'composer_install',
                    'label' => 'Composer install',
                    'exitCode' => 1,
                    'succeeded' => false,
                    'output' => 'Composer failed',
                ],
                [
                    'id' => 'cache_clear',
                    'label' => 'Rensa cache',
                    'exitCode' => 0,
                    'succeeded' => true,
                    'output' => 'Cache cleared',
                ],
            ],
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        $crawler = $this->client->request('GET', '/portal/admin/jobs?job_source=updates&job_status=failed&job_id=failed-update-run');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->selectLink('Ladda ner logg'));

        $this->client->request('GET', '/portal/admin/jobs/updates/failed-update-run/download-log');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/plain; charset=UTF-8');
        self::assertTrue($this->client->getResponse()->headers->has('content-disposition'));
        self::assertStringContainsString(
            'attachment; filename=driftpunkt-jobb-updates-failed-update-run.log.txt',
            (string) $this->client->getResponse()->headers->get('content-disposition'),
        );

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Driftpunkt jobb-logg', $content);
        self::assertStringContainsString('Källa: updates', $content);
        self::assertStringContainsString('Jobb-ID: failed-update-run', $content);
        self::assertStringContainsString('[FAIL] Composer install', $content);
        self::assertStringContainsString('Composer failed', $content);
        self::assertStringContainsString('[OK] Rensa cache', $content);
        self::assertStringContainsString('Cache cleared', $content);
    }

    public function testAdminCanPurgeFinishedJobsFromJobView(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->client->loginUser($admin);

        $databaseJobsDirectory = dirname(__DIR__, 2).'/var/database_jobs';
        $codeUpdateRunsDirectory = dirname(__DIR__, 2).'/var/code_update_runs';
        mkdir($databaseJobsDirectory, 0777, true);
        mkdir($codeUpdateRunsDirectory, 0777, true);

        file_put_contents($databaseJobsDirectory.'/db-completed.json', json_encode([
            'id' => 'db-completed',
            'queuedAt' => '2026-04-19T10:00:00+02:00',
            'startedAt' => '2026-04-19T10:00:01+02:00',
            'finishedAt' => '2026-04-19T10:01:00+02:00',
            'status' => 'completed',
            'action' => 'backup',
            'label' => 'Skapa databasbackup',
            'databasePath' => dirname(__DIR__, 2).'/var/driftpunkt_test.db',
            'succeeded' => true,
            'resultSummary' => 'Klar',
            'output' => 'Done',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        file_put_contents($databaseJobsDirectory.'/db-running.json', json_encode([
            'id' => 'db-running',
            'queuedAt' => '2026-04-19T11:00:00+02:00',
            'startedAt' => '2026-04-19T11:00:01+02:00',
            'finishedAt' => null,
            'status' => 'running',
            'action' => 'backup',
            'label' => 'Skapa databasbackup',
            'databasePath' => dirname(__DIR__, 2).'/var/driftpunkt_test.db',
            'succeeded' => null,
            'resultSummary' => null,
            'output' => 'Running',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        file_put_contents($codeUpdateRunsDirectory.'/update-failed.json', json_encode([
            'id' => 'update-failed',
            'queuedAt' => '2026-04-19T09:00:00+02:00',
            'startedAt' => '2026-04-19T09:00:01+02:00',
            'finishedAt' => '2026-04-19T09:01:00+02:00',
            'status' => 'failed',
            'succeeded' => false,
            'selectedTasks' => ['composer_install'],
            'taskResults' => [
                [
                    'id' => 'composer_install',
                    'label' => 'Composer install',
                    'exitCode' => 1,
                    'succeeded' => false,
                    'output' => 'Failed',
                ],
            ],
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

        $crawler = $this->client->request('GET', '/portal/admin/jobs');
        self::assertResponseIsSuccessful();

        $this->client->submit(
            $crawler->filter('form[action*="/portal/admin/jobs/purge-finished"]')->form(),
        );

        self::assertResponseRedirects('/portal/admin/jobs?job_source=all&job_status=all');
        $this->client->followRedirect();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Rensade bort 2 avslutade jobb', $html);
        self::assertFileDoesNotExist($databaseJobsDirectory.'/db-completed.json');
        self::assertFileExists($databaseJobsDirectory.'/db-running.json');
        self::assertFileDoesNotExist($codeUpdateRunsDirectory.'/update-failed.json');
    }

    public function testAdminCanCreateDatabaseBackupFromDatabaseSection(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/database');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Databashantering', (string) $crawler->html());

        $this->client->submitForm('Köa backup', []);

        self::assertResponseRedirects('/portal/admin/database');
        $this->client->followRedirect();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Databasbackup köades som jobb', $html);
        self::assertStringContainsString('Databasjobb', $html);
    }

    public function testRegularAdminCannotOpenSystemMaintenanceSections(): void
    {
        $admin = $this->createRegularAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringNotContainsString('href="/portal/admin/updates"', $html);
        self::assertStringNotContainsString('href="/portal/admin/database"', $html);
        self::assertStringNotContainsString('href="/portal/admin/jobs"', $html);
        self::assertStringNotContainsString('action="/portal/admin/underhall"', $html);

        foreach (['/portal/admin/updates', '/portal/admin/database', '/portal/admin/jobs'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testRegularAdminCannotPostSystemMaintenanceActions(): void
    {
        $admin = $this->createRegularAdminUser();
        $this->client->loginUser($admin);

        $this->client->request('POST', '/portal/admin/database/backups/create');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/portal/admin/database/migrations/run');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/portal/admin/updates/post-deploy/run');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/portal/admin/underhall');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminOverviewShowsMaintenanceToolsButHidesTicketOperations(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Super Admin', $html);
        self::assertStringContainsString('href="/portal/admin/updates"', $html);
        self::assertStringContainsString('href="/portal/admin/database"', $html);
        self::assertStringContainsString('href="/portal/admin/jobs"', $html);
        self::assertStringNotContainsString('href="/portal/admin/categories"', $html);
        self::assertStringNotContainsString('href="/portal/admin/automation"', $html);
        self::assertStringNotContainsString('href="/portal/admin/reports"', $html);
        self::assertStringNotContainsString('href="/portal/admin/import-export/arendeimport"', $html);
        self::assertStringNotContainsString('href="/portal/admin/sla"', $html);
    }

    public function testAdminCanQueueDatabaseMigrationsFromDatabaseSection(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/database');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Köa migrering', []);

        self::assertResponseRedirects('/portal/admin/database');
        $this->client->followRedirect();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Databasmigreringen köades som körning', $html);

        $runFiles = glob(dirname(__DIR__, 2).'/var/post_update_runs/*.json') ?: [];
        self::assertCount(1, $runFiles);

        $run = json_decode((string) file_get_contents($runFiles[0]), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['doctrine_migrate', 'cache_clear'], $run['selectedTasks']);
    }

    public function testAdminCanStageZipPackageFromUpdatesSection(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/updates');
        self::assertResponseIsSuccessful();

        $zipPath = $this->createValidUpdateZip();
        $form = $crawler->selectButton('Ladda upp och kontrollera paket')->form();
        $form['update_package'] = new UploadedFile($zipPath, 'driftpunkt-update.zip', 'application/zip', null, true);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/updates');
        $this->client->followRedirect();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('driftpunkt/test-release', $html);
        self::assertStringContainsString('Klar för uppdatering', $html);
    }

    public function testAdminSeesPostUpdateTasksAndGetsValidationErrorWithoutSelection(): void
    {
        $admin = $this->createSuperAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/updates');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Efter uppdatering', (string) $crawler->html());

        $this->client->request('POST', '/portal/admin/updates/post-deploy/run', [
            '_token' => (string) $crawler->filter('form[action="/portal/admin/updates/post-deploy/run"] input[name="_token"]')->attr('value'),
        ]);

        self::assertResponseRedirects('/portal/admin/updates');
        $this->client->followRedirect();
        self::assertStringContainsString('Välj minst ett efter-uppdateringssteg att köra.', (string) $this->client->getResponse()->getContent());
    }

    public function testAdminCanCreateAndUpdateSlaPolicy(): void
    {
        $admin = $this->createAdminUser();
        $defaultTechnician = new User('sla-owner@example.test', 'Siri', 'SLA', UserType::TECHNICIAN);
        $defaultTechnician->setPassword($this->passwordHasher->hashPassword($defaultTechnician, 'SlaOwnerPassword123'));
        $defaultTechnician->enableMfa();
        $defaultTeam = new TechnicianTeam('Servicedesk');
        $this->entityManager->persist($defaultTechnician);
        $this->entityManager->persist($defaultTeam);
        $this->entityManager->flush();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/sla');
        self::assertResponseIsSuccessful();

        $createForm = $crawler->selectButton('Skapa SLA-policy')->form([
            'name' => 'Premium 4h',
            'description' => 'Snabb hantering för prioriterade kunder.',
            'first_response_hours' => '4',
            'resolution_hours' => '24',
            'first_response_warning_hours' => '3',
            'resolution_warning_hours' => '12',
            'default_priority' => TicketPriority::HIGH->value,
            'default_assignee_id' => (string) $defaultTechnician->getId(),
            'default_team_id' => (string) $defaultTeam->getId(),
            'default_escalation_level' => TicketEscalationLevel::LEAD->value,
            'default_priority_enabled' => '1',
            'default_assignee_enabled' => '1',
            'default_team_enabled' => '1',
            'default_escalation_enabled' => '1',
        ]);
        $this->client->submit($createForm);

        self::assertResponseRedirects('/portal/admin/sla');
        $this->client->followRedirect();

        $slaPolicy = $this->entityManager->getRepository(SlaPolicy::class)->findOneBy(['name' => 'Premium 4h']);
        self::assertNotNull($slaPolicy);
        self::assertSame(4, $slaPolicy->getFirstResponseHours());
        self::assertSame(24, $slaPolicy->getResolutionHours());
        self::assertSame(3, $slaPolicy->getFirstResponseWarningHours());
        self::assertSame(12, $slaPolicy->getResolutionWarningHours());
        self::assertTrue($slaPolicy->isDefaultPriorityEnabled());
        self::assertSame(TicketPriority::HIGH, $slaPolicy->getDefaultPriority());
        self::assertTrue($slaPolicy->isDefaultAssigneeEnabled());
        self::assertSame($defaultTechnician->getId(), $slaPolicy->getDefaultAssignee()?->getId());
        self::assertTrue($slaPolicy->isDefaultTeamEnabled());
        self::assertSame($defaultTeam->getId(), $slaPolicy->getDefaultTeam()?->getId());
        self::assertTrue($slaPolicy->isDefaultEscalationEnabled());
        self::assertSame(TicketEscalationLevel::LEAD, $slaPolicy->getDefaultEscalationLevel());
        self::assertSame('Snabb hantering för prioriterade kunder.', $slaPolicy->getDescription());
        self::assertTrue($slaPolicy->isActive());

        $crawler = $this->client->request('GET', '/portal/admin/sla');
        self::assertResponseIsSuccessful();

        $updateForm = $crawler->filter(sprintf('form[action="/portal/admin/sla/%d"]', $slaPolicy->getId()))->form([
            'name' => 'Premium 2h',
            'description' => 'Ännu snabbare för kritiska kunder.',
            'first_response_hours' => '2',
            'resolution_hours' => '12',
            'first_response_warning_hours' => '1',
            'resolution_warning_hours' => '',
            'default_priority' => '',
            'default_assignee_id' => '',
            'default_team_id' => '',
            'default_escalation_level' => '',
        ]);
        unset($updateForm['is_active']);
        unset($updateForm['default_priority_enabled']);
        unset($updateForm['default_assignee_enabled']);
        unset($updateForm['default_team_enabled']);
        unset($updateForm['default_escalation_enabled']);
        $this->client->submit($updateForm);

        self::assertResponseRedirects('/portal/admin/sla');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $slaPolicy = $this->entityManager->getRepository(SlaPolicy::class)->find($slaPolicy->getId());
        self::assertNotNull($slaPolicy);
        self::assertSame('Premium 2h', $slaPolicy->getName());
        self::assertSame(2, $slaPolicy->getFirstResponseHours());
        self::assertSame(12, $slaPolicy->getResolutionHours());
        self::assertSame(1, $slaPolicy->getFirstResponseWarningHours());
        self::assertNull($slaPolicy->getResolutionWarningHours());
        self::assertFalse($slaPolicy->isDefaultPriorityEnabled());
        self::assertNull($slaPolicy->getDefaultPriority());
        self::assertFalse($slaPolicy->isDefaultAssigneeEnabled());
        self::assertNull($slaPolicy->getDefaultAssignee());
        self::assertFalse($slaPolicy->isDefaultTeamEnabled());
        self::assertNull($slaPolicy->getDefaultTeam());
        self::assertFalse($slaPolicy->isDefaultEscalationEnabled());
        self::assertNull($slaPolicy->getDefaultEscalationLevel());
        self::assertSame('Ännu snabbare för kritiska kunder.', $slaPolicy->getDescription());
        self::assertFalse($slaPolicy->isActive());
    }

    public function testAdminCanCreateCategoryAndRoutingRule(): void
    {
        $admin = $this->createAdminUser();
        $team = new TechnicianTeam('NOC');
        $technician = new User('routing-tech@example.test', 'Rolf', 'Routing', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'RoutingPassword123'));
        $technician->enableMfa();
        $template = new TicketIntakeTemplate('Nätverksmall', TicketRequestType::INCIDENT);
        $templateField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'affected_service', 'Påverkad tjänst'))
            ->setTemplate($template);
        $this->entityManager->persist($team);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($template);
        $this->entityManager->persist($templateField);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Skapa kategori', [
            'name' => 'Nätverk',
            'description' => 'VPN, brandvägg och uppkoppling',
        ]);

        self::assertResponseRedirects('/portal/admin/categories');
        $this->client->followRedirect();

        $category = $this->entityManager->getRepository(TicketCategory::class)->findOneBy(['name' => 'Nätverk']);
        self::assertNotNull($category);

        $crawler = $this->client->request('GET', '/portal/admin/automation');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Skapa routingregel')->form([
            'name' => 'NOC Kund',
            'team_id' => (string) $team->getId(),
            'category_id' => (string) $category->getId(),
            'customer_type' => UserType::CUSTOMER->value,
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::COMPANY->value,
            'intake_template_id' => (string) $template->getId(),
            'intake_field_key' => 'affected_service',
            'intake_field_value' => 'VPN gateway Stockholm',
            'default_priority' => TicketPriority::CRITICAL->value,
            'default_escalation_level' => TicketEscalationLevel::INCIDENT->value,
            'default_assignee_id' => (string) $technician->getId(),
            'sort_order' => '10',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/automation');
        $this->client->followRedirect();

        $rule = $this->entityManager->getRepository(TicketRoutingRule::class)->findOneBy(['name' => 'NOC Kund']);
        self::assertNotNull($rule);
        self::assertSame('NOC', $rule->getTeam()->getName());
        self::assertSame('Nätverk', $rule->getCategory()?->getName());
        self::assertSame(UserType::CUSTOMER, $rule->getCustomerType());
        self::assertSame(TicketRequestType::INCIDENT, $rule->getRequestType());
        self::assertSame(TicketImpactLevel::COMPANY, $rule->getImpactLevel());
        self::assertSame($template->getVersionFamily(), $rule->getIntakeTemplateFamily());
        self::assertSame('affected_service', $rule->getIntakeFieldKey());
        self::assertSame('VPN gateway Stockholm', $rule->getIntakeFieldValue());
        self::assertSame(TicketPriority::CRITICAL, $rule->getDefaultPriority());
        self::assertSame(TicketEscalationLevel::INCIDENT, $rule->getDefaultEscalationLevel());
        self::assertSame($technician->getId(), $rule->getDefaultAssignee()?->getId());
    }

    public function testAdminCanCreateIntakeField(): void
    {
        $admin = $this->createAdminUser();
        $category = new TicketCategory('Nätverk');
        $slaPolicy = new SlaPolicy('Mall-SLA', 2, 8);
        $team = new TechnicianTeam('Mallteam');
        $assignee = new User('mall-template-tech@example.test', 'Malla', 'Tekniker', UserType::TECHNICIAN);
        $assignee->setPassword($this->passwordHasher->hashPassword($assignee, 'MallTemplate123'));
        $assignee->enableMfa();
        $this->entityManager->persist($category);
        $this->entityManager->persist($slaPolicy);
        $this->entityManager->persist($team);
        $this->entityManager->persist($assignee);
        $this->entityManager->flush();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();

        $templateForm = $crawler->selectButton('Skapa intake-mall')->form([
            'name' => 'VPN incidentmall',
            'description' => 'Frågepaket för nätverksincidenter.',
            'request_type' => TicketRequestType::INCIDENT->value,
            'category_id' => (string) $category->getId(),
            'customer_type' => UserType::CUSTOMER->value,
            'default_sla_policy_id' => (string) $slaPolicy->getId(),
            'default_priority' => TicketPriority::HIGH->value,
            'default_team_id' => (string) $team->getId(),
            'default_assignee_id' => (string) $assignee->getId(),
            'default_escalation_level' => TicketEscalationLevel::INCIDENT->value,
            'playbook_text' => "Bekräfta påverkan\nKontrollera övervakning",
            'checklist_items' => "Verifiera tjänst\nÅterkoppla till kund",
        ]);
        $this->client->submit($templateForm);

        self::assertResponseRedirects('/portal/admin/categories');
        $this->client->followRedirect();

        $template = $this->entityManager->getRepository(TicketIntakeTemplate::class)->findOneBy(['name' => 'VPN incidentmall']);
        self::assertNotNull($template);
        self::assertSame(TicketRequestType::INCIDENT, $template->getRequestType());
        self::assertSame('Nätverk', $template->getCategory()?->getName());
        self::assertSame(UserType::CUSTOMER, $template->getCustomerType());
        self::assertSame($slaPolicy->getId(), $template->getDefaultSlaPolicy()?->getId());
        self::assertSame(TicketPriority::HIGH, $template->getDefaultPriority());
        self::assertSame($team->getId(), $template->getDefaultTeam()?->getId());
        self::assertSame($assignee->getId(), $template->getDefaultAssignee()?->getId());
        self::assertSame(TicketEscalationLevel::INCIDENT, $template->getDefaultEscalationLevel());
        self::assertSame("Bekräfta påverkan\nKontrollera övervakning", $template->getPlaybookText());
        self::assertSame(['Verifiera tjänst', 'Återkoppla till kund'], $template->getChecklistItems());

        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Skapa intake-fält')->form([
            'request_type' => TicketRequestType::INCIDENT->value,
            'template_id' => (string) $template->getId(),
            'category_id' => (string) $category->getId(),
            'customer_type' => UserType::CUSTOMER->value,
            'field_key' => 'affected_service',
            'label' => 'Påverkad tjänst',
            'field_type' => TicketIntakeFieldType::SELECT->value,
            'help_text' => 'Vilken tjänst eller funktion påverkas?',
            'placeholder' => 'VPN gateway Stockholm',
            'depends_on_field_key' => 'environment',
            'depends_on_field_value' => 'Produktion',
            'select_options' => "VPN gateway Stockholm\nVPN gateway Göteborg",
            'sort_order' => '10',
            'is_required' => '1',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/categories');
        $this->client->followRedirect();

        $field = $this->entityManager->getRepository(TicketIntakeField::class)->findOneBy(['fieldKey' => 'affected_service']);
        self::assertNotNull($field);
        self::assertSame(TicketRequestType::INCIDENT, $field->getRequestType());
        self::assertSame('Påverkad tjänst', $field->getLabel());
        self::assertSame(TicketIntakeFieldType::SELECT, $field->getFieldType());
        self::assertSame('VPN gateway Stockholm', $field->getPlaceholder());
        self::assertSame(['VPN gateway Stockholm', 'VPN gateway Göteborg'], $field->getSelectOptions());
        self::assertSame('VPN incidentmall', $field->getTemplate()?->getName());
        self::assertSame('Nätverk', $field->getCategory()?->getName());
        self::assertSame(UserType::CUSTOMER, $field->getCustomerType());
        self::assertSame('Nätverk', $field->getEffectiveCategory()?->getName());
        self::assertSame(UserType::CUSTOMER, $field->getEffectiveCustomerType());
        self::assertSame('environment', $field->getDependsOnFieldKey());
        self::assertSame('Produktion', $field->getDependsOnFieldValue());
        self::assertSame(10, $field->getSortOrder());
        self::assertTrue($field->isRequired());
    }

    public function testAdminCanCloneIntakeTemplateWithFields(): void
    {
        $admin = $this->createAdminUser();
        $category = new TicketCategory('VPN');
        $template = (new TicketIntakeTemplate('VPN grundmall', TicketRequestType::INCIDENT))
            ->setDescription('Basfrågor för VPN-incidenter.')
            ->setCategory($category)
            ->setCustomerType(UserType::CUSTOMER);
        $environmentField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'environment', 'Miljö'))
            ->setTemplate($template)
            ->setFieldType(TicketIntakeFieldType::SELECT)
            ->setSelectOptions(['Produktion', 'Test'])
            ->setSortOrder(10);
        $affectedUsersField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'affected_users', 'Påverkade användare'))
            ->setTemplate($template)
            ->setRequired(true)
            ->setDependsOnFieldKey('environment')
            ->setDependsOnFieldValue('Produktion')
            ->setSortOrder(20);

        $this->entityManager->persist($category);
        $this->entityManager->persist($template);
        $this->entityManager->persist($environmentField);
        $this->entityManager->persist($affectedUsersField);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();

        $cloneForm = $crawler->filter(sprintf('form[action="/portal/admin/intake-templates/%d/clone"]', $template->getId()))->form();
        $this->client->submit($cloneForm);

        self::assertResponseRedirects('/portal/admin/categories');
        $this->client->followRedirect();

        $clonedTemplate = $this->entityManager->getRepository(TicketIntakeTemplate::class)->findOneBy(['name' => 'VPN grundmall (kopia)']);
        self::assertNotNull($clonedTemplate);
        self::assertSame('Basfrågor för VPN-incidenter.', $clonedTemplate->getDescription());
        self::assertSame('VPN', $clonedTemplate->getCategory()?->getName());
        self::assertSame(UserType::CUSTOMER, $clonedTemplate->getCustomerType());

        $clonedEnvironmentField = $this->entityManager->getRepository(TicketIntakeField::class)->findOneBy(['fieldKey' => 'environment_copy']);
        $clonedAffectedUsersField = $this->entityManager->getRepository(TicketIntakeField::class)->findOneBy(['fieldKey' => 'affected_users_copy']);

        self::assertNotNull($clonedEnvironmentField);
        self::assertNotNull($clonedAffectedUsersField);
        self::assertSame($clonedTemplate->getId(), $clonedEnvironmentField->getTemplate()?->getId());
        self::assertSame($clonedTemplate->getId(), $clonedAffectedUsersField->getTemplate()?->getId());
        self::assertSame(['Produktion', 'Test'], $clonedEnvironmentField->getSelectOptions());
        self::assertSame('environment_copy', $clonedAffectedUsersField->getDependsOnFieldKey());
        self::assertSame('Produktion', $clonedAffectedUsersField->getDependsOnFieldValue());
        self::assertSame(UserType::CUSTOMER, $clonedAffectedUsersField->getEffectiveCustomerType());
    }

    public function testAdminCanDeleteIntakeTemplateWithFields(): void
    {
        $admin = $this->createAdminUser();
        $template = (new TicketIntakeTemplate('Stadmall', TicketRequestType::INCIDENT))
            ->setDescription('Tillfällig mall för borttagning.');
        $field = (new TicketIntakeField(TicketRequestType::INCIDENT, 'city', 'Stad'))
            ->setTemplate($template)
            ->setSortOrder(10);

        $this->entityManager->persist($template);
        $this->entityManager->persist($field);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();

        $deleteForm = $crawler->filter(sprintf('form[action="/portal/admin/intake-templates/%d/delete"]', $template->getId()))->form();
        $this->client->submit($deleteForm);

        self::assertResponseRedirects('/portal/admin/categories');
        $this->client->followRedirect();

        self::assertNull($this->entityManager->getRepository(TicketIntakeTemplate::class)->find($template->getId()));
        self::assertNull($this->entityManager->getRepository(TicketIntakeField::class)->findOneBy(['fieldKey' => 'city']));
    }

    public function testAdminCannotDeleteIntakeTemplateWhenRoutingRuleUsesItsField(): void
    {
        $admin = $this->createAdminUser();
        $team = new TechnicianTeam('Routingteam');
        $template = new TicketIntakeTemplate('Skyddad mall', TicketRequestType::INCIDENT);
        $field = (new TicketIntakeField(TicketRequestType::INCIDENT, 'affected_service', 'Påverkad tjänst'))
            ->setTemplate($template);
        $rule = (new TicketRoutingRule('Skyddad regel', $team))
            ->setIntakeFieldKey('affected_service')
            ->setIntakeFieldValue('VPN gateway Stockholm');

        $this->entityManager->persist($team);
        $this->entityManager->persist($template);
        $this->entityManager->persist($field);
        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();

        $deleteForm = $crawler->filter(sprintf('form[action="/portal/admin/intake-templates/%d/delete"]', $template->getId()))->form();
        $this->client->submit($deleteForm);

        self::assertResponseRedirects('/portal/admin/categories');
        $crawler = $this->client->followRedirect();
        $html = $crawler->html();
        self::assertIsString($html);
        self::assertStringContainsString('Skyddad mall', $html);

        self::assertNotNull($this->entityManager->getRepository(TicketIntakeTemplate::class)->find($template->getId()));
        self::assertNotNull($this->entityManager->getRepository(TicketIntakeField::class)->findOneBy(['fieldKey' => 'affected_service']));
    }

    public function testAdminCanArchiveAndRestoreIntakeFieldAndRoutingRule(): void
    {
        $admin = $this->createAdminUser();
        $team = new TechnicianTeam('Arkivteam');
        $intakeField = new TicketIntakeField(TicketRequestType::INCIDENT, 'archive_me', 'Arkivera mig');
        $routingRule = (new TicketRoutingRule('Arkivregel', $team))
            ->setRequestType(TicketRequestType::INCIDENT)
            ->setIntakeFieldKey('archive_me');

        $this->entityManager->persist($team);
        $this->entityManager->persist($intakeField);
        $this->entityManager->persist($routingRule);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();
        $html = $crawler->html();
        self::assertIsString($html);
        self::assertStringContainsString('Arkivera mig', $html);
        $automationCrawler = $this->client->request('GET', '/portal/admin/automation');
        self::assertResponseIsSuccessful();
        $automationHtml = $automationCrawler->html();
        self::assertIsString($automationHtml);
        self::assertStringContainsString('Arkivregel', $automationHtml);

        $archiveFieldForm = $crawler->filter(sprintf('form[action="/portal/admin/intake-fields/%d/archive"]', $intakeField->getId()))->form();
        $this->client->submit($archiveFieldForm);
        self::assertResponseRedirects('/portal/admin/automation?show_archived_intake_fields=1');
        $this->client->followRedirect();

        $archiveRuleForm = $this->client->request('GET', '/portal/admin/automation')->filter(sprintf('form[action="/portal/admin/routing-rules/%d/archive"]', $routingRule->getId()))->form();
        $this->client->submit($archiveRuleForm);
        self::assertResponseRedirects('/portal/admin/automation?show_archived_routing_rules=1');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $intakeField = $this->entityManager->getRepository(TicketIntakeField::class)->findOneBy(['fieldKey' => 'archive_me']);
        $routingRule = $this->entityManager->getRepository(TicketRoutingRule::class)->findOneBy(['name' => 'Arkivregel']);
        self::assertNotNull($intakeField);
        self::assertNotNull($routingRule);
        self::assertFalse($intakeField->isActive());
        self::assertFalse($routingRule->isActive());

        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();
        self::assertSame(
            0,
            $crawler->filter(sprintf('form[action="/portal/admin/intake-fields/%d/archive"]', $intakeField->getId()))->count(),
        );
        $automationCrawler = $this->client->request('GET', '/portal/admin/automation');
        self::assertResponseIsSuccessful();
        self::assertSame(
            0,
            $automationCrawler->filter(sprintf('form[action="/portal/admin/routing-rules/%d/archive"]', $routingRule->getId()))->count(),
        );

        $crawler = $this->client->request('GET', '/portal/admin/categories?show_archived_intake_fields=1');
        self::assertResponseIsSuccessful();

        $restoreFieldForm = $crawler->filter(sprintf('form[action="/portal/admin/intake-fields/%d/restore"]', $intakeField->getId()))->form();
        $this->client->submit($restoreFieldForm);
        self::assertResponseRedirects('/portal/admin/categories?show_archived_intake_fields=1');
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/portal/admin/automation?show_archived_routing_rules=1');
        self::assertResponseIsSuccessful();
        $restoreRuleForm = $crawler->filter(sprintf('form[action="/portal/admin/routing-rules/%d/restore"]', $routingRule->getId()))->form();
        $this->client->submit($restoreRuleForm);
        self::assertResponseRedirects('/portal/admin/automation?show_archived_routing_rules=1');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $intakeField = $this->entityManager->getRepository(TicketIntakeField::class)->findOneBy(['fieldKey' => 'archive_me']);
        $routingRule = $this->entityManager->getRepository(TicketRoutingRule::class)->findOneBy(['name' => 'Arkivregel']);
        self::assertNotNull($intakeField);
        self::assertNotNull($routingRule);
        self::assertTrue($intakeField->isActive());
        self::assertTrue($routingRule->isActive());
    }

    public function testAdminCanArchiveAndRestoreIntakeTemplate(): void
    {
        $admin = $this->createAdminUser();
        $template = new TicketIntakeTemplate('Arkivmall', TicketRequestType::INCIDENT);
        $field = (new TicketIntakeField(TicketRequestType::INCIDENT, 'archive_template_field', 'Mallfält'))
            ->setTemplate($template);

        $this->entityManager->persist($template);
        $this->entityManager->persist($field);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();
        $html = $crawler->html();
        self::assertIsString($html);
        self::assertStringContainsString('Arkivmall', $html);

        $archiveForm = $crawler->filter(sprintf('form[action="/portal/admin/intake-templates/%d/archive"]', $template->getId()))->form();
        $this->client->submit($archiveForm);
        self::assertResponseRedirects('/portal/admin/categories?show_archived_intake_templates=1');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $template = $this->entityManager->getRepository(TicketIntakeTemplate::class)->findOneBy(['name' => 'Arkivmall']);
        self::assertNotNull($template);
        self::assertFalse($template->isActive());

        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();
        self::assertSame(
            0,
            $crawler->filter(sprintf('form[action="/portal/admin/intake-templates/%d/archive"]', $template->getId()))->count(),
        );
        self::assertSame(
            0,
            $crawler->filter(sprintf('form[action="/portal/admin/intake-templates/%d/restore"]', $template->getId()))->count(),
        );

        $crawler = $this->client->request('GET', '/portal/admin/categories?show_archived_intake_templates=1');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(
            0,
            $crawler->filter(sprintf('form[action="/portal/admin/intake-templates/%d/restore"]', $template->getId()))->count(),
        );
        $restoreForm = $crawler->filter(sprintf('form[action="/portal/admin/intake-templates/%d/restore"]', $template->getId()))->form();
        $this->client->submit($restoreForm);
        self::assertResponseRedirects('/portal/admin/categories?show_archived_intake_templates=1');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $template = $this->entityManager->getRepository(TicketIntakeTemplate::class)->findOneBy(['name' => 'Arkivmall']);
        self::assertNotNull($template);
        self::assertTrue($template->isActive());

        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(
            0,
            $crawler->filter(sprintf('form[action="/portal/admin/intake-templates/%d/archive"]', $template->getId()))->count(),
        );
    }

    public function testAdminCanPublishNewIntakeTemplateVersionWithoutBreakingExistingTickets(): void
    {
        $admin = $this->createAdminUser();
        $customer = new User('version-customer@example.test', 'Vera', 'Version', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $slaPolicy = new SlaPolicy('Versions-SLA', 1, 6);

        $template = new TicketIntakeTemplate('Versionsmall', TicketRequestType::INCIDENT);
        $template->setDefaultSlaPolicy($slaPolicy);
        $field = (new TicketIntakeField(TicketRequestType::INCIDENT, 'affected_service', 'Påverkad tjänst'))
            ->setTemplate($template)
            ->setSortOrder(10);
        $ticket = (new Ticket('DP-6150', 'Versionsbunden ticket', 'Ska fortsätta peka på v1.', TicketStatus::OPEN, TicketVisibility::PRIVATE, TicketPriority::NORMAL, TicketRequestType::INCIDENT, TicketImpactLevel::SINGLE_USER))
            ->setRequester($customer)
            ->setIntakeTemplate($template)
            ->setIntakeAnswers(['affected_service' => 'VPN']);

        $this->entityManager->persist($customer);
        $this->entityManager->persist($slaPolicy);
        $this->entityManager->persist($template);
        $this->entityManager->persist($field);
        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();

        $publishForm = $crawler->filter(sprintf('form[action="/portal/admin/intake-templates/%d/publish-version"]', $template->getId()));
        self::assertGreaterThan(0, $publishForm->count());
        $token = $publishForm->filter('input[name="_token"]')->attr('value');
        self::assertNotNull($token);
        $this->client->request('POST', sprintf('/portal/admin/intake-templates/%d/publish-version', $template->getId()), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/portal/admin/categories');
        $this->client->followRedirect();

        $this->entityManager->clear();

        $templateVersions = $this->entityManager->getRepository(TicketIntakeTemplate::class)->findBy(
            ['versionFamily' => $template->getVersionFamily()],
            ['versionNumber' => 'ASC'],
        );

        self::assertCount(2, $templateVersions);
        self::assertSame(1, $templateVersions[0]->getVersionNumber());
        self::assertFalse($templateVersions[0]->isCurrentVersion());
        self::assertFalse($templateVersions[0]->isActive());
        self::assertSame(2, $templateVersions[1]->getVersionNumber());
        self::assertTrue($templateVersions[1]->isCurrentVersion());
        self::assertTrue($templateVersions[1]->isActive());

        $publishedField = $this->entityManager->getRepository(TicketIntakeField::class)->findOneBy([
            'template' => $templateVersions[1],
            'fieldKey' => 'affected_service',
        ]);
        self::assertNotNull($publishedField);
        self::assertSame('Påverkad tjänst', $publishedField->getLabel());

        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['reference' => 'DP-6150']);
        self::assertNotNull($ticket);
        self::assertSame($templateVersions[0]->getId(), $ticket->getIntakeTemplate()?->getId());
        self::assertSame(['affected_service' => 'VPN'], $ticket->getIntakeAnswers());
        self::assertSame(
            $templateVersions[0]->getDefaultSlaPolicy()?->getId(),
            $templateVersions[1]->getDefaultSlaPolicy()?->getId(),
        );
        self::assertSame($templateVersions[0]->getDefaultPriority(), $templateVersions[1]->getDefaultPriority());
        self::assertSame(
            $templateVersions[0]->getDefaultTeam()?->getId(),
            $templateVersions[1]->getDefaultTeam()?->getId(),
        );
        self::assertSame(
            $templateVersions[0]->getDefaultAssignee()?->getId(),
            $templateVersions[1]->getDefaultAssignee()?->getId(),
        );
        self::assertSame(
            $templateVersions[0]->getDefaultEscalationLevel(),
            $templateVersions[1]->getDefaultEscalationLevel(),
        );
        self::assertSame($templateVersions[0]->getPlaybookText(), $templateVersions[1]->getPlaybookText());
        self::assertSame($templateVersions[0]->getChecklistItems(), $templateVersions[1]->getChecklistItems());
    }

    public function testAdminCannotDeleteLastActiveTemplateVersionWhenRoutingRuleUsesFamily(): void
    {
        $admin = $this->createAdminUser();
        $team = new TechnicianTeam('Core');
        $template = new TicketIntakeTemplate('Skyddad versionsmall', TicketRequestType::INCIDENT);
        $field = (new TicketIntakeField(TicketRequestType::INCIDENT, 'service_window', 'Servicefönster'))
            ->setTemplate($template);
        $rule = (new TicketRoutingRule('Mallfamiljsregel', $team))
            ->setIntakeTemplateFamily($template->getVersionFamily());

        $this->entityManager->persist($team);
        $this->entityManager->persist($template);
        $this->entityManager->persist($field);
        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        $templateId = $template->getId();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/categories');
        self::assertResponseIsSuccessful();

        $deleteForm = $crawler->filter(sprintf('form[action="/portal/admin/intake-templates/%d/delete"]', $templateId))->form();
        $this->client->submit($deleteForm);

        self::assertResponseRedirects('/portal/admin/categories');
        $this->client->followRedirect();

        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->getRepository(TicketIntakeTemplate::class)->find($templateId));
    }

    public function testAdminPortalShowsSlaDashboardQueues(): void
    {
        $admin = $this->createAdminUser();
        $this->entityManager->clear();
        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@test.local']);
        self::assertNotNull($admin);
        $company = new Company('Dashboard AB');
        $technician = new User('dash-tech@example.test', 'Doris', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'TechPassword123'));
        $technician->enableMfa();

        $customer = new User('dash-customer@example.test', 'Kund', 'Dashboard', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $slaPolicy = new SlaPolicy('Dashboard SLA', 4, 24);
        $breachedTicket = new Ticket('DP-6101', 'Bruten SLA', 'Borde synas i adminpanelen.', TicketStatus::OPEN, TicketVisibility::PRIVATE, TicketPriority::HIGH, TicketRequestType::INCIDENT, TicketImpactLevel::COMPANY);
        $breachedTicket->setCompany($company)->setRequester($customer)->setAssignee($technician)->setSlaPolicy($slaPolicy);

        $this->entityManager->persist($company);
        $this->entityManager->persist($technician);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($slaPolicy);
        $this->entityManager->persist($breachedTicket);
        $this->entityManager->flush();

        $this->backdateTicket($breachedTicket, '-30 hours');

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin');
        self::assertResponseIsSuccessful();

        $html = $crawler->html();
        self::assertIsString($html);
        self::assertStringContainsString('SLA &amp; policyer', $html);
        self::assertStringContainsString('DP-6101', $html);
        self::assertStringContainsString('SLA bruten', $html);
    }

    public function testAdminCanUpdateSlaWarningSettings(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/sla');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Spara SLA-varningar')->form([
            'first_response_warning_hours' => '5',
            'resolution_warning_hours' => '10',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/sla');
        $this->client->followRedirect();

        $firstResponseSetting = $this->entityManager->getRepository(SystemSetting::class)->find('sla.first_response_warning_hours');
        $resolutionSetting = $this->entityManager->getRepository(SystemSetting::class)->find('sla.resolution_warning_hours');

        self::assertNotNull($firstResponseSetting);
        self::assertNotNull($resolutionSetting);
        self::assertSame('5', $firstResponseSetting->getSettingValue());
        self::assertSame('10', $resolutionSetting->getSettingValue());
    }

    public function testAdminCanToggleTicketTemplateGuidanceSettings(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/automation');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Spara mallstöd')->form([
            'playbook_enabled' => '1',
            'checklist_enabled' => '1',
            'checklist_progress_enabled' => '1',
            'checklist_customer_visible' => '1',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/automation');
        $this->client->followRedirect();

        $playbookSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_TICKET_TEMPLATE_PLAYBOOK_ENABLED);
        $checklistSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_TICKET_TEMPLATE_CHECKLIST_ENABLED);
        $checklistProgressSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_TICKET_TEMPLATE_CHECKLIST_PROGRESS_ENABLED);
        $checklistCustomerVisibleSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_TICKET_TEMPLATE_CHECKLIST_CUSTOMER_VISIBLE);

        self::assertNotNull($playbookSetting);
        self::assertNotNull($checklistSetting);
        self::assertNotNull($checklistProgressSetting);
        self::assertNotNull($checklistCustomerVisibleSetting);
        self::assertSame('1', $playbookSetting->getSettingValue());
        self::assertSame('1', $checklistSetting->getSettingValue());
        self::assertSame('1', $checklistProgressSetting->getSettingValue());
        self::assertSame('1', $checklistCustomerVisibleSetting->getSettingValue());
    }

    public function testAdminCanUpdateTicketAttachmentSettings(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/automation');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Spara bilageinställningar')->form([
            'attachments_enabled' => '1',
            'max_upload_mb' => '24',
            'storage_path' => 'var/test_ticket_uploads',
            'allowed_extensions' => 'png, pdf, log',
            'external_uploads_enabled' => '1',
            'external_provider_label' => 'OneDrive',
            'external_instructions' => 'Ladda upp stora filer i OneDrive och klistra in länken i ärendet.',
            'zip_archiving_enabled' => '1',
            'zip_archive_after_days' => '7',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/automation');
        $this->client->followRedirect();

        $enabledSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ENABLED);
        $maxUploadSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::TICKET_ATTACHMENTS_MAX_UPLOAD_MB);
        $storageSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::TICKET_ATTACHMENTS_STORAGE_PATH);
        $allowedExtensionsSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::TICKET_ATTACHMENTS_ALLOWED_EXTENSIONS);
        $externalEnabledSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_TICKET_ATTACHMENTS_EXTERNAL_ENABLED);
        $externalProviderSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::TICKET_ATTACHMENTS_EXTERNAL_PROVIDER_LABEL);
        $externalInstructionsSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::TICKET_ATTACHMENTS_EXTERNAL_INSTRUCTIONS);
        $zipArchivingEnabledSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ZIP_ARCHIVING_ENABLED);
        $zipArchiveAfterDaysSetting = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::TICKET_ATTACHMENTS_ZIP_ARCHIVE_AFTER_DAYS);

        self::assertNotNull($enabledSetting);
        self::assertNotNull($maxUploadSetting);
        self::assertNotNull($storageSetting);
        self::assertNotNull($allowedExtensionsSetting);
        self::assertNotNull($externalEnabledSetting);
        self::assertNotNull($externalProviderSetting);
        self::assertNotNull($externalInstructionsSetting);
        self::assertNotNull($zipArchivingEnabledSetting);
        self::assertNotNull($zipArchiveAfterDaysSetting);
        self::assertSame('1', $enabledSetting->getSettingValue());
        self::assertSame('24', $maxUploadSetting->getSettingValue());
        self::assertSame('var/test_ticket_uploads', $storageSetting->getSettingValue());
        self::assertSame('png,pdf,log', $allowedExtensionsSetting->getSettingValue());
        self::assertSame('1', $externalEnabledSetting->getSettingValue());
        self::assertSame('OneDrive', $externalProviderSetting->getSettingValue());
        self::assertSame('Ladda upp stora filer i OneDrive och klistra in länken i ärendet.', $externalInstructionsSetting->getSettingValue());
        self::assertSame('1', $zipArchivingEnabledSetting->getSettingValue());
        self::assertSame('7', $zipArchiveAfterDaysSetting->getSettingValue());
    }

    public function testAdminCanToggleKnowledgeBaseSettingsPerAudience(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/knowledge-base');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Spara kunskapsbasinställningar')->form([
            'knowledge_base_public_enabled' => '1',
            'knowledge_base_public_smart_tips_enabled' => '1',
            'knowledge_base_public_faq_enabled' => '1',
            'knowledge_base_public_technician_contributions_enabled' => '1',
            'knowledge_base_customer_smart_tips_enabled' => '1',
            'knowledge_base_customer_technician_contributions_enabled' => '1',
        ]);
        unset($form['knowledge_base_customer_enabled']);
        unset($form['knowledge_base_customer_faq_enabled']);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/knowledge-base');
        $this->client->followRedirect();

        $publicEnabled = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_KNOWLEDGE_BASE_PUBLIC_ENABLED);
        $publicSmartTips = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_KNOWLEDGE_BASE_PUBLIC_SMART_TIPS_ENABLED);
        $publicFaq = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_KNOWLEDGE_BASE_PUBLIC_FAQ_ENABLED);
        $customerEnabled = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_KNOWLEDGE_BASE_CUSTOMER_ENABLED);
        $customerSmartTips = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_KNOWLEDGE_BASE_CUSTOMER_SMART_TIPS_ENABLED);
        $customerFaq = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_KNOWLEDGE_BASE_CUSTOMER_FAQ_ENABLED);
        $publicTechnicianContributions = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_KNOWLEDGE_BASE_PUBLIC_TECHNICIAN_CONTRIBUTIONS_ENABLED);
        $customerTechnicianContributions = $this->entityManager->getRepository(SystemSetting::class)->find(SystemSettings::FEATURE_KNOWLEDGE_BASE_CUSTOMER_TECHNICIAN_CONTRIBUTIONS_ENABLED);

        self::assertNotNull($publicEnabled);
        self::assertNotNull($publicSmartTips);
        self::assertNotNull($publicFaq);
        self::assertNotNull($customerEnabled);
        self::assertNotNull($customerSmartTips);
        self::assertNotNull($customerFaq);
        self::assertNotNull($publicTechnicianContributions);
        self::assertNotNull($customerTechnicianContributions);
        self::assertSame('1', $publicEnabled->getSettingValue());
        self::assertSame('1', $publicSmartTips->getSettingValue());
        self::assertSame('1', $publicFaq->getSettingValue());
        self::assertSame('0', $customerEnabled->getSettingValue());
        self::assertSame('1', $customerSmartTips->getSettingValue());
        self::assertSame('0', $customerFaq->getSettingValue());
        self::assertSame('1', $publicTechnicianContributions->getSettingValue());
        self::assertSame('1', $customerTechnicianContributions->getSettingValue());
    }

    public function testAdminCanFilterKnowledgeBaseEntriesByDate(): void
    {
        $admin = $this->createAdminUser();
        $olderEntry = new KnowledgeBaseEntry(
            'Äldre guide',
            'Den här posten är äldre än en vecka.',
            KnowledgeBaseEntryType::ARTICLE,
            KnowledgeBaseAudience::PUBLIC,
        );
        $todayEntry = new KnowledgeBaseEntry(
            'Ny guide',
            'Den här posten uppdaterades idag.',
            KnowledgeBaseEntryType::SMART_TIP,
            KnowledgeBaseAudience::CUSTOMER,
        );

        $this->entityManager->persist($olderEntry);
        $this->entityManager->persist($todayEntry);
        $this->entityManager->flush();

        $this->setEntityCreatedAt($olderEntry, new \DateTimeImmutable('-10 days'));
        $this->setEntityCreatedAt($todayEntry, new \DateTimeImmutable('now'));

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/knowledge-base?kb_date=older');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Äldre guide', $html);
        self::assertStringNotContainsString('Ny guide', $html);
        self::assertStringContainsString('option value="older" selected', $html);

        $crawler = $this->client->request('GET', '/portal/admin/knowledge-base?kb_date=today');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Ny guide', $html);
        self::assertStringNotContainsString('Äldre guide', $html);
        self::assertStringContainsString('option value="today" selected', $html);
    }

    public function testAdminCanFilterSettingsNavigatorByDate(): void
    {
        $admin = $this->createAdminUser();

        $contactTitle = new SystemSetting(SystemSettings::CONTACT_PAGE_TITLE, 'Kontakta supporten');
        $homeWidgetTitle = new SystemSetting(SystemSettings::HOME_SUPPORT_WIDGET_TITLE, 'Snabb hjälp');

        $this->entityManager->persist($contactTitle);
        $this->entityManager->persist($homeWidgetTitle);
        $this->entityManager->flush();

        $this->setEntityCreatedAt($contactTitle, new \DateTimeImmutable('-10 days'));
        $this->setEntityCreatedAt($homeWidgetTitle, new \DateTimeImmutable('now'));

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/settings-content?settings_q=kontakt&settings_date=older');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Inställningsnavigator', $html);
        self::assertStringContainsString('Kontaktsida', $html);
        self::assertStringNotContainsString('Startsida & nyheter', $html);
        self::assertStringContainsString('option value="older" selected', $html);
        self::assertStringContainsString('value="kontakt"', $html);
    }

    public function testAdminCanFilterAddonsByStatusAndDate(): void
    {
        $admin = $this->createAdminUser();

        $mailServer = new MailServer('Support SMTP', MailServerDirection::OUTGOING, 'smtp.example.test', 587);
        $knowledgeBaseEntry = new KnowledgeBaseEntry(
            'Ny guide',
            'Nytt innehåll i kunskapsbasen.',
            KnowledgeBaseEntryType::ARTICLE,
            KnowledgeBaseAudience::PUBLIC,
        );

        $this->entityManager->persist($mailServer);
        $this->entityManager->persist($knowledgeBaseEntry);
        $this->entityManager->flush();

        $this->setEntityCreatedAt($mailServer, new \DateTimeImmutable('-10 days'));
        $this->setEntityCreatedAt($knowledgeBaseEntry, new \DateTimeImmutable('now'));

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/addons?addon_q=mail&addon_status=active&addon_date=older');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Mailintegration', $html);
        self::assertStringNotContainsString('Kunskapsbasmodul', $html);
        self::assertStringContainsString('option value="active" selected', $html);
        self::assertStringContainsString('option value="older" selected', $html);
        self::assertStringContainsString('value="mail"', $html);
    }

    public function testAddonSectionIsReadOnlyForManualRegistration(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/addons');
        self::assertResponseIsSuccessful();

        self::assertStringNotContainsString('Lägg till i katalogen', (string) $crawler->html());
        self::assertSame(0, $crawler->filter('input[name="slug"]')->reduce(static fn ($node): bool => 'exempel-addon' === (string) $node->attr('placeholder'))->count());

        $this->client->request('POST', '/portal/admin/addons', [
            '_token' => 'manual-registration-disabled',
            'name' => 'SMS Gateway',
            'slug' => 'SMS Gateway',
            'version' => '1.2.3',
            'install_status' => 'configuring',
            'health_status' => 'warning',
            'source_label' => 'Kundspecifikt paket',
            'description' => 'Skickar SMS vid kritiska notifieringar.',
            'admin_route' => '/portal/admin/status',
            'verified_at' => '2026-04-21 09:30',
            'notes' => 'Kräver separat API-nyckel i produktion.',
            'dependencies' => "Twilio-konto\nNotifieringskö",
            'environment_variables' => "TWILIO_ACCOUNT_SID\nTWILIO_AUTH_TOKEN",
            'setup_checklist' => "Skapa API-nyckel\nVerifiera testnummer",
            'impact_areas' => "Notifieringar\n/portal/admin/status\nSMS-utskick",
            'is_enabled' => '1',
        ]);

        self::assertResponseRedirects('/portal/admin/addons');
        $this->client->followRedirect();

        $addon = $this->entityManager->getRepository(AddonModule::class)->findOneBy(['slug' => 'sms-gateway']);
        self::assertNull($addon);

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('Addon-katalogen är låst', $html);
        self::assertStringContainsString('Addon-katalogen är skrivskyddad i adminen. Bygg eller importera addon-paket i stället.', $html);
    }

    public function testAddonSectionDoesNotAllowEditingRegisteredAddon(): void
    {
        $admin = $this->createAdminUser();
        $addon = new AddonModule('ops-calendar', 'Ops Calendar', 'Visar jour- och beredskapsschema.');
        $addon
            ->setVersion('0.9.0')
            ->setInstallStatus('planned')
            ->setHealthStatus('unknown')
            ->setSourceLabel('Internt paket')
            ->setNotes('Första pilotversionen.')
            ->setDependencies("Schema-api\nSSO")
            ->setEnvironmentVariables("OPS_CALENDAR_URL")
            ->setSetupChecklist("Bekräfta schemaimport")
            ->setEnabled(false);

        $this->entityManager->persist($addon);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/addons');
        self::assertResponseIsSuccessful();

        self::assertSame(0, $crawler->filter(sprintf('form[action="/portal/admin/addons/%d"]', $addon->getId()))->count());

        $this->client->request('POST', sprintf('/portal/admin/addons/%d', $addon->getId()), [
            '_token' => 'manual-update-disabled',
            'name' => 'Ops Calendar Plus',
            'slug' => 'ops-calendar-plus',
            'version' => '1.0.0',
            'install_status' => 'installed',
            'health_status' => 'healthy',
            'source_label' => 'Partnerpaket',
            'description' => 'Visar jour, beredskap och nästa växelöverlämning.',
            'admin_route' => '/portal/admin/overview',
            'verified_at' => '2026-04-21 10:45',
            'notes' => 'Redo för bredare utrullning.',
            'dependencies' => "Schema-api\nSSO\nKvittenswebhook",
            'environment_variables' => "OPS_CALENDAR_URL\nOPS_CALENDAR_TOKEN",
            'setup_checklist' => "Verifiera import\nSlå på synk",
            'impact_areas' => "Jourschema\n/portal/admin/overview\nÖverlämningar",
            'is_enabled' => '1',
        ]);

        self::assertResponseRedirects('/portal/admin/addons');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $updatedAddon = $this->entityManager->getRepository(AddonModule::class)->find($addon->getId());
        self::assertNotNull($updatedAddon);
        self::assertSame('ops-calendar', $updatedAddon->getSlug());
        self::assertSame('Ops Calendar', $updatedAddon->getName());
        self::assertSame('0.9.0', $updatedAddon->getVersion());
        self::assertSame('planned', $updatedAddon->getInstallStatus());
        self::assertSame('unknown', $updatedAddon->getHealthStatus());
        self::assertSame('Internt paket', $updatedAddon->getSourceLabel());
        self::assertFalse($updatedAddon->isEnabled());

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('Addon "Ops Calendar" kan inte ändras i adminen.', $html);
        self::assertStringNotContainsString('Spara metadata', $html);
        self::assertStringContainsString('Planerad', $html);
        self::assertStringContainsString('Ej verifierad', $html);
    }

    public function testAdminCanUploadAddonZipPackageAndRegisterItAutomatically(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/addons');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action="/portal/admin/addons/upload-package"]')->form();
        $zipPath = $this->createValidAddonPackageZip();
        $form['addon_package'] = new UploadedFile($zipPath, 'status-board.zip', 'application/zip', null, true);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/addons');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $addon = $this->entityManager->getRepository(AddonModule::class)->findOneBy(['slug' => 'status-board']);
        self::assertNotNull($addon);
        self::assertSame('Status Board', $addon->getName());
        self::assertSame('1.4.0', $addon->getVersion());
        self::assertSame('Zip-import', $addon->getSourceLabel());
        self::assertSame('configuring', $addon->getInstallStatus());
        self::assertSame(['STATUS_BOARD_API_KEY'], $addon->getEnvironmentVariablesList());
        self::assertSame(['Publik status', 'Adminöversikt'], $addon->getImpactAreasList());

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('Addon-paketet "Status Board" (1.4.0) laddades upp och registrerades automatiskt.', $html);
        self::assertStringContainsString('Ladda upp addon som zip', $html);
        self::assertStringContainsString('Importerade addon-paket', $html);
        self::assertStringContainsString('Aktivt paket:', $html);
        self::assertStringContainsString('Aktivt paket 1.4.0', $html);
        self::assertStringContainsString('Paketversioner', $html);
    }

    public function testAdminCanRollbackAddonPackageToEarlierVersion(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/addons');
        self::assertResponseIsSuccessful();

        $uploadForm = $crawler->filter('form[action="/portal/admin/addons/upload-package"]')->form();
        $uploadForm['addon_package'] = new UploadedFile($this->createValidAddonPackageZip('1.4.0'), 'status-board-1.4.0.zip', 'application/zip', null, true);
        $this->client->submit($uploadForm);
        self::assertResponseRedirects('/portal/admin/addons');
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/portal/admin/addons');
        self::assertResponseIsSuccessful();

        $uploadForm = $crawler->filter('form[action="/portal/admin/addons/upload-package"]')->form();
        $uploadForm['addon_package'] = new UploadedFile($this->createValidAddonPackageZip('1.5.0'), 'status-board-1.5.0.zip', 'application/zip', null, true);
        $this->client->submit($uploadForm);
        self::assertResponseRedirects('/portal/admin/addons');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $addon = $this->entityManager->getRepository(AddonModule::class)->findOneBy(['slug' => 'status-board']);
        self::assertNotNull($addon);

        $crawler = $this->client->request('GET', '/portal/admin/addons');
        self::assertResponseIsSuccessful();
        $rollbackForm = $crawler->filter(sprintf('form[action="/portal/admin/addons/%d/packages/activate"] input[value="1.4.0"]', $addon->getId()))->ancestors()->filter('form')->form();
        $this->client->submit($rollbackForm);

        self::assertResponseRedirects('/portal/admin/addons');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $addon = $this->entityManager->getRepository(AddonModule::class)->findOneBy(['slug' => 'status-board']);
        self::assertNotNull($addon);
        self::assertSame('1.4.0', $addon->getVersion());

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('Addon "Status Board" använder nu paketversion 1.4.0 som aktiv version.', $html);
        self::assertStringContainsString('Aktiv version', $html);
        self::assertStringContainsString('Tidigare version', $html);
        self::assertStringContainsString('Aktivera denna version', $html);
    }

    public function testNonOwnerAdminCannotReleaseAddon(): void
    {
        $admin = $this->createAdminUser();
        $addon = (new AddonModule('release-candidate', 'Release Candidate', 'Redo för release men skyddad.'))
            ->setInstallStatus('installed')
            ->setHealthStatus('healthy')
            ->setVerifiedAt(new \DateTimeImmutable('2026-04-21 11:00'))
            ->setSetupChecklist("Verifiera API\nVerifiera UI");

        $this->entityManager->persist($addon);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/addons');
        self::assertResponseIsSuccessful();

        $releaseForm = $crawler->filter(sprintf('form[action="/portal/admin/addons/%d/release"]', $addon->getId()));
        self::assertSame(0, $releaseForm->count());

        $this->client->request('POST', sprintf('/portal/admin/addons/%d/release', $addon->getId()), [
            '_token' => 'not-needed-for-non-owner',
        ]);

        self::assertResponseRedirects('/portal/admin/addons');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $addon = $this->entityManager->getRepository(AddonModule::class)->find($addon->getId());
        self::assertNotNull($addon);
        self::assertFalse($addon->isReleased());

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('Bara ägarkontot för addon-release får släppa addons till ärendesystemet.', $html);
    }

    public function testConfiguredOwnerCanReleaseAddon(): void
    {
        $owner = $this->createReleaseOwnerUser();
        $addon = (new AddonModule('owner-release', 'Owner Release', 'Ska kunna släppas av owner.'))
            ->setInstallStatus('installed')
            ->setHealthStatus('healthy')
            ->setVerifiedAt(new \DateTimeImmutable('2026-04-21 11:15'))
            ->setSetupChecklist("Verifiera auth\nVerifiera notifiering");

        $this->entityManager->persist($addon);
        $this->entityManager->flush();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/portal/admin/addons');
        self::assertResponseIsSuccessful();

        $releaseForm = $crawler->filter(sprintf('form[action="/portal/admin/addons/%d/release"]', $addon->getId()));
        self::assertGreaterThan(0, $releaseForm->count());
        $this->client->submit($releaseForm->form([
            'release_notes' => 'Första godkända versionen för skarp användning i ärendesystemet.',
        ]));

        self::assertResponseRedirects('/portal/admin/addons');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $addon = $this->entityManager->getRepository(AddonModule::class)->find($addon->getId());
        self::assertNotNull($addon);
        self::assertTrue($addon->isReleased());
        self::assertSame('owner-addon@example.test', $addon->getReleasedByEmail());
        self::assertTrue($addon->isEnabled());
        self::assertNotNull($addon->getReleasedAt());
        $releaseLogs = $this->entityManager->getRepository(AddonReleaseLog::class)->findBy(['addon' => $addon], ['releasedAt' => 'DESC']);
        self::assertCount(1, $releaseLogs);
        self::assertSame('owner-addon@example.test', $releaseLogs[0]->getReleasedByEmail());
        self::assertStringContainsString('status installed', $releaseLogs[0]->getSummary());
        self::assertStringContainsString('health healthy', $releaseLogs[0]->getSummary());
        self::assertSame('Första godkända versionen för skarp användning i ärendesystemet.', $releaseLogs[0]->getReleaseNotes());
        $releasedAtLabel = $releaseLogs[0]->getReleasedAt()->format('Y-m-d H:i');

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('Registrerade addons', $html);
        self::assertStringContainsString('Aktiva addons', $html);
        self::assertStringContainsString('Inaktiva addons', $html);
        self::assertStringNotContainsString('Släppta addons', $html);
        self::assertStringNotContainsString('Blockerade addons', $html);
        self::assertStringNotContainsString('Senaste släpp', $html);
        self::assertStringNotContainsString('Release till ärendesystemet', $html);
        self::assertStringNotContainsString('Rekommenderat addonflöde', $html);
        self::assertStringContainsString($releasedAtLabel, $html);
        self::assertStringContainsString('släpptes till ärendesystemet av owner-addon@example.test', $html);
        self::assertStringContainsString('Släppt till ärendesystemet', $html);
        self::assertStringContainsString('Releasehistorik', $html);
        self::assertStringContainsString('owner-addon@example.test', $html);
        self::assertStringContainsString('Första godkända versionen för skarp användning i ärendesystemet.', $html);
    }

    public function testOwnerMustWriteReleaseNotesBeforeRelease(): void
    {
        $owner = $this->createReleaseOwnerUser();
        $addon = (new AddonModule('owner-release-notes', 'Owner Release Notes', 'Ska kräva release notes.'))
            ->setInstallStatus('installed')
            ->setHealthStatus('healthy')
            ->setVerifiedAt(new \DateTimeImmutable('2026-04-21 11:20'))
            ->setSetupChecklist("Verifiera steg ett\nVerifiera steg två");

        $this->entityManager->persist($addon);
        $this->entityManager->flush();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/portal/admin/addons');
        self::assertResponseIsSuccessful();

        $releaseForm = $crawler->filter(sprintf('form[action="/portal/admin/addons/%d/release"]', $addon->getId()));
        self::assertGreaterThan(0, $releaseForm->count());
        $this->client->submit($releaseForm->form([
            'release_notes' => '',
        ]));

        self::assertResponseRedirects('/portal/admin/addons');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $addon = $this->entityManager->getRepository(AddonModule::class)->find($addon->getId());
        self::assertNotNull($addon);
        self::assertFalse($addon->isReleased());

        $releaseLogs = $this->entityManager->getRepository(AddonReleaseLog::class)->findBy(['addon' => $addon]);
        self::assertCount(0, $releaseLogs);

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('Skriv release notes innan addonet släpps.', $html);
    }

    public function testOwnerCanRevokeLatestAddonRelease(): void
    {
        $owner = $this->createReleaseOwnerUser();
        $addon = (new AddonModule('owner-release-revoke', 'Owner Release Revoke', 'Ska kunna rollbackas.'))
            ->setInstallStatus('installed')
            ->setHealthStatus('healthy')
            ->setVerifiedAt(new \DateTimeImmutable('2026-04-21 11:30'))
            ->setSetupChecklist("Verifiera release ett\nVerifiera release två");

        $this->entityManager->persist($addon);
        $this->entityManager->flush();

        $releaseLog = new AddonReleaseLog(
            $addon,
            'owner-addon@example.test',
            '1.0.0',
            'status installed · health healthy',
            'Versionen orsakade fel i produktion.',
        );
        $addon
            ->setReleasedAt($releaseLog->getReleasedAt())
            ->setReleasedByEmail('owner-addon@example.test')
            ->setEnabled(true);
        $this->entityManager->persist($releaseLog);
        $this->entityManager->flush();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/portal/admin/addons');
        self::assertResponseIsSuccessful();

        $revokeForm = $crawler->filter(sprintf('form[action="/portal/admin/addons/release-logs/%d/revoke"]', $releaseLog->getId()));
        self::assertGreaterThan(0, $revokeForm->count());
        $this->client->submit($revokeForm->form([
            'revoke_notes' => 'Rollback efter verifierade produktionsfel i notifieringskedjan.',
        ]));

        self::assertResponseRedirects('/portal/admin/addons');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $addon = $this->entityManager->getRepository(AddonModule::class)->find($addon->getId());
        self::assertNotNull($addon);
        self::assertFalse($addon->isReleased());
        self::assertFalse($addon->isEnabled());
        self::assertSame('blocked', $addon->getInstallStatus());

        $releaseLog = $this->entityManager->getRepository(AddonReleaseLog::class)->find($releaseLog->getId());
        self::assertNotNull($releaseLog);
        self::assertTrue($releaseLog->isRevoked());
        self::assertSame('owner-addon@example.test', $releaseLog->getRevokedByEmail());
        self::assertSame('Rollback efter verifierade produktionsfel i notifieringskedjan.', $releaseLog->getRevokeNotes());
        $releasedAtLabel = $releaseLog->getReleasedAt()->format('Y-m-d H:i');
        $revokedAtLabel = $releaseLog->getRevokedAt()?->format('Y-m-d H:i');

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('Registrerade addons', $html);
        self::assertStringContainsString('Aktiva addons', $html);
        self::assertStringContainsString('Inaktiva addons', $html);
        self::assertStringNotContainsString('Släppta addons', $html);
        self::assertStringNotContainsString('Blockerade addons', $html);
        self::assertStringNotContainsString('Senaste släpp', $html);
        self::assertStringNotContainsString('Release till ärendesystemet', $html);
        self::assertStringNotContainsString('Rekommenderat addonflöde', $html);
        self::assertStringContainsString($releasedAtLabel, $html);
        self::assertStringContainsString('drogs tillbaka och addonet markerades som blockerat', $html);
        self::assertStringContainsString('Indragen', $html);
        self::assertStringContainsString('Rollback-notering:', $html);
        self::assertNotNull($revokedAtLabel);
        self::assertStringContainsString($revokedAtLabel, $html);
        self::assertStringContainsString('Owner Release Revoke', $html);
    }

    public function testAddonAdminShowsMigrationWarningWhenAddonSchemaIsOutdated(): void
    {
        $admin = $this->createSuperAdminUser();
        $connection = $this->entityManager->getConnection();

        $connection->executeStatement('DROP TABLE IF EXISTS addon_release_logs');
        $connection->executeStatement('DROP TABLE IF EXISTS addon_modules');
        $connection->executeStatement('CREATE TABLE addon_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            slug VARCHAR(120) NOT NULL,
            name VARCHAR(180) NOT NULL,
            description CLOB NOT NULL,
            version VARCHAR(64) DEFAULT NULL,
            admin_route VARCHAR(255) DEFAULT NULL,
            source_label VARCHAR(120) DEFAULT NULL,
            notes CLOB DEFAULT NULL,
            is_enabled BOOLEAN DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/addons');

        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('Addon-databasen behöver migreras', $html);
        self::assertStringContainsString('install_status', $html);
        self::assertStringContainsString('Kör migreringar nu', $html);
        self::assertStringNotContainsString('Registrera nytt addon', $html);
    }

    public function testAdminOverviewHandlesOutdatedNewsSchemaGracefully(): void
    {
        $admin = $this->createAdminUser();
        $connection = $this->entityManager->getConnection();

        $connection->executeStatement('DROP TABLE IF EXISTS news_articles');
        $connection->executeStatement('CREATE TABLE news_articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            title VARCHAR(180) NOT NULL,
            summary CLOB NOT NULL,
            body CLOB NOT NULL,
            image_url VARCHAR(2048) DEFAULT NULL,
            maintenance_starts_at DATETIME DEFAULT NULL,
            maintenance_ends_at DATETIME DEFAULT NULL,
            is_published BOOLEAN NOT NULL,
            published_at DATETIME NOT NULL,
            is_pinned BOOLEAN NOT NULL,
            category VARCHAR(255) NOT NULL,
            author_id INTEGER DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin');

        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('Nyheter', $html);
        self::assertStringContainsString('Inga nyheter publicerade ännu', $html);
    }

    public function testAdminNewsShowsMigrationWarningWhenNewsSchemaIsOutdated(): void
    {
        $admin = $this->createAdminUser();
        $connection = $this->entityManager->getConnection();

        $connection->executeStatement('DROP TABLE IF EXISTS news_articles');
        $connection->executeStatement('CREATE TABLE news_articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            title VARCHAR(180) NOT NULL,
            summary CLOB NOT NULL,
            body CLOB NOT NULL,
            image_url VARCHAR(2048) DEFAULT NULL,
            maintenance_starts_at DATETIME DEFAULT NULL,
            maintenance_ends_at DATETIME DEFAULT NULL,
            is_published BOOLEAN NOT NULL,
            published_at DATETIME NOT NULL,
            is_pinned BOOLEAN NOT NULL,
            category VARCHAR(255) NOT NULL,
            author_id INTEGER DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/portal/admin/nyheter');

        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('Nyhetsdatabasen behöver migreras', $html);
        self::assertStringContainsString('archived_at', $html);
    }

    public function testAddonSectionShowsNewsEditorPlusStatusCard(): void
    {
        $admin = $this->createAdminUser();
        $addon = (new AddonModule('news-editor-plus', 'News Editor Plus', 'Utökad editor för nyhetsmodulen.'))
            ->setVersion('1.0.0')
            ->setInstallStatus('installed')
            ->setHealthStatus('healthy')
            ->setAdminRoute('/portal/admin/nyheter')
            ->setImpactAreas("Nyhetsmodul\n/portal/admin/nyheter\n/portal/technician/nyheter\nPublik artikelrendering")
            ->setReleasedAt(new \DateTimeImmutable('2026-04-21 12:00'))
            ->setReleasedByEmail('system@driftpunkt.local')
            ->setEnabled(true);
        $releaseLog = new AddonReleaseLog(
            $addon,
            'system@driftpunkt.local',
            '1.0.0',
            'Core addon seeded as released in addon catalog.',
            'News Editor Plus markerades som släppt för att spegla kärnstatusen i addon-katalogen.',
        );

        $this->entityManager->persist($addon);
        $this->entityManager->persist($releaseLog);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/addons');

        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Påverkade ytor', $html);
        self::assertStringContainsString('Addonet är aktivt och påverkar följande moduler, vyer eller funktioner.', $html);
        self::assertStringContainsString('/portal/admin/nyheter', $html);
        self::assertStringContainsString('/portal/technician/nyheter', $html);
        self::assertStringContainsString('Publik artikelrendering', $html);
        self::assertStringContainsString('Släppt till ärendesystemet', $html);
        self::assertStringContainsString('system@driftpunkt.local', $html);
        self::assertSame(0, $crawler->filter(sprintf('form[action="/portal/admin/addons/%d"]', $addon->getId()))->count());
        self::assertStringContainsString('Inaktivera addon', $html);

        $toggleForm = $crawler->filter(sprintf('form[action="/portal/admin/addons/%d/toggle-enabled"]', $addon->getId()));
        self::assertSame(1, $toggleForm->count());
        $this->client->submit($toggleForm->form([
            'enable' => '0',
        ]));

        self::assertResponseRedirects('/portal/admin/addons');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $addon = $this->entityManager->getRepository(AddonModule::class)->findOneBy(['slug' => 'news-editor-plus']);
        self::assertInstanceOf(AddonModule::class, $addon);
        self::assertFalse($addon->isEnabled());

        $html = (string) $this->client->getCrawler()->html();
        self::assertStringContainsString('Addon "News Editor Plus" inaktiverades i portalen.', $html);
        self::assertStringContainsString('Addonet är avstängt, men de här ytorna påverkas när det aktiveras igen.', $html);
        self::assertStringContainsString('/portal/technician/nyheter', $html);
        self::assertStringContainsString('Aktivera addon', $html);
    }

    public function testAdminCanQueueDatabaseMigrationsFromAddonWarning(): void
    {
        $admin = $this->createSuperAdminUser();
        $connection = $this->entityManager->getConnection();

        $connection->executeStatement('DROP TABLE IF EXISTS addon_release_logs');
        $connection->executeStatement('DROP TABLE IF EXISTS addon_modules');
        $connection->executeStatement('CREATE TABLE addon_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            slug VARCHAR(120) NOT NULL,
            name VARCHAR(180) NOT NULL,
            description CLOB NOT NULL,
            version VARCHAR(64) DEFAULT NULL,
            admin_route VARCHAR(255) DEFAULT NULL,
            source_label VARCHAR(120) DEFAULT NULL,
            notes CLOB DEFAULT NULL,
            is_enabled BOOLEAN DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/addons');
        self::assertResponseIsSuccessful();

        $this->client->submit(
            $crawler->filter('form[action="/portal/admin/database/migrations/run"]')->form(),
        );

        self::assertResponseRedirects('/portal/admin/addons');
        $this->client->followRedirect();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Databasmigreringen köades som körning', $html);

        $runFiles = glob(dirname(__DIR__, 2).'/var/post_update_runs/*.json') ?: [];
        self::assertCount(1, $runFiles);

        $run = json_decode((string) file_get_contents($runFiles[0]), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['doctrine_migrate', 'cache_clear'], $run['selectedTasks']);
    }

    public function testAdminCanFilterNewsEntriesByDate(): void
    {
        $admin = $this->createAdminUser();
        $olderArticle = new NewsArticle('Äldre driftinfo', 'Äldre sammanfattning', 'Äldre brödtext');
        $olderArticle->setCategory(NewsCategory::GENERAL)->setPublishedAt(new \DateTimeImmutable('-10 days'))->publish();

        $todayArticle = new NewsArticle('Ny driftinfo', 'Ny sammanfattning', 'Ny brödtext');
        $todayArticle->setCategory(NewsCategory::GENERAL)->setPublishedAt(new \DateTimeImmutable('now'))->publish();

        $this->entityManager->persist($olderArticle);
        $this->entityManager->persist($todayArticle);
        $this->entityManager->flush();

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/nyheter?news_date=older');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Äldre driftinfo', $html);
        self::assertStringNotContainsString('Ny driftinfo', $html);
        self::assertStringContainsString('option value="older" selected', $html);

        $crawler = $this->client->request('GET', '/portal/admin/nyheter?news_date=today');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Ny driftinfo', $html);
        self::assertStringNotContainsString('Äldre driftinfo', $html);
        self::assertStringContainsString('option value="today" selected', $html);
    }

    public function testAdminCanFilterCategoriesAndIntakeByDate(): void
    {
        $admin = $this->createAdminUser();

        $olderCategory = (new TicketCategory('VPN äldre'))->setDescription('Äldre kategori för VPN');
        $todayCategory = (new TicketCategory('Skrivare ny'))->setDescription('Ny kategori för skrivare');

        $olderTemplate = (new TicketIntakeTemplate('VPN intake äldre', TicketRequestType::INCIDENT))
            ->setCategory($olderCategory)
            ->setDescription('Äldre mall för VPN-incidenter');
        $todayTemplate = (new TicketIntakeTemplate('Skrivare intake ny', TicketRequestType::SERVICE_REQUEST))
            ->setCategory($todayCategory)
            ->setDescription('Ny mall för skrivarfel');

        $olderField = (new TicketIntakeField(TicketRequestType::INCIDENT, 'vpn_gateway', 'VPN-gateway'))
            ->setCategory($olderCategory)
            ->setHelpText('Äldre fält för VPN')
            ->setTemplate($olderTemplate)
            ->setFieldType(TicketIntakeFieldType::TEXT);
        $todayField = (new TicketIntakeField(TicketRequestType::SERVICE_REQUEST, 'printer_model', 'Skrivarmodell'))
            ->setCategory($todayCategory)
            ->setHelpText('Nytt fält för skrivare')
            ->setTemplate($todayTemplate)
            ->setFieldType(TicketIntakeFieldType::TEXT);

        $this->entityManager->persist($olderCategory);
        $this->entityManager->persist($todayCategory);
        $this->entityManager->persist($olderTemplate);
        $this->entityManager->persist($todayTemplate);
        $this->entityManager->persist($olderField);
        $this->entityManager->persist($todayField);
        $this->entityManager->flush();

        $this->setEntityCreatedAt($olderCategory, new \DateTimeImmutable('-10 days'));
        $this->setEntityCreatedAt($olderTemplate, new \DateTimeImmutable('-10 days'));
        $this->setEntityCreatedAt($olderField, new \DateTimeImmutable('-10 days'));
        $this->setEntityCreatedAt($todayCategory, new \DateTimeImmutable('now'));
        $this->setEntityCreatedAt($todayTemplate, new \DateTimeImmutable('now'));
        $this->setEntityCreatedAt($todayField, new \DateTimeImmutable('now'));

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/categories?category_q=vpn&category_date=older');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Filter för kategorier &amp; inflöden', $html);
        self::assertStringContainsString('VPN äldre', $html);
        self::assertStringContainsString('VPN intake äldre', $html);
        self::assertStringContainsString('VPN-gateway', $html);
        self::assertStringNotContainsString('Skrivare ny', $html);
        self::assertStringNotContainsString('Skrivare intake ny', $html);
        self::assertStringNotContainsString('Skrivarmodell', $html);
        self::assertStringContainsString('option value="older" selected', $html);
        self::assertStringContainsString('value="vpn"', $html);
    }

    public function testAdminCanFilterRoutingRulesByDate(): void
    {
        $admin = $this->createAdminUser();
        $team = new TechnicianTeam('NOC');

        $olderCategory = new TicketCategory('Nätverk');
        $todayCategory = new TicketCategory('Klient');

        $olderRule = (new TicketRoutingRule('VPN routing äldre', $team))
            ->setCategory($olderCategory)
            ->setRequestType(TicketRequestType::INCIDENT)
            ->setImpactLevel(TicketImpactLevel::CRITICAL_SERVICE)
            ->setIntakeFieldKey('vpn_gateway')
            ->setIntakeFieldValue('stockholm');

        $todayRule = (new TicketRoutingRule('Skrivare routing ny', $team))
            ->setCategory($todayCategory)
            ->setRequestType(TicketRequestType::SERVICE_REQUEST)
            ->setImpactLevel(TicketImpactLevel::SINGLE_USER)
            ->setIntakeFieldKey('printer_model')
            ->setIntakeFieldValue('hp');

        $this->entityManager->persist($team);
        $this->entityManager->persist($olderCategory);
        $this->entityManager->persist($todayCategory);
        $this->entityManager->persist($olderRule);
        $this->entityManager->persist($todayRule);
        $this->entityManager->flush();

        $this->setEntityCreatedAt($olderRule, new \DateTimeImmutable('-10 days'));
        $this->setEntityCreatedAt($todayRule, new \DateTimeImmutable('now'));

        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/automation?automation_q=vpn&automation_date=older');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Filter för automation', $html);
        self::assertStringContainsString('VPN routing äldre', $html);
        self::assertStringNotContainsString('Skrivare routing ny', $html);
        self::assertStringContainsString('option value="older" selected', $html);
        self::assertStringContainsString('value="vpn"', $html);
    }

    public function testKnowledgeBaseSearchUsesAudienceSpecificTipsAndFaqSettings(): void
    {
        $customer = new User('kb-customer@example.test', 'Klara', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));

        $publicTip = new KnowledgeBaseEntry(
            'Publikt tips',
            'Publik guide för lösenordsbyte.',
            KnowledgeBaseEntryType::SMART_TIP,
            KnowledgeBaseAudience::PUBLIC,
        );
        $customerFaq = new KnowledgeBaseEntry(
            'Kundfråga',
            'Internt kundsvar om VPN.',
            KnowledgeBaseEntryType::FAQ,
            KnowledgeBaseAudience::CUSTOMER,
        );

        $this->entityManager->persist($customer);
        $this->entityManager->persist($publicTip);
        $this->entityManager->persist($customerFaq);
        $this->entityManager->persist(new SystemSetting(SystemSettings::FEATURE_KNOWLEDGE_BASE_PUBLIC_ENABLED, '1'));
        $this->entityManager->persist(new SystemSetting(SystemSettings::FEATURE_KNOWLEDGE_BASE_CUSTOMER_ENABLED, '1'));
        $this->entityManager->persist(new SystemSetting(SystemSettings::FEATURE_KNOWLEDGE_BASE_PUBLIC_SMART_TIPS_ENABLED, '1'));
        $this->entityManager->persist(new SystemSetting(SystemSettings::FEATURE_KNOWLEDGE_BASE_PUBLIC_FAQ_ENABLED, '0'));
        $this->entityManager->persist(new SystemSetting(SystemSettings::FEATURE_KNOWLEDGE_BASE_CUSTOMER_SMART_TIPS_ENABLED, '0'));
        $this->entityManager->persist(new SystemSetting(SystemSettings::FEATURE_KNOWLEDGE_BASE_CUSTOMER_FAQ_ENABLED, '1'));
        $this->entityManager->flush();

        $publicCrawler = $this->client->request('GET', '/kunskapsbas?q=guide');
        self::assertResponseIsSuccessful();
        $publicHtml = $publicCrawler->html();
        self::assertIsString($publicHtml);
        self::assertStringContainsString('Publikt tips', $publicHtml);
        self::assertStringNotContainsString('Kundfråga', $publicHtml);

        $this->client->loginUser($customer);
        $customerCrawler = $this->client->request('GET', '/portal/customer/kunskapsbas?q=vpn');
        self::assertResponseIsSuccessful();
        $customerHtml = $customerCrawler->html();
        self::assertIsString($customerHtml);
        self::assertStringContainsString('Kundfråga', $customerHtml);
        self::assertStringNotContainsString('Publikt tips', $customerHtml);
    }

    public function testAdminCanAddLocaleAndSaveTranslationsFromAdmin(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/portal/admin/languages?translations_locale=de&translations_per_page=100');
        self::assertResponseIsSuccessful();

        $localeForm = $crawler->selectButton('Spara språklista')->form();
        $localeForm['locale_codes[0]'] = 'sv';
        $localeForm['locale_codes[1]'] = 'en';
        $localeForm['locale_codes[2]'] = 'de';
        $localeForm['locale_names[0]'] = 'Svenska';
        $localeForm['locale_names[1]'] = 'English';
        $localeForm['locale_names[2]'] = 'Deutsch';
        $localeForm['selected_translation_locale'] = 'de';
        $this->client->submit($localeForm);

        self::assertResponseStatusCodeSame(302);
        self::assertStringStartsWith(
            '/portal/admin/languages?translations_locale=de',
            (string) $this->client->getResponse()->headers->get('Location'),
        );
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/portal/admin/languages?translations_locale=de&translations_per_page=100');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Spara översättningar')->form();
        $form['translations[ui.language]'] = 'Sprache';
        $form['translations[nav.home]'] = 'Startseite';
        $form['translations[nav.login]'] = 'Anmelden';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(302);
        self::assertStringStartsWith(
            '/portal/admin/languages?translations_locale=de',
            (string) $this->client->getResponse()->headers->get('Location'),
        );
        $this->client->followRedirect();

        $this->client->request('GET', '/sprak/de?returnTo=%2F');
        self::assertResponseRedirects('/');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $html = $crawler->html();
        self::assertIsString($html);
        self::assertStringContainsString('Sprache', $html);
        self::assertStringContainsString('Anmelden', $html);
        self::assertStringContainsString('Deutsch', $html);

    }

    public function testAdminLogsPageShowsSystemLogEntries(): void
    {
        $admin = $this->createAdminUser();
        $this->client->loginUser($admin);

        $logDirectory = dirname(__DIR__, 2).'/var/log';
        mkdir($logDirectory, 0777, true);

        file_put_contents($logDirectory.'/admin-test.log', implode("\n", [
            '[2026-04-23T19:10:00+00:00] app.ERROR: Testfel i adminloggen {"exception":"RuntimeException"} []',
            '[2026-04-23T19:11:00+00:00] app.WARNING: Varning från övervakningen [] []',
        ]));

        $crawler = $this->client->request('GET', '/portal/admin/logs?system_log_file=admin-test.log&system_log_level=error');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Systemloggar', $html);
        self::assertStringContainsString('admin-test.log', $html);
        self::assertStringContainsString('Testfel i adminloggen', $html);
        self::assertStringNotContainsString('Varning från övervakningen', $html);
    }

    private function createAdminUser(): User
    {
        return $this->createAdminUserWithEmail('admin@test.local', UserType::ADMIN);
    }

    private function createSuperAdminUser(): User
    {
        return $this->createAdminUserWithEmail('super-admin@test.local', UserType::SUPER_ADMIN);
    }

    private function createRegularAdminUser(): User
    {
        return $this->createAdminUserWithEmail('regular-admin@test.local', UserType::ADMIN);
    }

    private function createAdminUserWithEmail(string $email, UserType $type = UserType::ADMIN): User
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

    private function createReleaseOwnerUser(): User
    {
        $admin = new User('owner-addon@example.test', 'Olivia', 'Owner', UserType::SUPER_ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'AdminPassword123'));
        $admin->enableMfa();

        $this->entityManager->persist($admin);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'owner-addon@example.test']);
        self::assertNotNull($admin);

        return $admin;
    }

    private function createTinyPng(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'branding-logo-');
        self::assertNotFalse($path);
        file_put_contents(
            $path,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X2uoAAAAASUVORK5CYII=', true),
        );

        return $path;
    }

    private function setEntityCreatedAt(object $entity, \DateTimeImmutable $createdAt): void
    {
        $repository = $this->entityManager->getRepository($entity::class);

        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            self::assertNotNull($id);
            $managedEntity = $repository->find($id);
        } elseif (method_exists($entity, 'getSettingKey')) {
            $managedEntity = $repository->find($entity->getSettingKey());
        } else {
            self::fail('Entiteten saknar identifierare och kan inte tidsjusteras i testet.');
        }
        self::assertNotNull($managedEntity);

        if (method_exists($managedEntity, 'setCreatedAt')) {
            $managedEntity->setCreatedAt($createdAt);
        } else {
            $createdProperty = new \ReflectionProperty($managedEntity, 'createdAt');
            $createdProperty->setValue($managedEntity, $createdAt);
        }

        if (method_exists($managedEntity, 'setUpdatedAt')) {
            $managedEntity->setUpdatedAt($createdAt);
        } else {
            $updatedProperty = new \ReflectionProperty($managedEntity, 'updatedAt');
            $updatedProperty->setValue($managedEntity, $createdAt);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function createValidUpdateZip(): string
    {
        $root = sys_get_temp_dir().'/driftpunkt-update-'.bin2hex(random_bytes(4));
        mkdir($root.'/package/bin', 0777, true);
        mkdir($root.'/package/config/packages', 0777, true);
        mkdir($root.'/package/public', 0777, true);
        mkdir($root.'/package/src', 0777, true);
        mkdir($root.'/package/templates', 0777, true);
        mkdir($root.'/package/vendor', 0777, true);

        file_put_contents($root.'/package/bin/console', "#!/usr/bin/env php\n<?php\n");
        file_put_contents($root.'/package/composer.json', json_encode([
            'name' => 'driftpunkt/test-release',
            'version' => '2.0.0',
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        file_put_contents($root.'/package/composer.lock', json_encode([
            'packages' => [],
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        file_put_contents($root.'/package/config/packages/framework.yaml', "framework:\n    secret: test\n");
        file_put_contents($root.'/package/public/index.php', "<?php\n");
        file_put_contents($root.'/package/src/Kernel.php', "<?php\n");
        file_put_contents($root.'/package/templates/base.html.twig', "<html></html>\n");
        file_put_contents($root.'/package/vendor/autoload.php', "<?php\n");

        $zipPath = tempnam(sys_get_temp_dir(), 'driftpunkt-update-');
        if (false === $zipPath) {
            throw new \RuntimeException('Kunde inte skapa temporär zip-fil för testet.');
        }
        @unlink($zipPath);
        $zipPath .= '.zip';

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if (true !== $result) {
            throw new \RuntimeException('Kunde inte öppna zip-fil för testet.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root.'/package', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), \strlen($root.'/package/') );
            $zip->addFile($file->getPathname(), 'package/'.$relativePath);
        }

        $zip->close();
        $this->removeDirectory($root);

        return $zipPath;
    }

    private function createValidAddonPackageZip(string $version = '1.4.0'): string
    {
        $root = sys_get_temp_dir().'/driftpunkt-addon-upload-'.bin2hex(random_bytes(4));
        mkdir($root.'/package/files/src/Module/StatusBoard/Controller', 0777, true);
        mkdir($root.'/package/files/templates/status_board', 0777, true);

        file_put_contents($root.'/package/addon.json', json_encode([
            'slug' => 'status-board',
            'name' => 'Status Board',
            'description' => 'Visar statuskort och driftinformation.',
            'version' => $version,
            'files' => 'files',
            'install_status' => 'configuring',
            'health_status' => 'unknown',
            'environment_variables' => ['STATUS_BOARD_API_KEY'],
            'impact_areas' => ['Publik status', 'Adminöversikt'],
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
        file_put_contents($root.'/package/files/src/Module/StatusBoard/Controller/StatusBoardController.php', "<?php\n");
        file_put_contents($root.'/package/files/templates/status_board/index.html.twig', "<section>Status board</section>\n");

        $zipPath = tempnam(sys_get_temp_dir(), 'driftpunkt-addon-upload-');
        if (false === $zipPath) {
            throw new \RuntimeException('Kunde inte skapa temporär addon-zip för testet.');
        }
        @unlink($zipPath);
        $zipPath .= '.zip';

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if (true !== $result) {
            throw new \RuntimeException('Kunde inte öppna addon-zip för testet.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root.'/package', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relativePath = substr($file->getPathname(), \strlen($root.'/package/'));
            $zip->addFile($file->getPathname(), 'package/'.$relativePath);
        }

        $zip->close();
        $this->removeDirectory($root);

        return $zipPath;
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
