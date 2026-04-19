<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds ticket import templates and import/export run logs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ticket_import_templates (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_by_id INTEGER DEFAULT NULL, name VARCHAR(180) NOT NULL, source_system VARCHAR(64) NOT NULL, configuration CLOB DEFAULT \'[]\' NOT NULL --(DC2Type:json)
        , is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_7BA74B57B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_7BA74B57B03A8386 ON ticket_import_templates (created_by_id)');
        $this->addSql('CREATE TABLE import_export_runs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, actor_id INTEGER DEFAULT NULL, run_type VARCHAR(64) NOT NULL, source_system VARCHAR(64) NOT NULL, source_label VARCHAR(180) NOT NULL, dry_run BOOLEAN NOT NULL, total_items INTEGER NOT NULL, created_items INTEGER NOT NULL, skipped_items INTEGER NOT NULL, duplicate_items INTEGER NOT NULL, status_message VARCHAR(255) NOT NULL, details CLOB DEFAULT \'[]\' NOT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_64B22D2F10D38A0 FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_64B22D2F10D38A0 ON import_export_runs (actor_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE import_export_runs');
        $this->addSql('DROP TABLE ticket_import_templates');
    }
}
