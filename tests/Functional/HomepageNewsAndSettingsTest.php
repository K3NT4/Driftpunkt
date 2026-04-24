<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\News\Entity\NewsArticle;
use App\Module\News\Enum\NewsCategory;
use App\Module\System\Entity\AddonModule;
use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class HomepageNewsAndSettingsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->cleanupSqliteSidecars();
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

    private function cleanupSqliteSidecars(): void
    {
        $dbPath = dirname(__DIR__, 2).'/var/driftpunkt_test.db';
        @unlink($dbPath.'-wal');
        @unlink($dbPath.'-shm');
    }

    public function testHomepageRendersPublishedNewsAndConfigurableSupportWidget(): void
    {
        $settings = static::getContainer()->get(SystemSettings::class);
        $settings->setString(SystemSettings::HOME_SUPPORT_WIDGET_TITLE, 'Snabb hjälp');
        $settings->setString(SystemSettings::HOME_SUPPORT_WIDGET_INTRO, 'Välj snabbaste vägen till svar.');
        $settings->setString(SystemSettings::HOME_SUPPORT_WIDGET_LINKS, "book | Guider | /kunskapsbas\ncheck | Kontakta teamet | /portal");
        $settings->setString(SystemSettings::HOME_STATUS_SECTION_TITLE, 'Viktiga tjänster');
        $settings->setString(SystemSettings::HOME_STATUS_SECTION_INTRO, 'Bara utvalda tjänster ska synas här.');
        $settings->setInt(SystemSettings::HOME_STATUS_SECTION_MAX_ITEMS, 2);
        $settings->setString(SystemSettings::STATUS_MONITORS, "manual | Intern tjänst | https://status.example.org | Manuell kontroll | Läs mer | https://status.example.org | check | 0\nmanual | Kundportal | https://portal.example.org | Viktig tjänst på startsidan | Öppna portal | https://portal.example.org | display | 1");

        $author = new User('news-author@example.test', 'Nina', 'Nyhet', UserType::ADMIN);
        $author->setPassword($this->passwordHasher->hashPassword($author, 'Supersakert123'));

        $article = new NewsArticle('Ny release ute', 'Kort sammanfattning av releasen.', "Första raden.\nAndra raden.");
        $article->setAuthor($author);
        $article->publish();
        $article->pin();

        $this->entityManager->persist($author);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Driftpunkt', $crawler->html());
        self::assertStringContainsString('Ny release ute', $crawler->html());
        self::assertStringContainsString('Snabb hjälp', $crawler->html());
        self::assertStringContainsString('Kontakta teamet', $crawler->html());
        self::assertStringContainsString('Viktiga tjänster', $crawler->html());
        self::assertStringContainsString('Bara utvalda tjänster ska synas här.', $crawler->html());
        self::assertStringContainsString('Kundportal', $crawler->html());
        self::assertStringNotContainsString('Intern tjänst', $crawler->html());

        $newsCrawler = $this->client->request('GET', '/nyheter');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Ny release ute', $newsCrawler->html());

        $detailsCrawler = $this->client->request('GET', '/nyheter/'.$article->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Första raden.', $detailsCrawler->html());
    }

    public function testPublicNewsPagesFallbackWhenUserSchemaIsMissingMfaSecret(): void
    {
        $author = new User('news-fallback@example.test', 'Nina', 'Nyhet', UserType::ADMIN);
        $author->setPassword($this->passwordHasher->hashPassword($author, 'Supersakert123'));

        $article = new NewsArticle('Schemafallback', 'Visar nyhet utan forfattarrelation.', "Rad ett.\nRad tva.");
        $article->setAuthor($author);
        $article->publish();

        $this->entityManager->persist($author);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->entityManager->getConnection()->executeStatement('ALTER TABLE users DROP COLUMN mfa_secret');
        $this->entityManager->clear();

        $homeCrawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Schemafallback', $homeCrawler->html());
        self::assertStringContainsString('Driftpunkt', $homeCrawler->html());

        $newsCrawler = $this->client->request('GET', '/nyheter');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Schemafallback', $newsCrawler->html());

        $detailsCrawler = $this->client->request('GET', '/nyheter/'.$article->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Rad ett.', $detailsCrawler->html());

        $statusCrawler = $this->client->request('GET', '/driftstatus');
        self::assertResponseIsSuccessful();
    }

    public function testLanguageSwitcherPersistsEnglishLocaleAcrossRedirect(): void
    {
        $crawler = $this->client->request('GET', '/sprak/en?returnTo=%2F');

        self::assertResponseRedirects('/');

        $crawler = $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Language', $crawler->html());
        self::assertStringContainsString('Sign in', $crawler->html());
        self::assertStringContainsString('Contact us', $crawler->html());
        self::assertSame('en', $this->client->getRequest()->getLocale());
    }

    public function testNewsArticleBodySupportsRichArticleFormatting(): void
    {
        $author = new User('news-format@example.test', 'Nina', 'Nyhet', UserType::ADMIN);
        $author->setPassword($this->passwordHasher->hashPassword($author, 'Supersakert123'));

        $article = new NewsArticle(
            'Formatterad artikel',
            'Kort intro.',
            "## Delrubrik\nLite **viktig** text med [länk](https://example.test)\n\n- Punkt ett\n- Punkt två",
        );
        $article->setAuthor($author);
        $article->publish();

        $this->entityManager->persist($author);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/nyheter/'.$article->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<h2>Delrubrik</h2>', $crawler->html());
        self::assertStringContainsString('<strong>viktig</strong>', $crawler->html());
        self::assertStringContainsString('<ul><li>Punkt ett</li><li>Punkt två</li></ul>', $crawler->html());
    }

    public function testNewsArticleBodySupportsNewsEditorPlusBlocks(): void
    {
        $author = new User('news-addon@example.test', 'Nina', 'Nyhet', UserType::ADMIN);
        $author->setPassword($this->passwordHasher->hashPassword($author, 'Supersakert123'));

        $article = new NewsArticle(
            'News Editor Plus',
            'Visar de nya blocken.',
            ":::info Viktigt\nDetta ar **viktig** information.\n:::\n\n- [x] Editor aktiv\n- [ ] Publicera brett\n\n=> Las mer | https://example.test/news-editor-plus\n\n```\nphp bin/phpunit\n```",
        );
        $article->setAuthor($author);
        $article->publish();

        $this->entityManager->persist($author);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/nyheter/'.$article->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('article-callout info', $crawler->html());
        self::assertStringContainsString('<strong>Viktigt</strong>', $crawler->html());
        self::assertStringContainsString('article-checklist', $crawler->html());
        self::assertStringContainsString('article-checklist-box', $crawler->html());
        self::assertStringContainsString('article-cta', $crawler->html());
        self::assertStringContainsString('https://example.test/news-editor-plus', $crawler->html());
        self::assertStringContainsString('article-code', $crawler->html());
        self::assertStringContainsString('php bin/phpunit', $crawler->html());
    }

    public function testNewsArticleBodySupportsSuccessCallouts(): void
    {
        $author = new User('news-success@example.test', 'Nina', 'Nyhet', UserType::ADMIN);
        $author->setPassword($this->passwordHasher->hashPassword($author, 'Supersakert123'));

        $article = new NewsArticle(
            'Incident lost',
            'Visar success-callout i artikeln.',
            ":::success Lage aterstallt\nTjansten fungerar igen.\n:::",
        );
        $article->setAuthor($author);
        $article->publish();

        $this->entityManager->persist($author);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/nyheter/'.$article->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('article-callout success', $crawler->html());
        self::assertStringContainsString('<strong>Lage aterstallt</strong>', $crawler->html());
        self::assertStringContainsString('Tjansten fungerar igen.', $crawler->html());
    }

    public function testNewsArticleBodySupportsFaqAndVersionBlocks(): void
    {
        $author = new User('news-faq@example.test', 'Nina', 'Nyhet', UserType::ADMIN);
        $author->setPassword($this->passwordHasher->hashPassword($author, 'Supersakert123'));

        $article = new NewsArticle(
            'Release med FAQ',
            'Visar FAQ-block och versionsrad.',
            "+++ 2.4.0 | Produktionssatt\n\n??? Hur paverkas jag?\nIngen manuell insats kravs efter releasen.\n???",
        );
        $article->setAuthor($author);
        $article->publish();

        $this->entityManager->persist($author);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/nyheter/'.$article->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('article-version', $crawler->html());
        self::assertStringContainsString('2.4.0', $crawler->html());
        self::assertStringContainsString('Produktionssatt', $crawler->html());
        self::assertStringContainsString('article-faq', $crawler->html());
        self::assertStringContainsString('Hur paverkas jag?', $crawler->html());
        self::assertStringContainsString('Ingen manuell insats kravs efter releasen.', $crawler->html());
    }

    public function testNewsArticleBodySupportsTables(): void
    {
        $author = new User('news-table@example.test', 'Nina', 'Nyhet', UserType::ADMIN);
        $author->setPassword($this->passwordHasher->hashPassword($author, 'Supersakert123'));

        $article = new NewsArticle(
            'Release med tabell',
            'Visar tabellblock i artikeln.',
            "| Omrade | Status | Kommentar |\n| --- | --- | --- |\n| Portal | Klar | Ingen manuell atgard kravs |\n| API | Pa gar | Uppfoljning efter deploy |",
        );
        $article->setAuthor($author);
        $article->publish();

        $this->entityManager->persist($author);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/nyheter/'.$article->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('article-table-wrap', $crawler->html());
        self::assertStringContainsString('<th>Omrade</th>', $crawler->html());
        self::assertStringContainsString('<td>Portal</td>', $crawler->html());
        self::assertStringContainsString('Ingen manuell atgard kravs', $crawler->html());
    }

    public function testNewsArticleBodySupportsInlineImageBlocks(): void
    {
        $author = new User('news-image@example.test', 'Nina', 'Nyhet', UserType::ADMIN);
        $author->setPassword($this->passwordHasher->hashPassword($author, 'Supersakert123'));

        $article = new NewsArticle(
            'Bildblock i artikel',
            'Visar infogad bild i brödtexten.',
            "## Skarmbild\n![Redaktorsvy](https://example.test/editor-preview.png)\n\nKort kommentar efter bilden.",
        );
        $article->setAuthor($author);
        $article->publish();

        $this->entityManager->persist($author);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/nyheter/'.$article->getId());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('article-media', $crawler->html());
        self::assertStringContainsString('editor-preview.png', $crawler->html());
        self::assertStringContainsString('Redaktorsvy', $crawler->html());
    }

    public function testAdminCanUpdateHomepageSettingsAndTechnicianNewsPermission(): void
    {
        $admin = new User('admin-home@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Supersakert123'));
        $admin->enableMfa();
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/settings-content');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Spara startsida')->form([
            'news_technician_contributions_enabled' => '1',
            'home_support_widget_title' => 'Behöver du snabb hjälp?',
            'home_support_widget_intro' => 'Admin styr nu innehållet här.',
            'home_support_widget_links' => "spark | FAQ | /kunskapsbas\ncheck | Support | /portal",
            'home_status_section_title' => 'Viktiga driftstjänster',
            'home_status_section_intro' => 'Visa bara de viktigaste korten på startsidan.',
            'home_status_section_max_items' => '3',
            'maintenance_notice_lookahead_days' => '5',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/settings-content');
        $this->client->followRedirect();

        $settings = static::getContainer()->get(SystemSettings::class);
        self::assertTrue($settings->getNewsSettings()['technicianContributionsEnabled']);
        self::assertSame('Behöver du snabb hjälp?', $settings->getHomeSupportWidgetSettings()['title']);
        self::assertSame('Viktiga driftstjänster', $settings->getHomepageStatusSectionSettings()['title']);
        self::assertSame('Visa bara de viktigaste korten på startsidan.', $settings->getHomepageStatusSectionSettings()['intro']);
        self::assertSame(3, $settings->getHomepageStatusSectionSettings()['maxItems']);
        self::assertSame(5, $settings->getMaintenanceNoticeSettings()['lookaheadDays']);
    }

    public function testAdminCanUpdateMfaPolicySettings(): void
    {
        $admin = new User('admin-mfa-policy@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Supersakert123'));
        $admin->enableMfa();
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/settings-content');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Spara MFA-inställningar')->form([
            'mfa_customer_enabled' => '1',
            'mfa_technician_enabled' => '1',
        ]);
        unset($form['mfa_admin_enabled']);
        $this->client->submit($form);

        self::assertResponseRedirects('/portal/admin/settings-content');
        $this->client->followRedirect();

        $settings = static::getContainer()->get(SystemSettings::class);
        self::assertTrue($settings->getMfaSettings()['customerEnabled']);
        self::assertTrue($settings->getMfaSettings()['technicianEnabled']);
        self::assertFalse($settings->getMfaSettings()['adminEnabled']);
    }

    public function testAdminNewsPageShowsTechnicianNewsPermissionToggle(): void
    {
        $admin = new User('admin-news-toggle@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Supersakert123'));
        $admin->enableMfa();
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/nyheter');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Låt tekniker skapa nyheter', $crawler->html());
        self::assertStringContainsString('Spara behörighet', $crawler->html());
        self::assertStringContainsString('Förhandsvisning', $crawler->html());
        self::assertStringContainsString('News Editor Plus', $crawler->html());
        self::assertStringContainsString('Checklista', $crawler->html());
        self::assertStringContainsString('Bildblock', $crawler->html());
        self::assertStringContainsString('CTA-knapp', $crawler->html());
        self::assertStringContainsString('Kodblock', $crawler->html());
        self::assertStringContainsString('Klart-ruta', $crawler->html());
        self::assertStringContainsString('FAQ-block', $crawler->html());
        self::assertStringContainsString('Versionsrad', $crawler->html());
        self::assertStringContainsString('Tabell', $crawler->html());
        self::assertStringContainsString('Kort kod', $crawler->html());
        self::assertStringContainsString('Snabbmallar', $crawler->html());
        self::assertStringContainsString('Kopiera sektion', $crawler->html());
        self::assertStringContainsString('Påverkan', $crawler->html());
        self::assertStringContainsString('Åtgärder', $crawler->html());
        self::assertStringContainsString('Nästa uppdatering', $crawler->html());
        self::assertStringContainsString('Kända fel', $crawler->html());
        self::assertStringContainsString('Release notes', $crawler->html());
        self::assertStringContainsString('Driftstörning', $crawler->html());
        self::assertStringContainsString('Löst incident', $crawler->html());
        self::assertStringContainsString('Planerat underhåll', $crawler->html());
        self::assertStringContainsString('Redaktörsstöd', $crawler->html());
        self::assertStringContainsString('Sammanfattning', $crawler->html());
        self::assertStringContainsString('Lästid', $crawler->html());
        self::assertStringContainsString('Autosparning redo', $crawler->html());
        self::assertStringContainsString('data-autosave-status', $crawler->html());
        self::assertStringContainsString('Återställ utkast', $crawler->html());
        self::assertStringContainsString('Rensa lokalt utkast', $crawler->html());
        self::assertStringContainsString('smarta standardvärden för publicering, prioritet och underhållsfönster', $crawler->html());
    }

    public function testAdminNewsPageFallsBackToBaseEditorWhenNewsEditorPlusAddonIsDisabled(): void
    {
        $admin = new User('admin-news-addon-off@example.test', 'Ada', 'Admin', UserType::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Supersakert123'));
        $admin->enableMfa();
        $addon = (new AddonModule('news-editor-plus', 'News Editor Plus', 'Styr utökad editor för nyheter.'))
            ->setEnabled(false);

        $this->entityManager->persist($admin);
        $this->entityManager->persist($addon);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/portal/admin/nyheter');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Addon avstängt: <strong>News Editor Plus</strong>', $crawler->html());
        self::assertStringContainsString('Basredigering:', $crawler->html());
        self::assertStringNotContainsString('Snabbmallar', $crawler->html());
        self::assertStringNotContainsString('Kopiera sektion', $crawler->html());
        self::assertStringNotContainsString('Checklista', $crawler->html());
        self::assertStringNotContainsString('Bildblock', $crawler->html());
        self::assertStringNotContainsString('CTA-knapp', $crawler->html());
        self::assertStringNotContainsString('Kodblock', $crawler->html());
        self::assertStringNotContainsString('Klart-ruta', $crawler->html());
        self::assertStringNotContainsString('data-editor-action="faq"', $crawler->html());
        self::assertStringNotContainsString('data-editor-action="version"', $crawler->html());
        self::assertStringNotContainsString('data-editor-action="table"', $crawler->html());
        self::assertStringNotContainsString('data-news-section="impact"', $crawler->html());
        self::assertStringContainsString('Autosparning redo', $crawler->html());
        self::assertStringContainsString('Återställ utkast', $crawler->html());
        self::assertStringContainsString('Rensa lokalt utkast', $crawler->html());
        self::assertStringNotContainsString('Kort kod', $crawler->html());
    }

    public function testTechnicianNewsPageRequiresAdminPermissionFlag(): void
    {
        $technician = new User('tech-news@example.test', 'Ture', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'Supersakert123'));
        $technician->enableMfa();
        $this->entityManager->persist($technician);
        $this->entityManager->flush();

        $this->client->loginUser($technician);
        $this->client->request('GET', '/portal/technician/nyheter');
        self::assertResponseRedirects('/portal/technician');

        static::getContainer()->get(SystemSettings::class)->setBool(
            SystemSettings::FEATURE_NEWS_TECHNICIAN_CONTRIBUTIONS_ENABLED,
            true,
        );

        $crawler = $this->client->request('GET', '/portal/technician/nyheter');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Publicera nyheter och uppdateringar till startsidan.', $crawler->html());
        self::assertStringContainsString('>Nyheter</span>', $crawler->html());
        self::assertStringContainsString('Release notes', $crawler->html());
        self::assertStringContainsString('Driftstörning', $crawler->html());
        self::assertStringContainsString('färdig struktur för vanliga publiceringstyper', $crawler->html());
        self::assertStringNotContainsString('data-news-preset="maintenance"', $crawler->html());
        self::assertStringNotContainsString('Underhåll start', $crawler->html());
    }

    public function testTechnicianCanScheduleNewsWithoutMakingItPublicImmediately(): void
    {
        $technician = new User('tech-scheduled@example.test', 'Ture', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'Supersakert123'));
        $technician->enableMfa();
        $this->entityManager->persist($technician);
        $this->entityManager->flush();

        static::getContainer()->get(SystemSettings::class)->setBool(
            SystemSettings::FEATURE_NEWS_TECHNICIAN_CONTRIBUTIONS_ENABLED,
            true,
        );

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/nyheter');
        self::assertResponseIsSuccessful();

        $token = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');
        $publishAt = (new \DateTimeImmutable('+2 days'))->format('Y-m-d\TH:i');

        $this->client->request('POST', '/portal/technician/nyheter', [
            '_token' => $token,
            'title' => 'Schemalagd teknikernyhet',
            'summary' => 'Ska publiceras senare.',
            'body' => 'Denna nyhet ska inte synas publikt an.',
            'category' => 'general',
            'publish_at' => $publishAt,
            'is_published' => '1',
        ]);

        self::assertResponseRedirects('/portal/technician/nyheter');
        $this->client->followRedirect();
        self::assertStringContainsString('Schemalagd publicering', (string) $this->client->getResponse()->getContent());

        $publicCrawler = $this->client->request('GET', '/nyheter');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Schemalagd teknikernyhet', $publicCrawler->html());
    }

    public function testTechnicianCannotCreatePlannedMaintenanceNews(): void
    {
        $technician = new User('tech-maintenance@example.test', 'Ture', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'Supersakert123'));
        $technician->enableMfa();
        $this->entityManager->persist($technician);
        $this->entityManager->flush();

        static::getContainer()->get(SystemSettings::class)->setBool(
            SystemSettings::FEATURE_NEWS_TECHNICIAN_CONTRIBUTIONS_ENABLED,
            true,
        );

        $this->client->loginUser($technician);
        $crawler = $this->client->request('GET', '/portal/technician/nyheter');
        self::assertResponseIsSuccessful();

        $token = (string) $crawler->filter('input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/portal/technician/nyheter', [
            '_token' => $token,
            'title' => 'Teknikerforsok',
            'summary' => 'Ska stoppas.',
            'body' => 'Detta ar ett manipulerat formular.',
            'category' => 'planned_maintenance',
            'is_published' => '1',
        ]);

        self::assertResponseRedirects('/portal/technician/nyheter');
        $this->client->followRedirect();
        self::assertStringContainsString('Tekniker kan bara skapa vanliga sajt-nyheter.', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $this->entityManager->getRepository(NewsArticle::class)->count([
            'title' => 'Teknikerforsok',
        ]));
    }

    public function testTechnicianCannotUpdatePlannedMaintenanceNews(): void
    {
        $technician = new User('tech-maintenance-update@example.test', 'Ture', 'Tekniker', UserType::TECHNICIAN);
        $technician->setPassword($this->passwordHasher->hashPassword($technician, 'Supersakert123'));
        $technician->enableMfa();

        $article = new NewsArticle('Planerat arbete', 'Underhall', 'Bakgrundstext');
        $article->setAuthor($technician);
        $article->setCategory(NewsCategory::PLANNED_MAINTENANCE);
        $article->publish();

        $this->entityManager->persist($technician);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        static::getContainer()->get(SystemSettings::class)->setBool(
            SystemSettings::FEATURE_NEWS_TECHNICIAN_CONTRIBUTIONS_ENABLED,
            true,
        );

        $this->client->loginUser($technician);
        $this->client->request('POST', '/portal/technician/nyheter/'.$article->getId(), [
            '_token' => 'ignored',
            'title' => 'Andrad titel',
            'summary' => 'Ny sammanfattning',
            'body' => 'Ny text',
            'category' => 'general',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testLoggedInCustomerIsRedirectedAwayFromHomepage(): void
    {
        $customer = new User('customer-home@example.test', 'Klara', 'Kund', UserType::CUSTOMER);
        $customer->setPassword($this->passwordHasher->hashPassword($customer, 'Supersakert123'));
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $this->client->loginUser($customer);
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/portal/customer');
    }

    public function testHomepageShowsActiveTicketsInsteadOfIncidentCounter(): void
    {
        $incidentTicket = new Ticket(
            'DP-9001',
            'Internet nere',
            'En incident som fortfarande ar oppen.',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::HIGH,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::COMPANY,
        );
        $supportTicket = new Ticket(
            'DP-9002',
            'Behorighet saknas',
            'Ett vanligt supportarende som fortfarande ar aktivt.',
            TicketStatus::OPEN,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::ACCESS_REQUEST,
            TicketImpactLevel::SINGLE_USER,
        );
        $pendingTicket = new Ticket(
            'DP-9003',
            'Vantar pa kundsvar',
            'Arendet ar fortfarande aktivt.',
            TicketStatus::PENDING_CUSTOMER,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::SERVICE_REQUEST,
            TicketImpactLevel::SINGLE_USER,
        );
        $resolvedTicket = new Ticket(
            'DP-9004',
            'Lost incident',
            'Ska inte raknas som aktivt.',
            TicketStatus::RESOLVED,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
        );

        $this->entityManager->persist($incidentTicket);
        $this->entityManager->persist($supportTicket);
        $this->entityManager->persist($pendingTicket);
        $this->entityManager->persist($resolvedTicket);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aktiva ärenden: <strong>3</strong>', $crawler->html());
        self::assertStringNotContainsString('Pågående incidenter', $crawler->html());
    }
}
