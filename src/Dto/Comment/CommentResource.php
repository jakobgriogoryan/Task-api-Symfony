<?php

declare(strict_types=1);

namespace App\Dto\Comment;

use App\Entity\Comment;

final class CommentResource
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Comment $comment): array
    {
        return [
            'id' => $comment->getId(),
            'task_id' => $comment->getTask()?->getId(),
            'author_id' => $comment->getAuthor()?->getId(),
            'content' => $comment->getContent(),
            'created_at' => $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updated_at' => $comment->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<int, Comment> $comments
     *
     * @return array<int, array<string, mixed>>
     */
    public static function collection(array $comments): array
    {
        return array_map([self::class, 'from'], $comments);
    }
}
