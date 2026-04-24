<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a dedicated resolution summary field to tickets';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('tickets')) {
            return;
        }

        $table = $schema->getTable('tickets');
        if ($table->hasColumn('resolution_summary')) {
            return;
        }

        $platformClass = mb_strtolower($this->connection->getDatabasePlatform()::class);
        if (str_contains($platformClass, 'mysql') || str_contains($platformClass, 'mariadb')) {
            $this->addSql('ALTER TABLE tickets ADD resolution_summary LONGTEXT NULL');

            return;
        }

        $this->addSql('ALTER TABLE tickets ADD resolution_summary CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('tickets')) {
            return;
        }

        $table = $schema->getTable('tickets');
        if (!$table->hasColumn('resolution_summary')) {
            return;
        }

        $this->addSql('ALTER TABLE tickets DROP COLUMN resolution_summary');
    }
}
