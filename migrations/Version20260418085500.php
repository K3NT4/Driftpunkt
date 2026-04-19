<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418085500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds system settings for SLA warnings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE system_settings (setting_key VARCHAR(120) NOT NULL, setting_value VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(setting_key))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE system_settings');
    }
}
