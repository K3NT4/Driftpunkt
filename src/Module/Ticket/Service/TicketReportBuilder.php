<?php

declare(strict_types=1);

namespace App\Module\Ticket\Service;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\User;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;

final class TicketReportBuilder
{
    /**
     * @param list<Ticket> $tickets
     * @param array{from?: string, to?: string, company_id?: string, open_monthly_summary?: bool} $filters
     * @return list<Ticket>
     */
    public function filterReportTickets(array $tickets, array $filters, ?Company $forcedCompany = null): array
    {
        $from = $this->dateFromFilter((string) ($filters['from'] ?? ''), true);
        $to = $this->dateFromFilter((string) ($filters['to'] ?? ''), false);
        $companyId = $forcedCompany?->getId();
        if (null === $companyId && ctype_digit((string) ($filters['company_id'] ?? ''))) {
            $companyId = (int) $filters['company_id'];
        }

        return array_values(array_filter(
            $tickets,
            static function (Ticket $ticket) use ($from, $to, $companyId, $forcedCompany): bool {
                if (null === $companyId && $forcedCompany instanceof Company && $ticket->getCompany() !== $forcedCompany) {
                    return false;
                }

                if (null !== $companyId && $ticket->getCompany()?->getId() !== $companyId) {
                    return false;
                }

                if (!$from instanceof \DateTimeImmutable && !$to instanceof \DateTimeImmutable) {
                    return true;
                }

                $relevantDates = [$ticket->getCreatedAt()];
                if ($ticket->getClosedAt() instanceof \DateTimeImmutable) {
                    $relevantDates[] = $ticket->getClosedAt();
                }

                foreach ($relevantDates as $date) {
                    if ($from instanceof \DateTimeImmutable && $date < $from) {
                        continue;
                    }

                    if ($to instanceof \DateTimeImmutable && $date > $to) {
                        continue;
                    }

                    return true;
                }

                return false;
            },
        ));
    }

    /**
     * @param list<Ticket> $tickets
     * @param array{from?: string, to?: string, company_id?: string, open_monthly_summary?: bool} $filters
     * @return list<Ticket>
     */
    public function filterOpenTicketsForMonthlySummary(array $tickets, array $filters, ?Company $forcedCompany = null): array
    {
        $companyId = $forcedCompany?->getId();
        if (null === $companyId && ctype_digit((string) ($filters['company_id'] ?? ''))) {
            $companyId = (int) $filters['company_id'];
        }

        return array_values(array_filter(
            $tickets,
            static function (Ticket $ticket) use ($companyId, $forcedCompany): bool {
                if (null === $companyId && $forcedCompany instanceof Company && $ticket->getCompany() !== $forcedCompany) {
                    return false;
                }

                if (null !== $companyId && $ticket->getCompany()?->getId() !== $companyId) {
                    return false;
                }

                return \in_array($ticket->getStatus(), [TicketStatus::NEW, TicketStatus::OPEN, TicketStatus::PENDING_CUSTOMER], true);
            },
        ));
    }

