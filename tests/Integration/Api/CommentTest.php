<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Tests\Integration\AbstractApiTestCase;

final class CommentTest extends AbstractApiTestCase
{
    public function testCommentLifecycle(): void
    {
        $user = $this->createUser('c@example.com');
        $token = $this->loginAs($user);

        $project = $this->json('POST', '/api/projects', ['name' => 'Proj'], token: $token);
        $projectId = $project['body']['data']['id'];

        $task = $this->json(
            'POST',
            "/api/projects/{$projectId}/tasks",
            ['title' => 'Discussion'],
            token: $token,
        );
        $taskId = $task['body']['data']['id'];

        $create = $this->json(
            'POST',
            "/api/tasks/{$taskId}/comments",
            ['content' => 'First comment'],
            token: $token,
        );
        self::assertSame(201, $create['status']);
        $commentId = $create['body']['data']['id'];
        self::assertSame('First comment', $create['body']['data']['content']);

        $list = $this->json('GET', "/api/tasks/{$taskId}/comments", token: $token);
        self::assertSame(200, $list['status']);
        self::assertSame(1, $list['body']['data']['meta']['total']);

        $update = $this->json(
            'PATCH',
            "/api/comments/{$commentId}",
            ['content' => 'Edited'],
            token: $token,
        );
        self::assertSame(200, $update['status']);
        self::assertSame('Edited', $update['body']['data']['content']);

        $destroy = $this->json('DELETE', "/api/comments/{$commentId}", token: $token);
        self::assertSame(204, $destroy['status']);
    }

    public function testOtherUserCannotEditMyComment(): void
    {
        $owner = $this->createUser('o@example.com');
        $other = $this->createUser('x@example.com');
        $ownerToken = $this->loginAs($owner);
        $otherToken = $this->loginAs($other);

        $project = $this->json('POST', '/api/projects', ['name' => 'PP'], token: $ownerToken);
        $projectId = $project['body']['data']['id'];

        $this->json('POST', "/api/projects/{$projectId}/members", [
            'user_id' => $other->getId(),
        ], token: $ownerToken);

        $task = $this->json(
            'POST',
            "/api/projects/{$projectId}/tasks",
            ['title' => 'T'],
            token: $ownerToken,
        );
        $taskId = $task['body']['data']['id'];

        $comment = $this->json(
            'POST',
            "/api/tasks/{$taskId}/comments",
            ['content' => 'Owner comment'],
            token: $ownerToken,
        );
        $commentId = $comment['body']['data']['id'];

        $edit = $this->json(
            'PATCH',
            "/api/comments/{$commentId}",
            ['content' => 'Hacked'],
            token: $otherToken,
        );
        self::assertSame(403, $edit['status']);
    }
}
