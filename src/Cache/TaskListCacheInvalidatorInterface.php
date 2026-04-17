<?php

declare(strict_types=1);

namespace App\Cache;

interface TaskListCacheInvalidatorInterface
{
    public function invalidateProject(int $projectId): void;
}
