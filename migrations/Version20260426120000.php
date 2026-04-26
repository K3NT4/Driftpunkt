<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds system audit logs for superadmin maintenance actions';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('system_audit_logs')) {
            return;
        }

        $table = $schema->createTable('system_audit_logs');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('actor_id', 'integer', ['notnull' => false]);
        $table->addColumn('actor_email', 'string', ['length' => 180]);
        $table->addColumn('action', 'string', ['length' => 100]);
        $table->addColumn('title', 'string', ['length' => 180]);
        $table->addColumn('message', 'text');
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addIndex(['actor_id'], 'IDX_SYSTEM_AUDIT_ACTOR');
        if ($schema->hasTable('users')) {
            $table->addForeignKeyConstraint('users', ['actor_id'], ['id'], ['onDelete' => 'SET NULL']);
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('system_audit_logs')) {
            $schema->dropTable('system_audit_logs');
        }
    }
}
