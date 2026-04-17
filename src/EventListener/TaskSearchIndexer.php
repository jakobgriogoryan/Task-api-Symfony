<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Task;
use App\Search\TaskSearchInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Keeps the external search index in sync with the relational store.
 *
 * The active {@see TaskSearchInterface} decides if indexing is a no-op
 * (Postgres FTS) or a live write (Elasticsearch).
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final class TaskSearchIndexer
{
    public function __construct(private readonly TaskSearchInterface $search)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Task) {
            $this->search->index($entity);
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Task) {
            $this->search->index($entity);
        }
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Task && $entity->getId() !== null) {
            $this->search->remove((int) $entity->getId());
        }
    }
}
