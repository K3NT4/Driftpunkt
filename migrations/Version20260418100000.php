<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds field types and selectable options to ticket intake fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ticket_intake_fields ADD COLUMN field_type VARCHAR(255) NOT NULL DEFAULT 'text'");
        $this->addSql("ALTER TABLE ticket_intake_fields ADD COLUMN select_options CLOB NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
    }
}
