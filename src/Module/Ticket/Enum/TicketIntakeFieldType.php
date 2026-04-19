<?php

declare(strict_types=1);

namespace App\Module\Ticket\Enum;

enum TicketIntakeFieldType: string
{
    case TEXT = 'text';
    case SELECT = 'select';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Fri text',
            self::SELECT => 'Valbar lista',
        };
    }
}
