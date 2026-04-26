<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\TechnicianTeam;
use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Ticket\Entity\SlaPolicy;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketComment;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use App\Module\Ticket\Service\TicketReportBuilder;
use PHPUnit\Framework\TestCase;

final class TicketReportBuilderTest extends TestCase
{
    public function testFiltersTicketsByCompanyAndPeriod(): void
    {
        $builder = new TicketReportBuilder();
        $company = new Company('Rapport AB');
        $otherCompany = new Company('Annan AB');

        $included = $this->ticket('DP-1', $company, TicketStatus::OPEN, '2026-03-10 10:00:00');
        $excludedCompany = $this->ticket('DP-2', $otherCompany, TicketStatus::OPEN, '2026-03-11 10:00:00');
        $excludedDate = $this->ticket('DP-3', $company, TicketStatus::OPEN, '2026-02-10 10:00:00');

        $filtered = $builder->filterReportTickets([$included, $excludedCompany, $excludedDate], [
            'from' => '2026-03-01',
            'to' => '2026-03-31',
            'company_id' => 'all',
        ], $company);

        self::assertSame([$included], $filtered);
    }

    public function testBuildReportIncludesOlderOpenTickets(): void
    {
        $builder = new TicketReportBuilder();
        $company = new Company('Open AB');
        $oldOpen = $this->ticket('DP-10', $company, TicketStatus::OPEN, '2026-01-01 08:00:00');
        $newOpen = $this->ticket('DP-11', $company, TicketStatus::NEW, '2026-03-20 08:00:00');

        $report = $builder->build([$newOpen, $oldOpen]);

        self::assertSame(2, $report['summary']['total']);
        self::assertSame('DP-10', $report['olderOpenTickets'][0]->getReference());
        self::assertSame('DP-11', $report['olderOpenTickets'][1]->getReference());
    }

    public function testBuildReportIncludesBiLightFlowRiskSlaAndTopLists(): void
    {
        $builder = new TicketReportBuilder();
        $alpha = new Company('Alpha AB');
        $beta = new Company('Beta AB');
        $team = new TechnicianTeam('NOC');
        $technician = new User('tech-report@example.test', 'Tess', 'Tekniker', UserType::TECHNICIAN);
        $sla = new SlaPolicy('Standard SLA', 4, 24);

        $openCritical = $this->ticket('DP-20', $alpha, TicketStatus::OPEN, '2026-01-10 08:00:00');
        $openCritical
            ->setPriority(TicketPriority::CRITICAL)
            ->setEscalationLevel(TicketEscalationLevel::INCIDENT)
            ->setAssignee($technician)
            ->setAssignedTeam($team)
            ->setSlaPolicy($sla);

        $resolvedFast = $this->ticket('DP-21', $alpha, TicketStatus::RESOLVED, '2026-03-01 08:00:00');
        $resolvedFast
            ->setAssignee($technician)
            ->setAssignedTeam($team)
            ->setSlaPolicy($sla)
            ->setClosedAt(new \DateTimeImmutable('2026-03-01 18:00:00'));
        $this->addStaffComment($resolvedFast, $technician, '2026-03-01 10:00:00');

        $resolvedSlow = $this->ticket('DP-22', $alpha, TicketStatus::CLOSED, '2026-03-03 08:00:00');
        $resolvedSlow
            ->setSlaPolicy($sla)
            ->setClosedAt(new \DateTimeImmutable('2026-03-05 14:00:00'));
        $this->addStaffComment($resolvedSlow, $technician, '2026-03-03 15:00:00');

        $waitingUnassigned = $this->ticket('DP-23', $beta, TicketStatus::PENDING_CUSTOMER, '2026-03-10 08:00:00');

        $report = $builder->build([$openCritical, $resolvedFast, $resolvedSlow, $waitingUnassigned]);

        self::assertSame(4, $report['flow']['totals']['created']);
        self::assertSame(2, $report['flow']['totals']['resolved']);
        self::assertSame(2, $report['flow']['totals']['net']);
        self::assertSame(2, $report['flow']['totals']['backlog']);
        self::assertSame('2026-03', $report['flow']['months'][1]['label']);
        self::assertSame(3, $report['flow']['months'][1]['created']);
        self::assertSame(2, $report['flow']['months'][1]['resolved']);

        self::assertSame(3, $report['slaHealth']['firstResponse']['measured']);
        self::assertSame(1, $report['slaHealth']['firstResponse']['met']);
        self::assertSame(2, $report['slaHealth']['firstResponse']['breached']);
        self::assertSame(3, $report['slaHealth']['resolution']['measured']);
        self::assertSame(1, $report['slaHealth']['resolution']['met']);
        self::assertSame(2, $report['slaHealth']['resolution']['breached']);
        self::assertSame(33, $report['slaHealth']['overallRate']);

        self::assertSame(2, $report['risk']['backlog']);
        self::assertSame(1, $report['risk']['waiting']);
        self::assertSame(1, $report['risk']['highPriority']);
        self::assertSame(1, $report['risk']['escalated']);
        self::assertSame(1, $report['risk']['unassignedOpen']);
        self::assertGreaterThanOrEqual(1, $report['risk']['oldestOpenDays']);

        self::assertSame('Alpha AB', $report['topCompanies'][0]['name']);
        self::assertSame(3, $report['topCompanies'][0]['total']);
        self::assertSame('Tess Tekniker', $report['topOwners'][0]['label']);
        self::assertSame('NOC', $report['workload']['byTeam'][0]['label']);
    }

    private function addStaffComment(Ticket $ticket, User $author, string $createdAt): void
    {
        $comment = new TicketComment($ticket, $author, 'Publikt svar');
        $comment->setCreatedAt(new \DateTimeImmutable($createdAt));
        $comment->setUpdatedAt(new \DateTimeImmutable($createdAt));
        $ticket->addComment($comment);
    }

    private function ticket(string $reference, Company $company, TicketStatus $status, string $createdAt): Ticket
    {
        $ticket = new Ticket(
            $reference,
            'Testärende '.$reference,
            'Sammanfattning '.$reference,
            $status,
            TicketVisibility::PRIVATE,
            TicketPriority::NORMAL,
            TicketRequestType::INCIDENT,
            TicketImpactLevel::SINGLE_USER,
        );
        $ticket->setCompany($company);
        $ticket->setCreatedAt(new \DateTimeImmutable($createdAt));
        $ticket->setUpdatedAt(new \DateTimeImmutable($createdAt));

        return $ticket;
    }
}
