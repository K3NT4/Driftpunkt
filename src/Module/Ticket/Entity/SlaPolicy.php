<?php

declare(strict_types=1);

namespace App\Module\Ticket\Entity;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Entity\TechnicianTeam;
use App\Module\Shared\Entity\TimestampableTrait;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketPriority;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sla_policies')]
#[ORM\HasLifecycleCallbacks]
class SlaPolicy
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $publicId;

    #[ORM\Column(length: 180, unique: true)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $firstResponseHours;

    #[ORM\Column]
    private int $resolutionHours;

    #[ORM\Column(nullable: true)]
    private ?int $firstResponseWarningHours = null;

    #[ORM\Column(nullable: true)]
    private ?int $resolutionWarningHours = null;

    #[ORM\Column]
    private bool $defaultPriorityEnabled = false;

    #[ORM\Column(enumType: TicketPriority::class, nullable: true)]
    private ?TicketPriority $defaultPriority = null;

    #[ORM\Column]
    private bool $defaultAssigneeEnabled = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?User $defaultAssignee = null;

    #[ORM\Column]
    private bool $defaultEscalationEnabled = false;

    #[ORM\Column(enumType: TicketEscalationLevel::class, nullable: true)]
    private ?TicketEscalationLevel $defaultEscalationLevel = null;

    #[ORM\Column]
    private bool $defaultTeamEnabled = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?TechnicianTeam $defaultTeam = null;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(string $name, int $firstResponseHours, int $resolutionHours)
    {
        $this->publicId = Uuid::v7();
        $this->name = trim($name);
        $this->firstResponseHours = $firstResponseHours;
        $this->resolutionHours = $resolutionHours;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = null !== $description && '' !== trim($description) ? trim($description) : null;

        return $this;
    }

    public function getFirstResponseHours(): int
    {
        return $this->firstResponseHours;
    }

    public function setFirstResponseHours(int $firstResponseHours): self
    {
        $this->firstResponseHours = $firstResponseHours;

        return $this;
    }

    public function getResolutionHours(): int
    {
        return $this->resolutionHours;
    }

    public function setResolutionHours(int $resolutionHours): self
    {
        $this->resolutionHours = $resolutionHours;

        return $this;
    }

    public function getFirstResponseWarningHours(): ?int
    {
        return $this->firstResponseWarningHours;
    }

    public function setFirstResponseWarningHours(?int $firstResponseWarningHours): self
    {
        $this->firstResponseWarningHours = null !== $firstResponseWarningHours ? max(1, $firstResponseWarningHours) : null;

        return $this;
    }

    public function getResolutionWarningHours(): ?int
    {
        return $this->resolutionWarningHours;
    }

    public function setResolutionWarningHours(?int $resolutionWarningHours): self
    {
        $this->resolutionWarningHours = null !== $resolutionWarningHours ? max(1, $resolutionWarningHours) : null;

        return $this;
    }

    public function getEffectiveFirstResponseWarningHours(int $default): int
    {
        return $this->firstResponseWarningHours ?? max(1, $default);
    }

    public function getEffectiveResolutionWarningHours(int $default): int
    {
        return $this->resolutionWarningHours ?? max(1, $default);
    }

    public function isDefaultPriorityEnabled(): bool
    {
        return $this->defaultPriorityEnabled;
    }

    public function setDefaultPriorityEnabled(bool $defaultPriorityEnabled): self
    {
        $this->defaultPriorityEnabled = $defaultPriorityEnabled;

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

    public function getEffectiveDefaultPriority(): ?TicketPriority
    {
        if (!$this->defaultPriorityEnabled) {
            return null;
        }

        return $this->defaultPriority;
    }

    public function isDefaultAssigneeEnabled(): bool
    {
        return $this->defaultAssigneeEnabled;
    }

    public function setDefaultAssigneeEnabled(bool $defaultAssigneeEnabled): self
    {
        $this->defaultAssigneeEnabled = $defaultAssigneeEnabled;

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

    public function getEffectiveDefaultAssignee(): ?User
    {
        if (!$this->defaultAssigneeEnabled) {
            return null;
        }

        return $this->defaultAssignee;
    }

    public function isDefaultEscalationEnabled(): bool
    {
        return $this->defaultEscalationEnabled;
    }

    public function setDefaultEscalationEnabled(bool $defaultEscalationEnabled): self
    {
        $this->defaultEscalationEnabled = $defaultEscalationEnabled;

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

    public function getEffectiveDefaultEscalationLevel(): ?TicketEscalationLevel
    {
        if (!$this->defaultEscalationEnabled) {
            return null;
        }

        return $this->defaultEscalationLevel;
    }

    public function isDefaultTeamEnabled(): bool
    {
        return $this->defaultTeamEnabled;
    }

    public function setDefaultTeamEnabled(bool $defaultTeamEnabled): self
    {
        $this->defaultTeamEnabled = $defaultTeamEnabled;

        return $this;
    }

    public function getDefaultTeam(): ?TechnicianTeam
    {
        return $this->defaultTeam;
    }

    public function setDefaultTeam(?TechnicianTeam $defaultTeam): self
    {
        $this->defaultTeam = $defaultTeam;

        return $this;
    }

    public function getEffectiveDefaultTeam(): ?TechnicianTeam
    {
        if (!$this->defaultTeamEnabled) {
            return null;
        }

        return $this->defaultTeam;
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
