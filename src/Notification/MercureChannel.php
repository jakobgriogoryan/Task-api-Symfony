<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Notification;
use App\Service\Mercure\MercureTokenProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publishes notifications to the authenticated user's private Mercure topic
 * for real-time UI updates.
 */
#[AsTaggedItem(index: 'mercure', priority: 90)]
final class MercureChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function deliver(Notification $notification): void
    {
        $user = $notification->getRecipient();
        if ($user === null) {
            return;
        }

        $topic = MercureTokenProvider::userTopic($user);

        try {
            $this->hub->publish(new Update(
                $topic,
                (string) json_encode([
                    'id' => $notification->getId(),
                    'type' => $notification->getType()->value,
                    'payload' => $notification->getPayload(),
                    'created_at' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ], JSON_THROW_ON_ERROR),
                private: true,
            ));
        } catch (\Throwable $e) {
            // Never fail the worker for a transient publish error.
            $this->logger->warning('Mercure publish failed.', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
