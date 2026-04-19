<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418097000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds configurable intake fields and ticket intake answers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tickets ADD COLUMN intake_answers CLOB NOT NULL DEFAULT '[]'");
        $this->addSql('CREATE TABLE ticket_intake_fields (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, request_type VARCHAR(255) NOT NULL, field_key VARCHAR(80) NOT NULL, label VARCHAR(120) NOT NULL, help_text VARCHAR(255) DEFAULT NULL, placeholder VARCHAR(120) DEFAULT NULL, is_required BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_713A9B7F997E1D01 ON ticket_intake_fields (field_key)');
    }

    public function down(Schema $schema): void
    {
    }
}
