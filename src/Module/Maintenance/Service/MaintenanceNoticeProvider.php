<?php

declare(strict_types=1);

namespace App\Module\Maintenance\Service;

use App\Module\News\Entity\NewsArticle;
use App\Module\News\Enum\NewsCategory;
use App\Module\System\Service\SystemSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MaintenanceNoticeProvider
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly MaintenanceMode $maintenanceMode,
        private readonly SystemSettings $systemSettings,
    ) {
    }

    /**
     * @return array{
     *     status: 'upcoming'|'active',
     *     eyebrow: string,
     *     title: string,
     *     summary: string,
     *     schedule: ?string,
     *     href: ?string,
     *     linkLabel: string
     * }|null
     */
    public function getNotice(): ?array
    {
        $now = new \DateTimeImmutable();
        $state = $this->maintenanceMode->getState($now);

        if ($state['effectiveEnabled'] || $state['isUpcoming']) {
            return $this->buildStateNotice($state, $now);
        }

        $article = $this->findRelevantArticle($now);

        if (!$article instanceof NewsArticle) {
            return null;
        }

        return [
            'status' => $this->resolveArticleStatus($article, $now),
            'eyebrow' => 'Planerat underhall',
            'title' => $article->getTitle(),
            'summary' => $article->getSummary(),
            'schedule' => $this->formatArticleSchedule($article, $now),
            'href' => $this->urlGenerator->generate('app_status_page'),
            'linkLabel' => 'Las driftinfo',
        ];
    }

    /**
     * @param array{
     *     effectiveEnabled: bool,
     *     isUpcoming: bool,
     *     mode: string,
     *     message: ?string,
     *     scheduledStartAt: ?string,
     *     scheduledEndAt: ?string
     * } $state
     * @return array{
     *     status: 'upcoming'|'active',
     *     eyebrow: string,
     *     title: string,
     *     summary: string,
     *     schedule: ?string,
     *     href: string,
     *     linkLabel: string
     * }
     */
    private function buildStateNotice(array $state, \DateTimeImmutable $now): array
    {
        $startsAt = $this->parseOptionalDateTime($state['scheduledStartAt']);
        $endsAt = $this->parseOptionalDateTime($state['scheduledEndAt']);
        $isActive = (bool) $state['effectiveEnabled'];

        return [
            'status' => $isActive ? 'active' : 'upcoming',
            'eyebrow' => $isActive ? 'Underhallslage aktivt' : 'Schemalagt underhall',
            'title' => match ($state['mode']) {
                'manual' => 'Driftpunkt ar tillfalligt i underhallslage',
                'scheduled_active' => 'Schemalagt underhall pagar just nu',
                default => 'Underhall ar planerat inom kort',
            },
            'summary' => $state['message'] ?: (
                $isActive
                    ? 'Planerat arbete pagar just nu. Kund- och teknikerinloggning ar tillfalligt pausad tills arbetet ar klart.'
                    : 'Vi har ett planerat underhallsfonster pa vag. Las driftinformationen for att forbereda teamet i god tid.'
            ),
            'schedule' => $this->formatWindow($startsAt, $endsAt, $now),
            'href' => $this->urlGenerator->generate('app_status_page'),
            'linkLabel' => 'Visa driftinfo',
        ];
    }

    private function findRelevantArticle(\DateTimeImmutable $now): ?NewsArticle
    {
        try {
            /** @var list<NewsArticle> $articles */
            $articles = $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
                ->andWhere('article.isPublished = :published')
                ->andWhere('article.publishedAt <= :now')
                ->andWhere('article.category = :category')
                ->setParameter('published', true)
                ->setParameter('now', $now)
                ->setParameter('category', NewsCategory::PLANNED_MAINTENANCE)
                ->orderBy('article.isPinned', 'DESC')
                ->addOrderBy('article.publishedAt', 'DESC')
                ->addOrderBy('article.updatedAt', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Throwable) {
            return null;
        }

        $activeArticle = null;
        $upcomingArticle = null;
        $lookaheadDays = $this->systemSettings->getMaintenanceNoticeSettings()['lookaheadDays'];
        $lookaheadLimit = $now->modify(sprintf('+%d days', $lookaheadDays));

        foreach ($articles as $article) {
            $startsAt = $article->getMaintenanceStartsAt() ?? $article->getPublishedAt();
            $endsAt = $article->getMaintenanceEndsAt();

            if ($startsAt <= $now && (!$endsAt instanceof \DateTimeImmutable || $endsAt >= $now)) {
                if (
                    null === $activeArticle
                    || $startsAt->getTimestamp() < ($activeArticle->getMaintenanceStartsAt() ?? $activeArticle->getPublishedAt())->getTimestamp()
                ) {
                    $activeArticle = $article;
                }

                continue;
            }

            if ($startsAt > $now && $startsAt <= $lookaheadLimit) {
                if (
                    null === $upcomingArticle
                    || $startsAt->getTimestamp() < ($upcomingArticle->getMaintenanceStartsAt() ?? $upcomingArticle->getPublishedAt())->getTimestamp()
                ) {
                    $upcomingArticle = $article;
                }
            }
        }

        return $activeArticle ?? $upcomingArticle;
    }

    private function resolveArticleStatus(NewsArticle $article, \DateTimeImmutable $now): string
    {
        $startsAt = $article->getMaintenanceStartsAt() ?? $article->getPublishedAt();
        $endsAt = $article->getMaintenanceEndsAt();

        if ($startsAt <= $now && (!$endsAt instanceof \DateTimeImmutable || $endsAt >= $now)) {
            return 'active';
        }

        return 'upcoming';
    }

    private function formatArticleSchedule(NewsArticle $article, \DateTimeImmutable $now): ?string
    {
        return $this->formatWindow(
            $article->getMaintenanceStartsAt(),
            $article->getMaintenanceEndsAt(),
            $now,
        );
    }

    private function formatWindow(?\DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt, \DateTimeImmutable $now): ?string
    {
        if (!$startsAt instanceof \DateTimeImmutable && !$endsAt instanceof \DateTimeImmutable) {
            return null;
        }

        if ($startsAt instanceof \DateTimeImmutable && $startsAt > $now) {
            $prefix = 'Startar ';
        } elseif ($endsAt instanceof \DateTimeImmutable && $endsAt >= $now) {
            $prefix = 'Pagar till ';
        } else {
            $prefix = 'Tid: ';
        }

        if ($startsAt instanceof \DateTimeImmutable && $endsAt instanceof \DateTimeImmutable) {
            return sprintf('%s%s - %s', $prefix, $startsAt->format('Y-m-d H:i'), $endsAt->format('Y-m-d H:i'));
        }

        $moment = $startsAt ?? $endsAt;

        return $prefix.($moment instanceof \DateTimeImmutable ? $moment->format('Y-m-d H:i') : '');
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
