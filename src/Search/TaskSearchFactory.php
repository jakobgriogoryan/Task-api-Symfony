<?php

declare(strict_types=1);

namespace App\Search;

/**
 * Factory (Strategy pattern selector) returning the concrete
 * {@see TaskSearchInterface} based on runtime configuration.
 *
 * Valid values for SEARCH_DRIVER:
 *   - "elastic" -> {@see ElasticTaskSearch}
 *   - "pg"      -> {@see PostgresTaskSearch} (default)
 */
final class TaskSearchFactory
{
    public function __construct(
        private readonly PostgresTaskSearch $postgres,
        private readonly ElasticTaskSearch $elastic,
        private readonly string $searchDriver = 'pg',
    ) {
    }

    public function create(): TaskSearchInterface
    {
        return match (strtolower(trim($this->searchDriver))) {
            'elastic', 'elasticsearch', 'es' => $this->elastic,
            default => $this->postgres,
        };
    }
}
