<?php

declare(strict_types=1);

namespace App\Module\Ticket\Enum;

enum TicketRequestType: string
{
    case INCIDENT = 'incident';
    case SERVICE_REQUEST = 'service_request';
    case CHANGE_REQUEST = 'change_request';
    case ACCESS_REQUEST = 'access_request';
    case BILLING = 'billing';

    public function label(): string
    {
        return match ($this) {
            self::INCIDENT => 'Incident',
            self::SERVICE_REQUEST => 'Serviceförfrågan',
            self::CHANGE_REQUEST => 'Ändringsbegäran',
            self::ACCESS_REQUEST => 'Behörighet',
            self::BILLING => 'Fakturafråga',
        };
    }
}
