<?php

declare(strict_types=1);

namespace App\Module\PublicPortal\Service;

use App\Module\KnowledgeBase\Entity\KnowledgeBaseEntry;
use App\Module\KnowledgeBase\Enum\KnowledgeBaseAudience;
use App\Module\News\Entity\NewsArticle;
use App\Module\News\Enum\NewsCategory;
use App\Module\News\Service\NewsArticleSchemaInspector;
use App\Module\System\Service\StatusMonitorService;
use App\Module\System\Service\SystemSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PublicSiteSearch
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SystemSettings $systemSettings,
        private readonly StatusMonitorService $statusMonitorService,
        private readonly NewsArticleSchemaInspector $newsArticleSchemaInspector,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return list<array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string
     * }>
     */
    public function getSystemStatuses(): array
    {
        return $this->statusMonitorService->getPublicStatuses();
    }

    /**
     * @return list<array{section: string, title: string, summary: string, href: ?string}>
     */
    public function search(string $searchQuery): array
    {
        $needle = mb_strtolower(trim($searchQuery));
        if ('' === $needle) {
            return [];
        }

        $terms = $this->expandTerms($this->tokenize($needle));

        $knowledgeBaseSettings = $this->systemSettings->getKnowledgeBaseSettings();
        $customerLoginSettings = $this->systemSettings->getCustomerLoginSettings();
        $contactPageSettings = $this->systemSettings->getContactPageSettings();
        $privacyPolicySettings = $this->systemSettings->getPrivacyPolicySettings();
        $termsPageSettings = $this->systemSettings->getTermsPageSettings();
        $cookiePolicySettings = $this->systemSettings->getCookiePolicySettings();
        $supportWidget = $this->filterHomeSupportWidget(
            $this->systemSettings->getHomeSupportWidgetSettings(),
            (bool) $knowledgeBaseSettings['publicEnabled'],
        );

        $results = [];

        $this->addResultIfMatches(
            $results,
            $needle,
            $terms,
            'page',
            $this->translator->trans('nav.home'),
            $this->translator->trans('search.index.home_summary'),
            $this->urlGenerator->generate('app_home'),
            $this->translator->trans('search.index.home_keywords'),
            48,
        );
        $this->addResultIfMatches(
            $results,
            $needle,
            $terms,
            'page',
            $this->translator->trans('nav.news'),
            $this->translator->trans('search.index.news_summary'),
            $this->urlGenerator->generate('app_news_index'),
            $this->translator->trans('search.index.news_keywords'),
            52,
        );
        $this->addResultIfMatches(
            $results,
            $needle,
            $terms,
            'page',
            $this->translator->trans('nav.contact'),
            sprintf('%s %s', $contactPageSettings['title'], $contactPageSettings['subtitle']),
            $this->urlGenerator->generate('app_contact'),
            sprintf(
                'kontakta oss support hjalp %s %s %s %s',
                $contactPageSettings['title'],
                $contactPageSettings['subtitle'],
                $contactPageSettings['email'],
                $contactPageSettings['phone']
            ),
            54,
        );
        $this->addResultIfMatches(
            $results,
            $needle,
            $terms,
            'page',
            $privacyPolicySettings['title'],
            $privacyPolicySettings['intro'],
            $this->urlGenerator->generate('app_privacy_policy'),
            sprintf(
                'integritetspolicy gdpr dataskydd personuppgifter imy cookies lagringstid rattigheter %s %s',
                $privacyPolicySettings['title'],
                $privacyPolicySettings['contactEmail'],
            ),
            56,
        );
        $this->addResultIfMatches(
            $results,
            $needle,
            $terms,
            'page',
            $termsPageSettings['title'],
            $termsPageSettings['intro'],
            $this->urlGenerator->generate('app_terms_page'),
            sprintf(
                'anvandarvillkor villkor avtal konto regler support %s %s',
                $termsPageSettings['title'],
                $termsPageSettings['contactEmail'],
            ),
            55,
        );
        $this->addResultIfMatches(
            $results,
            $needle,
            $terms,
            'page',
            $cookiePolicySettings['title'],
            $cookiePolicySettings['intro'],
            $this->urlGenerator->generate('app_cookie_policy'),
            sprintf(
                'cookiepolicy cookies sessionscookie samtycke spårning webbläsare %s %s',
                $cookiePolicySettings['title'],
                $cookiePolicySettings['contactEmail'],
            ),
            55,
        );

        if ($knowledgeBaseSettings['publicEnabled']) {
            $this->addResultIfMatches(
                $results,
                $needle,
                $terms,
                'page',
                $this->translator->trans('search.filter.knowledge_base'),
                $this->translator->trans('search.index.knowledge_base_summary'),
                $this->urlGenerator->generate('app_knowledge_base_public'),
                $this->translator->trans('search.index.knowledge_base_keywords'),
                58,
            );
        }

        $publicLinks = [
            [
                'section' => 'sign_in',
                'title' => $this->translator->trans('home.roles.technician.title'),
                'summary' => $this->translator->trans('home.roles.technician.copy'),
                'href' => $this->urlGenerator->generate('app_login', ['role' => 'technician']),
            ],
            [
                'section' => 'sign_in',
                'title' => $this->translator->trans('home.roles.admin.title'),
                'summary' => $this->translator->trans('home.roles.admin.copy'),
                'href' => $this->urlGenerator->generate('app_login', ['role' => 'admin']),
            ],
            [
                'section' => 'sign_in',
                'title' => $this->translator->trans('home.roles.customer.title'),
                'summary' => $this->translator->trans('home.roles.customer.copy'),
                'href' => $this->urlGenerator->generate('app_login', ['role' => 'customer']),
            ],
            [
                'section' => 'account',
                'title' => $this->translator->trans('reset.request.page_title'),
                'summary' => $this->translator->trans('search.index.reset_summary'),
                'href' => $this->urlGenerator->generate('app_password_reset_request', ['role' => 'customer']),
            ],
        ];

        if ($customerLoginSettings['createAccountEnabled']) {
            $publicLinks[] = [
                'section' => 'account',
                'title' => $this->translator->trans('register.page_title'),
                'summary' => $this->translator->trans('search.index.register_summary'),
                'href' => $this->urlGenerator->generate('app_register_customer'),
            ];
        }

        foreach ($publicLinks as $link) {
            $this->addResultIfMatches(
                $results,
                $needle,
                $terms,
                $link['section'],
                $link['title'],
                $link['summary'],
                $link['href'],
                null,
                $this->translator->trans('register.page_title') === $link['title'] ? 42 : 44,
            );
        }

        foreach ($supportWidget['links'] as $item) {
            $this->addResultIfMatches(
                $results,
                $needle,
                $terms,
                'support',
                $item['title'],
                $supportWidget['intro'],
                $item['url'],
                null,
                40,
            );
        }

        $this->addResultIfMatches(
            $results,
            $needle,
            $terms,
            'contact',
            $contactPageSettings['quickHelpTitle'],
            $contactPageSettings['quickHelpIntro'],
            $this->urlGenerator->generate('app_contact'),
            null,
            45,
        );
        $this->addResultIfMatches(
            $results,
            $needle,
            $terms,
            'contact',
            $contactPageSettings['priorityTitle'],
            $contactPageSettings['priorityIntro'],
            $this->urlGenerator->generate('app_contact'),
            null,
            46,
        );
        $this->addResultIfMatches(
            $results,
            $needle,
            $terms,
            'contact',
            $contactPageSettings['whenTitle'],
            $this->translator->trans('search.index.contact_when_summary'),
            $this->urlGenerator->generate('app_contact'),
            null,
            44,
        );

        foreach ($this->getSystemStatuses() as $system) {
            $this->addResultIfMatches(
                $results,
                $needle,
                $terms,
                'system_status',
                $system['name'],
                trim(sprintf('%s %s', $system['status'], $system['stateLabel'] ?? '')),
                $this->urlGenerator->generate('app_home').'#systemstatus',
                null,
                68,
            );
        }

        /** @var list<NewsArticle> $newsArticles */
        $newsArticles = [];
        if ($this->newsArticleSchemaInspector->isReady()) {
            $newsQueryBuilder = $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
                ->andWhere('article.isPublished = true')
                ->andWhere('article.publishedAt <= :now')
                ->setParameter('now', new \DateTimeImmutable())
                ->orderBy('article.isPinned', 'DESC')
                ->addOrderBy('article.publishedAt', 'DESC')
                ->setMaxResults(16);
            $this->applySearchConstraint(
                $newsQueryBuilder,
                ['article.title', 'article.summary', 'article.body'],
                $terms,
            );
            $newsArticles = $newsQueryBuilder
                ->getQuery()
                ->getResult();
        }

        foreach ($newsArticles as $article) {
            $summary = $article->getSummary();
            if (NewsCategory::PLANNED_MAINTENANCE === $article->getCategory()) {
                $summary = 'Planerat underhåll. '.$summary;
            }

            $this->addResult(
                $results,
                'news',
                $article->getTitle(),
                $summary,
                $this->urlGenerator->generate('app_news_show', ['id' => $article->getId()]),
                $this->scoreContentMatch(
                    $needle,
                    $terms,
                    $article->getTitle(),
                    $article->getSummary(),
                    $article->getBody(),
                    NewsCategory::PLANNED_MAINTENANCE === $article->getCategory() ? 90 : 74,
                ),
            );
        }

        if ($knowledgeBaseSettings['publicEnabled']) {
            /** @var list<KnowledgeBaseEntry> $knowledgeBaseEntries */
            $knowledgeBaseQueryBuilder = $this->entityManager->getRepository(KnowledgeBaseEntry::class)->createQueryBuilder('entry')
                ->andWhere('entry.isActive = true')
                ->andWhere('entry.audience IN (:audiences)')
                ->setParameter('audiences', [KnowledgeBaseAudience::PUBLIC, KnowledgeBaseAudience::BOTH])
                ->orderBy('entry.sortOrder', 'ASC')
                ->addOrderBy('entry.updatedAt', 'DESC')
                ->setMaxResults(16);
            $this->applySearchConstraint(
                $knowledgeBaseQueryBuilder,
                ['entry.title', 'entry.body'],
                $terms,
            );
            $knowledgeBaseEntries = $knowledgeBaseQueryBuilder
                ->getQuery()
                ->getResult();

            foreach ($knowledgeBaseEntries as $entry) {
                $this->addResult(
                $results,
                'knowledge_base',
                $entry->getTitle(),
                    mb_strimwidth(trim(strip_tags($entry->getBody())), 0, 150, '...'),
                    $this->urlGenerator->generate('app_knowledge_base_public', ['q' => $entry->getTitle()]),
                    $this->scoreContentMatch(
                        $needle,
                        $terms,
                        $entry->getTitle(),
                        $entry->getBody(),
                        '',
                        70,
                    ),
                );
            }
        }

        uasort(
            $results,
            static fn (array $left, array $right): int => ($right['score'] <=> $left['score']) ?: strcmp($left['title'], $right['title']),
        );

        return array_map(
            static fn (array $result): array => [
                'section' => $result['section'],
                'title' => $result['title'],
                'summary' => $result['summary'],
                'href' => $result['href'],
            ],
            array_slice(array_values($results), 0, 24),
        );
    }

    /**
     * @param array<string, array{section: string, title: string, summary: string, href: ?string, score: int}> $results
     */
    private function addResultIfMatches(
        array &$results,
        string $needle,
        array $terms,
        string $section,
        string $title,
        string $summary,
        ?string $href,
        ?string $searchableText = null,
        int $baseScore = 30,
    ): void {
        $content = trim(($searchableText ?? '').' '.$title.' '.$summary);
        $haystack = mb_strtolower($content);
        if (!$this->matches($needle, $terms, $haystack)) {
            return;
        }

        $this->addResult(
            $results,
            $section,
            $title,
            $summary,
            $href,
            $this->scoreContentMatch($needle, $terms, $title, $summary, $content, $baseScore),
        );
    }

    /**
     * @param array<string, array{section: string, title: string, summary: string, href: ?string, score: int}> $results
     */
    private function addResult(
        array &$results,
        string $section,
        string $title,
        string $summary,
        ?string $href,
        int $score,
    ): void {
        $key = mb_strtolower(sprintf('%s|%s|%s', $section, $title, $href ?? ''));
        $existingScore = $results[$key]['score'] ?? null;
        if (\is_int($existingScore) && $existingScore >= $score) {
            return;
        }

        $results[$key] = [
            'section' => $section,
            'title' => $title,
            'summary' => $summary,
            'href' => $href,
            'score' => $score,
        ];
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $needle): array
    {
        $parts = preg_split('/[\s\-_\/]+/u', $needle) ?: [];

        return array_values(array_filter(
            array_unique(array_map(static fn (string $part): string => trim($part), $parts)),
            static fn (string $part): bool => '' !== $part,
        ));
    }

    /**
     * @param list<string> $terms
     * @return list<string>
     */
    private function expandTerms(array $terms): array
    {
        $synonyms = [
            'driftstorning' => ['incident', 'fel', 'problem', 'storning', 'avbrott'],
            'storning' => ['driftstorning', 'incident', 'fel', 'problem', 'avbrott'],
            'incident' => ['driftstorning', 'fel', 'problem', 'avbrott'],
            'fel' => ['problem', 'incident', 'driftstorning', 'avbrott'],
            'problem' => ['fel', 'incident', 'driftstorning'],
            'avbrott' => ['incident', 'driftstorning', 'storning'],
            'underhall' => ['servicefonster', 'driftarbete', 'arbete', 'uppgradering'],
            'servicefonster' => ['underhall', 'driftarbete'],
            'driftarbete' => ['underhall', 'servicefonster', 'uppgradering'],
            'uppgradering' => ['underhall', 'release', 'uppdatering'],
            'uppdatering' => ['release', 'forandring', 'nyhet'],
            'release' => ['uppdatering', 'nyhet', 'lansering'],
            'nyhet' => ['uppdatering', 'release', 'information'],
            'kunskapsbank' => ['kunskapsbas', 'guide', 'faq', 'manual'],
            'kunskapsbas' => ['kunskapsbank', 'guide', 'faq', 'manual'],
            'guide' => ['manual', 'instruktion', 'kunskapsbas'],
            'faq' => ['vanliga', 'fragor', 'kunskapsbas'],
            'support' => ['hjalp', 'kontakt', 'assistans'],
            'hjalp' => ['support', 'kontakt', 'assistans'],
            'kontakt' => ['support', 'hjalp'],
            'konto' => ['registrering', 'inloggning', 'anvandare'],
            'registrering' => ['konto', 'skapa', 'anvandare'],
            'inloggning' => ['logga', 'login', 'konto'],
            'login' => ['inloggning', 'logga'],
            'losenord' => ['password', 'aterstall', 'glomt'],
            'aterstall' => ['losenord', 'glomt'],
            'glomt' => ['losenord', 'aterstall'],
        ];

        $expanded = $terms;
        foreach ($terms as $term) {
            foreach ($synonyms[$term] ?? [] as $synonym) {
                $expanded[] = $synonym;
            }
        }

        return array_values(array_filter(array_unique($expanded)));
    }

    /**
     * @param list<string> $terms
     */
    private function matches(string $needle, array $terms, string $haystack): bool
    {
        if (str_contains($haystack, $needle)) {
            return true;
        }

        $matchedTerms = 0;
        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                ++$matchedTerms;
            }
        }

        return $matchedTerms > 0;
    }

    /**
     * @param list<string> $fields
     * @param list<string> $terms
     */
    private function applySearchConstraint(\Doctrine\ORM\QueryBuilder $queryBuilder, array $fields, array $terms): void
    {
        $expressions = [];
        $uniqueTerms = array_values(array_slice(array_unique($terms), 0, 12));

        foreach ($uniqueTerms as $index => $term) {
            if ('' === trim($term)) {
                continue;
            }

            $parameter = 'search_term_'.$index;
            $fieldExpressions = [];
            foreach ($fields as $field) {
                $fieldExpressions[] = sprintf('LOWER(%s) LIKE :%s', $field, $parameter);
            }

            $expressions[] = '('.implode(' OR ', $fieldExpressions).')';
            $queryBuilder->setParameter($parameter, '%'.$term.'%');
        }

        if ([] !== $expressions) {
            $queryBuilder->andWhere('('.implode(' OR ', $expressions).')');
        }
    }

    /**
     * @param list<string> $terms
     */
    private function scoreContentMatch(
        string $needle,
        array $terms,
        string $title,
        string $summary,
        string $content,
        int $baseScore,
    ): int {
        $score = $baseScore;
        $titleText = mb_strtolower(trim($title));
        $summaryText = mb_strtolower(trim($summary));
        $contentText = mb_strtolower(trim($content));

        if ('' === $titleText && '' === $contentText) {
            return $score;
        }

        if ($titleText === $needle) {
            $score += 70;
        } elseif (str_starts_with($titleText, $needle)) {
            $score += 42;
        } elseif (str_contains($titleText, $needle)) {
            $score += 30;
        }

        if (str_contains($summaryText, $needle)) {
            $score += 16;
        }

        if (str_contains($contentText, $needle)) {
            $score += 12;
        }

        foreach ($terms as $term) {
            if (str_contains($titleText, $term)) {
                $score += 12;
            }

            if (str_contains($summaryText, $term)) {
                $score += 6;
            }

            if (str_contains($contentText, $term)) {
                $score += 4;
            }
        }

        if (str_contains($summaryText, 'planerat underhall') || str_contains($contentText, 'planerat underhall')) {
            $score += 8;
        }

        return $score;
    }

    /**
     * @param array{
     *     title: string,
     *     intro: string,
     *     links: list<array{icon: string, title: string, url: string}>,
     *     linkLines: list<string>
     * } $widget
     * @return array{
     *     title: string,
     *     intro: string,
     *     links: list<array{icon: string, title: string, url: string}>,
     *     linkLines: list<string>
     * }
     */
    private function filterHomeSupportWidget(array $widget, bool $publicKnowledgeBaseEnabled): array
    {
        if ($publicKnowledgeBaseEnabled) {
            return $widget;
        }

        $widget['links'] = array_values(array_filter(
            $widget['links'],
            fn (array $item): bool => !$this->isPublicKnowledgeBaseUrl($item['url']),
        ));
        $widget['linkLines'] = array_values(array_filter(
            $widget['linkLines'],
            fn (string $line): bool => !$this->isPublicKnowledgeBaseUrl(trim((string) (explode('|', $line, 3)[2] ?? ''))),
        ));

        return $widget;
    }

    private function isPublicKnowledgeBaseUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        $path = \is_string($path) ? $path : $url;

        return str_starts_with($path, '/kunskapsbas');
    }
}
