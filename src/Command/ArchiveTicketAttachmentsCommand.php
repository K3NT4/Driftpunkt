<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\System\Service\SystemSettings;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Service\TicketAttachmentArchiver;
use App\Module\Ticket\Service\TicketAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:archive-ticket-attachments',
    description: 'Archives local attachments for resolved and closed tickets according to the admin policy.',
)]
final class ArchiveTicketAttachmentsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SystemSettings $systemSettings,
        private readonly TicketAttachmentArchiver $ticketAttachmentArchiver,
        private readonly TicketAuditLogger $ticketAuditLogger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Override how many days after closing attachments should be archived.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Run even if zip-arkivering is disabled in admin settings.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $settings = $this->systemSettings->getTicketAttachmentSettings();
        $force = (bool) $input->getOption('force');

        if (!$settings['zipArchivingEnabled'] && !$force) {
            $io->success('Zip-arkivering för ticketbilagor är avstängd i admininställningarna. Ingen körning behövdes.');

            return Command::SUCCESS;
        }

        $daysOption = $input->getOption('days');
        $archiveAfterDays = null !== $daysOption ? max(0, (int) $daysOption) : $settings['zipArchiveAfterDays'];
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d days', $archiveAfterDays));

        /** @var list<Ticket> $tickets */
        $tickets = $this->entityManager->getRepository(Ticket::class)->createQueryBuilder('ticket')
            ->leftJoin('ticket.comments', 'comments')->addSelect('comments')
            ->leftJoin('comments.attachments', 'attachments')->addSelect('attachments')
            ->andWhere('ticket.status IN (:closedStatuses)')
            ->andWhere('ticket.updatedAt <= :cutoff')
            ->setParameter('closedStatuses', [TicketStatus::RESOLVED, TicketStatus::CLOSED])
            ->setParameter('cutoff', $cutoff)
            ->orderBy('ticket.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();

        $processedTickets = 0;
        $archivedAttachments = 0;

        foreach ($tickets as $ticket) {
            $count = $this->ticketAttachmentArchiver->archiveLocalAttachmentsForClosedTicket($ticket);
            if ($count <= 0) {
                continue;
            }

            ++$processedTickets;
            $archivedAttachments += $count;
            $this->entityManager->flush();
            $this->ticketAuditLogger->log(
                $ticket,
                'ticket_attachments_archived',
                sprintf('%d bilagor arkiverades i zip via automatisk städning.', $count),
            );
        }

        $io->success(sprintf(
            'Bilagearkivering körd för %d stängda tickets. %d tickets fick zip-arkivering och %d bilagor packades.',
            \count($tickets),
            $processedTickets,
            $archivedAttachments,
        ));

        return Command::SUCCESS;
    }
}
