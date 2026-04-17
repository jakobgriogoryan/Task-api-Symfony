<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Notification;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Strategy pattern: every channel knows how to deliver a persisted
 * notification. Adding new channels (email, Slack, SMS, …) means adding a
 * new implementation without touching the handler.
 */
#[AutoconfigureTag('app.notification_channel')]
interface NotificationChannelInterface
{
    public function deliver(Notification $notification): void;
}
