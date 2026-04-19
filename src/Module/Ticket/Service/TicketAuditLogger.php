<?php

declare(strict_types=1);

namespace App\Module\Ticket\Service;

use App\Module\Identity\Entity\User;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketAuditLog;
use Doctrine\ORM\EntityManagerInterface;

final class TicketAuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function log(Ticket $ticket, string $action, string $message, ?User $actor = null): void
    {
        $log = new TicketAuditLog($ticket, $action, $message, $actor);
        $ticket->addAuditLog($log);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
