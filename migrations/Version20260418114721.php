<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418114721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_intake_templates AS SELECT id, category_id, name, description, request_type, customer_type, is_active, created_at, updated_at, version_family, version_number, is_current_version FROM ticket_intake_templates');
        $this->addSql('DROP TABLE ticket_intake_templates');
        $this->addSql('CREATE TABLE ticket_intake_templates (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, category_id INTEGER DEFAULT NULL, name VARCHAR(180) NOT NULL, description VARCHAR(255) DEFAULT NULL, request_type VARCHAR(255) NOT NULL, customer_type VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, version_family VARCHAR(36) NOT NULL, version_number INTEGER DEFAULT 1 NOT NULL, is_current_version BOOLEAN DEFAULT 1 NOT NULL, default_sla_policy_id INTEGER DEFAULT NULL, CONSTRAINT FK_D7F1A0F312469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54A5581F1568807 FOREIGN KEY (default_sla_policy_id) REFERENCES sla_policies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_intake_templates (id, category_id, name, description, request_type, customer_type, is_active, created_at, updated_at, version_family, version_number, is_current_version) SELECT id, category_id, name, description, request_type, customer_type, is_active, created_at, updated_at, version_family, version_number, is_current_version FROM __temp__ticket_intake_templates');
        $this->addSql('DROP TABLE __temp__ticket_intake_templates');
        $this->addSql('CREATE INDEX IDX_54A5581F12469DE2 ON ticket_intake_templates (category_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54A5581F5E237E06 ON ticket_intake_templates (name)');
        $this->addSql('CREATE INDEX IDX_54A5581F1568807 ON ticket_intake_templates (default_sla_policy_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_intake_templates AS SELECT id, name, version_family, version_number, is_current_version, description, request_type, customer_type, is_active, created_at, updated_at, category_id FROM ticket_intake_templates');
        $this->addSql('DROP TABLE ticket_intake_templates');
        $this->addSql('CREATE TABLE ticket_intake_templates (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(180) NOT NULL, version_family VARCHAR(36) NOT NULL, version_number INTEGER DEFAULT 1 NOT NULL, is_current_version BOOLEAN DEFAULT 1 NOT NULL, description VARCHAR(255) DEFAULT NULL, request_type VARCHAR(255) NOT NULL, customer_type VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INTEGER DEFAULT NULL, CONSTRAINT FK_54A5581F12469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_intake_templates (id, name, version_family, version_number, is_current_version, description, request_type, customer_type, is_active, created_at, updated_at, category_id) SELECT id, name, version_family, version_number, is_current_version, description, request_type, customer_type, is_active, created_at, updated_at, category_id FROM __temp__ticket_intake_templates');
        $this->addSql('DROP TABLE __temp__ticket_intake_templates');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54A5581F5E237E06 ON ticket_intake_templates (name)');
        $this->addSql('CREATE INDEX IDX_54A5581F12469DE2 ON ticket_intake_templates (category_id)');
    }
}
