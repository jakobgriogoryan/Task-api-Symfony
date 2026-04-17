<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Notification\NotificationChannelInterface;
use App\Repository\UserRepository;
use App\Service\Notification\NotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles {@see SendNotificationMessage} on the async transport.
 *
 * 1. Persists a {@see \App\Entity\Notification} row.
 * 2. Fans it out through every registered {@see NotificationChannelInterface}
 *    (Strategy pattern). Failing channels are logged, not fatal.
 */
#[AsMessageHandler]
final class SendNotificationMessageHandler
{
    /**
     * @param iterable<NotificationChannelInterface> $channels
     */
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly UserRepository $users,
        #[AutowireIterator('app.notification_channel')]
        private readonly iterable $channels,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendNotificationMessage $message): void
    {
        $recipient = $this->users->find($message->recipientId);
        if ($recipient === null) {
            $this->logger->warning('Notification recipient not found.', [
                'recipient_id' => $message->recipientId,
            ]);

            return;
        }

        $notification = $this->notifications->persist(
            $recipient,
            $message->type,
            $message->payload,
        );

        foreach ($this->channels as $channel) {
            try {
                $channel->deliver($notification);
            } catch (\Throwable $e) {
                $this->logger->error('Notification channel failed.', [
                    'channel' => $channel::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
