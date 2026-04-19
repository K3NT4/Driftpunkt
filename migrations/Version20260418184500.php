<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418184500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds news article storage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE news_articles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, author_id INTEGER DEFAULT NULL, title VARCHAR(180) NOT NULL, summary CLOB NOT NULL, body CLOB NOT NULL, is_published BOOLEAN NOT NULL, published_at DATETIME NOT NULL, is_pinned BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_8D351FF6F675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8D351FF6F675F31B ON news_articles (author_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE news_articles');
    }
}
