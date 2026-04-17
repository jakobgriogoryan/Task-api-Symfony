<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notifications,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function persist(User $recipient, NotificationType $type, array $payload): Notification
    {
        $notification = (new Notification())
            ->setRecipient($recipient)
            ->setType($type)
            ->setPayload($payload);

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    /**
     * @return array<int, Notification>
     */
    public function unseenForUser(User $user, int $limit = 50): array
    {
        return $this->notifications->findUnseenForUser($user, $limit);
    }

    public function markAsRead(User $user, int $id): Notification
    {
        $notification = $this->notifications->find($id);
        if ($notification === null) {
            throw new NotFoundHttpException('Notification not found.');
        }

        if ($notification->getRecipient()?->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('Cannot access this notification.');
        }

        $notification->markSeen();
        $this->em->flush();

        return $notification;
    }

    public function markAllAsRead(User $user): int
    {
        return $this->notifications->markAllSeen($user);
    }
}
