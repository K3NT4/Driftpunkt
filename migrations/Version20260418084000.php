<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418084000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds SLA policies and links tickets to SLA.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sla_policies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, name VARCHAR(180) NOT NULL, description VARCHAR(255) DEFAULT NULL, first_response_hours INTEGER NOT NULL, resolution_hours INTEGER NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64A810DAB5B48B91 ON sla_policies (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64A810DA5E237E06 ON sla_policies (name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__tickets AS SELECT id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority, NULL AS sla_policy_id FROM tickets');
        $this->addSql('DROP TABLE tickets');
        $this->addSql('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, reference VARCHAR(32) NOT NULL, subject VARCHAR(180) NOT NULL, summary CLOB NOT NULL, status VARCHAR(255) NOT NULL, visibility VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id INTEGER DEFAULT NULL, requester_id INTEGER DEFAULT NULL, assignee_id INTEGER DEFAULT NULL, priority VARCHAR(255) NOT NULL, sla_policy_id INTEGER DEFAULT NULL, FOREIGN KEY (sla_policy_id) REFERENCES sla_policies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4ED442CF4 FOREIGN KEY (requester_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF459EC7D60 FOREIGN KEY (assignee_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tickets (id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority, sla_policy_id) SELECT id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority, sla_policy_id FROM __temp__tickets');
        $this->addSql('DROP TABLE __temp__tickets');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4B5B48B91 ON tickets (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4AEA34913 ON tickets (reference)');
        $this->addSql('CREATE INDEX IDX_54469DF4979B1AD6 ON tickets (company_id)');
        $this->addSql('CREATE INDEX IDX_54469DF4ED442CF4 ON tickets (requester_id)');
        $this->addSql('CREATE INDEX IDX_54469DF459EC7D60 ON tickets (assignee_id)');
        $this->addSql('CREATE INDEX IDX_54469DF437771414 ON tickets (sla_policy_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_54469DF4F65DF962');
        $this->addSql('CREATE TEMPORARY TABLE __temp__tickets AS SELECT id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority FROM tickets');
        $this->addSql('DROP TABLE tickets');
        $this->addSql('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, reference VARCHAR(32) NOT NULL, subject VARCHAR(180) NOT NULL, summary CLOB NOT NULL, status VARCHAR(255) NOT NULL, visibility VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id INTEGER DEFAULT NULL, requester_id INTEGER DEFAULT NULL, assignee_id INTEGER DEFAULT NULL, priority VARCHAR(255) NOT NULL, CONSTRAINT FK_54469DF4979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4ED442CF4 FOREIGN KEY (requester_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF459EC7D60 FOREIGN KEY (assignee_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tickets (id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority) SELECT id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority FROM __temp__tickets');
        $this->addSql('DROP TABLE __temp__tickets');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4B5B48B91 ON tickets (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4AEA34913 ON tickets (reference)');
        $this->addSql('CREATE INDEX IDX_54469DF4979B1AD6 ON tickets (company_id)');
        $this->addSql('CREATE INDEX IDX_54469DF4ED442CF4 ON tickets (requester_id)');
        $this->addSql('CREATE INDEX IDX_54469DF459EC7D60 ON tickets (assignee_id)');
        $this->addSql('DROP TABLE sla_policies');
    }
}
