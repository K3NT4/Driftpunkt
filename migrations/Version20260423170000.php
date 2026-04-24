<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20260423170000 extends AbstractMigration
{
    private const SEEDED_EMAIL = 'kenta@spelhubben.se';
    private const SEEDED_PASSWORD_HASH = '$2y$12$T1UxEgiLAk6T7XvD7ELURuXSGBXPuJb92UL3wQSQdYfesPCGTaNaq';

    public function getDescription(): string
    {
        return 'Seeds a fixed super admin account for the primary installation owner';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $this->addSql(
            <<<'SQL'
UPDATE users
SET type = 'super_admin',
    password = :password,
    is_active = 1,
    mfa_enabled = 0,
    email_notifications_enabled = 1,
    updated_at = CURRENT_TIMESTAMP
WHERE email = :email
SQL,
            [
                'email' => self::SEEDED_EMAIL,
                'password' => self::SEEDED_PASSWORD_HASH,
            ],
        );

        $this->addSql(
            <<<'SQL'
INSERT INTO users (
    public_id,
    email,
    roles,
    password,
    first_name,
    last_name,
    type,
    is_active,
    mfa_enabled,
    email_notifications_enabled,
    company_id,
    technician_team_id,
    created_at,
    updated_at
)
SELECT
    :public_id,
    :email,
    :roles,
    :password,
    'Kenta',
    'Seed Admin',
    'super_admin',
    1,
    0,
    1,
    NULL,
    NULL,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1
    FROM users
    WHERE email = :email
)
SQL,
            [
                'public_id' => Uuid::v7()->toBinary(),
                'email' => self::SEEDED_EMAIL,
                'roles' => '[]',
                'password' => self::SEEDED_PASSWORD_HASH,
            ],
            [
                'public_id' => Types::BINARY,
            ],
        );
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $this->addSql(
            'DELETE FROM users WHERE email = :email',
            ['email' => self::SEEDED_EMAIL],
        );
    }
}
