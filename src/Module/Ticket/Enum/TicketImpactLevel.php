<?php

declare(strict_types=1);

namespace App\Module\Ticket\Enum;

enum TicketImpactLevel: string
{
    case SINGLE_USER = 'single_user';
    case TEAM = 'team';
    case DEPARTMENT = 'department';
    case COMPANY = 'company';
    case CRITICAL_SERVICE = 'critical_service';

    public function label(): string
    {
        return match ($this) {
            self::SINGLE_USER => 'En användare',
            self::TEAM => 'Ett team',
            self::DEPARTMENT => 'En avdelning',
            self::COMPANY => 'Hela företaget',
            self::CRITICAL_SERVICE => 'Kritisk tjänst',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SINGLE_USER => '#64748b',
            self::TEAM => '#1d4ed8',
            self::DEPARTMENT => '#7c3aed',
            self::COMPANY => '#b45309',
            self::CRITICAL_SERVICE => '#b42318',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::SINGLE_USER => 10,
            self::TEAM => 20,
            self::DEPARTMENT => 30,
            self::COMPANY => 40,
            self::CRITICAL_SERVICE => 50,
        };
    }
}
