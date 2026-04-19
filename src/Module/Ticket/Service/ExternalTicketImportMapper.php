<?php

declare(strict_types=1);

namespace App\Module\Ticket\Service;

use App\Module\Ticket\Entity\ExternalTicketEvent;
use App\Module\Ticket\Entity\ExternalTicketImport;
use App\Module\Ticket\Entity\Ticket;

final class ExternalTicketImportMapper
{
    /**
     * @return array{
     *     subject: string,
     *     summary: string,
     *     import: ExternalTicketImport,
     *     eventCount: int
     * }
     */
    public function mapToTicketImport(
        Ticket $ticket,
        string $payload,
        string $selectedSourceSystem = 'auto',
        ?string $explicitReference = null,
        ?string $explicitUrl = null,
    ): array {
        $imports = $this->mapToTicketImports($payload, [$ticket], $selectedSourceSystem, $explicitReference, $explicitUrl);

        return $imports[0] ?? throw new \InvalidArgumentException('Importpayloaden innehöll inga användbara ärenden.');
    }

    /**
     * @param list<Ticket> $tickets
     *
     * @return list<array{
     *     subject: string,
     *     summary: string,
     *     import: ExternalTicketImport,
     *     eventCount: int
     * }>
     */
    public function mapToTicketImports(
        string $payload,
        array $tickets,
        string $selectedSourceSystem = 'auto',
        ?string $explicitReference = null,
        ?string $explicitUrl = null,
    ): array {
        try {
            $decodedPayload = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Importpayloaden måste vara giltig JSON.', 0, $exception);
        }

        if (!\is_array($decodedPayload)) {
            throw new \InvalidArgumentException('Importpayloaden måste vara ett JSON-objekt eller en JSON-lista.');
        }

        $payloadItems = $this->extractPayloadItems($decodedPayload);
        if ([] === $payloadItems) {
            throw new \InvalidArgumentException('Importpayloaden innehöll inga användbara ärenden.');
        }

        $ticketPool = array_values($tickets);
        $imports = [];

        foreach ($payloadItems as $index => $payloadItem) {
            $ticket = $ticketPool[$index] ?? new Ticket(
                sprintf('IMPORT-%d', $index + 1),
                '',
                '',
            );
            $workingPayload = $this->unwrapPrimaryPayload($payloadItem);
            $sourceSystem = 'auto' !== $selectedSourceSystem ? $selectedSourceSystem : $this->detectSourceSystem($workingPayload, $payloadItem);
            $sourceLabel = $this->sourceLabel($sourceSystem);
            $subject = $this->firstString($workingPayload, ['subject', 'title', 'Title', 'name', 'Name', 'headline']);
            $summary = $this->firstString($workingPayload, ['summary', 'description', 'Description', 'body', 'details', 'Details', 'content']);

            if ('' === $subject) {
                $reference = $this->firstString($workingPayload, ['reference', 'Reference', 'ticketNumber', 'TicketNumber', 'key', 'id', 'ID']);
                $subject = '' !== $reference ? sprintf('Importerat ärende %s', $reference) : sprintf('Importerat ärende från %s', $sourceLabel);
            }

            if ('' === $summary) {
                $summary = 'Ärendet importerades från extern källa. Öppna den importerade historiken för rådata och tidigare händelser.';
            }

            $ticket->setClosedAt($ticket->getStatus()->value === 'closed'
                ? $this->parseDate($workingPayload, ['closedAt', 'closed_at', 'closedDate', 'closed_date', 'closedOn', 'closed_on'])
                    ?? $ticket->getClosedAt()
                : null);

            $import = new ExternalTicketImport($ticket, $sourceSystem, $sourceLabel);
            $import
                ->setSourceReference($explicitReference ?: $this->firstString($workingPayload, ['reference', 'Reference', 'ticketNumber', 'TicketNumber', 'key', 'id', 'ID']))
                ->setSourceUrl($explicitUrl ?: $this->firstString($workingPayload, ['url', 'Url', 'webUrl', 'link', 'Link']))
                ->setRequesterName($this->detectRequesterName($workingPayload))
                ->setRequesterEmail($this->detectRequesterEmail($workingPayload))
                ->setStatusLabel($this->firstString($workingPayload, ['status', 'Status', 'state', 'State']))
                ->setPriorityLabel($this->firstString($workingPayload, ['priority', 'Priority', 'severity', 'Severity']))
                ->setRawPayload($payloadItem)
                ->setMetadata($this->collectMetadata($workingPayload, $sourceSystem));

            $events = $this->collectEvents($workingPayload);
            foreach ($events as $eventIndex => $event) {
                $event->setSortOrder($eventIndex);
                $import->addEvent($event);
            }

            $imports[] = [
                'subject' => $subject,
                'summary' => $summary,
                'import' => $import,
                'eventCount' => \count($events),
            ];
        }

        return $imports;
    }

