<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds closed_at timestamp to tickets';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tickets ADD closed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tickets DROP closed_at');
    }
}
