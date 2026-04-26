<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds imported ticket shadow people for external imports';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('imported_ticket_people')) {
            return;
        }

        $table = $schema->createTable('imported_ticket_people');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('ticket_id', 'integer');
        $table->addColumn('linked_user_id', 'integer', ['notnull' => false]);
        $table->addColumn('role', 'string', ['length' => 32]);
        $table->addColumn('display_name', 'string', ['length' => 180]);
        $table->addColumn('source_system', 'string', ['length' => 64]);
        $table->addColumn('source_reference', 'string', ['length' => 180, 'notnull' => false]);
        $table->addColumn('linked_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addIndex(['ticket_id'], 'IDX_IMPORTED_TICKET_PEOPLE_TICKET');
        $table->addIndex(['linked_user_id'], 'IDX_IMPORTED_TICKET_PEOPLE_USER');
        $table->addIndex(['role', 'display_name'], 'IDX_IMPORTED_TICKET_PEOPLE_ROLE_NAME');

        if ($schema->hasTable('tickets')) {
            $table->addForeignKeyConstraint('tickets', ['ticket_id'], ['id'], ['onDelete' => 'CASCADE']);
        }
        if ($schema->hasTable('users')) {
            $table->addForeignKeyConstraint('users', ['linked_user_id'], ['id'], ['onDelete' => 'SET NULL']);
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('imported_ticket_people')) {
            $schema->dropTable('imported_ticket_people');
        }
    }
}
