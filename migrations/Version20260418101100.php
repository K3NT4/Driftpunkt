<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418101100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalizes SQLite schema for scoped ticket intake fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_intake_fields AS SELECT id, category_id, request_type, field_key, label, field_type, help_text, placeholder, is_required, sort_order, is_active, select_options, customer_type, created_at, updated_at FROM ticket_intake_fields');
        $this->addSql('DROP TABLE ticket_intake_fields');
        $this->addSql("CREATE TABLE ticket_intake_fields (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, category_id INTEGER DEFAULT NULL, request_type VARCHAR(255) NOT NULL, field_key VARCHAR(80) NOT NULL, label VARCHAR(120) NOT NULL, field_type VARCHAR(255) DEFAULT 'text' NOT NULL, help_text VARCHAR(255) DEFAULT NULL, placeholder VARCHAR(120) DEFAULT NULL, is_required BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, is_active BOOLEAN NOT NULL, select_options CLOB DEFAULT '[]' NOT NULL, customer_type VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_713A9B7F12469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)");
        $this->addSql('INSERT INTO ticket_intake_fields (id, category_id, request_type, field_key, label, field_type, help_text, placeholder, is_required, sort_order, is_active, select_options, customer_type, created_at, updated_at) SELECT id, category_id, request_type, field_key, label, field_type, help_text, placeholder, is_required, sort_order, is_active, select_options, customer_type, created_at, updated_at FROM __temp__ticket_intake_fields');
        $this->addSql('DROP TABLE __temp__ticket_intake_fields');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_713A9B7F997E1D01 ON ticket_intake_fields (field_key)');
        $this->addSql('CREATE INDEX IDX_2AECA15212469DE2 ON ticket_intake_fields (category_id)');
    }

    public function down(Schema $schema): void
    {
    }
}
