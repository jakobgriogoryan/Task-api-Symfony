<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Returns projects visible to the user: owned OR user is a member.
     *
     * @return array{items: array<int, Project>, total: int, page: int, per_page: int}
     */
    public function findForUserPaginated(User $user, int $page = 1, int $perPage = 15): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.members', 'm')
            ->andWhere('p.owner = :user OR m.id = :userId')
            ->setParameter('user', $user)
            ->setParameter('userId', $user->getId())
            ->groupBy('p.id')
            ->orderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);

        return [
            'items' => iterator_to_array($paginator->getIterator()),
            'total' => $paginator->count(),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array{items: array<int, Project>, total: int, page: int, per_page: int}
     */
    public function findAllPaginated(int $page = 1, int $perPage = 15): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: true);

        return [
            'items' => iterator_to_array($paginator->getIterator()),
            'total' => $paginator->count(),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
}
