<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: users, projects, project_members, tasks, comments,
 * notifications. Supports both PostgreSQL (production/dev) and SQLite (tests).
 */
final class Version20260417074843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema for users, projects, tasks, comments, notifications.';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            $this->upPostgres();
        } elseif ($platform instanceof SqlitePlatform) {
            $this->upSqlite();
        } else {
            $this->abortIf(true, 'Unsupported database platform: ' . $platform::class);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS comments');
        $this->addSql('DROP TABLE IF EXISTS notifications');
        $this->addSql('DROP TABLE IF EXISTS tasks');
        $this->addSql('DROP TABLE IF EXISTS project_members');
        $this->addSql('DROP TABLE IF EXISTS projects');
        $this->addSql('DROP TABLE IF EXISTS "users"');
    }

    private function upPostgres(): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "users" (
                id SERIAL PRIMARY KEY,
                email VARCHAR(180) NOT NULL,
                name VARCHAR(120) NOT NULL,
                password VARCHAR(255) NOT NULL,
                selected_role VARCHAR(32) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON "users" (email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE projects (
                id SERIAL PRIMARY KEY,
                owner_id INTEGER NOT NULL REFERENCES "users" (id) ON DELETE CASCADE,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_projects_owner ON projects (owner_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE project_members (
                project_id INTEGER NOT NULL REFERENCES projects (id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL REFERENCES "users" (id) ON DELETE CASCADE,
                PRIMARY KEY (project_id, user_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_project_members_user ON project_members (user_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE tasks (
                id SERIAL PRIMARY KEY,
                project_id INTEGER NOT NULL REFERENCES projects (id) ON DELETE CASCADE,
                assignee_id INTEGER NULL REFERENCES "users" (id) ON DELETE SET NULL,
                creator_id INTEGER NOT NULL REFERENCES "users" (id) ON DELETE CASCADE,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                status VARCHAR(32) NOT NULL,
                due_date DATE NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_tasks_project ON tasks (project_id)');
        $this->addSql('CREATE INDEX idx_tasks_assignee ON tasks (assignee_id)');
        $this->addSql('CREATE INDEX idx_tasks_creator ON tasks (creator_id)');
        $this->addSql('CREATE INDEX idx_tasks_status ON tasks (status)');
        $this->addSql('CREATE INDEX idx_tasks_due_date ON tasks (due_date)');

        $this->addSql(<<<'SQL'
            CREATE TABLE comments (
                id SERIAL PRIMARY KEY,
                task_id INTEGER NOT NULL REFERENCES tasks (id) ON DELETE CASCADE,
                author_id INTEGER NOT NULL REFERENCES "users" (id) ON DELETE CASCADE,
                content TEXT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_comments_task ON comments (task_id)');
        $this->addSql('CREATE INDEX idx_comments_author ON comments (author_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE notifications (
                id SERIAL PRIMARY KEY,
                recipient_id INTEGER NOT NULL REFERENCES "users" (id) ON DELETE CASCADE,
                type VARCHAR(64) NOT NULL,
                payload JSONB NOT NULL,
                seen BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                seen_at TIMESTAMP(0) WITHOUT TIME ZONE NULL
            )
        SQL);
        $this->addSql('CREATE INDEX idx_notifications_recipient ON notifications (recipient_id)');
        $this->addSql('CREATE INDEX idx_notifications_seen ON notifications (seen)');
    }

    private function upSqlite(): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "users" (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                email VARCHAR(180) NOT NULL,
                name VARCHAR(120) NOT NULL,
                password VARCHAR(255) NOT NULL,
                selected_role VARCHAR(32) NOT NULL,
                created_at DATETIME NOT NULL
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON "users" (email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                owner_id INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                description CLOB DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES "users" (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_projects_owner ON projects (owner_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE project_members (
                project_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                PRIMARY KEY (project_id, user_id),
                CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                CONSTRAINT fk_pm_user FOREIGN KEY (user_id) REFERENCES "users" (id) ON DELETE CASCADE
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                project_id INTEGER NOT NULL,
                assignee_id INTEGER DEFAULT NULL,
                creator_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                description CLOB DEFAULT NULL,
                status VARCHAR(32) NOT NULL,
                due_date DATE DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                CONSTRAINT fk_tasks_assignee FOREIGN KEY (assignee_id) REFERENCES "users" (id) ON DELETE SET NULL,
                CONSTRAINT fk_tasks_creator FOREIGN KEY (creator_id) REFERENCES "users" (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_tasks_project ON tasks (project_id)');
        $this->addSql('CREATE INDEX idx_tasks_assignee ON tasks (assignee_id)');
        $this->addSql('CREATE INDEX idx_tasks_creator ON tasks (creator_id)');
        $this->addSql('CREATE INDEX idx_tasks_status ON tasks (status)');
        $this->addSql('CREATE INDEX idx_tasks_due_date ON tasks (due_date)');

        $this->addSql(<<<'SQL'
            CREATE TABLE comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                task_id INTEGER NOT NULL,
                author_id INTEGER NOT NULL,
                content CLOB NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                CONSTRAINT fk_comments_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
                CONSTRAINT fk_comments_author FOREIGN KEY (author_id) REFERENCES "users" (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_comments_task ON comments (task_id)');
        $this->addSql('CREATE INDEX idx_comments_author ON comments (author_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                recipient_id INTEGER NOT NULL,
                type VARCHAR(64) NOT NULL,
                payload CLOB NOT NULL,
                seen BOOLEAN NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                seen_at DATETIME DEFAULT NULL,
                CONSTRAINT fk_notifications_recipient FOREIGN KEY (recipient_id) REFERENCES "users" (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_notifications_recipient ON notifications (recipient_id)');
        $this->addSql('CREATE INDEX idx_notifications_seen ON notifications (seen)');
    }
}
