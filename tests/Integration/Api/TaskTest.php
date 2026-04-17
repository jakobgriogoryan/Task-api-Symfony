<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Tests\Integration\AbstractApiTestCase;

final class TaskTest extends AbstractApiTestCase
{
    public function testTaskLifecycle(): void
    {
        $owner = $this->createUser('o@example.com');
        $token = $this->loginAs($owner);

        $project = $this->json('POST', '/api/projects', ['name' => 'Proj'], token: $token);
        $projectId = $project['body']['data']['id'];

        $create = $this->json('POST', "/api/projects/{$projectId}/tasks", [
            'title' => 'First task',
            'description' => 'write tests',
            'status' => 'todo',
            'due_date' => '2026-05-01',
        ], token: $token);
        self::assertSame(201, $create['status']);
        $taskId = $create['body']['data']['id'];

        $list = $this->json('GET', "/api/projects/{$projectId}/tasks", token: $token);
        self::assertSame(200, $list['status']);
        self::assertSame(1, $list['body']['data']['meta']['total']);

        $filter = $this->json(
            'GET',
            "/api/projects/{$projectId}/tasks?status=done",
            token: $token,
        );
        self::assertSame(0, $filter['body']['data']['meta']['total']);

        $update = $this->json('PATCH', "/api/tasks/{$taskId}", [
            'status' => 'in-progress',
            'title' => 'First task (updated)',
        ], token: $token);
        self::assertSame(200, $update['status']);
        self::assertSame('in-progress', $update['body']['data']['status']);
        self::assertSame('First task (updated)', $update['body']['data']['title']);

        $destroy = $this->json('DELETE', "/api/tasks/{$taskId}", token: $token);
        self::assertSame(204, $destroy['status']);
    }

    public function testAssignTaskToProjectMember(): void
    {
        $owner = $this->createUser('owner@example.com');
        $member = $this->createUser('member@example.com');
        $ownerToken = $this->loginAs($owner);

        $project = $this->json('POST', '/api/projects', ['name' => 'Team'], token: $ownerToken);
        $projectId = $project['body']['data']['id'];

        $this->json('POST', "/api/projects/{$projectId}/members", [
            'user_id' => $member->getId(),
        ], token: $ownerToken);

        $create = $this->json('POST', "/api/projects/{$projectId}/tasks", [
            'title' => 'Needs assignment',
        ], token: $ownerToken);
        $taskId = $create['body']['data']['id'];

        $assign = $this->json('POST', "/api/tasks/{$taskId}/assign", [
            'assignee_id' => $member->getId(),
        ], token: $ownerToken);

        self::assertSame(200, $assign['status']);
        self::assertSame($member->getId(), $assign['body']['data']['assignee_id']);
    }

    public function testAssignFailsForNonMember(): void
    {
        $owner = $this->createUser('own@example.com');
        $stranger = $this->createUser('stranger@example.com');
        $ownerToken = $this->loginAs($owner);

        $project = $this->json('POST', '/api/projects', ['name' => 'Closed'], token: $ownerToken);
        $projectId = $project['body']['data']['id'];

        $create = $this->json('POST', "/api/projects/{$projectId}/tasks", [
            'title' => 'Stuck',
        ], token: $ownerToken);
        $taskId = $create['body']['data']['id'];

        $res = $this->json('POST', "/api/tasks/{$taskId}/assign", [
            'assignee_id' => $stranger->getId(),
        ], token: $ownerToken);

        self::assertSame(400, $res['status']);
    }

    public function testOutsiderCannotViewTasks(): void
    {
        $owner = $this->createUser('o2@example.com');
        $outsider = $this->createUser('out@example.com');
        $ownerToken = $this->loginAs($owner);
        $outsiderToken = $this->loginAs($outsider);

        $project = $this->json('POST', '/api/projects', ['name' => 'Private'], token: $ownerToken);
        $projectId = $project['body']['data']['id'];

        $create = $this->json('POST', "/api/projects/{$projectId}/tasks", [
            'title' => 'Secret',
        ], token: $ownerToken);
        $taskId = $create['body']['data']['id'];

        $show = $this->json('GET', "/api/tasks/{$taskId}", token: $outsiderToken);
        self::assertSame(403, $show['status']);
    }

    public function testValidationFailsWithoutTitle(): void
    {
        $user = $this->createUser('v@example.com');
        $token = $this->loginAs($user);

        $project = $this->json('POST', '/api/projects', ['name' => 'pp'], token: $token);
        $projectId = $project['body']['data']['id'];

        $res = $this->json('POST', "/api/projects/{$projectId}/tasks", [
            'description' => 'no title',
        ], token: $token);

        self::assertSame(422, $res['status']);
        self::assertSame('Validation failed.', $res['body']['error']);
    }
}
