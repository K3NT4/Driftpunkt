<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260425120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds per-company monthly report email settings';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('companies')) {
            return;
        }

        $table = $schema->getTable('companies');
        if (!$table->hasColumn('monthly_report_enabled')) {
            $this->addSql('ALTER TABLE companies ADD monthly_report_enabled BOOLEAN DEFAULT 0 NOT NULL');
        }
        if (!$table->hasColumn('monthly_report_recipient_email')) {
            $this->addSql('ALTER TABLE companies ADD monthly_report_recipient_email VARCHAR(180) DEFAULT NULL');
        }
        if (!$table->hasColumn('monthly_report_last_sent_at')) {
            $this->addSql('ALTER TABLE companies ADD monthly_report_last_sent_at DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('companies')) {
            return;
        }

        $table = $schema->getTable('companies');
        if ($table->hasColumn('monthly_report_enabled')) {
            $this->addSql('ALTER TABLE companies DROP monthly_report_enabled');
        }
        if ($table->hasColumn('monthly_report_recipient_email')) {
            $this->addSql('ALTER TABLE companies DROP monthly_report_recipient_email');
        }
        if ($table->hasColumn('monthly_report_last_sent_at')) {
            $this->addSql('ALTER TABLE companies DROP monthly_report_last_sent_at');
        }
    }
}
