<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Notification;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * The in-app channel is a no-op: the notification row is already persisted
 * by {@see \App\Messenger\SendNotificationMessageHandler} and surfaced via
 * the GET /api/notifications/unseen endpoint.
 */
#[AsTaggedItem(index: 'in_app', priority: 100)]
final class InAppChannel implements NotificationChannelInterface
{
    public function deliver(Notification $notification): void
    {
        // Persistence is the delivery.
    }
}
