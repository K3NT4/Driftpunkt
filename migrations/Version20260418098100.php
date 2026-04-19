<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418098100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restores SQLite unique index for ticket intake fields';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_713A9B7F997E1D01 ON ticket_intake_fields (field_key)');
    }

    public function down(Schema $schema): void
    {
    }
}
