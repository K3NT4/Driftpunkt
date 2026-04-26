<?php

declare(strict_types=1);

namespace App\Module\Ticket\Service;

use App\Module\Identity\Entity\Company;
use App\Module\Mail\Service\ConfiguredMailer;
use App\Module\Notification\Entity\NotificationLog;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Enum\TicketVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class CompanyMonthlyReportMailer
{
    private const EVENT_TYPE = 'company_monthly_report';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfiguredMailer $mailer,
        private readonly TicketReportBuilder $ticketReportBuilder,
        private readonly Environment $twig,
    ) {
    }

    public function send(Company $company, \DateTimeImmutable $from, \DateTimeImmutable $to, bool $dryRun = false): bool
    {
        $recipientEmail = $company->getMonthlyReportRecipientEmail();
        $subject = sprintf('Månadsrapport för %s %s', $company->getName(), $from->format('Y-m'));

        if (!$company->isActive()) {
            $this->logAndFlush($recipientEmail ?? 'unknown@local', $subject, false, 'Företaget är inaktivt.');

            return false;
        }

        if (!$company->isMonthlyReportEnabled()) {
            $this->logAndFlush($recipientEmail ?? 'unknown@local', $subject, false, 'Månadsrapport är inte aktiverad.');

            return false;
        }

        if (null === $recipientEmail || false === filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $this->logAndFlush($recipientEmail ?? 'unknown@local', $subject, false, 'Ogiltig mottagaradress för månadsrapport.');

            return false;
        }

        $context = $this->buildContext($company, $from, $to);

        if ($dryRun) {
            $this->logAndFlush($recipientEmail, $subject, false, 'Dry-run: månadsrapport hade skickats.');

            return false;
        }

        $this->mailer->send(
            (new Email())
                ->to($recipientEmail)
                ->subject($subject)
                ->text($this->twig->render('emails/company_monthly_report.txt.twig', $context))
                ->html($this->twig->render('emails/company_monthly_report.html.twig', $context)),
            $company,
        );

        $company->markMonthlyReportSent();
        $this->log($recipientEmail, $subject, true, 'Månadsrapport skickad.');
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return array{company: Company, report: array<string, mixed>, periodLabel: string}
     */
    private function buildContext(Company $company, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<Ticket> $tickets */
        $tickets = $this->entityManager->getRepository(Ticket::class)->findBy(['company' => $company], ['updatedAt' => 'DESC']);
        $tickets = array_values(array_filter(
            $tickets,
            static fn (Ticket $ticket): bool => TicketVisibility::INTERNAL_ONLY !== $ticket->getVisibility(),
        ));
        $filters = [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'company_id' => (string) $company->getId(),
        ];
        $reportTickets = $this->ticketReportBuilder->filterReportTickets($tickets, $filters, $company);
        $openTickets = $this->ticketReportBuilder->filterOpenTicketsForMonthlySummary($tickets, $filters, $company);

        return [
            'company' => $company,
            'report' => $this->ticketReportBuilder->build($reportTickets, $openTickets),
            'periodLabel' => sprintf('%s - %s', $from->format('Y-m-d'), $to->format('Y-m-d')),
        ];
    }

    private function logAndFlush(string $recipientEmail, string $subject, bool $sent, string $statusMessage): void
    {
        $this->log($recipientEmail, $subject, $sent, $statusMessage);
        $this->entityManager->flush();
    }

    private function log(string $recipientEmail, string $subject, bool $sent, string $statusMessage): void
    {
        $this->entityManager->persist(new NotificationLog(
            self::EVENT_TYPE,
            $recipientEmail,
            $subject,
            $sent,
            $statusMessage,
        ));
    }
}