    /**
     * @param array<mixed> $decodedPayload
     *
     * @return array<mixed>
     */
    private function unwrapPrimaryPayload(array $decodedPayload): array
    {
        if ($this->isList($decodedPayload) && isset($decodedPayload[0]) && \is_array($decodedPayload[0])) {
            return $decodedPayload[0];
        }

        if (isset($decodedPayload['d']) && \is_array($decodedPayload['d'])) {
            $results = $decodedPayload['d']['results'] ?? null;
            if (\is_array($results) && isset($results[0]) && \is_array($results[0])) {
                return $results[0];
            }
        }

        if (isset($decodedPayload['value']) && \is_array($decodedPayload['value']) && isset($decodedPayload['value'][0]) && \is_array($decodedPayload['value'][0])) {
            return array_merge($decodedPayload['value'][0], ['_collection' => $decodedPayload['value']]);
        }

        return $decodedPayload;
    }

    /**
     * @param array<mixed> $decodedPayload
     * @return list<array<mixed>>
     */
    private function extractPayloadItems(array $decodedPayload): array
    {
        if ($this->isList($decodedPayload)) {
            return array_values(array_filter($decodedPayload, static fn (mixed $item): bool => \is_array($item)));
        }

        if (isset($decodedPayload['d']['results']) && \is_array($decodedPayload['d']['results'])) {
            return array_values(array_filter($decodedPayload['d']['results'], static fn (mixed $item): bool => \is_array($item)));
        }

        if (isset($decodedPayload['value']) && \is_array($decodedPayload['value'])) {
            return array_values(array_filter($decodedPayload['value'], static fn (mixed $item): bool => \is_array($item)));
        }

        return [$decodedPayload];
    }

