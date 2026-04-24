<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketComment;
use App\Module\Ticket\Entity\TicketCategory;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CustomerTicketCreateTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private SystemSettings $systemSettings;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
        $this->client = static::createClient();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->systemSettings = static::getContainer()->get(SystemSettings::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            @unlink($tempFile);
        }

        $this->removeDirectory(dirname(__DIR__, 2).'/var/test_customer_ticket_uploads');
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

    /**
     * @return array{0: Company, 1: User, 2: TicketCategory}
     */
    private function seedCustomerPortalFixture(string $email = 'portal-customer@example.test'): array
    {
        $company = new Company('Kundbolaget AB');
        $customer = new User($email, 'Klara', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'CustomerPassword123'));
        $customer->setCompany($company);
        $category = new TicketCategory('Nätverk');

        $this->entityManager->persist($company);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return [$company, $customer, $category];
    }

    public function testCustomerCanCreateTicketFromCustomerPortal(): void
    {
        [$company, $customer, $category] = $this->seedCustomerPortalFixture();

        $this->client->loginUser($customer);
        $crawler = $this->client->request('GET', '/portal/customer/tickets/ny');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Skicka in ett nytt ärende', (string) $crawler->html());

        $form = $crawler->filter('form[action="/portal/customer/tickets"]')->form([
            'subject' => 'VPN fungerar inte hemifrån',
            'summary' => 'VPN-anslutningen bryts efter inloggning för flera personer i teamet.',
            'category_id' => (string) $category->getId(),
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::TEAM->value,
            'visibility' => TicketVisibility::COMPANY_SHARED->value,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertStringContainsString('skickades in', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('VPN fungerar inte hemifrån', (string) $this->client->getResponse()->getContent());

        /** @var Ticket|null $ticket */
        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'VPN fungerar inte hemifrån']);
        self::assertNotNull($ticket);
        self::assertSame(TicketStatus::NEW, $ticket->getStatus());
        self::assertSame(TicketVisibility::COMPANY_SHARED, $ticket->getVisibility());
        self::assertSame($customer->getId(), $ticket->getRequester()?->getId());
        self::assertSame($company->getId(), $ticket->getCompany()?->getId());
        self::assertSame(TicketRequestType::INCIDENT, $ticket->getRequestType());
        self::assertSame(TicketImpactLevel::TEAM, $ticket->getImpactLevel());
    }

    public function testCustomerOverviewAndTicketListAreSeparatedForCustomerPortal(): void
    {
        [$company, $customer] = $this->seedCustomerPortalFixture('list-customer@example.test');

        $privateTicket = new Ticket(
            'DK-1001',
            'Privat ärende',
            'Det här ska bara kunden själv se i listan.',
            TicketStatus::PENDING_CUSTOMER,
            TicketVisibility::PRIVATE,
        );
        $privateTicket->setRequester($customer);
        $privateTicket->setCompany($company);

        $companySharedTicket = new Ticket(
            'DK-1002',
            'Delat företagsärende',
            'Det här ärendet ska synas i företagets gemensamma lista.',
            TicketStatus::OPEN,
            TicketVisibility::COMPANY_SHARED,
        );
        $companySharedTicket->setRequester($customer);
        $companySharedTicket->setCompany($company);

        $comment = new TicketComment($companySharedTicket, $customer, 'Vi behöver skicka in en ny loggfil.', false);
        $companySharedTicket->addComment($comment);

        $this->entityManager->persist($privateTicket);
        $this->entityManager->persist($companySharedTicket);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->client->loginUser($customer);
        $crawler = $this->client->request('GET', '/portal/customer');
        self::assertResponseIsSuccessful();

        $html = (string) $crawler->html();
        self::assertStringContainsString('Det här är din översikt', $html);
        self::assertStringContainsString('Senaste ärenden i korthet', $html);
        self::assertStringContainsString('Privat ärende', $html);
        self::assertStringContainsString('Delat företagsärende', $html);
        self::assertCount(0, $crawler->filter('a.customer-ticket-list-link'));
        self::assertStringNotContainsString('Här kan kunden läsa hela historiken', $html);
        self::assertStringNotContainsString('Alla ärenden visas som en tydlig lista.', $html);

        $crawler = $this->client->request('GET', '/portal/customer/tickets');
        self::assertResponseIsSuccessful();

        $listHtml = (string) $crawler->html();
        self::assertStringContainsString('Alla ärenden visas som en tydlig lista.', $listHtml);
        self::assertStringContainsString('Privat ärende', $listHtml);
        self::assertStringContainsString('Delat företagsärende', $listHtml);
        self::assertCount(2, $crawler->filter('a.customer-ticket-list-link'));

        $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $companySharedTicket->getId()));
        self::assertResponseIsSuccessful();

        $detailHtml = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Här kan kunden läsa hela historiken, ladda ner bilagor och skicka nya uppdateringar utan att listan blir rörig.', $detailHtml);
        self::assertStringContainsString('Vi behöver skicka in en ny loggfil.', $detailHtml);
        self::assertStringContainsString('Till ärendelistan', $detailHtml);
    }

    public function testCustomerTicketListSupportsSearchAndCompletedPriorityToggle(): void
    {
        [$company, $customer] = $this->seedCustomerPortalFixture('search-customer@example.test');

        $activeTicket = new Ticket(
            'DK-2001',
            'VPN nere för ekonomi',
            'Aktivt ärende som fortfarande felsöks.',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
        );
        $activeTicket->setRequester($customer);
        $activeTicket->setCompany($company);

        $resolvedTicket = new Ticket(
            'DK-2002',
            'Skrivare återställd',
            'Avslutat ärende som ska kunna lyftas fram.',
            TicketStatus::RESOLVED,
            TicketVisibility::PRIVATE,
        );
        $resolvedTicket->setRequester($customer);
        $resolvedTicket->setCompany($company);

        $this->entityManager->persist($activeTicket);
        $this->entityManager->persist($resolvedTicket);
        $this->entityManager->flush();

        $this->client->loginUser($customer);

        $crawler = $this->client->request('GET', '/portal/customer/tickets?q=skrivare');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();
        self::assertStringContainsString('Skrivare återställd', $html);
        self::assertStringNotContainsString('VPN nere för ekonomi', $html);

        $crawler = $this->client->request('GET', '/portal/customer/tickets?show_completed=1');
        self::assertResponseIsSuccessful();

        $listTexts = $crawler->filter('.customer-ticket-list-item .customer-ticket-list-subject')->each(
            static fn ($node): string => trim($node->text()),
        );
        self::assertSame(['Skrivare återställd', 'VPN nere för ekonomi'], $listTexts);
    }

    public function testParentCompanyCustomerCanSeeSharedTicketsFromSubsidiary(): void
    {
        $parentCompany = new Company('HV Holding AB');
        $childCompany = new Company('Fika Drift AB');
        $childCompany->setParentCompany($parentCompany);
        $childCompany->setAllowParentCompanyAccessToSharedTickets(true);

        $parentCustomer = new User('parent-customer@example.test', 'Helena', 'Holding', UserType::CUSTOMER);
        $parentCustomer->setPassword($this->passwordHasher->hashPassword($parentCustomer, 'CustomerPassword123'));
        $parentCustomer->setCompany($parentCompany);

        $childRequester = new User('child-requester@example.test', 'Filip', 'Fika', UserType::CUSTOMER);
        $childRequester->setPassword($this->passwordHasher->hashPassword($childRequester, 'CustomerPassword123'));
        $childRequester->setCompany($childCompany);

        $sharedChildTicket = new Ticket(
            'HV-3001',
            'Kassasystemet ligger nere',
            'Dotterbolaget behöver hjälp med sitt kassasystem.',
            TicketStatus::OPEN,
            TicketVisibility::COMPANY_SHARED,
        );
        $sharedChildTicket->setRequester($childRequester);
        $sharedChildTicket->setCompany($childCompany);

        $this->entityManager->persist($parentCompany);
        $this->entityManager->persist($childCompany);
        $this->entityManager->persist($parentCustomer);
        $this->entityManager->persist($childRequester);
        $this->entityManager->persist($sharedChildTicket);
        $this->entityManager->flush();

        $this->client->loginUser($parentCustomer);

        $crawler = $this->client->request('GET', '/portal/customer/tickets');
        self::assertResponseIsSuccessful();
        $html = (string) $crawler->html();
        self::assertStringContainsString('Kassasystemet ligger nere', $html);
        self::assertStringContainsString('Delat inom ert företag', $html);

        $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $sharedChildTicket->getId()));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Kassasystemet ligger nere', (string) $this->client->getResponse()->getContent());
    }

    public function testParentCompanyCustomerCannotSeeSubsidiarySharedTicketWhenParentAccessIsDisabled(): void
    {
        $parentCompany = new Company('HV Blocked AB');
        $childCompany = new Company('Zebra Drift AB');
        $childCompany->setParentCompany($parentCompany);

        $parentCustomer = new User('blocked-parent@example.test', 'Bodil', 'Blocked', UserType::CUSTOMER);
        $parentCustomer->setPassword($this->passwordHasher->hashPassword($parentCustomer, 'CustomerPassword123'));
        $parentCustomer->setCompany($parentCompany);

        $childRequester = new User('blocked-child@example.test', 'Zelda', 'Child', UserType::CUSTOMER);
        $childRequester->setPassword($this->passwordHasher->hashPassword($childRequester, 'CustomerPassword123'));
        $childRequester->setCompany($childCompany);

        $sharedChildTicket = new Ticket(
            'HV-3003',
            'Det här ska inte synas uppåt',
            'Underbolaget har inte delat sina företagsärenden med moderbolaget.',
            TicketStatus::OPEN,
            TicketVisibility::COMPANY_SHARED,
        );
        $sharedChildTicket->setRequester($childRequester);
        $sharedChildTicket->setCompany($childCompany);

        $this->entityManager->persist($parentCompany);
        $this->entityManager->persist($childCompany);
        $this->entityManager->persist($parentCustomer);
        $this->entityManager->persist($childRequester);
        $this->entityManager->persist($sharedChildTicket);
        $this->entityManager->flush();

        $this->client->loginUser($parentCustomer);

        $crawler = $this->client->request('GET', '/portal/customer/tickets');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Det här ska inte synas uppåt', (string) $crawler->html());

        $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $sharedChildTicket->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testParentCompanyCustomerCannotSeeSubsidiarySharedTicketWhenGlobalParentVisibilityIsDisabled(): void
    {
        $this->systemSettings->setBool(SystemSettings::FEATURE_COMPANY_HIERARCHY_PARENT_CAN_SEE_CHILD_SHARED_TICKETS, false);

        $parentCompany = new Company('HV Global Off AB');
        $childCompany = new Company('Matte Drift AB');
        $childCompany->setParentCompany($parentCompany);
        $childCompany->setAllowParentCompanyAccessToSharedTickets(true);

        $parentCustomer = new User('global-off-parent@example.test', 'Greta', 'Globaloff', UserType::CUSTOMER);
        $parentCustomer->setPassword($this->passwordHasher->hashPassword($parentCustomer, 'CustomerPassword123'));
        $parentCustomer->setCompany($parentCompany);

        $childRequester = new User('global-off-child@example.test', 'Mats', 'Drift', UserType::CUSTOMER);
        $childRequester->setPassword($this->passwordHasher->hashPassword($childRequester, 'CustomerPassword123'));
        $childRequester->setCompany($childCompany);

        $sharedChildTicket = new Ticket(
            'HV-3004',
            'Global policy blockerar uppåt',
            'Det här ska blockeras av admininställningen.',
            TicketStatus::OPEN,
            TicketVisibility::COMPANY_SHARED,
        );
        $sharedChildTicket->setRequester($childRequester);
        $sharedChildTicket->setCompany($childCompany);

        $this->entityManager->persist($parentCompany);
        $this->entityManager->persist($childCompany);
        $this->entityManager->persist($parentCustomer);
        $this->entityManager->persist($childRequester);
        $this->entityManager->persist($sharedChildTicket);
        $this->entityManager->flush();

        $this->client->loginUser($parentCustomer);
        $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $sharedChildTicket->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testSubsidiaryCustomerCanSeeParentSharedTicketWhenAdminAllowsIt(): void
    {
        $this->systemSettings->setBool(SystemSettings::FEATURE_COMPANY_HIERARCHY_CHILD_CAN_SEE_PARENT_SHARED_TICKETS, true);

        $parentCompany = new Company('HV Parent Visible AB');
        $childCompany = new Company('Sirap Visible AB');
        $childCompany->setParentCompany($parentCompany);

        $parentRequester = new User('parent-visible-requester@example.test', 'Pelle', 'Parent', UserType::CUSTOMER);
        $parentRequester->setPassword($this->passwordHasher->hashPassword($parentRequester, 'CustomerPassword123'));
        $parentRequester->setCompany($parentCompany);

        $childCustomer = new User('child-visible-customer@example.test', 'Siv', 'Child', UserType::CUSTOMER);
        $childCustomer->setPassword($this->passwordHasher->hashPassword($childCustomer, 'CustomerPassword123'));
        $childCustomer->setCompany($childCompany);

        $sharedParentTicket = new Ticket(
            'HV-3005',
            'Moderbolagets gemensamma driftinfo',
            'Det här ska synas nedåt när admin har tillåtit det.',
            TicketStatus::OPEN,
            TicketVisibility::COMPANY_SHARED,
        );
        $sharedParentTicket->setRequester($parentRequester);
        $sharedParentTicket->setCompany($parentCompany);

        $this->entityManager->persist($parentCompany);
        $this->entityManager->persist($childCompany);
        $this->entityManager->persist($parentRequester);
        $this->entityManager->persist($childCustomer);
        $this->entityManager->persist($sharedParentTicket);
        $this->entityManager->flush();

        $this->client->loginUser($childCustomer);

        $crawler = $this->client->request('GET', '/portal/customer/tickets');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Moderbolagets gemensamma driftinfo', (string) $crawler->html());

        $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $sharedParentTicket->getId()));
        self::assertResponseIsSuccessful();
    }

    public function testSubsidiaryCustomerCannotSeeParentCompanySharedTicket(): void
    {
        $parentCompany = new Company('HV Parent Services AB');
        $childCompany = new Company('Sirap Drift AB');
        $childCompany->setParentCompany($parentCompany);

        $parentRequester = new User('parent-requester@example.test', 'Petra', 'Parent', UserType::CUSTOMER);
        $parentRequester->setPassword($this->passwordHasher->hashPassword($parentRequester, 'CustomerPassword123'));
        $parentRequester->setCompany($parentCompany);

        $childCustomer = new User('child-customer@example.test', 'Sara', 'Subsidiary', UserType::CUSTOMER);
        $childCustomer->setPassword($this->passwordHasher->hashPassword($childCustomer, 'CustomerPassword123'));
        $childCustomer->setCompany($childCompany);

        $sharedParentTicket = new Ticket(
            'HV-3002',
            'Moderbolagets avtal ska uppdateras',
            'Det här delade ärendet ska inte automatiskt synas nedåt i koncernen.',
            TicketStatus::OPEN,
            TicketVisibility::COMPANY_SHARED,
        );
        $sharedParentTicket->setRequester($parentRequester);
        $sharedParentTicket->setCompany($parentCompany);

        $this->entityManager->persist($parentCompany);
        $this->entityManager->persist($childCompany);
        $this->entityManager->persist($parentRequester);
        $this->entityManager->persist($childCustomer);
        $this->entityManager->persist($sharedParentTicket);
        $this->entityManager->flush();

        $this->client->loginUser($childCustomer);

        $crawler = $this->client->request('GET', '/portal/customer/tickets');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Moderbolagets avtal ska uppdateras', (string) $crawler->html());

        $this->client->request('GET', sprintf('/portal/customer/tickets/%d', $sharedParentTicket->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testCustomerCanCreateTicketWithAttachmentWhenFeatureIsEnabled(): void
    {
        [, $customer, $category] = $this->seedCustomerPortalFixture('attachment-customer@example.test');

        $this->systemSettings->setBool(SystemSettings::FEATURE_TICKET_ATTACHMENTS_ENABLED, true);
        $this->systemSettings->setInt(SystemSettings::TICKET_ATTACHMENTS_MAX_UPLOAD_MB, 5);
        $this->systemSettings->setString(SystemSettings::TICKET_ATTACHMENTS_STORAGE_PATH, 'var/test_customer_ticket_uploads');
        $this->systemSettings->setString(SystemSettings::TICKET_ATTACHMENTS_ALLOWED_EXTENSIONS, 'png,txt,log');

        $uploadPath = dirname(__DIR__, 2).'/var/kundunderlag.txt';
        file_put_contents($uploadPath, 'Skärmdump och felsökningsanteckningar från kunden.');
        $this->tempFiles[] = $uploadPath;

        $this->client->loginUser($customer);
        $crawler = $this->client->request('GET', '/portal/customer/tickets/ny');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Bilagor', (string) $crawler->html());

        $form = $crawler->filter('form[action="/portal/customer/tickets"]')->form([
            'subject' => 'Bifogad felsökning',
            'summary' => 'Jag skickar med underlaget direkt i ärendet.',
            'category_id' => (string) $category->getId(),
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            'visibility' => TicketVisibility::PRIVATE->value,
        ]);
        $form['attachment']->upload($uploadPath);

        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Bifogad felsökning', $html);
        self::assertStringContainsString('Bilagor bifogades när ärendet skapades.', $html);
        self::assertStringContainsString('kundunderlag.txt', $html);

        /** @var Ticket|null $ticket */
        $ticket = $this->entityManager->getRepository(Ticket::class)->findOneBy(['subject' => 'Bifogad felsökning']);
        self::assertNotNull($ticket);
        self::assertCount(1, $ticket->getComments());

        /** @var TicketComment $attachmentComment */
        $attachmentComment = $ticket->getComments()->first();
        self::assertSame('Bilagor bifogades när ärendet skapades.', $attachmentComment->getBody());
        self::assertCount(1, $attachmentComment->getAttachments());
        self::assertSame('kundunderlag.txt', $attachmentComment->getAttachments()->first()->getDisplayName());
    }
}
