<?php

declare(strict_types=1);

namespace App\Dto\Notification;

use App\Entity\Notification;

final class NotificationResource
{
    /**
     * @return array<string, mixed>
     */
    public static function from(Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'type' => $notification->getType()->value,
            'payload' => $notification->getPayload(),
            'seen' => $notification->isSeen(),
            'created_at' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'seen_at' => $notification->getSeenAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<int, Notification> $notifications
     *
     * @return array<int, array<string, mixed>>
     */
    public static function collection(array $notifications): array
    {
        return array_map([self::class, 'from'], $notifications);
    }
}
