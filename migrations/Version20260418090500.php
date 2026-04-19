<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418090500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds optional per-policy SLA warning thresholds.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sla_policies ADD first_response_warning_hours INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE sla_policies ADD resolution_warning_hours INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__sla_policies AS SELECT id, public_id, name, description, first_response_hours, resolution_hours, is_active, created_at, updated_at FROM sla_policies');
        $this->addSql('DROP TABLE sla_policies');
        $this->addSql('CREATE TABLE sla_policies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, name VARCHAR(180) NOT NULL, description VARCHAR(255) DEFAULT NULL, first_response_hours INTEGER NOT NULL, resolution_hours INTEGER NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO sla_policies (id, public_id, name, description, first_response_hours, resolution_hours, is_active, created_at, updated_at) SELECT id, public_id, name, description, first_response_hours, resolution_hours, is_active, created_at, updated_at FROM __temp__sla_policies');
        $this->addSql('DROP TABLE __temp__sla_policies');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64A810DAB5B48B91 ON sla_policies (public_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64A810DA5E237E06 ON sla_policies (name)');
    }
}
