<?php

declare(strict_types=1);

namespace App\Dto\Task;

use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Partial update — only fields present in the payload are validated/applied.
 */
class UpdateTaskRequest
{
    #[Assert\Length(min: 1, max: 255)]
    public ?string $title = null;

    #[Assert\Length(max: 10_000)]
    public ?string $description = null;

    #[Assert\Choice(callback: [CreateTaskRequest::class, 'allowedStatuses'])]
    public ?string $status = null;

    #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'due_date must be YYYY-MM-DD.')]
    public ?string $dueDate = null;

    public bool $clearDueDate = false;

    /** @var array<string, bool> */
    public array $touched = [];

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $dto = new self();

        if (\array_key_exists('title', $payload)) {
            $dto->title = (string) $payload['title'];
            $dto->touched['title'] = true;
        }
        if (\array_key_exists('description', $payload)) {
            $dto->description = $payload['description'] === null ? null : (string) $payload['description'];
            $dto->touched['description'] = true;
        }
        if (\array_key_exists('status', $payload)) {
            $dto->status = (string) $payload['status'];
            $dto->touched['status'] = true;
        }
        if (\array_key_exists('due_date', $payload)) {
            if ($payload['due_date'] === null || $payload['due_date'] === '') {
                $dto->dueDate = null;
                $dto->clearDueDate = true;
            } else {
                $dto->dueDate = (string) $payload['due_date'];
            }
            $dto->touched['due_date'] = true;
        }

        return $dto;
    }

    public function taskStatus(): ?TaskStatus
    {
        return $this->status === null ? null : TaskStatus::tryFrom($this->status);
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
