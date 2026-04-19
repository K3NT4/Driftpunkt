<?php

declare(strict_types=1);

namespace App\Module\KnowledgeBase\Enum;

enum KnowledgeBaseAudience: string
{
    case PUBLIC = 'public';
    case CUSTOMER = 'customer';
    case BOTH = 'both';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'Publik',
            self::CUSTOMER => 'Endast kund',
            self::BOTH => 'Publik och kund',
        };
    }
}
