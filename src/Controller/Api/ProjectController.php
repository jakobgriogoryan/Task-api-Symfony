<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Concerns\ApiJsonResponse;
use App\Dto\Project\AddMemberRequest;
use App\Dto\Project\ProjectResource;
use App\Dto\Project\SaveProjectRequest;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\Project\ProjectService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/projects', name: 'api_projects_')]
#[OA\Tag(name: 'Projects')]
class ProjectController extends AbstractController
{
    use ApiJsonResponse;

    public function __construct(
        private readonly ProjectService $projects,
        private readonly ProjectRepository $projectRepository,
        private readonly UserRepository $users,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[OA\Get(summary: 'List projects visible to the current user')]
    public function index(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', 15)));

        $seeAll = \in_array($user->getSelectedRole(), [UserRole::ADMIN, UserRole::REVIEWER], true);

        $result = $this->projects->listForUser($user, $page, $perPage, $seeAll);

        return $this->success([
            'items' => ProjectResource::collection($result['items']),
            'meta' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
            ],
        ]);
    }

    #[Route('', name: 'store', methods: ['POST'])]
    #[OA\Post(summary: 'Create a project')]
    public function store(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $dto = SaveProjectRequest::fromArray($payload);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $project = $this->projects->create($user, $dto);

        return $this->created(ProjectResource::from($project));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $project = $this->loadOrFail($id);
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->success(ProjectResource::from($project));
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH', 'PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $project = $this->loadOrFail($id);
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $dto = SaveProjectRequest::fromArray($payload);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $project = $this->projects->update($project, $dto);

        return $this->success(ProjectResource::from($project));
    }

    #[Route('/{id}', name: 'destroy', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function destroy(int $id): JsonResponse
    {
        $project = $this->loadOrFail($id);
        $this->denyAccessUnlessGranted(ProjectVoter::DELETE, $project);

        $this->projects->delete($project);

        return $this->noContent();
    }

    #[Route('/{id}/members', name: 'add_member', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addMember(int $id, Request $request): JsonResponse
    {
        $project = $this->loadOrFail($id);
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_MEMBERS, $project);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $dto = AddMemberRequest::fromArray($payload);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $user = $this->users->find($dto->userId);
        if ($user === null) {
            return $this->error('User not found.', Response::HTTP_NOT_FOUND);
        }

        $this->projects->addMember($project, $user);

        return $this->success(ProjectResource::from($project));
    }

    #[Route(
        '/{id}/members/{userId}',
        name: 'remove_member',
        methods: ['DELETE'],
        requirements: ['id' => '\d+', 'userId' => '\d+'],
    )]
    public function removeMember(int $id, int $userId): JsonResponse
    {
        $project = $this->loadOrFail($id);
        $this->denyAccessUnlessGranted(ProjectVoter::MANAGE_MEMBERS, $project);

        $user = $this->users->find($userId);
        if ($user === null) {
            return $this->error('User not found.', Response::HTTP_NOT_FOUND);
        }

        $this->projects->removeMember($project, $user);

        return $this->noContent();
    }

    private function loadOrFail(int $id): Project
    {
        $project = $this->projectRepository->find($id);
        if ($project === null) {
            throw $this->createNotFoundException('Project not found.');
        }

        return $project;
    }
}
