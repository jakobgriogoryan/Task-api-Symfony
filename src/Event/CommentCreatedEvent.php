<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Comment;
use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

final class CommentCreatedEvent extends Event
{
    public function __construct(
        public readonly Comment $comment,
        public readonly User $actor,
    ) {
    }
}
