<?php

declare(strict_types=1);

namespace App\Module\Ticket\Enum;

enum TicketEscalationLevel: string
{
    case NONE = 'none';
    case TEAM = 'team';
    case LEAD = 'lead';
    case INCIDENT = 'incident';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Ingen eskalering',
            self::TEAM => 'Teamnivå',
            self::LEAD => 'Teamledare',
            self::INCIDENT => 'Större incident',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::NONE => 'Ingen',
            self::TEAM => 'Team',
            self::LEAD => 'Lead',
            self::INCIDENT => 'Incident',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NONE => '#64748b',
            self::TEAM => '#7c3aed',
            self::LEAD => '#b45309',
            self::INCIDENT => '#b42318',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::NONE => 10,
            self::TEAM => 20,
            self::LEAD => 30,
            self::INCIDENT => 40,
        };
    }
}
