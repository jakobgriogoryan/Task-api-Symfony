<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Tests\Integration\AbstractApiTestCase;

final class TaskSearchTest extends AbstractApiTestCase
{
    public function testEmptyQueryReturnsEmptyPage(): void
    {
        $user = $this->createUser('s@example.com');
        $token = $this->loginAs($user);

        $res = $this->json('GET', '/api/tasks/search?q=', token: $token);

        self::assertSame(200, $res['status']);
        self::assertSame([], $res['body']['data']['items']);
        self::assertSame(0, $res['body']['data']['meta']['total']);
    }

    public function testFullTextSearchFindsTask(): void
    {
        $user = $this->createUser('ss@example.com');
        $token = $this->loginAs($user);

        $project = $this->json('POST', '/api/projects', ['name' => 'Proj'], token: $token);
        $projectId = $project['body']['data']['id'];

        $this->json('POST', "/api/projects/{$projectId}/tasks", [
            'title' => 'Deploy staging pipeline',
            'description' => 'kubernetes rollout',
        ], token: $token);

        $this->json('POST', "/api/projects/{$projectId}/tasks", [
            'title' => 'Write report',
        ], token: $token);

        $res = $this->json('GET', '/api/tasks/search?q=pipeline', token: $token);

        self::assertSame(200, $res['status']);
        self::assertGreaterThanOrEqual(1, $res['body']['data']['meta']['total']);
        self::assertSame('Deploy staging pipeline', $res['body']['data']['items'][0]['title']);
    }
}
