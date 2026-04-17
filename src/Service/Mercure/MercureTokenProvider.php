<?php

declare(strict_types=1);

namespace App\Service\Mercure;

use App\Entity\User;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Symfony\Component\Mercure\HubInterface;

/**
 * Mints per-user Mercure subscribe JWTs so clients can listen to their own
 * private notification topic. Uses the shared Mercure hub secret directly so
 * it works outside of an HTTP request lifecycle (e.g. CLI workers, tests).
 */
class MercureTokenProvider
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly string $mercureJwtSecret,
    ) {
    }

    /**
     * @return array{hub_url: string, topic: string, subscribe_token: string}
     */
    public function forUser(User $user): array
    {
        $topic = self::userTopic($user);

        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->mercureJwtSecret),
        );

        $token = $config->builder()
            ->withClaim('mercure', ['subscribe' => [$topic]])
            ->expiresAt(new \DateTimeImmutable('+1 hour'))
            ->getToken($config->signer(), $config->signingKey())
            ->toString();

        return [
            'hub_url' => $this->hub->getPublicUrl(),
            'topic' => $topic,
            'subscribe_token' => $token,
        ];
    }

    public static function userTopic(User $user): string
    {
        return sprintf('user/%d/notifications', (int) $user->getId());
    }
}
