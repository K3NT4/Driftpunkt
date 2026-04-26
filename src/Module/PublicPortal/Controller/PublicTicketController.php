<?php

declare(strict_types=1);

namespace App\Module\PublicPortal\Controller;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketCategory;
use App\Module\Ticket\Entity\TicketComment;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use App\Module\Ticket\Service\TicketAuditLogger;
use App\Module\Ticket\Service\TicketCommentAttachmentBuilder;
use App\Module\Ticket\Service\TicketReferenceGenerator;
use App\Module\Ticket\Service\TicketResponseNotifier;
use App\Module\Ticket\Service\TicketRoutingResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicTicketController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SystemSettings $systemSettings,
        private readonly TicketReferenceGenerator $ticketReferenceGenerator,
        private readonly TicketRoutingResolver $ticketRoutingResolver,
        private readonly TicketAuditLogger $ticketAuditLogger,
        private readonly TicketResponseNotifier $ticketResponseNotifier,
        private readonly TicketCommentAttachmentBuilder $ticketCommentAttachmentBuilder,
    ) {
    }

    #[Route('/skapa-arende', name: 'app_public_ticket_new', methods: ['GET'])]
    public function new(Request $request): Response
    {
        $this->denyWhenDisabled();

        return $this->renderFormPage($this->defaultsFromRequest($request));
    }

    #[Route('/skapa-arende', name: 'app_public_ticket_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->denyWhenDisabled();

        $defaults = $this->defaultsFromRequest($request);
        if (!$this->isCsrfTokenValid('public_create_ticket', $this->requestString($request, '_token'))) {
            return $this->renderFormPage($defaults, 'Kunde inte verifiera formuläret.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ('' !== $this->requestString($request, 'website')) {
            return $this->renderFormPage($defaults, 'Kontrollera namn, e-post, ämne och beskrivning.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name = $this->requestString($request, 'name');
        $email = mb_strtolower($this->requestString($request, 'email'));
        $phone = $this->requestString($request, 'phone');
        $subject = mb_substr($this->requestString($request, 'subject'), 0, 180);
        $summary = $this->requestString($request, 'summary');

        if ('' === $name || '' === $subject || '' === $summary || false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->renderFormPage($defaults, 'Kontrollera namn, e-post, ämne och beskrivning.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category = $this->findCategory($request);
        $requestType = TicketRequestType::tryFrom($this->requestString($request, 'request_type')) ?? TicketRequestType::INCIDENT;
        $impactLevel = TicketImpactLevel::tryFrom($this->requestString($request, 'impact_level')) ?? TicketImpactLevel::SINGLE_USER;
        $customer = $this->findActiveCustomerByEmail($email);
        $routingRule = $this->ticketRoutingResolver->resolveRule($category, $customer, $requestType, $impactLevel);
        $slaPolicy = $routingRule?->getDefaultSlaPolicy();
        $priority = $routingRule?->getDefaultPriority() ?? $slaPolicy?->getEffectiveDefaultPriority() ?? TicketPriority::NORMAL;
        $escalationLevel = $routingRule?->getDefaultEscalationLevel() ?? $slaPolicy?->getEffectiveDefaultEscalationLevel() ?? TicketEscalationLevel::NONE;
        $assignee = $routingRule?->getDefaultAssignee() ?? $slaPolicy?->getEffectiveDefaultAssignee();
        $assignedTeam = $routingRule?->getTeam() ?? $slaPolicy?->getEffectiveDefaultTeam() ?? $assignee?->getTechnicianTeam();
        $company = $customer?->getCompany();

        $ticket = new Ticket(
            $this->ticketReferenceGenerator->nextReference($company),
            $subject,
            $this->buildPublicSummary($name, $email, $phone, $summary),
            TicketStatus::NEW,
            TicketVisibility::PRIVATE,
            $priority,
            $requestType,
            $impactLevel,
            $escalationLevel,
        );
        $ticket->setRequester($customer);
        $ticket->setCompany($company);
        $ticket->setCategory($category);
        $ticket->setAssignee($assignee);
        $ticket->setAssignedTeam($assignedTeam);
        $ticket->setSlaPolicy($slaPolicy);

        $initialComment = null;
        $attachments = [];
        $attachmentSettings = $this->systemSettings->getTicketAttachmentSettings();
        $uploadedFile = $request->files->get('attachment');
        $externalAttachmentUrl = $this->requestString($request, 'external_attachment_url');
        $externalAttachmentLabel = $this->requestString($request, 'external_attachment_label');
        if ($uploadedFile instanceof UploadedFile || '' !== $externalAttachmentUrl) {
            if (mb_strlen($externalAttachmentUrl) > 1000) {
                return $this->renderFormPage($defaults, 'Delningslänken får vara högst 1000 tecken.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $initialComment = new TicketComment($ticket, $customer ?? $this->findFirstAdminAuthor(), 'Bilagor bifogades när ärendet skapades.', false);

            try {
                $attachments = $this->ticketCommentAttachmentBuilder->build(
                    $uploadedFile,
                    $externalAttachmentUrl,
                    $externalAttachmentLabel,
                    $ticket,
                    $initialComment,
                    $attachmentSettings,
                );
            } catch (\InvalidArgumentException $exception) {
                return $this->renderFormPage($defaults, $exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
            } catch (\RuntimeException) {
                return $this->renderFormPage($defaults, 'Bilagan kunde inte sparas just nu.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $this->entityManager->persist($ticket);
        if ($initialComment instanceof TicketComment) {
            $ticket->addComment($initialComment);
            $this->entityManager->persist($initialComment);
            foreach ($attachments as $attachment) {
                $initialComment->addAttachment($attachment);
                $this->entityManager->persist($attachment);
            }
        }
        $this->entityManager->flush();

        $this->ticketAuditLogger->log($ticket, 'public_ticket_created', 'Ticket skapad via publikt formulär.', $customer);
        if ($ticket->getAssignee() instanceof User) {
            try {
                $this->ticketResponseNotifier->notifyAssignedTechnician($ticket, $ticket->getAssignee(), 'En ny ticket har skapats via publikt formulär och tilldelats dig.');
            } catch (\Throwable) {
                // Ticketen är redan skapad; ett notifieringsfel ska inte ge publik besökare 500 eller uppmana till dubbelpostning.
            }
        }

        return $this->renderFormPage([], null, Response::HTTP_OK, true);
    }

    private function denyWhenDisabled(): void
    {
        if (!$this->systemSettings->getPublicTicketFormSettings()['enabled']) {
            throw $this->createNotFoundException();
        }
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private function renderFormPage(array $defaults, ?string $formError = null, int $status = Response::HTTP_OK, bool $ticketCreated = false): Response
    {
        return $this->render('public/ticket_create.html.twig', [
            'defaults' => $defaults + $this->emptyDefaults(),
            'formError' => $formError,
            'ticketCreated' => $ticketCreated,
            'categories' => $this->entityManager->getRepository(TicketCategory::class)->findBy([], ['name' => 'ASC']),
            'ticketRequestTypes' => TicketRequestType::cases(),
            'ticketImpactLevels' => TicketImpactLevel::cases(),
            'ticketAttachmentSettings' => $this->systemSettings->getTicketAttachmentSettings(),
        ], new Response('', $status));
    }

    /**
     * @return array<string, string>
     */
    private function defaultsFromRequest(Request $request): array
    {
        return [
            'name' => $this->requestString($request, 'name'),
            'email' => $this->requestString($request, 'email'),
            'phone' => $this->requestString($request, 'phone'),
            'subject' => $this->requestString($request, 'subject'),
            'summary' => $this->requestString($request, 'summary'),
            'category_id' => $this->requestString($request, 'category_id'),
            'request_type' => $this->requestString($request, 'request_type', TicketRequestType::INCIDENT->value),
            'impact_level' => $this->requestString($request, 'impact_level', TicketImpactLevel::SINGLE_USER->value),
            'external_attachment_url' => $this->requestString($request, 'external_attachment_url'),
            'external_attachment_label' => $this->requestString($request, 'external_attachment_label'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function emptyDefaults(): array
    {
        return [
            'name' => '',
            'email' => '',
            'phone' => '',
            'subject' => '',
            'summary' => '',
            'category_id' => '',
            'request_type' => TicketRequestType::INCIDENT->value,
            'impact_level' => TicketImpactLevel::SINGLE_USER->value,
            'external_attachment_url' => '',
            'external_attachment_label' => '',
        ];
    }

    private function findCategory(Request $request): ?TicketCategory
    {
        $categoryId = (int) $this->requestString($request, 'category_id');
        if ($categoryId <= 0) {
            return null;
        }

        $category = $this->entityManager->getRepository(TicketCategory::class)->find($categoryId);

        return $category instanceof TicketCategory ? $category : null;
    }

    private function findActiveCustomerByEmail(string $email): ?User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => mb_strtolower(trim($email))]);
        if (!$user instanceof User || !$user->isActive() || !\in_array($user->getType(), [UserType::CUSTOMER, UserType::PRIVATE_CUSTOMER], true)) {
            return null;
        }

        return $user;
    }

    private function findFirstAdminAuthor(): User
    {
        $author = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'public-ticket@driftpunkt.local']);
        if (!$author instanceof User) {
            $author = new User('public-ticket@driftpunkt.local', 'Publikt', 'Formulär', UserType::TECHNICIAN);
            $author->setPassword(bin2hex(random_bytes(24)));
            $this->entityManager->persist($author);
        }

        $author
            ->setType(UserType::TECHNICIAN)
            ->disableEmailNotifications()
            ->deactivate();

        return $author;
    }

    private function requestString(Request $request, string $key, string $default = ''): string
    {
        $value = $request->request->all()[$key] ?? $default;
        if (null === $value || \is_array($value)) {
            return '';
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            return '';
        }

        return trim((string) $value);
    }

    private function buildPublicSummary(string $name, string $email, string $phone, string $summary): string
    {
        $lines = [
            'Publik kontakt:',
            sprintf('Namn: %s', $name),
            sprintf('E-post: %s', $email),
        ];
        if ('' !== $phone) {
            $lines[] = sprintf('Telefon: %s', $phone);
        }
        $lines[] = '';
        $lines[] = 'Beskrivning:';
        $lines[] = $summary;

        return implode("\n", $lines);
    }
}
