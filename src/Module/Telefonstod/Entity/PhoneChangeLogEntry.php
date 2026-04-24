<?php

declare(strict_types=1);

namespace App\Module\Telefonstod\Entity;

use App\Module\Ticket\Entity\Ticket;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'telefonstod_change_log')]
#[ORM\HasLifecycleCallbacks]
class PhoneChangeLogEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PhoneCustomerProfile::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE', nullable: false)]
    private PhoneCustomerProfile $customerProfile;

    #[ORM\ManyToOne(targetEntity: Ticket::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?Ticket $ticket = null;

    #[ORM\Column(length: 64)]
    private string $objectType;

    #[ORM\Column(length: 180)]
    private string $objectLabel;

    #[ORM\Column(length: 120)]
    private string $fieldName;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $oldValue = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $newValue = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(length: 180)]
    private string $changedBy;

    #[ORM\Column]
    private \DateTimeImmutable $changedAt;

    public function __construct(
        PhoneCustomerProfile $customerProfile,
        string $objectType,
        string $objectLabel,
        string $fieldName,
        string $changedBy,
    ) {
        $this->customerProfile = $customerProfile;
        $this->objectType = trim($objectType);
        $this->objectLabel = trim($objectLabel);
        $this->fieldName = trim($fieldName);
        $this->changedBy = trim($changedBy);
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerProfile(): PhoneCustomerProfile
    {
        return $this->customerProfile;
    }

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(?Ticket $ticket): self
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function getObjectLabel(): string
    {
        return $this->objectLabel;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function setOldValue(?string $oldValue): self
    {
        $normalized = null !== $oldValue ? trim($oldValue) : null;
        $this->oldValue = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function setNewValue(?string $newValue): self
    {
        $normalized = null !== $newValue ? trim($newValue) : null;
        $this->newValue = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $normalized = null !== $comment ? trim($comment) : null;
        $this->comment = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getChangedBy(): string
    {
        return $this->changedBy;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function setChangedAt(\DateTimeImmutable $changedAt): self
    {
        $this->changedAt = $changedAt;

        return $this;
    }
}
