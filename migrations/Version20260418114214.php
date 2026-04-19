<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418114214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket_routing_rules ADD COLUMN intake_template_family VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__ticket_routing_rules AS SELECT id, name, customer_type, request_type, impact_level, intake_field_key, intake_field_value, default_priority, default_escalation_level, sort_order, is_active, created_at, updated_at, team_id, category_id, default_sla_policy_id, default_assignee_id FROM ticket_routing_rules');
        $this->addSql('DROP TABLE ticket_routing_rules');
        $this->addSql('CREATE TABLE ticket_routing_rules (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(180) NOT NULL, customer_type VARCHAR(255) DEFAULT NULL, request_type VARCHAR(255) DEFAULT NULL, impact_level VARCHAR(255) DEFAULT NULL, intake_field_key VARCHAR(80) DEFAULT NULL, intake_field_value VARCHAR(180) DEFAULT NULL, default_priority VARCHAR(255) DEFAULT NULL, default_escalation_level VARCHAR(255) DEFAULT NULL, sort_order INTEGER NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, team_id INTEGER NOT NULL, category_id INTEGER DEFAULT NULL, default_sla_policy_id INTEGER DEFAULT NULL, default_assignee_id INTEGER DEFAULT NULL, CONSTRAINT FK_77D928E5296CD8AE FOREIGN KEY (team_id) REFERENCES technician_teams (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_77D928E512469DE2 FOREIGN KEY (category_id) REFERENCES ticket_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_77D928E51568807 FOREIGN KEY (default_sla_policy_id) REFERENCES sla_policies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_77D928E58D1F6ED6 FOREIGN KEY (default_assignee_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ticket_routing_rules (id, name, customer_type, request_type, impact_level, intake_field_key, intake_field_value, default_priority, default_escalation_level, sort_order, is_active, created_at, updated_at, team_id, category_id, default_sla_policy_id, default_assignee_id) SELECT id, name, customer_type, request_type, impact_level, intake_field_key, intake_field_value, default_priority, default_escalation_level, sort_order, is_active, created_at, updated_at, team_id, category_id, default_sla_policy_id, default_assignee_id FROM __temp__ticket_routing_rules');
        $this->addSql('DROP TABLE __temp__ticket_routing_rules');
        $this->addSql('CREATE INDEX IDX_77D928E5296CD8AE ON ticket_routing_rules (team_id)');
        $this->addSql('CREATE INDEX IDX_77D928E512469DE2 ON ticket_routing_rules (category_id)');
        $this->addSql('CREATE INDEX IDX_77D928E51568807 ON ticket_routing_rules (default_sla_policy_id)');
        $this->addSql('CREATE INDEX IDX_77D928E58D1F6ED6 ON ticket_routing_rules (default_assignee_id)');
    }
}
