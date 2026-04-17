<?php

declare(strict_types=1);

namespace App\Search;

use App\Entity\Task;

interface TaskSearchInterface
{
    /**
     * @return array{ids: array<int, int>, total: int, driver: string}
     */
    public function search(string $query, int $page = 1, int $perPage = 15): array;

    public function index(Task $task): void;

    public function remove(int $taskId): void;
}
