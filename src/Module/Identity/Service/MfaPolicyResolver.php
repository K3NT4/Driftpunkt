<?php

declare(strict_types=1);

namespace App\Module\Identity\Service;

use App\Module\Identity\Entity\User;
use App\Module\Identity\Enum\UserType;
use App\Module\System\Service\SystemSettings;

final class MfaPolicyResolver
{
    public function __construct(
        private readonly SystemSettings $systemSettings,
    ) {
    }

    public function isMfaAvailable(User $user): bool
    {
        $settings = $this->systemSettings->getMfaSettings();

        return match ($user->getType()) {
            UserType::SUPER_ADMIN, UserType::ADMIN => $settings['adminEnabled'],
            UserType::TECHNICIAN => $settings['technicianEnabled'],
            UserType::CUSTOMER, UserType::PRIVATE_CUSTOMER => $settings['customerEnabled'],
        };
    }

    public function requiresMfa(User $user): bool
    {
        return $user->isMfaEnabled() && $this->isMfaAvailable($user);
    }
}
