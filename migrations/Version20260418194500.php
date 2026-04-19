<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418194500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds archive entry tracking for zipped ticket attachments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket_comment_attachments ADD archive_entry_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_comment_attachments AS SELECT id, comment_id, display_name, mime_type, file_size, file_path, external_url, created_at, updated_at FROM ticket_comment_attachments');
        $this->addSql('DROP TABLE ticket_comment_attachments');
        $this->addSql('CREATE TABLE ticket_comment_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, comment_id INTEGER NOT NULL, display_name VARCHAR(255) NOT NULL, mime_type VARCHAR(191) DEFAULT NULL, file_size INTEGER DEFAULT NULL, file_path VARCHAR(1000) DEFAULT NULL, external_url VARCHAR(1000) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_2B2C019DF8697D13 FOREIGN KEY (comment_id) REFERENCES ticket_comments (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_comment_attachments (id, comment_id, display_name, mime_type, file_size, file_path, external_url, created_at, updated_at) SELECT id, comment_id, display_name, mime_type, file_size, file_path, external_url, created_at, updated_at FROM __temp__ticket_comment_attachments');
        $this->addSql('DROP TABLE __temp__ticket_comment_attachments');
        $this->addSql('CREATE INDEX IDX_2B2C019DF8697D13 ON ticket_comment_attachments (comment_id)');
    }
}
