<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds addon release history log table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE addon_release_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, addon_id INTEGER NOT NULL, released_by_email VARCHAR(180) NOT NULL, version VARCHAR(64) DEFAULT NULL, summary CLOB NOT NULL, released_at DATETIME NOT NULL, CONSTRAINT FK_ADDON_RELEASE_LOGS_ADDON FOREIGN KEY (addon_id) REFERENCES addon_modules (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_ADDON_RELEASE_LOGS_ADDON ON addon_release_logs (addon_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE addon_release_logs');
    }
}
