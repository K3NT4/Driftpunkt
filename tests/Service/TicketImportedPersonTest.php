<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\Ticket\Entity\ImportedTicketPerson;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\ImportedTicketPersonRole;
use PHPUnit\Framework\TestCase;

final class TicketImportedPersonTest extends TestCase
{
    public function testTicketUsesImportedRequesterAndAssigneeDisplayNamesAsFallback(): void
    {
        $ticket = new Ticket('DP-100', 'Import', 'Importerat');
        $ticket->addImportedPerson(new ImportedTicketPerson(
            $ticket,
            ImportedTicketPersonRole::REQUESTER,
            'Gisle',
            'sharepoint',
            '2',
        ));
        $ticket->addImportedPerson(new ImportedTicketPerson(
            $ticket,
            ImportedTicketPersonRole::ASSIGNEE,
            'Paul',
            'sharepoint',
            '2',
        ));

        self::assertSame('Gisle', $ticket->getRequesterDisplayName());
        self::assertSame('Paul', $ticket->getAssigneeDisplayName());
        self::assertSame('Gisle', $ticket->getImportedRequesterPerson()?->getDisplayName());
        self::assertSame('Paul', $ticket->getImportedAssigneePerson()?->getDisplayName());
    }

    public function testRealUsersWinOverImportedDisplayNames(): void
    {
        $ticket = new Ticket('DP-101', 'Import', 'Importerat');
        $requester = new User('gisle@example.test', 'Gisle', 'Hammervold', UserType::CUSTOMER);
        $assignee = new User('paul@example.test', 'Paul', 'Tekniker', UserType::TECHNICIAN);

        $ticket
            ->setRequester($requester)
            ->setAssignee($assignee)
            ->addImportedPerson(new ImportedTicketPerson(
                $ticket,
                ImportedTicketPersonRole::REQUESTER,
                'Gisle',
                'sharepoint',
                '2',
            ))
            ->addImportedPerson(new ImportedTicketPerson(
                $ticket,
                ImportedTicketPersonRole::ASSIGNEE,
                'Paul',
                'sharepoint',
                '2',
            ));

        self::assertSame('Gisle Hammervold', $ticket->getRequesterDisplayName());
        self::assertSame('Paul Tekniker', $ticket->getAssigneeDisplayName());
    }
}
