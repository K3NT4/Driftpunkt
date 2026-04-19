<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418094000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds technician teams and team routing fields';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('PRAGMA foreign_keys = OFF');

            $this->addSql('CREATE TABLE technician_teams (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, name VARCHAR(180) NOT NULL, description VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_FD5F3C25B5B48B91 ON technician_teams (public_id)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_FD5F3C255E237E06 ON technician_teams (name)');

            $this->addSql('ALTER TABLE users RENAME TO __temp__users');
            $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(80) NOT NULL, last_name VARCHAR(80) NOT NULL, type VARCHAR(255) NOT NULL, is_active BOOLEAN NOT NULL, mfa_enabled BOOLEAN NOT NULL, email_notifications_enabled BOOLEAN NOT NULL, company_id INTEGER DEFAULT NULL, technician_team_id INTEGER DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_1483A5E9979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E9588F0EBF FOREIGN KEY (technician_team_id) REFERENCES technician_teams (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('INSERT INTO users (id, public_id, email, roles, password, first_name, last_name, type, is_active, mfa_enabled, email_notifications_enabled, company_id, technician_team_id, created_at, updated_at) SELECT id, public_id, email, roles, password, first_name, last_name, type, is_active, mfa_enabled, email_notifications_enabled, company_id, NULL, created_at, updated_at FROM __temp__users');
            $this->addSql('DROP TABLE __temp__users');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9B5B48B91 ON users (public_id)');
            $this->addSql('CREATE INDEX IDX_1483A5E9979B1AD6 ON users (company_id)');
            $this->addSql('CREATE INDEX IDX_1483A5E9588F0EBF ON users (technician_team_id)');

            $this->addSql('ALTER TABLE sla_policies RENAME TO __temp__sla_policies');
            $this->addSql('CREATE TABLE sla_policies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, name VARCHAR(180) NOT NULL, description VARCHAR(255) DEFAULT NULL, first_response_hours INTEGER NOT NULL, resolution_hours INTEGER NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_response_warning_hours INTEGER DEFAULT NULL, resolution_warning_hours INTEGER DEFAULT NULL, default_priority_enabled BOOLEAN NOT NULL, default_priority VARCHAR(255) DEFAULT NULL, default_assignee_enabled BOOLEAN NOT NULL, default_assignee_id INTEGER DEFAULT NULL, default_escalation_enabled BOOLEAN NOT NULL, default_escalation_level VARCHAR(255) DEFAULT NULL, default_team_enabled BOOLEAN NOT NULL, default_team_id INTEGER DEFAULT NULL, CONSTRAINT FK_38A2B754CB852C2C FOREIGN KEY (default_assignee_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_38A2B75454F0659 FOREIGN KEY (default_team_id) REFERENCES technician_teams (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('INSERT INTO sla_policies (id, public_id, name, description, first_response_hours, resolution_hours, is_active, created_at, updated_at, first_response_warning_hours, resolution_warning_hours, default_priority_enabled, default_priority, default_assignee_enabled, default_assignee_id, default_escalation_enabled, default_escalation_level, default_team_enabled, default_team_id) SELECT id, public_id, name, description, first_response_hours, resolution_hours, is_active, created_at, updated_at, first_response_warning_hours, resolution_warning_hours, default_priority_enabled, default_priority, default_assignee_enabled, default_assignee_id, default_escalation_enabled, default_escalation_level, 0, NULL FROM __temp__sla_policies');
            $this->addSql('DROP TABLE __temp__sla_policies');
            $this->addSql('CREATE INDEX IDX_64A810DA8D1F6ED6 ON sla_policies (default_assignee_id)');
            $this->addSql('CREATE INDEX IDX_64A810DA54F0659 ON sla_policies (default_team_id)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_64A810DAB5B48B91 ON sla_policies (public_id)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_64A810DA5E237E06 ON sla_policies (name)');

            $this->addSql('ALTER TABLE tickets RENAME TO __temp__tickets');
            $this->addSql('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, reference VARCHAR(32) NOT NULL, subject VARCHAR(180) NOT NULL, summary CLOB NOT NULL, status VARCHAR(255) NOT NULL, visibility VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id INTEGER DEFAULT NULL, requester_id INTEGER DEFAULT NULL, assignee_id INTEGER DEFAULT NULL, priority VARCHAR(255) NOT NULL, sla_policy_id INTEGER DEFAULT NULL, escalation_level VARCHAR(255) NOT NULL, assigned_team_id INTEGER DEFAULT NULL, CONSTRAINT FK_54469DF437771414 FOREIGN KEY (sla_policy_id) REFERENCES sla_policies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4ED442CF4 FOREIGN KEY (requester_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF459EC7D60 FOREIGN KEY (assignee_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4F14C4107 FOREIGN KEY (assigned_team_id) REFERENCES technician_teams (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('INSERT INTO tickets (id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority, sla_policy_id, escalation_level, assigned_team_id) SELECT id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority, sla_policy_id, escalation_level, NULL FROM __temp__tickets');
            $this->addSql('DROP TABLE __temp__tickets');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4B5B48B91 ON tickets (public_id)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4AEA34913 ON tickets (reference)');
            $this->addSql('CREATE INDEX IDX_54469DF4979B1AD6 ON tickets (company_id)');
            $this->addSql('CREATE INDEX IDX_54469DF4ED442CF4 ON tickets (requester_id)');
            $this->addSql('CREATE INDEX IDX_54469DF459EC7D60 ON tickets (assignee_id)');
            $this->addSql('CREATE INDEX IDX_54469DF437771414 ON tickets (sla_policy_id)');
            $this->addSql('CREATE INDEX IDX_54469DF4F14C4107 ON tickets (assigned_team_id)');

            $this->addSql('PRAGMA foreign_keys = ON');

            return;
        }

        $this->addSql('CREATE TABLE technician_teams (id INT AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL COMMENT "(DC2Type:uuid)", name VARCHAR(180) NOT NULL, description VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FD5F3C25B5B48B91 (public_id), UNIQUE INDEX UNIQ_FD5F3C255E237E06 (name), PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE users ADD technician_team_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9588F0EBF FOREIGN KEY (technician_team_id) REFERENCES technician_teams (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1483A5E9588F0EBF ON users (technician_team_id)');
        $this->addSql('ALTER TABLE sla_policies ADD default_team_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD default_team_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sla_policies ADD CONSTRAINT FK_64A810DA54F0659 FOREIGN KEY (default_team_id) REFERENCES technician_teams (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_64A810DA54F0659 ON sla_policies (default_team_id)');
        $this->addSql('ALTER TABLE tickets ADD assigned_team_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tickets ADD CONSTRAINT FK_54469DF4F14C4107 FOREIGN KEY (assigned_team_id) REFERENCES technician_teams (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_54469DF4F14C4107 ON tickets (assigned_team_id)');
    }

    public function down(Schema $schema): void
    {
    }
}
