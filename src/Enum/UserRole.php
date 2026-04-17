<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case ADMIN = 'admin';
    case REVIEWER = 'reviewer';
    case MEMBER = 'member';

    public function securityRole(): string
    {
        return match ($this) {
            self::ADMIN => 'ROLE_ADMIN',
            self::REVIEWER => 'ROLE_REVIEWER',
            self::MEMBER => 'ROLE_MEMBER',
        };
    }
}
