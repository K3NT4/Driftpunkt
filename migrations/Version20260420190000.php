<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds addon module registry for admin addon management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE addon_modules (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, slug VARCHAR(120) NOT NULL, name VARCHAR(180) NOT NULL, description CLOB NOT NULL, version VARCHAR(64) DEFAULT NULL, admin_route VARCHAR(255) DEFAULT NULL, source_label VARCHAR(120) DEFAULT NULL, notes CLOB DEFAULT NULL, is_enabled BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX uniq_addon_modules_slug ON addon_modules (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE addon_modules');
    }
}
