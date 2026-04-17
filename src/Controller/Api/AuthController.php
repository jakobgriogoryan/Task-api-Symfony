<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Concerns\ApiJsonResponse;
use App\Dto\Auth\RegisterRequest;
use App\Dto\Auth\UserResource;
use App\Entity\User;
use App\Service\Auth\AuthService;
use App\Service\Mercure\MercureTokenProvider;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
#[OA\Tag(name: 'Auth')]
class AuthController extends AbstractController
{
    use ApiJsonResponse;

    public function __construct(
        private readonly AuthService $authService,
        private readonly MercureTokenProvider $mercureTokenProvider,
    ) {
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    #[OA\Post(
        summary: 'Authenticate and obtain a JWT',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string'),
            ],
        )),
    )]
    #[OA\Response(response: 200, description: 'Returns { token: string }')]
    #[OA\Response(response: 401, description: 'Invalid credentials')]
    public function login(): JsonResponse
    {
        // The firewall (json_login) intercepts this request; this method is
        // only reachable if the firewall is misconfigured. Surfacing the
        // route makes it visible in the API docs and gives the rate limiter
        // a stable name to match.
        return $this->error('Login handled by security firewall.', Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new user',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(
            required: ['email', 'password', 'name', 'role'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', minLength: 8),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'role', type: 'string', enum: ['admin', 'reviewer', 'member']),
            ],
        )),
    )]
    #[OA\Response(response: 201, description: 'Registered')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function register(Request $request, ValidatorInterface $validator): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $dto = RegisterRequest::fromArray($payload);

        $violations = $validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        if ($this->authService->emailExists($dto->email)) {
            return $this->error('Email is already registered.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $role = $dto->selectedRole();
        if ($role === null) {
            return $this->error('Invalid role.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->authService->register($dto->email, $dto->password, $dto->name, $role);

        return $this->created(['user' => UserResource::from($user)]);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[OA\Get(summary: 'Get current authenticated user + Mercure subscribe token')]
    #[OA\Response(response: 200, description: 'OK')]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user === null) {
            return $this->error('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $mercure = $this->mercureTokenProvider->forUser($user);

        return $this->success([
            'user' => UserResource::from($user),
            'mercure' => $mercure,
        ]);
    }
}
