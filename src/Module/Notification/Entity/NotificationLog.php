<?php

declare(strict_types=1);

namespace App\Module\Notification\Entity;

use App\Module\Identity\Entity\User;
use App\Module\Shared\Entity\TimestampableTrait;
use App\Module\Ticket\Entity\Ticket;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_logs')]
#[ORM\HasLifecycleCallbacks]
class NotificationLog
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $eventType;

    #[ORM\Column(length: 180)]
    private string $recipientEmail;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column]
    private bool $sent;

    #[ORM\Column(length: 255)]
    private string $statusMessage;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?User $recipient = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?Ticket $ticket = null;

    public function __construct(
        string $eventType,
        string $recipientEmail,
        string $subject,
        bool $sent,
        string $statusMessage,
    ) {
        $this->eventType = $eventType;
        $this->recipientEmail = mb_strtolower(trim($recipientEmail));
        $this->subject = trim($subject);
        $this->sent = $sent;
        $this->statusMessage = trim($statusMessage);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function isSent(): bool
    {
        return $this->sent;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
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
}
