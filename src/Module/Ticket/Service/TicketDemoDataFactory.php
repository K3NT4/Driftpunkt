<?php

declare(strict_types=1);

namespace App\Module\Ticket\Service;

use App\Module\Identity\Entity\Company;
use App\Module\Identity\Entity\User;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketComment;
use App\Module\Ticket\Enum\TicketEscalationLevel;
use App\Module\Ticket\Enum\TicketImpactLevel;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketRequestType;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class TicketDemoDataFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * @return list<Ticket>
     */
    public function ensureDemoTickets(): array
    {
        if ('test' === $this->kernel->getEnvironment()) {
            return [];
        }

        $connection = $this->entityManager->getConnection();
        try {
            $existingCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM tickets');
        } catch (\Throwable) {
            return [];
        }

        if ($existingCount > 0) {
            return [];
        }

        $users = $this->entityManager->getRepository(User::class)->findBy([], ['createdAt' => 'ASC']);
        $companies = $this->entityManager->getRepository(Company::class)->findBy([], ['createdAt' => 'ASC']);

        $firstTechnician = $this->findFirstByRole($users, 'ROLE_TECHNICIAN') ?? $this->findFirstByRole($users, 'ROLE_ADMIN');
        $firstCustomer = $this->findFirstByRole($users, 'ROLE_CUSTOMER') ?? $this->findFirstByRole($users, 'ROLE_USER');
        $firstCompany = $companies[0] ?? $firstCustomer?->getCompany();

        $tickets = [
            (new Ticket(
                'DP-1001',
                'VPN nere för supportteamet',
                'Flera användare rapporterar att VPN-anslutningen bryts efter cirka 30 sekunder.',
                TicketStatus::OPEN,
                TicketVisibility::COMPANY_SHARED,
                TicketPriority::HIGH,
                TicketRequestType::INCIDENT,
                TicketImpactLevel::DEPARTMENT,
                TicketEscalationLevel::TEAM,
            ))->setCompany($firstCompany)->setRequester($firstCustomer)->setAssignee($firstTechnician),
            (new Ticket(
                'DP-1002',
                'Begäran om ny användare till kundportal',
                'Kunden vill lägga till en ny kontaktperson som ska kunna se delade företagsärenden.',
                TicketStatus::PENDING_CUSTOMER,
                TicketVisibility::PRIVATE,
                TicketPriority::NORMAL,
                TicketRequestType::ACCESS_REQUEST,
                TicketImpactLevel::TEAM,
                TicketEscalationLevel::NONE,
            ))->setCompany($firstCompany)->setRequester($firstCustomer)->setAssignee($firstTechnician),
            (new Ticket(
                'DP-1003',
                'Internt driftfönster för databasunderhåll',
                'Förberedande intern ticket för planerat underhåll och kommunikationssteg.',
                TicketStatus::NEW,
                TicketVisibility::INTERNAL_ONLY,
                TicketPriority::CRITICAL,
                TicketRequestType::CHANGE_REQUEST,
                TicketImpactLevel::CRITICAL_SERVICE,
                TicketEscalationLevel::INCIDENT,
            ))->setAssignee($firstTechnician),
        ];

        foreach ($tickets as $ticket) {
            $this->entityManager->persist($ticket);
        }

        if (null !== $firstTechnician) {
            $comment = new TicketComment(
                $tickets[0],
                $firstTechnician,
                'Vi undersöker VPN-gatewayen och återkommer med en uppdatering inom kort.',
                false,
            );
            $tickets[0]->addComment($comment);
            $this->entityManager->persist($comment);

            $internalComment = new TicketComment(
                $tickets[0],
                $firstTechnician,
                'Intern notering: misstanke om timeout i brandväggsregeln efter senaste ändringen.',
                true,
            );
            $tickets[0]->addComment($internalComment);
            $this->entityManager->persist($internalComment);
        }

        if (null !== $firstCustomer) {
            $customerComment = new TicketComment(
                $tickets[1],
                $firstCustomer,
                'Det gäller en ny kontaktperson på ekonomiavdelningen som behöver åtkomst snarast.',
                false,
            );
            $tickets[1]->addComment($customerComment);
            $this->entityManager->persist($customerComment);
        }

        $this->entityManager->flush();

        return $tickets;
    }

    /**
     * @param list<User> $users
     */
    private function findFirstByRole(array $users, string $role): ?User
    {
        foreach ($users as $user) {
            if (\in_array($role, $user->getRoles(), true)) {
                return $user;
            }
        }

        return null;
    }
}
