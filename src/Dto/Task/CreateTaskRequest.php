<?php

declare(strict_types=1);

namespace App\Dto\Task;

use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

class CreateTaskRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    public string $title = '';

    #[Assert\Length(max: 10_000)]
    public ?string $description = null;

    #[Assert\Choice(callback: [self::class, 'allowedStatuses'])]
    public string $status = TaskStatus::TODO->value;

    #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'due_date must be YYYY-MM-DD.')]
    public ?string $dueDate = null;

    #[Assert\PositiveOrZero]
    public ?int $assigneeId = null;

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $dto = new self();
        $dto->title = (string) ($payload['title'] ?? '');
        $dto->description = isset($payload['description']) ? (string) $payload['description'] : null;
        $dto->status = (string) ($payload['status'] ?? TaskStatus::TODO->value);
        $dto->dueDate = isset($payload['due_date']) && $payload['due_date'] !== null
            ? (string) $payload['due_date']
            : null;
        $dto->assigneeId = isset($payload['assignee_id']) && $payload['assignee_id'] !== null
            ? (int) $payload['assignee_id']
            : null;

        return $dto;
    }

    /**
     * @return array<int, string>
     */
    public static function allowedStatuses(): array
    {
        return array_map(static fn (TaskStatus $s) => $s->value, TaskStatus::cases());
    }

    public function taskStatus(): TaskStatus
    {
        return TaskStatus::tryFrom($this->status) ?? TaskStatus::TODO;
    }

    public function dueDateObject(): ?\DateTimeImmutable
    {
        if ($this->dueDate === null || $this->dueDate === '') {
            return null;
        }

        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $this->dueDate);

        return $d === false ? null : $d;
    }
}
