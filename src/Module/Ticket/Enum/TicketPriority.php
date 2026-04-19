<?php

declare(strict_types=1);

namespace App\Module\Ticket\Enum;

enum TicketPriority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Låg',
            self::NORMAL => 'Normal',
            self::HIGH => 'Hög',
            self::CRITICAL => 'Kritisk',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => '#64748b',
            self::NORMAL => '#1d4ed8',
            self::HIGH => '#b45309',
            self::CRITICAL => '#b42318',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::LOW => 10,
            self::NORMAL => 20,
            self::HIGH => 30,
            self::CRITICAL => 40,
        };
    }
}
