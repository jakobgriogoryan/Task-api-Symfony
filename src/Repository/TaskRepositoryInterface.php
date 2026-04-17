<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;

interface TaskRepositoryInterface
{
    /**
     * @param array{
     *     status?: string|null,
     *     due_from?: string|null,
     *     due_to?: string|null,
     *     q?: string|null,
     *     assignee?: int|null,
     *     sort?: string|null,
     *     page?: int,
     *     per_page?: int
     * } $filters
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     per_page: int
     * }
     */
    public function findForListing(Project $project, array $filters): array;
}
