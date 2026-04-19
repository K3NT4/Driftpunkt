<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418092000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds optional SLA policy defaults for priority and assignee';
    }

    public function up(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('PRAGMA foreign_keys = OFF');
            $this->addSql('ALTER TABLE sla_policies RENAME TO __temp__sla_policies');
            $this->addSql('CREATE TABLE sla_policies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, name VARCHAR(180) NOT NULL, description VARCHAR(255) DEFAULT NULL, first_response_hours INTEGER NOT NULL, resolution_hours INTEGER NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_response_warning_hours INTEGER DEFAULT NULL, resolution_warning_hours INTEGER DEFAULT NULL, default_priority_enabled BOOLEAN NOT NULL DEFAULT FALSE, default_priority VARCHAR(255) DEFAULT NULL, default_assignee_enabled BOOLEAN NOT NULL DEFAULT FALSE, default_assignee_id INTEGER DEFAULT NULL, CONSTRAINT FK_38A2B754CB852C2C FOREIGN KEY (default_assignee_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('INSERT INTO sla_policies (id, public_id, name, description, first_response_hours, resolution_hours, is_active, created_at, updated_at, first_response_warning_hours, resolution_warning_hours, default_priority_enabled, default_priority, default_assignee_enabled, default_assignee_id) SELECT id, public_id, name, description, first_response_hours, resolution_hours, is_active, created_at, updated_at, first_response_warning_hours, resolution_warning_hours, 0, NULL, 0, NULL FROM __temp__sla_policies');
            $this->addSql('DROP TABLE __temp__sla_policies');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_64A810DAB5B48B91 ON sla_policies (public_id)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_64A810DA5E237E06 ON sla_policies (name)');
            $this->addSql('CREATE INDEX IDX_38A2B754CB852C2C ON sla_policies (default_assignee_id)');
            $this->addSql('PRAGMA foreign_keys = ON');

            return;
        }

        $this->addSql('ALTER TABLE sla_policies ADD default_priority_enabled BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE sla_policies ADD default_priority VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sla_policies ADD default_assignee_enabled BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE sla_policies ADD default_assignee_id INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE sla_policies ADD CONSTRAINT FK_38A2B754CB852C2C FOREIGN KEY (default_assignee_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_38A2B754CB852C2C ON sla_policies (default_assignee_id)');
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('PRAGMA foreign_keys = OFF');
            $this->addSql('DROP INDEX IF EXISTS IDX_38A2B754CB852C2C');
            $this->addSql('ALTER TABLE sla_policies RENAME TO __temp__sla_policies');
            $this->addSql('CREATE TABLE sla_policies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, public_id BLOB NOT NULL, name VARCHAR(180) NOT NULL, description VARCHAR(255) DEFAULT NULL, first_response_hours INTEGER NOT NULL, resolution_hours INTEGER NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, first_response_warning_hours INTEGER DEFAULT NULL, resolution_warning_hours INTEGER DEFAULT NULL)');
            $this->addSql('INSERT INTO sla_policies (id, public_id, name, description, first_response_hours, resolution_hours, is_active, created_at, updated_at, first_response_warning_hours, resolution_warning_hours) SELECT id, public_id, name, description, first_response_hours, resolution_hours, is_active, created_at, updated_at, first_response_warning_hours, resolution_warning_hours FROM __temp__sla_policies');
            $this->addSql('DROP TABLE __temp__sla_policies');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_64A810DAB5B48B91 ON sla_policies (public_id)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_64A810DA5E237E06 ON sla_policies (name)');
            $this->addSql('PRAGMA foreign_keys = ON');

            return;
        }

        $this->addSql('DROP INDEX IDX_38A2B754CB852C2C');
        $this->addSql('ALTER TABLE sla_policies DROP CONSTRAINT FK_38A2B754CB852C2C');
        $this->addSql('ALTER TABLE sla_policies DROP default_priority_enabled');
        $this->addSql('ALTER TABLE sla_policies DROP default_priority');
        $this->addSql('ALTER TABLE sla_policies DROP default_assignee_enabled');
        $this->addSql('ALTER TABLE sla_policies DROP default_assignee_id');
    }
}
