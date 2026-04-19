<?php

declare(strict_types=1);

namespace App\Module\Ticket\Enum;

enum TicketStatus: string
{
    case NEW = 'new';
    case OPEN = 'open';
    case PENDING_CUSTOMER = 'pending_customer';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Ny',
            self::OPEN => 'Öppen',
            self::PENDING_CUSTOMER => 'Väntar på kund',
            self::RESOLVED => 'Löst',
            self::CLOSED => 'Stängd',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NEW => '#0f766e',
            self::OPEN => '#1d4ed8',
            self::PENDING_CUSTOMER => '#b45309',
            self::RESOLVED => '#166534',
            self::CLOSED => '#6b7280',
        };
    }
}
