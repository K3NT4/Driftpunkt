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

    public function testCsvImportCreatesSeparateResolutionEventFromActionColumn(): void
    {
        $ticket = new Ticket('DP-4', '', '');
        $mapper = new CsvTicketImportMapper();

        $result = $mapper->mapToTicketImport($ticket, [
            'headers' => ['summary', 'event_body', 'resolution_body', 'event_date'],
            'rows' => [
                [
                    'summary' => 'Importerat ärende',
                    'event_body' => '',
                    'resolution_body' => '',
                    'event_date' => '',
                ],
                [
                    'summary' => '',
                    'event_body' => 'Kunden återkopplade med extra info.',
                    'resolution_body' => 'Rensade cache och startade om klienten.',
                    'event_date' => '2026-04-18 09:30:00',
                ],
            ],
            'fieldMapping' => [
                'summary' => 'summary',
                'event_body' => 'event_body',
                'resolution_body' => 'resolution_body',
                'event_date' => 'event_date',
            ],
            'rowTargets' => ['0' => 'ticket', '1' => 'history'],
        ]);

        $events = $result['import']->getEvents()->toArray();

        self::assertCount(2, $events);
        self::assertSame('history', $events[0]->getEventType());
        self::assertSame('Kunden återkopplade med extra info.', $events[0]->getBody());
        self::assertSame('resolution', $events[1]->getEventType());
        self::assertSame('Lösning', $events[1]->getTitle());
        self::assertSame('Rensade cache och startade om klienten.', $events[1]->getBody());
        self::assertNull($ticket->getResolutionSummary());
    }

    public function testCsvImportStoresResolutionSummaryFromTicketRow(): void
    {
        $ticket = new Ticket('DP-5', '', '', TicketStatus::CLOSED);
        $mapper = new CsvTicketImportMapper();

        $mapper->mapToTicketImport($ticket, [
            'headers' => ['subject', 'summary', 'resolution_body', 'closed_at'],
            'rows' => [[
                'subject' => 'Importerat ärende',
                'summary' => 'Importerat från csv',
                'resolution_body' => 'Åtgärdade problemet genom att återställa skrivarkön.',
                'closed_at' => '2026-04-18 09:30:00',
            ]],
            'fieldMapping' => [
                'subject' => 'subject',
                'summary' => 'summary',
                'resolution_body' => 'resolution_body',
                'closed_at' => 'closed_at',
            ],
            'rowTargets' => ['0' => 'ticket'],
        ]);

        self::assertSame('Åtgärdade problemet genom att återställa skrivarkön.', $ticket->getResolutionSummary());
    }

    public function testCsvImportExtractsRequesterAndAssigneeNamesFromSharepointColumns(): void
    {
        $ticket = new Ticket('DP-6', '', '');
        $mapper = new CsvTicketImportMapper();

        $result = $mapper->mapToTicketImport($ticket, [
            'headers' => ['Ärende ID', 'Namn', 'Ansvarig Tekniker', 'Beskrivning av problem'],
            'rows' => [[
                'Ärende ID' => '2',
                'Namn' => 'Gisle',
                'Ansvarig Tekniker' => 'Paul',
                'Beskrivning av problem' => 'Minne under Excel.',
            ]],
            'fieldMapping' => [
                'reference' => 'Ärende ID',
                'requester_name' => 'Namn',
                'assignee_name' => 'Ansvarig Tekniker',
                'summary' => 'Beskrivning av problem',
            ],
            'rowTargets' => ['0' => 'ticket'],
        ], 'sharepoint');

        self::assertSame('Gisle', $result['importedPeople']['requester']['displayName'] ?? null);
        self::assertSame('Paul', $result['importedPeople']['assignee']['displayName'] ?? null);
    }
}