    /**
     * @param list<Ticket> $tickets
     * @param list<Ticket>|null $openMonthlySummaryTickets
     * @return array<string, mixed>
     */
    public function build(array $tickets, ?array $openMonthlySummaryTickets = null): array
    {
        $closedStatuses = [TicketStatus::RESOLVED, TicketStatus::CLOSED];
        $activeStatuses = [TicketStatus::NEW, TicketStatus::OPEN, TicketStatus::PENDING_CUSTOMER];
        $byStatus = [];
        $byCompany = [];
        $byMonth = [];
        $byPriority = [];
        $byRequestType = [];
        $byImpact = [];
        $byEscalation = [];
        $byAssignee = [];
        $byTeam = [];
        $recentResolved = [];
        $resolutionHours = [];
        $firstResponseHours = [];
        $firstResponseSlaMet = 0;
        $firstResponseSlaBreached = 0;
        $resolutionSlaMet = 0;
        $resolutionSlaBreached = 0;
        $slaTicketCount = 0;
        $assignedCount = 0;
        $escalatedCount = 0;
        $highPriorityCount = 0;

        foreach (TicketStatus::cases() as $status) {
            $byStatus[$status->value] = [
                'label' => $status->label(),
                'color' => $status->color(),
                'count' => 0,
            ];
        }
        foreach (TicketPriority::cases() as $priority) {
            $byPriority[$priority->value] = $this->emptyReportGroupRow($priority->label(), $priority->color());
        }
        foreach (TicketRequestType::cases() as $requestType) {
            $byRequestType[$requestType->value] = $this->emptyReportGroupRow($requestType->label(), '#1d4ed8');
        }
        foreach (TicketImpactLevel::cases() as $impactLevel) {
            $byImpact[$impactLevel->value] = $this->emptyReportGroupRow($impactLevel->label(), $impactLevel->color());
        }
        foreach (TicketEscalationLevel::cases() as $escalationLevel) {
            $byEscalation[$escalationLevel->value] = $this->emptyReportGroupRow($escalationLevel->label(), $escalationLevel->color());
        }

        foreach ($tickets as $ticket) {
            $status = $ticket->getStatus();
            ++$byStatus[$status->value]['count'];

            $company = $ticket->getCompany();
            $companyKey = $this->entityGroupKey($company);
            $byCompany[$companyKey] ??= [
                'name' => $company?->getName() ?? 'Utan företag',
                'total' => 0,
                'open' => 0,
                'resolved' => 0,
                'avgResolutionHours' => null,
                'resolutionHours' => [],
            ];
            ++$byCompany[$companyKey]['total'];

            if (\in_array($status, $activeStatuses, true)) {
                ++$byCompany[$companyKey]['open'];
            }

            $this->addTicketToReportGroup($byPriority[$ticket->getPriority()->value], $ticket, $activeStatuses, $closedStatuses);
            $this->addTicketToReportGroup($byRequestType[$ticket->getRequestType()->value], $ticket, $activeStatuses, $closedStatuses);
            $this->addTicketToReportGroup($byImpact[$ticket->getImpactLevel()->value], $ticket, $activeStatuses, $closedStatuses);
            $this->addTicketToReportGroup($byEscalation[$ticket->getEscalationLevel()->value], $ticket, $activeStatuses, $closedStatuses);

            $assigneeKey = $this->entityGroupKey($ticket->getAssignee());
            $byAssignee[$assigneeKey] ??= $this->emptyReportGroupRow($ticket->getAssignee()?->getDisplayName() ?? 'Ej tilldelad', '#64748b');
            $this->addTicketToReportGroup($byAssignee[$assigneeKey], $ticket, $activeStatuses, $closedStatuses);

            $teamKey = $this->entityGroupKey($ticket->getAssignedTeam());
            $byTeam[$teamKey] ??= $this->emptyReportGroupRow($ticket->getAssignedTeam()?->getName() ?? 'Inget team', '#7c3aed');
            $this->addTicketToReportGroup($byTeam[$teamKey], $ticket, $activeStatuses, $closedStatuses);

            if (null !== $ticket->getAssignee()) {
                ++$assignedCount;
            }
            if (TicketEscalationLevel::NONE !== $ticket->getEscalationLevel()) {
                ++$escalatedCount;
            }
            if (\in_array($ticket->getPriority(), [TicketPriority::HIGH, TicketPriority::CRITICAL], true)) {
                ++$highPriorityCount;
            }

            $closedAt = $ticket->getClosedAt();
            if (\in_array($status, $closedStatuses, true)) {
                ++$byCompany[$companyKey]['resolved'];
                $recentResolved[] = $ticket;
            }

            if ($closedAt instanceof \DateTimeImmutable) {
                $hours = max(0, (int) floor(($closedAt->getTimestamp() - $ticket->getCreatedAt()->getTimestamp()) / 3600));
                $resolutionHours[] = $hours;
                $byCompany[$companyKey]['resolutionHours'][] = $hours;
            }

            $firstResponseAt = $this->findFirstPublicStaffResponseAt($ticket);
            if ($firstResponseAt instanceof \DateTimeImmutable) {
                $firstResponseHours[] = max(0, (int) floor(($firstResponseAt->getTimestamp() - $ticket->getCreatedAt()->getTimestamp()) / 3600));
            }

            $firstResponseDueAt = $ticket->getFirstResponseDueAt();
            if ($firstResponseDueAt instanceof \DateTimeImmutable && ($firstResponseAt instanceof \DateTimeImmutable || $ticket->isFirstResponseSlaBreached())) {
                if ($firstResponseAt instanceof \DateTimeImmutable && $firstResponseAt <= $firstResponseDueAt) {
                    ++$firstResponseSlaMet;
                } else {
                    ++$firstResponseSlaBreached;
                }
            }

            $resolutionDueAt = $ticket->getResolutionDueAt();
            if ($resolutionDueAt instanceof \DateTimeImmutable) {
                ++$slaTicketCount;
                if ($closedAt instanceof \DateTimeImmutable) {
                    if ($closedAt <= $resolutionDueAt) {
                        ++$resolutionSlaMet;
                    } else {
                        ++$resolutionSlaBreached;
                    }
                } elseif ($ticket->isResolutionSlaBreached()) {
                    ++$resolutionSlaBreached;
                }
            }

            $createdMonthKey = $ticket->getCreatedAt()->format('Y-m');
            $byMonth[$createdMonthKey] ??= [
                'label' => $createdMonthKey,
                'created' => 0,
                'resolved' => 0,
            ];
            ++$byMonth[$createdMonthKey]['created'];

            if (\in_array($status, $closedStatuses, true) && $closedAt instanceof \DateTimeImmutable) {
                $closedMonthKey = $closedAt->format('Y-m');
                $byMonth[$closedMonthKey] ??= [
                    'label' => $closedMonthKey,
                    'created' => 0,
                    'resolved' => 0,
                ];
                ++$byMonth[$closedMonthKey]['resolved'];
            }
        }

        foreach ($byCompany as &$companyRow) {
            if ([] !== $companyRow['resolutionHours']) {
                $companyRow['avgResolutionHours'] = (int) round(array_sum($companyRow['resolutionHours']) / \count($companyRow['resolutionHours']));
            }
            unset($companyRow['resolutionHours']);
        }
        unset($companyRow);

        usort($byCompany, static fn (array $left, array $right): int => [$right['total'], $left['name']] <=> [$left['total'], $right['name']]);
        ksort($byMonth);
        usort($recentResolved, static fn (Ticket $left, Ticket $right): int => ($right->getClosedAt() ?? $right->getUpdatedAt())->getTimestamp() <=> ($left->getClosedAt() ?? $left->getUpdatedAt())->getTimestamp());
        $firstResponseMeasured = $firstResponseSlaMet + $firstResponseSlaBreached;
        $resolutionMeasured = $resolutionSlaMet + $resolutionSlaBreached;
        $openTicketCount = \count(array_filter($tickets, static fn (Ticket $ticket): bool => \in_array($ticket->getStatus(), $activeStatuses, true)));
        $resolvedTicketCount = \count(array_filter($tickets, static fn (Ticket $ticket): bool => \in_array($ticket->getStatus(), $closedStatuses, true)));
        $olderOpenTickets = $this->olderOpenTickets($tickets);
        $byPriorityRows = $this->finalizeReportGroupRows(array_values($byPriority));
        $byRequestTypeRows = $this->finalizeReportGroupRows(array_values($byRequestType));
        $byImpactRows = $this->finalizeReportGroupRows(array_values($byImpact));
        $byEscalationRows = $this->finalizeReportGroupRows(array_values($byEscalation));
        $byAssigneeRows = $this->finalizeReportGroupRows(array_values($byAssignee));
        $byTeamRows = $this->finalizeReportGroupRows(array_values($byTeam));
        $topOwners = array_values(array_filter(
            $byAssigneeRows,
            static fn (array $row): bool => ($row['total'] ?? 0) > 0 && 'Ej tilldelad' !== ($row['label'] ?? ''),
        ));
        $workloadTeams = array_values(array_filter(
            $byTeamRows,
            static fn (array $row): bool => ($row['total'] ?? 0) > 0 && 'Inget team' !== ($row['label'] ?? ''),
        ));

        return [
            'summary' => [
                'total' => \count($tickets),
                'open' => $openTicketCount,
                'waiting' => \count(array_filter($tickets, static fn (Ticket $ticket): bool => TicketStatus::PENDING_CUSTOMER === $ticket->getStatus())),
                'resolved' => $resolvedTicketCount,
                'avgResolutionHours' => [] === $resolutionHours ? null : (int) round(array_sum($resolutionHours) / \count($resolutionHours)),
                'medianResolutionHours' => $this->medianInt($resolutionHours),
                'avgFirstResponseHours' => [] === $firstResponseHours ? null : (int) round(array_sum($firstResponseHours) / \count($firstResponseHours)),
                'medianFirstResponseHours' => $this->medianInt($firstResponseHours),
                'resolutionRate' => $this->percentage($resolvedTicketCount, \count($tickets)),
                'firstResponseSlaRate' => $this->percentage($firstResponseSlaMet, $firstResponseMeasured),
                'resolutionSlaRate' => $this->percentage($resolutionSlaMet, $resolutionMeasured),
                'firstResponseSlaMet' => $firstResponseSlaMet,
                'firstResponseSlaBreached' => $firstResponseSlaBreached,
                'resolutionSlaMet' => $resolutionSlaMet,
                'resolutionSlaBreached' => $resolutionSlaBreached,
                'slaTicketCount' => $slaTicketCount,
                'assigned' => $assignedCount,
                'escalated' => $escalatedCount,
                'highPriority' => $highPriorityCount,
            ],
            'byStatus' => array_values($byStatus),
            'byCompany' => array_values($byCompany),
            'byMonth' => array_values($byMonth),
            'byPriority' => $byPriorityRows,
            'byRequestType' => $byRequestTypeRows,
            'byImpact' => $byImpactRows,
            'byEscalation' => $byEscalationRows,
            'byAssignee' => \array_slice($byAssigneeRows, 0, 10),
            'byTeam' => \array_slice($byTeamRows, 0, 10),
            'recentResolved' => \array_slice($recentResolved, 0, 12),
            'olderOpenTickets' => \array_slice($olderOpenTickets, 0, 10),
            'openMonthlySummary' => null === $openMonthlySummaryTickets ? null : $this->buildOpenMonthlySummary($openMonthlySummaryTickets),
            'openMonthlySummaryTotal' => null === $openMonthlySummaryTickets ? null : \count($openMonthlySummaryTickets),
            'flow' => $this->buildFlowSummary(array_values($byMonth), $openTicketCount),
            'slaHealth' => $this->buildSlaHealthSummary($firstResponseSlaMet, $firstResponseSlaBreached, $resolutionSlaMet, $resolutionSlaBreached),
            'risk' => $this->buildRiskSummary($tickets, $olderOpenTickets, $activeStatuses, $highPriorityCount, $escalatedCount),
            'topCompanies' => \array_slice(array_values($byCompany), 0, 6),
            'topOwners' => \array_slice($topOwners, 0, 6),
            'workload' => [
                'byAssignee' => \array_slice($byAssigneeRows, 0, 8),
                'byTeam' => \array_slice($workloadTeams, 0, 8),
            ],
        ];
    }

