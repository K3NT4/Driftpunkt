<?php

declare(strict_types=1);

namespace App\Module\Identity\Service;

use App\Module\Identity\Entity\User;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class MfaSessionManager
{
    private const VERIFIED_USER_ID_KEY = '_mfa_verified_user_id';

    public function markVerified(SessionInterface $session, User $user): void
    {
        $userId = $user->getId();
        if (null === $userId) {
            $this->clear($session);

            return;
        }

        $session->set(self::VERIFIED_USER_ID_KEY, $userId);
    }

    public function isVerified(SessionInterface $session, User $user): bool
    {
        $userId = $user->getId();
        if (null === $userId) {
            return false;
        }

        return $session->get(self::VERIFIED_USER_ID_KEY) === $userId;
    }

    public function clear(SessionInterface $session): void
    {
        $session->remove(self::VERIFIED_USER_ID_KEY);
    }
}
