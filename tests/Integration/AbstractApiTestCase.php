<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base test case that boots a KernelBrowser, exposes convenience helpers to
 * create users, authenticate and issue JSON API requests.
 */
abstract class AbstractApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createUser(string $email, UserRole $role = UserRole::MEMBER, string $password = 'secret1234'): User
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = (new User())
            ->setEmail($email)
            ->setName(explode('@', $email)[0])
            ->setSelectedRole($role);
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function loginAs(User $user): string
    {
        $jwt = static::getContainer()
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);

        return $jwt;
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $headers
     */
    protected function json(string $method, string $uri, ?array $body = null, array $headers = [], ?string $token = null): array
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
        foreach ($headers as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: $body === null ? null : json_encode($body, \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();
        $raw = $response->getContent();
        $decoded = $raw === '' || $raw === false ? [] : (json_decode($raw, true) ?? []);

        return [
            'status' => $response->getStatusCode(),
            'body' => $decoded,
            'headers' => $response->headers->all(),
        ];
    }
}
