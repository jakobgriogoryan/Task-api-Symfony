<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds a PostgreSQL tsvector GIN index over tasks.(title, description) used
 * by {@see \App\Search\PostgresTaskSearch}. No-op on other platforms.
 */
final class Version20260417074900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add GIN tsvector index on tasks(title, description) for Postgres full-text search.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_tasks_fts
            ON tasks
            USING GIN (
                to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(description, ''))
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        $this->addSql('DROP INDEX IF EXISTS idx_tasks_fts');
    }
}
