<?php

declare(strict_types=1);

namespace App\Module\News\Enum;

enum NewsCategory: string
{
    case GENERAL = 'general';
    case PLANNED_MAINTENANCE = 'planned_maintenance';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'Nyhet',
            self::PLANNED_MAINTENANCE => 'Planerat underhall',
        };
    }
}
