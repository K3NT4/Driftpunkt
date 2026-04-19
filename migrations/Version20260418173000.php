<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds password reset request storage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE password_reset_requests (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, CONSTRAINT FK_B5A8209BA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B5A8209B2C16BA69 ON password_reset_requests (token_hash)');
        $this->addSql('CREATE INDEX IDX_B5A8209BA76ED395 ON password_reset_requests (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_reset_requests');
    }
}
