<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds health status and verification timestamp to addon modules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE addon_modules ADD health_status VARCHAR(32) DEFAULT 'unknown' NOT NULL");
        $this->addSql('ALTER TABLE addon_modules ADD verified_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE addon_modules DROP verified_at');
        $this->addSql('ALTER TABLE addon_modules DROP health_status');
    }
}
