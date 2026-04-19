<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds mail servers, support mailboxes, and company mail overrides';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mail_servers (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(180) NOT NULL, direction VARCHAR(20) NOT NULL, transport_type VARCHAR(40) NOT NULL, host VARCHAR(255) NOT NULL, port INTEGER NOT NULL, encryption VARCHAR(20) NOT NULL, username VARCHAR(180) DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, from_address VARCHAR(180) DEFAULT NULL, from_name VARCHAR(180) DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_primary_outbound BOOLEAN NOT NULL, fallback_to_primary BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6C6C4F195E237E06 ON mail_servers (name)');
        $this->addSql('CREATE TABLE support_mailboxes (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, company_id INTEGER DEFAULT NULL, incoming_server_id INTEGER DEFAULT NULL, default_category_id INTEGER DEFAULT NULL, default_team_id INTEGER DEFAULT NULL, name VARCHAR(180) NOT NULL, email_address VARCHAR(180) NOT NULL, default_priority VARCHAR(255) DEFAULT NULL, polling_interval_minutes INTEGER NOT NULL, allow_unknown_senders BOOLEAN NOT NULL, create_draft_tickets_for_unknown_senders BOOLEAN NOT NULL, allow_attachments BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_1411D6FE979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1411D6FE427FF6F1 FOREIGN KEY (incoming_server_id) REFERENCES mail_servers (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1411D6FE52B48FCE FOREIGN KEY (default_category_id) REFERENCES ticket_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1411D6FE367086E7 FOREIGN KEY (default_team_id) REFERENCES technician_teams (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1411D6FE5E237E06 ON support_mailboxes (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1411D6FEF85E0677 ON support_mailboxes (email_address)');
        $this->addSql('CREATE INDEX IDX_1411D6FE979B1AD6 ON support_mailboxes (company_id)');
        $this->addSql('CREATE INDEX IDX_1411D6FE427FF6F1 ON support_mailboxes (incoming_server_id)');
        $this->addSql('CREATE INDEX IDX_1411D6FE52B48FCE ON support_mailboxes (default_category_id)');
        $this->addSql('CREATE INDEX IDX_1411D6FE367086E7 ON support_mailboxes (default_team_id)');
        $this->addSql('CREATE TABLE company_mail_overrides (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, company_id INTEGER NOT NULL, outbound_server_id INTEGER DEFAULT NULL, from_address VARCHAR(180) DEFAULT NULL, from_name VARCHAR(180) DEFAULT NULL, fallback_to_primary BOOLEAN NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_2B7DFA8E979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2B7DFA8E580C7A9 FOREIGN KEY (outbound_server_id) REFERENCES mail_servers (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2B7DFA8E979B1AD6 ON company_mail_overrides (company_id)');
        $this->addSql('CREATE INDEX IDX_2B7DFA8E580C7A9 ON company_mail_overrides (outbound_server_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE company_mail_overrides');
        $this->addSql('DROP TABLE support_mailboxes');
        $this->addSql('DROP TABLE mail_servers');
    }
}
