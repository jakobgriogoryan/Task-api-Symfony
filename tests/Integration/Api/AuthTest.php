<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Tests\Integration\AbstractApiTestCase;

final class AuthTest extends AbstractApiTestCase
{
    public function testRegisterCreatesUser(): void
    {
        $res = $this->json('POST', '/api/register', [
            'email' => 'alice@example.com',
            'password' => 'supersecret1',
            'name' => 'Alice',
            'role' => 'member',
        ]);

        self::assertSame(201, $res['status']);
        self::assertTrue($res['body']['success']);
        self::assertSame('alice@example.com', $res['body']['data']['user']['email']);
        self::assertSame('member', $res['body']['data']['user']['role']);
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $this->createUser('dup@example.com');

        $res = $this->json('POST', '/api/register', [
            'email' => 'dup@example.com',
            'password' => 'supersecret1',
            'name' => 'Dup',
            'role' => 'member',
        ]);

        self::assertSame(422, $res['status']);
        self::assertFalse($res['body']['success']);
    }

    public function testRegisterValidatesPayload(): void
    {
        $res = $this->json('POST', '/api/register', [
            'email' => 'not-an-email',
            'password' => 'x',
            'name' => '',
            'role' => 'unknown',
        ]);

        self::assertSame(422, $res['status']);
        self::assertSame('Validation failed.', $res['body']['error']);
        self::assertNotEmpty($res['body']['details']);
    }

    public function testMeReturnsCurrentUserWhenAuthenticated(): void
    {
        $user = $this->createUser('me@example.com');
        $token = $this->loginAs($user);

        $res = $this->json('GET', '/api/me', token: $token);

        self::assertSame(200, $res['status']);
        self::assertSame('me@example.com', $res['body']['data']['user']['email']);
        self::assertArrayHasKey('mercure', $res['body']['data']);
        self::assertArrayHasKey('subscribe_token', $res['body']['data']['mercure']);
    }

    public function testMeRejectsAnonymous(): void
    {
        $res = $this->json('GET', '/api/me');
        self::assertSame(401, $res['status']);
    }
}
