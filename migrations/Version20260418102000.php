<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds optional field dependency rules to ticket intake fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket_intake_fields ADD COLUMN depends_on_field_key VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket_intake_fields ADD COLUMN depends_on_field_value VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
