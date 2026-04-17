<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Enforces per-endpoint rate limits for sensitive actions.
 *
 * Keyed on route name → limiter factory. IP address is used for
 * unauthenticated endpoints (login, register); user id for the rest.
 */
class RateLimiterSubscriber implements EventSubscriberInterface
{
    /**
     * @var array<string, array{factory: RateLimiterFactoryInterface, scope: 'ip'|'user'}>
     */
    private array $map;

    public function __construct(
        #[Autowire(service: 'limiter.login')] RateLimiterFactoryInterface $loginLimiter,
        #[Autowire(service: 'limiter.register')] RateLimiterFactoryInterface $registerLimiter,
        #[Autowire(service: 'limiter.comment_create')] RateLimiterFactoryInterface $commentLimiter,
        #[Autowire(service: 'limiter.task_search')] RateLimiterFactoryInterface $searchLimiter,
        private readonly Security $security,
    ) {
        $this->map = [
            'api_login' => ['factory' => $loginLimiter, 'scope' => 'ip'],
            'api_register' => ['factory' => $registerLimiter, 'scope' => 'ip'],
            'api_comments_store' => ['factory' => $commentLimiter, 'scope' => 'user'],
            'api_tasks_search' => ['factory' => $searchLimiter, 'scope' => 'user'],
        ];
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 16 — after Security (8) so user is resolved, before Controller.
        return [KernelEvents::REQUEST => ['onRequest', 16]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $routeName = (string) $request->attributes->get('_route');
        $config = $this->map[$routeName] ?? null;
        if ($config === null) {
            return;
        }

        $limiter = $config['factory']->create($this->identifier($request, $config['scope']));
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            $event->setResponse(new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Too many requests.',
                    'details' => [
                        'retry_after' => $limit->getRetryAfter()->getTimestamp(),
                    ],
                ],
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'X-RateLimit-Limit' => (string) $limit->getLimit(),
                    'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
                    'Retry-After' => (string) max(
                        1,
                        $limit->getRetryAfter()->getTimestamp() - time(),
                    ),
                ],
            ));
        }
    }

    private function identifier(Request $request, string $scope): string
    {
        if ($scope === 'user') {
            $user = $this->security->getUser();
            if ($user instanceof User && $user->getId() !== null) {
                return 'user:'.$user->getId();
            }
        }

        return 'ip:'.($request->getClientIp() ?? 'unknown');
    }
}
