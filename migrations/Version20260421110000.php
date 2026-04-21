<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds release metadata to addon modules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE addon_modules ADD released_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE addon_modules ADD released_by_email VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE addon_modules DROP released_by_email');
        $this->addSql('ALTER TABLE addon_modules DROP released_at');
    }
}
