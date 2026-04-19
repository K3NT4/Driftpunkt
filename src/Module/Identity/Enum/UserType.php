<?php

declare(strict_types=1);

namespace App\Module\Identity\Enum;

enum UserType: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case TECHNICIAN = 'technician';
    case CUSTOMER = 'customer';
    case PRIVATE_CUSTOMER = 'private_customer';

    /**
     * @return list<string>
     */
    public function defaultRoles(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_TECHNICIAN', 'ROLE_USER'],
            self::ADMIN => ['ROLE_ADMIN', 'ROLE_TECHNICIAN', 'ROLE_USER'],
            self::TECHNICIAN => ['ROLE_TECHNICIAN', 'ROLE_USER'],
            self::CUSTOMER, self::PRIVATE_CUSTOMER => ['ROLE_CUSTOMER', 'ROLE_USER'],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::TECHNICIAN => 'Tekniker',
            self::CUSTOMER => 'Kund',
            self::PRIVATE_CUSTOMER => 'Privatkund',
        };
    }
}
