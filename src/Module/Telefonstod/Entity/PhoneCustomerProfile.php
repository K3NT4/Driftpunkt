<?php

declare(strict_types=1);

namespace App\Module\Telefonstod\Entity;

use App\Module\Identity\Entity\Company;
use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'telefonstod_customer_profiles')]
#[ORM\UniqueConstraint(name: 'uniq_telefonstod_customer_profiles_company', columns: ['company_id'])]
#[ORM\HasLifecycleCallbacks]
class PhoneCustomerProfile
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE', nullable: false)]
    private Company $company;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $wx3CustomerReference = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $mainPhoneNumber = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $solutionType = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $internalDocumentation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    /**
     * @var Collection<int, PhoneNumberRecord>
     */
    #[ORM\OneToMany(mappedBy: 'customerProfile', targetEntity: PhoneNumberRecord::class, orphanRemoval: true)]
    #[ORM\OrderBy(['updatedAt' => 'DESC', 'phoneNumber' => 'ASC'])]
    private Collection $phoneNumbers;

    /**
     * @var Collection<int, PhoneExtensionRecord>
     */
    #[ORM\OneToMany(mappedBy: 'customerProfile', targetEntity: PhoneExtensionRecord::class, orphanRemoval: true)]
    #[ORM\OrderBy(['updatedAt' => 'DESC', 'extensionNumber' => 'ASC'])]
    private Collection $extensions;

    public function __construct(Company $company)
    {
        $this->company = $company;
        $this->phoneNumbers = new ArrayCollection();
        $this->extensions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getWx3CustomerReference(): ?string
    {
        return $this->wx3CustomerReference;
    }

    public function setWx3CustomerReference(?string $wx3CustomerReference): self
    {
        $normalized = null !== $wx3CustomerReference ? trim($wx3CustomerReference) : null;
        $this->wx3CustomerReference = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getMainPhoneNumber(): ?string
    {
        return $this->mainPhoneNumber;
    }

    public function setMainPhoneNumber(?string $mainPhoneNumber): self
    {
        $normalized = null !== $mainPhoneNumber ? trim($mainPhoneNumber) : null;
        $this->mainPhoneNumber = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getSolutionType(): ?string
    {
        return $this->solutionType;
    }

    public function setSolutionType(?string $solutionType): self
    {
        $normalized = null !== $solutionType ? trim($solutionType) : null;
        $this->solutionType = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getInternalDocumentation(): ?string
    {
        return $this->internalDocumentation;
    }

    public function setInternalDocumentation(?string $internalDocumentation): self
    {
        $normalized = null !== $internalDocumentation ? trim($internalDocumentation) : null;
        $this->internalDocumentation = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function setLastSyncedAt(?\DateTimeImmutable $lastSyncedAt): self
    {
        $this->lastSyncedAt = $lastSyncedAt;

        return $this;
    }

    /**
     * @return Collection<int, PhoneNumberRecord>
     */
    public function getPhoneNumbers(): Collection
    {
        return $this->phoneNumbers;
    }

    /**
     * @return Collection<int, PhoneExtensionRecord>
     */
    public function getExtensions(): Collection
    {
        return $this->extensions;
    }
}
