<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds install status, dependencies, environment variables, and setup checklist to addon modules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE addon_modules ADD install_status VARCHAR(32) DEFAULT 'planned' NOT NULL");
        $this->addSql('ALTER TABLE addon_modules ADD dependencies CLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE addon_modules ADD environment_variables CLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE addon_modules ADD setup_checklist CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE addon_modules DROP setup_checklist');
        $this->addSql('ALTER TABLE addon_modules DROP environment_variables');
        $this->addSql('ALTER TABLE addon_modules DROP dependencies');
        $this->addSql('ALTER TABLE addon_modules DROP install_status');
    }
}
