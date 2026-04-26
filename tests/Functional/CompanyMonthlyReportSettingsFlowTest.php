<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\System\Entity\SystemSetting;
use App\Module\System\Service\SystemSettings;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CompanyMonthlyReportSettingsFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');

        $this->client = static::createClient();
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

    public function testCompanyCustomerCanUpdateMonthlyReportRecipientWhenReportsAreEnabled(): void
    {
        [$company, $customer] = $this->createCompanyCustomerFixture();
        $this->entityManager->persist(new SystemSetting(SystemSettings::FEATURE_CUSTOMER_REPORTS_ENABLED, '1'));
        $this->entityManager->flush();

        $this->client->loginUser($customer);
        $crawler = $this->client->request('GET', '/portal/customer/reports');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Månadsrapport', $crawler->html());

        $form = $crawler->filter('form[action="/portal/customer/reports/settings"]')->form([
            'monthly_report_enabled' => '1',
            'monthly_report_recipient_email' => ' Finance@Customer.Test ',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/customer/reports');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $company = $this->entityManager->getRepository(Company::class)->find($company->getId());
        self::assertNotNull($company);
        self::assertTrue($company->isMonthlyReportEnabled());
        self::assertSame('finance@customer.test', $company->getMonthlyReportRecipientEmail());
    }

    public function testCompanyCustomerCannotStoreTooLongMonthlyReportRecipient(): void
    {
        [$company, $customer] = $this->createCompanyCustomerFixture('length-customer-report@example.test');
        $tooLongEmail = str_repeat('a', 64).'@'.str_repeat('b', 60).'.'.str_repeat('c', 60).'.test';

        self::assertGreaterThan(180, mb_strlen($tooLongEmail));
        self::assertNotFalse(filter_var($tooLongEmail, FILTER_VALIDATE_EMAIL));

        $this->entityManager->persist(new SystemSetting(SystemSettings::FEATURE_CUSTOMER_REPORTS_ENABLED, '1'));
        $this->entityManager->flush();

        $this->client->loginUser($customer);
        $crawler = $this->client->request('GET', '/portal/customer/reports');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('form[action="/portal/customer/reports/settings"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/portal/customer/reports/settings', [
            '_token' => $token,
            'monthly_report_recipient_email' => $tooLongEmail,
        ]);

        self::assertResponseRedirects('/portal/customer/reports');
        $this->client->followRedirect();
        self::assertStringContainsString('Månadsrapportens mottagaradress får vara högst 180 tecken.', $this->client->getResponse()->getContent() ?? '');

        $this->entityManager->clear();
        $company = $this->entityManager->getRepository(Company::class)->find($company->getId());
        self::assertNotNull($company);
        self::assertFalse($company->isMonthlyReportEnabled());
        self::assertNull($company->getMonthlyReportRecipientEmail());
    }

    public function testPrivateCustomerCannotUpdateCompanyMonthlyReportSettings(): void
    {
        $customer = new User('private-report@example.test', 'Privat', 'Kund', UserType::PRIVATE_CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $this->entityManager->persist(new SystemSetting(SystemSettings::FEATURE_CUSTOMER_REPORTS_ENABLED, '1'));
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $this->client->loginUser($customer);
        $this->client->request('POST', '/portal/customer/reports/settings', [
            'monthly_report_enabled' => '1',
            'monthly_report_recipient_email' => 'private@example.test',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testTechnicianWithCompanyCannotUpdateCompanyMonthlyReportSettingsThroughCustomerRoute(): void
    {
        $company = new Company('Technician Report AB');
        $technician = new User('technician-report@example.test', 'Tina', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'TechnicianPassword123'));
        $technician->setCompany($company);

        $this->entityManager->persist(new SystemSetting(SystemSettings::FEATURE_CUSTOMER_REPORTS_ENABLED, '1'));
        $this->entityManager->persist($company);
        $this->entityManager->persist($technician);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $this->client->request('POST', '/portal/customer/reports/settings', [
            'monthly_report_enabled' => '1',
            'monthly_report_recipient_email' => 'technician@example.test',
        ]);

        self::assertResponseStatusCodeSame(403);

        $this->entityManager->clear();
        $company = $this->entityManager->getRepository(Company::class)->find($company->getId());
        self::assertNotNull($company);
        self::assertFalse($company->isMonthlyReportEnabled());
        self::assertNull($company->getMonthlyReportRecipientEmail());
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function createCompanyCustomerFixture(string $email = 'customer-report@example.test'): array
    {
        $company = new Company('Customer Report AB');
        $customer = new User($email, 'Cora', 'Customer', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);

        $this->entityManager->persist($company);
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return [$company, $customer];
    }
}
