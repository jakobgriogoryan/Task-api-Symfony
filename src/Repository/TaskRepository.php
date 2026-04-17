<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Task;
use App\Enum\TaskStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository implements TaskRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function findForListing(Project $project, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.project = :project')
            ->setParameter('project', $project);

        $this->applyFilters($qb, $filters);
        $this->applySort($qb, (string) ($filters['sort'] ?? '-created_at'));

        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);

        $items = [];
        foreach ($paginator->getIterator() as $task) {
            $items[] = $this->hydrate($task);
        }

        return [
            'items' => $items,
            'total' => $paginator->count(),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Used by the global search implementation to hydrate results once IDs are known.
     *
     * @param array<int, int> $ids
     *
     * @return array<int, Task>
     */
    public function findByIdsInOrder(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, Task> $rows */
        $rows = $this->createQueryBuilder('t')
            ->andWhere('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row->getId()] = $row;
        }

        $sorted = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $sorted[] = $byId[$id];
            }
        }

        return $sorted;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['status'])) {
            $status = TaskStatus::tryFrom((string) $filters['status']);
            if ($status !== null) {
                $qb->andWhere('t.status = :status')->setParameter('status', $status);
            }
        }

        if (!empty($filters['due_from'])) {
            $from = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $filters['due_from']);
            if ($from !== false) {
                $qb->andWhere('t.dueDate >= :dueFrom')->setParameter('dueFrom', $from);
            }
        }

        if (!empty($filters['due_to'])) {
            $to = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $filters['due_to']);
            if ($to !== false) {
                $qb->andWhere('t.dueDate <= :dueTo')->setParameter('dueTo', $to);
            }
        }

        if (!empty($filters['assignee'])) {
            $qb->andWhere('t.assignee = :assignee')->setParameter('assignee', (int) $filters['assignee']);
        }

        if (!empty($filters['q'])) {
            $term = '%'.mb_strtolower((string) $filters['q']).'%';
            $qb->andWhere('LOWER(t.title) LIKE :q OR LOWER(COALESCE(t.description, \'\')) LIKE :q')
                ->setParameter('q', $term);
        }
    }

    private function applySort(QueryBuilder $qb, string $sort): void
    {
        $direction = str_starts_with($sort, '-') ? 'DESC' : 'ASC';
        $field = ltrim($sort, '-+');

        $allowed = [
            'created_at' => 't.createdAt',
            'updated_at' => 't.updatedAt',
            'due_date' => 't.dueDate',
            'title' => 't.title',
            'status' => 't.status',
        ];

        $column = $allowed[$field] ?? 't.createdAt';
        $qb->orderBy($column, $direction);
    }

    /**
     * @return array<string, mixed>
     */
    private function hydrate(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'project_id' => $task->getProject()?->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus()->value,
            'due_date' => $task->getDueDate()?->format('Y-m-d'),
            'assignee_id' => $task->getAssignee()?->getId(),
            'creator_id' => $task->getCreator()?->getId(),
            'created_at' => $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $task->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
