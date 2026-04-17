<?php

declare(strict_types=1);

namespace App\Service\Comment;

use App\Dto\Comment\SaveCommentRequest;
use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use App\Event\CommentCreatedEvent;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class CommentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CommentRepository $comments,
        private readonly EventDispatcherInterface $events,
    ) {
    }

    public function create(Task $task, User $author, SaveCommentRequest $dto): Comment
    {
        $comment = (new Comment())
            ->setTask($task)
            ->setAuthor($author)
            ->setContent($dto->content);

        $this->em->persist($comment);
        $this->em->flush();

        $this->events->dispatch(new CommentCreatedEvent($comment, $author));

        return $comment;
    }

    public function update(Comment $comment, SaveCommentRequest $dto): Comment
    {
        $comment->setContent($dto->content);
        $comment->touch();
        $this->em->flush();

        return $comment;
    }

    public function delete(Comment $comment): void
    {
        $this->em->remove($comment);
        $this->em->flush();
    }

    /**
     * @return array{items: array<int, Comment>, total: int, page: int, per_page: int}
     */
    public function listForTask(Task $task, int $page, int $perPage): array
    {
        return $this->comments->findByTaskPaginated($task, $page, $perPage);
    }
}
