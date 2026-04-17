<?php

declare(strict_types=1);

namespace App\Dto\Comment;

use Symfony\Component\Validator\Constraints as Assert;

class SaveCommentRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 5000)]
    public string $content = '';

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $dto = new self();
        $dto->content = (string) ($payload['content'] ?? '');

        return $dto;
    }
}
