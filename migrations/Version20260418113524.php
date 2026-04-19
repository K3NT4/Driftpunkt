<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418113524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_intake_fields AS SELECT id, template_id, category_id, request_type, field_key, label, field_type, help_text, placeholder, is_required, sort_order, is_active, select_options, customer_type, depends_on_field_key, depends_on_field_value, created_at, updated_at FROM ticket_intake_fields');
        $this->addSql('DROP TABLE ticket_intake_fields');
        $this->addSql('CREATE TABLE ticket_intake_fields (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, template_id INTEGER DEFAULT NULL, category_id INTEGER DEFAULT NULL, request_type VARCHAR(255) NOT NULL, field_key VARCHAR(80) NOT NULL, label VARCHAR(120) NOT NULL, field_type VARCHAR(255) DEFAULT \'text\' NOT NULL, help_text VARCHAR(255) DEFAULT NULL, placeholder VARCHAR(120) DEFAULT NULL, is_required BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, is_active BOOLEAN NOT NULL, select_options CLOB DEFAULT \'[]\' NOT NULL, customer_type VARCHAR(255) DEFAULT NULL, depends_on_field_key VARCHAR(80) DEFAULT NULL, depends_on_field_value VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_713A9B7F5DA0FB8 FOREIGN KEY (template_id) REFERENCES ticket_intake_templates (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_713A9B7F12469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_intake_fields (id, template_id, category_id, request_type, field_key, label, field_type, help_text, placeholder, is_required, sort_order, is_active, select_options, customer_type, depends_on_field_key, depends_on_field_value, created_at, updated_at) SELECT id, template_id, category_id, request_type, field_key, label, field_type, help_text, placeholder, is_required, sort_order, is_active, select_options, customer_type, depends_on_field_key, depends_on_field_value, created_at, updated_at FROM __temp__ticket_intake_fields');
        $this->addSql('DROP TABLE __temp__ticket_intake_fields');
        $this->addSql('CREATE INDEX IDX_2AECA1525DA0FB8 ON ticket_intake_fields (template_id)');
        $this->addSql('CREATE INDEX IDX_2AECA15212469DE2 ON ticket_intake_fields (category_id)');
        $this->addSql('ALTER TABLE ticket_intake_templates ADD COLUMN version_family VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE ticket_intake_templates ADD COLUMN version_number INTEGER DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE ticket_intake_templates ADD COLUMN is_current_version BOOLEAN DEFAULT 1 NOT NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__tickets AS SELECT id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority, sla_policy_id, escalation_level, assigned_team_id, category_id, request_type, impact_level, intake_answers FROM tickets');
        $this->addSql('DROP TABLE tickets');
        $this->addSql('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, reference VARCHAR(32) NOT NULL, subject VARCHAR(180) NOT NULL, summary CLOB NOT NULL, status VARCHAR(255) NOT NULL, visibility VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id INTEGER DEFAULT NULL, requester_id INTEGER DEFAULT NULL, assignee_id INTEGER DEFAULT NULL, priority VARCHAR(255) NOT NULL, sla_policy_id INTEGER DEFAULT NULL, escalation_level VARCHAR(255) NOT NULL, assigned_team_id INTEGER DEFAULT NULL, category_id INTEGER DEFAULT NULL, request_type VARCHAR(255) DEFAULT \'incident\' NOT NULL, impact_level VARCHAR(255) DEFAULT \'single_user\' NOT NULL, intake_answers CLOB DEFAULT \'[]\' NOT NULL, intake_template_id INTEGER DEFAULT NULL, CONSTRAINT FK_54469DF437771414 FOREIGN KEY (sla_policy_id) REFERENCES sla_policies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4ED442CF4 FOREIGN KEY (requester_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF459EC7D60 FOREIGN KEY (assignee_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4F14C4107 FOREIGN KEY (assigned_team_id) REFERENCES technician_teams (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF412469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF44A0B775D FOREIGN KEY (intake_template_id) REFERENCES ticket_intake_templates (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tickets (id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority, sla_policy_id, escalation_level, assigned_team_id, category_id, request_type, impact_level, intake_answers) SELECT id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority, sla_policy_id, escalation_level, assigned_team_id, category_id, request_type, impact_level, intake_answers FROM __temp__tickets');
        $this->addSql('DROP TABLE __temp__tickets');
        $this->addSql('CREATE INDEX IDX_54469DF412469DE2 ON tickets (category_id)');
        $this->addSql('CREATE INDEX IDX_54469DF423F46021 ON tickets (assigned_team_id)');
        $this->addSql('CREATE INDEX IDX_54469DF437771414 ON tickets (sla_policy_id)');
        $this->addSql('CREATE INDEX IDX_54469DF459EC7D60 ON tickets (assignee_id)');
        $this->addSql('CREATE INDEX IDX_54469DF4ED442CF4 ON tickets (requester_id)');
        $this->addSql('CREATE INDEX IDX_54469DF4979B1AD6 ON tickets (company_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4AEA34913 ON tickets (reference)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4B5B48B91 ON tickets (public_id)');
        $this->addSql('CREATE INDEX IDX_54469DF44A0B775D ON tickets (intake_template_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_intake_fields AS SELECT id, request_type, field_key, label, field_type, help_text, placeholder, is_required, sort_order, is_active, select_options, customer_type, depends_on_field_key, depends_on_field_value, created_at, updated_at, category_id, template_id FROM ticket_intake_fields');
        $this->addSql('DROP TABLE ticket_intake_fields');
        $this->addSql('CREATE TABLE ticket_intake_fields (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, request_type VARCHAR(255) NOT NULL, field_key VARCHAR(80) NOT NULL, label VARCHAR(120) NOT NULL, field_type VARCHAR(255) DEFAULT \'text\' NOT NULL, help_text VARCHAR(255) DEFAULT NULL, placeholder VARCHAR(120) DEFAULT NULL, is_required BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, is_active BOOLEAN NOT NULL, select_options CLOB DEFAULT \'[]\' NOT NULL, customer_type VARCHAR(255) DEFAULT NULL, depends_on_field_key VARCHAR(80) DEFAULT NULL, depends_on_field_value VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INTEGER DEFAULT NULL, template_id INTEGER DEFAULT NULL, CONSTRAINT FK_2AECA15212469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2AECA1525DA0FB8 FOREIGN KEY (template_id) REFERENCES ticket_intake_templates (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_intake_fields (id, request_type, field_key, label, field_type, help_text, placeholder, is_required, sort_order, is_active, select_options, customer_type, depends_on_field_key, depends_on_field_value, created_at, updated_at, category_id, template_id) SELECT id, request_type, field_key, label, field_type, help_text, placeholder, is_required, sort_order, is_active, select_options, customer_type, depends_on_field_key, depends_on_field_value, created_at, updated_at, category_id, template_id FROM __temp__ticket_intake_fields');
        $this->addSql('DROP TABLE __temp__ticket_intake_fields');
        $this->addSql('CREATE INDEX IDX_2AECA15212469DE2 ON ticket_intake_fields (category_id)');
        $this->addSql('CREATE INDEX IDX_2AECA1525DA0FB8 ON ticket_intake_fields (template_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_713A9B7F997E1D01 ON ticket_intake_fields (field_key)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_intake_templates AS SELECT id, name, description, request_type, customer_type, is_active, created_at, updated_at, category_id FROM ticket_intake_templates');
        $this->addSql('DROP TABLE ticket_intake_templates');
        $this->addSql('CREATE TABLE ticket_intake_templates (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(180) NOT NULL, description VARCHAR(255) DEFAULT NULL, request_type VARCHAR(255) NOT NULL, customer_type VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INTEGER DEFAULT NULL, CONSTRAINT FK_54A5581F12469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_intake_templates (id, name, description, request_type, customer_type, is_active, created_at, updated_at, category_id) SELECT id, name, description, request_type, customer_type, is_active, created_at, updated_at, category_id FROM __temp__ticket_intake_templates');
        $this->addSql('DROP TABLE __temp__ticket_intake_templates');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54A5581F5E237E06 ON ticket_intake_templates (name)');
        $this->addSql('CREATE INDEX IDX_54A5581F12469DE2 ON ticket_intake_templates (category_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__tickets AS SELECT id, public_id, reference, subject, summary, status, visibility, priority, request_type, impact_level, escalation_level, intake_answers, created_at, updated_at, category_id, company_id, requester_id, assignee_id, assigned_team_id, sla_policy_id FROM tickets');
        $this->addSql('DROP TABLE tickets');
        $this->addSql('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, reference VARCHAR(32) NOT NULL, subject VARCHAR(180) NOT NULL, summary CLOB NOT NULL, status VARCHAR(255) NOT NULL, visibility VARCHAR(255) NOT NULL, priority VARCHAR(255) NOT NULL, request_type VARCHAR(255) DEFAULT \'incident\' NOT NULL, impact_level VARCHAR(255) DEFAULT \'single_user\' NOT NULL, escalation_level VARCHAR(255) NOT NULL, intake_answers CLOB DEFAULT \'[]\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INTEGER DEFAULT NULL, company_id INTEGER DEFAULT NULL, requester_id INTEGER DEFAULT NULL, assignee_id INTEGER DEFAULT NULL, assigned_team_id INTEGER DEFAULT NULL, sla_policy_id INTEGER DEFAULT NULL, CONSTRAINT FK_54469DF412469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4ED442CF4 FOREIGN KEY (requester_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF459EC7D60 FOREIGN KEY (assignee_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF423F46021 FOREIGN KEY (assigned_team_id) REFERENCES technician_teams (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF437771414 FOREIGN KEY (sla_policy_id) REFERENCES sla_policies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tickets (id, public_id, reference, subject, summary, status, visibility, priority, request_type, impact_level, escalation_level, intake_answers, created_at, updated_at, category_id, company_id, requester_id, assignee_id, assigned_team_id, sla_policy_id) SELECT id, public_id, reference, subject, summary, status, visibility, priority, request_type, impact_level, escalation_level, intake_answers, created_at, updated_at, category_id, company_id, requester_id, assignee_id, assigned_team_id, sla_policy_id FROM __temp__tickets');
        $this->addSql('DROP TABLE __temp__tickets');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4B5B48B91 ON tickets (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4AEA34913 ON tickets (reference)');
        $this->addSql('CREATE INDEX IDX_54469DF412469DE2 ON tickets (category_id)');
        $this->addSql('CREATE INDEX IDX_54469DF4979B1AD6 ON tickets (company_id)');
        $this->addSql('CREATE INDEX IDX_54469DF4ED442CF4 ON tickets (requester_id)');
        $this->addSql('CREATE INDEX IDX_54469DF459EC7D60 ON tickets (assignee_id)');
        $this->addSql('CREATE INDEX IDX_54469DF423F46021 ON tickets (assigned_team_id)');
        $this->addSql('CREATE INDEX IDX_54469DF437771414 ON tickets (sla_policy_id)');
    }
}
