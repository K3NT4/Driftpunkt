<?php

declare(strict_types=1);

namespace App\Module\Ticket\Entity;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\TechnicianTeam;
use App\Module\Identity\Entity\User;
use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tickets')]
#[ORM\HasLifecycleCallbacks]
class Ticket
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $publicId;

    #[ORM\Column(length: 32, unique: true)]
    private string $reference;

    #[ORM\Column(length: 180)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $summary;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resolutionSummary = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?TicketCategory $category = null;

    #[ORM\Column(enumType: TicketStatus::class)]
    private TicketStatus $status;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(enumType: TicketVisibility::class)]
    private TicketVisibility $visibility;

    #[ORM\Column(enumType: TicketPriority::class)]
    private TicketPriority $priority;

    #[ORM\Column(enumType: TicketRequestType::class, options: ['default' => 'incident'])]
    private TicketRequestType $requestType;

    #[ORM\Column(enumType: TicketImpactLevel::class, options: ['default' => 'single_user'])]
    private TicketImpactLevel $impactLevel;

    #[ORM\Column(enumType: TicketEscalationLevel::class)]
    private TicketEscalationLevel $escalationLevel;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?Company $company = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?User $requester = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?User $assignee = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?TechnicianTeam $assignedTeam = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?SlaPolicy $slaPolicy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?TicketIntakeTemplate $intakeTemplate = null;

    /**
     * @var array<string, string>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $intakeAnswers = [];

    /**
     * @var array<string, bool>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $checklistProgress = [];

    /**
     * @var Collection<int, TicketComment>
     */
    #[ORM\OneToMany(mappedBy: 'ticket', targetEntity: TicketComment::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $comments;

    /**
     * @var Collection<int, TicketAuditLog>
     */
    #[ORM\OneToMany(mappedBy: 'ticket', targetEntity: TicketAuditLog::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $auditLogs;

    #[ORM\OneToOne(mappedBy: 'ticket', targetEntity: ExternalTicketImport::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private ?ExternalTicketImport $externalImport = null;

    public function __construct(
        string $reference,
        string $subject,
        string $summary,
        TicketStatus $status = TicketStatus::NEW,
        TicketVisibility $visibility = TicketVisibility::PRIVATE,
        TicketPriority $priority = TicketPriority::NORMAL,
        TicketRequestType $requestType = TicketRequestType::INCIDENT,
        TicketImpactLevel $impactLevel = TicketImpactLevel::SINGLE_USER,
        TicketEscalationLevel $escalationLevel = TicketEscalationLevel::NONE,
    ) {
        $this->publicId = Uuid::v7();
        $this->reference = strtoupper(trim($reference));
        $this->subject = trim($subject);
        $this->summary = trim($summary);
        $this->visibility = $visibility;
        $this->priority = $priority;
        $this->requestType = $requestType;
        $this->impactLevel = $impactLevel;
        $this->escalationLevel = $escalationLevel;
        $this->comments = new ArrayCollection();
        $this->auditLogs = new ArrayCollection();
        $this->setStatus($status);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function rename(string $subject): self
    {
        $this->subject = trim($subject);

        return $this;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    /**
     * @return Collection<int, TicketComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function setSummary(string $summary): self
    {
        $this->summary = trim($summary);

        return $this;
    }

    public function getResolutionSummary(): ?string
    {
        return $this->resolutionSummary;
    }

    public function setResolutionSummary(?string $resolutionSummary): self
    {
        $normalized = null !== $resolutionSummary ? trim($resolutionSummary) : null;
        $this->resolutionSummary = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getCategory(): ?TicketCategory
    {
        return $this->category;
    }

    public function setCategory(?TicketCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getStatus(): TicketStatus
    {
        return $this->status;
    }

    public function setStatus(TicketStatus $status): self
    {
        $wasClosed = isset($this->status) && TicketStatus::CLOSED === $this->status;
        $this->status = $status;
        if (TicketStatus::CLOSED === $status) {
            if (!$wasClosed) {
                $this->closedAt = new \DateTimeImmutable();
            }
        } else {
            $this->closedAt = null;
        }

        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): self
    {
        $this->closedAt = $closedAt;

        return $this;
    }

    public function getVisibility(): TicketVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(TicketVisibility $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getPriority(): TicketPriority
    {
        return $this->priority;
    }

    public function setPriority(TicketPriority $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getRequestType(): TicketRequestType
    {
        return $this->requestType;
    }

    public function setRequestType(TicketRequestType $requestType): self
    {
        $this->requestType = $requestType;

        return $this;
    }

    public function getImpactLevel(): TicketImpactLevel
    {
        return $this->impactLevel;
    }

    public function setImpactLevel(TicketImpactLevel $impactLevel): self
    {
        $this->impactLevel = $impactLevel;

        return $this;
    }

    public function getEscalationLevel(): TicketEscalationLevel
    {
        return $this->escalationLevel;
    }

    public function setEscalationLevel(TicketEscalationLevel $escalationLevel): self
    {
        $this->escalationLevel = $escalationLevel;

        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getIntakeAnswers(): array
    {
        return $this->intakeAnswers;
    }

    public function getIntakeTemplate(): ?TicketIntakeTemplate
    {
        return $this->intakeTemplate;
    }

    public function setIntakeTemplate(?TicketIntakeTemplate $intakeTemplate): self
    {
        $this->intakeTemplate = $intakeTemplate;
        $this->syncChecklistProgress();

        return $this;
    }

    /**
     * @param array<string, string> $intakeAnswers
     */
    public function setIntakeAnswers(array $intakeAnswers): self
    {
        $normalized = [];
        foreach ($intakeAnswers as $key => $value) {
            $normalizedKey = trim((string) $key);
            $normalizedValue = trim((string) $value);
            if ('' !== $normalizedKey && '' !== $normalizedValue) {
                $normalized[$normalizedKey] = $normalizedValue;
            }
        }

        ksort($normalized);
        $this->intakeAnswers = $normalized;

        return $this;
    }

    /**
     * @return array<string, bool>
     */
    public function getChecklistProgress(): array
    {
        $this->syncChecklistProgress();

        return $this->checklistProgress;
    }

    /**
     * @param array<string, bool> $checklistProgress
     */
    public function setChecklistProgress(array $checklistProgress): self
    {
        $normalized = [];
        foreach ($checklistProgress as $item => $completed) {
            $normalizedItem = trim((string) $item);
            if ('' === $normalizedItem) {
                continue;
            }

            $normalized[$normalizedItem] = (bool) $completed;
        }

        $this->checklistProgress = $normalized;
        $this->syncChecklistProgress();

        return $this;
    }

    public function setChecklistItemCompleted(string $item, bool $completed): self
    {
        $normalizedItem = trim($item);
        if ('' === $normalizedItem) {
            return $this;
        }

        $this->syncChecklistProgress();
        if (\array_key_exists($normalizedItem, $this->checklistProgress)) {
            $this->checklistProgress[$normalizedItem] = $completed;
        }

        return $this;
    }

    public function getChecklistCompletionCount(): int
    {
        return \count(array_filter($this->getChecklistProgress()));
    }

    public function getChecklistItemCount(): int
    {
        return \count($this->getChecklistProgress());
    }

    public function getRequester(): ?User
    {
        return $this->requester;
    }

    public function setRequester(?User $requester): self
    {
        $this->requester = $requester;

        return $this;
    }

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): self
    {
        $this->assignee = $assignee;

        return $this;
    }

    public function getAssignedTeam(): ?TechnicianTeam
    {
        return $this->assignedTeam;
    }

    public function setAssignedTeam(?TechnicianTeam $assignedTeam): self
    {
        $this->assignedTeam = $assignedTeam;

        return $this;
    }

    public function getSlaPolicy(): ?SlaPolicy
    {
        return $this->slaPolicy;
    }

    public function setSlaPolicy(?SlaPolicy $slaPolicy): self
    {
        $this->slaPolicy = $slaPolicy;

        return $this;
    }

    public function getFirstResponseDueAt(): ?\DateTimeImmutable
    {
        if (!$this->slaPolicy instanceof SlaPolicy) {
            return null;
        }

        return $this->createdAt->modify(sprintf('+%d hours', $this->slaPolicy->getFirstResponseHours()));
    }

    public function getResolutionDueAt(): ?\DateTimeImmutable
    {
        if (!$this->slaPolicy instanceof SlaPolicy) {
            return null;
        }

        return $this->createdAt->modify(sprintf('+%d hours', $this->slaPolicy->getResolutionHours()));
    }

    public function hasFirstResponse(): bool
    {
        foreach ($this->comments as $comment) {
            if (
                !$comment->isInternal()
                && [] !== array_intersect($comment->getAuthor()->getRoles(), ['ROLE_TECHNICIAN', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'])
            ) {
                return true;
            }
        }

        return false;
    }

    public function isFirstResponseSlaBreached(): bool
    {
        $dueAt = $this->getFirstResponseDueAt();
        if (null === $dueAt || $this->hasFirstResponse()) {
            return false;
        }

        return $dueAt < new \DateTimeImmutable();
    }

    public function isResolutionSlaBreached(): bool
    {
        $dueAt = $this->getResolutionDueAt();
        if (null === $dueAt || \in_array($this->status, [TicketStatus::RESOLVED, TicketStatus::CLOSED], true)) {
            return false;
        }

        return $dueAt < new \DateTimeImmutable();
    }

    public function addComment(TicketComment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setTicket($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, TicketAuditLog>
     */
    public function getAuditLogs(): Collection
    {
        return $this->auditLogs;
    }

    public function addAuditLog(TicketAuditLog $auditLog): self
    {
        if (!$this->auditLogs->contains($auditLog)) {
            $this->auditLogs->add($auditLog);
        }

        return $this;
    }

    public function getExternalImport(): ?ExternalTicketImport
    {
        return $this->externalImport;
    }

    public function setExternalImport(?ExternalTicketImport $externalImport): self
    {
        $this->externalImport = $externalImport;

        return $this;
    }

    private function syncChecklistProgress(): void
    {
        $templateItems = $this->intakeTemplate?->getChecklistItems() ?? [];
        if ([] === $templateItems) {
            $this->checklistProgress = [];

            return;
        }

        $normalized = [];
        foreach ($templateItems as $item) {
            $normalizedItem = trim($item);
            if ('' === $normalizedItem) {
                continue;
            }

            $normalized[$normalizedItem] = (bool) ($this->checklistProgress[$normalizedItem] ?? false);
        }

        $this->checklistProgress = $normalized;
    }
}
