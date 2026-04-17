<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

final class TaskUpdatedEvent extends Event
{
    /**
     * @param array<int, string> $changedFields
     */
    public function __construct(
        public readonly Task $task,
        public readonly User $actor,
        public readonly array $changedFields,
    ) {
    }
}
