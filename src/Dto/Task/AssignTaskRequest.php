<?php

declare(strict_types=1);

namespace App\Dto\Task;

use Symfony\Component\Validator\Constraints as Assert;

class AssignTaskRequest
{
    #[Assert\PositiveOrZero]
    public ?int $assigneeId = null;

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $dto = new self();
        if (\array_key_exists('assignee_id', $payload)) {
            $dto->assigneeId = $payload['assignee_id'] === null ? null : (int) $payload['assignee_id'];
        }

        return $dto;
    }
}
