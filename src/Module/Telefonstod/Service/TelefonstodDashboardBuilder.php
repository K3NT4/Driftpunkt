<?php

declare(strict_types=1);

namespace App\Module\Telefonstod\Service;

use App\Module\Identity\Entity\Company;
use App\Module\Telefonstod\Entity\PhoneChangeLogEntry;
use App\Module\Telefonstod\Entity\PhoneCustomerProfile;
use App\Module\Telefonstod\Entity\PhoneExtensionRecord;
use App\Module\Telefonstod\Entity\PhoneNumberRecord;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketPriority;
use App\Module\Ticket\Enum\TicketStatus;
use Doctrine\ORM\EntityManagerInterface;

final class TelefonstodDashboardBuilder
{
    private const TELEPHONY_KEYWORDS = [
        'telefoni',
        'telefon',
        'växel',
        'vaxel',
        'wx3',
        'samtal',
        'anknyt',
        'nummer',
        'röstbrevlåda',
        'rostbrevlada',
        'hänvisning',
        'hanvisning',
        'mobilkoppling',
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array{
     *   metrics: array<string, int>,
     *   companies: list<array<string, mixed>>,
     *   tickets: list<array<string, mixed>>,
     *   numbers: list<array<string, mixed>>,
     *   extensions: list<array<string, mixed>>,
     *   changes: list<array<string, mixed>>,
     *   searchResults: list<array<string, string|int>>,
     *   readiness: array<string, mixed>
     * }
     */
    public function build(?string $query = null): array
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $phoneSchemaReady = $schemaManager->tablesExist(['telefonstod_customer_profiles', 'telefonstod_phone_numbers', 'telefonstod_extensions', 'telefonstod_change_log']);

        $companies = $this->loadCompanies();
        $tickets = $this->loadTelephonyTickets();
        $numbers = $phoneSchemaReady ? $this->loadPhoneNumbers() : [];
        $extensions = $phoneSchemaReady ? $this->loadExtensions() : [];
        $changes = $phoneSchemaReady ? $this->loadChangeLog() : [];

        $companyRows = $this->buildCompanyRows($companies, $tickets);
        $ticketRows = $this->buildTicketRows($tickets);
        $numberRows = $this->buildNumberRows($numbers);
        $extensionRows = $this->buildExtensionRows($extensions);
        $changeRows = $this->buildChangeRows($changes);

        return [
            'metrics' => [
                'companies' => count($companies),
                'openTickets' => count(array_filter($tickets, static fn (Ticket $ticket): bool => !\in_array($ticket->getStatus(), [TicketStatus::RESOLVED, TicketStatus::CLOSED], true))),
                'waitingOnCustomer' => count(array_filter($tickets, static fn (Ticket $ticket): bool => TicketStatus::PENDING_CUSTOMER === $ticket->getStatus())),
                'priorityIssues' => count(array_filter($tickets, static fn (Ticket $ticket): bool => \in_array($ticket->getPriority(), [TicketPriority::HIGH, TicketPriority::CRITICAL], true) && !\in_array($ticket->getStatus(), [TicketStatus::RESOLVED, TicketStatus::CLOSED], true))),
            ],
            'companies' => array_slice($companyRows, 0, 8),
            'tickets' => array_slice($ticketRows, 0, 10),
            'numbers' => array_slice($numberRows, 0, 10),
            'extensions' => array_slice($extensionRows, 0, 10),
            'changes' => array_slice($changeRows, 0, 10),
            'searchResults' => $this->buildSearchResults($query, $companyRows, $ticketRows, $numberRows, $extensionRows),
            'readiness' => [
                'phoneInventoryReady' => $phoneSchemaReady,
                'changeLogReady' => $phoneSchemaReady,
                'integrationReady' => false,
                'message' => $phoneSchemaReady
                    ? 'Telefonstod anvander nu egna tabeller for kundprofil, nummer, anknytningar och andringslogg.'
                    : 'Telefonstod ar kopplat till Driftpunkts foretag och arenden. Kor migrationen for att aktivera nummerregister och andringslogg.',
            ],
        ];
    }

    /**
     * @return list<Company>
     */
    private function loadCompanies(): array
    {
        /** @var list<Company> $companies */
        $companies = $this->entityManager
            ->getRepository(Company::class)
            ->createQueryBuilder('company')
            ->orderBy('company.updatedAt', 'DESC')
            ->addOrderBy('company.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $companies;
    }

    /**
     * @return list<Ticket>
     */
    private function loadTelephonyTickets(): array
    {
        /** @var list<Ticket> $tickets */
        $tickets = $this->entityManager
            ->getRepository(Ticket::class)
            ->createQueryBuilder('ticket')
            ->leftJoin('ticket.category', 'category')
            ->leftJoin('ticket.company', 'company')
            ->addSelect('category', 'company')
            ->orderBy('ticket.updatedAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $tickets,
            fn (Ticket $ticket): bool => $this->isTelephonyTicket($ticket)
        ));
    }

