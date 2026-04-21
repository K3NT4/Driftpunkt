<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds generic impact areas metadata for addon catalog entries';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('addon_modules')) {
            return;
        }

        $table = $schema->getTable('addon_modules');
        if (!$table->hasColumn('impact_areas')) {
            $this->addSql('ALTER TABLE addon_modules ADD COLUMN impact_areas CLOB DEFAULT NULL');
        }

        $this->addSql("UPDATE addon_modules
SET impact_areas = 'Nyhetsmodul\n/portal/admin/nyheter\n/portal/technician/nyheter\nPublik artikelrendering'
WHERE slug = 'news-editor-plus'
  AND (impact_areas IS NULL OR impact_areas = '')");
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('addon_modules')) {
            return;
        }

        $this->addSql('ALTER TABLE addon_modules DROP COLUMN impact_areas');
    }
}
