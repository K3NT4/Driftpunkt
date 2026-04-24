<?php

declare(strict_types=1);

namespace App\Module\Identity\Entity;

use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'companies')]
#[ORM\HasLifecycleCallbacks]
class Company
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
    private ?string $primaryEmail = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $allowSharedTickets = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $allowParentCompanyAccessToSharedTickets = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $useCustomTicketSequence = false;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $ticketReferencePrefix = null;

    #[ORM\Column(options: ['default' => 1001])]
    private int $ticketSequenceNextNumber = 1001;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_company_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?self $parentCompany = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(mappedBy: 'company', targetEntity: User::class)]
    private Collection $users;

    public function __construct(string $name)
    {
        $this->publicId = Uuid::v7();
        $this->name = $name;
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPrimaryEmail(): ?string
    {
        return $this->primaryEmail;
    }

    public function setPrimaryEmail(?string $primaryEmail): self
    {
        $this->primaryEmail = $primaryEmail;

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

    public function allowsSharedTickets(): bool
    {
        return $this->allowSharedTickets;
    }

    public function setAllowSharedTickets(bool $allowSharedTickets): self
    {
        $this->allowSharedTickets = $allowSharedTickets;

        return $this;
    }

    public function allowsParentCompanyAccessToSharedTickets(): bool
    {
        return $this->allowParentCompanyAccessToSharedTickets;
    }

    public function setAllowParentCompanyAccessToSharedTickets(bool $allowParentCompanyAccessToSharedTickets): self
    {
        $this->allowParentCompanyAccessToSharedTickets = $allowParentCompanyAccessToSharedTickets;

        return $this;
    }

    public function usesCustomTicketSequence(): bool
    {
        return $this->useCustomTicketSequence;
    }

    public function setUseCustomTicketSequence(bool $useCustomTicketSequence): self
    {
        $this->useCustomTicketSequence = $useCustomTicketSequence;

        return $this;
    }

    public function getTicketReferencePrefix(): ?string
    {
        return $this->ticketReferencePrefix;
    }

    public function setTicketReferencePrefix(?string $ticketReferencePrefix): self
    {
        $normalized = null !== $ticketReferencePrefix ? strtoupper(trim($ticketReferencePrefix)) : null;
        $this->ticketReferencePrefix = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getTicketSequenceNextNumber(): int
    {
        return max(1, $this->ticketSequenceNextNumber);
    }

    public function setTicketSequenceNextNumber(int $ticketSequenceNextNumber): self
    {
        $this->ticketSequenceNextNumber = max(1, $ticketSequenceNextNumber);

        return $this;
    }

    public function reserveNextTicketNumber(): int
    {
        $nextNumber = $this->getTicketSequenceNextNumber();
        $this->ticketSequenceNextNumber = $nextNumber + 1;

        return $nextNumber;
    }

    public function getParentCompany(): ?self
    {
        return $this->parentCompany;
    }

    public function setParentCompany(?self $parentCompany): self
    {
        if ($this->parentCompany === $parentCompany) {
            return $this;
        }

        if ($parentCompany === $this) {
            throw new \InvalidArgumentException('Ett företag kan inte vara sitt eget moderbolag.');
        }

        if ($parentCompany?->isDescendantOf($this)) {
            throw new \InvalidArgumentException('Ett företag kan inte flyttas under sitt eget dotterbolag.');
        }

        $this->parentCompany = $parentCompany;

        return $this;
    }

    public function isDescendantOf(self $company): bool
    {
        $parent = $this->parentCompany;
        while ($parent instanceof self) {
            if ($parent === $company) {
                return true;
            }

            $parent = $parent->getParentCompany();
        }

        return false;
    }

    public function getHierarchyDepth(): int
    {
        $depth = 0;
        $parent = $this->parentCompany;

        while ($parent instanceof self) {
            ++$depth;
            $parent = $parent->getParentCompany();
        }

        return $depth;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }
}
