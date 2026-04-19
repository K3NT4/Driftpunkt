<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds knowledge base entries for public and customer help content';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE knowledge_base_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, author_id INTEGER DEFAULT NULL, title VARCHAR(180) NOT NULL, body CLOB NOT NULL, type VARCHAR(255) NOT NULL, audience VARCHAR(255) NOT NULL, is_active BOOLEAN NOT NULL, sort_order INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_4038FE3BF675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_4038FE3BF675F31B ON knowledge_base_entries (author_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE knowledge_base_entries');
    }
}
