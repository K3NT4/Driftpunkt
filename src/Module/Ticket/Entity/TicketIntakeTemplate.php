<?php

declare(strict_types=1);

namespace App\Module\Ticket\Entity;

use App\Module\Identity\Entity\TechnicianTeam;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Shared\Entity\TimestampableTrait;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'ticket_intake_templates')]
#[ORM\HasLifecycleCallbacks]
class TicketIntakeTemplate
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $name;

    #[ORM\Column(length: 36)]
    private string $versionFamily;

    #[ORM\Column(options: ['default' => 1])]
    private int $versionNumber = 1;

    #[ORM\Column(options: ['default' => true])]
    private bool $isCurrentVersion = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $playbookText = null;

    #[ORM\Column(enumType: TicketRequestType::class)]
    private TicketRequestType $requestType;

    #[ORM\ManyToOne(targetEntity: TicketCategory::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?TicketCategory $category = null;

    #[ORM\Column(enumType: UserType::class, nullable: true)]
    private ?UserType $customerType = null;

    #[ORM\ManyToOne(targetEntity: SlaPolicy::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?SlaPolicy $defaultSlaPolicy = null;

    #[ORM\Column(enumType: TicketPriority::class, nullable: true)]
    private ?TicketPriority $defaultPriority = null;

    #[ORM\ManyToOne(targetEntity: TechnicianTeam::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?TechnicianTeam $defaultTeam = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?User $defaultAssignee = null;

    #[ORM\Column(enumType: TicketEscalationLevel::class, nullable: true)]
    private ?TicketEscalationLevel $defaultEscalationLevel = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $checklistItems = [];

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(string $name, TicketRequestType $requestType)
    {
        $this->name = trim($name);
        $this->requestType = $requestType;
        $this->versionFamily = Uuid::v7()->toRfc4122();
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

    public function getVersionFamily(): string
    {
        return $this->versionFamily;
    }

    public function setVersionFamily(string $versionFamily): self
    {
        $this->versionFamily = trim($versionFamily);

        return $this;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(int $versionNumber): self
    {
        $this->versionNumber = max(1, $versionNumber);

        return $this;
    }

    public function isCurrentVersion(): bool
    {
        return $this->isCurrentVersion;
    }

    public function markAsCurrentVersion(): self
    {
        $this->isCurrentVersion = true;

        return $this;
    }

    public function retireCurrentVersion(): self
    {
        $this->isCurrentVersion = false;

        return $this;
    }

    public function getVersionLabel(): string
    {
        return sprintf('v%d', $this->versionNumber);
    }

    public function setDescription(?string $description): self
    {
        $this->description = null !== $description && '' !== trim($description) ? trim($description) : null;

        return $this;
    }

    public function getPlaybookText(): ?string
    {
        return $this->playbookText;
    }

    public function setPlaybookText(?string $playbookText): self
    {
        $this->playbookText = null !== $playbookText && '' !== trim($playbookText) ? trim($playbookText) : null;

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

    public function setDefaultSlaPolicy(?SlaPolicy $defaultSlaPolicy): self
    {
        $this->defaultSlaPolicy = $defaultSlaPolicy;

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

    public function getDefaultTeam(): ?TechnicianTeam
    {
        return $this->defaultTeam;
    }

    public function setDefaultTeam(?TechnicianTeam $defaultTeam): self
    {
        $this->defaultTeam = $defaultTeam;

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

    public function getDefaultEscalationLevel(): ?TicketEscalationLevel
    {
        return $this->defaultEscalationLevel;
    }

    public function setDefaultEscalationLevel(?TicketEscalationLevel $defaultEscalationLevel): self
    {
        $this->defaultEscalationLevel = $defaultEscalationLevel;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getChecklistItems(): array
    {
        return $this->checklistItems;
    }

    /**
     * @param list<string> $checklistItems
     */
    public function setChecklistItems(array $checklistItems): self
    {
        $normalized = [];
        foreach ($checklistItems as $item) {
            $value = trim((string) $item);
            if ('' !== $value) {
                $normalized[] = $value;
            }
        }

        $this->checklistItems = array_values(array_unique($normalized));

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
