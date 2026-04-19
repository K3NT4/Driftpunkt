<?php

declare(strict_types=1);

namespace App\Module\Ticket\Entity;

use App\Module\Identity\Entity\User;
use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ticket_audit_logs')]
#[ORM\HasLifecycleCallbacks]
class TicketAuditLog
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'auditLogs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Ticket $ticket;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?User $actor = null;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\Column(length: 255)]
    private string $message;

    public function __construct(Ticket $ticket, string $action, string $message, ?User $actor = null)
    {
        $this->ticket = $ticket;
        $this->action = trim($action);
        $this->message = trim($message);
        $this->actor = $actor;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicket(): Ticket
    {
        return $this->ticket;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
