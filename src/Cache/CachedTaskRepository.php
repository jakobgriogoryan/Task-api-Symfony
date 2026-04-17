<?php

declare(strict_types=1);

namespace App\Cache;

use App\Entity\Project;
use App\Repository\TaskRepository;
use App\Repository\TaskRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Cache-aware decorator around {@see TaskRepository} (Decorator pattern).
 *
 * Caches task listings in a tagged pool so that writes inside
 * {@see \App\Service\Task\TaskService} can invalidate them with O(1) cost.
 * Also implements {@see TaskListCacheInvalidatorInterface} so services never
 * reach into the cache component directly.
 */
final class CachedTaskRepository implements TaskRepositoryInterface, TaskListCacheInvalidatorInterface
{
    public function __construct(
        private readonly TaskRepository $inner,
        #[Autowire(service: 'cache.task_lists')]
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    public function findForListing(Project $project, array $filters): array
    {
        $projectId = (int) $project->getId();
        $key = $this->buildKey($projectId, $filters);
        $tag = self::tagForProject($projectId);

        return $this->cache->get($key, function (ItemInterface $item) use ($project, $filters, $tag) {
            $item->tag($tag);
            $item->expiresAfter(60);

            return $this->inner->findForListing($project, $filters);
        });
    }

    public function invalidateProject(int $projectId): void
    {
        if ($projectId <= 0) {
            return;
        }

        $this->cache->invalidateTags([self::tagForProject($projectId)]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function buildKey(int $projectId, array $filters): string
    {
        ksort($filters);

        return sprintf('tasks_list_%d_%s', $projectId, md5(serialize($filters)));
    }

    private static function tagForProject(int $projectId): string
    {
        return sprintf('task_project_%d', $projectId);
    }
}
