<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expands system setting values to text for configurable login content';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('CREATE TEMPORARY TABLE __temp__system_settings AS SELECT setting_key, setting_value, created_at, updated_at FROM system_settings');
            $this->addSql('DROP TABLE system_settings');
            $this->addSql('CREATE TABLE system_settings (setting_key VARCHAR(120) NOT NULL, setting_value CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(setting_key))');
            $this->addSql('INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at) SELECT setting_key, setting_value, created_at, updated_at FROM __temp__system_settings');
            $this->addSql('DROP TABLE __temp__system_settings');

            return;
        }

        $this->addSql('ALTER TABLE system_settings ALTER COLUMN setting_value TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('CREATE TEMPORARY TABLE __temp__system_settings AS SELECT setting_key, setting_value, created_at, updated_at FROM system_settings');
            $this->addSql('DROP TABLE system_settings');
            $this->addSql('CREATE TABLE system_settings (setting_key VARCHAR(120) NOT NULL, setting_value VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(setting_key))');
            $this->addSql('INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at) SELECT setting_key, SUBSTR(setting_value, 1, 255), created_at, updated_at FROM __temp__system_settings');
            $this->addSql('DROP TABLE __temp__system_settings');

            return;
        }

        $this->addSql('ALTER TABLE system_settings ALTER COLUMN setting_value TYPE VARCHAR(255)');
    }
}
