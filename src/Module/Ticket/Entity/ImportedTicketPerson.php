<?php

declare(strict_types=1);

namespace App\Module\Ticket\Entity;

use App\Module\Identity\Entity\User;
use App\Module\Shared\Entity\TimestampableTrait;
use App\Module\Ticket\Enum\ImportedTicketPersonRole;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'imported_ticket_people')]
#[ORM\HasLifecycleCallbacks]
class ImportedTicketPerson
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'importedPeople')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;

    #[ORM\Column(enumType: ImportedTicketPersonRole::class)]
    private ImportedTicketPersonRole $role;

    #[ORM\Column(length: 180)]
    private string $displayName;

    #[ORM\Column(length: 64)]
    private string $sourceSystem;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $sourceReference = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?User $linkedUser = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $linkedAt = null;

    public function __construct(
        Ticket $ticket,
        ImportedTicketPersonRole $role,
        string $displayName,
        string $sourceSystem,
        ?string $sourceReference = null,
    ) {
        $this->ticket = $ticket;
        $this->role = $role;
        $this->displayName = trim($displayName);
        $this->sourceSystem = trim($sourceSystem);
        $this->setSourceReference($sourceReference);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): Ticket
    {
        return $this->ticket;
    }

    public function setTicket(Ticket $ticket): self
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getRole(): ImportedTicketPersonRole
    {
        return $this->role;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function rename(string $displayName): self
    {
        $this->displayName = trim($displayName);

        return $this;
    }

    public function getSourceSystem(): string
    {
        return $this->sourceSystem;
    }

    public function getSourceReference(): ?string
    {
        return $this->sourceReference;
    }

    public function setSourceReference(?string $sourceReference): self
    {
        $normalized = null !== $sourceReference ? trim($sourceReference) : null;
        $this->sourceReference = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getLinkedUser(): ?User
    {
        return $this->linkedUser;
    }

    public function linkToUser(User $user): self
    {
        $this->linkedUser = $user;
        $this->linkedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isLinked(): bool
    {
        return $this->linkedUser instanceof User;
    }

    public function getLinkedAt(): ?\DateTimeImmutable
    {
        return $this->linkedAt;
    }
}
