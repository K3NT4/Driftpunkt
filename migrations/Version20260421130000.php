<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds release notes to addon release logs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE addon_release_logs ADD release_notes CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE addon_release_logs DROP release_notes');
    }
}
