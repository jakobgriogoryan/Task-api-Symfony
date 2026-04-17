<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Enum\NotificationType;
use App\Service\Notification\NotificationService;
use App\Tests\Integration\AbstractApiTestCase;

final class NotificationTest extends AbstractApiTestCase
{
    public function testUnseenListAndMarkRead(): void
    {
        $user = $this->createUser('n@example.com');
        $token = $this->loginAs($user);

        /** @var NotificationService $service */
        $service = static::getContainer()->get(NotificationService::class);
        $n1 = $service->persist($user, NotificationType::TASK_ASSIGNED, ['task_id' => 1]);
        $n2 = $service->persist($user, NotificationType::TASK_UPDATED, ['task_id' => 2]);

        $list = $this->json('GET', '/api/notifications/unseen', token: $token);
        self::assertSame(200, $list['status']);
        self::assertSame(2, $list['body']['data']['meta']['count']);

        $read = $this->json(
            'POST',
            "/api/notifications/{$n1->getId()}/read",
            token: $token,
        );
        self::assertSame(200, $read['status']);

        $after = $this->json('GET', '/api/notifications/unseen', token: $token);
        self::assertSame(1, $after['body']['data']['meta']['count']);

        $readAll = $this->json('POST', '/api/notifications/read-all', token: $token);
        self::assertSame(200, $readAll['status']);
        self::assertSame(1, $readAll['body']['data']['marked']);

        $final = $this->json('GET', '/api/notifications/unseen', token: $token);
        self::assertSame(0, $final['body']['data']['meta']['count']);
    }

    public function testCannotMarkAnotherUsersNotificationAsRead(): void
    {
        $alice = $this->createUser('a@example.com');
        $bob = $this->createUser('b@example.com');
        $bobToken = $this->loginAs($bob);

        /** @var NotificationService $service */
        $service = static::getContainer()->get(NotificationService::class);
        $n = $service->persist($alice, NotificationType::TASK_UPDATED, []);

        $res = $this->json('POST', "/api/notifications/{$n->getId()}/read", token: $bobToken);
        self::assertSame(403, $res['status']);
    }
}
