<?php

declare(strict_types=1);

namespace App\Module\Ticket\Enum;

enum ImportedTicketPersonRole: string
{
    case REQUESTER = 'requester';
    case ASSIGNEE = 'assignee';

    public function label(): string
    {
        return match ($this) {
            self::REQUESTER => 'Begärd av',
            self::ASSIGNEE => 'Ansvarig tekniker',
        };
    }
}
