<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418095200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalizes SQLite index names for ticket categories and routing rules';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $this->addSql('DROP INDEX IF EXISTS UNIQ_3F1C4C855E237E06');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AC60D43C5E237E06 ON ticket_categories (name)');

        $this->addSql('DROP INDEX IF EXISTS IDX_BB71E53B296CD8AE');
        $this->addSql('DROP INDEX IF EXISTS IDX_BB71E53B12469DE2');
        $this->addSql('DROP INDEX IF EXISTS IDX_BB71E53BF5A04706');
        $this->addSql('DROP INDEX IF EXISTS IDX_BB71E53BB8CA29A7');
        $this->addSql('CREATE INDEX IDX_77D928E5296CD8AE ON ticket_routing_rules (team_id)');
        $this->addSql('CREATE INDEX IDX_77D928E512469DE2 ON ticket_routing_rules (category_id)');
        $this->addSql('CREATE INDEX IDX_77D928E51568807 ON ticket_routing_rules (default_sla_policy_id)');
        $this->addSql('CREATE INDEX IDX_77D928E58D1F6ED6 ON ticket_routing_rules (default_assignee_id)');
    }

    public function down(Schema $schema): void
    {
    }
}
