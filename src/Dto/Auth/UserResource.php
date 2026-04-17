<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use App\Entity\User;

final class UserResource
{
    /**
     * @return array<string, mixed>
     */
    public static function from(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'role' => $user->getSelectedRole()->value,
            'roles' => $user->getRoles(),
            'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