    /**
     * @return list<PhoneNumberRecord>
     */
    private function loadPhoneNumbers(): array
    {
        /** @var list<PhoneNumberRecord> $numbers */
        $numbers = $this->entityManager
            ->getRepository(PhoneNumberRecord::class)
            ->createQueryBuilder('number')
            ->leftJoin('number.customerProfile', 'profile')
            ->leftJoin('profile.company', 'company')
            ->addSelect('profile', 'company')
            ->orderBy('number.updatedAt', 'DESC')
            ->addOrderBy('number.phoneNumber', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $numbers;
    }

    /**
     * @return list<PhoneExtensionRecord>
     */
    private function loadExtensions(): array
    {
        /** @var list<PhoneExtensionRecord> $extensions */
        $extensions = $this->entityManager
            ->getRepository(PhoneExtensionRecord::class)
            ->createQueryBuilder('extension')
            ->leftJoin('extension.customerProfile', 'profile')
            ->leftJoin('profile.company', 'company')
            ->addSelect('profile', 'company')
            ->orderBy('extension.updatedAt', 'DESC')
            ->addOrderBy('extension.extensionNumber', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $extensions;
    }

    /**
     * @return list<PhoneChangeLogEntry>
     */
    private function loadChangeLog(): array
    {
        /** @var list<PhoneChangeLogEntry> $entries */
        $entries = $this->entityManager
            ->getRepository(PhoneChangeLogEntry::class)
            ->createQueryBuilder('entry')
            ->leftJoin('entry.customerProfile', 'profile')
            ->leftJoin('profile.company', 'company')
            ->leftJoin('entry.ticket', 'ticket')
            ->addSelect('profile', 'company', 'ticket')
            ->orderBy('entry.changedAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $entries;
    }

    private function isTelephonyTicket(Ticket $ticket): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            $ticket->getSubject(),
            $ticket->getSummary(),
            $ticket->getResolutionSummary(),
            $ticket->getCategory()?->getName(),
            $ticket->getCategory()?->getDescription(),
        ])));

        foreach (self::TELEPHONY_KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Company> $companies
     * @param list<Ticket> $tickets
     * @return list<array<string, mixed>>
     */
    private function buildCompanyRows(array $companies, array $tickets): array
    {
        $rows = [];

        foreach ($companies as $company) {
            $companyTickets = array_values(array_filter(
                $tickets,
                static fn (Ticket $ticket): bool => $ticket->getCompany()?->getId() === $company->getId()
            ));

            $rows[] = [
                'id' => $company->getId() ?? 0,
                'name' => $company->getName(),
                'primaryEmail' => $company->getPrimaryEmail() ?? 'Ingen e-post registrerad',
                'openTicketCount' => count(array_filter(
                    $companyTickets,
                    static fn (Ticket $ticket): bool => !\in_array($ticket->getStatus(), [TicketStatus::RESOLVED, TicketStatus::CLOSED], true)
                )),
                'ticketCount' => count($companyTickets),
                'lastUpdatedAt' => $company->getUpdatedAt()->format('Y-m-d H:i'),
                'isActive' => $company->isActive(),
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => [$right['openTicketCount'], $right['ticketCount']] <=> [$left['openTicketCount'], $left['ticketCount']]
        );

        return $rows;
    }

    /**
     * @param list<Ticket> $tickets
     * @return list<array<string, mixed>>
     */
    private function buildTicketRows(array $tickets): array
    {
        return array_map(function (Ticket $ticket): array {
            return [
                'id' => $ticket->getId() ?? 0,
                'reference' => $ticket->getReference(),
                'subject' => $ticket->getSubject(),
                'company' => $ticket->getCompany()?->getName() ?? 'Ingen kund kopplad',
                'category' => $ticket->getCategory()?->getName() ?? 'Okategoriserad',
                'status' => $ticket->getStatus()->label(),
                'priority' => $ticket->getPriority()->label(),
                'assignee' => $ticket->getAssignee()?->getDisplayName() ?? 'Ej tilldelad',
                'updatedAt' => $ticket->getUpdatedAt()->format('Y-m-d H:i'),
            ];
        }, $tickets);
    }

    /**
     * @param list<PhoneNumberRecord> $numbers
     * @return list<array<string, mixed>>
     */
    private function buildNumberRows(array $numbers): array
    {
        return array_map(function (PhoneNumberRecord $number): array {
            return [
                'id' => $number->getId() ?? 0,
                'phoneNumber' => $number->getPhoneNumber(),
                'type' => $number->getNumberType(),
                'customer' => $number->getCustomerProfile()->getCompany()->getName(),
                'extension' => $number->getExtensionNumber() ?? '-',
                'displayName' => $number->getDisplayName() ?? 'Ej satt',
                'status' => $number->getStatus(),
                'queueName' => $number->getQueueName(),
                'updatedAt' => ($number->getLastChangedAt() ?? $number->getUpdatedAt())->format('Y-m-d H:i'),
            ];
        }, $numbers);
    }

    /**
     * @param list<PhoneExtensionRecord> $extensions
     * @return list<array<string, mixed>>
     */
    private function buildExtensionRows(array $extensions): array
    {
        return array_map(function (PhoneExtensionRecord $extension): array {
            return [
                'id' => $extension->getId() ?? 0,
                'extensionNumber' => $extension->getExtensionNumber(),
                'displayName' => $extension->getDisplayName(),
                'customer' => $extension->getCustomerProfile()->getCompany()->getName(),
                'directNumber' => $extension->getDirectNumber() ?? '-',
                'email' => $extension->getEmail() ?? 'Ingen e-post',
                'status' => $extension->getStatus(),
                'updatedAt' => $extension->getUpdatedAt()->format('Y-m-d H:i'),
            ];
        }, $extensions);
    }

    /**
     * @param list<PhoneChangeLogEntry> $entries
     * @return list<array<string, mixed>>
     */
    private function buildChangeRows(array $entries): array
    {
        return array_map(function (PhoneChangeLogEntry $entry): array {
            return [
                'id' => $entry->getId() ?? 0,
                'customer' => $entry->getCustomerProfile()->getCompany()->getName(),
                'objectLabel' => $entry->getObjectLabel(),
                'fieldName' => $entry->getFieldName(),
                'oldValue' => $entry->getOldValue() ?? '-',
                'newValue' => $entry->getNewValue() ?? '-',
                'changedBy' => $entry->getChangedBy(),
                'changedAt' => $entry->getChangedAt()->format('Y-m-d H:i'),
                'ticketReference' => $entry->getTicket()?->getReference(),
            ];
        }, $entries);
    }

    /**
     * @param list<array<string, mixed>> $companyRows
     * @param list<array<string, mixed>> $ticketRows
     * @param list<array<string, mixed>> $numberRows
     * @param list<array<string, mixed>> $extensionRows
     * @return list<array<string, string|int>>
     */
    private function buildSearchResults(?string $query, array $companyRows, array $ticketRows, array $numberRows, array $extensionRows): array
    {
        $needle = mb_strtolower(trim((string) $query));
        if ('' === $needle) {
            return [];
        }

        $results = [];

        foreach ($companyRows as $company) {
            if ($this->matches($company['name'], $needle) || $this->matches($company['primaryEmail'], $needle)) {
                $results[] = [
                    'type' => 'Kund',
                    'id' => (int) $company['id'],
                    'label' => (string) $company['name'],
                    'detail' => sprintf('%d oppna telefoniarenden', $company['openTicketCount']),
                ];
            }
        }

        foreach ($ticketRows as $ticket) {
            if (
                $this->matches($ticket['reference'], $needle)
                || $this->matches($ticket['subject'], $needle)
                || $this->matches($ticket['company'], $needle)
                || $this->matches($ticket['category'], $needle)
            ) {
                $results[] = [
                    'type' => 'Arende',
                    'id' => (int) $ticket['id'],
                    'label' => (string) $ticket['reference'],
                    'detail' => (string) $ticket['subject'],
                ];
            }
        }

        foreach ($numberRows as $number) {
            if (
                $this->matches($number['phoneNumber'], $needle)
                || $this->matches($number['customer'], $needle)
                || $this->matches($number['displayName'], $needle)
            ) {
                $results[] = [
                    'type' => 'Nummer',
                    'id' => (int) $number['id'],
                    'label' => (string) $number['phoneNumber'],
                    'detail' => sprintf('%s - %s', $number['type'], $number['customer']),
                ];
            }
        }

        foreach ($extensionRows as $extension) {
            if (
                $this->matches($extension['extensionNumber'], $needle)
                || $this->matches($extension['displayName'], $needle)
                || $this->matches($extension['customer'], $needle)
            ) {
                $results[] = [
                    'type' => 'Anknytning',
                    'id' => (int) $extension['id'],
                    'label' => (string) $extension['extensionNumber'],
                    'detail' => sprintf('%s - %s', $extension['displayName'], $extension['customer']),
                ];
            }
        }

        return array_slice($results, 0, 12);
    }

    private function matches(mixed $value, string $needle): bool
    {
        return str_contains(mb_strtolower((string) $value), $needle);
    }
}
