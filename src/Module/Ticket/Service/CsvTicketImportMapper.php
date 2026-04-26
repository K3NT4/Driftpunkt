<?php

declare(strict_types=1);

namespace App\Module\Ticket\Service;

use App\Module\Ticket\Entity\ExternalTicketEvent;
use App\Module\Ticket\Entity\ExternalTicketImport;
use App\Module\Ticket\Entity\Ticket;

final class CsvTicketImportMapper
{
    /**
     * @param array{
     *     headers?: list<string>,
     *     rows?: list<array<string, scalar|null>>,
     *     fieldMapping?: array<string, string>,
     *     rowTargets?: array<int|string, string>,
     *     filename?: string,
     *     delimiter?: string
     * } $payload
     *
     * @return array{
     *     subject: string,
     *     summary: string,
     *     import: ExternalTicketImport,
     *     eventCount: int,
     *     importedPeople: array<string, array{displayName: string}>
     * }
     */
    public function mapToTicketImport(
        Ticket $ticket,
        array $payload,
        string $selectedSourceSystem = 'generic',
        ?string $explicitReference = null,
        ?string $explicitUrl = null,
    ): array {
        $imports = $this->mapToTicketImports($payload, [$ticket], $selectedSourceSystem, $explicitReference, $explicitUrl);

        return $imports[0] ?? throw new \InvalidArgumentException('CSV-importen innehöll inga användbara ärenden.');
    }

