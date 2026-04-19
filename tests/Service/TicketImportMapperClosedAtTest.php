<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Service\CsvTicketImportMapper;
use App\Module\Ticket\Service\ExternalTicketImportMapper;
use PHPUnit\Framework\TestCase;

final class TicketImportMapperClosedAtTest extends TestCase
{
    public function testCsvImportPreservesClosedAtForClosedTickets(): void
    {
        $ticket = new Ticket('DP-2', '', '', TicketStatus::CLOSED);
        $mapper = new CsvTicketImportMapper();

        $mapper->mapToTicketImport($ticket, [
            'headers' => ['subject', 'summary', 'closed_at'],
            'rows' => [[
                'subject' => 'Importerat ärende',
                'summary' => 'Importerat från csv',
                'closed_at' => '2026-04-18 09:30:00',
            ]],
            'fieldMapping' => [
                'subject' => 'subject',
                'summary' => 'summary',
                'closed_at' => 'closed_at',
            ],
            'rowTargets' => ['0' => 'ticket'],
        ]);

        self::assertNotNull($ticket->getClosedAt());
        self::assertSame('2026-04-18 09:30', $ticket->getClosedAt()?->format('Y-m-d H:i'));
    }

    public function testJsonImportPreservesClosedAtForClosedTickets(): void
    {
        $ticket = new Ticket('DP-3', '', '', TicketStatus::CLOSED);
        $mapper = new ExternalTicketImportMapper();

        $mapper->mapToTicketImport($ticket, json_encode([
            'subject' => 'Importerat json-ärende',
            'summary' => 'Importerat från json',
            'closedAt' => '2026-04-17T14:45:00+00:00',
        ], \JSON_THROW_ON_ERROR));

        self::assertNotNull($ticket->getClosedAt());
        self::assertSame('2026-04-17 14:45', $ticket->getClosedAt()?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i'));
    }
}
