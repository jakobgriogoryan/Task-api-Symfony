<?php

declare(strict_types=1);

namespace App\Dto\Project;

use Symfony\Component\Validator\Constraints as Assert;

class SaveProjectRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    public string $name = '';

    #[Assert\Length(max: 5000)]
    public ?string $description = null;

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $dto = new self();
        $dto->name = (string) ($payload['name'] ?? '');
        $dto->description = isset($payload['description']) ? (string) $payload['description'] : null;

        return $dto;
    }
}
