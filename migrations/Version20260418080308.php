<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418080308 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, event_type VARCHAR(80) NOT NULL, recipient_email VARCHAR(180) NOT NULL, subject VARCHAR(255) NOT NULL, sent BOOLEAN NOT NULL, status_message VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, recipient_id INTEGER DEFAULT NULL, ticket_id INTEGER DEFAULT NULL, CONSTRAINT FK_48B38D66E92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_48B38D66700047D2 FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_48B38D66E92F8F78 ON notification_logs (recipient_id)');
        $this->addSql('CREATE INDEX IDX_48B38D66700047D2 ON notification_logs (ticket_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE notification_logs');
    }
}
