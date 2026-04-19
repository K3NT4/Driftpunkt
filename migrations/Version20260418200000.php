<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds optional image URL to news articles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE news_articles ADD image_url VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__news_articles AS SELECT id, author_id, title, summary, body, is_published, published_at, is_pinned, created_at, updated_at, category FROM news_articles');
        $this->addSql('DROP TABLE news_articles');
        $this->addSql("CREATE TABLE news_articles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, author_id INTEGER DEFAULT NULL, title VARCHAR(180) NOT NULL, summary CLOB NOT NULL, body CLOB NOT NULL, is_published BOOLEAN NOT NULL, published_at DATETIME NOT NULL, is_pinned BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category VARCHAR(40) NOT NULL DEFAULT 'general', CONSTRAINT FK_8D351FF6F675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)");
        $this->addSql("INSERT INTO news_articles (id, author_id, title, summary, body, is_published, published_at, is_pinned, created_at, updated_at, category) SELECT id, author_id, title, summary, body, is_published, published_at, is_pinned, created_at, updated_at, category FROM __temp__news_articles");
        $this->addSql('CREATE INDEX IDX_8D351FF6F675F31B ON news_articles (author_id)');
        $this->addSql('DROP TABLE __temp__news_articles');
    }
}
