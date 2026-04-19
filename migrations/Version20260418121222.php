<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418121222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tickets ADD COLUMN checklist_progress CLOB DEFAULT \'[]\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__tickets AS SELECT id, public_id, reference, subject, summary, status, visibility, priority, request_type, impact_level, escalation_level, intake_answers, created_at, updated_at, category_id, company_id, requester_id, assignee_id, assigned_team_id, sla_policy_id, intake_template_id FROM tickets');
        $this->addSql('DROP TABLE tickets');
        $this->addSql('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, reference VARCHAR(32) NOT NULL, subject VARCHAR(180) NOT NULL, summary CLOB NOT NULL, status VARCHAR(255) NOT NULL, visibility VARCHAR(255) NOT NULL, priority VARCHAR(255) NOT NULL, request_type VARCHAR(255) DEFAULT \'incident\' NOT NULL, impact_level VARCHAR(255) DEFAULT \'single_user\' NOT NULL, escalation_level VARCHAR(255) NOT NULL, intake_answers CLOB DEFAULT \'[]\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INTEGER DEFAULT NULL, company_id INTEGER DEFAULT NULL, requester_id INTEGER DEFAULT NULL, assignee_id INTEGER DEFAULT NULL, assigned_team_id INTEGER DEFAULT NULL, sla_policy_id INTEGER DEFAULT NULL, intake_template_id INTEGER DEFAULT NULL, CONSTRAINT FK_54469DF412469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4ED442CF4 FOREIGN KEY (requester_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF459EC7D60 FOREIGN KEY (assignee_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF423F46021 FOREIGN KEY (assigned_team_id) REFERENCES technician_teams (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF437771414 FOREIGN KEY (sla_policy_id) REFERENCES sla_policies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF44A0B775D FOREIGN KEY (intake_template_id) REFERENCES ticket_intake_templates (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tickets (id, public_id, reference, subject, summary, status, visibility, priority, request_type, impact_level, escalation_level, intake_answers, created_at, updated_at, category_id, company_id, requester_id, assignee_id, assigned_team_id, sla_policy_id, intake_template_id) SELECT id, public_id, reference, subject, summary, status, visibility, priority, request_type, impact_level, escalation_level, intake_answers, created_at, updated_at, category_id, company_id, requester_id, assignee_id, assigned_team_id, sla_policy_id, intake_template_id FROM __temp__tickets');
        $this->addSql('DROP TABLE __temp__tickets');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4B5B48B91 ON tickets (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4AEA34913 ON tickets (reference)');
        $this->addSql('CREATE INDEX IDX_54469DF412469DE2 ON tickets (category_id)');
        $this->addSql('CREATE INDEX IDX_54469DF4979B1AD6 ON tickets (company_id)');
        $this->addSql('CREATE INDEX IDX_54469DF4ED442CF4 ON tickets (requester_id)');
        $this->addSql('CREATE INDEX IDX_54469DF459EC7D60 ON tickets (assignee_id)');
        $this->addSql('CREATE INDEX IDX_54469DF423F46021 ON tickets (assigned_team_id)');
        $this->addSql('CREATE INDEX IDX_54469DF437771414 ON tickets (sla_policy_id)');
        $this->addSql('CREATE INDEX IDX_54469DF44A0B775D ON tickets (intake_template_id)');
    }
}
