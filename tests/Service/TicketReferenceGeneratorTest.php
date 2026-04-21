<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\Identity\Entity\Company;
use App\Module\Ticket\Service\TicketReferenceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class TicketReferenceGeneratorTest extends TestCase
{
    public function testSuggestPrefixFromCompanyNameBuildsReadablePrefix(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $generator = new TicketReferenceGenerator($entityManager);

        self::assertSame('VAX', $generator->suggestPrefixFromCompanyName('Vax AB'));
        self::assertSame('NORTEC', $generator->suggestPrefixFromCompanyName('North Tech AB'));
    }

    public function testCustomCompanySequenceUsesOwnPrefixAndCounter(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $generator = new TicketReferenceGenerator($entityManager);
        $company = new Company('Vax AB');
        $company
            ->setUseCustomTicketSequence(true)
            ->setTicketReferencePrefix('VAX')
            ->setTicketSequenceNextNumber(1001);

        self::assertSame(['VAX-1001', 'VAX-1002'], $generator->nextReferences(2, $company));
        self::assertSame(1003, $company->getTicketSequenceNextNumber());
    }
}
