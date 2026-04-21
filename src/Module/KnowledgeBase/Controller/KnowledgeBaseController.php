<?php

declare(strict_types=1);

namespace App\Module\KnowledgeBase\Controller;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\KnowledgeBase\Entity\KnowledgeBaseEntry;
use App\Module\KnowledgeBase\Enum\KnowledgeBaseAudience;
use App\Module\KnowledgeBase\Enum\KnowledgeBaseEntryType;
use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

final class KnowledgeBaseController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SystemSettings $systemSettings,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/kunskapsbas', name: 'app_knowledge_base_public', methods: ['GET'])]
    public function publicIndex(Request $request): Response
    {
        $settings = $this->systemSettings->getKnowledgeBaseSettings();
        if (!$settings['publicEnabled']) {
            $this->addFlash('error', 'Den publika kunskapsbasen är inte aktiv just nu.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('knowledge_base/index.html.twig', [
            'pageMode' => 'public',
            'pageTitle' => $this->translator->trans('knowledge.page_title.public'),
            'pageSubtitle' => $this->translator->trans('knowledge.page_subtitle.public'),
            'searchQuery' => trim((string) $request->query->get('q')),
            'entries' => $this->findEntriesForAudience($request, true),
            'knowledgeBaseSettings' => $settings,
        ]);
    }

    #[Route('/portal/customer/kunskapsbas', name: 'app_portal_customer_knowledge_base', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function customerIndex(Request $request): Response
    {
        $settings = $this->systemSettings->getKnowledgeBaseSettings();
        $user = $this->getUser();
        \assert($user instanceof User);
        if (!$settings['customerEnabled']) {
            $this->addFlash('error', 'Kundkunskapsbasen är inte aktiv just nu.');

            return $this->redirectToRoute('app_portal_customer');
        }

        return $this->render('knowledge_base/index.html.twig', [
            'pageMode' => 'customer',
            'pageTitle' => $this->translator->trans('knowledge.page_title.customer'),
            'pageSubtitle' => $this->translator->trans('knowledge.page_subtitle.customer'),
            'searchQuery' => trim((string) $request->query->get('q')),
            'entries' => $this->findEntriesForAudience($request, false),
            'knowledgeBaseSettings' => $settings,
            'customerSidebar' => $this->buildCustomerSidebarViewData($user),
        ]);
    }

    #[Route('/portal/technician/kunskapsbas', name: 'app_portal_technician_knowledge_base', methods: ['GET'])]
    #[IsGranted('ROLE_TECHNICIAN')]
    public function technicianIndex(Request $request): Response
    {
        $settings = $this->systemSettings->getKnowledgeBaseSettings();
        $allowedAudiences = $this->getTechnicianAllowedAudiences($settings);

        if ([] === $allowedAudiences) {
            $this->addFlash('error', 'Tekniker kan inte redigera kunskapsbasen just nu.');

            return $this->redirectToRoute('app_portal_technician');
        }

        return $this->render('portal/technician_knowledge_base.html.twig', [
            'title' => 'Kunskapsbas',
            'summary' => 'Fyll på guider, smarta tips och vanliga frågor som hjälper både kunder och teamet.',
            'knowledgeBaseEntries' => $this->findTechnicianEntries($request, $allowedAudiences),
            'knowledgeBaseEntryTypes' => KnowledgeBaseEntryType::cases(),
            'knowledgeBaseAudiences' => $allowedAudiences,
            'knowledgeBaseSettings' => $settings,
            'searchQuery' => trim((string) $request->query->get('q')),
        ]);
    }

    #[Route('/portal/admin/kunskapsbas', name: 'app_portal_admin_knowledge_base_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createAdminEntry(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('create_knowledge_base_entry', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera formuläret för kunskapsbasen.');

            return $this->redirectToRoute('app_portal_admin');
        }

        return $this->handleEntryUpsert($request, null, 'app_portal_admin');
    }

    #[Route('/portal/admin/kunskapsbas/{id}', name: 'app_portal_admin_knowledge_base_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateAdminEntry(Request $request, KnowledgeBaseEntry $entry): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('update_knowledge_base_entry_%d', $entry->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera uppdateringen av kunskapsbasen.');

            return $this->redirectToRoute('app_portal_admin');
        }

        return $this->handleEntryUpsert($request, $entry, 'app_portal_admin');
    }

    #[Route('/portal/technician/kunskapsbas', name: 'app_portal_technician_knowledge_base_create', methods: ['POST'])]
    #[IsGranted('ROLE_TECHNICIAN')]
    public function createTechnicianEntry(Request $request): RedirectResponse
    {
        if ([] === $this->getTechnicianAllowedAudiences()) {
            throw $this->createAccessDeniedException('Teknikerbidrag till kunskapsbasen är avstängt.');
        }

        if (!$this->isCsrfTokenValid('create_knowledge_base_entry_technician', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera formuläret för kunskapsbasen.');

            return $this->redirectToRoute('app_portal_technician_knowledge_base');
        }

        return $this->handleEntryUpsert($request, null, 'app_portal_technician_knowledge_base', true);
    }

    #[Route('/portal/technician/kunskapsbas/{id}', name: 'app_portal_technician_knowledge_base_update', methods: ['POST'])]
    #[IsGranted('ROLE_TECHNICIAN')]
    public function updateTechnicianEntry(Request $request, KnowledgeBaseEntry $entry): RedirectResponse
    {
        if (!$this->canTechnicianManageAudience($entry->getAudience())) {
            throw $this->createAccessDeniedException('Teknikerbidrag till kunskapsbasen är avstängt.');
        }

        if (!$this->isCsrfTokenValid(sprintf('update_knowledge_base_entry_technician_%d', $entry->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Kunde inte verifiera uppdateringen av kunskapsbasen.');

            return $this->redirectToRoute('app_portal_technician_knowledge_base');
        }

        return $this->handleEntryUpsert($request, $entry, 'app_portal_technician_knowledge_base', true);
    }

    /**
     * @return array{tips: list<KnowledgeBaseEntry>, faq: list<KnowledgeBaseEntry>, articles: list<KnowledgeBaseEntry>}
     */
    private function findEntriesForAudience(Request $request, bool $publicOnly): array
    {
        $settings = $this->systemSettings->getKnowledgeBaseSettings();
        $query = trim((string) $request->query->get('q'));

        $qb = $this->entityManager
            ->getRepository(KnowledgeBaseEntry::class)
            ->createQueryBuilder('entry')
            ->andWhere('entry.isActive = true')
            ->orderBy('entry.sortOrder', 'ASC')
            ->addOrderBy('entry.updatedAt', 'DESC');

        if ('' !== $query) {
            $qb
                ->andWhere('LOWER(entry.title) LIKE :query OR LOWER(entry.body) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower($query).'%');
        }

        if ($publicOnly) {
            $qb->andWhere('entry.audience IN (:audiences)')
                ->setParameter('audiences', [KnowledgeBaseAudience::PUBLIC, KnowledgeBaseAudience::BOTH]);
        } else {
            $qb->andWhere('entry.audience IN (:audiences)')
                ->setParameter('audiences', [KnowledgeBaseAudience::CUSTOMER, KnowledgeBaseAudience::BOTH]);
        }

        /** @var list<KnowledgeBaseEntry> $entries */
        $entries = $qb->getQuery()->getResult();

        $tipsEnabled = $publicOnly ? $settings['publicSmartTipsEnabled'] : $settings['customerSmartTipsEnabled'];
        $faqEnabled = $publicOnly ? $settings['publicFaqEnabled'] : $settings['customerFaqEnabled'];

        return [
            'tips' => $tipsEnabled ? array_values(array_filter($entries, static fn (KnowledgeBaseEntry $entry): bool => KnowledgeBaseEntryType::SMART_TIP === $entry->getType())) : [],
            'faq' => $faqEnabled ? array_values(array_filter($entries, static fn (KnowledgeBaseEntry $entry): bool => KnowledgeBaseEntryType::FAQ === $entry->getType())) : [],
            'articles' => array_values(array_filter($entries, static fn (KnowledgeBaseEntry $entry): bool => KnowledgeBaseEntryType::ARTICLE === $entry->getType())),
        ];
    }

    private function handleEntryUpsert(Request $request, ?KnowledgeBaseEntry $entry, string $redirectRoute, bool $technicianRestricted = false): RedirectResponse
    {
        $title = trim((string) $request->request->get('title'));
        $body = trim((string) $request->request->get('body'));
        $sortOrder = max(0, (int) $request->request->get('sort_order'));
        $isActive = $request->request->getBoolean('is_active', true);

        try {
            $type = KnowledgeBaseEntryType::from((string) $request->request->get('type'));
            $audience = KnowledgeBaseAudience::from((string) $request->request->get('audience'));
        } catch (\ValueError) {
            $this->addFlash('error', 'Ogiltig typ eller målgrupp för kunskapsbasinlägget.');

            return $this->redirectToRoute($redirectRoute);
        }

        if ($technicianRestricted && !$this->canTechnicianManageAudience($audience)) {
            $this->addFlash('error', 'Tekniker får inte uppdatera den valda delen av kunskapsbasen.');

            return $this->redirectToRoute($redirectRoute);
        }

        if ('' === $title || '' === $body) {
            $this->addFlash('error', 'Titel och innehåll måste anges för kunskapsbasinlägget.');

            return $this->redirectToRoute($redirectRoute);
        }

        $isNew = null === $entry;
        if ($isNew) {
            $entry = new KnowledgeBaseEntry($title, $body, $type, $audience);
            $author = $this->getUser();
            if ($author instanceof User) {
                $entry->setAuthor($author);
            }
            $this->entityManager->persist($entry);
        }

        $entry
            ->setTitle($title)
            ->setBody($body)
            ->setType($type)
            ->setAudience($audience)
            ->setSortOrder($sortOrder);

        $isActive ? $entry->activate() : $entry->deactivate();

        $this->entityManager->flush();

        $this->addFlash('success', $isNew ? 'Kunskapsbasinlägget skapades.' : 'Kunskapsbasinlägget uppdaterades.');

        return $this->redirectToRoute($redirectRoute);
    }

    /**
     * @param array{
     *     publicEnabled: bool,
     *     customerEnabled: bool,
     *     publicSmartTipsEnabled: bool,
     *     publicFaqEnabled: bool,
     *     customerSmartTipsEnabled: bool,
     *     customerFaqEnabled: bool,
     *     publicTechnicianContributionsEnabled: bool,
     *     customerTechnicianContributionsEnabled: bool
     * }|null $settings
     * @return list<KnowledgeBaseAudience>
     */
    private function getTechnicianAllowedAudiences(?array $settings = null): array
    {
        $settings ??= $this->systemSettings->getKnowledgeBaseSettings();
        $audiences = [];

        if ($settings['publicTechnicianContributionsEnabled']) {
            $audiences[] = KnowledgeBaseAudience::PUBLIC;
        }

        if ($settings['customerTechnicianContributionsEnabled']) {
            $audiences[] = KnowledgeBaseAudience::CUSTOMER;
        }

        if ($settings['publicTechnicianContributionsEnabled'] && $settings['customerTechnicianContributionsEnabled']) {
            $audiences[] = KnowledgeBaseAudience::BOTH;
        }

        return $audiences;
    }

    private function canTechnicianManageAudience(KnowledgeBaseAudience $audience): bool
    {
        $settings = $this->systemSettings->getKnowledgeBaseSettings();

        return match ($audience) {
            KnowledgeBaseAudience::PUBLIC => $settings['publicTechnicianContributionsEnabled'],
            KnowledgeBaseAudience::CUSTOMER => $settings['customerTechnicianContributionsEnabled'],
            KnowledgeBaseAudience::BOTH => $settings['publicTechnicianContributionsEnabled'] && $settings['customerTechnicianContributionsEnabled'],
        };
    }

    /**
     * @param list<KnowledgeBaseAudience> $allowedAudiences
     * @return list<KnowledgeBaseEntry>
     */
    private function findTechnicianEntries(Request $request, array $allowedAudiences): array
    {
        $query = trim((string) $request->query->get('q'));

        $qb = $this->entityManager
            ->getRepository(KnowledgeBaseEntry::class)
            ->createQueryBuilder('entry')
            ->andWhere('entry.audience IN (:audiences)')
            ->setParameter('audiences', $allowedAudiences)
            ->orderBy('entry.sortOrder', 'ASC')
            ->addOrderBy('entry.updatedAt', 'DESC');

        if ('' !== $query) {
            $qb
                ->andWhere('LOWER(entry.title) LIKE :query OR LOWER(entry.body) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower($query).'%');
        }

        /** @var list<KnowledgeBaseEntry> $entries */
        $entries = $qb->getQuery()->getResult();

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCustomerSidebarViewData(User $user): array
    {
        $visibleTickets = array_values(array_filter(
            $this->entityManager->getRepository(Ticket::class)->findBy([], ['updatedAt' => 'DESC']),
            fn (Ticket $ticket): bool => $this->customerCanAccessTicket($user, $ticket),
        ));

        return [
            'activePage' => 'knowledge-base',
            'knowledgeBaseEnabled' => true,
            'ticketSummary' => [
                'total' => \count($visibleTickets),
                'open' => \count(array_filter($visibleTickets, static fn (Ticket $ticket): bool => \in_array($ticket->getStatus(), [TicketStatus::NEW, TicketStatus::OPEN, TicketStatus::PENDING_CUSTOMER], true))),
            ],
            'waitingOnCustomer' => \count(array_filter($visibleTickets, static fn (Ticket $ticket): bool => TicketStatus::PENDING_CUSTOMER === $ticket->getStatus())),
            'viewLabel' => 'Kunskapsläge',
            'viewNote' => 'Du är i kundens kunskapsbank just nu.',
            'actionItems' => [],
        ];
    }

    private function customerCanAccessTicket(User $user, Ticket $ticket): bool
    {
        if (TicketVisibility::INTERNAL_ONLY === $ticket->getVisibility()) {
            return false;
        }

        if ($ticket->getRequester()?->getId() === $user->getId()) {
            return true;
        }

        if (UserType::PRIVATE_CUSTOMER === $user->getType()) {
            return false;
        }

        if (null === $user->getCompany() || null === $ticket->getCompany()) {
            return false;
        }

        return TicketVisibility::COMPANY_SHARED === $ticket->getVisibility()
            && $ticket->getCompany()->getId() === $user->getCompany()?->getId();
    }
}
