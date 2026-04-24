<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds parent-child company hierarchy support';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('companies')) {
            return;
        }

        $table = $schema->getTable('companies');
        if ($table->hasColumn('parent_company_id')) {
            return;
        }

        $this->addSql('ALTER TABLE companies ADD parent_company_id INTEGER DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_5F93B6F3727ACA70 ON companies (parent_company_id)');

        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE companies ADD CONSTRAINT FK_5F93B6F3727ACA70 FOREIGN KEY (parent_company_id) REFERENCES companies (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('companies')) {
            return;
        }

        $table = $schema->getTable('companies');
        if (!$table->hasColumn('parent_company_id')) {
            return;
        }

        $this->addSql('DROP INDEX IDX_5F93B6F3727ACA70');

        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->addSql('ALTER TABLE companies DROP CONSTRAINT FK_5F93B6F3727ACA70');
        }

        $this->addSql('ALTER TABLE companies DROP COLUMN parent_company_id');
    }
}
