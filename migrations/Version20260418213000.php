<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds incoming mail logs and draft ticket review tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE incoming_mails (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, mailbox_id INTEGER DEFAULT NULL, matched_user_id INTEGER DEFAULT NULL, matched_company_id INTEGER DEFAULT NULL, matched_ticket_id INTEGER DEFAULT NULL, from_email VARCHAR(180) NOT NULL, from_name VARCHAR(180) DEFAULT NULL, subject VARCHAR(255) NOT NULL, body CLOB NOT NULL, processing_result VARCHAR(255) DEFAULT NULL, processing_note VARCHAR(255) DEFAULT NULL, processed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_D6D4A5A0A6A1EAB8 FOREIGN KEY (mailbox_id) REFERENCES support_mailboxes (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D6D4A5A0DB0A8F35 FOREIGN KEY (matched_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D6D4A5A06C5A24D FOREIGN KEY (matched_company_id) REFERENCES companies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D6D4A5A0A58E6A9 FOREIGN KEY (matched_ticket_id) REFERENCES tickets (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D6D4A5A0A6A1EAB8 ON incoming_mails (mailbox_id)');
        $this->addSql('CREATE INDEX IDX_D6D4A5A0DB0A8F35 ON incoming_mails (matched_user_id)');
        $this->addSql('CREATE INDEX IDX_D6D4A5A06C5A24D ON incoming_mails (matched_company_id)');
        $this->addSql('CREATE INDEX IDX_D6D4A5A0A58E6A9 ON incoming_mails (matched_ticket_id)');
        $this->addSql('CREATE TABLE draft_ticket_reviews (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, incoming_mail_id INTEGER NOT NULL, mailbox_id INTEGER DEFAULT NULL, draft_ticket_id INTEGER DEFAULT NULL, matched_ticket_id INTEGER DEFAULT NULL, matched_company_id INTEGER DEFAULT NULL, reviewed_by_id INTEGER DEFAULT NULL, status VARCHAR(255) NOT NULL, reason VARCHAR(255) NOT NULL, reviewed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_7ACB8C5CC4E44BEE FOREIGN KEY (incoming_mail_id) REFERENCES incoming_mails (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7ACB8C5CA6A1EAB8 FOREIGN KEY (mailbox_id) REFERENCES support_mailboxes (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7ACB8C5C6A7E7F95 FOREIGN KEY (draft_ticket_id) REFERENCES tickets (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7ACB8C5CA58E6A9 FOREIGN KEY (matched_ticket_id) REFERENCES tickets (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7ACB8C5C6C5A24D FOREIGN KEY (matched_company_id) REFERENCES companies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7ACB8C5C16BAB96 FOREIGN KEY (reviewed_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7ACB8C5CC4E44BEE ON draft_ticket_reviews (incoming_mail_id)');
        $this->addSql('CREATE INDEX IDX_7ACB8C5CA6A1EAB8 ON draft_ticket_reviews (mailbox_id)');
        $this->addSql('CREATE INDEX IDX_7ACB8C5C6A7E7F95 ON draft_ticket_reviews (draft_ticket_id)');
        $this->addSql('CREATE INDEX IDX_7ACB8C5CA58E6A9 ON draft_ticket_reviews (matched_ticket_id)');
        $this->addSql('CREATE INDEX IDX_7ACB8C5C6C5A24D ON draft_ticket_reviews (matched_company_id)');
        $this->addSql('CREATE INDEX IDX_7ACB8C5C16BAB96 ON draft_ticket_reviews (reviewed_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE draft_ticket_reviews');
        $this->addSql('DROP TABLE incoming_mails');
    }
}
