<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketStatus;
use PHPUnit\Framework\TestCase;

final class TicketClosedAtTest extends TestCase
{
    public function testClosedAtIsSetWhenTicketIsClosedAndClearedWhenReopened(): void
    {
        $ticket = new Ticket('DP-1', 'Test', 'Summary', TicketStatus::OPEN);

        self::assertNull($ticket->getClosedAt());

        $ticket->setStatus(TicketStatus::CLOSED);
        $firstClosedAt = $ticket->getClosedAt();

        self::assertNotNull($firstClosedAt);

        $ticket->setStatus(TicketStatus::OPEN);
        self::assertNull($ticket->getClosedAt());

        $ticket->setStatus(TicketStatus::CLOSED);
        $secondClosedAt = $ticket->getClosedAt();

        self::assertNotNull($secondClosedAt);
        self::assertNotEquals($firstClosedAt, $secondClosedAt);
    }
}
