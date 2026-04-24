<?php

declare(strict_types=1);

namespace App\Module\PublicPortal\Controller;

use App\Module\Maintenance\Service\MaintenanceMode;
use App\Module\News\Entity\NewsArticle;
use App\Module\News\Enum\NewsCategory;
use App\Module\News\Service\NewsArticleSchemaInspector;
use App\Module\PublicPortal\Service\PublicSiteSearch;
use App\Module\System\Service\SystemSettings;
use App\Module\Identity\Service\UserSchemaInspector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StatusController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MaintenanceMode $maintenanceMode,
        private readonly NewsArticleSchemaInspector $newsArticleSchemaInspector,
        private readonly PublicSiteSearch $publicSiteSearch,
        private readonly SystemSettings $systemSettings,
        private readonly UserSchemaInspector $userSchemaInspector,
    ) {
    }

    #[Route('/driftstatus', name: 'app_status_page', methods: ['GET'])]
    public function __invoke(): Response
    {
        $now = new \DateTimeImmutable();
        $maintenanceState = $this->maintenanceMode->getState($now);
        $statusPageSettings = $this->systemSettings->getStatusPageSettings();
        /** @var list<NewsArticle> $maintenanceArticles */
        $maintenanceArticles = $this->newsArticleSchemaInspector->isReady()
            ? $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
                ->andWhere('article.isPublished = :published')
                ->andWhere('article.publishedAt <= :now')
                ->andWhere('article.category = :category')
                ->setParameter('published', true)
                ->setParameter('now', $now)
                ->setParameter('category', NewsCategory::PLANNED_MAINTENANCE)
                ->orderBy('article.isPinned', 'DESC')
                ->addOrderBy('article.publishedAt', 'DESC')
                ->addOrderBy('article.updatedAt', 'DESC')
                ->setMaxResults(6)
                ->getQuery()
                ->getResult()
            : [];
        $maintenanceArticles = $this->stripAuthorsWhenUserSchemaIsOutdated($maintenanceArticles);

        /** @var list<NewsArticle> $recentNews */
        $recentNews = $this->newsArticleSchemaInspector->isReady()
            ? $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
                ->andWhere('article.isPublished = :published')
                ->andWhere('article.publishedAt <= :now')
                ->setParameter('published', true)
                ->setParameter('now', $now)
                ->orderBy('article.isPinned', 'DESC')
                ->addOrderBy('article.publishedAt', 'DESC')
                ->setMaxResults($statusPageSettings['recentUpdatesMaxItems'])
                ->getQuery()
                ->getResult()
            : [];
        $recentNews = $this->stripAuthorsWhenUserSchemaIsOutdated($recentNews);

        return $this->render('public/status.html.twig', [
            'maintenanceState' => $maintenanceState,
            'maintenanceScheduleLabel' => $this->formatWindow(
                $this->parseOptionalDateTime($maintenanceState['scheduledStartAt']),
                $this->parseOptionalDateTime($maintenanceState['scheduledEndAt']),
            ),
            'heroMaintenanceMeta' => $this->buildHeroMaintenanceMeta(
                $maintenanceState,
                $this->parseOptionalDateTime($maintenanceState['scheduledStartAt']),
                $this->parseOptionalDateTime($maintenanceState['scheduledEndAt']),
                $now,
            ),
            'statusPageSettings' => $statusPageSettings,
            'impactStatuses' => $this->buildImpactStatuses($statusPageSettings['impactItems'], $maintenanceState),
            'maintenanceArticles' => $maintenanceArticles,
            'maintenanceStatuses' => $this->buildMaintenanceStatuses($maintenanceArticles, $now),
            'recentNews' => $recentNews,
            'systemStatuses' => $this->publicSiteSearch->getSystemStatuses(),
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

    /**
     * @param array{
     *     effectiveEnabled: bool,
     *     isUpcoming: bool,
     *     mode: string
     * } $maintenanceState
     * @return array{
     *     title: string,
     *     summary: string,
     *     chips: list<string>
     * }
     */
    private function buildHeroMaintenanceMeta(
        array $maintenanceState,
        ?\DateTimeImmutable $startsAt,
        ?\DateTimeImmutable $endsAt,
        \DateTimeImmutable $now,
    ): array {
        $chips = [];

        if ($maintenanceState['effectiveEnabled']) {
            if ($endsAt instanceof \DateTimeImmutable && $endsAt > $now) {
                $chips[] = 'Beräknat slut '.mb_strtolower($this->formatAbsoluteDateTime($endsAt));
                $chips[] = 'Ca '.$this->formatDuration($now, $endsAt).' kvar';
            }

            return [
                'title' => 'Underhåll pågår just nu',
                'summary' => 'Kund- och teknikerinloggning är pausad medan arbetet slutförs. Statussidan hålls uppdaterad under hela fönstret.',
                'chips' => $chips,
            ];
        }

        if ($maintenanceState['isUpcoming'] && $startsAt instanceof \DateTimeImmutable) {
            $chips[] = 'Startar '.mb_strtolower($this->formatAbsoluteDateTime($startsAt));
            $chips[] = 'Om '.$this->formatDuration($now, $startsAt);

            if ($endsAt instanceof \DateTimeImmutable && $endsAt > $startsAt) {
                $chips[] = 'Beräknat slut '.mb_strtolower($this->formatAbsoluteDateTime($endsAt));
            }

            return [
                'title' => 'Schemalagt underhåll närmar sig',
                'summary' => 'Här ser du när driftfönstret börjar och ungefär när det väntas vara klart, så att ni kan planera innan inloggningen påverkas.',
                'chips' => $chips,
            ];
        }

        return [
            'title' => 'Normal drift just nu',
            'summary' => 'Inga aktiva driftstopp är registrerade. Eventuella planerade underhåll och senaste driftuppdateringar visas längre ner på sidan.',
            'chips' => [],
        ];
    }

    /**
     * @param list<NewsArticle> $articles
     * @return array<int, array{status: string, label: string, schedule: ?string}>
     */
    private function buildMaintenanceStatuses(array $articles, \DateTimeImmutable $now): array
    {
        $items = [];

        foreach ($articles as $article) {
            $startsAt = $article->getMaintenanceStartsAt();
            $endsAt = $article->getMaintenanceEndsAt();
            $status = 'scheduled';
            $label = 'Schemalagd';

            if ($startsAt instanceof \DateTimeImmutable && $startsAt > $now) {
                $status = 'upcoming';
                $label = 'Kommande';
            } elseif (
                (!$startsAt instanceof \DateTimeImmutable || $startsAt <= $now)
                && (!$endsAt instanceof \DateTimeImmutable || $endsAt >= $now)
            ) {
                $status = 'active';
                $label = 'Pågår';
            } elseif ($endsAt instanceof \DateTimeImmutable && $endsAt < $now) {
                $status = 'completed';
                $label = 'Avslutat';
            }

            $items[(int) $article->getId()] = [
                'status' => $status,
                'label' => $label,
                'schedule' => $this->formatWindow($startsAt, $endsAt),
            ];
        }

        return $items;
    }

    /**
     * @param list<array{
     *     name: string,
     *     normalLabel: string,
     *     upcomingLabel: string,
     *     activeLabel: string,
     *     description: ?string
     * }> $impactItems
     * @param array{
     *     effectiveEnabled: bool,
     *     isUpcoming: bool
     * } $maintenanceState
     * @return list<array{name: string, label: string, tone: string, description: ?string}>
     */
    private function buildImpactStatuses(array $impactItems, array $maintenanceState): array
    {
        $items = [];
        $isActive = (bool) ($maintenanceState['effectiveEnabled'] ?? false);
        $isUpcoming = (bool) ($maintenanceState['isUpcoming'] ?? false);

        foreach ($impactItems as $item) {
            $label = $item['normalLabel'];
            $tone = 'ok';

            if ($isActive) {
                $label = $item['activeLabel'];
                $tone = 'active';
            } elseif ($isUpcoming) {
                $label = $item['upcomingLabel'];
                $tone = 'upcoming';
            }

            $items[] = [
                'name' => $item['name'],
                'label' => $label,
                'tone' => $tone,
                'description' => $item['description'],
            ];
        }

        return $items;
    }

    private function formatWindow(?\DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): ?string
    {
        if (!$startsAt instanceof \DateTimeImmutable && !$endsAt instanceof \DateTimeImmutable) {
            return null;
        }

        if ($startsAt instanceof \DateTimeImmutable && $endsAt instanceof \DateTimeImmutable) {
            return sprintf('%s - %s', $startsAt->format('Y-m-d H:i'), $endsAt->format('Y-m-d H:i'));
        }

        $point = $startsAt ?? $endsAt;

        return $point instanceof \DateTimeImmutable ? $point->format('Y-m-d H:i') : null;
    }

    private function formatAbsoluteDateTime(\DateTimeImmutable $at): string
    {
        return $at->format('Y-m-d H:i');
    }

    private function formatDuration(\DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        $seconds = max(0, $to->getTimestamp() - $from->getTimestamp());

        if ($seconds < 3600) {
            $minutes = max(1, (int) ceil($seconds / 60));

            return sprintf('%d minut%s', $minutes, 1 === $minutes ? '' : 'er');
        }

        if ($seconds < 86400) {
            $hours = (int) floor($seconds / 3600);
            $minutes = (int) ceil(($seconds % 3600) / 60);

            if (0 === $minutes) {
                return sprintf('%d timm%s', $hours, 1 === $hours ? 'e' : 'ar');
            }

            return sprintf('%d tim %d min', $hours, $minutes);
        }

        $days = (int) floor($seconds / 86400);
        $hours = (int) floor(($seconds % 86400) / 3600);

        if (0 === $hours) {
            return sprintf('%d dag%s', $days, 1 === $days ? '' : 'ar');
        }

        return sprintf('%d dag%s %d tim', $days, 1 === $days ? '' : 'ar', $hours);
    }

    private function parseOptionalDateTime(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
