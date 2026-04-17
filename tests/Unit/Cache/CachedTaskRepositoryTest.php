<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cache;

use App\Cache\CachedTaskRepository;
use App\Entity\Project;
use App\Repository\TaskRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

#[AllowMockObjectsWithoutExpectations]
final class CachedTaskRepositoryTest extends TestCase
{
    public function testReadsHitCacheAndInvalidationForcesReload(): void
    {
        $project = new Project();
        $project->setName('P');
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, 42);

        $calls = 0;
        $inner = $this->createMock(TaskRepository::class);
        $inner->method('findForListing')->willReturnCallback(function () use (&$calls): array {
            ++$calls;

            return [['id' => $calls]];
        });

        $cache = new TagAwareAdapter(new ArrayAdapter());
        $repo = new CachedTaskRepository($inner, $cache);

        $filters = ['status' => 'todo', 'page' => 1, 'per_page' => 10];

        $first = $repo->findForListing($project, $filters);
        $second = $repo->findForListing($project, $filters);
        self::assertSame($first, $second, 'Second call must be served from cache');
        self::assertSame(1, $calls, 'Inner repository must be hit only once');

        $repo->invalidateProject(42);
        $third = $repo->findForListing($project, $filters);
        self::assertNotSame($first, $third);
        self::assertSame(2, $calls);
    }

    public function testDifferentFiltersProduceDistinctCacheEntries(): void
    {
        $project = new Project();
        $project->setName('P');
        (new \ReflectionProperty(Project::class, 'id'))->setValue($project, 1);

        $inner = $this->createMock(TaskRepository::class);
        $inner->expects(self::exactly(2))
            ->method('findForListing')
            ->willReturnOnConsecutiveCalls([['a' => 1]], [['b' => 2]]);

        $cache = new TagAwareAdapter(new ArrayAdapter());
        $repo = new CachedTaskRepository($inner, $cache);

        self::assertSame([['a' => 1]], $repo->findForListing($project, ['status' => 'todo']));
        self::assertSame([['b' => 2]], $repo->findForListing($project, ['status' => 'done']));
    }

    public function testInvalidateProjectWithZeroIsNoop(): void
    {
        $inner = $this->createMock(TaskRepository::class);
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $repo = new CachedTaskRepository($inner, $cache);

        $repo->invalidateProject(0);
        $this->expectNotToPerformAssertions();
    }
}
