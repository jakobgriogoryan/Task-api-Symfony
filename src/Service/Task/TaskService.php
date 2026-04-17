<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Cache\TaskListCacheInvalidatorInterface;
use App\Dto\Task\CreateTaskRequest;
use App\Dto\Task\UpdateTaskRequest;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Event\TaskAssignedEvent;
use App\Event\TaskUpdatedEvent;
use App\Repository\TaskRepository;
use App\Repository\TaskRepositoryInterface;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TaskService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TaskRepositoryInterface $taskReader,
        private readonly TaskRepository $taskWriter,
        private readonly UserRepository $users,
        private readonly EventDispatcherInterface $events,
        private readonly TaskListCacheInvalidatorInterface $cacheInvalidator,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listForProject(Project $project, array $filters): array
    {
        return $this->taskReader->findForListing($project, $filters);
    }

    public function find(int $id): Task
    {
        $task = $this->taskWriter->find($id);
        if ($task === null) {
            throw new NotFoundHttpException('Task not found.');
        }

        return $task;
    }

    public function create(Project $project, User $actor, CreateTaskRequest $dto): Task
    {
        $task = (new Task())
            ->setProject($project)
            ->setCreator($actor)
            ->setTitle($dto->title)
            ->setDescription($dto->description)
            ->setStatus($dto->taskStatus())
            ->setDueDate($dto->dueDateObject());

        if ($dto->assigneeId !== null && $dto->assigneeId > 0) {
            $assignee = $this->resolveAssignee($project, $dto->assigneeId);
            $task->setAssignee($assignee);
        }

        $this->em->persist($task);
        $this->em->flush();

        $this->cacheInvalidator->invalidateProject((int) $project->getId());

        if ($task->getAssignee() !== null) {
            $this->events->dispatch(new TaskAssignedEvent($task, $task->getAssignee(), $actor));
        }

        return $task;
    }

    public function update(Task $task, User $actor, UpdateTaskRequest $dto): Task
    {
        $changed = [];

        if (isset($dto->touched['title']) && $dto->title !== null) {
            $task->setTitle($dto->title);
            $changed[] = 'title';
        }
        if (isset($dto->touched['description'])) {
            $task->setDescription($dto->description);
            $changed[] = 'description';
        }
        if (isset($dto->touched['status']) && $dto->taskStatus() !== null) {
            $task->setStatus($dto->taskStatus());
            $changed[] = 'status';
        }
        if (isset($dto->touched['due_date'])) {
            $task->setDueDate($dto->clearDueDate ? null : $dto->dueDateObject());
            $changed[] = 'due_date';
        }

        if ($changed !== []) {
            $task->touch();
            $this->em->flush();
            $this->cacheInvalidator->invalidateProject((int) $task->getProject()?->getId());
            $this->events->dispatch(new TaskUpdatedEvent($task, $actor, $changed));
        }

        return $task;
    }

    public function delete(Task $task): void
    {
        $projectId = (int) $task->getProject()?->getId();
        $this->em->remove($task);
        $this->em->flush();
        $this->cacheInvalidator->invalidateProject($projectId);
    }

    public function assign(Task $task, User $actor, ?int $assigneeId): Task
    {
        $project = $task->getProject();
        if ($project === null) {
            throw new BadRequestHttpException('Task has no project.');
        }

        if ($assigneeId === null || $assigneeId === 0) {
            $task->setAssignee(null);
        } else {
            $assignee = $this->resolveAssignee($project, $assigneeId);
            $task->setAssignee($assignee);
        }

        $task->touch();
        $this->em->flush();
        $this->cacheInvalidator->invalidateProject((int) $project->getId());

        if ($task->getAssignee() !== null) {
            $this->events->dispatch(new TaskAssignedEvent($task, $task->getAssignee(), $actor));
        }

        return $task;
    }

    private function resolveAssignee(Project $project, int $assigneeId): User
    {
        $user = $this->users->find($assigneeId);
        if ($user === null) {
            throw new NotFoundHttpException('Assignee not found.');
        }

        if (!$project->isOwner($user) && !$project->hasMember($user)) {
            throw new BadRequestHttpException('Assignee must be the owner or a member of the project.');
        }

        return $user;
    }
}
