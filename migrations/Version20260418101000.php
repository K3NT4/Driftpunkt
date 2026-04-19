<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds optional category and customer type targeting to ticket intake fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket_intake_fields ADD COLUMN category_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket_intake_fields ADD COLUMN customer_type VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_713A9B7F12469DE2 ON ticket_intake_fields (category_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_intake_fields AS SELECT id, request_type, field_key, label, field_type, help_text, placeholder, is_required, sort_order, is_active, select_options, category_id, customer_type, created_at, updated_at FROM ticket_intake_fields');
        $this->addSql('DROP TABLE ticket_intake_fields');
        $this->addSql('CREATE TABLE ticket_intake_fields (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, category_id INTEGER DEFAULT NULL, request_type VARCHAR(255) NOT NULL, field_key VARCHAR(80) NOT NULL, label VARCHAR(120) NOT NULL, field_type VARCHAR(255) NOT NULL DEFAULT \'text\', help_text VARCHAR(255) DEFAULT NULL, placeholder VARCHAR(120) DEFAULT NULL, is_required BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, is_active BOOLEAN NOT NULL, select_options CLOB NOT NULL DEFAULT \'[]\', customer_type VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
, updated_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
, CONSTRAINT FK_713A9B7F12469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON DELETE SET NULL)');
        $this->addSql('INSERT INTO ticket_intake_fields (id, category_id, request_type, field_key, label, field_type, help_text, placeholder, is_required, sort_order, is_active, select_options, customer_type, created_at, updated_at) SELECT id, category_id, request_type, field_key, label, field_type, help_text, placeholder, is_required, sort_order, is_active, select_options, customer_type, created_at, updated_at FROM __temp__ticket_intake_fields');
        $this->addSql('DROP TABLE __temp__ticket_intake_fields');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_713A9B7F997E1D01 ON ticket_intake_fields (field_key)');
        $this->addSql('CREATE INDEX IDX_713A9B7F12469DE2 ON ticket_intake_fields (category_id)');
    }

    public function down(Schema $schema): void
    {
    }
}
