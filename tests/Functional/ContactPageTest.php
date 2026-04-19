<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\System\Service\SystemSettings;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ContactPageTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        $this->client = static::createClient();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
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

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }

    public function testContactPageRendersProfessionalSupportSections(): void
    {
        $crawler = $this->client->request('GET', '/kontakta-oss');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Vi hjälper dig vidare', $crawler->html());
        self::assertStringContainsString('support@driftpunkt.local', $crawler->html());
        self::assertStringContainsString('Skapa supportärende', $crawler->html());
    }

    public function testAdminCanUpdateContactPageContent(): void
    {
        $admin = new User('contact-admin@example.test', 'Ari', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Supersakert123'));
        $admin->enableMfa();
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/settings-content');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Spara kontaktsida')->form([
            'contact_page_hero_pill' => 'Kontaktcenter',
            'contact_page_title' => 'Prata med Driftpunkt',
            'contact_page_subtitle' => 'Nya öppettider och nya kontaktvägar.',
            'contact_page_email' => 'hello@example.test',
            'contact_page_phone' => '+46 8 555 55 55',
            'contact_page_hours' => 'Vardagar 09:00-16:00',
            'contact_page_primary_cta_label' => 'Skicka förfrågan',
            'contact_page_primary_cta_url' => '/login?role=customer',
            'contact_page_secondary_cta_label' => 'Läs guider',
            'contact_page_secondary_cta_url' => '/kunskapsbas',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/settings-content');
        $this->client->followRedirect();

        $settings = static::getContainer()->get(SystemSettings::class)->getContactPageSettings();
        self::assertSame('Kontaktcenter', $settings['heroPill']);
        self::assertSame('Prata med Driftpunkt', $settings['title']);
        self::assertSame('hello@example.test', $settings['email']);

        $contactCrawler = $this->client->request('GET', '/kontakta-oss');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Prata med Driftpunkt', $contactCrawler->html());
        self::assertStringContainsString('hello@example.test', $contactCrawler->html());
        self::assertStringContainsString('Vardagar 09:00-16:00', $contactCrawler->html());
        self::assertStringContainsString('Skicka förfrågan', $contactCrawler->html());
    }
}
