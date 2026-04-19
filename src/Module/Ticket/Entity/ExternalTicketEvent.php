<?php

declare(strict_types=1);

namespace App\Module\Ticket\Entity;

use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'external_ticket_events')]
#[ORM\HasLifecycleCallbacks]
class ExternalTicketEvent
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ExternalTicketImport $ticketImport;

    #[ORM\Column(length: 80)]
    private string $eventType;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actorName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actorEmail = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column]
    private int $sortOrder = 0;

    /**
     * @var array<string, string>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $metadata = [];

    public function __construct(string $eventType, string $title, \DateTimeImmutable $occurredAt)
    {
        $this->eventType = trim($eventType);
        $this->title = trim($title);
        $this->occurredAt = $occurredAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicketImport(): ExternalTicketImport
    {
        return $this->ticketImport;
    }

    public function setTicketImport(ExternalTicketImport $ticketImport): self
    {
        $this->ticketImport = $ticketImport;

        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): self
    {
        $normalized = null !== $body ? trim($body) : null;
        $this->body = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getActorName(): ?string
    {
        return $this->actorName;
    }

    public function setActorName(?string $actorName): self
    {
        $normalized = null !== $actorName ? trim($actorName) : null;
        $this->actorName = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function setActorEmail(?string $actorEmail): self
    {
        $normalized = null !== $actorEmail ? mb_strtolower(trim($actorEmail)) : null;
        $this->actorEmail = '' !== (string) $normalized ? $normalized : null;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

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
}
