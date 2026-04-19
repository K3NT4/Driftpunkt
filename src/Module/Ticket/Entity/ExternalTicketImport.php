<?php

declare(strict_types=1);

namespace App\Module\Ticket\Entity;

use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'external_ticket_imports')]
#[ORM\HasLifecycleCallbacks]
class ExternalTicketImport
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'externalImport')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;

    #[ORM\Column(length: 64)]
    private string $sourceSystem;

    #[ORM\Column(length: 180)]
    private string $sourceLabel;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $sourceReference = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $requesterName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $requesterEmail = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $statusLabel = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $priorityLabel = null;

    /**
     * @var array<mixed>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $rawPayload = [];

    /**
     * @var array<string, string>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $metadata = [];

    /**
     * @var Collection<int, ExternalTicketEvent>
     */
    #[ORM\OneToMany(mappedBy: 'ticketImport', targetEntity: ExternalTicketEvent::class, orphanRemoval: true, cascade: ['persist'])]
    #[ORM\OrderBy(['occurredAt' => 'ASC', 'sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $events;

    public function __construct(Ticket $ticket, string $sourceSystem, string $sourceLabel)
    {
        $this->ticket = $ticket;
        $this->sourceSystem = trim($sourceSystem);
        $this->sourceLabel = trim($sourceLabel);
        $this->events = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): Ticket
    {
        return $this->ticket;
    }

    public function getSourceSystem(): string
    {
        return $this->sourceSystem;
    }

    public function getSourceLabel(): string
    {
        return $this->sourceLabel;
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

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): self
    {
        $normalized = null !== $sourceUrl ? trim($sourceUrl) : null;
        $this->sourceUrl = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getRequesterName(): ?string
    {
        return $this->requesterName;
    }

    public function setRequesterName(?string $requesterName): self
    {
        $normalized = null !== $requesterName ? trim($requesterName) : null;
        $this->requesterName = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getRequesterEmail(): ?string
    {
        return $this->requesterEmail;
    }

    public function setRequesterEmail(?string $requesterEmail): self
    {
        $normalized = null !== $requesterEmail ? mb_strtolower(trim($requesterEmail)) : null;
        $this->requesterEmail = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getStatusLabel(): ?string
    {
        return $this->statusLabel;
    }

    public function setStatusLabel(?string $statusLabel): self
    {
        $normalized = null !== $statusLabel ? trim($statusLabel) : null;
        $this->statusLabel = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getPriorityLabel(): ?string
    {
        return $this->priorityLabel;
    }

    public function setPriorityLabel(?string $priorityLabel): self
    {
        $normalized = null !== $priorityLabel ? trim($priorityLabel) : null;
        $this->priorityLabel = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    /**
     * @param array<mixed> $rawPayload
     */
    public function setRawPayload(array $rawPayload): self
    {
        $this->rawPayload = $rawPayload;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, string> $metadata
     */
    public function setMetadata(array $metadata): self
    {
        $normalized = [];
        foreach ($metadata as $key => $value) {
            $normalizedKey = trim((string) $key);
            $normalizedValue = trim((string) $value);
            if ('' !== $normalizedKey && '' !== $normalizedValue) {
                $normalized[$normalizedKey] = $normalizedValue;
            }
        }

        ksort($normalized);
        $this->metadata = $normalized;

        return $this;
    }

    /**
     * @return Collection<int, ExternalTicketEvent>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(ExternalTicketEvent $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setTicketImport($this);
        }

        return $this;
    }
}
