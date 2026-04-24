<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424120000 extends AbstractMigration
{
    private const SEEDED_EMAIL = 'kenta@spelhubben.se';

    public function getDescription(): string
    {
        return 'Disables MFA on the seeded start admin so first login is not blocked';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $this->addSql(
            'UPDATE users SET mfa_enabled = 0, updated_at = CURRENT_TIMESTAMP WHERE email = :email',
            ['email' => self::SEEDED_EMAIL],
        );
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $this->addSql(
            'UPDATE users SET mfa_enabled = 1, updated_at = CURRENT_TIMESTAMP WHERE email = :email',
            ['email' => self::SEEDED_EMAIL],
        );
    }
}
