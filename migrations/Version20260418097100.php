<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418097100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalizes SQLite schema for ticket intake fields';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $this->addSql('PRAGMA foreign_keys = OFF');
        $this->addSql('ALTER TABLE ticket_intake_fields RENAME TO __temp__ticket_intake_fields');
        $this->addSql('CREATE TABLE ticket_intake_fields (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, request_type VARCHAR(255) NOT NULL, field_key VARCHAR(80) NOT NULL, label VARCHAR(120) NOT NULL, help_text VARCHAR(255) DEFAULT NULL, placeholder VARCHAR(120) DEFAULT NULL, is_required BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO ticket_intake_fields (id, request_type, field_key, label, help_text, placeholder, is_required, sort_order, is_active, created_at, updated_at) SELECT id, request_type, field_key, label, help_text, placeholder, is_required, sort_order, is_active, created_at, updated_at FROM __temp__ticket_intake_fields');
        $this->addSql('DROP TABLE __temp__ticket_intake_fields');
        $this->addSql('PRAGMA foreign_keys = ON');
    }

    public function down(Schema $schema): void
    {
    }
}
