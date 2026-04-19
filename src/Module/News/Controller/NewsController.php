<?php

declare(strict_types=1);

namespace App\Module\News\Controller;

use App\Module\Identity\Entity\User;
use App\Module\News\Entity\NewsArticle;
use App\Module\News\Enum\NewsCategory;
use App\Module\Maintenance\Service\MaintenanceMode;
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
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SystemSettings $systemSettings,
        private readonly MaintenanceMode $maintenanceMode,
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

        $queryBuilder = $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
            ->andWhere('article.isPublished = :published')
            ->andWhere('article.publishedAt <= :now')
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

        /** @var list<NewsArticle> $maintenanceArticles */
        /** @var list<NewsArticle> $maintenanceArticles */
        $maintenanceArticles = $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
            ->andWhere('article.isPublished = :published')
            ->andWhere('article.publishedAt <= :now')
            ->andWhere('article.category = :category')
            ->setParameter('published', true)
            ->setParameter('now', $now)
            ->setParameter('category', NewsCategory::PLANNED_MAINTENANCE)
            ->orderBy('article.isPinned', 'DESC')
            ->addOrderBy('article.publishedAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
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
    public function show(NewsArticle $article): Response
    {
        $now = new \DateTimeImmutable();

        if (!$article->isPublished() || $article->getPublishedAt() > $now) {
            throw $this->createNotFoundException('Nyheten är inte publicerad.');
        }

        /** @var list<NewsArticle> $relatedArticles */
        $relatedArticles = $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
            ->andWhere('article.isPublished = :published')
            ->andWhere('article.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('now', $now)
            ->orderBy('article.isPinned', 'DESC')
            ->addOrderBy('article.publishedAt', 'DESC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

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

    #[Route('/portal/admin/nyheter', name: 'app_portal_admin_news', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminIndex(Request $request): Response
    {
        $searchQuery = trim($request->query->getString('news_q'));
        $dateFilter = trim($request->query->getString('news_date', 'all'));

        return $this->render('portal/admin_news.html.twig', [
            'title' => 'Nyheter',
            'summary' => 'Publicera nyheter som visas på startsidan och i nyhetslistan.',
            'maintenanceState' => $this->maintenanceMode->getState(),
            'newsArticles' => $this->findAllArticles($searchQuery, $dateFilter),
            'newsFilters' => [
                'q' => $searchQuery,
                'date' => $dateFilter,
            ],
            'newsSettings' => $this->systemSettings->getNewsSettings(),
            'homeSupportWidget' => $this->systemSettings->getHomeSupportWidgetSettings(),
            'homepageStatusSection' => $this->systemSettings->getHomepageStatusSectionSettings(),
            'maintenanceNoticeSettings' => $this->systemSettings->getMaintenanceNoticeSettings(),
        ]);
    }

    #[Route('/portal/admin/nyheter', name: 'app_portal_admin_news_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createAdminNews(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('create_news_article', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera formuläret för nyheten.');

            return $this->redirectToRoute('app_portal_admin_news');
        }

        return $this->handleNewsUpsert($request, null, 'app_portal_admin_news');
    }

    #[Route('/portal/admin/nyheter/{id}', name: 'app_portal_admin_news_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateAdminNews(Request $request, NewsArticle $article): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('update_news_article_%d', $article->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera uppdateringen av nyheten.');

            return $this->redirectToRoute('app_portal_admin_news');
        }

        return $this->handleNewsUpsert($request, $article, 'app_portal_admin_news');
    }

    #[Route('/portal/technician/nyheter', name: 'app_portal_technician_news', methods: ['GET'])]
    #[IsGranted('ROLE_TECHNICIAN')]
    public function technicianIndex(): Response
    {
        if (!$this->systemSettings->getNewsSettings()['technicianContributionsEnabled']) {
            $this->addFlash('error', 'Tekniker kan inte skapa nyheter just nu.');

            return $this->redirectToRoute('app_portal_technician');
        }

        return $this->render('portal/technician_news.html.twig', [
            'title' => 'Nyheter',
            'summary' => 'Publicera nyheter och uppdateringar till startsidan.',
            'newsArticles' => $this->findArticlesForTechnician(),
            'newsSettings' => $this->systemSettings->getNewsSettings(),
            'allowMaintenanceCategory' => false,
        ]);
    }

    #[Route('/portal/technician/nyheter', name: 'app_portal_technician_news_create', methods: ['POST'])]
    #[IsGranted('ROLE_TECHNICIAN')]
    public function createTechnicianNews(Request $request): RedirectResponse
    {
        if (!$this->systemSettings->getNewsSettings()['technicianContributionsEnabled']) {
            throw $this->createAccessDeniedException('Tekniker får inte skapa nyheter just nu.');
        }

        if (!$this->isCsrfTokenValid('create_news_article_technician', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera formuläret för nyheten.');

            return $this->redirectToRoute('app_portal_technician_news');
        }

        return $this->handleNewsUpsert($request, null, 'app_portal_technician_news', false);
    }

    #[Route('/portal/technician/nyheter/{id}', name: 'app_portal_technician_news_update', methods: ['POST'])]
    #[IsGranted('ROLE_TECHNICIAN')]
    public function updateTechnicianNews(Request $request, NewsArticle $article): RedirectResponse
    {
        if (!$this->systemSettings->getNewsSettings()['technicianContributionsEnabled']) {
            throw $this->createAccessDeniedException('Tekniker får inte uppdatera nyheter just nu.');
        }

        if (NewsCategory::PLANNED_MAINTENANCE === $article->getCategory()) {
            throw $this->createAccessDeniedException('Tekniker får inte uppdatera underhållsnyheter.');
        }

        if (!$this->isCsrfTokenValid(sprintf('update_news_article_technician_%d', $article->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera uppdateringen av nyheten.');

            return $this->redirectToRoute('app_portal_technician_news');
        }

        return $this->handleNewsUpsert($request, $article, 'app_portal_technician_news', false);
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
            $this->addFlash('error', 'Tekniker kan bara skapa vanliga sajt-nyheter.');

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

        $isPublished ? $article->publish() : $article->unpublish();
        $isPinned ? $article->pin() : $article->unpin();

        $this->entityManager->flush();

        $this->addFlash('success', $isNew ? 'Nyheten skapades.' : 'Nyheten uppdaterades.');

        return $this->redirectToRoute($redirectRoute);
    }

    /**
     * @return list<NewsArticle>
     */
    private function findAllArticles(string $searchQuery = '', string $dateFilter = 'all'): array
    {
        /** @var list<NewsArticle> $articles */
        $articles = $this->entityManager->getRepository(NewsArticle::class)->findBy([], [
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
    private function findArticlesForTechnician(): array
    {
        /** @var list<NewsArticle> $articles */
        $articles = $this->entityManager->getRepository(NewsArticle::class)->findBy(
            ['category' => NewsCategory::GENERAL],
            [
                'isPinned' => 'DESC',
                'publishedAt' => 'DESC',
                'updatedAt' => 'DESC',
            ],
        );

        return $articles;
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
