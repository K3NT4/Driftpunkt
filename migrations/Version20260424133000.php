<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424133000 extends AbstractMigration
{
    private const SEEDED_EMAILS = [
        'kenta@spelhubben.se',
        'admin@test.local',
        'tech@test.local',
        'customer@test.local',
    ];

    private const RESERVED_SUPER_ADMIN_EMAIL = 'kenta@spelhubben.se';
    private const RESERVED_SUPER_ADMIN_PASSWORD = 'LYnG79AExTfWn7GHmk2A';

    public function getDescription(): string
    {
        return 'Adds first-login password change enforcement for seeded and admin-created users';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');
        if (!$table->hasColumn('password_change_required')) {
            $this->addSql('ALTER TABLE users ADD password_change_required BOOLEAN DEFAULT 0 NOT NULL');
        }

        $quotedEmails = array_map(
            static fn (string $email): string => "'".str_replace("'", "''", $email)."'",
            self::SEEDED_EMAILS,
        );

        $this->addSql(sprintf(
            'UPDATE users SET password_change_required = 1, updated_at = CURRENT_TIMESTAMP WHERE email IN (%s)',
            implode(', ', $quotedEmails),
        ));

        $this->addSql(
            'UPDATE users SET password = :password, password_change_required = 1, updated_at = CURRENT_TIMESTAMP WHERE email = :email',
            [
                'password' => password_hash(self::RESERVED_SUPER_ADMIN_PASSWORD, \PASSWORD_BCRYPT),
                'email' => self::RESERVED_SUPER_ADMIN_EMAIL,
            ],
        );
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');
        if ($table->hasColumn('password_change_required')) {
            $this->addSql('ALTER TABLE users DROP password_change_required');
        }
    }
}