    /**
     * @param array<mixed> $payload
     * @param array<mixed> $decodedPayload
     */
    private function detectSourceSystem(array $payload, array $decodedPayload): string
    {
        $payloadJson = mb_strtolower((string) json_encode($decodedPayload));

        if (
            str_contains($payloadJson, 'sharepoint')
            || \array_key_exists('__metadata', $payload)
            || \array_key_exists('Editor', $payload)
            || \array_key_exists('Author', $payload)
        ) {
            return 'sharepoint';
        }

        if (\array_key_exists('jira', $payload) || \array_key_exists('fields', $payload) || str_contains($payloadJson, 'atlassian')) {
            return 'jira';
        }

        if (\array_key_exists('sys_id', $payload) || str_contains($payloadJson, 'servicenow')) {
            return 'servicenow';
        }

        if (\array_key_exists('zendesk_ticket_id', $payload) || str_contains($payloadJson, 'zendesk')) {
            return 'zendesk';
        }

        return 'generic';
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

    /**
     * @param array<mixed> $payload
     */
    private function detectRequesterName(array $payload): string
    {
        return $this->firstNestedString($payload, [
            ['requester', 'name'],
            ['requester', 'displayName'],
            ['author', 'name'],
            ['author', 'displayName'],
            ['reporter', 'displayName'],
            ['createdBy', 'displayName'],
            ['Author', 'Title'],
        ]);
    }

    /**
     * @param array<mixed> $payload
     */
    private function detectRequesterEmail(array $payload): string
    {
        return $this->firstNestedString($payload, [
            ['requester', 'email'],
            ['author', 'email'],
            ['author', 'mail'],
            ['reporter', 'emailAddress'],
            ['createdBy', 'mail'],
            ['Author', 'EMail'],
        ]);
    }

    /**
     * @param array<mixed> $payload
     * @return array<string, string>
     */
    private function collectMetadata(array $payload, string $sourceSystem): array
    {
        $metadata = [
            'Källa' => $this->sourceLabel($sourceSystem),
        ];

        foreach ([
            'siteName' => 'Site',
            'listName' => 'Lista',
            'status' => 'Extern status',
            'priority' => 'Extern prioritet',
            'assignedTo' => 'Extern tilldelning',
            'category' => 'Extern kategori',
        ] as $field => $label) {
            $value = $this->firstString($payload, [$field, ucfirst($field)]);
            if ('' !== $value) {
                $metadata[$label] = $value;
            }
        }

        foreach (['AssignedTo', 'Category', 'Status', 'Priority'] as $field) {
            $value = $this->stringify($payload[$field] ?? null);
            if ('' !== $value) {
                $metadata[$field] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @param array<mixed> $payload
     * @return list<ExternalTicketEvent>
     */
    private function collectEvents(array $payload): array
    {
        $events = [];
        $sources = [
            ['comments', 'comment'],
            ['history', 'history'],
            ['events', 'event'],
            ['timeline', 'timeline'],
            ['activities', 'activity'],
            ['versions', 'version'],
            ['journal', 'journal'],
            ['_collection', 'record'],
            ['Comments', 'comment'],
            ['History', 'history'],
            ['Versions', 'version'],
        ];

        foreach ($sources as [$field, $fallbackType]) {
            $items = $payload[$field] ?? null;
            if (!\is_array($items)) {
                continue;
            }

            foreach ($items as $index => $item) {
                if (!\is_array($item)) {
                    continue;
                }

                $event = $this->mapEvent($item, $fallbackType, $index);
                if ($event instanceof ExternalTicketEvent) {
                    $events[] = $event;
                }
            }
        }

        usort(
            $events,
            static fn (ExternalTicketEvent $left, ExternalTicketEvent $right): int => $left->getOccurredAt() <=> $right->getOccurredAt(),
        );

        return $events;
    }

    /**
     * @param array<mixed> $item
     */
    private function mapEvent(array $item, string $fallbackType, int $index): ?ExternalTicketEvent
    {
        $eventType = $this->firstString($item, ['type', 'eventType', 'category', 'kind', 'changeType']);
        if ('' === $eventType) {
            $eventType = $fallbackType;
        }

        $title = $this->firstString($item, ['title', 'name', 'summary', 'label', 'change', 'changeSummary']);
        $body = $this->firstString($item, ['body', 'comment', 'message', 'description', 'text', 'details', 'content']);
        if ('' === $title) {
            $title = '' !== $body ? mb_substr($body, 0, 90) : sprintf('Importerad %s %d', $fallbackType, $index + 1);
        }

        $occurredAt = $this->parseDate($item, ['occurredAt', 'createdAt', 'created', 'Created', 'modified', 'Modified', 'timestamp', 'date']);
        $event = new ExternalTicketEvent($eventType, $title, $occurredAt ?? new \DateTimeImmutable());
        $event
            ->setBody($body)
            ->setActorName($this->firstNestedString($item, [
                ['actor', 'name'],
                ['actor', 'displayName'],
                ['author', 'name'],
                ['author', 'displayName'],
                ['editor', 'displayName'],
                ['createdBy', 'displayName'],
                ['Editor', 'Title'],
                ['Author', 'Title'],
            ]))
            ->setActorEmail($this->firstNestedString($item, [
                ['actor', 'email'],
                ['author', 'email'],
                ['editor', 'mail'],
                ['createdBy', 'mail'],
                ['Editor', 'EMail'],
                ['Author', 'EMail'],
            ]))
            ->setMetadata($this->scalarMetadata($item));

        return $event;
    }

    /**
     * @param array<mixed> $item
     * @return array<string, string>
     */
    private function scalarMetadata(array $item): array
    {
        $metadata = [];
        foreach ($item as $key => $value) {
            if (\is_scalar($value) || null === $value) {
                $stringValue = $this->stringify($value);
                if ('' !== $stringValue) {
                    $metadata[(string) $key] = $stringValue;
                }
            }
        }

        return $metadata;
    }

    /**
     * @param array<mixed> $payload
     * @param list<string> $keys
     */
    private function firstString(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            if (!\array_key_exists($key, $payload)) {
                continue;
            }

            $value = $this->stringify($payload[$key]);
            if ('' !== $value) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<mixed> $payload
     * @param list<list<string>> $paths
     */
    private function firstNestedString(array $payload, array $paths): string
    {
        foreach ($paths as $path) {
            $current = $payload;
            foreach ($path as $segment) {
                if (!\is_array($current) || !\array_key_exists($segment, $current)) {
                    continue 2;
                }

                $current = $current[$segment];
            }

            $value = $this->stringify($current);
            if ('' !== $value) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<mixed> $payload
     * @param list<string> $keys
     */
    private function parseDate(array $payload, array $keys): ?\DateTimeImmutable
    {
        $value = $this->firstString($payload, $keys);
        if ('' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function stringify(mixed $value): string
    {
        if (\is_string($value)) {
            return trim($value);
        }

        if (\is_int($value) || \is_float($value) || \is_bool($value)) {
            return trim((string) $value);
        }

        if (\is_array($value)) {
            $parts = [];
            foreach ($value as $entry) {
                if (\is_scalar($entry) || null === $entry) {
                    $entryString = $this->stringify($entry);
                    if ('' !== $entryString) {
                        $parts[] = $entryString;
                    }
                }
            }

            return implode(', ', $parts);
        }

        return '';
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        return array_is_list($value);
    }
}
