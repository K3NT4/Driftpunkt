<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registers the News Editor Plus addon in the addon catalog';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('addon_modules')) {
            return;
        }

        $this->addSql("INSERT INTO addon_modules (
    slug,
    name,
    description,
    version,
    admin_route,
    source_label,
    notes,
    install_status,
    dependencies,
    environment_variables,
    setup_checklist,
    health_status,
    verified_at,
    released_at,
    released_by_email,
    is_enabled,
    created_at,
    updated_at
)
SELECT
    'news-editor-plus',
    'News Editor Plus',
    'Ger nyhetsmodulen en rikare editor med callouts, checklistor, CTA-knappar, kodblock och förbättrad förhandsvisning.',
    '1.0.0',
    '/portal/admin/nyheter',
    'Core addon',
    'Första addon-paketet för nyhetsmodulen. Förbättrar redaktörsflödet utan extern integration.',
    'installed',
    'Nyhetsmodul
Publik artikelrendering',
    NULL,
    'Verifiera toolbar
Verifiera preview
Verifiera publik rendering',
    'healthy',
    CURRENT_TIMESTAMP,
    NULL,
    NULL,
    1,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1 FROM addon_modules WHERE slug = 'news-editor-plus'
)");
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('addon_modules')) {
            return;
        }

        $this->addSql("DELETE FROM addon_modules WHERE slug = 'news-editor-plus'");
    }
}
