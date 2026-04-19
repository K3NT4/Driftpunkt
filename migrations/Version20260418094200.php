<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418094200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalizes SQLite index names for technician teams';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $this->addSql('DROP INDEX IF EXISTS IDX_1483A5E9588F0EBF');
        $this->addSql('CREATE INDEX IDX_1483A5E997594808 ON users (technician_team_id)');

        $this->addSql('DROP INDEX IF EXISTS IDX_54469DF4F14C4107');
        $this->addSql('CREATE INDEX IDX_54469DF423F46021 ON tickets (assigned_team_id)');

        $this->addSql('DROP INDEX IF EXISTS IDX_64A810DA54F0659');
        $this->addSql('CREATE INDEX IDX_64A810DADBE989EB ON sla_policies (default_team_id)');

        $this->addSql('DROP INDEX IF EXISTS UNIQ_FD5F3C25B5B48B91');
        $this->addSql('DROP INDEX IF EXISTS UNIQ_FD5F3C255E237E06');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_78038BBFB5B48B91 ON technician_teams (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_78038BBF5E237E06 ON technician_teams (name)');
    }

    public function down(Schema $schema): void
    {
    }
}
