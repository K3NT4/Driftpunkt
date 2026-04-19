<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418094100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repairs SQLite dependent foreign keys after team ticket rebuild';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $this->addSql('PRAGMA foreign_keys = OFF');

        $this->addSql('ALTER TABLE ticket_comments RENAME TO __temp__ticket_comments');
        $this->addSql('CREATE TABLE ticket_comments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, internal BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, ticket_id INTEGER NOT NULL, author_id INTEGER NOT NULL, CONSTRAINT FK_DAF76AABF675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_DAF76AAB700047D2 FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_comments (id, body, internal, created_at, updated_at, ticket_id, author_id) SELECT id, body, internal, created_at, updated_at, ticket_id, author_id FROM __temp__ticket_comments');
        $this->addSql('DROP TABLE __temp__ticket_comments');
        $this->addSql('CREATE INDEX IDX_DAF76AAB700047D2 ON ticket_comments (ticket_id)');
        $this->addSql('CREATE INDEX IDX_DAF76AABF675F31B ON ticket_comments (author_id)');

        $this->addSql('ALTER TABLE ticket_audit_logs RENAME TO __temp__ticket_audit_logs');
        $this->addSql('CREATE TABLE ticket_audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "action" VARCHAR(80) NOT NULL, message VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, ticket_id INTEGER NOT NULL, actor_id INTEGER DEFAULT NULL, CONSTRAINT FK_40BCBA0C10DAF24A FOREIGN KEY (actor_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_40BCBA0C700047D2 FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_audit_logs (id, "action", message, created_at, updated_at, ticket_id, actor_id) SELECT id, "action", message, created_at, updated_at, ticket_id, actor_id FROM __temp__ticket_audit_logs');
        $this->addSql('DROP TABLE __temp__ticket_audit_logs');
        $this->addSql('CREATE INDEX IDX_40BCBA0C700047D2 ON ticket_audit_logs (ticket_id)');
        $this->addSql('CREATE INDEX IDX_40BCBA0C10DAF24A ON ticket_audit_logs (actor_id)');

        $this->addSql('ALTER TABLE notification_logs RENAME TO __temp__notification_logs');
        $this->addSql('CREATE TABLE notification_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, event_type VARCHAR(80) NOT NULL, recipient_email VARCHAR(180) NOT NULL, subject VARCHAR(255) NOT NULL, sent BOOLEAN NOT NULL, status_message VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, recipient_id INTEGER DEFAULT NULL, ticket_id INTEGER DEFAULT NULL, CONSTRAINT FK_48B38D66E92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_48B38D66700047D2 FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO notification_logs (id, event_type, recipient_email, subject, sent, status_message, created_at, updated_at, recipient_id, ticket_id) SELECT id, event_type, recipient_email, subject, sent, status_message, created_at, updated_at, recipient_id, ticket_id FROM __temp__notification_logs');
        $this->addSql('DROP TABLE __temp__notification_logs');
        $this->addSql('CREATE INDEX IDX_48B38D66E92F8F78 ON notification_logs (recipient_id)');
        $this->addSql('CREATE INDEX IDX_48B38D66700047D2 ON notification_logs (ticket_id)');

        $this->addSql('PRAGMA foreign_keys = ON');
    }

    public function down(Schema $schema): void
    {
    }
}
