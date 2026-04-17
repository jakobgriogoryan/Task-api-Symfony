<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

final class TaskAssignedEvent extends Event
{
    public function __construct(
        public readonly Task $task,
        public readonly User $assignee,
        public readonly User $actor,
    ) {
    }
}
