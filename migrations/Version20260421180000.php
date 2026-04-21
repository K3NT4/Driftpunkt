<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds archived_at to news articles for portal archive management';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('news_articles')) {
            return;
        }

        $table = $schema->getTable('news_articles');
        if (!$table->hasColumn('archived_at')) {
            $this->addSql('ALTER TABLE news_articles ADD COLUMN archived_at DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('news_articles')) {
            return;
        }

        $table = $schema->getTable('news_articles');
        if ($table->hasColumn('archived_at')) {
            $this->addSql('ALTER TABLE news_articles DROP COLUMN archived_at');
        }
    }
}
