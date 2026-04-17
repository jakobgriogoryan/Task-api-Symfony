<?php

declare(strict_types=1);

namespace App\Dto\Project;

use Symfony\Component\Validator\Constraints as Assert;

class AddMemberRequest
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $userId = 0;

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $dto = new self();
        $dto->userId = (int) ($payload['user_id'] ?? 0);

        return $dto;
    }
}
