<?php

declare(strict_types=1);

namespace App\Module\KnowledgeBase\Enum;

enum KnowledgeBaseEntryType: string
{
    case ARTICLE = 'article';
    case SMART_TIP = 'smart_tip';
    case FAQ = 'faq';

    public function label(): string
    {
        return match ($this) {
            self::ARTICLE => 'Kunskapsartikel',
            self::SMART_TIP => 'Smart tips',
            self::FAQ => 'Vanlig fråga',
        };
    }
}
