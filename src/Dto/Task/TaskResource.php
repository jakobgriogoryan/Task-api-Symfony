<?php

declare(strict_types=1);

namespace App\Dto\Task;

use App\Entity\Task;

final class TaskResource
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Task $task): array
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

    /**
     * @param array<int, Task> $tasks
     *
     * @return array<int, array<string, mixed>>
     */
    public static function collection(array $tasks): array
    {
        return array_map([self::class, 'from'], $tasks);
    }
}
