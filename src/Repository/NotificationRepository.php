<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return array<int, Notification>
     */
    public function findUnseenForUser(User $user, int $limit = 50): array
    {
        /** @var array<int, Notification> $result */
        $result = $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :user')
            ->andWhere('n.seen = false')
            ->setParameter('user', $user)
            ->orderBy('n.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function markAllSeen(User $user): int
    {
        $now = new \DateTimeImmutable();

        return (int) $this->createQueryBuilder('n')
            ->update()
            ->set('n.seen', ':seen')
            ->set('n.seenAt', ':seenAt')
            ->andWhere('n.recipient = :user')
            ->andWhere('n.seen = false')
            ->setParameter('seen', true)
            ->setParameter('seenAt', $now)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