    /**
     * @param list<Ticket> $tickets
     *
     * @return list<array{
     *     subject: string,
     *     summary: string,
     *     import: ExternalTicketImport,
     *     eventCount: int,
     *     importedPeople: array<string, array{displayName: string}>
     * }>
     */
    public function mapToTicketImports(
        array $payload,
        array $tickets,
        string $selectedSourceSystem = 'generic',
        ?string $explicitReference = null,
        ?string $explicitUrl = null,
    ): array {
        $headers = array_values(array_filter(
            array_map(static fn (mixed $header): string => trim((string) $header), $payload['headers'] ?? []),
            static fn (string $header): bool => '' !== $header,
        ));
        $rows = $payload['rows'] ?? [];
        $fieldMapping = $payload['fieldMapping'] ?? [];
        $rowTargets = $payload['rowTargets'] ?? [];

        if ([] === $headers || [] === $rows) {
            throw new \InvalidArgumentException('CSV-importen måste innehålla kolumner och minst en rad.');
        }

        $normalizedRows = [];
        foreach ($rows as $rowIndex => $row) {
            if (!\is_array($row)) {
                continue;
            }

            $normalizedRow = [];
            foreach ($headers as $header) {
                $normalizedRow[$header] = $this->normalizeValue($row[$header] ?? null);
            }

            if ([] !== array_filter($normalizedRow, static fn (string $value): bool => '' !== $value)) {
                $normalizedRows[$rowIndex] = $normalizedRow;
            }
        }

        if ([] === $normalizedRows) {
            throw new \InvalidArgumentException('CSV-importen innehåller inga användbara rader.');
        }

        $sourceSystem = 'auto' !== $selectedSourceSystem && '' !== trim($selectedSourceSystem) ? trim($selectedSourceSystem) : 'generic';
        $sourceLabel = $this->sourceLabel($sourceSystem);
        $ticketRowIndexes = $this->findTicketRowIndexes($normalizedRows, $rowTargets, $fieldMapping);
        $rowsByTicket = $this->groupRowsByTicket($normalizedRows, $rowTargets, $ticketRowIndexes);
        $ticketPool = array_values($tickets);
        $imports = [];

        foreach ($ticketRowIndexes as $ticketDefinitionIndex => $ticketRowIndex) {
            $ticketRow = $normalizedRows[$ticketRowIndex];
            $ticket = $ticketPool[$ticketDefinitionIndex] ?? new Ticket(
                sprintf('IMPORT-%d', $ticketDefinitionIndex + 1),
                '',
                '',
            );
            $subject = $this->mappedValue($ticketRow, $fieldMapping, 'subject');
            $summary = $this->mappedValue($ticketRow, $fieldMapping, 'summary');

            if ('' === $subject) {
                $subject = $explicitReference
                    ? sprintf('Importerat ärende %s', trim($explicitReference))
                    : sprintf('Importerat CSV-ärende från %s', $sourceLabel);
            }

            if ('' === $summary) {
                $summary = 'Ärendet importerades från CSV. Öppna den importerade historiken för tidigare rader och originalstruktur.';
            }

            $ticketRows = [];
            foreach ($rowsByTicket[$ticketRowIndex] ?? [$ticketRowIndex] as $associatedRowIndex) {
                $ticketRows[] = $normalizedRows[$associatedRowIndex];
            }

            $ticket->setClosedAt($this->resolveClosedAt($ticketRow, $fieldMapping, $ticket));
            $ticket->setResolutionSummary($this->mappedValue($ticketRow, $fieldMapping, 'resolution_body'));
            $importedPeople = $this->buildImportedPeople($ticketRow, $fieldMapping);

            $import = new ExternalTicketImport($ticket, $sourceSystem, $sourceLabel);
            $import
                ->setSourceReference($this->referenceForTicket($ticketRow, $fieldMapping, $explicitReference, $ticketDefinitionIndex))
                ->setSourceUrl($explicitUrl ?: $this->mappedValue($ticketRow, $fieldMapping, 'source_url'))
                ->setRequesterName($this->mappedValue($ticketRow, $fieldMapping, 'requester_name'))
                ->setRequesterEmail($this->mappedValue($ticketRow, $fieldMapping, 'requester_email'))
                ->setStatusLabel($this->mappedValue($ticketRow, $fieldMapping, 'status'))
                ->setPriorityLabel($this->mappedValue($ticketRow, $fieldMapping, 'priority'))
                ->setRawPayload([
                    'type' => 'csv',
                    'headers' => $headers,
                    'rows' => $ticketRows,
                    'fieldMapping' => $fieldMapping,
                    'rowTargets' => $this->rowTargetsForTicket($rowsByTicket[$ticketRowIndex] ?? [$ticketRowIndex], $rowTargets),
                    'filename' => $this->normalizeValue($payload['filename'] ?? null),
                    'delimiter' => $this->normalizeValue($payload['delimiter'] ?? null),
                ])
                ->setMetadata($this->buildMetadata($payload, $headers, $ticketRows, $fieldMapping, $this->rowTargetsForTicket($rowsByTicket[$ticketRowIndex] ?? [$ticketRowIndex], $rowTargets), $sourceLabel));

            $events = $this->buildEvents($ticketRows, $fieldMapping, $this->rowTargetsForTicket($rowsByTicket[$ticketRowIndex] ?? [$ticketRowIndex], $rowTargets));
            foreach ($events as $index => $event) {
                $event->setSortOrder($index);
                $import->addEvent($event);
            }

            $imports[] = [
                'subject' => $subject,
                'summary' => $summary,
                'import' => $import,
                'eventCount' => \count($events),
                'importedPeople' => $importedPeople,
            ];
        }

        return $imports;
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @param array<int|string, string> $rowTargets
     *
     * @return array<string, string>
     */
    private function findTicketRow(array $rows, array $rowTargets): array
    {
        foreach ($rows as $rowIndex => $row) {
            if (($rowTargets[(string) $rowIndex] ?? $rowTargets[$rowIndex] ?? '') === 'ticket') {
                return $row;
            }
        }

        return reset($rows) ?: [];
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @param array<int|string, string> $rowTargets
     * @param array<string, string> $fieldMapping
     * @return list<int>
     */
    private function findTicketRowIndexes(array $rows, array $rowTargets, array $fieldMapping): array
    {
        $indexes = [];
        foreach ($rows as $rowIndex => $row) {
            $target = $rowTargets[(string) $rowIndex] ?? $rowTargets[$rowIndex] ?? '';
            if ('ticket' === $target || ('' === $target && $this->looksLikeTicketRow($row, $fieldMapping))) {
                $indexes[] = $rowIndex;
            }
        }

        if ([] !== $indexes) {
            return $indexes;
        }

        return [array_key_first($rows) ?? 0];
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @param array<int|string, string> $rowTargets
     * @param list<int> $ticketRowIndexes
     * @return array<int, list<int>>
     */
    private function groupRowsByTicket(array $rows, array $rowTargets, array $ticketRowIndexes): array
    {
        $grouped = [];
        $currentTicketRowIndex = $ticketRowIndexes[0] ?? null;

        foreach ($rows as $rowIndex => $_row) {
            $target = $rowTargets[(string) $rowIndex] ?? $rowTargets[$rowIndex] ?? '';
            if ('ticket' === $target && \in_array($rowIndex, $ticketRowIndexes, true)) {
                $currentTicketRowIndex = $rowIndex;
                $grouped[$rowIndex] ??= [];
                $grouped[$rowIndex][] = $rowIndex;

                continue;
            }

            if ('ignore' === $target || null === $currentTicketRowIndex) {
                continue;
            }

            $grouped[$currentTicketRowIndex] ??= [$currentTicketRowIndex];
            if (!\in_array($rowIndex, $grouped[$currentTicketRowIndex], true)) {
                $grouped[$currentTicketRowIndex][] = $rowIndex;
            }
        }

        return $grouped;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, string> $fieldMapping
     */
    private function mappedValue(array $row, array $fieldMapping, string $field): string
    {
        $header = trim((string) ($fieldMapping[$field] ?? ''));
        if ('' === $header) {
            return '';
        }

        return $row[$header] ?? '';
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @param array<string, string> $fieldMapping
     * @param array<int|string, string> $rowTargets
     * @return list<ExternalTicketEvent>
     */
    private function buildEvents(array $rows, array $fieldMapping, array $rowTargets): array
    {
        $events = [];

        foreach ($rows as $rowIndex => $row) {
            $target = $rowTargets[(string) $rowIndex] ?? $rowTargets[$rowIndex] ?? ($rowIndex === array_key_first($rows) ? 'ticket' : 'history');
            if ('ignore' === $target || 'ticket' === $target) {
                continue;
            }

            $title = $this->mappedValue($row, $fieldMapping, 'event_title');
            $body = $this->mappedValue($row, $fieldMapping, 'event_body');
            $resolutionBody = $this->mappedValue($row, $fieldMapping, 'resolution_body');
            $eventType = $this->mappedValue($row, $fieldMapping, 'event_type');
            $eventDate = $this->mappedValue($row, $fieldMapping, 'event_date');

            if ('' === $title) {
                $title = '' !== $body ? mb_substr($body, 0, 90) : sprintf('Importerad historikrad %d', $rowIndex + 1);
            }

            $occurredAt = null;
            if ('' !== $eventDate) {
                try {
                    $occurredAt = new \DateTimeImmutable($eventDate);
                } catch (\Exception) {
                    $occurredAt = null;
                }
            }

            $event = new ExternalTicketEvent('' !== $eventType ? $eventType : 'history', $title, $occurredAt ?? new \DateTimeImmutable());
            $event
                ->setBody($body)
                ->setActorName($this->mappedValue($row, $fieldMapping, 'event_actor_name'))
                ->setActorEmail($this->mappedValue($row, $fieldMapping, 'event_actor_email'))
                ->setMetadata($this->rowMetadata($row, $fieldMapping));

            $events[] = $event;

            if ('' !== $resolutionBody) {
                $resolutionEvent = new ExternalTicketEvent('resolution', 'Lösning', $occurredAt ?? new \DateTimeImmutable());
                $resolutionEvent
                    ->setBody($resolutionBody)
                    ->setActorName($this->mappedValue($row, $fieldMapping, 'event_actor_name'))
                    ->setActorEmail($this->mappedValue($row, $fieldMapping, 'event_actor_email'))
                    ->setMetadata($this->rowMetadata($row, $fieldMapping));

                $events[] = $resolutionEvent;
            }
        }

        usort(
            $events,
            static fn (ExternalTicketEvent $left, ExternalTicketEvent $right): int => $left->getOccurredAt() <=> $right->getOccurredAt(),
        );

        return $events;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, string> $fieldMapping
     */
    private function looksLikeTicketRow(array $row, array $fieldMapping): bool
    {
        foreach (['subject', 'summary', 'reference', 'requester_name', 'requester_email', 'assignee_name', 'status', 'priority', 'closed_at'] as $field) {
            if ('' !== $this->mappedValue($row, $fieldMapping, $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, string> $fieldMapping
     * @return array<string, array{displayName: string}>
     */
    private function buildImportedPeople(array $row, array $fieldMapping): array
    {
        $people = [];
        $requesterName = $this->mappedValue($row, $fieldMapping, 'requester_name');
        $assigneeName = $this->mappedValue($row, $fieldMapping, 'assignee_name');

        if ('' !== $requesterName) {
            $people['requester'] = ['displayName' => $requesterName];
        }

        if ('' !== $assigneeName) {
            $people['assignee'] = ['displayName' => $assigneeName];
        }

        return $people;
    }

    /**
     * @param list<int> $rowIndexes
     * @param array<int|string, string> $rowTargets
     * @return array<int|string, string>
     */
    private function rowTargetsForTicket(array $rowIndexes, array $rowTargets): array
    {
        $scopedTargets = [];
        foreach ($rowIndexes as $position => $rowIndex) {
            $target = $rowTargets[(string) $rowIndex] ?? $rowTargets[$rowIndex] ?? ($position === 0 ? 'ticket' : 'history');
            $scopedTargets[(string) $position] = $position === 0 ? 'ticket' : ('ignore' === $target ? 'ignore' : 'history');
        }

        return $scopedTargets;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, string> $fieldMapping
     */
    private function referenceForTicket(array $row, array $fieldMapping, ?string $explicitReference, int $ticketDefinitionIndex): ?string
    {
        $reference = $explicitReference ?: $this->mappedValue($row, $fieldMapping, 'reference');
        if ('' === trim((string) $reference)) {
            return null;
        }

        return 0 === $ticketDefinitionIndex ? trim((string) $reference) : trim((string) $reference);
    }

    /**
     * @param array{
     *     filename?: string,
     *     delimiter?: string
     * } $payload
     * @param list<string> $headers
     * @param array<int, array<string, string>> $rows
     * @param array<string, string> $fieldMapping
     * @param array<int|string, string> $rowTargets
     * @return array<string, string>
     */
    private function buildMetadata(array $payload, array $headers, array $rows, array $fieldMapping, array $rowTargets, string $sourceLabel): array
    {
        $historyRows = 0;
        $ignoredRows = 0;
        foreach (array_keys($rows) as $rowIndex) {
            $target = $rowTargets[(string) $rowIndex] ?? $rowTargets[$rowIndex] ?? ($rowIndex === array_key_first($rows) ? 'ticket' : 'history');
            if ('history' === $target) {
                ++$historyRows;
            } elseif ('ignore' === $target) {
                ++$ignoredRows;
            }
        }

        $metadata = [
            'Källa' => $sourceLabel,
            'Importformat' => 'CSV',
            'Kolumner' => (string) \count($headers),
            'Rader' => (string) \count($rows),
            'Historikrader' => (string) $historyRows,
        ];

        $filename = $this->normalizeValue($payload['filename'] ?? null);
        if ('' !== $filename) {
            $metadata['Fil'] = $filename;
        }

        $delimiter = $this->normalizeValue($payload['delimiter'] ?? null);
        if ('' !== $delimiter) {
            $metadata['Avgränsare'] = $delimiter === "\t" ? 'tab' : $delimiter;
        }

        if ($ignoredRows > 0) {
            $metadata['Ignorerade rader'] = (string) $ignoredRows;
        }

        foreach ($fieldMapping as $field => $header) {
            $normalizedField = trim((string) $field);
            $normalizedHeader = trim((string) $header);
            if ('' !== $normalizedField && '' !== $normalizedHeader) {
                $metadata['Mappning '.str_replace('_', ' ', $normalizedField)] = $normalizedHeader;
            }
        }

        return $metadata;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, string> $fieldMapping
     * @return array<string, string>
     */
    private function rowMetadata(array $row, array $fieldMapping): array
    {
        $ignoredHeaders = array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $fieldMapping));
        $metadata = [];

        foreach ($row as $header => $value) {
            if (\in_array($header, $ignoredHeaders, true) || '' === $value) {
                continue;
            }

            $metadata[$header] = $value;
        }

        return $metadata;
    }

    private function sourceLabel(string $sourceSystem): string
    {
        return match ($sourceSystem) {
            'sharepoint' => 'SharePoint',
            'jira' => 'Jira',
            'servicenow' => 'ServiceNow',
            'zendesk' => 'Zendesk',
            default => 'Externt ärendesystem',
        };
    }

    private function normalizeValue(mixed $value): string
    {
        return trim((string) $value);
    }

    /**
     * @param array<string, string> $row
     * @param array<string, string> $fieldMapping
     */
    private function resolveClosedAt(array $row, array $fieldMapping, Ticket $ticket): ?\DateTimeImmutable
    {
        if ($ticket->getStatus()->value !== 'closed') {
            return null;
        }

        $value = $this->mappedValue($row, $fieldMapping, 'closed_at');
        if ('' === $value) {
            return $ticket->getClosedAt();
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return $ticket->getClosedAt();
        }
    }
}
