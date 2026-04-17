<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Enum\NotificationType;

/**
 * Immutable message dispatched to the async transport when a domain event
 * needs to notify a user.
 */
final class SendNotificationMessage
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $recipientId,
        public readonly NotificationType $type,
        public readonly array $payload,
    ) {
    }
}
