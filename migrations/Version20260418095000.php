<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418095000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds ticket categories, routing rules, and ticket category relation';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('PRAGMA foreign_keys = OFF');

            $this->addSql('CREATE TABLE ticket_categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(180) NOT NULL, description VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_3F1C4C855E237E06 ON ticket_categories (name)');

            $this->addSql('CREATE TABLE ticket_routing_rules (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, team_id INTEGER NOT NULL, category_id INTEGER DEFAULT NULL, default_sla_policy_id INTEGER DEFAULT NULL, default_assignee_id INTEGER DEFAULT NULL, name VARCHAR(180) NOT NULL, customer_type VARCHAR(255) DEFAULT NULL, sort_order INTEGER NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_BB71E53B296CD8AE FOREIGN KEY (team_id) REFERENCES technician_teams (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BB71E53B12469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BB71E53BF5A04706 FOREIGN KEY (default_sla_policy_id) REFERENCES sla_policies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_BB71E53BB8CA29A7 FOREIGN KEY (default_assignee_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE INDEX IDX_BB71E53B296CD8AE ON ticket_routing_rules (team_id)');
            $this->addSql('CREATE INDEX IDX_BB71E53B12469DE2 ON ticket_routing_rules (category_id)');
            $this->addSql('CREATE INDEX IDX_BB71E53BF5A04706 ON ticket_routing_rules (default_sla_policy_id)');
            $this->addSql('CREATE INDEX IDX_BB71E53BB8CA29A7 ON ticket_routing_rules (default_assignee_id)');

            $this->addSql('ALTER TABLE tickets RENAME TO __temp__tickets');
            $this->addSql('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, reference VARCHAR(32) NOT NULL, subject VARCHAR(180) NOT NULL, summary CLOB NOT NULL, status VARCHAR(255) NOT NULL, visibility VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id INTEGER DEFAULT NULL, requester_id INTEGER DEFAULT NULL, assignee_id INTEGER DEFAULT NULL, priority VARCHAR(255) NOT NULL, sla_policy_id INTEGER DEFAULT NULL, escalation_level VARCHAR(255) NOT NULL, assigned_team_id INTEGER DEFAULT NULL, category_id INTEGER DEFAULT NULL, CONSTRAINT FK_54469DF437771414 FOREIGN KEY (sla_policy_id) REFERENCES sla_policies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4ED442CF4 FOREIGN KEY (requester_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF459EC7D60 FOREIGN KEY (assignee_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4F14C4107 FOREIGN KEY (assigned_team_id) REFERENCES technician_teams (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF412469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('INSERT INTO tickets (id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority, sla_policy_id, escalation_level, assigned_team_id, category_id) SELECT id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority, sla_policy_id, escalation_level, assigned_team_id, NULL FROM __temp__tickets');
            $this->addSql('DROP TABLE __temp__tickets');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4B5B48B91 ON tickets (public_id)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4AEA34913 ON tickets (reference)');
            $this->addSql('CREATE INDEX IDX_54469DF4979B1AD6 ON tickets (company_id)');
            $this->addSql('CREATE INDEX IDX_54469DF4ED442CF4 ON tickets (requester_id)');
            $this->addSql('CREATE INDEX IDX_54469DF459EC7D60 ON tickets (assignee_id)');
            $this->addSql('CREATE INDEX IDX_54469DF437771414 ON tickets (sla_policy_id)');
            $this->addSql('CREATE INDEX IDX_54469DF423F46021 ON tickets (assigned_team_id)');
            $this->addSql('CREATE INDEX IDX_54469DF412469DE2 ON tickets (category_id)');

            $this->addSql('PRAGMA foreign_keys = ON');

            return;
        }
    }

    public function down(Schema $schema): void
    {
    }
}
