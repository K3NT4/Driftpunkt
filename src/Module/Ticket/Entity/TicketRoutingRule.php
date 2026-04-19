<?php

declare(strict_types=1);

namespace App\Module\Ticket\Entity;

use App\Module\Identity\Entity\TechnicianTeam;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Shared\Entity\TimestampableTrait;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ticket_routing_rules')]
#[ORM\HasLifecycleCallbacks]
class TicketRoutingRule
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TechnicianTeam $team;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?TicketCategory $category = null;

    #[ORM\Column(enumType: UserType::class, nullable: true)]
    private ?UserType $customerType = null;

    #[ORM\Column(enumType: TicketRequestType::class, nullable: true)]
    private ?TicketRequestType $requestType = null;

    #[ORM\Column(enumType: TicketImpactLevel::class, nullable: true)]
    private ?TicketImpactLevel $impactLevel = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $intakeFieldKey = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $intakeFieldValue = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $intakeTemplateFamily = null;

    #[ORM\Column(enumType: TicketPriority::class, nullable: true)]
    private ?TicketPriority $defaultPriority = null;

    #[ORM\Column(enumType: TicketEscalationLevel::class, nullable: true)]
    private ?TicketEscalationLevel $defaultEscalationLevel = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?SlaPolicy $defaultSlaPolicy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?User $defaultAssignee = null;

    #[ORM\Column]
    private int $sortOrder = 100;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(string $name, TechnicianTeam $team)
    {
        $this->name = trim($name);
        $this->team = $team;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getTeam(): TechnicianTeam
    {
        return $this->team;
    }

    public function setTeam(TechnicianTeam $team): self
    {
        $this->team = $team;

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

    public function getCustomerType(): ?UserType
    {
        return $this->customerType;
    }

    public function setCustomerType(?UserType $customerType): self
    {
        $this->customerType = $customerType;

        return $this;
    }

    public function getDefaultSlaPolicy(): ?SlaPolicy
    {
        return $this->defaultSlaPolicy;
    }

    public function getRequestType(): ?TicketRequestType
    {
        return $this->requestType;
    }

    public function setRequestType(?TicketRequestType $requestType): self
    {
        $this->requestType = $requestType;

        return $this;
    }

    public function getImpactLevel(): ?TicketImpactLevel
    {
        return $this->impactLevel;
    }

    public function setImpactLevel(?TicketImpactLevel $impactLevel): self
    {
        $this->impactLevel = $impactLevel;

        return $this;
    }

    public function getIntakeFieldKey(): ?string
    {
        return $this->intakeFieldKey;
    }

    public function setIntakeFieldKey(?string $intakeFieldKey): self
    {
        $normalized = null !== $intakeFieldKey ? TicketIntakeField::normalizeFieldKey($intakeFieldKey) : null;
        $this->intakeFieldKey = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getIntakeFieldValue(): ?string
    {
        return $this->intakeFieldValue;
    }

    public function setIntakeFieldValue(?string $intakeFieldValue): self
    {
        $this->intakeFieldValue = null !== $intakeFieldValue && '' !== trim($intakeFieldValue) ? trim($intakeFieldValue) : null;

        return $this;
    }

    public function getIntakeTemplateFamily(): ?string
    {
        return $this->intakeTemplateFamily;
    }

    public function setIntakeTemplateFamily(?string $intakeTemplateFamily): self
    {
        $this->intakeTemplateFamily = null !== $intakeTemplateFamily && '' !== trim($intakeTemplateFamily) ? trim($intakeTemplateFamily) : null;

        return $this;
    }

    public function getDefaultPriority(): ?TicketPriority
    {
        return $this->defaultPriority;
    }

    public function setDefaultPriority(?TicketPriority $defaultPriority): self
    {
        $this->defaultPriority = $defaultPriority;

        return $this;
    }

    public function getDefaultEscalationLevel(): ?TicketEscalationLevel
    {
        return $this->defaultEscalationLevel;
    }

    public function setDefaultEscalationLevel(?TicketEscalationLevel $defaultEscalationLevel): self
    {
        $this->defaultEscalationLevel = $defaultEscalationLevel;

        return $this;
    }

    public function setDefaultSlaPolicy(?SlaPolicy $defaultSlaPolicy): self
    {
        $this->defaultSlaPolicy = $defaultSlaPolicy;

        return $this;
    }

    public function getDefaultAssignee(): ?User
    {
        return $this->defaultAssignee;
    }

    public function setDefaultAssignee(?User $defaultAssignee): self
    {
        $this->defaultAssignee = $defaultAssignee;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = max(0, $sortOrder);

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function activate(): self
    {
        $this->isActive = true;

        return $this;
    }

    public function deactivate(): self
    {
        $this->isActive = false;

        return $this;
    }
}
