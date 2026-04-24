<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422090000 extends AbstractMigration
{
    private const RELEASE_EMAIL = 'system@driftpunkt.local';
    private const RELEASE_NOTES = 'Karnmodulen for nyhetsredigering markerades som slappt vid katalogregistrering.';

    public function getDescription(): string
    {
        return 'Marks the core News Editor Plus addon as released and seeds release history';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('addon_modules')) {
            return;
        }

        $this->addSql(sprintf(
            "UPDATE addon_modules
SET released_at = COALESCE(released_at, CURRENT_TIMESTAMP),
    released_by_email = COALESCE(released_by_email, '%s'),
    is_enabled = 1,
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'news-editor-plus'",
            self::RELEASE_EMAIL,
        ));

        if (!$schema->hasTable('addon_release_logs')) {
            return;
        }

        $this->addSql(sprintf(
            "INSERT INTO addon_release_logs (
    addon_id,
    released_by_email,
    version,
    summary,
    release_notes,
    released_at
)
SELECT
    addon.id,
    '%s',
    addon.version,
    'Core addon seeded as released in addon catalog.',
    '%s',
    COALESCE(addon.released_at, CURRENT_TIMESTAMP)
FROM addon_modules addon
WHERE addon.slug = 'news-editor-plus'
  AND NOT EXISTS (
      SELECT 1
      FROM addon_release_logs log
      WHERE log.addon_id = addon.id
        AND log.revoked_at IS NULL
  )",
            self::RELEASE_EMAIL,
            self::RELEASE_NOTES,
        ));
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('addon_release_logs')) {
            $this->addSql(sprintf(
                "DELETE FROM addon_release_logs
WHERE released_by_email = '%s'
  AND summary = 'Core addon seeded as released in addon catalog.'
  AND release_notes = '%s'",
                self::RELEASE_EMAIL,
                self::RELEASE_NOTES,
            ));
        }

        if (!$schema->hasTable('addon_modules')) {
            return;
        }

        $this->addSql(sprintf(
            "UPDATE addon_modules
SET released_at = NULL,
    released_by_email = NULL,
    updated_at = CURRENT_TIMESTAMP
WHERE slug = 'news-editor-plus'
  AND released_by_email = '%s'",
            self::RELEASE_EMAIL,
        ));
    }
}
