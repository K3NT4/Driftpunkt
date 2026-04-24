<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds MFA secret storage for TOTP QR setup';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');
        if ($table->hasColumn('mfa_secret')) {
            return;
        }

        $this->addSql('ALTER TABLE users ADD mfa_secret VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('users')) {
            return;
        }

        $table = $schema->getTable('users');
        if (!$table->hasColumn('mfa_secret')) {
            return;
        }

        $this->addSql('ALTER TABLE users DROP COLUMN mfa_secret');
    }
}
