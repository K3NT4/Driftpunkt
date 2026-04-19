<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418099000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds default priority and escalation to ticket routing rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket_routing_rules ADD COLUMN default_priority VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket_routing_rules ADD COLUMN default_escalation_level VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
