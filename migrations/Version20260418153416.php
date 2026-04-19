<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418153416 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds ticket comment attachments for local files and external sharing links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ticket_comment_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, comment_id INTEGER NOT NULL, display_name VARCHAR(255) NOT NULL, mime_type VARCHAR(191) DEFAULT NULL, file_size INTEGER DEFAULT NULL, file_path VARCHAR(1000) DEFAULT NULL, external_url VARCHAR(1000) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_2B2C019DF8697D13 FOREIGN KEY (comment_id) REFERENCES ticket_comments (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2B2C019DF8697D13 ON ticket_comment_attachments (comment_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ticket_comment_attachments');
    }
}
