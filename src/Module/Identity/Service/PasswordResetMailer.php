<?php

declare(strict_types=1);

namespace App\Module\Identity\Service;

use App\Module\Identity\Entity\User;
use App\Module\Mail\Service\ConfiguredMailer;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class PasswordResetMailer
{
    public function __construct(
        private readonly ConfiguredMailer $mailer,
        private readonly Environment $twig,
    ) {
    }

    public function sendResetLink(User $user, string $resetUrl): void
    {
        $context = [
            'user' => $user,
            'resetUrl' => $resetUrl,
            'expiresInMinutes' => 60,
        ];

        $this->mailer->send(
            (new Email())
                ->to($user->getEmail())
                ->subject('Återställ ditt lösenord')
                ->text($this->twig->render('emails/password_reset.txt.twig', $context))
                ->html($this->twig->render('emails/password_reset.html.twig', $context)),
        );
    }
}
