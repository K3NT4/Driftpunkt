<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418191500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds category to news articles for public status bar integration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE news_articles ADD category VARCHAR(40) NOT NULL DEFAULT 'general'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__news_articles AS SELECT id, author_id, title, summary, body, is_published, published_at, is_pinned, created_at, updated_at FROM news_articles');
        $this->addSql('DROP TABLE news_articles');
        $this->addSql('CREATE TABLE news_articles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, author_id INTEGER DEFAULT NULL, title VARCHAR(180) NOT NULL, summary CLOB NOT NULL, body CLOB NOT NULL, is_published BOOLEAN NOT NULL, published_at DATETIME NOT NULL, is_pinned BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_8D351FF6F675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO news_articles (id, author_id, title, summary, body, is_published, published_at, is_pinned, created_at, updated_at) SELECT id, author_id, title, summary, body, is_published, published_at, is_pinned, created_at, updated_at FROM __temp__news_articles');
        $this->addSql('CREATE INDEX IDX_8D351FF6F675F31B ON news_articles (author_id)');
        $this->addSql('DROP TABLE __temp__news_articles');
    }
}
