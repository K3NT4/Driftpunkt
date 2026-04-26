<?php

declare(strict_types=1);

namespace App\Module\News\Controller;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Service\UserSchemaInspector;
use App\Module\News\Entity\NewsArticle;
use App\Module\News\Enum\NewsCategory;
use App\Module\Maintenance\Service\MaintenanceMode;
use App\Module\News\Service\NewsArticleSchemaInspector;
use App\Module\System\Entity\AddonModule;
use App\Module\System\Service\SystemSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class NewsController extends AbstractController
{
    private ?bool $newsEditorPlusEnabled = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SystemSettings $systemSettings,
        private readonly MaintenanceMode $maintenanceMode,
        private readonly NewsArticleSchemaInspector $newsArticleSchemaInspector,
        private readonly UserSchemaInspector $userSchemaInspector,
    ) {
    }

    #[Route('/nyheter', name: 'app_news_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $now = new \DateTimeImmutable();
        $selectedCategory = $request->query->getString('kategori', 'alla');
        $searchQuery = trim($request->query->getString('q'));
        $page = max(1, $request->query->getInt('sida', 1));
        $perPage = 7;
        $validCategories = ['alla', ...array_map(
            static fn (NewsCategory $category): string => $category->value,
            NewsCategory::cases(),
        )];

        if (!\in_array($selectedCategory, $validCategories, true)) {
            $selectedCategory = 'alla';
        }

        if (!$this->newsArticleSchemaInspector->isReady()) {
            return $this->render('news/index.html.twig', [
                'articles' => [],
                'featuredArticle' => null,
                'remainingArticles' => [],
                'maintenanceArticles' => [],
                'selectedCategory' => $selectedCategory,
                'categoryOptions' => [[
                    'value' => 'alla',
                    'label' => 'Alla',
                    'count' => 0,
                ]],
                'selectedCategoryLabel' => 'Alla',
                'searchQuery' => $searchQuery,
                'page' => 1,
                'perPage' => $perPage,
                'totalArticles' => 0,
                'totalPages' => 1,
                'maintenanceStatuses' => [],
                'featuredMaintenanceStatus' => null,
                'articleMaintenanceStatuses' => [],
            ]);
        }

        $queryBuilder = $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
            ->andWhere('article.isPublished = :published')
            ->andWhere('article.publishedAt <= :now')
            ->andWhere('article.archivedAt IS NULL')
            ->setParameter('published', true)
            ->setParameter('now', $now)
            ->orderBy('article.isPinned', 'DESC')
            ->addOrderBy('article.publishedAt', 'DESC');

        if ('alla' !== $selectedCategory) {
            $queryBuilder
                ->andWhere('article.category = :category')
                ->setParameter('category', NewsCategory::from($selectedCategory));
        }

        if ('' !== $searchQuery) {
            $queryBuilder
                ->andWhere('LOWER(article.title) LIKE :query OR LOWER(article.summary) LIKE :query OR LOWER(article.body) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower($searchQuery).'%');
        }

        $countQueryBuilder = clone $queryBuilder;
        $totalArticles = (int) $countQueryBuilder
            ->select('COUNT(article.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalArticles / $perPage));
        $page = min($page, $totalPages);

        /** @var list<NewsArticle> $articles */
        $articles = $queryBuilder
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
        $articles = $this->stripAuthorsWhenUserSchemaIsOutdated($articles);

        /** @var list<NewsArticle> $maintenanceArticles */
        /** @var list<NewsArticle> $maintenanceArticles */
        $maintenanceArticles = $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
            ->andWhere('article.isPublished = :published')
            ->andWhere('article.publishedAt <= :now')
            ->andWhere('article.archivedAt IS NULL')
            ->andWhere('article.category = :category')
            ->setParameter('published', true)
            ->setParameter('now', $now)
            ->setParameter('category', NewsCategory::PLANNED_MAINTENANCE)
            ->orderBy('article.isPinned', 'DESC')
            ->addOrderBy('article.publishedAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
        $maintenanceArticles = $this->stripAuthorsWhenUserSchemaIsOutdated($maintenanceArticles);
        usort(
            $maintenanceArticles,
            fn (NewsArticle $left, NewsArticle $right): int => ($left->getMaintenanceStartsAt() ?? $left->getPublishedAt()) <=> ($right->getMaintenanceStartsAt() ?? $right->getPublishedAt()),
        );

        $categoryOptions = [
            [
                'value' => 'alla',
                'label' => 'Alla',
                'count' => (int) $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
                    ->select('COUNT(article.id)')
                    ->andWhere('article.isPublished = :published')
                    ->andWhere('article.publishedAt <= :now')
                    ->andWhere('article.archivedAt IS NULL')
                    ->setParameter('published', true)
                    ->setParameter('now', $now)
                    ->getQuery()
                    ->getSingleScalarResult(),
            ],
        ];

        foreach (NewsCategory::cases() as $category) {
            $categoryOptions[] = [
                'value' => $category->value,
                'label' => $category->label(),
                'count' => (int) $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
                    ->select('COUNT(article.id)')
                    ->andWhere('article.isPublished = :published')
                    ->andWhere('article.publishedAt <= :now')
                    ->andWhere('article.archivedAt IS NULL')
                    ->andWhere('article.category = :category')
                    ->setParameter('published', true)
                    ->setParameter('now', $now)
                    ->setParameter('category', $category)
                    ->getQuery()
                    ->getSingleScalarResult(),
            ];
        }

        $featuredArticle = $articles[0] ?? null;
        $remainingArticles = null !== $featuredArticle ? array_slice($articles, 1) : [];
        $selectedCategoryLabel = 'Alla';

        foreach ($categoryOptions as $option) {
            if ($option['value'] === $selectedCategory) {
                $selectedCategoryLabel = $option['label'];
                break;
            }
        }

        return $this->render('news/index.html.twig', [
            'articles' => $articles,
            'featuredArticle' => $featuredArticle,
            'remainingArticles' => $remainingArticles,
            'maintenanceArticles' => $maintenanceArticles,
            'selectedCategory' => $selectedCategory,
            'categoryOptions' => $categoryOptions,
            'selectedCategoryLabel' => $selectedCategoryLabel,
            'searchQuery' => $searchQuery,
            'page' => $page,
            'perPage' => $perPage,
            'totalArticles' => $totalArticles,
            'totalPages' => $totalPages,
            'maintenanceStatuses' => $this->buildMaintenanceStatuses($maintenanceArticles, $now),
            'featuredMaintenanceStatus' => $featuredArticle instanceof NewsArticle ? $this->describeMaintenance($featuredArticle, $now) : null,
            'articleMaintenanceStatuses' => $this->buildMaintenanceStatuses($remainingArticles, $now),
        ]);
    }

    #[Route('/nyheter/{id}', name: 'app_news_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        if (!$this->newsArticleSchemaInspector->isReady()) {
            throw $this->createNotFoundException('Nyhetsdatabasen behöver migreras innan nyheten kan visas.');
        }

        $article = $this->findNewsArticleOr404($id);
        $this->stripAuthorsWhenUserSchemaIsOutdated([$article]);
        $now = new \DateTimeImmutable();

        if (!$article->isPublished() || $article->getPublishedAt() > $now) {
            throw $this->createNotFoundException('Nyheten är inte publicerad.');
        }

        if ($article->isArchived()) {
            throw $this->createNotFoundException('Nyheten är arkiverad.');
        }

        /** @var list<NewsArticle> $relatedArticles */
        $relatedArticles = $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
            ->andWhere('article.isPublished = :published')
            ->andWhere('article.publishedAt <= :now')
            ->andWhere('article.archivedAt IS NULL')
            ->setParameter('published', true)
            ->setParameter('now', $now)
            ->orderBy('article.isPinned', 'DESC')
            ->addOrderBy('article.publishedAt', 'DESC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();
        $relatedArticles = $this->stripAuthorsWhenUserSchemaIsOutdated($relatedArticles);

        return $this->render('news/show.html.twig', [
            'article' => $article,
            'articleMaintenanceStatus' => $this->describeMaintenance($article, $now),
            'relatedMaintenanceStatuses' => $this->buildMaintenanceStatuses($relatedArticles, $now),
            'relatedArticles' => array_values(array_filter(
                $relatedArticles,
                static fn (NewsArticle $candidate): bool => $candidate->getId() !== $article->getId(),
            )),
        ]);
    }

    /**
     * @param list<NewsArticle> $articles
     *
     * @return list<NewsArticle>
     */
    private function stripAuthorsWhenUserSchemaIsOutdated(array $articles): array
    {
        if ($this->userSchemaInspector->isReady()) {
            return $articles;
        }

        foreach ($articles as $article) {
            $article->setAuthor(null);
        }

        return $articles;
    }

    #[Route('/portal/admin/nyheter', name: 'app_portal_admin_news', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminIndex(Request $request): Response
    {
        $searchQuery = trim($request->query->getString('news_q'));
        $dateFilter = trim($request->query->getString('news_date', 'all'));
        $selectedArticleId = $request->query->getInt('edit', 0);
        $newsSchemaReady = $this->newsArticleSchemaInspector->isReady();
        $allowMaintenanceCategory = $this->isGranted('ROLE_SUPER_ADMIN');
        $newsArticles = $newsSchemaReady ? $this->findAllArticles($searchQuery, $dateFilter) : [];
        if (!$allowMaintenanceCategory) {
            $newsArticles = array_values(array_filter(
                $newsArticles,
                static fn (NewsArticle $article): bool => NewsCategory::PLANNED_MAINTENANCE !== $article->getCategory(),
            ));
        }

        return $this->render('portal/admin_news.html.twig', [
            'title' => 'Nyheter',
            'summary' => 'Publicera nyheter som visas på startsidan och i nyhetslistan.',
            'maintenanceState' => $this->maintenanceMode->getState(),
            'newsArticles' => $newsArticles,
            'selectedArticle' => $this->resolveSelectedArticle($newsArticles, $selectedArticleId),
            'newsFilters' => [
                'q' => $searchQuery,
                'date' => $dateFilter,
            ],
            'newsSchemaReady' => $newsSchemaReady,
            'newsSchemaMissingColumns' => $this->newsArticleSchemaInspector->missingColumns(),
            'newsSettings' => $this->systemSettings->getNewsSettings(),
            'newsEditorPlusEnabled' => $this->isNewsEditorPlusEnabled(),
            'allowMaintenanceCategory' => $allowMaintenanceCategory,
            'homeSupportWidget' => $this->systemSettings->getHomeSupportWidgetSettings(),
            'homepageStatusSection' => $this->systemSettings->getHomepageStatusSectionSettings(),
            'maintenanceNoticeSettings' => $this->systemSettings->getMaintenanceNoticeSettings(),
        ]);
    }

    #[Route('/portal/admin/nyheter', name: 'app_portal_admin_news_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createAdminNews(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNewsSchemaIsOutdated('app_portal_admin_news')) {
            return $redirect;
        }

        if (!$this->isCsrfTokenValid('create_news_article', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera formuläret för nyheten.');

            return $this->redirectToRoute('app_portal_admin_news');
        }

        return $this->handleNewsUpsert($request, null, 'app_portal_admin_news', $this->isGranted('ROLE_SUPER_ADMIN'));
    }

    #[Route('/portal/admin/nyheter/{id}', name: 'app_portal_admin_news_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateAdminNews(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->redirectIfNewsSchemaIsOutdated('app_portal_admin_news')) {
            return $redirect;
        }

        $article = $this->findNewsArticleOr404($id);
        $this->assertAdminMaintenanceNewsAccess($article, 'uppdatera');
        if (!$this->isCsrfTokenValid(sprintf('update_news_article_%d', $article->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera uppdateringen av nyheten.');

            return $this->redirectToRoute('app_portal_admin_news');
        }

        return $this->handleNewsUpsert($request, $article, 'app_portal_admin_news', $this->isGranted('ROLE_SUPER_ADMIN'));
    }

    #[Route('/portal/admin/nyheter/{id}/arkivera', name: 'app_portal_admin_news_archive', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function archiveAdminNews(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->redirectIfNewsSchemaIsOutdated('app_portal_admin_news')) {
            return $redirect;
        }

        $article = $this->findNewsArticleOr404($id);
        $this->assertAdminMaintenanceNewsAccess($article, 'arkivera');
        if (!$this->isCsrfTokenValid(sprintf('archive_news_article_%d', $article->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera arkiveringen av nyheten.');

            return $this->redirectToAdminNewsSelection($request, $article);
        }

        if ($article->isArchived()) {
            $article->unarchive();
            $this->addFlash('success', 'Nyheten flyttades tillbaka från arkivet.');
        } else {
            $article->archive();
            $this->addFlash('success', 'Nyheten arkiverades.');
        }

        $this->entityManager->flush();

        return $this->redirectToAdminNewsSelection($request, $article);
    }

    #[Route('/portal/admin/nyheter/{id}/ta-bort', name: 'app_portal_admin_news_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAdminNews(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->redirectIfNewsSchemaIsOutdated('app_portal_admin_news')) {
            return $redirect;
        }

        $article = $this->findNewsArticleOr404($id);
        $this->assertAdminMaintenanceNewsAccess($article, 'ta bort');
        if (!$this->isCsrfTokenValid(sprintf('delete_news_article_%d', $article->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera borttagningen av nyheten.');

            return $this->redirectToRoute('app_portal_admin_news', $this->buildNewsRedirectParameters($request));
        }

        $this->entityManager->remove($article);
        $this->entityManager->flush();
        $this->addFlash('success', 'Nyheten togs bort.');

        return $this->redirectToRoute('app_portal_admin_news', $this->buildNewsRedirectParameters($request));
    }

    #[Route('/portal/technician/nyheter', name: 'app_portal_technician_news', methods: ['GET'])]
    #[IsGranted('ROLE_TECHNICIAN')]
    public function technicianIndex(Request $request): Response
    {
        if (!$this->systemSettings->getNewsSettings()['technicianContributionsEnabled']) {
            $this->addFlash('error', 'Tekniker kan inte skapa nyheter just nu.');

            return $this->redirectToRoute('app_portal_technician');
        }

        $searchQuery = trim($request->query->getString('news_q'));
        $selectedArticleId = $request->query->getInt('edit', 0);
        $newsSchemaReady = $this->newsArticleSchemaInspector->isReady();
        $newsArticles = $newsSchemaReady ? $this->findArticlesForTechnician($searchQuery) : [];

        return $this->render('portal/technician_news.html.twig', [
            'title' => 'Nyheter',
            'summary' => 'Publicera nyheter och uppdateringar till startsidan.',
            'newsArticles' => $newsArticles,
            'selectedArticle' => $this->resolveSelectedArticle($newsArticles, $selectedArticleId),
            'newsSettings' => $this->systemSettings->getNewsSettings(),
            'newsSchemaReady' => $newsSchemaReady,
            'newsSchemaMissingColumns' => $this->newsArticleSchemaInspector->missingColumns(),
            'newsEditorPlusEnabled' => $this->isNewsEditorPlusEnabled(),
            'allowMaintenanceCategory' => false,
            'newsFilters' => [
                'q' => $searchQuery,
                'date' => 'all',
            ],
        ]);
    }

    #[Route('/portal/technician/nyheter', name: 'app_portal_technician_news_create', methods: ['POST'])]
    #[IsGranted('ROLE_TECHNICIAN')]
    public function createTechnicianNews(Request $request): RedirectResponse
    {
        if (!$this->systemSettings->getNewsSettings()['technicianContributionsEnabled']) {
            throw $this->createAccessDeniedException('Tekniker får inte skapa nyheter just nu.');
        }

        if ($redirect = $this->redirectIfNewsSchemaIsOutdated('app_portal_technician_news')) {
            return $redirect;
        }

        if (!$this->isCsrfTokenValid('create_news_article_technician', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera formuläret för nyheten.');

            return $this->redirectToRoute('app_portal_technician_news');
        }

        return $this->handleNewsUpsert($request, null, 'app_portal_technician_news', false);
    }

    #[Route('/portal/technician/nyheter/{id}', name: 'app_portal_technician_news_update', methods: ['POST'])]
    #[IsGranted('ROLE_TECHNICIAN')]
    public function updateTechnicianNews(Request $request, int $id): RedirectResponse
    {
        if (!$this->systemSettings->getNewsSettings()['technicianContributionsEnabled']) {
            throw $this->createAccessDeniedException('Tekniker får inte uppdatera nyheter just nu.');
        }

        if ($redirect = $this->redirectIfNewsSchemaIsOutdated('app_portal_technician_news')) {
            return $redirect;
        }

        $article = $this->findNewsArticleOr404($id);
        if (NewsCategory::PLANNED_MAINTENANCE === $article->getCategory()) {
            throw $this->createAccessDeniedException('Tekniker får inte uppdatera underhållsnyheter.');
        }

        if (!$this->isCsrfTokenValid(sprintf('update_news_article_technician_%d', $article->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera uppdateringen av nyheten.');

            return $this->redirectToRoute('app_portal_technician_news');
        }

        return $this->handleNewsUpsert($request, $article, 'app_portal_technician_news', false);
    }

    #[Route('/portal/technician/nyheter/{id}/arkivera', name: 'app_portal_technician_news_archive', methods: ['POST'])]
    #[IsGranted('ROLE_TECHNICIAN')]
    public function archiveTechnicianNews(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->redirectIfNewsSchemaIsOutdated('app_portal_technician_news')) {
            return $redirect;
        }

        $article = $this->findNewsArticleOr404($id);
        $this->assertTechnicianNewsAccess($article, 'arkivera');

        if (!$this->isCsrfTokenValid(sprintf('archive_news_article_technician_%d', $article->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera arkiveringen av nyheten.');

            return $this->redirectToTechnicianNewsSelection($request, $article);
        }

        if ($article->isArchived()) {
            $article->unarchive();
            $this->addFlash('success', 'Nyheten flyttades tillbaka från arkivet.');
        } else {
            $article->archive();
            $this->addFlash('success', 'Nyheten arkiverades.');
        }

        $this->entityManager->flush();

        return $this->redirectToTechnicianNewsSelection($request, $article);
    }

    #[Route('/portal/technician/nyheter/{id}/ta-bort', name: 'app_portal_technician_news_delete', methods: ['POST'])]
    #[IsGranted('ROLE_TECHNICIAN')]
    public function deleteTechnicianNews(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->redirectIfNewsSchemaIsOutdated('app_portal_technician_news')) {
            return $redirect;
        }

        $article = $this->findNewsArticleOr404($id);
        $this->assertTechnicianNewsAccess($article, 'ta bort');

        if (!$this->isCsrfTokenValid(sprintf('delete_news_article_technician_%d', $article->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera borttagningen av nyheten.');

            return $this->redirectToRoute('app_portal_technician_news', $this->buildNewsRedirectParameters($request));
        }

        $this->entityManager->remove($article);
        $this->entityManager->flush();
        $this->addFlash('success', 'Nyheten togs bort.');

        return $this->redirectToRoute('app_portal_technician_news', $this->buildNewsRedirectParameters($request));
    }

    private function handleNewsUpsert(
        Request $request,
        ?NewsArticle $article,
        string $redirectRoute,
        bool $allowMaintenanceCategory = true,
    ): RedirectResponse
    {
        $title = trim((string) $request->request->get('title'));
        $summary = trim((string) $request->request->get('summary'));
        $body = trim((string) $request->request->get('body'));
        $imageUrl = trim((string) $request->request->get('image_url'));
        $publishAt = $this->parseOptionalDateTime((string) $request->request->get('publish_at'));
        $maintenanceStartsAt = $this->parseOptionalDateTime((string) $request->request->get('maintenance_starts_at'));
        $maintenanceEndsAt = $this->parseOptionalDateTime((string) $request->request->get('maintenance_ends_at'));
        $categoryValue = (string) $request->request->get('category', NewsCategory::GENERAL->value);
        $isPublished = $request->request->getBoolean('is_published', true);
        $isPinned = $request->request->getBoolean('is_pinned');
        $now = new \DateTimeImmutable();

        try {
            $category = NewsCategory::from($categoryValue);
        } catch (\ValueError) {
            $this->addFlash('error', 'Ogiltig nyhetskategori.');

            return $this->redirectToRoute($redirectRoute);
        }

        if (!$allowMaintenanceCategory && NewsCategory::PLANNED_MAINTENANCE === $category) {
            $this->addFlash('error', 'Planerat underhåll kan bara hanteras av superadmin.');

            return $this->redirectToRoute($redirectRoute);
        }

        if ($maintenanceStartsAt instanceof \DateTimeImmutable && $maintenanceEndsAt instanceof \DateTimeImmutable && $maintenanceEndsAt < $maintenanceStartsAt) {
            $this->addFlash('error', 'Sluttid for underhall kan inte vara tidigare an starttid.');

            return $this->redirectToRoute($redirectRoute);
        }

        if (NewsCategory::PLANNED_MAINTENANCE !== $category) {
            $maintenanceStartsAt = null;
            $maintenanceEndsAt = null;
        }

        if ('' === $title || '' === $summary || '' === $body) {
            $this->addFlash('error', 'Titel, sammanfattning och innehåll måste anges.');

            return $this->redirectToRoute($redirectRoute);
        }

        if (!$publishAt instanceof \DateTimeImmutable) {
            $publishAt = $isPublished ? $now : ($article?->getPublishedAt() ?? $now);
        }

        $isNew = null === $article;
        if ($isNew) {
            $article = new NewsArticle($title, $summary, $body);
            $author = $this->getUser();
            if ($author instanceof User) {
                $article->setAuthor($author);
            }
            $this->entityManager->persist($article);
        }

        $article
            ->setTitle($title)
            ->setSummary($summary)
            ->setBody($body)
            ->setImageUrl($imageUrl)
            ->setPublishedAt($publishAt)
            ->setMaintenanceStartsAt($maintenanceStartsAt)
            ->setMaintenanceEndsAt($maintenanceEndsAt)
            ->setCategory($category);

        if ($article->isArchived()) {
            $article->unarchive();
        }

        $isPublished ? $article->publish() : $article->unpublish();
        $isPinned ? $article->pin() : $article->unpin();

        $this->entityManager->flush();

        $successMessage = $isNew ? 'Nyheten skapades.' : 'Nyheten uppdaterades.';
        if ($isPublished && $publishAt > $now) {
            $successMessage .= ' Schemalagd publicering är aktiverad.';
        }

        $this->addFlash('success', $successMessage);

        $parameters = $this->buildNewsRedirectParameters($request);
        if ('app_portal_technician_news' !== $redirectRoute) {
            $parameters['edit'] = $article->getId();
        }

        return $this->redirectToRoute($redirectRoute, $parameters);
    }

    /**
     * @return list<NewsArticle>
     */
    private function findAllArticles(string $searchQuery = '', string $dateFilter = 'all'): array
    {
        /** @var list<NewsArticle> $articles */
        $articles = $this->entityManager->getRepository(NewsArticle::class)->findBy([], [
            'archivedAt' => 'ASC',
            'isPinned' => 'DESC',
            'publishedAt' => 'DESC',
            'updatedAt' => 'DESC',
        ]);

        return array_values(array_filter(
            $articles,
            function (NewsArticle $article) use ($searchQuery, $dateFilter): bool {
                if ('all' !== $dateFilter && !$this->matchesDatePreset($article->getPublishedAt(), $dateFilter)) {
                    return false;
                }

                if ('' === $searchQuery) {
                    return true;
                }

                $needle = mb_strtolower($searchQuery);
                $haystack = mb_strtolower(implode(' ', [
                    $article->getTitle(),
                    $article->getSummary(),
                    $article->getBody(),
                    $article->getCategory()->value,
                ]));

                return str_contains($haystack, $needle);
            },
        ));
    }

    /**
     * @return list<NewsArticle>
     */
    private function findArticlesForTechnician(string $searchQuery = ''): array
    {
        if (!$this->newsArticleSchemaInspector->isReady()) {
            return [];
        }

        /** @var list<NewsArticle> $articles */
        $articles = $this->entityManager->getRepository(NewsArticle::class)->findBy(
            ['category' => NewsCategory::GENERAL],
            [
                'archivedAt' => 'ASC',
                'isPinned' => 'DESC',
                'publishedAt' => 'DESC',
                'updatedAt' => 'DESC',
            ],
        );

        if ('' === $searchQuery) {
            return $articles;
        }

        $needle = mb_strtolower($searchQuery);

        return array_values(array_filter(
            $articles,
            static function (NewsArticle $article) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $article->getTitle(),
                    $article->getSummary(),
                    $article->getBody(),
                    $article->getCategory()->value,
                ]));

                return str_contains($haystack, $needle);
            },
        ));
    }

    private function findNewsArticleOr404(int $id): NewsArticle
    {
        /** @var NewsArticle|null $article */
        $article = $this->entityManager->getRepository(NewsArticle::class)->find($id);
        if (!$article instanceof NewsArticle) {
            throw $this->createNotFoundException('Nyheten kunde inte hittas.');
        }

        return $article;
    }

    private function redirectIfNewsSchemaIsOutdated(string $route): ?RedirectResponse
    {
        if ($this->newsArticleSchemaInspector->isReady()) {
            return null;
        }

        $this->addFlash('error', sprintf(
            'Nyhetsdatabasen behöver migreras innan nyhetsmodulen kan användas. Saknade kolumner: %s.',
            implode(', ', $this->newsArticleSchemaInspector->missingColumns()),
        ));

        return $this->redirectToRoute($route);
    }

    /**
     * @param list<NewsArticle> $articles
     */
    private function resolveSelectedArticle(array $articles, int $selectedArticleId): ?NewsArticle
    {
        if ($selectedArticleId > 0) {
            foreach ($articles as $article) {
                if ($article->getId() === $selectedArticleId) {
                    return $article;
                }
            }
        }

        return $articles[0] ?? null;
    }

    private function redirectToAdminNewsSelection(Request $request, NewsArticle $article): RedirectResponse
    {
        $parameters = $this->buildNewsRedirectParameters($request);
        $parameters['edit'] = $article->getId();

        return $this->redirectToRoute('app_portal_admin_news', $parameters);
    }

    private function redirectToTechnicianNewsSelection(Request $request, NewsArticle $article): RedirectResponse
    {
        $parameters = $this->buildNewsRedirectParameters($request);
        $parameters['edit'] = $article->getId();

        return $this->redirectToRoute('app_portal_technician_news', $parameters);
    }

    /**
     * @return array<string, string|int>
     */
    private function buildNewsRedirectParameters(Request $request): array
    {
        $parameters = [];

        foreach (['news_q', 'news_date'] as $queryKey) {
            $value = trim((string) $request->query->get($queryKey, ''));
            if ('' !== $value) {
                $parameters[$queryKey] = $value;
            }
        }

        return $parameters;
    }

    private function assertTechnicianNewsAccess(NewsArticle $article, string $action): void
    {
        if (!$this->systemSettings->getNewsSettings()['technicianContributionsEnabled']) {
            throw $this->createAccessDeniedException(sprintf('Tekniker får inte %s nyheter just nu.', $action));
        }

        if (NewsCategory::PLANNED_MAINTENANCE === $article->getCategory()) {
            throw $this->createAccessDeniedException(sprintf('Tekniker får inte %s underhållsnyheter.', $action));
        }
    }

    private function assertAdminMaintenanceNewsAccess(NewsArticle $article, string $action): void
    {
        if (NewsCategory::PLANNED_MAINTENANCE === $article->getCategory() && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException(sprintf('Bara superadmin får %s underhållsnyheter.', $action));
        }
    }

    private function matchesDatePreset(\DateTimeImmutable $createdAt, string $preset): bool
    {
        return match ($preset) {
            'today' => $createdAt->format('Y-m-d') === (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'last_7_days' => $createdAt >= new \DateTimeImmutable('-7 days'),
            'older' => $createdAt < new \DateTimeImmutable('-7 days'),
            default => true,
        };
    }

    private function isNewsEditorPlusEnabled(): bool
    {
        if (null !== $this->newsEditorPlusEnabled) {
            return $this->newsEditorPlusEnabled;
        }

        try {
            $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
            if (!$schemaManager->tablesExist(['addon_modules'])) {
                $this->newsEditorPlusEnabled = true;

                return $this->newsEditorPlusEnabled;
            }
        } catch (\Throwable) {
            $this->newsEditorPlusEnabled = true;

            return $this->newsEditorPlusEnabled;
        }

        /** @var AddonModule|null $addon */
        $addon = $this->entityManager->getRepository(AddonModule::class)->findOneBy(['slug' => 'news-editor-plus']);
        $this->newsEditorPlusEnabled = null === $addon ? true : $addon->isEnabled();

        return $this->newsEditorPlusEnabled;
    }

    /**
     * @param list<NewsArticle> $articles
     * @return array<int, array{status: string, label: string, schedule: ?string}>
     */
    private function buildMaintenanceStatuses(array $articles, \DateTimeImmutable $now): array
    {
        $statuses = [];

        foreach ($articles as $article) {
            if (!$article->getId()) {
                continue;
            }

            $statuses[$article->getId()] = $this->describeMaintenance($article, $now);
        }

        return $statuses;
    }

    /**
     * @return array{status: string, label: string, schedule: ?string}
     */
    private function describeMaintenance(NewsArticle $article, \DateTimeImmutable $now): array
    {
        if (NewsCategory::PLANNED_MAINTENANCE !== $article->getCategory()) {
            return [
                'status' => 'standard',
                'label' => $article->getCategory()->label(),
                'schedule' => null,
            ];
        }

        $startsAt = $article->getMaintenanceStartsAt();
        $endsAt = $article->getMaintenanceEndsAt();

        if ($startsAt instanceof \DateTimeImmutable && $startsAt > $now) {
            return [
                'status' => 'upcoming',
                'label' => 'Kommande',
                'schedule' => $this->formatMaintenanceSchedule($startsAt, $endsAt),
            ];
        }

        if ($startsAt instanceof \DateTimeImmutable && (!$endsAt instanceof \DateTimeImmutable || $endsAt >= $now)) {
            return [
                'status' => 'active',
                'label' => 'Pagar',
                'schedule' => $this->formatMaintenanceSchedule($startsAt, $endsAt),
            ];
        }

        if ($endsAt instanceof \DateTimeImmutable && $endsAt < $now) {
            return [
                'status' => 'completed',
                'label' => 'Avslutat',
                'schedule' => $this->formatMaintenanceSchedule($startsAt, $endsAt),
            ];
        }

        return [
            'status' => 'scheduled',
            'label' => 'Planerat',
            'schedule' => $this->formatMaintenanceSchedule($startsAt, $endsAt),
        ];
    }

    private function formatMaintenanceSchedule(?\DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): ?string
    {
        if (!$startsAt instanceof \DateTimeImmutable && !$endsAt instanceof \DateTimeImmutable) {
            return null;
        }

        if ($startsAt instanceof \DateTimeImmutable && $endsAt instanceof \DateTimeImmutable) {
            return sprintf('%s - %s', $startsAt->format('Y-m-d H:i'), $endsAt->format('Y-m-d H:i'));
        }

        if ($startsAt instanceof \DateTimeImmutable) {
            return sprintf('Start %s', $startsAt->format('Y-m-d H:i'));
        }

        return sprintf('Till %s', $endsAt?->format('Y-m-d H:i'));
    }

    private function parseOptionalDateTime(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
