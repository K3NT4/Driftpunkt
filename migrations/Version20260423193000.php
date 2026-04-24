<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds company setting for parent-company access to shared tickets';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('companies')) {
            return;
        }

        $table = $schema->getTable('companies');
        if ($table->hasColumn('allow_parent_company_access_to_shared_tickets')) {
            return;
        }

        $this->addSql('ALTER TABLE companies ADD allow_parent_company_access_to_shared_tickets BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('companies')) {
            return;
        }

        $table = $schema->getTable('companies');
        if (!$table->hasColumn('allow_parent_company_access_to_shared_tickets')) {
            return;
        }

        $this->addSql('ALTER TABLE companies DROP COLUMN allow_parent_company_access_to_shared_tickets');
    }
}
