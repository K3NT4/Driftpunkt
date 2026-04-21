<?php

declare(strict_types=1);

namespace App\Module\Ticket\Service;

use App\Module\Identity\Entity\User;
use App\Module\Mail\Service\ConfiguredMailer;
use App\Module\Notification\Entity\NotificationLog;
use App\Module\Ticket\Entity\Ticket;
use App\Module\Ticket\Entity\TicketComment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class TicketResponseNotifier
{
    public function __construct(
        private readonly ConfiguredMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly Environment $twig,
    ) {
    }

    public function notifyCustomerWaitingForReply(Ticket $ticket, TicketComment $comment): void
    {
        $recipient = $ticket->getRequester();
        $subject = sprintf('[%s] Ticket väntar på ditt svar', $ticket->getReference());

        if (!$recipient instanceof User) {
            $this->logNotification('customer_waiting_reply', $ticket, null, '', $subject, false, 'Ingen kundmottagare satt på ticket.');
            return;
        }

        if (!$recipient->isEmailNotificationsEnabled()) {
            $this->logNotification('customer_waiting_reply', $ticket, $recipient, $recipient->getEmail(), $subject, false, 'Mailnotiser är avstängda för mottagaren.');
            return;
        }

        $this->sendTicketEmail(
            eventType: 'customer_waiting_reply',
            recipient: $recipient,
            ticket: $ticket,
            subject: $subject,
            intro: 'En tekniker har svarat och ticketen väntar nu på ditt svar.',
            comment: $comment,
            badge: 'Väntar på kund',
        );
    }

    public function notifyTechnicianWaitingForReply(Ticket $ticket, TicketComment $comment): void
    {
        $recipient = $ticket->getAssignee();
        $subject = sprintf('[%s] Ticket väntar på teknikersvar', $ticket->getReference());

        if (!$recipient instanceof User) {
            $this->logNotification('technician_waiting_reply', $ticket, null, '', $subject, false, 'Ingen tekniker är tilldelad ticketen.');
            return;
        }

        if (!$recipient->isEmailNotificationsEnabled()) {
            $this->logNotification('technician_waiting_reply', $ticket, $recipient, $recipient->getEmail(), $subject, false, 'Mailnotiser är avstängda för mottagaren.');
            return;
        }

        $this->sendTicketEmail(
            eventType: 'technician_waiting_reply',
            recipient: $recipient,
            ticket: $ticket,
            subject: $subject,
            intro: 'Kunden har svarat och ticketen väntar nu på teknikersvar.',
            comment: $comment,
            badge: 'Väntar på tekniker',
        );
    }

    public function notifyAssignedTechnician(Ticket $ticket, User $recipient, string $intro): void
    {
        $subject = sprintf('[%s] Ticket tilldelad dig', $ticket->getReference());

        if (!$recipient->isEmailNotificationsEnabled()) {
            $this->logNotification('ticket_assigned', $ticket, $recipient, $recipient->getEmail(), $subject, false, 'Mailnotiser är avstängda för mottagaren.');
            return;
        }

        $this->sendTicketEmail(
            eventType: 'ticket_assigned',
            recipient: $recipient,
            ticket: $ticket,
            subject: $subject,
            intro: $intro,
            comment: null,
            badge: 'Tilldelad',
        );
    }

    public function notifyCustomerTicketUpdate(Ticket $ticket, TicketComment $comment): void
    {
        $recipient = $ticket->getRequester();
        $subject = sprintf('[%s] Ticket uppdaterad', $ticket->getReference());

        if (!$recipient instanceof User) {
            $this->logNotification('customer_ticket_update', $ticket, null, '', $subject, false, 'Ingen kundmottagare satt på ticket.');
            return;
        }

        if (!$recipient->isEmailNotificationsEnabled()) {
            $this->logNotification('customer_ticket_update', $ticket, $recipient, $recipient->getEmail(), $subject, false, 'Mailnotiser är avstängda för mottagaren.');
            return;
        }

        $this->sendTicketEmail(
            eventType: 'customer_ticket_update',
            recipient: $recipient,
            ticket: $ticket,
            subject: $subject,
            intro: 'En tekniker har uppdaterat ditt ärende med ny information.',
            comment: $comment,
            badge: 'Uppdatering',
        );
    }

    public function notifySlaReminder(Ticket $ticket, string $eventType, string $subject, string $intro, string $badge): bool
    {
        $recipient = $ticket->getAssignee();

        if ($this->hasExistingNotification($eventType, $ticket, $recipient)) {
            return false;
        }

        if (!$recipient instanceof User) {
            $this->logNotification($eventType, $ticket, null, '', $subject, false, 'Ingen tekniker är tilldelad ticketen.');

            return true;
        }

        if (!$recipient->isEmailNotificationsEnabled()) {
            $this->logNotification($eventType, $ticket, $recipient, $recipient->getEmail(), $subject, false, 'Mailnotiser är avstängda för mottagaren.');

            return true;
        }

        $this->sendTicketEmail(
            eventType: $eventType,
            recipient: $recipient,
            ticket: $ticket,
            subject: $subject,
            intro: $intro,
            comment: null,
            badge: $badge,
        );

        return true;
    }

    public function notifyIncomingMailTicketReceived(
        Ticket $ticket,
        string $recipientEmail,
        ?string $recipientName = null,
        ?User $recipient = null,
    ): void {
        $normalizedEmail = mb_strtolower(trim($recipientEmail));
        $subject = sprintf('[%s] Vi har tagit emot ditt ärende', $ticket->getReference());

        if (false === filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            $this->logNotification('incoming_mail_ticket_received', $ticket, $recipient, $normalizedEmail, $subject, false, 'Ogiltig mottagaradress för inkommande bekräftelse.');

            return;
        }

        $context = [
            'recipientName' => $recipientName ?: $normalizedEmail,
            'ticket' => $ticket,
        ];

        $this->mailer->send(
            (new Email())
                ->to($normalizedEmail)
                ->subject($subject)
                ->text($this->twig->render('emails/incoming_mail_ticket_received.txt.twig', $context))
                ->html($this->twig->render('emails/incoming_mail_ticket_received.html.twig', $context)),
            $ticket->getCompany(),
        );

        $this->logNotification('incoming_mail_ticket_received', $ticket, $recipient, $normalizedEmail, $subject, true, 'Inkommande bekräftelse skickad.');
    }

    private function sendTicketEmail(
        string $eventType,
        User $recipient,
        Ticket $ticket,
        string $subject,
        string $intro,
        ?TicketComment $comment,
        string $badge,
    ): void {
        $context = [
            'recipient' => $recipient,
            'ticket' => $ticket,
            'intro' => $intro,
            'comment' => $comment,
            'badge' => $badge,
        ];

        $this->mailer->send(
            (new Email())
                ->to($recipient->getEmail())
                ->subject($subject)
                ->text($this->twig->render('emails/ticket_notification.txt.twig', $context))
                ->html($this->twig->render('emails/ticket_notification.html.twig', $context)),
            $ticket->getCompany(),
        );

        $this->logNotification($eventType, $ticket, $recipient, $recipient->getEmail(), $subject, true, 'Mail skickat.');
    }

    private function logNotification(
        string $eventType,
        Ticket $ticket,
        ?User $recipient,
        string $recipientEmail,
        string $subject,
        bool $sent,
        string $statusMessage,
    ): void {
        $log = new NotificationLog(
            $eventType,
            '' !== $recipientEmail ? $recipientEmail : 'unknown@local',
            $subject,
            $sent,
            $statusMessage,
        );
        $log->setTicket($ticket);
        $log->setRecipient($recipient);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    private function hasExistingNotification(string $eventType, Ticket $ticket, ?User $recipient): bool
    {
        return null !== $this->entityManager->getRepository(NotificationLog::class)->findOneBy([
            'eventType' => $eventType,
            'ticket' => $ticket,
            'recipient' => $recipient,
        ]);
    }
}
