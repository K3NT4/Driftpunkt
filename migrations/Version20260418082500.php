<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418082500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds ticket priority.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TEMPORARY TABLE __temp__tickets AS SELECT id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, 'normal' AS priority FROM tickets");
        $this->addSql('DROP TABLE tickets');
        $this->addSql('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, reference VARCHAR(32) NOT NULL, subject VARCHAR(180) NOT NULL, summary CLOB NOT NULL, status VARCHAR(255) NOT NULL, visibility VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id INTEGER DEFAULT NULL, requester_id INTEGER DEFAULT NULL, assignee_id INTEGER DEFAULT NULL, priority VARCHAR(255) NOT NULL, CONSTRAINT FK_54469DF4979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4ED442CF4 FOREIGN KEY (requester_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF459EC7D60 FOREIGN KEY (assignee_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tickets (id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority) SELECT id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id, priority FROM __temp__tickets');
        $this->addSql('DROP TABLE __temp__tickets');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4B5B48B91 ON tickets (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4AEA34913 ON tickets (reference)');
        $this->addSql('CREATE INDEX IDX_54469DF4979B1AD6 ON tickets (company_id)');
        $this->addSql('CREATE INDEX IDX_54469DF4ED442CF4 ON tickets (requester_id)');
        $this->addSql('CREATE INDEX IDX_54469DF459EC7D60 ON tickets (assignee_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__tickets AS SELECT id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id FROM tickets');
        $this->addSql('DROP TABLE tickets');
        $this->addSql('CREATE TABLE tickets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, reference VARCHAR(32) NOT NULL, subject VARCHAR(180) NOT NULL, summary CLOB NOT NULL, status VARCHAR(255) NOT NULL, visibility VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id INTEGER DEFAULT NULL, requester_id INTEGER DEFAULT NULL, assignee_id INTEGER DEFAULT NULL, CONSTRAINT FK_54469DF4979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF4ED442CF4 FOREIGN KEY (requester_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_54469DF459EC7D60 FOREIGN KEY (assignee_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tickets (id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id) SELECT id, public_id, reference, subject, summary, status, visibility, created_at, updated_at, company_id, requester_id, assignee_id FROM __temp__tickets');
        $this->addSql('DROP TABLE __temp__tickets');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4B5B48B91 ON tickets (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54469DF4AEA34913 ON tickets (reference)');
        $this->addSql('CREATE INDEX IDX_54469DF4979B1AD6 ON tickets (company_id)');
        $this->addSql('CREATE INDEX IDX_54469DF4ED442CF4 ON tickets (requester_id)');
        $this->addSql('CREATE INDEX IDX_54469DF459EC7D60 ON tickets (assignee_id)');
    }
}
