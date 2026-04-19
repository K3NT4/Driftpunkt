<?php

declare(strict_types=1);

namespace App\Module\PublicPortal\Controller;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Maintenance\Service\MaintenanceMode;
use App\Module\News\Entity\NewsArticle;
use App\Module\News\Enum\NewsCategory;
use App\Module\PublicPortal\Service\PublicSiteSearch;
use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SystemSettings $systemSettings,
        private readonly MaintenanceMode $maintenanceMode,
        private readonly PublicSiteSearch $publicSiteSearch,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $now = new \DateTimeImmutable();
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && \in_array($currentUser->getType(), [UserType::CUSTOMER, UserType::PRIVATE_CUSTOMER], true)) {
            return $this->redirectToRoute('app_portal_customer');
        }

        $knowledgeBaseSettings = $this->systemSettings->getKnowledgeBaseSettings();
        $customerLoginSettings = $this->systemSettings->getCustomerLoginSettings();
        $searchQuery = trim($request->query->getString('q'));
        /** @var list<NewsArticle> $latestNews */
        $latestNews = $this->entityManager->getRepository(NewsArticle::class)->createQueryBuilder('article')
            ->andWhere('article.isPublished = :published')
            ->andWhere('article.publishedAt <= :now')
            ->setParameter('published', true)
            ->setParameter('now', $now)
            ->orderBy('article.isPinned', 'DESC')
            ->addOrderBy('article.publishedAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
        $homeSupportWidget = $this->filterHomeSupportWidget(
            $this->systemSettings->getHomeSupportWidgetSettings(),
            (bool) $knowledgeBaseSettings['publicEnabled'],
        );
        $homepageStatusSection = $this->systemSettings->getHomepageStatusSectionSettings();
        $systemStatuses = array_slice(
            array_values(array_filter(
                $this->publicSiteSearch->getSystemStatuses(),
                static fn (array $item): bool => (bool) ($item['showOnHomepage'] ?? true),
            )),
            0,
            $homepageStatusSection['maxItems'],
        );

        return $this->render('home/index.html.twig', [
            'phase' => 'Fas 1',
            'latestNews' => $latestNews,
            'searchQuery' => $searchQuery,
            'searchResults' => '' !== $searchQuery ? $this->publicSiteSearch->search($searchQuery) : [],
            'topStatus' => $this->buildTopStatus(),
            'knowledgeBaseSettings' => $knowledgeBaseSettings,
            'customerLoginSettings' => $customerLoginSettings,
            'homeSupportWidget' => $homeSupportWidget,
            'homepageStatusSection' => $homepageStatusSection,
            'systemStatuses' => $systemStatuses,
        ]);
    }

    /**
     * @return array{
     *     label: string,
     *     isHealthy: bool,
     *     updatedLabel: string,
     *     activeTicketCount: int,
     *     plannedMaintenanceCount: int,
     *     plannedMaintenanceHref: ?string
     * }
     */
    private function buildTopStatus(): array
    {
        $now = new \DateTimeImmutable();
        $ticketRepository = $this->entityManager->getRepository(Ticket::class);
        $activeTicketStats = $ticketRepository->createQueryBuilder('ticket')
            ->select('COUNT(ticket.id) AS activeTicketCount', 'MAX(ticket.updatedAt) AS latestActiveTicketAt')
            ->andWhere('ticket.status NOT IN (:closedStatuses)')
            ->setParameter('closedStatuses', [TicketStatus::RESOLVED, TicketStatus::CLOSED])
            ->getQuery()
            ->getSingleResult();
        $incidentStats = $ticketRepository->createQueryBuilder('ticket')
            ->select('COUNT(ticket.id) AS incidentCount', 'MAX(ticket.updatedAt) AS latestIncidentAt')
            ->andWhere('ticket.requestType = :requestType')
            ->andWhere('ticket.status NOT IN (:closedStatuses)')
            ->setParameter('requestType', TicketRequestType::INCIDENT)
            ->setParameter('closedStatuses', [TicketStatus::RESOLVED, TicketStatus::CLOSED])
            ->getQuery()
            ->getSingleResult();

        $activeTicketCount = (int) ($activeTicketStats['activeTicketCount'] ?? 0);
        $latestActiveTicketAt = $this->parseDateTimeImmutable($activeTicketStats['latestActiveTicketAt'] ?? null);
        $incidentCount = (int) ($incidentStats['incidentCount'] ?? 0);
        $latestIncidentAt = $this->parseDateTimeImmutable($incidentStats['latestIncidentAt'] ?? null);

        $newsRepository = $this->entityManager->getRepository(NewsArticle::class);
        /** @var list<NewsArticle> $plannedMaintenanceArticles */
        $plannedMaintenanceArticles = $newsRepository->createQueryBuilder('article')
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
        $activePlannedMaintenanceArticles = array_values(array_filter(
            $plannedMaintenanceArticles,
            fn (NewsArticle $article): bool => $this->isActiveOrUpcomingMaintenance($article, $now),
        ));
        $plannedMaintenanceCount = count($activePlannedMaintenanceArticles);
        $latestMaintenanceAt = null;
        foreach ($activePlannedMaintenanceArticles as $article) {
            $latestMaintenanceAt = $this->latestDateTime($latestMaintenanceAt, $article->getUpdatedAt());
        }
        $latestPlannedMaintenanceArticle = $activePlannedMaintenanceArticles[0] ?? null;

        $maintenanceState = $this->maintenanceMode->getState();
        $maintenanceEnabled = (bool) ($maintenanceState['effectiveEnabled'] ?? false);
        $maintenanceScheduledUpcoming = (bool) ($maintenanceState['isUpcoming'] ?? false);
        $maintenanceUpdatedAt = $this->parseDateTimeImmutable($maintenanceState['updatedAt'] ?? null);
        $maintenanceScheduledStartAt = $this->parseDateTimeImmutable($maintenanceState['scheduledStartAt'] ?? null);

        if ($maintenanceScheduledUpcoming || $maintenanceEnabled) {
            $plannedMaintenanceCount = max(1, $plannedMaintenanceCount);
        }

        $isHealthy = !$maintenanceEnabled && 0 === $incidentCount;
        $label = $maintenanceEnabled
            ? 'Underhall pagar'
            : ($maintenanceScheduledUpcoming
                ? 'Underhall planerat'
                : ($incidentCount > 0 ? 'Incidenter rapporterade' : 'Driftpunkt OK'));

        $latestUpdatedAt = $this->latestDateTime(
            $maintenanceUpdatedAt,
            $maintenanceScheduledStartAt,
            $latestActiveTicketAt,
            $latestIncidentAt,
            $latestMaintenanceAt,
            $latestPlannedMaintenanceArticle?->getUpdatedAt(),
        ) ?? new \DateTimeImmutable();

        return [
            'label' => $label,
            'isHealthy' => $isHealthy,
            'updatedLabel' => $this->formatRelativeTime($latestUpdatedAt),
            'activeTicketCount' => $activeTicketCount,
            'plannedMaintenanceCount' => $plannedMaintenanceCount,
            'plannedMaintenanceHref' => ($maintenanceScheduledUpcoming || $maintenanceEnabled || $latestPlannedMaintenanceArticle instanceof NewsArticle)
                ? $this->generateUrl('app_status_page')
                : null,
        ];
    }

    private function formatRelativeTime(\DateTimeImmutable $at): string
    {
        $seconds = max(0, time() - $at->getTimestamp());

        if ($seconds < 3600) {
            $minutes = max(1, (int) floor($seconds / 60));

            return sprintf('%d minut%s sedan', $minutes, 1 === $minutes ? '' : 'er');
        }

        if ($seconds < 86400) {
            $hours = max(1, (int) floor($seconds / 3600));

            return sprintf('%d timm%s sedan', $hours, 1 === $hours ? 'e' : 'ar');
        }

        if ($seconds < 172800) {
            return 'igar';
        }

        $days = (int) floor($seconds / 86400);

        return sprintf('%d dagar sedan', $days);
    }

    private function parseDateTimeImmutable(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function latestDateTime(?\DateTimeImmutable ...$dates): ?\DateTimeImmutable
    {
        $latest = null;

        foreach ($dates as $date) {
            if (!$date instanceof \DateTimeImmutable) {
                continue;
            }

            if (null === $latest || $date->getTimestamp() > $latest->getTimestamp()) {
                $latest = $date;
            }
        }

        return $latest;
    }

    private function isActiveOrUpcomingMaintenance(NewsArticle $article, \DateTimeImmutable $now): bool
    {
        $startsAt = $article->getMaintenanceStartsAt();
        $endsAt = $article->getMaintenanceEndsAt();

        if ($startsAt instanceof \DateTimeImmutable && $startsAt > $now) {
            return true;
        }

        if ($endsAt instanceof \DateTimeImmutable) {
            return $endsAt >= $now;
        }

        return !($startsAt instanceof \DateTimeImmutable);
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
