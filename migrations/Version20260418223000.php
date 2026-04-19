<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds attachment metadata to incoming mails';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE incoming_mails ADD attachment_metadata CLOB DEFAULT '[]' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__incoming_mails AS SELECT id, mailbox_id, matched_user_id, matched_company_id, matched_ticket_id, from_email, from_name, subject, body, processing_result, processing_note, processed_at, created_at, updated_at FROM incoming_mails');
        $this->addSql('DROP TABLE incoming_mails');
        $this->addSql('CREATE TABLE incoming_mails (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, mailbox_id INTEGER DEFAULT NULL, matched_user_id INTEGER DEFAULT NULL, matched_company_id INTEGER DEFAULT NULL, matched_ticket_id INTEGER DEFAULT NULL, from_email VARCHAR(180) NOT NULL, from_name VARCHAR(180) DEFAULT NULL, subject VARCHAR(255) NOT NULL, body CLOB NOT NULL, processing_result VARCHAR(255) DEFAULT NULL, processing_note VARCHAR(255) DEFAULT NULL, processed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_806B7432427FF6F1 FOREIGN KEY (mailbox_id) REFERENCES support_mailboxes (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_806B7432FB88E14F FOREIGN KEY (matched_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_806B743266C4D63F FOREIGN KEY (matched_company_id) REFERENCES companies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_806B743274700D47 FOREIGN KEY (matched_ticket_id) REFERENCES tickets (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO incoming_mails (id, mailbox_id, matched_user_id, matched_company_id, matched_ticket_id, from_email, from_name, subject, body, processing_result, processing_note, processed_at, created_at, updated_at) SELECT id, mailbox_id, matched_user_id, matched_company_id, matched_ticket_id, from_email, from_name, subject, body, processing_result, processing_note, processed_at, created_at, updated_at FROM __temp__incoming_mails');
        $this->addSql('CREATE INDEX IDX_806B7432427FF6F1 ON incoming_mails (mailbox_id)');
        $this->addSql('CREATE INDEX IDX_806B7432FB88E14F ON incoming_mails (matched_user_id)');
        $this->addSql('CREATE INDEX IDX_806B743266C4D63F ON incoming_mails (matched_company_id)');
        $this->addSql('CREATE INDEX IDX_806B743274700D47 ON incoming_mails (matched_ticket_id)');
        $this->addSql('DROP TABLE __temp__incoming_mails');
    }
}
