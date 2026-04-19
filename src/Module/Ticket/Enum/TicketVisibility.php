<?php

declare(strict_types=1);

namespace App\Module\Ticket\Enum;

enum TicketVisibility: string
{
    case PRIVATE = 'private';
    case COMPANY_SHARED = 'company_shared';
    case INTERNAL_ONLY = 'internal_only';

    public function label(): string
    {
        return match ($this) {
            self::PRIVATE => 'Privat',
            self::COMPANY_SHARED => 'Delad inom företag',
            self::INTERNAL_ONLY => 'Endast intern',
        };
    }
}
