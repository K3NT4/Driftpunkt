<?php

declare(strict_types=1);

namespace App\Command;

use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketStatus;
use App\Module\Ticket\Service\TicketResponseNotifier;
use App\Module\System\Service\SystemSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-ticket-sla',
    description: 'Checks SLA deadlines and sends reminders for tickets that are nearing or breaching them.',
)]
final class CheckTicketSlaCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TicketResponseNotifier $ticketResponseNotifier,
        private readonly SystemSettings $systemSettings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('first-response-warning-hours', null, InputOption::VALUE_REQUIRED, 'Warn this many hours before first response SLA is due. Defaults to the admin setting.')
            ->addOption('resolution-warning-hours', null, InputOption::VALUE_REQUIRED, 'Warn this many hours before resolution SLA is due. Defaults to the admin setting.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $settings = $this->systemSettings->getSlaWarningSettings();
        $firstResponseWarningHours = max(1, (int) ($input->getOption('first-response-warning-hours') ?? $settings['firstResponseWarningHours']));
        $resolutionWarningHours = max(1, (int) ($input->getOption('resolution-warning-hours') ?? $settings['resolutionWarningHours']));

        /** @var list<Ticket> $tickets */
        $tickets = $this->entityManager->getRepository(Ticket::class)->createQueryBuilder('ticket')
            ->leftJoin('ticket.slaPolicy', 'slaPolicy')->addSelect('slaPolicy')
            ->leftJoin('ticket.assignee', 'assignee')->addSelect('assignee')
            ->andWhere('ticket.slaPolicy IS NOT NULL')
            ->andWhere('ticket.status NOT IN (:closedStatuses)')
            ->setParameter('closedStatuses', [TicketStatus::RESOLVED, TicketStatus::CLOSED])
            ->getQuery()
            ->getResult();

        $now = new \DateTimeImmutable();
        $notificationsTriggered = 0;

        foreach ($tickets as $ticket) {
            $firstResponseWarningWindow = $ticket->getSlaPolicy()?->getEffectiveFirstResponseWarningHours($firstResponseWarningHours) ?? $firstResponseWarningHours;
            $resolutionWarningWindow = $ticket->getSlaPolicy()?->getEffectiveResolutionWarningHours($resolutionWarningHours) ?? $resolutionWarningHours;

            $firstResponseDueAt = $ticket->getFirstResponseDueAt();
            if (!$ticket->hasFirstResponse() && $firstResponseDueAt instanceof \DateTimeImmutable) {
                if ($firstResponseDueAt <= $now) {
                    if ($this->ticketResponseNotifier->notifySlaReminder(
                        $ticket,
                        'sla_first_response_breached',
                        sprintf('[%s] SLA bruten för första svar', $ticket->getReference()),
                        sprintf('Ticketen har passerat SLA för första svar sedan %s och behöver omedelbar hantering.', $firstResponseDueAt->format('Y-m-d H:i')),
                        'SLA bruten',
                    )) {
                        ++$notificationsTriggered;
                    }
                } elseif ($firstResponseDueAt <= $now->modify(sprintf('+%d hours', $firstResponseWarningWindow))) {
                    if ($this->ticketResponseNotifier->notifySlaReminder(
                        $ticket,
                        'sla_first_response_due_soon',
                        sprintf('[%s] SLA närmar sig för första svar', $ticket->getReference()),
                        sprintf('Ticketen behöver ett första svar senast %s enligt vald SLA-policy.', $firstResponseDueAt->format('Y-m-d H:i')),
                        'SLA snart',
                    )) {
                        ++$notificationsTriggered;
                    }
                }
            }

            $resolutionDueAt = $ticket->getResolutionDueAt();
            if ($resolutionDueAt instanceof \DateTimeImmutable) {
                if ($resolutionDueAt <= $now) {
                    if ($this->ticketResponseNotifier->notifySlaReminder(
                        $ticket,
                        'sla_resolution_breached',
                        sprintf('[%s] SLA bruten för lösningstid', $ticket->getReference()),
                        sprintf('Ticketen har passerat SLA för lösningstid sedan %s och behöver eskaleras eller avslutas.', $resolutionDueAt->format('Y-m-d H:i')),
                        'SLA bruten',
                    )) {
                        ++$notificationsTriggered;
                    }
                } elseif ($resolutionDueAt <= $now->modify(sprintf('+%d hours', $resolutionWarningWindow))) {
                    if ($this->ticketResponseNotifier->notifySlaReminder(
                        $ticket,
                        'sla_resolution_due_soon',
                        sprintf('[%s] SLA närmar sig för lösningstid', $ticket->getReference()),
                        sprintf('Ticketen behöver lösas senast %s enligt vald SLA-policy.', $resolutionDueAt->format('Y-m-d H:i')),
                        'SLA snart',
                    )) {
                        ++$notificationsTriggered;
                    }
                }
            }
        }

        $io->success(sprintf('SLA-kontroll körd för %d tickets. %d notifieringsförsök triggrades.', \count($tickets), $notificationsTriggered));

        return Command::SUCCESS;
    }
}
