<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Enum\UserRole;
use App\Tests\Integration\AbstractApiTestCase;

final class ProjectTest extends AbstractApiTestCase
{
    public function testCreateListShowUpdateDelete(): void
    {
        $user = $this->createUser('owner@example.com');
        $token = $this->loginAs($user);

        $create = $this->json('POST', '/api/projects', [
            'name' => 'Backend',
            'description' => 'API project',
        ], token: $token);
        self::assertSame(201, $create['status']);
        $projectId = $create['body']['data']['id'];
        self::assertSame('Backend', $create['body']['data']['name']);

        $list = $this->json('GET', '/api/projects', token: $token);
        self::assertSame(200, $list['status']);
        self::assertSame(1, $list['body']['data']['meta']['total']);
        self::assertSame($projectId, $list['body']['data']['items'][0]['id']);

        $show = $this->json('GET', "/api/projects/{$projectId}", token: $token);
        self::assertSame(200, $show['status']);
        self::assertSame('Backend', $show['body']['data']['name']);

        $update = $this->json('PATCH', "/api/projects/{$projectId}", [
            'name' => 'Backend v2',
        ], token: $token);
        self::assertSame(200, $update['status']);
        self::assertSame('Backend v2', $update['body']['data']['name']);

        $destroy = $this->json('DELETE', "/api/projects/{$projectId}", token: $token);
        self::assertSame(204, $destroy['status']);
    }

    public function testMemberCannotEditProjectTheyDoNotOwn(): void
    {
        $owner = $this->createUser('o@example.com');
        $intruder = $this->createUser('x@example.com');

        $ownerToken = $this->loginAs($owner);
        $intruderToken = $this->loginAs($intruder);

        $create = $this->json('POST', '/api/projects', [
            'name' => 'Secret',
        ], token: $ownerToken);
        $projectId = $create['body']['data']['id'];

        $update = $this->json('PATCH', "/api/projects/{$projectId}", [
            'name' => 'Hacked',
        ], token: $intruderToken);
        self::assertSame(403, $update['status']);

        $destroy = $this->json('DELETE', "/api/projects/{$projectId}", token: $intruderToken);
        self::assertSame(403, $destroy['status']);
    }

    public function testReviewerCanViewButNotEdit(): void
    {
        $owner = $this->createUser('owner2@example.com');
        $reviewer = $this->createUser('reviewer@example.com', UserRole::REVIEWER);

        $ownerToken = $this->loginAs($owner);
        $reviewerToken = $this->loginAs($reviewer);

        $create = $this->json('POST', '/api/projects', [
            'name' => 'Viewable',
        ], token: $ownerToken);
        $projectId = $create['body']['data']['id'];

        $show = $this->json('GET', "/api/projects/{$projectId}", token: $reviewerToken);
        self::assertSame(200, $show['status']);

        $update = $this->json('PATCH', "/api/projects/{$projectId}", [
            'name' => 'No way',
        ], token: $reviewerToken);
        self::assertSame(403, $update['status']);
    }

    public function testAddRemoveMember(): void
    {
        $owner = $this->createUser('o3@example.com');
        $member = $this->createUser('m3@example.com');

        $ownerToken = $this->loginAs($owner);

        $create = $this->json('POST', '/api/projects', [
            'name' => 'Team',
        ], token: $ownerToken);
        $projectId = $create['body']['data']['id'];

        $add = $this->json('POST', "/api/projects/{$projectId}/members", [
            'user_id' => $member->getId(),
        ], token: $ownerToken);
        self::assertSame(200, $add['status']);
        self::assertContains($member->getId(), $add['body']['data']['member_ids']);

        $remove = $this->json(
            'DELETE',
            "/api/projects/{$projectId}/members/{$member->getId()}",
            token: $ownerToken,
        );
        self::assertSame(204, $remove['status']);
    }
}
