<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use App\Enum\UserRole;
use Symfony\Component\Validator\Constraints as Assert;

class RegisterRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 255)]
    public string $password = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 120)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'allowedRoles'])]
    public string $role = '';

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $dto = new self();
        $dto->email = (string) ($payload['email'] ?? '');
        $dto->password = (string) ($payload['password'] ?? '');
        $dto->name = (string) ($payload['name'] ?? '');
        $dto->role = (string) ($payload['role'] ?? '');

        return $dto;
    }

    /**
     * @return array<int, string>
     */
    public static function allowedRoles(): array
    {
        return array_map(static fn (UserRole $r) => $r->value, UserRole::cases());
    }

    public function selectedRole(): ?UserRole
    {
        return UserRole::tryFrom($this->role);
    }
}
