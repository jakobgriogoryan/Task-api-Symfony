<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Concerns\ApiJsonResponse;
use App\Dto\Notification\NotificationResource;
use App\Entity\User;
use App\Service\Notification\NotificationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/notifications', name: 'api_notifications_')]
#[OA\Tag(name: 'Notifications')]
class NotificationController extends AbstractController
{
    use ApiJsonResponse;

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    #[Route('/unseen', name: 'unseen', methods: ['GET'])]
    #[OA\Get(summary: 'List unseen notifications for the current user')]
    public function unseen(#[CurrentUser] User $user): JsonResponse
    {
        $items = $this->notifications->unseenForUser($user);

        return $this->success([
            'items' => NotificationResource::collection($items),
            'meta' => ['count' => \count($items)],
        ]);
    }

    #[Route('/{id}/read', name: 'read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function read(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $notification = $this->notifications->markAsRead($user, $id);

        return $this->success(NotificationResource::from($notification));
    }

    #[Route('/read-all', name: 'read_all', methods: ['POST'])]
    public function readAll(#[CurrentUser] User $user): JsonResponse
    {
        $count = $this->notifications->markAllAsRead($user);

        return $this->success(['marked' => $count]);
    }
}
