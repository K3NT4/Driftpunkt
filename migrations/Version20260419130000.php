<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds optional company-specific ticket reference sequences';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE companies ADD use_custom_ticket_sequence BOOLEAN DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE companies ADD ticket_reference_prefix VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE companies ADD ticket_sequence_next_number INTEGER DEFAULT 1001 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE companies DROP ticket_sequence_next_number');
        $this->addSql('ALTER TABLE companies DROP ticket_reference_prefix');
        $this->addSql('ALTER TABLE companies DROP use_custom_ticket_sequence');
    }
}
