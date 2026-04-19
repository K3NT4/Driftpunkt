<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds authentication mode and Microsoft OAuth fields to mail servers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE mail_servers ADD authentication_mode VARCHAR(32) DEFAULT 'password' NOT NULL");
        $this->addSql('ALTER TABLE mail_servers ADD oauth_tenant_id VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE mail_servers ADD oauth_client_id VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE mail_servers ADD oauth_client_secret VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__mail_servers AS SELECT id, name, direction, transport_type, host, port, encryption, username, password, from_address, from_name, description, is_active, is_primary_outbound, fallback_to_primary, created_at, updated_at FROM mail_servers');
        $this->addSql('DROP TABLE mail_servers');
        $this->addSql("CREATE TABLE mail_servers (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(180) NOT NULL, direction VARCHAR(20) NOT NULL, transport_type VARCHAR(40) NOT NULL, host VARCHAR(255) NOT NULL, port INTEGER NOT NULL, encryption VARCHAR(20) NOT NULL, username VARCHAR(180) DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, from_address VARCHAR(180) DEFAULT NULL, from_name VARCHAR(180) DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_primary_outbound BOOLEAN NOT NULL, fallback_to_primary BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)");
        $this->addSql('INSERT INTO mail_servers (id, name, direction, transport_type, host, port, encryption, username, password, from_address, from_name, description, is_active, is_primary_outbound, fallback_to_primary, created_at, updated_at) SELECT id, name, direction, transport_type, host, port, encryption, username, password, from_address, from_name, description, is_active, is_primary_outbound, fallback_to_primary, created_at, updated_at FROM __temp__mail_servers');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6C6C4F195E237E06 ON mail_servers (name)');
        $this->addSql('DROP TABLE __temp__mail_servers');
    }
}
