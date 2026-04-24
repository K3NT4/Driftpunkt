<?php

declare(strict_types=1);

namespace App\Module\Telefonstod\Entity;

use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'telefonstod_phone_numbers')]
#[ORM\UniqueConstraint(name: 'uniq_telefonstod_phone_numbers_number', columns: ['phone_number'])]
#[ORM\HasLifecycleCallbacks]
class PhoneNumberRecord
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PhoneCustomerProfile::class, inversedBy: 'phoneNumbers')]
    #[ORM\JoinColumn(onDelete: 'CASCADE', nullable: false)]
    private PhoneCustomerProfile $customerProfile;

    #[ORM\Column(length: 64)]
    private string $phoneNumber;

    #[ORM\Column(length: 32)]
    private string $numberType;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $extensionNumber = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(length: 32)]
    private string $status = 'aktiv';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $queueName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastChangedAt = null;

    public function __construct(PhoneCustomerProfile $customerProfile, string $phoneNumber, string $numberType)
    {
        $this->customerProfile = $customerProfile;
        $this->phoneNumber = trim($phoneNumber);
        $this->numberType = trim($numberType);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerProfile(): PhoneCustomerProfile
    {
        return $this->customerProfile;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): self
    {
        $this->phoneNumber = trim($phoneNumber);

        return $this;
    }

    public function getNumberType(): string
    {
        return $this->numberType;
    }

    public function setNumberType(string $numberType): self
    {
        $this->numberType = trim($numberType);

        return $this;
    }

    public function getExtensionNumber(): ?string
    {
        return $this->extensionNumber;
    }

    public function setExtensionNumber(?string $extensionNumber): self
    {
        $normalized = null !== $extensionNumber ? trim($extensionNumber) : null;
        $this->extensionNumber = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $normalized = null !== $displayName ? trim($displayName) : null;
        $this->displayName = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = trim($status);

        return $this;
    }

    public function getQueueName(): ?string
    {
        return $this->queueName;
    }

    public function setQueueName(?string $queueName): self
    {
        $normalized = null !== $queueName ? trim($queueName) : null;
        $this->queueName = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $normalized = null !== $notes ? trim($notes) : null;
        $this->notes = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getLastChangedAt(): ?\DateTimeImmutable
    {
        return $this->lastChangedAt;
    }

    public function setLastChangedAt(?\DateTimeImmutable $lastChangedAt): self
    {
        $this->lastChangedAt = $lastChangedAt;

        return $this;
    }
}
