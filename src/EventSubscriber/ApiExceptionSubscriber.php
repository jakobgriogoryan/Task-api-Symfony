<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Converts exceptions raised inside any /api/* route into the standard
 * JSON envelope:
 *
 *   { "success": false, "error": "…", "details"?: … }
 */
class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'kernel.debug')]
        private readonly bool $debug = false,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 0]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with((string) $request->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof ValidationFailedException) {
            $details = [];
            foreach ($throwable->getViolations() as $violation) {
                $details[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }

            $event->setResponse($this->respond(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Validation failed.',
                $details,
            ));

            return;
        }

        if ($throwable instanceof AccessDeniedException) {
            $event->setResponse($this->respond(Response::HTTP_FORBIDDEN, 'Forbidden.'));

            return;
        }

        if ($throwable instanceof AuthenticationException) {
            $event->setResponse($this->respond(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.'));

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $event->setResponse($this->respond(
                $throwable->getStatusCode(),
                $throwable->getMessage() !== '' ? $throwable->getMessage() : Response::$statusTexts[$throwable->getStatusCode()] ?? 'Error',
            ));

            return;
        }

        $this->logger->error('Unhandled API exception.', [
            'exception' => $throwable,
            'path' => $request->getPathInfo(),
        ]);

        $event->setResponse($this->respond(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $this->debug ? $throwable->getMessage() : 'Internal server error.',
        ));
    }

    /**
     * @param list<mixed>|array<string, mixed>|null $details
     */
    private function respond(int $status, string $message, ?array $details = null): JsonResponse
    {
        $payload = ['success' => false, 'error' => $message];
        if ($details !== null) {
            $payload['details'] = $details;
        }

        return new JsonResponse($payload, $status);
    }
}