    private function dateFromFilter(string $value, bool $startOfDay): ?\DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof \DateTimeImmutable) {
            return null;
        }

        return $startOfDay ? $date->setTime(0, 0) : $date->setTime(23, 59, 59);
    }

    /**
     * @return array{label: string, color: string, total: int, open: int, resolved: int, waiting: int, resolutionHours: list<int>, avgResolutionHours: ?int}
     */
    private function emptyReportGroupRow(string $label, string $color): array
    {
        return [
            'label' => $label,
            'color' => $color,
            'total' => 0,
            'open' => 0,
            'resolved' => 0,
            'waiting' => 0,
            'resolutionHours' => [],
            'avgResolutionHours' => null,
        ];
    }

    /**
     * @param array{label: string, color: string, total: int, open: int, resolved: int, waiting: int, resolutionHours: list<int>, avgResolutionHours: ?int} $row
     * @param list<TicketStatus> $activeStatuses
     * @param list<TicketStatus> $closedStatuses
     */
    private function addTicketToReportGroup(array &$row, Ticket $ticket, array $activeStatuses, array $closedStatuses): void
    {
        ++$row['total'];

        if (\in_array($ticket->getStatus(), $activeStatuses, true)) {
            ++$row['open'];
        }
        if (TicketStatus::PENDING_CUSTOMER === $ticket->getStatus()) {
            ++$row['waiting'];
        }
        if (\in_array($ticket->getStatus(), $closedStatuses, true)) {
            ++$row['resolved'];
        }

        $closedAt = $ticket->getClosedAt();
        if ($closedAt instanceof \DateTimeImmutable) {
            $row['resolutionHours'][] = max(0, (int) floor(($closedAt->getTimestamp() - $ticket->getCreatedAt()->getTimestamp()) / 3600));
        }
    }

    /**
     * @param list<array{label: string, color: string, total: int, open: int, resolved: int, waiting: int, resolutionHours: list<int>, avgResolutionHours: ?int}> $rows
     * @return list<array{label: string, color: string, total: int, open: int, resolved: int, waiting: int, avgResolutionHours: ?int}>
     */
    private function finalizeReportGroupRows(array $rows): array
    {
        foreach ($rows as &$row) {
            if ([] !== $row['resolutionHours']) {
                $row['avgResolutionHours'] = (int) round(array_sum($row['resolutionHours']) / \count($row['resolutionHours']));
            }
            unset($row['resolutionHours']);
        }
        unset($row);

        usort($rows, static fn (array $left, array $right): int => [$right['total'], $left['label']] <=> [$left['total'], $right['label']]);

        return $rows;
    }

    /**
     * @param list<int> $values
     */
    private function medianInt(array $values): ?int
    {
        if ([] === $values) {
            return null;
        }

        sort($values);
        $middle = intdiv(\count($values), 2);

        if (1 === \count($values) % 2) {
            return $values[$middle];
        }

        return (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    private function percentage(int $part, int $total): ?int
    {
        if ($total <= 0) {
            return null;
        }

        return (int) round(($part / $total) * 100);
    }

    private function entityGroupKey(?object $entity): string
    {
        if (null === $entity) {
            return 'none';
        }

        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            if (null !== $id) {
                return (string) $id;
            }
        }

        return 'object:'.spl_object_id($entity);
    }

    /**
     * @param list<array{label: string, created: int, resolved: int}> $monthRows
     * @return array{
     *     totals: array{created: int, resolved: int, net: int, backlog: int},
     *     months: list<array{label: string, created: int, resolved: int, net: int, backlog: int, createdShare: int, resolvedShare: int}>,
     *     maxMonthlyVolume: int
     * }
     */
    private function buildFlowSummary(array $monthRows, int $backlog): array
    {
        $createdTotal = 0;
        $resolvedTotal = 0;
        $maxMonthlyVolume = 1;
        $runningBacklog = 0;

        foreach ($monthRows as $row) {
            $createdTotal += (int) $row['created'];
            $resolvedTotal += (int) $row['resolved'];
            $maxMonthlyVolume = max($maxMonthlyVolume, (int) $row['created'], (int) $row['resolved']);
        }

        $months = [];
        foreach ($monthRows as $row) {
            $created = (int) $row['created'];
            $resolved = (int) $row['resolved'];
            $net = $created - $resolved;
            $runningBacklog = max(0, $runningBacklog + $net);
            $months[] = [
                'label' => (string) $row['label'],
                'created' => $created,
                'resolved' => $resolved,
                'net' => $net,
                'backlog' => $runningBacklog,
                'createdShare' => $this->percentage($created, $maxMonthlyVolume) ?? 0,
                'resolvedShare' => $this->percentage($resolved, $maxMonthlyVolume) ?? 0,
            ];
        }

        return [
            'totals' => [
                'created' => $createdTotal,
                'resolved' => $resolvedTotal,
                'net' => $createdTotal - $resolvedTotal,
                'backlog' => $backlog,
            ],
            'months' => $months,
            'maxMonthlyVolume' => $maxMonthlyVolume,
        ];
    }

    /**
     * @return array{
     *     firstResponse: array{label: string, met: int, breached: int, measured: int, rate: ?int},
     *     resolution: array{label: string, met: int, breached: int, measured: int, rate: ?int},
     *     overallMet: int,
     *     overallBreached: int,
     *     overallMeasured: int,
     *     overallRate: ?int,
     *     state: string
     * }
     */
    private function buildSlaHealthSummary(int $firstResponseMet, int $firstResponseBreached, int $resolutionMet, int $resolutionBreached): array
    {
        $firstResponseMeasured = $firstResponseMet + $firstResponseBreached;
        $resolutionMeasured = $resolutionMet + $resolutionBreached;
        $overallMet = $firstResponseMet + $resolutionMet;
        $overallBreached = $firstResponseBreached + $resolutionBreached;
        $overallMeasured = $overallMet + $overallBreached;
        $overallRate = $this->percentage($overallMet, $overallMeasured);

        return [
            'firstResponse' => [
                'label' => 'Första svar',
                'met' => $firstResponseMet,
                'breached' => $firstResponseBreached,
                'measured' => $firstResponseMeasured,
                'rate' => $this->percentage($firstResponseMet, $firstResponseMeasured),
            ],
            'resolution' => [
                'label' => 'Lösning',
                'met' => $resolutionMet,
                'breached' => $resolutionBreached,
                'measured' => $resolutionMeasured,
                'rate' => $this->percentage($resolutionMet, $resolutionMeasured),
            ],
            'overallMet' => $overallMet,
            'overallBreached' => $overallBreached,
            'overallMeasured' => $overallMeasured,
            'overallRate' => $overallRate,
            'state' => match (true) {
                null === $overallRate => 'gray',
                $overallRate >= 90 => 'green',
                $overallRate >= 70 => 'orange',
                default => 'red',
            },
        ];
    }

    /**
     * @param list<Ticket> $tickets
     * @param list<Ticket> $olderOpenTickets
     * @param list<TicketStatus> $activeStatuses
     * @return array{backlog: int, waiting: int, highPriority: int, escalated: int, unassignedOpen: int, olderOpen: int, oldestOpenDays: ?int}
     */
    private function buildRiskSummary(array $tickets, array $olderOpenTickets, array $activeStatuses, int $highPriorityCount, int $escalatedCount): array
    {
        $openTickets = array_values(array_filter(
            $tickets,
            static fn (Ticket $ticket): bool => \in_array($ticket->getStatus(), $activeStatuses, true),
        ));
        $unassignedOpen = \count(array_filter($openTickets, static fn (Ticket $ticket): bool => null === $ticket->getAssignee()));
        $oldestOpenDays = null;
        if ([] !== $olderOpenTickets) {
            $oldestOpenDays = max(0, (int) $olderOpenTickets[0]->getCreatedAt()->diff(new \DateTimeImmutable())->format('%a'));
        }

        return [
            'backlog' => \count($openTickets),
            'waiting' => \count(array_filter($tickets, static fn (Ticket $ticket): bool => TicketStatus::PENDING_CUSTOMER === $ticket->getStatus())),
            'highPriority' => $highPriorityCount,
            'escalated' => $escalatedCount,
            'unassignedOpen' => $unassignedOpen,
            'olderOpen' => \count($olderOpenTickets),
            'oldestOpenDays' => $oldestOpenDays,
        ];
    }

    /**
     * @param list<Ticket> $tickets
     * @return list<Ticket>
     */
    private function olderOpenTickets(array $tickets): array
    {
        $open = array_values(array_filter(
            $tickets,
            static fn (Ticket $ticket): bool => \in_array($ticket->getStatus(), [TicketStatus::NEW, TicketStatus::OPEN, TicketStatus::PENDING_CUSTOMER], true),
        ));
        usort($open, static fn (Ticket $left, Ticket $right): int => $left->getCreatedAt()->getTimestamp() <=> $right->getCreatedAt()->getTimestamp());

        return $open;
    }

    /**
     * @param list<Ticket> $tickets
     * @return list<array{
     *     label: string,
     *     total: int,
     *     new: int,
     *     open: int,
     *     waiting: int,
     *     companies: int,
     *     oldestOpenDays: ?int,
     *     latestUpdatedAt: ?\DateTimeImmutable
     * }>
     */
    private function buildOpenMonthlySummary(array $tickets): array
    {
        $summary = [];
        $now = new \DateTimeImmutable();

        foreach ($tickets as $ticket) {
            $monthKey = $ticket->getCreatedAt()->format('Y-m');
            $summary[$monthKey] ??= [
                'label' => $monthKey,
                'total' => 0,
                'new' => 0,
                'open' => 0,
                'waiting' => 0,
                'companies' => [],
                'oldestOpenDays' => null,
                'latestUpdatedAt' => null,
            ];

            ++$summary[$monthKey]['total'];
            $statusKey = match ($ticket->getStatus()) {
                TicketStatus::NEW => 'new',
                TicketStatus::OPEN => 'open',
                TicketStatus::PENDING_CUSTOMER => 'waiting',
                default => null,
            };

            if (null !== $statusKey) {
                ++$summary[$monthKey][$statusKey];
            }

            $companyKey = (string) ($ticket->getCompany()?->getId() ?? 0);
            $summary[$monthKey]['companies'][$companyKey] = true;
            $openDays = max(0, (int) $ticket->getCreatedAt()->diff($now)->format('%a'));
            if (null === $summary[$monthKey]['oldestOpenDays'] || $openDays > $summary[$monthKey]['oldestOpenDays']) {
                $summary[$monthKey]['oldestOpenDays'] = $openDays;
            }

            if (!$summary[$monthKey]['latestUpdatedAt'] instanceof \DateTimeImmutable || $ticket->getUpdatedAt() > $summary[$monthKey]['latestUpdatedAt']) {
                $summary[$monthKey]['latestUpdatedAt'] = $ticket->getUpdatedAt();
            }
        }

        krsort($summary);

        return array_map(
            static function (array $row): array {
                $row['companies'] = \count($row['companies']);

                return $row;
            },
            array_values($summary),
        );
    }

    private function findFirstPublicStaffResponseAt(Ticket $ticket): ?\DateTimeImmutable
    {
        foreach ($ticket->getComments() as $comment) {
            $author = $comment->getAuthor();
            if ($comment->isInternal() || !$this->userIsInternalStaff($author)) {
                continue;
            }

            return $comment->getCreatedAt();
        }

        return null;
    }

    private function userIsInternalStaff(User $user): bool
    {
        return [] !== array_intersect($user->getRoles(), ['ROLE_TECHNICIAN', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
    }
}
