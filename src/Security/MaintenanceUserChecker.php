<?php

declare(strict_types=1);

namespace App\Security;

use App\Module\Identity\Entity\User;
use App\Module\Maintenance\Service\MaintenanceMode;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class MaintenanceUserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly MaintenanceMode $maintenanceMode,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User || !$this->maintenanceMode->getState()['effectiveEnabled']) {
            return;
        }

        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        throw new CustomUserMessageAccountStatusException(
            'Inloggningen ar tillfalligt pausad medan Driftpunkt ar i underhallslage. Forsok igen senare eller folj driftinformationen.',
        );
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
