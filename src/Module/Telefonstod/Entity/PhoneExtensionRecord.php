<?php

declare(strict_types=1);

namespace App\Module\Telefonstod\Entity;

use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'telefonstod_extensions')]
#[ORM\UniqueConstraint(name: 'uniq_telefonstod_extensions_profile_extension', columns: ['customer_profile_id', 'extension_number'])]
#[ORM\HasLifecycleCallbacks]
class PhoneExtensionRecord
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PhoneCustomerProfile::class, inversedBy: 'extensions')]
    #[ORM\JoinColumn(onDelete: 'CASCADE', nullable: false)]
    private PhoneCustomerProfile $customerProfile;

    #[ORM\Column(length: 32)]
    private string $extensionNumber;

    #[ORM\Column(length: 180)]
    private string $displayName;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $directNumber = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $mobilePhone = null;

    #[ORM\Column(length: 32)]
    private string $status = 'aktiv';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct(PhoneCustomerProfile $customerProfile, string $extensionNumber, string $displayName)
    {
        $this->customerProfile = $customerProfile;
        $this->extensionNumber = trim($extensionNumber);
        $this->displayName = trim($displayName);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerProfile(): PhoneCustomerProfile
    {
        return $this->customerProfile;
    }

    public function getExtensionNumber(): string
    {
        return $this->extensionNumber;
    }

    public function setExtensionNumber(string $extensionNumber): self
    {
        $this->extensionNumber = trim($extensionNumber);

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = trim($displayName);

        return $this;
    }

    public function getDirectNumber(): ?string
    {
        return $this->directNumber;
    }

    public function setDirectNumber(?string $directNumber): self
    {
        $normalized = null !== $directNumber ? trim($directNumber) : null;
        $this->directNumber = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $normalized = null !== $email ? trim($email) : null;
        $this->email = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getMobilePhone(): ?string
    {
        return $this->mobilePhone;
    }

    public function setMobilePhone(?string $mobilePhone): self
    {
        $normalized = null !== $mobilePhone ? trim($mobilePhone) : null;
        $this->mobilePhone = '' !== (string) $normalized ? $normalized : null;

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
}
