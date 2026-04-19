<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418098000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds intake-based routing conditions to ticket routing rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket_routing_rules ADD COLUMN intake_field_key VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket_routing_rules ADD COLUMN intake_field_value VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
