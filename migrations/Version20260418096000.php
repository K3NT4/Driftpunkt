<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418096000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds request type and impact level to tickets and routing rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tickets ADD COLUMN request_type VARCHAR(255) NOT NULL DEFAULT 'incident'");
        $this->addSql("ALTER TABLE tickets ADD COLUMN impact_level VARCHAR(255) NOT NULL DEFAULT 'single_user'");
        $this->addSql('ALTER TABLE ticket_routing_rules ADD COLUMN request_type VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket_routing_rules ADD COLUMN impact_level VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
