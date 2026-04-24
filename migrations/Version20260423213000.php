<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds Telefonstod customer profiles, number inventory, extensions and change log';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('companies')) {
            return;
        }

        if ($this->isMysqlLikePlatform()) {
            $this->upMysql($schema);

            return;
        }

        if (!$schema->hasTable('telefonstod_customer_profiles')) {
            $this->addSql('CREATE TABLE telefonstod_customer_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, company_id INTEGER NOT NULL, wx3_customer_reference VARCHAR(128) DEFAULT NULL, main_phone_number VARCHAR(64) DEFAULT NULL, solution_type VARCHAR(180) DEFAULT NULL, internal_documentation CLOB DEFAULT NULL, last_synced_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_TELEFONSTOD_CUSTOMER_PROFILES_COMPANY FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_telefonstod_customer_profiles_company ON telefonstod_customer_profiles (company_id)');
            $this->addSql('CREATE INDEX IDX_TELEFONSTOD_CUSTOMER_PROFILES_COMPANY ON telefonstod_customer_profiles (company_id)');
        }

        if (!$schema->hasTable('telefonstod_phone_numbers')) {
            $this->addSql('CREATE TABLE telefonstod_phone_numbers (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, customer_profile_id INTEGER NOT NULL, phone_number VARCHAR(64) NOT NULL, number_type VARCHAR(32) NOT NULL, extension_number VARCHAR(32) DEFAULT NULL, display_name VARCHAR(180) DEFAULT NULL, status VARCHAR(32) NOT NULL, queue_name VARCHAR(180) DEFAULT NULL, notes CLOB DEFAULT NULL, last_changed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_TELEFONSTOD_PHONE_NUMBERS_PROFILE FOREIGN KEY (customer_profile_id) REFERENCES telefonstod_customer_profiles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_telefonstod_phone_numbers_number ON telefonstod_phone_numbers (phone_number)');
            $this->addSql('CREATE INDEX IDX_TELEFONSTOD_PHONE_NUMBERS_PROFILE ON telefonstod_phone_numbers (customer_profile_id)');
        }

        if (!$schema->hasTable('telefonstod_extensions')) {
            $this->addSql('CREATE TABLE telefonstod_extensions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, customer_profile_id INTEGER NOT NULL, extension_number VARCHAR(32) NOT NULL, display_name VARCHAR(180) NOT NULL, direct_number VARCHAR(64) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, mobile_phone VARCHAR(64) DEFAULT NULL, status VARCHAR(32) NOT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_TELEFONSTOD_EXTENSIONS_PROFILE FOREIGN KEY (customer_profile_id) REFERENCES telefonstod_customer_profiles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE UNIQUE INDEX uniq_telefonstod_extensions_profile_extension ON telefonstod_extensions (customer_profile_id, extension_number)');
            $this->addSql('CREATE INDEX IDX_TELEFONSTOD_EXTENSIONS_PROFILE ON telefonstod_extensions (customer_profile_id)');
        }

        if (!$schema->hasTable('telefonstod_change_log')) {
            $this->addSql('CREATE TABLE telefonstod_change_log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, customer_profile_id INTEGER NOT NULL, ticket_id INTEGER DEFAULT NULL, object_type VARCHAR(64) NOT NULL, object_label VARCHAR(180) NOT NULL, field_name VARCHAR(120) NOT NULL, old_value CLOB DEFAULT NULL, new_value CLOB DEFAULT NULL, comment CLOB DEFAULT NULL, changed_by VARCHAR(180) NOT NULL, changed_at DATETIME NOT NULL, CONSTRAINT FK_TELEFONSTOD_CHANGE_LOG_PROFILE FOREIGN KEY (customer_profile_id) REFERENCES telefonstod_customer_profiles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_TELEFONSTOD_CHANGE_LOG_TICKET FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
            $this->addSql('CREATE INDEX IDX_TELEFONSTOD_CHANGE_LOG_PROFILE ON telefonstod_change_log (customer_profile_id)');
            $this->addSql('CREATE INDEX IDX_TELEFONSTOD_CHANGE_LOG_TICKET ON telefonstod_change_log (ticket_id)');
        }
    }

    private function upMysql(Schema $schema): void
    {
        if (!$schema->hasTable('telefonstod_customer_profiles')) {
            $this->addSql('CREATE TABLE telefonstod_customer_profiles (id INT AUTO_INCREMENT NOT NULL, company_id INT NOT NULL, wx3_customer_reference VARCHAR(128) DEFAULT NULL, main_phone_number VARCHAR(64) DEFAULT NULL, solution_type VARCHAR(180) DEFAULT NULL, internal_documentation LONGTEXT DEFAULT NULL, last_synced_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_telefonstod_customer_profiles_company (company_id), INDEX IDX_TELEFONSTOD_CUSTOMER_PROFILES_COMPANY (company_id), PRIMARY KEY(id))');
            $this->addSql('ALTER TABLE telefonstod_customer_profiles ADD CONSTRAINT FK_TELEFONSTOD_CUSTOMER_PROFILES_COMPANY FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        }

        if (!$schema->hasTable('telefonstod_phone_numbers')) {
            $this->addSql('CREATE TABLE telefonstod_phone_numbers (id INT AUTO_INCREMENT NOT NULL, customer_profile_id INT NOT NULL, phone_number VARCHAR(64) NOT NULL, number_type VARCHAR(32) NOT NULL, extension_number VARCHAR(32) DEFAULT NULL, display_name VARCHAR(180) DEFAULT NULL, status VARCHAR(32) NOT NULL, queue_name VARCHAR(180) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, last_changed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_telefonstod_phone_numbers_number (phone_number), INDEX IDX_TELEFONSTOD_PHONE_NUMBERS_PROFILE (customer_profile_id), PRIMARY KEY(id))');
            $this->addSql('ALTER TABLE telefonstod_phone_numbers ADD CONSTRAINT FK_TELEFONSTOD_PHONE_NUMBERS_PROFILE FOREIGN KEY (customer_profile_id) REFERENCES telefonstod_customer_profiles (id) ON DELETE CASCADE');
        }

        if (!$schema->hasTable('telefonstod_extensions')) {
            $this->addSql('CREATE TABLE telefonstod_extensions (id INT AUTO_INCREMENT NOT NULL, customer_profile_id INT NOT NULL, extension_number VARCHAR(32) NOT NULL, display_name VARCHAR(180) NOT NULL, direct_number VARCHAR(64) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, mobile_phone VARCHAR(64) DEFAULT NULL, status VARCHAR(32) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_telefonstod_extensions_profile_extension (customer_profile_id, extension_number), INDEX IDX_TELEFONSTOD_EXTENSIONS_PROFILE (customer_profile_id), PRIMARY KEY(id))');
            $this->addSql('ALTER TABLE telefonstod_extensions ADD CONSTRAINT FK_TELEFONSTOD_EXTENSIONS_PROFILE FOREIGN KEY (customer_profile_id) REFERENCES telefonstod_customer_profiles (id) ON DELETE CASCADE');
        }

        if (!$schema->hasTable('telefonstod_change_log')) {
            $this->addSql('CREATE TABLE telefonstod_change_log (id INT AUTO_INCREMENT NOT NULL, customer_profile_id INT NOT NULL, ticket_id INT DEFAULT NULL, object_type VARCHAR(64) NOT NULL, object_label VARCHAR(180) NOT NULL, field_name VARCHAR(120) NOT NULL, old_value LONGTEXT DEFAULT NULL, new_value LONGTEXT DEFAULT NULL, comment LONGTEXT DEFAULT NULL, changed_by VARCHAR(180) NOT NULL, changed_at DATETIME NOT NULL, INDEX IDX_TELEFONSTOD_CHANGE_LOG_PROFILE (customer_profile_id), INDEX IDX_TELEFONSTOD_CHANGE_LOG_TICKET (ticket_id), PRIMARY KEY(id))');
            $this->addSql('ALTER TABLE telefonstod_change_log ADD CONSTRAINT FK_TELEFONSTOD_CHANGE_LOG_PROFILE FOREIGN KEY (customer_profile_id) REFERENCES telefonstod_customer_profiles (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE telefonstod_change_log ADD CONSTRAINT FK_TELEFONSTOD_CHANGE_LOG_TICKET FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE SET NULL');
        }
    }

    private function isMysqlLikePlatform(): bool
    {
        $platformClass = mb_strtolower($this->connection->getDatabasePlatform()::class);

        return str_contains($platformClass, 'mysql') || str_contains($platformClass, 'mariadb');
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('telefonstod_change_log')) {
            $this->addSql('DROP TABLE telefonstod_change_log');
        }

        if ($schema->hasTable('telefonstod_extensions')) {
            $this->addSql('DROP TABLE telefonstod_extensions');
        }

        if ($schema->hasTable('telefonstod_phone_numbers')) {
            $this->addSql('DROP TABLE telefonstod_phone_numbers');
        }

        if ($schema->hasTable('telefonstod_customer_profiles')) {
            $this->addSql('DROP TABLE telefonstod_customer_profiles');
        }
    }
}
