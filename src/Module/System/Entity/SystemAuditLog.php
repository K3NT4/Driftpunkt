<?php

declare(strict_types=1);

namespace App\Module\System\Entity;

use App\Module\Identity\Entity\User;
use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'system_audit_logs')]
#[ORM\HasLifecycleCallbacks]
class SystemAuditLog
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?User $actor = null;

    #[ORM\Column(length: 180)]
    private string $actorEmail;

    #[ORM\Column(length: 100)]
    private string $action;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $message;

    public function __construct(?User $actor, string $action, string $title, string $message)
    {
        $this->actor = $actor;
        $this->actorEmail = $actor instanceof User ? $actor->getEmail() : 'system';
        $this->action = trim($action);
        $this->title = trim($title);
        $this->message = trim($message);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getActorEmail(): string
    {
        return $this->actorEmail;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
