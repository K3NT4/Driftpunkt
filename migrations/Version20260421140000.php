<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds revoke metadata to addon release logs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE addon_release_logs ADD revoked_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE addon_release_logs ADD revoked_by_email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE addon_release_logs ADD revoke_notes CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE addon_release_logs DROP revoke_notes');
        $this->addSql('ALTER TABLE addon_release_logs DROP revoked_by_email');
        $this->addSql('ALTER TABLE addon_release_logs DROP revoked_at');
    }
}
