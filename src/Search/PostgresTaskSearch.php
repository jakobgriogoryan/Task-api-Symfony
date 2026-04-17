<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Task;
use Doctrine\DBAL\Connection;

/**
 * Portable full-text search implementation.
 *
 * - On PostgreSQL it uses plainto_tsquery against a GIN tsvector index
 *   created in the migrations.
 * - On any other driver (SQLite used in tests) it falls back to ILIKE/LIKE
 *   so the tests don't need Postgres.
 */
class PostgresTaskSearch implements TaskSearchInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function search(string $query, int $page = 1, int $perPage = 15): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['ids' => [], 'total' => 0, 'driver' => $this->driverName()];
        }

        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $platform = $this->connection->getDatabasePlatform()::class;
        $isPostgres = str_contains(strtolower($platform), 'postgres');

        if ($isPostgres) {
            $sql = <<<'SQL'
                SELECT id
                FROM tasks
                WHERE to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(description, ''))
                      @@ plainto_tsquery('simple', :q)
                ORDER BY ts_rank(
                    to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(description, '')),
                    plainto_tsquery('simple', :q)
                ) DESC, id DESC
                LIMIT :limit OFFSET :offset
            SQL;

            $countSql = <<<'SQL'
                SELECT COUNT(*) FROM tasks
                WHERE to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(description, ''))
                      @@ plainto_tsquery('simple', :q)
            SQL;
        } else {
            $sql = <<<'SQL'
                SELECT id FROM tasks
                WHERE LOWER(title) LIKE :q OR LOWER(COALESCE(description, '')) LIKE :q
                ORDER BY id DESC
                LIMIT :limit OFFSET :offset
            SQL;
            $countSql = <<<'SQL'
                SELECT COUNT(*) FROM tasks
                WHERE LOWER(title) LIKE :q OR LOWER(COALESCE(description, '')) LIKE :q
            SQL;

            $query = '%'.mb_strtolower($query).'%';
        }

        $ids = array_map(
            'intval',
            $this->connection->fetchFirstColumn($sql, [
                'q' => $query,
                'limit' => $perPage,
                'offset' => $offset,
            ]),
        );

        /** @var int $total */
        $total = (int) $this->connection->fetchOne($countSql, ['q' => $query]);

        return [
            'ids' => $ids,
            'total' => $total,
            'driver' => $this->driverName(),
        ];
    }

    public function index(Task $task): void
    {
        // No-op: Postgres FTS reads directly from the source table.
    }

    public function remove(int $taskId): void
    {
        // No-op: Postgres FTS reads directly from the source table.
    }

    private function driverName(): string
    {
        $platform = strtolower($this->connection->getDatabasePlatform()::class);

        return str_contains($platform, 'postgres') ? 'postgres_fts' : 'like_fallback';
    }
}
