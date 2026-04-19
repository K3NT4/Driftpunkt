<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds ticket escalation levels and optional SLA escalation defaults';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tickets ADD escalation_level VARCHAR(255) NOT NULL DEFAULT 'none'");
        $this->addSql('ALTER TABLE sla_policies ADD default_escalation_enabled BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE sla_policies ADD default_escalation_level VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sla_policies DROP default_escalation_enabled');
        $this->addSql('ALTER TABLE sla_policies DROP default_escalation_level');
        $this->addSql('ALTER TABLE tickets DROP escalation_level');
    }
}
